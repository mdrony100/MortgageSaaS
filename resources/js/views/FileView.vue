<script setup lang="ts">
import {ref,watch,computed} from 'vue'
import {useRoute} from 'vue-router'
import {api} from '../api'
import {useAuth} from '../stores/auth'
import BankWorkspace from '../components/BankWorkspace.vue'
import AppraisalWorkspace from '../components/AppraisalWorkspace.vue'
import FileSteps from '../components/FileSteps.vue'
import DataPanel from '../components/DataPanel.vue'
import {journeyFor,roleName} from '../customerLanguage'
import {ArrowLeft,Upload,Share2,Archive,CheckCircle2} from 'lucide-vue-next'

const route=useRoute(),auth=useAuth(),file=ref<any>(),error=ref(''),success=ref(''),busy=ref(false),activity=ref<any[]>([]),history=ref<any[]>([]),members=ref<any[]>([]),report=ref<any>(),shareUser=ref(''),note=ref(''),visibility=ref('shared'),upload=ref<File>(),fictional=ref(false),replace=ref(''),rename=ref(''),module=ref('bank')
const draft=ref<any>({statement_months:12,account_type:'business',expense_method:'standard',expense_ratio:'40.00',appraisal_form:'1004',units:1,occupancy:'investment',rent:'3300.00',pitia:'3000.00',loan_amount:'250000.00',value:'325000.00'})
const workspaceStatus=ref<{documents:number,runStatus:string|null,hasReport:boolean,errorCode?:string|null}|null>(null)
const intent=ref<{action:string,n:number}|null>(null)
const celebrate=ref(false)
let intentN=0
const endpoint=computed(()=>'/api/files/'+route.params.id)
const docCount=computed(()=>(file.value?.documents||[]).filter((d:any)=>d.category!=='fixture').length)
const journey=computed(()=>journeyFor({
  documents:workspaceStatus.value?.documents??docCount.value,
  runStatus:workspaceStatus.value?.runStatus??null,
  hasReport:!!workspaceStatus.value?.hasReport,
  archived:!!file.value?.archived_at,
  fileStatus:file.value?.status,
  errorCode:workspaceStatus.value?.errorCode??null,
  module:module.value,
}))
const canUpload=computed(()=>!!(file.value?.permissions.edit||file.value?.permissions.upload))

