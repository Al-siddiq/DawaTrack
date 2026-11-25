<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Read-only, current-Shop Stock report. Inventory remains the sole owner of Stock meaning. */
final class StockReportQuery
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {}

    public function report(ShopContext $context, array $input): array
    {
        $this->access->read($context, 'report.view');
        $this->access->read($context, 'inventory.view');
        $this->only($input, ['search', 'state', 'page', 'per_page']);

        $state = $input['state'] ?? 'ALL';
        if (!is_string($state) || !in_array($state, ['ALL', 'LOW', 'NEGATIVE', 'NORMAL', 'RETAINED', 'UNESTABLISHED'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose a valid Stock filter.', 422);
        }
        $search = $input['search'] ?? '';
        if (!is_string($search) || mb_strlen(trim($search)) > 100) {
            throw new ApiException('VALIDATION_FAILED', 'Use a shorter Product name.', 422);
        }
        $search = trim($search);
        [$page, $size] = $this->paginationInput($input);

        foreach (['stock_positions', 'products'] as $table) {
            if (!$this->database->tableExists($table)) {
                throw new ApiException('STOCK_REPORT_SETUP_REQUIRED', 'Stock information is not ready. Ask the administrator to check the approved application update.', 503);
            }
        }

        return $this->snapshot(function () use ($context, $state, $search, $page, $size): array {
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

            $scope = [$context->tenantId, $context->shopId];
            $summary = $this->database->query(<<<'SQL'
SELECT
 COUNT(*) product_count,
 COALESCE(SUM(CASE WHEN sp.expected_quantity<=sp.low_stock_threshold THEN 1 ELSE 0 END),0) low_stock_count,
 COALESCE(SUM(CASE WHEN sp.expected_quantity<0 THEN 1 ELSE 0 END),0) negative_stock_count,
 COALESCE(SUM(CASE WHEN sp.cost_basis_state='VALID' AND sp.expected_quantity>0 THEN 1 ELSE 0 END),0) valued_product_count,
 COALESCE(SUM(CASE WHEN sp.cost_basis_state='RETAINED' THEN 1 ELSE 0 END),0) retained_cost_product_count,
 COALESCE(SUM(CASE WHEN sp.cost_basis_state='UNESTABLISHED' THEN 1 ELSE 0 END),0) unestablished_cost_product_count,
 CAST(ROUND(COALESCE(SUM(CASE WHEN sp.cost_basis_state='VALID' AND sp.expected_quantity>0
      THEN CAST(sp.expected_quantity AS DECIMAL(38,4))*CAST(sp.average_unit_cost AS DECIMAL(38,6))
      ELSE 0 END),0),2) AS DECIMAL(38,2)) stock_value_confirmed
FROM stock_positions sp
JOIN products p ON p.tenant_id=sp.tenant_id AND p.id=sp.product_id
WHERE sp.tenant_id=? AND sp.shop_id=?
SQL, $scope)->getRowArray();

            $where = 'sp.tenant_id=? AND sp.shop_id=?';
            $binds = $scope;
            $stateSql = match ($state) {
                'LOW' => 'sp.expected_quantity<=sp.low_stock_threshold',
                'NEGATIVE' => 'sp.expected_quantity<0',
                'NORMAL' => 'sp.expected_quantity>=0 AND sp.expected_quantity>sp.low_stock_threshold',
                'RETAINED' => "sp.cost_basis_state='RETAINED'",
                'UNESTABLISHED' => "sp.cost_basis_state='UNESTABLISHED'",
                default => '1=1',
            };
            if ($stateSql !== '1=1') {
                $where .= ' AND ' . $stateSql;
            }
            if ($search !== '') {
                $where .= ' AND LOCATE(LOWER(?),LOWER(p.name))>0';
                $binds[] = $search;
            }
            $total = (int) $this->database->query(
                "SELECT COUNT(*) total FROM stock_positions sp JOIN products p ON p.tenant_id=sp.tenant_id AND p.id=sp.product_id WHERE $where",
                $binds,
            )->getRowArray()['total'];

            $rows = $this->database->query(
                "SELECT p.public_id,p.name,p.unit_name,p.status product_status,
                    sp.expected_quantity,sp.low_stock_threshold,sp.cost_basis_state,
                    CASE WHEN sp.expected_quantity<=sp.low_stock_threshold THEN 1 ELSE 0 END is_low_stock,
                    CASE WHEN sp.expected_quantity<0 THEN 1 ELSE 0 END is_negative_stock,
                    CASE WHEN sp.cost_basis_state='VALID' AND sp.expected_quantity>0 THEN CAST(sp.average_unit_cost AS CHAR) ELSE NULL END average_unit_cost,
                    CASE WHEN sp.cost_basis_state='VALID' AND sp.expected_quantity>0
                         THEN CAST(ROUND(sp.expected_quantity*sp.average_unit_cost,2) AS DECIMAL(38,2))
                         ELSE NULL END product_stock_value
                 FROM stock_positions sp
                 JOIN products p ON p.tenant_id=sp.tenant_id AND p.id=sp.product_id
                 WHERE $where
                 ORDER BY CASE WHEN sp.expected_quantity<0 THEN 0 WHEN sp.expected_quantity<=sp.low_stock_threshold THEN 1 ELSE 2 END,
                          p.name,p.id
                 LIMIT ? OFFSET ?",
                [...$binds, $size, ($page - 1) * $size],
            )->getResultArray();

            $retained = (int) $summary['retained_cost_product_count'];
            $unestablished = (int) $summary['unestablished_cost_product_count'];
            $negative = (int) $summary['negative_stock_count'];
            return [
                'shop' => ['timezone_name' => $timezoneName, 'currency_code' => (string) $shop['currency_code']],
                'as_of' => $asOf,
                'refreshed_at' => $refreshed,
                'summary' => [
                    'product_count' => (int) $summary['product_count'],
                    'low_stock_count' => (int) $summary['low_stock_count'],
                    'negative_stock_count' => $negative,
                    'valued_product_count' => (int) $summary['valued_product_count'],
                    'retained_cost_product_count' => $retained,
                    'unestablished_cost_product_count' => $unestablished,
                    'stock_value_confirmed' => (string) $summary['stock_value_confirmed'],
                    'valuation_complete' => $retained === 0 && $unestablished === 0 && $negative === 0,
                ],
                'filters' => ['state' => $state, 'search' => $search],
                'items' => array_map(static fn(array $row): array => [
                    'product_public_id' => $row['public_id'],
                    'name' => $row['name'],
                    'unit_name' => $row['unit_name'],
                    'product_status' => $row['product_status'],
                    'expected_quantity' => (string) $row['expected_quantity'],
                    'low_stock_threshold' => (string) $row['low_stock_threshold'],
                    'cost_basis_state' => $row['cost_basis_state'],
                    'average_unit_cost' => $row['average_unit_cost'] === null ? null : (string) $row['average_unit_cost'],
                    'stock_value' => $row['product_stock_value'] === null ? null : (string) $row['product_stock_value'],
                    'is_low_stock' => (bool) $row['is_low_stock'],
                    'is_negative_stock' => (bool) $row['is_negative_stock'],
                ], $rows),
                'pagination' => $this->pagination($page, $size, $total),
            ];
        });
    }

    private function only(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new ApiException('VALIDATION_FAILED', 'Remove filters that this Stock report does not support.', 422);
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

    private function snapshot(callable $query): array
    {
        if ($this->database->transDepth > 0) {
            return $query();
        }
        $this->database->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $this->database->transException(true);
        if (!$this->database->transBegin()) {
            throw new \RuntimeException('The Stock report could not start.');
        }
        try {
            $result = $query();
            if (!$this->database->transCommit()) {
                throw new \RuntimeException('The Stock report could not finish.');
            }
            return $result;
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }
}
