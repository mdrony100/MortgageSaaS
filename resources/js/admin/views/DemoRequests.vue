<script setup lang="ts">
import {ref,reactive,onMounted,watch,nextTick} from 'vue';import {useRoute} from 'vue-router';import {api,qs,when} from '../util';import Badge from '../components/Badge.vue';import Pager from '../components/Pager.vue';
const route=useRoute();
const filters=reactive({status:'',spam:'exclude',page:1});
const page=ref<any>(null),list=ref<any[]>([]),statuses=ref<Record<string,string>>({}),selected=ref<any>(null),note=ref(''),error=ref(''),success=ref(''),busy=ref(false),loaded=ref(false),notify=ref(true);
async function load(){error.value='';try{const d=await api('/api/admin/demo-requests'+qs(filters));page.value=d.requests;list.value=d.requests.data;statuses.value=d.statuses;notify.value=d.notification_configured}catch(e:any){error.value=e.message;list.value=[]}finally{loaded.value=true}}
async function open(id:number){error.value='';success.value='';try{selected.value=(await api('/api/admin/demo-requests/'+id)).request;await nextTick();document.getElementById('demo-heading')?.focus()}catch(e:any){error.value=e.message}}
async function update(payload:Record<string,unknown>,message:string){busy.value=true;error.value='';success.value='';try{selected.value=(await api('/api/admin/demo-requests/'+selected.value.id,'PATCH',payload)).request;success.value=message;await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
async function addNote(){if(!note.value.trim())return;busy.value=true;error.value='';try{selected.value=(await api('/api/admin/demo-requests/'+selected.value.id+'/notes','POST',{body:note.value})).request;note.value='';success.value='Note added.';await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
watch(()=>[filters.status,filters.spam],()=>{filters.page=1;load()});
onMounted(async()=>{await load();const id=Number(route.query?.id);if(id)await open(id)});
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Demo requests</h1><p class="c-lead">Requests from the public website. Update the status and keep notes. Spam stays out of the list unless you ask for it.</p></div></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p><p v-if="success" class="c-alert success" role="status">{{success}}</p>
<p v-if="loaded&&!notify&&!error" class="c-alert info">No owner admin email is set, and WEBSITE_INQUIRY_NOTIFY_EMAIL is empty. Requests are still saved here.</p>
<div class="c-filters" role="group" aria-label="Filter demo requests">
<label>Status<select v-model="filters.status"><option value="">All statuses</option><option v-for="(label,key) in statuses" :key="key" :value="key">{{label}}</option></select></label>
<label>Spam<select v-model="filters.spam"><option value="exclude">Hide spam</option><option value="only">Spam only</option><option value="all">Show all</option></select></label>
</div>
<div class="c-split">
<div class="c-card c-flush"><div class="c-table-wrap"><table><caption class="sr-only">Demo requests</caption><thead><tr><th scope="col">From</th><th scope="col">Volume</th><th scope="col">Status</th><th scope="col">Received</th></tr></thead><tbody>
<tr v-for="row in list" :key="row.id" :class="{selected:selected?.id===row.id}"><td><button class="c-link-btn" @click="open(row.id)">{{row.name}}</button><span class="c-sub">{{row.company}} · {{row.email}}</span><Badge v-if="row.is_spam" value="past_due" label="Spam"/></td><td>{{row.monthly_volume}}</td><td><Badge :value="row.status" :label="statuses[row.status]"/></td><td>{{when(row.created_at)}}</td></tr>
<tr v-if="loaded&&!list.length"><td colspan="4" class="c-empty">No demo requests match these filters.</td></tr>
</tbody></table></div><Pager :page="page" @go="(p:number)=>{filters.page=p;load()}"/></div>
<article v-if="selected" class="c-card" aria-labelledby="demo-heading">
<h2 id="demo-heading" tabindex="-1">{{selected.name}} · {{selected.company}}</h2>
<p class="c-muted">{{selected.email}} · {{selected.phone}} · {{selected.monthly_volume}} · {{when(selected.created_at)}}</p>
<label>Status<select :value="selected.status" @change="update({status:($event.target as HTMLSelectElement).value},'Status updated.')"><option v-for="(label,key) in statuses" :key="key" :value="key">{{label}}</option></select></label>
<div class="c-actions"><button class="c-btn ghost" type="button" @click="update({is_spam:!selected.is_spam}, selected.is_spam?'Marked as not spam.':'Marked as spam.')">{{selected.is_spam?'Not spam':'Mark as spam'}}</button></div>
<h3>Notes</h3>
<ul class="c-list"><li v-for="n in selected.notes" :key="n.id"><strong>{{n.author?.name||'Staff'}}</strong><span class="c-list-meta">{{when(n.created_at)}}</span><p>{{n.body}}</p></li><li v-if="!selected.notes?.length" class="c-empty">No notes yet.</li></ul>
<form class="c-form" @submit.prevent="addNote"><label>Add a note<textarea v-model="note" rows="3" maxlength="5000"></textarea></label><button class="c-btn" :disabled="busy||!note.trim()">Save note</button></form>
</article>
</div>
</section></template>
