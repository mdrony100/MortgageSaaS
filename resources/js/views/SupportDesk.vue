<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../stores/auth'
import { api } from '../api'

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

const mode = computed(() => route.path.endsWith('/new') ? 'new' : route.params.id ? 'thread' : 'list')

function when(value?: string) {
  return value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : ''
}

async function loadList() {
  const data = await api('/api/organizations/' + auth.orgId + '/support-tickets')
  tickets.value = data.tickets?.data || []
  meta.value = data
}

async function loadThread() {
  ticket.value = (await api('/api/organizations/' + auth.orgId + '/support-tickets/' + route.params.id)).ticket
}

async function load() {
  error.value = ''
  busy.value = true
  try {
    await loadList()
    if (mode.value === 'thread') await loadThread()
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
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

watch(() => route.fullPath, load)
onMounted(load)
</script>

<template>
<section>
  <div class="page-heading">
    <div>
      <span class="eyebrow">Support</span>
      <h1>{{ mode === 'new' ? 'New support ticket' : mode === 'thread' ? (ticket?.subject || 'Support ticket') : 'Support tickets' }}</h1>
      <p class="muted">{{ mode === 'list' ? 'Contact Non-QM Doc AI about this company. You only see tickets you are allowed to open.' : 'Messages stay with this company. Another company’s tickets are never shown here.' }}</p>
    </div>
    <RouterLink v-if="mode !== 'new'" class="button primary" to="/support/new">New ticket</RouterLink>
  </div>
  <p v-if="error" class="alert error" role="alert">{{ error }}</p>
  <p v-if="busy && !ticket && mode !== 'new'" role="status">Loading tickets…</p>

  <form v-if="mode === 'new'" class="card form-stack" @submit.prevent="create">
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

  <div v-else-if="mode === 'list'" class="table-wrap">
    <table>
      <thead><tr><th>Subject</th><th>Status</th><th>Priority</th><th>Updated</th></tr></thead>
      <tbody>
        <tr v-for="item in tickets" :key="item.id">
          <td><RouterLink class="file-link" :to="'/support/' + item.id">{{ item.subject }}</RouterLink><small>{{ meta.categories[item.category] || item.category }}<template v-if="item.requester"> · {{ item.requester }}</template></small></td>
          <td><span class="badge">{{ meta.statuses[item.status] || item.status }}</span></td>
          <td>{{ meta.priorities[item.priority] || item.priority }}</td>
          <td>{{ when(item.updated_at) }}</td>
        </tr>
      </tbody>
    </table>
    <div v-if="!busy && !tickets.length" class="empty-state"><h3>No tickets yet</h3><p>Send a note to Non-QM Doc AI support for this company.</p><RouterLink class="button primary" to="/support/new">New ticket</RouterLink></div>
  </div>

  <template v-else-if="ticket">
    <p class="micro"><RouterLink to="/support">All tickets</RouterLink> · {{ meta.statuses?.[ticket.status] || ticket.status }} · {{ meta.priorities?.[ticket.priority] || ticket.priority }}</p>
    <article v-for="message in ticket.messages" :key="message.id" class="note-card">
      <div class="inline"><strong>{{ message.author || 'Member' }}</strong><span class="badge" :class="message.staff ? 'teal' : ''">{{ message.staff ? 'Non-QM Doc AI' : 'Your company' }}</span></div>
      <p>{{ message.body }}</p>
      <small>{{ when(message.created_at) }}</small>
      <p v-for="file in message.attachments" :key="file.id"><a :href="'/api/support-ticket-attachments/' + file.id + '/content'">{{ file.name }}</a> <span class="badge">{{ file.scan_status }}</span></p>
    </article>
    <form class="card form-stack spaced" @submit.prevent="sendReply">
      <label>Reply<textarea v-model="reply" required maxlength="5000"/></label>
      <label>Attachment (optional)<input type="file" accept=".pdf,.png,.jpg,.jpeg" @change="replyFile = ($event.target as HTMLInputElement).files?.[0] || null"/></label>
      <button class="primary" :disabled="busy">Send reply</button>
    </form>
  </template>
</section>
</template>
