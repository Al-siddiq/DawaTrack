<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Internal scope-bound evidence access. Owning command establishes authorization and transaction. */
final class DebtPaymentRepository
{
    public function __construct(private readonly BaseConnection $database) {}

    public function customer(ShopContext $context, string $publicId, bool $lock = false): array
    {
        $row = $this->database->query(
            'SELECT id,public_id,display_name,status FROM customers WHERE tenant_id=? AND public_id=?' . ($lock ? ' FOR UPDATE' : ''),
            [$context->tenantId, $publicId],
        )->getRowArray();
        if (!$row) throw new ApiException('RESOURCE_NOT_FOUND', 'This customer was not found.', 404);
        return $row;
    }

    public function balances(ShopContext $context, int $customerId, bool $lock = false): array
    {
        if ($lock) {
            if ($this->database->transDepth < 1) throw new \LogicException('Balance locks require an owning transaction.');
            $this->database->query(
                'SELECT id FROM receivables WHERE tenant_id=? AND shop_id=? AND customer_id=? ORDER BY posted_at,id FOR UPDATE',
                [$context->tenantId, $context->shopId, $customerId],
            )->getResultArray();
        }
        $shop = $this->database->table('shops')->select('timezone_name')
            ->where(['tenant_id' => $context->tenantId, 'id' => $context->shopId])->get()->getRowArray();
        if (!$shop) throw new ApiException('RESOURCE_NOT_FOUND', 'This shop was not found.', 404);
        $date = (new \DateTimeImmutable('now', new \DateTimeZone($shop['timezone_name'])))->format('Y-m-d');
        [$sql, $binds] = (new ReceivableBalanceQuery())->projection($context, $date);
        $rows = $this->database->query(
            "SELECT b.* FROM ($sql) b WHERE b.customer_id=? ORDER BY b.posted_at,b.id", [...$binds, $customerId],
        )->getResultArray();
        foreach ($rows as &$row) {
            if ((int) $row['invalid_balance'] > 0) throw new ApiException('DEBT_EVIDENCE_INVALID', 'These balances need checking. Ask the shop owner for help.', 409);
            foreach (['outstanding_amount', 'adjustment_delta', 'adjusted_amount', 'collected_amount', 'original_amount'] as $key) $row[$key] = (string) $row[$key];
        }
        unset($row);
        return $rows;
    }

    public function payment(ShopContext $context, string $id): ?array
    {
        return $this->database->query(
            'SELECT p.*,c.public_id customer_public_id,c.display_name customer_name,s.name shop_name,u.username recorded_by,
             original.public_id reversal_of_public_id,inverse.public_id reversed_by_public_id
             FROM debt_payments p
             JOIN customers c ON c.tenant_id=p.tenant_id AND c.id=p.customer_id
             JOIN shops s ON s.tenant_id=p.tenant_id AND s.id=p.shop_id
             LEFT JOIN users u ON u.id=p.created_by_user_id
             LEFT JOIN debt_payments original ON original.tenant_id=p.tenant_id AND original.shop_id=p.shop_id AND original.id=p.reversal_of_debt_payment_id
             LEFT JOIN debt_payments inverse ON inverse.tenant_id=p.tenant_id AND inverse.shop_id=p.shop_id AND inverse.reversal_of_debt_payment_id=p.id
             WHERE p.tenant_id=? AND p.shop_id=? AND p.public_id=?',
            [$context->tenantId, $context->shopId, $id],
        )->getRowArray();
    }

    public function allocations(ShopContext $context, int $paymentId): array
    {
        return $this->database->query(
            'SELECT r.public_id receivable_public_id,s.public_id sale_public_id,a.amount,a.allocation_order
             FROM debt_payment_allocations a
             JOIN receivables r ON r.tenant_id=a.tenant_id AND r.shop_id=a.shop_id AND r.id=a.receivable_id
             JOIN sales s ON s.tenant_id=r.tenant_id AND s.shop_id=r.shop_id AND s.id=r.sale_id
             WHERE a.tenant_id=? AND a.shop_id=? AND a.debt_payment_id=? ORDER BY a.allocation_order,a.id',
            [$context->tenantId, $context->shopId, $paymentId],
        )->getResultArray();
    }

    /** Safe public allocation DTO shared by receipts and statement rows. */
    public function verifiedAllocations(ShopContext $context, array $payment, array $metadata): array
    {
        $rows = $this->allocations($context, (int) $payment['id']);
        $signature = \App\Modules\CustomerDebt\Application\DebtPaymentInput::fingerprint(array_map(static fn(array $a): array => [
            'receivable_public_id' => $a['receivable_public_id'], 'amount' => (string) $a['amount'], 'allocation_order' => (int) $a['allocation_order'],
        ], $rows));
        if (!hash_equals((string) ($metadata['allocation_fingerprint'] ?? ''), $signature)
            || \App\Modules\CustomerDebt\Domain\DebtAmount::compare(\App\Modules\CustomerDebt\Domain\DebtAmount::sum(array_column($rows, 'amount')), (string) $payment['amount']) !== 0) {
            throw new ApiException('DEBT_EVIDENCE_INVALID', 'This payment needs checking. Ask the shop owner for help.', 409);
        }
        return array_map(static fn(array $a): array => [
            'receivable_public_id' => $a['receivable_public_id'], 'sale_public_id' => $a['sale_public_id'],
            'amount' => (string) $a['amount'], 'allocation_order' => (int) $a['allocation_order'],
        ], $rows);
    }

    public function recordedEvidence(ShopContext $context, string $id): array
    {
        $audit = $this->database->table('audit_events')->select('change_metadata,reason')
            ->where(['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                'resource_type' => 'debt_payment', 'resource_public_id' => $id])
            ->whereIn('action_code', ['debt_payment.recorded', 'debt_payment.reversed'])->orderBy('id')->get(1)->getRowArray();
        $metadata = $audit ? json_decode($audit['change_metadata'] ?? '', true, 512, JSON_THROW_ON_ERROR) : null;
        if (!is_array($metadata) || !isset($metadata['command_fingerprint'], $metadata['customer_balance_before'], $metadata['customer_balance_after'])) {
            throw new ApiException('DEBT_EVIDENCE_INVALID', 'This payment needs checking. Ask the shop owner for help.', 409);
        }
        return ['metadata' => $metadata, 'reason' => $audit['reason']];
    }
}
