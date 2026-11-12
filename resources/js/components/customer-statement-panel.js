import { apiRequest } from '../api/http-client.js';
import { action, field, submit, has, message } from './commercial-ui.js';
import { selectField, fieldSummary } from './trading-ui.js';
import { UiAlert, UiInformationPanel, UiLoading } from './ui/index.js';
import { CustomerStatementDocument } from './customer-statement-document.js';
const { h, ref, reactive, computed, watch, onBeforeUnmount, nextTick } = window.Vue;
const printLimit = 2000;

export const CustomerStatementPanel = {
    props: { customerId: { type: String, required: true }, permissions: { type: Array, default: () => [] } },
    emits: ['navigate'],
    setup(props, { emit }) {
        const data = ref(null), busy = ref(false), error = ref(''), validation = ref({}), heading = ref(null), documentHost = ref(null), printHost = ref(null);
        const online = ref(navigator.onLine), printing = ref(false), prepared = ref(null), printProgress = ref(0), refreshRequired = ref(false);
        const form = reactive({ from_date: '', to_date: '', per_page: '20' });
        const pages = ref([]), index = ref(0);
        let alive = true, generation = 0, printGeneration = 0, controller = null, printController = null;
        let closedForPrint = [];
        const allowed = computed(() => has(props.permissions, 'customer.view') && has(props.permissions, 'debt.view'));
        const changedFilters = computed(() => data.value && (form.from_date !== (data.value.filters.from_date || '') || form.to_date !== (data.value.filters.to_date || '') || Number(form.per_page) !== data.value.pagination.per_page));
        const locked = computed(() => busy.value || printing.value || !online.value || !allowed.value);
        const go = hash => emit('navigate', hash);
        function query(cursor = null) {
            const q = new URLSearchParams({ per_page: form.per_page });
            if (form.from_date) q.set('from_date', form.from_date);
            if (form.to_date) q.set('to_date', form.to_date);
            if (cursor) q.set('cursor', cursor);
            return `/api/v1/shop/customers/${props.customerId}/statement?${q}`;
        }
        function fail(failure) {
            data.value = null; prepared.value = null; pages.value = []; index.value = 0;
            refreshRequired.value = failure.code === 'STATEMENT_REFRESH_REQUIRED';
            validation.value = failure.details || {};
            error.value = refreshRequired.value ? 'The records changed or this page expired. Refresh the statement from the beginning.'
                : failure.status === 403 ? 'You are not allowed to view this statement. Ask your shop owner for help.'
                    : failure.status === 404 ? 'This customer was not found.'
                        : failure.code === 'STATEMENT_SETUP_REQUIRED' ? failure.message
                            : failure.status >= 500 || failure.status === 0 ? 'We could not load the statement. Check your connection and try again.' : message(failure);
        }
        async function load(cursor = null, target = 0) {
            if (!allowed.value || !online.value || printing.value) return;
            const current = ++generation; controller?.abort(); controller = new AbortController();
            busy.value = true; error.value = ''; validation.value = {}; refreshRequired.value = false; data.value = null;
            if (cursor === null) { pages.value = []; index.value = 0; }
            try {
                const result = await apiRequest(query(cursor), { signal: controller.signal });
                if (!alive || current !== generation) return;
                if (result.data.customer.public_id !== props.customerId) throw new Error('This statement belongs to another customer.');
                data.value = result.data; index.value = target;
                pages.value = [...pages.value.slice(0, target), result.data.pagination.page_cursor];
                await nextTick(); if (alive && current === generation) heading.value?.focus();
            } catch (failure) { if (alive && current === generation) fail(failure); }
            finally { if (alive && current === generation) busy.value = false; }
        }
        function show(event) { event?.preventDefault(); if (!locked.value) load(); }
        function previous() { if (!locked.value && !changedFilters.value && index.value > 0) load(pages.value[index.value - 1], index.value - 1); }
        function next() { if (!locked.value && !changedFilters.value && data.value?.pagination.next_cursor) load(data.value.pagination.next_cursor, index.value + 1); }
        function printCopy(host) {
            const article = host?.querySelector('.customer-statement');
            if (!article) return;
            const copy = article.cloneNode(true); copy.id = 'commercial-print-receipt';
            copy.querySelectorAll('button,.receipt-private,.ui-alert,.statement-links').forEach(node => node.remove());
            // Printed bill explanations must remain visible even if screen details were collapsed.
            copy.querySelectorAll('details').forEach(node => { node.open = true; });
            document.body.append(copy); document.body.classList.add('printing-commercial-receipt');
            try { window.print(); } finally { copy.remove(); document.body.classList.remove('printing-commercial-receipt'); }
        }
        function printPage() { if (!locked.value && !changedFilters.value && data.value) printCopy(documentHost.value); }
        async function printAll() {
            if (locked.value || changedFilters.value || !data.value || data.value.pagination.total > printLimit) return;
            printing.value = true; error.value = ''; printProgress.value = 0; prepared.value = null;
            const current = generation, operation = ++printGeneration, original = data.value;
            printController = new AbortController();
            try {
                let cursor = pages.value[0], first = null;
                const items = [], seen = new Set();
                do {
                    const result = await apiRequest(query(cursor), { signal: printController.signal });
                    if (!alive || current !== generation || operation !== printGeneration || !printing.value) return;
                    const page = result.data;
                    if (page.customer.public_id !== props.customerId || page.as_of !== original.as_of || page.pagination.total !== original.pagination.total) throw Error('The statement changed. Refresh before printing.');
                    first ||= page;
                    for (const row of page.items) {
                        const key = `${row.source_type}:${row.public_id}`;
                        if (seen.has(key) || items.length >= printLimit) throw Error('The statement pages need refreshing.');
                        seen.add(key); items.push(row);
                    }
                    printProgress.value = items.length;
                    cursor = page.pagination.next_cursor;
                } while (cursor);
                if (items.length !== first.pagination.total) throw Error('The statement is incomplete. Refresh before printing.');
                prepared.value = { ...first, items };
                await nextTick();
                if (alive && current === generation && operation === printGeneration && printing.value) printCopy(printHost.value);
            } catch (failure) {
                if (!alive || !printing.value || current !== generation || operation !== printGeneration) return;
                fail(failure);
                if (!failure.status) error.value = 'We could not prepare the full statement. Nothing was printed. Refresh and try again.';
            } finally { if (operation === printGeneration) { printing.value = false; prepared.value = null; printController = null; } }
        }
        function cancelPrint() { printGeneration++; printing.value = false; printController?.abort(); prepared.value = null; }
        function connection() {
            online.value = navigator.onLine; generation++; controller?.abort(); cancelPrint(); data.value = null; pages.value = []; index.value = 0; busy.value = false;
            error.value = online.value ? 'Your connection is back. Refresh to see the latest statement.' : 'You are offline. Connect and refresh to see the latest statement. Nothing is queued.';
        }
        function beforePrint() {
            closedForPrint = Array.from(documentHost.value?.querySelectorAll('details:not([open])') || []);
            for (const node of closedForPrint) node.open = true;
        }
        function afterPrint() { for (const node of closedForPrint) node.open = false; closedForPrint = []; }
        window.addEventListener('beforeprint', beforePrint); window.addEventListener('afterprint', afterPrint);
        window.addEventListener('online', connection); window.addEventListener('offline', connection);
        watch([() => props.customerId, allowed], () => {
            generation++; controller?.abort(); cancelPrint(); data.value = null; pages.value = []; index.value = 0; busy.value = false; error.value = ''; validation.value = {};
            if (allowed.value && online.value) load();
        }, { immediate: true });
        onBeforeUnmount(() => { alive = false; generation++; controller?.abort(); cancelPrint(); window.removeEventListener('online', connection); window.removeEventListener('offline', connection); window.removeEventListener('beforeprint', beforePrint); window.removeEventListener('afterprint', afterPrint); afterPrint(); });
        return () => h('section', { class: 'workspace commercial-workspace trading-screen ui-scope customer-statement-workspace', 'aria-busy': busy.value || printing.value }, [
            h('h2', { ref: heading, tabindex: '-1' }, 'Customer statement'),
            action('Back to customer bills', () => go(`#customers/debt/${props.customerId}`), busy.value || printing.value, 'btn-outline-primary', { icon: 'back' }),
            !allowed.value && h(UiInformationPanel, { severity: 'warning', message: 'You are not allowed to view customer statements. Ask your shop owner for help.' }),
            !online.value && h(UiInformationPanel, { severity: 'warning', message: 'You are offline. Connect to read or print a current statement.' }),
            allowed.value && [
                h('p', 'See how sales, payments, returns and bill changes affect what this customer still needs to pay. Dates use your shop’s time.'),
                h('form', { class: 'commercial-filters', onSubmit: show }, [
                    field('statement-from', 'From date (optional)', form.from_date, v => { form.from_date = v; }, { type: 'date', disabled: locked.value, errors: validation.value.from_date || [] }),
                    field('statement-to', 'To date (optional)', form.to_date, v => { form.to_date = v; }, { type: 'date', disabled: locked.value, errors: validation.value.to_date || [] }),
                    selectField('statement-size', 'Records per page', form.per_page, v => { form.per_page = v; }, [['20','20 records'],['50','50 records']], { disabled: locked.value }),
                    submit('Show statement', locked.value, { loading: busy.value, busyLabel: 'Loading statement…', icon: 'search' }),
                ]),
                fieldSummary(validation.value, { from_date: 'statement-from', to_date: 'statement-to', per_page: 'statement-size' }),
                error.value && h(UiAlert, { severity: 'error', message: error.value, persistent: true }),
                (busy.value || printing.value) && h(UiLoading, { label: printing.value ? `Preparing statement for printing… ${printProgress.value} records ready` : 'Loading statement…' }),
                printing.value && action('Cancel print preparation', cancelPrint, false, 'btn-outline-primary', { icon: 'cancel-record' }),
                action('Refresh statement', () => load(), locked.value, refreshRequired.value ? 'btn-primary' : 'btn-outline-primary', { icon: 'refresh' }),
                changedFilters.value && h(UiInformationPanel, { message: 'Choose Show statement to apply your new dates or page size before reading more pages or printing.' }),
                data.value && [
                    h('div', { ref: documentHost, class: 'statement-document-host' }, [h(CustomerStatementDocument, { data: data.value, permissions: props.permissions, onNavigate: go })]),
                    h('nav', { class: 'ui-actions', 'aria-label': 'Statement pages' }, [
                        action('Previous page', previous, locked.value || changedFilters.value || index.value === 0, 'btn-outline-primary', { icon: 'back' }),
                        h('span', `Page ${index.value + 1} of ${Math.max(1, Math.ceil(data.value.pagination.total / data.value.pagination.per_page))}`),
                        action('Next page', next, locked.value || changedFilters.value || !data.value.pagination.next_cursor, 'btn-outline-primary', { icon: 'next' }),
                    ]),
                    h('div', { class: 'ui-actions' }, [action('Print this page', printPage, locked.value || changedFilters.value, 'btn-outline-primary', { icon: 'print' }),
                        action('Print full date range', printAll, locked.value || changedFilters.value || data.value.pagination.total > printLimit, 'btn-outline-primary', { icon: 'print' })]),
                    data.value.pagination.total > printLimit && h('p', `For a full print copy, choose a shorter date range with up to ${printLimit} records. You can still read and print each page.`),
                ],
                prepared.value && h('div', { ref: printHost, class: 'statement-print-prepared', 'aria-hidden': 'true' }, [h(CustomerStatementDocument, { data: prepared.value, permissions: props.permissions, full: true })]),
            ],
        ].filter(Boolean));
    },
};
