import { intakesApi } from '../../api/intakes.js';
export function useIntakes({ref,reactive,computed},access,context,products){
 const list=ref([]),current=ref(null),loading=ref(false),pending=ref(false),error=ref(''),notice=ref(''),review=ref(false);const fields=reactive({}),pagination=reactive({page:1,per_page:20,total:0,total_pages:0});
 const header=reactive({intake_kind:'',reference:'',notes:'',occurred_at:'',device_local_offset_minutes:''});const line=reactive({public_id:null,product_public_id:'',quantity:'',unit_cost:'',line_number:1});
 const canWrite=computed(()=>context.permissions.includes('inventory.intake')&&access.mode==='FULL');
 const clear=()=>{error.value='';notice.value='';Object.keys(fields).forEach(k=>delete fields[k]);};const fail=e=>{error.value=e.message;Object.assign(fields,e.details??{});};
 async function load(page=pagination.page){loading.value=true;clear();try{const response=await intakesApi.list(page);list.value=response.data;Object.assign(pagination,response.meta.pagination??{});}catch(e){fail(e);}finally{loading.value=false;}}
 async function open(row){loading.value=true;clear();try{current.value=(await intakesApi.detail(row.public_id)).data;Object.assign(header,{intake_kind:current.value.intake_kind,reference:current.value.reference??'',notes:current.value.notes??'',occurred_at:current.value.occurred_at,device_local_offset_minutes:current.value.device_local_offset_minutes??''});review.value=false;}catch(e){fail(e);}finally{loading.value=false;}}
 function newDraft(kind){current.value=null;review.value=false;Object.assign(header,{intake_kind:kind,reference:'',notes:'',occurred_at:'',device_local_offset_minutes:''});clear();}
 async function create(){pending.value=true;clear();try{current.value=(await intakesApi.create(header)).data;notice.value='Draft saved.';await load();}catch(e){fail(e);}finally{pending.value=false;}}
 async function saveHeader(){pending.value=true;clear();try{current.value=(await intakesApi.update(current.value.public_id,header)).data;notice.value='Draft details saved.';}catch(e){fail(e);}finally{pending.value=false;}}
 function resetLine(){Object.assign(line,{public_id:null,product_public_id:'',quantity:'',unit_cost:'',line_number:(current.value?.items?.length??0)+1});}
 function editLine(item){Object.assign(line,{public_id:item.public_id,product_public_id:item.product_public_id,quantity:item.quantity,unit_cost:item.unit_cost,line_number:item.line_number});}
 async function saveLine(){pending.value=true;clear();try{const api=line.public_id?intakesApi.updateItem(current.value.public_id,line.public_id,line):intakesApi.addItem(current.value.public_id,line);current.value=(await api).data;resetLine();notice.value='Intake item saved.';}catch(e){fail(e);}finally{pending.value=false;}}
 async function removeLine(item){pending.value=true;clear();try{current.value=(await intakesApi.removeItem(current.value.public_id,item.public_id)).data;notice.value='Intake item removed.';}catch(e){fail(e);}finally{pending.value=false;}}
 async function post(){pending.value=true;clear();try{current.value=(await intakesApi.post(current.value.public_id)).data;review.value=false;notice.value='Stock Intake posted.';await load();}catch(e){fail(e);}finally{pending.value=false;}}
 async function discard(){pending.value=true;clear();try{await intakesApi.discard(current.value.public_id);current.value=null;notice.value='Draft discarded.';await load();}catch(e){fail(e);}finally{pending.value=false;}}
 function close(){current.value=null;review.value=false;header.intake_kind='';clear();}
 return{list,current,loading,pending,error,notice,review,fields,pagination,header,line,canWrite,load,open,newDraft,create,saveHeader,resetLine,editLine,saveLine,removeLine,post,discard,close,products};
}
