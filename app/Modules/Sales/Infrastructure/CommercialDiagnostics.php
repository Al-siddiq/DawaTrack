<?php

declare(strict_types=1);
namespace App\Modules\Sales\Infrastructure;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/** Operator-only, scoped evidence checks. No repairs, Audit writes or change publication. */
final class CommercialDiagnostics
{
    public function __construct(private readonly BaseConnection $database) {}

    public function inspect(int $tenantId, int $shopId): array
    {
        if ($tenantId < 1 || $shopId < 1) {
            throw new \InvalidArgumentException(
                "Explicit Tenant and Shop scope is required.",
            );
        }
        if ($this->database->transDepth !== 0) {
            throw new \LogicException(
                "Commercial diagnostics require their own read-only snapshot.",
            );
        }
        // Transaction characteristics apply to the next transaction only. Avoid transient cross-query drift during live posting.
        $this->database->query(
            "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY",
        );
        $this->database->transException(true);
        if (!$this->database->transBegin()) {
            throw new \RuntimeException(
                "Commercial diagnostic snapshot could not start.",
            );
        }
        try {
            $result = [];
            foreach ($this->checks() as $name => $sql) {
                $result[$name] = $this->database
                    ->query($sql, [$tenantId, $shopId])
                    ->getResultArray();
            }
            if (!$this->database->transCommit()) {
                throw new \RuntimeException(
                    "Commercial diagnostic snapshot could not complete.",
                );
            }
            return $result;
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    /** Normal evidence checks do not decide the pending WP4 compensation-header policy. */
    private function checks(): array
    {
        return [
            "sale_line_totals" => <<<'SQL'
            SELECT s.public_id sale_public_id,s.total_amount,s.subtotal_amount,
                   COALESCE(SUM(i.line_sales_amount),0.00) line_total,COUNT(i.id) line_count
            FROM sales s
            LEFT JOIN sale_items i ON i.tenant_id=s.tenant_id AND i.shop_id=s.shop_id AND i.sale_id=s.id
            WHERE s.tenant_id=? AND s.shop_id=? AND s.status='POSTED' AND s.kind='SALE' AND s.reversal_of_sale_id IS NULL
            GROUP BY s.id,s.public_id,s.total_amount,s.subtotal_amount
            HAVING line_count=0 OR s.total_amount<>line_total OR s.subtotal_amount<>line_total
            ORDER BY s.id LIMIT 100
            SQL
            ,
            "sale_line_arithmetic" => <<<'SQL'
            SELECT i.public_id item_public_id,s.public_id sale_public_id,i.line_sales_amount,i.line_cogs_amount
            FROM sale_items i
            JOIN sales s ON s.tenant_id=i.tenant_id AND s.shop_id=i.shop_id AND s.id=i.sale_id
            WHERE i.tenant_id=? AND i.shop_id=? AND s.status='POSTED' AND s.kind='SALE' AND s.reversal_of_sale_id IS NULL AND (
             i.line_sales_amount<>ROUND(CAST(i.quantity AS DECIMAL(38,12))*CAST(i.unit_selling_price AS DECIMAL(38,12)),2)
             OR i.line_cogs_amount<>ROUND(CAST(i.quantity AS DECIMAL(38,12))*CAST(i.unit_cost_snapshot AS DECIMAL(38,12)),2)
            )
            ORDER BY i.id LIMIT 100
            SQL
            ,
            "sale_payment_credit_identity" => <<<'SQL'
            WITH scoped AS (
             SELECT * FROM sales WHERE tenant_id=? AND shop_id=? AND status='POSTED' AND kind='SALE' AND reversal_of_sale_id IS NULL
            ), paid AS (
             SELECT p.sale_id,SUM(CASE WHEN p.direction='RECEIPT' THEN p.amount ELSE 0 END) receipts,
                    SUM(CASE WHEN p.direction<>'RECEIPT' THEN 1 ELSE 0 END) invalid_directions
             FROM sale_payments p JOIN scoped s ON s.tenant_id=p.tenant_id AND s.shop_id=p.shop_id AND s.id=p.sale_id GROUP BY p.sale_id
            ), credit AS (
             SELECT r.sale_id,SUM(r.original_amount) original_credit,COUNT(*) obligations
             FROM receivables r JOIN scoped s ON s.tenant_id=r.tenant_id AND s.shop_id=r.shop_id AND s.id=r.sale_id GROUP BY r.sale_id
            )
            SELECT s.public_id sale_public_id,s.total_amount,s.received_amount_snapshot,s.credit_created_amount_snapshot,
                   COALESCE(p.receipts,0.00) receipts,COALESCE(c.original_credit,0.00) original_credit,COALESCE(c.obligations,0) obligations
            FROM scoped s LEFT JOIN paid p ON p.sale_id=s.id LEFT JOIN credit c ON c.sale_id=s.id
            WHERE s.received_amount_snapshot IS NULL OR s.credit_created_amount_snapshot IS NULL
             OR s.received_amount_snapshot<>COALESCE(p.receipts,0.00) OR s.credit_created_amount_snapshot<>COALESCE(c.original_credit,0.00)
             OR s.total_amount<>s.received_amount_snapshot+s.credit_created_amount_snapshot OR COALESCE(p.invalid_directions,0)<>0
             OR (s.credit_created_amount_snapshot>0 AND (COALESCE(c.obligations,0)<>1 OR s.customer_id IS NULL))
             OR (s.credit_created_amount_snapshot=0 AND COALESCE(c.obligations,0)<>0)
            ORDER BY s.id LIMIT 100
            SQL
            ,
            "payment_source_scope" => <<<'SQL'
            SELECT p.public_id payment_public_id,s.public_id sale_public_id
            FROM sale_payments p LEFT JOIN sales s ON s.id=p.sale_id
            WHERE p.tenant_id=? AND p.shop_id=? AND (
             s.id IS NULL OR s.tenant_id<>p.tenant_id OR s.shop_id<>p.shop_id OR s.status<>'POSTED'
             OR p.amount<=0 OR p.channel NOT IN ('CASH','TRANSFER') OR p.direction NOT IN ('RECEIPT','REFUND')
            )
            ORDER BY p.id LIMIT 100
            SQL
            ,
            "receivable_source_scope" => <<<'SQL'
            SELECT r.public_id receivable_public_id,s.public_id sale_public_id
            FROM receivables r LEFT JOIN sales s ON s.id=r.sale_id LEFT JOIN customers c ON c.id=r.customer_id
            WHERE r.tenant_id=? AND r.shop_id=? AND (
             s.id IS NULL OR s.tenant_id<>r.tenant_id OR s.shop_id<>r.shop_id OR s.status<>'POSTED' OR s.kind<>'SALE'
             OR c.id IS NULL OR c.tenant_id<>r.tenant_id OR NOT (s.customer_id<=>r.customer_id)
             OR r.original_amount<=0 OR NOT (s.credit_created_amount_snapshot<=>r.original_amount)
            )
            ORDER BY r.id LIMIT 100
            SQL
            ,
            "negative_adjusted_receivable" => <<<'SQL'
            SELECT r.public_id receivable_public_id,r.original_amount,
                   r.original_amount+COALESCE(SUM(a.amount_delta),0.00) phase_four_adjusted_amount
            FROM receivables r LEFT JOIN receivable_adjustments a ON a.tenant_id=r.tenant_id AND a.shop_id=r.shop_id AND a.receivable_id=r.id
            WHERE r.tenant_id=? AND r.shop_id=?
            GROUP BY r.id,r.public_id,r.original_amount
            HAVING phase_four_adjusted_amount<0
            ORDER BY r.id LIMIT 100
            SQL
            ,
            "normal_return_settlement" => <<<'SQL'
            WITH scoped AS (
             SELECT * FROM sales WHERE tenant_id=? AND shop_id=? AND status='POSTED' AND kind='RETURN' AND reversal_of_sale_id IS NULL
            ), refunds AS (
             SELECT p.sale_id,SUM(CASE WHEN p.direction='REFUND' THEN p.amount ELSE 0 END) refunded,
                    SUM(CASE WHEN p.direction<>'REFUND' THEN 1 ELSE 0 END) invalid_directions
             FROM sale_payments p JOIN scoped s ON s.tenant_id=p.tenant_id AND s.shop_id=p.shop_id AND s.id=p.sale_id GROUP BY p.sale_id
            ), reductions AS (
             SELECT a.sale_id,-SUM(a.amount_delta) reduced
             FROM receivable_adjustments a JOIN scoped s ON s.tenant_id=a.tenant_id AND s.shop_id=a.shop_id AND s.id=a.sale_id
             WHERE a.adjustment_type='RETURN_REDUCTION' GROUP BY a.sale_id
            )
            SELECT s.public_id return_public_id,s.total_amount,COALESCE(p.refunded,0.00) refunded,COALESCE(a.reduced,0.00) reduced
            FROM scoped s LEFT JOIN refunds p ON p.sale_id=s.id LEFT JOIN reductions a ON a.sale_id=s.id
            WHERE s.original_sale_id IS NULL OR s.received_amount_snapshot IS NOT NULL OR s.credit_created_amount_snapshot IS NOT NULL
             OR s.total_amount<>COALESCE(p.refunded,0.00)+COALESCE(a.reduced,0.00) OR COALESCE(p.invalid_directions,0)<>0
            ORDER BY s.id LIMIT 100
            SQL
            ,
            "normal_return_line_source" => <<<'SQL'
            SELECT i.public_id item_public_id,r.public_id return_public_id
            FROM sale_items i JOIN sales r ON r.tenant_id=i.tenant_id AND r.shop_id=i.shop_id AND r.id=i.sale_id
            LEFT JOIN sale_items original ON original.id=i.original_sale_item_id
            LEFT JOIN sales source ON source.id=r.original_sale_id
            WHERE i.tenant_id=? AND i.shop_id=? AND r.status='POSTED' AND r.kind='RETURN' AND r.reversal_of_sale_id IS NULL AND (
             original.id IS NULL OR source.id IS NULL OR source.status<>'POSTED' OR source.kind<>'SALE' OR source.reversal_of_sale_id IS NOT NULL
             OR original.tenant_id<>i.tenant_id OR original.shop_id<>i.shop_id OR original.sale_id<>r.original_sale_id
             OR original.product_id<>i.product_id OR original.unit_selling_price<>i.unit_selling_price OR original.unit_cost_snapshot<>i.unit_cost_snapshot
             OR NOT (r.customer_id<=>source.customer_id)
            )
            ORDER BY i.id LIMIT 100
            SQL
            ,
            "effective_return_quantity_value" => <<<'SQL'
            WITH effective AS (
             SELECT i.original_sale_item_id,SUM(i.quantity) returned_quantity,SUM(i.line_sales_amount) returned_value,SUM(i.line_cogs_amount) returned_cogs
             FROM sale_items i JOIN sales r ON r.tenant_id=i.tenant_id AND r.shop_id=i.shop_id AND r.id=i.sale_id
             WHERE r.tenant_id=? AND r.shop_id=? AND r.status='POSTED' AND r.kind='RETURN' AND r.reversal_of_sale_id IS NULL
             AND NOT EXISTS (SELECT 1 FROM sales reverse_sale WHERE reverse_sale.tenant_id=r.tenant_id AND reverse_sale.shop_id=r.shop_id AND reverse_sale.reversal_of_sale_id=r.id AND reverse_sale.status='POSTED')
             GROUP BY i.original_sale_item_id
            )
            SELECT original.public_id original_item_public_id,e.returned_quantity,e.returned_value,e.returned_cogs
            FROM effective e JOIN sale_items original ON original.id=e.original_sale_item_id
            WHERE e.returned_quantity>original.quantity OR e.returned_value>original.line_sales_amount OR e.returned_cogs>original.line_cogs_amount
            ORDER BY original.id LIMIT 100
            SQL
        ,
        ];
    }
}
