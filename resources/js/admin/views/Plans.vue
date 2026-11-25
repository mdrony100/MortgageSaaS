<script setup lang="ts">
import {ref,onMounted} from 'vue';import {api} from '../util';
const d=ref<any>(null),error=ref(''),success=ref(''),busy=ref(false),editing=ref<any>(null);
const blank=()=>({name:'',display_monthly_price:'0.00',monthly_page_quota:500,trial_page_allowance:0,per_file_page_cap:'' as any,is_active:true,listed_publicly:false,summary:'',file_limit:25,member_limit:5,storage_limit_bytes:1073741824,topup_packs:[{pages:500,price:'79.00',label:'500 pages'}]});
const form=ref<any>(blank());
async function load(){try{d.value=await api('/api/admin/plans')}catch(e:any){error.value=e.message}}
function edit(p:any){editing.value=p.id;form.value={...p,per_file_page_cap:p.per_file_page_cap??'',topup_packs:p.topup_packs?.length?[...p.topup_packs.map((x:any)=>({...x}))]:[]};success.value='';error.value=''}
function create(){editing.value='new';form.value=blank()}
function payload(){const f=form.value;return {...f,per_file_page_cap:f.per_file_page_cap===''||f.per_file_page_cap==null?null:Number(f.per_file_page_cap),display_monthly_price:String(f.display_monthly_price),monthly_page_quota:Number(f.monthly_page_quota),trial_page_allowance:Number(f.trial_page_allowance),file_limit:Number(f.file_limit),member_limit:Number(f.member_limit),storage_limit_bytes:Number(f.storage_limit_bytes),topup_packs:f.topup_packs.map((p:any)=>({pages:Number(p.pages),price:String(p.price),label:p.label}))}}
async function save(){busy.value=true;error.value='';success.value='';try{if(editing.value==='new')await api('/api/admin/plans','POST',payload());else await api('/api/admin/plans/'+editing.value,'PUT',payload());success.value='Plan saved.';editing.value=null;await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
onMounted(load);
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Plans &amp; billing</h1><p class="c-lead">Page allowances and monthly prices. Payment is arranged offline and approved manually. No card is charged here.</p></div><button class="c-btn" type="button" @click="create">New plan</button></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<p v-if="success" class="c-alert success" role="status">{{success}}</p>
<p v-if="d" class="c-alert info">{{d.notice}}</p>
<form v-if="editing" class="c-card c-form" @submit.prevent="save">
<h2>{{editing==='new'?'New plan':'Edit plan'}}</h2>
<div class="c-form-3">
<label>Name<input v-model="form.name" required maxlength="80"/></label>
<label>Monthly price<input v-model="form.display_monthly_price" required inputmode="decimal"/></label>
<label>Monthly PDF pages<input v-model.number="form.monthly_page_quota" type="number" min="0" required/></label>
<label>Trial pages (one-time)<input v-model.number="form.trial_page_allowance" type="number" min="0" required/></label>
<label>Per-file page cap <span class="c-muted">(blank = no cap)</span><input v-model="form.per_file_page_cap" type="number" min="1"/></label>
<label>Summary<input v-model="form.summary" maxlength="280"/></label>
<label>Loan files<input v-model.number="form.file_limit" type="number" min="0"/></label>
<label>Members<input v-model.number="form.member_limit" type="number" min="0"/></label>
<label>Storage bytes<input v-model.number="form.storage_limit_bytes" type="number" min="0"/></label>
</div>
<label class="c-check"><input type="checkbox" v-model="form.is_active"/> Active</label>
<label class="c-check"><input type="checkbox" v-model="form.listed_publicly"/> Show on the public pricing page</label>
<h3>Top-up packs</h3>
<div v-for="(pack,i) in form.topup_packs" :key="i" class="c-form-3">
<label>Label<input v-model="pack.label" required maxlength="80"/></label>
<label>Pages<input v-model.number="pack.pages" type="number" min="1" required/></label>
<label>Price<input v-model="pack.price" required/></label>
</div>
<div class="c-actions"><button type="button" class="c-btn ghost" @click="form.topup_packs.push({pages:500,price:'79.00',label:'500 pages'})">Add pack</button><button type="button" class="c-btn ghost" @click="form.topup_packs.pop()" :disabled="!form.topup_packs.length">Remove last pack</button></div>
<div class="c-actions"><button class="c-btn" :disabled="busy">{{busy?'Saving…':'Save plan'}}</button><button type="button" class="c-btn ghost" @click="editing=null">Cancel</button></div>
</form>
<div v-if="d" class="c-plan-grid"><article v-for="p in d.plans" :key="p.id" class="c-card c-plan"><h2>{{p.name}}</h2><p class="c-plan-price">${{p.display_monthly_price}} / month</p><p class="c-muted">{{p.pricing_status}}</p><ul><li><strong>{{p.monthly_page_quota.toLocaleString()}}</strong> pages / month</li><li v-if="p.trial_page_allowance"><strong>{{p.trial_page_allowance.toLocaleString()}}</strong> trial pages</li><li v-if="p.per_file_page_cap"><strong>{{p.per_file_page_cap.toLocaleString()}}</strong> pages per file</li><li><strong>{{p.file_limit}}</strong> loan files</li><li><strong>{{p.member_limit}}</strong> team members</li></ul><p v-for="pack in p.topup_packs" :key="pack.label" class="c-muted">{{pack.label}} · ${{pack.price}}</p><p class="c-muted">{{p.organizations}} organization{{p.organizations===1?'':'s'}} · {{p.is_active?'Active':'Hidden'}}{{p.listed_publicly?' · On the pricing page':''}}</p><button class="c-btn ghost" type="button" @click="edit(p)">Edit</button></article></div>
<div v-if="d" class="c-card"><h2>Billing</h2><p>{{d.billing.notice}}</p>
<dl class="c-dl"><div><dt>Billing</dt><dd>{{d.billing.label}}</dd></div></dl></div>
<p v-else-if="!error" class="c-loading" role="status">Loading…</p>
</section></template>
