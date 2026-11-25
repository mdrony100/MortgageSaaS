<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from 'lucide-vue-next'
import { useAuth } from '../stores/auth'
import { api } from '../api'

const SEEN_KEY = 'workspace.supportTicketSeen'

const auth = useAuth()
const route = useRoute()
const router = useRouter()
const tickets = ref<any[]>([])
const meta = ref<any>({ categories: { billing: 'Billing', technical: 'Technical', documents: 'Documents', account: 'Account', other: 'Other' }, priorities: { low: 'Low', normal: 'Normal', high: 'High', urgent: 'Urgent' }, statuses: { open: 'Open', pending: 'Pending', resolved: 'Resolved', closed: 'Closed' } })
const ticket = ref<any>(null)
const error = ref('')
const busy = ref(false)
const form = ref({ subject: '', category: 'technical', priority: 'normal', message: '', file: null as File | null })
const reply = ref('')
const replyFile = ref<File | null>(null)
const seen = ref<Record<string, string>>({})

const mode = computed(() => route.path.endsWith('/new') ? 'new' : route.params.id ? 'thread' : 'list')

function when(value?: string) {
  return value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : ''
}

function loadSeen() {
  try { seen.value = JSON.parse(sessionStorage.getItem(SEEN_KEY) || '{}') } catch { seen.value = {} }
}