async function load(){const scope=endpoint.value;try{const result=await api(scope);const [events,reviews,candidates]=await Promise.all([api(scope+'/activity'),result.permissions.review?api(scope+'/review-history'):Promise.resolve([]),result.permissions.share?api(scope+'/share-candidates'):Promise.resolve([])]);if(scope!==endpoint.value)return;file.value=result;rename.value=result.name;draft.value={...draft.value,...result.draft_inputs};activity.value=events;history.value=reviews;members.value=candidates}catch(e:any){if(scope!==endpoint.value)return;error.value=e.message;if([401,403,404].includes(e.status))file.value=null}}
watch(()=>route.params.id,async()=>{file.value=null;workspaceStatus.value=null;intent.value=null;celebrate.value=false;activity.value=[];history.value=[];members.value=[];upload.value=undefined;replace.value='';fictional.value=false;note.value='';visibility.value='shared';shareUser.value='';error.value='';success.value='';busy.value=false;draft.value={statement_months:12,account_type:'business',expense_method:'standard',expense_ratio:'40.00',appraisal_form:'1004',units:1,occupancy:'investment',rent:'3300.00',pitia:'3000.00',loan_amount:'250000.00',value:'325000.00'};await load();if(file.value)module.value=file.value.module},{immediate:true})
watch(module,()=>{workspaceStatus.value=null;intent.value=null})
watch(()=>journey.value.phase,(next,prev)=>{if(prev==='processing'&&(next==='review'||next==='export')){celebrate.value=true;window.setTimeout(()=>{celebrate.value=false},2400)}})
async function action(fn:()=>Promise<any>,message:string){const scope=endpoint.value;busy.value=true;error.value='';success.value='';try{await fn();if(scope!==endpoint.value)return;success.value=message;await load()}catch(e:any){if(scope===endpoint.value)error.value=e.message}finally{if(scope===endpoint.value)busy.value=false}}
async function sample(){await action(()=>api(endpoint.value+'/sample-analysis','POST',{idempotency_key:'fictional-v1'}),'Fictional sample requested. It appears when it finishes. This does not read your uploads.')}
async function addDocument(){if(!upload.value)return;const fd=new FormData();fd.append('file',upload.value);fd.append('fictional_sample',fictional.value?'1':'0');await action(()=>api(replace.value?'/api/documents/'+replace.value+'/versions':endpoint.value+'/documents','POST',fd),'Document saved. Next, start analysis.');upload.value=undefined}
async function previewReport(){const scope=endpoint.value;try{const next=await api(scope+'/reports/'+module.value);if(scope===endpoint.value)report.value=next}catch(e:any){if(scope===endpoint.value)error.value=e.message}}
function onStatus(status:any){workspaceStatus.value=status}
function nextAction(){const phase=journey.value.phase;const actionName=phase==='failed'?'retry':phase==='review'?'review':phase==='export'?'export':phase==='start'?'start':'';if(!actionName)return;intent.value={action:actionName,n:++intentN}}
function setModule(next:string){module.value=next}
</script>
<template>
<div class="customer-file">
<RouterLink to="/dashboard" class="back-link"><ArrowLeft :size="16"/>All loan files</RouterLink>
<p v-if="error" role="alert" class="alert error">{{error}}</p>
<p v-if="success" role="status" class="alert success">{{success}}</p>
<template v-if="file">
<div class="page-heading">
  <div>
    <div class="inline">
      <span v-if="file.is_demo" class="badge amber">Practice file</span>
      <span class="badge" :class="journey.badge.tone">{{journey.badge.label}}</span>
    </div>
    <h1>{{file.name}}</h1>
    <p class="muted">{{file.borrower.name}}<template v-if="file.reference"> · {{file.reference}}</template> · {{roleName(auth.membership?.role)}}</p>
  </div>
</div>
<FileSteps :current="journey.step" :settled="journey.settled" :failed="journey.failed"/>
<section class="next-step-card" :class="{celebrate,'is-failed':journey.failed}" aria-live="polite">
  <p class="eyebrow">Do this next</p>
  <h2>{{journey.title}}</h2>
  <p>{{journey.body}}</p>
  <p v-if="celebrate" class="success-line" role="status">Analysis finished. Review the findings, then export when you are ready.</p>
  <div v-if="journey.phase==='processing'" class="process-track" role="progressbar" aria-label="Documents are being read"><span class="process-bar"></span></div>
  <form v-if="journey.phase==='upload'&&canUpload" class="form-stack upload-form" @submit.prevent="addDocument">
    <label>PDF, PNG, or JPG, up to 20 MB<input type="file" accept=".pdf,.png,.jpg,.jpeg" required @change="upload=($event.target as HTMLInputElement).files?.[0]"/></label>
    <label v-if="file.is_demo" class="check"><input type="checkbox" v-model="fictional"/>These documents are a practice sample, not a real borrower's documents</label>
    <button class="primary" :disabled="busy"><Upload :size="16"/>Upload documents</button>
  </form>
  <p v-else-if="journey.phase==='upload'" class="muted">You can view this file. Someone with upload access needs to add the documents.</p>
  <button v-else-if="journey.cta" class="primary" type="button" :disabled="busy" @click="nextAction">{{journey.cta}}</button>
</section>
<div class="module-switch" aria-label="What this file is reviewing">
  <button v-for="[key,name] in [['bank','Bank statements'],['appraisal','Appraisal'],['dscr','Rental income']]" :key="key" :class="{active:module===key}" @click="setModule(key)">{{name}}</button>
</div>
<BankWorkspace v-if="module==='bank'" :file="file" :guided="true" :intent="intent" @refresh="load" @status="onStatus"/>
<AppraisalWorkspace v-else :file="file" :module="module" :guided="true" :intent="intent" @refresh="load" @status="onStatus"/>

