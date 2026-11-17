import { apiRequest } from '../api/http-client.js';
import { field, action, submit, feedback, message } from './commercial-ui.js';
import { selectField, useTradingConfirm } from './trading-ui.js';
import { UiLoading, UiInformationPanel } from './ui/index.js';
import { useNavigationGuard } from '../shared/workspace-navigation.js';

const { h, ref, computed, onMounted, onUnmounted, watch } = window.Vue;
const validId = id => typeof id === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(id);
const cash = value => {
    const text = String(value ?? '').trim();
    if (!/^(?:0|[1-9][0-9]{0,16})(?:\.[0-9]{1,2})?$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    return `${whole}.${fraction.padEnd(2, '0')}`;
};
const isRecoveredAbort = (record, marker) => record?.status === 'ABORTED' && marker?.action === 'abort' && record.public_id === marker.control_public_id;
const utc = value => value ? new Date(value).toISOString().replace('T', ' ').replace('Z', '000') : null;
const labels = { COUNTING: 'Count now', COUNTS_COMMITTED: 'Count confirmed', REVIEW: 'Compare the numbers', FINALIZED: 'Finished', ABORTED: 'Cancelled' };
const roles = { ON_DUTY: 'Worked in the shop', COUNTER: 'Counted', REVIEWER: 'Checked the numbers', FINALIZER: 'Finished the check' };
const noteLabels = { EXPLANATION: 'Reason for the difference', INVESTIGATION_FINDING: 'What we found out', REVIEW_NOTE: 'Other note' };
const costLabels = { VALID: 'Buying price is recorded', RETAINED: 'Using the last known buying price', UNESTABLISHED: 'Buying price is not yet known' };
const sourceLabels = { stock_movement: 'Stock record', sale: 'Sale or return', debt_payment: 'Customer payment', financial_movement: 'Money record' };
const when = value => {
    if (!value) return 'Not recorded';
    const date = new Date(String(value).replace(' ', 'T') + 'Z');
    return Number.isNaN(date.getTime()) ? 'Time not available' : new Intl.DateTimeFormat('en-NG', { timeZone: 'Africa/Lagos', day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }).format(date) + ' (Nigeria time)';
};
const differenceText = (value, money = false) => {
    if (/^-?0(?:\.0+)?$/.test(value)) return 'Your count matches the shop records.';
    const amount = String(value).replace(/^-/, '');
    return `${money ? 'Cash counted is ₦' : 'Your count is '}${amount} ${String(value).startsWith('-') ? 'less' : 'more'} than the shop records.`;
};
const componentLabels = { opening_baseline_amount: 'Cash counted at the last check', cash_sale_receipts: 'Cash received from sales', cash_sale_refunds: 'Cash paid back for returned items',
    cash_debt_receipts: 'Cash received from customers who owed money', cash_introductions: 'Cash the owner added', cash_expenses: 'Cash spent on shop costs', cash_withdrawals: 'Cash the owner took out', cash_corrections_net: 'Other cash changes' };

export const CashControlsPanel = {
    props: { controlScope: { type: String, default: 'CASH' }, permissions: { type: Array, default: () => [] }, isOwner: Boolean, readOnly: Boolean,
        recoveryScope: { type: String, required: true }, hash: { type: String, default: '#cash-controls' } },
    emits: ['navigate'],
    setup(props, { emit }) {
        const confirmation = useTradingConfirm();
        const scope = props.controlScope, hasStock = scope !== 'CASH', hasCash = scope !== 'STOCK';
        const slug = `${scope.toLowerCase()}-controls`, rootHash = `#${slug}`, root = `/api/v1/shop/${slug}`;
        const title = scope === 'FULL' ? 'Cash and stock' : scope === 'STOCK' ? 'Stock' : 'Cash';
        const stockRows = ref([]), stockPage = ref(1), stockPages = ref(0), stockSearch = ref(''), stockDrafts = ref({});
        const lateRows = ref([]), latePage = ref(1), latePages = ref(0), noteVariance = ref('');
        const adjustment = ref(null), adjustmentPreview = ref(null), adjustmentReason = ref('');
        const stockDirty = computed(() => Object.keys(stockDrafts.value).length > 0);
        const quantity = value => /^(?:0|[1-9][0-9]{0,14})(?:\.[0-9]{1,4})?$/.test(String(value));
        const records = ref([]), control = ref(null), devices = ref([]), notes = ref([]), staff = ref([]);
        const listPage = ref(1), listPages = ref(0), devicePage = ref(1), devicePages = ref(0), notePage = ref(1), notePages = ref(0), staffPage = ref(1), staffPages = ref(0);
        const loaded = ref(false), loading = ref(true), pending = ref(false), online = ref(navigator.onLine), unknown = ref(false), storageWarning = ref(false);
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
        const stepHelp = status => {
            if (props.readOnly) return 'You can look at this saved check, but you cannot change it.';
            if (control.value?.generation_changed && ['COUNTING', 'COUNTS_COMMITTED', 'REVIEW'].includes(status)) return has('abort') ? 'This check cannot continue after DawaTrack changed. Give a reason and cancel it, then start again.' : 'This check cannot continue after DawaTrack changed. Ask the shop owner to cancel it and start again.';
            if (status === 'COUNTING') return has('count')
                ? `Enter what you counted and save it. ${has('commit') ? 'Check each saved number before you confirm the count.' : 'Someone allowed to confirm the count must continue from here.'}`
                : 'Counting is still in progress. You can look at the check, but you are not allowed to enter a count. Ask the person counting to save and confirm it.';
            if (status === 'COUNTS_COMMITTED') return has('review')
                ? 'The count cannot be changed now. Tap Compare the numbers to check it against the shop records.'
                : 'The count has been confirmed. Someone allowed to check the counts will compare the numbers next.';
            if (status === 'REVIEW') return has('review')
                ? `Compare your count with the shop records. Save a reason for each difference. ${has('finalize') ? 'Then finish the check.' : 'Someone allowed to finish the check will finish it.'}`
                : 'The counts are being checked. You can look at the saved details you are allowed to see.';
            if (status === 'FINALIZED') return `This check is finished. The counts cannot be changed. ${has('review') && has('note') ? 'You can add a note if you find out more later.' : 'Ask the shop owner if you find out more later.'}`;
            if (status === 'ABORTED') return 'This check was cancelled. Counts you already saved were kept, but they will not be used as a finished count.';
            return 'Open this check to see what was saved.';
        };
        const blocked = computed(() => pending.value || loading.value || !loaded.value || props.readOnly || !online.value || unknown.value);
        const progressBlocked = computed(() => blocked.value || !!control.value?.generation_changed);
        const key = `dt:pending-${scope === 'CASH' ? 'cash-control' : slug}:${location.host}:${props.recoveryScope}`;
        let revision = 0, alive = true;
        const dirty = computed(() => stockDirty.value || adjustmentReason.value.trim() || noteText.value.trim() || reason.value.trim() || Object.keys(duty.value).length
            || (control.value?.status === 'COUNTING' && amount.value !== '' && cash(amount.value) !== control.value?.count?.actual_amount)
            || (control.value?.status === 'REVIEW' && explanation.value !== (control.value?.snapshot?.explanation || '')));
        useNavigationGuard(() => unknown.value ? { hard: true, message: `Check what was saved before leaving. Keep the same check number.` }
            : dirty.value ? { message: `You have changes that are not saved. Save them before leaving.` } : null);

        function remember(actionName, id) {
            const value = { control_public_id: id, action: actionName };
            if (actionName === 'note') { value.note_public_id = noteId.value; if (hasStock) value.variance_public_id = noteVariance.value; }
            if (actionName === 'adjust') { value.movement_public_id = adjustment.value.movement_public_id; value.variance_public_id = adjustment.value.variance_public_id; }
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
                const actions = ['start', 'count', 'commit', 'review', 'explain', 'finalize', 'abort', 'acknowledge', 'exclude', 'note', 'adjust'];
                if (!validId(value.control_public_id) || !actions.includes(value.action) || (value.action === 'note' && (!validId(value.note_public_id) || (hasStock && !validId(value.variance_public_id)))) || (value.action === 'adjust' && (!hasStock || !validId(value.movement_public_id) || !validId(value.variance_public_id)))) {
                    storageWarning.value = true;
                    error.value = 'We could not read the saved check number. Keep this page open and ask the shop owner for help. Do not clear the browser data.';
                    unknown.value = true;
                    return;
                }
                savedMarker.value = value;
                selectedId.value = value.control_public_id;
                if (value.action === 'start') startId.value = value.control_public_id;
                if (value.note_public_id) noteId.value = value.note_public_id;
                if (value.variance_public_id) noteVariance.value = value.variance_public_id;
                if (value.action === 'adjust') adjustment.value = { movement_public_id: value.movement_public_id, variance_public_id: value.variance_public_id };
                unknown.value = true;
            } catch { storageWarning.value = true; }
        }
        function choose(id) {
            if (pending.value || unknown.value) return;
            devicePage.value = notePage.value = staffPage.value = 1;
            // The parent navigation guard approves the route; its hash watcher loads the next record.
            emit('navigate', id ? `${rootHash}/${id}` : rootHash);
        }
        async function load(preserve = true, savedDetail = null) {
            const request = ++revision;
            const id = selectedId.value;
            loading.value = true;
            loaded.value = false;
            error.value = '';
            control.value = null;
            devices.value = notes.value = staff.value = records.value = stockRows.value = lateRows.value = [];
            sourceResult.value = null;
            try {
                if (!id) {
                    const result = await apiRequest(`${root}?page=${listPage.value}&per_page=20`);
                    if (!alive || request !== revision) return;
                    records.value = result.data.controls;
                    listPages.value = result.meta.pagination.total_pages;
                } else {
                    // A successful command already returns the current scoped detail.
                    // Reuse it once; explicit status checks always make a fresh GET.
                    const reusable = savedDetail?.public_id === id && savedDetail?.scope === scope
                        && labels[savedDetail?.status] && savedDetail?.readiness
                        && typeof savedDetail?.can_review_values === 'boolean'
                        && (!hasStock || savedDetail.stock_progress);
                    const result = reusable ? { data: savedDetail } : await apiRequest(`${root}/${id}`);
                    if (!alive || request !== revision) return;
                    const value = result.data;
                    const requests = [apiRequest(`${root}/${id}/readiness?page=${devicePage.value}&per_page=20`)];
                    if (value.can_review_values) requests.push(apiRequest(`${root}/${id}/staff?page=${staffPage.value}&per_page=20`));
                    if (value.status === 'FINALIZED' && value.can_review_values) requests.push(apiRequest(`${root}/${id}/notes?page=${notePage.value}&per_page=20`));
                    if (hasStock) requests.push(apiRequest(`${root}/${id}/counts?search=${encodeURIComponent(stockSearch.value)}&page=${stockPage.value}&per_page=20`));
                    if (value.can_review_values) requests.push(apiRequest(`${root}/${id}/late-operations?page=${latePage.value}&per_page=20`));
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
                        notePages.value = extras[index++].meta.pagination.total_pages;
                    }
                    if (hasStock) {
                        stockRows.value = extras[index].data.counts;
                        stockPages.value = extras[index++].meta.pagination.total_pages;
                    }
                    if (value.can_review_values) {
                        lateRows.value = extras[index].data.associations;
                        latePages.value = extras[index].meta.pagination.total_pages;
                    }
                    if (!preserve) {
                        amount.value = value.count?.actual_amount ?? '';
                        explanation.value = value.snapshot?.explanation ?? '';
                    }
                    if (unknown.value) notice.value = 'Here is what was saved. Keep the same check or note number if you need to try again.';
                }
                if (isRecoveredAbort(control.value, savedMarker.value)) { stockDrafts.value = {}; reason.value = ''; }
                loaded.value = true;
                if (unknown.value && savedMarker.value?.action === 'adjust') {
                    const status = (await apiRequest(`${root}/${id}/adjustment-status?public_id=${savedMarker.value.movement_public_id}`)).data;
                    if (status.found && status.movement.variance_public_id === savedMarker.value.variance_public_id) {
                        unknown.value = false; clearMarker(); adjustment.value = adjustmentPreview.value = null; adjustmentReason.value = '';
                        notice.value = 'The stock change was saved. Check that record before doing anything else.';
                    }
                } else if (unknown.value && savedMarker.value?.action !== 'note') {
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
                    devices.value = notes.value = staff.value = records.value = stockRows.value = lateRows.value = [];
                }
            } finally { if (alive && request === revision) loading.value = false; }
        }
        async function command(actionName, body = {}, allowRetry = false) {
            if (pending.value || loading.value || !loaded.value || props.readOnly || !online.value || (unknown.value && !allowRetry)) return;
            if (control.value?.generation_changed && !['start', 'abort', 'note', 'adjust'].includes(actionName)) return;
            const id = actionName === 'start' ? startId.value : selectedId.value;
            if (!validId(id)) return;
            pending.value = true;
            error.value = '';
            notice.value = '';
            const hadUnknownSave = unknown.value || savedMarker.value !== null;
            remember(actionName, id);
            try {
                const path = actionName === 'start' ? root : `${root}/${id}/${actionName}`;
                const response = await apiRequest(path, { method: 'POST', body: JSON.stringify(actionName === 'start' ? { public_id: id, ...body } : body) });
                unknown.value = false;
                clearMarker();
                if (actionName === 'note') { noteId.value = crypto.randomUUID(); noteText.value = ''; }
                if (actionName === 'adjust') { adjustment.value = adjustmentPreview.value = null; adjustmentReason.value = ''; }
                if (actionName === 'abort') stockDrafts.value = {};
                if (actionName === 'start') { duty.value = {}; startId.value = crypto.randomUUID(); }
                reason.value = '';
                notice.value = 'Saved. The page now shows your saved work.';
                selectedId.value = id;
                emit('navigate', `${rootHash}/${id}`);
                await load(hasStock && ['count', 'explain'].includes(actionName) && !!body.product_public_id, actionName === 'adjust' ? null : response.data);
            } catch (e) {
                error.value = message(e);
                // Start checks this setting before any new control row is written.
                // Never dismiss uncertainty retained from an earlier request.
                if (actionName === 'start' && e.status === 503 && e.code === 'READINESS_SETUP_REQUIRED' && !hadUnknownSave) {
                    clearMarker();
                } else if (!e.status || e.status >= 500 || [401, 403, 404].includes(e.status) || ['CASH_CONTROL_IDENTITY_CONFLICT', 'STOCK_ADJUSTMENT_IDENTITY_CONFLICT'].includes(e.code)) {
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
                if (!await confirmation.ask({ title: `Start this ${title.toLowerCase()} check?`, message: `${scope === 'CASH' ? 'While you count, no one can record cash coming in or going out.' : scope === 'STOCK' ? 'While you count, no one can record sales, returns or other stock changes.' : 'While you count, no one can record sales, returns, stock changes or cash coming in or going out.'} Count first. You will see the numbers in the shop records after you confirm your count. Finish or cancel this check to allow those records again.`, confirm: `Start ${title.toLowerCase()} check` })) return;
                await command('start', { on_duty: onDuty });
            } catch (e) { error.value = message(e); }
        }
        async function confirmCommand(name, body, title, text) {
            if (blocked.value || (name !== 'abort' && control.value?.generation_changed)) return;
            if (await confirmation.ask({ title, message: text, confirm: 'Confirm', danger: name === 'abort' })) await command(name, body);
        }
        async function acknowledge() {
            if (progressBlocked.value) return;
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
        function stockDraft(row, name, value) {
            const current = stockDrafts.value[row.product_public_id] || { revision: row.revision, actual_quantity: row.actual_quantity ?? '', explanation: row.explanation || '' };
            stockDrafts.value = { ...stockDrafts.value, [row.product_public_id]: { ...current, [name]: value } };
        }
        async function saveStock(row, explain = false) {
            const draft = stockDrafts.value[row.product_public_id];
            if (!draft || progressBlocked.value) return;
            const before = draft;
            await command(explain ? 'explain' : 'count', { product_public_id: row.product_public_id,
                [explain ? 'explanation' : 'actual_quantity']: draft[explain ? 'explanation' : 'actual_quantity'], expected_updated_at: draft.revision });
            if (!error.value && !unknown.value && stockDrafts.value[row.product_public_id] === before) {
                const copy = { ...stockDrafts.value }; delete copy[row.product_public_id]; stockDrafts.value = copy;
            }
        }
        async function previewAdjustment() {
            if (pending.value || loading.value || props.readOnly || !online.value || !adjustment.value || (unknown.value && savedMarker.value?.action !== 'adjust')) return;
            pending.value = true; error.value = ''; adjustmentPreview.value = null;
            try {
                const result = (await apiRequest(`${root}/${selectedId.value}/adjustment-preview`, { method: 'POST', body: JSON.stringify({ ...adjustment.value, reason: adjustmentReason.value }) })).data;
                adjustmentPreview.value = result;
                if (result.already_saved) { notice.value = 'This stock change is already saved.'; unknown.value = false; clearMarker(); adjustment.value = null; adjustmentReason.value = ''; await load(true); }
            } catch (e) { error.value = message(e); }
            finally { pending.value = false; }
        }
        async function postAdjustment() {
            if (!adjustmentPreview.value || pending.value || props.readOnly || !online.value) return;
            if (await confirmation.ask({ title: 'Save this stock change?', message: `Stock in the records will change from ${adjustmentPreview.value.current_quantity} to ${adjustmentPreview.value.quantity_after}. Sales and stock changes made after the count are kept. Your earlier count will not change.`, confirm: 'Save stock change' })) {
                await command('adjust', { ...adjustment.value, reason: adjustmentReason.value, review_fingerprint: adjustmentPreview.value.review_fingerprint }, unknown.value && savedMarker.value?.action === 'adjust');
                adjustmentPreview.value = null;
            }
        }
        function commitBody() { return hasStock ? { expected_commit_revision: control.value.commit_revision } : { expected_updated_at: control.value.count.revision }; }
        function predecessor(id, predecessorScope) { emit('navigate', `#${predecessorScope.toLowerCase()}-controls/${id}`); }
        const connection = () => {
            online.value = navigator.onLine;
            if (!online.value) { loaded.value = false; revision++; control.value = null; devices.value = notes.value = staff.value = records.value = stockRows.value = lateRows.value = []; loading.value = false; }
        };
        watch(() => props.hash, value => {
            if (pending.value || unknown.value) return;
            const id = value.split('/')[1] || null;
            if (id === selectedId.value) return;
            stockDrafts.value = {}; stockPage.value = latePage.value = 1; stockSearch.value = ''; adjustment.value = adjustmentPreview.value = null; adjustmentReason.value = '';
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
            h('h1', { id: 'cash-control-title' }, `${title} check`),
            h('p', `${scope === 'CASH' ? 'Count the cash in your shop.' : scope === 'STOCK' ? 'Count each item in your shop.' : 'Count the cash and each item in your shop.'} Save your count, then confirm it. Compare it with the shop records and add a reason if the numbers differ. A difference does not tell us who caused it.`),
            ...feedback(error.value, notice.value),
            props.readOnly && h(UiInformationPanel, { title: 'You can only look at this check', message: 'You can look at saved checks, but you cannot change them.' }),
            !online.value && h(UiInformationPanel, { title: 'Connect to continue', message: `Connect to the internet to use this page. Anything already saved is safe. New entries cannot be saved while you are offline.` }),
            storageWarning.value && h(UiInformationPanel, { title: 'Keep this check number', message: `Keep this page open and copy this number: ${savedMarker.value?.control_public_id || selectedId.value || startId.value}. We could not keep it in this browser. Ask for help before clearing browser data.` }),
            unknown.value && h(UiInformationPanel, { title: 'Check what was saved first', message: `We do not yet know if your last action was saved. Tap “Check what was saved”. Keep this number: ${savedMarker.value?.control_public_id || selectedId.value || startId.value}. We will not send it again unless you choose to.` }),
            action('Check what was saved', () => load(true), pending.value || loading.value || !online.value),
            loading.value ? h(UiLoading, { label: `Loading ${title} check` }) : !selectedId.value ? h('div', [
                !has('start') && h('p', 'You can open past checks below. Ask the shop owner if a new check is needed.'),
                has('start') && h('form', { class: 'commercial-form', onSubmit: start }, [
                    h('h2', `Start a ${title.toLowerCase()} check`),
                    h('p', 'You can add who worked in the shop. Leave any time you do not know empty. If the person is still working, leave “Finished work” empty.'),
                    field('cash-members-search', 'Who worked in the shop?', search.value, v => { search.value = v; }, { type: 'search', disabled: blocked.value }),
                    action('Find staff', memberSearch, blocked.value),
                    ...members.value.map(member => h('div', { key: member.public_id, class: 'ui-card' }, [
                        h('label', [h('input', { type: 'checkbox', checked: !!duty.value[member.public_id], disabled: blocked.value,
                            onChange: event => { if (event.target.checked) updateDuty(member, 'notes', ''); else { const copy = { ...duty.value }; delete copy[member.public_id]; duty.value = copy; } } }), ` ${member.display_name}`]),
                        duty.value[member.public_id] && h('div', [
                            field(`duty-start-${member.public_id}`, 'Started work (leave empty if not known)', duty.value[member.public_id].starts_at || '', v => updateDuty(member, 'starts_at', v), { type: 'datetime-local', disabled: blocked.value }),
                            field(`duty-end-${member.public_id}`, 'Finished work (leave empty if still working)', duty.value[member.public_id].ends_at || '', v => updateDuty(member, 'ends_at', v), { type: 'datetime-local', disabled: blocked.value }),
                            field(`duty-note-${member.public_id}`, 'Note about their work (you can leave this empty)', duty.value[member.public_id].notes || '', v => updateDuty(member, 'notes', v), { maxlength: 500, disabled: blocked.value }),
                        ]),
                    ])),
                    memberPages.value > 1 && pages('Staff pages', memberPage, memberPages, memberSearch),
                    h('p', `${Object.keys(duty.value).length} people added.`),
                    submit(`Start ${title.toLowerCase()} check`, blocked.value, { loading: pending.value }),
                ]),
                h('h2', `Past ${title.toLowerCase()} checks`),
                !error.value && !records.value.length && h('p', `No ${title.toLowerCase()} checks on this page.`),
                ...records.value.map(row => h('article', { key: row.public_id, class: 'ui-card' }, [
                    h('h3', labels[row.status] || 'Check saved'), h('p', `Started: ${when(row.cutoff_at)}`),
                    action(`Open this check`, () => choose(row.public_id), pending.value || unknown.value),
                ])),
                pages(`${title} check pages`, listPage, listPages, () => load(false)),
            ]) : control.value ? h('div', [
                action(`Back to ${title} checks`, () => choose(null), pending.value || unknown.value),
                h('h2', labels[control.value.status] || 'Check saved'),
                h('p', stepHelp(control.value.status)),
                h('p', `Check number: ${control.value.public_id}`),
                hasCash && h('p', `Cash records from ${when(control.value.cash_period_starts_at)} to ${when(control.value.cutoff_at)}`),
                hasStock && h('p', `Stock records from ${when(control.value.stock_period_starts_at)} to ${when(control.value.cutoff_at)}`),
                control.value.generation_changed && h(UiInformationPanel, { title: 'This check must be started again', message: 'DawaTrack has changed since this check started. Ask the shop owner to cancel this check and start again. Your saved work will be kept.' }),
                h('h3', 'Check shop phones and computers'),
                h('p', control.value.readiness.required === 0 ? 'There were no shop phones or computers on the list when this check started.' : 'Open this check on each shop phone or computer listed below and tap the ready button. If one is not available, the owner can give a reason to skip it. All must be ready or skipped before you confirm the count. This does not send any unsaved sales from a phone.'),
                h('p', `${control.value.readiness.resolved} of ${control.value.readiness.required} shop phones or computers checked.`),
                control.value.status !== 'FINALIZED' && control.value.status !== 'ABORTED' && control.value.current_device_public_id && control.value.current_device_required
                    && action('This phone or computer is ready', acknowledge, blocked.value || control.value.generation_changed),
                ...devices.value.map(device => h('article', { key: device.public_id, class: 'ui-card' }, [
                    h('h4', device.display_name), h('p', `${device.decision === 'ACKNOWLEDGED' ? 'Ready' : device.decision === 'EXCLUDED' ? 'Skipped with a reason' : 'Waiting'} — ${({ ACTIVE: 'in use', REVOKED: 'access removed', PENDING: 'not ready yet' })[device.device_status] || 'ask the owner to check'}`),
                    device.reason && h('p', `Reason: ${device.reason}`),
                    device.decided_by && h('p', `Checked by ${device.decided_by}`),
                    props.isOwner && has('exclude_device') && !device.decision && ['COUNTING', 'COUNTS_COMMITTED', 'REVIEW'].includes(control.value.status)
                        && action('Skip this phone or computer', () => confirmCommand('exclude', { device_public_id: device.public_id, reason: reason.value }, 'Skip this phone or computer?', 'This phone or computer has not been checked. Some shop records may still be on it. The reason you give for skipping it will be saved.'), progressBlocked.value || !reason.value.trim()),
                ])),
                pages('Phone and computer pages', devicePage, devicePages, () => load(true)),
                control.value.status === 'COUNTING' && h('div', [
                    h('p', 'Count what is in the shop first. Save each count, then confirm it. After that, a person allowed to check the numbers can compare them with the shop records.'),
                    has('count') && control.value.count && h('form', { class: 'commercial-form', onSubmit: event => {
                        event.preventDefault(); command('count', { amount: amount.value, expected_updated_at: control.value.count.revision });
                    } }, [
                        field('cash-actual', 'Cash you counted', amount.value, v => { amount.value = v; }, { inputmode: 'decimal', required: true, disabled: progressBlocked.value }),
                        h('p', `Saved cash count: ${control.value.count.actual_amount === null ? 'Not counted yet' : `₦${control.value.count.actual_amount}`}`),
                        submit('Save cash count', progressBlocked.value, { loading: pending.value }),
                        has('commit') && action(hasStock ? 'Confirm cash and item counts' : 'Confirm this count', () => confirmCommand('commit', commitBody(), 'Keep these counts?', 'You cannot change these counts after this. Next, a person allowed to check them can compare them with the shop records.'), progressBlocked.value || !control.value.readiness.complete || control.value.count.actual_amount === null || cash(amount.value) !== control.value.count.actual_amount || (hasStock && (!control.value.stock_progress.all_counted || stockDirty.value))),
                    ]),
                ]),
                control.value.status === 'COUNTS_COMMITTED' && has('review') && action('Compare the numbers', () => command('review'), blocked.value || control.value.generation_changed),
                control.value.can_review_values && control.value.snapshot && h('section', { 'aria-label': 'Compare cash counts' }, [
                    h('h3', 'Compare your count with the shop records'),
                    control.value.previous_cash_kind === 'STANDARD' && validId(control.value.previous_cash_public_id) && action('Open the last cash check', () => predecessor(control.value.previous_cash_public_id, control.value.previous_cash_scope || 'CASH'), pending.value || unknown.value),
                    props.isOwner && control.value.previous_cash_kind === 'OPENING' && action('Open starting cash', () => emit('navigate', '#opening-cash'), pending.value || unknown.value),
                    props.isOwner && action('See shop activity', () => emit('navigate', '#audit'), pending.value || unknown.value),
                    h('dl', { class: 'trading-facts' }, Object.entries(componentLabels).map(([key, label]) => h('div', [h('dt', label), h('dd', `₦${control.value.snapshot[key]}`)]))),
                    h('p', `Cash in the shop records: ₦${control.value.snapshot.expected_amount}`), h('p', `Cash you counted: ₦${control.value.snapshot.actual_amount}`),
                    h('p', differenceText(control.value.snapshot.variance_amount, true)),
                    control.value.snapshot.explanation && h('p', `Saved reason: ${control.value.snapshot.explanation}`),
                    control.value.status === 'REVIEW' && h('form', { class: 'commercial-form', onSubmit: event => {
                        event.preventDefault(); command('explain', { explanation: explanation.value, expected_updated_at: control.value.count.revision });
                    } }, [field('cash-explanation', 'Why are the numbers different?', explanation.value, v => { explanation.value = v; }, { multiline: true, maxlength: 500, disabled: progressBlocked.value, required: true }), submit('Save reason', progressBlocked.value, { loading: pending.value })]),
                    !hasStock && control.value.status === 'REVIEW' && has('finalize') && action('Finish cash check', () => confirmCommand('finalize', {}, 'Finish this cash check?', 'The count and reason cannot be changed after this. The next cash check will start from the cash you counted. This will not add or remove any money.'), blocked.value || !control.value.readiness.complete || control.value.generation_changed || explanation.value !== (control.value.snapshot.explanation || '')),
                    h('h3', 'Who took part in this check?'),
                    ...staff.value.map(row => h('p', { key: `${row.membership_public_id}:${row.context_role}` }, `${row.display_name}: ${roles[row.context_role] || 'Took part'}${row.context_starts_at ? `; from ${when(row.context_starts_at)}` : ''}${row.context_ends_at ? `; to ${when(row.context_ends_at)}` : ''}${row.notes ? `; ${row.notes}` : ''}`)),
                    pages('People pages', staffPage, staffPages, () => load(true)),
                ]),
                ['COUNTING', 'COUNTS_COMMITTED', 'REVIEW'].includes(control.value.status) && (has('abort') || (props.isOwner && has('exclude_device'))) && h('div', [
                    field('cash-control-reason', 'Why are you skipping a phone or computer, or cancelling this check?', reason.value, v => { reason.value = v; }, { multiline: true, maxlength: 500, disabled: blocked.value }),
                    has('abort') && action(`Cancel ${title.toLowerCase()} check`, () => confirmCommand('abort', { reason: reason.value }, `Cancel this ${title.toLowerCase()} check?`, 'Counts you already saved will be kept. Unsaved entries will not be used. This check will not be used for the next check. You can start again.'), blocked.value || !reason.value.trim()),
                ]),
                control.value.status === 'ABORTED' && h('p', control.value.abort_reason ? `Reason for cancelling: ${control.value.abort_reason}` : 'Ask the shop owner for the reason this check was cancelled.'),
                control.value.status === 'FINALIZED' && control.value.can_review_values && h('div', [
                    h('h3', 'Notes added after the check'),
                    ...notes.value.map(row => h('article', { key: row.public_id, class: 'ui-card' }, [h('h4', noteLabels[row.note_type] || 'Note'), h('p', row.note_text), row.variance_public_id && h('p', `Difference number: ${row.variance_public_id}`), h('p', `${row.author_name} — ${when(row.created_at)}`), h('small', row.public_id)])),
                    pages('Note pages', notePage, notePages, () => load(true)),
                    has('note') && (hasStock ? validId(noteVariance.value) : control.value.variance_public_id) && h('form', { class: 'commercial-form', onSubmit: event => {
                        event.preventDefault(); command('note', { public_id: noteId.value, note_type: noteType.value, note_text: noteText.value, ...(hasStock ? { variance_public_id: noteVariance.value } : {}) }, unknown.value && savedMarker.value?.action === 'note');
                    } }, [
                        selectField('cash-note-type', 'What is this note about?', noteType.value, v => { noteType.value = v; }, [['EXPLANATION', 'Reason for the difference'], ['INVESTIGATION_FINDING', 'What we found out'], ['REVIEW_NOTE', 'Other note']], { disabled: pending.value || props.readOnly || !online.value }),
                        field('cash-note', 'Add a note', noteText.value, v => { noteText.value = v; }, { multiline: true, maxlength: 2000, required: true, disabled: pending.value || props.readOnly || !online.value }),
                        submit(unknown.value ? 'Try saving this same note again' : 'Save note', pending.value || props.readOnly || !online.value || (unknown.value && savedMarker.value?.action !== 'note'), { loading: pending.value }),
                    ]),
                ]),
                hasStock && h('section', { 'aria-label': 'Item counts' }, [
                    h('h3', 'Items to count'),
                    h('p', `${control.value.stock_progress.counted} of ${control.value.stock_progress.total} items counted. Enter 0 and save it if you have none of an item.`),
                    control.value.previous_stock_public_id && action('Open the last stock check', () => predecessor(control.value.previous_stock_public_id, control.value.previous_stock_scope), pending.value || unknown.value),
                    h('form', { onSubmit: event => { event.preventDefault(); stockPage.value = 1; load(true); } }, [field('stock-control-search', 'Find an item', stockSearch.value, v => { stockSearch.value = v; }, { type: 'search', maxlength: 100, disabled: pending.value }), submit('Find item', pending.value || !online.value)]),
                    ...stockRows.value.map(row => {
                        const draft = stockDrafts.value[row.product_public_id];
                        return h('article', { key: row.product_public_id, class: 'ui-card' }, [
                            h('h4', `${row.name} (${row.unit_name})`), h('small', row.product_public_id),
                            row.actual_quantity !== undefined && h('p', `Saved item count: ${row.actual_quantity === null ? 'Not counted' : row.actual_quantity}`),
                            control.value.status === 'COUNTING' && has('count') && row.revision !== undefined && h('form', { onSubmit: event => { event.preventDefault(); saveStock(row); } }, [
                                field(`stock-actual-${row.product_public_id}`, 'How many did you count?', draft?.actual_quantity ?? row.actual_quantity ?? '', v => stockDraft(row, 'actual_quantity', v), { inputmode: 'decimal', required: true, disabled: progressBlocked.value }),
                                submit('Save item count', progressBlocked.value || !draft || !quantity(draft.actual_quantity)),
                            ]),
                            row.expected_quantity !== undefined && h('div', [h('p', `Items in the shop records: ${row.expected_quantity}. ${row.cost_basis_state === "UNESTABLISHED" ? "Buying price is not yet known." : `Buying price used per item: ₦${row.expected_average_unit_cost}. ${costLabels[row.cost_basis_state] || "Buying price needs checking"}.`}`),
                                h('p', differenceText(row.variance_quantity)),
                                row.explanation && h('p', `Saved reason: ${row.explanation}`),
                                control.value.status === 'REVIEW' && has('review') && h('form', { onSubmit: event => { event.preventDefault(); saveStock(row, true); } }, [field(`stock-explain-${row.product_public_id}`, 'Why is the item count different?', draft?.explanation ?? row.explanation ?? '', v => stockDraft(row, 'explanation', v), { multiline: true, maxlength: 500, required: true, disabled: progressBlocked.value }), submit('Save reason', progressBlocked.value || !draft)]),
                            ]),
                            draft && action('Drop my changes and use the saved count', () => { const copy = { ...stockDrafts.value }; delete copy[row.product_public_id]; stockDrafts.value = copy; }, blocked.value),
                            row.variance_public_id && h('p', `Difference number: ${row.variance_public_id}`),
                            row.variance_public_id && has('note') && action('Add a note about this difference', () => { noteVariance.value = row.variance_public_id; }, blocked.value),
                            row.adjustment && h('p', `Stock change saved: ${when(row.adjustment.posted_at)}. Number: ${row.adjustment.public_id}`),
                            row.variance_public_id && !row.adjustment && control.value.stock_adjustment_eligible && has('adjust') && action('Change the stock record', () => { adjustment.value = { movement_public_id: crypto.randomUUID(), variance_public_id: row.variance_public_id }; adjustmentReason.value = ''; adjustmentPreview.value = null; }, blocked.value || !!adjustment.value),
                        ]);
                    }),
                    pages('Item count pages', stockPage, stockPages, () => load(true)),
                    !hasCash && control.value.status === 'COUNTING' && has('count') && has('commit') && action('Confirm all item counts', () => confirmCommand('commit', commitBody(), 'Keep all these item counts?', 'You cannot change these counts after this. Next, a person allowed to check them can compare them with the shop records.'), progressBlocked.value || !control.value.stock_progress.all_counted || !control.value.readiness.complete || stockDirty.value),
                    control.value.status === 'REVIEW' && has('finalize') && action(`Finish ${title.toLowerCase()} check`, () => confirmCommand('finalize', {}, `Finish this ${title.toLowerCase()} check?`, 'Add a reason for each difference first. After this, the count and reasons cannot be changed. Stock stays as it is until you choose to change the stock record. This will not add or remove any money.'), blocked.value || !control.value.readiness.complete || control.value.generation_changed || stockDirty.value || (hasCash && explanation.value !== (control.value.snapshot?.explanation || ''))),
                    control.value.can_review_values && !hasCash && h('div', [h('h3', 'Who took part in this check?'), ...staff.value.map(row => h('p', { key: `${row.membership_public_id}:${row.context_role}` }, `${row.display_name}: ${roles[row.context_role] || 'Took part'}${row.context_starts_at ? `; from ${when(row.context_starts_at)}` : ''}${row.context_ends_at ? `; to ${when(row.context_ends_at)}` : ''}${row.notes ? `; ${row.notes}` : ''}`)), pages('People pages', staffPage, staffPages, () => load(true))]),
                    control.value.variance_public_id && has('note') && action('Add a note about the cash difference', () => { noteVariance.value = control.value.variance_public_id; }, blocked.value),
                    noteVariance.value && h('p', `This note is for difference number: ${noteVariance.value}`),
                ]),
                adjustment.value && h('section', { 'aria-label': 'Change the stock record' }, [
                    h('h3', 'Review one Change the stock record'),
                    h('p', `Stock change number: ${adjustment.value.movement_public_id}; difference number: ${adjustment.value.variance_public_id}`),
                    field('stock-adjust-reason', 'Why are you changing the stock record?', adjustmentReason.value, v => { adjustmentReason.value = v; adjustmentPreview.value = null; }, { multiline: true, required: true, maxlength: 500, disabled: pending.value || props.readOnly || !online.value }),
                    action('See the stock change first', previewAdjustment, pending.value || loading.value || props.readOnly || !online.value || !adjustmentReason.value.trim()),
                    adjustmentPreview.value && h('div', [h('p', `When you counted, the records showed ${adjustmentPreview.value.frozen_expected_quantity} items. You counted ${adjustmentPreview.value.frozen_actual_quantity}. The stock record now shows ${adjustmentPreview.value.current_quantity}. It will change to ${adjustmentPreview.value.quantity_after}. ${adjustmentPreview.value.cost_basis_state === "UNESTABLISHED" ? "Buying price is not yet known." : `Buying price used per item stays ₦${adjustmentPreview.value.average_unit_cost}. ${costLabels[adjustmentPreview.value.cost_basis_state] || "Buying price needs checking"}.`}`), action('Save this stock change', postAdjustment, pending.value || props.readOnly || !online.value)]),
                    !unknown.value && action('Leave the stock record as it is', () => { adjustment.value = adjustmentPreview.value = null; adjustmentReason.value = ''; }, pending.value),
                ]),
                control.value.can_review_values && h('section', { 'aria-label': 'Shop records added later' }, [h('h3', 'Shop records added after this check started'), h('p', 'These records were saved after the check started, but happened earlier. Your earlier count stays the same. No money is added or removed to cover a difference.'),
                    !lateRows.value.length && h('p', 'No records added later on this page.'),
                    ...lateRows.value.map(row => h('article', { key: row.public_id, class: 'ui-card' }, [h('p', `${sourceLabels[row.source_resource_type] || "Shop record"}: ${row.source_public_id}`), h('p', `Happened: ${when(row.occurred_at)}. Saved: ${when(row.posted_at)}. Check started: ${when(row.cutoff_at)}.`)])), pages('Later record pages', latePage, latePages, () => load(true))]),
                sourceChoices.value.length > 0 && h('form', { class: 'commercial-form', onSubmit: sourceStatus }, [
                    h('h3', 'Find a saved sale or payment'),
                    h('p', 'Enter the number from the sale or payment you tried to save. This shows whether that record was saved. It does not check any changes you made to it later.'),
                    selectField('cash-source-kind', 'What did you save?', sourceKind.value, v => { sourceKind.value = v; }, sourceChoices.value),
                    field('cash-source-reference', 'Sale or payment number', sourceId.value, v => { sourceId.value = v; }),
                    submit('Find saved record', pending.value || !online.value || !validId(sourceId.value)),
                    sourceResult.value && h('p', `${sourceResult.value.found ? 'A saved record was found. You can open it on its own page to check the details.' : 'No saved record was found yet. Your earlier save may still finish. Keep the same record number.'}`),
                ]),
            ]) : h('div', [
                h('p', 'We could not open this check. This does not tell us whether your last action was saved. Keep the same check number and try loading it again.'),
                unknown.value && savedMarker.value?.action === 'start' && has('start') && action('Try starting with the same check number', () => {
                    unknown.value = false; selectedId.value = null; emit('navigate', rootHash); load(false);
                }, pending.value || !online.value),
            ]),
            confirmation.render(),
        ]);
    },
};
