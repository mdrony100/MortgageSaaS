<script setup lang="ts">
import {ref,reactive,onMounted,watch,nextTick} from 'vue';import {useRoute} from 'vue-router';import {api,qs,when} from '../util';import Badge from '../components/Badge.vue';import Pager from '../components/Pager.vue';
const route=useRoute();
const filters=reactive({type:'',status:'',spam:'exclude',page:1});
const page=ref<any>(null),list=ref<any[]>([]),types=ref<Record<string,string>>({}),statuses=ref<Record<string,string>>({}),plans=ref<any[]>([]),notifyConfigured=ref(true),selected=ref<any>(null),note=ref(''),error=ref(''),success=ref(''),busy=ref(false),loaded=ref(false);
const conv=reactive({organization_name:'',plan_id:0,trial_days:14});
async function load(){error.value='';try{const d=await api('/api/admin/inquiries'+qs(filters));page.value=d.inquiries;list.value=d.inquiries.data;types.value=d.types;statuses.value=d.statuses;plans.value=d.plans||[];notifyConfigured.value=d.notification_configured}catch(e:any){error.value=e.message;list.value=[]}finally{loaded.value=true}}
async function open(id:number){error.value='';success.value='';try{selected.value=(await api('/api/admin/inquiries/'+id)).inquiry;conv.organization_name=selected.value.company;conv.plan_id=plans.value[0]?.id||0;await nextTick();document.getElementById('inquiry-heading')?.focus()}catch(e:any){error.value=e.message}}
async function update(payload:Record<string,unknown>,message:string){busy.value=true;error.value='';success.value='';try{selected.value=(await api('/api/admin/inquiries/'+selected.value.id,'PATCH',payload)).inquiry;success.value=message;await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
async function addNote(){if(!note.value.trim())return;busy.value=true;error.value='';success.value='';try{selected.value=(await api('/api/admin/inquiries/'+selected.value.id+'/notes','POST',{body:note.value})).inquiry;note.value='';success.value='Internal note added.';await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
async function convert(){if(!confirm('Create organization "'+conv.organization_name+'" and email an owner invitation to '+selected.value.email+'?'))return;busy.value=true;error.value='';success.value='';try{const r=await api('/api/admin/inquiries/'+selected.value.id+'/convert','POST',conv);selected.value=r.inquiry;success.value=r.message;await load()}catch(e:any){error.value=e.message}finally{busy.value=false}}
watch(()=>[filters.type,filters.status,filters.spam],()=>{filters.page=1;load()});
onMounted(async()=>{await load();const id=Number(route.query?.id);if(id)await open(id)});
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Website inquiries</h1><p class="c-lead">Contact and demo requests from the public website.</p></div></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p><p v-if="success" class="c-alert success" role="status">{{success}}</p>
<p v-if="loaded&&!notifyConfigured&&!error" class="c-alert info">Email notification is not configured (WEBSITE_INQUIRY_NOTIFY_EMAIL). Inquiries are still saved here.</p>
<div class="c-filters" role="group" aria-label="Filter inquiries">
<label>Inquiry type<select v-model="filters.type"><option value="">All types</option><option v-for="(label,key) in types" :key="key" :value="key">{{label}}</option></select></label>
<label>Status<select v-model="filters.status"><option value="">All statuses</option><option v-for="(label,key) in statuses" :key="key" :value="key">{{label}}</option></select></label>
<label>Spam<select v-model="filters.spam"><option value="exclude">Hide spam</option><option value="only">Spam only</option><option value="all">Show all</option></select></label></div>
<div class="c-split">
<div class="c-card c-flush"><div class="c-table-wrap"><table><caption class="sr-only">Website inquiries</caption><thead><tr><th scope="col">From</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Received</th></tr></thead><tbody>
<tr v-for="i in list" :key="i.id" :class="{selected:selected?.id===i.id}"><td><button class="c-link-btn" @click="open(i.id)">{{i.name}}</button><span class="c-sub">{{i.company}} · {{i.email}}</span><Badge v-if="i.is_spam" value="past_due" label="Spam"/><Badge v-if="i.converted_at" value="approved" label="Converted"/></td><td>{{types[i.inquiry_type]||i.inquiry_type}}</td><td><Badge :value="i.status" :label="statuses[i.status]"/></td><td>{{when(i.created_at)}}</td></tr>
<tr v-if="loaded&&!list.length"><td colspan="4" class="c-empty">No inquiries match these filters.</td></tr></tbody></table></div>
<Pager :page="page" @go="(p:number)=>{filters.page=p;load()}"/></div>
<article v-if="selected" class="c-card" aria-labelledby="inquiry-heading">
<h2 id="inquiry-heading" tabindex="-1">{{selected.name}} · {{selected.company}}</h2>
<p class="c-muted">{{selected.email}}<span v-if="selected.role"> · {{selected.role}}</span> · {{types[selected.inquiry_type]||selected.inquiry_type}} · {{when(selected.created_at)}}</p>
<p class="c-pre">{{selected.message}}</p>
<p class="c-muted">Notification: {{selected.notification_status}}<span v-if="selected.spam_reason"> · Spam reason: {{selected.spam_reason}}</span></p>
<div class="c-inline"><label>Status<select :value="selected.status" :disabled="busy" @change="update({status:($event.target as HTMLSelectElement).value},'Status updated.')"><option v-for="(label,key) in statuses" :key="key" :value="key">{{label}}</option></select></label>
<button class="c-btn ghost" :disabled="busy" @click="update({is_spam:!selected.is_spam},selected.is_spam?'Marked as not spam.':'Marked as spam.')">{{selected.is_spam?'Not spam':'Mark as spam'}}</button></div>
<hr/><h3>Convert to organization invite</h3>
<p v-if="selected.converted_at" class="c-alert success">Converted {{when(selected.converted_at)}}. <RouterLink :to="'/organizations/'+selected.converted_organization_id">Open organization</RouterLink></p>
<p v-else-if="selected.is_spam" class="c-muted">Spam inquiries cannot be converted.</p>
<form v-else class="c-form" @submit.prevent="convert"><p class="c-muted">Creates the organization and emails {{selected.email}} an owner invitation (valid 7 days) through the normal invitation flow.</p>
<label>Organization name<input v-model="conv.organization_name" required maxlength="120"/></label>
<div class="c-form-3"><label>Plan<select v-model.number="conv.plan_id"><option v-for="p in plans" :key="p.id" :value="p.id">{{p.name}}</option></select></label><label>Trial days<input v-model.number="conv.trial_days" type="number" min="0" max="365"/></label></div>
<div class="c-actions"><button class="c-btn" :disabled="busy">Create organization and send invite</button></div></form>
<hr/><h3>Internal notes</h3>
<ul class="c-list"><li v-for="n in selected.notes" :key="n.id"><strong>{{n.author?.name||'Staff'}}</strong><span class="c-list-meta">{{when(n.created_at)}}</span><p class="c-pre">{{n.body}}</p></li><li v-if="!selected.notes?.length" class="c-empty">No internal notes yet.</li></ul>
<form class="c-form" @submit.prevent="addNote"><label>Add internal note<textarea v-model="note" rows="3" maxlength="5000"></textarea></label><div class="c-actions"><button class="c-btn" :disabled="busy||!note.trim()">Add note</button></div></form>
</article>
<div v-else class="c-card c-empty-card"><h2>Select an inquiry</h2><p>Choose an inquiry to read it, change its status, convert it or add notes.</p></div>
</div></section></template>
