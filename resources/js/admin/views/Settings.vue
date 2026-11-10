<script setup lang="ts">
import {reactive,ref,onMounted} from 'vue';
import {api} from '../util';

type Field={key:string,label:string,secret?:boolean,kind?:'text'|'bool'|'select'|'number',options?:{value:string,label:string}[],hint?:string,fallback?:string|boolean|number|null};
type Group={id:string,title:string,lead:string,check?:string,email?:boolean,fields:Field[]};
const groups:Group[]=[
 {id:'support',title:'Log in as customer',lead:'Turns on the existing support session. The time limit and document-download switch override the server environment, including when that environment has sessions turned off.',fields:[
  {key:'impersonation_enabled',label:'Allow a platform admin to log in as a customer',kind:'bool'},
  {key:'impersonation_minutes',label:'Session length (minutes)',kind:'number',hint:'5 to 240. Leave blank to use the server environment.'},
  {key:'impersonation_allow_document_download',label:'Allow original document downloads during a support session',kind:'bool'},
 ]},
 {id:'openai',title:'OpenAI',lead:'Bank statement and appraisal vision models. Approval must be on before a run may call the provider.',check:'openai',fields:[
  {key:'openai_approved',label:'Approve OpenAI for bank statements',kind:'bool'},
  {key:'openai_api_key',label:'OpenAI API key',secret:true},
  {key:'openai_model',label:'Bank model',kind:'text'},
  {key:'openai_secondary_model',label:'Bank fallback model',kind:'text'},
  {key:'appraisal_vision_approved',label:'Approve appraisal vision',kind:'bool'},
  {key:'appraisal_vision_model',label:'Appraisal vision model',kind:'text'},
 ]},
 {id:'integrity',title:'Ocrolus integrity',lead:'Optional integrity provider. Leave the token blank to keep the current value.',fields:[
  {key:'integrity_approved',label:'Approve the integrity provider',kind:'bool'},
  {key:'integrity_provider',label:'Provider',kind:'select',options:[{value:'disabled',label:'Disabled'},{value:'ocrolus',label:'Ocrolus'}]},
  {key:'ocrolus_token',label:'Ocrolus access token',secret:true},
 ]},
 {id:'billing',title:'Billing',lead:'Test mode uses the sandbox provider and does not charge cards. Live mode stores the provider and keys for an approved adapter.',fields:[
  {key:'billing_provider',label:'Provider',kind:'select',options:[{value:'disabled',label:'Disabled'},{value:'fake',label:'Test provider'},{value:'stripe',label:'Stripe'}]},
  {key:'billing_mode',label:'Mode',kind:'select',options:[{value:'test',label:'Test'},{value:'live',label:'Live'}]},
  {key:'billing_secret',label:'Billing secret key',secret:true},
  {key:'billing_webhook_secret',label:'Webhook secret',secret:true},
 ]},
 {id:'mail',title:'Email',lead:'SMTP settings override the server environment. The test email goes only to your sign-in address.',check:'smtp',email:true,fields:[
  {key:'mail_mailer',label:'Mailer',kind:'select',options:[{value:'smtp',label:'SMTP'},{value:'log',label:'Log'},{value:'sendmail',label:'Sendmail'},{value:'array',label:'Array (tests)'}]},
  {key:'mail_host',label:'Host',kind:'text'},
  {key:'mail_port',label:'Port',kind:'number'},
  {key:'mail_scheme',label:'Scheme',kind:'text',hint:'Leave blank to use the server environment. Common values are smtp and smtps.'},
  {key:'mail_username',label:'Username',kind:'text'},
  {key:'mail_password',label:'Password',secret:true},
  {key:'mail_from_address',label:'From address',kind:'text'},
  {key:'mail_from_name',label:'From name',kind:'text'},
 ]},
 {id:'storage',title:'Storage',lead:'Private documents stay on the local disk unless you switch the disk to S3 and save a bucket.',check:'storage',fields:[
  {key:'storage_disk',label:'Private document disk',kind:'select',options:[{value:'private',label:'Local private disk'},{value:'s3',label:'S3'}]},
  {key:'s3_key',label:'S3 access key',secret:true},
  {key:'s3_secret',label:'S3 secret key',secret:true},
  {key:'s3_region',label:'Region',kind:'text'},
  {key:'s3_bucket',label:'Bucket',kind:'text'},
  {key:'s3_endpoint',label:'Endpoint',kind:'text'},
  {key:'s3_path_style',label:'Use path-style endpoint',kind:'bool'},
 ]},
 {id:'clamav',title:'ClamAV',lead:'Checks that the scanner binary is present. A missing scanner leaves uploads Unscanned.',check:'clamav',fields:[
  {key:'clamav_path',label:'ClamAV path',kind:'text',hint:'A command name on PATH, or a full path to clamscan.'},
 ]},
 {id:'python',title:'Parser',lead:'Bubblewrap is the secure default. Production will not read documents without it unless you explicitly choose no sandbox.',check:'python',fields:[
  {key:'bank_python',label:'Python path',kind:'text'},
  {key:'parser_sandbox',label:'Parser sandbox',kind:'select',options:[{value:'bwrap',label:'Bubblewrap (recommended)'},{value:'none',label:'No OS sandbox'}]},
 ]},
 {id:'website',title:'Public website',lead:'Contact details and the legal company name shown on the public site.',fields:[
  {key:'website_contact_email',label:'Contact email',kind:'text'},
  {key:'website_contact_phone',label:'Contact phone',kind:'text'},
  {key:'website_legal_company_name',label:'Legal company name',kind:'text'},
 ]},
];

