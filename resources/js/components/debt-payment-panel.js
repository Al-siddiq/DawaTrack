import { correctionHash } from './debt-correction-panel.js';
import { apiRequest } from '../api/http-client.js';
import { action, field, submit, money, message, has, pager } from './commercial-ui.js';
import { facts, selectField, fieldSummary, stateLabel, useTradingConfirm } from './trading-ui.js';
import { UiLoading, UiAlert, UiRecordList, UiInformationPanel } from './ui/index.js';
import { useNavigationGuard } from '../shared/workspace-navigation.js';
import { DebtPaymentReceipt, paymentTime } from './debt-payment-receipt.js';
const { h, ref, reactive, computed, watch, onBeforeUnmount, nextTick } = window.Vue;
const base = '/api/v1/shop/debt-payments';
const asKobo = value => /^(0|[1-9][0-9]{0,16})(\.[0-9]{1,2})?$/.test(value) ? BigInt(value.split('.')[0]) * 100n + BigInt((value.split('.')[1] || '').padEnd(2, '0')) : null;
const decimal = value => `${value < 0n ? '-' : ''}${(value < 0n ? -value : value) / 100n}.${String((value < 0n ? -value : value) % 100n).padStart(2, '0')}`;
const utcNow = () => new Date().toISOString().replace('T', ' ').replace('Z', '000');

