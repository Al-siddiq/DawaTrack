<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Application;

use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory, AuditOccurrence};
use App\Modules\CustomerDebt\Domain\DebtAllocationPlanner;
use App\Modules\CustomerDebt\Infrastructure\{DebtPaymentRepository, DebtSchema};
use App\Modules\Reconciliation\Application\CashControlGuard;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;

final class DebtPaymentService
{
    private readonly DebtPaymentRepository $repository;

    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
        private readonly AuditService $audit,
        private readonly ChangeSequenceService $changes,
        private readonly RequestContext $request,
    ) {
        $this->repository = new DebtPaymentRepository($database);
    }

    public function preview(ShopContext $context, array $input): array
    {
        $command = DebtPaymentInput::normalize($input);
        return $this->access->mutate($context, $this->permissions($command), function () use ($context, $command): array {
            (new DebtSchema($this->database))->requireReady();
            if ($this->repository->payment($context, $command['public_id'])) {
                $this->assertReplay($context, $command);
                return ['already_saved' => true, 'public_id' => $command['public_id']];
            }
            return $this->review($context, $command);
        });
    }

    public function post(ShopContext $context, array $input): string
    {
        $command = DebtPaymentInput::normalize($input, true);
        return $this->access->mutate($context, $this->permissions($command), function () use ($context, $command, $input): string {
            (new DebtSchema($this->database))->requireReady();
            if ($this->repository->payment($context, $command['public_id'])) {
                $this->assertReplay($context, $command);
                return $command['public_id'];
            }
            $review = $this->review($context, $command);
            if (!hash_equals($review['review_fingerprint'], $input['review_fingerprint'])) {
                throw new ApiException('DEBT_REVIEW_STALE', 'The bills changed after your review. Refresh and review this payment again.', 409);
            }
            $customer = $this->repository->customer($context, $command['customer_public_id']);
            $now = UtcTimestamp::now();
            $this->database->table('debt_payments')->insert([
                'public_id' => $command['public_id'], 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                'customer_id' => $customer['id'], 'channel' => $command['channel'], 'amount' => $command['amount'],
                'external_reference' => $command['external_reference'], 'occurred_at' => $command['occurred_at'],
                'posted_at' => $now, 'created_by_user_id' => $context->userId, 'reversal_of_debt_payment_id' => null,
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
                $actor, 'debt_payment.recorded', AuditCategory::DEBT, 'debt_payment', $command['public_id'], $command['reason'],
                ['amount' => $command['amount'], 'channel' => $command['channel'], 'allocation_mode' => $command['allocation_mode'],
                    'allocation_count' => count($review['allocations']),
                    'allocation_fingerprint' => DebtPaymentInput::fingerprint(array_map(static fn(array $a, int $i): array => [
                        'receivable_public_id' => $a['receivable_public_id'], 'amount' => $a['amount'], 'allocation_order' => $i + 1,
                    ], $review['allocations'], array_keys($review['allocations']))), 'command_fingerprint' => DebtPaymentInput::fingerprint($command),
                    'customer_balance_before' => $review['customer_balance_before'], 'customer_balance_after' => $review['customer_balance_after']],
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
            // Acquires generation last. No further domain reads/locks/writes after publication.
            $this->changes->publish($commands);
            return $command['public_id'];
        });
    }

    private function review(ShopContext $context, array $command): array
    {
        AuditOccurrence::supplied($command['occurred_at']);
        if ($command['channel'] === 'CASH') (new CashControlGuard($this->database))->assertCanAffectCash($context);
        $customer = $this->repository->customer($context, $command['customer_public_id'], true);
        // Inactive identity may still repay a legitimate existing debt.
        $bills = $this->repository->balances($context, (int) $customer['id'], true);
        $plan = (new DebtAllocationPlanner())->plan($bills, $command['amount'], $command['allocation_mode'], $command['allocations']);
        $allocations = array_map(static function (array $allocation): array {
            unset($allocation['receivable_id']);
            return $allocation;
        }, $plan['allocations']);
        $state = array_map(static fn(array $bill): array => [
            $bill['public_id'], $bill['adjustment_delta'], $bill['collected_amount'], $bill['outstanding_amount'], $bill['due_date'],
        ], $bills);
        return [
            'already_saved' => false, 'public_id' => $command['public_id'], 'customer_name' => $customer['display_name'],
            'command' => $command, 'allocations' => $allocations,
            'customer_balance_before' => $plan['customer_balance_before'], 'customer_balance_after' => $plan['customer_balance_after'],
            'review_fingerprint' => DebtPaymentInput::fingerprint([$command, $state]),
        ];
    }

    private function assertReplay(ShopContext $context, array $command): void
    {
        $payment = $this->repository->payment($context, $command['public_id']);
        if ($payment['reversal_of_debt_payment_id'] !== null) throw new ApiException('DEBT_IDENTITY_CONFLICT', 'This reference belongs to a different payment action.', 409);
        $recorded = $this->repository->recordedEvidence($context, $command['public_id']);
        $allocations = array_map(static fn(array $a): array => [
            'receivable_public_id' => $a['receivable_public_id'], 'amount' => (string) $a['amount'], 'allocation_order' => (int) $a['allocation_order'],
        ], $this->repository->allocations($context, (int) $payment['id']));
        foreach (['customer_public_id', 'channel', 'amount', 'external_reference', 'occurred_at'] as $key) {
            if ($payment[$key] !== $command[$key]) throw new ApiException('DEBT_IDENTITY_CONFLICT', 'This payment reference was already saved with different details.', 409);
        }
        if (!hash_equals((string) ($recorded['metadata']['allocation_fingerprint'] ?? ''), DebtPaymentInput::fingerprint($allocations))) {
            throw new ApiException('DEBT_EVIDENCE_INVALID', 'This payment needs checking. Ask the shop owner for help.', 409);
        }
        if (!hash_equals((string) $recorded['metadata']['command_fingerprint'], DebtPaymentInput::fingerprint($command))) {
            throw new ApiException('DEBT_IDENTITY_CONFLICT', 'This payment reference was already used for different details. Check the saved payment.', 409);
        }
    }

    private function permissions(array $command): array
    {
        $permissions = ['customer.view', 'debt.view', 'debt.payment.record'];
        if ($command['allocation_mode'] === 'EXPLICIT') $permissions[] = 'debt.payment.allocate';
        return $permissions;
    }
}
