<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Read-only customer balance report built from the existing Receivable evidence projection. */
final class CustomerDebtReportQuery
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {}

    public function report(ShopContext $context, array $input): array
    {
        $this->access->read($context, 'report.view');
        $this->access->read($context, 'customer.view');
        $this->access->read($context, 'debt.view');
        $this->only($input, ['search', 'status', 'customer_status', 'page', 'per_page']);

        $status = $input['status'] ?? 'OWING';
        if (!is_string($status) || !in_array($status, ['ALL', 'OWING', 'OUTSTANDING', 'PARTIAL', 'SETTLED', 'OVERDUE'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose which customer balances to show.', 422);
        }
        $customerStatus = $input['customer_status'] ?? 'ALL';
        if (!is_string($customerStatus) || !in_array($customerStatus, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose which customer records to show.', 422);
        }
        $search = $input['search'] ?? '';
        if (!is_string($search) || mb_strlen(trim($search)) > 160) {
            throw new ApiException('VALIDATION_FAILED', 'Use a shorter customer name.', 422);
        }
        $search = trim($search);
        [$page, $size] = $this->paginationInput($input);
        (new DebtSchema($this->database))->requireReady();
        foreach (['customers', 'receivables', 'receivable_adjustments'] as $table) {
            if (!$this->database->tableExists($table)) {
                throw new ApiException('DEBT_SETUP_REQUIRED', 'Customer balances are not ready. Ask the shop owner to complete the approved application update.', 503);
            }
        }

        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $status, $customerStatus, $search, $page, $size): array {
            $shop = $this->database->table('shops')->select('timezone_name,currency_code')
                ->where(['tenant_id' => $context->tenantId, 'id' => $context->shopId])->get()->getRowArray();
            if (!$shop) {
                throw new ApiException('RESOURCE_NOT_FOUND', 'This Shop report is unavailable.', 404);
            }
            $timezoneName = (string) ($shop['timezone_name'] ?: 'Africa/Lagos');
            try {
                $timezone = new DateTimeZone($timezoneName);
            } catch (Throwable) {
                throw new ApiException('REPORT_TIMEZONE_INVALID', 'The Shop time setting needs to be checked before this report can be shown.', 409);
            }
            $asOf = UtcTimestamp::now();
            $now = (new DateTimeImmutable($asOf, new DateTimeZone('UTC')))->setTimezone($timezone);
            $shopDate = $now->format('Y-m-d');
            $refreshed = $now->format('Y-m-d H:i:s');

            [$balances, $balanceBinds] = (new ReceivableBalanceQuery())->projection($context, $shopDate);
            $where = 'c.tenant_id=?';
            $binds = [...$balanceBinds, $context->tenantId];
            if ($customerStatus !== 'ALL') {
                $where .= ' AND c.status=?';
                $binds[] = $customerStatus;
            }
            if ($search !== '') {
                // Literal display-name search only; private phone and notes are never searched.
                $where .= ' AND LOCATE(LOWER(?),LOWER(c.display_name))>0';
                $binds[] = $search;
            }
            $grouped = "SELECT c.id,c.public_id,c.display_name,c.status customer_status,
                COUNT(b.id) bill_count,
                COALESCE(SUM(b.original_amount),0.00) original_amount,
                COALESCE(SUM(b.adjustment_delta),0.00) adjustment_delta,
                COALESCE(SUM(b.adjusted_amount),0.00) adjusted_amount,
                COALESCE(SUM(b.collected_amount),0.00) collected_amount,
                COALESCE(SUM(b.outstanding_amount),0.00) outstanding_amount,
                COALESCE(SUM(CASE WHEN b.is_overdue=1 THEN b.outstanding_amount ELSE 0.00 END),0.00) overdue_amount,
                COALESCE(SUM(CASE WHEN b.is_overdue=1 THEN 1 ELSE 0 END),0) overdue_bill_count,
                MIN(CASE WHEN b.outstanding_amount>0 THEN b.due_date ELSE NULL END) next_due_date,
                COALESCE(SUM(b.invalid_balance),0) invalid_balance
                FROM customers c
                LEFT JOIN ($balances) b ON b.customer_id=c.id
                WHERE $where
                GROUP BY c.id,c.public_id,c.display_name,c.status";

            $integrity = $this->database->query(
                "SELECT COALESCE(SUM(invalid_balance),0) invalid_balance FROM ($grouped) checked",
                $binds,
            )->getRowArray();
            if ((int) ($integrity['invalid_balance'] ?? 0) > 0) {
                throw new ApiException('DEBT_EVIDENCE_INVALID', 'Some customer balances need checking. Ask the shop owner for help.', 409);
            }

            $statusSql = match ($status) {
                'OWING' => 'g.outstanding_amount>0',
                'OUTSTANDING' => 'g.outstanding_amount>0 AND g.collected_amount=0',
                'PARTIAL' => 'g.outstanding_amount>0 AND g.collected_amount>0',
                'SETTLED' => 'g.outstanding_amount=0 AND g.bill_count>0',
                'OVERDUE' => 'g.overdue_amount>0',
                default => '1=1',
            };
            $filtered = "SELECT g.*,
                CASE WHEN g.bill_count=0 THEN 'NO_DEBT'
                     WHEN g.outstanding_amount=0 THEN 'SETTLED'
                     WHEN g.collected_amount>0 THEN 'PARTIAL'
                     ELSE 'OUTSTANDING' END settlement_state
                FROM ($grouped) g
                WHERE $statusSql";

            $summary = $this->database->query(
                "SELECT COUNT(*) customer_count,
                    COALESCE(SUM(outstanding_amount),0.00) outstanding_amount,
                    COALESCE(SUM(overdue_amount),0.00) overdue_amount,
                    COALESCE(SUM(CASE WHEN overdue_amount>0 THEN 1 ELSE 0 END),0) overdue_customer_count,
                    COALESCE(SUM(CASE WHEN settlement_state='PARTIAL' THEN 1 ELSE 0 END),0) partially_paid_customer_count,
                    COALESCE(SUM(bill_count),0) bill_count
                 FROM ($filtered) totals",
                $binds,
            )->getRowArray();
            $total = (int) $summary['customer_count'];
            $rows = $this->database->query(
                "$filtered ORDER BY g.outstanding_amount DESC,g.display_name,g.id LIMIT ? OFFSET ?",
                [...$binds, $size, ($page - 1) * $size],
            )->getResultArray();

            $items = [];
            foreach ($rows as $row) {
                if ((int) ($row['invalid_balance'] ?? 0) > 0) {
                    throw new ApiException('DEBT_EVIDENCE_INVALID', 'A customer balance needs checking. Ask the shop owner for help.', 409);
                }
                $items[] = [
                    'public_id' => $row['public_id'],
                    'display_name' => $row['display_name'],
                    'customer_status' => $row['customer_status'],
                    'bill_count' => (int) $row['bill_count'],
                    'original_amount' => (string) $row['original_amount'],
                    'adjustment_delta' => (string) $row['adjustment_delta'],
                    'adjusted_amount' => (string) $row['adjusted_amount'],
                    'collected_amount' => (string) $row['collected_amount'],
                    'outstanding_amount' => (string) $row['outstanding_amount'],
                    'overdue_amount' => (string) $row['overdue_amount'],
                    'overdue_bill_count' => (int) $row['overdue_bill_count'],
                    'next_due_date' => $row['next_due_date'],
                    'settlement_state' => $row['settlement_state'],
                    'is_overdue' => (string) $row['overdue_amount'] !== '0.00',
                ];
            }

            return [
                'shop' => ['timezone_name' => $timezoneName, 'currency_code' => (string) $shop['currency_code']],
                'as_of' => $asOf,
                'shop_date' => $shopDate,
                'refreshed_at' => $refreshed,
                'filters' => ['status' => $status, 'customer_status' => $customerStatus, 'search' => $search],
                'summary' => [
                    'customer_count' => $total,
                    'bill_count' => (int) $summary['bill_count'],
                    'outstanding_amount' => (string) $summary['outstanding_amount'],
                    'overdue_amount' => (string) $summary['overdue_amount'],
                    'overdue_customer_count' => (int) $summary['overdue_customer_count'],
                    'partially_paid_customer_count' => (int) $summary['partially_paid_customer_count'],
                ],
                'items' => $items,
                'pagination' => $this->pagination($page, $size, $total),
            ];
        });
    }

    private function only(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new ApiException('VALIDATION_FAILED', 'Remove filters that this customer report does not support.', 422);
            }
        }
    }

    private function paginationInput(array $input): array
    {
        $values = [];
        foreach (['page' => [1, 100000], 'per_page' => [20, 50]] as $key => [$default, $maximum]) {
            $value = $input[$key] ?? $default;
            if ((!is_string($value) && !is_int($value))
                || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]) === false) {
                throw new ApiException('VALIDATION_FAILED', 'Choose a valid page and number of records.', 422);
            }
            $values[] = (int) $value;
        }
        return $values;
    }

    private function pagination(int $page, int $size, int $total): array
    {
        return ['page' => $page, 'per_page' => $size, 'total' => $total, 'total_pages' => (int) ceil($total / $size)];
    }
}
