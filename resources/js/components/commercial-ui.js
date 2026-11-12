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
export const actionIcon = label => {
    const match = [[/^(Back|Previous)/i, 'back'], [/^(Add|New|Start|Create)/i, 'add'], [/^(Edit|Change|Correct)/i, 'edit'], [/^(Remove|Discard)/i, 'remove'], [/^(Cancel|Reverse|Undo)/i, 'cancel-record'], [/^(Find|Search)/i, 'search'], [/^(Reload|Refresh|Check .*status)/i, 'refresh'], [/^Print/i, 'print'], [/^Return/i, 'return'], [/^(Review|Continue|Open|View|Next|Load more)/i, 'next'], [/^(Save|Confirm)/i, 'success']].find(([pattern]) => pattern.test(label));
    return match?.[1] || '';
};
const variants = { 'btn-primary': 'primary', 'btn-danger': 'danger', 'btn-outline-danger': 'danger-secondary', 'btn-link': 'plain' };
export const action = (label, click, disabled = false, style = 'btn-outline-primary', options = {}) => h(UiButton, {
    type: 'button', icon: actionIcon(label), variant: variants[style] || 'secondary', disabled, onClick: click, ...options,
}, () => label);
export const submit = (label, disabled, options = {}) => h(UiButton, { type: 'submit', icon: actionIcon(label), disabled, ...options }, () => label);
// Legacy notices can contain financial next steps: keep them until each caller is reviewed.
export const feedback = (error, notice) => [
    error && h(UiAlert, { key: `error:${error}`, severity: 'error', message: error, persistent: true }),
    notice && h(UiAlert, { key: `notice:${notice}`, severity: 'success', message: notice, persistent: !['Customer saved.', 'Product saved.', 'Product created.', 'Low-stock level saved.'].includes(notice) }),
].filter(Boolean);
const errorCopy = {
    RECEIVABLE_DEPENDENCY: 'This sale still has linked payments or bill changes. Check and safely undo them before cancelling the sale.',
    DEBT_REFUND_DEPENDENCY: 'Part of this payment has been refunded. Open the original sale and undo the eligible return first, then review this payment again.',
    CUSTOMER_REQUIRED: 'Choose a customer because some of this sale is left unpaid.',
    CUSTOMER_INACTIVE: 'This customer is inactive. Choose an active customer.',
    PRODUCT_INACTIVE: 'This product is inactive. Remove it or choose an active product.',
    DRAFT_STALE: 'This record changed. Reload it and check your entries before saving.',
    COMMERCIAL_REVIEW_STALE: 'The saved details changed. Review the amounts again before confirming.',
    PAYMENT_EXCEEDS_TOTAL: 'Enter only the amount paid for this sale. Do not include change given back.',
    RETURN_REFUND_MISMATCH: 'The cash and transfer refunds must add up to the amount shown in the return review.',
    RETURN_REFUND_CAP_EXCEEDED: 'The refund is above the amount originally received by that payment method.',
    RETURN_QUANTITY_EXCEEDED: 'You are returning more than the remaining quantity from the original sale.',
    REVERSAL_OF_REVERSAL_NOT_ALLOWED: 'This cancellation cannot be cancelled here. Open the original record for the available options.',
    INVENTORY_STATE_CONFLICT: 'Later stock records affect this change. Check the stock history before continuing.',
    SALE_HAS_EFFECTIVE_RETURNS: 'This sale already has returns. Check those returns before trying to cancel the sale.',
    REPLACEMENT_EXISTS: 'A replacement already exists. Open that record instead of creating another.',
    PERMISSION_DENIED: 'You are not allowed to do this. Ask your shop owner for help.',
    REQUEST_TIMEOUT: 'We could not confirm the result in time. Check the saved record before trying again.',
    NETWORK_ERROR: 'We cannot reach DawaTrack. Check your connection and the saved status before trying again.',
};
export const message = error => [error.status >= 500 ? 'DawaTrack could not complete the request. Check the saved status before trying again.' : errorCopy[error.code] || error.message, ...(error.status >= 500 ? [] : Object.values(error.details ?? {}).flat()), error.requestId && `Support reference: ${error.requestId}`].filter(Boolean).join(' ');
export const pager = (page, disabled, load) => h(UiPagination, { page: page.page, totalPages: page.total_pages, busy: disabled, onPageRequest: load });
