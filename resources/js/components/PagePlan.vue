<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { api } from '../api'

const props = defineProps<{ organizationId: number, canRequest: boolean }>()
const data = ref<any>(null)
const error = ref('')
const success = ref('')
const busy = ref(false)
const planId = ref(0)
const packPages = ref(0)
const note = ref('')
const showPlan = ref(false)
const showPack = ref(false)

const otherPlans = computed(() => (data.value?.plans || []).filter((p: any) => p.id !== data.value?.plan?.id && p.is_active))
const packs = computed(() => data.value?.plan?.topup_packs || [])
const pendingPlan = computed(() => (data.value?.requests || []).find((r: any) => r.kind === 'plan' && r.status === 'pending'))
const pendingPack = computed(() => (data.value?.requests || []).find((r: any) => r.kind === 'pack' && r.status === 'pending'))

async function load() {
  try { data.value = await api('/api/organizations/' + props.organizationId + '/page-credits') }
  catch (e: any) { error.value = e.message }
}
async function send(kind: 'plan' | 'pack') {
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    const body: any = { kind, note: note.value || null }
    if (kind === 'plan') body.plan_id = planId.value
    else body.pack_pages = packPages.value
    const result = await api('/api/organizations/' + props.organizationId + '/payment-requests', 'POST', body)
    success.value = result.message
    note.value = ''
    showPlan.value = false
    showPack.value = false
    await load()
  } catch (e: any) { error.value = e.message }
  finally { busy.value = false }
}
function label(request: any) {
  if (request.kind === 'plan') return 'Plan change to ' + (request.plan?.name || 'another plan')
  return 'Top-up of ' + Number(request.pack_pages).toLocaleString() + ' pages'
}
onMounted(() => { load() })
</script>
<template>
<div class="plan-page">
<p v-if="error" class="alert error" role="alert">{{ error }}</p>
<p v-if="success" class="alert success" role="status">{{ success }}</p>
<p v-if="data?.warning_message" class="credit-banner" role="status">{{ data.warning_message }}</p>
<template v-if="data">
<p class="notice">{{ data.offline_notice }}</p>
<div class="stat-grid">
<article class="stat"><div><span>Current plan</span><strong>{{ data.plan.name }}</strong><small>{{ data.plan.monthly_page_quota ? data.plan.monthly_page_quota.toLocaleString() + ' pages each month' : data.plan.trial_page_allowance.toLocaleString() + ' pages in total' }}</small></div></article>
<article class="stat"><div><span>Pages used this period</span><strong>{{ data.used_this_period.toLocaleString() }}</strong><small>{{ data.percent_used }}% of pages currently available</small></div></article>
<article class="stat"><div><span>Pages remaining</span><strong>{{ data.balance.toLocaleString() }}</strong><small>Includes monthly pages and any top-ups</small></div></article>
</div>
<div class="meter" role="meter" :aria-valuenow="data.percent_used" aria-valuemin="0" aria-valuemax="100" aria-label="Pages used this period"><span :style="{ width: Math.min(100, data.percent_used) + '%' }" :class="{ hot: data.warning }"></span></div>
<p class="micro">Placeholder price ${{ data.plan.display_monthly_price }} / month. The period refreshes {{ data.period_ends_at ? new Date(data.period_ends_at).toLocaleDateString() : 'monthly' }}.</p>

<template v-if="canRequest">
<div class="actions spaced">
<button class="primary" type="button" @click="showPlan = !showPlan; showPack = false; planId = otherPlans[0]?.id || 0">Request plan change</button>
<button type="button" @click="showPack = !showPack; showPlan = false; packPages = packs[0]?.pages || 0">Request page top-up</button>
</div>
<form v-if="showPlan" class="card form-stack" @submit.prevent="send('plan')">
<h2>Request a plan change</h2>
<p v-if="pendingPlan" class="notice">A plan change is already waiting for review.</p>
<template v-else>
<label>Plan<select v-model.number="planId" required><option v-for="p in otherPlans" :key="p.id" :value="p.id">{{ p.name }} — ${{ p.display_monthly_price }} / month · {{ p.monthly_page_quota ? p.monthly_page_quota.toLocaleString() + ' pages / month' : p.trial_page_allowance.toLocaleString() + ' pages total' }}</option></select></label>
<label>Note (optional)<textarea v-model="note" rows="3" maxlength="1000"></textarea></label>
<button class="primary" :disabled="busy || !planId">{{ busy ? 'Sending…' : 'Send plan request' }}</button>
</template>
</form>
<form v-if="showPack" class="card form-stack" @submit.prevent="send('pack')">
<h2>Request a page top-up</h2>
<p v-if="pendingPack" class="notice">A top-up request is already waiting for review.</p>
<p v-else-if="!packs.length">This plan does not have a page pack yet. Ask for a plan change instead.</p>
<template v-else>
<label>Pack<select v-model.number="packPages" required><option v-for="p in packs" :key="p.pages" :value="p.pages">{{ p.label }} — ${{ p.price }} placeholder</option></select></label>
<label>Note (optional)<textarea v-model="note" rows="3" maxlength="1000"></textarea></label>
<button class="primary" :disabled="busy || !packPages">{{ busy ? 'Sending…' : 'Send top-up request' }}</button>
</template>
</form>
<h2 class="spaced">Your requests</h2>
<ul class="request-list">
<li v-for="request in data.requests" :key="request.id"><strong>{{ label(request) }}</strong> — {{ request.status }}<span v-if="request.decision_reason"> · {{ request.decision_reason }}</span><small>${{ request.amount }} placeholder · {{ new Date(request.created_at).toLocaleDateString() }}</small></li>
<li v-if="!data.requests.length">No requests yet.</li>
</ul>
<h2 class="spaced">Usage history</h2>
<ul class="request-list">
<li v-for="entry in data.history" :key="entry.id"><strong>{{ entry.pages > 0 ? '+' : '' }}{{ entry.pages.toLocaleString() }} pages</strong> · {{ entry.type.replaceAll('_', ' ') }}<small>{{ entry.reason }} · balance {{ entry.balance_after.toLocaleString() }} · {{ new Date(entry.created_at).toLocaleString() }}</small></li>
<li v-if="!data.history.length">No page activity yet.</li>
</ul>
</template>
<p v-else class="notice">An organization admin can request a plan change or more pages from this page.</p>
</template>
<p v-else-if="!error" role="status">Loading your plan…</p>
</div>
</template>
<style>
.plan-page{font-size:16px}
.credit-banner{margin:0 0 16px;padding:12px 14px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;color:#78350f;font-size:16px}
.meter{height:12px;background:#e2e8f0;border-radius:99px;overflow:hidden;margin:8px 0 16px}
.meter span{display:block;height:100%;background:#0e7c71}
.meter span.hot{background:#b45309}
.request-list{list-style:none;padding:0;margin:8px 0 0}
.request-list li{padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:16px}
.request-list small{display:block;color:#475569;font-size:15px}
</style>
