<script setup lang="ts">
import {ref,reactive,onMounted,watch} from 'vue';import {api,qs,day,bytes} from '../util';import Badge from '../components/Badge.vue';import Pager from '../components/Pager.vue';import {Search} from 'lucide-vue-next';
const f=reactive({q:'',status:'',plan_id:'',page:1}),d=ref<any>(null),error=ref('');
async function load(){error.value='';try{d.value=await api('/api/admin/organizations'+qs(f))}catch(e:any){error.value=e.message}}
let t:any;watch(()=>f.q,()=>{clearTimeout(t);t=setTimeout(()=>{f.page=1;load()},300)});watch(()=>[f.status,f.plan_id],()=>{f.page=1;load()});onMounted(load);
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Organizations</h1><p class="c-lead">Customer organizations, plans and subscription status.</p></div></div>
<div class="c-filters" role="search"><label class="c-grow">Search by name<span class="c-input-icon"><Search :size="18" aria-hidden="true"/><input v-model="f.q" type="search" placeholder="Organization name"/></span></label>
<label>Status<select v-model="f.status"><option value="">All</option><option v-for="(l,k) in d?.statuses" :key="k" :value="k">{{l}}</option><option value="suspended_now">Suspended now</option><option value="pending_approval">Waiting for approval</option></select></label>
<label>Plan<select v-model="f.plan_id"><option value="">All plans</option><option v-for="p in d?.plans" :key="p.id" :value="p.id">{{p.name}}</option></select></label></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<div v-if="d" class="c-card c-flush"><div class="c-table-wrap"><table><caption class="sr-only">Organizations</caption><thead><tr><th scope="col">Organization</th><th scope="col">Plan</th><th scope="col">Status</th><th scope="col" class="num">Members</th><th scope="col" class="num">Files</th><th scope="col" class="num">Uploads</th><th scope="col">Created</th></tr></thead><tbody>
<tr v-for="o in d.organizations.data" :key="o.id"><td><RouterLink :to="'/organizations/'+o.id" class="c-strong-link">{{o.name}}</RouterLink></td><td>{{o.plan?.name}}</td><td><Badge :value="o.subscription_status"/><Badge v-if="o.suspended_at&&o.subscription_status!=='suspended'" value="suspended"/><Badge v-if="o.approval_status!=='approved'" :value="o.approval_status"/></td><td class="num">{{o.member_count}}</td><td class="num">{{o.file_count}}</td><td class="num">{{bytes(Number(o.upload_bytes))}}</td><td>{{day(o.created_at)}}</td></tr>
<tr v-if="!d.organizations.data.length"><td colspan="7" class="c-empty">No organizations match.</td></tr></tbody></table></div></div>
<Pager v-if="d" :page="d.organizations" @go="(p:number)=>{f.page=p;load()}"/>
</section></template>
