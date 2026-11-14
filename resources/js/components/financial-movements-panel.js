import { apiRequest } from '../api/http-client.js';
import { action, field, submit, money, message, has, pager } from './commercial-ui.js';
import { facts, selectField, fieldSummary, useTradingConfirm } from './trading-ui.js';
import { UiAlert, UiInformationPanel, UiLoading, UiRecordList } from './ui/index.js';
import { useNavigationGuard } from '../shared/workspace-navigation.js';
import { FinancialMovementCorrectionPanel } from './financial-movement-correction-panel.js';
const { h, ref, reactive, computed, watch, onBeforeUnmount, nextTick } = window.Vue;
const base = '/api/v1/shop/financial-movements';
const actions = {
    expense: { path: 'expenses', title: 'Record expense', amount: 'Amount spent', explanation: 'What was the expense for?', help: 'For example: Transport for stock delivery.', intro: 'Record money genuinely spent for the shop, such as transport or electricity. Check the payment method carefully.', method: 'Paid by' },
    withdrawal: { path: 'owner-withdrawals', title: 'Owner took money', amount: 'Amount taken out', explanation: 'Why was this money taken out?', help: 'For example: Money taken by the owner for personal use.', intro: 'Record money taken out by the shop owner. Choose Cash only when physical cash leaves the shop.', method: 'Taken from' },
    introduction: { path: 'owner-introductions', title: 'Add money to shop', amount: 'Amount added', explanation: 'Why was this money added?', help: 'For example: Owner added money for shop running costs.', intro: 'Record new money genuinely added by the owner. This is not a sale or a replacement for Starting cash. Cash still needs finalized Starting cash first.', method: 'Added as' },
};
const actionHash = action => `#financial-movements/${action}`;
const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i;
const time = (value, zone) => new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short', timeZone: zone })
    .format(new Date(value.replace(' ', 'T').replace(/(\.\d{3})\d+$/, '$1') + 'Z'));
const labels = { SHOP_EXPENSE: 'Shop expense', OWNER_WITHDRAWAL: 'Owner took money', OWNER_INTRODUCTION: 'Owner added money', FINANCIAL_CORRECTION: 'Corrected money record', REVERSAL: 'Money record undone' };

