<script setup lang="ts">
import {ref,reactive,onMounted} from 'vue';import {api,qs,download} from '../util';import {FileSpreadsheet,FileText} from 'lucide-vue-next';
const types=ref<Record<string,string>>({}),f=reactive({from:'',to:''}),error=ref(''),success=ref(''),busy=ref('');
const notes:Record<string,string>={organizations:'Plan, subscription status, approval, members and file counts.',users:'Verification, two-factor, admin flag, disabled state and organizations. No passwords.',usage:'Files, members, storage and analysis runs per organization.',inquiries:'Website contact and demo requests with status.',approvals:'Signup and plan change decisions.',audit:'Platform-level audit events (no file-level activity).',revenue:'Billing subscriptions and events. Test provider rows are labeled.'};
async function go(type:string,format:'csv'|'xlsx'){busy.value=type+format;error.value='';success.value='';try{const name=await download('/api/admin/reports/'+type+qs({format,...f}));success.value='Downloaded '+name}catch(e:any){error.value=e.message}finally{busy.value=''}}
onMounted(async()=>{try{types.value=(await api('/api/admin/reports')).types}catch(e:any){error.value=e.message}});
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Reports</h1><p class="c-lead">Export owner-level reports as CSV or Excel. Each export is recorded in the audit log.</p></div></div>
<form class="c-filters" @submit.prevent><label>From<input v-model="f.from" type="date"/></label><label>To<input v-model="f.to" type="date"/></label><p class="c-muted c-grow">Leave dates empty for all records. Dates filter by when each record was created.</p></form>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p><p v-if="success" class="c-alert success" role="status">{{success}}</p>
<div class="c-report-grid"><article v-for="(label,key) in types" :key="key" class="c-card c-report"><h2>{{label}}</h2><p>{{notes[key]}}</p><div class="c-actions"><button class="c-btn ghost" :disabled="!!busy" @click="go(key,'csv')"><FileText :size="17" aria-hidden="true"/>CSV<span class="sr-only"> for {{label}}</span></button><button class="c-btn ghost" :disabled="!!busy" @click="go(key,'xlsx')"><FileSpreadsheet :size="17" aria-hidden="true"/>Excel<span class="sr-only"> for {{label}}</span></button></div></article></div>
</section></template>
