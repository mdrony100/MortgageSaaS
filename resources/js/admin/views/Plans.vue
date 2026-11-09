<script setup lang="ts">
import {ref,onMounted} from 'vue';import {api,bytes} from '../util';
const d=ref<any>(null),error=ref('');
onMounted(async()=>{try{d.value=await api('/api/admin/plans')}catch(e:any){error.value=e.message}});
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Plans &amp; billing</h1><p class="c-lead">Plan limits enforced by the platform, and billing provider status. Read-only.</p></div></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<template v-if="d">
<div class="c-plan-grid"><article v-for="p in d.plans" :key="p.id" class="c-card c-plan"><h2>{{p.name}}</h2><p class="c-plan-price">{{p.approved_price?'$'+p.approved_price+' / month':p.pricing_status}}</p><ul><li><strong>{{p.file_limit}}</strong> loan files</li><li><strong>{{p.member_limit}}</strong> team members</li><li><strong>{{bytes(p.storage_limit_bytes)}}</strong> storage</li></ul><p class="c-muted">{{p.organizations}} organization{{p.organizations===1?'':'s'}} on this plan</p></article></div>
<div class="c-card"><h2>Billing provider</h2><p v-if="d.billing.test_only" class="c-alert warn"><strong>Test provider.</strong> {{d.billing.notice}}</p><p v-else class="c-alert info">{{d.billing.notice}}</p>
<dl class="c-dl"><div><dt>Provider</dt><dd>{{d.billing.provider}}</dd></div><div><dt>Live provider configured</dt><dd>{{d.billing.live_provider_configured?'Yes':'No'}}</dd></div><div><dt>Test provider enabled</dt><dd>{{d.billing.fake_enabled?'Yes':'No'}}</dd></div><div><dt>Webhook secret set</dt><dd>{{d.billing.webhook_secret_configured?'Yes':'No'}}</dd></div></dl>
<p class="c-muted">Secret values are never shown. Approved prices are published by adding them to config/billing.php (catalog).</p></div>
</template><p v-else-if="!error" class="c-loading" role="status">Loading…</p>
</section></template>
