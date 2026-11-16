<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Reconciliation\Application\CashAmount;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Read-only CASH payment components. Sale payment direction owns receipt/refund semantics. */
final class SaleCashComponentQuery
{
    public function __construct(private readonly BaseConnection $database) {}

    public function forPeriod(ShopContext $context, string $periodStartedAt, string $cutoffAt): array
    {
        $this->requireReady();
        $crossChannel = $this->database->query(
            "SELECT COUNT(*) invalid_count FROM sale_payments p
             LEFT JOIN sale_payments source ON source.tenant_id=p.tenant_id AND source.shop_id=p.shop_id AND source.id=p.reversal_of_payment_id
             WHERE p.tenant_id=? AND p.shop_id=? AND p.reversal_of_payment_id IS NOT NULL
               AND p.posted_at>? AND p.posted_at<=? AND (p.channel='CASH' OR source.channel='CASH')
               AND (source.id IS NULL OR p.channel<>source.channel)",
            [$context->tenantId, $context->shopId, $periodStartedAt, $cutoffAt],
        )->getRowArray();
        if ((int) $crossChannel['invalid_count'] !== 0) {
            throw new ApiException('EXPECTED_CASH_EVIDENCE_INVALID', 'A Sale payment reversal changes Cash/Transfer channel across its source link.', 409);
        }

        $row = $this->database->query(
            "SELECT
                COALESCE(SUM(CASE WHEN p.direction='RECEIPT' THEN p.amount ELSE 0.00 END),0.00) receipts,
                COALESCE(SUM(CASE WHEN p.direction='REFUND' THEN p.amount ELSE 0.00 END),0.00) refunds,
                COALESCE(SUM(CASE WHEN p.direction='RECEIPT' THEN 1 ELSE 0 END),0) receipt_count,
                COALESCE(SUM(CASE WHEN p.direction='REFUND' THEN 1 ELSE 0 END),0) refund_count,
                COALESCE(SUM(CASE WHEN s.id IS NULL OR s.status<>'POSTED' OR s.kind NOT IN ('SALE','RETURN')
                    OR p.direction NOT IN ('RECEIPT','REFUND') OR p.amount<=0
                    OR p.correlation_id IS NULL OR p.correlation_id<>s.correlation_id
                    OR NOT EXISTS (SELECT 1 FROM audit_events ae WHERE ae.tenant_id=s.tenant_id AND ae.shop_id=s.shop_id
                        AND ae.resource_type='sale' AND ae.resource_public_id=s.public_id
                        AND ae.action_code=CASE WHEN s.reversal_of_sale_id IS NOT NULL THEN 'sale.reversed'
                            WHEN s.kind='RETURN' THEN 'sale.return_posted' ELSE 'sale.posted' END
                        AND ae.correlation_id=s.correlation_id)
                    OR (p.reversal_of_payment_id IS NOT NULL AND (original.id IS NULL
                        OR original.reversal_of_payment_id IS NOT NULL OR original.channel<>p.channel
                        OR original.amount<>p.amount OR original.direction=p.direction OR original_sale.id IS NULL
                        OR original_sale.status<>'POSTED' OR original.correlation_id IS NULL
                        OR original.correlation_id<>original_sale.correlation_id
                        OR NOT EXISTS (SELECT 1 FROM audit_events source_audit WHERE source_audit.tenant_id=original_sale.tenant_id
                            AND source_audit.shop_id=original_sale.shop_id AND source_audit.resource_type='sale'
                            AND source_audit.resource_public_id=original_sale.public_id
                            AND source_audit.action_code=CASE WHEN original_sale.reversal_of_sale_id IS NOT NULL THEN 'sale.reversed'
                                WHEN original_sale.kind='RETURN' THEN 'sale.return_posted' ELSE 'sale.posted' END
                            AND source_audit.correlation_id=original_sale.correlation_id))) THEN 1 ELSE 0 END),0) invalid_count,
                COUNT(p.id) source_count
             FROM sale_payments p
             LEFT JOIN sales s ON s.tenant_id=p.tenant_id AND s.shop_id=p.shop_id AND s.id=p.sale_id
             LEFT JOIN sale_payments original ON original.tenant_id=p.tenant_id AND original.shop_id=p.shop_id AND original.id=p.reversal_of_payment_id
             LEFT JOIN sales original_sale ON original_sale.tenant_id=original.tenant_id AND original_sale.shop_id=original.shop_id AND original_sale.id=original.sale_id
             WHERE p.tenant_id=? AND p.shop_id=? AND p.channel='CASH'
               AND p.posted_at>? AND p.posted_at<=?",
            [$context->tenantId, $context->shopId, $periodStartedAt, $cutoffAt],
        )->getRowArray();

        if ((int) $row['invalid_count'] !== 0) {
            throw new ApiException('EXPECTED_CASH_EVIDENCE_INVALID', 'A Cash sale payment has incomplete or invalid posted evidence. Review the linked Sale before continuing.', 409);
        }

        return [
            'cash_sale_receipts' => CashAmount::parse((string) $row['receipts']),
            'cash_sale_refunds' => CashAmount::parse((string) $row['refunds']),
            'receipt_count' => (int) $row['receipt_count'],
            'refund_count' => (int) $row['refund_count'],
            'source_count' => (int) $row['source_count'],
        ];
    }

    private function requireReady(): void
    {
        $rows = $this->database->query(
            "SELECT table_name AS source_table,column_name AS source_column FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name IN ('sales','sale_payments')",
        )->getResultArray();
        $columns = [];
        foreach ($rows as $row) $columns[$row['source_table']][$row['source_column']] = true;
        $required = [
            'sales' => ['id', 'tenant_id', 'shop_id', 'public_id', 'kind', 'status', 'reversal_of_sale_id', 'correlation_id'],
            'sale_payments' => ['id', 'tenant_id', 'shop_id', 'sale_id', 'direction', 'channel', 'amount', 'posted_at', 'reversal_of_payment_id', 'correlation_id'],
        ];
        foreach ($required as $table => $names) {
            foreach ($names as $name) {
                if (!isset($columns[$table][$name])) {
                    throw new ApiException('EXPECTED_CASH_SETUP_REQUIRED', 'Cash expectation is not ready because Sales payment evidence is incomplete.', 503);
                }
            }
        }
    }
}
