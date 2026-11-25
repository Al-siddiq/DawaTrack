<?php

declare(strict_types=1);

/**
 * Source-level contract guard for Phase 8 WP1.
 * This does not execute MySQL, CodeIgniter routing, browser behavior or native acceptance cases.
 */

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $file = $root . '/' . $path;
    if (!is_file($file)) {
        throw new RuntimeException('Required source file is missing: ' . $path);
    }
    return (string) file_get_contents($file);
};
$checks = 0;
$ok = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $checks++;
    echo "OK   " . $label . PHP_EOL;
};

$query = $read('app/Modules/Sales/Infrastructure/SalesReportQuery.php');
$controller = $read('app/Controllers/Api/SalesReportController.php');
$routes = $read('app/Config/Routes.php');
$shop = $read('app/Controllers/Api/ShopController.php');
$registry = $read('resources/js/shared/workspace-registry.js');
$template = $read('resources/js/shop/template.html');
$history = $read('resources/js/components/sale-history-panel.js');
$panel = $read('resources/js/components/sales-reports-panel.js');
$plain = $read('docs/ui/plain-english.md');
$costHistory = $read('app/Modules/Inventory/Application/CommercialCostHistory.php');

$ok(str_contains($query, "'report.view'"), 'Every report read requires report.view');
$ok(str_contains($query, "'sale.profit.view'"), 'Gross Profit has an independent sale.profit.view check');
$ok(str_contains($query, "s.posted_at>=? AND s.posted_at<?"), 'Sales use bounded posted_at Shop-period filtering');
$ok(str_contains($query, "new DateTimeZone(\$shop['timezone_name'])"), 'Shop timezone is used for calendar boundaries');
$ok(str_contains($query, "RETURN_REDUCTION','CORRECTION_DECREASE','REVERSAL"), 'Credit includes only Sale-owned return/cancellation adjustments');
$ok(!str_contains($query, 'debt_payment_allocations') && !str_contains($query, 'debt_payments'), 'Debt collection is not included as new Sales');
$ok(str_contains($query, "p.direction='RECEIPT'") && str_contains($query, "p.direction='REFUND'"), 'Cash and Transfer use signed receipt/refund evidence');
$ok(str_contains($query, 'line_cogs_amount') && str_contains($query, 'CommercialCostHistory') && str_contains($query, 'CostStateReconstructor'), 'Gross Profit uses historical Sale Item cost evidence');
$ok(str_contains($costHistory, 'chainsThrough') && str_contains($costHistory, 'array_chunk($targets, 500, true)'), 'Historical cost-state reads are batched in bounded product groups');
$ok(str_contains($query, 'linked_item.original_sale_item_id,item.original_sale_item_id,item.id') && str_contains($query, 'LIMIT 25001'), 'Historical cost lookup follows cancelled Return lines and caps target rows');
$ok(str_contains($query, 'REPEATABLE READ, READ ONLY'), 'Report calculations share one read-only snapshot');
$ok(str_contains($query, 'invalid_line_count') && str_contains($query, 'line_sales_amount<>total_amount'), 'Each saved Sale/Return item total is checked before aggregation');
$ok(str_contains($query, 'REPORT_RANGE_TOO_WIDE') && str_contains($query, '365'), 'Custom dates and historical cost checks are bounded');
$ok(preg_match('/\b(INSERT|UPDATE|DELETE|ALTER|DROP|TRUNCATE)\b/i', $query) === 0, 'Sales report query has no write or DDL statements');
$ok(str_contains($controller, 'SalesReportQuery') && str_contains($controller, 'getGet()'), 'Controller maps a GET-only report query');
$ok(str_contains($routes, "shop/reports/sales', 'Api\\SalesReportController::index"), 'Authenticated Sales report route is registered');
$ok(str_contains($shop, "'view_sales_workspace' => \$hasPermission('sale.view') || \$hasPermission('report.view')"), 'Sales workspace visibility derives from existing independent grants');
$ok(str_contains($shop, "'view_sale_entry' => \$hasPermission('sale.view')") && str_contains($shop, "'view_sale_drafts' => \$hasPermission('sale.view') || \$hasPermission('report.view')"), 'Report-only Staff can mount Sales workspace without Sell/draft access');
$ok(str_contains($registry, "prefixes: ['#sale/', '#sales-reports']") && str_contains($registry, "capability: 'view_sales_workspace'"), 'Existing Sales workspace resolves the report-only route');
$ok(str_contains($registry, "id: 'sale-drafts'") && str_contains($registry, "capability: 'view_sale_entry'"), 'Sell and draft routes remain restricted to sale.view');
$ok(str_contains($template, "page==='sale-history' && context.capabilities.view_sale_drafts"), 'Existing generated Shop template mounts the shared Sales workspace for report-only Staff');
$ok(str_contains($history, "includes('report.view')") && str_contains($history, "includes('sale.view')"), 'Sales history and report UI remain separately permission-aware');
$ok(str_contains($panel, 'refreshAfterPending') && str_contains($panel, "includes('sale.profit.view')"), 'Profit visibility refreshes after permission changes, including an in-flight report');
$ok(str_contains($panel, "addEventListener('offline'") && str_contains($panel, "addEventListener('online'") && str_contains($panel, 'not saved for offline use'), 'Offline state hides old report values and reconnect refreshes online data');
$ok(str_contains($panel, 'error.value ? h(UiAlert') && str_contains($panel, 'message: error.value'), 'Report error ref is rendered as its current message');
$ok($history === $read('public/assets/js/components/sale-history-panel.js'), 'Published Sales workspace module exactly matches its source');
$ok($panel === $read('public/assets/js/components/sales-reports-panel.js'), 'Published Sales report module exactly matches its source');
$ok($registry === $read('public/assets/js/shared/workspace-registry.js'), 'Published Shop navigation module exactly matches its source');
$ok(str_contains($panel, 'Money from sales after returns') && str_contains($panel, 'Cash after refunds') && str_contains($panel, 'Money left after paying for goods') && str_contains($panel, 'Money customers can pay later'), 'Report labels use everyday words');
$ok(str_contains($plain, 'without accounting knowledge') && str_contains($plain, 'Money left after paying for goods'), 'Plain-English policy documents the Sales report language');

echo PHP_EOL . $checks . " Phase 8 WP1 source contract checks passed." . PHP_EOL;