const settings=ref<Record<string,any>>({});
const form=reactive<Record<string,any>>({});
const typed=reactive<Record<string,string>>({});
const clears=reactive<Record<string,boolean>>({});
const checks=reactive<Record<string,{ok:boolean,message:string}|null>>({});
const parser=ref<any>(null);
const error=ref(''),success=ref(''),busy=ref(false),emailBusy=ref(false);

function status(key:string){
 const s=settings.value[key];
 if(!s?.secret)return s?.source==='database'?'Saved · overrides the server environment':'Using the server environment';
 if(clears[key])return 'Saved value will be removed. The server environment will be used again.';
 if(!s.set)return 'Not set';
 return (s.source==='database'?'Saved':'From server environment')+' · ends in '+s.last4;
}
async function load(){
 error.value='';
 const d=await api('/api/admin/settings');
 settings.value=d.settings;parser.value=d.parser||null;
 for(const g of groups)for(const f of g.fields){
  if(f.secret){typed[f.key]='';clears[f.key]=false;continue}
  const value=d.settings[f.key]?.value;
  form[f.key]=value==null||value===''?(f.kind==='bool'?false:(f.fallback??'')):value;
 }
}
async function save(){
 busy.value=true;error.value='';success.value='';
 const body:Record<string,unknown>={};
 for(const g of groups)for(const f of g.fields){
  if(f.secret){if(typed[f.key].trim())body[f.key]=typed[f.key].trim();else if(clears[f.key])body['clear_'+f.key]=true}
  else body[f.key]=form[f.key]===''?null:form[f.key];
 }
 try{
  const d=await api('/api/admin/settings','PUT',body);
  settings.value=d.settings;parser.value=d.parser||null;
  for(const k of Object.keys(typed))typed[k]='';
  for(const k of Object.keys(clears))clears[k]=false;
  success.value=d.changed?.length?'Saved '+d.changed.length+' change'+(d.changed.length===1?'':'s')+'. Secret values stay encrypted and are not shown again.':'No changes to save.';
 }catch(e:any){error.value=e.message}finally{busy.value=false}
}
async function runCheck(check:string){
 error.value='';checks[check]=null;
 try{checks[check]=await api('/api/admin/settings/test','POST',{check})}catch(e:any){error.value=e.message}
}
async function sendEmail(){
 emailBusy.value=true;error.value='';success.value='';
 try{const r=await api('/api/admin/settings/test-email','POST');success.value=r.message}catch(e:any){error.value=e.message}finally{emailBusy.value=false}
}
function markClear(key:string){clears[key]=true;typed[key]=''}
onMounted(()=>{load().catch((e:any)=>{error.value=e.message})});
</script>
<template>
<section id="settings-page">
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Settings</h1><p class="c-lead">Integration settings saved here override the server environment, including when configuration is cached. Secrets are encrypted and shown only as set or not set, plus the last 4 characters.</p></div></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<p v-if="success" class="c-alert success" role="status">{{success}}</p>
<form class="c-form" @submit.prevent="save">
<article v-for="g in groups" :key="g.id" class="c-card" :id="'settings-'+g.id">
<h2>{{g.title}}</h2>
<p class="c-muted">{{g.lead}}</p>
<div v-for="f in g.fields" :key="f.key" class="c-setting">
<label v-if="f.kind==='bool'" class="c-check"><input type="checkbox" v-model="form[f.key]"/>{{f.label}}</label>
<label v-else-if="f.secret">{{f.label}}<span class="c-secret-status">{{status(f.key)}}</span><input :id="'setting-'+f.key" :name="f.key" type="password" autocomplete="new-password" spellcheck="false" v-model="typed[f.key]" placeholder="Leave blank to keep the current value"/><button type="button" class="c-btn small ghost" :disabled="settings[f.key]?.source!=='database'" @click="markClear(f.key)">Remove saved value</button></label>
<label v-else-if="f.kind==='select'">{{f.label}}<span class="c-secret-status">{{status(f.key)}}</span><select v-model="form[f.key]"><option v-for="o in f.options" :key="o.value" :value="o.value">{{o.label}}</option></select></label>
<label v-else-if="f.kind==='number'">{{f.label}}<span class="c-secret-status">{{status(f.key)}}</span><input type="number" v-model="form[f.key]" :min="f.key==='impersonation_minutes'?5:1" :max="f.key==='impersonation_minutes'?240:65535"/></label>
<label v-else>{{f.label}}<span class="c-secret-status">{{status(f.key)}}</span><input type="text" v-model="form[f.key]" autocomplete="off"/></label>
<p v-if="f.hint" class="c-muted">{{f.hint}}</p>
</div>
<div class="c-actions">
<button v-if="g.check" type="button" class="c-btn ghost" @click="runCheck(g.check)">Test connection</button>
<button v-if="g.email" id="send-test-email" type="button" class="c-btn ghost" :disabled="emailBusy" @click="sendEmail">{{emailBusy?'Sending…':'Send test email'}}</button>
</div>
<div v-if="g.id==='python'&&form.parser_sandbox==='none'" class="c-alert warn" role="alert">No OS sandbox means the parser is not isolated with bubblewrap. It can see more of this server’s files and network. Production stays sandboxed unless you save this choice.</div>
<p v-for="issue in (g.id==='python'?parser?.issues||[]:[])" :key="issue" class="c-alert warn" role="status">{{issue}}</p>
<p v-if="g.id==='python'&&parser?.warning&&form.parser_sandbox!=='none'" class="c-alert info" role="status">{{parser.warning}}</p>
<p v-if="g.check&&checks[g.check]" class="c-alert" :class="checks[g.check]!.ok?'success':'warn'" role="status">{{checks[g.check]!.message}}</p>
</article>
<div class="c-actions"><button class="c-btn" :disabled="busy">{{busy?'Saving…':'Save settings'}}</button></div>
</form>
</section>
</template>
<style scoped>
.c-setting{margin:14px 0}
.c-setting label{gap:8px}
.c-secret-status{font-weight:500}
</style>
