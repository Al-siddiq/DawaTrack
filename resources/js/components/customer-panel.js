import { useTradingConfirm, fieldSummary, selectField, stateLabel } from './trading-ui.js';
import { useNavigationGuard } from '../shared/workspace-navigation.js';
import { UiLoading, UiRecordList, UiInformationPanel, useUiList } from './ui/index.js';
import { apiRequest } from '../api/http-client.js';
import { has, get, send, field, action, submit, feedback, message, pager } from './commercial-ui.js';
const { h, ref, reactive, computed, onMounted, onUnmounted, nextTick } = window.Vue;
const base = '/api/v1/shop/customers';
export const CustomerPanel = {
    props: { permissions: { type: Array, default: () => [] }, readOnly: { type: Boolean, default: true } },
    setup(props) {
        const confirmation = useTradingConfirm(), validation = ref({});
        const pendingLabel = ref('Loading customers…');
        const selected = ref(null), editing = ref(false), pending = ref(false), error = ref(''), notice = ref(''), search = ref(''), status = ref('ACTIVE'), online = ref(navigator.onLine), heading = ref(null);
        const list = useUiList(async (query, signal) => {
            const response = await apiRequest(`${base}?${new URLSearchParams(query)}`, { signal });
            return { rows: response.data, pagination: response.meta.pagination };
        });
        const rows = list.rows;
        const pagination = list.pagination;
        const form = reactive({ public_id: crypto.randomUUID(), display_name: '', phone: '', notes: '' });
        const reason = ref('');
        useNavigationGuard(() => editing.value || reason.value.trim() ? { message: 'Your customer edits are not saved. Keep editing, or leave without saving them.' } : null);
        const manage = computed(() => has(props.permissions, 'customer.manage') && has(props.permissions, 'customer.view'));
        const privateDetails = computed(() => has(props.permissions, 'customer.private'));
        const disabled = computed(() => pending.value || list.pending.value || props.readOnly || !online.value || !manage.value);
        const connection = () => { online.value = navigator.onLine; };
        async function load(page = pagination.page) {
            error.value = '';
            await list.load(page, { search: search.value, status: status.value });
            if (list.error.value) error.value = list.error.value;
        }
        async function open(row) {
            pendingLabel.value = 'Loading customer details…';
            pending.value = true; error.value = ''; notice.value = '';
            try { selected.value = await get(`${base}/${row.public_id}`); editing.value = false; reason.value = ''; await nextTick(); heading.value?.focus(); }
            catch (e) { error.value = message(e); } finally { pending.value = false; }
        }
        function edit(isNew = false) {
            error.value = ''; notice.value = ''; validation.value = {}; editing.value = true;
            if (isNew) selected.value = null;
            Object.assign(form, { public_id: selected.value?.public_id ?? crypto.randomUUID(), display_name: selected.value?.display_name ?? '', phone: selected.value?.phone ?? '', notes: selected.value?.notes ?? '' });
            nextTick(() => document.getElementById('customer-name')?.focus());
        }
        async function save(event) {
            event.preventDefault(); if (disabled.value) return;
            pendingLabel.value = 'Saving customer…';
            pending.value = true; error.value = ''; notice.value = '';
            try {
                const input = { display_name: form.display_name };
                if (privateDetails.value) Object.assign(input, { phone: form.phone, notes: form.notes });
                if (selected.value) input.expected_updated_at = selected.value.updated_at;
                else input.public_id = form.public_id;
                selected.value = await send(selected.value ? `${base}/${selected.value.public_id}` : base, selected.value ? 'PUT' : 'POST', input);
                validation.value = {}; editing.value = false; notice.value = 'Customer saved.';
                await nextTick(); heading.value?.focus();
            } catch (e) { validation.value = e.details || {}; error.value = `${message(e)} Your entered details are retained. Reload the Customer if its state changed.`; }
            finally { pending.value = false; }
        }
        async function deactivate(event) {
            event.preventDefault(); if (disabled.value || !selected.value) return;
            if (!(await confirmation.ask({ title: 'Make this customer inactive?', message: 'You will no longer be able to choose this customer for new sales. Past records will stay.', confirm: 'Make inactive', danger: true }))) return;
            pendingLabel.value = 'Saving change…';
            pending.value = true; error.value = ''; notice.value = '';
            try { selected.value = await send(`${base}/${selected.value.public_id}/deactivate`, 'POST', { expected_updated_at: selected.value.updated_at, reason: reason.value }); reason.value = ''; notice.value = 'Customer made inactive. Past records are kept.'; }
            catch (e) { error.value = message(e); } finally { pending.value = false; }
        }
        function back() { return confirmation.guard(() => { selected.value = null; editing.value = false; reason.value = ''; validation.value = {}; error.value = ''; notice.value = ''; load(); }); }
        onMounted(() => { load(); window.addEventListener('online', connection); window.addEventListener('offline', connection); });
        onUnmounted(() => { window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        return () => h('section', { class: 'workspace commercial-workspace trading-screen ui-scope', 'aria-busy': pending.value || list.pending.value }, [
            h('h2', { ref: heading, tabindex: '-1' }, 'Customers'), h('p', 'Find a customer or save their details for future sales.'),
            !online.value && h('p', { class: 'alert alert-warning' }, 'You are offline. Customer changes are not queued.'),
            props.readOnly && h(UiInformationPanel, { severity: 'warning', message: 'Your current access permits viewing only.' }), ...feedback(selected.value || editing.value ? error.value : '', notice.value), fieldSummary(validation.value, { display_name: 'customer-name', phone: 'customer-phone', notes: 'customer-notes' }), confirmation.render(),
            pending.value && (selected.value || editing.value) && h(UiLoading, { label: pendingLabel.value }),
            editing.value ? h('form', { class: 'commercial-form', onSubmit: save }, [
                h('h2', selected.value ? 'Edit customer' : 'Add customer'),
                field('customer-name', 'Customer name', form.display_name, value => { form.display_name = value; }, { required: true, maxlength: 160, disabled: disabled.value, autocomplete: 'name', errors: validation.value.display_name || [] }),
                privateDetails.value && field('customer-phone', 'Phone (optional; shared numbers are allowed)', form.phone, value => { form.phone = value; }, { maxlength: 32, type: 'tel', disabled: disabled.value, autocomplete: 'tel', errors: validation.value.phone || [] }),
                privateDetails.value && field('customer-notes', 'Relevant notes (optional)', form.notes, value => { form.notes = value; }, { multiline: true, maxlength: 500, disabled: disabled.value, errors: validation.value.notes || [] }),
                !privateDetails.value && h('p', 'You do not have access to private details. Any saved private details will be kept.'), submit('Save customer', disabled.value, { loading: pending.value, busyLabel: 'Saving customer…', icon: 'success' }), action('Cancel editing', async () => { if (await confirmation.ask({ title: 'Cancel these changes?', message: 'Your entered changes will not be saved.', confirm: 'Discard changes' })) { editing.value = false; validation.value = {}; } }, pending.value),
            ].filter(Boolean)) : selected.value ? h('section', [
                h('h2', selected.value.display_name), h('p', `Status: ${stateLabel(selected.value.status)}`), h('p', `Reference: ${selected.value.public_id}`),
                privateDetails.value && selected.value.private_visible ? h('dl', [h('dt', 'Phone'), h('dd', selected.value.phone ?? 'Not provided'), h('dt', 'Notes'), h('dd', selected.value.notes ?? 'Not provided')]) : h('p', 'Private details are hidden.'),
                action('Back to customers', back, pending.value), action('Reload customer', () => confirmation.guard(() => open(selected.value)), pending.value || !online.value),
                manage.value && selected.value.status === 'ACTIVE' && action('Edit customer', () => edit(), disabled.value),
                manage.value && selected.value.status === 'ACTIVE' && h('form', { onSubmit: deactivate, class: 'sensitive-action' }, [h('h3', 'Make customer inactive'), h('p', 'You will not be able to choose this customer for a new sale. Past sales will stay.'), field('customer-reason', 'Why are you making this customer inactive?', reason.value, value => { reason.value = value; }, { multiline: true, required: true, maxlength: 500, disabled: disabled.value }), submit('Make customer inactive', disabled.value, { loading: pending.value, busyLabel: 'Saving change…' })]),
            ].filter(Boolean)) : h('section', [
                manage.value && action('Add customer', () => edit(true), disabled.value, 'btn-primary'),
                h('form', { onSubmit: e => { e.preventDefault(); load(1); }, class: 'commercial-filters' }, [field('customer-search', 'Find customers', search.value, value => { search.value = value; }, { type: 'search', maxlength: 160 }), selectField('customer-status', 'Show customers', status.value, value => { status.value = value; }, ['ACTIVE', 'INACTIVE', 'ALL'].map(value => [value, stateLabel(value)])), submit('Find customers', pending.value || list.pending.value || !online.value, { loading: list.pending.value, busyLabel: 'Finding customers…', icon: 'search' })]),
                h(UiRecordList, { records: rows.value, busy: list.pending.value, error: error.value, loadingLabel: 'Finding customers…', emptyTitle: 'No matching customers', emptyMessage: 'Try another name or change the status filter.' }, {
                    record: ({ record: row }) => h('article', { class: 'commercial-card' }, [h('h2', row.display_name), h('p', stateLabel(row.status)), privateDetails.value && row.phone && h('p', row.phone), action('View customer', () => open(row), pending.value || list.pending.value || !online.value)]),
                }), pager(pagination, pending.value || list.pending.value || !online.value, load),
            ].filter(Boolean)),
        ].filter(Boolean));
    },
};
