<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\CustomerDebt\Domain\DebtAmount;
use App\Modules\CustomerDebt\Application\DebtPaymentInput;
use App\Modules\Sales\Application\{CommercialAccess, DraftInput};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class DebtPaymentQuery
{
    public function __construct(private readonly BaseConnection $database, private readonly CommercialAccess $access) {}

    public function show(ShopContext $context, string $id): array
    {
        $this->authorize($context);
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $id): array {
            $repository = new DebtPaymentRepository($this->database);
            $row = $repository->payment($context, DraftInput::uuid($id));
            if (!$row) throw new ApiException('DEBT_PAYMENT_NOT_FOUND', 'This payment is not saved yet. Check again before recording another payment.', 404);
            $evidence = $repository->recordedEvidence($context, $row['public_id']);
            $allocations = $repository->allocations($context, (int) $row['id']);
            $signature = DebtPaymentInput::fingerprint(array_map(static fn(array $a): array => [
                'receivable_public_id' => $a['receivable_public_id'], 'amount' => (string) $a['amount'], 'allocation_order' => (int) $a['allocation_order'],
            ], $allocations));
            if (!hash_equals((string) ($evidence['metadata']['allocation_fingerprint'] ?? ''), $signature)
                || DebtAmount::compare(DebtAmount::sum(array_column($allocations, 'amount')), (string) $row['amount']) !== 0) {
                throw new ApiException('DEBT_EVIDENCE_INVALID', 'This payment needs checking. Ask the shop owner for help.', 409);
            }
            $bills = $repository->balances($context, (int) $row['customer_id']);
            return $this->header($row) + [
                'customer_public_id' => $row['customer_public_id'], 'customer_name' => $row['customer_name'],
                'correction_reason' => ($row['reversal_of_public_id'] !== null || isset($evidence['metadata']['corrects_public_id'])) && $this->access->allows($context, 'debt.payment.reverse') ? $evidence['reason'] : null,
                'replacement_public_id' => $evidence['metadata']['replacement_public_id'] ?? null,
                'corrects_public_id' => $evidence['metadata']['corrects_public_id'] ?? null,
                'inverse_public_id' => $evidence['metadata']['inverse_public_id'] ?? null,
                'shop_name' => $row['shop_name'], 'recorded_by' => $row['recorded_by'] ?: 'Shop user',
                'allocations' => array_map(static fn(array $a): array => [
                    'receivable_public_id' => $a['receivable_public_id'], 'sale_public_id' => $a['sale_public_id'],
                    'amount' => (string) $a['amount'], 'allocation_order' => (int) $a['allocation_order'],
                ], $allocations),
                'customer_balance_before' => (string) $evidence['metadata']['customer_balance_before'],
                'customer_balance_after' => (string) $evidence['metadata']['customer_balance_after'],
                'current_customer_balance' => DebtAmount::sum(array_column($bills, 'outstanding_amount')),
            ];
        });
    }

    public function history(ShopContext $context, string $customerId, array $input): array
    {
        $this->authorize($context);
        DraftInput::only($input, ['page', 'per_page']);
        $page = $this->integer($input['page'] ?? 1, 100000);
        $size = $this->integer($input['per_page'] ?? 20, 50);
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $customerId, $page, $size): array {
            $customer = (new DebtPaymentRepository($this->database))->customer($context, DraftInput::uuid($customerId));
            $binds = [$context->tenantId, $context->shopId, $customer['id']];
            $where = 'p.tenant_id=? AND p.shop_id=? AND p.customer_id=?';
            $total = (int) $this->database->query("SELECT COUNT(*) total FROM debt_payments p WHERE $where", $binds)->getRowArray()['total'];
            $rows = $this->database->query(
                "SELECT p.*,inverse.public_id reversed_by_public_id,original.public_id reversal_of_public_id FROM debt_payments p
                 LEFT JOIN debt_payments inverse ON inverse.tenant_id=p.tenant_id AND inverse.shop_id=p.shop_id AND inverse.reversal_of_debt_payment_id=p.id
                 LEFT JOIN debt_payments original ON original.tenant_id=p.tenant_id AND original.shop_id=p.shop_id AND original.id=p.reversal_of_debt_payment_id
                 WHERE $where ORDER BY p.posted_at DESC,p.id DESC LIMIT ? OFFSET ?", [...$binds, $size, ($page - 1) * $size],
            )->getResultArray();
            return ['customer' => ['public_id' => $customer['public_id'], 'display_name' => $customer['display_name']],
                'items' => array_map(fn(array $row): array => $this->header($row), $rows),
                'pagination' => ['page' => $page, 'per_page' => $size, 'total' => $total, 'total_pages' => (int) ceil($total / $size)]];
        });
    }

    private function header(array $row): array
    {
        return ['public_id' => $row['public_id'], 'channel' => $row['channel'], 'amount' => (string) $row['amount'],
            'external_reference' => $row['external_reference'], 'occurred_at' => $row['occurred_at'], 'posted_at' => $row['posted_at'],
            'reversed_by_public_id' => $row['reversed_by_public_id'], 'reversal_of_public_id' => $row['reversal_of_public_id']];
    }

    private function integer(mixed $input, int $max): int
    {
        if ((!is_string($input) && !is_int($input)) || filter_var($input, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => $max]]) === false) {
            throw new ApiException('VALIDATION_FAILED', 'Choose a valid page and number of payments.', 422);
        }
        return (int) $input;
    }

    private function authorize(ShopContext $context): void
    {
        $this->access->read($context, 'customer.view');
        $this->access->read($context, 'debt.view');
        (new DebtSchema($this->database))->requireReady();
    }
}
