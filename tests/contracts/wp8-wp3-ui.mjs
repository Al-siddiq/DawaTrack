import fs from 'node:fs';
import assert from 'node:assert/strict';

const root = new URL('../../', import.meta.url);
const read = path => fs.readFileSync(new URL(path, root), 'utf8');
const dashboard = read('resources/js/components/operational-dashboard-panel.js');
const home = read('resources/js/components/workspace-home.js');
const salesHistory = read('resources/js/components/sale-history-panel.js');
const salesPanel = read('resources/js/components/sales-reports-panel.js');
const css = read('resources/css/app.scss');
const contract = read('docs/Phase-8/Phase_8_WP3_Composition_Contract.md');
const reconciliationQuery = read('app/Modules/Reconciliation/Infrastructure/ReconciliationReportQuery.php');
const routes = read('app/Config/Routes.php');

for (const phrase of [
    'Shop overview',
    'Sales period',
    'Current Stock',
    'Current customer balances',
    'Finished shop checks',
    'Sales after returns',
    'Cash after refunds',
    'Bank transfers after refunds',
    'Customer Credit created',
    'Gross profit before expenses',
    'Customers who still owe',
    'Cash checks with a difference',
    'Last refreshed:',
    'You are offline.',
    'No report figures are saved on this device.',
    'Other cards show the current position or latest finished checks.',
    'Open Sales report',
    'Open Stock report',
    'Open Customer Debt report',
    'Open Reconciliation report',
]) assert.ok(dashboard.includes(phrase), 'Missing dashboard wording: ' + phrase);

for (const route of [
    '/api/v1/shop/reports/sales',
    '/api/v1/shop/reports/stock',
    '/api/v1/shop/reports/customer-debt',
    '/api/v1/shop/reports/reconciliation',
]) assert.ok(dashboard.includes(route), 'Missing existing source report request: ' + route);

assert.ok(dashboard.includes("props.permissions.includes('report.view')"), 'Dashboard checks report.view');
assert.ok(dashboard.includes('view_stock_report === true'), 'Stock card uses source capability');
assert.ok(dashboard.includes('view_customer_debt_report === true'), 'Debt card uses source capability');
assert.ok(dashboard.includes('view_reconciliation_report === true'), 'Reconciliation card uses source capability');
assert.ok(dashboard.includes('profit_visible') && dashboard.includes('variance_visibility'), 'Source privacy masks are preserved');
assert.ok(dashboard.includes("per_page: '1'"), 'Dashboard uses summary-only WP2 pages');
assert.ok(dashboard.includes('new AbortController()'), 'Requests are cancellable');
assert.ok(dashboard.includes("addEventListener('offline'") && dashboard.includes("addEventListener('online'"), 'Offline and online transitions are handled');
assert.ok(home.includes('OperationalDashboardPanel'), 'Dashboard is integrated into Shop Home');
assert.ok(home.indexOf("'ui-home-intro'") < home.indexOf('canViewReports.value && h(OperationalDashboardPanel'), 'Personal greeting is the first Home section');
assert.ok(home.indexOf('canViewReports.value && h(OperationalDashboardPanel') < home.indexOf('unfinishedSalesSection(),'), 'Shop overview follows the greeting and precedes unfinished sales');
assert.ok(home.indexOf('unfinishedSalesSection()') < home.indexOf('quickActionsSection()'), 'Unfinished sales precede quick actions');
assert.ok(home.includes("'What would you like to do?'"), 'Quick-action section uses the requested heading');
assert.ok(home.includes("'ui-home-actions'"), 'Four primary quick actions are grouped together');
assert.ok(!home.includes("'Quick actions'"), 'Generic Quick actions heading is removed');
assert.ok(reconciliationQuery.includes('$binds = [];'), 'Reconciliation query starts with no unused SQL bind values');
for (const route of [
    "shop/reports/sales', 'Api\\\\SalesReportController::index",
    "shop/reports/stock', 'Api\\\\OperationalReportController::stock",
    "shop/reports/customer-debt', 'Api\\\\OperationalReportController::customerDebt",
    "shop/reports/reconciliation', 'Api\\\\OperationalReportController::reconciliation",
]) assert.ok(routes.includes(route), 'Missing registered source report route: ' + route);
assert.ok(!salesHistory.includes('reportOpen.value') && !salesHistory.includes('SalesReportPanel'), 'Sales workspace renders only the registered SalesReportsPanel');
assert.ok(salesHistory.includes("location.hash.startsWith('#sales-reports')"), 'Sales drill-down accepts period-bearing report hash');
assert.ok(salesPanel.includes("hashParams.get('period')") && salesPanel.includes("hashParams.get('from_date')") && salesPanel.includes("hashParams.get('to_date')"), 'Sales report initializes the dashboard-selected period');
assert.ok(dashboard.includes("period: 'TODAY'") && dashboard.includes("period: 'CUSTOM'") && dashboard.includes('from_date: start') && dashboard.includes('to_date: end'), 'Dashboard sends the WP1 period/date request contract');
assert.ok(dashboard.includes('data.sales || {}') && dashboard.includes('data.payments || {}') && dashboard.includes('data.gross_profit'), 'Dashboard reads the WP1 Sales response contract');
assert.ok(css.includes('.dashboard-grid') && css.includes('@media(max-width:35rem)'), 'Dashboard layout is responsive');
assert.ok(contract.includes('Export is not approved and is not implemented'), 'Unapproved export remains excluded');
assert.ok(!dashboard.includes('localStorage') && !dashboard.includes('indexedDB'), 'No offline report persistence');
assert.ok(!dashboard.includes('setInterval') && !dashboard.includes('setTimeout'), 'No polling or retry timer');
assert.ok(!dashboard.includes('/export') && !dashboard.includes('Download report'), 'No export endpoint or download UI is present');

console.log('PASS: static WP3 Shop dashboard UI and wording assertions.');
