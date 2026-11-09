<script setup lang="ts">
import {ref,reactive,onMounted,computed} from 'vue';import {useRoute} from 'vue-router';import {api,when,day,bytes} from '../util';import Badge from '../components/Badge.vue';import {ArrowLeft} from 'lucide-vue-next';
const route=useRoute(),d=ref<any>(null),error=ref(''),success=ref(''),busy=ref(false),note=ref('');
const form=reactive({plan_id:0,status:'active',reason:'',days:14,end_status:'active',file_limit:'' as any,member_limit:'' as any,storage_gb:'' as any});
const id=computed(()=>Number(route.params.id));
function fill(x:any){d.value=x;const o=x.organization;form.plan_id=o.plan.id;form.status=o.subscription_status;form.reason='';form.file_limit=o.file_limit_override??'';form.member_limit=o.member_limit_override??'';form.storage_gb=o.storage_limit_bytes_override!=null?+(o.storage_limit_bytes_override/1073741824).toFixed(2):''}
async function load(){error.value='';try{fill(await api('/api/admin/organizations/'+id.value))}catch(e:any){error.value=e.message}}
async function act(payload:Record<string,unknown>,msg:string){busy.value=true;error.value='';success.value='';try{fill(await api('/api/admin/organizations/'+id.value+'/actions','POST',payload));success.value=msg}catch(e:any){error.value=e.message}finally{busy.value=false}}
const nul=(v:any)=>v===''||v==null?null:Number(v);
function saveLimits(){act({action:'set_limits',file_limit:nul(form.file_limit),member_limit:nul(form.member_limit),storage_gb:nul(form.storage_gb)},'Limit overrides saved.')}
function suspend(){if(!form.reason.trim()){error.value='Enter a reason before suspending.';return}act({action:'suspend',reason:form.reason},'Organization suspended. Members now see a suspension message.')}
async function addNote(){if(!note.value.trim())return;busy.value=true;error.value='';try{d.value.notes=(await api('/api/admin/organizations/'+id.value+'/notes','POST',{body:note.value})).notes;note.value='';success.value='Internal note added.'}catch(e:any){error.value=e.message}finally{busy.value=false}}
const pct=(u:number,l:number)=>l>0?Math.min(100,Math.round(u/l*100)):100;
onMounted(load);
</script>
<template><section>
<RouterLink to="/organizations" class="c-back"><ArrowLeft :size="18" aria-hidden="true"/>All organizations</RouterLink>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p><p v-if="success" class="c-alert success" role="status">{{success}}</p>
<template v-if="d">
<div class="c-head"><div><span class="c-eyebrow">Organization #{{d.organization.id}}</span><h1>{{d.organization.name}}</h1><p class="c-lead"><Badge :value="d.organization.subscription_status" :label="d.statuses[d.organization.subscription_status]"/> <Badge v-if="d.organization.approval_status!=='approved'" :value="d.organization.approval_status"/> {{d.organization.plan.name}} plan · created {{day(d.organization.created_at)}}</p></div></div>
<p v-if="d.organization.suspended_at" class="c-alert warn"><strong>Suspended {{when(d.organization.suspended_at)}}.</strong> {{d.organization.suspension_reason}} Members cannot use the workspace until it is reactivated.</p>
<div class="c-grid-3">
<div class="c-card c-meter-card" v-for="m in [{k:'Loan files',u:d.usage.files,l:d.limits.files,f:(x:number)=>x},{k:'Members',u:d.usage.members,l:d.limits.members,f:(x:number)=>x},{k:'Storage',u:d.usage.storage_bytes,l:d.limits.storage_bytes,f:bytes}]" :key="m.k">
<h2>{{m.k}}</h2><p class="c-meter-value">{{m.f(m.u)}} <span>of {{m.f(m.l)}}</span></p><div class="c-meter" role="meter" :aria-valuenow="pct(m.u,m.l)" aria-valuemin="0" aria-valuemax="100" :aria-label="m.k+' used'"><span :style="{width:pct(m.u,m.l)+'%'}" :class="{hot:pct(m.u,m.l)>=90}"></span></div></div>
</div>
<div class="c-grid-2">
<div class="c-card"><h2>Subscription</h2>
<form class="c-form" @submit.prevent="act({action:'set_status',status:form.status,reason:form.reason||null},'Status updated.')">
<label>Status<select v-model="form.status"><option v-for="(l,k) in d.statuses" :key="k" :value="k">{{l}}</option></select></label>
<label>Reason <span class="c-muted">(required to suspend; kept internal)</span><input v-model="form.reason" maxlength="500"/></label>
<div class="c-actions"><button class="c-btn" :disabled="busy">Save status</button><button v-if="!d.organization.suspended_at" type="button" class="c-btn danger" :disabled="busy" @click="suspend">Suspend</button><button v-else type="button" class="c-btn ghost" :disabled="busy" @click="act({action:'reactivate',status:'active'},'Organization reactivated.')">Reactivate</button></div></form>
<hr/><h3>Trial</h3><p class="c-muted">Trial ends: {{d.organization.trial_ends_at?when(d.organization.trial_ends_at):'not set'}}</p>
<div class="c-inline"><label>Extend by (days)<input v-model.number="form.days" type="number" min="1" max="365"/></label><button class="c-btn ghost" :disabled="busy" @click="act({action:'extend_trial',days:form.days},'Trial extended.')">Extend trial</button></div>
<div class="c-inline"><label>After ending, status<select v-model="form.end_status"><option value="active">Active</option><option value="past_due">Past due</option><option value="cancelled">Cancelled</option></select></label><button class="c-btn ghost" :disabled="busy" @click="act({action:'end_trial',status:form.end_status},'Trial ended.')">End trial now</button></div>
</div>
<div class="c-card"><h2>Plan and limits</h2>
<div class="c-inline"><label class="c-grow">Plan<select v-model.number="form.plan_id"><option v-for="p in d.plans" :key="p.id" :value="p.id">{{p.name}} · {{p.file_limit}} files · {{p.member_limit}} members · {{bytes(p.storage_limit_bytes)}}</option></select></label><button class="c-btn" :disabled="busy||form.plan_id===d.organization.plan.id" @click="act({action:'change_plan',plan_id:form.plan_id},'Plan changed.')">Change plan</button></div>
<hr/><h3>Limit overrides</h3><p class="c-muted">Leave blank to use the plan limit. Overrides apply on top of the plan.</p>
<form class="c-form c-form-3" @submit.prevent="saveLimits"><label>Files<input v-model="form.file_limit" type="number" min="0" :placeholder="String(d.organization.plan.file_limit)"/></label><label>Members<input v-model="form.member_limit" type="number" min="0" :placeholder="String(d.organization.plan.member_limit)"/></label><label>Storage (GB)<input v-model="form.storage_gb" type="number" min="0" step="0.5" :placeholder="String(d.organization.plan.storage_limit_bytes/1073741824)"/></label><div class="c-actions"><button class="c-btn" :disabled="busy">Save overrides</button></div></form>
</div></div>
<div class="c-card c-flush"><h2 class="c-pad">Members</h2><div class="c-table-wrap"><table><caption class="sr-only">Members</caption><thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Account</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
<tr v-for="m in d.members" :key="m.id"><td>{{m.user.name}}</td><td>{{m.user.email}}</td><td>{{m.role}}<span v-if="m.org_wide_access" class="c-muted"> · all files</span></td><td><Badge v-if="m.user.disabled_at" value="suspended" label="Disabled"/><Badge v-else-if="!m.user.email_verified_at" value="pending" label="Unverified"/><Badge v-else value="active" label="Active"/></td><td><RouterLink :to="'/users?q='+encodeURIComponent(m.user.email)">Manage user</RouterLink></td></tr>
<tr v-if="!d.members.length"><td colspan="5" class="c-empty">No members.</td></tr></tbody></table></div>
<p class="c-muted c-pad">Usage: {{d.usage.files}} loan files<span v-for="(n,mod) in d.usage.files_by_module" :key="mod"> · {{mod}} {{n}}</span> · {{d.usage.pending_invitations}} pending invitations. File contents are not shown here.</p></div>
<div class="c-grid-2">
<div class="c-card"><h2>Internal notes</h2><p class="c-muted">Visible to platform staff only.</p><form class="c-form" @submit.prevent="addNote"><label>New note<textarea v-model="note" rows="3" maxlength="5000"></textarea></label><div class="c-actions"><button class="c-btn" :disabled="busy||!note.trim()">Add note</button></div></form>
<ul class="c-list"><li v-for="n in d.notes" :key="n.id"><strong>{{n.author?.name||'Staff'}}</strong><span class="c-list-meta">{{when(n.created_at)}}</span><p class="c-pre">{{n.body}}</p></li><li v-if="!d.notes.length" class="c-empty">No notes yet.</li></ul></div>
<div class="c-card"><h2>Requests and admin history</h2><ul class="c-list"><li v-for="r in d.requests" :key="'r'+r.id"><strong>{{r.type==='signup'?'Signup approval':'Plan change request'}}</strong> <Badge :value="r.status"/><span class="c-list-meta">{{when(r.created_at)}}</span></li><li v-for="h in d.history" :key="h.id"><strong>{{h.action==='platform_support.accessed'?'Platform support accessed this workspace':h.action.replace('admin.organization.','').replace(/_/g,' ')}}</strong><span class="c-list-meta">{{when(h.created_at)}}<span v-if="h.metadata?.reason"> · {{h.metadata.reason}}</span></span></li><li v-if="!d.requests.length&&!d.history.length" class="c-empty">No admin activity yet.</li></ul>
<p class="c-muted">Subscription record: {{d.subscription?d.subscription.provider+' · '+d.subscription.status:'none'}}</p></div></div>
</template><p v-else-if="!error" class="c-loading" role="status">Loading…</p>
</section></template>
