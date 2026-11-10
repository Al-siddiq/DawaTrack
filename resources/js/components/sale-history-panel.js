import { UiLoading, UiRecordList, UiBadge } from './ui/index.js';
import { useTradingConfirm, selectField, stateLabel, facts } from './trading-ui.js';
import { useNavigationGuard, announceWorkspaceHash as replaceLocation } from '../shared/workspace-navigation.js';
import { ReturnEditor } from './return-editor.js';
import { CommercialActions } from './commercial-actions.js';
import { apiRequest } from '../api/http-client.js';
import { get, money, field, action, submit, feedback, message, pager } from './commercial-ui.js';
import { SaleReceipt } from './sale-receipt.js';
const { h, ref, reactive, onMounted, onUnmounted, nextTick } = window.Vue;
export const SaleHistoryPanel = {
    props: { permissions: { type: Array, default: () => [] }, readOnly: { type: Boolean, default: true } },
    setup(props) {
        const confirmation = useTradingConfirm();
        let alive = true;
        const announceWorkspaceHash = hash => { if (alive) replaceLocation(hash); };
        const rows = ref([]), selected = ref(null), pending = ref(false), error = ref(''), heading = ref(null), childBusy = ref(false);
        useNavigationGuard(({ hash }) => (pending.value || childBusy.value) && (hash.startsWith('#sale/') || hash === '#sale-history') ? { hard: true, message: 'Wait for this record to finish loading before opening another sale.' } : null);
        const filters = reactive({ search: '', from: '', to: '', kind: 'ALL' });
        const pagination = reactive({ page: 1, total_pages: 0 });
        async function run(operation) { if (pending.value) return; pending.value = true; error.value = ''; try { await operation(); } catch (e) { error.value = message(e); } finally { pending.value = false; } }
        async function load(page = 1) { await run(async () => { const result = await apiRequest(`/api/v1/shop/sales?${new URLSearchParams({ ...filters, page, per_page: 20 })}`); rows.value = result.data; Object.assign(pagination, result.meta.pagination); }); }
        async function open(id) { await run(async () => { const value = await get(`/api/v1/shop/sales/${id}`); if (!alive) return; if (value.status === 'DRAFT' && value.kind === 'SALE') { location.hash = `#sale-draft/${value.public_id}`; return; } selected.value = value; announceWorkspaceHash(`#sale/${value.public_id}`); await nextTick(); heading.value?.focus(); }); }
        function adopt(value, sourceId = null) { if (!alive) return; if (sourceId && selected.value?.public_id !== sourceId) return; childBusy.value = false; selected.value = value; error.value = ''; if (value) { announceWorkspaceHash(`#sale/${value.public_id}`); open(value.public_id); } else back(); }
        const hashChange = () => { const match = location.hash.match(/^#sale\/([a-f0-9-]{36})$/); if (match) open(match[1]); else if (location.hash === '#sale-history' && selected.value) back(); };
        function back() { selected.value = null; announceWorkspaceHash('#sale-history'); load(pagination.page); }
        onMounted(() => { const match = location.hash.match(/^#sale\/([a-f0-9-]{36})$/); if (match) open(match[1]); else load(); window.addEventListener('hashchange', hashChange); });
        onUnmounted(() => { alive = false; window.removeEventListener('hashchange', hashChange); });
        return () => h('section', { class: 'workspace commercial-workspace trading-screen ui-scope', 'aria-busy': pending.value }, [
            h('h2', { ref: heading, tabindex: '-1' }, selected.value ? (selected.value.status === 'DRAFT' ? 'Unfinished return' : 'Saved receipt') : 'Sales and returns'),
            ...feedback(error.value, ''), confirmation.render(),
            selected.value ? h('div', { class: 'trading-stack' }, [
                h('div', { class: 'ui-actions' }, [action('Back to sales', () => confirmation.guard(back, '#sale-history'), pending.value || childBusy.value), action('Reload saved record', () => confirmation.guard(() => open(selected.value.public_id)), pending.value || childBusy.value)]),
                pending.value && h(UiLoading, { label: 'Loading saved record…' }),
                selected.value.status === 'DRAFT'
                    ? h(ReturnEditor, { key: selected.value.public_id, record: selected.value, permissions: props.permissions, readOnly: props.readOnly || pending.value, onBusy: value => { childBusy.value = value; }, onOpen: ((id) => value => adopt(value, id))(selected.value.public_id) })
                    : h('div', { class: 'trading-stack' }, [h(SaleReceipt, { sale: selected.value, permissions: props.permissions }), h(CommercialActions, { key: selected.value.public_id, sale: selected.value, permissions: props.permissions, readOnly: props.readOnly || pending.value, onBusy: value => { childBusy.value = value; }, onOpen: ((id) => value => adopt(value, id))(selected.value.public_id) })]),
            ]) : h('div', { class: 'trading-stack' }, [
                h('p', 'Find a sale, print a receipt or open a return.'),
                h('form', { onSubmit: event => { event.preventDefault(); load(); }, class: 'commercial-filters' }, [
                    field('history-search', 'Customer or receipt number', filters.search, value => { filters.search = value; }, { type: 'search', maxlength: 160 }),
                    field('history-from', 'Saved from (UTC date)', filters.from, value => { filters.from = value; }, { type: 'date' }),
                    field('history-to', 'Saved to (UTC date)', filters.to, value => { filters.to = value; }, { type: 'date' }),
                    selectField('history-kind', 'Show', filters.kind, value => { filters.kind = value; }, [['ALL', 'All saved records'], ['SALE', 'Sales'], ['RETURN', 'Returns'], ['COMPENSATION', 'Cancellations'], ['DRAFT_RETURN', 'Unfinished returns']]),
                    submit('Find records', pending.value, { loading: pending.value, busyLabel: 'Finding records…', icon: 'search' }),
                ]),
                h(UiRecordList, { records: rows.value, busy: pending.value, error: '', loadingLabel: 'Finding sales and returns…', emptyTitle: error.value ? 'Records could not be loaded' : 'No matching records', emptyMessage: error.value ? 'Try finding records again.' : 'Try a different name, date or record type.' }, {
                    record: ({ record: sale }) => h('article', { class: 'commercial-card' }, [
                        h('div', { class: 'trading-task-header' }, [h('h2', sale.customer_name || 'Walk-in customer'), h(UiBadge, { label: sale.reversal_public_id ? 'Cancelled' : stateLabel(sale.status || 'POSTED'), severity: sale.status === 'DRAFT' ? 'warning' : 'neutral' })]),
                        facts([['Record', stateLabel(sale.operation_type || sale.kind || 'SALE')], ['Total', money(sale.total_amount)], ...(sale.credit_created_amount_snapshot != null ? [['Left unpaid when saved', money(sale.credit_created_amount_snapshot)]] : [])]),
                        h('p', { class: 'trading-record-meta' }, `${sale.posted_at || sale.updated_at} UTC · ${sale.posted_by_name || 'Shop user'}`),
                        action(sale.status === 'DRAFT' ? 'Continue return' : 'View receipt', () => open(sale.public_id), pending.value),
                    ]),
                }), pager(pagination, pending.value, load),
            ]),
        ]);
    },
};
