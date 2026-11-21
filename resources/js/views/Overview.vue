<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuth } from '../stores/auth'
import { api } from '../api'
import { FileText, Flag, FolderOpen, Hourglass, ListChecks, CircleCheck, Upload } from 'lucide-vue-next'

const auth = useAuth()
const data = ref<any>(null)
const error = ref('')
const busy = ref(true)

function when(value?: string) {
  return value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : ''
}

async function load() {
  busy.value = true
  error.value = ''
  try { data.value = await api('/api/organizations/' + auth.orgId + '/overview') }
  catch (e: any) { error.value = e.message }
  finally { busy.value = false }
}
onMounted(load)
</script>

<template>
<section>
  <div class="page-heading">
    <div>
      <span class="eyebrow">Dashboard</span>
      <h1>{{ data?.organization?.name || 'Workspace' }}</h1>
      <p class="muted">Counts and recent documents include only the loan files you can open in this company.</p>
    </div>
    <div class="actions">
      <RouterLink v-if="data?.can_create" class="button primary" to="/files/create">New loan file</RouterLink>
      <RouterLink v-if="data?.can_upload" class="button" :to="data.upload_file_id ? '/files/' + data.upload_file_id : '/files'"><Upload :size="16"/>Upload</RouterLink>
    </div>
  </div>
  <p v-if="error" class="alert error" role="alert">{{ error }}</p>
  <p v-if="busy" class="empty-state" role="status">Loading the workspace…</p>
  <template v-else-if="data">
    <div class="stat-grid">
      <article class="stat"><FolderOpen :size="18" aria-hidden="true"/><div><span>Loan files</span><strong>{{ data.stats.loan_files }}</strong><small>Open files you can see</small></div></article>
      <article class="stat"><ListChecks :size="18" aria-hidden="true"/><div><span>Needing review</span><strong>{{ data.stats.needing_review }}</strong><small>Ready for a person</small></div></article>
      <article class="stat"><Hourglass :size="18" aria-hidden="true"/><div><span>In progress</span><strong>{{ data.stats.in_progress }}</strong><small>Processing right now</small></div></article>
      <article class="stat"><CircleCheck :size="18" aria-hidden="true"/><div><span>Completed</span><strong>{{ data.stats.completed }}</strong><small>Finished or exported</small></div></article>
      <article class="stat"><FileText :size="18" aria-hidden="true"/><div><span>Documents</span><strong>{{ data.stats.documents }}</strong><small>Uploads on open files</small></div></article>
      <article class="stat"><Flag :size="18" aria-hidden="true"/><div><span>Flags raised</span><strong>{{ data.stats.flags }}</strong><small>Items for a person to check</small></div></article>
    </div>
    <p class="micro dash-modules">Bank statements {{ data.modules.bank }} · Appraisals {{ data.modules.appraisal }} · Rental income {{ data.modules.dscr }}<template v-if="data.stats.archived"> · Archived {{ data.stats.archived }}</template></p>
    <div class="dash-grid">
      <section class="card">
        <div class="section-heading"><h2>Latest appraisal documents</h2></div>
        <div class="table-wrap" v-if="data.appraisals.length">
          <table>
            <thead><tr><th>Document</th><th>Loan file</th><th>Status</th><th>Added</th></tr></thead>
            <tbody>
              <tr v-for="doc in data.appraisals" :key="doc.id">
                <td>{{ doc.name || doc.title }}</td>
                <td><RouterLink :to="'/files/' + doc.loan_file_id">{{ doc.loan_file }}</RouterLink><small>{{ doc.borrower }}</small></td>
                <td><span class="badge">{{ doc.status }}</span></td>
                <td>{{ when(doc.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="muted">No appraisal uploads on the files you can open.</p>
      </section>
      <section class="card">
        <div class="section-heading"><h2>Recent bank statements</h2></div>
        <div class="table-wrap" v-if="data.statements.length">
          <table>
            <thead><tr><th>Document</th><th>Loan file</th><th>Status</th><th>Added</th></tr></thead>
            <tbody>
              <tr v-for="doc in data.statements" :key="doc.id">
                <td>{{ doc.name || doc.title }}</td>
                <td><RouterLink :to="'/files/' + doc.loan_file_id">{{ doc.loan_file }}</RouterLink><small>{{ doc.borrower }}</small></td>
                <td><span class="badge">{{ doc.status }}</span></td>
                <td>{{ when(doc.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="muted">No bank statement uploads on the files you can open.</p>
      </section>
    </div>
    <section class="card spaced">
      <div class="section-heading"><h2>Recent activity</h2><RouterLink to="/files">All loan files</RouterLink></div>
      <div v-if="data.activity.length" class="activity-list">
        <div v-for="item in data.activity" :key="item.id" class="activity-row">
          <div><strong>{{ item.label }}</strong><small><RouterLink v-if="item.loan_file_id" :to="'/files/' + item.loan_file_id">{{ item.loan_file }}</RouterLink><template v-else>{{ item.loan_file }}</template> · {{ when(item.created_at) }}</small></div>
        </div>
      </div>
      <p v-else class="muted">Nothing has been recorded on the files you can open yet.</p>
    </section>
  </template>
</section>
</template>