function markSeen(item?: { id: number, updated_at?: string } | null) {
  if (!item?.updated_at) return
  const next = { ...seen.value, [String(item.id)]: item.updated_at }
  seen.value = next
  const row = tickets.value.find((ticket) => ticket.id === item.id)
  if (row) row.updated_at = item.updated_at
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

function tone(value?: string) {
  if (value === 'high' || value === 'pending') return 'amber'
  if (value === 'urgent') return 'red'
  if (value === 'normal' || value === 'open') return 'teal'
  if (value === 'resolved') return 'green'
  return ''
}

function scrollThread() {
  const scroller = document.querySelector('.support-msgs')
  if (scroller) scroller.scrollTop = scroller.scrollHeight
}

async function loadList() {
  const data = await api('/api/organizations/' + auth.orgId + '/support-tickets')
  tickets.value = data.tickets?.data || []
  meta.value = data
}

async function loadThread() {
  ticket.value = (await api('/api/organizations/' + auth.orgId + '/support-tickets/' + route.params.id)).ticket
  markSeen(ticket.value)
  scrollThread()
}

async function load() {
  error.value = ''
  busy.value = true
  try {
    await loadList()
    if (mode.value === 'thread') await loadThread()
    else ticket.value = null
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

async function create() {
  busy.value = true
  error.value = ''
  try {
    const body = new FormData()
    body.append('subject', form.value.subject)
    body.append('category', form.value.category)
    body.append('priority', form.value.priority)
    body.append('message', form.value.message)
    if (form.value.file) body.append('file', form.value.file)
    const created = await api('/api/organizations/' + auth.orgId + '/support-tickets', 'POST', body)
    router.push('/support/' + created.ticket.id)
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

async function sendReply() {
  busy.value = true
  error.value = ''
  try {
    const body = new FormData()
    body.append('message', reply.value)
    if (replyFile.value) body.append('file', replyFile.value)
    ticket.value = (await api('/api/organizations/' + auth.orgId + '/support-tickets/' + route.params.id + '/replies', 'POST', body)).ticket
    reply.value = ''
    replyFile.value = null
    markSeen(ticket.value)
    scrollThread()
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

watch(() => route.fullPath, load)
onMounted(() => { loadSeen(); load() })
</script>

<template>
<section class="support-page" :class="{ 'has-thread': mode === 'thread', 'is-new': mode === 'new' }">
  <div class="page-heading">
    <div>
      <span class="eyebrow">Support</span>
      <h1>{{ mode === 'new' ? 'New support ticket' : 'Support tickets' }}</h1>
      <p class="muted">{{ mode === 'list' ? 'Contact Non-QM Doc AI about this company. You only see tickets you are allowed to open.' : 'Messages stay with this company. Another company’s tickets are never shown here.' }}</p>
    </div>
    <RouterLink v-if="mode !== 'new'" class="button primary" to="/support/new">New ticket</RouterLink>
  </div>
  <p v-if="error" class="alert error" role="alert">{{ error }}</p>

  <form v-if="mode === 'new'" class="card form-stack support-compose" @submit.prevent="create">
    <label>Subject<input v-model="form.subject" required maxlength="160"/></label>
    <div class="form-grid">
      <label>Category
        <select v-model="form.category">
          <option v-for="(label, key) in meta.categories" :key="key" :value="key">{{ label }}</option>
        </select>
      </label>
      <label>Priority
        <select v-model="form.priority">
          <option v-for="(label, key) in meta.priorities" :key="key" :value="key">{{ label }}</option>
        </select>
      </label>
    </div>
    <label>Message<textarea v-model="form.message" required maxlength="5000"/></label>
    <label>Attachment (optional, PDF, PNG, or JPG up to 20 MB)<input type="file" accept=".pdf,.png,.jpg,.jpeg" @change="form.file = ($event.target as HTMLInputElement).files?.[0] || null"/></label>
    <p class="micro">The file is stored privately and scanned with the same quarantine rules as loan documents. Unscanned is not a clean result.</p>
    <div class="actions">
      <RouterLink class="button" to="/support">Cancel</RouterLink>
      <button class="primary" :disabled="busy">Send ticket</button>
    </div>
  </form>

  <div v-else class="support-desk">
    <div class="support-list">
      <p v-if="busy && !tickets.length" class="support-loading" role="status">Loading tickets…</p>
      <div v-else-if="!busy && !tickets.length" class="empty-state">
        <h3>No tickets yet</h3>
        <p>Send a note to Non-QM Doc AI support for this company.</p>
        <RouterLink class="button primary" to="/support/new">New ticket</RouterLink>
      </div>
      <div v-else class="support-list-scroll" role="list" aria-label="Support tickets">
        <RouterLink
          v-for="item in tickets"
          :key="item.id"
          class="support-row"
          :class="{ 'is-selected': String(route.params.id) === String(item.id), 'is-unread': isUnread(item) }"
          :to="'/support/' + item.id"
          :aria-current="String(route.params.id) === String(item.id) ? 'page' : undefined"
        >
          <span class="support-row-top">
            <span v-if="isUnread(item)" class="support-dot" aria-hidden="true"></span>
            <span v-if="isUnread(item)" class="sr-only">Unread. </span>
            <span class="support-subject">{{ item.subject }}</span>
            <time :datetime="item.updated_at">{{ when(item.updated_at) }}</time>
          </span>
          <span class="support-who">{{ meta.categories?.[item.category] || item.category }}<template v-if="item.requester"> · {{ item.requester }}</template></span>
          <span class="support-badges">
            <span class="badge" :class="tone(item.priority)">{{ meta.priorities?.[item.priority] || item.priority }}</span>
            <span class="badge" :class="tone(item.status)">{{ meta.statuses?.[item.status] || item.status }}</span>
          </span>
        </RouterLink>
      </div>
    </div>

    <div class="support-thread">
      <template v-if="mode === 'thread' && ticket">
        <header class="support-thread-head">
          <div class="support-thread-title">
            <RouterLink class="support-back" to="/support"><ArrowLeft :size="16" aria-hidden="true"/> All tickets</RouterLink>
            <h2>{{ ticket.subject }}</h2>
            <p class="micro">{{ meta.categories?.[ticket.category] || ticket.category }}<template v-if="ticket.requester"> · {{ ticket.requester }}</template></p>
          </div>
          <div class="support-thread-tools">
            <span class="badge" :class="tone(ticket.priority)">{{ meta.priorities?.[ticket.priority] || ticket.priority }}</span>
            <span class="badge" :class="tone(ticket.status)">{{ meta.statuses?.[ticket.status] || ticket.status }}</span>
          </div>
        </header>
        <div class="support-msgs" role="log" aria-label="Conversation">
          <article v-for="message in ticket.messages" :key="message.id" class="support-bubble" :class="message.staff ? 'is-staff' : 'is-customer'">
            <div class="support-bubble-top">
              <strong>{{ message.author || 'Member' }}</strong>
              <span class="badge" :class="message.staff ? 'teal' : ''">{{ message.staff ? 'Non-QM Doc AI' : 'Your company' }}</span>
              <time :datetime="message.created_at">{{ when(message.created_at) }}</time>
            </div>
            <p>{{ message.body }}</p>
            <p v-for="file in message.attachments" :key="file.id" class="support-file"><a :href="'/api/support-ticket-attachments/' + file.id + '/content'">{{ file.name }}</a> <span class="badge">{{ file.scan_status }}</span></p>
          </article>
        </div>
        <form class="support-reply" @submit.prevent="sendReply">
          <label>Reply<textarea v-model="reply" required maxlength="5000" rows="4"/></label>
          <label>Attachment (optional)<input type="file" accept=".pdf,.png,.jpg,.jpeg" @change="replyFile = ($event.target as HTMLInputElement).files?.[0] || null"/></label>
          <div class="support-reply-bar"><button class="primary" :disabled="busy">Send reply</button></div>
        </form>
      </template>
      <div v-else class="support-empty">
        <h2>{{ mode === 'thread' ? 'Loading ticket…' : 'Select a ticket' }}</h2>
        <p v-if="mode !== 'thread'">Choose a ticket to read the conversation and reply.</p>
      </div>
    </div>
  </div>
</section>
</template>
