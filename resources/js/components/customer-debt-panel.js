import { apiRequest } from '../api/http-client.js';
import { UiLoading, UiRecordList, UiInformationPanel, UiAlert } from './ui/index.js';
import { selectField, facts } from './trading-ui.js';
import { has, money, field, action, submit, pager } from './commercial-ui.js';
const { h, ref, reactive, computed, watch, onBeforeUnmount, nextTick } = window.Vue;
const states = { OUTSTANDING: 'Unpaid', PARTIAL: 'Partly paid', SETTLED: 'Paid', NO_DEBT: 'No unpaid bills' };
const statuses = [['OWING', 'Still to pay'], ['OUTSTANDING', 'Unpaid'], ['PARTIAL', 'Partly paid'], ['SETTLED', 'Paid'], ['OVERDUE', 'Payment overdue'], ['ALL', 'All']];
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const dateLabel = value => value ? new Intl.DateTimeFormat('en-NG', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`)) : 'No due date';
const signedMoney = value => value?.startsWith('-') ? `−${money(value.slice(1))}` : money(value);

/** Online read-only balances. No collection/adjustment API or browser-persisted debt cache. */
export const CustomerDebtPanel = {
    props: { permissions: { type: Array, default: () => [] }, hash: { type: String, default: '#customers/debts' } },
    emits: ['navigate'],
    setup(props, { emit }) {
        const data = ref(null), busy = ref(false), error = ref(''), heading = ref(null), online = ref(navigator.onLine);
        const search = ref(''), status = ref('OWING'), customerStatus = ref('ALL');
        const pagination = reactive({ page: 1, per_page: 20, total: 0, total_pages: 0 });
        let generation = 0, controller = null, alive = true;
        const allowed = computed(() => has(props.permissions, 'customer.view') && has(props.permissions, 'debt.view'));
        const route = computed(() => {
            if (props.hash === '#customers/debts') return { kind: 'customers' };
            const parts = props.hash.split('/');
            if (parts[0] === '#customers' && parts[1] === 'debt' && uuid.test(parts[2] || '')) {
                if (parts.length === 3) return { kind: 'customer', customer: parts[2] };
                if (parts.length === 5 && parts[3] === 'bill' && uuid.test(parts[4] || '')) return { kind: 'bill', customer: parts[2], bill: parts[4] };
            }
            return { kind: 'invalid' };
        });
        const go = hash => emit('navigate', hash);
        async function load(page = 1) {
            const current = ++generation;
            controller?.abort(); controller = new AbortController();
            data.value = null; error.value = ''; busy.value = false;
            if (!allowed.value) return;
            if (!online.value) { error.value = 'You are offline. Connect to see the latest balances.'; return; }
            const target = route.value;
            if (target.kind === 'invalid') { error.value = 'This bill link is incomplete. Go back to the customer list.'; return; }
            busy.value = true;
            const query = new URLSearchParams({ page, per_page: pagination.per_page, status: status.value });
            if (target.kind === 'customers') { query.set('search', search.value); query.set('customer_status', customerStatus.value); }
            const path = target.kind === 'customers' ? `/api/v1/shop/debts?${query}`
                : target.kind === 'customer' ? `/api/v1/shop/customers/${target.customer}/debt?${query}`
                    : `/api/v1/shop/debt-bills/${target.bill}`;
            try {
                const response = await apiRequest(path, { signal: controller.signal });
                if (!alive || current !== generation) return;
                if (target.kind === 'bill' && response.data.customer_public_id !== target.customer) {
                    error.value = 'This bill does not belong to the customer in this link.'; return;
                }
                data.value = response.data;
                if (data.value.pagination) Object.assign(pagination, data.value.pagination);
                await nextTick(); if (alive && current === generation) heading.value?.focus();
            } catch (failure) {
                if (alive && current === generation) {
                    error.value = ['DEBT_SETUP_REQUIRED', 'DEBT_EVIDENCE_INVALID'].includes(failure.code) ? failure.message
                        : failure.status === 403 ? 'You are not allowed to view these balances. Ask your shop owner for help.'
                            : failure.status === 404 ? 'This customer or bill was not found.'
                                : 'We could not load these balances. Check your connection and try again.';
                }
            } finally { if (alive && current === generation) busy.value = false; }
        }
        const connection = () => { online.value = navigator.onLine; load(pagination.page); };
        window.addEventListener('online', connection); window.addEventListener('offline', connection);
        watch([() => props.hash, allowed], () => { status.value = route.value.kind === 'customers' ? 'OWING' : 'ALL'; load(1); }, { immediate: true });
        onBeforeUnmount(() => { alive = false; generation++; controller?.abort(); window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        function balanceSummary(summary, filter = false) {
            return h('section', { class: 'commercial-card' }, [h('h3', filter ? 'Totals for this list' : 'Customer total'), facts([
                ['Still to pay', money(summary.outstanding_amount)], ['Payment overdue', money(summary.overdue_amount)],
            ])]);
        }
        function filters(customers) {
            return h('form', { class: 'commercial-filters', onSubmit: event => { event.preventDefault(); load(1); } }, [
                customers && field('debt-search', 'Customer name', search.value, value => { search.value = value; }, { type: 'search', maxlength: 160 }),
                selectField('debt-status', 'Show balances', status.value, value => { status.value = value; }, statuses),
                customers && selectField('debt-customer-status', 'Customer records', customerStatus.value, value => { customerStatus.value = value; }, [['ALL', 'Active and inactive'], ['ACTIVE', 'Active'], ['INACTIVE', 'Inactive']]),
                submit('Find balances', busy.value || !online.value, { loading: busy.value, busyLabel: 'Finding balances…', icon: 'search' }),
            ].filter(Boolean));
        }
        function billCard(bill, customer) {
            return h('article', { class: 'commercial-card' }, [
                h('h3', 'Bill from a sale'), h('p', `Bill reference: ${bill.public_id}`),
                h('p', states[bill.settlement_state]),
                bill.is_overdue && h(UiInformationPanel, { severity: 'warning', message: 'Payment overdue' }),
                facts([['Still to pay', money(bill.outstanding_amount)], ['Due date', dateLabel(bill.due_date)]]),
                action('View bill', () => go(`#customers/debt/${customer}/bill/${bill.public_id}`), busy.value || !online.value),
            ].filter(Boolean));
        }
        return () => h('section', { class: 'workspace commercial-workspace trading-screen ui-scope', 'aria-busy': busy.value }, [
            h('h2', { ref: heading, tabindex: '-1' }, route.value.kind === 'bill' ? 'Bill details' : 'Money customers owe'),
            action('Back to customer records', () => go('#customers'), false, 'btn-outline-primary', { icon: 'back' }),
            route.value.kind !== 'customers' && action('Back to balances', () => go('#customers/debts')),
            !allowed.value ? h(UiInformationPanel, { severity: 'warning', message: 'You are not allowed to view customer balances. Ask your shop owner for help.' }) : [
                h('p', 'Check the bills and amounts each customer still needs to pay.'),
                busy.value && h(UiLoading, { label: 'Loading the latest balances…' }),
                error.value && h(UiAlert, { severity: 'error', message: error.value, persistent: true }),
                action('Refresh balances', () => load(pagination.page), busy.value || !online.value, 'btn-outline-primary', { icon: 'refresh' }),
                route.value.kind === 'customers' && filters(true),
                data.value && h('p', `Due dates follow your shop’s date: ${dateLabel(data.value.shop_date)}. Balances are checked when you refresh.`),
                data.value && route.value.kind === 'customers' && [
                    balanceSummary(data.value.summary, true),
                    h('p', `${data.value.summary.customer_count} matching customers`),
                    h(UiRecordList, { records: data.value.items, emptyTitle: 'No matching balances', emptyMessage: 'Try another name or choose a different balance filter.' }, {
                        record: ({ record }) => h('article', { class: 'commercial-card' }, [
                            h('h3', record.display_name), h('p', record.customer_status === 'INACTIVE' ? 'Inactive customer — past bills are kept' : 'Active customer'),
                            h('p', states[record.settlement_state]),
                            record.overdue_bill_count > 0 && h(UiInformationPanel, { severity: 'warning', message: `${record.overdue_bill_count} bills have overdue payments` }),
                            facts([['Still to pay', money(record.outstanding_amount)], ['Next due date', dateLabel(record.next_due_date)]]),
                            action('View bills', () => go(`#customers/debt/${record.public_id}`), busy.value || !online.value),
                        ].filter(Boolean)),
                    }), pager(pagination, busy.value || !online.value, load),
                ],
                data.value && route.value.kind === 'customer' && [
                    h('h3', data.value.customer.display_name),
                    data.value.customer.status === 'INACTIVE' && h('p', 'This customer is inactive. Their past bills are kept.'),
                    balanceSummary(data.value.summary), h('p', 'The customer total includes every bill. The filter below changes only the bill list.'), filters(false),
                    h(UiRecordList, { records: data.value.bills, emptyTitle: 'No matching bills', emptyMessage: 'Try a different balance filter. A customer may also have no credit bills.' }, {
                        record: ({ record }) => billCard(record, data.value.customer.public_id),
                    }), pager(pagination, busy.value || !online.value, load),
                ],
                data.value && route.value.kind === 'bill' && [
                    h('h3', data.value.customer_name), h('p', states[data.value.settlement_state]), h('p', `Bill reference: ${data.value.public_id}`),
                    data.value.is_overdue && h(UiInformationPanel, { severity: 'warning', message: 'Payment overdue' }),
                    facts([
                        ['Original amount left unpaid', money(data.value.original_amount)],
                        ['Bill changes, including returns', signedMoney(data.value.adjustment_delta)],
                        ['Bill amount after changes', money(data.value.adjusted_amount)],
                        ['Payments toward this bill', money(data.value.collected_amount)],
                        ['Still to pay', money(data.value.outstanding_amount)], ['Due date', dateLabel(data.value.due_date)],
                    ]),
                    h('p', 'A return can reduce a bill without being a payment. The original sale receipt keeps the amounts recorded at the time.'),
                    action('Back to this customer’s bills', () => go(`#customers/debt/${route.value.customer}`)),
                    has(props.permissions, 'sale.view') && action('View original sale', () => go(`#sale/${data.value.sale_public_id}`)),
                ].filter(Boolean),
            ],
        ].filter(Boolean));
    },
};
