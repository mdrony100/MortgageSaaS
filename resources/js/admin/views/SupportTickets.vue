<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { api, qs, when } from '../util'
import Badge from '../components/Badge.vue'
import Pager from '../components/Pager.vue'

const filters = reactive({ status: '', q: '', page: 1 })
const page = ref<any>(null)
const list = ref<any[]>([])
const statuses = ref<Record<string, string>>({})
const categories = ref<Record<string, string>>({})
const priorities = ref<Record<string, string>>({})
const selected = ref<any>(null)
const reply = ref('')
const error = ref('')
const success = ref('')
const busy = ref(false)
const loaded = ref(false)

async function load() {
  error.value = ''
  try {
    const data = await api('/api/admin/support-tickets' + qs(filters))
    page.value = data.tickets
    list.value = data.tickets.data
    statuses.value = data.statuses
    categories.value = data.categories
    priorities.value = data.priorities
  } catch (e: any) {
    error.value = e.message
    list.value = []
  } finally {
    loaded.value = true
  }
}

async function open(id: number) {
  error.value = ''
  success.value = ''
  try { selected.value = (await api('/api/admin/support-tickets/' + id)).ticket }
  catch (e: any) { error.value = e.message }
}

async function setStatus(status: string) {
  if (!selected.value || status === selected.value.status) return
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    selected.value = (await api('/api/admin/support-tickets/' + selected.value.id, 'PATCH', { status })).ticket
    success.value = 'Status updated.'
    await load()
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

async function send() {
  if (!reply.value.trim() || !selected.value) return
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    selected.value = (await api('/api/admin/support-tickets/' + selected.value.id + '/replies', 'POST', { message: reply.value })).ticket
    reply.value = ''
    success.value = 'Reply sent.'
    await load()
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

watch(() => filters.status, () => { filters.page = 1; load() })
onMounted(load)
</script>

<template>
<section>
  <div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Support tickets</h1><p class="c-lead">Tickets from every organization. Customer loan documents stay in the customer workspace.</p></div></div>
  <p v-if="error" class="c-alert error" role="alert">{{ error }}</p>
  <p v-if="success" class="c-alert success" role="status">{{ success }}</p>
  <form class="c-filters" @submit.prevent="filters.page = 1; load()">
    <label>Status
      <select v-model="filters.status">
        <option value="">All statuses</option>
        <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
      </select>
    </label>
    <label>Search<input v-model="filters.q" placeholder="Subject or organization"/></label>
    <button class="c-btn">Apply</button>
  </form>
  <div class="c-split">
    <div class="c-card c-flush">
      <div class="c-table-wrap"><table>
        <caption class="sr-only">Support tickets</caption>
        <thead><tr><th>Ticket</th><th>Status</th><th>Updated</th></tr></thead>
        <tbody>
          <tr v-for="item in list" :key="item.id" :class="{ selected: selected?.id === item.id }">
            <td><button class="c-link-btn" @click="open(item.id)">{{ item.subject }}</button><span class="c-sub">{{ item.organization }} · {{ item.requester }} · {{ priorities[item.priority] || item.priority }}</span></td>
            <td><Badge :value="item.status" :label="statuses[item.status]"/></td>
            <td>{{ when(item.updated_at) }}</td>
          </tr>
          <tr v-if="loaded && !list.length"><td colspan="3" class="c-empty">No tickets match these filters.</td></tr>
        </tbody>
      </table></div>
      <Pager :page="page" @go="(p: number) => { filters.page = p; load() }"/>
    </div>
    <article v-if="selected" class="c-card">
      <h2>{{ selected.subject }}</h2>
      <p class="c-muted">{{ selected.organization }} · {{ selected.requester }} · {{ categories[selected.category] || selected.category }} · {{ priorities[selected.priority] || selected.priority }}</p>
      <label>Status
        <select :value="selected.status" :disabled="busy" @change="setStatus(($event.target as HTMLSelectElement).value)">
          <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
        </select>
      </label>
      <article v-for="message in selected.messages" :key="message.id" class="c-card">
        <h3>{{ message.author || 'Member' }} · {{ message.staff ? 'Staff' : 'Customer' }}</h3>
        <p class="c-pre">{{ message.body }}</p>
        <p class="c-muted">{{ when(message.created_at) }}</p>
        <p v-for="file in message.attachments" :key="file.id"><a :href="'/api/admin/support-ticket-attachments/' + file.id">{{ file.name }}</a> · {{ file.scan_status }}</p>
      </article>
      <form class="c-form" @submit.prevent="send">
        <label>Reply<textarea v-model="reply" required maxlength="5000"/></label>
        <button class="c-btn" :disabled="busy">Send reply</button>
      </form>
    </article>
  </div>
</section>
</template>
