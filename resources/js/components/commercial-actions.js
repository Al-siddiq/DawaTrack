import { useTradingConfirm, stateLabel } from './trading-ui.js';
import { UiLoading } from './ui/index.js';
import { useNavigationGuard } from '../shared/workspace-navigation.js';
import { get, send, money, field, action, feedback, message, has } from './commercial-ui.js';
const { h, ref, computed, onMounted, onUnmounted } = window.Vue;
export const CommercialActions = {
    props: { sale: Object, permissions: { type: Array, default: () => [] }, readOnly: Boolean }, emits: ['open', 'busy'],
    setup(props, { emit }) {
        const confirmation = useTradingConfirm();
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
                catch (e) { if (e.status === 404) { unknown.value = false; error.value = 'No return was found. You can try again safely using this same request.'; } else throw e; }
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
        const link = (label, id) => action(label, () => confirmation.guard(() => run(async () => emit('open', await get(`/api/v1/shop/sales/${id}`)))), pending.value || !online.value);
        return () => h('section', { class: 'commercial-card trading-screen ui-scope', 'aria-busy': pending.value }, [h('h2', 'Returns, cancellations and linked records'), ...feedback(error.value, ''), confirmation.render(), pending.value && h(UiLoading, { label: 'Updating this record…' }),
            props.sale.original_sale_public_id && link('View original sale', props.sale.original_sale_public_id), props.sale.reversal_of_public_id && link('View cancelled record', props.sale.reversal_of_public_id), props.sale.reversal_public_id && link('View cancellation receipt', props.sale.reversal_public_id), props.sale.replacement && action('Open replacement', () => confirmation.guard(() => { if (props.sale.replacement.kind === 'SALE' && props.sale.replacement.status === 'DRAFT') location.hash = `#sale-draft/${props.sale.replacement.public_id}`; else run(async () => emit('open', await get(`/api/v1/shop/sales/${props.sale.replacement.public_id}`))); }), pending.value || !online.value),
            ...(props.sale.returns ?? []).map(r => h('p', { key: r.public_id }, [link(`Open return · ${stateLabel(r.status)}${r.reversal_public_id ? ' · cancelled' : ''} · ${money(r.total_amount)}`, r.public_id)])),
            !online.value && h('p', 'Connect to make changes. Nothing will be saved while offline.'),
            unknown.value && h('section', { role: 'alert', class: 'alert alert-warning' }, [h('p', 'We could not confirm whether this was saved. Check the saved status before making another change.'), action(unknownKind.value === 'REVERSAL' ? 'Check cancellation status' : 'Check saved operation', recover, pending.value || !online.value)]),
            props.sale.operation_type !== 'COMPENSATION' && !props.sale.reversal_public_id && props.sale.kind === 'SALE' && has(props.permissions, 'sale.return') && action('Return products from this sale', () => confirmation.guard(startReturn), disabled.value, 'btn-primary', { icon: 'return', loading: pending.value }),
            props.sale.operation_type !== 'COMPENSATION' && has(props.permissions, 'sale.reverse') && h('details', [h('summary', props.sale.reversal_public_id ? 'Prepare a replacement sale' : 'Cancel this saved record'), h('p', 'Cancelling undoes the money, amount owed and stock changes together. The original record stays. Later sales or returns may prevent cancellation.'), field('commercial-correction-reason', 'Why does this record need changing?', reason.value, value => { reason.value = value; }, { multiline: true, required: true, maxlength: 500, disabled: disabled.value }),
                props.sale.reversal_public_id ? !props.sale.replacement && action('Create replacement sale', replace, disabled.value || !reason.value.trim(), 'btn-outline-primary') : action('Review cancellation', preview, disabled.value),
                review.value && h('section', { role: 'status' }, [h('p', `${stateLabel(review.value.money_direction)}: cash ${money(review.value.cash_amount)}, transfer ${money(review.value.transfer_amount)}. ${stateLabel(review.value.debt_direction)} amount owed: ${money(review.value.debt_amount)}. ${review.value.line_count} product lines.`), h('label', { class: 'commercial-confirmation' }, [h('input', { type: 'checkbox', checked: confirmed.value, disabled: disabled.value, onChange: e => { confirmed.value = e.target.checked; } }), ' I understand this cancels the money, amount owed and stock changes together. The original record will stay.']), action('Confirm cancellation', reverse, disabled.value || !confirmed.value || !reason.value.trim(), 'btn-outline-danger', { loading: pending.value, busyLabel: 'Saving cancellation…', icon: 'cancel-record' })]),
            ].filter(Boolean)),
        ].filter(Boolean));
    },
};
