<script setup lang="ts">
import { nextTick, onMounted, reactive, ref, watch } from 'vue'
import { ArrowLeft } from 'lucide-vue-next'
import { api, qs, when } from '../util'
import Badge from '../components/Badge.vue'
import Pager from '../components/Pager.vue'

const SEEN_KEY = 'console.supportTicketSeen'

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
const seen = ref<Record<string, string>>({})

function loadSeen() {
  try { seen.value = JSON.parse(sessionStorage.getItem(SEEN_KEY) || '{}') } catch { seen.value = {} }
}

function markSeen(ticket?: { id: number, updated_at?: string } | null) {
  if (!ticket?.updated_at) return
  const next = { ...seen.value, [String(ticket.id)]: ticket.updated_at }
  seen.value = next
  const row = list.value.find((item) => item.id === ticket.id)
  if (row) row.updated_at = ticket.updated_at
  try { sessionStorage.setItem(SEEN_KEY, JSON.stringify(next)) } catch { /* private mode */ }
}

function isUnread(item: { id: number, updated_at?: string }) {
  const seenAt = seen.value[String(item.id)]
  if (!seenAt) return true
  const seenMs = Date.parse(seenAt)
  const itemMs = item.updated_at ? Date.parse(item.updated_at) : NaN
  if (Number.isNaN(seenMs) || Number.isNaN(itemMs)) return seenAt !== item.updated_at
  return itemMs > seenMs
}

function scrollThread() {
  const scroller = document.querySelector('.desk-msgs')
  if (scroller) scroller.scrollTop = scroller.scrollHeight
}

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
  try {
    selected.value = (await api('/api/admin/support-tickets/' + id)).ticket
    markSeen(selected.value)
    await nextTick()
    document.getElementById('ticket-heading')?.focus()
    scrollThread()
  } catch (e: any) {
    error.value = e.message
  }
}

function closeTicket() {
  selected.value = null
}

async function setStatus(status: string) {
  if (!selected.value || status === selected.value.status) return
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    selected.value = (await api('/api/admin/support-tickets/' + selected.value.id, 'PATCH', { status })).ticket
    markSeen(selected.value)
    success.value = 'Status updated.'
    await load()
    markSeen(selected.value)
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
    markSeen(selected.value)
    success.value = 'Reply sent.'
    await load()
    markSeen(selected.value)
    await nextTick()
    scrollThread()
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

watch(() => filters.status, () => { filters.page = 1; load() })
onMounted(() => { loadSeen(); load() })
</script>

<template>
<section class="desk" :class="{ 'has-ticket': !!selected }">
  <div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>Support tickets</h1><p class="c-lead">Tickets from every organization. Customer loan documents stay in the customer workspace.</p></div></div>
  <p v-if="error" class="c-alert error" role="alert">{{ error }}</p>
  <p v-if="success" class="c-alert success" role="status">{{ success }}</p>
  <form class="c-filters desk-filters" @submit.prevent="filters.page = 1; load()">
    <label>Status
      <select v-model="filters.status">
        <option value="">All statuses</option>
        <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
      </select>
    </label>
    <label class="desk-search">Search<input v-model="filters.q" type="search" placeholder="Subject or organization"/></label>
    <button class="c-btn desk-apply" type="submit">Apply</button>
  </form>
  <div class="desk-panes">
    <div class="desk-list">
      <div class="desk-list-scroll" role="list" aria-label="Support tickets">
        <button
          v-for="item in list"
          :key="item.id"
          type="button"
          class="desk-row"
          role="listitem"
          :class="{ 'is-selected': selected?.id === item.id, 'is-unread': isUnread(item) }"
          :aria-current="selected?.id === item.id ? 'true' : undefined"
          @click="open(item.id)"
        >
          <span class="desk-row-top">
            <span v-if="isUnread(item)" class="desk-dot" aria-hidden="true"></span>
            <span v-if="isUnread(item)" class="sr-only">Unread. </span>
            <span class="desk-subject">{{ item.subject }}</span>
            <time class="desk-time" :datetime="item.updated_at">{{ when(item.updated_at) }}</time>
          </span>
          <span class="desk-who">{{ item.organization }} · {{ item.requester }}</span>
          <span class="desk-badges">
            <Badge :value="item.priority" :label="priorities[item.priority] || item.priority"/>
            <Badge :value="item.status" :label="statuses[item.status] || item.status"/>
          </span>
        </button>
        <p v-if="loaded && !list.length" class="desk-none">No tickets match these filters.</p>
      </div>
      <Pager :page="page" @go="(p: number) => { filters.page = p; load() }"/>
    </div>

    <article v-if="selected" class="desk-thread" aria-labelledby="ticket-heading">
      <header class="desk-thread-head">
        <div class="desk-thread-title">
          <button type="button" class="desk-back" @click="closeTicket"><ArrowLeft :size="16" aria-hidden="true"/> All tickets</button>
          <h2 id="ticket-heading" tabindex="-1">{{ selected.subject }}</h2>
          <p class="c-muted">{{ selected.organization }} · {{ selected.requester }} · {{ categories[selected.category] || selected.category }}</p>
        </div>
        <div class="desk-thread-tools">
          <Badge :value="selected.priority" :label="priorities[selected.priority] || selected.priority"/>
          <label class="desk-status">Status
            <select :value="selected.status" :disabled="busy" @change="setStatus(($event.target as HTMLSelectElement).value)">
              <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>
        </div>
      </header>
      <div class="desk-msgs" role="log" aria-label="Conversation">
        <article v-for="message in selected.messages" :key="message.id" class="desk-bubble" :class="message.staff ? 'is-admin' : 'is-customer'">
          <div class="desk-bubble-top">
            <strong>{{ message.author || 'Member' }}</strong>
            <span class="c-badge" :class="message.staff ? 'tone-blue' : 'tone-slate'">{{ message.staff ? 'Admin' : 'Customer' }}</span>
            <time :datetime="message.created_at">{{ when(message.created_at) }}</time>
          </div>
          <p class="c-pre">{{ message.body }}</p>
          <p v-for="file in message.attachments" :key="file.id" class="desk-file"><a :href="'/api/admin/support-ticket-attachments/' + file.id">{{ file.name }}</a> · {{ file.scan_status }}</p>
        </article>
      </div>
      <form class="desk-reply" @submit.prevent="send">
        <label for="ticket-reply">Reply<textarea id="ticket-reply" v-model="reply" required maxlength="5000" rows="3"/></label>
        <div class="desk-reply-bar"><button class="c-btn desk-send" :disabled="busy">Send reply</button></div>
      </form>
    </article>
    <div v-else class="desk-thread desk-empty">
      <h2>Select a ticket</h2>
      <p>Choose a ticket to read the conversation, update its status, or send a reply.</p>
    </div>
  </div>
</section>
</template>
