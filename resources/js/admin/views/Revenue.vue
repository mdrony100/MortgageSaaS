<script setup lang="ts">
import {ref, onMounted} from 'vue'
import {api, when} from '../util'
import Stat from '../components/Stat.vue'
import Badge from '../components/Badge.vue'

const d = ref<any>(null)
const error = ref('')
const money = (n: number) => Number(n || 0).toLocaleString(undefined, {style: 'currency', currency: 'USD'})
const collectedHint = () => d.value
  ? `${d.value.month_label} · plans ${money(d.value.collected_plans_this_month)} · page packs ${money(d.value.collected_packs_this_month)}`
  : ''

onMounted(async () => {
  try { d.value = await api('/api/admin/revenue') }
  catch (e: any) { error.value = e.message }
})
</script>
<template>
<section>
  <div class="c-head">
    <div>
      <span class="c-eyebrow">Owner console</span>
      <h1>Revenue</h1>
      <p class="c-lead">Monthly recurring revenue from active organization plans. Collected revenue is approved offline payments for plans and page packs.</p>
    </div>
  </div>
  <p v-if="error" class="c-alert error" role="alert">{{ error }}</p>
  <template v-if="d">
    <p class="c-muted c-billing-line">Billing: {{ d.billing.label }}</p>
    <div class="c-stats c-stats-even">
      <Stat label="Monthly recurring revenue" :value="money(d.mrr)" hint="Active organizations × plan price"/>
      <Stat label="Annual run rate" :value="money(d.arr)" hint="Monthly recurring revenue × 12"/>
      <Stat label="Active subscriptions" :value="d.active_subscriptions" hint="Organizations with status Active"/>
      <Stat label="Active plans" :value="d.active_plans" hint="Plans in use by those organizations"/>
      <Stat label="Pending payment requests" :value="d.pending_payment_requests" to="/payment-requests" hint="Waiting for manual approval"/>
      <Stat label="Revenue collected this month" :value="money(d.collected_this_month)" :hint="collectedHint()"/>
    </div>

    <div class="c-card c-flush">
      <div class="c-pad"><h2>Payments</h2><p class="c-muted">Approved and pending requests for plan changes and page packs.</p></div>
      <div class="c-table-wrap">
        <table>
          <caption class="sr-only">Approved and pending payment requests</caption>
          <thead>
            <tr>
              <th scope="col">Organization</th>
              <th scope="col">Request</th>
              <th scope="col" class="num">Amount</th>
              <th scope="col">Status</th>
              <th scope="col">Requested</th>
              <th scope="col">Decided</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in d.payments" :key="row.id">
              <td><RouterLink class="c-strong-link" :to="'/organizations/' + row.organization_id">{{ row.organization }}</RouterLink></td>
              <td>{{ row.kind }}<span class="c-sub">{{ row.detail }}</span></td>
              <td class="num">{{ money(row.amount) }}</td>
              <td><Badge :value="row.status"/></td>
              <td class="c-nowrap">{{ when(row.created_at) }}</td>
              <td class="c-nowrap">{{ row.decided_at ? when(row.decided_at) : '—' }}</td>
            </tr>
            <tr v-if="!d.payments.length">
              <td colspan="6" class="c-empty">No payment requests yet. Plan changes and page packs appear here after a customer sends a request.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="c-card c-flush">
      <div class="c-pad"><h2>Monthly recurring revenue by plan</h2></div>
      <div class="c-table-wrap">
        <table>
          <caption class="sr-only">Monthly recurring revenue by plan</caption>
          <thead>
            <tr>
              <th scope="col">Plan</th>
              <th scope="col" class="num">Organizations</th>
              <th scope="col" class="num">Monthly price</th>
              <th scope="col" class="num">Monthly recurring revenue</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in d.plan_breakdown" :key="row.plan">
              <td>{{ row.plan }}</td>
              <td class="num">{{ row.organizations }}</td>
              <td class="num">{{ money(row.monthly_price) }}</td>
              <td class="num">{{ money(row.mrr) }}</td>
            </tr>
            <tr v-if="!d.plan_breakdown.length">
              <td colspan="4" class="c-empty">No organizations are on an active plan, so monthly recurring revenue is {{ money(0) }}.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </template>
  <p v-else-if="!error" class="c-loading" role="status">Loading…</p>
</section>
</template>
