<script setup lang="ts">
import {ref,reactive,onMounted,computed} from 'vue';import {api,qs,bytes} from '../util';import BarChart from '../components/BarChart.vue';import HBars from '../components/HBars.vue';import Stat from '../components/Stat.vue';
const f=reactive({from:'',to:''}),d=ref<any>(null),error=ref(''),busy=ref(false);
async function load(){busy.value=true;error.value='';try{d.value=await api('/api/admin/analytics'+qs(f));if(!f.from){f.from=d.value.range.from;f.to=d.value.range.to}}catch(e:any){error.value=e.message}finally{busy.value=false}}
const pts=(s:any[])=>s.map(x=>({label:x.bucket,count:x.count}));
const sum=(s:any[])=>s.reduce((a,x)=>a+x.count,0);
const totals=computed(()=>d.value?{signups:sum(d.value.signups),orgs:sum(d.value.organizations),inquiries:sum(d.value.inquiries),analyses:d.value.analyses.reduce((a:number,m:any)=>a+sum(m.series),0)}:null);
onMounted(load);
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Analytics</h1><p class="c-lead">Real activity recorded in the database. Empty charts mean nothing has happened in that range yet.</p></div></div>
<form class="c-filters" @submit.prevent="load"><label>From<input v-model="f.from" type="date"/></label><label>To<input v-model="f.to" type="date"/></label><button class="c-btn" :disabled="busy">Update</button></form>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<template v-if="d&&totals">
<p class="c-muted">Grouped by {{d.range.unit}} · {{d.range.from}} to {{d.range.to}}</p>
<div class="c-stats"><Stat label="New users" :value="totals.signups"/><Stat label="New organizations" :value="totals.orgs"/><Stat label="Active organizations" :value="d.active_organizations" :hint="d.active_definition"/><Stat label="Analyses run" :value="totals.analyses"/><Stat label="Website inquiries" :value="totals.inquiries" :hint="d.inquiries_converted+' converted to organizations'"/></div>
<div class="c-grid-2"><div class="c-card"><BarChart title="User signups" :data="pts(d.signups)" unit="signups"/></div><div class="c-card"><BarChart title="New organizations" :data="pts(d.organizations)" unit="organizations"/></div></div>
<div class="c-grid-2"><div v-for="m in d.analyses" :key="m.module" class="c-card"><BarChart :title="m.module" :data="pts(m.series)" unit="runs"/></div>
<div class="c-card" v-if="!d.loan_files_by_module.length"><figure class="c-chart"><figcaption>Loan files created</figcaption><p class="c-empty">No loan files were created in this range.</p></figure></div>
<div v-for="m in d.loan_files_by_module" :key="m.module" class="c-card"><BarChart :title="'Loan files created · '+m.module" :data="pts(m.series)" unit="files"/></div></div>
<div class="c-grid-2"><div class="c-card"><BarChart title="Website inquiries (spam excluded)" :data="pts(d.inquiries)" unit="inquiries"/></div><div class="c-card"><HBars title="Inquiry status" :data="d.inquiry_status"/></div></div>
<div class="c-grid-2"><div class="c-card"><HBars title="Plan distribution (organizations)" :data="d.plan_distribution"/></div><div class="c-card"><HBars title="Storage used · top organizations" :data="d.storage_top.map((s:any)=>({label:s.organization,count:s.bytes,display:bytes(s.bytes)}))"/></div></div>
</template><p v-else-if="!error" class="c-loading" role="status">Loading…</p>
</section></template>
