/** Plain-language labels for the customer workspace. Display only; values sent to the API stay unchanged. */

export const roleNames: Record<string, string> = {
  owner: 'Owner',
  admin: 'Admin',
  underwriter: 'Reviewer',
  processor: 'Processor',
  broker: 'Broker',
  viewer: 'View only',
}

export function roleName(role?: string | null): string {
  if (!role) return 'Team member'
  return roleNames[role] || role
}

export type StatusCopy = { label: string; help: string; tone: '' | 'teal' | 'amber' | 'red' }

const fileStatus: Record<string, StatusCopy> = {
  draft: { label: 'Ready to upload', help: 'Upload bank statements or an appraisal to begin.', tone: '' },
  in_review: { label: 'In review', help: 'A person is checking the findings. Nothing is approved or declined automatically.', tone: 'amber' },
  complete: { label: 'Ready to export', help: 'You can download the report. Open findings can stay open.', tone: 'teal' },
  archived: { label: 'Archived', help: 'This file is put away. Open it and choose Restore if you need it again.', tone: '' },
}

const runStatus: Record<string, StatusCopy> = {
  Queued: { label: 'Waiting to start', help: 'Processing is in line. You can leave this page and come back.', tone: 'amber' },
  Validating: { label: 'Checking the file', help: 'We are confirming this is the document you selected.', tone: 'amber' },
  Extracting: { label: 'Reading the document', help: 'Transactions and fields are being read. This is not a loan decision.', tone: 'amber' },
  Reconciling: { label: 'Checking the totals', help: 'Printed totals are being compared with the transactions.', tone: 'amber' },
  Analyzing: { label: 'Preparing the review', help: 'Figures are being prepared for a person to check.', tone: 'amber' },
  NeedsReview: { label: 'Ready for review', help: 'Read the findings. A person decides what to include. The tool only flags potential issues.', tone: 'amber' },
  PartiallyCompleted: { label: 'Processed with gaps', help: 'Some months or pages are missing. Review those gaps before you rely on the income figure.', tone: 'amber' },
  Completed: { label: 'Processing finished', help: 'Review the figures, then export a report.', tone: 'teal' },
  Failed: { label: 'Processing failed', help: 'The file could not be read. Use an unlocked PDF, PNG, or JPG, then try again.', tone: 'red' },
  Cancelled: { label: 'Cancelled', help: 'This run was stopped. Start processing again when you are ready.', tone: '' },
}

const scanStatus: Record<string, StatusCopy> = {
  Unscanned: { label: 'Not virus-scanned', help: 'No virus scanner is installed, so this file was stored without a malware check. It is not certified clean.', tone: 'amber' },
  Pending: { label: 'Scan waiting', help: 'A security scan has not finished. Preview stays locked until it does.', tone: 'amber' },
  Clean: { label: 'Scan clear', help: 'The security scan did not report a problem.', tone: 'teal' },
  Rejected: { label: 'Scan stopped this file', help: 'The security scan refused this file. Do not use it. Upload a different copy.', tone: 'red' },
  ScanFailed: { label: 'Scan could not finish', help: 'The security scan failed. Try the upload again.', tone: 'red' },
  Unavailable: { label: 'Scan unavailable', help: 'Scanning could not run. The file is not certified clean.', tone: 'amber' },
  not_configured: { label: 'Scan not set up', help: 'Scanning is not configured. The file is not certified clean.', tone: 'amber' },
  infected: { label: 'Scan stopped this file', help: 'The security scan refused this file. Do not use it.', tone: 'red' },
  failed: { label: 'Scan could not finish', help: 'The security scan failed. Try the upload again.', tone: 'red' },
}

