<?php

declare(strict_types=1);
namespace App\Modules\CustomerDebt\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Effective receipts attributable to one original Sale, less effective refunds, by channel. */
final class DebtSettlementQuery
{
    public function __construct(private readonly BaseConnection $database) {}

    public function capacity(ShopContext $context, int $sale): array
    {
        (new DebtSchema($this->database))->requireReady();
        $out = [];
        foreach (['CASH', 'TRANSFER'] as $channel) {
            $sql = <<<'SQL'
SELECT
 (SELECT COALESCE(SUM(amount),0.00) FROM sale_payments
  WHERE tenant_id=? AND shop_id=? AND sale_id=? AND direction='RECEIPT' AND channel=?)
 + (SELECT COALESCE(SUM(CASE WHEN p.reversal_of_debt_payment_id IS NULL THEN a.amount ELSE -a.amount END),0.00)
    FROM debt_payment_allocations a
    JOIN debt_payments p ON p.tenant_id=a.tenant_id AND p.shop_id=a.shop_id AND p.id=a.debt_payment_id
    JOIN receivables b ON b.tenant_id=a.tenant_id AND b.shop_id=a.shop_id AND b.id=a.receivable_id AND b.customer_id=p.customer_id
    WHERE a.tenant_id=? AND a.shop_id=? AND b.sale_id=? AND p.channel=?)
 - (SELECT COALESCE(SUM(p.amount),0.00) FROM sale_payments p
    JOIN sales r ON r.tenant_id=p.tenant_id AND r.shop_id=p.shop_id AND r.id=p.sale_id
    WHERE r.tenant_id=? AND r.shop_id=? AND r.original_sale_id=? AND r.kind='RETURN' AND r.status='POSTED'
      AND r.reversal_of_sale_id IS NULL AND p.direction='REFUND' AND p.channel=?
      AND NOT EXISTS(SELECT 1 FROM sales x WHERE x.tenant_id=r.tenant_id AND x.shop_id=r.shop_id
                     AND x.reversal_of_sale_id=r.id AND x.status='POSTED')) value
SQL;
            $bind = [$context->tenantId, $context->shopId, $sale, $channel];
            $value = (string) $this->database->query($sql, [...$bind, ...$bind, ...$bind])->getRowArray()['value'];
            if (str_starts_with($value, '-')) throw new ApiException('DEBT_EVIDENCE_INVALID', 'Refunds exceed the payments available for this sale. Ask the shop owner to check its linked records.', 409);
            $out[$channel] = $value;
        }
        return $out;
    }
}
