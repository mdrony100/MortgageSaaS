<script setup lang="ts">
import {ref,reactive,onMounted,watch} from 'vue';import {api,qs,when} from '../util';import Pager from '../components/Pager.vue';
const f=reactive({category:'',actor:'',action:'',from:'',to:'',page:1}),d=ref<any>(null),error=ref('');
async function load(){error.value='';try{d.value=await api('/api/admin/audit'+qs(f))}catch(e:any){error.value=e.message}}
function details(m:any){if(!m)return '';return Object.entries(m).filter(([,v])=>v!==null&&v!=='').map(([k,v])=>k.replace(/_/g,' ')+': '+(typeof v==='object'?JSON.stringify(v):v)).join(' · ')}
watch(()=>f.category,()=>{f.page=1;load()});onMounted(load);
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Audit log</h1><p class="c-lead">Append-only record of owner actions, sign-ins, support sessions and inquiry handling. Read-only.</p></div></div>
<form class="c-filters" @submit.prevent="f.page=1;load()"><label>Category<select v-model="f.category"><option value="">All platform events</option><option v-for="(l,k) in d?.categories" :key="k" :value="k">{{l}}</option></select></label><label>Actor<input v-model="f.actor" placeholder="Name or email"/></label><label>Action contains<input v-model="f.action" placeholder="e.g. suspend"/></label><label>From<input v-model="f.from" type="date"/></label><label>To<input v-model="f.to" type="date"/></label><button class="c-btn">Apply</button></form>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<div v-if="d" class="c-card c-flush"><div class="c-table-wrap"><table><caption class="sr-only">Audit events</caption><thead><tr><th scope="col">When</th><th scope="col">Action</th><th scope="col">Actor</th><th scope="col">Organization</th><th scope="col">Details</th></tr></thead><tbody>
<tr v-for="e in d.events.data" :key="e.id"><td class="c-nowrap">{{when(e.created_at)}}</td><td><code>{{e.action}}</code></td><td>{{e.actor_name||'System / CLI'}}<span v-if="e.actor_email" class="c-sub">{{e.actor_email}}</span></td><td>{{e.organization||'—'}}</td><td class="c-details">{{details(e.metadata)}}</td></tr>
<tr v-if="!d.events.data.length"><td colspan="5" class="c-empty">No events match.</td></tr></tbody></table></div></div>
<Pager v-if="d" :page="d.events" @go="(p:number)=>{f.page=p;load()}"/>
</section></template>
