import { apiRequest } from '../api/http-client.js';
import { UiLoading, UiAlert, UiButton, UiEmptyState } from './ui/index.js';

const { h, ref, reactive, onMounted, onUnmounted } = window.Vue;

const debtStates = {
    OUTSTANDING: 'Unpaid',
    PARTIAL: 'Partly paid',
    SETTLED: 'Paid',
    NO_DEBT: 'No unpaid bills',
};
const costStates = {
    VALID: 'Buying price is recorded',
    RETAINED: 'Last known buying price is being kept; value is not counted as confirmed',
    UNESTABLISHED: 'Buying price is not yet known',
};
const scopeLabels = { CASH: 'Cash check', STOCK: 'Stock check', FULL: 'Cash and stock check' };

function money(value, currency = 'NGN') {
    const amount = String(value ?? '0.00');
    const negative = amount.startsWith('-');
    const unsigned = negative ? amount.slice(1) : amount;
    const [whole = '0', fraction = '00'] = unsigned.split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const symbol = currency === 'NGN' ? '₦' : currency + ' ';
    return (negative ? '-' : '') + symbol + grouped + '.' + fraction.padEnd(2, '0').slice(0, 2);
}
function when(value, timezone) {
    if (!value) return 'Not recorded';
    const date = new Date(String(value).replace(' ', 'T') + 'Z');
    if (Number.isNaN(date.getTime())) return 'Time not available';
    return new Intl.DateTimeFormat('en-NG', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: 'numeric', minute: '2-digit', hour12: true,
        timeZone: timezone || 'Africa/Lagos',
    }).format(date);
}
function metric(label, value, explanation = '') {
    return h('article', { class: 'sales-report-metric' }, [
        h('span', { class: 'sales-report-metric-label' }, label),
        h('strong', { class: 'sales-report-metric-value' }, String(value)),
        explanation && h('p', { class: 'sales-report-help' }, explanation),
    ]);
}
function moneyMetric(label, value, currency, explanation = '') {
    return h('article', { class: 'sales-report-metric' }, [
        h('span', { class: 'sales-report-metric-label' }, label),
        h('strong', { class: 'sales-report-metric-value' }, money(value, currency)),
        explanation && h('p', { class: 'sales-report-help' }, explanation),
    ]);
}
function cashDifference(value, currency) {
    if (value === null || value === undefined) return 'No recorded difference';
    const amount = String(value);
    if (/^-?0(?:\.0+)?$/.test(amount)) return 'The cash count matched the expected amount.';
    const short = amount.startsWith('-');
    const absolute = short ? amount.slice(1) : amount;
    return 'Cash was ' + (short ? 'short by ' : 'over by ') + money(absolute, currency)
        + ' (recorded difference ' + (short ? '-' : '+') + money(absolute, currency) + ').';
}
function selectControl(id, label, value, options, onChange) {
    return h('label', { for: id }, [
        label,
        h('select', {
            id, class: 'form-select', value,
            onChange: event => onChange(event.target.value),
        }, options.map(([option, text]) => h('option', { value: option }, text))),
    ]);
}
function textControl(id, label, value, onInput, maxLength = 160) {
    return h('label', { for: id }, [
        label,
        h('input', {
            id, class: 'form-control', type: 'search', maxlength: maxLength,
            value, onInput: event => onInput(event.target.value),
        }),
    ]);
}

