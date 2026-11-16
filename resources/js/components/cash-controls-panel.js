import { apiRequest } from '../api/http-client.js';
import { field, action, submit, feedback, message } from './commercial-ui.js';
import { selectField, useTradingConfirm } from './trading-ui.js';
import { UiLoading, UiInformationPanel } from './ui/index.js';
import { useNavigationGuard } from '../shared/workspace-navigation.js';

const { h, ref, computed, onMounted, onUnmounted, watch } = window.Vue;
const root = '/api/v1/shop/cash-controls';
const validId = id => typeof id === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(id);
const cash = value => {
    const text = String(value ?? '').trim();
    if (!/^(?:0|[1-9][0-9]{0,16})(?:\.[0-9]{1,2})?$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    return `${whole}.${fraction.padEnd(2, '0')}`;
};
const utc = value => value ? new Date(value).toISOString().replace('T', ' ').replace('Z', '000') : null;
const labels = { COUNTING: 'Physical counting', COUNTS_COMMITTED: 'Count committed', REVIEW: 'Review', FINALIZED: 'Finalized', ABORTED: 'Aborted' };
const componentLabels = { opening_baseline_amount: 'Previous physical Cash baseline', cash_sale_receipts: 'Cash Sale receipts', cash_sale_refunds: 'Cash Sale refunds',
    cash_debt_receipts: 'Cash Debt receipts', cash_introductions: 'Owner Cash introductions', cash_expenses: 'Cash expenses', cash_withdrawals: 'Owner Cash withdrawals', cash_corrections_net: 'Signed Cash corrections' };

export const CashControlsPanel = {
    props: { permissions: { type: Array, default: () => [] }, isOwner: Boolean, readOnly: Boolean,
        recoveryScope: { type: String, required: true }, hash: { type: String, default: '#cash-controls' } },
    emits: ['navigate'],
    setup(props, { emit }) {
        const confirmation = useTradingConfirm();
        const records = ref([]), control = ref(null), devices = ref([]), notes = ref([]), staff = ref([]);
        const listPage = ref(1), listPages = ref(0), devicePage = ref(1), devicePages = ref(0), notePage = ref(1), notePages = ref(0), staffPage = ref(1), staffPages = ref(0);
        const loading = ref(true), pending = ref(false), online = ref(navigator.onLine), unknown = ref(false), storageWarning = ref(false);
        const error = ref(''), notice = ref(''), amount = ref(''), explanation = ref(''), reason = ref('');
        const selectedId = ref(null), startId = ref(crypto.randomUUID()), noteId = ref(crypto.randomUUID()), savedMarker = ref(null);
        const sourceChoices = computed(() => [
            ...(props.permissions.includes('sale.view') ? [['sale', 'Sale or Return']] : []),
            ...(props.permissions.includes('customer.view') && props.permissions.includes('debt.view') ? [['debt_payment', 'Debt payment']] : []),
            ...(props.permissions.includes('financial.view') ? [['financial_movement', 'Money movement']] : []),
        ]);
        const noteType = ref('EXPLANATION'), noteText = ref(''), sourceKind = ref(sourceChoices.value[0]?.[0] || ''), sourceId = ref(''), sourceResult = ref(null);
        const members = ref([]), search = ref(''), memberPage = ref(1), memberPages = ref(0), duty = ref({});
        const has = code => props.permissions.includes(`reconciliation.${code}`);
        const blocked = computed(() => pending.value || loading.value || props.readOnly || !online.value || unknown.value);
        const key = `dt:pending-cash-control:${location.host}:${props.recoveryScope}`;
        let revision = 0, alive = true;
        const dirty = computed(() => noteText.value.trim() || reason.value.trim() || Object.keys(duty.value).length
            || (control.value?.status === 'COUNTING' && amount.value !== '' && cash(amount.value) !== control.value?.count?.actual_amount)
            || (control.value?.status === 'REVIEW' && explanation.value !== (control.value?.snapshot?.explanation || '')));
        useNavigationGuard(() => unknown.value ? { hard: true, message: 'Check the saved Cash control status before leaving. Keep its original reference.' }
            : dirty.value ? { message: 'Your Cash count, responsibility context or explanation has unsaved changes.' } : null);

        function remember(actionName, id) {
            const value = { control_public_id: id, action: actionName };
            if (actionName === 'note') value.note_public_id = noteId.value;
            savedMarker.value = value;
            try { sessionStorage.setItem(key, JSON.stringify(value)); } catch { storageWarning.value = true; }
        }
        function clearMarker() {
            savedMarker.value = null;
            try { sessionStorage.removeItem(key); } catch { storageWarning.value = true; }
        }
        function readMarker() {
            try {
                const raw = sessionStorage.getItem(key);
                if (!raw) return;
                const value = JSON.parse(raw);
                const actions = ['start', 'count', 'commit', 'review', 'explain', 'finalize', 'abort', 'acknowledge', 'exclude', 'note'];
                if (!validId(value.control_public_id) || !actions.includes(value.action) || (value.action === 'note' && !validId(value.note_public_id))) {
                    storageWarning.value = true;
                    error.value = 'The saved recovery reference needs Owner review. Do not clear browser storage to dismiss uncertain work.';
                    unknown.value = true;
                    return;
                }
                savedMarker.value = value;
                selectedId.value = value.control_public_id;
                if (value.action === 'start') startId.value = value.control_public_id;
                if (value.note_public_id) noteId.value = value.note_public_id;
                unknown.value = true;
            } catch { storageWarning.value = true; }
        }
        function choose(id) {
            if (pending.value || unknown.value) return;
            devicePage.value = notePage.value = staffPage.value = 1;
            // The parent navigation guard approves the route; its hash watcher loads the next record.
            emit('navigate', id ? `#cash-controls/${id}` : '#cash-controls');
        }
        async function load(preserve = true) {
            const request = ++revision;
            const id = selectedId.value;
            loading.value = true;
            error.value = '';
            control.value = null;
            devices.value = notes.value = staff.value = records.value = [];
            sourceResult.value = null;
            try {
                if (!id) {
                    const result = await apiRequest(`${root}?page=${listPage.value}&per_page=20`);
                    if (!alive || request !== revision) return;
                    records.value = result.data.controls;
                    listPages.value = result.meta.pagination.total_pages;
                } else {
                    const result = await apiRequest(`${root}/${id}`);
                    const value = result.data;
                    const requests = [apiRequest(`${root}/${id}/readiness?page=${devicePage.value}&per_page=20`)];
                    if (value.can_review_values) requests.push(apiRequest(`${root}/${id}/staff?page=${staffPage.value}&per_page=20`));
                    if (value.status === 'FINALIZED' && value.can_review_values) requests.push(apiRequest(`${root}/${id}/notes?page=${notePage.value}&per_page=20`));
                    const extras = await Promise.all(requests);
                    if (!alive || request !== revision) return;
                    control.value = value;
                    devices.value = extras[0].data.devices;
                    devicePages.value = extras[0].meta.pagination.total_pages;
                    let index = 1;
                    if (value.can_review_values) {
                        staff.value = extras[index].data.contexts;
                        staffPages.value = extras[index++].meta.pagination.total_pages;
                    }
                    if (value.status === 'FINALIZED' && value.can_review_values) {
                        notes.value = extras[index].data.notes;
                        notePages.value = extras[index].meta.pagination.total_pages;
                    }
                    if (!preserve) {
                        amount.value = value.count?.actual_amount ?? '';
                        explanation.value = value.snapshot?.explanation ?? '';
                    }
                    if (unknown.value) notice.value = 'Saved server status is shown. It does not mean a missing or changed command failed. Keep the original note/reference when continuing.';
                }
                if (unknown.value && savedMarker.value?.action !== 'note') {
                    unknown.value = false;
                    clearMarker();
                } else if (unknown.value && savedMarker.value?.action === 'note' && notes.value.some(n => n.public_id === savedMarker.value.note_public_id)) {
                    unknown.value = false;
                    noteId.value = crypto.randomUUID();
                    noteText.value = '';
                    clearMarker();
                }
            } catch (e) {
                if (alive && request === revision) {
                    error.value = message(e);
                    control.value = null;
                    devices.value = notes.value = staff.value = records.value = [];
                }
            } finally { if (alive && request === revision) loading.value = false; }
        }
        async function command(actionName, body = {}, allowRetry = false) {
            if (pending.value || loading.value || props.readOnly || !online.value || (unknown.value && !allowRetry)) return;
            const id = actionName === 'start' ? startId.value : selectedId.value;
            if (!validId(id)) return;
            pending.value = true;
            error.value = '';
            notice.value = '';
            remember(actionName, id);
            try {
                const path = actionName === 'start' ? root : `${root}/${id}/${actionName}`;
                await apiRequest(path, { method: 'POST', body: JSON.stringify(actionName === 'start' ? { public_id: id, ...body } : body) });
                unknown.value = false;
                clearMarker();
                if (actionName === 'note') { noteId.value = crypto.randomUUID(); noteText.value = ''; }
                if (actionName === 'start') { duty.value = {}; startId.value = crypto.randomUUID(); }
                reason.value = '';
                notice.value = 'Saved. The server status has been refreshed.';
                selectedId.value = id;
                emit('navigate', `#cash-controls/${id}`);
                await load(false);
            } catch (e) {
                error.value = message(e);
                if (!e.status || e.status >= 500 || [401, 403, 404].includes(e.status) || e.code === 'CASH_CONTROL_IDENTITY_CONFLICT') {
                    unknown.value = true;
                } else { clearMarker(); }
            } finally { pending.value = false; }
        }
        async function start(event) {
            event.preventDefault();
            if (blocked.value) return;
            try {
                const onDuty = Object.values(duty.value).map(v => ({ membership_public_id: v.public_id,
                    starts_at: utc(v.starts_at), ends_at: utc(v.ends_at), notes: v.notes || null }));
                if (!await confirmation.ask({ title: 'Start a blind Cash control?', message: 'The server will freeze the Cash cutoff and relevant Device set. Cash-affecting transactions pause until this control is finalized or aborted.', confirm: 'Start Cash control' })) return;
                await command('start', { on_duty: onDuty });
            } catch (e) { error.value = message(e); }
        }
        async function confirmCommand(name, body, title, text) {
            if (blocked.value) return;
            if (await confirmation.ask({ title, message: text, confirm: 'Confirm', danger: name === 'abort' })) await command(name, body);
        }
        async function acknowledge() {
            if (blocked.value) return;
            pending.value = true;
            error.value = '';
            let receipt;
            try { receipt = await apiRequest(`${root}/${selectedId.value}/challenge`); }
            catch (e) { error.value = message(e); }
            finally { pending.value = false; }
            if (receipt) await command('acknowledge', { proof: receipt.data.proof });
        }
        async function memberSearch(event) {
            event?.preventDefault();
            if (pending.value || !online.value) return;
            pending.value = true;
            error.value = '';
            try {
                const result = await apiRequest(`${root}/members?search=${encodeURIComponent(search.value)}&page=${memberPage.value}&per_page=20`);
                members.value = result.data.members;
                memberPages.value = result.meta.pagination.total_pages;
            } catch (e) { error.value = message(e); members.value = []; }
            finally { pending.value = false; }
        }
        function updateDuty(member, name, value) {
            duty.value = { ...duty.value, [member.public_id]: { ...duty.value[member.public_id], public_id: member.public_id, [name]: value } };
        }
        async function sourceStatus(event) {
            event.preventDefault();
            if (pending.value || !online.value || !validId(sourceId.value)) return;
            pending.value = true;
            error.value = '';
            try { sourceResult.value = (await apiRequest(`${root}/${selectedId.value}/source-status?kind=${sourceKind.value}&public_id=${sourceId.value}`)).data; }
            catch (e) { error.value = message(e); sourceResult.value = null; }
            finally { pending.value = false; }
        }
        const connection = () => {
            online.value = navigator.onLine;
            if (!online.value) { revision++; control.value = null; devices.value = notes.value = staff.value = records.value = []; loading.value = false; }
        };
        watch(() => props.hash, value => {
            if (pending.value || unknown.value) return;
            const id = value.split('/')[1] || null;
            if (id === selectedId.value) return;
            selectedId.value = validId(id) ? id : null;
            load(false);
        });
        onMounted(() => {
            readMarker();
            if (!savedMarker.value) {
                const id = props.hash.split('/')[1] || null;
                selectedId.value = validId(id) ? id : null;
            }
            load(false);
            window.addEventListener('online', connection);
            window.addEventListener('offline', connection);
        });
        onUnmounted(() => { alive = false; revision++; window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        const pages = (label, page, max, work) => h('nav', { 'aria-label': label }, [
            action('Previous page', () => { page.value--; work(); }, pending.value || loading.value || !online.value || page.value <= 1),
            h('span', ` Page ${page.value} of ${Math.max(1, max.value)} `),
            action('Next page', () => { page.value++; work(); }, pending.value || loading.value || !online.value || page.value >= max.value),
        ]);
        return () => h('section', { class: 'commercial-panel', 'aria-labelledby': 'cash-control-title' }, [
            h('h1', { id: 'cash-control-title' }, 'Cash control'),
            h('p', 'Count the physical Cash, confirm it, then review the frozen result. A variance records a difference, not proof that a Staff member caused it.'),
            ...feedback(error.value, notice.value),
            props.readOnly && h(UiInformationPanel, { title: 'View only', message: 'You can inspect permitted saved records, but cannot change this control.' }),
            !online.value && h(UiInformationPanel, { title: 'Connect to continue', message: 'Cash control requires the server. Saved progress stays on the server; unsaved input is not queued offline.' }),
            storageWarning.value && h(UiInformationPanel, { title: 'Keep this reference', message: `Recovery storage is unavailable or needs review. Keep this page open and copy ${savedMarker.value?.control_public_id || selectedId.value || startId.value}. Do not clear site storage to dismiss uncertain work.` }),
            unknown.value && h(UiInformationPanel, { title: 'Check saved status before continuing', message: `The reply is unconfirmed. Original reference: ${savedMarker.value?.control_public_id || selectedId.value || startId.value}. No automatic retry will run.` }),
            action('Check saved status', () => load(true), pending.value || loading.value || !online.value),
            loading.value ? h(UiLoading, { label: 'Loading Cash control' }) : !selectedId.value ? h('div', [
                has('start') && h('form', { class: 'commercial-form', onSubmit: start }, [
                    h('h2', 'Start a Cash control'),
                    h('p', 'On-duty context is optional. Add known duty times only; unknown times can stay empty.'),
                    field('cash-members-search', 'Find on-duty members', search.value, v => { search.value = v; }, { type: 'search', disabled: blocked.value }),
                    action('Find members', memberSearch, blocked.value),
                    ...members.value.map(member => h('div', { key: member.public_id, class: 'ui-card' }, [
                        h('label', [h('input', { type: 'checkbox', checked: !!duty.value[member.public_id], disabled: blocked.value,
                            onChange: event => { if (event.target.checked) updateDuty(member, 'notes', ''); else { const copy = { ...duty.value }; delete copy[member.public_id]; duty.value = copy; } } }), ` ${member.display_name}`]),
                        duty.value[member.public_id] && h('div', [
                            field(`duty-start-${member.public_id}`, 'Duty began (optional)', duty.value[member.public_id].starts_at || '', v => updateDuty(member, 'starts_at', v), { type: 'datetime-local', disabled: blocked.value }),
                            field(`duty-end-${member.public_id}`, 'Duty ended (optional)', duty.value[member.public_id].ends_at || '', v => updateDuty(member, 'ends_at', v), { type: 'datetime-local', disabled: blocked.value }),
                            field(`duty-note-${member.public_id}`, 'Duty context (optional)', duty.value[member.public_id].notes || '', v => updateDuty(member, 'notes', v), { maxlength: 500, disabled: blocked.value }),
                        ]),
                    ])),
                    memberPages.value > 1 && pages('Member pages', memberPage, memberPages, memberSearch),
                    h('p', `${Object.keys(duty.value).length} on-duty members selected.`),
                    submit('Start Cash control', blocked.value, { loading: pending.value }),
                ]),
                h('h2', 'Cash control history'),
                !records.value.length && h('p', 'No Cash controls on this page.'),
                ...records.value.map(row => h('article', { key: row.public_id, class: 'ui-card' }, [
                    h('h3', labels[row.status] || row.status), h('p', `Cutoff (UTC): ${row.cutoff_at}`),
                    action('View Cash control', () => choose(row.public_id), pending.value || unknown.value),
                ])),
                pages('Cash control pages', listPage, listPages, () => load(false)),
            ]) : control.value ? h('div', [
                action('Back to Cash controls', () => choose(null), pending.value || unknown.value),
                h('h2', labels[control.value.status] || control.value.status),
                h('p', `Reference: ${control.value.public_id}`),
                h('p', `Period (UTC): ${control.value.cash_period_starts_at} to ${control.value.cutoff_at}`),
                control.value.generation_changed && h(UiInformationPanel, { title: 'Server generation changed', message: 'This control cannot progress. An authorized user must abort it safely and start a new one.' }),
                h('h3', 'Device readiness'),
                h('p', `${control.value.readiness.resolved} of ${control.value.readiness.required} pinned Devices resolved.`),
                control.value.status !== 'FINALIZED' && control.value.status !== 'ABORTED' && control.value.current_device_public_id && control.value.current_device_required
                    && action('Acknowledge this Device online', acknowledge, blocked.value || control.value.generation_changed),
                ...devices.value.map(device => h('article', { key: device.public_id, class: 'ui-card' }, [
                    h('h4', device.display_name), h('p', `${device.decision === 'ACKNOWLEDGED' ? 'Acknowledged' : device.decision === 'EXCLUDED' ? 'Explicitly excluded' : 'Waiting'} — Device ${device.device_status.toLowerCase()}`),
                    device.reason && h('p', `Reason: ${device.reason}`),
                    device.decided_by && h('p', `Decision by ${device.decided_by}`),
                    props.isOwner && has('exclude_device') && !device.decision && ['COUNTING', 'COUNTS_COMMITTED', 'REVIEW'].includes(control.value.status)
                        && action('Exclude this Device', () => confirmCommand('exclude', { device_public_id: device.public_id, reason: reason.value }, 'Exclude this Device?', 'Its unavailable information may affect confidence in the count. Your explicit reason will remain in the evidence.'), blocked.value || !reason.value.trim()),
                ])),
                pages('Readiness pages', devicePage, devicePages, () => load(true)),
                control.value.status === 'COUNTING' && h('div', [
                    h('p', 'Expected Cash and its components stay hidden until count commitment and authorized review.'),
                    has('count') && control.value.count && h('form', { class: 'commercial-form', onSubmit: event => {
                        event.preventDefault(); command('count', { amount: amount.value, expected_updated_at: control.value.count.revision });
                    } }, [
                        field('cash-actual', 'Physical Cash counted', amount.value, v => { amount.value = v; }, { inputmode: 'decimal', required: true, disabled: blocked.value }),
                        h('p', `Saved server observation: ${control.value.count.actual_amount === null ? 'Not counted yet' : `₦${control.value.count.actual_amount}`}`),
                        submit('Save physical count', blocked.value, { loading: pending.value }),
                        has('commit') && action('Commit and lock this count', () => confirmCommand('commit', { expected_updated_at: control.value.count.revision }, 'Commit this physical count?', 'The observation becomes read-only. Expected values are revealed only to authorized reviewers.'), blocked.value || !control.value.readiness.complete || control.value.count.actual_amount === null || cash(amount.value) !== control.value.count.actual_amount),
                    ]),
                ]),
                control.value.status === 'COUNTS_COMMITTED' && has('review') && action('Open review', () => command('review'), blocked.value || control.value.generation_changed),
                control.value.can_review_values && control.value.snapshot && h('section', { 'aria-label': 'Frozen Cash review' }, [
                    h('h3', 'Frozen result'),
                    control.value.previous_cash_kind === 'STANDARD' && validId(control.value.previous_cash_public_id) && action('View previous Cash control', () => choose(control.value.previous_cash_public_id), pending.value || unknown.value),
                    props.isOwner && control.value.previous_cash_kind === 'OPENING' && action('View Starting cash', () => emit('navigate', '#opening-cash'), pending.value || unknown.value),
                    props.isOwner && action('View activity', () => emit('navigate', '#audit'), pending.value || unknown.value),
                    h('dl', { class: 'trading-facts' }, Object.entries(componentLabels).map(([key, label]) => h('div', [h('dt', label), h('dd', `₦${control.value.snapshot[key]}`)]))),
                    h('p', `Expected Cash: ₦${control.value.snapshot.expected_amount}`), h('p', `Physical Cash: ₦${control.value.snapshot.actual_amount}`),
                    h('p', `${control.value.snapshot.variance_amount.startsWith('-') ? 'Shortage' : control.value.snapshot.variance_amount === '0.00' ? 'Balanced' : 'Surplus'}: ₦${control.value.snapshot.variance_amount}`),
                    control.value.snapshot.explanation && h('p', `Saved explanation: ${control.value.snapshot.explanation}`),
                    control.value.status === 'REVIEW' && h('form', { class: 'commercial-form', onSubmit: event => {
                        event.preventDefault(); command('explain', { explanation: explanation.value, expected_updated_at: control.value.count.revision });
                    } }, [field('cash-explanation', 'Explain the variance', explanation.value, v => { explanation.value = v; }, { multiline: true, maxlength: 500, disabled: blocked.value, required: true }), submit('Save explanation', blocked.value, { loading: pending.value })]),
                    control.value.status === 'REVIEW' && has('finalize') && action('Finalize Cash control', () => confirmCommand('finalize', {}, 'Finalize this Cash control?', 'The observations, variance and explanation become immutable. This actual Cash becomes the next baseline; no balancing movement is created.'), blocked.value || !control.value.readiness.complete || control.value.generation_changed || explanation.value !== (control.value.snapshot.explanation || '')),
                    h('h3', 'Staff responsibility context'),
                    ...staff.value.map(row => h('p', { key: `${row.membership_public_id}:${row.context_role}` }, `${row.display_name}: ${row.context_role.replaceAll('_', ' ').toLowerCase()}${row.context_starts_at ? `; from ${row.context_starts_at} UTC` : ''}${row.context_ends_at ? `; to ${row.context_ends_at} UTC` : ''}${row.notes ? `; ${row.notes}` : ''}`)),
                    pages('Staff context pages', staffPage, staffPages, () => load(true)),
                ]),
                ['COUNTING', 'COUNTS_COMMITTED', 'REVIEW'].includes(control.value.status) && (has('abort') || (props.isOwner && has('exclude_device'))) && h('div', [
                    field('cash-control-reason', 'Reason for exclusion or abort', reason.value, v => { reason.value = v; }, { multiline: true, maxlength: 500, disabled: blocked.value }),
                    has('abort') && action('Abort Cash control', () => confirmCommand('abort', { reason: reason.value }, 'Abort this Cash control?', 'Saved observations remain as aborted evidence. This control will not advance the Cash baseline.'), blocked.value || !reason.value.trim()),
                ]),
                control.value.status === 'ABORTED' && h('p', `Abort reason: ${control.value.abort_reason}`),
                control.value.status === 'FINALIZED' && control.value.can_review_values && h('div', [
                    h('h3', 'Later variance notes'),
                    ...notes.value.map(row => h('article', { key: row.public_id, class: 'ui-card' }, [h('h4', row.note_type.replaceAll('_', ' ').toLowerCase()), h('p', row.note_text), h('p', `${row.author_name} — ${row.created_at} UTC`), h('small', row.public_id)])),
                    pages('Variance note pages', notePage, notePages, () => load(true)),
                    has('note') && control.value.variance_public_id && h('form', { class: 'commercial-form', onSubmit: event => {
                        event.preventDefault(); command('note', { public_id: noteId.value, note_type: noteType.value, note_text: noteText.value }, unknown.value && savedMarker.value?.action === 'note');
                    } }, [
                        selectField('cash-note-type', 'Note type', noteType.value, v => { noteType.value = v; }, [['EXPLANATION', 'Explanation'], ['INVESTIGATION_FINDING', 'Investigation finding'], ['REVIEW_NOTE', 'Review note']], { disabled: pending.value || props.readOnly || !online.value }),
                        field('cash-note', 'Append a later note', noteText.value, v => { noteText.value = v; }, { multiline: true, maxlength: 2000, required: true, disabled: pending.value || props.readOnly || !online.value }),
                        submit(unknown.value ? 'Retry this same note reference' : 'Append variance note', pending.value || props.readOnly || !online.value || (unknown.value && savedMarker.value?.action !== 'note'), { loading: pending.value }),
                    ]),
                ]),
                sourceChoices.value.length > 0 && h('form', { class: 'commercial-form', onSubmit: sourceStatus }, [
                    h('h3', 'Check a saved transaction reference'),
                    h('p', 'This check shows only whether a record exists and its saved state. It does not reveal amounts or prove a changed command was accepted.'),
                    selectField('cash-source-kind', 'Record kind', sourceKind.value, v => { sourceKind.value = v; }, sourceChoices.value),
                    field('cash-source-reference', 'Original public reference', sourceId.value, v => { sourceId.value = v; }),
                    submit('Check transaction reference', pending.value || !online.value || !validId(sourceId.value)),
                    sourceResult.value && h('p', `${sourceResult.value.state}. ${sourceResult.value.guidance}`),
                ]),
            ]) : h('div', [
                h('p', 'The control could not be loaded. A404 read does not prove an earlier request failed.'),
                unknown.value && savedMarker.value?.action === 'start' && has('start') && action('Continue original start reference', () => {
                    unknown.value = false; selectedId.value = null; emit('navigate', '#cash-controls'); load(false);
                }, pending.value || !online.value),
            ]),
            confirmation.render(),
        ]);
    },
};
