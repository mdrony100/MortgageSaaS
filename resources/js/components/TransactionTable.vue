<script setup lang="ts">
import { computed, ref } from 'vue'
import { formatDate, formatMoney, humanize } from '../formatMoney'

const props = defineProps<{ transactions?: any[]; statementId?: string | null; currency?: string }>()
const emit = defineEmits<{ open: [transaction: any] }>()
const query = ref('')
const category = ref('')
const selected = ref<string | number | null>(null)

function pageOf(row: any): number | undefined {
  const page = Number(row?.data?.page)
  return Number.isFinite(page) && page > 0 ? page : undefined
}

function categoryOf(row: any): string {
  return String(row?.current_category || row?.data?.category_suggestion || '')
}

const categories = computed(() => [...new Set((props.transactions || []).map(categoryOf).filter(Boolean))].sort())

const rows = computed(() => (props.transactions || []).filter((row) => {
  if (props.statementId && props.statementId !== 'all' && row?.statement_id !== undefined && row?.statement_id !== null && String(row.statement_id) !== props.statementId) return false
  if (category.value && categoryOf(row) !== category.value) return false
  const needle = query.value.trim().toLowerCase()
  if (!needle) return true
  const haystack = [row?.posting_date, row?.data?.description, row?.data?.reference, categoryOf(row), row?.id].join(' ').toLowerCase()
  return haystack.includes(needle)
}))

function side(amount: unknown): string {
  if (amount === null || amount === undefined || amount === '') return '—'
  const numeric = typeof amount === 'number' ? amount : Number(String(amount).replace(/[$,]/g, ''))
  if (!Number.isFinite(numeric) || numeric === 0) return '—'
  return formatMoney(amount, props.currency || 'USD')
}

function open(row: any) {
  if (!pageOf(row)) return
  selected.value = row.id
  emit('open', row)
}
</script>
<template>
<section id="statement-transactions">
  <h2>Transactions</h2>
  <div class="tx-tools">
    <label>Search
      <input v-model="query" type="search" placeholder="Description, date, reference" />
    </label>
    <label>Category
      <select v-model="category" aria-label="Filter by category">
        <option value="">All categories</option>
        <option v-for="item in categories" :key="item" :value="item">{{ humanize(item) }}</option>
      </select>
    </label>
  </div>
  <div v-if="rows.length" class="tx-scroll">
    <table class="tx-table">
      <caption class="sr-only">Statement transactions. Choose a row to open that page when the page is known.</caption>
      <thead>
        <tr>
          <th>Date</th>
          <th>Description</th>
          <th class="num">Credit</th>
          <th class="num">Debit</th>
          <th class="num">Balance</th>
          <th>Reference</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in rows"
          :key="row.id"
          :class="{ clickable: !!pageOf(row), active: selected === row.id }"
          :tabindex="pageOf(row) ? 0 : undefined"
          :title="pageOf(row) ? 'Open page ' + pageOf(row) : 'Location unknown'"
          @click="open(row)"
          @keydown.enter.prevent="open(row)"
        >
          <td>{{ formatDate(row.posting_date) }}</td>
          <td>{{ row.data?.description || '—' }}</td>
          <td class="num">{{ side(row.credit_minor ?? row.data?.credit) }}</td>
          <td class="num">{{ side(row.debit_minor ?? row.data?.debit) }}</td>
          <td class="num">{{ formatMoney(row.balance_minor ?? row.data?.running_balance, currency || 'USD') }}</td>
          <td>{{ row.data?.reference || '—' }}<small>{{ humanize(categoryOf(row)) || 'Uncategorized' }}</small><small v-if="!pageOf(row)">Location unknown</small></td>
        </tr>
      </tbody>
    </table>
  </div>
  <p v-else-if="query || category" class="micro">No transactions match this search.</p>
  <p v-else class="micro">Transactions show up here after the statement is read.</p>
</section>
</template>
