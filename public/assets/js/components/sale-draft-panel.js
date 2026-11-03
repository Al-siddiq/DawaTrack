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
        const disabled = computed(() => pending.value || props.readOnly || !online.value || !record.value);
        const connection = () => { online.value = navigator.onLine; };
        function resetLine() { Object.assign(line, { public_id: '', retry_id: crypto.randomUUID(), product_public_id: '', quantity: '1', unit_selling_price: '', line_number: '' }); }
        function setDraft(value) {
            draft.value = value; preview.value = null;
            Object.assign(header, { customer_public_id: value.customer_public_id ?? '', occurred_at: localTime(value.occurred_at) });
        }
        async function run(operation) {
            if (pending.value) return;
            pending.value = true; error.value = ''; notice.value = '';
            try { await operation(); }
            catch (e) { error.value = `${message(e)} Entered values are retained. If a save may have completed, reload the saved draft before retrying.`; }
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
                setDraft(await get(`${base}/${id}`)); resetLine();
                Object.assign(payment, { cash_amount: '0.00', transfer_amount: '0.00', due_date: '' });
                history.replaceState(null, '', `#sale-draft/${draft.value.public_id}`);
                if (record.value && online.value) { await loadLookup('products'); await loadLookup('customers'); }
                await nextTick(); heading.value?.focus();
            });
        }
        async function create() {
            if (disabled.value) return;
            await run(async () => {
                setDraft(await send(base, 'POST', { public_id: newId.value })); newId.value = crypto.randomUUID(); resetLine();
                Object.assign(payment, { cash_amount: '0.00', transfer_amount: '0.00', due_date: '' });
                history.replaceState(null, '', `#sale-draft/${draft.value.public_id}`);
                await loadLookup('products'); await loadLookup('customers'); notice.value = 'Online draft created. This is not a posted Sale.';
            });
        }
        async function mutate(path, method, body, after = null) {
            if (disabled.value) return;
            await run(async () => {
                const value = await send(`${base}/${draft.value.public_id}${path}`, method, { ...body, expected_updated_at: draft.value.updated_at });
                setDraft(value); if (after) after(); notice.value = 'Draft saved. Review payment preparation again after changes.';
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
                notice.value = 'Customer created. Save draft details to attach this Customer.';
            });
        }
        async function review(event) {
            event.preventDefault(); if (disabled.value) return;
            preview.value = null;
            await run(async () => { preview.value = await send(`${base}/${draft.value.public_id}/preview`, 'POST', { cash_amount: payment.cash_amount, transfer_amount: payment.transfer_amount, due_date: payment.due_date || null, expected_updated_at: draft.value.updated_at }); });
        }
        async function discard(event) {
            event.preventDefault(); if (disabled.value) return;
            await run(async () => { await send(`${base}/${draft.value.public_id}`, 'DELETE', { expected_updated_at: draft.value.updated_at, reason: reason.value }); draft.value = null; preview.value = null; reason.value = ''; resetLine(); history.replaceState(null, '', '#sale-drafts'); notice.value = 'Draft discarded. No stock, payment or debt entries were created.'; });
            if (!draft.value) await load();
        }
        function back() { draft.value = null; preview.value = null; resetLine(); history.replaceState(null, '', '#sale-drafts'); load(); }
        onMounted(() => {
            window.addEventListener('online', connection); window.addEventListener('offline', connection);
            const match = location.hash.match(/^#sale-draft\/([a-f0-9-]{36})$/);
            if (match) open(match[1]); else load();
        });
        onUnmounted(() => { window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        const select = (id, label, value, update, options) => h('div', { class: 'commercial-field' }, [h('label', { for: id }, label), h('select', { id, class: 'form-select', value, disabled: disabled.value, onChange: event => update(event.target.value) }, options)]);
        return () => h('section', { class: 'workspace commercial-workspace', 'aria-busy': pending.value }, [
            h('h1', { ref: heading, tabindex: '-1' }, 'Sale drafts'), h('p', 'Prepare an online basket. Totals are estimates. Posting, stock deductions, payment receipts and credit creation arrive in WP3.'),
            !online.value && h('p', { class: 'alert alert-warning' }, 'You are offline. Changes are disabled and are not queued.'),
            props.readOnly && h('p', { class: 'alert alert-warning' }, 'Your current access permits viewing only.'),
            ...feedback(error.value, notice.value), pending.value && h('p', { role: 'status' }, 'Please wait…'),
            !draft.value ? h('section', [
                record.value && action('Create Sale draft', create, disabled.value, 'btn-primary'), action('Refresh drafts', () => load(), pending.value || !online.value),
                !rows.value.length && !pending.value && h('p', 'No saved Sale drafts.'),
                ...rows.value.map(row => h('article', { class: 'commercial-card', key: row.public_id }, [h('h2', row.customer_name ?? 'Walk-in Customer'), h('p', `Estimate: ${money(row.total_amount)}`), h('p', `Saved: ${row.updated_at} UTC`), action('Open draft', () => open(row.public_id), pending.value || !online.value)])), pager(pagination, pending.value || !online.value, load),
            ].filter(Boolean)) : h('section', [
                h('p', { class: 'break-all' }, `Draft reference: ${draft.value.public_id}`), h('p', `Estimate: ${money(draft.value.total_amount)} · ${draft.value.customer_name ?? 'Walk-in Customer'}`),
                h('p', `Business time: ${draft.value.occurred_at} UTC`),
                action('Back to drafts', back, pending.value), action('Reload saved draft', () => open(draft.value.public_id), pending.value || !online.value),
                h('p', 'Reload restores the saved basket, Customer and business time. Unsaved edits, cash/transfer preparation and due date reset.'),
                record.value && h('form', { class: 'commercial-form', onSubmit: saveHeader }, [
                    h('h2', 'Draft details'),
                    field('sale-customer-search', 'Search active Customers by name', customerSearch.value, value => { customerSearch.value = value; }, { disabled: disabled.value, type: 'search', maxlength: 160 }),
                    action('Find Customers', () => run(() => loadLookup('customers')), disabled.value),
                    select('sale-customer', 'Customer (optional for a fully paid Sale)', header.customer_public_id, value => { header.customer_public_id = value; preview.value = null; }, [h('option', { value: '' }, 'Walk-in / no Customer'),
                        draft.value.customer_public_id && !customers.value.some(c => c.public_id === draft.value.customer_public_id) && h('option', { value: draft.value.customer_public_id }, `${draft.value.customer_name} (${draft.value.customer_status})`),
                        ...customers.value.map(customer => h('option', { value: customer.public_id }, customer.display_name))].filter(Boolean)),
                    draft.value.customer_status === 'INACTIVE' && h('p', { class: 'alert alert-warning' }, 'This Customer is inactive. Choose an active Customer or clear the selection before saving.'),
                    field('sale-occurred', 'Business time (this device’s timezone)', header.occurred_at, value => { header.occurred_at = value; preview.value = null; }, { type: 'datetime-local', step: '1', required: true, disabled: disabled.value }), submit('Save draft details', disabled.value),
                ].filter(Boolean)),
                quickCreate.value && record.value && h('details', { class: 'commercial-card' }, [h('summary', 'Quick-create Customer'), h('form', { onSubmit: createCustomer }, [field('sale-quick-name', 'New Customer name', quickName.value, value => { quickName.value = value; }, { required: true, maxlength: 160, disabled: disabled.value }), h('p', 'Creates a Customer identity only. Private details can be added in Customers with permission.'), submit('Create Customer', disabled.value)])]),
                h('h2', 'Basket'), !draft.value.items.length && h('p', 'No Products in this draft.'),
                ...draft.value.items.map(item => h('article', { class: 'commercial-card', key: item.public_id }, [h('h3', `${item.line_number}. ${item.product_name}`), h('p', `${item.quantity} ${item.unit_name} × ${money(item.unit_selling_price)} = ${money(item.line_sales_amount)}`), item.product_status !== 'ACTIVE' && h('p', { class: 'alert alert-warning' }, 'Inactive Product: remove or replace this line.'), item.current_selling_price !== item.unit_selling_price && h('p', 'The current Product price differs from this saved quote. Editing without override permission uses the current price.'), record.value && action('Edit line', () => edit(item), disabled.value), record.value && action('Remove line', () => mutate(`/items/${item.public_id}`, 'DELETE', {}, resetLine), disabled.value, 'btn-outline-danger')].filter(Boolean))),
                record.value && h('form', { class: 'commercial-form', onSubmit: saveLine }, [
                    h('h2', line.public_id ? 'Edit basket line' : 'Add Product'),
                    field('sale-product-search', 'Search active Products', productSearch.value, value => { productSearch.value = value; }, { type: 'search', disabled: disabled.value, maxlength: 160 }), action('Find Products', () => run(() => loadLookup('products')), disabled.value),
                    select('sale-product', 'Product', line.product_public_id, selectProduct, [h('option', { value: '' }, 'Choose a Product'), line.product_public_id && !products.value.some(p => p.public_id === line.product_public_id) && h('option', { value: line.product_public_id }, draft.value.items.find(i => i.product_public_id === line.product_public_id)?.product_name ?? 'Selected Product'), ...products.value.map(product => h('option', { value: product.public_id }, `${product.name} · ${product.unit_name} · ${money(product.current_selling_price)}`))].filter(Boolean)),
                    field('sale-quantity', 'Quantity (up to 4 decimal places)', line.quantity, value => { line.quantity = value; }, { inputmode: 'decimal', required: true, disabled: disabled.value, maxlength: 20 }),
                    field('sale-price', override.value ? 'Selling price per unit (₦)' : 'Quoted price per unit (₦; current price used on save)', line.unit_selling_price, value => { line.unit_selling_price = value; }, { inputmode: 'decimal', required: true, disabled: disabled.value || !override.value, maxlength: 20 }),
                    field('sale-line-order', 'Line order (optional when adding)', line.line_number, value => { line.line_number = value; }, { type: 'number', min: 1, max: 65535, disabled: disabled.value }),
                    submit(line.public_id ? 'Save line' : 'Add line', disabled.value || !line.product_public_id), line.public_id && action('Cancel line edit', resetLine, pending.value),
                ].filter(Boolean)),
                record.value && h('form', { class: 'commercial-form', onSubmit: review }, [
                    h('h2', 'Payment and credit preparation'), h('p', 'Save all basket and Customer edits first. These payment amounts and due date are temporary, reset on reload, and create no financial records.'),
                    ...[['cash_amount', 'Prepared cash (₦)'], ['transfer_amount', 'Prepared transfer (₦)'], ['due_date', 'Credit due date (optional; Shop calendar date)']].map(([key, label]) => field(`sale-${key}`, label, payment[key], value => { payment[key] = value; preview.value = null; }, { disabled: disabled.value, type: key === 'due_date' ? 'date' : 'text', inputmode: key === 'due_date' ? undefined : 'decimal', maxlength: key === 'due_date' ? undefined : 20 })), submit('Review preparation', disabled.value || !draft.value.items.length),
                ]),
                preview.value && h('section', { class: 'commercial-card', role: 'status' }, [h('h2', 'Preparation estimate'), h('dl', [['Total', preview.value.total_amount], ['Cash', preview.value.cash_amount], ['Transfer', preview.value.transfer_amount], ['Paid', preview.value.paid_amount], ['Credit', preview.value.credit_amount]].flatMap(([label, value]) => [h('dt', label), h('dd', money(value))])), h('p', `Due date: ${preview.value.due_date ?? 'Not specified'}`), h('p', `Based on saved draft version ${preview.value.draft_updated_at}. No payment or Receivable was saved.`)]),
                record.value && h('form', { class: 'sensitive-action', onSubmit: discard }, [h('h2', 'Discard draft'), field('sale-discard-reason', 'Discard reason', reason.value, value => { reason.value = value; }, { multiline: true, required: true, maxlength: 500, disabled: disabled.value }), submit('Discard draft', disabled.value)]),
            ].filter(Boolean)),
        ].filter(Boolean));
    },
};
