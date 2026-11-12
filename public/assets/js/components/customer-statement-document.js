import { action, money, has } from './commercial-ui.js';
import { facts } from './trading-ui.js';
const { h } = window.Vue;
export const statementLabels = {
    CREDIT: 'Sale left to pay', RETURN: 'Goods returned', RETURN_UNDONE: 'Return undone', SALE_CANCELLED: 'Sale cancelled',
    CORRECTION_INCREASE: 'Bill increased', CORRECTION_DECREASE: 'Bill reduced', REVERSAL: 'Bill change undone',
    PAYMENT: 'Customer payment', PAYMENT_UNDONE: 'Payment undone',
};
const signed = value => `${value.startsWith('-') ? '−' : value === '0.00' ? '' : '+'}${money(value.replace(/^-/, ''))}`;
export const statementTime = (value, zone) => new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short', timeZone: zone })
    .format(new Date(value.replace(' ', 'T').replace(/(\.\d{3})\d+$/, '$1') + 'Z'));
export const statementRange = filters => `${filters.from_date || 'First record'} to ${filters.to_date || 'Latest record'}`;

/** Screen and customer print share the same authoritative values; no private notes/reasons. */
export const CustomerStatementDocument = {
    props: { data: { type: Object, required: true }, permissions: { type: Array, default: () => [] }, full: Boolean },
    emits: ['navigate'],
    setup(props, { emit }) {
        const go = hash => emit('navigate', hash);
        return () => {
            const d = props.data, customer = d.customer.public_id, p = d.pagination;
            return h('article', { class: 'sale-receipt commercial-card customer-statement' }, [
                h('h2', 'Customer statement'), h('p', { class: 'statement-shop' }, d.shop.name), h('h3', d.customer.display_name),
                h('p', `Dates: ${statementRange(d.filters)}`),
                h('p', `Prepared: ${statementTime(d.as_of, d.shop.timezone)} · Shop time: ${d.shop.timezone}`),
                h('p', props.full ? `All ${p.total} records in this date range` : `Records ${p.from_record}–${p.to_record} of ${p.total}. This copy contains this page only.`),
                facts([['Still to pay before these dates', money(d.summary.opening_amount)], ['Added to bills', money(d.summary.increase_amount)],
                    ['Paid or reduced', money(d.summary.decrease_amount)], ['Still to pay at the end', money(d.summary.closing_amount)]]),
                h('p', 'A plus sign adds to the amount still to pay. A minus sign reduces it. A return may also pay money back without changing the amount owed.'),
                !props.full && h('p', `Still to pay before this page: ${money(p.page_start_amount)}`),
                h('ol', { class: 'statement-records', start: props.full ? 1 : p.from_record || 1 }, d.items.map((e, index) => h('li', {
                    class: 'statement-row', key: `${e.source_type}:${e.public_id}`,
                }, [
                    h('div', { class: 'statement-event-heading' }, [h('h4', statementLabels[e.event_type] || 'Bill record'), h('strong', signed(e.amount_delta))]),
                    h('p', `Saved: ${statementTime(e.posted_at, d.shop.timezone)}`),
                    h('p', { class: 'statement-occurrence' }, `Happened: ${statementTime(e.occurred_at, d.shop.timezone)}`),
                    e.direction === 'NONE' && h('p', 'No change to the amount still to pay.'),
                    ...(e.money_payments || []).map((p, i) => h('p', { key: `money-${i}` }, `${p.channel === 'CASH' ? 'Cash' : 'Bank transfer'} ${p.direction === 'REFUND' ? 'paid back' : 'received back'}: ${money(p.amount)}. This money is separate from the bill change above.`)),
                    e.channel && h('p', e.channel === 'CASH' ? 'Cash' : 'Bank transfer'),
                    h('p', { class: 'trading-record-meta' }, `Reference: ${e.public_id}`),
                    h('p', { class: 'statement-running' }, `Still to pay after this record: ${money(e.running_amount)}`),
                    e.allocations?.length && h('details', { class: 'statement-allocation-details' }, [h('summary', `Bills covered: ${e.allocations.length}`),
                        h('p', 'These bill amounts explain the payment above. They do not change the total again.'),
                        ...e.allocations.map(a => h('p', { key: a.receivable_public_id }, `Bill ${a.receivable_public_id}: ${money(a.amount)}`))]),
                    h('div', { class: 'ui-actions statement-links' }, [
                        e.source_type === 'CREDIT' && action('View bill', () => go(`#customers/debt/${customer}/bill/${e.receivable_public_id}`)),
                        e.source_type === 'BILL_CHANGE' && action('View bill change', () => go(`#customers/debt/${customer}/adjustment/${e.public_id}`)),
                        e.source_type === 'PAYMENT' && action('View payment', () => go(`#customers/debt/${customer}/payment/${e.public_id}`)),
                        e.original_public_id && e.source_type === 'PAYMENT' && action('View original payment', () => go(`#customers/debt/${customer}/payment/${e.original_public_id}`)),
                        e.replacement_public_id && action('View replacement payment', () => go(`#customers/debt/${customer}/payment/${e.replacement_public_id}`)),
                        e.corrects_public_id && action('View corrected original', () => go(`#customers/debt/${customer}/payment/${e.corrects_public_id}`)),
                        e.reversed_by_public_id && action('View payment undo', () => go(`#customers/debt/${customer}/payment/${e.reversed_by_public_id}`)),
                        has(props.permissions, 'sale.view') && e.sale_public_id && action('View original sale', () => go(`#sale/${e.sale_public_id}`)),
                        has(props.permissions, 'sale.view') && e.source_type === 'SALE_CHANGE' && action('View return or cancellation', () => go(`#sale/${e.public_id}`)),
                    ].filter(Boolean)),
                ].filter(Boolean)))),
                !d.items.length && h('p', 'No records in these dates. The amount carried in may still be greater than zero.'),
                h('p', `Still to pay after ${props.full ? 'these dates' : 'this page'}: ${money(props.full ? d.summary.closing_amount : p.page_end_amount)}`),
                h('section', { class: 'receipt-private' }, [h('h3', 'Current customer total'), h('p', `${money(d.summary.current_amount)} still to pay across all dates.`),
                    h('p', 'This can differ from a past date range. It is hidden on the customer print copy.')]),
            ].filter(Boolean));
        };
    },
};
