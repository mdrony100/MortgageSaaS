<script setup lang="ts">
import {ref,onMounted} from 'vue';import {api,when,day} from '../util';import Stat from '../components/Stat.vue';import Badge from '../components/Badge.vue';import HBars from '../components/HBars.vue';
const d=ref<any>(null),error=ref('');
onMounted(async()=>{try{d.value=await api('/api/admin/overview')}catch(e:any){error.value=e.message}});
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Overview</h1><p class="c-lead">Live counts from the MortgageLens database.</p></div></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<template v-if="d">
<div class="c-stats">
<Stat label="Organizations" :value="d.counts.organizations" to="/organizations"/>
<Stat label="Users" :value="d.counts.users" to="/users"/>
<Stat label="Loan files (count only)" :value="d.counts.loan_files"/>
<Stat label="Waiting for approval" :value="d.counts.pending_approvals" to="/approvals"/>
<Stat label="New website inquiries" :value="d.counts.new_inquiries" to="/inquiries"/>
<Stat label="Suspended organizations" :value="d.counts.suspended_organizations" :hint="d.counts.disabled_users+' disabled users'"/>
</div>
<div class="c-grid-2">
<div class="c-card"><HBars title="Organizations by subscription status" :data="d.subscription_statuses.map((s:any)=>({label:s.label,count:s.count}))"/></div>
<div class="c-card"><h2>Recent organizations</h2><ul class="c-list"><li v-for="o in d.recent_organizations" :key="o.id"><RouterLink :to="'/organizations/'+o.id">{{o.name}}</RouterLink><span class="c-list-meta">{{o.plan?.name}} · <Badge :value="o.subscription_status"/><Badge v-if="o.approval_status!=='approved'" :value="o.approval_status"/> · {{day(o.created_at)}}</span></li><li v-if="!d.recent_organizations.length" class="c-empty">No organizations yet.</li></ul></div>
</div>
<div class="c-grid-2">
<div class="c-card"><h2>Recent signups</h2><ul class="c-list"><li v-for="u in d.recent_signups" :key="u.id"><strong>{{u.name}}</strong><span class="c-list-meta">{{u.email}} · {{u.email_verified_at?'Verified':'Not verified'}} · {{when(u.created_at)}}</span></li><li v-if="!d.recent_signups.length" class="c-empty">No signups yet.</li></ul></div>
<div class="c-card"><h2>Recent website inquiries</h2><ul class="c-list"><li v-for="i in d.recent_inquiries" :key="i.id"><RouterLink :to="'/inquiries?id='+i.id">{{i.name}} · {{i.company}}</RouterLink><span class="c-list-meta">{{i.inquiry_type}} · <Badge :value="i.status"/> · {{when(i.created_at)}}</span></li><li v-if="!d.recent_inquiries.length" class="c-empty">No inquiries yet.</li></ul></div>
</div>
</template><p v-else-if="!error" class="c-loading" role="status">Loading…</p>
</section></template>
