<script setup lang="ts">
import { computed, ref } from 'vue'
import DocumentViewer from './DocumentViewer.vue'
import StatementSummaryCard from './StatementSummaryCard.vue'
import TransactionTable from './TransactionTable.vue'
import { regionFrom, smoothScroll, type ReviewIssue } from '../customerLanguage'

const props = defineProps<{ issues: ReviewIssue[]; versions: any[]; empty?: string; statements?: any[]; transactions?: any[] }>()
const root = ref<HTMLElement>()
const picked = ref<ReviewIssue | null>(null)
const focus = ref(0)
const statementId = ref<string | null>(null)
const jump = ref<{ page?: number; region?: ReviewIssue['region']; versionId?: number } | null>(null)

const showAnalysis = computed(() => props.statements !== undefined || props.transactions !== undefined)
const version = computed(() => props.versions.find((item) => item.id === (jump.value?.versionId ?? props.versions[0]?.id)))
const page = computed(() => jump.value?.page)
const region = computed(() => jump.value?.region)
const currency = computed(() => {
  const statement = (props.statements || []).find((item) => String(item?.id) === statementId.value) || props.statements?.[0]
  const code = statement?.data?.currency || statement?.currency
  return typeof code === 'string' ? code : 'USD'
})

function place(issue: ReviewIssue) {
  if (issue.region && issue.page) return `Page ${issue.page} · highlighted on the document`
  if (issue.page) return `Page ${issue.page}`
  return 'Location unknown'
}

function severityClass(severity?: string) {
  const name = (severity || '').toLowerCase()
  if (name === 'high' || name === 'critical' || name === 'severe') return 'severity-high'
  if (name === 'low' || name === 'info' || name === 'informational') return 'severity-low'
  return 'severity-medium'
}

function severityTone(severity?: string) {
  const name = severityClass(severity)
  if (name === 'severity-high') return 'red'
  if (name === 'severity-low') return 'teal'
  return 'amber'
}

function reveal() {
  root.value?.querySelector('.split-pdf')?.scrollIntoView({ behavior: smoothScroll(), block: 'nearest' })
}

function choose(issue: ReviewIssue) {
  picked.value = issue
  jump.value = { page: issue.page, region: issue.region, versionId: issue.documentVersionId }
  focus.value += 1
  reveal()
}

function openTransaction(transaction: any) {
  const pageNo = Number(transaction?.data?.page)
  const knownPage = Number.isFinite(pageNo) && pageNo > 0 ? pageNo : undefined
  if (!knownPage) return
  const versionId = Number(transaction?.document_version_id)
  picked.value = null
  jump.value = {
    page: knownPage,
    region: transaction?.data?.region_verified ? regionFrom(transaction.data.region) : undefined,
    versionId: Number.isFinite(versionId) ? versionId : undefined,
  }
  focus.value += 1
  reveal()
}
</script>
<template>
<section ref="root" id="issue-review" class="split-review" aria-label="Findings beside the document">
  <div class="split-pdf">
    <DocumentViewer v-if="version && version.mime === 'application/pdf'" :version="version" :page="page" :region="region" :focus="focus" />
    <img v-else-if="version" class="source-image" :src="'/api/document-versions/' + version.id + '/content'" alt="Uploaded document" />
    <div v-else class="empty-state">
      <h3>The document will show here</h3>
      <p>Upload a PDF, then open an issue to jump to its page.</p>
    </div>
  </div>
  <div class="split-issues">
    <template v-if="showAnalysis">
      <nav class="analysis-jump" aria-label="Statement analysis">
        <a href="#statement-summary">Summary</a>
        <a href="#statement-transactions">Transactions</a>
        <a href="#statement-findings">Findings</a>
      </nav>
      <StatementSummaryCard :statements="statements || []" @select="statementId = $event" />
      <TransactionTable :transactions="transactions || []" :statement-id="statementId" :currency="currency" @open="openTransaction" />
    </template>
    <section id="statement-findings">
      <h2>What was flagged</h2>
      <p class="micro">Open an issue to jump to its page. A box is drawn when we know where on the page it is.</p>
      <div class="issue-list">
        <button
          v-for="issue in issues"
          :key="issue.id"
          type="button"
          class="issue-card"
          :class="[severityClass(issue.severity), { active: picked?.id === issue.id }]"
          :aria-current="picked?.id === issue.id ? 'true' : undefined"
          @click="choose(issue)"
        >
          <span class="badge" :class="severityTone(issue.severity)">{{ (issue.severity || 'review').replaceAll('_', ' ') }}</span>
          <strong>{{ issue.title }}</strong>
          <span>{{ issue.detail }}</span>
          <small>{{ place(issue) }}</small>
        </button>
      </div>
      <div v-if="!issues.length" class="empty-state">
        <h3>Nothing to review yet</h3>
        <p>{{ empty || 'When analysis flags something, it shows up here beside the document. A person decides what it means.' }}</p>
      </div>
    </section>
  </div>
</section>
</template>
