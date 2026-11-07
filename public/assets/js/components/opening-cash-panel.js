import { apiRequest } from '../api/http-client.js';
const { h, ref, onMounted, onUnmounted, computed, nextTick } = window.Vue;
const root = '/api/v1/shop/opening-cash';
const money = value => `₦${value ?? '—'}`;
const canonicalCash = value => { const text = String(value).trim(); if (!/^(?:0|[1-9][0-9]{0,16})(?:\.[0-9]{1,2})?$/.test(text)) return null; const [whole, fraction = ''] = text.split('.'); return `${whole}.${fraction.padEnd(2, '0')}`; };

export const OpeningCashPanel = {
    props: { readOnly: { type: Boolean, default: true } },
    setup(props) {
        const state = ref({ active: null, baseline: null, history: [] });
        const pending = ref(false), loading = ref(true), error = ref(''), notice = ref('');
        const amount = ref(''), reason = ref(''), online = ref(navigator.onLine);
        const attemptId = ref(crypto.randomUUID()), stageTitle = ref(null);
        const disabled = computed(() => pending.value || props.readOnly || !online.value);
        const connection = () => { online.value = navigator.onLine; };
        async function refresh() {
            const response = await apiRequest(root);
            state.value = response.data;
            if (state.value.active?.status === 'COUNTING') amount.value = state.value.active.actual_amount ?? '';
        }
        async function load() {
            loading.value = true; error.value = '';
            try { await refresh(); } catch (e) { error.value = e.message; }
            finally { loading.value = false; }
        }
        async function command(action, body = {}) {
            if (disabled.value) return;
            if (action === 'commit-count' && canonicalCash(amount.value) !== state.value.active?.actual_amount) { error.value = 'Save the edited Cash amount before confirming.'; return; }
            pending.value = true; error.value = ''; notice.value = '';
            const id = state.value.active?.public_id;
            const path = action === 'start' ? root : `${root}/${encodeURIComponent(id)}/${action}`;
            try {
                const response = await apiRequest(path, { method: 'POST', body: JSON.stringify(body) });
                state.value = response.data;
                notice.value = 'Opening Cash status saved.';
                if (action === 'abort') { reason.value = ''; amount.value = ''; attemptId.value = crypto.randomUUID(); }
                await nextTick(); stageTitle.value?.focus();
            } catch (e) {
                error.value = `${e.message} Check the current status before retrying.`;
                try { await refresh(); } catch (_) { /* Preserve error and offer an explicit refresh. */ }
            } finally { pending.value = false; }
        }
        onMounted(() => { window.addEventListener('online', connection); window.addEventListener('offline', connection); load(); });
        onUnmounted(() => { window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        const button = (label, action, style = 'btn-primary') => h('button', { type: 'button', class: `btn ${style}`, disabled: disabled.value, onClick: action }, label);
        const evidence = row => h('dl', { class: 'opening-cash-evidence' }, [
            ['Reference', row.public_id], ['Stage', row.status], ['Physical Cash', money(row.actual_amount)],
            ['Confirmed at (UTC)', row.counts_committed_at ?? 'Not confirmed'], ['Cash period / cutoff (UTC)', row.cash_period_starts_at ?? 'Not confirmed'],
            ['Generation', row.generation_uuid], ['Cutoff sequence', row.cutoff_change_sequence],
            ['Started by', row.started_by_name], ['Finalized by', row.finalized_by_name ?? 'Not finalized'],
            ['Finalized at (UTC)', row.finalized_at ?? 'Not finalized'],
        ].map(([label, value]) => h('div', [h('dt', label), h('dd', String(value))])));
        return () => {
            const active = state.value.active, baseline = state.value.baseline;
            const content = [h('h1', { ref: stageTitle, tabindex: '-1' }, 'Opening Cash'),
                h('p', 'Count the physical cash in your Shop. This first baseline must be finalized before Cash sales or refunds can be recorded.'),
                !online.value && h('p', { class: 'alert alert-warning', role: 'status' }, 'You are offline. Opening Cash is available online only. Nothing is queued.'),
                props.readOnly && h('p', { class: 'alert alert-warning' }, 'Your current access allows viewing only.'),
                error.value && h('p', { class: 'alert alert-danger', role: 'alert' }, error.value),
                notice.value && h('p', { class: 'alert alert-success', role: 'status' }, notice.value),
            ];
            if (loading.value) content.push(h('p', { role: 'status' }, 'Loading Opening Cash…'));
            else if (baseline) content.push(h('section', { class: 'welcome-panel' }, [h('h2', 'Finalized Cash baseline'), h('p', 'This record is protected and cannot be edited or replaced by another Opening Cash.'), evidence(baseline)]));
            else if (!active) content.push(h('section', [h('h2', 'No finalized baseline'), button('Start Opening Cash', () => command('start', { public_id: attemptId.value }))]));
            else {
                content.push(h('h2', `Current stage: ${active.status.replaceAll('_', ' ')}`));
                if (active.status === 'COUNTING') content.push(h('form', { onSubmit: e => { e.preventDefault(); command('count', { amount: amount.value }); }, class: 'opening-cash-form' }, [
                    h('label', { for: 'opening-amount' }, 'Physical Cash amount (₦)'),
                    h('input', { id: 'opening-amount', class: 'form-control', type: 'text', inputmode: 'decimal', autocomplete: 'off', required: true, maxlength: 20, value: amount.value, disabled: disabled.value, onInput: e => { amount.value = e.target.value; }, 'aria-describedby': 'opening-amount-help' }),
                    h('p', { id: 'opening-amount-help' }, 'Enter one amount, for example 12500.50. Zero is allowed. No denomination breakdown is needed.'),
                    h('button', { type: 'submit', class: 'btn btn-primary', disabled: disabled.value }, 'Save Cash amount'),
                    active.actual_amount !== null && h('p', `Saved amount: ${money(active.actual_amount)}. Save any changes before confirming.`),
                    active.actual_amount !== null && canonicalCash(amount.value) === active.actual_amount && button('Confirm saved amount', () => command('commit-count')),
                ]));
                if (active.status === 'COUNTS_COMMITTED') content.push(h('section', [h('p', `Confirmed physical Cash: ${money(active.actual_amount)}. Review this fixed baseline before finalizing.`), button('Review confirmed baseline', () => command('review'))]));
                if (active.status === 'REVIEW') content.push(h('section', [h('p', 'Finalizing permanently establishes this physical Cash baseline. It cannot be edited afterward.'), evidence(active), button('Finalize Opening Cash', () => command('finalize'))]));
                content.push(h('form', { class: 'sensitive-action', onSubmit: e => { e.preventDefault(); command('abort', { reason: reason.value }); } }, [
                    h('h3', 'Abort this attempt'), h('p', 'The attempt and its observations will be retained. You can start again if no baseline has been finalized.'),
                    h('label', { for: 'opening-abort-reason' }, 'Reason'), h('textarea', { id: 'opening-abort-reason', class: 'form-control', required: true, maxlength: 500, value: reason.value, disabled: disabled.value, onInput: e => { reason.value = e.target.value; } }),
                    h('button', { type: 'submit', class: 'btn btn-outline-danger', disabled: disabled.value }, 'Abort attempt'),
                ]));
            }
            content.push(h('button', { class: 'btn btn-outline-secondary', type: 'button', disabled: pending.value || !online.value, onClick: load }, 'Refresh current status'));
            if (state.value.history.length) content.push(h('section', [h('h2', 'Opening Cash history'), h('p', 'Latest 50 attempts. Aborted attempts remain evidence.'), ...state.value.history.map(row => h('article', { class: 'opening-cash-history', key: row.public_id }, [
                h('h3', row.status.replaceAll('_', ' ')), h('p', row.public_id), h('p', `${money(row.actual_amount)} · Started ${row.started_at} UTC`), row.abort_reason && h('p', `Abort reason: ${row.abort_reason}`),
            ]))]));
            if (pending.value) content.push(h('p', { role: 'status', 'aria-live': 'polite' }, 'Saving… Please wait.'));
            return h('section', { class: 'workspace opening-cash-workspace', 'aria-busy': pending.value || loading.value }, content.filter(Boolean));
        };
    },
};
