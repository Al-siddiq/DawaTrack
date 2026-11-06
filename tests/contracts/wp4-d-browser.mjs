// Real Chromium rendering and interactions with mocked API, NOT native authentication/database acceptance.
// Install optional playwright-core and axe-core outside the project; point environment variables at them.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
const { chromium } = await import(process.env.WP4_PLAYWRIGHT_MODULE ?? 'playwright-core');
const repo = path.resolve(new URL('../../', import.meta.url).pathname);
const server = http.createServer((req,res) => {
 if(req.url.split('?')[0] === '/__wp4d') {res.setHeader('Content-Type','text/html');res.end(`<!doctype html><html lang="en"><head><title>WP4-D panel verification</title><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/app.css"></head><body><main class="container" id="test"></main><script src="/assets/vendor/vue/vue.runtime.global.prod.js"></script><script type="module">import {SaleHistoryPanel} from '/assets/js/components/sale-history-panel.js';window.app=Vue.createApp(SaleHistoryPanel,{permissions:['sale.view','sale.return','sale.reverse','sale.profit.view'],readOnly:false});window.app.mount('#test');</script></body></html>`);return;}
 const filename=path.resolve(repo,'public','.'+decodeURIComponent(req.url.split('?')[0]));
 if(!filename.startsWith(path.join(repo,'public')+path.sep)||!fs.existsSync(filename)){res.statusCode=404;res.end();return;}
 res.setHeader('Content-Type',filename.endsWith('.js')?'text/javascript':filename.endsWith('.css')?'text/css':'text/plain');res.end(fs.readFileSync(filename));
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const base=`http://127.0.0.1:${server.address().port}`;
const browser=await chromium.launch({executablePath:process.env.WP4_CHROMIUM ?? '/usr/bin/chromium',headless:true,args:['--no-sandbox']});
const S='11234567-89ab-4cde-8fab-0123456789ab',I='21234567-89ab-4cde-8fab-0123456789ab';
let scenarios=0;
try {
 for(const viewport of [{width:1280,height:900},{width:390,height:844}]){
  const page=await browser.newPage({viewport});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  let draft=null,comp=null,posted=false,posts=0,reversals=0,lost=true,lostReversal=true,created=0,replacement=null;
  const line={public_id:I,original_sale_item_public_id:null,product_name:'Test medicine',unit_name:'pack',quantity:'2.0000',unit_selling_price:'100.00',line_sales_amount:'200.00',unit_cost_snapshot:'60.000000',line_cogs_amount:'120.00',gross_profit:'80.00',cost_basis_state:'VALID',movement_public_id:S,quantity_before:'3.0000',quantity_after:'1.0000',movement_posted_at:'2026-11-06 10:00:00.000000'};
  const common={status:'POSTED',currency_code:'NGN',customer_name:'Alice',posted_by_name:'Owner',occurred_at:'2026-11-06 10:00:00.000000',posted_at:'2026-11-06 10:00:01.000000',total_amount:'200.00',subtotal_amount:'200.00',profit_visible:true,total_cogs_amount:'120.00',gross_profit:'80.00',gross_profit_effect:'80.00',credit_adjustments:[],receivable:null,items:[line],payments:[{public_id:S,channel:'CASH',direction:'RECEIPT',amount:'200.00'}]};
  const source=()=>({...common,public_id:S,kind:'SALE',operation_type:'SALE',received_amount_snapshot:'200.00',credit_created_amount_snapshot:'0.00',returns:draft?[{public_id:draft.public_id,status:posted?'POSTED':'DRAFT',total_amount:posted?'200.00':'0.00',reversal_public_id:comp?.public_id}]:[]});
  const eligibility=[{public_id:I,product_name:'Test medicine',unit_name:'pack',remaining_quantity:'2.0000',original_quantity:'2.0000',unit_selling_price:'100.00'}];
  const draftView=d=>({...d,original_items:eligibility,customer_name:'Alice'});
  const returnReceipt=()=>({...common,public_id:draft.public_id,kind:'RETURN',operation_type:'RETURN',original_sale_public_id:S,reversal_public_id:comp?.public_id??null,replacement:replacement?{public_id:replacement.public_id,kind:'RETURN',status:'DRAFT'}:null,received_amount_snapshot:null,credit_created_amount_snapshot:null,gross_profit_effect:'-80.00',reason:'Customer returned the selected medicine.',items:[{...line,public_id:draft.items[0].public_id,original_sale_item_public_id:I,movement_public_id:draft.public_id,quantity_before:'1.0000',quantity_after:'3.0000'}],payments:[{public_id:I,direction:'REFUND',channel:'CASH',amount:'200.00'}]});
  await page.route('**/api/v1/shop/**',async route=>{
   const req=route.request(),url=new URL(req.url()),body=req.postData()?JSON.parse(req.postData()):{};let data;
   if(url.pathname.endsWith('/returns')&&req.method()==='POST'){created++;draft={public_id:body.public_id,kind:'RETURN',status:'DRAFT',updated_at:'v1',items:[],payments:[]};data=draft;}
   else if(url.pathname.endsWith('/replacement')){replacement={public_id:body.public_id,kind:'RETURN',status:'DRAFT',updated_at:'v3',items:draft.items,payments:[]};data={public_id:replacement.public_id,kind:'RETURN',status:'DRAFT',draft:replacement};}
   else if(req.method()==='PUT'){assert.equal(body.items[0].quantity,'2');draft={...draft,updated_at:'v2',items:[{...body.items[0],quantity:'2.0000',unit_selling_price:'100.00',line_sales_amount:'200.00'}]};data=draft;}
   else if(url.pathname.endsWith('/estimate'))data={total_amount:'200.00',debt_reduction:'0.00',refund_amount:'200.00',cash_capacity:'200.00',transfer_capacity:'0.00'};
   else if(url.pathname.endsWith('/reversal-preview'))data={source_kind:'RETURN',cash_amount:'200.00',transfer_amount:'0.00',debt_amount:'0.00',money_direction:'RECEIPT',debt_direction:'RESTORE',line_count:1,review_fingerprint:'reverse'};
   else if(url.pathname.endsWith('/preview'))data={total_amount:'200.00',debt_reduction:'0.00',refund_amount:'200.00',cash_amount:body.cash_amount,transfer_amount:body.transfer_amount,review_fingerprint:'review'};
   else if(url.pathname.endsWith('/post')){posts++;posted=true;if(lost){lost=false;await route.abort('failed');return;}data=returnReceipt();}
   else if(url.pathname.endsWith('/reverse')){reversals++;comp={...returnReceipt(),public_id:body.public_id,kind:'SALE',operation_type:'COMPENSATION',reversal_public_id:null,replacement:null,reversal_of_public_id:draft.public_id,gross_profit_effect:'80.00',items:[{...returnReceipt().items[0],movement_public_id:body.public_id,quantity_before:'3.0000',quantity_after:'1.0000'}],payments:[{public_id:I,channel:'CASH',direction:'RECEIPT',amount:'200.00'}]};if(lostReversal){lostReversal=false;await route.abort('failed');return;}data=comp;}
   else if(url.pathname.endsWith('/sales')){data=[source(),...(posted?[returnReceipt()]:[])];await route.fulfill({json:{success:true,data,meta:{pagination:{page:1,total_pages:1}}}});return;}
   else if(url.pathname.endsWith(S))data=source();
   else if(comp&&url.pathname.endsWith(comp.public_id))data=comp;
   else if(replacement&&url.pathname.endsWith(replacement.public_id))data=draftView(replacement);
   else if(draft&&url.pathname.endsWith(draft.public_id))data=posted?returnReceipt():draftView(draft);
   else throw new Error('Unhandled mocked API '+req.method()+' '+url.pathname);
   await route.fulfill({json:{success:true,data,meta:{}}});
  });
  const btn=name=>page.getByRole('button',{name,exact:true});
  const noOverflow=async()=>assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Horizontal overflow '+viewport.width);
  const axe=async()=>{if(!process.env.WP4_AXE_SOURCE)return;await page.addScriptTag({path:process.env.WP4_AXE_SOURCE});const result=await page.evaluate(async()=>axe.run(document.querySelector('main'),{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));assert.deepEqual(result.violations.filter(v=>['serious','critical'].includes(v.impact)).map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})),[]);};
  await page.goto(base+'/__wp4d');await btn('View receipt').first().click();await page.getByRole('heading',{name:'Sale receipt',exact:true}).waitFor();assert.equal(await page.evaluate(()=>document.activeElement.tagName),'H1');await btn('Return Products from this Sale').click();await page.getByRole('heading',{name:'1. Choose returned quantities'}).waitFor();await noOverflow();await axe();
  await page.getByLabel('Quantity to return (leave blank for none)').fill('2');await btn('Save returned quantities').click();await btn('Calculate settlement').click();await page.getByLabel('Cash refund (₦)',{exact:true}).fill('200.00');await page.getByLabel('Return reason',{exact:true}).fill('Customer returned selected medicine.');await btn('Review Return').click();await page.getByText('4. Confirm the Return',{exact:true}).waitFor();assert(await btn('Confirm and post Return').isDisabled());await page.getByRole('checkbox').check();await btn('Confirm and post Return').click();await btn('Check Return status').waitFor();assert(await btn('Save returned quantities').isDisabled());await btn('Check Return status').click();await page.getByRole('heading',{name:'Return receipt',exact:true}).waitFor();assert.equal(posts,1);assert.equal(created,1);await noOverflow();await axe();
  await page.getByText('Reverse this operation',{exact:true}).click();await page.getByLabel('Correction reason',{exact:true}).fill('Wrong Return recorded, restore original evidence.');await btn('Review reversal').click();assert(await btn('Confirm reversal').isDisabled());await page.getByRole('checkbox').check();await btn('Confirm reversal').click();await btn('Check reversal status').waitFor();await btn('Check reversal status').click();await page.getByRole('heading',{name:'Reversal receipt',exact:true}).waitFor();assert.equal(reversals,1);assert(!await page.getByText('Credit created:',{exact:false}).count());await noOverflow();await axe();
  // The actual print action clones only the permission-filtered receipt and opens stock details.
  await page.evaluate(()=>{window.print=()=>{window.printMarkup=document.querySelector('#commercial-print-receipt').outerHTML;};});await btn('Print receipt').click();const markup=await page.evaluate(()=>window.printMarkup);assert(markup.includes('Linked stock evidence'));assert(markup.includes('Reversal receipt'));assert(!markup.includes('Related records and corrections'));
  if(process.env.WP4_SCREENSHOT_DIR){fs.mkdirSync(process.env.WP4_SCREENSHOT_DIR,{recursive:true});await page.screenshot({path:path.join(process.env.WP4_SCREENSHOT_DIR,`receipt-${viewport.width}.png`),fullPage:true});}
  await btn('View compensated operation').click();await page.getByRole('heading',{name:'Return receipt',exact:true}).waitFor();await page.getByText('Prepare a replacement',{exact:true}).click();await page.getByLabel('Correction reason',{exact:true}).fill('Prepare replacement Return separately.');await btn('Create linked replacement draft').click();await page.getByRole('heading',{name:'1. Choose returned quantities'}).waitFor();await noOverflow();assert.equal(await page.getByLabel('Quantity to return (leave blank for none)').inputValue(),'2.0000');
  // Keyboard controls and phone/desktop viewports use the same real components and CSS.
  await page.keyboard.press('Tab');assert(await page.evaluate(()=>document.activeElement!==document.body));assert.deepEqual(errors,[]);scenarios++;await page.close();
 }
 console.log(`PASS: ${scenarios} Chromium viewports: integrated Return/save/review/confirmation, lost Return/reversal recovery, replacement wrapper normalization, focus/keyboard, no horizontal overflow, isolated print markup and serious/critical axe checks. Mocked APIs; no native authentication/SQL/concurrency acceptance.`);
} finally {await browser.close();await new Promise(r=>server.close(r));}
