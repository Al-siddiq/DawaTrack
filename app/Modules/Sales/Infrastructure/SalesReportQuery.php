<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\Sales\Application\SaleMoney;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Read-only Shop Sales report. Sales owns the meaning of Sale, Return,
 * payment and historical cost evidence; this query never writes business data.
 */
final class SalesReportQuery
{
    private const MAX_DAYS = 366;

    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {}

    public function report(ShopContext $context, array $input): array
    {
        $this->access->read($context, 'report.view');

        $shop = $this->database->table('shops')
            ->select('timezone_name,currency_code')
            ->where([
                'tenant_id' => $context->tenantId,
                'id' => $context->shopId,
            ])
            ->get()
            ->getRowArray();

        if (! $shop) {
            throw new ApiException('RESOURCE_NOT_FOUND', 'This Shop report is unavailable.', 404);
        }

        $timezoneName = (string) ($shop['timezone_name'] ?: 'Africa/Lagos');
        try {
            $timezone = new DateTimeZone($timezoneName);
        } catch (Throwable) {
            throw new ApiException('REPORT_TIMEZONE_INVALID', 'The Shop time setting needs to be checked before this report can be shown.', 409);
        }

        $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0);
        $from = $this->date($input['from'] ?? null, 'from', $today->modify('first day of this month'), $timezone);
        $to = $this->date($input['to'] ?? null, 'to', $today, $timezone);

        if ($from > $to) {
            throw new ApiException('REPORT_DATE_RANGE_INVALID', 'The end date must be the same as or later than the start date.', 422, [
                'to' => ['Choose an end date that is not before the start date.'],
            ]);
        }

        $days = (int) $from->diff($to)->days + 1;
        if ($days > self::MAX_DAYS) {
            throw new ApiException('REPORT_DATE_RANGE_TOO_WIDE', 'Choose a date range of 366 days or less.', 422, [
                'from' => ['The report can show up to 366 days at a time.'],
                'to' => ['The report can show up to 366 days at a time.'],
            ]);
        }

        $groupBy = strtoupper((string) ($input['group_by'] ?? 'DAY'));
        if (! in_array($groupBy, ['DAY', 'MONTH'], true)) {
            throw new ApiException('REPORT_GROUP_INVALID', 'Choose daily or monthly results.', 422, [
                'group_by' => ['Choose Daily or Monthly.'],
            ]);
        }

        [$bucketSql, $bucketParams, $dates] = $this->buckets($from, $to);
        $scopeParams = [$context->tenantId, $context->shopId];

        $salesRows = $this->database->query(
            "SELECT b.local_date,
                COALESCE(SUM(CASE WHEN s.kind='SALE' THEN s.total_amount ELSE 0.00 END),0.00) sales_added,
                COALESCE(SUM(CASE WHEN s.kind='RETURN' THEN s.total_amount ELSE 0.00 END),0.00) returns_and_cancellations
             FROM ({$bucketSql}) b
             LEFT JOIN sales s ON s.tenant_id=? AND s.shop_id=? AND s.status='POSTED'
                AND s.posted_at>=b.utc_start AND s.posted_at<b.utc_end
             GROUP BY b.local_date ORDER BY b.local_date",
            [...$bucketParams, ...$scopeParams],
        )->getResultArray();

        $paymentRows = $this->database->query(
            "SELECT b.local_date,
                COALESCE(SUM(CASE WHEN p.channel='CASH' AND p.direction='RECEIPT' THEN p.amount ELSE 0.00 END),0.00) cash_received,
                COALESCE(SUM(CASE WHEN p.channel='CASH' AND p.direction='REFUND' THEN p.amount ELSE 0.00 END),0.00) cash_refunded,
                COALESCE(SUM(CASE WHEN p.channel='TRANSFER' AND p.direction='RECEIPT' THEN p.amount ELSE 0.00 END),0.00) transfer_received,
                COALESCE(SUM(CASE WHEN p.channel='TRANSFER' AND p.direction='REFUND' THEN p.amount ELSE 0.00 END),0.00) transfer_refunded
             FROM ({$bucketSql}) b
             LEFT JOIN sales s ON s.tenant_id=? AND s.shop_id=? AND s.status='POSTED'
                AND s.posted_at>=b.utc_start AND s.posted_at<b.utc_end
             LEFT JOIN sale_payments p ON p.tenant_id=s.tenant_id AND p.shop_id=s.shop_id AND p.sale_id=s.id
             GROUP BY b.local_date ORDER BY b.local_date",
            [...$bucketParams, ...$scopeParams],
        )->getResultArray();

