<script setup lang="ts">
import {ref,onMounted} from 'vue';import {api,when} from '../util';import Stat from '../components/Stat.vue';import Badge from '../components/Badge.vue';
const d=ref<any>(null),error=ref('');
const money=(n:number)=>n.toLocaleString(undefined,{style:'currency',currency:'USD'});
onMounted(async()=>{try{d.value=await api('/api/admin/revenue')}catch(e:any){error.value=e.message}});
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Revenue</h1><p class="c-lead">Built only from stored billing and subscription records.</p></div></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<template v-if="d">
<div v-if="d.test_data" class="c-alert warn" role="note"><strong>Test provider data.</strong> {{d.billing.notice}}</div>
<div class="c-stats">
<div v-if="!d.pricing_configured" class="c-stat c-stat-wide"><span class="c-stat-label">Monthly recurring revenue</span><strong class="c-stat-value c-stat-text">Pricing not configured</strong><span class="c-stat-hint">{{d.message}}</span></div>
<template v-else><Stat label="Monthly recurring revenue (MRR)" :value="money(d.mrr)"/><Stat label="Annual run rate (ARR)" :value="money(d.arr)"/></template>
<Stat label="Active subscriptions" :value="d.active_subscriptions" hint="Organizations with status Active"/>
<Stat label="Billing provider" :value="d.billing.provider" :hint="d.billing.live_provider_configured?'Live provider configured':'No live provider'"/>
</div>
<div class="c-card"><h2>Subscriptions</h2><div class="c-table-wrap"><table><caption class="sr-only">Billing subscriptions</caption><thead><tr><th scope="col">Organization</th><th scope="col">Provider</th><th scope="col">Status</th><th scope="col">Created</th><th scope="col">Updated</th></tr></thead><tbody>
<tr v-for="s in d.subscriptions" :key="s.id"><td><RouterLink :to="'/organizations/'+s.organization_id">{{s.organization}}</RouterLink></td><td>{{s.provider}}<span v-if="s.provider==='fake'" class="c-badge tone-amber">Test</span></td><td><Badge :value="s.status"/></td><td>{{when(s.created_at)}}</td><td>{{when(s.updated_at)}}</td></tr>
<tr v-if="!d.subscriptions.length"><td colspan="5" class="c-empty">No billing subscriptions have been recorded.</td></tr></tbody></table></div></div>
<div class="c-card"><h2>Subscription events</h2><div class="c-table-wrap"><table><caption class="sr-only">Billing events</caption><thead><tr><th scope="col">Organization</th><th scope="col">Kind</th><th scope="col">Action</th><th scope="col">Status</th><th scope="col">When</th></tr></thead><tbody>
<tr v-for="e in d.events" :key="e.id"><td>{{e.organization}}</td><td>{{e.kind}}</td><td>{{e.action||'—'}}<span v-if="e.plan_id"> · plan {{e.plan_id}}</span></td><td>{{e.status}}</td><td>{{when(e.created_at)}}</td></tr>
<tr v-if="!d.events.length"><td colspan="5" class="c-empty">No billing events have been recorded.</td></tr></tbody></table></div>
<p class="c-muted">{{d.invoices_notice}}</p></div>
</template><p v-else-if="!error" class="c-loading" role="status">Loading…</p>
</section></template>
