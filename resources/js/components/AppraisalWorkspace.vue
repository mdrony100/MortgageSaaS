<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { api } from '../api'
import DataPanel from './DataPanel.vue'
import DocumentViewer from './DocumentViewer.vue'
import HelpNote from './HelpNote.vue'
import NextStep from './NextStep.vue'
import IssueReview from './IssueReview.vue'
import GuidedSteps from './GuidedSteps.vue'
import Fold from './Fold.vue'
import HowItWorks from './HowItWorks.vue'
import FieldTip from './FieldTip.vue'
import { appraisalTabLabel, documentTypeLabel, documentTypeOptions, explainIntegrity, explainStatus, failureGuide, flowFor, guessDocumentType, guideFor, helpText, occupancyOptions, optionLabel, readingProgress, reviewIssues, smoothScroll, transactionOptions } from '../customerLanguage'

const props = defineProps<{ file: any; module?: string }>()
const emit = defineEmits(['refresh', 'guide'])
const state = ref<any>({})
const tab = ref('')
const error = ref('')
const busy = ref(false)
const runId = ref('')
const selected = ref<number[]>([])
const classifications = ref<Record<string, string>>({})
const source = ref<any>()
const notes = ref('')
const upload = ref<File>()
const replace = ref('')
const fictional = ref(false)
const parent = ref('')
const guideline = ref('')
const context = ref({ loan_occupancy: 'unknown', transaction: 'unknown', program: '', units: 1 })
const loanReady = ref(false)
const contextHydrated = ref(false)
const dragging = ref(false)
const detailTabs = ['facts', 'photos', 'rent', 'calculations', 'findings', 'revisions', 'guidelines', 'notes']
const closedFinding = ['reviewed', 'resolved', 'dismissed']

const money = () => ({ value: null as string | null, confirmed: false, source: '', basis: '' })
const freshInput = () => ({ units: [], payment: { mode: 'total', total: money(), components: { payment: money(), taxes: money(), insurance: money(), association: money(), other: money() }, interest_only: false, definition: '' }, loan: money(), value_basis: money(), appraised_value: null })
const input = ref<any>(freshInput())
const root = computed(() => '/api/files/' + props.file.id + '/appraisal')
const rr = computed(() => root.value + '/runs/' + runId.value)
const records = computed(() => state.value.records || [])
const versions = computed(() => props.file.documents.flatMap((d: any) => d.versions))
const version = computed(() => versions.value.find((v: any) => v.id === source.value?.document_version_id))
function rows(k: string) { return records.value.filter((r: any) => r.kind === k) }

