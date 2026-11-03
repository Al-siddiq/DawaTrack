import { apiRequest } from '../api/http-client.js';
import { get, money, field, action, submit, feedback, message, pager } from './commercial-ui.js';
import { SaleReceipt } from './sale-receipt.js';
const { h, ref, reactive, onMounted, nextTick } = window.Vue;
export const SaleHistoryPanel = {
    props: { permissions: { type: Array, default: () => [] } },
    setup(props) {
        const rows = ref([]), selected = ref(null), pending = ref(false), error = ref(''), heading = ref(null);
        const filters = reactive({ search: '', from: '', to: '' });
        const pagination = reactive({ page: 1, total_pages: 0 });
        async function run(operation) { if (pending.value) return; pending.value = true; error.value = ''; try { await operation(); } catch (e) { error.value = message(e); } finally { pending.value = false; } }
        async function load(page = 1) { await run(async () => { const result = await apiRequest(`/api/v1/shop/sales?${new URLSearchParams({ ...filters, page, per_page: 20 })}`); rows.value = result.data; Object.assign(pagination, result.meta.pagination); }); }
        async function open(id) { await run(async () => { const value = await get(`/api/v1/shop/sales/${id}`); if (value.status !== 'POSTED') { error.value = 'This Sale is still a draft. Open it from Sale drafts.'; return; } selected.value = value; history.replaceState(null, '', `#sale/${value.public_id}`); await nextTick(); heading.value?.focus(); }); }
        function back() { selected.value = null; history.replaceState(null, '', '#sale-history'); load(pagination.page); }
        onMounted(() => { const match = location.hash.match(/^#sale\/([a-f0-9-]{36})$/); if (match) open(match[1]); else load(); });
        return () => h('section', { class: 'workspace commercial-workspace', 'aria-busy': pending.value }, [h('h1', { ref: heading, tabindex: '-1' }, 'Sale history'), ...feedback(error.value, ''), pending.value && h('p', { role: 'status' }, 'Loading Sales…'),
            selected.value ? h('section', [action('Back to history', back, pending.value), action('Reload receipt', () => open(selected.value.public_id), pending.value), h(SaleReceipt, { sale: selected.value, permissions: props.permissions })]) : h('section', [
                h('form', { onSubmit: event => { event.preventDefault(); load(); }, class: 'commercial-filters' }, [field('history-search', 'Customer name or Sale reference', filters.search, value => { filters.search = value; }, { type: 'search', maxlength: 160 }), field('history-from', 'Posted from (UTC date)', filters.from, value => { filters.from = value; }, { type: 'date' }), field('history-to', 'Posted to (UTC date)', filters.to, value => { filters.to = value; }, { type: 'date' }), submit('Find Sales', pending.value)]),
                !rows.value.length && !pending.value && h('p', 'No matching posted Sales.'),
                ...rows.value.map(sale => h('article', { key: sale.public_id, class: 'commercial-card' }, [h('h2', sale.customer_name ?? 'Walk-in Customer'), h('p', `Total ${money(sale.total_amount)} · Credit ${money(sale.credit_created_amount_snapshot)}`), h('p', `${sale.posted_at} UTC · ${sale.posted_by_name ?? 'Recorded user'}`), action('View receipt', () => open(sale.public_id), pending.value)])), pager(pagination, pending.value, load),
            ].filter(Boolean)),
        ].filter(Boolean));
    },
};
