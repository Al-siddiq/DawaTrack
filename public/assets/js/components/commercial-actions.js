import { useNavigationGuard } from '../shared/workspace-navigation.js';
import { get, send, money, field, action, feedback, message, has } from './commercial-ui.js';
const { h, ref, computed, onMounted, onUnmounted } = window.Vue;
export const CommercialActions = {
    props: { sale: Object, permissions: { type: Array, default: () => [] }, readOnly: Boolean }, emits: ['open', 'busy'],
    setup(props, { emit }) {
        let alive = true;
        const pending = ref(false), error = ref(''), review = ref(null), reason = ref(''), confirmed = ref(false), unknown = ref(false), unknownKind = ref('REVERSAL'), online = ref(navigator.onLine);
        useNavigationGuard(() => unknown.value ? { hard: true, message: 'Check the saved operation status before leaving this task.' } : reason.value.trim() || review.value ? { message: 'This correction is not saved. Keep reviewing, or leave these entries behind.' } : null);
        const returnId = ref(crypto.randomUUID()), reverseId = ref(crypto.randomUUID()), replacementId = ref(crypto.randomUUID());
        const disabled = computed(() => pending.value || props.readOnly || !online.value || unknown.value);
        const connection = () => { online.value = navigator.onLine; };
        onMounted(() => { window.addEventListener('online', connection); window.addEventListener('offline', connection); }); onUnmounted(() => { alive = false; window.removeEventListener('online', connection); window.removeEventListener('offline', connection); });
        async function run(fn) { if (pending.value) return; pending.value = true; emit('busy', true); error.value = ''; try { await fn(); } catch (e) { error.value = message(e); } finally { pending.value = false; emit('busy', false); } }
        async function startReturn() { if (disabled.value) return; await run(async () => { try { const value = await send(`/api/v1/shop/sales/${props.sale.public_id}/returns`, 'POST', { public_id: returnId.value }); emit('open', value); } catch (e) { markUnknown(e, 'RETURN_DRAFT'); throw e; } }); }
        async function preview() { if (disabled.value) return; await run(async () => { review.value = await send(`/api/v1/shop/sales/${props.sale.public_id}/reversal-preview`, 'POST', {}); confirmed.value = false; }); }
        async function reverse() { if (disabled.value || !review.value || !confirmed.value || !reason.value.trim()) return; const prepared = review.value; await run(async () => { try { const value = await send(`/api/v1/shop/sales/${props.sale.public_id}/reverse`, 'POST', { public_id: reverseId.value, reason: reason.value, review_fingerprint: prepared.review_fingerprint }); emit('open', value); } catch (e) { review.value = null; markUnknown(e, 'REVERSAL'); throw e; } }); }
        function markUnknown(error, kind) { if (error.status === 0 || error.status >= 500 || error.status === 403) { unknown.value = true; unknownKind.value = kind; } }
        async function recover() { await run(async () => {
            if (unknownKind.value === 'RETURN_DRAFT') {
                try { const draft = await get(`/api/v1/shop/sales/${returnId.value}`); unknown.value = false; emit('open', draft); }
                catch (e) { if (e.status === 404) { unknown.value = false; error.value = 'No Return was saved. You can retry using the same identity.'; } else throw e; }
                return;
            }
            const source = await get(`/api/v1/shop/sales/${props.sale.public_id}`);
            const value = unknownKind.value === 'REVERSAL' && source.reversal_public_id ? await get(`/api/v1/shop/sales/${source.reversal_public_id}`) : source;
            unknown.value = false; emit('open', value);
        }); }
        async function replace() { if (disabled.value || !reason.value.trim()) return; await run(async () => {
            try { const value = await send(`/api/v1/shop/sales/${props.sale.public_id}/replacement`, 'POST', { public_id: replacementId.value, reason: reason.value }); if (!alive) return; reason.value = ''; review.value = null; if (value.kind === 'SALE' && value.status === 'DRAFT') location.hash = `#sale-draft/${value.public_id}`; else emit('open', value.draft ?? value); }
            catch (e) { markUnknown(e, 'REPLACEMENT'); throw e; }
        }); }
        const link = (label, id) => action(label, () => run(async () => emit('open', await get(`/api/v1/shop/sales/${id}`))), pending.value || !online.value);
        return () => h('section', { class: 'commercial-card', 'aria-busy': pending.value }, [h('h2', 'Related records and corrections'), ...feedback(error.value, ''),
            props.sale.original_sale_public_id && link('View original Sale', props.sale.original_sale_public_id), props.sale.reversal_of_public_id && link('View compensated operation', props.sale.reversal_of_public_id), props.sale.reversal_public_id && link('View reversal receipt', props.sale.reversal_public_id), props.sale.replacement && action('Open replacement', () => { if (props.sale.replacement.kind === 'SALE' && props.sale.replacement.status === 'DRAFT') location.hash = `#sale-draft/${props.sale.replacement.public_id}`; else run(async () => emit('open', await get(`/api/v1/shop/sales/${props.sale.replacement.public_id}`))); }, pending.value || !online.value),
            ...(props.sale.returns ?? []).map(r => h('p', { key: r.public_id }, [link(`Open Return · ${r.status}${r.reversal_public_id ? ' · reversed' : ''} · ${money(r.total_amount)}`, r.public_id)])),
            !online.value && h('p', 'Offline: correction commands are unavailable.'),
            unknown.value && h('section', { role: 'alert', class: 'alert alert-warning' }, [h('p', 'The operation outcome is uncertain. Retrieve its recorded state before continuing.'), action(unknownKind.value === 'REVERSAL' ? 'Check reversal status' : 'Check saved operation', recover, pending.value || !online.value)]),
            props.sale.operation_type !== 'COMPENSATION' && !props.sale.reversal_public_id && props.sale.kind === 'SALE' && has(props.permissions, 'sale.return') && action('Return Products from this Sale', startReturn, disabled.value, 'btn-primary'),
            props.sale.operation_type !== 'COMPENSATION' && has(props.permissions, 'sale.reverse') && h('details', [h('summary', props.sale.reversal_public_id ? 'Prepare a replacement' : 'Reverse this operation'), h('p', 'A reversal compensates money, debt and stock together. Later dependent activity may prevent direct reversal.'), field('commercial-correction-reason', 'Correction reason', reason.value, value => { reason.value = value; }, { multiline: true, required: true, maxlength: 500, disabled: disabled.value }),
                props.sale.reversal_public_id ? !props.sale.replacement && action('Create linked replacement draft', replace, disabled.value || !reason.value.trim(), 'btn-outline-primary') : action('Review reversal', preview, disabled.value),
                review.value && h('section', { role: 'status' }, [h('p', `${review.value.money_direction}: cash ${money(review.value.cash_amount)}, transfer ${money(review.value.transfer_amount)}. ${review.value.debt_direction} existing debt: ${money(review.value.debt_amount)}. ${review.value.line_count} stock lines.`), h('label', { class: 'commercial-confirmation' }, [h('input', { type: 'checkbox', checked: confirmed.value, disabled: disabled.value, onChange: e => { confirmed.value = e.target.checked; } }), ' I confirm the entire linked compensation.']), action('Confirm reversal', reverse, disabled.value || !confirmed.value || !reason.value.trim(), 'btn-outline-danger')]),
            ].filter(Boolean)),
        ].filter(Boolean));
    },
};
