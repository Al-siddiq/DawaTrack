<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Infrastructure;

use App\Modules\TenantShop\Infrastructure\ShopContext;

/** Read model only: every debt effect once, with zero-debt Returns retained as context. */
final class StatementEventQuery
{
    public function projection(ShopContext $context, int $customerId): array
    {
        $sql = <<<'SQL'
WITH bills AS (
    SELECT b.*,s.public_id sale_public_id FROM receivables b
    JOIN sales s ON s.tenant_id=b.tenant_id AND s.shop_id=b.shop_id AND s.id=b.sale_id AND s.kind='SALE' AND s.status='POSTED'
    WHERE b.tenant_id=? AND b.shop_id=? AND b.customer_id=?
), commercial_changes AS (
    SELECT s.id,s.public_id,s.kind,s.reversal_of_sale_id,s.occurred_at,s.posted_at,
           b.id bill_id,b.public_id bill_public_id,b.sale_public_id,
           original.public_id original_public_id,original.kind original_kind,
           COALESCE(SUM(a.amount_delta),0.00) amount_delta
    FROM sales s JOIN bills b ON b.tenant_id=s.tenant_id AND b.shop_id=s.shop_id AND b.sale_id=s.original_sale_id
    LEFT JOIN sales original ON original.tenant_id=s.tenant_id AND original.shop_id=s.shop_id AND original.id=s.reversal_of_sale_id
    LEFT JOIN receivable_adjustments a ON a.tenant_id=s.tenant_id AND a.shop_id=s.shop_id AND a.sale_id=s.id AND a.receivable_id=b.id
    WHERE s.status='POSTED' AND (s.kind='RETURN' OR s.reversal_of_sale_id IS NOT NULL)
    GROUP BY s.id,s.public_id,s.kind,s.reversal_of_sale_id,s.occurred_at,s.posted_at,
             b.id,b.public_id,b.sale_public_id,original.public_id,original.kind
)
SELECT 'CREDIT' source_type,1 event_rank,CAST(b.id AS DECIMAL(21,0)) event_id,b.id source_id,
       b.public_id public_id,b.sale_public_id,b.public_id bill_public_id,
       NULL original_public_id,'CREDIT' event_type,b.original_amount amount_delta,b.original_amount amount,
       NULL channel,b.occurred_at,b.posted_at
FROM bills b
UNION ALL
SELECT 'SALE_CHANGE',2,CAST(s.id AS DECIMAL(21,0))*2,s.id,s.public_id,s.sale_public_id,s.bill_public_id,
       s.original_public_id,
       CASE WHEN s.reversal_of_sale_id IS NULL THEN 'RETURN'
            WHEN s.original_kind='RETURN' THEN 'RETURN_UNDONE' ELSE 'SALE_CANCELLED' END,
       s.amount_delta,ABS(s.amount_delta),NULL,s.occurred_at,s.posted_at
FROM commercial_changes s
UNION ALL
SELECT 'BILL_CHANGE',2,CAST(a.id AS DECIMAL(21,0))*2+1,a.id,a.public_id,b.sale_public_id,b.public_id,
       original.public_id,a.adjustment_type,a.amount_delta,ABS(a.amount_delta),NULL,a.occurred_at,a.posted_at
FROM receivable_adjustments a JOIN bills b ON b.tenant_id=a.tenant_id AND b.shop_id=a.shop_id AND b.id=a.receivable_id
LEFT JOIN receivable_adjustments original ON original.tenant_id=a.tenant_id AND original.shop_id=a.shop_id AND original.id=a.reversal_of_adjustment_id
WHERE a.sale_id IS NULL
UNION ALL
SELECT 'PAYMENT',3,CAST(p.id AS DECIMAL(21,0)),p.id,p.public_id,NULL,NULL,original.public_id,
       CASE WHEN p.reversal_of_debt_payment_id IS NULL THEN 'PAYMENT' ELSE 'PAYMENT_UNDONE' END,
       CASE WHEN p.reversal_of_debt_payment_id IS NULL THEN -p.amount ELSE p.amount END,p.amount,p.channel,p.occurred_at,p.posted_at
FROM debt_payments p
LEFT JOIN debt_payments original ON original.tenant_id=p.tenant_id AND original.shop_id=p.shop_id AND original.id=p.reversal_of_debt_payment_id
WHERE p.tenant_id=? AND p.shop_id=? AND p.customer_id=?
SQL;
        return [$sql, [$context->tenantId, $context->shopId, $customerId, $context->tenantId, $context->shopId, $customerId]];
    }
}
