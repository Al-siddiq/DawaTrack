import { inspectWorkspaceIssues } from '../shared/workspace-navigation.js';
import { UiField, UiInformationPanel, UiDialog, UiButton, UiErrorSummary, UiAlert } from './ui/index.js';
const { h, ref, nextTick, onBeforeUnmount } = window.Vue;

const labels = { ACTIVE: 'Active', INACTIVE: 'Inactive', ALL: 'All', DRAFT: 'Unfinished', POSTED: 'Saved', SALE: 'Sale', RETURN: 'Return', COMPENSATION: 'Cancellation', REVERSAL: 'Cancelled', ESTABLISHED: 'Cost recorded', UNESTABLISHED: 'Cost not yet recorded', CASH: 'Cash', TRANSFER: 'Bank transfer', IN: 'Money received', OUT: 'Money paid back', INCREASE: 'Increase', DECREASE: 'Decrease', NONE: 'No change', OPENING: 'Starting stock', RECEIPT: 'Stock delivery' };
export const stateLabel = value => labels[value] || String(value ?? '').replace(/_/g, ' ').toLowerCase();
export const note = (message, severity = 'info', title = '') => h(UiInformationPanel, { message, severity, title });
export const facts = pairs => h('dl', { class: 'trading-facts' }, pairs.map(([label, value]) => h('div', [h('dt', label), h('dd', value ?? 'Not provided')])));
export function selectField(id, label, value, update, choices, options = {}) {
    const { help = '', errors = [], ...attrs } = options;
    return h(UiField, { id, label, help, errors, required: !!attrs.required }, { default: ({ controlAttrs }) => h('select', { ...attrs, ...controlAttrs, class: 'form-select', value, onChange: event => update(event.target.value) }, choices.map(([value, text]) => h('option', { key: value, value }, text))) });
}
const summaryCache = new WeakMap();
export function fieldSummary(details, mapping = {}) {
    const key = details || {};
    const signature = JSON.stringify([key, mapping]);
    let cached = summaryCache.get(key);
    if (!cached || cached.signature !== signature) {
        cached = { signature, errors: Object.entries(key).flatMap(([name, values]) => (Array.isArray(values) ? values : [values]).map(message => ({ id: mapping[name], message: String(message) }))) };
        summaryCache.set(key, cached);
    }
    return h(UiErrorSummary, { errors: cached.errors });
}
/** Local decisions only: no new request identity, retry or financial operation lives here. */
export function useTradingConfirm() {
    const decision = ref(null), warning = ref('');
    let resolve = null;
    async function finish(accepted) { const callback = resolve; resolve = null; decision.value = null; await nextTick(); callback?.(accepted); }
    onBeforeUnmount(() => { resolve?.(false); resolve = null; });
    const ask = options => decision.value ? Promise.resolve(false) : new Promise(done => { resolve = done; decision.value = options; });
    return {
        async guard(work, hash = location.hash) {
            const issues = inspectWorkspaceIssues(hash);
            const hard = issues.find(issue => issue.hard);
            if (hard) { warning.value = hard.message; return false; }
            if (issues.length && !(await ask({ title: 'Leave these changes?', message: issues[0].message, confirm: 'Leave without saving' }))) return false;
            warning.value = ''; await work(); return true;
        },
        ask: ask,
        render: () => [warning.value && h(UiAlert, { severity: 'warning', message: warning.value, dismissible: false }), h(UiDialog, { open: !!decision.value, title: decision.value?.title || 'Please confirm', 'onUpdate:open': value => { if (!value) finish(false); }, onBlocked: () => finish(false) }, {
            default: () => h('p', decision.value?.message),
            footer: () => [h(UiButton, { variant: 'secondary', onClick: () => finish(false) }, () => 'Keep editing'), h(UiButton, { variant: decision.value?.danger ? 'danger' : 'primary', onClick: () => finish(true) }, () => decision.value?.confirm || 'Continue')],
        })],
    };
}