<details class="more-tools">
  <summary>File name, borrower, and archive</summary>
  <div class="card form-stack">
    <label>File name<input v-model="rename"/></label>
    <div class="actions">
      <button v-if="file.permissions.edit" @click="action(()=>api(endpoint,'PATCH',{name:rename}),'File name saved.')">Save name</button>
      <button v-if="file.permissions.edit" @click="action(()=>api(endpoint,'PATCH',{archived:!file.archived_at}),file.archived_at?'File restored.':'File archived.')"><Archive :size="16"/>{{file.archived_at?'Restore file':'Archive file'}}</button>
    </div>
    <label>Borrower name<input v-model="file.borrower.name"/></label>
    <label>Borrower email<input v-model="file.borrower.email" type="email"/></label>
    <label>Borrower phone<input v-model="file.borrower.phone"/></label>
    <button v-if="file.permissions.edit" @click="action(()=>api('/api/organizations/'+file.organization_id+'/borrowers/'+file.borrower.id,'PATCH',file.borrower),'Borrower updated.')">Save borrower</button>
  </div>
</details>
<details class="more-tools">
  <summary>Draft numbers</summary>
  <div class="alert">Saved drafts only. Changing these values does not run financial or document analysis.</div>
  <form class="form-grid" @submit.prevent="action(()=>api(endpoint,'PATCH',{draft_inputs:draft}),'Draft numbers saved. No calculation was run.')">
    <template v-if="module==='bank'">
      <label>Statement history<select v-model="draft.statement_months"><option :value="12">12 months</option><option :value="24">24 months</option></select></label>
      <label>Account type<select v-model="draft.account_type"><option value="personal">Personal</option><option value="business">Business</option></select></label>
      <label>Expense approach<select v-model="draft.expense_method"><option value="standard">Standard (draft)</option><option value="custom">Custom (draft)</option></select></label>
      <label>Expense ratio (%)<input v-model="draft.expense_ratio" type="number" min="0" max="100" step="0.01"/></label>
    </template>
    <template v-else-if="module==='appraisal'">
      <label>Appraisal form<select v-model="draft.appraisal_form"><option v-for="form in ['1004','1025','1007','216']" :key="form">{{form}}</option></select></label>
      <label>Units<input v-model.number="draft.units" type="number" min="1" max="4"/></label>
      <label>Occupancy<select v-model="draft.occupancy"><option value="owner_occupied">Owner occupied</option><option value="second_home">Second home</option><option value="investment">Investment</option></select></label>
    </template>
    <template v-else>
      <label>Monthly rent ($)<input v-model="draft.rent" type="number" min="0" step="0.01"/></label>
      <label>Monthly PITIA ($)<input v-model="draft.pitia" type="number" min="0" step="0.01"/></label>
      <label>Loan amount ($)<input v-model="draft.loan_amount" type="number" min="0" step="0.01"/></label>
      <label>Property value ($)<input v-model="draft.value" type="number" min="0" step="0.01"/></label>
      <p class="data-wide muted">This draft does not run financial or document analysis. Fixed sample examples: 3300 ÷ 3000 = 1.10; 2850 ÷ 3000 = 0.95.</p>
    </template>
    <button v-if="file.permissions.edit" class="primary" :disabled="busy">Save draft numbers</button>
    <p v-else class="muted">Your role can view these numbers, not change them.</p>
  </form>
</details>
<details v-if="file.is_demo&&file.permissions.edit" class="practice-tools more-tools">
  <summary>Practice tools</summary>
  <p>Load fictional sample results to look around. This does not read your uploads, and it is not a loan decision.</p>
  <div class="actions">
    <button type="button" :disabled="busy" @click="sample">Load a fictional sample</button>
    <button type="button" @click="previewReport">Preview a sample report</button>
  </div>
  <div v-if="file.runs.length" class="run-strip"><span v-for="r in file.runs" :key="r.id" class="badge">Sample {{r.status}}</span></div>
