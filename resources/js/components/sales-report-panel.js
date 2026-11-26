import { UiLoading, UiAlert, UiButton, UiEmptyState } from './ui/index.js';
import { apiRequest } from '../api/http-client.js';

const { h, ref, onMounted, onUnmounted } = window.Vue;

export const SalesReportPanel = {
    props: { permissions: { type: Array, default: () => [] }, initialFrom: { type: String, default: '' }, initialTo: { type: String, default: '' } },
    emits: ['back'],
    setup(props, { emit }) {
        const report = ref(null);
        const loading = ref(false);
        const error = ref('');
        const from = ref(props.initialFrom);
        const to = ref(props.initialTo);
        const groupBy = ref('DAY');
        let alive = true;
        let requestNumber = 0;

        async function load(useFilters = false) {
            if (loading.value) return;
            const currentRequest = ++requestNumber;
            loading.value = true;
            error.value = '';
            try {
                const params = new URLSearchParams();
                if (useFilters) {
                    if (from.value) params.set('from', from.value);
                    if (to.value) params.set('to', to.value);
                }
                params.set('group_by', groupBy.value);
                const query = params.toString();
                const result = await apiRequest('/api/v1/shop/reports/sales' + (query ? '?' + query : ''));
                if (!alive || currentRequest !== requestNumber) return;
                report.value = result.data;
                from.value = result.data.period.from;
                to.value = result.data.period.to;
                groupBy.value = result.data.period.group_by;
            } catch (failure) {
                if (alive && currentRequest === requestNumber) error.value = failure.message || 'The sales report could not be loaded.';
            } finally {
                if (alive && currentRequest === requestNumber) loading.value = false;
            }
        }

        function money(value) {
            const amount = String(value ?? '0.00');
            const negative = amount.startsWith('-');
            const unsigned = negative ? amount.slice(1) : amount;
            const [whole = '0', fraction = '00'] = unsigned.split('.');
            const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            const currency = report.value?.currency_code === 'NGN' ? '₦' : (report.value?.currency_code || 'NGN') + ' ';
            return (negative ? '-' : '') + currency + grouped + '.' + fraction.padEnd(2, '0').slice(0, 2);
        }

        function dateLabel(value) {
            if (!value) return '';
            const date = new Date(value.length === 7 ? value + '-01T00:00:00Z' : value + 'T00:00:00Z');
            return new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeZone: 'UTC' }).format(date);
        }

        function refreshedLabel(value, timezone) {
            if (!value) return '';
            const date = new Date(value.replace(' ', 'T') + 'Z');
            return new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone }).format(date);
        }

        function metric(label, value, explanation = '') {
            return h('article', { class: 'sales-report-metric' }, [
                h('span', { class: 'sales-report-metric-label' }, label),
                h('strong', { class: 'sales-report-metric-value' }, money(value)),
                explanation && h('p', { class: 'sales-report-help' }, explanation),
            ]);
        }

        function amountLine(label, value) {
            return h('div', { class: 'sales-report-line' }, [h('span', label), h('strong', money(value))]);
        }

        function periodCard(row) {
            const details = [
                amountLine('Cash received', row.cash_received),
                amountLine('Cash refunded', row.cash_refunded),
                amountLine('Transfer received', row.transfer_received),
                amountLine('Transfer refunded', row.transfer_refunded),
                amountLine('Credit added to customer bills', row.credit_created),
                amountLine('Credit added back to customer bills', row.credit_adjustments_increase),
                amountLine('Credit removed from customer bills', row.credit_adjustments_decrease),
            ];
            if (report.value?.profit_visible) details.push(amountLine('Money left after product costs', row.gross_profit));
            return h('article', { class: 'sales-report-period-card', key: row.period_start }, [
                h('header', { class: 'sales-report-period-head' }, [
                    h('h3', dateLabel(row.period_start)),
                    h('strong', money(row.net_sales)),
                ]),
                h('div', { class: 'sales-report-period-summary' }, [
                    amountLine('Sales added', row.sales_added),
                    amountLine('Returns and cancellations', row.returns_and_cancellations),
                    amountLine('Sales after returns', row.net_sales),
                ]),
                h('details', { class: 'sales-report-details' }, [
                    h('summary', 'See how customers paid'),
                    ...details,
                    h('p', { class: 'sales-report-help' }, 'Credit means the part of a sale added to a customer bill. It is not money received today.'),
                ]),
                !row.payment_mix_matches_sales && h(UiAlert, {
                    severity: 'warning',
                    message: 'The payment records do not add up to the sales total for this period. Ask the shop owner to review the linked records.',
                    dismissible: false,
                }),
            ]);
        }

        onMounted(() => load(Boolean(from.value && to.value)));
        onUnmounted(() => { alive = false; requestNumber++; });

        return () => h('section', { class: 'sales-report-workspace', 'aria-busy': loading.value }, [
            h('div', { class: 'workspace-heading' }, [
                h('div', [
                    h('h2', { tabindex: '-1' }, 'Sales summary'),
                    h('p', 'See how much the shop sold and how customers paid. Only saved sales and returns are included.'),
                ]),
                h('div', { class: 'sales-report-header-actions' }, [
                    h(UiButton, { variant: 'secondary', onClick: () => emit('back') }, () => props.permissions.includes('sale.view') ? 'View saved sales' : 'Back to Home'),
                ]),
            ]),
            h('form', {
                class: 'sales-report-filters',
                onSubmit: event => { event.preventDefault(); load(true); },
            }, [
                h('label', { for: 'sales-report-from' }, ['From', h('input', { id: 'sales-report-from', class: 'form-control', type: 'date', value: from.value, max: to.value || undefined, onInput: event => { from.value = event.target.value; } })]),
                h('label', { for: 'sales-report-to' }, ['To', h('input', { id: 'sales-report-to', class: 'form-control', type: 'date', value: to.value, min: from.value || undefined, onInput: event => { to.value = event.target.value; } })]),
                h('label', { for: 'sales-report-group' }, ['Show results', h('select', { id: 'sales-report-group', class: 'form-select', value: groupBy.value, onChange: event => { groupBy.value = event.target.value; } }, [
                    h('option', { value: 'DAY' }, 'Each day'),
                    h('option', { value: 'MONTH' }, 'Each month'),
                ])]),
                h(UiButton, { type: 'submit', icon: 'search', loading: loading.value, busyLabel: 'Updating report…' }, () => 'Update report'),
            ]),
            loading.value && h(UiLoading, { label: 'Loading saved sales…' }),
            error.value && h(UiAlert, { severity: 'error', message: error.value, dismissible: false }),
            error.value && h(UiButton, { variant: 'secondary', icon: 'refresh', onClick: () => load(Boolean(from.value && to.value)) }, () => 'Try again'),
            report.value && !error.value && h('div', { class: 'sales-report-content' }, [
                h('p', { class: 'sales-report-period-note' }, [
                    'Showing ', dateLabel(report.value.period.from), ' to ', dateLabel(report.value.period.to),
                    ' · Shop time: ', report.value.period.timezone_name,
                ]),
                h('div', { class: 'sales-report-metrics' }, [
                    metric('Sales before returns', report.value.summary.sales_added),
                    metric('Returns and cancelled sales', report.value.summary.returns_and_cancellations),
                    metric('Sales after returns', report.value.summary.net_sales, 'This is the amount left after returns and cancellations.'),
                ]),
                h('section', { class: 'sales-report-section' }, [
                    h('h3', 'How customers paid'),
                    h('div', { class: 'sales-report-payment-grid' }, [
                        metric('Cash received', report.value.summary.cash_received),
                        metric('Cash refunded', report.value.summary.cash_refunded),
                        metric('Transfer received', report.value.summary.transfer_received),
                        metric('Transfer refunded', report.value.summary.transfer_refunded),
                        metric('Credit added to customer bills', report.value.summary.credit_created, 'This is the part customers still needed to pay when the sale was saved.'),
                        metric('Credit added back to customer bills', report.value.summary.credit_adjustments_increase),
                        metric('Credit removed from customer bills', report.value.summary.credit_adjustments_decrease, 'This includes bill changes linked to returns and cancellations.'),
                    ]),
                ]),
                report.value.profit_visible
                    ? h('section', { class: 'sales-report-profit' }, [
                        h('h3', 'Money left after product costs'),
                        h('strong', { class: 'sales-report-profit-value' }, money(report.value.summary.gross_profit)),
                        h('p', 'This is also called gross profit before shop expenses.'),
                        h('p', report.value.profit_explanation),
                        h('p', { class: 'sales-report-help' }, report.value.profit_cost_note),
                    ])
                    : h('section', { class: 'sales-report-private-note' }, [
                        h('h3', 'Product cost and profit details'),
                        h('p', 'These details are not available with your current access. Ask the shop owner if you need them.'),
                    ]),
                !report.value.summary.payment_mix_matches_sales && h(UiAlert, {
                    severity: 'warning',
                    message: 'The sales total and the money records do not match for this date range. The figures are shown for checking, not as a confirmed payment breakdown. Ask the shop owner to review the linked records.',
                    dismissible: false,
                }),
                h('section', { class: 'sales-report-section' }, [
                    h('h3', groupBy.value === 'MONTH' ? 'Results for each month' : 'Results for each day'),
                    h('p', 'Open a period to see the payment details.'),
                    report.value.series.length
                        ? h('div', { class: 'sales-report-period-list' }, report.value.series.map(periodCard))
                        : h(UiEmptyState, { title: 'No saved sales in these dates', message: 'Choose another date range to see sales.', icon: 'sales' }),
                ]),
                h('p', { class: 'sales-report-refreshed' }, 'Last updated: ' + refreshedLabel(report.value.refreshed_at, report.value.period.timezone_name) + ' (' + report.value.period.timezone_name + ').'),
                h(UiButton, { variant: 'secondary', icon: 'refresh', loading: loading.value, busyLabel: 'Refreshing…', onClick: () => load(true) }, () => 'Refresh report'),
            ]),
        ]);
    },
};
