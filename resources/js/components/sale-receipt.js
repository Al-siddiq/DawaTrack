import { money, action, has } from './commercial-ui.js';
const { h } = window.Vue;
export const SaleReceipt = {
    props: { sale: { type: Object, required: true }, permissions: { type: Array, default: () => [] } },
    setup(props) {
        return () => {
            const sale = props.sale;
            const profit = sale.profit_visible && has(props.permissions, 'sale.profit.view');
            return h('article', { class: 'sale-receipt commercial-card' }, [
                h('header', [h('h2', 'Sale receipt'), h('p', { role: 'status', class: 'alert alert-success' }, 'Posted successfully. This receipt is permanent.'), h('p', { class: 'break-all' }, `Reference: ${sale.public_id}`)]),
                h('dl', { class: 'detail-grid' }, [h('div', [h('dt', 'Customer'), h('dd', sale.customer_name ?? 'Walk-in Customer')]), h('div', [h('dt', 'Posted by'), h('dd', sale.posted_by_name ?? 'Recorded user')]), h('div', [h('dt', 'Business time (UTC)'), h('dd', sale.occurred_at)]), h('div', [h('dt', 'Posted (UTC)'), h('dd', sale.posted_at)])]),
                ...sale.items.map(item => h('section', { class: 'commercial-card', key: item.public_id }, [h('h3', item.product_name), h('p', `${item.quantity} ${item.unit_name} × ${money(item.unit_selling_price)} = ${money(item.line_sales_amount)}`),
                    profit && h('p', `Historical Cost basis: ${money(item.unit_cost_snapshot)} per unit · COGS: ${money(item.line_cogs_amount)} · Gross profit (before expenses): ${money(item.gross_profit)}`),
                    profit && h('p', item.cost_basis_state === 'UNESTABLISHED' ? 'Cost basis unestablished: this profit figure is provisional.' : `Historical cost state: ${item.cost_basis_state}`),
                    h('a', { href: `#stock-proof-${item.movement_public_id}`, onClick: event => { event.preventDefault(); const evidence = document.getElementById(`stock-proof-${item.movement_public_id}`); if (evidence) { evidence.closest('details').open = true; evidence.focus(); } } }, 'Stock evidence'),
                ].filter(Boolean))),
                h('h3', `Total: ${money(sale.total_amount)}`), h('h3', 'Payments received'),
                !sale.payments.length && h('p', 'No money received for this Sale.'),
                ...sale.payments.map(payment => h('p', { key: payment.public_id }, `${payment.channel}: ${money(payment.amount)} · ${payment.direction}`)),
                h('p', `Paid: ${money(sale.received_amount_snapshot)} · Credit created: ${money(sale.credit_created_amount_snapshot)}`),
                sale.receivable && h('section', { class: 'commercial-card' }, [h('h3', 'Sale credit'), h('p', `Original credit: ${money(sale.receivable.original_amount)}`), h('p', `Due date: ${sale.receivable.due_date ?? 'Not specified'}`), h('p', { class: 'break-all' }, `Credit reference: ${sale.receivable.public_id}`), h('p', 'This is the original Sale obligation. Collections and Customer statements belong to the next phase.')]),
                profit && h('section', { class: 'commercial-card' }, [h('h3', 'Historical margin'), h('p', `COGS: ${money(sale.total_cogs_amount)}`), h('p', `Gross profit (before expenses): ${money(sale.gross_profit)}`), sale.profit_provisional && h('p', { class: 'alert alert-warning' }, 'Provisional profit: one or more cost bases were unestablished when this Sale posted. It is not verified profit.')].filter(Boolean)),
                h('details', { open: false }, [h('summary', 'Linked stock evidence'), ...sale.items.map(item => h('section', { id: `stock-proof-${item.movement_public_id}`, tabindex: '-1', class: 'commercial-card' }, [h('h4', item.product_name), h('p', { class: 'break-all' }, `Movement: ${item.movement_public_id ?? 'Evidence unavailable'}`), h('p', `${item.quantity_before} → ${item.quantity_after} ${item.unit_name}`), String(item.quantity_after).startsWith('-') && h('p', { class: 'alert alert-warning' }, 'Stock left is below zero. Resolve the stock discrepancy through the accountable Inventory workflow.'), h('p', `Posted: ${item.movement_posted_at} UTC`)]))]),
                action('Print receipt', () => window.print(), false, 'btn-outline-secondary'),
            ].filter(Boolean));
        };
    },
};