        $creditRows = $this->database->query(
            "SELECT b.local_date,
                COALESCE(SUM(CASE WHEN s.kind='SALE' THEN s.credit_created_amount_snapshot ELSE 0.00 END),0.00) credit_created
             FROM ({$bucketSql}) b
             LEFT JOIN sales s ON s.tenant_id=? AND s.shop_id=? AND s.status='POSTED'
                AND s.posted_at>=b.utc_start AND s.posted_at<b.utc_end
             GROUP BY b.local_date ORDER BY b.local_date",
            [...$bucketParams, ...$scopeParams],
        )->getResultArray();

        $creditAdjustmentRows = $this->database->query(
            "SELECT b.local_date,
                COALESCE(SUM(CASE WHEN a.amount_delta>0 THEN a.amount_delta ELSE 0.00 END),0.00) credit_adjustments_increase,
                COALESCE(SUM(CASE WHEN a.amount_delta<0 THEN -a.amount_delta ELSE 0.00 END),0.00) credit_adjustments_decrease
             FROM ({$bucketSql}) b
             LEFT JOIN sales s ON s.tenant_id=? AND s.shop_id=? AND s.status='POSTED'
                AND s.posted_at>=b.utc_start AND s.posted_at<b.utc_end
             LEFT JOIN receivable_adjustments a ON a.tenant_id=s.tenant_id AND a.shop_id=s.shop_id AND a.sale_id=s.id
             GROUP BY b.local_date ORDER BY b.local_date",
            [...$bucketParams, ...$scopeParams],
        )->getResultArray();

        $profitVisible = $this->access->allows($context, 'sale.profit.view');
        $costRows = [];
        if ($profitVisible) {
            $costRows = $this->database->query(
                "SELECT b.local_date,
                    COALESCE(SUM(CASE WHEN s.kind='SALE' THEN i.line_cogs_amount ELSE 0.00 END),0.00) cost_of_sales_added,
                    COALESCE(SUM(CASE WHEN s.kind='RETURN' THEN i.line_cogs_amount ELSE 0.00 END),0.00) cost_of_returns_and_cancellations
                 FROM ({$bucketSql}) b
                 LEFT JOIN sales s ON s.tenant_id=? AND s.shop_id=? AND s.status='POSTED'
                    AND s.posted_at>=b.utc_start AND s.posted_at<b.utc_end
                 LEFT JOIN sale_items i ON i.tenant_id=s.tenant_id AND i.shop_id=s.shop_id AND i.sale_id=s.id
                 GROUP BY b.local_date ORDER BY b.local_date",
                [...$bucketParams, ...$scopeParams],
            )->getResultArray();
        }

        $daily = [];
        foreach ($dates as $date) {
            $daily[$date] = $this->emptyDay($date);
        }
        foreach ($salesRows as $row) {
            $daily[$row['local_date']]['sales_added'] = (string) $row['sales_added'];
            $daily[$row['local_date']]['returns_and_cancellations'] = (string) $row['returns_and_cancellations'];
        }
        foreach ($paymentRows as $row) {
            foreach (['cash_received', 'cash_refunded', 'transfer_received', 'transfer_refunded'] as $field) {
                $daily[$row['local_date']][$field] = (string) $row[$field];
            }
        }
        foreach ($creditRows as $row) {
            $daily[$row['local_date']]['credit_created'] = (string) $row['credit_created'];
        }
        foreach ($creditAdjustmentRows as $row) {
            $daily[$row['local_date']]['credit_adjustments_increase'] = (string) $row['credit_adjustments_increase'];
            $daily[$row['local_date']]['credit_adjustments_decrease'] = (string) $row['credit_adjustments_decrease'];
        }
        foreach ($costRows as $row) {
            $daily[$row['local_date']]['cost_of_sales_added'] = (string) $row['cost_of_sales_added'];
            $daily[$row['local_date']]['cost_of_returns_and_cancellations'] = (string) $row['cost_of_returns_and_cancellations'];
        }

        $daily = array_values(array_map(fn (array $row): array => $this->derive($row, $profitVisible), $daily));
        $summary = $this->sumRows($daily, $profitVisible);
        $series = $groupBy === 'MONTH' ? $this->byMonth($daily, $profitVisible) : $daily;

