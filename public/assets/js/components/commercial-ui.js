import { apiRequest } from '../api/http-client.js';
import { UiButton, UiField, UiAlert, UiPagination } from './ui/index.js';
const { h } = window.Vue;
export const has = (permissions, code) => permissions.includes(code);
export const money = value => `₦${value ?? '0.00'}`;
export const get = async path => (await apiRequest(path)).data;
export const send = async (path, method, body) => (await apiRequest(path, { method, body: JSON.stringify(body) })).data;

// Compatibility adapters retain current caller signatures, values and endpoint ownership.
export function field(id, label, value, update, options = {}) {
    const { multiline, help = '', errors = [], ...input } = options;
    return h(UiField, { id, label, help, errors, required: !!input.required, describedBy: input['aria-describedby'], class: 'commercial-field' }, {
        default: ({ controlAttrs }) => h(multiline ? 'textarea' : 'input', {
            ...input, ...controlAttrs, 'aria-invalid': controlAttrs['aria-invalid'] ?? input['aria-invalid'], value, class: ['form-control', input.class],
            onInput: event => update(event.target.value),
        }),
    });
}
const variants = { 'btn-primary': 'primary', 'btn-danger': 'danger', 'btn-outline-danger': 'danger-secondary', 'btn-link': 'plain' };
export const action = (label, click, disabled = false, style = 'btn-outline-primary', options = {}) => h(UiButton, {
    type: 'button', variant: variants[style] || 'secondary', disabled, onClick: click, ...options,
}, () => label);
export const submit = (label, disabled, options = {}) => h(UiButton, { type: 'submit', disabled, ...options }, () => label);
// Legacy notices can contain financial next steps: keep them until each caller is reviewed.
export const feedback = (error, notice) => [
    error && h(UiAlert, { key: `error:${error}`, severity: 'error', message: error, persistent: true }),
    notice && h(UiAlert, { key: `notice:${notice}`, severity: 'success', message: notice, persistent: true }),
].filter(Boolean);
export const message = error => [error.message, ...Object.values(error.details ?? {}).flat()].filter(Boolean).join(' ');
export const pager = (page, disabled, load) => h(UiPagination, { page: page.page, totalPages: page.total_pages, busy: disabled, onPageRequest: load });
