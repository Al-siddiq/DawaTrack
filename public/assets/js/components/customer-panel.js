import { apiRequest } from '../api/http-client.js';
import { has, get, send, field, action, submit, feedback, message, pager } from './commercial-ui.js';
const { h, ref, reactive, computed, onMounted, onUnmounted, nextTick } = window.Vue;
const base = '/api/v1/shop/customers';
export const CustomerPanel = {
    props: { permissions: { type: Array, default: () => [] }, readOnly: { type: Boolean, default: true } },
    setup(props) {
        const rows = ref([]), selected = ref(null), editing = ref(false), pending = ref(false), error = ref(''), notice = ref(''), search = ref(''), status = ref('ACTIVE'), online = ref(navigator.onLine), heading = ref(null);
        const pagination = reactive({ page: 1, per_page: 20, total: 0, total_pages: 0 });
        const form = reactive({ public_id: crypto.randomUUID(), display_name: '', phone: '', notes: '' });
        const reason = ref('');
        const manage = computed(() => has(props.permissions, 'customer.manage') && has(props.permissions, 'customer.view'));
        const privateDetails = computed(() => has(props.permissions, 'customer.private'));
        const disabled = computed(() => pending.value || props.readOnly || !online.value || !manage.value);
        const connection = () => { online.value = navigator.onLine; };
        async function load(page = pagination.page) {
            pending.value = true; error.value = '';
            try {
                const response = await apiRequest(`${base}?${new URLSearchParams({ search: search.value, status: status.value, page, per_page: 20 })}`);
                rows.value = response.data; Object.assign(pagination, response.meta.pagination);
            } catch (e) { error.value = message(e); } finally { pending.value = false; }
        }
        async function open(row) {
            pending.value = true; error.value = ''; notice.value = '';
            try { selected.value = await get(`${base}/${row.public_id}`); editing.value = false; reason.value = ''; await nextTick(); heading.value?.focus(); }
            catch (e) { error.value = message(e); } finally { pending.value = false; }
        }
        function edit(isNew = false) {
            error.value = ''; notice.value = ''; editing.value = true;
            if (isNew) selected.value = null;
            Object.assign(form, { public_id: selected.value?.public_id ?? crypto.randomUUID(), display_name: selected.value?.display_name ?? '', phone: selected.value?.phone ?? '', notes: selected.value?.notes ?? '' });
            nextTick(() => document.getElementById('customer-name')?.focus());
        }
        async function save(event) {
            event.preventDefault(); if (disabled.value) return;
            pending.value = true; error.value = ''; notice.value = '';
            try {
                const input = { display_name: form.display_name };
                if (privateDetails.value) Object.assign(input, { phone: form.phone, notes: form.notes });
                if (selected.value) input.expected_updated_at = selected.value.updated_at;
                else input.public_id = form.public_id;
                selected.value = await send(selected.value ? `${base}/${selected.value.public_id}` : base, selected.value ? 'PUT' : 'POST', input);
                editing.value = false; notice.value = 'Customer saved.';
                await nextTick(); heading.value?.focus();
            } catch (e) { error.value = `${message(e)} Your entered details are retained. Reload the Customer if its state changed.`; }
            finally { pending.value = false; }
        }
        async function deactivate(event) {
            event.preventDefault(); if (disabled.value || !selected.value) return;
            pending.value = true; error.value = ''; notice.value = '';
            try { selected.value = await send(`${base}/${selected.value.public_id}/deactivate`, 'POST', { expected_updated_at: selected.value.updated_at, reason: reason.value }); reason.value = ''; notice.value = 'Customer deactivated. Existing history is retained.'; }
            catch (e) { error.value = message(e); } finally { pending.value = false; }
        }
        function back() { selected.value = null; editing.value = false; error.value = ''; notice.value = ''; load(); }
        onMounted(() => { load(); window.addEventListener('online', connection); window.addEventListener('offline', connection); });
        onUnmounted(() => { window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        return () => h('section', { class: 'workspace commercial-workspace', 'aria-busy': pending.value }, [
            h('h1', { ref: heading, tabindex: '-1' }, 'Customers'), h('p', 'Manage Customer identities. Deactivation keeps their history. No editable balance or debt collection is provided here.'),
            !online.value && h('p', { class: 'alert alert-warning' }, 'You are offline. Customer changes are not queued.'),
            props.readOnly && h('p', { class: 'alert alert-warning' }, 'Your current access permits viewing only.'), ...feedback(error.value, notice.value),
            pending.value && h('p', { role: 'status' }, 'Please wait…'),
            editing.value ? h('form', { class: 'commercial-form', onSubmit: save }, [
                h('h2', selected.value ? 'Edit Customer' : 'Create Customer'),
                field('customer-name', 'Customer name', form.display_name, value => { form.display_name = value; }, { required: true, maxlength: 160, disabled: disabled.value, autocomplete: 'name' }),
                privateDetails.value && field('customer-phone', 'Phone (optional; shared numbers are allowed)', form.phone, value => { form.phone = value; }, { maxlength: 32, type: 'tel', disabled: disabled.value, autocomplete: 'tel' }),
                privateDetails.value && field('customer-notes', 'Relevant notes (optional)', form.notes, value => { form.notes = value; }, { multiline: true, maxlength: 500, disabled: disabled.value }),
                !privateDetails.value && h('p', 'Private details are hidden and will be preserved.'), submit('Save Customer', disabled.value), action('Cancel editing', () => { editing.value = false; }, pending.value),
            ].filter(Boolean)) : selected.value ? h('section', [
                h('h2', selected.value.display_name), h('p', `Status: ${selected.value.status}`), h('p', `Reference: ${selected.value.public_id}`),
                privateDetails.value && selected.value.private_visible ? h('dl', [h('dt', 'Phone'), h('dd', selected.value.phone ?? 'Not provided'), h('dt', 'Notes'), h('dd', selected.value.notes ?? 'Not provided')]) : h('p', 'Private details are hidden.'),
                action('Back to Customers', back, pending.value), action('Reload Customer', () => open(selected.value), pending.value || !online.value),
                manage.value && selected.value.status === 'ACTIVE' && action('Edit Customer', () => edit(), disabled.value),
                manage.value && selected.value.status === 'ACTIVE' && h('form', { onSubmit: deactivate, class: 'sensitive-action' }, [h('h3', 'Deactivate Customer'), h('p', 'This prevents new selection while preserving existing records.'), field('customer-reason', 'Deactivation reason', reason.value, value => { reason.value = value; }, { multiline: true, required: true, maxlength: 500, disabled: disabled.value }), submit('Deactivate Customer', disabled.value)]),
            ].filter(Boolean)) : h('section', [
                manage.value && action('Create Customer', () => edit(true), disabled.value, 'btn-primary'),
                h('form', { onSubmit: e => { e.preventDefault(); load(1); }, class: 'commercial-filters' }, [field('customer-search', 'Search Customers', search.value, value => { search.value = value; }, { type: 'search', maxlength: 160 }), h('label', { for: 'customer-status' }, 'Status'), h('select', { id: 'customer-status', class: 'form-select', value: status.value, onChange: e => { status.value = e.target.value; } }, ['ACTIVE', 'INACTIVE', 'ALL'].map(value => h('option', { value }, value))), submit('Search Customers', pending.value || !online.value)]),
                !rows.value.length && !pending.value && h('p', 'No matching Customers.'),
                ...rows.value.map(row => h('article', { class: 'commercial-card', key: row.public_id }, [h('h2', row.display_name), h('p', row.status), privateDetails.value && row.phone && h('p', row.phone), action('View Customer', () => open(row), pending.value || !online.value)])), pager(pagination, pending.value || !online.value, load),
            ].filter(Boolean)),
        ].filter(Boolean));
    },
};
