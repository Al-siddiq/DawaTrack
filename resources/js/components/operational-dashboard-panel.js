import { apiRequest } from '../api/http-client.js';
import { UiAlert, UiButton, UiLoading, UiEmptyState } from './ui/index.js';

const { h, ref, reactive, computed, onMounted, onUnmounted } = window.Vue;

function money(value, currency = 'NGN') {
    const amount = String(value ?? '0.00');
    const negative = amount.startsWith('-');
    const unsigned = negative ? amount.slice(1) : amount;
    const [whole = '0', fraction = '00'] = unsigned.split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const symbol = currency === 'NGN' ? '₦' : currency + ' ';
    return (negative ? '-' : '') + symbol + grouped + '.' + fraction.padEnd(2, '0').slice(0, 2);
}

function count(value) {
    return new Intl.NumberFormat('en-NG').format(Number(value ?? 0));
}

function dateLabel(value) {
    if (!value) return 'Not available';
    const date = new Date(value.length === 7 ? value + '-01T00:00:00Z' : value + 'T00:00:00Z');
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeZone: 'UTC' }).format(date);
}

function timeLabel(data) {
    if (!data) return 'Refresh time not available';
    const timezone = data.period?.timezone_name || data.shop?.timezone_name || 'Africa/Lagos';
    const raw = data.as_of || data.refreshed_at;
    if (!raw) return 'Refresh time not available';
    const date = new Date(String(raw).includes('T') ? raw : String(raw).replace(' ', 'T') + 'Z');
    if (Number.isNaN(date.getTime())) return 'Refresh time not available';
    try {
        return new Intl.DateTimeFormat('en-NG', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: timezone,
        }).format(date) + ' (' + timezone + ')';
    } catch {
        return String(raw) + ' (' + timezone + ')';
    }
}

function dateTimeLabel(value, timezone) {
    if (!value) return 'Not available';
    const raw = String(value);
    const date = new Date(raw.includes('T') ? raw : raw.replace(' ', 'T') + 'Z');
    if (Number.isNaN(date.getTime())) return raw;
    try { return new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone || 'Africa/Lagos' }).format(date); }
    catch { return raw; }
}

function reportUrl(path, params) {
    const query = params.toString();
    return path + (query ? '?' + query : '');
}

