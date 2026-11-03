import { apiRequest } from '../api/http-client.js';
const { h } = window.Vue;
export const has = (permissions, code) => permissions.includes(code);
export const money = value => `₦${value ?? '0.00'}`;
export const get = async path => (await apiRequest(path)).data;
export const send = async (path, method, body) => (await apiRequest(path, { method, body: JSON.stringify(body) })).data;
export const field = (id, label, value, update, options = {}) => h('div', { class: 'commercial-field' }, [
    h('label', { for: id }, label), h(options.multiline ? 'textarea' : 'input', { id, class: 'form-control', value, ...options, multiline: undefined, onInput: event => update(event.target.value) }),
]);
export const action = (label, click, disabled = false, style = 'btn-outline-primary') => h('button', { type: 'button', class: `btn ${style}`, disabled, onClick: click }, label);
export const submit = (label, disabled) => h('button', { type: 'submit', class: 'btn btn-primary', disabled }, label);
export const feedback = (error, notice) => [error && h('p', { role: 'alert', class: 'alert alert-danger' }, error), notice && h('p', { role: 'status', class: 'alert alert-success' }, notice)].filter(Boolean);
export const message = error => [error.message, ...Object.values(error.details ?? {}).flat()].filter(Boolean).join(' ');
export const pager = (page, disabled, load) => h('nav', { class: 'button-row', 'aria-label': 'Record pages' }, [
    action('Previous', () => load(page.page - 1), disabled || page.page <= 1), h('span', `Page ${page.page} of ${Math.max(1, page.total_pages)}`), action('Next', () => load(page.page + 1), disabled || page.page >= page.total_pages),
]);
