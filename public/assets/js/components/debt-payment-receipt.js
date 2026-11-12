import { action, money, has } from './commercial-ui.js';
import { facts, note, stateLabel } from './trading-ui.js';
const { h, ref } = window.Vue;
export const paymentTime = value => new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value.replace(' ', 'T').replace(/(\.\d{3})\d+$/, '$1') + 'Z'));
export const DebtPaymentReceipt = {
    props: { payment: { type: Object, required: true }, permissions: { type: Array, default: () => [] } },
    emits: ['navigate'],
    setup(props, { emit }) {
        const element = ref(null);
        function print() {
            if (!element.value) return;
            const copy = element.value.cloneNode(true); copy.id = 'commercial-print-receipt';
            copy.querySelectorAll('button,.receipt-private,.ui-alert').forEach(node => node.remove());
            document.body.append(copy); document.body.classList.add('printing-commercial-receipt');
            try { window.print(); } finally { copy.remove(); document.body.classList.remove('printing-commercial-receipt'); }
        }
        return () => {
            const p = props.payment;
            return h('article', { ref: element, class: 'sale-receipt commercial-card debt-payment-receipt' }, [
                h('h2', p.reversal_of_public_id ? 'Customer payment undone' : 'Customer payment receipt'), h('p', p.shop_name), h('p', p.reversal_of_public_id ? 'Saved. The original payment was undone. Its receipt is kept.' : 'Saved. This payment reduced an existing bill; it is not another sale.'),
                h('p', { class: 'trading-record-meta' }, `Payment reference: ${p.public_id}`),
                p.reversed_by_public_id && note('This payment was undone. The original receipt is kept.', 'warning'),
                facts([['Customer', p.customer_name], [p.reversal_of_public_id ? 'Amount undone' : 'Amount received', money(p.amount)], ['Paid by', stateLabel(p.channel)],
                    ['Recorded by', p.recorded_by], ['Payment time (on this device)', paymentTime(p.occurred_at)], ['Saved (on this device)', paymentTime(p.posted_at)],
                    ...(p.external_reference ? [['Bank transfer reference', p.external_reference]] : [])]),
                h('h3', p.reversal_of_public_id ? 'Amounts put back on bills' : 'Bills covered by this payment'), ...p.allocations.map(a => h('section', { class: 'receipt-line', key: a.receivable_public_id }, [
                    h('p', { class: 'trading-record-meta' }, `Bill reference: ${a.receivable_public_id}`), h('strong', money(a.amount)),
                    has(props.permissions, 'sale.view') && action('View original sale', () => emit('navigate', `#sale/${a.sale_public_id}`)),
                ])),
                facts([['Customer total before this payment', money(p.customer_balance_before)], ['Customer total just after this payment', money(p.customer_balance_after)]]),
                h('section', { class: 'receipt-private' }, [p.correction_reason && h('p', `Reason for this change: ${p.correction_reason}`), h('h3', 'Current balance'), h('p', `${money(p.current_customer_balance)} still to pay.`), h('p', 'This live balance can change after more sales, payments or returns. It is hidden on the printed receipt.')]),
                p.reversal_of_public_id && action('View original payment', () => emit('navigate', `#customers/debt/${p.customer_public_id}/payment/${p.reversal_of_public_id}`)),
                p.reversed_by_public_id && action('View payment undo', () => emit('navigate', `#customers/debt/${p.customer_public_id}/payment/${p.reversed_by_public_id}`)),
                p.replacement_public_id && action('View replacement payment', () => emit('navigate', `#customers/debt/${p.customer_public_id}/payment/${p.replacement_public_id}`)),
                p.corrects_public_id && action('View corrected original', () => emit('navigate', `#customers/debt/${p.customer_public_id}/payment/${p.corrects_public_id}`)),
                action('Print customer receipt', print, false, 'btn-outline-primary', { icon: 'print' }),
            ].filter(Boolean));
        };
    },
};
