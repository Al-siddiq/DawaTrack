import { apiRequest } from '../api/http-client.js';
import { action, field, submit, money, message, has, pager } from './commercial-ui.js';
import { facts, selectField, fieldSummary, stateLabel, useTradingConfirm } from './trading-ui.js';
import { UiLoading, UiAlert, UiInformationPanel } from './ui/index.js';
import { useNavigationGuard } from '../shared/workspace-navigation.js';
import { DebtPaymentReceipt } from './debt-payment-receipt.js';
const { h, ref, reactive, computed, watch, onBeforeUnmount } = window.Vue;
const base = '/api/v1/shop';
const now = () => new Date().toISOString().replace('T', ' ').replace('Z', '000');
const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/;
const titles = { reverse: 'Undo customer payment', correct: 'Correct customer payment', adjust: 'Change a bill amount', 'adjust-reverse': 'Undo bill change', adjustment: 'Saved bill change', changes: 'Bill changes' };
export const correctionHash = m => `#customers/debt/${m.customer}/${m.kind === 'reverse' || m.kind === 'correct' ? 'payment' : m.kind === 'adjust' ? 'bill' : 'adjustment'}/${m.source}/${m.kind === 'adjust-reverse' ? 'reverse' : m.kind}`;

/** Reviewed, reasoned corrections; storage keeps only operation identities, never money or reasons. */
export const DebtCorrectionPanel = {
    props: { customerId: String, sourceId: String, kind: String, permissions: { type: Array, default: () => [] }, readOnly: Boolean, recoveryScope: String },
    emits: ['navigate'],
    setup(props, { emit }) {
        const confirmation = useTradingConfirm(), source = ref(null), review = ref(null), receipt = ref(null), records = ref([]), bills = ref([]);
        const dependency = ref(null);
        const busy = ref(false), saving = ref(false), phase = ref('input'), error = ref(''), validation = ref({}), dirty = ref(false), absent = ref(false), pending = ref(null), online = ref(navigator.onLine), storageAvailable = ref(true);
        const form = reactive({ id: crypto.randomUUID(), replacement: crypto.randomUUID(), reason: '', amount: '', type: 'CORRECTION_DECREASE', channel: 'CASH', reference: '', mode: 'OLDEST_FIRST' });
        const selections = reactive({}), billPage = ref(0), billPages = ref(0), pagination = reactive({ page: 1, total_pages: 0 });
        let alive = true, generation = 0, controller = null, frozen = null;
        const view = computed(() => has(props.permissions, 'customer.view') && has(props.permissions, 'debt.view'));
        const mutation = computed(() => ['reverse','correct','adjust','adjust-reverse'].includes(props.kind));
        const allowed = computed(() => view.value && has(props.permissions, ['reverse','correct'].includes(props.kind) ? 'debt.payment.reverse' : 'debt.adjust') && (props.kind !== 'correct' || has(props.permissions, 'debt.payment.record')));
        const locked = computed(() => busy.value || saving.value || !online.value || props.readOnly || !allowed.value || phase.value === 'unknown');
        const key = () => props.recoveryScope ? `dt:pending-debt-correction:${location.host}:${props.recoveryScope}` : '';
        const go = hash => emit('navigate', hash);
        function marker(value) { try { if (!key()) throw Error(); value ? sessionStorage.setItem(key(), JSON.stringify(value)) : sessionStorage.removeItem(key()); } catch { storageAvailable.value = false; } }
        function readMarker() { try { const m = JSON.parse(key() ? sessionStorage.getItem(key()) : 'null'); return m && uuid.test(m.customer) && uuid.test(m.source) && uuid.test(m.id) && ['reverse','correct','adjust','adjust-reverse'].includes(m.kind) && (m.kind !== 'correct' || uuid.test(m.replacement)) ? m : null; } catch { return null; } }
        useNavigationGuard(({ hash }) => {
            if (saving.value) return { hard: true, message: 'This change is being saved. Wait for the result.' };
            if (pending.value) { if (hash === correctionHash(pending.value) && hash !== location.hash) return null; return { hard: true, message: 'Check saved status or continue with the same reference before leaving.' }; }
            return dirty.value || phase.value === 'review' ? { message: 'This change has not been saved. Keep editing, or leave without saving.' } : null;
        });
        const path = () => props.kind === 'adjust' ? `${base}/debt-bills/${props.sourceId}` : props.kind === 'adjust-reverse' ? `${base}/debt-adjustments/${props.sourceId}` : `${base}/debt-payments/${props.sourceId}`;
        const suffix = () => props.kind === 'correct' ? ['correction-preview','correct'] : props.kind === 'adjust' ? ['adjustment-preview','adjustments'] : ['reversal-preview','reverse'];
        const statusPath = id => `${base}/${['reverse','correct'].includes(props.kind) ? 'debt-payments' : 'debt-adjustments'}/${id}`;
        function change(k,v) { form[k]=v; review.value=null; dirty.value=true; }
        async function loadBills(page = 1) {
            const current = generation;
            // Include paid bills: undoing the original may restore money owed on one of them.
            const result = await apiRequest(`${base}/customers/${props.customerId}/debt?status=ALL&per_page=50&page=${page}`, { signal: controller.signal });
            if (!alive || current !== generation) return;
            bills.value = page === 1 ? result.data.bills : [...bills.value, ...result.data.bills.filter(b => !bills.value.some(old => old.public_id === b.public_id))];
            billPage.value=page; billPages.value=result.data.pagination.total_pages;
        }
        async function initialize(page = 1) {
            const current=++generation; controller?.abort(); controller=new AbortController(); busy.value=false; source.value=null; receipt.value=null; records.value=[]; review.value=null; error.value='';
            if (!view.value || !online.value) return;
            pending.value=readMarker();
            if (pending.value) {
                const target=correctionHash(pending.value);
                if (pending.value.customer !== props.customerId || pending.value.source !== props.sourceId || pending.value.kind !== props.kind) { go(target); return; }
                form.id=pending.value.id; form.replacement=pending.value.replacement || form.replacement; phase.value='unknown';
            } else { phase.value='input'; dirty.value=false; Object.assign(form,{id:crypto.randomUUID(),replacement:crypto.randomUUID(),reason:'',amount:'',type:'CORRECTION_DECREASE',channel:'CASH',reference:'',mode:'OLDEST_FIRST'}); for(const k of Object.keys(selections))delete selections[k]; }
            // A normal payment awaiting recovery must also be resolved before another money command.
            try { const m=JSON.parse(props.recoveryScope ? sessionStorage.getItem(`dt:pending-debt-payment:${location.host}:${props.recoveryScope}`) : 'null'); if(m && uuid.test(m.customer) && uuid.test(m.payment)) { go(`#customers/debt/${m.customer}/receive`); return; } } catch { /* Existing payment panel owns validation/recovery. */ }
            busy.value=true;
            try {
                const route=props.kind==='changes' ? `${base}/debt-bills/${props.sourceId}/adjustments?page=${page}` : props.kind==='adjustment' || props.kind==='adjust-reverse' ? `${base}/debt-adjustments/${props.sourceId}` : props.kind==='adjust' ? `${base}/debt-bills/${props.sourceId}` : `${base}/debt-payments/${props.sourceId}`;
                const result=await apiRequest(route,{signal:controller.signal}); if(!alive || current!==generation) return;
                if(props.kind==='changes') { records.value=result.data.items; Object.assign(pagination,result.data.pagination); if(records.value.some(r=>r.customer_public_id!==props.customerId)) throw Error('This bill belongs to another customer.'); }
                else { if(result.data.customer_public_id!==props.customerId) throw Error('This record belongs to another customer.'); source.value=result.data; }
                if(props.kind==='correct') await loadBills();
            } catch(failure) { if(alive && current===generation) { error.value=message(failure); source.value=null; records.value=[]; } }
            finally { if(alive && current===generation) busy.value=false; }
        }
        function command() {
            const common={reason:form.reason,occurred_at:frozen?.occurred_at || now()};
            if(props.kind==='correct') return {...common,reversal_public_id:form.id,replacement_public_id:form.replacement,replacement:{customer_public_id:props.customerId,channel:form.channel,amount:form.amount,external_reference:form.channel==='TRANSFER'?form.reference||null:null,occurred_at:frozen?.replacement?.occurred_at || now(),allocation_mode:form.mode,allocations:form.mode==='EXPLICIT'?Object.entries(selections).filter(([,v])=>v!==null).map(([id,amount])=>({receivable_public_id:id,amount})):[]}};
            return {...common,public_id:form.id,...(props.kind==='adjust'?{adjustment_type:form.type,amount_delta:(form.type==='CORRECTION_DECREASE'?'-':'')+form.amount}:{})};
        }
        async function showSaved(id, data = null) {
            const result=data || (await apiRequest(statusPath(id))).data;
            if(result.customer_public_id!==props.customerId) throw Error('This saved change belongs to another customer.');
            // A corrected result is recovered via the inverse receipt; its linked replacement was committed with it.
            if(props.kind==='correct' && result.replacement_public_id!==form.replacement) throw Error('The saved replacement needs checking. Keep these references and ask your shop owner for help.');
            receipt.value=result; phase.value='saved'; dirty.value=false; pending.value=null; marker(null);
        }
        async function prepare(event) {
            event?.preventDefault(); if(locked.value) return; busy.value=true;error.value='';validation.value={};dependency.value=null;
            try { const input=command(); const result=await apiRequest(`${path()}/${suffix()[0]}`,{method:'POST',body:JSON.stringify(input)}); if(!alive)return; if(result.data.already_saved) await showSaved(form.id); else {frozen=input;review.value=result.data;phase.value='review';} }
            catch(failure) {dependency.value=failure.details?.dependencies||null; validation.value={...(failure.details||{})}; delete validation.value.dependencies; error.value=message(failure);review.value=null;}
            finally {busy.value=false;}
        }
        async function save() {
            if(locked.value || !review.value || phase.value!=='review')return;
            if(!(await confirmation.ask({title:'Save this change?',message:props.kind==='correct'?'The original payment will be undone and the replacement saved together. Both records will be kept.':'This saves a permanent linked record. The original record will be kept.',confirm:'Confirm and save',danger:true})))return;
            if(locked.value)return; saving.value=true;error.value='';absent.value=false;
            pending.value={kind:props.kind,customer:props.customerId,source:props.sourceId,id:form.id,...(props.kind==='correct'?{replacement:form.replacement}:{})};marker(pending.value);
            try {const result=await apiRequest(`${path()}/${suffix()[1]}`,{method:'POST',body:JSON.stringify({...frozen,review_fingerprint:review.value.review_fingerprint})});await showSaved(form.id,result.data);}
            catch(failure) {if(failure.code==='DEBT_IDENTITY_CONFLICT' || !failure.status || failure.status>=500 || [401,403,404].includes(failure.status)){phase.value='unknown';error.value='We could not confirm the saved result. Check saved status. Keep these references and do not start the same change again.';}else{pending.value=null;marker(null);phase.value='input';review.value=null;dependency.value=failure.details?.dependencies||null;validation.value={...(failure.details||{})};delete validation.value.dependencies;error.value=message(failure);}}
            finally{saving.value=false;}
        }
        async function checkStatus() {
            if(busy.value || saving.value || !online.value || !pending.value)return;busy.value=true;absent.value=false;
            try{await showSaved(pending.value.id);error.value='';}
            catch(failure){absent.value=failure.status===404;error.value=absent.value?'Not found at the time of this check. An earlier request may still finish. Continue only with the same references.':'Saved status is not available. Keep these references and check again when access and connection are restored.';}
            finally{busy.value=false;}
        }
        function resume(){if(absent.value && pending.value && allowed.value && !props.readOnly){phase.value='input';review.value=null;frozen=null;dirty.value=true;error.value='Use these same references. Review checks for a saved result before making another change.';}}
        async function more(){if(locked.value)return;busy.value=true;try{await loadBills(billPage.value+1);}catch(f){error.value=message(f);}finally{busy.value=false;}}
        const connection=()=>{online.value=navigator.onLine;};window.addEventListener('online',connection);window.addEventListener('offline',connection);
        watch([()=>props.customerId,()=>props.sourceId,()=>props.kind,view],()=>{frozen=null;initialize();},{immediate:true});
        onBeforeUnmount(()=>{alive=false;generation++;controller?.abort();window.removeEventListener('online',connection);window.removeEventListener('offline',connection);});
        function changeCard(r){return h('article',{class:'commercial-card',key:r.public_id},[h('h3',r.adjustment_type==='REVERSAL'?'Bill change undone':r.amount_delta.startsWith('-')?'Bill reduced':'Bill increased'),h('p',`${r.amount_delta.startsWith('-')?'−':'+'}${money(r.amount_delta.replace(/^-/,''))}`),h('p',`Reference: ${r.public_id}`),r.reversed_by_public_id&&h('p','This change was undone.'),action('View bill change',()=>go(`#customers/debt/${props.customerId}/adjustment/${r.public_id}`))].filter(Boolean));}
        function adjustmentReceipt(r){return h('article',{class:'commercial-card'},[h('h3','Bill change saved'),h('p',`Reference: ${r.public_id}`),facts([['Customer',r.customer_name],['Change',`${r.amount_delta.startsWith('-')?'−':'+'}${money(r.amount_delta.replace(/^-/,'') )}`],...(r.bill_balance_before!==undefined?[['Bill before this change',money(r.bill_balance_before)],['Bill after this change',money(r.bill_balance_after)]]:[])]),h('p',r.reason),r.reversed_by_public_id&&h('p','This change was undone.'),r.reversal_of_public_id&&action('View original bill change',()=>go(`#customers/debt/${props.customerId}/adjustment/${r.reversal_of_public_id}`)),!r.sales_owned&&!r.reversal_of_public_id&&!r.reversed_by_public_id&&has(props.permissions,'debt.adjust')&&action('Undo this bill change',()=>go(`#customers/debt/${props.customerId}/adjustment/${r.public_id}/reverse`),props.readOnly),has(props.permissions,'sale.view')&&action('View original sale',()=>go(`#sale/${r.sale_public_id}`))].filter(Boolean));}
        return()=>h('section',{class:'workspace commercial-workspace trading-screen ui-scope','aria-busy':busy.value||saving.value},[
            h('h2',titles[props.kind]),action('Back to customer bills',()=>confirmation.guard(()=>go(`#customers/debt/${props.customerId}`)),saving.value||busy.value,'btn-outline-primary',{icon:'back'}),...confirmation.render(),
            !online.value&&h(UiInformationPanel,{severity:'warning',message:'Connect before reviewing or saving a change. Nothing is queued.'}),!view.value&&h(UiInformationPanel,{severity:'warning',message:'You are not allowed to view these records.'}),
            mutation.value&&(!allowed.value||props.readOnly)&&h(UiInformationPanel,{severity:'warning',message:'You cannot save this change with your current access. Ask your shop owner for help.'}),
            error.value&&h(UiAlert,{severity:'error',message:error.value,persistent:true}),fieldSummary(validation.value,{reason:'correction-reason',amount:'correction-amount',amount_delta:'correction-amount'}),
            dependency.value?.sale_public_id&&has(props.permissions,'sale.view')&&action('Open sale with the linked return',()=>confirmation.guard(()=>go(`#sale/${dependency.value.sale_public_id}`)),busy.value||saving.value),
            (busy.value||saving.value)&&h(UiLoading,{label:saving.value?'Saving this change…':'Checking linked records…'}),
            !storageAvailable.value&&h(UiInformationPanel,{severity:'warning',message:'Keep this page open and copy the references. This browser cannot keep them after reload.'}),
            pending.value&&[h('p',`Change reference: ${pending.value.id}`),pending.value.replacement&&h('p',`Replacement reference: ${pending.value.replacement}`),action('Check saved status',checkStatus,busy.value||saving.value||!online.value,'btn-primary',{icon:'refresh'}),absent.value&&phase.value==='unknown'&&action('Continue with these same references',resume,busy.value||props.readOnly||!allowed.value)],
            view.value&&props.kind==='changes'&&[...records.value.map(changeCard),!busy.value&&!records.value.length&&h('p','No bill changes yet.'),pager(pagination,busy.value||!online.value,initialize)],
            view.value&&props.kind==='adjustment'&&source.value&&adjustmentReceipt(source.value),
            mutation.value&&source.value&&phase.value==='input'&&h('form',{class:'commercial-form',onSubmit:prepare},[
                h('h3',source.value.customer_name),h('p',`Original reference: ${props.sourceId}`),
                ['reverse','correct'].includes(props.kind)&&facts([['Original amount',money(source.value.amount)],['Originally paid by',stateLabel(source.value.channel)]]),
                props.kind==='adjust'&&h(UiInformationPanel,{message:'Use this only to correct a factual bill mistake. Explain what was wrong. For returned goods, use Return goods on the original sale. This does not record money received or change stock.'}),
                props.kind==='correct'&&[h('h3','Replacement payment'),field('correction-amount','Correct amount received (₦)',form.amount,v=>change('amount',v),{inputmode:'decimal',required:true,disabled:locked.value}),selectField('correction-channel','Correct payment method',form.channel,v=>change('channel',v),[['CASH','Cash'],['TRANSFER','Bank transfer']],{disabled:locked.value}),form.channel==='TRANSFER'&&field('correction-reference','Bank transfer reference (optional)',form.reference,v=>change('reference',v),{maxlength:120,disabled:locked.value}),
                    has(props.permissions,'debt.payment.allocate')&&selectField('correction-mode','Bills the replacement will cover',form.mode,v=>change('mode',v),[['OLDEST_FIRST','Pay oldest bills first'],['EXPLICIT','Choose bills']],{disabled:locked.value}),
                    form.mode==='EXPLICIT'&&[h('p','Enter the full replacement amount across your selected bills. Review includes the amount put back by undoing the original.'),...bills.value.map(b=>h('article',{class:'commercial-card',key:b.public_id},[h('label',[h('input',{type:'checkbox',checked:selections[b.public_id]!==undefined&&selections[b.public_id]!==null,disabled:locked.value,onChange:e=>{selections[b.public_id]=e.target.checked?'':null;dirty.value=true;}}),` Bill ${b.public_id}`]),h('p',`${money(b.outstanding_amount)} before undoing the original payment`),selections[b.public_id]!==undefined&&selections[b.public_id]!==null&&field(`correction-bill-${b.public_id}`,'Amount for this bill (₦)',selections[b.public_id],v=>{selections[b.public_id]=v;dirty.value=true;},{inputmode:'decimal',required:true,disabled:locked.value})])),billPage.value<billPages.value&&action('Load more bills',more,locked.value)]],
                props.kind==='adjust'&&[selectField('correction-type','How should the bill change?',form.type,v=>change('type',v),[['CORRECTION_DECREASE','Reduce the bill'],['CORRECTION_INCREASE','Increase the bill']],{disabled:locked.value}),field('correction-amount','Amount to change (₦)',form.amount,v=>change('amount',v),{inputmode:'decimal',required:true,disabled:locked.value})],
                field('correction-reason','Why is this change needed?',form.reason,v=>change('reason',v),{multiline:true,required:true,maxlength:500,disabled:locked.value}),submit('Review change',locked.value,{loading:busy.value,busyLabel:'Checking change…',icon:'next'}),
            ].filter(Boolean)),
            review.value&&phase.value==='review'&&h('section',{class:'commercial-card'},[
                h('h3','Review before saving'),h('p',form.reason),facts([['Customer',review.value.customer_name],['Customer total before',money(review.value.customer_balance_before)],['Customer total after',money(review.value.customer_balance_after)],...(review.value.bill_balance_after!==undefined?[['Bill before',money(review.value.bill_balance_before)],['Bill after',money(review.value.bill_balance_after)]]:[])]),
                props.kind==='correct'&&facts([['Replacement received',money(review.value.replacement_amount)],['Replacement paid by',stateLabel(review.value.replacement_channel)]]),
                ...(review.value.allocations||[]).map(a=>h('p',`Put back on bill ${a.receivable_public_id}: ${money(a.amount)}`)),...(review.value.replacement_allocations||[]).map(a=>h('p',`Replacement toward bill ${a.receivable_public_id}: ${money(a.amount)}`)),
                action('Save this change',save,locked.value,'btn-primary',{loading:saving.value,busyLabel:'Saving change…',icon:'success'}),action('Edit change',()=>{phase.value='input';review.value=null;},saving.value||busy.value),
            ]),
            receipt.value&&( ['reverse','correct'].includes(props.kind)?h(DebtPaymentReceipt,{payment:receipt.value,permissions:props.permissions,onNavigate:go}):adjustmentReceipt(receipt.value)),
        ].filter(Boolean));
    },
};
