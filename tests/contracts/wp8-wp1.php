<?php
declare(strict_types=1);

/**
 * Static WP1 source-contract checks only. These checks do not replace native
 * MySQL, CodeIgniter HTTP, browser, accessibility or Owner manual acceptance.
 */
$root = dirname(__DIR__, 2);
$checks = 0;
$read = static function (string $path) use ($root): string {
    $file = $root . '/' . $path;
    if (! is_file($file)) {
        throw new RuntimeException('Missing WP1 source: ' . $path);
    }
    return (string) file_get_contents($file);
};
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
};

$query = $read('app/Modules/Sales/Infrastructure/SalesReportQuery.php');
$controller = $read('app/Controllers/Api/SalesReportController.php');
$routes = $read('app/Config/Routes.php');
$shop = $read('app/Controllers/Api/ShopController.php');
$permissions = $read('app/Enums/PermissionCode.php');
$registry = $read('resources/js/shared/workspace-registry.js');
$history = $read('resources/js/components/sale-history-panel.js');
$panel = $read('resources/js/components/sales-report-panel.js');
$contract = $read('docs/Phase-8/Phase_8_WP1_Integration_Contracts.md');

$assert(str_contains($query, "'report.view'"), 'report.view is enforced by the query');
$assert(str_contains($query, "'sale.profit.view'"), 'profit permission is independent');
$assert(str_contains($query, 'self::MAX_DAYS = 366'), 'date range is bounded');
$assert(str_contains($query, 'timezone_name'), 'Shop timezone is server-derived');
$assert(str_contains($query, 'posted_at>=b.utc_start AND s.posted_at<b.utc_end'), 'Shop-local dates use half-open posted_at bounds');
$assert(str_contains($query, 'sale_payments'), 'Cash and Transfer use posted payment evidence');
$assert(str_contains($query, 'credit_created_amount_snapshot'), 'Credit uses Sale snapshot evidence');
$assert(str_contains($query, 'receivable_adjustments'), 'Credit changes use linked Receivable adjustments');
$assert(str_contains($query, 'line_cogs_amount'), 'Gross Profit uses historical Sale Item cost');
$assert(str_contains($query, 'SaleMoney::difference'), 'money arithmetic uses exact decimal helpers');
$assert(str_contains($query, 'Cache-Control') === false, 'query layer does not own HTTP headers');
$assert(str_contains($controller, 'SalesReportQuery'), 'transport controller calls the Sales-owned query');
$assert(str_contains($routes, "shop/reports/sales"), 'report route is registered');
$assert(str_contains($shop, "'view_sales_workspace' => $hasPermission('sale.view') || $hasPermission('report.view')"), 'Sales workspace is visible to history or report viewers');
$assert(str_contains($permissions, "case ReportView = 'report.view'"), 'existing report permission remains the authority');
$assert(str_contains($registry, "capability: 'view_sales_workspace'"), 'navigation uses server capability hint');
$assert(str_contains($registry, "'#sales-report'"), 'Sales summary hash is routed within existing Sales workspace');
$assert(str_contains($history, "props.permissions.includes('sale.view')"), 'report-only users do not gain Sales history');
$assert(str_contains($panel, 'Money left after product costs'), 'plain-language Gross Profit label exists');
$assert(str_contains($panel, 'Credit added to customer bills'), 'Credit is explained in everyday language');
$assert(str_contains($panel, 'Last updated:'), 'report freshness is displayed');
$assert(str_contains($contract, 'Debt collection is not joined into this report as new revenue'), 'debt collection is excluded from Sales');

echo "PASS: {$checks} static Phase 8 WP1 source-contract assertions. Native database/HTTP/browser acceptance is separate.\n";