</details>
<section class="card spaced"><details><summary>Shared file notes and audit history</summary>
  <article v-for="n in file.notes" :key="n.id" class="note-card"><span class="badge">{{n.visibility}}</span><p>{{n.body}}</p><small>Member {{n.user_id}} · {{new Date(n.created_at).toLocaleString('en-US',{timeZone:'Asia/Dhaka'})}} (Dhaka)</small></article>
  <p v-if="!file.notes.length">No shared file notes yet.</p>
  <form v-if="auth.membership?.role!=='viewer'" class="form-stack" @submit.prevent="action(()=>api(endpoint+'/notes','POST',{body:note,visibility:file.permissions.review?visibility:'shared'}),'Shared file note added.').then(()=>note='')"><label>Shared file note<textarea v-model="note" maxlength="5000" required/></label><label v-if="file.permissions.review">Visibility<select v-model="visibility"><option value="shared">Shared with authorized file users</option><option value="internal">Internal reviewers only</option></select></label><button :disabled="busy">Save file note</button></form>
  <h3>Authorized file activity</h3>
  <div v-for="a in activity" :key="a.id" class="activity-row"><CheckCircle2 :size="17"/><div><strong>{{a.action.replaceAll('.',' · ')}}</strong><small>Member {{a.actor_id||'system'}} · {{new Date(a.created_at).toLocaleString('en-US',{timeZone:'Asia/Dhaka'})}} (Dhaka)</small></div></div>
  <p v-if="!activity.length" class="muted">No activity yet.</p>
  <h3 v-if="file.permissions.review">Reviewer history</h3>
  <DataPanel v-if="file.permissions.review" :data="history"/>
</details></section>
<section v-if="!file.permissions.review" class="card spaced"><details><summary>Shared private originals</summary>
  <p>Original access follows your role. Sharing this file does not show reviewer-only findings.</p>
  <form v-if="file.permissions.upload" class="form-stack" @submit.prevent="addDocument"><label>Private PDF, JPEG, or PNG<input type="file" accept=".pdf,.png,.jpg,.jpeg" required @change="upload=($event.target as HTMLInputElement).files?.[0]"/></label><label v-if="file.is_demo" class="check"><input type="checkbox" v-model="fictional"/>This upload is a practice sample</label><button :disabled="busy">Preserve private original</button></form>
  <article v-for="d in file.documents" :key="d.id" class="document-card"><h3>{{d.title}}</h3><div v-for="v in d.versions" :key="v.id"><span>Version {{v.version}} · {{v.original_name}} · {{v.scan_status}}</span> <a class="button" :href="'/api/document-versions/'+v.id+'/content'">Authorized original</a></div></article>
</details></section>
<section class="card sharing-card" v-if="file.permissions.share"><h2><Share2 :size="20"/>Share this file</h2><p class="muted">Choose someone already on your team. Sharing this file does not change their role, and brokers still cannot see reviewer-only findings.</p><form class="inline" @submit.prevent="action(()=>api(endpoint+'/shares','POST',{user_id:Number(shareUser)}),'File shared with the selected person.')"><label>Member<select v-model="shareUser" required><option value="">Select a member</option><option v-for="m in members.filter(m=>m.user_id!==auth.user.id)" :key="m.user_id" :value="m.user_id">{{m.user.name}} · {{roleName(m.role)}}</option></select></label><button :disabled="busy" class="primary">Share file</button></form><div v-for="s in file.shares" :key="s.id" class="version-row"><span>Member {{s.user_id}} · {{s.revoked_at?'Revoked':'Active'}}</span><button v-if="!s.revoked_at" @click="action(()=>api(endpoint+'/shares/'+s.id,'DELETE'),'Share revoked.')">Revoke</button></div></section>
</template>
<p v-else-if="!error" role="status">Loading loan file…</p>
<div v-if="report" class="modal-backdrop" @click.self="report=null"><section class="modal report-modal" v-focus-trap="()=>report=null" role="dialog" aria-modal="true" aria-label="Sample report preview"><div class="section-heading"><h2>{{report.title}}</h2><button @click="report=null">Close</button></div><p class="alert warning">{{report.notice}}</p><h3>{{report.borrower}} · {{report.file}}</h3><DataPanel :data="report.data"/><p class="muted">{{report.downloads}}</p></section></div>
</div>
</template>