export const DebtPaymentPanel = {
    props: { customerId: { type: String, required: true }, paymentId: { type: String, default: '' }, mode: { type: String, default: 'new' }, permissions: { type: Array, default: () => [] }, readOnly: { type: Boolean, default: true }, recoveryScope: { type: String, default: '' } },
    emits: ['navigate'],
    setup(props, { emit }) {
        const confirmation = useTradingConfirm(), phase = ref('input'), busy = ref(false), saving = ref(false), dirty = ref(false), online = ref(navigator.onLine);
        const customer = ref(null), context = ref(null), review = ref(null), receipt = ref(null), history = ref([]), error = ref(''), validation = ref({}), heading = ref(null), statusAbsent = ref(false);
        const form = reactive({ public_id: crypto.randomUUID(), amount: '', channel: 'CASH', external_reference: '', allocation_mode: 'OLDEST_FIRST', reason: '' });
        const choices = reactive({}), bills = ref([]), billPage = ref(0), billPages = ref(0);
        const pagination = reactive({ page: 1, total_pages: 0, per_page: 20, total: 0 });
        const frozen = ref(null), pendingIdentity = ref(null), storageAvailable = ref(true);
        let generation = 0, alive = true, controller = null;
        const view = computed(() => has(props.permissions, 'customer.view') && has(props.permissions, 'debt.view'));
        const collect = computed(() => view.value && has(props.permissions, 'debt.payment.record'));
        const allocate = computed(() => has(props.permissions, 'debt.payment.allocate'));
        const locked = computed(() => busy.value || saving.value || props.readOnly || !online.value || !collect.value || phase.value === 'unknown');
        const storageKey = () => props.recoveryScope ? `dt:pending-debt-payment:${location.host}:${props.recoveryScope}` : '';
        function marker(value) {
            try { if (!storageKey()) { storageAvailable.value = false; return; } value ? sessionStorage.setItem(storageKey(), JSON.stringify(value)) : sessionStorage.removeItem(storageKey()); }
            catch { storageAvailable.value = false; }
        }
        function readMarker() {
            try {
                const parsed = JSON.parse(storageKey() ? sessionStorage.getItem(storageKey()) : 'null');
                const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/;
                return parsed && uuid.test(parsed.payment) && uuid.test(parsed.customer) ? parsed : null;
            } catch { return null; }
        }
        const go = hash => emit('navigate', hash);
        const receiveHash = id => `#customers/debt/${id}/receive`;
        useNavigationGuard(({ hash }) => {
            if (saving.value) return { hard: true, message: 'This payment is being saved. Wait for the result.' };
            if (phase.value === 'unknown' || pendingIdentity.value) {
                if (hash === receiveHash(pendingIdentity.value?.customer) && hash !== location.hash) return null;
                return { hard: true, message: 'Check saved status or continue with the same payment reference before leaving this payment.' };
            }
            return dirty.value || phase.value === 'review' ? { message: 'This payment has not been saved. Keep editing, or leave without saving.' } : null;
        });
        function change(key, value) { form[key] = value; review.value = null; dirty.value = true; }
        async function loadBills(page = 1) {
            const current = generation, target = props.customerId;
            const response = await apiRequest(`/api/v1/shop/customers/${target}/debt?status=OWING&per_page=50&page=${page}`, { signal: controller?.signal });
            if (!alive || current !== generation || target !== props.customerId) return;
            context.value = response.data; customer.value = response.data.customer;
            bills.value = page === 1 ? response.data.bills : [...bills.value, ...response.data.bills.filter(b => !bills.value.some(old => old.public_id === b.public_id))];
            billPage.value = page; billPages.value = response.data.pagination.total_pages;
        }
        async function initialize(page = 1) {
            const current = ++generation; controller?.abort(); controller = new AbortController();
            busy.value = false; error.value = ''; receipt.value = null; history.value = []; customer.value = null; context.value = null;
            if (!view.value) return;
            if (!online.value) { error.value = 'Connect to load customer payments.'; return; }
            busy.value = true;
            try {
                if (props.mode === 'receipt') {
                    const result = await apiRequest(`${base}/${props.paymentId}`, { signal: controller.signal });
                    if (!alive || current !== generation) return;
                    if (result.data.customer_public_id !== props.customerId) throw new Error('This payment does not belong to the customer in this link.');
                    receipt.value = result.data; phase.value = 'saved';
                    const pending = readMarker(); if (pending?.payment === props.paymentId) marker(null);
                } else if (props.mode === 'history') {
                    const result = await apiRequest(`/api/v1/shop/customers/${props.customerId}/debt-payments?page=${page}&per_page=20`, { signal: controller.signal });
                    if (!alive || current !== generation) return;
                    customer.value = result.data.customer; history.value = result.data.items; Object.assign(pagination, result.data.pagination);
                } else {
                    try { const m = JSON.parse(props.recoveryScope ? sessionStorage.getItem(`dt:pending-debt-correction:${location.host}:${props.recoveryScope}`) : 'null');
                        const id = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/;
                        if (m && id.test(m.customer) && id.test(m.source) && id.test(m.id) && ['reverse','correct','adjust','adjust-reverse'].includes(m.kind) && (m.kind !== 'correct' || id.test(m.replacement))) { go(correctionHash(m)); return; }
                    } catch { /* A correction marker holds identities only. */ }
                    pendingIdentity.value = readMarker();
                    if (!pendingIdentity.value) {
                        phase.value = 'input'; dirty.value = false; review.value = null; frozen.value = null;
                        Object.assign(form, { public_id: crypto.randomUUID(), amount: '', channel: 'CASH', external_reference: '', allocation_mode: 'OLDEST_FIRST', reason: '' });
                        for (const key of Object.keys(choices)) delete choices[key];
                    }
                    if (pendingIdentity.value) { form.public_id = pendingIdentity.value.payment; phase.value = 'unknown'; error.value = 'An earlier payment may have been saved. Check saved status before recording another.'; }
                    if (pendingIdentity.value?.customer && pendingIdentity.value.customer !== props.customerId) {
                        go(receiveHash(pendingIdentity.value.customer)); return;
                    }
                    const result = await apiRequest(`/api/v1/shop/customers/${props.customerId}/debt?status=OWING&per_page=50`, { signal: controller.signal });
                    if (!alive || current !== generation) return;
                    customer.value = result.data.customer; context.value = result.data; bills.value = result.data.bills;
                    billPage.value = 1; billPages.value = result.data.pagination.total_pages;
                }
                await nextTick(); if (alive && current === generation) heading.value?.focus();
            } catch (failure) { if (alive && current === generation) error.value = failure.message || 'We could not load this payment.'; }
            finally { if (alive && current === generation) busy.value = false; }
        }
        function command() {
            return { public_id: form.public_id, customer_public_id: props.customerId, channel: form.channel, amount: form.amount,
                external_reference: form.channel === 'TRANSFER' ? form.external_reference || null : null, occurred_at: frozen.value?.occurred_at || utcNow(),
                allocation_mode: form.allocation_mode, allocations: form.allocation_mode === 'EXPLICIT' ? Object.entries(choices).filter(([,value]) => value !== null).map(([id,amount]) => ({ receivable_public_id: id, amount })) : [], reason: form.reason || null };
        }
        async function prepare(event) {
            event?.preventDefault(); if (locked.value) return;
            busy.value = true; error.value = ''; validation.value = {};
            try {
                const input = command();
                const response = await apiRequest(`${base}/preview`, { method: 'POST', body: JSON.stringify(input) });
                if (!alive) return;
                if (response.data.already_saved) { await showSaved(input.public_id); return; }
                review.value = response.data; frozen.value = response.data.command; phase.value = 'review';
                await nextTick(); heading.value?.focus();
            } catch (failure) { validation.value = failure.details || {}; error.value = failure.code === 'AUDIT_CONTEXT_INVALID' ? 'Check your device date and time, then review the payment again.' : failure.code === 'OPENING_CASH_REQUIRED' ? 'Set up Starting cash before receiving a Cash payment. Ask your shop owner for help.' : failure.code === 'CASH_CONTROL_ACTIVE' ? 'Finish the current cash count before receiving a Cash payment.' : failure.status >= 500 ? 'We could not review this payment. Your entries are kept. Try reviewing again.' : message(failure); review.value = null; }
            finally { busy.value = false; }
        }
        async function showSaved(id, data = null) {
            const result = data || (await apiRequest(`${base}/${id}`)).data;
            receipt.value = result; phase.value = 'saved'; dirty.value = false; pendingIdentity.value = null; marker(null);
            go(`#customers/debt/${result.customer_public_id}/payment/${id}`);
        }
        async function save() {
            if (locked.value || !review.value || phase.value !== 'review') return;
            saving.value = true; error.value = ''; statusAbsent.value = false;
            pendingIdentity.value = { payment: frozen.value.public_id, customer: props.customerId }; marker(pendingIdentity.value);
            try {
                const result = await apiRequest(base, { method: 'POST', body: JSON.stringify({ ...frozen.value, review_fingerprint: review.value.review_fingerprint }) });
                receipt.value = result.data; phase.value = 'saved'; dirty.value = false; pendingIdentity.value = null; marker(null);
                saving.value = false;
                go(`#customers/debt/${props.customerId}/payment/${frozen.value.public_id}`);
            } catch (failure) {
                if (failure.code === 'DEBT_IDENTITY_CONFLICT' || failure.status === 0 || failure.status >= 500 || [401,403,404].includes(failure.status)) {
                    phase.value = 'unknown'; error.value = 'We could not confirm whether this payment was saved. Check saved status. Do not record it again with a new reference.';
                } else {
                    pendingIdentity.value = null; marker(null); phase.value = 'input'; review.value = null;
                    validation.value = failure.details || {}; error.value = message(failure);
                }
            } finally { saving.value = false; }
        }
        async function checkStatus() {
            if (busy.value || saving.value || !online.value || !pendingIdentity.value) return;
            busy.value = true; error.value = ''; statusAbsent.value = false;
            try { await showSaved(pendingIdentity.value.payment); }
            catch (failure) {
                if (failure.status === 404) { statusAbsent.value = true; error.value = 'This reference was not found at the time of the check. An earlier request may still finish. Continue only with this same reference.'; }
                else error.value = 'We cannot check the saved status yet. Keep this reference and try again when your access and connection are available.';
            } finally { busy.value = false; }
        }
        function resumeSame() {
            if (!statusAbsent.value || !pendingIdentity.value || props.readOnly || !collect.value) return;
            form.public_id = pendingIdentity.value.payment; phase.value = 'input'; review.value = null; dirty.value = true; error.value = 'Continue with this same payment reference. Review checks whether it was already saved before any new payment can be recorded.';
        }
        async function moreBills() {
            if (locked.value) return; busy.value = true; error.value = '';
            try { await loadBills(billPage.value + 1); } catch (failure) { error.value = message(failure); } finally { busy.value = false; }
        }
        const remainder = computed(() => {
            const amount = asKobo(form.amount); if (amount === null) return 'Enter a valid payment amount';
            let total = 0n; for (const value of Object.values(choices)) { if (value === null) continue; const parsed = asKobo(value); if (parsed === null) return 'Check each chosen amount'; total += parsed; }
            return total > amount ? `${money(decimal(total - amount))} too much assigned` : `${money(decimal(amount - total))} left to assign`;
        });
        const connection = () => { online.value = navigator.onLine; if (!online.value && props.mode !== 'new') { receipt.value = null; history.value = []; error.value = 'Connect to load customer payments.'; } };
        window.addEventListener('online', connection); window.addEventListener('offline', connection);
        watch([() => props.mode, () => props.customerId, () => props.paymentId, view], () => initialize(), { immediate: true });
        onBeforeUnmount(() => { alive = false; generation++; controller?.abort(); window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        return () => h('section', { class: 'workspace commercial-workspace trading-screen ui-scope', 'aria-busy': busy.value || saving.value }, [
            h('h2', { ref: heading, tabindex: '-1' }, props.mode === 'new' ? 'Receive payment' : props.mode === 'history' ? 'Customer payments' : 'Saved customer payment'),
            action('Back to customer bills', () => confirmation.guard(() => go(`#customers/debt/${props.customerId}`)), busy.value || saving.value),
            ...confirmation.render(),
            !online.value && h(UiInformationPanel, { severity: 'warning', message: 'You are offline. Connect before reviewing or saving a payment. Nothing is queued.' }), !view.value && h(UiInformationPanel, { severity: 'warning', message: 'You are not allowed to view customer payments.' }),
            error.value && h(UiAlert, { severity: 'error', message: error.value, persistent: true }),
            fieldSummary(validation.value, { amount: 'debt-payment-amount', allocations: 'debt-assignments', external_reference: 'debt-payment-reference', reason: 'debt-payment-reason' }),
            (busy.value || saving.value) && h(UiLoading, { label: saving.value ? 'Saving this payment…' : 'Loading payment details…' }),
            props.mode !== 'new' && action('Refresh payment details', () => initialize(pagination.page), busy.value || !online.value, 'btn-outline-primary', { icon: 'refresh' }),
            props.mode === 'new' && pendingIdentity.value && [
                h('p', { class: 'trading-record-meta' }, `Payment reference: ${pendingIdentity.value?.payment || form.public_id}`),
                action('Check saved status', checkStatus, busy.value || saving.value || !online.value, 'btn-primary', { icon: 'refresh' }),
                statusAbsent.value && phase.value === 'unknown' && action('Continue with this same payment reference', resumeSame, busy.value || props.readOnly || !collect.value),
            ],
            !storageAvailable.value && h(UiInformationPanel, { severity: 'warning', message: 'This browser cannot keep your payment reference after a reload. Keep this page open until the saved status is confirmed.' }),
            props.mode === 'new' && !collect.value && h(UiInformationPanel, { severity: 'warning', message: 'You are not allowed to receive payments. Ask your shop owner for help.' }),
            props.mode === 'new' && customer.value && phase.value === 'input' && collect.value && h('form', { class: 'commercial-form', onSubmit: prepare }, [
                h('h3', customer.value.display_name), context.value && facts([['Current amount still to pay', money(context.value.summary.outstanding_amount)]]),
                h('p', 'Enter one Cash or bank transfer payment. Two payment methods need two separate records.'),
                field('debt-payment-amount', 'Amount received (₦)', form.amount, value => change('amount', value), { required: true, inputmode: 'decimal', disabled: locked.value, errors: validation.value.amount || [] }),
                selectField('debt-payment-channel', 'Paid by', form.channel, value => change('channel', value), [['CASH','Cash'],['TRANSFER','Bank transfer']], { disabled: locked.value }),
                form.channel === 'TRANSFER' && field('debt-payment-reference', 'Bank transfer reference (optional)', form.external_reference, value => change('external_reference', value), { maxlength:120, disabled:locked.value }),
                allocate.value ? selectField('debt-payment-mode', 'Bills this payment will cover', form.allocation_mode, value => change('allocation_mode', value), [['OLDEST_FIRST','Pay the oldest bills first'],['EXPLICIT','Choose bills']], { disabled:locked.value }) : h('p', 'This payment will cover the oldest unpaid bills first.'),
                form.allocation_mode === 'EXPLICIT' && h('section', { id: 'debt-assignments', tabindex:'-1' }, [h('h3','Choose bills'), h('p',remainder.value), h('p','These entered amounts will be checked before saving.'), ...bills.value.map(b => h('article',{class:'commercial-card',key:b.public_id},[
                    h('label',[h('input',{type:'checkbox',checked:choices[b.public_id] !== undefined && choices[b.public_id] !== null,disabled:locked.value,onChange:e=>{ choices[b.public_id]=e.target.checked?'':null;dirty.value=true; }}),` Bill ${b.public_id}`]), h('p',`${money(b.outstanding_amount)} still to pay · Due: ${b.due_date || 'Not set'}`),
                    choices[b.public_id] !== undefined && choices[b.public_id] !== null && field(`assign-${b.public_id}`,'Amount for this bill (₦)',choices[b.public_id],value=>{choices[b.public_id]=value;dirty.value=true;},{inputmode:'decimal',required:true,disabled:locked.value}),
                ])), billPage.value < billPages.value && action('Load more bills',moreBills,locked.value)]),
                field('debt-payment-reason','Payment note (optional)',form.reason,value=>change('reason',value),{multiline:true,maxlength:500,disabled:locked.value}),
                submit('Review payment',locked.value,{loading:busy.value,busyLabel:'Checking the bills…',icon:'next'}),
            ].filter(Boolean)),
            props.mode === 'new' && review.value && phase.value === 'review' && h('section',{class:'commercial-card'},[
                h('h3','Review this payment'),facts([['Customer',review.value.customer_name],['Amount received',money(review.value.command.amount)],['Paid by',stateLabel(review.value.command.channel)],['Total before payment',money(review.value.customer_balance_before)],['Total after payment',money(review.value.customer_balance_after)]]),
                ...review.value.allocations.map(a=>h('article',{class:'commercial-card',key:a.receivable_public_id},[h('p',`Bill ${a.receivable_public_id}`),facts([['Payment toward this bill',money(a.amount)],['Still to pay after payment',money(a.after_amount)]])])),
                action('Save payment',save,locked.value,'btn-primary',{loading:saving.value,busyLabel:'Saving payment…',icon:'success'}),
                action('Edit payment',()=>{phase.value='input';review.value=null;},busy.value||saving.value),
            ]),
            view.value && receipt.value && !receipt.value.reversal_of_public_id && !receipt.value.reversed_by_public_id && has(props.permissions,'debt.payment.reverse') && [
                action('Undo payment',()=>go(`#customers/debt/${props.customerId}/payment/${receipt.value.public_id}/reverse`),props.readOnly||!online.value),
                has(props.permissions,'debt.payment.record') && action('Correct payment',()=>go(`#customers/debt/${props.customerId}/payment/${receipt.value.public_id}/correct`),props.readOnly||!online.value),
            ],
            view.value && receipt.value && h(DebtPaymentReceipt,{payment:receipt.value,permissions:props.permissions,onNavigate:go}),
            props.mode === 'history' && view.value && !busy.value && !error.value && [customer.value && h('h3',customer.value.display_name),h(UiRecordList,{records:history.value,emptyTitle:'No customer payments yet',emptyMessage:'Payments will appear here after they are saved.'},{record:({record:p})=>h('article',{class:'commercial-card'},[h('h3',`${money(p.amount)} · ${stateLabel(p.channel)}`),h('p',`Saved (on this device): ${paymentTime(p.posted_at)}`),p.reversed_by_public_id && h('p','This payment was undone.'),p.reversal_of_public_id && h('p','This record undoes an earlier payment.'),action('View receipt',()=>go(`#customers/debt/${props.customerId}/payment/${p.public_id}`))])}),pager(pagination,busy.value||!online.value,initialize)],
        ].filter(Boolean));
    },
};