        return [
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'group_by' => $groupBy,
                'timezone_name' => $timezoneName,
            ],
            'currency_code' => (string) ($shop['currency_code'] ?: 'NGN'),
            'profit_visible' => $profitVisible,
            'summary' => $summary,
            'series' => $series,
            'refreshed_at' => gmdate('Y-m-d H:i:s'),
            'profit_explanation' => $profitVisible
                ? 'Gross profit is sales after returns minus the saved cost of the products sold. Shop expenses have not been taken away.'
                : null,
            'profit_cost_note' => $profitVisible
                ? 'This uses the product costs saved when each sale was recorded. If a product had no known cost then, the figure may be incomplete.'
                : null,
        ];
    }

    private function date(mixed $value, string $field, DateTimeImmutable $default, DateTimeZone $timezone): DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new ApiException('REPORT_DATE_INVALID', 'Enter a date in the calendar.', 422, [
                $field => ['Use the date picker to choose a valid date.'],
            ]);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new ApiException('REPORT_DATE_INVALID', 'Enter a real calendar date.', 422, [
                $field => ['Choose a real date from the calendar.'],
            ]);
        }
        return $date;
    }

    /** @return array{0:string,1:list<string>,2:list<string>} */
    private function buckets(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $parts = [];
        $params = [];
        $dates = [];
        $utc = new DateTimeZone('UTC');
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $next = $day->modify('+1 day');
            $dates[] = $day->format('Y-m-d');
            $parts[] = 'SELECT CAST(? AS DATE) local_date, CAST(? AS DATETIME(6)) utc_start, CAST(? AS DATETIME(6)) utc_end';
            $params[] = $day->format('Y-m-d');
            $params[] = $day->setTimezone($utc)->format('Y-m-d H:i:s.u');
            $params[] = $next->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }
        return [implode(' UNION ALL ', $parts), $params, $dates];
    }

    private function emptyDay(string $date): array
    {
        return [
            'period_start' => $date,
            'sales_added' => '0.00',
            'returns_and_cancellations' => '0.00',
            'cash_received' => '0.00',
            'cash_refunded' => '0.00',
            'transfer_received' => '0.00',
            'transfer_refunded' => '0.00',
            'credit_created' => '0.00',
            'credit_adjustments_increase' => '0.00',
            'credit_adjustments_decrease' => '0.00',
            'cost_of_sales_added' => '0.00',
            'cost_of_returns_and_cancellations' => '0.00',
        ];
    }

    private function derive(array $row, bool $profitVisible): array
    {
        $row['net_sales'] = SaleMoney::difference($row['sales_added'], $row['returns_and_cancellations']);
        $row['cash_net'] = SaleMoney::difference($row['cash_received'], $row['cash_refunded']);
        $row['transfer_net'] = SaleMoney::difference($row['transfer_received'], $row['transfer_refunded']);
        $row['credit_net'] = SaleMoney::difference(
            SaleMoney::sum([$row['credit_created'], $row['credit_adjustments_increase']]),
            $row['credit_adjustments_decrease'],
        );
        $positiveMix = SaleMoney::sum([
            $row['cash_received'],
            $row['transfer_received'],
            $row['credit_created'],
            $row['credit_adjustments_increase'],
        ]);
        $negativeMix = SaleMoney::sum([
            $row['cash_refunded'],
            $row['transfer_refunded'],
            $row['credit_adjustments_decrease'],
        ]);
        $row['payment_mix_total'] = SaleMoney::difference($positiveMix, $negativeMix);
        $row['payment_mix_matches_sales'] = $row['payment_mix_total'] === $row['net_sales'];
        if ($profitVisible) {
            $row['cost_of_sales_net'] = SaleMoney::difference($row['cost_of_sales_added'], $row['cost_of_returns_and_cancellations']);
            $row['gross_profit'] = SaleMoney::difference(
                SaleMoney::sum([$row['sales_added'], $row['cost_of_returns_and_cancellations']]),
                SaleMoney::sum([$row['returns_and_cancellations'], $row['cost_of_sales_added']]),
            );
        } else {
            unset($row['cost_of_sales_added'], $row['cost_of_returns_and_cancellations']);
        }
        return $row;
    }

    private function sumRows(array $rows, bool $profitVisible): array
    {
        $fields = [
            'sales_added',
            'returns_and_cancellations',
            'cash_received',
            'cash_refunded',
            'transfer_received',
            'transfer_refunded',
            'credit_created',
            'credit_adjustments_increase',
            'credit_adjustments_decrease',
        ];
        if ($profitVisible) {
            $fields[] = 'cost_of_sales_added';
            $fields[] = 'cost_of_returns_and_cancellations';
        }
        $sum = ['period_start' => null];
        foreach ($fields as $field) {
            $sum[$field] = SaleMoney::sum(array_column($rows, $field));
        }
        return $this->derive($sum, $profitVisible);
    }

    private function byMonth(array $rows, bool $profitVisible): array
    {
        $months = [];
        foreach ($rows as $row) {
            $key = substr($row['period_start'], 0, 7);
            if (! isset($months[$key])) {
                $months[$key] = $this->emptyDay($key);
            }
            foreach (array_keys($months[$key]) as $field) {
                if ($field === 'period_start') {
                    continue;
                }
                if (array_key_exists($field, $row)) {
                    $months[$key][$field] = SaleMoney::sum([$months[$key][$field], $row[$field]]);
                }
            }
        }
        return array_values(array_map(fn (array $row): array => $this->derive($row, $profitVisible), $months));
    }
}
