import { ReturnEditor } from './return-editor.js';
import { CommercialActions } from './commercial-actions.js';
import { apiRequest } from '../api/http-client.js';
import { get, money, field, action, submit, feedback, message, pager } from './commercial-ui.js';
import { SaleReceipt } from './sale-receipt.js';
const { h, ref, reactive, onMounted, onUnmounted, nextTick } = window.Vue;
export const SaleHistoryPanel = {
    props: { permissions: { type: Array, default: () => [] }, readOnly: { type: Boolean, default: true } },
    setup(props) {
        const rows = ref([]), selected = ref(null), pending = ref(false), error = ref(''), heading = ref(null), childBusy = ref(false);
        const filters = reactive({ search: '', from: '', to: '', kind: 'ALL' });
        const pagination = reactive({ page: 1, total_pages: 0 });
        async function run(operation) { if (pending.value) return; pending.value = true; error.value = ''; try { await operation(); } catch (e) { error.value = message(e); } finally { pending.value = false; } }
        async function load(page = 1) { await run(async () => { const result = await apiRequest(`/api/v1/shop/sales?${new URLSearchParams({ ...filters, page, per_page: 20 })}`); rows.value = result.data; Object.assign(pagination, result.meta.pagination); }); }
        async function open(id) { await run(async () => { const value = await get(`/api/v1/shop/sales/${id}`); if (value.status === 'DRAFT' && value.kind === 'SALE') { location.hash = `#sale-draft/${value.public_id}`; return; } selected.value = value; history.replaceState(null, '', `#sale/${value.public_id}`); await nextTick(); heading.value?.focus(); }); }
        function adopt(value, sourceId = null) { if (sourceId && selected.value?.public_id !== sourceId) return; childBusy.value = false; selected.value = value; error.value = ''; if (value) { history.replaceState(null, '', `#sale/${value.public_id}`); open(value.public_id); } else back(); }
        const hashChange = () => { const match = location.hash.match(/^#sale\/([a-f0-9-]{36})$/); if (match) open(match[1]); };
        function back() { selected.value = null; history.replaceState(null, '', '#sale-history'); load(pagination.page); }
        onMounted(() => { const match = location.hash.match(/^#sale\/([a-f0-9-]{36})$/); if (match) open(match[1]); else load(); window.addEventListener('hashchange', hashChange); });
        onUnmounted(() => window.removeEventListener('hashchange', hashChange));
        return () => h('section', { class: 'workspace commercial-workspace', 'aria-busy': pending.value }, [h('h1', { ref: heading, tabindex: '-1' }, 'Sale history'), ...feedback(error.value, ''), pending.value && h('p', { role: 'status' }, 'Loading Sales…'),
            selected.value ? h('section', [action('Back to history', back, pending.value || childBusy.value), action('Reload receipt', () => open(selected.value.public_id), pending.value || childBusy.value), selected.value.status === 'DRAFT' ? h(ReturnEditor, { key: selected.value.public_id, record: selected.value, permissions: props.permissions, readOnly: props.readOnly || pending.value, onBusy: value => { childBusy.value = value; }, onOpen: ((id) => value => adopt(value, id))(selected.value.public_id) }) : h('section', [h(SaleReceipt, { sale: selected.value, permissions: props.permissions }), h(CommercialActions, { key: selected.value.public_id, sale: selected.value, permissions: props.permissions, readOnly: props.readOnly || pending.value, onBusy: value => { childBusy.value = value; }, onOpen: ((id) => value => adopt(value, id))(selected.value.public_id) })])]) : h('section', [
                h('form', { onSubmit: event => { event.preventDefault(); load(); }, class: 'commercial-filters' }, [field('history-search', 'Customer name or Sale reference', filters.search, value => { filters.search = value; }, { type: 'search', maxlength: 160 }), field('history-from', 'Posted from (UTC date)', filters.from, value => { filters.from = value; }, { type: 'date' }), field('history-to', 'Posted to (UTC date)', filters.to, value => { filters.to = value; }, { type: 'date' }), h('label', { for: 'history-kind' }, 'Record type'), h('select', { id: 'history-kind', class: 'form-select', value: filters.kind, onChange: e => { filters.kind = e.target.value; } }, [['ALL', 'All posted records'], ['SALE', 'Sales'], ['RETURN', 'Returns'], ['COMPENSATION', 'Reversals'], ['DRAFT_RETURN', 'Saved Return drafts']].map(([value, label]) => h('option', { value }, label))), submit('Find Sales', pending.value)]),
                !rows.value.length && !pending.value && h('p', filters.kind === 'DRAFT_RETURN' ? 'No saved Return drafts.' : 'No matching posted records.'),
                ...rows.value.map(sale => h('article', { key: sale.public_id, class: 'commercial-card' }, [h('h2', sale.customer_name ?? 'Walk-in Customer'), h('p', `${sale.operation_type ?? sale.kind ?? 'SALE'} · ${sale.status ?? 'POSTED'} · Total ${money(sale.total_amount)}${sale.credit_created_amount_snapshot != null ? ` · Credit ${money(sale.credit_created_amount_snapshot)}` : ''}${sale.reversal_public_id ? ' · REVERSED' : ''}`), h('p', `${sale.posted_at ?? sale.updated_at} UTC · ${sale.posted_by_name ?? 'Recorded user'}`), action(sale.status === 'DRAFT' ? 'Open Return draft' : 'View receipt', () => open(sale.public_id), pending.value)])), pager(pagination, pending.value, load),
            ].filter(Boolean)),
        ].filter(Boolean));
    },
};
