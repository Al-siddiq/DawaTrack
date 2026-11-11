<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Infrastructure;

use App\Modules\TenantShop\Infrastructure\ShopContext;

/** Shared scoped SQL projection. Every signed row is counted once; DECIMAL never becomes float. */
final class ReceivableBalanceQuery
{
    /** @return array{0:string,1:array} Caller checks schema and read authority/transaction first. */
    public function projection(ShopContext $context, string $shopDate): array
    {
        $sql = <<<'SQL'
WITH adjustment_totals AS (
    SELECT receivable_id,SUM(amount_delta) adjustment_delta
    FROM receivable_adjustments WHERE tenant_id=? AND shop_id=? GROUP BY receivable_id
), allocation_totals AS (
    SELECT a.receivable_id,
           SUM(CASE WHEN p.reversal_of_debt_payment_id IS NULL THEN a.amount ELSE -a.amount END) collected_amount,
           SUM(CASE WHEN p.customer_id<>r.customer_id THEN 1 ELSE 0 END) invalid_allocations
    FROM debt_payment_allocations a
    JOIN debt_payments p ON p.tenant_id=a.tenant_id AND p.shop_id=a.shop_id AND p.id=a.debt_payment_id
    JOIN receivables r ON r.tenant_id=a.tenant_id AND r.shop_id=a.shop_id AND r.id=a.receivable_id
    WHERE a.tenant_id=? AND a.shop_id=? GROUP BY a.receivable_id
), amounts AS (
    SELECT r.id,r.public_id,r.customer_id,r.sale_id,r.due_date,r.occurred_at,r.posted_at,r.original_amount,
           COALESCE(j.adjustment_delta,0.00) adjustment_delta,
           r.original_amount+COALESCE(j.adjustment_delta,0.00) adjusted_amount,
           COALESCE(a.collected_amount,0.00) collected_amount,
           r.original_amount+COALESCE(j.adjustment_delta,0.00)-COALESCE(a.collected_amount,0.00) outstanding_amount,
           COALESCE(a.invalid_allocations,0) invalid_allocations
    FROM receivables r
    JOIN sales s ON s.tenant_id=r.tenant_id AND s.shop_id=r.shop_id AND s.id=r.sale_id AND s.status='POSTED' AND s.kind='SALE'
    LEFT JOIN adjustment_totals j ON j.receivable_id=r.id
    LEFT JOIN allocation_totals a ON a.receivable_id=r.id
    WHERE r.tenant_id=? AND r.shop_id=?
)
SELECT amounts.*,
       CASE WHEN outstanding_amount=0 THEN 'SETTLED'
            WHEN collected_amount>0 THEN 'PARTIAL' ELSE 'OUTSTANDING' END settlement_state,
       CASE WHEN outstanding_amount>0 AND due_date<? THEN 1 ELSE 0 END is_overdue,
       CASE WHEN adjusted_amount<0 OR collected_amount<0 OR outstanding_amount<0 OR invalid_allocations>0 THEN 1 ELSE 0 END invalid_balance
FROM amounts
SQL;
        return [$sql, [
            $context->tenantId, $context->shopId,
            $context->tenantId, $context->shopId,
            $context->tenantId, $context->shopId,
            $shopDate,
        ]];
    }
}
