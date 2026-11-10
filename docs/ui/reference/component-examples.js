import { UiButton, UiBadge, UiField, UiErrorSummary, UiAlert, UiLoading, UiEmptyState, UiInformationPanel, UiDialog, UiSheet, UiAccordion, UiDropdown, UiNavigation, UiPagination, UiRecordList, UiStatusPanel, useUiTask, useUiList } from '/public/assets/js/components/ui/index.js';
const { createApp, h, ref, computed, onBeforeUnmount } = window.Vue;

const app = createApp({
    setup() {
        const timeouts = new Map();
        function wait(ms) {
            return new Promise(resolve => {
                const id = window.setTimeout(() => { timeouts.delete(id); resolve(); }, ms);
                timeouts.set(id, resolve);
            });
        }
        onBeforeUnmount(() => { timeouts.forEach((resolve, id) => { window.clearTimeout(id); resolve(); }); timeouts.clear(); });
        const task = useUiTask({ kind: 'write' });
        const unknownTask = useUiTask({ kind: 'write' });
        const checking = ref(false), outcome = ref(''), selected = ref('home');
        const modal = ref(false), sheet = ref(false), dirty = ref(false), closeBlocked = ref(false);
        const cash = ref('8,000.00'), errors = ref([]), notice = ref(''), feedbackVersion = ref(0), successVisible = ref(false);
        const actionsMessage = ref('Choose a secondary action.');
        const allowedNavigation = ['home', 'sell', 'stock', 'customers', 'menu'].map(id => ({ id, icon: id, label: id === 'sell' ? 'Sell' : id[0].toUpperCase() + id.slice(1) }));
        const examples = [
            { public_id: 'example-amina', name: 'Amina Bello' }, { public_id: 'example-chidi', name: 'Chidi Okafor' }, { public_id: 'example-maryam', name: 'Maryam Ali' },
        ];
        const search = ref('');
        const list = useUiList(async query => {
            // Deliberately ignores AbortSignal: generation guard must also protect stale reads.
            await wait(query.search.length === 1 ? 1000 : 250);
            const matches = examples.filter(row => row.name.toLowerCase().includes((query.search || '').toLowerCase()));
            return { rows: matches.slice((query.page - 1) * 2, query.page * 2), pagination: { page: query.page, per_page: 2, total: matches.length, total_pages: Math.ceil(matches.length / 2) } };
        }, { perPage: 2 });
        list.load();
        async function showUnknown() {
            try { await unknownTask.run(async () => { await wait(900); throw Object.assign(new Error('Example response lost'), { status: 0 }); }); }
            catch { /* The real task state supplies the persistent outcome panel. */ }
        }
        async function checkStatus() {
            if (checking.value) return;
            checking.value = true; await wait(900);
            unknownTask.resolveOutcome('saved'); outcome.value = 'Example status confirmed saved. Reference DT-1043.'; checking.value = false;
        }
        function validate(event) {
            event.preventDefault();
            errors.value = cash.value === '7,500.00' || cash.value === '7500.00' ? [] : [{ id: 'component-cash', message: 'Enter 7,500.00 for this payment example.' }];
            notice.value = errors.value.length ? '' : 'Example input accepted. No payment was recorded.';
        }
        const article = (title, children) => h('article', { class: 'ui-panel component-example' }, [h('h3', title), ...children]);
        const button = (label, props = {}) => h(UiButton, props, () => label);
        return () => h('div', { class: 'reference-grid' }, [
            article('Navigation and actions', [
                h(UiNavigation, { items: allowedNavigation, variant: 'bottom', currentId: selected.value, label: 'Example navigation', onNavigate: item => { selected.value = item.id; } }),
                h('p', 'This example changes the selected destination only. Production destinations follow the authorized registry.'),
                h('div', { class: 'ui-actions' }, [button('Back to stock', { variant: 'secondary', icon: 'back' }), button('Add stock', { icon: 'add' })]),
            ]),
            article('Saving and reduced motion', [
                button('Save sale', { class: 'reference-save', icon: 'success', loading: task.pending.value, busyLabel: 'Saving sale…', onClick: () => task.run(() => wait(2500)) }),
                h('p', { role: 'status' }, task.status.value === 'saved' ? 'Example finished. No records were changed.' : 'Try the loading state; repeated clicks must not start another task.'),
                h(UiLoading, { label: 'Loading products…', variant: 'skeleton' }),
            ]),
            article('Correctable input', [
                h('form', { onSubmit: validate }, [
                    h(UiErrorSummary, { errors: errors.value }),
                    h(UiField, { id: 'component-cash', label: 'Paid in cash (₦)', help: 'Use 7,500.00 in this example.', errors: errors.value.map(row => row.message), required: true }, {
                        default: ({ controlAttrs }) => h('input', { ...controlAttrs, value: cash.value, inputmode: 'decimal', required: true, onInput: event => { cash.value = event.target.value; } }),
                    }),
                    button('Check payment', { type: 'submit' }),
                    notice.value && h(UiAlert, { severity: 'success', message: notice.value, persistent: true }),
                ]),
            ]),
            article('Optional information', [
                h(UiBadge, { label: 'Active', severity: 'success', icon: 'success' }),
                h(UiInformationPanel, { title: 'Still owed: ₦3,000.00', message: 'Keep essential totals and next steps visible.' }),
                h(UiAccordion, { title: 'Sale details' }, () => h('p', 'Reference DT-1043 · saved today, 10:42 am.')),
                h(UiEmptyState, { title: 'No matching sales', message: 'Try another customer name or date.' }),
            ]),
            article('Dialogs, sheets and unsaved edits', [
                h('div', { class: 'ui-actions' }, [button('Open confirmation', { onClick: () => { modal.value = true; } }), button('Choose customer', { variant: 'secondary', icon: 'customers', onClick: () => { closeBlocked.value = false; sheet.value = true; } })]),
                h('p', 'Use Tab, Shift+Tab and Escape. A second modal cannot open behind an existing one. Try closing with unsaved text.'),
                h(UiDialog, { open: modal.value, title: 'Review this change', description: 'No business operation occurs in this component example.', busy: task.pending.value, 'onUpdate:open': value => { modal.value = value; }, onBlocked: message => { actionsMessage.value = message; modal.value = false; } }, {
                    default: () => h(UiInformationPanel, { severity: 'warning', message: 'The original record remains in history. This example makes no change.' }),
                    footer: () => [button('Keep record', { variant: 'secondary', disabled: task.pending.value, onClick: () => { modal.value = false; } }), button('Show busy state', { loading: task.pending.value, busyLabel: 'Saving change…', onClick: () => task.run(() => wait(2500)) })],
                }),
                h(UiSheet, { open: sheet.value, title: 'Choose customer', 'onUpdate:open': value => { sheet.value = value; }, beforeClose: () => { closeBlocked.value = dirty.value; return !dirty.value; }, onBlocked: () => { sheet.value = false; } }, {
                    default: () => [h(UiField, { id: 'sheet-note', label: 'Example note' }, { default: ({ controlAttrs }) => h('input', { ...controlAttrs, onInput: () => { dirty.value = true; } }) }), closeBlocked.value && h(UiAlert, { severity: 'warning', persistent: true, dismissible: false, message: 'Your note is not saved. Keep editing or discard it to close.' }), button('Amina Bello', { variant: 'secondary', icon: 'account', onClick: () => { dirty.value = false; sheet.value = false; } })],
                    footer: () => button('Discard example note and close', { variant: 'secondary', onClick: () => { dirty.value = false; closeBlocked.value = false; sheet.value = false; } }),
                }),
            ]),
            article('Success and secondary actions', [
                button('Show success message', { variant: 'secondary', onClick: () => { feedbackVersion.value++; successVisible.value = true; } }),
                successVisible.value && h(UiAlert, { key: feedbackVersion.value, severity: 'success', persistent: false, message: 'Sale saved. Reference DT-1043.', onDismiss: () => { successVisible.value = false; } }),
                h('p', 'Saved evidence stays here after the message closes. Hover or focus the message to pause its timer.'),
                h(UiDropdown, { items: [{ id: 'print', label: 'Print receipt', icon: 'print' }, { id: 'view', label: 'View sale', icon: 'sales' }, { id: 'restricted', label: 'Unavailable action', disabled: true }], onSelect: item => { actionsMessage.value = `${item.label} selected in the example. No request was sent.`; } }),
                h('p', { role: 'status' }, actionsMessage.value),
            ]),
            article('Uncertain save and explicit status recovery', [
                button('Show lost response', { disabled: unknownTask.status.value === 'unknown', loading: unknownTask.pending.value, busyLabel: 'Saving example…', onClick: showUnknown }),
                h(UiStatusPanel, { state: unknownTask.status.value === 'unknown' ? 'unknown' : 'ready', checking: checking.value, onCheckStatus: checkStatus }),
                outcome.value && h(UiAlert, { severity: 'success', message: outcome.value, persistent: true }),
                h(UiStatusPanel, { state: 'offline' }),
            ]),
            article('Lists, paging and stale reads', [
                h(UiField, { id: 'component-search', label: 'Find example customers', help: 'Type quickly: old results must not replace the newest search.' }, { default: ({ controlAttrs }) => h('input', { ...controlAttrs, type: 'search', value: search.value, onInput: event => { search.value = event.target.value; list.setQuery({ search: search.value }); } }) }),
                h(UiRecordList, { records: list.rows.value, busy: list.pending.value, error: list.error.value, loadingLabel: 'Finding example customers…', emptyTitle: 'No matching customers' }, { record: ({ record }) => h('div', { class: 'ui-panel' }, record.name) }),
                h(UiPagination, { page: list.pagination.page, totalPages: list.pagination.total_pages, busy: list.pending.value, onPageRequest: page => list.load(page) }),
            ]),
        ]);
    },
});
app.config.idPrefix = 'component-example';
app.mount('#component-examples');