async function load() {
  const fileId = props.file.id, requestedRun = runId.value
  try {
    const next = await api(root.value + (requestedRun ? '?run_id=' + requestedRun : ''))
    if (fileId !== props.file.id || (requestedRun && requestedRun !== runId.value)) return
    state.value = next
    if (state.value.run) runId.value = state.value.run.id
    const saved = state.value.run?.context
    if (saved && typeof saved === 'object' && !contextHydrated.value) {
      context.value = { loan_occupancy: saved.loan_occupancy || 'unknown', transaction: saved.transaction || 'unknown', program: saved.program || '', units: Number(saved.units) || 1 }
      contextHydrated.value = true
    }
    if (!selected.value.length) selected.value = props.file.documents.filter((d: any) => d.category !== 'fixture').map((d: any) => d.versions.at(-1)?.id).filter(Boolean)
  } catch (e: any) {
    if (fileId !== props.file.id) return
    error.value = e.message
    if ([401, 403, 404].includes(e.status)) { state.value = {}; source.value = null; emit('refresh') }
  }
}
async function act(fn: () => Promise<any>) {
  busy.value = true; error.value = ''
  try { await fn(); await load() } catch (e: any) { error.value = e.message } finally { busy.value = false }
}
async function command(action: string, data: any = {}) {
  if (action === 'inputs') data = { ...data, input: { ...data.input, units: data.input.units.map(({ leaseVersion, matchEvidence, ...unit }: any) => unit) } }
  await act(() => api(rr.value + '/' + action, 'POST', { notes: notes.value, ...data }))
}
async function start() {
  await act(async () => {
    const r = await api(root.value + '/runs', 'POST', { document_version_ids: selected.value, classifications: classifications.value, context: context.value, parent_id: parent.value || null, guideline_id: guideline.value ? Number(guideline.value) : null, idempotency_key: crypto.randomUUID() })
    runId.value = r.run_id
  })
}
async function addUpload() {
  if (!upload.value) return
  await act(async () => {
    const f = new FormData()
    f.append('file', upload.value!)
    f.append('fictional_sample', fictional.value ? '1' : '0')
    await api(replace.value ? '/api/documents/' + replace.value + '/versions' : ('/api/files/' + props.file.id + '/documents'), 'POST', f)
    upload.value = undefined
    emit('refresh')
  })
}
function onDrop(event: DragEvent) {
  dragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (!file) return
  upload.value = file
  addUpload()
}
function addUnit() { if (input.value.units.length < 4) input.value.units.push({ id: String(input.value.units.length + 1), inspection_occupancy: 'unknown', current_occupancy: 'unknown', lease_status: 'unknown', rent_method: 'long_term', is_adu: false, market: money(), qualifying: money() }) }
function restore() {
  const old = JSON.parse(JSON.stringify(state.value.inputs?.data?.input || input.value)), base = freshInput()
  input.value = { ...base, ...old, units: (old.units || []).map((u: any) => ({ ...u, market: u.market || money(), qualifying: u.qualifying || money() })), payment: { ...base.payment, ...old.payment, total: old.payment?.total || money(), components: { ...base.payment.components, ...old.payment?.components } }, loan: old.loan || money(), value_basis: old.value_basis || money() }
}
function sourceRefs(f: any) {
  const e = f.data?.evidence || {}
  return [...(e.document_version_id ? [e] : []), ...(e.image_references || []), ...(e.field_ids || [e.field_id]).filter(Boolean).map((id: number) => state.value.facts?.find((x: any) => x.id === id)?.data).filter(Boolean)]
}
function evidenceLine(f: any) {
  const refs = sourceRefs(f)
  if (!refs.length) return 'Source page not recorded'
  return refs.map((ref: any) => `Page ${ref.page || ref.number || 1}`).join(' · ')
}
function findingTitle(f: any) {
  return String(f.data?.category || f.data?.code || 'Potential issue').replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase())
}
function isClosed(f: any) { return closedFinding.includes(f.status) }
async function decide(f: any, status: string) {
  f.next = status
  await command('finding', { finding_id: f.id, status })
}
async function saveFindingNote(f: any) {
  notes.value = f.noteDraft || notes.value
  await command('finding', { finding_id: f.id, status: f.next || 'reviewed' })
  f.addingNote = false
}

watch(() => props.file.id, () => { runId.value = ''; selected.value = []; classifications.value = {}; state.value = {}; source.value = null; input.value = freshInput(); loanReady.value = false; contextHydrated.value = false; openSection.value = 0; tab.value = ''; load() })
watch(runId, () => { source.value = null; input.value = freshInput() })
watch(versions, (list) => {
  const next = { ...classifications.value }
  let changed = false
  for (const item of list) {
    if (next[item.id] == null || next[item.id] === '') { next[item.id] = guessDocumentType(item.original_name); changed = true }
  }
  if (changed) classifications.value = next
}, { immediate: true })

