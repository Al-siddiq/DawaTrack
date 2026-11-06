// Optional mocked DOM workflow; install jsdom outside the project and set WP4_JSDOM_MODULE.
const { JSDOM } = await import(process.env.WP4_JSDOM_MODULE ?? 'jsdom');
import fs from 'node:fs';
import assert from 'node:assert/strict';
const repo = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const dom = new JSDOM('<div id="test"></div>', { url: 'https://shop.test/', runScripts: 'outside-only' });
const { window } = dom;
for (const key of ['window', 'document', 'navigator', 'location', 'history', 'HTMLElement', 'Element', 'SVGElement', 'FormData']) Object.defineProperty(globalThis, key, { value: key === 'window' ? window : window[key], configurable: true });
window.eval(fs.readFileSync(`${repo}/node_modules/vue/dist/vue.runtime.global.prod.js`, 'utf8'));
const { createApp, h, ref, nextTick } = window.Vue;
const { ReturnEditor } = await import(`${repo}/resources/js/components/return-editor.js`);
const { CommercialActions } = await import(`${repo}/resources/js/components/commercial-actions.js`);
const { SaleReceipt } = await import(`${repo}/resources/js/components/sale-receipt.js`);
const settle = async () => { await new Promise(r => setTimeout(r, 15)); await nextTick(); await new Promise(r => setTimeout(r, 15)); };
const button = text => [...document.querySelectorAll('button')].find(b => b.textContent === text);
function input(id, value) { const node = document.getElementById(id); assert(node, id); node.value = value; node.dispatchEvent(new window.Event('input', { bubbles: true })); }
const id = '01234567-89ab-4cde-8fab-0123456789ab', original = '11234567-89ab-4cde-8fab-0123456789ab';
let draft = { public_id: id, kind: 'RETURN', operation_type: 'RETURN', status: 'DRAFT', customer_name: 'Alice', updated_at: '2026-11-04 12:00:00.000001', original_items: [{ public_id: original, product_name: 'Medicine', remaining_quantity: '2.0000', original_quantity: '2.0000', unit_selling_price: '100.00', unit_name: 'pack' }], items: [] };
let posted = false, posts = 0, reversalPosts = 0, calls = [];
const postedReceipt = () => ({ ...draft, status: 'POSTED', total_amount: '200.00', occurred_at: '2026-11-04 12:00:00.000000', posted_at: '2026-11-04 12:01:00.000000', posted_by_name: 'Owner', payments: [{ public_id: original, direction: 'REFUND', channel: 'CASH', amount: '100.00' }], credit_adjustments: [{ public_id: id, adjustment_type: 'RETURN_REDUCTION', amount_delta: '-100.00' }], items: draft.items.map(i => ({ ...i, product_name: 'Medicine', unit_name: 'pack', movement_public_id: id, quantity_before: '0.0000', quantity_after: '2.0000' })), profit_visible: false });
const response = data => new Response(JSON.stringify({ success: true, data, meta: {} }), { status: 200 });
globalThis.fetch = async (path, options) => {
    const body = options.body ? JSON.parse(options.body) : null; calls.push({ path, method: options.method ?? 'GET', body });
    if (options.method === 'PUT') { assert.equal(body.items[0].quantity, '2'); assert.equal(body.expected_updated_at, draft.updated_at); assert.equal(body.items[0].unit_selling_price, undefined); draft = { ...draft, updated_at: '2026-11-04 12:00:00.000002', items: [{ ...body.items[0], quantity: '2.0000', unit_selling_price: '100.00', line_sales_amount: '200.00' }] }; return response(draft); }
    if (path.endsWith('/estimate')) return response({ total_amount: '200.00', debt_reduction: '100.00', refund_amount: '100.00', cash_capacity: '100.00', transfer_capacity: '0.00' });
    if (path.endsWith('/reversal-preview')) return response({ source_kind: 'RETURN', cash_amount: '100.00', transfer_amount: '0.00', debt_amount: '100.00', money_direction: 'RECEIPT', debt_direction: 'RESTORE', line_count: 1, review_fingerprint: 'reverse-review' });
    if (path.endsWith('/preview')) return response({ total_amount: '200.00', debt_reduction: '100.00', refund_amount: '100.00', cash_amount: body.cash_amount, transfer_amount: body.transfer_amount, review_fingerprint: 'return-review' });
    if (path.endsWith('/post')) { posts++; assert.equal(body.review_fingerprint, 'return-review'); assert.equal(body.cash_amount, '100.00'); posted = true; throw new Error('Lost response after commit'); }
    if (path.endsWith('/reverse')) { reversalPosts++; assert.equal(body.review_fingerprint, 'reverse-review'); return response({ ...postedReceipt(), public_id: original, kind: 'SALE', operation_type: 'COMPENSATION', received_amount_snapshot: null, credit_created_amount_snapshot: null, payments: [{ public_id: id, direction: 'RECEIPT', channel: 'CASH', amount: '100.00' }], credit_adjustments: [{ public_id: original, adjustment_type: 'REVERSAL', amount_delta: '100.00' }] }); }
    return response(posted ? postedReceipt() : draft);
};
const state = ref(draft);
let app = createApp({ setup: () => () => state.value.status === 'DRAFT' ? h(ReturnEditor, { record: state.value, permissions: ['sale.view', 'sale.return'], readOnly: false, onOpen: value => { state.value = value; } }) : h(SaleReceipt, { sale: state.value, permissions: ['sale.view'] }) });
app.mount('#test'); await settle(); input(`return-qty-${original}`, '2'); document.getElementById(`return-qty-${original}`).closest('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true })); await settle();
button('Calculate settlement').click(); await settle(); assert(document.body.textContent.includes('₦100.00')); input('return-cash', '100.00'); input('return-reason', 'Damaged package returned.'); document.getElementById('return-cash').closest('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true })); await settle(); assert(button('Confirm and post Return').disabled);
let checkbox = document.querySelector('.commercial-confirmation input'); checkbox.checked = true; checkbox.dispatchEvent(new window.Event('change', { bubbles: true })); await settle(); button('Confirm and post Return').click(); button('Confirm and post Return').click(); await settle(); assert.equal(posts, 1); assert(button('Check Return status')); assert(button('Save returned quantities').disabled); button('Check Return status').click(); await settle(); assert(document.body.textContent.includes('Return receipt')); assert(document.body.textContent.includes('Existing debt reduced: ₦100.00')); assert(!document.body.textContent.includes('Historical margin')); app.unmount();
let received = null;
app = createApp(CommercialActions, { sale: postedReceipt(), permissions: ['sale.view', 'sale.reverse'], readOnly: false, onOpen: value => { received = value; } }); app.mount('#test'); await settle(); input('commercial-correction-reason', 'Wrong Return, restore the original obligation.'); button('Review reversal').click(); await settle(); assert(button('Confirm reversal').disabled); checkbox = document.querySelector('.commercial-confirmation input'); checkbox.checked = true; checkbox.dispatchEvent(new window.Event('change', { bubbles: true })); await settle(); button('Confirm reversal').click(); await settle(); assert.equal(reversalPosts, 1); assert.equal(received.operation_type, 'COMPENSATION'); assert.equal(received.credit_created_amount_snapshot, null); app.unmount();
app = createApp(SaleReceipt, { sale: received, permissions: ['sale.view'] }); app.mount('#test'); await settle(); assert(document.body.textContent.includes('Existing debt restored: ₦100.00')); assert(!document.body.textContent.includes('Credit created:')); app.unmount();
app = createApp(CommercialActions, { sale: postedReceipt(), permissions: ['sale.view', 'sale.reverse'], readOnly: true }); app.mount('#test'); await settle(); assert(button('Review reversal').disabled); app.unmount();
console.log('PASS: mocked Return selection/save/review/confirmation; lost response retrieval without repost; refund/debt receipt; reversal confirmation; restored-existing-debt label; private cost masking and read-only controls. No native browser/HTTP acceptance.');
