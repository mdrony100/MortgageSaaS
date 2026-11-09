<script setup lang="ts">
import {ref,reactive,onMounted,watch,nextTick} from 'vue';import {useRoute} from 'vue-router';import {api,qs,when,day} from '../util';import Badge from '../components/Badge.vue';import Pager from '../components/Pager.vue';import {Search,Eye} from 'lucide-vue-next';
const route=useRoute();
const f=reactive({q:String(route.query.q||''),filter:'',page:1}),d=ref<any>(null),error=ref(''),success=ref(''),busy=ref(0),sessions=ref<any[]>([]);
const target=ref<any>(null),reason=ref(''),starting=ref(false);
async function load(){error.value='';try{d.value=await api('/api/admin/users'+qs(f));sessions.value=(await api('/api/admin/impersonations')).sessions}catch(e:any){error.value=e.message}}
async function act(u:any,action:string,confirmText?:string){if(confirmText&&!confirm(confirmText))return;busy.value=u.id;error.value='';success.value='';try{const r=await api('/api/admin/users/'+u.id+'/actions','POST',{action});success.value=r.message;const i=d.value.users.data.findIndex((x:any)=>x.id===u.id);if(i>=0)d.value.users.data[i]=r.user}catch(e:any){error.value=e.message}finally{busy.value=0}}
async function openSupport(u:any){target.value=u;reason.value='';await nextTick();document.getElementById('support-reason')?.focus()}
async function start(){if(reason.value.trim().length<10){error.value='Describe the reason (at least 10 characters).';return}starting.value=true;error.value='';try{const r=await api('/api/admin/impersonation','POST',{user_id:target.value.id,reason:reason.value});location.href=r.redirect}catch(e:any){error.value=e.message;starting.value=false}}
let t:any;watch(()=>f.q,()=>{clearTimeout(t);t=setTimeout(()=>{f.page=1;load()},300)});watch(()=>f.filter,()=>{f.page=1;load()});onMounted(load);
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Users</h1><p class="c-lead">Manage sign-in access. Staff never see or set passwords; platform admin is granted only by an operator command.</p></div></div>
<div class="c-filters" role="search"><label class="c-grow">Search name or email<span class="c-input-icon"><Search :size="18" aria-hidden="true"/><input v-model="f.q" type="search" placeholder="Name or email"/></span></label><label>Show<select v-model="f.filter"><option value="">All users</option><option value="disabled">Disabled</option><option value="unverified">Unverified email</option><option value="no_2fa">No two-factor</option><option value="platform_admins">Platform admins</option></select></label></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p><p v-if="success" class="c-alert success" role="status">{{success}}</p>
<div v-if="target" class="c-card c-support" role="region" aria-labelledby="support-title">
<h2 id="support-title">Start a support session as {{target.name}}</h2>
<p>You will see the customer workspace exactly as {{target.email}} does, for up to {{d?.impersonation.minutes}} minutes. A banner stays visible the whole time. Password, email, two-factor, billing, role changes, guideline approval and original document downloads are blocked. The start, the end and every change you make are recorded with your name, and the customer's organization sees "Platform support accessed this workspace" in its audit history.</p>
<form class="c-form" @submit.prevent="start"><label for="support-reason">Reason (required, recorded)</label><textarea id="support-reason" v-model="reason" rows="3" maxlength="500" required minlength="10" placeholder="e.g. Customer asked for help with a stuck bank statement upload (ticket 1042)"></textarea>
<div class="c-actions"><button class="c-btn" :disabled="starting">{{starting?'Starting…':'Start support session'}}</button><button type="button" class="c-btn ghost" @click="target=null">Cancel</button></div></form></div>
<p v-if="d&&!d.impersonation.enabled" class="c-alert info">Support sessions are turned off (IMPERSONATION_ENABLED=false).</p>
<p v-else-if="d&&!d.impersonation.allowed" class="c-alert info">Turn on two-factor sign-in for your account to start support sessions.</p>
<div v-if="d" class="c-card c-flush"><div class="c-table-wrap"><table><caption class="sr-only">Users</caption><thead><tr><th scope="col">User</th><th scope="col">Organizations</th><th scope="col">Security</th><th scope="col">Last sign-in</th><th scope="col">Created</th><th scope="col">Actions</th></tr></thead><tbody>
<tr v-for="u in d.users.data" :key="u.id"><td><strong>{{u.name}}</strong><span class="c-sub">{{u.email}}</span><Badge v-if="u.platform_admin" value="new" label="Platform admin"/><Badge v-if="u.disabled_at" value="suspended" label="Disabled"/></td>
<td><span v-for="o in u.organizations" :key="o.id" class="c-sub"><RouterLink :to="'/organizations/'+o.id">{{o.name}}</RouterLink> · {{o.role}}</span><span v-if="!u.organizations.length" class="c-muted">None</span></td>
<td><span class="c-sub">{{u.verified?'Email verified':'Email not verified'}}</span><span class="c-sub">{{u.two_factor?'Two-factor on':'Two-factor off'}}</span></td>
<td>{{u.last_login_at?when(u.last_login_at):'Not recorded'}}</td><td>{{day(u.created_at)}}</td>
<td><div class="c-row-actions">
<button v-if="!u.disabled_at" class="c-btn small danger" :disabled="busy===u.id||u.id===d.current_user_id||u.platform_admin" :title="u.id===d.current_user_id?'You cannot disable yourself':u.platform_admin?'Revoke platform admin via CLI first':''" @click="act(u,'disable','Disable sign-in for '+u.email+'? Their open sessions will end.')">Disable sign-in</button>
<button v-else class="c-btn small ghost" :disabled="busy===u.id" @click="act(u,'enable')">Enable sign-in</button>
<button v-if="!u.verified" class="c-btn small ghost" :disabled="busy===u.id" @click="act(u,'resend_verification')">Resend verification</button>
<button class="c-btn small ghost" :disabled="busy===u.id||!!u.disabled_at" @click="act(u,'send_password_reset','Email a password reset link to '+u.email+'?')">Send reset link</button>
<button v-if="!u.platform_admin" class="c-btn small ghost" :disabled="!d.impersonation.allowed||!!u.disabled_at" @click="openSupport(u)"><Eye :size="16" aria-hidden="true"/>View as customer</button>
</div></td></tr>
<tr v-if="!d.users.data.length"><td colspan="6" class="c-empty">No users match.</td></tr></tbody></table></div></div>
<Pager v-if="d" :page="d.users" @go="(p:number)=>{f.page=p;load()}"/>
<div class="c-card"><h2>Recent support sessions</h2><div class="c-table-wrap"><table><caption class="sr-only">Support sessions</caption><thead><tr><th scope="col">Staff</th><th scope="col">Customer</th><th scope="col">Reason</th><th scope="col">Started</th><th scope="col">Ended</th></tr></thead><tbody>
<tr v-for="s in sessions" :key="s.id"><td>{{s.admin?.name}}</td><td>{{s.target?.name}} <span class="c-sub">{{s.target?.email}}</span></td><td class="c-pre">{{s.reason}}</td><td>{{when(s.started_at)}}</td><td>{{s.ended_at?when(s.ended_at)+' · '+s.end_reason:'Active until '+when(s.expires_at)}}</td></tr>
<tr v-if="!sessions.length"><td colspan="5" class="c-empty">No support sessions yet.</td></tr></tbody></table></div></div>
</section></template>
