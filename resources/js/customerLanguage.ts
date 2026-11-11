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
  draft: { label: 'Not started', help: 'Upload bank statements or an appraisal to begin.', tone: '' },
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

export function explainStatus(code?: string | null): StatusCopy {
  if (!code) return { label: 'Unknown', help: 'Refresh the page. If this stays unknown, ask your administrator.', tone: '' }
  return fileStatus[code] || runStatus[code] || scanStatus[code] || {
    label: code.replaceAll('_', ' '),
    help: 'Refresh the page, or ask a reviewer if you are unsure what to do next.',
    tone: '',
  }
}

export const fileSteps = [
  { id: 1, label: 'Upload', hint: 'Upload bank statements or the appraisal for this borrower.' },
  { id: 2, label: 'Processing', hint: 'Wait while the documents are read. You can leave and come back.' },
  { id: 3, label: 'Review findings', hint: 'A person checks potential issues. Nothing is approved or declined automatically.' },
  { id: 4, label: 'Export report', hint: 'Download Excel or PDF. Open findings do not block the export.' },
] as const

export function plainFailure(code?: string | null): string {
  if (!code) return 'Something went wrong while reading the file. Check that it is an unlocked PDF, PNG, or JPG, then start analysis again.'
  if (/unavailable|unscanned/i.test(code)) return 'The virus scan could not run, so this file is not certified clean. Processing stopped. Ask an administrator about scanning, or use a practice file for a fictional sample.'
  if (/rejected|scanfailed|scan failed/i.test(code)) return 'The security scan stopped this file. Upload a different copy, then start analysis again.'
  return code
}

const processingStatuses = ['Queued', 'Validating', 'Extracting', 'Reconciling', 'Analyzing', 'DocumentClassificationAndExtraction']

export type JourneyPhase = 'upload' | 'start' | 'processing' | 'failed' | 'review' | 'export' | 'archived'

export type Journey = {
  step: number
  settled: boolean
  failed: boolean
  phase: JourneyPhase
  badge: StatusCopy
  title: string
  body: string
  cta: string | null
}

export function journeyFor(opts: {
  documents: number
  runStatus?: string | null
  hasReport?: boolean
  archived?: boolean
  fileStatus?: string | null
  errorCode?: string | null
  module?: string | null
}): Journey {
  const uploadTitle = opts.module === 'appraisal' ? 'Upload the appraisal' : opts.module === 'dscr' ? 'Upload the rental documents' : 'Upload bank statements'
  if (opts.archived) {
    return { step: 4, settled: true, failed: false, phase: 'archived', badge: fileStatus.archived, title: 'This file is archived', body: 'Open File details and choose Restore if you need it again.', cta: null }
  }
  if (!opts.documents) {
    return {
      step: 1, settled: false, failed: false, phase: 'upload',
      badge: { label: 'Not started', help: 'Upload the documents for this borrower.', tone: '' },
      title: uploadTitle,
      body: 'PDF, PNG, or JPG, up to 20 MB. Nothing is read until you start analysis.',
      cta: 'Upload documents',
    }
  }
  const status = opts.runStatus || ''
  if (processingStatuses.includes(status)) {
    const badge = explainStatus(status)
    return {
      step: 2, settled: false, failed: false, phase: 'processing', badge,
      title: badge.label,
      body: 'You can leave this page and come back. This is not a loan decision.',
      cta: null,
    }
  }
  if (status === 'Failed') {
    return {
      step: 2, settled: false, failed: true, phase: 'failed', badge: explainStatus('Failed'),
      title: 'Processing did not finish',
      body: plainFailure(opts.errorCode),
      cta: 'Try again',
    }
  }
  if (opts.hasReport || status === 'Completed' || opts.fileStatus === 'complete') {
    return {
      step: 4, settled: false, failed: false, phase: 'export',
      badge: { label: 'Ready to export', help: 'Download the report. Open findings can stay open.', tone: 'teal' },
      title: 'Export the report',
      body: 'Download Excel or PDF. Open findings do not block the export. This is not a loan approval.',
      cta: 'Go to export',
    }
  }
  if (status === 'NeedsReview' || status === 'PartiallyCompleted' || opts.fileStatus === 'in_review') {
    const badge = status ? explainStatus(status) : fileStatus.in_review
    return {
      step: 3, settled: false, failed: false, phase: 'review', badge,
      title: 'Review what was flagged',
      body: 'Choose an item to see it on the document. A person decides what it means. The tool does not call something fraud or approve a loan.',
      cta: 'Review findings',
    }
  }
  if (status === 'Cancelled') {
    return {
      step: 1, settled: true, failed: false, phase: 'start', badge: explainStatus('Cancelled'),
      title: 'Analysis was cancelled',
      body: 'The documents are still saved. Start analysis again when you are ready.',
      cta: 'Start analysis',
    }
  }
  return {
    step: 1, settled: true, failed: false, phase: 'start',
    badge: { label: 'Ready to start', help: 'Documents are saved. Start analysis when you are ready. Processing has not begun.', tone: 'amber' },
    title: 'Start analysis',
    body: 'Uploading only stores the file. The documents are not read until you start analysis.',
    cta: 'Start analysis',
  }
}

export function stepFor(opts: { documents: number; runStatus?: string | null; hasReport?: boolean }): number {
  return journeyFor(opts).step
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
