<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { Layers, Plus } from 'lucide-vue-next'
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
const requestLimit = ref(4)
const historyLimit = ref(6)

const otherPlans = computed(() => (data.value?.plans || []).filter((p: any) => p.id !== data.value?.plan?.id && p.is_active))
const packs = computed(() => data.value?.plan?.topup_packs || [])
const pendingPlan = computed(() => (data.value?.requests || []).find((r: any) => r.kind === 'plan' && r.status === 'pending'))
const pendingPack = computed(() => (data.value?.requests || []).find((r: any) => r.kind === 'pack' && r.status === 'pending'))
const requests = computed(() => (data.value?.requests || []).slice(0, requestLimit.value))
const history = computed(() => (data.value?.history || []).slice(0, historyLimit.value))

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
function when(value: string) {
  return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}
onMounted(() => { load() })
</script>
<template>
<div class="plan-page">
<p v-if="error" class="alert error" role="alert">{{ error }}</p>
<p v-if="success" class="alert success" role="status">{{ success }}</p>
<template v-if="data">
<p v-if="data.warning_message" class="plan-banner warn" role="status">{{ data.warning_message }} Payment is arranged offline, and nothing on this page charges a card.</p>
<p v-else class="plan-banner info" role="note">{{ data.offline_notice }}</p>
<div class="stat-grid">
<article class="stat"><div><span>Current plan</span><strong>{{ data.plan.name }}</strong><small>{{ data.plan.monthly_page_quota ? data.plan.monthly_page_quota.toLocaleString() + ' pages each month' : data.plan.trial_page_allowance.toLocaleString() + ' pages in total' }}</small></div></article>
<article class="stat"><div><span>Pages used this period</span><strong>{{ data.used_this_period.toLocaleString() }}</strong><small>{{ data.percent_used }}% of pages currently available</small></div></article>
<article class="stat"><div><span>Pages remaining</span><strong>{{ data.balance.toLocaleString() }}</strong><small>Includes monthly pages and any top-ups</small></div></article>
</div>
<div class="meter" role="meter" :aria-valuenow="data.percent_used" aria-valuemin="0" aria-valuemax="100" aria-label="Pages used this period"><span :style="{ width: Math.min(100, data.percent_used) + '%' }" :class="{ hot: data.warning }"></span></div>
<p class="micro">${{ data.plan.display_monthly_price }} / month. The period refreshes {{ data.period_ends_at ? new Date(data.period_ends_at).toLocaleDateString() : 'monthly' }}.</p>

<template v-if="canRequest">
<div class="plan-actions">
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
<label>Pack<select v-model.number="packPages" required><option v-for="p in packs" :key="p.pages" :value="p.pages">{{ p.label }} — ${{ p.price }}</option></select></label>
<label>Note (optional)<textarea v-model="note" rows="3" maxlength="1000"></textarea></label>
<button class="primary" :disabled="busy || !packPages">{{ busy ? 'Sending…' : 'Send top-up request' }}</button>
</template>
</form>

<article class="card plan-panel">
<header class="plan-panel-head"><h2>Your requests</h2><span>{{ (data.requests || []).length }}</span></header>
<ul v-if="requests.length" class="plan-rows">
<li v-for="request in requests" :key="request.id">
<span class="plan-icon" aria-hidden="true"><Layers v-if="request.kind === 'plan'" :size="18"/><Plus v-else :size="18"/></span>
<div class="plan-row-copy"><strong>{{ label(request) }}</strong><small>${{ request.amount }} · {{ when(request.created_at) }}<template v-if="request.decision_reason"> · {{ request.decision_reason }}</template></small></div>
<span class="plan-pill" :class="request.status">{{ request.status }}</span>
</li>
</ul>
<p v-else class="plan-empty">No requests yet. Ask for a plan change or more pages when you need them.</p>
<button v-if="(data.requests || []).length > requestLimit" type="button" class="text-button" @click="requestLimit = data.requests.length">Show more</button>
</article>

