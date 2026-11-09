<script setup lang="ts">
import {ref,reactive,onMounted,watch} from 'vue';import {useRoute} from 'vue-router';import {api} from '../api';
const route=useRoute();
const filters=reactive({type:'',status:'',spam:'exclude'});
const list=ref<any[]>([]),types=ref<Record<string,string>>({}),statuses=ref<Record<string,string>>({}),notifyConfigured=ref(true),selected=ref<any>(null),note=ref(''),error=ref(''),success=ref(''),busy=ref(false),loaded=ref(false);
function query(){const p=new URLSearchParams();for(const [k,v] of Object.entries(filters))if(v)p.set(k,v);return p.toString()}
async function load(){error.value='';try{const d=await api('/api/platform/inquiries?'+query());list.value=d.inquiries.data;types.value=d.types;statuses.value=d.statuses;notifyConfigured.value=d.notification_configured}catch(e:any){error.value=e.message;list.value=[]}finally{loaded.value=true}}
async function open(id:number){error.value='';success.value='';try{selected.value=(await api('/api/platform/inquiries/'+id)).inquiry}catch(e:any){error.value=e.message}}
async function update(payload:Record<string,unknown>,message:string){busy.value=true;error.value='';success.value='';try{selected.value=(await api('/api/platform/inquiries/'+selected.value.id,'PATCH',payload)).inquiry;success.value=message;await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
async function addNote(){if(!note.value.trim())return;busy.value=true;error.value='';success.value='';try{selected.value=(await api('/api/platform/inquiries/'+selected.value.id+'/notes','POST',{body:note.value})).inquiry;note.value='';success.value='Internal note added.';await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
function when(v:string){return v?new Date(v).toLocaleString():''}
watch(filters,load);
onMounted(async()=>{await load();const id=Number(route.query?.id);if(id)await open(id)});
</script>
<template><section>
<div class="page-heading"><div><span class="eyebrow">PLATFORM STAFF</span><h1>Website inquiries</h1><p class="muted">Contact and demo requests from the public website. Visible to platform staff only.</p></div></div>
<p v-if="error" class="alert error" role="alert">{{error}}</p><p v-if="success" class="alert success" role="status">{{success}}</p>
<p v-if="loaded&&!notifyConfigured&&!error" class="notice">Email notification is not configured (WEBSITE_INQUIRY_NOTIFY_EMAIL). Inquiries are still saved here.</p>
<div class="card"><div class="actions" role="group" aria-label="Filter inquiries">
<label>Inquiry type<select v-model="filters.type"><option value="">All types</option><option v-for="(label,key) in types" :key="key" :value="key">{{label}}</option></select></label>
<label>Status<select v-model="filters.status"><option value="">All statuses</option><option v-for="(label,key) in statuses" :key="key" :value="key">{{label}}</option></select></label>
<label>Spam<select v-model="filters.spam"><option value="exclude">Hide spam</option><option value="only">Spam only</option><option value="all">Show all</option></select></label>
</div></div>
<div class="settings-grid">
<div class="table-wrap"><table><caption class="sr-only">Website inquiries</caption><thead><tr><th scope="col">From</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Received</th></tr></thead><tbody>
<tr v-for="i in list" :key="i.id"><td><button class="text-button" @click="open(i.id)">{{i.name}}</button><small>{{i.company}} · {{i.email}}</small><span v-if="i.is_spam" class="badge amber">Spam</span></td><td>{{types[i.inquiry_type]||i.inquiry_type}}</td><td><span class="badge">{{statuses[i.status]||i.status}}</span></td><td>{{when(i.created_at)}}</td></tr>
<tr v-if="loaded&&!list.length"><td colspan="4" class="muted">No inquiries match these filters.</td></tr>
</tbody></table></div>
<article v-if="selected" class="card" aria-labelledby="inquiry-heading">
<h2 id="inquiry-heading">{{selected.name}} · {{selected.company}}</h2>
<p class="muted">{{selected.email}}<span v-if="selected.role"> · {{selected.role}}</span> · {{types[selected.inquiry_type]||selected.inquiry_type}} · {{when(selected.created_at)}}</p>
<p style="white-space:pre-wrap">{{selected.message}}</p>
<p class="micro">Notification: {{selected.notification_status}}<span v-if="selected.spam_reason"> · Spam reason: {{selected.spam_reason}}</span></p>
<div class="actions"><label>Status<select :value="selected.status" :disabled="busy" @change="update({status:($event.target as HTMLSelectElement).value},'Status updated.')"><option v-for="(label,key) in statuses" :key="key" :value="key">{{label}}</option></select></label>
<button :disabled="busy" @click="update({is_spam:!selected.is_spam},selected.is_spam?'Marked as not spam.':'Marked as spam.')">{{selected.is_spam?'Not spam':'Mark as spam'}}</button></div>
<h3>Internal notes</h3>
<div v-for="n in selected.notes" :key="n.id" class="note-card"><strong>{{n.author?.name||'Staff'}}</strong> <small>{{when(n.created_at)}}</small><p style="white-space:pre-wrap">{{n.body}}</p></div>
<p v-if="!selected.notes?.length" class="muted">No internal notes yet.</p>
<form class="form-stack" @submit.prevent="addNote"><label>Add internal note<textarea v-model="note" maxlength="5000"/></label><button class="primary" :disabled="busy||!note.trim()">Add note</button></form>
</article>
</div>
</section></template>
