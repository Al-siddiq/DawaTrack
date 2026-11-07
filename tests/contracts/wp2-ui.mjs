// Optional mocked DOM check; install jsdom outside the project and set WP2_JSDOM_MODULE.
const { JSDOM } = await import(process.env.WP2_JSDOM_MODULE ?? 'jsdom');
import fs from 'node:fs';
import assert from 'node:assert/strict';
const repo=new URL('../../', import.meta.url).pathname.replace(/\/$/,'');
const dom=new JSDOM('<div id="test"></div>',{url:'https://shop.test/',runScripts:'outside-only'});
const {window}=dom;
for(const key of ['window','document','navigator','location','history','HTMLElement','Element','SVGElement','FormData'])Object.defineProperty(globalThis,key,{value:key==='window'?window:window[key],configurable:true});
window.eval(fs.readFileSync(`${repo}/node_modules/vue/dist/vue.runtime.global.prod.js`,'utf8'));
const {createApp,nextTick}=window.Vue;
const sleep=()=>new Promise(resolve=>setTimeout(resolve,10));
const settle=async()=>{await sleep();await nextTick();await sleep();};
let calls=[];
const uuid='01234567-89ab-4cde-8fab-0123456789ab',product='11234567-89ab-4cde-8fab-0123456789ab',item='21234567-89ab-4cde-8fab-0123456789ab';
let draft={public_id:uuid,status:'DRAFT',occurred_at:'2026-11-03 12:00:00.000000',updated_at:'2026-11-03 12:00:00.000001',total_amount:'0.00',items:[]};
let stale=false;
globalThis.fetch=async(path,options)=>{
 const body=options.body?JSON.parse(options.body):null; calls.push({path,method:options.method??'GET',body});
 if(stale && options.method==='PUT')return new Response(JSON.stringify({success:false,error:{code:'DRAFT_STALE',message:'This record changed. Reload its current state before editing.'}}),{status:409});
 let data,meta={};
 if(path.includes('/sale-draft-lookups/products'))data=[{public_id:product,name:'Paracetamol',unit_name:'pack',current_selling_price:'100.00'}];
 else if(path.includes('/sale-draft-lookups/customers'))data=[];
 else if(path.includes('/customers')){data=[];meta={pagination:{page:1,total_pages:1}};}
 else if(path.endsWith('/preview'))data={total_amount:'150.00',cash_amount:body.cash_amount,transfer_amount:body.transfer_amount,paid_amount:'100.00',credit_amount:'50.00',due_date:body.due_date,draft_updated_at:draft.updated_at};
 else if(path.endsWith('/items') && options.method==='POST'){assert.equal(body.expected_updated_at,draft.updated_at);assert.equal(body.quantity,'1.5');assert.equal(body.unit_selling_price,undefined);draft={...draft,total_amount:'150.00',updated_at:'2026-11-03 12:00:00.000002',items:[{public_id:item,product_public_id:product,product_name:'Paracetamol',unit_name:'pack',product_status:'ACTIVE',quantity:body.quantity,unit_selling_price:'100.00',current_selling_price:'100.00',line_sales_amount:'150.00',line_number:1}]};data=draft;}
 else if(path.match(/sale-drafts\?/)){data=[];meta={pagination:{page:1,total_pages:1}};}
 else data=draft;
 return new Response(JSON.stringify({success:true,data,meta}),{status:200});
};
const {CustomerPanel}=await import(`${repo}/resources/js/components/customer-panel.js`);
let app=createApp(CustomerPanel,{permissions:['customer.view','customer.manage'],readOnly:false});app.mount('#test');await settle();
const button=label=>[...document.querySelectorAll('button')].find(button=>button.textContent===label);
button('Create Customer').click();await settle();assert(document.querySelector('#customer-name'));assert.equal(document.querySelector('#customer-phone'),null);assert.equal(document.querySelector('#customer-notes'),null);app.unmount();
const {SaleDraftPanel}=await import(`${repo}/resources/js/components/sale-draft-panel.js`);
app=createApp(SaleDraftPanel,{permissions:['sale.view','sale.record'],readOnly:false});app.mount('#test');await settle();button('Start new Sale').click();await settle();
assert(location.hash===`#sale-draft/${uuid}`);assert(document.body.textContent.includes('No Products in this draft.'));
function input(id,value,event='input'){const el=document.getElementById(id);assert(el,id);el.value=value;el.dispatchEvent(new window.Event(event,{bubbles:true}));}
input('sale-product',product,'change');input('sale-quantity','1.5');document.getElementById('sale-quantity').closest('form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await settle();assert(document.body.textContent.includes('₦150.00'));
input('sale-cash_amount','75.00');input('sale-transfer_amount','25.00');input('sale-due_date','2026-12-01');document.getElementById('sale-cash_amount').closest('form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await settle();assert(document.body.textContent.includes('Preparation estimate'));
assert.equal(calls.find(c=>c.path.endsWith('/preview')).body.expected_updated_at,draft.updated_at);
input('sale-cash_amount','80.00');await settle();assert(!document.body.textContent.includes('Preparation estimate'));
button('Reload saved draft').click();await settle();assert.equal(document.getElementById('sale-cash_amount').value,'0.00');assert.equal(document.getElementById('sale-due_date').value,'');assert(document.body.textContent.includes('Paracetamol'));
button('Edit line').click();await settle();stale=true;input('sale-quantity','2');document.getElementById('sale-quantity').closest('form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await settle();assert(document.querySelector('[role=alert]').textContent.includes('Reload'));assert.equal(document.getElementById('sale-quantity').value,'2');
Object.defineProperty(window.navigator,'onLine',{value:false,configurable:true});window.dispatchEvent(new window.Event('offline'));await settle();assert(button('Save basket item').disabled);assert(button('Review Sale').disabled);
assert(!calls.some(c=>c.path.endsWith('/post')));app.unmount();
Object.defineProperty(window.navigator,'onLine',{value:true,configurable:true});
app=createApp(SaleDraftPanel,{permissions:['sale.view'],readOnly:true});app.mount('#test');await settle();assert(!button('Add to basket'));assert(!button('Discard draft'));assert(!button('Review Sale'));app.unmount();
console.log('PASS: mocked DOM customer privacy; persistent draft identity; exact-string basket input; versioned preview; transient reset; stale error retention; offline/read-only controls; no posting. This is not browser or HTTP acceptance.');
