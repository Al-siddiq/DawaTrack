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
            $result["inventory_cost_state"] = [];
            $positions = $this->database
                ->query(
                    "SELECT id,product_id,cost_basis_state,last_movement_id FROM stock_positions WHERE tenant_id=? AND shop_id=? ORDER BY id",
                    [$tenantId, $shopId],
                )
                ->getResultArray();
            $history = new \App\Modules\Inventory\Application\CommercialCostHistory(
                $this->database,
            );
            foreach ($positions as $p) {
                $states = (new \App\Modules\Inventory\Application\CostStateReconstructor())->afterStates(
                    $history->chain($tenantId, $shopId, (int) $p["product_id"]),
                );
                $expected =
                    $p["last_movement_id"] === null
                        ? "UNESTABLISHED"
                        : $states[(int) $p["last_movement_id"]] ??
                            "UNESTABLISHED";
                if ($expected !== $p["cost_basis_state"]) {
                    $result["inventory_cost_state"][] = [
                        "position_id" => $p["id"],
                        "stored_state" => $p["cost_basis_state"],
                        "evidence_state" => $expected,
                    ];
                }
                if (count($result["inventory_cost_state"]) >= 100) {
                    break;
                }
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

    /** Normal and approved compensation evidence checks; no repairs. */
    private function checks(): array
    {
        return [
            "sale_line_totals" => <<<'SQL'
            SELECT s.public_id sale_public_id,s.total_amount,s.subtotal_amount,
                   COALESCE(SUM(i.line_sales_amount),0.00) line_total,COUNT(i.id) line_count
            FROM sales s
            LEFT JOIN sale_items i ON i.tenant_id=s.tenant_id AND i.shop_id=s.shop_id AND i.sale_id=s.id
            WHERE s.tenant_id=? AND s.shop_id=? AND s.status='POSTED' AND s.kind IN ('SALE','RETURN')
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
            "debt_payment_evidence" => <<<'SQL'
            WITH scoped AS (SELECT * FROM debt_payments WHERE tenant_id=? AND shop_id=?),
            totals AS (SELECT a.debt_payment_id,SUM(a.amount) amount,COUNT(*) n,COUNT(DISTINCT a.allocation_order) distinct_orders,MIN(a.allocation_order) first_order,MAX(a.allocation_order) last_order
                       FROM debt_payment_allocations a JOIN scoped p ON p.tenant_id=a.tenant_id AND p.shop_id=a.shop_id AND p.id=a.debt_payment_id GROUP BY a.debt_payment_id)
            SELECT p.public_id payment_public_id FROM scoped p LEFT JOIN totals t ON t.debt_payment_id=p.id
            LEFT JOIN debt_payments original ON original.id=p.reversal_of_debt_payment_id
            WHERE COALESCE(t.amount,0.00)<>p.amount OR t.first_order<>1 OR t.last_order<>t.n OR t.distinct_orders<>t.n
             OR (p.reversal_of_debt_payment_id IS NOT NULL AND (original.id IS NULL OR original.id=p.id OR original.reversal_of_debt_payment_id IS NOT NULL
                 OR original.tenant_id<>p.tenant_id OR original.shop_id<>p.shop_id OR original.customer_id<>p.customer_id OR original.channel<>p.channel OR original.amount<>p.amount
                 OR EXISTS(SELECT 1 FROM debt_payment_allocations a LEFT JOIN debt_payment_allocations b ON b.tenant_id=a.tenant_id AND b.shop_id=a.shop_id AND b.debt_payment_id=original.id AND b.receivable_id=a.receivable_id
                           WHERE a.debt_payment_id=p.id AND (b.id IS NULL OR b.amount<>a.amount OR b.allocation_order<>a.allocation_order))
                 OR (SELECT COUNT(*) FROM debt_payment_allocations a WHERE a.debt_payment_id=original.id)<>t.n))
             OR EXISTS(SELECT 1 FROM debt_payment_allocations a LEFT JOIN receivables r ON r.id=a.receivable_id
                       WHERE a.debt_payment_id=p.id AND (a.tenant_id<>p.tenant_id OR a.shop_id<>p.shop_id OR r.id IS NULL OR r.tenant_id<>p.tenant_id OR r.shop_id<>p.shop_id OR r.customer_id<>p.customer_id))
            ORDER BY p.id LIMIT 100
            SQL
            ,
            "debt_live_balance" => <<<'SQL'
            WITH bills AS (SELECT * FROM receivables WHERE tenant_id=? AND shop_id=?),
            adjusted AS (SELECT a.receivable_id,SUM(a.amount_delta) amount FROM receivable_adjustments a JOIN bills b ON b.tenant_id=a.tenant_id AND b.shop_id=a.shop_id AND b.id=a.receivable_id GROUP BY a.receivable_id),
            collected AS (SELECT a.receivable_id,SUM(CASE WHEN p.reversal_of_debt_payment_id IS NULL THEN a.amount ELSE -a.amount END) amount
             FROM debt_payment_allocations a JOIN debt_payments p ON p.tenant_id=a.tenant_id AND p.shop_id=a.shop_id AND p.id=a.debt_payment_id
             JOIN bills b ON b.tenant_id=a.tenant_id AND b.shop_id=a.shop_id AND b.id=a.receivable_id GROUP BY a.receivable_id)
            SELECT b.public_id receivable_public_id FROM bills b LEFT JOIN adjusted j ON j.receivable_id=b.id LEFT JOIN collected k ON k.receivable_id=b.id
            WHERE b.original_amount+COALESCE(j.amount,0.00)<0 OR COALESCE(k.amount,0.00)<0 OR b.original_amount+COALESCE(j.amount,0.00)-COALESCE(k.amount,0.00)<0
            ORDER BY b.id LIMIT 100
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
            "compensation_header" => <<<'SQL'
            SELECT c.public_id compensation_public_id,o.public_id source_public_id
            FROM sales c LEFT JOIN sales o ON o.id=c.reversal_of_sale_id LEFT JOIN sales root ON root.id=c.original_sale_id
            WHERE c.tenant_id=? AND c.shop_id=? AND c.reversal_of_sale_id IS NOT NULL AND (
             c.status<>'POSTED' OR c.received_amount_snapshot IS NOT NULL OR c.credit_created_amount_snapshot IS NOT NULL
             OR o.id IS NULL OR o.tenant_id<>c.tenant_id OR o.shop_id<>c.shop_id OR o.status<>'POSTED' OR o.reversal_of_sale_id IS NOT NULL OR c.kind=o.kind
             OR root.id IS NULL OR root.tenant_id<>c.tenant_id OR root.shop_id<>c.shop_id OR root.kind<>'SALE' OR root.status<>'POSTED' OR root.reversal_of_sale_id IS NOT NULL
             OR root.id<>COALESCE(o.original_sale_id,o.id) OR NOT (c.customer_id<=>o.customer_id) OR c.total_amount<>o.total_amount
             OR EXISTS(SELECT 1 FROM receivables r WHERE r.sale_id=c.id)
            )
            ORDER BY c.id LIMIT 100
            SQL
            ,
            "compensation_lineage" => <<<'SQL'
            SELECT i.public_id item_public_id,c.public_id compensation_public_id
            FROM sale_items i JOIN sales c ON c.tenant_id=i.tenant_id AND c.shop_id=i.shop_id AND c.id=i.sale_id
            LEFT JOIN sale_items original ON original.id=i.original_sale_item_id
            WHERE i.tenant_id=? AND i.shop_id=? AND c.reversal_of_sale_id IS NOT NULL AND (
             original.id IS NULL OR original.sale_id<>c.reversal_of_sale_id OR original.tenant_id<>i.tenant_id OR original.shop_id<>i.shop_id
             OR original.product_id<>i.product_id OR original.quantity<>i.quantity OR original.unit_selling_price<>i.unit_selling_price
             OR original.line_sales_amount<>i.line_sales_amount OR original.unit_cost_snapshot<>i.unit_cost_snapshot OR original.line_cogs_amount<>i.line_cogs_amount
            )
            ORDER BY i.id LIMIT 100
            SQL
            ,
            "missing_compensation_line" => <<<'SQL'
            SELECT original.public_id original_item_public_id,c.public_id compensation_public_id,COUNT(i.id) inverse_line_count
            FROM sale_items original JOIN sales c ON c.tenant_id=original.tenant_id AND c.shop_id=original.shop_id AND c.reversal_of_sale_id=original.sale_id AND c.status='POSTED'
            LEFT JOIN sale_items i ON i.tenant_id=original.tenant_id AND i.shop_id=original.shop_id AND i.sale_id=c.id AND i.original_sale_item_id=original.id
            WHERE original.tenant_id=? AND original.shop_id=?
            GROUP BY original.id,original.public_id,c.public_id HAVING inverse_line_count<>1
            ORDER BY original.id LIMIT 100
            SQL
            ,
            "compensation_payment_inverse" => <<<'SQL'
            SELECT p.public_id payment_public_id,c.public_id compensation_public_id
            FROM sale_payments p JOIN sales c ON c.tenant_id=p.tenant_id AND c.shop_id=p.shop_id AND c.id=p.sale_id
            LEFT JOIN sale_payments original ON original.id=p.reversal_of_payment_id
            WHERE p.tenant_id=? AND p.shop_id=? AND c.reversal_of_sale_id IS NOT NULL AND (
             original.id IS NULL OR original.sale_id<>c.reversal_of_sale_id OR original.tenant_id<>p.tenant_id OR original.shop_id<>p.shop_id
             OR original.channel<>p.channel OR original.amount<>p.amount OR original.direction=p.direction
            )
            ORDER BY p.id LIMIT 100
            SQL
            ,
            "missing_payment_compensation" => <<<'SQL'
            SELECT p.public_id original_payment_public_id,c.public_id compensation_public_id
            FROM sale_payments p JOIN sales c ON c.tenant_id=p.tenant_id AND c.shop_id=p.shop_id AND c.reversal_of_sale_id=p.sale_id AND c.status='POSTED'
            LEFT JOIN sale_payments reversed ON reversed.tenant_id=p.tenant_id AND reversed.shop_id=p.shop_id AND reversed.reversal_of_payment_id=p.id AND reversed.sale_id=c.id
            WHERE p.tenant_id=? AND p.shop_id=? AND reversed.id IS NULL
            ORDER BY p.id LIMIT 100
            SQL
            ,
            "compensation_settlement" => <<<'SQL'
            WITH scoped AS (SELECT * FROM sales WHERE tenant_id=? AND shop_id=? AND status='POSTED' AND reversal_of_sale_id IS NOT NULL),
            paid AS (SELECT p.sale_id,SUM(p.amount) money FROM sale_payments p JOIN scoped c ON c.tenant_id=p.tenant_id AND c.shop_id=p.shop_id AND c.id=p.sale_id GROUP BY p.sale_id),
            adjusted AS (SELECT a.sale_id,SUM(a.amount_delta) debt FROM receivable_adjustments a JOIN scoped c ON c.tenant_id=a.tenant_id AND c.shop_id=a.shop_id AND c.id=a.sale_id GROUP BY a.sale_id)
            SELECT c.public_id compensation_public_id,c.total_amount,COALESCE(p.money,0.00) money,COALESCE(a.debt,0.00) debt
            FROM scoped c LEFT JOIN paid p ON p.sale_id=c.id LEFT JOIN adjusted a ON a.sale_id=c.id
            WHERE c.total_amount<>COALESCE(p.money,0.00)+CASE WHEN c.kind='SALE' THEN COALESCE(a.debt,0.00) ELSE -COALESCE(a.debt,0.00) END
            ORDER BY c.id LIMIT 100
            SQL
            ,
            "adjustment_source_graph" => <<<'SQL'
            SELECT a.public_id adjustment_public_id,s.public_id source_public_id
            FROM receivable_adjustments a LEFT JOIN receivables r ON r.id=a.receivable_id LEFT JOIN sales s ON s.id=a.sale_id
            LEFT JOIN sales original ON original.id=s.reversal_of_sale_id LEFT JOIN receivable_adjustments reversed ON reversed.id=a.reversal_of_adjustment_id
            WHERE a.tenant_id=? AND a.shop_id=? AND (
             r.id IS NULL OR r.tenant_id<>a.tenant_id OR r.shop_id<>a.shop_id
             OR (a.sale_id IS NULL AND (
                 a.adjustment_type NOT IN ('CORRECTION_INCREASE','CORRECTION_DECREASE','REVERSAL')
                 OR (a.adjustment_type='CORRECTION_INCREASE' AND a.amount_delta<=0)
                 OR (a.adjustment_type='CORRECTION_DECREASE' AND a.amount_delta>=0)
                 OR (a.adjustment_type='REVERSAL' AND (reversed.id IS NULL OR reversed.sale_id IS NOT NULL
                     OR reversed.reversal_of_adjustment_id IS NOT NULL OR reversed.tenant_id<>a.tenant_id OR reversed.shop_id<>a.shop_id
                     OR reversed.receivable_id<>a.receivable_id OR a.amount_delta<>-reversed.amount_delta))
             ))
             OR (a.sale_id IS NOT NULL AND (s.id IS NULL OR s.tenant_id<>a.tenant_id OR s.shop_id<>a.shop_id OR s.status<>'POSTED'
                 OR r.sale_id<>s.original_sale_id
                 OR (a.adjustment_type='RETURN_REDUCTION' AND (s.kind<>'RETURN' OR s.reversal_of_sale_id IS NOT NULL OR a.amount_delta>=0))
                 OR (a.adjustment_type='CORRECTION_DECREASE' AND (s.kind<>'RETURN' OR original.kind<>'SALE' OR original.id<>r.sale_id OR a.amount_delta>=0))
                 OR (a.adjustment_type='REVERSAL' AND (s.kind<>'SALE' OR original.kind<>'RETURN' OR reversed.id IS NULL OR reversed.tenant_id<>a.tenant_id OR reversed.shop_id<>a.shop_id OR reversed.adjustment_type<>'RETURN_REDUCTION' OR reversed.sale_id<>original.id OR reversed.receivable_id<>a.receivable_id OR a.amount_delta<>-reversed.amount_delta))
                 OR a.adjustment_type='CORRECTION_INCREASE'))
            )
            ORDER BY a.id LIMIT 100
            SQL
            ,
            "effective_refund_channel_caps" => <<<'SQL'
            WITH roots AS (SELECT * FROM sales WHERE tenant_id=? AND shop_id=? AND kind='SALE' AND status='POSTED' AND reversal_of_sale_id IS NULL),
            receipts AS (SELECT p.sale_id,p.channel,p.amount FROM sale_payments p JOIN roots s ON s.tenant_id=p.tenant_id AND s.shop_id=p.shop_id AND s.id=p.sale_id WHERE p.direction='RECEIPT'
             UNION ALL SELECT b.sale_id,p.channel,CASE WHEN p.reversal_of_debt_payment_id IS NULL THEN a.amount ELSE -a.amount END amount
             FROM debt_payment_allocations a JOIN debt_payments p ON p.tenant_id=a.tenant_id AND p.shop_id=a.shop_id AND p.id=a.debt_payment_id
             JOIN receivables b ON b.tenant_id=a.tenant_id AND b.shop_id=a.shop_id AND b.id=a.receivable_id AND b.customer_id=p.customer_id
             JOIN roots s ON s.tenant_id=b.tenant_id AND s.shop_id=b.shop_id AND s.id=b.sale_id),
            received AS (SELECT sale_id,channel,SUM(amount) amount FROM receipts GROUP BY sale_id,channel),
            refunded AS (SELECT r.original_sale_id root_id,p.channel,SUM(p.amount) amount FROM sales r JOIN roots s ON s.tenant_id=r.tenant_id AND s.shop_id=r.shop_id AND s.id=r.original_sale_id JOIN sale_payments p ON p.tenant_id=r.tenant_id AND p.shop_id=r.shop_id AND p.sale_id=r.id WHERE r.kind='RETURN' AND r.status='POSTED' AND r.reversal_of_sale_id IS NULL AND p.direction='REFUND' AND NOT EXISTS(SELECT 1 FROM sales x WHERE x.reversal_of_sale_id=r.id AND x.status='POSTED') GROUP BY r.original_sale_id,p.channel)
            SELECT s.public_id original_sale_public_id,r.channel,r.amount refunded,COALESCE(p.amount,0.00) received
            FROM refunded r JOIN roots s ON s.id=r.root_id LEFT JOIN received p ON p.sale_id=r.root_id AND p.channel=r.channel
            WHERE r.amount>COALESCE(p.amount,0.00)
            ORDER BY s.id,r.channel LIMIT 100
            SQL
            ,
            "replacement_graph" => <<<'SQL'
            SELECT replacement.public_id replacement_public_id,original.public_id original_public_id
            FROM sales replacement LEFT JOIN sales original ON original.id=replacement.replacement_for_sale_id
            WHERE replacement.tenant_id=? AND replacement.shop_id=? AND replacement.replacement_for_sale_id IS NOT NULL AND (
             original.id IS NULL OR original.tenant_id<>replacement.tenant_id OR original.shop_id<>replacement.shop_id OR original.status<>'POSTED'
             OR original.kind<>replacement.kind OR original.reversal_of_sale_id IS NOT NULL OR replacement.reversal_of_sale_id IS NOT NULL
             OR NOT (original.original_sale_id<=>replacement.original_sale_id)
             OR NOT EXISTS(SELECT 1 FROM sales c WHERE c.reversal_of_sale_id=original.id AND c.status='POSTED')
            )
            ORDER BY replacement.id LIMIT 100
            SQL
        ,
        ];
    }
}
