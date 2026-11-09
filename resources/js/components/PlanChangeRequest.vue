<script setup lang="ts">
import {ref,onMounted,computed} from 'vue';import {api} from '../api';
const props=defineProps<{organizationId:number,currentPlanId:number,isOwner:boolean,plans:any[]}>();
const requests=ref<any[]>([]),planId=ref(0),message=ref(''),error=ref(''),success=ref(''),busy=ref(false);
const pending=computed(()=>requests.value.find(r=>r.status==='pending'));
const name=(id:number)=>props.plans.find(p=>p.id===id)?.name||'Plan '+id;
async function load(){try{requests.value=(await api('/api/organizations/'+props.organizationId+'/plan-requests')).requests}catch(e:any){error.value=e.message}}
async function submit(){busy.value=true;error.value='';success.value='';try{const r=await api('/api/organizations/'+props.organizationId+'/plan-requests','POST',{plan_id:planId.value,message:message.value||null});success.value=r.message;message.value='';await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
onMounted(()=>{planId.value=props.plans.find(p=>p.id!==props.currentPlanId)?.id||0;load()});
</script>
<template><article class="card plan-request" aria-labelledby="plan-request-title"><h2 id="plan-request-title">Request a plan change</h2>
<p>Ask MortgageLens to move your organization to a different plan. No payment is taken here; we review each request and email you the decision.</p>
<p v-if="error" class="alert error" role="alert">{{error}}</p><p v-if="success" class="alert success" role="status">{{success}}</p>
<p v-if="pending" class="notice">Request to move to <strong>&nbsp;{{name(pending.payload?.plan_id)}}&nbsp;</strong> is waiting for review (sent {{new Date(pending.created_at).toLocaleDateString()}}).</p>
<form v-else-if="isOwner" class="form-stack" @submit.prevent="submit"><label>New plan<select v-model.number="planId" required><option v-for="p in plans.filter(p=>p.id!==currentPlanId)" :key="p.id" :value="p.id">{{p.name}}</option></select></label><label>Message (optional)<textarea v-model="message" maxlength="1000" rows="3"></textarea></label><button class="primary" :disabled="busy||!planId">{{busy?'Sending…':'Send request'}}</button></form>
<p v-else>Only the organization owner can request a plan change.</p>
<ul v-if="requests.filter(r=>r.status!=='pending').length" class="request-history"><li v-for="r in requests.filter(r=>r.status!=='pending')" :key="r.id">{{name(r.payload?.plan_id)}}: <strong>{{r.status}}</strong><span v-if="r.decision_reason"> · {{r.decision_reason}}</span></li></ul>
</article></template>