const integrityCopy: Record<'Clean' | 'Unscanned' | 'Unavailable' | 'Quarantined', StatusCopy> = {
  Clean: { label: 'Clean', help: 'The security scan did not report a problem. Original bytes are unchanged.', tone: 'teal' },
  Unscanned: { label: 'Unscanned', help: 'No virus scan has cleared this file. It is not certified clean.', tone: 'amber' },
  Unavailable: { label: 'Unavailable', help: 'Scanning could not run. The file is not certified clean.', tone: 'amber' },
  Quarantined: { label: 'Quarantined', help: 'The security scan held this file. Do not use it. Upload a different copy.', tone: 'red' },
}

/** Map a stored scan verdict to the four reader badges. */
export function integrityCode(version?: { scan_status?: string | null; latest_scan_status?: string | null } | null): keyof typeof integrityCopy {
  const raw = String(version?.latest_scan_status || version?.scan_status || '').trim().toLowerCase()
  if (['infected', 'rejected', 'scanfailed', 'failed', 'quarantined'].includes(raw) || raw.includes('quarantine')) return 'Quarantined'
  if (raw === 'clean') return 'Clean'
  if (raw === 'unavailable' || raw.includes('unavailable')) return 'Unavailable'
  return 'Unscanned'
}

export function explainIntegrity(code?: string | null): StatusCopy {
  if (code === 'Clean' || code === 'Unscanned' || code === 'Unavailable' || code === 'Quarantined') return integrityCopy[code]
  return integrityCopy[integrityCode({ scan_status: code })]
}

export function explainStatus(code?: string | null): StatusCopy {
  if (!code) return { label: 'Unknown', help: 'Refresh the page. If this stays unknown, ask your administrator.', tone: '' }
  return fileStatus[code] || runStatus[code] || scanStatus[code] || {
    label: code.replaceAll('_', ' '),
    help: 'Refresh the page, or ask a reviewer if you are unsure what to do next.',
    tone: '',
  }
}

export const fileSteps = [
  { id: 1, label: 'Documents & details', hint: 'Upload bank statements or the appraisal, then add the loan details.' },
  { id: 2, label: 'Process', hint: 'Start reading the documents. You can leave and come back.' },
  { id: 3, label: 'Review & export', hint: 'Check what was flagged, then export the report.' },
] as const

export type FailureCta = 'retry' | 'replace' | 'admin'

/** One plain-language failure, and the single action that fits it. Raw codes are never shown. */
export function failureGuide(code?: string | null): { body: string; cta: FailureCta; button: string } {
  const raw = String(code || '')
  if (/page credits are used up|more pages than your plan allows/i.test(raw)) {
    return { body: /more pages than your plan allows/i.test(raw) ? 'This file has more pages than your plan allows for one loan file. Request a plan change from Plan.' : 'Your page credits are used up. Request more pages from Plan.', cta: 'admin', button: 'Contact support' }
  }
  if (/rejected|infected|quarantined/i.test(raw) && !/scanner unavailable|malware scan unavailable|unscanned/i.test(raw)) {
    return { body: 'The security scan stopped this file. Upload a different copy, then start processing again.', cta: 'replace', button: 'Replace the file' }
  }
  if (/scanfailed|scan failed/i.test(raw)) {
    return { body: 'The security scan could not finish. Try again with an unlocked PDF, PNG, or JPG.', cta: 'retry', button: 'Try again' }
  }
  if (/scanner unavailable|malware scan unavailable|unscanned/i.test(raw)) {
    return { body: 'The virus scan could not run, so this file is not certified clean. Processing stopped. Ask an administrator about scanning, or open a practice file and load the sample analysis.', cta: 'admin', button: 'Contact support' }
  }
  if (/password|encrypted|locked pdf/i.test(raw)) {
    return { body: 'This file looks locked. Upload an unlocked PDF, PNG, or JPG, then try again.', cta: 'replace', button: 'Replace the file' }
  }
  if (/page limit|image review limit|limit exceeded|limit reached/i.test(raw)) {
    return { body: 'This file is larger than the processing limit. Use fewer documents, or ask an administrator, then try again.', cta: 'admin', button: 'Contact support' }
  }
  if (/hash mismatch|scope invalid/i.test(raw)) {
    return { body: 'The saved file does not match what was uploaded. Upload the document again.', cta: 'replace', button: 'Replace the file' }
  }
  if (/provider|rate limit|replay|operator review|concurrent provider|openai|manual review required/i.test(raw)) {
    return { body: 'Reading stopped because a required service needs an administrator. Nothing was approved or declined.', cta: 'admin', button: 'Contact support' }
  }
  if (/bounded attempts/i.test(raw)) {
    return { body: 'Processing stopped after several tries. Check that the file is an unlocked PDF, PNG, or JPG, then try again.', cta: 'retry', button: 'Try again' }
  }
  return { body: 'Something went wrong while reading the file. Check that it is an unlocked PDF, PNG, or JPG, then try again.', cta: 'retry', button: 'Try again' }
}

