<?php
declare(strict_types=1);

/**
 * Static WP2 source-contract checks only. Native MySQL, HTTP, browser,
 * accessibility, EXPLAIN and Owner manual acceptance remain separate.
 */
$root = dirname(__DIR__, 2);
$checks = 0;
$read = static function (string $path) use ($root): string {
    $file = $root . '/' . $path;
    if (! is_file($file)) {
        throw new RuntimeException('Missing WP2 source: ' . $path);
    }
    return (string) file_get_contents($file);
};
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
};

$stock = $read('app/Modules/Inventory/Infrastructure/StockReportQuery.php');
$debt = $read('app/Modules/CustomerDebt/Infrastructure/CustomerDebtReportQuery.php');
$reconciliation = $read('app/Modules/Reconciliation/Infrastructure/ReconciliationReportQuery.php');
$controller = $read('app/Controllers/Api/OperationalReportController.php');
$commercialController = $read('app/Controllers/Api/CommercialController.php');
$routes = $read('app/Config/Routes.php');
$shop = $read('app/Controllers/Api/ShopController.php');
$registry = $read('resources/js/shared/workspace-registry.js');
$app = $read('resources/js/shop/app.js');
$template = $read('resources/js/shop/template.html');
$panel = $read('resources/js/components/operational-reports-panel.js');
$products = $read('resources/js/components/products-panel.js');
$contract = $read('docs/Phase-8/Phase_8_WP2_Integration_Contracts.md');
$matrix = $read('docs/Phase-8/Phase_8_WP2_Source_Matrix.csv');
$manual = $read('docs/Phase-8/Phase_8_WP2_Manual_Results.csv');

$assert(str_contains($stock, "'report.view'"), 'Stock report requires report.view');
$assert(str_contains($stock, "'inventory.view'"), 'Stock report requires inventory.view');
$assert(str_contains($stock, 'expected_quantity<=sp.low_stock_threshold'), 'low stock uses persisted threshold derivation');
$assert(str_contains($stock, "cost_basis_state='VALID'"), 'confirmed valuation requires VALID cost basis');
$assert(str_contains($stock, "cost_basis_state='RETAINED'"), 'retained cost state is separately counted');
$assert(str_contains($stock, "cost_basis_state='UNESTABLISHED'"), 'unestablished cost state is separately counted');
$assert(str_contains($stock, 'DECIMAL(38,2)'), 'Stock value remains exact wide DECIMAL arithmetic');
$assert(str_contains($stock, 'ORDER BY CASE WHEN sp.expected_quantity<0'), 'Stock item order is deterministic');
$assert(str_contains($stock, 'LIMIT ? OFFSET ?'), 'Stock list pagination is bounded');
$assert(str_contains($stock, 'timezone_name'), 'Stock report returns Shop timezone and freshness');
$assert(str_contains($stock, 'stock_value_confirmed'), 'Stock report labels confirmed valuation separately');

$assert(str_contains($debt, "'report.view'"), 'Customer Debt report requires report.view');
$assert(str_contains($debt, "'customer.view'"), 'Customer Debt report requires customer.view');
$assert(str_contains($debt, "'debt.view'"), 'Customer Debt report requires debt.view');
$assert(str_contains($debt, 'ReceivableBalanceQuery())->projection'), 'Customer Debt uses authoritative Receivable projection');
$assert(str_contains($debt, 'DebtReadSnapshot'), 'Customer Debt count and page share the established read snapshot');
$assert(str_contains($debt, 'due_date'), 'Customer Debt uses established due-date evidence');
$assert(str_contains($debt, 'shopDate'), 'Customer Debt overdue uses Shop-local date');
$assert(str_contains($debt, 'DEBT_EVIDENCE_INVALID'), 'Contradictory debt evidence fails closed');
$assert(str_contains($debt, 'display_name'), 'Customer Debt output uses minimal display identity');
$assert(!str_contains($debt, 'phone'), 'Customer Debt report does not select Customer phone');

