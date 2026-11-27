<?php
declare(strict_types=1);

/**
 * Static WP3 source-contract checks only. Native MySQL, HTTP, browser,
 * accessibility, query-plan and Owner manual acceptance remain separate.
 */
$root = dirname(__DIR__, 2);
$checks = 0;
$read = static function (string $path) use ($root): string {
    $file = $root . '/' . $path;
    if (! is_file($file)) {
        throw new RuntimeException('Missing WP3 source: ' . $path);
    }
    return (string) file_get_contents($file);
};
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
};

$dashboard = $read('resources/js/components/operational-dashboard-panel.js');
$home = $read('resources/js/components/workspace-home.js');
$salesHistory = $read('resources/js/components/sale-history-panel.js');
$stockQuery = $read('app/Modules/Inventory/Infrastructure/StockReportQuery.php');
$debtQuery = $read('app/Modules/CustomerDebt/Infrastructure/CustomerDebtReportQuery.php');
$reconciliationQuery = $read('app/Modules/Reconciliation/Infrastructure/ReconciliationReportQuery.php');
$salesQuery = $read('app/Modules/Sales/Infrastructure/SalesReportQuery.php');
$routes = $read('app/Config/Routes.php');
$filters = $read('app/Config/Filters.php');
$throttle = $read('app/Filters/SensitiveRateLimitFilter.php');
$shop = $read('app/Controllers/Api/ShopController.php');
$css = $read('resources/css/app.scss');
$contract = $read('docs/Phase-8/Phase_8_WP3_Composition_Contract.md');
$matrix = $read('docs/Phase-8/Phase_8_WP3_Source_Matrix.csv');
$manual = $read('docs/Phase-8/Phase_8_WP3_Manual_Results.csv');
$guide = $read('docs/Phase-8/Phase_8_WP3_Manual_Verification_Guide.md');
$performance = $read('docs/Phase-8/Phase_8_WP3_Performance_Notes.md');
$operations = $read('docs/Phase-8/Phase_8_WP3_Operations_Handover.md');
$reconciliation = $read('app/Modules/Reconciliation/Infrastructure/ReconciliationReportQuery.php');

$assert(str_contains($dashboard, "'/api/v1/shop/reports/sales'"), 'Dashboard consumes accepted Sales report API');
$assert(str_contains($dashboard, "'/api/v1/shop/reports/stock'"), 'Dashboard consumes accepted Stock report API');
$assert(str_contains($dashboard, "'/api/v1/shop/reports/customer-debt'"), 'Dashboard consumes accepted Customer Debt report API');
$assert(str_contains($dashboard, "'/api/v1/shop/reports/reconciliation'"), 'Dashboard consumes accepted Reconciliation report API');
$assert(str_contains($dashboard, "props.permissions.includes('report.view')"), 'Dashboard Sales family requires report.view');
$assert(str_contains($dashboard, 'view_stock_report === true'), 'Stock card uses existing source capability');
$assert(str_contains($dashboard, 'view_customer_debt_report === true'), 'Debt card uses existing source capability');
$assert(str_contains($dashboard, 'view_reconciliation_report === true'), 'Reconciliation card uses existing source capability');
$assert(str_contains($dashboard, 'data.profit_visible'), 'Gross Profit card respects WP1 profit masking');
$assert(str_contains($dashboard, 'data.variance_visibility'), 'Reconciliation card respects WP2 variance masking');
$assert(str_contains($dashboard, "per_page: '20'"), 'WP2 dashboard requests use the supported report page size');
$assert(str_contains($dashboard, "period: 'CUSTOM'") && str_contains($dashboard, 'from_date: start') && str_contains($dashboard, 'to_date: end'), 'Sales dashboard sends the accepted custom-period query');
$assert(str_contains($dashboard, "period: 'TODAY'"), 'Sales dashboard uses the accepted default period');
$assert(str_contains($dashboard, 'data.sales') && str_contains($dashboard, 'data.payments') && str_contains($dashboard, 'data.gross_profit.amount'), 'Sales dashboard reads the authoritative API response fields');
$assert(str_contains($dashboard, "'#sales-reports?'"), 'Sales drill-down preserves the selected custom date range');