export function plainFailure(code?: string | null): string {
  return failureGuide(code).body
}

export const documentTypeOptions = [
  { value: 'uncertain', label: 'Not sure yet' },
  { value: 'appraisal', label: 'Appraisal' },
  { value: 'sales_comparison', label: 'Sales comparison' },
  { value: 'rental_schedule', label: 'Rent schedule' },
  { value: 'operating_income', label: 'Operating income' },
  { value: 'addendum', label: 'Addendum' },
  { value: 'photo', label: 'Photo' },
  { value: 'sketch', label: 'Sketch' },
  { value: 'map', label: 'Map' },
  { value: 'contract', label: 'Purchase contract' },
  { value: 'contract_amendment', label: 'Contract amendment' },
  { value: 'lease', label: 'Lease' },
  { value: 'lease_amendment', label: 'Lease amendment' },
  { value: 'rent_roll', label: 'Rent roll' },
  { value: 'rent_payment', label: 'Rent payment' },
  { value: 'repair_completion', label: 'Repair completion' },
  { value: 'revised_appraisal', label: 'Revised appraisal' },
] as const

export const occupancyOptions = [
  { value: 'primary', label: 'Primary home' },
  { value: 'second_home', label: 'Second home' },
  { value: 'investment', label: 'Investment' },
  { value: 'unknown', label: 'Not set yet' },
] as const

export const transactionOptions = [
  { value: 'purchase', label: 'Purchase' },
  { value: 'rate_term', label: 'Rate and term refinance' },
  { value: 'cash_out', label: 'Cash-out refinance' },
  { value: 'unknown', label: 'Not set yet' },
] as const

/** Guess a document type from the file name. The reviewer can change it. Values sent to the API stay the same. */
export function guessDocumentType(name?: string | null): string {
  const n = String(name || '').toLowerCase()
  if (/lease/.test(n)) return 'lease'
  if (/1007|rent[-_\s]?schedule|rental[-_\s]?schedule/.test(n)) return 'rental_schedule'
  if (/216|operating/.test(n)) return 'operating_income'
  if (/1025|1004|1073|appraisal/.test(n)) return 'appraisal'
  if (/photo/.test(n)) return 'photo'
  if (/sketch/.test(n)) return 'sketch'
  if (/amendment/.test(n) && /contract/.test(n)) return 'contract_amendment'
  if (/contract/.test(n)) return 'contract'
  if (/rent[-_\s]?roll/.test(n)) return 'rent_roll'
  if (/addendum/.test(n)) return 'addendum'
  return 'uncertain'
}

export function documentTypeLabel(value?: string | null, name?: string | null): string {
  const n = String(name || '').toLowerCase()
  if (/1025/.test(n) && (value === 'appraisal' || value === 'uncertain' || !value || /1025/.test(n))) return 'Appraisal form 1025'
  if (/1004/.test(n)) return 'Appraisal form 1004'
  if (/1073/.test(n) && value === 'appraisal') return 'Appraisal form 1073'
  if (value === 'rental_schedule' || /1007/.test(n)) return 'Rent schedule (form 1007)'
  if (value === 'operating_income' || /(?:form[-_\s]?216|\b216\b)/.test(n)) return 'Operating income (form 216)'
  if (value === 'lease' || (/lease/.test(n) && value !== 'lease_amendment')) return 'Lease'
  return documentTypeOptions.find((option) => option.value === value)?.label || 'Document'
}

