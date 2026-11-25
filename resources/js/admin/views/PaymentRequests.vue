<script setup lang="ts">
import {ref,reactive,onMounted,watch} from 'vue';import {api,qs,when} from '../util';import Badge from '../components/Badge.vue';import Pager from '../components/Pager.vue';
const filters=reactive({status:'pending',kind:'',page:1});
const page=ref<any>(null),list=ref<any[]>([]),kinds=ref<Record<string,string>>({}),statuses=ref<Record<string,string>>({}),selected=ref<any>(null),error=ref(''),success=ref(''),busy=ref(false),loaded=ref(false);
const pay=reactive({payment_method:'Wire',payment_reference:'',payment_note:''});
const reason=ref('');
async function load(){error.value='';try{const d=await api('/api/admin/payment-requests'+qs(filters));page.value=d.requests;list.value=d.requests.data;kinds.value=d.kinds;statuses.value=d.statuses}catch(e:any){error.value=e.message;list.value=[]}finally{loaded.value=true}}
function open(row:any){selected.value=row;success.value='';pay.payment_reference='';pay.payment_note='';reason.value=''}
async function approve(){if(!selected.value)return;busy.value=true;error.value='';success.value='';try{selected.value=(await api('/api/admin/payment-requests/'+selected.value.id+'/approve','POST',{...pay})).request;success.value='Marked paid offline and applied.';await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
async function reject(){if(!selected.value)return;busy.value=true;error.value='';success.value='';try{selected.value=(await api('/api/admin/payment-requests/'+selected.value.id+'/reject','POST',{reason:reason.value})).request;success.value='Request rejected. The customer has been emailed.';await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
function title(row:any){return row.kind==='plan'?(row.plan?.name||'Plan change'):((row.pack_label||row.pack_pages+' pages'))}
watch(()=>[filters.status,filters.kind],()=>{filters.page=1;load()});
onMounted(load);
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Payment requests</h1><p class="c-lead">Plan changes and page top-ups. Approving records an offline payment, then applies the plan or the pages. No card is charged.</p></div></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p><p v-if="success" class="c-alert success" role="status">{{success}}</p>
<div class="c-filters" role="group" aria-label="Filter payment requests">
<label>Status<select v-model="filters.status"><option value="all">All</option><option v-for="(label,key) in statuses" :key="key" :value="key">{{label}}</option></select></label>
<label>Kind<select v-model="filters.kind"><option value="">All</option><option v-for="(label,key) in kinds" :key="key" :value="key">{{label}}</option></select></label>
</div>
<div class="c-split">
<div class="c-card c-flush"><div class="c-table-wrap"><table><caption class="sr-only">Payment requests</caption><thead><tr><th scope="col">Organization</th><th scope="col">Request</th><th scope="col">Amount</th><th scope="col">Status</th></tr></thead><tbody>
<tr v-for="row in list" :key="row.id" :class="{selected:selected?.id===row.id}"><td><button class="c-link-btn" @click="open(row)">{{row.organization?.name}}</button><span class="c-sub">{{row.requester?.email}}</span></td><td>{{kinds[row.kind]||row.kind}} · {{title(row)}}</td><td>${{row.amount}}</td><td><Badge :value="row.status" :label="statuses[row.status]"/></td></tr>
<tr v-if="loaded&&!list.length"><td colspan="4" class="c-empty">No payment requests match these filters.</td></tr>
</tbody></table></div><Pager :page="page" @go="(p:number)=>{filters.page=p;load()}"/></div>
<article v-if="selected" class="c-card">
<h2>{{selected.organization?.name}}</h2>
<p class="c-muted">{{title(selected)}} · ${{selected.amount}} placeholder · {{when(selected.created_at)}}</p>
<p v-if="selected.note">{{selected.note}}</p>
<p v-if="selected.status!=='pending'"><Badge :value="selected.status" :label="statuses[selected.status]"/> <span v-if="selected.payment_method">{{selected.payment_method}} · {{selected.payment_reference}}</span><span v-if="selected.decision_reason"> · {{selected.decision_reason}}</span></p>
<template v-else>
<form class="c-form" @submit.prevent="approve">
<h3>Mark paid offline and apply</h3>
<label>Payment method<input v-model="pay.payment_method" required maxlength="80"/></label>
<label>Reference<input v-model="pay.payment_reference" required maxlength="120"/></label>
<label>Note<input v-model="pay.payment_note" maxlength="1000"/></label>
<button class="c-btn" :disabled="busy">Approve and apply</button>
</form>
<form class="c-form" @submit.prevent="reject">
<h3>Reject</h3>
<label>Reason<textarea v-model="reason" required maxlength="1000" rows="3"></textarea></label>
<button class="c-btn danger" :disabled="busy">Reject request</button>
</form>
</template>
</article>
</div>
</section></template>