$assert(str_contains($dashboard, 'new AbortController()'), 'Dashboard requests are cancellable');
$assert(str_contains($dashboard, "addEventListener('offline'"), 'Dashboard handles offline transition');
$assert(str_contains($dashboard, "addEventListener('online'"), 'Dashboard refreshes when connection returns');
$assert(str_contains($dashboard, 'state.stale'), 'Online network failures label retained figures stale');
$assert(str_contains($dashboard, 'Last refreshed:'), 'Each dashboard card shows source freshness');
$assert(str_contains($dashboard, 'Current Stock position'), 'Stock card is explicitly a current snapshot');
$assert(str_contains($dashboard, 'Current customer balances'), 'Debt card is explicitly a current snapshot');
$assert(str_contains($dashboard, 'Finalized Cash, Stock and combined checks'), 'Reconciliation card describes finalized evidence');
$assert(str_contains($dashboard, '#sales-report'), 'Sales drill-down route is preserved');
$assert(str_contains($dashboard, 'URLSearchParams'), 'Sales drill-down carries the selected period');
$assert(str_contains($home, 'OperationalDashboardPanel'), 'Dashboard is integrated into existing Shop Home');
$assert(strpos($home, "'ui-home-intro'") < strpos($home, 'canViewReports.value && h(OperationalDashboardPanel'), 'Personal greeting is the first Home section');
$assert(strpos($home, 'canViewReports.value && h(OperationalDashboardPanel') < strpos($home, 'unfinishedSalesSection(),'), 'Shop overview follows greeting and precedes unfinished sales');
$assert(strpos($home, 'unfinishedSalesSection()') < strpos($home, 'quickActionsSection()'), 'Unfinished sales precede quick actions');
$assert(str_contains($home, "'What would you like to do?'"), 'Quick-action section uses the requested heading');
$assert(strpos($home, 'canViewReports.value && h(OperationalDashboardPanel') < strpos($home, 'unfinishedSalesSection(),'), 'Shop overview renders before unfinished sales');
$assert(strpos($home, 'unfinishedSalesSection()') < strpos($home, 'quickActionsSection()'), 'Unfinished sales render before quick actions');
$assert(str_contains($reconciliation, '$binds = [];') && str_contains($reconciliation, 'The variance subquery is first in SQL, followed by the outer Shop scope.'), 'Reconciliation list query binds only its actual SQL placeholders');
$assert(str_contains($home, "props.context.permissions.includes('report.view')"), 'Shop Home only mounts dashboard for report viewers');
$assert(str_contains($salesHistory, "location.hash.startsWith('#sales-reports')"), 'Sales history accepts a period-bearing report hash');
$assert(str_contains($salesHistory, "selected.value ? h('div', { class: 'trading-stack' }, ["), 'Sales history has no undefined Sales report render branch');
$salesReports = $read('resources/js/components/sales-reports-panel.js');
$assert(str_contains($salesReports, "hashParams.get('from_date')") && str_contains($salesReports, "hashParams.get('to_date')"), 'Sales report reads dashboard-selected dates');
$assert(str_contains($salesReports, "hashParams.get('from_date')") && str_contains($salesReports, "hashParams.get('to_date')"), 'Sales report initializes the carried custom date range');
$assert(str_contains($stockQuery, "'report.view'") && str_contains($debtQuery, "'report.view'") && str_contains($reconciliationQuery, "'report.view'") && str_contains($salesQuery, "'report.view'"), 'All source report queries independently authorize report.view');
$assert(str_contains($shop, "'view_stock_report'") && str_contains($shop, "'view_customer_debt_report'") && str_contains($shop, "'view_reconciliation_report'"), 'Dashboard relies on existing server capabilities');
$assert(!str_contains($dashboard, 'localStorage') && !str_contains($dashboard, 'indexedDB'), 'Dashboard does not persist report data locally');
$assert(!str_contains($dashboard, 'setInterval') && !str_contains($dashboard, 'setTimeout'), 'Dashboard adds no polling or retry timer');
$assert(str_contains($filters, "'api/v1/shop/reports/*'"), 'All report GET paths receive the established throttle filter');
$assert(str_contains($throttle, '&& ! $isReportQuery'), 'Report GET requests are not skipped by the sensitive throttle');
$assert(str_contains($throttle, 'return [20, 60]'), 'Report GET requests have a bounded per-user rate limit');
$assert(str_contains($throttle, "return '/api/v1/shop/reports'"), 'All report routes share one per-user throttle bucket');
$assert(str_contains($routes, "shop/reports/sales") && str_contains($routes, "shop/reports/stock") && str_contains($routes, "shop/reports/customer-debt") && str_contains($routes, "shop/reports/reconciliation"), 'All four source report routes are registered');
$assert(str_contains($routes, 'OperationalReportController::stock') && str_contains($routes, 'OperationalReportController::customerDebt') && str_contains($routes, 'OperationalReportController::reconciliation'), 'WP2 report routes target the operational report controller methods');
$assert(str_contains($salesQuery, 'DraftInput::only($input, ['), 'Dashboard Sales request matches the authoritative query input allowlist');
$assert(str_contains($stockQuery, "['page' => [1, 100000], 'per_page' => [20, 50]]") && str_contains($debtQuery, "['page' => [1, 100000], 'per_page' => [20, 50]]") && str_contains($reconciliationQuery, "['page' => [1, 100000], 'per_page' => [20, 50]]"), 'Dashboard WP2 page size is accepted by all three source queries');
$assert(!preg_match('/shop\/(?:dashboard|export)(?:[\'\/]|\s)/i', $routes), 'WP3 adds no dashboard or export route');
$assert(str_contains($css, '.dashboard-grid') && str_contains($css, '@media(max-width:35rem)'), 'Dashboard has responsive grid and phone layout');
$assert(str_contains($contract, 'Export is not approved and is not implemented'), 'Export disposition is documented');
$assert(str_contains($contract, 'not one database transaction'), 'Independent source freshness is documented honestly');
$assert(str_contains($matrix, 'P8-25') && str_contains($matrix, 'P8-37'), 'WP3 source matrix maps all reserved manual cases');
$assert(str_contains($manual, 'P8-25') && str_contains($manual, 'P8-37') && str_contains($manual, 'Not run'), 'WP3 manual register preserves unrun acceptance cases');
$assert(str_contains($guide, 'P8-34') && str_contains($guide, 'EXPLAIN'), 'WP3 manual guide covers export disposition and query plans');
$assert(str_contains($performance, 'No EXPLAIN or EXPLAIN ANALYZE output has been captured yet'), 'WP3 performance evidence is honestly marked pending');
$assert(str_contains($operations, '20 requests per 60 seconds'), 'WP3 operations handover documents report rate limits');

echo "PASS: {$checks} static Phase 8 WP3 source-contract assertions. Native database/HTTP/browser/accessibility/query-plan acceptance is separate.\n";
