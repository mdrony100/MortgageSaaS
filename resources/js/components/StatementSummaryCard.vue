<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { formatDate, formatMoney } from '../formatMoney'

const props = defineProps<{ statements?: any[] }>()
const emit = defineEmits<{ select: [id: string] }>()
const selected = ref('all')

function read(record: any, key: string) {
  const data = record?.data && typeof record.data === 'object' ? record.data : null
  const nested = data && key in data ? data[key] : undefined
  if (nested !== undefined && nested !== null && nested !== '') return nested
  const top = record?.[key]
  if (top !== undefined && top !== null && top !== '') return top
  return null
}

const options = computed(() => (props.statements || []).map((record, index) => {
  const id = String(record?.id ?? index)
  const institution = read(record, 'institution') || 'Statement'
  const period = read(record, 'period_start')
  return { id, record, label: period ? `${institution} · ${formatDate(period)}` : `${institution} ${index + 1}` }
}))

const current = computed(() => options.value.find((option) => option.id === selected.value)?.record || options.value[0]?.record || null)
const currency = computed(() => {
  const code = read(current.value, 'currency')
  return typeof code === 'string' && /^[A-Z]{3}$/.test(code) ? code : 'USD'
})

watch(options, (list) => {
  if (!list.some((option) => option.id === selected.value)) selected.value = list[0]?.id || 'all'
}, { immediate: true })
watch(selected, (id) => emit('select', id), { immediate: true })

const period = computed(() => {
  const start = read(current.value, 'period_start')
  const end = read(current.value, 'period_end')
  if (!start && !end) return 'Period not on the statement'
  return `${formatDate(start)} – ${formatDate(end)}`
})

const figures = computed(() => [
  ['Opening balance', read(current.value, 'opening_balance')],
  ['Closing balance', read(current.value, 'closing_balance')],
  ['Credits', read(current.value, 'reported_credits')],
  ['Debits', read(current.value, 'reported_debits')],
  ['Fees', read(current.value, 'reported_fees')],
] as const)
</script>
<template>
<article class="statement-card" id="statement-summary">
  <div class="statement-card-head">
    <div class="statement-identity">
      <p class="eyebrow">Statement</p>
      <h3 class="statement-institution">{{ read(current, 'institution') || 'Statement not read yet' }}</h3>
      <p class="statement-holder">{{ read(current, 'holder') || 'Account holder not on the statement' }} · {{ read(current, 'masked_account') || 'Account number unavailable' }}</p>
      <p class="micro statement-period">{{ period }}</p>
    </div>
    <label v-if="options.length > 1" class="statement-picker">Statement
      <select v-model="selected" aria-label="Statement">
        <option v-for="option in options" :key="option.id" :value="option.id">{{ option.label }}</option>
      </select>
    </label>
  </div>
  <div v-if="current" class="money-grid">
    <div v-for="[label, amount] in figures" :key="label">
      <span>{{ label }}</span>
      <strong>{{ formatMoney(amount, currency) }}</strong>
    </div>
  </div>
  <p v-else class="micro">Statement totals show up here after the document is read. Nothing is calculated until then.</p>
</article>
</template>
