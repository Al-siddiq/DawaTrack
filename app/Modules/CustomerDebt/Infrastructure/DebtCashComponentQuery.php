<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\CustomerDebt\Domain\DebtAmount;
use App\Modules\Reconciliation\Application\CashAmount;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Read-only Cash collections; reversal links define the negative payment effect. */
final class DebtCashComponentQuery
{
    public function __construct(private readonly BaseConnection $database) {}

    public function forPeriod(ShopContext $context, string $periodStartedAt, string $cutoffAt): array
    {
        (new DebtSchema($this->database))->requireReady();
        $columns = $this->database->query(
            "SELECT table_name AS source_table,column_name AS source_column FROM information_schema.columns WHERE table_schema=DATABASE()
             AND table_name IN ('debt_payments','debt_payment_allocations')",
        )->getResultArray();
        $available = [];
        foreach ($columns as $column) $available[$column['source_table']][$column['source_column']] = true;
        foreach ([
            'debt_payments' => ['id','tenant_id','shop_id','public_id','customer_id','channel','amount','posted_at','correlation_id','reversal_of_debt_payment_id'],
            'debt_payment_allocations' => ['tenant_id','shop_id','debt_payment_id','amount'],
        ] as $table => $required) {
            foreach ($required as $name) {
                if (!isset($available[$table][$name])) {
                    throw new ApiException('EXPECTED_CASH_SETUP_REQUIRED', 'Cash expectation is not ready because Debt payment evidence is incomplete.', 503);
                }
            }
        }
        $crossChannel = $this->database->query(
            "SELECT COUNT(*) invalid_count FROM debt_payments p
             LEFT JOIN debt_payments source ON source.tenant_id=p.tenant_id AND source.shop_id=p.shop_id AND source.id=p.reversal_of_debt_payment_id
             WHERE p.tenant_id=? AND p.shop_id=? AND p.reversal_of_debt_payment_id IS NOT NULL
               AND p.posted_at>? AND p.posted_at<=? AND (p.channel='CASH' OR source.channel='CASH')
               AND (source.id IS NULL OR p.channel<>source.channel)",
            [$context->tenantId, $context->shopId, $periodStartedAt, $cutoffAt],
        )->getRowArray();
        if ((int) $crossChannel['invalid_count'] !== 0) {
            throw new ApiException('EXPECTED_CASH_EVIDENCE_INVALID', 'A Debt payment reversal changes Cash/Transfer channel across its source link.', 409);
        }

        $row = $this->database->query(
            "SELECT
                COALESCE(SUM(CASE WHEN p.reversal_of_debt_payment_id IS NULL THEN p.amount ELSE 0.00 END),0.00) receipts,
                COALESCE(SUM(CASE WHEN p.reversal_of_debt_payment_id IS NOT NULL THEN p.amount ELSE 0.00 END),0.00) reversal_magnitude,
                COALESCE(SUM(CASE WHEN p.reversal_of_debt_payment_id IS NOT NULL THEN 1 ELSE 0 END),0) reversal_count,
                COALESCE(SUM(CASE WHEN p.reversal_of_debt_payment_id IS NULL THEN 1 ELSE 0 END),0) receipt_count,
                COUNT(p.id) source_count,
                COALESCE(SUM(CASE WHEN p.amount<=0
                    OR COALESCE(a.allocated_amount,0.00)<>p.amount
                    OR p.correlation_id IS NULL
                    OR NOT EXISTS (
                        SELECT 1 FROM audit_events ae
                        WHERE ae.tenant_id=p.tenant_id AND ae.shop_id=p.shop_id
                          AND ae.resource_type='debt_payment' AND ae.resource_public_id=p.public_id
                          AND ae.action_code=CASE WHEN p.reversal_of_debt_payment_id IS NULL
                              THEN 'debt_payment.recorded' ELSE 'debt_payment.reversed' END
                          AND ae.correlation_id=p.correlation_id
                    )
                    OR (p.reversal_of_debt_payment_id IS NOT NULL AND (
                        source.id IS NULL OR source.reversal_of_debt_payment_id IS NOT NULL
                        OR source.customer_id<>p.customer_id OR source.channel<>p.channel OR source.amount<>p.amount
                        OR source.correlation_id IS NULL
                        OR COALESCE((SELECT SUM(sa.amount) FROM debt_payment_allocations sa
                            WHERE sa.tenant_id=source.tenant_id AND sa.shop_id=source.shop_id AND sa.debt_payment_id=source.id),0.00)<>source.amount
                        OR NOT EXISTS (SELECT 1 FROM audit_events se WHERE se.tenant_id=source.tenant_id AND se.shop_id=source.shop_id
                            AND se.resource_type='debt_payment' AND se.resource_public_id=source.public_id
                            AND se.action_code='debt_payment.recorded' AND se.correlation_id=source.correlation_id)
                    )) THEN 1 ELSE 0 END),0) invalid_count
             FROM debt_payments p
             LEFT JOIN debt_payments source
               ON source.tenant_id=p.tenant_id AND source.shop_id=p.shop_id AND source.id=p.reversal_of_debt_payment_id
             LEFT JOIN (
                SELECT tenant_id,shop_id,debt_payment_id,SUM(amount) allocated_amount
                FROM debt_payment_allocations GROUP BY tenant_id,shop_id,debt_payment_id
             ) a ON a.tenant_id=p.tenant_id AND a.shop_id=p.shop_id AND a.debt_payment_id=p.id
             WHERE p.tenant_id=? AND p.shop_id=? AND p.channel='CASH'
               AND p.posted_at>? AND p.posted_at<=?",
            [$context->tenantId, $context->shopId, $periodStartedAt, $cutoffAt],
        )->getRowArray();

        if ((int) $row['invalid_count'] !== 0) {
            throw new ApiException('EXPECTED_CASH_EVIDENCE_INVALID', 'A Cash debt payment or its linked reversal has incomplete evidence. Review the payment history before continuing.', 409);
        }

        $magnitude = DebtAmount::sum([(string) $row['reversal_magnitude']]);
        return [
            'cash_debt_receipts' => CashAmount::parse((string) $row['receipts']),
            'cash_debt_reversal_net' => $magnitude === '0.00' ? '0.00' : '-' . $magnitude,
            'reversal_count' => (int) $row['reversal_count'],
            'receipt_count' => (int) $row['receipt_count'],
            'source_count' => (int) $row['source_count'],
        ];
    }
}
