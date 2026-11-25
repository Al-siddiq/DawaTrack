import { UiLoading, UiAlert, UiInformationPanel } from './ui/index.js';
import { apiRequest } from '../api/http-client.js';
import { money, action, submit } from './commercial-ui.js';

const { h, ref, onMounted, onUnmounted, watch } = window.Vue;

function shopTime(value, timezone) {
    if (!value) return 'Not available';
    const text = String(value);
    const normalized = text.endsWith('Z') ? text : (text.includes('T') ? text + 'Z' : text.replace(' ', 'T') + 'Z');
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return text;
    return new Intl.DateTimeFormat('en-NG', {
        timeZone: timezone || 'Africa/Lagos',
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function reportCard(title, amount, explanation) {
    return h('article', { class: 'commercial-card' }, [
        h('h3', title),
        h('p', { class: 'report-amount' }, money(amount)),
        h('p', explanation),
    ]);
}

export const SalesReportsPanel = {
    props: { permissions: { type: Array, default: () => [] } },
    setup(props) {
        let alive = true;
        const period = ref('TODAY');
        const fromDate = ref('');
        const toDate = ref('');
        const report = ref(null);
        const pending = ref(false);
        const error = ref('');

        async function load() {
            if (pending.value) return;
            pending.value = true;
            error.value = '';
            report.value = null;
            const params = new URLSearchParams({ period: period.value });
            if (period.value === 'CUSTOM') {
                if (!fromDate.value || !toDate.value) {
                    error.value = 'Choose both a start date and an end date.';
                    pending.value = false;
                    return;
                }
                params.set('from_date', fromDate.value);
                params.set('to_date', toDate.value);
            }
            try {
                const response = await apiRequest(`/api/v1/shop/reports/sales?${params.toString()}`);
                if (!alive) return;
                report.value = response.data;
                fromDate.value = response.data.period.from_date;
                toDate.value = response.data.period.to_date;
            } catch (failure) {
                if (alive) error.value = failure.message || 'The report could not be loaded. Check your connection and try again.';
            } finally {
                if (alive) pending.value = false;
            }
        }

        watch(() => props.permissions.includes('sale.profit.view'), () => { if (alive && !pending.value) load(); });
        onMounted(load);
        onUnmounted(() => { alive = false; });

        return () => h('div', { class: 'trading-stack sales-report-panel', 'aria-busy': pending.value }, [
            h('section', { class: 'commercial-card' }, [
                h('h3', 'Choose the dates'),
                h('p', 'See what the shop sold and how customers paid. Choose today, this month or your own dates.'),
                h('form', { class: 'commercial-filters', onSubmit: event => { event.preventDefault(); load(); } }, [
                    h('label', { for: 'sales-report-period' }, 'Dates to show'),
                    h('select', {
                        id: 'sales-report-period',
                        class: 'form-select',
                        value: period.value,
                        disabled: pending.value,
                        onChange: event => { period.value = event.target.value; report.value = null; error.value = ''; },
                    }, [
                        h('option', { value: 'TODAY' }, 'Today'),
                        h('option', { value: 'THIS_MONTH' }, 'This month'),
                        h('option', { value: 'CUSTOM' }, 'Choose dates'),
                    ]),
                    period.value === 'CUSTOM' ? h('div', { class: 'trading-stack' }, [
                        h('label', { for: 'sales-report-from' }, 'Start date'),
                        h('input', { id: 'sales-report-from', class: 'form-control', type: 'date', value: fromDate.value, required: true, disabled: pending.value, onInput: event => { fromDate.value = event.target.value; report.value = null; } }),
                        h('label', { for: 'sales-report-to' }, 'End date'),
                        h('input', { id: 'sales-report-to', class: 'form-control', type: 'date', value: toDate.value, required: true, disabled: pending.value, onInput: event => { toDate.value = event.target.value; report.value = null; } }),
                    ]) : null,
                    submit('Show report', pending.value, { loading: pending.value, busyLabel: 'Loading report…' }),
                ]),
            ]),
            error ? h(UiAlert, { severity: 'error', message: error, dismissible: false }) : null,
            pending.value && h(UiLoading, { label: 'Getting the latest saved Sales…' }),
            report.value ? h('div', { class: 'trading-stack' }, [
                h('section', { class: 'commercial-card' }, [
                    h('h3', 'Your Sales at a glance'),
                    h('p', `Shop dates: ${report.value.period.from_date} to ${report.value.period.to_date}`),
                    h('p', { class: 'trading-record-meta' }, `Time shown for this Shop: ${report.value.period.timezone}`),
                    h('p', { class: 'trading-record-meta' }, `Last updated: ${shopTime(report.value.period.refreshed_at, report.value.period.timezone)}`),
                    h('p', 'These figures show how Sales were paid. They are not the money currently in the till or bank.'),
                ]),
                reportCard('Money from sales after returns', report.value.sales.amount, 'This is the value of goods sold, less goods returned or cancelled.'),
                reportCard('Cash after refunds', report.value.payments.cash_amount, 'Cash received, less Cash paid back to customers.'),
                reportCard('Bank transfers after refunds', report.value.payments.transfer_amount, 'Bank transfers received, less transfers paid back.'),
                reportCard('Customers allowed to pay later', report.value.payments.credit_amount, 'This is the part customers were allowed to pay later, less any amount removed after a return. Later repayments are not counted as new sales.'),
                props.permissions.includes('sale.profit.view') && report.value.gross_profit ? reportCard(
                    'Money left after paying for goods',
                    report.value.gross_profit.amount,
                    report.value.gross_profit.message,
                ) : h(UiInformationPanel, {
                    severity: 'info',
                    title: 'Profit figures are not shown',
                    message: 'You cannot see profit figures with your current access. Ask the Shop Owner if you need them.',
                }),
                h('section', { class: 'commercial-card' }, [
                    h('h3', 'Saved records in this date range'),
                    h('p', `Sales: ${report.value.sales.sale_count} · Returns: ${report.value.sales.return_count} · Cancellations: ${report.value.sales.cancellation_count}`),
                    h('p', 'Only saved records are included. Unfinished sales and returns are not counted. Saved cancellations are included.'),
                    props.permissions.includes('sale.view') ? action('Open sales and returns', () => { location.hash = '#sale-history'; }) : null,
                ]),
            ]) : null,
        ]);
    },
};
