<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Application;

use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory, AuditOccurrence};
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;

/** Append only; caller owns authorization, cash guard, ordered locks and final publication. */
final class DebtPaymentPostingParticipant
{
    public function __construct(private readonly BaseConnection $database, private readonly AuditService $audit, private readonly RequestContext $request) {}

    public function append(ShopContext $context, array $customer, array $command, array $review,
        ?int $reversal = null, string $action = 'debt_payment.recorded', ?string $commandFingerprint = null, array $links = []): array
    {
        if ($this->database->transDepth < 1) throw new \LogicException('An owning transaction is required.');
        $now = UtcTimestamp::now();
        $this->database->table('debt_payments')->insert([
            'public_id' => $command['public_id'], 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
            'customer_id' => $customer['id'], 'channel' => $command['channel'], 'amount' => $command['amount'],
            'external_reference' => $command['external_reference'], 'occurred_at' => $command['occurred_at'],
            'posted_at' => $now, 'created_by_user_id' => $context->userId, 'reversal_of_debt_payment_id' => $reversal,
            'correlation_id' => $this->request->requestId(), 'created_at' => $now,
        ]);
        $paymentId = (int) $this->database->insertID();
        $actor = AuditActor::tenantUser($context->userId, $context->membershipId, $context->tenantId, $context->shopId);
        foreach ($review['allocations'] as $index => $allocation) {
            $bill = $this->database->table('receivables')->select('id')->where([
                'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'customer_id' => $customer['id'],
                'public_id' => $allocation['receivable_public_id'],
            ])->get()->getRowArray();
            if (!$bill) throw new ApiException('DEBT_REVIEW_STALE', 'A selected bill is no longer available.', 409);
            $this->database->table('debt_payment_allocations')->insert([
                'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'debt_payment_id' => $paymentId,
                'receivable_id' => $bill['id'], 'amount' => $allocation['amount'], 'allocation_order' => $index + 1, 'created_at' => $now,
            ]);
        }
        // The planner proves the sum; recheck the persisted set before any commit.
        $sum = $this->database->query(
            'SELECT COALESCE(SUM(amount),0.00) amount FROM debt_payment_allocations WHERE tenant_id=? AND shop_id=? AND debt_payment_id=?',
            [$context->tenantId, $context->shopId, $paymentId],
        )->getRowArray()['amount'];
        if ((string) $sum !== $command['amount']) throw new \RuntimeException('Stored allocation sum differs from payment.');
        $parent = $this->audit->append(new AuditAppendCommand(
            $actor, $action, AuditCategory::DEBT, 'debt_payment', $command['public_id'], $command['reason'],
            ['amount' => $command['amount'], 'channel' => $command['channel'], 'allocation_mode' => $command['allocation_mode'],
                'allocation_count' => count($review['allocations']),
                'allocation_fingerprint' => DebtPaymentInput::fingerprint(array_map(static fn(array $a, int $i): array => [
                    'receivable_public_id' => $a['receivable_public_id'], 'amount' => $a['amount'], 'allocation_order' => $i + 1,
                ], $review['allocations'], array_keys($review['allocations']))), 'command_fingerprint' => $commandFingerprint ?? DebtPaymentInput::fingerprint($command),
                'customer_balance_before' => $review['customer_balance_before'], 'customer_balance_after' => $review['customer_balance_after']] + $links,
            occurrence: AuditOccurrence::supplied($command['occurred_at']),
        ));
        $commands = [new ChangeCommand($context->tenantId, $context->shopId, 'debt_payment', $command['public_id']),
            new ChangeCommand($context->tenantId, null, 'customer', $command['customer_public_id'])];
        foreach ($review['allocations'] as $index => $allocation) {
            $this->audit->append(new AuditAppendCommand(
                $actor, 'debt_payment.allocated', AuditCategory::DEBT, 'debt_payment', $command['public_id'], $command['reason'],
                $allocation + ['allocation_order' => $index + 1], $parent, AuditOccurrence::supplied($command['occurred_at']),
            ));
            $commands[] = new ChangeCommand($context->tenantId, $context->shopId, 'receivable', $allocation['receivable_public_id']);
        }
        return $commands;
    }
}