<article class="card plan-panel">
<header class="plan-panel-head"><h2>Usage history</h2><span>{{ (data.history || []).length }}</span></header>
<div v-if="history.length" class="plan-table-wrap">
<table class="plan-table">
<thead><tr><th>Date</th><th>Change</th><th>Reason</th><th class="num">Balance</th></tr></thead>
<tbody>
<tr v-for="entry in history" :key="entry.id">
<td data-label="Date">{{ when(entry.created_at) }}</td>
<td data-label="Change" :class="entry.pages < 0 ? 'neg' : 'pos'">{{ entry.pages > 0 ? '+' : '' }}{{ entry.pages.toLocaleString() }} pages</td>
<td data-label="Reason">{{ entry.reason }}</td>
<td data-label="Balance" class="num">{{ entry.balance_after.toLocaleString() }}</td>
</tr>
</tbody>
</table>
</div>
<p v-else class="plan-empty">No page activity yet.</p>
<button v-if="(data.history || []).length > historyLimit" type="button" class="text-button" @click="historyLimit = data.history.length">Show more</button>
</article>
</template>
<p v-else class="plan-banner info">An organization admin can request a plan change or more pages from this page.</p>
</template>
<p v-else-if="!error" role="status">Loading your plan…</p>
</div>
</template>
<style>
.plan-page{font-size:16px}
.plan-banner{margin:0 0 16px;padding:14px 16px;border-radius:10px;font-size:16px;line-height:1.45}
.plan-banner.warn{background:#fffbeb;border:1px solid #fde68a;color:#78350f}
.plan-banner.info{background:#f3faf8;border:1px solid #b7e0d8;color:#134e48}
.meter{height:12px;background:#e2e8f0;border-radius:99px;overflow:hidden;margin:8px 0 16px}
.meter span{display:block;height:100%;background:#0e7c71}
.meter span.hot{background:#b45309}
.plan-actions{display:flex;flex-wrap:wrap;gap:10px;margin:8px 0 16px}
.plan-actions button{min-height:44px}
.plan-page .plan-actions .primary,.plan-page form .primary{background:#0e7c71;border-color:#0e7c71;color:#fff}
.plan-page .plan-actions .primary:hover,.plan-page form .primary:hover{background:#0b655c}
.plan-panel{margin-top:16px;padding:18px 20px}
.plan-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
.plan-panel-head h2{margin:0;font-size:19px}
.plan-panel-head span{min-width:28px;padding:2px 8px;border-radius:99px;background:#e8eef5;color:#334155;font-size:14px;font-weight:700;text-align:center}
.plan-rows{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
.plan-rows li{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#fff}
.plan-icon{display:grid;place-items:center;width:36px;height:36px;border-radius:8px;background:#f3faf8;color:#0e7c71}
.plan-row-copy{min-width:0}
.plan-row-copy strong{display:block;font-size:16px}
.plan-row-copy small{display:block;color:#475569;font-size:15px}
.plan-pill{padding:4px 10px;border-radius:99px;font-size:14px;font-weight:700;text-transform:capitalize}
.plan-pill.pending{background:#fef3c7;color:#92400e}
.plan-pill.approved{background:#dcfce7;color:#166534}
.plan-pill.rejected{background:#fee2e2;color:#991b1b}
.plan-pill.cancelled{background:#e2e8f0;color:#334155}
.plan-empty{margin:0;padding:14px;border-radius:10px;background:#f8fafc;color:#475569;font-size:16px}
.plan-table{width:100%;border-collapse:collapse;font-size:16px}
.plan-table th{text-align:left;font-size:14px;color:#475569;padding:8px 10px}
.plan-table td{padding:12px 10px;border-top:1px solid #e2e8f0;vertical-align:top}
.plan-table .num{text-align:right;font-variant-numeric:tabular-nums;font-weight:650}
.plan-table .pos{color:#166534;font-weight:700}
.plan-table .neg{color:#991b1b;font-weight:700}
@media(max-width:700px){
.plan-rows li{grid-template-columns:auto minmax(0,1fr); }
.plan-pill{grid-column:2}
.plan-table thead{display:none}
.plan-table tr{display:block;padding:12px 0;border-top:1px solid #e2e8f0}
.plan-table td{display:flex;justify-content:space-between;gap:12px;border:0;padding:4px 0}
.plan-table td::before{content:attr(data-label);color:#475569;font-weight:600}
.plan-table .num{text-align:right}
}
</style>
