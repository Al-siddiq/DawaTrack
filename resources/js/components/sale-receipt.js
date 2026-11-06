import { money, action, has } from './commercial-ui.js';
const { h, ref } = window.Vue;
export const SaleReceipt = {
    props: { sale: { type: Object, required: true }, permissions: { type: Array, default: () => [] } },
    setup(props) {
        const receipt = ref(null);
        function printReceipt() {
            if (!receipt.value) return;
            const copy = receipt.value.cloneNode(true);
            copy.id = 'commercial-print-receipt';
            copy.querySelectorAll('button').forEach(node => node.remove());
            copy.querySelectorAll('details').forEach(node => { node.open = true; });
            document.body.append(copy); document.body.classList.add('printing-commercial-receipt');
            try { window.print(); } finally { copy.remove(); document.body.classList.remove('printing-commercial-receipt'); }
        }
        return () => {
            const sale = props.sale;
            const correction = sale.operation_type === 'COMPENSATION';
            const isReturn = sale.kind === 'RETURN' && !correction;
            const profit = sale.profit_visible && has(props.permissions, 'sale.profit.view');
            return h('article', { ref: receipt, class: 'sale-receipt commercial-card' }, [
                h('header', [h('h2', correction ? 'Reversal receipt' : isReturn ? 'Return receipt' : 'Sale receipt'), h('p', { role: 'status', class: 'alert alert-success' }, 'Posted successfully. This receipt is permanent.'), h('p', { class: 'break-all' }, `Reference: ${sale.public_id}`)]),
                sale.reversal_public_id && h('p', { class: 'alert alert-warning' }, 'This operation has been reversed. Its original evidence is retained.'), sale.reason && h('p', `Reason: ${sale.reason}`),
                h('dl', { class: 'detail-grid' }, [h('div', [h('dt', 'Customer'), h('dd', sale.customer_name ?? 'Walk-in Customer')]), h('div', [h('dt', 'Posted by'), h('dd', sale.posted_by_name ?? 'Recorded user')]), h('div', [h('dt', 'Business time (UTC)'), h('dd', sale.occurred_at)]), h('div', [h('dt', 'Posted (UTC)'), h('dd', sale.posted_at)])]),
                ...sale.items.map(item => h('section', { class: 'commercial-card', key: item.public_id }, [h('h3', item.product_name), h('p', `${item.quantity} ${item.unit_name} × ${money(item.unit_selling_price)} = ${money(item.line_sales_amount)}`),
                    profit && h('p', `Historical Cost basis: ${money(item.unit_cost_snapshot)} per unit · COGS: ${money(item.line_cogs_amount)} · Original margin attributed (before expenses): ${money(item.gross_profit)}`),
                    profit && h('p', item.cost_basis_state === 'UNESTABLISHED' ? 'Cost basis unestablished: this profit figure is provisional.' : `Historical cost state: ${item.cost_basis_state}`),
                    h('a', { href: `#stock-proof-${item.movement_public_id}`, onClick: event => { event.preventDefault(); const evidence = document.getElementById(`stock-proof-${item.movement_public_id}`); if (evidence) { evidence.closest('details').open = true; evidence.focus(); } } }, 'Stock evidence'),
                ].filter(Boolean))),
                h('h3', `Total: ${money(sale.total_amount)}`), h('h3', isReturn ? 'Refunds paid' : correction ? 'Compensating money movements' : 'Payments received'),
                !sale.payments.length && h('p', 'No money movement for this operation.'),
                ...sale.payments.map(payment => h('p', { key: payment.public_id }, `${payment.channel}: ${money(payment.amount)} · ${payment.direction}`)),
                sale.received_amount_snapshot != null && h('p', `Paid: ${money(sale.received_amount_snapshot)} · Credit created: ${money(sale.credit_created_amount_snapshot)}`), ...(sale.credit_adjustments ?? []).map(a => h('p', { key: a.public_id }, `${a.adjustment_type === 'REVERSAL' ? 'Existing debt restored' : a.adjustment_type === 'CORRECTION_DECREASE' ? 'Original credit cancelled' : 'Existing debt reduced'}: ${money(String(a.amount_delta).replace(/^-/, ''))}`)),
                sale.receivable && h('section', { class: 'commercial-card' }, [h('h3', 'Sale credit'), h('p', `Original credit: ${money(sale.receivable.original_amount)}`), h('p', `Due date: ${sale.receivable.due_date ?? 'Not specified'}`), h('p', { class: 'break-all' }, `Credit reference: ${sale.receivable.public_id}`), h('p', 'This is the original Sale obligation. Collections and Customer statements belong to the next phase.')]),
                profit && h('section', { class: 'commercial-card' }, [h('h3', 'Historical margin'), h('p', `COGS: ${money(sale.total_cogs_amount)}`), h('p', `Gross profit effect (before expenses): ${money(sale.gross_profit_effect ?? sale.gross_profit)}`), sale.profit_provisional && h('p', { class: 'alert alert-warning' }, 'Provisional profit: one or more cost bases were unestablished when this Sale posted. It is not verified profit.')].filter(Boolean)),
                h('details', { open: false }, [h('summary', 'Linked stock evidence'), ...sale.items.map(item => h('section', { id: `stock-proof-${item.movement_public_id}`, tabindex: '-1', class: 'commercial-card' }, [h('h4', item.product_name), h('p', { class: 'break-all' }, `Movement: ${item.movement_public_id ?? 'Evidence unavailable'}`), h('p', `${item.quantity_before} → ${item.quantity_after} ${item.unit_name}`), String(item.quantity_after).startsWith('-') && h('p', { class: 'alert alert-warning' }, 'Stock left is below zero. Resolve the stock discrepancy through the accountable Inventory workflow.'), h('p', `Posted: ${item.movement_posted_at} UTC`)]))]),
                action('Print receipt', printReceipt, false, 'btn-outline-secondary'),
            ].filter(Boolean));
        };
    },
};
