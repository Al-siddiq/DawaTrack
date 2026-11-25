<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\CustomerDebt\Infrastructure\DebtReadSnapshot;
use App\Modules\Reconciliation\Application\BlindReadPolicy;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Read-only summaries of finalized Reconciliation evidence; never reconstructs historical results. */
final class ReconciliationReportQuery
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {}

    public function report(ShopContext $context, array $input): array
    {
        $this->access->read($context, 'report.view');
        $this->access->read($context, 'reconciliation.view');
        $this->only($input, ['scope', 'page', 'per_page']);

        $scope = $input['scope'] ?? 'ALL';
        if (!is_string($scope) || !in_array($scope, ['ALL', 'CASH', 'STOCK', 'FULL'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose Cash, Stock, Cash and stock, or All checks.', 422);
        }
        [$page, $size] = $this->paginationInput($input);

        (new CashControlSchema($this->database))->requireReady();
        (new StockControlSchema($this->database))->requireReady();

        $review = $this->access->allows($context, 'reconciliation.review');
        $blind = new BlindReadPolicy($this->database);
        $cashVisible = $review && !$blind->blocksScope($context, 'CASH');
        $stockVisible = $review && !$blind->blocksScope($context, 'STOCK');

        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $scope, $page, $size, $cashVisible, $stockVisible): array {
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
            $refreshed = (new DateTimeImmutable($asOf, new DateTimeZone('UTC')))->setTimezone($timezone)->format('Y-m-d H:i:s');

            $scopeWhere = $scope === 'ALL' ? '' : ' AND s.scope=?';
            $scopeBinds = $scope === 'ALL' ? [] : [$scope];
            $baseWhere = "s.tenant_id=? AND s.shop_id=? AND s.control_kind='STANDARD' AND s.status='FINALIZED'$scopeWhere";
            $baseBinds = [$context->tenantId, $context->shopId, ...$scopeBinds];

            $summary = $this->database->query(
                "SELECT COUNT(*) finalized_count,
                    COALESCE(SUM(CASE WHEN s.scope='CASH' THEN 1 ELSE 0 END),0) cash_checks,
                    COALESCE(SUM(CASE WHEN s.scope='STOCK' THEN 1 ELSE 0 END),0) stock_checks,
                    COALESCE(SUM(CASE WHEN s.scope='FULL' THEN 1 ELSE 0 END),0) full_checks,
                    MAX(s.finalized_at) latest_finalized_at
                 FROM reconciliation_sessions s
                 WHERE $baseWhere",
                $baseBinds,
            )->getRowArray();

            $varianceSummary = [];
            if ($cashVisible) {
                $cash = $this->database->query(
                    "SELECT COUNT(DISTINCT s.id) variance_checks
                     FROM reconciliation_sessions s
                     JOIN reconciliation_variances v ON v.tenant_id=s.tenant_id AND v.shop_id=s.shop_id
                        AND v.reconciliation_session_id=s.id AND v.variance_kind='CASH' AND v.amount_variance<>0
                     WHERE $baseWhere",
                    $baseBinds,
                )->getRowArray();
                $varianceSummary['cash_variance_checks'] = (int) $cash['variance_checks'];
            }
            if ($stockVisible) {
                $stock = $this->database->query(
                    "SELECT COALESCE(SUM(CASE WHEN v.quantity_variance<0 THEN 1 ELSE 0 END),0) shortage_items,
                            COALESCE(SUM(CASE WHEN v.quantity_variance>0 THEN 1 ELSE 0 END),0) surplus_items
                     FROM reconciliation_sessions s
                     JOIN reconciliation_variances v ON v.tenant_id=s.tenant_id AND v.shop_id=s.shop_id
                        AND v.reconciliation_session_id=s.id AND v.variance_kind='STOCK' AND v.quantity_variance<>0
                     WHERE $baseWhere",
                    $baseBinds,
                )->getRowArray();
                $varianceSummary['stock_shortage_items'] = (int) $stock['shortage_items'];
                $varianceSummary['stock_surplus_items'] = (int) $stock['surplus_items'];
            }

            $select = 's.public_id,s.scope,s.cutoff_at,s.finalized_at';
            $join = '';
            $binds = [$context->tenantId, $context->shopId];
            if ($cashVisible || $stockVisible) {
                $columns = [];
                if ($cashVisible) {
                    $columns[] = "MAX(CASE WHEN v.variance_kind='CASH' THEN v.amount_variance ELSE NULL END) cash_variance_amount";
                    $select .= ',v.cash_variance_amount';
                }
                if ($stockVisible) {
                    $columns[] = "SUM(CASE WHEN v.variance_kind='STOCK' AND v.quantity_variance<0 THEN 1 ELSE 0 END) stock_shortage_items";
                    $columns[] = "SUM(CASE WHEN v.variance_kind='STOCK' AND v.quantity_variance>0 THEN 1 ELSE 0 END) stock_surplus_items";
                    $select .= ',COALESCE(v.stock_shortage_items,0) stock_shortage_items,COALESCE(v.stock_surplus_items,0) stock_surplus_items';
                }
                $join = ' LEFT JOIN (SELECT v.reconciliation_session_id,' . implode(',', $columns) . '
                    FROM reconciliation_variances v
                    WHERE v.tenant_id=? AND v.shop_id=?
                    GROUP BY v.reconciliation_session_id) v ON v.reconciliation_session_id=s.id';
                $binds = [$context->tenantId, $context->shopId, ...$binds];
            }
            $where = "s.tenant_id=? AND s.shop_id=? AND s.control_kind='STANDARD' AND s.status='FINALIZED'";
            $binds[] = $context->tenantId;
            $binds[] = $context->shopId;
            if ($scope !== 'ALL') {
                $where .= ' AND s.scope=?';
                $binds[] = $scope;
            }
            $rows = $this->database->query(
                "SELECT $select FROM reconciliation_sessions s$join
                 WHERE $where
                 ORDER BY s.finalized_at DESC,s.id DESC
                 LIMIT ? OFFSET ?",
                [...$binds, $size, ($page - 1) * $size],
            )->getResultArray();

            $items = [];
            foreach ($rows as $row) {
                $item = [
                    'public_id' => $row['public_id'],
                    'scope' => $row['scope'],
                    'cutoff_at' => $row['cutoff_at'],
                    'finalized_at' => $row['finalized_at'],
                    'detail_hash' => match ($row['scope']) {
                        'CASH' => '#cash-controls/' . $row['public_id'],
                        'STOCK' => '#stock-controls/' . $row['public_id'],
                        default => '#full-controls/' . $row['public_id'],
                    },
                ];
                if ($cashVisible) {
                    $amount = $row['cash_variance_amount'] ?? null;
                    $item['cash_variance_amount'] = $amount === null ? null : (string) $amount;
                    $item['has_cash_variance'] = $amount !== null && (string) $amount !== '0.00';
                }
                if ($stockVisible) {
                    $item['stock_shortage_items'] = (int) ($row['stock_shortage_items'] ?? 0);
                    $item['stock_surplus_items'] = (int) ($row['stock_surplus_items'] ?? 0);
                }
                $items[] = $item;
            }

            $total = (int) $summary['finalized_count'];
            return [
                'shop' => ['timezone_name' => $timezoneName, 'currency_code' => (string) $shop['currency_code']],
                'as_of' => $asOf,
                'refreshed_at' => $refreshed,
                'filters' => ['scope' => $scope],
                'variance_visibility' => ['cash' => $cashVisible, 'stock' => $stockVisible],
                'summary' => [
                    'finalized_count' => $total,
                    'cash_checks' => (int) $summary['cash_checks'],
                    'stock_checks' => (int) $summary['stock_checks'],
                    'full_checks' => (int) $summary['full_checks'],
                    'latest_finalized_at' => $summary['latest_finalized_at'],
                    ...$varianceSummary,
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
                throw new ApiException('VALIDATION_FAILED', 'Remove filters that this Reconciliation report does not support.', 422);
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
                throw new ApiException('VALIDATION_FAILED', 'Choose a valid page and number of checks.', 422);
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