export function optionLabel(options: readonly { value: string; label: string }[], value?: string | null): string {
  return options.find((option) => option.value === value)?.label || 'Not set yet'
}

export type GuideCta = 'upload' | 'details' | 'start' | 'wait' | 'review' | 'export' | 'retry' | 'replace' | 'admin' | 'none' | 'create'

export type Guide = {
  step: number
  badge: string
  tone: '' | 'teal' | 'amber' | 'red'
  title: string
  body: string
  hint: string
  waiting: boolean
  action: string
  cta: GuideCta
  /** Small text link beside the one primary button. Not a second action. */
  support?: boolean
}

const processingStatuses = ['Queued', 'Validating', 'Extracting', 'Reconciling', 'Analyzing']

export function guideFor(opts: {
  documents: number
  runStatus?: string | null
  hasReport?: boolean
  archived?: boolean
  canReview?: boolean
  subject?: string
  failure?: string | null
}): Guide {
  const subject = opts.subject || 'bank statements'
  const canReview = opts.canReview !== false
  if (opts.archived) {
    return { step: 3, badge: 'Archived', tone: '', title: 'This file is archived', body: 'Open File actions and choose Restore if you need it again.', hint: 'Archived files stay out of the active list.', waiting: false, action: '', cta: 'none' }
  }
  if (!opts.documents) {
    return { step: 1, badge: 'Ready to upload', tone: '', title: `Upload ${subject}`, body: 'Add a PDF, PNG, or JPG up to 20 MB.', hint: 'Upload the documents for this borrower.', waiting: false, action: 'Upload documents', cta: 'upload' }
  }
  const status = opts.runStatus || ''
  if (!status) {
    if (!canReview) {
      return { step: 1, badge: 'Waiting for a reviewer', tone: 'amber', title: 'Waiting for a reviewer', body: 'Your documents are saved. A reviewer starts processing. Uploading only stores the file.', hint: 'A reviewer starts processing.', waiting: false, action: '', cta: 'none' }
    }
    return { step: 1, badge: 'Loan details', tone: 'amber', title: 'Fill loan details', body: 'Add occupancy, the transaction, and how many units. Uploading only stores the file.', hint: 'Fill in the loan details.', waiting: false, action: 'Fill loan details', cta: 'details' }
  }
  if (processingStatuses.includes(status)) {
    const copy = explainStatus(status)
    return { step: 2, badge: copy.label, tone: 'amber', title: copy.label, body: copy.help, hint: 'You can leave this page and come back.', waiting: true, action: '', cta: 'wait' }
  }
  if (status === 'Failed') {
    const failure = failureGuide(opts.failure)
    return { step: 2, badge: 'Processing failed', tone: 'red', title: 'Processing failed', body: failure.body, hint: failure.body, waiting: false, action: canReview ? 'Try again' : '', cta: canReview ? 'retry' : 'none', support: failure.cta === 'admin' }
  }
  if (status === 'Cancelled') {
    const copy = explainStatus(status)
    return { step: 2, badge: copy.label, tone: 'amber', title: 'Processing was cancelled', body: copy.help, hint: 'Start processing again when you are ready.', waiting: false, action: canReview ? 'Start processing' : '', cta: canReview ? 'start' : 'none' }
  }
  if (status === 'NeedsReview' || status === 'PartiallyCompleted') {
    const copy = explainStatus(status)
    return {
      step: 3,
      badge: copy.label,
      tone: 'amber',
      title: canReview ? 'Review what was flagged' : 'A reviewer is checking this file',
      body: canReview ? 'Work through each flagged item. Nothing is approved or declined automatically.' : 'Questions and the export are in Review & export.',
      hint: canReview ? 'Accept, dismiss, or add a note on each item.' : 'Check questions and the export.',
      waiting: false,
      action: canReview ? 'Review findings' : 'See questions',
      cta: 'review',
    }
  }
  if (status === 'Completed' || opts.hasReport) {
    return { step: 3, badge: 'Ready to export', tone: 'teal', title: 'Export the report', body: 'Download Excel or PDF. Open findings do not block the export.', hint: 'Download the report.', waiting: false, action: 'Download report', cta: 'export' }
  }
  if (status === 'draft') {
    return { step: 1, badge: 'Ready to upload', tone: '', title: `Upload ${subject}`, body: explainStatus('draft').help, hint: 'Upload the documents for this borrower.', waiting: false, action: 'Upload documents', cta: 'upload' }
  }
  if (status === 'in_review') {
    return { step: 3, badge: explainStatus('in_review').label, tone: 'amber', title: 'Review what was flagged', body: explainStatus('in_review').help, hint: 'A person is checking the findings.', waiting: false, action: canReview ? 'Review findings' : 'See questions', cta: 'review' }
  }
  if (status === 'complete') {
    return { step: 3, badge: 'Ready to export', tone: 'teal', title: 'Export the report', body: explainStatus('complete').help, hint: 'Download the report.', waiting: false, action: 'Download report', cta: 'export' }
  }
  const copy = explainStatus(status)
  return { step: 2, badge: copy.label, tone: copy.tone, title: copy.label, body: copy.help, hint: 'Refresh the page if this stays unchanged.', waiting: false, action: '', cta: 'none' }
}

