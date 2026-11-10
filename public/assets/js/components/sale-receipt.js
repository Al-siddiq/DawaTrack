import { money, action, has } from './commercial-ui.js';
import { facts, note, stateLabel } from './trading-ui.js';
const { h, ref } = window.Vue;
export const SaleReceipt = {
    props: { sale: { type: Object, required: true }, permissions: { type: Array, default: () => [] } },
    setup(props) {
        const receipt = ref(null);
        function printReceipt() {
            if (!receipt.value) return;
            const copy = receipt.value.cloneNode(true);
            copy.id = 'commercial-print-receipt';
            // A customer copy never includes internal cost/profit or stock evidence, even for an Owner.
            copy.querySelectorAll('button,.receipt-private,.receipt-stock-evidence,.ui-alert').forEach(node => node.remove());
            copy.querySelectorAll('details').forEach(node => { node.open = true; });
            document.body.append(copy); document.body.classList.add('printing-commercial-receipt');
            try { window.print(); } finally { copy.remove(); document.body.classList.remove('printing-commercial-receipt'); }
        }
        return () => {
            const sale = props.sale;
            const cancellation = sale.operation_type === 'COMPENSATION';
            const returned = sale.kind === 'RETURN' && !cancellation;
            const profit = sale.profit_visible && has(props.permissions, 'sale.profit.view');
            return h('article', { ref: receipt, class: 'sale-receipt commercial-card' }, [
                h('header', [h('h2', cancellation ? 'Cancellation receipt' : returned ? 'Return receipt' : 'Sale receipt'), h('p', 'Saved. Keep this receipt for your records.'), h('p', { class: 'trading-record-meta' }, `Receipt number: ${sale.public_id}`)]),
                sale.reversal_public_id && note('This record was cancelled. The original receipt is kept.', 'warning'),
                sale.reason && h('p', `Reason: ${sale.reason}`),
                facts([['Customer', sale.customer_name || 'Walk-in customer'], ['Recorded by', sale.posted_by_name || 'Shop user'], ['Sale time (UTC)', sale.occurred_at], ['Saved at (UTC)', sale.posted_at]]),
                ...sale.items.map(item => h('section', { class: 'receipt-line', key: item.public_id }, [h('h3', item.product_name), h('span', `${item.quantity} ${item.unit_name} × ${money(item.unit_selling_price)}`), h('strong', money(item.line_sales_amount))])),
                h('h3', `Total: ${money(sale.total_amount)}`),
                h('section', [h('h3', returned ? 'Money paid back' : cancellation ? 'Money changes from this cancellation' : 'Payment'), !sale.payments.length && h('p', 'No money changed hands.'), ...sale.payments.map(payment => h('p', { key: payment.public_id }, `${stateLabel(payment.channel)}: ${money(payment.amount)} · ${stateLabel(payment.direction)}`))]),
                sale.received_amount_snapshot != null && facts([['Amount paid', money(sale.received_amount_snapshot)], ['Left to pay from this sale', money(sale.credit_created_amount_snapshot)]]),
                ...(sale.credit_adjustments || []).map(a => h('p', { key: a.public_id }, `${a.adjustment_type === 'REVERSAL' ? 'Amount owed put back' : a.adjustment_type === 'CORRECTION_DECREASE' ? 'Amount owed cancelled' : 'Amount owed reduced'}: ${money(String(a.amount_delta).replace(/^-/, ''))}`)),
                sale.receivable && h('section', [h('h3', 'Amount left to pay'), facts([['From this sale', money(sale.receivable.original_amount)], ['Agreed payment date', sale.receivable.due_date || 'Not set']]), h('p', { class: 'trading-record-meta' }, `Payment record: ${sale.receivable.public_id}`), h('p', 'This shows the amount first left unpaid on this sale. It is not an updated customer balance.')]),
                profit && h('details', { class: 'receipt-private' }, [h('summary', 'Shop only: cost and profit'), h('p', 'Hidden on the customer’s printed receipt.'), ...sale.items.map(item => h('section', { key: item.public_id }, [h('h3', item.product_name), facts([['Cost per unit when sold', money(item.unit_cost_snapshot)], ['Total cost', money(item.line_cogs_amount)], ['Profit before shop expenses', money(item.gross_profit)]]), item.cost_basis_state === 'UNESTABLISHED' && note('The cost had not been recorded when this was sold. Treat this profit as an estimate.', 'warning')])), facts([['Total cost when sold', money(sale.total_cogs_amount)], ['Change in profit before expenses', money(sale.gross_profit_effect ?? sale.gross_profit)]]), sale.profit_provisional && note('Some costs were not recorded at the time. This profit is an estimate.', 'warning')]),
                h('details', { class: 'receipt-stock-evidence' }, [h('summary', 'Shop only: stock changes'), ...sale.items.map(item => h('section', { class: 'commercial-card', key: item.public_id }, [h('h3', item.product_name), h('p', `${item.quantity_before} → ${item.quantity_after} ${item.unit_name}`), h('p', { class: 'trading-record-meta' }, `Stock record: ${item.movement_public_id || 'Not available'}`), h('p', `Saved: ${item.movement_posted_at} UTC`), String(item.quantity_after).startsWith('-') && note('The recorded stock is below zero. Check and correct the stock record.', 'warning')]))]),
                action('Print customer receipt', printReceipt, false, 'btn-outline-primary', { icon: 'print' }),
            ].filter(Boolean));
        };
    },
};
