// Optional mocked DOM checks. Install jsdom outside the project and set WP3_JSDOM_MODULE.
const { JSDOM } = await import(process.env.WP3_JSDOM_MODULE ?? 'jsdom');
import fs from 'node:fs';
import assert from 'node:assert/strict';
const repo = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const dom = new JSDOM('<div id="test"></div>', { url: 'https://shop.test/', runScripts: 'outside-only' });
const { window } = dom;
for (const key of ['window', 'document', 'navigator', 'location', 'history', 'HTMLElement', 'Element', 'SVGElement', 'FormData']) Object.defineProperty(globalThis, key, { value: key === 'window' ? window : window[key], configurable: true });
window.eval(fs.readFileSync(`${repo}/node_modules/vue/dist/vue.runtime.global.prod.js`, 'utf8'));
const { createApp, nextTick } = window.Vue;
const settle = async () => { await new Promise(resolve => setTimeout(resolve, 15)); await nextTick(); await new Promise(resolve => setTimeout(resolve, 15)); };
const { SaleDraftPanel } = await import(`${repo}/resources/js/components/sale-draft-panel.js`);
const { SaleReceipt } = await import(`${repo}/resources/js/components/sale-receipt.js`);
const { SaleHistoryPanel } = await import(`${repo}/resources/js/components/sale-history-panel.js`);
const uuid = '01234567-89ab-4cde-8fab-0123456789ab', product = '11234567-89ab-4cde-8fab-0123456789ab', customer = '21234567-89ab-4cde-8fab-0123456789ab';
const draft = { public_id: uuid, status: 'DRAFT', occurred_at: '2026-11-03 12:00:00.000000', updated_at: '2026-11-03 12:00:00.000001', customer_public_id: customer, customer_name: 'Alice', customer_status: 'ACTIVE', total_amount: '20000.00', items: [{ public_id: product, product_public_id: product, product_name: 'Test medicine', unit_name: 'pack', product_status: 'ACTIVE', quantity: '2.0000', unit_selling_price: '10000.00', current_selling_price: '10000.00', line_sales_amount: '20000.00', line_number: 1 }] };
const receipt = { ...draft, status: 'POSTED', posted_at: '2026-11-03 12:01:00.000000', posted_by_name: 'Owner', currency_code: 'NGN', received_amount_snapshot: '10000.00', credit_created_amount_snapshot: '10000.00', profit_visible: true, profit_provisional: true, total_cogs_amount: '0.00', gross_profit: '20000.00', payments: [{ public_id: product, channel: 'CASH', direction: 'RECEIPT', amount: '5000.00' }, { public_id: customer, channel: 'TRANSFER', direction: 'RECEIPT', amount: '5000.00' }], receivable: { public_id: customer, original_amount: '10000.00', due_date: '2026-12-01' }, items: [{ ...draft.items[0], movement_public_id: product, quantity_before: '0.0000', quantity_after: '-2.0000', movement_posted_at: '2026-11-03 12:01:00.000000', unit_cost_snapshot: '0.000000', line_cogs_amount: '0.00', gross_profit: '20000.00', cost_basis_state: 'UNESTABLISHED' }] };
let calls = [], posted = false, mode = 'success', releasePost = null;
const response = (data, meta = {}) => new Response(JSON.stringify({ success: true, data, meta }), { status: 200 });
globalThis.fetch = async (path, options) => {
    const body = options.body ? JSON.parse(options.body) : null; calls.push({ path, method: options.method ?? 'GET', body });
    if (path.includes('/sale-draft-lookups/products')) return response([{ public_id: product, name: 'Test medicine', unit_name: 'pack', current_selling_price: '10000.00' }]);
    if (path.includes('/sale-draft-lookups/customers')) return response([{ public_id: customer, display_name: 'Alice' }]);
    if (path.endsWith('/preview')) return response({ total_amount: '20000.00', cash_amount: body.cash_amount, transfer_amount: body.transfer_amount, paid_amount: '10000.00', credit_amount: '10000.00', due_date: body.due_date, draft_updated_at: draft.updated_at });
    if (path.endsWith('/post')) {
        assert.equal(body.expected_updated_at, draft.updated_at); assert.equal(body.cash_amount, '5000.00'); assert.equal(body.transfer_amount, '5000.00'); assert.equal(body.due_date, '2026-12-01');
        if (mode === 'denied') return new Response(JSON.stringify({ success: false, error: { code: 'OPENING_CASH_REQUIRED', message: 'Finalize Opening Cash first.' } }), { status: 409 });
        if (mode === 'delayed') await new Promise(resolve => { releasePost = resolve; });
        posted = true; if (mode === 'lost') throw new Error('response lost after commit'); return response(receipt);
    }
    if (path.startsWith('/api/v1/shop/sales?')) return response([receipt], { pagination: { page: 1, total_pages: 1 } });
    if (path.startsWith('/api/v1/shop/sales/')) return response(posted ? receipt : draft);
    return response([], { pagination: { page: 1, total_pages: 1 } });
};
const button = label => [...document.querySelectorAll('button')].find(value => value.textContent === label);
function input(id, value) { const element = document.getElementById(id); assert(element, id); element.value = value; element.dispatchEvent(new window.Event('input', { bubbles: true })); }
async function mountDraft() { history.replaceState(null, '', `#sale-draft/${uuid}`); const app = createApp(SaleDraftPanel, { permissions: ['sale.view', 'sale.record'], readOnly: false }); app.mount('#test'); await settle(); return app; }
async function review() { input('sale-cash_amount', '5000.00'); input('sale-transfer_amount', '5000.00'); input('sale-due_date', '2026-12-01'); document.getElementById('sale-cash_amount').closest('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true })); await settle(); }
async function confirm() { const checkbox = document.querySelector('.commercial-confirmation input'); assert(checkbox); checkbox.checked = true; checkbox.dispatchEvent(new window.Event('change', { bubbles: true })); await settle(); }
let app = await mountDraft(); await review(); assert(button('Confirm and post Sale').disabled); await confirm();
mode = 'delayed'; button('Confirm and post Sale').click(); button('Confirm and post Sale').click(); await settle(); assert.equal(calls.filter(c => c.path.endsWith('/post')).length, 1); assert(button('Confirm and post Sale').disabled); releasePost(); await settle();
assert(document.body.textContent.includes('Posted successfully')); assert(document.body.textContent.includes('Original credit: ₦10000.00')); assert(!document.body.textContent.includes('Historical margin')); assert.equal(location.hash, `#sale/${uuid}`); app.unmount();
// Refresh/retrieve an already posted identity goes to receipt, never reconstructs a new draft.
app = await mountDraft(); assert(document.body.textContent.includes('Posted successfully')); assert(!button('Confirm and post Sale')); app.unmount();
posted = false; mode = 'lost'; calls = []; app = await mountDraft(); await review(); await confirm(); button('Confirm and post Sale').click(); await settle(); assert(button('Check Sale status')); assert(button('Back to drafts').disabled); assert(button('Add line').disabled); assert.equal(calls.filter(c => c.path.endsWith('/post')).length, 1); button('Check Sale status').click(); await settle(); assert(document.body.textContent.includes('Posted successfully')); assert.equal(calls.filter(c => c.path.endsWith('/post')).length, 1); app.unmount();
posted = false; mode = 'denied'; app = await mountDraft(); await review(); await confirm(); button('Confirm and post Sale').click(); await settle(); assert(document.querySelector('[role=alert]').textContent.includes('Opening Cash')); assert(document.body.textContent.includes('Test medicine')); assert(!document.body.textContent.includes('Posted successfully')); assert(!button('Check Sale status')); app.unmount();
// Unsaved basket edits cannot be reviewed or accidentally confirmed as if saved.
app = await mountDraft(); button('Edit line').click(); await settle(); assert(button('Review preparation').disabled); button('Cancel line edit').click(); await settle(); assert(!button('Review preparation').disabled); app.unmount();
app = createApp(SaleReceipt, { sale: receipt, permissions: ['sale.view', 'sale.profit.view'] }); app.mount('#test'); await settle(); assert(document.body.textContent.includes('Historical margin')); assert(document.body.textContent.includes('provisional')); document.querySelector('a').click(); assert(document.querySelector('details').open); assert(document.body.textContent.includes('below zero')); app.unmount();
history.replaceState(null, '', '#sale-history'); posted = true; app = createApp(SaleHistoryPanel, { permissions: ['sale.view'] }); app.mount('#test'); await settle(); assert(button('View receipt')); input('history-search', 'Alice'); input('history-from', '2026-11-01'); document.getElementById('history-search').closest('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true })); await settle(); assert(calls.some(c => c.path.includes('search=Alice') && c.path.includes('from=2026-11-01'))); button('View receipt').click(); await settle(); assert(document.body.textContent.includes('Posted successfully')); assert(!document.body.textContent.includes('Historical margin')); app.unmount();
console.log('PASS: mocked DOM confirmation/double-tap; receipt/privacy; posted refresh; lost-response retrieval without repost; Cash denial; unsaved edit guard; cost-provenance warning and stock links. No real HTTP/browser acceptance.');
