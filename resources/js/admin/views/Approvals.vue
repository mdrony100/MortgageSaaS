<script setup lang="ts">
import {ref,reactive,onMounted,watch} from 'vue';import {api,qs,when} from '../util';import Badge from '../components/Badge.vue';import Pager from '../components/Pager.vue';
const f=reactive({status:'pending',type:'',page:1}),d=ref<any>(null),error=ref(''),success=ref(''),busy=ref(0),reasons=reactive<Record<number,string>>({});
async function load(){error.value='';try{d.value=await api('/api/admin/approvals'+qs(f))}catch(e:any){error.value=e.message}}
const plan=(id:number)=>d.value?.plans.find((p:any)=>p.id===id)?.name||('Plan '+id);
async function decide(r:any,decision:'approve'|'reject'){if(decision==='reject'&&!reasons[r.id]?.trim()){error.value='Enter a reason before rejecting.';return}busy.value=r.id;error.value='';success.value='';try{await api('/api/admin/approvals/'+r.id+'/decision','POST',{decision,reason:reasons[r.id]||null});success.value=decision==='approve'?'Approved. The customer will be emailed.':'Rejected. The customer will be emailed.';await load()}catch(e:any){error.value=e.message}finally{busy.value=0}}
watch(()=>[f.status,f.type],()=>{f.page=1;load()});onMounted(load);
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Approvals</h1><p class="c-lead">New organization signups and plan change requests waiting for a decision.</p></div></div>
<p v-if="d&&!d.signup_approval_enabled" class="c-alert info">Signup approval is off (PLATFORM_SIGNUP_APPROVAL=false), so new organizations start immediately. Plan change requests still appear here.</p>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p><p v-if="success" class="c-alert success" role="status">{{success}}</p>
<div class="c-filters" role="group" aria-label="Filter requests"><label>Status<select v-model="f.status"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="all">All</option></select></label><label>Type<select v-model="f.type"><option value="">All types</option><option v-for="(l,k) in d?.types" :key="k" :value="k">{{l}}</option></select></label></div>
<template v-if="d">
<article v-for="r in d.requests.data" :key="r.id" class="c-card c-request">
<div class="c-request-head"><div><span class="c-eyebrow">{{d.types[r.type]}}</span><h2><RouterLink :to="'/organizations/'+r.organization_id">{{r.organization?.name}}</RouterLink></h2></div><Badge :value="r.status"/></div>
<dl class="c-dl"><div><dt>Requested by</dt><dd>{{r.requester?.name||'—'}} <span class="c-muted">{{r.requester?.email}}</span></dd></div><div><dt>Received</dt><dd>{{when(r.created_at)}}</dd></div>
<div v-if="r.type==='plan_change'"><dt>Plan change</dt><dd>{{plan(r.payload?.current_plan_id)}} → <strong>{{plan(r.payload?.plan_id)}}</strong></dd></div><div v-else><dt>Current plan</dt><dd>{{r.organization?.plan?.name}}</dd></div>
<div v-if="r.payload?.message" class="c-dl-wide"><dt>Customer message</dt><dd class="c-pre">{{r.payload.message}}</dd></div>
<div v-if="r.status!=='pending'" class="c-dl-wide"><dt>Decision</dt><dd>{{r.decider?.name}} · {{when(r.decided_at)}}<span v-if="r.decision_reason"> · {{r.decision_reason}}</span></dd></div></dl>
<div v-if="r.status==='pending'" class="c-decide"><label class="c-grow">Reason <span class="c-muted">(required to reject, emailed to the customer)</span><input v-model="reasons[r.id]" maxlength="1000"/></label><div class="c-actions"><button class="c-btn" :disabled="busy===r.id" @click="decide(r,'approve')">Approve</button><button class="c-btn danger" :disabled="busy===r.id" @click="decide(r,'reject')">Reject</button></div></div>
</article>
<div v-if="!d.requests.data.length" class="c-card c-empty-card"><h2>Nothing waiting</h2><p>No requests match these filters.</p></div>
<Pager :page="d.requests" @go="(p:number)=>{f.page=p;load()}"/>
</template><p v-else-if="!error" class="c-loading" role="status">Loading…</p>
</section></template>