export const OperationalReportsPanel = {
    props: {
        capabilities: { type: Object, default: () => ({}) },
        permissions: { type: Array, default: () => [] },
    },
    emits: ['navigate'],
    setup(props, { emit }) {
        const stock = reactive({ data: null, loading: false, error: '', stale: false, queryKey: null, page: 1, per_page: 20, state: 'ALL', search: '' });
        const debt = reactive({ data: null, loading: false, error: '', stale: false, queryKey: null, page: 1, per_page: 20, status: 'OWING', customer_status: 'ALL', search: '' });
        const reconciliation = reactive({ data: null, loading: false, error: '', stale: false, queryKey: null, page: 1, per_page: 20, scope: 'ALL' });
        const online = ref(navigator.onLine);
        let alive = true;
        const controllers = { stock: null, debt: null, reconciliation: null };

        function stop(name) {
            controllers[name]?.abort();
            controllers[name] = null;
        }
        function query(values) {
            const params = new URLSearchParams();
            Object.entries(values).forEach(([key, value]) => {
                if (value !== '' && value !== null && value !== undefined) params.set(key, String(value));
            });
            return params.toString();
        }
        function failureText(failure, domain) {
            if (failure?.status === 403) return 'Your current access does not allow this report. Ask the shop owner for help.';
            if (failure?.status === 503) return failure.message || domain + ' information is not ready. Ask the administrator to check the application setup.';
            return 'We could not refresh this report. Check your connection and try again.';
        }
        async function load(name, state, path, filters) {
            if (!online.value || state.loading) return;
            stop(name);
            const controller = new AbortController();
            controllers[name] = controller;
            state.loading = true;
            state.error = '';
            try {
                const suffix = query(filters);
                if (state.queryKey !== suffix) {
                    state.data = null;
                    state.stale = false;
                    state.queryKey = suffix;
                }
                const response = await apiRequest(path + (suffix ? '?' + suffix : ''), { signal: controller.signal });
                if (!alive || controllers[name] !== controller) return;
                state.data = response.data;
                state.stale = false;
            } catch (failure) {
                if (alive && controllers[name] === controller && failure.name !== 'AbortError') {
                    const sensitiveFailure = [401, 403, 409, 422, 503].includes(failure.status);
                    if (sensitiveFailure) {
                        state.data = null;
                        state.stale = false;
                    } else {
                        state.stale = Boolean(state.data);
                    }
                    state.error = failureText(failure, name === 'stock' ? 'Stock' : name === 'debt' ? 'Customer balances' : 'Reconciliation');
                }
            } finally {
                if (alive && controllers[name] === controller) {
                    state.loading = false;
                    controllers[name] = null;
                }
            }
        }
        function loadStock(page = stock.page) {
            if (!props.capabilities.view_stock_report) return;
            stock.page = page;
            return load('stock', stock, '/api/v1/shop/reports/stock', {
                state: stock.state, search: stock.search, page, per_page: stock.per_page,
            });
        }
        function loadDebt(page = debt.page) {
            if (!props.capabilities.view_customer_debt_report) return;
            debt.page = page;
            return load('debt', debt, '/api/v1/shop/reports/customer-debt', {
                status: debt.status, customer_status: debt.customer_status, search: debt.search,
                page, per_page: debt.per_page,
            });
        }
        function loadReconciliation(page = reconciliation.page) {
            if (!props.capabilities.view_reconciliation_report) return;
            reconciliation.page = page;
            return load('reconciliation', reconciliation, '/api/v1/shop/reports/reconciliation', {
                scope: reconciliation.scope, page, per_page: reconciliation.per_page,
            });
        }
        function refreshAll() {
            if (!online.value) return;
            const work = [];
            if (props.capabilities.view_stock_report) work.push(loadStock(1));
            if (props.capabilities.view_customer_debt_report) work.push(loadDebt(1));
            if (props.capabilities.view_reconciliation_report) work.push(loadReconciliation(1));
            return Promise.all(work);
        }
        function connection() {
            online.value = navigator.onLine;
            if (!online.value) {
                Object.assign(stock, { data: null, loading: false, stale: false, queryKey: null, error: 'You are offline. Connect to see the current Stock position.' });
                Object.assign(debt, { data: null, loading: false, stale: false, queryKey: null, error: 'You are offline. Connect to see the latest customer balances.' });
                Object.assign(reconciliation, { data: null, loading: false, stale: false, queryKey: null, error: 'You are offline. Connect to see finalized Reconciliation results.' });
                Object.keys(controllers).forEach(stop);
            } else {
                refreshAll();
            }
        }
        onMounted(() => {
            window.addEventListener('online', connection);
            window.addEventListener('offline', connection);
            refreshAll();
        });
        onUnmounted(() => {
            alive = false;
            Object.keys(controllers).forEach(stop);
            window.removeEventListener('online', connection);
            window.removeEventListener('offline', connection);
        });

        function pager(state, reload, label) {
            const pages = state.data?.pagination?.total_pages ?? 0;
            if (pages < 2) return null;
            return h('nav', { class: 'ui-actions', 'aria-label': label + ' pages' }, [
                h(UiButton, { variant: 'secondary', disabled: state.loading || state.page <= 1, onClick: () => reload(state.page - 1) }, () => 'Previous'),
                h('span', { class: 'sales-report-help', role: 'status' }, 'Page ' + state.page + ' of ' + pages),
                h(UiButton, { variant: 'secondary', disabled: state.loading || state.page >= pages, onClick: () => reload(state.page + 1) }, () => 'Next'),
            ]);
        }
        function reportHeader(title, description, state, reload) {
            return h('div', { class: 'workspace-heading' }, [
                h('div', [h('h3', title), h('p', description)]),
                h(UiButton, { variant: 'secondary', icon: 'refresh', loading: state.loading, disabled: !online.value, onClick: reload }, () => 'Refresh'),
            ]);
        }
        function refreshed(data) {
            if (!data?.refreshed_at) return null;
            return h('p', { class: 'sales-report-refreshed' }, 'Last updated: ' + data.refreshed_at + ' (' + (data.shop?.timezone_name || 'Shop time') + ').');
        }
        function staleNote(state) {
            return state.stale && h(UiAlert, { severity: 'warning', message: state.error + ' The last successful report is shown and may be out of date; refresh before making decisions.', dismissible: false });
        }
        function sectionError(state, reload) {
            return [
                state.loading && h(UiLoading, { label: 'Loading report…' }),
                state.error && !state.stale && h(UiAlert, { severity: 'error', message: state.error, dismissible: false }),
                state.error && h(UiButton, { variant: 'secondary', disabled: !online.value, onClick: reload }, () => 'Try again'),
            ].filter(Boolean);
        }
        function stockSection() {
            if (!props.capabilities.view_stock_report) return null;
            const data = stock.data;
            return h('section', { class: 'sales-report-section', 'aria-busy': stock.loading }, [
                reportHeader('Stock position', 'See how much stock is recorded now and which products need attention.', stock, () => loadStock(stock.page)),
                h('form', { class: 'sales-report-filters', onSubmit: event => { event.preventDefault(); loadStock(1); } }, [
                    textControl('stock-report-search', 'Find a product', stock.search, value => { stock.search = value; }, 100),
                    selectControl('stock-report-state', 'Show products', stock.state, [
                        ['ALL', 'All products'], ['LOW', 'Low stock'], ['NEGATIVE', 'Below zero'],
                        ['NORMAL', 'Stock above alert level'], ['RETAINED', 'Last known buying price'], ['UNESTABLISHED', 'Buying price not known'],
                    ], value => { stock.state = value; }),
                    h(UiButton, { type: 'submit', icon: 'search', loading: stock.loading }, () => 'Find products'),
                ]),
                ...sectionError(stock, () => loadStock(stock.page)),
                data && (!stock.error || stock.stale) && [
                    staleNote(stock),
                    h('p', { class: 'sales-report-period-note' }, 'Current Shop stock · ' + data.shop.timezone_name),
                    h('div', { class: 'sales-report-metrics' }, [
                        metric('Products with stock records', data.summary.product_count),
                        metric('Low-stock products', data.summary.low_stock_count),
                        metric('Stock below zero', data.summary.negative_stock_count),
                        moneyMetric('Value with confirmed cost', data.summary.stock_value_confirmed, data.shop.currency_code,
                            'Only positive stock with a VALID current cost basis is included.'),
                    ]),
                    !data.summary.valuation_complete && h(UiAlert, {
                        severity: 'warning',
                        dismissible: false,
                        message: 'This is not a complete Stock valuation. ' + data.summary.retained_cost_product_count + ' product(s) have a retained cost basis, ' + data.summary.unestablished_cost_product_count + ' do not yet have a cost basis, and ' + data.summary.negative_stock_count + ' have stock below zero. These positions are not included in the confirmed value.',
                    }),
                    data.items.length
                        ? h('div', { class: 'sales-report-period-list' }, data.items.map(row => h('article', { class: 'sales-report-period-card', key: row.product_public_id }, [
                            h('header', { class: 'sales-report-period-head' }, [
                                h('h4', row.name),
                                h('strong', row.is_negative_stock ? 'Below zero' : row.is_low_stock ? 'Low stock' : 'Stock available'),
                            ]),
                            h('div', { class: 'sales-report-period-summary' }, [
                                h('div', { class: 'sales-report-line' }, [h('span', 'Stock left'), h('strong', row.expected_quantity + ' ' + row.unit_name)]),
                                h('div', { class: 'sales-report-line' }, [h('span', 'Low-stock alert at'), h('strong', row.low_stock_threshold + ' ' + row.unit_name)]),
                                h('div', { class: 'sales-report-line' }, [h('span', 'Cost record'), h('strong', costStates[row.cost_basis_state] || row.cost_basis_state)]),
                                h('div', { class: 'sales-report-line' }, [h('span', 'Stock value'), h('strong', row.stock_value === null ? 'Not included in confirmed value' : money(row.stock_value, data.shop.currency_code))]),
                                row.product_status === 'INACTIVE' && h('p', { class: 'sales-report-help' }, 'Inactive product — past Stock records are kept.'),
                            ]),
                            h(UiButton, { variant: 'secondary', onClick: () => emit('navigate', '#products/' + row.product_public_id) }, () => 'Open Stock record'),
                        ])))
                        : h(UiEmptyState, { title: 'No matching products', message: 'Try another product name or Stock filter.', icon: 'stock' }),
                    pager(stock, loadStock, 'Stock report'),
                    refreshed(data),
                ],
            ].filter(Boolean));
        }
        function debtSection() {
            if (!props.capabilities.view_customer_debt_report) return null;
            const data = debt.data;
            return h('section', { class: 'sales-report-section', 'aria-busy': debt.loading }, [
                reportHeader('Customer balances', 'See what customers still need to pay, including overdue amounts.', debt, () => loadDebt(debt.page)),
                h('form', { class: 'sales-report-filters', onSubmit: event => { event.preventDefault(); loadDebt(1); } }, [
                    textControl('debt-report-search', 'Customer name', debt.search, value => { debt.search = value; }),
                    selectControl('debt-report-status', 'Show balances', debt.status, [
                        ['OWING', 'Still to pay'], ['OUTSTANDING', 'Unpaid'], ['PARTIAL', 'Partly paid'],
                        ['OVERDUE', 'Payment overdue'], ['SETTLED', 'Paid'], ['NO_DEBT', 'No unpaid bills'], ['ALL', 'All'],
                    ], value => { debt.status = value; }),
                    selectControl('debt-report-customer-status', 'Customer records', debt.customer_status, [
                        ['ALL', 'Active and inactive'], ['ACTIVE', 'Active'], ['INACTIVE', 'Inactive'],
                    ], value => { debt.customer_status = value; }),
                    h(UiButton, { type: 'submit', icon: 'search', loading: debt.loading }, () => 'Find balances'),
                ]),
                ...sectionError(debt, () => loadDebt(debt.page)),
                data && (!debt.error || debt.stale) && [
                    staleNote(debt),
                    h('p', { class: 'sales-report-period-note' }, 'Balances checked for ' + data.shop_date + ' · Shop time: ' + data.shop.timezone_name),
                    h('div', { class: 'sales-report-metrics' }, [
                        metric('Customers in this list', data.summary.customer_count),
                        metric('Bills in this list', data.summary.bill_count),
                        moneyMetric('Customers still need to pay', data.summary.outstanding_amount, data.shop.currency_code),
                        moneyMetric('Payment overdue', data.summary.overdue_amount, data.shop.currency_code,
                            data.summary.overdue_customer_count + ' customer(s) have an overdue balance.'),
                    ]),
                    data.items.length
                        ? h('div', { class: 'sales-report-period-list' }, data.items.map(row => h('article', { class: 'sales-report-period-card', key: row.public_id }, [
                            h('header', { class: 'sales-report-period-head' }, [
                                h('h4', row.display_name),
                                h('strong', debtStates[row.settlement_state] || row.settlement_state),
                            ]),
                            h('div', { class: 'sales-report-period-summary' }, [
                                h('div', { class: 'sales-report-line' }, [h('span', 'Still to pay'), h('strong', money(row.outstanding_amount, data.shop.currency_code))]),
                                h('div', { class: 'sales-report-line' }, [h('span', 'Payment overdue'), h('strong', money(row.overdue_amount, data.shop.currency_code))]),
                                h('div', { class: 'sales-report-line' }, [h('span', 'Bills'), h('strong', String(row.bill_count))]),
                                h('div', { class: 'sales-report-line' }, [h('span', 'Earliest unpaid due date'), h('strong', row.earliest_due_date || 'No due date recorded')]),
                                row.customer_status === 'INACTIVE' && h('p', { class: 'sales-report-help' }, 'Inactive customer — past bills are kept.'),
                                row.is_overdue && h(UiAlert, { severity: 'warning', message: row.overdue_bill_count + ' bill(s) are overdue.', dismissible: false }),
                            ]),
                            h(UiButton, { variant: 'secondary', onClick: () => emit('navigate', '#customers/debt/' + row.public_id) }, () => 'View customer bills'),
                        ].filter(Boolean))))
                        : h(UiEmptyState, { title: 'No matching customer balances', message: 'Try another name or choose a different balance filter.', icon: 'customers' }),
                    pager(debt, loadDebt, 'Customer balance report'),
                    refreshed(data),
                ],
            ].filter(Boolean));
        }
        function reconciliationSection() {
            if (!props.capabilities.view_reconciliation_report) return null;
            const data = reconciliation.data;
            return h('section', { class: 'sales-report-section', 'aria-busy': reconciliation.loading }, [
                reportHeader('Finished shop checks', 'See finalized Cash, Stock and combined checks. Live Stock and Cash are not used to rewrite past results.', reconciliation, () => loadReconciliation(reconciliation.page)),
                h('form', { class: 'sales-report-filters', onSubmit: event => { event.preventDefault(); loadReconciliation(1); } }, [
                    selectControl('reconciliation-report-scope', 'Show checks', reconciliation.scope, [
                        ['ALL', 'All checks'], ['CASH', 'Cash checks'], ['STOCK', 'Stock checks'], ['FULL', 'Cash and stock checks'],
                    ], value => { reconciliation.scope = value; }),
                    h(UiButton, { type: 'submit', icon: 'search', loading: reconciliation.loading }, () => 'Find checks'),
                ]),
                ...sectionError(reconciliation, () => loadReconciliation(reconciliation.page)),
                data && (!reconciliation.error || reconciliation.stale) && [
                    staleNote(reconciliation),
                    h('p', { class: 'sales-report-period-note' }, 'Only finished checks are included · Shop time: ' + data.shop.timezone_name),
                    h('div', { class: 'sales-report-metrics' }, [
                        metric('Finished checks', data.summary.finalized_count),
                        metric('Cash checks', data.summary.cash_checks),
                        metric('Stock checks', data.summary.stock_checks),
                        metric('Cash and stock checks', data.summary.full_checks),
                    ]),
                    (!data.variance_visibility.cash || !data.variance_visibility.stock) && h(UiAlert, {
                        severity: 'warning', dismissible: false,
                        message: 'Some difference details are hidden because your access does not include review permission or a blind count is still in progress. Finish the count or ask an authorized reviewer.',
                    }),
                    data.variance_visibility.cash && reconciliation.scope !== 'STOCK' && metric('Cash checks with a difference', data.summary.cash_variance_checks,
                        'Each finished check is counted once. A Cash difference is not added to a later check.'),
                    data.variance_visibility.stock && reconciliation.scope !== 'CASH' && h('div', { class: 'sales-report-metrics' }, [
                        metric('Products counted below expected stock', data.summary.stock_shortage_items),
                        metric('Products counted above expected stock', data.summary.stock_surplus_items),
                    ]),
                    data.items.length
                        ? h('div', { class: 'sales-report-period-list' }, data.items.map(row => h('article', { class: 'sales-report-period-card', key: row.public_id }, [
                            h('header', { class: 'sales-report-period-head' }, [
                                h('h4', scopeLabels[row.scope] || row.scope),
                                h('strong', when(row.finalized_at, data.shop.timezone_name)),
                            ]),
                            h('p', { class: 'sales-report-help' }, 'Fixed check time: ' + when(row.cutoff_at, data.shop.timezone_name) + ' · Finished: ' + when(row.finalized_at, data.shop.timezone_name)),
                            data.variance_visibility.cash && row.scope !== 'STOCK' && h('div', { class: 'sales-report-line' }, [
                                h('span', 'Cash difference (counted minus expected)'),
                                h('strong', cashDifference(row.cash_variance_amount, data.shop.currency_code)),
                            ]),
                            data.variance_visibility.stock && row.scope !== 'CASH' && h('div', { class: 'sales-report-period-summary' }, [
                                h('div', { class: 'sales-report-line' }, [h('span', 'Products with a shortage'), h('strong', String(row.stock_shortage_items))]),
                                h('div', { class: 'sales-report-line' }, [h('span', 'Products with a surplus'), h('strong', String(row.stock_surplus_items))]),
                            ]),
                            h(UiButton, { variant: 'secondary', onClick: () => emit('navigate', row.detail_hash) }, () => 'Open finished check'),
                        ].filter(Boolean))))
                        : h(UiEmptyState, { title: 'No finished checks found', message: 'Checks appear here after they have been finalized.', icon: 'activity' }),
                    pager(reconciliation, loadReconciliation, 'Reconciliation report'),
                    refreshed(data),
                ],
            ].filter(Boolean));
        }

        return () => h('section', { class: 'sales-report-workspace operational-report-workspace', 'aria-busy': stock.loading || debt.loading || reconciliation.loading }, [
            h('div', { class: 'workspace-heading' }, [
                h('div', [
                    h('h2', { tabindex: '-1' }, 'Shop reports'),
                    h('p', 'Check current Stock, customer balances and finished shop checks. These reports use the saved shop records.'),
                ]),
                h(UiButton, { variant: 'secondary', icon: 'refresh', disabled: !online.value, onClick: refreshAll }, () => 'Refresh all reports'),
            ]),
            !online.value && h(UiAlert, { severity: 'warning', message: 'You are offline. Live report figures are not saved on this device. Connect to refresh them.', dismissible: false }),
            stockSection(),
            debtSection(),
            reconciliationSection(),
        ].filter(Boolean));
    },
};
