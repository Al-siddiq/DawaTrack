import fs from 'node:fs';
import assert from 'node:assert/strict';

const root = new URL('../../', import.meta.url);
const read = path => fs.readFileSync(new URL(path, root), 'utf8');
const panel = read('resources/js/components/operational-reports-panel.js');
const template = read('resources/js/shop/template.html');
const app = read('resources/js/shop/app.js');
const registry = read('resources/js/shared/workspace-registry.js');
const products = read('resources/js/components/products-panel.js');
const css = read('resources/css/app.scss');
const contract = read('docs/Phase-8/Phase_8_WP2_Integration_Contracts.md');

for (const phrase of [
    'Shop reports',
    'Stock position',
    'Low-stock products',
    'Stock below zero',
    'Value with confirmed cost',
    'Last known buying price is being kept',
    'Customer balances',
    'Customers still need to pay',
    'Payment overdue',
    'Finished shop checks',
    'Cash checks with a difference',
    'Products counted below expected stock',
    'Products counted above expected stock',
    'Last updated:',
    'last successful report',
    'may be out of date',
    'You are offline.',
    'Open Stock record',
    'View customer bills',
    'Open finished check',
]) assert.ok(panel.includes(phrase), 'Missing WP2 UI text: ' + phrase);

for (const route of [
    '/api/v1/shop/reports/stock',
    '/api/v1/shop/reports/customer-debt',
    '/api/v1/shop/reports/reconciliation',
]) assert.ok(panel.includes(route), 'Missing WP2 report API call: ' + route);

assert.ok(registry.includes('view_operational_reports'), 'Shop reports navigation uses server capability');
assert.ok(template.includes('OperationalReportsPanel'), 'Shop template mounts the reports panel');
assert.ok(app.includes('OperationalReportsPanel'), 'Shop app registers the reports panel');
assert.ok(products.includes("props.hash.startsWith('#products/')"), 'Stock Product deep link returns to existing Product detail');
assert.ok(products.includes("emit('navigate', '#products')"), 'Stock Product detail has a guarded return path');
assert.ok(css.includes('.sales-report-period-card'), 'Report UI reuses existing responsive report styles');
assert.ok(!panel.includes('Net profit'), 'WP2 does not introduce a Net Profit claim');
assert.ok(!panel.includes('localStorage') && !panel.includes('indexedDB'), 'WP2 does not persist offline report data');
assert.ok(contract.includes('reconciliation.review'), 'Reconciliation variance field authority is documented');
assert.ok(contract.includes('UNESTABLISHED') && contract.includes('RETAINED'), 'Stock cost-state caveats are documented');

console.log('PASS: static WP2 operational report UI and wording assertions.');
