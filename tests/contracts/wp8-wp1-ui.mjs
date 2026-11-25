import fs from 'node:fs';
import assert from 'node:assert/strict';

const root = new URL('../../', import.meta.url);
const read = path => fs.readFileSync(new URL(path, root), 'utf8');
const panel = read('resources/js/components/sales-report-panel.js');
const history = read('resources/js/components/sale-history-panel.js');
const registry = read('resources/js/shared/workspace-registry.js');
const css = read('resources/css/app.scss');

for (const phrase of [
    'Sales summary',
    'Sales before returns',
    'Returns and cancelled sales',
    'Sales after returns',
    'Cash received',
    'Cash refunded',
    'Transfer received',
    'Transfer refunded',
    'Credit added to customer bills',
    'Gross profit (before shop expenses)',
    'Last updated:',
]) assert.ok(panel.includes(phrase), 'Missing plain-language report text: ' + phrase);

assert.ok(history.includes('SalesReportPanel'), 'Sales history workspace mounts the report panel');
assert.ok(registry.includes('view_sales_workspace'), 'Sales menu follows server capability');
assert.ok(css.includes('.sales-report-period-card'), 'Responsive report styles are present');
assert.ok(!panel.includes('Net profit'), 'UI does not label Gross Profit as Net Profit');

console.log('PASS: static WP1 Sales report UI and wording assertions.');