const baseGuide = computed(() => guideFor({ documents: versions.value.filter((v: any) => v).length, runStatus: state.value.run?.status || null, hasReport: rows('report').length > 0, canReview: !!props.file.permissions?.review, subject: props.module === 'dscr' ? 'rental income' : 'the appraisal', failure: state.value.run?.error }))
const guide = computed(() => {
  let g = baseGuide.value
  if (g.cta === 'details' && loanReady.value) {
    g = { ...g, step: 2, badge: 'Ready to start', tone: 'amber', title: 'Start processing', body: 'Uploading only stores the file. Nothing is read until you start processing.', hint: 'Start processing when the loan details look right.', waiting: false, action: 'Start processing', cta: 'start' }
  }
  const findings = state.value.findings || []
  const open = findings.filter((f: any) => !isClosed(f)).length
  if (g.cta === 'review' && props.file.permissions?.review && open > 0) {
    const noun = open === 1 ? 'item' : 'items'
    g = { ...g, title: `Review ${open} flagged ${noun}`, action: `Review ${open} flagged ${noun}`, body: 'Work through each flagged item. A person decides what it means. Nothing is approved or declined automatically.' }
  }
  if (g.waiting) {
    const total = state.value.run?.inputs?.length || 0
    g = { ...g, body: readingProgress(rows('document_completed').length, total) }
  }
  return g
})
watch(guide, (g) => emit('guide', g), { immediate: true })
const openSection = ref(0)
const running = computed(() => ['Queued', 'Validating', 'Extracting', 'Reconciling', 'Analyzing'].includes(state.value.run?.status || ''))
const loanSummary = computed(() => `${optionLabel(occupancyOptions, context.value.loan_occupancy)} · ${optionLabel(transactionOptions, context.value.transaction)} · ${context.value.units} unit${context.value.units === 1 ? '' : 's'}`)
const findingSummary = computed(() => {
  const findings = state.value.findings || []
  if (!findings.length) return 'No flagged items yet'
  const done = findings.filter((f: any) => isClosed(f)).length
  return `${done} of ${findings.length} reviewed`
})
const steps = computed(() => flowFor(guide.value, {
  documents: versions.value.length,
  loanSummary: loanSummary.value,
  processingSummary: state.value.run ? explainStatus(state.value.run.status).label : 'Not started',
  findingSummary: findingSummary.value,
  exportSummary: rows('report').length ? 'Report ready to download' : 'Excel and PDF, when processing has finished',
  reviewReady: (state.value.findings || []).length > 0,
}))
const issues = computed(() => props.file.permissions?.review ? reviewIssues(state.value.findings || []) : [])
const doneCount = computed(() => (state.value.findings || []).filter((f: any) => isClosed(f)).length)
function confirmLoan() { loanReady.value = true }
function setSection(id: number, open: boolean) {
  openSection.value = open ? id : openSection.value === id ? 0 : openSection.value
}
function onGuideAction() {
  const cta = guide.value.cta
  if (cta === 'retry' && state.value.run) command('control', { operation: 'retry' })
  if (cta === 'start') start()
  openSection.value = cta === 'upload' || cta === 'details' || cta === 'replace' ? 1 : cta === 'review' || cta === 'export' ? 3 : 2
  if (cta === 'review' || cta === 'export') document.getElementById('finding-checklist')?.scrollIntoView({ behavior: smoothScroll(), block: 'start' })
}
let timer: any
onMounted(async () => { await load(); timer = setInterval(load, 4000) })
onUnmounted(() => clearInterval(timer))
</script>
<template>
<section class="card guided-review" role="tabpanel">
  <div class="review-title"><h2>{{ module === 'dscr' ? 'Rental income review' : 'Appraisal / Rental Income Review' }}</h2><HowItWorks /></div>
  <p v-if="busy" role="status">Saving…</p>
  <p v-if="error" role="alert" class="alert error">{{ error }}</p>
  <p v-if="state.audience === 'restricted'">{{ state.notice }}</p>
  <template v-else>
  <GuidedSteps :steps="steps" />
  <NextStep :guide="guide" @act="onGuideAction" />
  <p class="compliance">A person makes the decision. The tool only flags potential issues. This is not lending advice.</p>
  <Fold title="Documents & details" :hint="steps[0]?.summary" :open="openSection === 1" @toggle="setSection(1, $event)">
      <form class="upload-form" @submit.prevent="addUpload">
        <label class="dropzone" :class="{ 'is-over': dragging }" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
          <strong>Drop a PDF, PNG, or JPG here</strong>
          <span>or browse. Up to 20 MB. The file is only stored until you start processing.</span>
          <input type="file" accept=".pdf,.png,.jpg,.jpeg" required @change="upload = ($event.target as HTMLInputElement).files?.[0]" />
        </label>
        <button class="primary" :disabled="busy">Upload documents</button>
        <details class="advanced">
          <summary>Advanced</summary>
          <label>Save as a new version of
            <FieldTip text="Leave this as a new document unless you are replacing one you already uploaded. The original is kept." />
            <select v-model="replace" aria-label="Save as a new version of">
              <option value="">New document</option>
              <option v-for="d in file.documents.filter((d: any) => d.category !== 'fixture')" :key="d.id" :value="d.id">New version of {{ d.title }}</option>
            </select>
          </label>
          <label v-if="file.is_demo" class="check"><input type="checkbox" v-model="fictional" /> This upload is fictional</label>
          <button :disabled="busy">Preserve original in quarantine</button>
        </details>
      </form>
      <ul v-if="versions.length" class="doc-rows">
        <li v-for="v in versions" :key="v.id" class="doc-row">
          <div class="doc-row-name">
            <strong>{{ v.original_name }}</strong>
            <span class="badge">{{ documentTypeLabel(classifications[v.id], v.original_name) }}</span>
            <span class="badge" :class="explainIntegrity(v.scan_status).tone">{{ explainIntegrity(v.scan_status).label }}</span>
          </div>
          <label>Document type
            <FieldTip text="What kind of document this is. We guess from the file name. Change it if the guess is wrong." />
            <select v-model="classifications[v.id]" :aria-label="'Document type for ' + v.original_name">
              <option v-for="opt in documentTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </label>
          <label class="check"><input type="checkbox" :value="v.id" v-model="selected" /> Include in processing</label>
        </li>
      </ul>
      <p v-else class="muted">No documents yet. Add the appraisal, rent schedule, and leases.</p>
      <p v-if="!file.permissions.review" class="muted">A reviewer enters occupancy, the transaction, and the unit count.</p>
      <div v-else class="loan-form">
        <fieldset>
          <legend>Property and loan</legend>
          <label>Loan occupancy
            <FieldTip text="How the borrower will use the property. This is separate from the appraisal form." />
            <select v-model="context.loan_occupancy" aria-label="Loan occupancy">
              <option v-for="opt in occupancyOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </label>
          <p class="field-help">Choose Investment for a rental. Use Not set yet if you do not know.</p>
          <label>Transaction
            <FieldTip text="Purchase or a refinance. This is stored with the review. It does not approve the loan." />
            <select v-model="context.transaction" aria-label="Transaction">
              <option v-for="opt in transactionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </label>
          <p class="field-help">Purchase, rate-and-term refinance, or cash-out refinance.</p>
          <label>Units
            <FieldTip text="How many units are on this property, from 1 to 4." />
            <input v-model.number="context.units" type="number" min="1" max="4" aria-label="Units" />
          </label>
          <p class="field-help">One to four units.</p>
          <label>Program or investor
            <FieldTip text="Optional. The investor or program name your team uses. It is not chosen by the tool." />
            <input v-model="context.program" aria-label="Program or investor" placeholder="Optional" />
          </label>
        </fieldset>
        <button type="button" @click="confirmLoan">Continue to processing</button>
        <details class="advanced">
          <summary>Advanced</summary>
          <label>Revision of an earlier review
            <FieldTip text="Leave this as the first review unless this run replaces an earlier one. Earlier results stay as they were." />
            <select v-model="parent" aria-label="Revision of an earlier review">
              <option value="">First review</option>
              <option v-for="(r, i) in state.runs" :key="r.id" :value="r.id">Earlier review {{ Number(i) + 1 }} · {{ explainStatus(r.status).label }}</option>
            </select>
          </label>
          <label>Frozen guideline version
            <FieldTip text="The guideline text this review is checked against. Leave the draft baseline unless a reviewer froze a version." />
            <select v-model="guideline" aria-label="Frozen guideline version">
              <option value="">Public draft baseline</option>
              <option v-for="g in state.guidelines" :key="g.id" :value="g.id">{{ g.status }} · {{ g.id }}</option>
            </select>
          </label>
        </details>
      </div>
  </Fold>
  <Fold title="Process" :hint="steps[1]?.summary" :open="openSection === 2" @toggle="setSection(2, $event)">
      <p v-if="guide.waiting || state.run" class="progress-plain">{{ guide.waiting ? guide.body : explainStatus(state.run.status).label }}</p>
      <p v-else class="muted">{{ selected.length }} documents ready. Nothing is read until you start processing.</p>
      <button v-if="running" type="button" class="quiet" @click="command('control', { operation: 'cancel' })">Cancel</button>
      <form v-if="file.permissions.review" class="form-stack start-processing" @submit.prevent="start"></form>
      <details class="advanced">
        <summary>Advanced</summary>
        <div class="actions">
          <button type="button" @click="load">Refresh review</button>
          <label>Earlier run
            <select v-model="runId" aria-label="Earlier run" @change="load">
              <option value="">Latest review</option>
              <option v-for="r in state.runs" :key="r.id" :value="r.id">{{ explainStatus(r.status).label }}</option>
            </select>
          </label>
        </div>
        <h3>Processing coverage and limitations</h3>
        <p v-if="state.run">Committed source extractions {{ rows('document_completed').length }} / {{ state.run.inputs?.length || 0 }} · rendered pages {{ rows('page').length }}. Queued continuations preserve progress; extraction is not reviewer confirmation.</p>
        <DataPanel :show-evidence="true" :data="rows('document')" />
        <DataPanel :show-evidence="true" :data="rows('document_batch')" />
        <p v-if="!state.run">Select preserved original versions to start. No uploaded file is replaced by sample analysis.</p>
      </details>
  </Fold>
  <Fold title="Review & export" :hint="steps[2]?.summary" :open="openSection === 3" @toggle="setSection(3, $event)">
      <p v-if="!file.permissions.review" class="muted">A reviewer checks the findings. Questions for you stay with the file notes.</p>
      <section v-if="file.permissions.review" id="finding-checklist" aria-label="Findings checklist">
        <div class="section-heading">
          <h3>Findings</h3>
          <span class="badge tally">{{ doneCount }} of {{ (state.findings || []).length }} reviewed</span>
        </div>
        <p v-if="!(state.findings || []).length" class="muted">Nothing was flagged. Check the detail sections if you need them, then export the report.</p>
        <article v-for="f in state.findings" :key="f.id" class="check-item" :class="{ done: isClosed(f) }">
          <header>
            <strong>{{ findingTitle(f) }}</strong>
            <span class="badge" :class="isClosed(f) ? 'teal' : 'amber'">{{ isClosed(f) ? explainStatus(f.status).label.replace(/^./, (c: string) => c.toUpperCase()) : 'Needs a decision' }}</span>
          </header>
          <p>{{ f.data?.observation || f.data?.explanation || 'A person needs to check this.' }}</p>
          <p class="micro">{{ evidenceLine(f) }}</p>
          <div class="actions">
            <button v-for="ref in sourceRefs(f)" :key="(ref.document_version_id || '') + '-' + (ref.page || ref.number || 1)" type="button" @click="source = ref">Page {{ ref.page || ref.number || 1 }}</button>
            <button type="button" class="primary" :disabled="busy" @click="decide(f, 'reviewed')">Accept</button>
            <button type="button" :disabled="busy" @click="decide(f, 'dismissed')">Dismiss</button>
            <button type="button" @click="f.addingNote = !f.addingNote">Add note</button>
          </div>
          <label v-if="f.addingNote">Note for this item
            <textarea v-model="f.noteDraft" maxlength="4000" />
            <button type="button" :disabled="busy" @click="saveFindingNote(f)">Save note</button>
          </label>
          <details class="advanced">
            <summary>More decisions</summary>
            <label>Disposition
              <select v-model="f.next" :aria-label="'Disposition for ' + findingTitle(f)">
                <option v-for="s in ['reviewed', 'resolved', 'needs_follow_up', 'reopened', 'dismissed']" :key="s" :value="s">{{ s.replaceAll('_', ' ') }}</option>
              </select>
            </label>
            <button type="button" :disabled="busy" @click="command('finding', { finding_id: f.id, status: f.next || 'reviewed' })">Append disposition with reason</button>
          </details>
        </article>
      </section>
      <template v-if="file.permissions.review">
      <label class="spaced">Note for your decision
        <FieldTip text="Required when you change a figure or a finding. It is stored with the decision and does not edit the original document." />
        <textarea v-model="notes" maxlength="4000" aria-label="Review reason" />
      </label>
      <nav class="tabs" role="tablist" aria-label="Review details">
        <button v-for="t in detailTabs" :key="t" type="button" role="tab" :aria-selected="tab === t" :class="{ active: tab === t }" @click="tab = t">{{ appraisalTabLabel[t] || t }}</button>
      </nav>
      <div class="review-body" :class="{ 'has-source': source }">
        <div>
          <div v-if="tab === 'facts'">
            <p v-if="!state.facts?.length">No extracted facts yet. Check processing progress and missing or unreadable evidence. Enter only figures you have verified.</p>
            <article v-for="f in state.facts" :key="f.id" class="note-card">
              <strong>{{ f.data.key }} · {{ f.data.unit || 'Property' }} · {{ f.data.status }}</strong>
              <DataPanel :show-evidence="true" :data="f.data" />
              <button type="button" @click="source = f.data">Source page {{ f.data.page }}</button>
              <input v-model="f.confirmedValue" :placeholder="f.data.normalized || 'Unknown'" :aria-label="'Confirmed value for ' + f.data.key" />
              <button type="button" @click="command('confirm', { field_id: f.id, value: f.confirmedValue ?? f.data.normalized })">Confirm with reason</button>
              <DataPanel :show-evidence="true" v-if="f.confirmation" :data="f.confirmation" />
              <div v-if="f.confirmation && state.run?.checklist?.status === 'approved'">
                <select v-model="f.rule" aria-label="Guideline rule"><option v-for="rule in state.run.checklist.rules" :key="rule.id" :value="rule.id">{{ rule.section }} · {{ rule.id }}</option></select>
                <label><input type="checkbox" v-model="f.applicable" /> I verified this specific rule applies to this evidence</label>
                <button type="button" :disabled="!f.rule || !f.applicable" @click="command('baseline_observation', { field_id: f.id, rule_id: f.rule, applicability_confirmed: f.applicable })">Record human-verified baseline observation</button>
              </div>
            </article>
          </div>
          <div v-else-if="tab === 'photos'" class="module-grid">
            <article v-for="p in rows('image')" :key="p.id" class="card">
              <img :src="rr + '/artifacts/' + p.id" alt="Authenticated derived source photograph; observation is unverified" style="max-width:100%;max-height:340px" />
              <p>Version {{ p.data.document_version_id }} · Page {{ p.data.page }} · duplicate hash evidence retained</p>
              <button type="button" @click="source = p.data">Open original page</button>
            </article>
            <article v-for="p in rows('photo_observation')" :key="p.id" class="card">
              <img v-if="rows('page').find((x: any) => x.data.document_version_id === p.data.document_version_id && x.data.number === p.data.page)" :src="rr + '/artifacts/' + rows('page').find((x: any) => x.data.document_version_id === p.data.document_version_id && x.data.number === p.data.page).id" alt="Actual authenticated rendered page associated with unverified observation" style="max-width:100%;max-height:340px" />
              <DataPanel :show-evidence="true" :data="p.data" />
              <button type="button" @click="source = p.data">Observation beside source</button>
            </article>
            <p v-if="!rows('image').length">No extracted photos. Rendered-page vision may still inspect scans; unavailable vision remains explicitly incomplete.</p>
          </div>
          <div v-else-if="tab === 'rent'">
            <HelpNote :text="helpText.dscr" />
            <button type="button" @click="addUnit">Add supported unit</button>
            <button type="button" @click="restore">Restore latest confirmed inputs</button>
            <article v-for="u in input.units" :key="u.id" class="card spaced">
              <h3>Unit {{ u.id }}</h3>
              <label>Unit identity<input v-model="u.id" /></label>
              <label v-for="key in ['inspection_occupancy', 'current_occupancy']" :key="key">{{ key }}<select v-model="u[key]"><option v-for="v in ['unknown', 'occupied', 'vacant']" :key="v">{{ v }}</option></select></label>
              <label>Current lease status<select v-model="u.lease_status"><option v-for="v in ['unknown', 'executed', 'expired_month_to_month', 'vacant', 'short_term']" :key="v">{{ v }}</option></select></label>
              <label>Rent method<select v-model="u.rent_method"><option v-for="v in ['unknown', 'long_term', 'short_term']" :key="v">{{ v }}</option></select></label>
              <label><input type="checkbox" v-model="u.is_adu" /> ADU — scenario only, not automatically eligible</label>
              <div v-for="k in ['market', 'qualifying']" :key="k" class="form-grid">
                <h4>{{ k }} rent</h4>
                <label>USD (blank unknown,0 distinct)<input v-model="u[k].value" type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,2})?" /></label>
                <label>Source document/page or user-entry evidence<input v-model="u[k].source" /></label>
                <label>Explicit basis<input v-model="u[k].basis" /></label>
                <label><input v-model="u[k].confirmed" type="checkbox" /> Reviewer confirmed</label>
              </div>
              <label>Lease version<select v-model="u.leaseVersion"><option v-for="v in versions" :key="v.id" :value="v.id">{{ v.original_name }} · {{ v.id }}</option></select></label>
              <label>Address/unit matching evidence<input v-model="u.matchEvidence" /></label>
              <button type="button" @click="command('unit_match', { document_version_id: Number(u.leaseVersion), unit: u.id, address_evidence: u.matchEvidence, status: 'confirmed' })">Confirm evidence-based lease match</button>
            </article>
            <button class="primary" type="button" @click="command('inputs', { input })">Confirm inputs and create new calculation</button>
            <h3>Extracted lease candidates — never automatically confirmed</h3>
            <DataPanel :show-evidence="true" :data="rows('lease_candidate')" />
            <DataPanel :show-evidence="true" :data="rows('unit_match')" />
            <h3>Source rents, evidence, selected basis and guideline version</h3>
            <DataPanel :show-evidence="true" :data="rows('rent_comparison')" />
            <DataPanel :show-evidence="true" :data="rows('rental_scope')" />
            <h3>Outstanding unit-specific evidence requests</h3>
            <DataPanel :show-evidence="true" :data="(state.findings || []).filter((f: any) => f.data.evidence.unit)" />
          </div>
          <div v-else-if="tab === 'calculations'">
            <HelpNote :text="helpText.dscr" />
            <label>Payment input mode<select v-model="input.payment.mode" aria-label="Payment input mode"><option>total</option><option>components</option></select></label>
            <label><input type="checkbox" v-model="input.payment.interest_only" /> Interest-only scenario</label>
            <label>Reviewer-confirmed payment definition<input v-model="input.payment.definition" /></label>
            <div v-for="[key, m] in Object.entries(input.payment.mode === 'total' ? { total: input.payment.total } : input.payment.components)" :key="key" class="form-grid">
              <h4>{{ key }}</h4>
              <label>Monthly USD<input v-model="(m as any).value" type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,2})?" /></label>
              <label>Source<input v-model="(m as any).source" /></label>
              <label><input type="checkbox" v-model="(m as any).confirmed" /> Confirmed</label>
            </div>
            <div v-for="key in ['loan', 'value_basis']" :key="key" class="form-grid">
              <h4>{{ key }}</h4>
              <input v-model="input[key].value" type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,2})?" />
              <input v-model="input[key].source" placeholder="Source" />
              <input v-model="input[key].basis" placeholder="Selected basis, not universal lower-of" />
              <label><input type="checkbox" v-model="input[key].confirmed" /> Confirmed</label>
            </div>
            <label>Appraised value separately reported<input v-model="input.appraised_value" /></label>
            <button class="primary" type="button" @click="command('inputs', { input })">Confirm and recalculate immutable snapshot</button>
            <DataPanel :show-evidence="true" :data="state.calculation?.data || { notice: 'No confirmed inputs yet' }" />
          </div>
          <div v-else-if="tab === 'findings'">
            <article v-for="f in state.findings" :key="'full-' + f.id" class="note-card">
              <strong>{{ f.status }} · {{ f.data.category }}</strong>
              <DataPanel :show-evidence="true" :data="f.data" />
              <button v-for="ref in sourceRefs(f)" :key="'src-' + (ref.document_version_id || ref.field_id)" type="button" @click="source = ref">Source V{{ ref.document_version_id }} · Page {{ ref.page || 1 }}</button>
              <select v-model="f.next" :aria-label="'Status for ' + (f.data.category || 'finding')"><option v-for="s in ['reviewed', 'resolved', 'needs_follow_up', 'reopened', 'dismissed']" :key="s">{{ s }}</option></select>
              <button type="button" @click="command('finding', { finding_id: f.id, status: f.next || 'reviewed' })">Append disposition with reason</button>
            </article>
          </div>
          <div v-else-if="tab === 'revisions'">
            <p>Proposed differences only. Prior confirmed inputs, reports, and findings stay unchanged until you review them.</p>
            <DataPanel :show-evidence="true" :data="rows('revision')" />
          </div>
          <div v-else-if="tab === 'guidelines'">
            <p>Public sourced draft for the designated reviewer. Developer drafts are not a production approval.</p>
            <DataPanel :show-evidence="true" :data="state.baseline" />
            <button type="button" @click="act(() => api(root + '/guidelines', 'POST', { status: 'draft', notes }))">Create draft baseline version</button>
            <article v-for="g in state.guidelines" :key="g.id" class="card">
              <DataPanel :show-evidence="true" :data="g" />
              <button type="button" @click="act(() => api(root + '/guidelines', 'POST', { status: 'approved', previous_id: g.id, notes }))">Designated client reviewer approval</button>
              <button type="button" @click="act(() => api(root + '/guidelines', 'POST', { status: 'retired', previous_id: g.id, notes }))">Retire in new version</button>
            </article>
          </div>
          <div v-else-if="tab === 'notes'">
            <button type="button" @click="command('note')">Append internal reviewer note</button>
            <DataPanel :show-evidence="true" :data="rows('note')" />
            <DataPanel :show-evidence="true" :data="rows('confirmation')" />
          </div>
        </div>
        <aside v-if="source" class="card spaced">
          <button type="button" @click="source = null">Close source</button>
          <p>Document {{ source.document_version_id }} · Page {{ source.page || source.number || 1 }}</p>
          <DocumentViewer v-if="version" :version="version" :page="source.page || source.number || 1" :region="source.region_verified && source.region ? { x: source.region[0], y: source.region[1], width: source.region[2], height: source.region[3], normalized: true } : undefined" />
          <p v-else>Original unavailable for this role or version.</p>
        </aside>
      </div>
      <IssueReview v-if="issues.length" :issues="issues" :versions="versions" />
      </template>
      <p v-if="!file.permissions.review" class="muted">A reviewer exports the report. Open findings do not block that download.</p>
      <template v-else>
      <p>The report lists flagged items, confirmed figures, and the guideline version. Open findings do not block downloads. This is not a loan approval.</p>
      <button class="primary" type="button" :disabled="!state.run" @click="command('report')">Export report</button>
      <article v-for="r in rows('report')" :key="r.id" class="note-card">
        Report {{ r.data.report_version }} · {{ r.data.hash }}
        <a v-for="format in ['pdf', 'xlsx']" :key="format" class="button" :href="rr + '/reports/' + r.id + '/' + format">Download {{ format }}</a>
      </article>
      </template>
  </Fold>
  </template>
</section>
</template>
<style scoped>
.review-body.has-source { display: grid; grid-template-columns: minmax(0, 1fr) minmax(280px, 1fr); gap: 1rem; align-items: start }
.review-body aside { position: sticky; top: 1rem; min-width: 0 }
.review-body > div { min-width: 0 }
@media (max-width: 900px) {
  .review-body.has-source { display: block }
  .review-body aside { position: static }
}
</style>
