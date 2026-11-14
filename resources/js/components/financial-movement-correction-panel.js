import { apiRequest } from '../api/http-client.js';
import { action, field, submit, money, message, has } from './commercial-ui.js';
import { facts, selectField, useTradingConfirm } from './trading-ui.js';
import { UiAlert, UiInformationPanel, UiLoading } from './ui/index.js';
import { useNavigationGuard } from '../shared/workspace-navigation.js';

const { h, ref, reactive, computed, watch, onBeforeUnmount, nextTick } = window.Vue;
const base = '/api/v1/shop/financial-movements';
const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i;
const labels = { SHOP_EXPENSE: 'Shop expense', OWNER_WITHDRAWAL: 'Owner took money', OWNER_INTRODUCTION: 'Owner added money', FINANCIAL_CORRECTION: 'Corrected money record', REVERSAL: 'Money record undone' };
const time = (value, zone) => new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short', timeZone: zone })
    .format(new Date(value.replace(' ', 'T').replace(/(\\.\\d{3})\\d+$/, '$1') + 'Z'));

export const FinancialMovementCorrectionPanel = {
    props: {
        mode: { type: String, required: true },
        sourcePublicId: { type: String, required: true },
        permissions: { type: Array, default: () => [] },
        readOnly: { type: Boolean, default: true },
        recoveryScope: { type: String, default: '' },
    },
    emits: ['navigate'],
    setup(props, { emit }) {
        const confirmation = useTradingConfirm();
        const heading = ref(null), source = ref(null), chain = ref(null), review = ref(null);
        const busy = ref(false), saving = ref(false), online = ref(navigator.onLine);
        const phase = ref('input'), error = ref(''), validation = ref({});
        const pending = ref(null), absent = ref(false), dirty = ref(false), storageAvailable = ref(true);
        const form = reactive({
            public_id: crypto.randomUUID(),
            replacement_public_id: crypto.randomUUID(),
            direction: 'MONEY_OUT',
            amount: '',
            channel: 'CASH',
            description: '',
            external_reference: '',
            happened: '',
            reason: '',
        });
        let generation = 0, controller = null, alive = true, frozen = null;
        const view = computed(() => has(props.permissions, 'financial.view'));
        const reversePermission = computed(() => has(props.permissions, 'financial.reverse'));
        const correctPermission = computed(() => reversePermission.value && has(props.permissions, 'financial.correct'));
        const canWrite = computed(() => props.mode === 'reverse' ? reversePermission.value : correctPermission.value);
        const sourceEligible = computed(() => source.value && source.value.movement_type !== 'REVERSAL' && !source.value.reversed_by_public_id && !source.value.replacement_public_id);
        const locked = computed(() => busy.value || saving.value || !online.value || props.readOnly || !canWrite.value || !sourceEligible.value || phase.value === 'unknown');
        const isHistory = computed(() => props.mode === 'history');
        const storageKey = () => props.recoveryScope ? `dt:pending-expense:${location.host}:${props.recoveryScope}` : '';
        const routeHash = (mode, id = props.sourcePublicId) => `#financial-movements/${mode}/${id}`;
        const marker = value => {
            try {
                const key = storageKey();
                if (!key) { storageAvailable.value = false; return false; }
                value ? sessionStorage.setItem(key, JSON.stringify(value)) : sessionStorage.removeItem(key);
                storageAvailable.value = true;
                return true;
            } catch { storageAvailable.value = false; return false; }
        };
        function readMarker() {
            try {
                const key = storageKey();
                if (!key) return null;
                const value = JSON.parse(sessionStorage.getItem(key) || 'null');
                if (!value || !uuid.test(value.movement || '')
                    || !['expense', 'withdrawal', 'introduction', 'reverse', 'correct'].includes(value.action)) return null;
                if (['reverse', 'correct'].includes(value.action) && !uuid.test(value.source || '')) return null;
                if (value.action === 'correct' && !uuid.test(value.replacement || '')) return null;
                return { movement: value.movement, source: value.source || null, action: value.action, replacement: value.replacement || null };
            } catch { return null; }
        }
        useNavigationGuard(({ hash }) => {
            if (saving.value) return { hard: true, message: 'This money record is being saved. Wait for the result.' };
            if (pending.value) {
                if (hash === routeHash(pending.value.action, pending.value.source) && hash !== location.hash) return null;
                return { hard: true, message: 'Check the saved status or continue with the same references before leaving.' };
            }
            return dirty.value || phase.value === 'review' ? { message: 'This change has not been saved. Keep reviewing it, or leave without saving.' } : null;
        });
        function change(key, value) {
            form[key] = value;
            dirty.value = true;
            review.value = null;
        }
        function command() {
            const result = {
                public_id: form.public_id,
                reason: form.reason,
            };
            if (props.mode === 'correct') {
                let occurred = null;
                if (form.happened) {
                    const date = new Date(form.happened);
                    if (!Number.isFinite(date.getTime())) throw Error('Choose a valid date and time for the corrected record.');
                    occurred = date.toISOString().replace('T', ' ').replace('Z', '000');
                }
                Object.assign(result, {
                    replacement_public_id: form.replacement_public_id,
                    direction: form.direction,
                    amount: form.amount,
                    channel: form.channel,
                    description: form.description,
                    external_reference: form.external_reference || null,
                    occurred_at: occurred,
                });
            }
            return result;
        }
        async function loadSource() {
            const result = await apiRequest(`${base}/${props.sourcePublicId}`);
            if (result.data.public_id !== props.sourcePublicId) throw Error('The original money record reference does not match. Keep the reference and ask the shop owner for help.');
            source.value = result.data;
        }
        async function loadChain() {
            const result = await apiRequest(`${base}/${props.sourcePublicId}/correction-state`);
            chain.value = result.data;
            phase.value = 'saved';
            return result.data;
        }
        async function initialize() {
            const current = ++generation;
            controller?.abort();
            controller = new AbortController();
            busy.value = false; error.value = ''; validation.value = {}; source.value = null; chain.value = null; review.value = null; absent.value = false;
            if (!view.value) return;
            if (!online.value) { error.value = 'You are offline. Connect to view the saved money record.'; return; }
            const saved = readMarker();
            if (saved) {
                if (['expense', 'withdrawal', 'introduction'].includes(saved.action)) {
                    emit('navigate', `#financial-movements/${saved.action}`);
                    return;
                }
                if (saved.source !== props.sourcePublicId || saved.action !== props.mode) {
                    emit('navigate', routeHash(saved.action, saved.source));
                    return;
                }
            }
            if (isHistory.value) {
                busy.value = true;
                try { await loadChain(); }
                catch (failure) { if (alive && current === generation) copyError(failure); }
                finally { if (alive && current === generation) busy.value = false; }
                await nextTick(); if (alive && current === generation) heading.value?.focus();
                return;
            }
            if (saved) {
                pending.value = saved;
                form.public_id = saved.movement;
                if (saved.action === 'correct' && saved.replacement) form.replacement_public_id = saved.replacement;
                phase.value = 'unknown';
            } else {
                pending.value = null;
                Object.assign(form, { public_id: crypto.randomUUID(), replacement_public_id: crypto.randomUUID(), direction: 'MONEY_OUT',
                    amount: '', channel: 'CASH', description: '', external_reference: '', happened: '', reason: '' });
                phase.value = 'input'; dirty.value = false; frozen = null;
            }
            busy.value = true;
            try { await loadSource(); }
            catch (failure) { if (alive && current === generation) copyError(failure); }
            finally { if (alive && current === generation) busy.value = false; }
            await nextTick(); if (alive && current === generation) heading.value?.focus();
        }
        function copyError(failure) {
            validation.value = failure.details || {};
            error.value = failure.status === 403 ? 'Your current access does not allow this action. Ask your shop owner for help.'
                : failure.code === 'CASH_CONTROL_ACTIVE' ? 'Finish the current cash count before undoing or correcting a Cash record.'
                : failure.code === 'OPENING_CASH_REQUIRED' ? 'Set up Starting cash before changing a record that affects physical Cash.'
                : failure.code === 'FINANCIAL_MOVEMENT_ALREADY_SUPERSEDED' ? 'This record has already been undone or replaced. Open its linked correction history.'
                : message(failure);
        }
        async function prepare(event) {
            event?.preventDefault();
            if (locked.value) return;
            const current = generation;
            busy.value = true; error.value = ''; validation.value = {};
            try {
                const body = command();
                const suffix = props.mode === 'reverse' ? 'reversal-preview' : 'correction-preview';
                const result = await apiRequest(`${base}/${props.sourcePublicId}/${suffix}`, { method: 'POST', body: JSON.stringify(body) });
                if (!alive || current !== generation) return;
                if (result.data.already_saved) {
                    await loadChain();
                    marker(null); pending.value = null; dirty.value = false;
                    emit('navigate', routeHash('history'));
                } else { frozen = body; review.value = result.data; phase.value = 'review'; dirty.value = true; await nextTick(); heading.value?.focus(); }
            } catch (failure) { if (alive && current === generation) copyError(failure); }
            finally { if (alive && current === generation) busy.value = false; }
        }
        async function save() {
            if (locked.value || !review.value || phase.value !== 'review') return;
            const current = generation, reviewed = review.value;
            const isCorrection = props.mode === 'correct';
            if (!(await confirmation.ask({
                title: isCorrection ? 'Save this correction?' : 'Undo this money record?',
                message: isCorrection
                    ? 'This permanently adds an exact undo and a linked corrected record. The original will remain unchanged.'
                    : 'This permanently adds an exact opposite money record. The original will remain unchanged.',
                confirm: isCorrection ? 'Confirm correction' : 'Confirm undo',
            }))) return;
            if (locked.value || current !== generation || review.value !== reviewed) return;
            saving.value = true; error.value = ''; absent.value = false;
            pending.value = { movement: frozen.public_id, source: props.sourcePublicId, action: props.mode,
                replacement: isCorrection ? frozen.replacement_public_id : null };
            marker(pending.value);
            try {
                const suffix = isCorrection ? 'correct' : 'reverse';
                await apiRequest(`${base}/${props.sourcePublicId}/${suffix}`, {
                    method: 'POST',
                    body: JSON.stringify({ ...frozen, review_fingerprint: review.value.review_fingerprint }),
                });
                if (!alive || current !== generation) return;
                await loadChain();
                marker(null); pending.value = null; saving.value = false; dirty.value = false;
                emit('navigate', routeHash('history'));
            } catch (failure) {
                if (!alive || current !== generation) return;
                if (!failure.status || failure.status >= 500 || [401, 403, 404].includes(failure.status)
                    || failure.code === 'FINANCIAL_IDENTITY_CONFLICT') {
                    phase.value = 'unknown';
                    error.value = 'We could not confirm whether the change was saved. Check the same reference. Do not create new references.';
                } else {
                    pending.value = null; marker(null); phase.value = 'input'; review.value = null; copyError(failure);
                }
            } finally { saving.value = false; }
        }
        async function checkStatus() {
            if (!pending.value || busy.value || saving.value || !online.value || !view.value) return;
            const current = generation;
            busy.value = true; absent.value = false; error.value = '';
            try {
                const result = await apiRequest(`${base}/${pending.value.movement}`);
                if (!alive || current !== generation) return;
                if (result.data.movement_type !== 'REVERSAL' || result.data.reversal_of_public_id !== pending.value.source) {
                    throw Error('The saved reference does not match this undo. Keep both references and ask the shop owner to investigate.');
                }
                if (pending.value.action === 'correct') {
                    let replacement;
                    try { replacement = await apiRequest(`${base}/${pending.value.replacement}`); }
                    catch (failure) {
                        if (failure.status === 404) throw Error('The undo record exists but its corrected replacement is not available. Do not retry with new references. Ask the shop owner to investigate.');
                        throw failure;
                    }
                    if (replacement.data.movement_type !== 'FINANCIAL_CORRECTION'
                        || replacement.data.replacement_for_public_id !== pending.value.source) {
                        throw Error('The corrected replacement does not match the original. Keep both references and ask the shop owner to investigate.');
                    }
                }
                await loadChain();
                marker(null); pending.value = null; dirty.value = false;
                emit('navigate', routeHash('history'));
            } catch (failure) {
                if (!alive || current !== generation) return;
                absent.value = failure.status === 404;
                error.value = absent.value
                    ? 'The undo reference was not found at the time of this check. An earlier request may still finish. Continue only with the same references and same details.'
                    : failure.message || 'Saved status is not available yet. Keep the references and check again.';
            } finally { if (alive && current === generation) busy.value = false; }
        }
        function resumeSame() {
            if (!absent.value || !pending.value || !canWrite.value || props.readOnly) return;
            form.public_id = pending.value.movement;
            if (pending.value.replacement) form.replacement_public_id = pending.value.replacement;
            phase.value = 'input'; review.value = null; dirty.value = true;
            error.value = 'Re-enter the same details and use the same references. Review checks whether the earlier request was saved.';
        }
        function connection() {
            online.value = navigator.onLine;
            generation++; controller?.abort(); busy.value = false;
            if (pending.value) { phase.value = 'unknown'; review.value = null; error.value = 'Your connection changed before the result was confirmed. Check saved status with the same reference.'; }
            else if (!online.value) error.value = 'You are offline. Nothing is queued. Connect to continue.';
        }
        window.addEventListener('online', connection);
        window.addEventListener('offline', connection);
        watch([() => props.mode, () => props.sourcePublicId, () => props.recoveryScope, view, reversePermission, correctPermission, () => props.readOnly], initialize, { immediate: true });
        onBeforeUnmount(() => { alive = false; generation++; controller?.abort(); window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });

        function chainView() {
            if (!chain.value) return null;
            return h('section', { class: 'commercial-card' }, [
                h('h3', 'Linked money record history'),
                h('p', 'Each entry is permanent. Undo and corrected records are added; earlier records are never edited or deleted.'),
                h('ol', { class: 'commercial-record-list' }, chain.value.items.map(item => h('li', { class: 'commercial-card' }, [
                    h('strong', item.relationship),
                    h('p', labels[item.movement_type] || 'Money record'),
                    facts([['Reference', item.public_id], ['Money direction', item.amount_delta], ['Payment method', item.channel === 'CASH' ? 'Cash' : 'Transfer'],
                        ['Description', item.description], ...(item.audit_reason !== item.description ? [['Why it was changed', item.audit_reason]] : []), ['Recorded by', item.recorded_by],
                        ['Date recorded', time(item.posted_at, item.shop_timezone)]]),
                ]))),
                h('p', chain.value.current_public_id ? 'The latest linked record is the current record.' : 'The latest record in this history has been undone; no replacement is currently active.'),
            ]);
        }
        function sourceCard() {
            if (!source.value) return null;
            return h('section', { class: 'commercial-card' }, [
                h('h3', 'Selected money record'),
                facts([['Type', labels[source.value.movement_type] || 'Money record'], ['Amount', money(source.value.amount)],
                    ['Money direction', source.value.amount_delta], ['Payment method', source.value.channel === 'CASH' ? 'Cash' : 'Transfer'],
                    ['Description', source.value.description], ['Date recorded', time(source.value.posted_at, source.value.shop_timezone)]]),
                h('p', 'This record will stay unchanged. New linked records will show the undo or correction.'),
            ]);
        }
        return () => h('section', { class: 'workspace commercial-workspace trading-screen ui-scope', 'aria-busy': busy.value || saving.value }, [
            h('h2', { ref: heading, tabindex: '-1' }, isHistory.value ? 'Linked money record history' : props.mode === 'reverse' ? 'Undo a money record' : 'Correct a money record'),
            !view.value ? h(UiInformationPanel, { severity: 'warning', message: 'You are not allowed to view money movements. Ask your shop owner for help.' }) : [
                error.value && h(UiAlert, { severity: 'error', message: error.value, persistent: true }),
                (busy.value || saving.value) && h(UiLoading, { label: saving.value ? 'Saving correction…' : 'Loading money record…' }),
                pending.value && [h('p', { class: 'trading-record-meta' }, `Undo reference: ${pending.value.movement}`),
                    pending.value.replacement && h('p', { class: 'trading-record-meta' }, `Replacement reference: ${pending.value.replacement}`),
                    action('Check saved status', checkStatus, busy.value || saving.value || !online.value, 'btn-primary', { icon: 'refresh' }),
                    absent.value && action('Continue with the same references', resumeSame, busy.value || props.readOnly || !canWrite.value)],
                !storageAvailable.value && h(UiInformationPanel, { severity: 'warning', message: 'This browser cannot keep the undo references after reload. Copy both references and keep this page open until the saved result is confirmed.' }),
                isHistory.value && chainView(),
                !isHistory.value && sourceCard(),
                !isHistory.value && source.value && !sourceEligible.value && h(UiInformationPanel, { severity: 'warning', message: 'This record has already been undone, replaced, or is itself an undo. Open its linked history instead of starting another correction.' }),
                !isHistory.value && (!canWrite.value || props.readOnly) && h(UiInformationPanel, { severity: 'warning', message: props.readOnly
                    ? 'Your current access is view only. You cannot undo or correct a money record.'
                    : props.mode === 'reverse'
                        ? 'You need the Undo money record permission to use this action.'
                        : 'You need both Undo money record and Correct money record permissions to use this action.' }),
                !isHistory.value && phase.value === 'input' && sourceEligible.value && canWrite.value && !props.readOnly && h('form', { class: 'commercial-form', onSubmit: prepare }, [
                    h('p', props.mode === 'reverse'
                        ? 'This adds a new record for the exact opposite amount. It does not erase the original.'
                        : 'This adds an exact undo and a corrected replacement together. Choose the right money direction and payment method.'),
                    props.mode === 'correct' && [
                        selectField('financial-correction-direction', 'Corrected money direction', form.direction, v => change('direction', v),
                            [['MONEY_OUT', 'Money went out of the shop'], ['MONEY_IN', 'Money came into the shop']], { disabled: locked.value }),
                        field('financial-correction-amount', 'Corrected amount (₦)', form.amount, v => change('amount', v), { inputmode: 'decimal', required: true, disabled: locked.value, errors: validation.value.amount || [] }),
                        selectField('financial-correction-channel', 'Payment method', form.channel, v => change('channel', v), [['CASH', 'Cash'], ['TRANSFER', 'Transfer']], { disabled: locked.value }),
                        field('financial-correction-description', 'Corrected description', form.description, v => change('description', v), { multiline: true, required: true, maxlength: 300, disabled: locked.value, errors: validation.value.description || [] }),
                        field('financial-correction-reference', 'Payment reference (optional)', form.external_reference, v => change('external_reference', v), { maxlength: 120, disabled: locked.value }),
                        field('financial-correction-happened', 'Date and time it happened (optional)', form.happened, v => change('happened', v), { type: 'datetime-local', step: 1, disabled: locked.value }),
                    ],
                    field('financial-correction-reason', props.mode === 'reverse' ? 'Why are you undoing this record?' : 'Why does this record need correcting?',
                        form.reason, v => change('reason', v), { multiline: true, required: true, maxlength: 500, disabled: locked.value, help: 'This explanation is kept with the permanent record.' }),
                    submit(props.mode === 'reverse' ? 'Review undo' : 'Review correction', locked.value, { loading: busy.value, busyLabel: 'Checking linked records…', icon: 'next' }),
                ]),
                !isHistory.value && phase.value === 'review' && review.value && h('section', { class: 'commercial-card' }, [
                    h('h3', 'Review before saving'),
                    facts([['Original record', labels[review.value.source_type] || 'Money record'], ['Original amount', review.value.source_amount_delta],
                        ['Undo amount', review.value.inverse_amount_delta], ['Undo payment method', review.value.inverse_channel === 'CASH' ? 'Cash' : 'Transfer'],
                        ['Cash effect of undo', review.value.physical_cash_effect],
                        ...(props.mode === 'correct' ? [['Corrected amount', review.value.replacement_amount_delta], ['Corrected payment method', review.value.replacement_channel === 'CASH' ? 'Cash' : 'Transfer'],
                            ['Corrected description', review.value.replacement_description],
                            ['Cash effect of corrected record', review.value.replacement_physical_cash_effect]] : []),
                        ['Reason', review.value.reason]]),
                    h('p', 'The original record will not change. The new records are permanent and linked.'),
                    action(props.mode === 'reverse' ? 'Confirm undo' : 'Confirm correction', save, locked.value, 'btn-primary', { loading: saving.value, busyLabel: 'Saving linked records…', icon: 'success' }),
                    action('Edit details', () => { phase.value = 'input'; review.value = null; }, saving.value || busy.value, 'btn-outline-primary', { icon: 'edit' }),
                ]),
                isHistory.value && chain.value && action('Back to money movements', () => emit('navigate', '#financial-movements'), busy.value, 'btn-outline-primary', { icon: 'back' }),
            ],
        ].filter(Boolean));
    },
};