export const OperationalDashboardPanel = {
    props: {
        permissions: { type: Array, default: () => [] },
        capabilities: { type: Object, default: () => ({}) },
        access: { type: Object, default: () => ({ mode: 'FULL' }) },
    },
    emits: ['navigate'],
    setup(props, { emit }) {
        const sales = reactive({ data: null, loading: false, error: '', stale: false, queryKey: null });
        const stock = reactive({ data: null, loading: false, error: '', stale: false, queryKey: null });
        const debt = reactive({ data: null, loading: false, error: '', stale: false, queryKey: null });
        const reconciliation = reactive({ data: null, loading: false, error: '', stale: false, queryKey: null });
        const from = ref('');
        const to = ref('');
        const refreshing = ref(false);
        const offline = ref(!navigator.onLine);
        let alive = true;
        const controllers = Object.create(null);
        const canSales = computed(() => props.permissions.includes('report.view'));
        const canStock = computed(() => props.capabilities.view_stock_report === true);
        const canDebt = computed(() => props.capabilities.view_customer_debt_report === true);
        const canReconciliation = computed(() => props.capabilities.view_reconciliation_report === true);
        const blocked = computed(() => ['BLOCKED', 'CLOSED'].includes(props.access.mode));

        async function loadOne(name, state, path, params) {
            if (!alive || offline.value || blocked.value) return;
            if (controllers[name]) controllers[name].abort();
            const controller = new AbortController();
            controllers[name] = controller;
            const query = params.toString();
            if (state.queryKey !== query) {
                state.data = null;
                state.stale = false;
                state.queryKey = query;
            }
            state.loading = true;
            state.error = '';
            try {
                const response = await apiRequest(reportUrl(path, params), { signal: controller.signal });
                if (!alive || controllers[name] !== controller) return;
                state.data = response.data;
                state.stale = false;
                if (name === 'sales') {
                    from.value = from.value || response.data.period?.from || '';
                    to.value = to.value || response.data.period?.to || '';
                }
            } catch (failure) {
                if (!alive || controllers[name] !== controller || failure.name === 'AbortError') return;
                const status = Number(failure.status ?? 0);
                if ([401, 403, 409, 422, 503].includes(status)) {
                    state.data = null;
                    state.stale = false;
                } else {
                    state.stale = Boolean(state.data);
                }
                state.error = failure.message || 'This report could not be refreshed.';
            } finally {
                if (alive && controllers[name] === controller) {
                    state.loading = false;
                    delete controllers[name];
                }
            }
        }

        function salesParams() {
            const params = new URLSearchParams({ group_by: 'MONTH' });
            if (from.value) params.set('from', from.value);
            if (to.value) params.set('to', to.value);
            return params;
        }

        async function loadSales() {
            if (canSales.value) await loadOne('sales', sales, '/api/v1/shop/reports/sales', salesParams());
        }

        async function loadStock() {
            if (!canStock.value) return;
            await loadOne('stock', stock, '/api/v1/shop/reports/stock', new URLSearchParams({ state: 'ALL', page: '1', per_page: '1' }));
        }

        async function loadDebt() {
            if (!canDebt.value) return;
            await loadOne('debt', debt, '/api/v1/shop/reports/customer-debt', new URLSearchParams({ status: 'OWING', customer_status: 'ALL', page: '1', per_page: '1' }));
        }

        async function loadReconciliation() {
            if (!canReconciliation.value) return;
            await loadOne('reconciliation', reconciliation, '/api/v1/shop/reports/reconciliation', new URLSearchParams({ scope: 'ALL', page: '1', per_page: '1' }));
        }

        async function refreshAll() {
            if (refreshing.value || offline.value || blocked.value) return;
            refreshing.value = true;
            try {
                await Promise.all([
                    loadSales(),
                    loadStock(),
                    loadDebt(),
                    loadReconciliation(),
                ]);
            } finally {
                if (alive) refreshing.value = false;
            }
        }

        async function applySalesPeriod() {
            if (!canSales.value || offline.value || blocked.value) return;
            await loadSales();
        }

        function onOffline() {
            offline.value = true;
            for (const name of Object.keys(controllers)) {
                const controller = controllers[name];
                delete controllers[name];
                controller.abort();
            }
            for (const state of [sales, stock, debt, reconciliation]) {
                state.data = null;
                state.loading = false;
                state.stale = false;
                state.error = 'You are offline. Connect to see live shop reports.';
            }
            refreshing.value = false;
        }

        function onOnline() {
            offline.value = false;
            for (const state of [sales, stock, debt, reconciliation]) state.error = '';
            refreshAll();
        }

        function openSales() {
            const params = new URLSearchParams();
            if (from.value) params.set('from', from.value);
            if (to.value) params.set('to', to.value);
            const query = params.toString();
            emit('navigate', '#sales-report' + (query ? '?' + query : ''));
        }

        const openShopReports = () => emit('navigate', '#shop-reports');

        onMounted(() => {
            window.addEventListener('offline', onOffline);
            window.addEventListener('online', onOnline);
            if (!offline.value && !blocked.value) refreshAll();
        });
        onUnmounted(() => {
            alive = false;
            window.removeEventListener('offline', onOffline);
            window.removeEventListener('online', onOnline);
            for (const controller of Object.values(controllers)) controller.abort();
        });

        function metric(label, value, explanation = '') {
            return h('div', { class: 'dashboard-metric' }, [
                h('span', label),
                h('strong', value),
                explanation && h('small', explanation),
            ]);
        }

        function card(title, subtitle, state, body, actionLabel, action) {
            const status = state.loading && !state.data
                ? h(UiLoading, { label: 'Loading report…' })
                : !state.data && state.error
                    ? h(UiAlert, { severity: 'error', message: state.error, dismissible: false })
                    : !state.data && !state.loading
                        ? h(UiEmptyState, { title: 'No report available', message: 'Refresh to try again.' })
                        : null;
            return h('article', { class: 'dashboard-card' }, [
                h('div', { class: 'dashboard-card-heading' }, [
                    h('div', [h('h3', title), h('p', subtitle)]),
                    state.loading && state.data && h('span', { class: 'dashboard-refreshing', role: 'status' }, 'Updating…'),
                ]),
                state.error && state.stale && h(UiAlert, {
                    severity: 'warning',
                    message: state.error + ' The last successful figures are shown and may be out of date.',
                    dismissible: false,
                }),
                status || (state.data && body(state.data)),
                state.data && h('p', { class: 'dashboard-freshness' }, 'Last refreshed: ' + timeLabel(state.data)),
                h('div', { class: 'dashboard-card-actions' }, [
                    h(UiButton, { variant: 'secondary', disabled: state.loading, onClick: action }, () => actionLabel),
                ]),
            ]);
        }

        function salesCard(data) {
            const summary = data.summary || {};
            return h('div', { class: 'dashboard-card-content' }, [
                h('p', { class: 'dashboard-period' }, 'Sales period: ' + dateLabel(data.period.from) + ' to ' + dateLabel(data.period.to) + ' · ' + data.period.timezone_name),
                h('div', { class: 'dashboard-metrics' }, [
                    metric('Sales after returns', money(summary.net_sales, data.currency_code)),
                    metric('Sales before returns', money(summary.sales_added, data.currency_code)),
                    metric('Returns and cancelled sales', money(summary.returns_and_cancellations, data.currency_code)),
                    metric('Cash received', money(summary.cash_received, data.currency_code)),
                    metric('Transfer received', money(summary.transfer_received, data.currency_code)),
                    metric('Credit created', money(summary.credit_created, data.currency_code)),
                    data.profit_visible
                        ? metric('Gross profit before expenses', money(summary.gross_profit, data.currency_code))
                        : metric('Product cost and profit', 'Not available', 'Your current access does not include these figures.'),
                ]),
            ]);
        }

        function stockCard(data) {
            const summary = data.summary || {};
            return h('div', { class: 'dashboard-card-content' }, [
                h('p', { class: 'dashboard-period' }, 'Current Stock position · ' + (data.shop?.timezone_name || 'Shop time')),
                h('div', { class: 'dashboard-metrics' }, [
                    metric('Products with Stock records', count(summary.product_count)),
                    metric('Low-stock products', count(summary.low_stock_count)),
                    metric('Stock below zero', count(summary.negative_stock_count)),
                    metric('Confirmed Stock value', money(summary.stock_value_confirmed, data.shop?.currency_code), summary.valuation_complete ? 'All listed cost states are confirmed.' : 'Some Stock values are not included because their cost basis is incomplete or quantity is below zero.'),
                ]),
            ]);
        }

        function debtCard(data) {
            const summary = data.summary || {};
            return h('div', { class: 'dashboard-card-content' }, [
                h('p', { class: 'dashboard-period' }, 'Current customer balances · Shop date ' + data.shop_date + ' · ' + (data.shop?.timezone_name || 'Shop time')),
                h('div', { class: 'dashboard-metrics' }, [
                    metric('Customers who still owe', count(summary.customer_count)),
                    metric('Outstanding amount', money(summary.outstanding_amount, data.shop?.currency_code)),
                    metric('Overdue amount', money(summary.overdue_amount, data.shop?.currency_code)),
                    metric('Customers with overdue bills', count(summary.overdue_customer_count)),
                    metric('Partly paid customers', count(summary.partially_paid_customer_count)),
                ]),
            ]);
        }

        function reconciliationCard(data) {
            const summary = data.summary || {};
            const visibility = data.variance_visibility || {};
            return h('div', { class: 'dashboard-card-content' }, [
                h('p', { class: 'dashboard-period' }, 'Latest finished checks · ' + (data.shop?.timezone_name || 'Shop time')),
                h('div', { class: 'dashboard-metrics' }, [
                    metric('Finished checks', count(summary.finalized_count)),
                    metric('Cash checks', count(summary.cash_checks)),
                    metric('Stock checks', count(summary.stock_checks)),
                    metric('Cash and Stock checks', count(summary.full_checks)),
                    visibility.cash && metric('Cash checks with a difference', count(summary.cash_variance_checks)),
                    visibility.stock && metric('Products counted below expected Stock', count(summary.stock_shortage_items)),
                    visibility.stock && metric('Products counted above expected Stock', count(summary.stock_surplus_items)),
                ].filter(Boolean)),
                h('p', { class: 'dashboard-period' }, summary.latest_finalized_at ? 'Latest finished: ' + dateTimeLabel(summary.latest_finalized_at, data.shop?.timezone_name) : 'No finished checks recorded yet.'),
            ]);
        }

        return () => {
            if (!canSales.value && !canStock.value && !canDebt.value && !canReconciliation.value) return null;
            if (blocked.value) return h('section', { class: 'operational-dashboard', 'aria-labelledby': 'operational-dashboard-title' }, [
                h('div', [h('h2', { id: 'operational-dashboard-title' }, 'Shop overview'), h('p', 'A quick view of saved sales, current Stock, customer balances and finished shop checks.')]),
                h(UiAlert, { severity: 'warning', message: 'Shop reports are unavailable while Shop access is blocked or closed.', dismissible: false }),
            ]);
            return h('section', { class: 'operational-dashboard', 'aria-labelledby': 'operational-dashboard-title', 'aria-busy': refreshing.value }, [
                h('div', { class: 'dashboard-heading' }, [
                    h('div', [
                        h('h2', { id: 'operational-dashboard-title' }, 'Shop overview'),
                        h('p', 'A quick view of saved sales, current Stock, customer balances and finished shop checks.'),
                    ]),
                    h(UiButton, { variant: 'secondary', icon: 'refresh', loading: refreshing.value, busyLabel: 'Refreshing…', disabled: offline.value || blocked.value, onClick: refreshAll }, () => 'Refresh overview'),
                ]),
                props.access.mode === 'READ_ONLY' && h(UiAlert, { severity: 'info', message: 'Read-only access: you can view these reports, but cannot make shop changes here.', dismissible: false }),
                offline.value && h(UiAlert, { severity: 'warning', message: 'You are offline. Connect to see live shop reports. No report figures are saved on this device.', dismissible: false }),
                canSales.value && h('form', { class: 'dashboard-period-form', onSubmit: event => { event.preventDefault(); applySalesPeriod(); } }, [
                    h('div', [
                        h('h3', 'Sales period'),
                        h('p', 'Choose the dates for the Sales figures. Other cards show the current position or latest finished checks.'),
                    ]),
                    h('label', { for: 'dashboard-sales-from' }, ['From', h('input', { id: 'dashboard-sales-from', class: 'form-control', type: 'date', value: from.value, max: to.value || undefined, onInput: event => { from.value = event.target.value; } })]),
                    h('label', { for: 'dashboard-sales-to' }, ['To', h('input', { id: 'dashboard-sales-to', class: 'form-control', type: 'date', value: to.value, min: from.value || undefined, onInput: event => { to.value = event.target.value; } })]),
                    h(UiButton, { type: 'submit', icon: 'search', loading: sales.loading, busyLabel: 'Updating Sales…', disabled: offline.value || blocked.value }, () => 'Update Sales period'),
                ]),
                h('div', { class: 'dashboard-grid' }, [
                    canSales.value && card('Sales and payments', 'Saved Sales and returns for the selected period.', sales, salesCard, 'Open Sales report', openSales),
                    canStock.value && card('Current Stock', 'The latest saved Stock position, not a past-period total.', stock, stockCard, 'Open Stock report', openShopReports),
                    canDebt.value && card('Current customer balances', 'What customers still owe as of the Shop date.', debt, debtCard, 'Open Customer Debt report', openShopReports),
                    canReconciliation.value && card('Finished shop checks', 'Finalized Cash, Stock and combined checks. Past results stay unchanged.', reconciliation, reconciliationCard, 'Open Reconciliation report', openShopReports),
                ].filter(Boolean)),
            ]);
        };
    },
};