export type FlowStepState = 'done' | 'current' | 'blocked' | 'locked' | 'ready'

export type FlowStep = {
  id: number
  label: string
  state: FlowStepState
  summary: string
  lockReason: string
}

const lockReasons: Record<number, string> = {
  1: 'Add documents to begin.',
  2: 'Add documents and loan details first.',
  3: 'Processing has to finish first.',
}

/** Done, current, blocked, and locked states for the three-step review. */
export function flowFor(guide: Guide, info: {
  documents: number
  loanSummary: string
  processingSummary: string
  findingSummary: string
  exportSummary: string
  reviewReady?: boolean
  unlock?: number[]
}): FlowStep[] {
  const current = guide.step
  const failed = guide.tone === 'red' && current === 2
  const documents = info.documents ? `${info.documents} document${info.documents === 1 ? '' : 's'}` : 'No documents yet'
  const reviewLine = [info.findingSummary, info.exportSummary].filter(Boolean).join(' · ')
  return fileSteps.map((step) => {
    let state: FlowStepState
    if (failed && step.id === 2) state = 'blocked'
    else if (step.id < current) state = 'done'
    else if (step.id === current) state = 'current'
    else state = 'locked'
    if (state === 'locked' && info.unlock?.includes(step.id)) state = 'ready'
    const summary = ['', info.documents && info.loanSummary ? `${documents} · ${info.loanSummary}` : documents, info.processingSummary, reviewLine][step.id] || ''
    return { id: step.id, label: step.label, state, summary, lockReason: lockReasons[step.id] || 'Finish the earlier step first.' }
  })
}

export function readingProgress(done: number, total: number): string {
  if (total <= 0) return 'Reading the documents. You can leave this page and come back.'
  return `Reading ${done} of ${total} documents. You can leave this page and come back.`
}

export function stepFor(opts: { documents: number; runStatus?: string | null; hasReport?: boolean; archived?: boolean; canReview?: boolean; subject?: string; failure?: string | null }): number {
  return guideFor(opts).step
}

export type ReviewIssue = {
  id: string | number
  title: string
  detail: string
  severity?: string
  documentVersionId?: number
  page?: number
  region?: { x: number; y: number; width: number; height: number; normalized?: boolean }
}

