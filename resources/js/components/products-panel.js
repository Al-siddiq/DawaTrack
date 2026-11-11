import { UiBadge, UiLoading, UiRecordList, UiSheet, UiAccordion, UiStatusPanel } from './ui/index.js';
import { field, action, submit, feedback, pager, money } from './commercial-ui.js';
import { useTradingConfirm, selectField, facts, stateLabel, note, fieldSummary } from './trading-ui.js';
const { h, ref, computed, onMounted, onUnmounted } = window.Vue;
export const ProductsPanel = {
    props: { products: Object, inventory: Object, context: Object, readOnly: Boolean },
    setup(props) {
        const sheetCloseWarning = ref(false);
        const confirmation = useTradingConfirm(), online = ref(navigator.onLine);
        const connection = () => { online.value = navigator.onLine; };
        onMounted(() => { window.addEventListener('online', connection); window.addEventListener('offline', connection); });
        onUnmounted(() => { window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        const busy = computed(() => props.products.pending || props.products.loading || props.inventory.pending);
        const blocked = computed(() => busy.value || props.readOnly || !online.value);
        const doSubmit = work => event => { event.preventDefault(); if (!blocked.value) work(); };
        const guard = work => () => confirmation.guard(work, '#products');
        const set = (object, key) => value => { object[key] = value; };
        const status = product => h(UiBadge, { label: product.is_negative_stock ? 'Below zero' : product.is_low_stock ? 'Low stock' : stateLabel(product.status), severity: product.is_negative_stock ? 'error' : product.is_low_stock ? 'warning' : 'neutral' });
        function editForm() {
            const p = props.products;
            return h('form', { class: 'commercial-form', onSubmit: doSubmit(p.save) }, [
                h('h2', p.selected ? 'Edit product' : 'Add product'), h('p', 'Use the name and unit you sell, such as a tablet, bottle or sachet.'),
                fieldSummary(p.fieldErrors, { name: 'product-name', unit_name: 'product-unit', current_selling_price: 'product-price' }),
                field('product-name', 'Product name', p.form.name, set(p.form, 'name'), { required: true, maxlength: 180, disabled: blocked.value, errors: p.fieldErrors.name || [], onBlur: p.checkSimilar }),
                p.similarProducts.length > 0 && note('A product with a similar name already exists. Continue only if this is a different product.', 'warning'),
                field('product-unit', 'Unit you sell', p.form.unit_name, set(p.form, 'unit_name'), { required: true, maxlength: 40, disabled: blocked.value, errors: p.fieldErrors.unit_name || [], help: 'Once stock has been recorded, add a different product rather than changing this unit.' }),
                field('product-price', 'Selling price per unit (₦)', p.form.current_selling_price, set(p.form, 'current_selling_price'), { required: true, inputmode: 'decimal', disabled: blocked.value, errors: p.fieldErrors.current_selling_price || [] }),
                h('div', { class: 'ui-actions' }, [submit('Save product', blocked.value || !p.canManage, { loading: p.pending, busyLabel: 'Saving product…' }), action('Cancel editing', guard(() => p.selected ? p.open(p.selected) : p.close()), busy.value)]),
            ].filter(Boolean));
        }
        function movementSheet() {
            const a = props.inventory, m = a.movement;
            return h(UiSheet, { open: !!m, placement: 'end', title: 'Stock record', busy: a.pending, beforeClose: () => { if (a.reversalReason.trim()) { sheetCloseWarning.value = true; return false; } return true; }, 'onUpdate:open': value => { if (!value) a.closeMovement(); } }, {
                default: () => m ? [h('h3', `${a.movementLabel(m)} · ${m.product_name}`), facts([['Change', `${m.quantity_delta} ${m.unit_name}`], ['Before', m.quantity_before], ['After', m.quantity_after]]), m.reason_text && h('p', `Reason: ${m.reason_text}`), h('p', { class: 'trading-record-meta' }, `Record: ${m.public_id}`), m.original_movement_public_id && h('p', `Cancels stock record: ${m.original_movement_public_id}`), m.reversal_public_id && h('p', `Cancellation record: ${m.reversal_public_id}`),
                    a.canReverse && m.can_reverse && h('form', { class: 'commercial-form trading-danger-zone', onSubmit: doSubmit(a.reverseMovement) }, [h('p', 'This saves a linked record that undoes this stock change. The original record stays.'), field('movement-reason', 'Why are you undoing this change?', a.reversalReason, value => { a.reversalReason = value; }, { multiline: true, required: true, maxlength: 500, disabled: blocked.value }), submit('Confirm undo stock change', blocked.value, { loading: a.pending, variant: 'danger-secondary', icon: 'cancel-record' })]), ...feedback(a.error, a.notice),
                sheetCloseWarning.value && note('You entered a reason. Keep editing or discard that reason before closing.', 'warning'), sheetCloseWarning.value && action('Discard reason and close', () => { a.reversalReason = ''; sheetCloseWarning.value = false; a.closeMovement(); }, a.pending, 'btn-outline-danger'),
                ].filter(Boolean) : [],
            });
        }
        function detail() {
            const p = props.products, a = props.inventory, row = p.selected;
            return h('div', { class: 'trading-stack' }, [
                h('article', { class: 'commercial-card' }, [h('div', { class: 'trading-task-header' }, [h('h2', row.name), status(row)]), facts([['Stock left', `${row.expected_quantity} ${row.unit_name}`], ['Selling price per unit', money(row.current_selling_price)], ['Low stock alert at', `${row.low_stock_threshold} ${row.unit_name}`]]), row.average_unit_cost != null && facts([['Recorded cost per unit', money(row.average_unit_cost)], ['Cost record', stateLabel(row.cost_basis_state)]]), row.is_negative_stock && note('Stock is below zero. Check the stock record and correct any missing entries.', 'warning'), h('div', { class: 'ui-actions' }, [action('Back to products', guard(p.close), busy.value), p.canManage && action('Edit product', guard(p.beginEdit), blocked.value, 'btn-outline-primary', { icon: 'edit' })])]),
                h('div', { class: 'trading-grid' }, [h('div', { class: 'trading-stack' }, [
                    props.context.capabilities.adjust_inventory && h(UiAccordion, { title: 'Low stock alert' }, { default: () => h('form', { class: 'commercial-form', onSubmit: doSubmit(p.updateThreshold) }, [field('product-threshold', 'Show a low stock alert at', p.threshold, value => { p.threshold = value; }, { inputmode: 'decimal', disabled: blocked.value || !p.canAdjust, errors: p.fieldErrors.low_stock_threshold || [], help: `Quantity in ${row.unit_name}.` }), submit('Save alert level', blocked.value || !p.canAdjust, { loading: p.pending })]) }),
                    a.canAdjust && h(UiAccordion, { title: 'Record damaged, missing or corrected stock' }, { default: () => h('form', { class: 'commercial-form', onSubmit: doSubmit(async () => { if (await confirmation.ask({ title: 'Save this stock change?', message: `Recorded stock will change from ${row.expected_quantity} to ${a.resultingStock} ${row.unit_name}. The reason and original record will be kept.`, confirm: 'Save stock change' })) a.record(); }) }, [
                        selectField('stock-action', 'What happened?', a.form.movement_type, set(a.form, 'movement_type'), [['DAMAGED', 'Damaged'], ['EXPIRED', 'Expired'], ['OWNER_WITHDRAWAL', 'Taken for owner use'], ['MISSING_LOSS', 'Missing or lost'], ['CORRECTION', 'Correct the recorded quantity']], { disabled: blocked.value }),
                        a.form.movement_type === 'CORRECTION' && selectField('stock-direction', 'Change', a.form.direction, set(a.form, 'direction'), [['ADD', 'Add stock'], ['REMOVE', 'Remove stock']], { disabled: blocked.value }),
                        field('stock-quantity', `Quantity (${row.unit_name})`, a.form.quantity, set(a.form, 'quantity'), { inputmode: 'decimal', required: true, disabled: blocked.value }),
                        field('stock-reason', 'Why did the stock change?', a.form.reason, set(a.form, 'reason'), { multiline: true, maxlength: 500, required: true, disabled: blocked.value }),
                        facts([['Stock now', `${row.expected_quantity} ${row.unit_name}`], ['Stock after this change', `${a.resultingStock} ${row.unit_name}`]]), h('p', 'Stock below zero is allowed and will be highlighted.'), submit('Review stock change', blocked.value, { loading: a.pending }),
                    ].filter(Boolean)) }),
                ].filter(Boolean)), h('section', { class: 'commercial-card' }, [h('h2', 'Stock history'), !p.history.length && h('p', 'No stock changes recorded yet.'), ...p.history.map(m => h('article', { class: 'return-line', key: m.public_id }, [h('h3', a.movementLabel(m)), facts([['Change', `${m.quantity_delta} ${row.unit_name}`], ['Before', m.quantity_before], ['After', m.quantity_after]]), m.intake_public_id && h('p', `${m.intake_kind === 'OPENING' ? 'Starting stock' : 'Stock delivery'} · ${m.intake_reference || 'No reference'}`), m.reason_text && h('p', `Reason: ${m.reason_text}`), h('p', { class: 'trading-record-meta' }, `${m.actor_name} · ${m.posted_at} UTC`), action('View stock record', () => a.openMovement(m), busy.value, 'btn-outline-primary', { icon: 'activity' })])), pager(p.historyPagination, busy.value, page => confirmation.guard(() => p.open(row, page)))])]),
                p.canManage && row.status === 'ACTIVE' && h(UiAccordion, { title: 'Make this product inactive', class: 'trading-danger-zone' }, { default: () => h('form', { class: 'commercial-form', onSubmit: doSubmit(async () => { if (await confirmation.ask({ title: 'Make this product inactive?', message: 'It will not be available for new sales. Stock quantities and past records stay.', confirm: 'Make inactive', danger: true })) p.deactivate(); }) }, [h('p', 'This does not remove stock or delete past records.'), field('product-inactive-reason', 'Why are you making this product inactive?', p.deactivateReason, value => { p.deactivateReason = value; }, { required: true, multiline: true, maxlength: 500, disabled: blocked.value, errors: p.fieldErrors.reason || [] }), submit('Make product inactive', blocked.value, { variant: 'danger-secondary', loading: p.pending })]) }),
            ].filter(Boolean));
        }
        return () => {
            const p = props.products;
            return h('section', { class: 'trading-screen ui-scope', 'aria-busy': busy.value }, [
                h('div', { class: 'trading-task-header' }, [h('div', [h('h2', 'Products and stock'), h('p', 'Find a product, check what is left or record a stock change.')]), !p.selected && !p.editing && p.canManage && action('Add product', () => p.beginCreate(), blocked.value, 'btn-primary')]),
                h(UiStatusPanel, { state: !online.value ? 'offline' : props.readOnly ? 'readonly' : 'ready' }), ...feedback(p.error, p.notice), ...feedback(props.inventory.error, props.inventory.notice), confirmation.render(),
                p.loading && h(UiLoading, { label: 'Loading products and stock…' }),
                p.editing ? editForm() : p.selected ? detail() : h('div', { class: 'trading-stack' }, [
                    h('form', { class: 'commercial-filters', onSubmit: event => { event.preventDefault(); p.filters.page = 1; p.load(); } }, [field('products-search', 'Find a product', p.filters.search, set(p.filters, 'search'), { type: 'search', placeholder: 'Name or strength' }), selectField('products-status', 'Show products', p.filters.status, set(p.filters, 'status'), [['ACTIVE', 'Active'], ['INACTIVE', 'Inactive'], ['ALL', 'All']]), selectField('products-stock', 'Stock level', p.filters.stock_state, set(p.filters, 'stock_state'), [['ALL', 'All levels'], ['LOW', 'Low stock'], ['NEGATIVE', 'Below zero']]), submit('Find products', p.loading || !online.value, { loading: p.loading, icon: 'search' })]),
                    h(UiRecordList, { records: p.items, busy: p.loading, error: p.error, emptyTitle: 'No matching products', emptyMessage: 'Try a different name or filter. Add a product if it is not listed.' }, { record: ({ record: row }) => h('article', { class: 'commercial-card' }, [h('div', { class: 'trading-task-header' }, [h('h3', row.name), status(row)]), facts([['Price per unit', money(row.current_selling_price)], ['Stock left', `${row.expected_quantity} ${row.unit_name}`]]), action('View product', () => p.open(row), busy.value || !online.value)]) }), pager(p.pagination, busy.value || !online.value, page => { p.filters.page = page; p.load(); }),
                ]), movementSheet(),
            ].filter(Boolean));
        };
    },
};