export const FinancialMovementsPanel = {
    props: { isOwner: { type: Boolean, default: false }, permissions: { type: Array, default: () => [] }, readOnly: { type: Boolean, default: true }, hash: { type: String, default: '#financial-movements' }, recoveryScope: { type: String, default: '' } },
    emits: ['navigate'],
    setup(props, { emit }) {
        const confirmation = useTradingConfirm(), heading = ref(null), data = ref(null), receipt = ref(null), review = ref(null);
        const busy = ref(false), saving = ref(false), online = ref(navigator.onLine), phase = ref('input'), dirty = ref(false);
        const error = ref(''), validation = ref({}), pending = ref(null), absent = ref(false), storageAvailable = ref(true);
        const form = reactive({ public_id: crypto.randomUUID(), channel: 'CASH', amount: '', description: '', external_reference: '', happened: '' });
        const filters = reactive({ channel: 'ALL', from_date: '', to_date: '' });
        const pagination = reactive({ page: 1, per_page: 20, total: 0, total_pages: 0 });
        let generation = 0, controller = null, alive = true, frozen = null;
        const view = computed(() => has(props.permissions, 'financial.view'));
        const record = computed(() => view.value && has(props.permissions, 'financial.record'));
        const route = computed(() => {
            if (props.hash === '#financial-movements') return { kind: 'history' };
            const entry = props.hash.split('/');
            if (entry.length === 2 && entry[0] === '#financial-movements' && Object.hasOwn(actions, entry[1])) return { kind: 'new', action: entry[1] };
            const parts = props.hash.split('/');
            if (parts.length === 3 && parts[0] === '#financial-movements' && ['reverse', 'correct', 'history'].includes(parts[1]) && uuid.test(parts[2])) {
                return { kind: 'correction', action: parts[1], source: parts[2] };
            }
            return parts.length === 3 && parts[0] === '#financial-movements' && parts[1] === 'record' && uuid.test(parts[2])
                ? { kind: 'detail', id: parts[2] } : { kind: 'invalid' };
        });
        const currentAction = computed(() => route.value.action || 'expense');
        const config = computed(() => actions[currentAction.value]);
        const canRecord = computed(() => record.value && (currentAction.value === 'expense' || props.isOwner));
        const locked = computed(() => busy.value || saving.value || !online.value || props.readOnly || !canRecord.value || phase.value === 'unknown');
        const dirtyFilters = computed(() => data.value && Object.keys(filters).some(k => filters[k] !== (data.value.filters[k] || (k === 'channel' ? 'ALL' : ''))));
        const key = () => props.recoveryScope ? `dt:pending-expense:${location.host}:${props.recoveryScope}` : '';
        const go = hash => emit('navigate', hash);
        function marker(value) {
            try { if (!key()) throw Error('Missing recovery scope'); value ? sessionStorage.setItem(key(), JSON.stringify(value)) : sessionStorage.removeItem(key()); }
            catch { storageAvailable.value = false; }
        }
        function readMarker() {
            try {
                const m = JSON.parse(key() ? sessionStorage.getItem(key()) : 'null');
                if (!m || !uuid.test(m.movement || '')) return null;
                const actionName = m.action || 'expense';
                if (Object.hasOwn(actions, actionName)) return { movement: m.movement, action: actionName };
                if (['reverse', 'correct'].includes(actionName) && uuid.test(m.source || '')
                    && (actionName !== 'correct' || uuid.test(m.replacement || ''))) {
                    return { movement: m.movement, action: actionName, source: m.source, replacement: m.replacement || null };
                }
                return null;
            } catch { return null; }
        }
        useNavigationGuard(({ hash }) => {
            if (saving.value) return { hard: true, message: 'This money record is being saved. Wait for the result.' };
            if (pending.value) {
                const recoveryHash = ['reverse', 'correct'].includes(pending.value.action)
                    ? `#financial-movements/${pending.value.action}/${pending.value.source}`
                    : actionHash(pending.value.action);
                if (hash === recoveryHash && hash !== location.hash) return null;
                return { hard: true, message: 'Check the saved money record status or continue with the same reference before leaving.' };
            }
            return dirty.value || phase.value === 'review' ? { message: 'This money record has not been saved. Keep editing, or leave without saving.' } : null;
        });
        function change(field, value) { form[field] = value; dirty.value = true; review.value = null; }
        function copyError(failure) {
            validation.value = failure.details || {};
            error.value = ['FINANCIAL_SETUP_REQUIRED', 'FINANCIAL_EVIDENCE_INVALID'].includes(failure.code) ? failure.message
                : failure.code === 'OPENING_CASH_REQUIRED' ? 'Set up Starting cash before recording a Cash money movement. Ask your shop owner for help.'
                : failure.code === 'CASH_CONTROL_ACTIVE' ? 'Finish the current cash count before recording a Cash money movement.'
                    : failure.code === 'AUDIT_CONTEXT_INVALID' ? 'Check when this money movement happened and your device date and time, then review again.'
                        : failure.status === 403 ? 'Your current access does not allow this action. Ask your shop owner for help.' : message(failure);
        }
        async function initialize(page = 1) {
            const current = ++generation; controller?.abort(); controller = new AbortController();
            busy.value = false; error.value = ''; validation.value = {}; data.value = null; receipt.value = null; absent.value = false;
            if (!view.value) return;
            pending.value = readMarker();
            if (pending.value && ['reverse', 'correct'].includes(pending.value.action)) {
                const recoveryHash = `#financial-movements/${pending.value.action}/${pending.value.source}`;
                if (!(route.value.kind === 'correction' && route.value.action === pending.value.action && route.value.source === pending.value.source)) {
                    go(recoveryHash); return;
                }
                // The correction child owns its multi-reference recovery marker.
                pending.value = null;
            } else if (pending.value && !(route.value.kind === 'new' && currentAction.value === pending.value.action)
                && !(route.value.kind === 'detail' && route.value.id === pending.value.movement)) {
                go(actionHash(pending.value.action)); return;
            }
            if (route.value.kind === 'correction') return;
            if (route.value.kind === 'new') {
                if (pending.value) { form.public_id = pending.value.movement; phase.value = 'unknown'; }
                else { Object.assign(form, { public_id: crypto.randomUUID(), channel: 'CASH', amount: '', description: '', external_reference: '', happened: '' }); phase.value = 'input'; dirty.value = false; frozen = null; review.value = null; }
                await nextTick(); if (alive && current === generation) heading.value?.focus();
                return;
            }
            if (!online.value) { error.value = 'You are offline. Connect to load the latest money records.'; return; }
            if (route.value.kind === 'invalid') { error.value = 'This money record link is incomplete. Go back to Money movements.'; return; }
            busy.value = true;
            try {
                const params = new URLSearchParams({ page, per_page: 20, ...filters });
                const path = route.value.kind === 'detail' ? `${base}/${route.value.id}` : `${base}?${params}`;
                const result = await apiRequest(path, { signal: controller.signal });
                if (!alive || current !== generation) return;
                if (route.value.kind === 'detail') {
                    receipt.value = result.data; phase.value = 'saved'; dirty.value = false;
                    if (pending.value?.movement === result.data.public_id) { marker(null); pending.value = null; }
                } else { data.value = result.data; Object.assign(pagination, result.data.pagination); }
                await nextTick(); if (alive && current === generation) heading.value?.focus();
            } catch (failure) { if (alive && current === generation) copyError(failure); }
            finally { if (alive && current === generation) busy.value = false; }
        }
        function command() {
            let occurred = null;
            if (form.happened) {
                const date = new Date(form.happened);
                if (!Number.isFinite(date.getTime())) throw Error('Choose a valid date and time for the money record.');
                occurred = date.toISOString().replace('T', ' ').replace('Z', '000');
            }
            return { public_id: form.public_id, channel: form.channel, amount: form.amount, description: form.description,
                external_reference: form.external_reference || null, occurred_at: occurred };
        }
        async function prepare(event) {
            event?.preventDefault(); if (locked.value) return; const current = generation; busy.value = true; error.value = ''; validation.value = {};
            try {
                const result = await apiRequest(`${base}/${config.value.path}/preview`, { method: 'POST', body: JSON.stringify(command()) });
                if (!alive || current !== generation) return;
                if (result.data.already_saved) await showSaved(form.public_id);
                else { frozen = result.data.command; review.value = result.data; phase.value = 'review'; await nextTick(); heading.value?.focus(); }
            } catch (failure) { if (alive && current === generation) { copyError(failure); review.value = null; } }
            finally { if (alive && current === generation) busy.value = false; }
        }
        async function showSaved(id, result = null) {
            const current = generation;
            const value = result || (await apiRequest(`${base}/${id}`)).data;
            if (!alive || current !== generation) return;
            if (value.public_id !== id) throw Error('The saved record reference needs checking. Keep this reference and ask your shop owner for help.');
            receipt.value = value; phase.value = 'saved'; dirty.value = false; pending.value = null; marker(null);
            go(`#financial-movements/record/${id}`);
        }
        async function save() {
            if (locked.value || !review.value || phase.value !== 'review') return;
            const current = generation, reviewed = review.value;
            if (!(await confirmation.ask({ title: currentAction.value === 'expense' ? 'Save this expense?' : `Save: ${config.value.title.toLowerCase()}?`, message: 'Check the amount, payment method and explanation. This saves a permanent money record.', confirm: 'Confirm and save' }))) return;
            if (locked.value || current !== generation || review.value !== reviewed) return;
            saving.value = true; error.value = ''; absent.value = false;
            pending.value = { movement: frozen.public_id, action: currentAction.value }; marker(pending.value);
            try {
                const result = await apiRequest(`${base}/${config.value.path}`, { method: 'POST', body: JSON.stringify({ ...frozen, review_fingerprint: review.value.review_fingerprint }) });
                if (!alive || current !== generation) return;
                saving.value = false; await showSaved(frozen.public_id, result.data);
            } catch (failure) {
                if (!alive || current !== generation) return;
                if (!failure.status || failure.status >= 500 || [401, 403, 404].includes(failure.status) || failure.code === 'FINANCIAL_IDENTITY_CONFLICT') {
                    phase.value = 'unknown'; error.value = 'We could not confirm whether the money record was saved. Check saved status. Do not record the same movement with a new reference.';
                } else { pending.value = null; marker(null); phase.value = 'input'; review.value = null; copyError(failure); }
            } finally { saving.value = false; }
        }
        async function checkStatus() {
            if (!pending.value || busy.value || saving.value || !online.value || !view.value) return;
            const current = generation;
            busy.value = true; absent.value = false; error.value = '';
            try { await showSaved(pending.value.movement); }
            catch (failure) {
                if (!alive || current !== generation) return;
                absent.value = failure.status === 404;
                error.value = absent.value ? 'Not found at the time of this check. An earlier request may still finish. Continue only with this same reference and the same details.'
                    : 'Saved status is not available yet. Keep this reference and check again when access and connection are restored.';
            } finally { if (alive && current === generation) busy.value = false; }
        }
        function resumeSame() {
            if (absent.value && pending.value && canRecord.value && !props.readOnly) {
                form.public_id = pending.value.movement; phase.value = 'input'; review.value = null; dirty.value = true;
                error.value = 'Keep this reference and re-enter the same details and use the same action. Review checks whether the money record was already saved.';
            }
        }
        function connection() {
            online.value = navigator.onLine; generation++; controller?.abort(); busy.value = false;
            if (pending.value) { phase.value = 'unknown'; review.value = null; error.value = 'Your connection changed before the money record was confirmed. Check saved status using this same reference.'; }
            if (route.value.kind !== 'new') { data.value = null; receipt.value = null; error.value = 'Your connection changed. Refresh to see the latest money records.'; }
        }
        window.addEventListener('online', connection); window.addEventListener('offline', connection);
        watch([() => props.hash, () => props.recoveryScope, () => props.isOwner, view], () => initialize(), { immediate: true });
        onBeforeUnmount(() => { alive = false; generation++; controller?.abort(); window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        function effect(channel, amount, type = currentAction.value === 'introduction' ? 'OWNER_INTRODUCTION' : 'SHOP_EXPENSE', delta = null) {
            const incoming = delta ? !delta.startsWith('-') : type === 'OWNER_INTRODUCTION';
            return h(UiInformationPanel, { message: channel === 'CASH' ? `Cash physically in the shop goes ${incoming ? 'up' : 'down'} by ${money(amount)}.` : 'Transfer does not change the cash physically in the shop.' });
        }
        function detail(r) {
            return h('article', { class: 'commercial-card' }, [h('h3', labels[r.movement_type] || 'Money record'), h('p', 'Saved. This is a permanent money record.'),
                h('p', { class: 'trading-record-meta' }, `Reference: ${r.public_id}`), facts([['Amount', money(r.amount)], ['Money record', labels[r.movement_type] || 'Money record'], ['Payment method', r.channel === 'CASH' ? 'Cash' : 'Transfer'],
                    ['Explanation', r.description], ...(r.audit_reason !== r.description ? [['Why it was changed', r.audit_reason]] : []), ['Money direction', r.amount_delta], ['Recorded by', r.recorded_by], ['Date recorded', time(r.posted_at, r.shop_timezone)],
                    ['Date it happened', time(r.occurred_at, r.shop_timezone)], ...(r.external_reference ? [['Reference', r.external_reference]] : [])]),
                h('p', `Dates shown in shop time: ${r.shop_timezone}`), effect(r.channel, r.amount, r.movement_type, r.amount_delta),
                ...(['SHOP_EXPENSE', 'OWNER_WITHDRAWAL', 'OWNER_INTRODUCTION', 'FINANCIAL_CORRECTION'].includes(r.movement_type) && !r.reversed_by_public_id && !r.replacement_public_id
                    ? [has(props.permissions, 'financial.reverse') && action('Undo this money record', () => go(`#financial-movements/reverse/${r.public_id}`), props.readOnly || busy.value, 'btn-outline-primary', { icon: 'return' }),
                        has(props.permissions, 'financial.reverse') && has(props.permissions, 'financial.correct') && action('Correct this money record', () => go(`#financial-movements/correct/${r.public_id}`), props.readOnly || busy.value, 'btn-primary', { icon: 'edit' })].filter(Boolean) : []),
                ...((r.reversed_by_public_id || r.replacement_public_id || r.reversal_of_public_id || r.replacement_for_public_id)
                    ? [action('View linked correction history', () => go(`#financial-movements/history/${r.public_id}`), busy.value, 'btn-outline-primary', { icon: 'activity' })] : [])]);
        }
        return () => h('section', { class: 'workspace commercial-workspace trading-screen ui-scope', 'aria-busy': busy.value || saving.value }, [
            h('h2', { ref: heading, tabindex: '-1' }, route.value.kind === 'new' ? config.value.title : route.value.kind === 'detail' ? 'Saved money record' : 'Money movements'),
            route.value.kind !== 'history' && action('Back', () => confirmation.guard(() => go(route.value.kind === 'correction' ? `#financial-movements/record/${route.value.source}` : '#financial-movements')), busy.value || saving.value, 'btn-outline-primary', { icon: 'back' }),
            ...confirmation.render(),
            !view.value ? h(UiInformationPanel, { severity: 'warning', message: 'You are not allowed to view money movements. Ask your shop owner for help.' }) : [
                !online.value && h(UiInformationPanel, { severity: 'warning', message: 'You are offline. Connect before reviewing or saving a money record. Nothing is queued.' }),
                error.value && h(UiAlert, { severity: 'error', message: error.value, persistent: true }),
                fieldSummary(validation.value, { amount: 'expense-amount', description: 'expense-description', channel: 'expense-channel', external_reference: 'expense-reference', occurred_at: 'expense-happened', from_date: 'expense-from', to_date: 'expense-to' }),
                (busy.value || saving.value) && h(UiLoading, { label: saving.value ? 'Saving money record…' : 'Loading money records…' }),
                pending.value && [h('p', { class: 'trading-record-meta' }, `Money record reference: ${pending.value.movement}`),
                    action('Check saved status', checkStatus, busy.value || saving.value || !online.value, 'btn-primary', { icon: 'refresh' }),
                    absent.value && phase.value === 'unknown' && action('Continue with this same reference', resumeSame, busy.value || props.readOnly || !record.value)],
                !storageAvailable.value && h(UiInformationPanel, { severity: 'warning', message: 'This browser cannot keep the money record reference after reload. Copy the reference and keep this page open until the saved result is confirmed.' }),
                route.value.kind === 'new' && !canRecord.value && h(UiInformationPanel, { severity: 'warning', message: 'You can view money records but cannot use this money action. Only the shop owner can record owner money taken out or added. Ask your shop owner for help.' }),
                route.value.kind === 'new' && props.readOnly && h(UiInformationPanel, { severity: 'warning', message: 'Your current access is view only. You cannot save a money record.' }),
                route.value.kind === 'new' && phase.value === 'input' && canRecord.value && h('form', { class: 'commercial-form', onSubmit: prepare }, [
                    h('p', config.value.intro),
                    field('expense-amount', `${config.value.amount} (₦)`, form.amount, v => change('amount', v), { inputmode: 'decimal', required: true, disabled: locked.value, errors: validation.value.amount || [] }),
                    selectField('expense-channel', config.value.method, form.channel, v => change('channel', v), [['CASH', 'Cash'], ['TRANSFER', 'Transfer']], { disabled: locked.value }),
                    effect(form.channel, form.amount || '0.00'),
                    field('expense-description', config.value.explanation, form.description, v => change('description', v), { multiline: true, required: true, maxlength: 300, disabled: locked.value, errors: validation.value.description || [], help: config.value.help }),
                    field('expense-reference', 'Payment reference (optional)', form.external_reference, v => change('external_reference', v), { maxlength: 120, disabled: locked.value }),
                    field('expense-happened', 'Date and time it happened (optional)', form.happened, v => change('happened', v), { type: 'datetime-local', step: 1, disabled: locked.value, help: 'Uses this device’s time. Leave blank to use the time when the money record is saved.' }),
                    submit(currentAction.value === 'expense' ? 'Review expense' : 'Review money record', locked.value, { loading: busy.value, busyLabel: 'Checking money record…', icon: 'next' }),
                ]),
                route.value.kind === 'new' && phase.value === 'review' && review.value && h('section', { class: 'commercial-card' }, [
                    h('h3', 'Review before saving'), facts([[config.value.amount, money(review.value.command.amount)], [config.value.method, review.value.command.channel === 'CASH' ? 'Cash' : 'Transfer'],
                        ['Explanation', review.value.command.description], ['Payment reference', review.value.command.external_reference || 'Not provided'],
                        ['Date it happened', review.value.command.occurred_at ? time(review.value.command.occurred_at, Intl.DateTimeFormat().resolvedOptions().timeZone) : 'Time when saved']]),
                    effect(review.value.command.channel, review.value.command.amount),
                    action(currentAction.value === 'expense' ? 'Save expense' : 'Save money record', save, locked.value, 'btn-primary', { loading: saving.value, busyLabel: 'Saving money record…', icon: 'success' }),
                    action(currentAction.value === 'expense' ? 'Edit expense' : 'Edit money record', () => { phase.value = 'input'; review.value = null; }, saving.value || busy.value, 'btn-outline-primary', { icon: 'edit' }),
                ]),
                receipt.value && detail(receipt.value),
                route.value.kind === 'correction' && h(FinancialMovementCorrectionPanel, { key: `${route.value.action}:${route.value.source}`, mode: route.value.action,
                    sourcePublicId: route.value.source, permissions: props.permissions, readOnly: props.readOnly, recoveryScope: props.recoveryScope, onNavigate: go }),
                route.value.kind === 'history' && [
                    record.value && action('Record expense', () => go(actionHash('expense')), props.readOnly || busy.value || !online.value, 'btn-primary', { icon: 'cash' }),
                    props.isOwner && record.value && action('Owner took money', () => go(actionHash('withdrawal')), props.readOnly || busy.value || !online.value, 'btn-outline-primary', { icon: 'cash' }),
                    props.isOwner && record.value && action('Add money to shop', () => go(actionHash('introduction')), props.readOnly || busy.value || !online.value, 'btn-outline-primary', { icon: 'add' }),
                    h('form', { class: 'commercial-filters', onSubmit: e => { e.preventDefault(); if (!busy.value) initialize(1); } }, [
                        selectField('expense-filter-channel', 'Payment method', filters.channel, v => { filters.channel = v; }, [['ALL', 'All'], ['CASH', 'Cash'], ['TRANSFER', 'Transfer']], { disabled: busy.value }),
                        field('expense-from', 'Date recorded from (optional)', filters.from_date, v => { filters.from_date = v; }, { type: 'date', disabled: busy.value }),
                        field('expense-to', 'Date recorded to (optional)', filters.to_date, v => { filters.to_date = v; }, { type: 'date', disabled: busy.value }),
                        submit('Find records', busy.value || !online.value, { loading: busy.value, busyLabel: 'Finding records…', icon: 'search' }),
                    ]),
                    dirtyFilters.value && h('p', 'Choose Find records to apply the new filters.'),
                    action('Refresh money records', () => initialize(pagination.page), busy.value || !online.value, 'btn-outline-primary', { icon: 'refresh' }),
                    data.value && [h('p', `Date recorded follows shop time: ${data.value.shop.timezone}.`), h(UiRecordList, { records: data.value.items, emptyTitle: 'No money movements found', emptyMessage: 'Try another date or payment method. Saved expenses and owner money movements will appear here.' }, {
                        record: ({ record: r }) => h('article', { class: 'commercial-card' }, [h('h3', r.description), facts([['Amount', money(r.amount)], ['Money record', labels[r.movement_type] || 'Money record'], ['Payment method', r.channel === 'CASH' ? 'Cash' : 'Transfer'], ['Date recorded', time(r.posted_at, r.shop_timezone)]]), action('View money record', () => go(`#financial-movements/record/${r.public_id}`))]),
                    }), pager(pagination, busy.value || !online.value || dirtyFilters.value, initialize)],
                ],
                route.value.kind === 'detail' && action('Refresh money record', () => initialize(), busy.value || !online.value, 'btn-outline-primary', { icon: 'refresh' }),
            ],
        ].filter(Boolean));
    },
};