function asNumber(value: unknown): number | undefined {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) return Number(value)
  return undefined
}

export function regionFrom(raw: any, forceNormalized?: boolean): ReviewIssue['region'] {
  if (!raw) return undefined
  const tuple = Array.isArray(raw)
    ? raw.slice(0, 4).map(Number)
    : [Number(raw.x), Number(raw.y), Number(raw.width ?? raw.w), Number(raw.height ?? raw.h)]
  if (tuple.length < 4 || tuple.some((n) => !Number.isFinite(n))) return undefined
  const [x, y, width, height] = tuple
  if (width <= 0 || height <= 0) return undefined
  const normalized = forceNormalized ?? (raw?.normalized === true || (raw?.normalized !== false && Math.max(x, y, width, height, x + width, y + height) <= 1.05))
  return { x, y, width, height, normalized }
}

export function reviewIssues(rows: any[] | null | undefined): ReviewIssue[] {
  return (rows || []).map((row, index) => {
    const data = row?.data || row || {}
    const evidence = data.evidence || data.source || {}
    const location = row?.location || {}
    const page = asNumber(location.page) ?? asNumber(row?.source_page) ?? asNumber(data.page) ?? asNumber(evidence.page)
    const documentVersionId = asNumber(location.document_version_id) ?? asNumber(row?.source_document_version_id) ?? asNumber(data.document_version_id) ?? asNumber(evidence.document_version_id)
    const columnRegion = [row?.bbox_x, row?.bbox_y, row?.bbox_w, row?.bbox_h].every((value) => value !== null && value !== undefined && value !== '')
      ? regionFrom([row.bbox_x, row.bbox_y, row.bbox_w, row.bbox_h], true)
      : undefined
    const verified = data.region_verified !== false && evidence.region_verified !== false
    const region = regionFrom(location.bbox, true) || columnRegion || (verified ? regionFrom(data.region) || regionFrom(evidence.region) : undefined)
    const code = String(data.title || data.code || data.category || 'Potential issue')
    return {
      id: row?.id ?? index,
      title: code.replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase()),
      detail: String(data.observation || data.explanation || ''),
      severity: String(data.severity || row?.severity || 'review'),
      documentVersionId,
      page,
      region,
    }
  })
}

export function smoothScroll(): ScrollBehavior {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return 'auto'
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
}

export const helpText = {
  income: 'Qualifying income is the eligible deposits in the months you selected, divided by 12 or 24. Missing months are not treated as zero. A reviewer confirms what counts. This is not a loan approval.',
  nsf: 'NSF means a payment was returned for insufficient funds. An overdraft means the balance went below zero. The fee for that event is listed as a fee, not as a second event. These are potential issues for a reviewer, not a fraud verdict.',
  dscr: 'DSCR is monthly rent divided by the monthly payment a reviewer confirmed. For example, $3,300 rent ÷ $3,000 payment = 1.10. The tool does not choose the rent or approve the loan.',
}

export const bankTabLabel: Record<string, string> = {
  progress: 'Processing',
  documents: 'Documents',
  coverage: 'Accounts',
  transactions: 'Deposits',
  transfers: 'Transfers',
  income: 'Qualifying income',
  nsf: 'NSF and overdrafts',
  sustainability: 'Cash flow notes',
  integrity: 'Potential issues',
  conditions: 'Questions for the file',
  history: 'Review history',
  reports: 'Export report',
}

export const appraisalTabLabel: Record<string, string> = {
  intake: 'Upload',
  facts: 'Extracted facts',
  photos: 'Photos',
  rent: 'Rent and DSCR',
  calculations: 'Payment and DSCR',
  findings: 'Potential issues',
  revisions: 'Revisions',
  guidelines: 'Guidelines',
  notes: 'Notes',
  reports: 'Export report',
}