$assert(str_contains($reconciliation, "'report.view'"), 'Reconciliation report requires report.view');
$assert(str_contains($reconciliation, "'reconciliation.view'"), 'Reconciliation report requires reconciliation.view');
$assert(str_contains($reconciliation, "'reconciliation.review'"), 'Variance fields require independent review authority');
$assert(str_contains($reconciliation, 'BlindReadPolicy'), 'Reconciliation report reuses blind-read policy');
$assert(str_contains($reconciliation, "s.control_kind='STANDARD' AND s.status='FINALIZED'"), 'Only standard finalized checks are reported');
$assert(str_contains($reconciliation, 'amount_variance'), 'Cash variance uses persisted signed evidence');
$assert(str_contains($reconciliation, 'quantity_variance<0'), 'Stock shortage items preserve negative variance sign');
$assert(str_contains($reconciliation, 'quantity_variance>0'), 'Stock surplus items preserve positive variance sign');
$assert(str_contains($reconciliation, 'blocksScope'), 'Blind-count scope blocks sensitive variance fields');
$assert(str_contains($reconciliation, 'detail_hash'), 'Finalized report rows link to existing authorized detail');
$assert(str_contains($reconciliation, 'reconciliation_sessions'), 'Reconciliation report reads persisted sessions');

$assert(str_contains($controller, 'StockReportQuery'), 'Controller delegates Stock to Inventory owner');
$assert(str_contains($controller, 'CustomerDebtReportQuery'), 'Controller delegates debt to CustomerDebt owner');
$assert(str_contains($controller, 'ReconciliationReportQuery'), 'Controller delegates variances to Reconciliation owner');
$assert(str_contains($routes, "shop/reports/stock"), 'Stock report route is registered');
$assert(str_contains($routes, "shop/reports/customer-debt"), 'Customer Debt report route is registered');
$assert(str_contains($routes, "shop/reports/reconciliation"), 'Reconciliation report route is registered');
$assert(substr_count($routes, "['filter' => 'authenticated']") > 0 && str_contains($routes, "shop/reports/stock"), 'Report endpoints follow authenticated route convention');
$assert(str_contains($commercialController, 'Cache-Control'), 'Shared commercial controller owns report HTTP headers');
$assert(str_contains($commercialController, 'no-store, private'), 'Report responses are private and not cacheable');
$assert(str_contains($commercialController, 'ShopContext::resolve'), 'Report requests use server-resolved Shop context');
$assert(str_contains($shop, "'view_stock_report' => \$hasPermission('report.view') && \$hasPermission('inventory.view')"), 'Stock UI capability is permission-derived');
$assert(str_contains($shop, "'view_customer_debt_report' => \$hasPermission('report.view') && \$hasPermission('customer.view') && \$hasPermission('debt.view')"), 'Debt UI capability requires both source grants');
$assert(str_contains($shop, "'view_reconciliation_report' => \$hasPermission('report.view') && \$hasPermission('reconciliation.view')"), 'Reconciliation UI capability is permission-derived');
$assert(str_contains($registry, "capability: 'view_operational_reports'"), 'Shop reports navigation uses server capability');
$assert(str_contains($app, 'OperationalReportsPanel'), 'Shop app registers operational reports panel');
$assert(str_contains($template, 'OperationalReportsPanel'), 'Shop template mounts operational reports panel');
$assert(str_contains($panel, '/api/v1/shop/reports/stock'), 'UI calls Stock report endpoint');
$assert(str_contains($panel, '/api/v1/shop/reports/customer-debt'), 'UI calls Customer Debt report endpoint');
$assert(str_contains($panel, '/api/v1/shop/reports/reconciliation'), 'UI calls Reconciliation report endpoint');
$assert(str_contains($panel, 'You are offline'), 'UI has honest offline state');
$assert(!str_contains($panel, 'localStorage') && !str_contains($panel, 'indexedDB'), 'WP2 does not persist report data offline');
$assert(str_contains($products, "props.hash.startsWith('#products/')"), 'Stock report Product link uses existing detail view');
$assert(str_contains($contract, 'P8-13') && str_contains($contract, 'P8-24'), 'WP2 contract preserves manual case identities');
$assert(str_contains($matrix, 'P8-13') && str_contains($matrix, 'P8-24'), 'WP2 source matrix maps manual cases');
$assert(str_contains($manual, 'Not run'), 'WP2 manual register does not invent Pass results');

echo "PASS: {$checks} static Phase 8 WP2 source-contract assertions. Native database/HTTP/browser/accessibility/query-plan acceptance is separate.\n";
