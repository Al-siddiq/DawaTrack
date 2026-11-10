import { useTradingConfirm, stateLabel, fieldSummary } from './trading-ui.js';
import { UiLoading, UiField } from './ui/index.js';
import { useNavigationGuard, announceWorkspaceHash as replaceLocation } from '../shared/workspace-navigation.js';
import { UiStatusPanel } from './ui/index.js';
import { SaleReceipt } from './sale-receipt.js';
import { apiRequest } from '../api/http-client.js';
import { has, money, get, send, field, action, submit, feedback, message, pager } from './commercial-ui.js';
const { h, ref, reactive, computed, onMounted, onUnmounted, nextTick } = window.Vue;
const base = '/api/v1/shop/sale-drafts';
const lookup = '/api/v1/shop/sale-draft-lookups';
const localTime = utc => {
    const date = new Date(`${utc.replace(' ', 'T')}Z`);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 19);
};
export const SaleDraftPanel = {
    props: { permissions: { type: Array, default: () => [] }, readOnly: { type: Boolean, default: true } },
    setup(props) {
        let alive = true;
        const announceWorkspaceHash = hash => { if (alive) replaceLocation(hash); };
        const confirmation = useTradingConfirm(), validation = ref({});
        const receipt = ref(null), uncertain = ref(false), confirmed = ref(false);
        const rows = ref([]), draft = ref(null), pending = ref(false), error = ref(''), notice = ref(''), online = ref(navigator.onLine), preview = ref(null), heading = ref(null);
        const pagination = reactive({ page: 1, per_page: 20, total: 0, total_pages: 0 });
        const products = ref([]), customers = ref([]), productSearch = ref(''), customerSearch = ref('');
        const newId = ref(crypto.randomUUID()), quickId = ref(crypto.randomUUID()), quickName = ref(''), reason = ref('');
        const header = reactive({ customer_public_id: '', occurred_at: '' });
        const line = reactive({ public_id: '', retry_id: crypto.randomUUID(), product_public_id: '', quantity: '1', unit_selling_price: '', line_number: '' });
        const payment = reactive({ cash_amount: '0.00', transfer_amount: '0.00', due_date: '' });
        const record = computed(() => has(props.permissions, 'sale.record') && has(props.permissions, 'sale.view'));
        const override = computed(() => has(props.permissions, 'sale.price_override'));
        const quickCreate = computed(() => has(props.permissions, 'customer.manage') && has(props.permissions, 'customer.view'));
        const unsaved = computed(() => !!draft.value && (header.customer_public_id !== (draft.value.customer_public_id ?? '') || header.occurred_at !== localTime(draft.value.occurred_at) || line.product_public_id !== ''));
        const disabled = computed(() => pending.value || uncertain.value || props.readOnly || !online.value || !record.value);
        const connection = () => { online.value = navigator.onLine; };
        useNavigationGuard(({ hash }) => pending.value && hash.startsWith('#sale-draft') ? { hard: true, message: 'Wait for this record to finish loading before opening another sale.' } : uncertain.value ? { hard: true, message: 'Check the saved sale status before leaving this task.' }
            : unsaved.value || (draft.value && (payment.cash_amount !== '0.00' || payment.transfer_amount !== '0.00' || payment.due_date || preview.value))
                ? { message: 'Your latest basket or payment edits are not saved. Keep editing, or leave these edits behind.' } : null);
        const hashChange = () => {
            const match = location.hash.match(/^#sale-draft\/([a-f0-9-]{36})$/);
            if (match && match[1] !== draft.value?.public_id) open(match[1]);
            else if (location.hash === '#sale-drafts' && (draft.value || receipt.value)) { receipt.value = null; back(true); }
            else if (location.hash === '#sale-drafts/new') { if (!disabled.value) create(); else { announceWorkspaceHash('#sale-drafts'); load(); } }
        };
        function resetLine() { Object.assign(line, { public_id: '', retry_id: crypto.randomUUID(), product_public_id: '', quantity: '1', unit_selling_price: '', line_number: '' }); }
        function setDraft(value) {
            draft.value = value; preview.value = null; confirmed.value = false;
            Object.assign(header, { customer_public_id: value.customer_public_id ?? '', occurred_at: localTime(value.occurred_at) });
        }
        async function run(operation) {
            if (pending.value) return;
            pending.value = true; error.value = ''; notice.value = ''; validation.value = {};
            try { await operation(); }
            catch (e) { validation.value = e.details || {}; error.value = `${message(e)} Entered values are retained. If a save may have completed, reload the saved draft before retrying.`; }
            finally { pending.value = false; }
        }
        async function load(page = pagination.page) {
            await run(async () => { const response = await apiRequest(`${base}?${new URLSearchParams({ page, per_page: 20 })}`); rows.value = response.data; Object.assign(pagination, response.meta.pagination); });
        }
        async function loadLookup(type) {
            const search = type === 'products' ? productSearch.value : customerSearch.value;
            const result = await get(`${lookup}/${type}?${new URLSearchParams({ search })}`);
            if (type === 'products') products.value = result; else customers.value = result;
        }
        async function open(id) {
            await run(async () => {
                const value = await get(`/api/v1/shop/sales/${id}`); if (!alive) return;
                uncertain.value = false;
                if (value.status === 'POSTED') { receipt.value = value; announceWorkspaceHash(`#sale/${value.public_id}`); return; }
                setDraft(value); resetLine();
                Object.assign(payment, { cash_amount: '0.00', transfer_amount: '0.00', due_date: '' });
                announceWorkspaceHash(`#sale-draft/${draft.value.public_id}`);
                if (record.value && online.value) { await loadLookup('products'); await loadLookup('customers'); }
                await nextTick(); heading.value?.focus();
            });
        }
        async function create() {
            if (disabled.value) return;
            await run(async () => {
                setDraft(await send(base, 'POST', { public_id: newId.value })); newId.value = crypto.randomUUID(); resetLine();
                Object.assign(payment, { cash_amount: '0.00', transfer_amount: '0.00', due_date: '' });
                announceWorkspaceHash(`#sale-draft/${draft.value.public_id}`);
                await loadLookup('products'); await loadLookup('customers'); notice.value = 'Unfinished sale created. Add your products next.';
            });
        }
        async function mutate(path, method, body, after = null) {
            if (disabled.value) return;
            await run(async () => {
                const value = await send(`${base}/${draft.value.public_id}${path}`, method, { ...body, expected_updated_at: draft.value.updated_at });
                setDraft(value); if (after) after(); notice.value = 'Changes saved. Check the payment again before saving the sale.';
            });
        }
        function saveHeader(event) {
            event.preventDefault();
            const date = new Date(header.occurred_at);
            if (!Number.isFinite(date.getTime())) { error.value = 'Choose a valid business time.'; return; }
            mutate('', 'PUT', { customer_public_id: header.customer_public_id || null, occurred_at: date.toISOString().replace('T', ' ').replace('Z', '000'), device_local_offset_minutes: -date.getTimezoneOffset() });
        }
        function selectProduct(id) {
            line.product_public_id = id;
            const product = products.value.find(value => value.public_id === id);
            if (product) line.unit_selling_price = product.current_selling_price;
        }
        function edit(item) {
            Object.assign(line, { public_id: item.public_id, product_public_id: item.product_public_id, quantity: item.quantity, unit_selling_price: item.unit_selling_price, line_number: String(item.line_number) });
            nextTick(() => document.getElementById('sale-quantity')?.focus());
        }
        function saveLine(event) {
            event.preventDefault();
            const body = { product_public_id: line.product_public_id, quantity: line.quantity };
            if (override.value) body.unit_selling_price = line.unit_selling_price;
            // Without override permission the server always selects the current Product price.
            if (line.line_number !== '') body.line_number = Number(line.line_number);
            if (!line.public_id) body.public_id = line.retry_id;
            mutate(line.public_id ? `/items/${line.public_id}` : '/items', line.public_id ? 'PUT' : 'POST', body, resetLine);
        }
        async function createCustomer(event) {
            event.preventDefault(); if (disabled.value || !quickCreate.value) return;
            await run(async () => {
                const customer = await send('/api/v1/shop/customers', 'POST', { public_id: quickId.value, display_name: quickName.value });
                quickId.value = crypto.randomUUID(); quickName.value = ''; await loadLookup('customers');
                if (!customers.value.some(row => row.public_id === customer.public_id)) customers.value.unshift(customer);
                header.customer_public_id = customer.public_id; preview.value = null;
                notice.value = 'Customer created. Save customer and time to attach this Customer.';
            });
        }
        async function review(event) {
            event.preventDefault(); if (disabled.value || unsaved.value) return;
            preview.value = null; confirmed.value = false;
            await run(async () => { preview.value = await send(`${base}/${draft.value.public_id}/preview`, 'POST', { cash_amount: payment.cash_amount, transfer_amount: payment.transfer_amount, due_date: payment.due_date || null, expected_updated_at: draft.value.updated_at }); });
        }
        async function post() {
            if (disabled.value || unsaved.value || !preview.value || !confirmed.value) return;
            const reviewed = preview.value;
            await run(async () => {
                try {
                    const value = await send(`/api/v1/shop/sales/${draft.value.public_id}/post`, 'POST', { expected_updated_at: reviewed.draft_updated_at, cash_amount: reviewed.cash_amount, transfer_amount: reviewed.transfer_amount, due_date: reviewed.due_date });
                    receipt.value = value; preview.value = null; announceWorkspaceHash(`#sale/${value.public_id}`);
                    await nextTick(); heading.value?.focus();
                } catch (e) {
                    if (e.status === 0 || e.status >= 500 || e.status === 403 || e.status === 404) uncertain.value = true;
                    preview.value = null; confirmed.value = false; throw e;
                }
            });
        }
        async function discard(event) {
            event.preventDefault(); if (disabled.value) return;
            if (!(await confirmation.ask({ title: 'Discard this unfinished sale?', message: 'The saved basket will be removed. No sale or payment will be recorded.', confirm: 'Discard unfinished sale', danger: true }))) return;
            await run(async () => { await send(`${base}/${draft.value.public_id}`, 'DELETE', { expected_updated_at: draft.value.updated_at, reason: reason.value }); draft.value = null; preview.value = null; reason.value = ''; resetLine(); announceWorkspaceHash('#sale-drafts'); notice.value = 'Draft discarded. No stock, payment or debt entries were created.'; });
            if (!draft.value) await load();
        }
        function back(approved = false) { const work = () => { draft.value = null; preview.value = null; resetLine(); announceWorkspaceHash('#sale-drafts'); load(); }; return approved === true ? work() : confirmation.guard(work, '#sale-drafts'); }
        onMounted(() => {
            window.addEventListener('online', connection); window.addEventListener('offline', connection);
            const match = location.hash.match(/^#sale-draft\/([a-f0-9-]{36})$/);
            if (match) open(match[1]); else if (location.hash === '#sale-drafts/new') { if (!disabled.value) create(); else { announceWorkspaceHash('#sale-drafts'); load(); } } else load();
            window.addEventListener('hashchange', hashChange);
        });
        onUnmounted(() => { alive = false; window.removeEventListener('hashchange', hashChange); window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        const select = (id, label, value, update, options) => h(UiField, { id, label, errors: validation.value[id === 'sale-customer' ? 'customer_public_id' : 'product_public_id'] || [] }, { default: ({ controlAttrs }) => h('select', { ...controlAttrs, class: 'form-select', value, disabled: disabled.value, onChange: event => update(event.target.value) }, options) });
        return () => h('section', { class: 'workspace commercial-workspace trading-screen ui-scope', 'aria-busy': pending.value }, [
            h('h2', { ref: heading, tabindex: '-1' }, receipt.value ? 'Saved sale' : 'Unfinished sales'), h('p', 'Add products, check what the customer paid, then save the sale.'),
            !online.value && h('p', { class: 'alert alert-warning' }, 'You are offline. Changes are disabled and are not queued.'),
            props.readOnly && h('p', { class: 'alert alert-warning' }, 'Your current access permits viewing only.'),
            ...feedback(error.value, notice.value), fieldSummary(validation.value, { cash_amount: 'sale-cash_amount', transfer_amount: 'sale-transfer_amount', due_date: 'sale-due_date', quantity: 'sale-quantity', unit_selling_price: 'sale-price', customer_public_id: 'sale-customer', occurred_at: 'sale-occurred' }), confirmation.render(), pending.value && h(UiLoading, { label: 'Updating this sale…' }),
            receipt.value ? h('section', [h(SaleReceipt, { sale: receipt.value, permissions: props.permissions }), action('View sales and returns', () => { location.hash = `#sale/${receipt.value.public_id}`; window.dispatchEvent(new Event('hashchange')); }, pending.value || !online.value), action('Start another sale', () => { receipt.value = null; draft.value = null; preview.value = null; resetLine(); announceWorkspaceHash('#sale-drafts'); load(); }, pending.value || !online.value)]) : !draft.value ? h('section', [
                record.value && action('New sale', create, disabled.value, 'btn-primary'), action('Refresh unfinished sales', () => load(), pending.value || !online.value),
                !rows.value.length && !pending.value && h('p', 'No unfinished sales. Choose New sale to begin.'),
                ...rows.value.map(row => h('article', { class: 'commercial-card', key: row.public_id }, [h('h2', row.customer_name ?? 'Walk-in customer'), h('p', `Estimate: ${money(row.total_amount)}`), h('p', `Saved: ${row.updated_at} UTC`), action('Continue sale', () => open(row.public_id), pending.value || !online.value)])), pager(pagination, pending.value || !online.value, load),
            ].filter(Boolean)) : h('section', [
                h('p', { class: 'break-all' }, `Sale number: ${draft.value.public_id}`), h('p', { class: 'sale-sticky-total' }, `Estimate: ${money(draft.value.total_amount)} · ${draft.value.customer_name ?? 'Walk-in customer'}`),
                h('p', `Sale time: ${draft.value.occurred_at} UTC`),
                action('Back to unfinished sales', back, pending.value || uncertain.value), action('Reload saved sale details', () => confirmation.guard(() => open(draft.value.public_id)), pending.value || !online.value),
                h('p', 'Reloading removes unsaved changes and clears the payment amounts you entered.'),
                record.value && h('details', { id: 'sale-customer-details', class: 'commercial-card' }, [h('summary', 'Customer and sale time'),
                record.value && h('form', { class: 'commercial-form', onSubmit: saveHeader }, [
                    h('h2', 'Customer and sale time'),
                    field('sale-customer-search', 'Find a customer', customerSearch.value, value => { customerSearch.value = value; }, { disabled: disabled.value, type: 'search', maxlength: 160 }),
                    action('Find customers', () => run(() => loadLookup('customers')), disabled.value),
                    select('sale-customer', 'Customer (needed if any amount is left unpaid)', header.customer_public_id, value => { header.customer_public_id = value; preview.value = null; confirmed.value = false; }, [h('option', { value: '' }, 'Walk-in customer'),
                        draft.value.customer_public_id && !customers.value.some(c => c.public_id === draft.value.customer_public_id) && h('option', { value: draft.value.customer_public_id }, `${draft.value.customer_name} (${stateLabel(draft.value.customer_status)})`),
                        ...customers.value.map(customer => h('option', { value: customer.public_id }, customer.display_name))].filter(Boolean)),
                    draft.value.customer_status === 'INACTIVE' && h('p', { class: 'alert alert-warning' }, 'This customer is inactive. Choose an active customer, or clear the selection for a fully paid sale.'),
                    field('sale-occurred', 'Sale time (this device’s time)', header.occurred_at, value => { header.occurred_at = value; preview.value = null; confirmed.value = false; }, { type: 'datetime-local', step: '1', required: true, disabled: disabled.value, errors: validation.value.occurred_at || [] }), submit('Save customer and time', disabled.value, { loading: pending.value, busyLabel: 'Saving details…' }),
                ].filter(Boolean))
                ]),
                quickCreate.value && record.value && h('details', { class: 'commercial-card' }, [h('summary', 'Add a new customer'), h('form', { onSubmit: createCustomer }, [field('sale-quick-name', 'Customer name', quickName.value, value => { quickName.value = value; }, { required: true, maxlength: 160, disabled: disabled.value }), h('p', 'Save a name here. You can add contact details on the Customers screen.'), submit('Add customer', disabled.value, { loading: pending.value, busyLabel: 'Adding customer…' })])]),
                uncertain.value && h(UiStatusPanel, { state: 'unknown', message: 'The response was lost or failed. This sale may already be saved. Check its saved state before any further changes.', checking: pending.value, canCheck: online.value, onCheckStatus: () => open(draft.value.public_id) }),
                h('div', { class: 'trading-grid' }, [h('div', { class: 'trading-stack' }, [record.value && h('form', { class: 'commercial-form cashier-product-form', onSubmit: saveLine }, [
                    h('h2', line.public_id ? 'Edit basket item' : '1. Add products'),
                    field('sale-product-search', 'Find a product', productSearch.value, value => { productSearch.value = value; }, { type: 'search', disabled: disabled.value, maxlength: 160 }), action('Find products', () => run(() => loadLookup('products')), disabled.value),
                    h('p', { class: 'cashier-search-hint', role: 'status' }, products.value.length ? `${products.value.length} matching Product choices. Open the Product list below and select one; its price fills automatically.` : 'No active Product choices loaded. Try Find products or check active Products in this Shop.'),
                    select('sale-product', 'Product', line.product_public_id, selectProduct, [h('option', { value: '' }, 'Choose a Product'), line.product_public_id && !products.value.some(p => p.public_id === line.product_public_id) && h('option', { value: line.product_public_id }, draft.value.items.find(i => i.product_public_id === line.product_public_id)?.product_name ?? 'Selected Product'), ...products.value.map(product => h('option', { value: product.public_id }, `${product.name} · ${product.unit_name} · ${money(product.current_selling_price)}`))].filter(Boolean)),
                    field('sale-quantity', 'Quantity', line.quantity, value => { line.quantity = value; }, { inputmode: 'decimal', required: true, disabled: disabled.value, maxlength: 20, errors: validation.value.quantity || [], help: 'For example, 2 for two units, or 0.5 for half a unit.' }),
                    field('sale-price', override.value ? 'Selling price per unit (₦)' : 'Price per unit (₦; shop price used when saved)', line.unit_selling_price, value => { line.unit_selling_price = value; }, { inputmode: 'decimal', required: true, disabled: disabled.value || !override.value, maxlength: 20, errors: validation.value.unit_selling_price || [] }),
                    h('details', [h('summary', 'Change item order'), field('sale-line-order', 'Position in the basket (optional)', line.line_number, value => { line.line_number = value; }, { type: 'number', min: 1, max: 65535, disabled: disabled.value })]),
                    submit(line.public_id ? 'Save basket item' : 'Add to basket', disabled.value || !line.product_public_id, { loading: pending.value, busyLabel: 'Saving item…' }), line.product_public_id && action(line.public_id ? 'Cancel line edit' : 'Clear unsaved line', resetLine, pending.value),
                ].filter(Boolean)),
                h('h2', '2. Check your basket'), !draft.value.items.length && h('p', 'Your basket is empty. Choose a product above and add it.'),
                h('details', { open: true, class: 'sale-basket' }, [h('summary', `Basket: ${draft.value.items.length} lines`),
                ...draft.value.items.map(item => h('article', { class: 'commercial-card', key: item.public_id }, [h('h3', `${item.line_number}. ${item.product_name}`), h('p', `${item.quantity} ${item.unit_name} × ${money(item.unit_selling_price)} = ${money(item.line_sales_amount)}`), item.product_status !== 'ACTIVE' && h('p', { class: 'alert alert-warning' }, 'Inactive Product: remove or replace this line.'), item.current_selling_price !== item.unit_selling_price && h('p', 'The shop price has changed since this item was added. Editing the item uses the current shop price unless you can set a different price.'), record.value && action('Edit line', () => confirmation.guard(() => edit(item)), disabled.value), record.value && action('Remove line', async () => { if (await confirmation.ask({ title: `Remove ${item.product_name}?`, message: 'This removes the item from this unfinished sale.', confirm: 'Remove item', danger: true })) mutate(`/items/${item.public_id}`, 'DELETE', {}, resetLine); }, disabled.value, 'btn-outline-danger')].filter(Boolean)))
                ]),
                ]), h('div', { class: 'trading-stack' }, [record.value && h('form', { class: 'commercial-form', onSubmit: review }, [
                    h('h2', '3. Enter payment'), action('Choose a customer for an unpaid sale', () => { const details = document.getElementById('sale-customer-details'); if (details) details.open = true; nextTick(() => document.getElementById('sale-customer')?.focus()); }, disabled.value, 'btn-link', { icon: 'customers' }), h('p', 'Save any product or customer changes first. Enter the cash and transfer amounts received. Any amount left unpaid must belong to a named customer. Set up starting cash before taking cash payments.'),
                    ...[['cash_amount', 'Cash received (₦)'], ['transfer_amount', 'Transfer received (₦)'], ['due_date', 'Agreed payment date (optional; shop date)']].map(([key, label]) => field(`sale-${key}`, label, payment[key], value => { payment[key] = value; preview.value = null; confirmed.value = false; }, { errors: validation.value[key] || [], disabled: disabled.value, type: key === 'due_date' ? 'date' : 'text', inputmode: key === 'due_date' ? undefined : 'decimal', maxlength: key === 'due_date' ? undefined : 20 })), unsaved.value && h('p', { class: 'alert alert-warning' }, 'Save or cancel unsaved draft details and basket lines before reviewing.'), submit('Review sale', disabled.value || unsaved.value || !draft.value.items.length, { loading: pending.value, busyLabel: 'Checking sale…' }),
                ]),
                preview.value && h('section', { class: 'commercial-card sale-checkout', role: 'status' }, [h('h2', '4. Check and save'), h('dl', [['Total', preview.value.total_amount], ['Cash', preview.value.cash_amount], ['Transfer', preview.value.transfer_amount], ['Paid', preview.value.paid_amount], ['Left to pay', preview.value.credit_amount]].flatMap(([label, value]) => [h('dt', label), h('dd', money(value))])), h('p', `Due date: ${preview.value.due_date ?? 'Not specified'}`), h('p', 'Check the products, customer and amounts below. Nothing is final until you confirm this sale.'), h('label', { class: 'commercial-confirmation' }, [h('input', { type: 'checkbox', checked: confirmed.value, disabled: disabled.value, onChange: event => { confirmed.value = event.target.checked; } }), ' I have checked the products, customer, payments and amount left to pay. Save this sale permanently.']), h('div', { class: 'sale-confirm-action' }, [action('Confirm and save sale', post, disabled.value || unsaved.value || !confirmed.value, 'btn-primary', { loading: pending.value, busyLabel: 'Saving sale…', icon: 'success' })])]),
                record.value && h('details', { class: 'commercial-card' }, [h('summary', 'Discard this unfinished sale'), record.value && h('form', { class: 'sensitive-action', onSubmit: discard }, [h('h2', 'Discard unfinished sale'), field('sale-discard-reason', 'Why are you discarding this sale?', reason.value, value => { reason.value = value; }, { multiline: true, required: true, maxlength: 500, disabled: disabled.value }), submit('Discard unfinished sale', disabled.value, { variant: 'danger-secondary', loading: pending.value })])]),
                ])]),
            ].filter(Boolean)),
        ].filter(Boolean));
    },
};
