<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Inventory\Application\CommercialCostHistory;
use App\Modules\Inventory\Application\CostStateReconstructor;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\Sales\Application\DraftInput;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Read-only Shop-scoped Sales summary. Sales, payments and credit remain owned by their source evidence. */
final class SalesReportQuery
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {}

    public function show(ShopContext $context, array $input): array
    {
        $this->access->read($context, 'report.view');
        DraftInput::only($input, ['period', 'from_date', 'to_date']);

        $shop = $this->database->table('shops')
            ->select('timezone_name')
            ->where(['tenant_id' => $context->tenantId, 'id' => $context->shopId])
            ->get()->getRowArray();
        if (!$shop || !is_string($shop['timezone_name'] ?? null)) {
            throw new ApiException('REPORT_TIMEZONE_UNAVAILABLE', 'The Shop time zone could not be read. Ask the Shop Owner for help.', 503);
        }
        try {
            $timezone = new DateTimeZone($shop['timezone_name']);
        } catch (Throwable) {
            throw new ApiException('REPORT_TIMEZONE_UNAVAILABLE', 'The Shop time zone is not set correctly. Ask the Shop Owner for help.', 503);
        }

        [$period, $fromDate, $toDate, $startUtc, $endUtc] = $this->period($input, $timezone);
        $profitVisible = $this->access->allows($context, 'sale.profit.view');

        if ($this->database->transDepth !== 0) {
            throw new \LogicException('Sales reports require their own read-only snapshot.');
        }
        $this->database->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $this->database->transException(true);
        if (!$this->database->transBegin()) {
            throw new \RuntimeException('The Sales report snapshot could not start.');
        }

        try {
            $sales = $this->salesTotals($context, $startUtc, $endUtc);
            $payments = $this->paymentTotals($context, $startUtc, $endUtc);
            $credit = $this->creditTotal($context, $startUtc, $endUtc);

            $this->assertEvidence($sales, $payments, $credit);

            $grossProfit = null;
            if ($profitVisible) {
                $profit = $this->profitTotals($context, $startUtc, $endUtc);
                if ((int) $sales['line_count'] !== (int) $profit['line_count']) {
                    throw new ApiException('COMMERCIAL_EVIDENCE_INVALID', 'Some saved item cost details are missing. Ask the Shop Owner to review the linked sales.', 409);
                }
                $grossProfit = [
                    'amount' => (string) $profit['gross_profit_amount'],
                    'provisional' => (int) $sales['line_count'] > 0
                        ? $this->hasUnestablishedCost($context, $startUtc, $endUtc)
                        : false,
                    'label' => 'Money left after paying for goods',
                ];
                $grossProfit['message'] = $grossProfit['provisional']
                    ? 'Some goods did not have a known cost when they were sold. Treat this amount as an estimate.'
                    : 'This is the money left after paying for the goods sold. Shop bills and other expenses have not been taken out.';
            }

            if (!$this->database->transCommit()) {
                throw new \RuntimeException('The Sales report snapshot could not finish.');
            }

            return [
                'period' => [
                    'type' => $period,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'timezone' => $timezone->getName(),
                    'refreshed_at' => UtcTimestamp::now(),
                ],
                'sales' => [
                    'amount' => (string) $sales['sales_after_returns'],
                    'sale_count' => (int) $sales['sale_count'],
                    'return_count' => (int) $sales['return_count'],
                    'cancellation_count' => (int) $sales['cancellation_count'],
                ],
                'payments' => [
                    'cash_amount' => (string) $payments['cash_amount'],
                    'transfer_amount' => (string) $payments['transfer_amount'],
                    'credit_amount' => (string) $credit['credit_amount'],
                ],
                'gross_profit' => $grossProfit,
                'profit_visible' => $profitVisible,
            ];
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    /** @return array{0:string,1:string,2:string,3:string,4:string} */
    private function period(array $input, DateTimeZone $timezone): array
    {
        $period = $input['period'] ?? 'TODAY';
        if (!is_string($period) || !in_array($period, ['TODAY', 'THIS_MONTH', 'CUSTOM'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose today, this month or a custom date range.', 422, ['period' => ['Choose one of the listed date ranges.']]);
        }

        $today = new DateTimeImmutable('now', $timezone);
        if ($period === 'TODAY') {
            if (($input['from_date'] ?? null) !== null || ($input['to_date'] ?? null) !== null) {
                throw new ApiException('VALIDATION_FAILED', 'Choose Custom dates before entering start and end dates.', 422);
            }
            $from = $today->setTime(0, 0, 0, 0);
            $to = $from;
        } elseif ($period === 'THIS_MONTH') {
            if (($input['from_date'] ?? null) !== null || ($input['to_date'] ?? null) !== null) {
                throw new ApiException('VALIDATION_FAILED', 'Choose Custom dates before entering start and end dates.', 422);
            }
            $to = $today->setTime(0, 0, 0, 0);
            $from = $to->modify('first day of this month')->setTime(0, 0, 0, 0);
        } else {
            $fromDate = $this->calendarDate($input['from_date'] ?? null, 'from_date');
            $toDate = $this->calendarDate($input['to_date'] ?? null, 'to_date');
            $from = new DateTimeImmutable($fromDate . ' 00:00:00', $timezone);
            $to = new DateTimeImmutable($toDate . ' 00:00:00', $timezone);
            if ($from > $to) {
                throw new ApiException('VALIDATION_FAILED', 'The end date must be the same as or later than the start date.', 422, ['to_date' => ['Choose an end date on or after the start date.']]);
            }
            if ((int) $from->diff($to)->days > 365) {
                throw new ApiException('REPORT_RANGE_TOO_WIDE', 'Choose a date range of one year or less.', 422, ['to_date' => ['The report can cover up to 366 calendar days.']]);
            }
        }

        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');
        $endExclusive = $to->modify('+1 day')->setTime(0, 0, 0, 0);
        $utc = new DateTimeZone('UTC');

        return [
            $period,
            $fromDate,
            $toDate,
            $from->setTimezone($utc)->format('Y-m-d H:i:s.u'),
            $endExclusive->setTimezone($utc)->format('Y-m-d H:i:s.u'),
        ];
    }

    private function calendarDate(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose valid start and end dates.', 422, [$field => ['Enter a date.']]);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value || (int) $date->format('Y') < 1000) {
            throw new ApiException('VALIDATION_FAILED', 'Choose valid start and end dates.', 422, [$field => ['Use a real calendar date in YYYY-MM-DD format.']]);
        }
        return $value;
    }

    private function salesTotals(ShopContext $context, string $start, string $end): array
    {
        return $this->database->query(
            <<<'SQL'
            WITH scoped_sales AS (
                SELECT s.id,s.kind,s.total_amount,s.reversal_of_sale_id,
                    COUNT(i.id) line_count,
                    CAST(COALESCE(SUM(i.line_sales_amount),0.00) AS DECIMAL(19,2)) line_sales_amount,
                    CASE
                        WHEN s.reversal_of_sale_id IS NOT NULL THEN CASE WHEN original_sale.kind='SALE' THEN -1 ELSE 1 END
                        WHEN s.kind='SALE' THEN 1 ELSE -1
                    END effect_sign
                FROM sales s
                LEFT JOIN sales original_sale ON original_sale.tenant_id=s.tenant_id AND original_sale.shop_id=s.shop_id AND original_sale.id=s.reversal_of_sale_id
                LEFT JOIN sale_items i ON i.tenant_id=s.tenant_id AND i.shop_id=s.shop_id AND i.sale_id=s.id
                WHERE s.tenant_id=? AND s.shop_id=? AND s.status='POSTED' AND s.posted_at>=? AND s.posted_at<?
                GROUP BY s.id,s.kind,s.total_amount,s.reversal_of_sale_id,original_sale.kind
            ), sales_totals AS (
                SELECT
                    CAST(COALESCE(SUM(total_amount*effect_sign),0.00) AS CHAR) sales_after_returns,
                    COALESCE(SUM(CASE WHEN reversal_of_sale_id IS NULL AND kind='SALE' THEN 1 ELSE 0 END),0) sale_count,
                    COALESCE(SUM(CASE WHEN reversal_of_sale_id IS NULL AND kind='RETURN' THEN 1 ELSE 0 END),0) return_count,
                    COALESCE(SUM(CASE WHEN reversal_of_sale_id IS NOT NULL THEN 1 ELSE 0 END),0) cancellation_count,
                    COALESCE(SUM(line_count),0) line_count,
                    CAST(COALESCE(SUM(line_sales_amount*effect_sign),0.00) AS CHAR) line_sales_amount,
                    COALESCE(SUM(CASE WHEN line_count=0 OR line_sales_amount<>total_amount THEN 1 ELSE 0 END),0) invalid_line_count
                FROM scoped_sales
            )
            SELECT * FROM sales_totals
            SQL,
            [$context->tenantId, $context->shopId, $start, $end],
        )->getRowArray() ?? throw new RuntimeException('Sales summary was not returned.');
    }

    private function paymentTotals(ShopContext $context, string $start, string $end): array
    {
        return $this->database->query(
            <<<'SQL'
            SELECT
                CAST(COALESCE(SUM(CASE WHEN p.channel='CASH' THEN CASE WHEN p.direction='RECEIPT' THEN p.amount WHEN p.direction='REFUND' THEN -p.amount ELSE 0.00 END ELSE 0.00 END),0.00) AS CHAR) cash_amount,
                CAST(COALESCE(SUM(CASE WHEN p.channel='TRANSFER' THEN CASE WHEN p.direction='RECEIPT' THEN p.amount WHEN p.direction='REFUND' THEN -p.amount ELSE 0.00 END ELSE 0.00 END),0.00) AS CHAR) transfer_amount,
                COALESCE(SUM(CASE WHEN p.direction NOT IN ('RECEIPT','REFUND') OR p.channel NOT IN ('CASH','TRANSFER') OR (s.kind='SALE' AND p.direction<>'RECEIPT') OR (s.kind='RETURN' AND p.direction<>'REFUND') THEN 1 ELSE 0 END),0) invalid_count
            FROM sale_payments p
            JOIN sales s ON s.tenant_id=p.tenant_id AND s.shop_id=p.shop_id AND s.id=p.sale_id
            WHERE p.tenant_id=? AND p.shop_id=? AND s.status='POSTED' AND s.posted_at>=? AND s.posted_at<?
            SQL,
            [$context->tenantId, $context->shopId, $start, $end],
        )->getRowArray() ?? ['cash_amount' => '0.00', 'transfer_amount' => '0.00'];
    }

    private function creditTotal(ShopContext $context, string $start, string $end): array
    {
        return $this->database->query(
            <<<'SQL'
            SELECT CAST(
                COALESCE((
                    SELECT SUM(r.original_amount)
                    FROM receivables r
                    JOIN sales s ON s.tenant_id=r.tenant_id AND s.shop_id=r.shop_id AND s.id=r.sale_id
                    WHERE r.tenant_id=? AND r.shop_id=? AND s.status='POSTED' AND s.kind='SALE'
                      AND r.posted_at>=? AND r.posted_at<?
                ),0.00)
                +
                COALESCE((
                    SELECT SUM(a.amount_delta)
                    FROM receivable_adjustments a
                    JOIN sales s ON s.tenant_id=a.tenant_id AND s.shop_id=a.shop_id AND s.id=a.sale_id
                    WHERE a.tenant_id=? AND a.shop_id=? AND a.sale_id IS NOT NULL
                      AND a.adjustment_type IN ('RETURN_REDUCTION','CORRECTION_DECREASE','REVERSAL')
                      AND s.status='POSTED' AND a.posted_at>=? AND a.posted_at<?
                ),0.00)
            AS CHAR) credit_amount
            SQL,
            [$context->tenantId, $context->shopId, $start, $end, $context->tenantId, $context->shopId, $start, $end],
        )->getRowArray() ?? ['credit_amount' => '0.00'];
    }

    private function assertEvidence(array $sales, array $payments, array $credit): void
    {
        if ((int) ($payments['invalid_count'] ?? 0) > 0) {
            throw new ApiException('COMMERCIAL_EVIDENCE_INVALID', 'A saved payment has an unsupported type. Ask the Shop Owner to review the linked records.', 409);
        }
        if ((int) ($sales['invalid_line_count'] ?? 0) > 0) {
            throw new ApiException('COMMERCIAL_EVIDENCE_INVALID', 'A saved Sale or Return has missing item details or item totals that do not match its saved total. Ask the Shop Owner to review the linked records.', 409);
        }
        $lineMatches = $this->database->query(
            'SELECT CAST(? AS DECIMAL(19,2))=CAST(? AS DECIMAL(19,2)) matches',
            [(string) $sales['sales_after_returns'], (string) $sales['line_sales_amount']],
        )->getRowArray();
        if ((int) ($lineMatches['matches'] ?? 0) !== 1) {
            throw new ApiException('COMMERCIAL_EVIDENCE_INVALID', 'Saved Sales and item totals do not match. Ask the Shop Owner to review the linked records.', 409);
        }

        $compositionMatches = $this->database->query(
            'SELECT CAST(? AS DECIMAL(19,2))+CAST(? AS DECIMAL(19,2))+CAST(? AS DECIMAL(19,2))=CAST(? AS DECIMAL(19,2)) matches',
            [(string) $payments['cash_amount'], (string) $payments['transfer_amount'], (string) $credit['credit_amount'], (string) $sales['sales_after_returns']],
        )->getRowArray();
        if ((int) ($compositionMatches['matches'] ?? 0) !== 1) {
            throw new ApiException('COMMERCIAL_EVIDENCE_INVALID', 'Cash, bank transfer and customer credit do not match the saved Sales total. Ask the Shop Owner to review the linked records.', 409);
        }
    }

    private function profitTotals(ShopContext $context, string $start, string $end): array
    {
        return $this->database->query(
            <<<'SQL'
            WITH scoped_sales AS (
                SELECT s.id,
                    CASE
                        WHEN s.reversal_of_sale_id IS NOT NULL THEN CASE WHEN original_sale.kind='SALE' THEN -1 ELSE 1 END
                        WHEN s.kind='SALE' THEN 1 ELSE -1
                    END effect_sign
                FROM sales s
                LEFT JOIN sales original_sale ON original_sale.tenant_id=s.tenant_id AND original_sale.shop_id=s.shop_id AND original_sale.id=s.reversal_of_sale_id
                WHERE s.tenant_id=? AND s.shop_id=? AND s.status='POSTED' AND s.posted_at>=? AND s.posted_at<?
            )
            SELECT COUNT(i.id) line_count,
                CAST(COALESCE(SUM((i.line_sales_amount-i.line_cogs_amount)*ss.effect_sign),0.00) AS CHAR) gross_profit_amount
            FROM scoped_sales ss
            JOIN sale_items i ON i.tenant_id=? AND i.shop_id=? AND i.sale_id=ss.id
            SQL,
            [$context->tenantId, $context->shopId, $start, $end, $context->tenantId, $context->shopId],
        )->getRowArray() ?? ['line_count' => 0, 'gross_profit_amount' => '0.00'];
    }

    private function hasUnestablishedCost(ShopContext $context, string $start, string $end): bool
    {
        $targets = $this->database->query(
            <<<'SQL'
            SELECT DISTINCT source_item.product_id,sm.id movement_id
            FROM sales s
            JOIN sale_items item ON item.tenant_id=s.tenant_id AND item.shop_id=s.shop_id AND item.sale_id=s.id
            LEFT JOIN sale_items linked_item ON linked_item.tenant_id=item.tenant_id AND linked_item.shop_id=item.shop_id
                AND linked_item.id=item.original_sale_item_id
            LEFT JOIN sale_items source_item ON source_item.tenant_id=item.tenant_id AND source_item.shop_id=item.shop_id
                AND source_item.id=COALESCE(linked_item.original_sale_item_id,item.original_sale_item_id,item.id)
            LEFT JOIN stock_movements sm ON sm.tenant_id=source_item.tenant_id AND sm.shop_id=source_item.shop_id
                AND sm.sale_item_id=source_item.id AND sm.source_kind='SALE_ITEM' AND sm.movement_type='SALE'
            WHERE s.tenant_id=? AND s.shop_id=? AND s.status='POSTED' AND s.posted_at>=? AND s.posted_at<?
            ORDER BY source_item.product_id,sm.id
            LIMIT 25001
            SQL,
            [$context->tenantId, $context->shopId, $start, $end],
        )->getResultArray();

        if (count($targets) > 25000) {
            throw new ApiException('REPORT_RANGE_TOO_WIDE', 'This date range contains too many item records to check profit safely. Choose a shorter date range.', 422);
        }

        $byProduct = [];
        $throughByProduct = [];
        foreach ($targets as $target) {
            if ($target['movement_id'] === null || $target['product_id'] === null) {
                throw new ApiException('COMMERCIAL_EVIDENCE_INVALID', 'A saved item is missing its original cost record. Ask the Shop Owner to review the linked sale.', 409);
            }
            $productId = (int) $target['product_id'];
            $movementId = (int) $target['movement_id'];
            $byProduct[$productId][] = $movementId;
            $throughByProduct[$productId] = max($throughByProduct[$productId] ?? 0, $movementId);
        }

        $history = new CommercialCostHistory($this->database);
        $reconstructor = new CostStateReconstructor();
        $chains = $history->chainsThrough($context->tenantId, $context->shopId, $throughByProduct);
        foreach ($byProduct as $productId => $movementIds) {
            $states = $reconstructor->beforeStates($chains[$productId] ?? []);
            foreach ($movementIds as $movementId) {
                if (!array_key_exists($movementId, $states)) {
                    throw new ApiException('COMMERCIAL_EVIDENCE_INVALID', 'The original item cost could not be checked. Ask the Shop Owner to review the sale.', 409);
                }
                if ($states[$movementId] === 'UNESTABLISHED') {
                    return true;
                }
            }
        }
        return false;
    }
}
