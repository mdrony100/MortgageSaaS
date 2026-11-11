<script setup lang="ts">
import {computed, ref, watch} from 'vue'
import DocumentViewer from './DocumentViewer.vue'

export type IssueItem = {
  id: string | number
  title: string
  detail: string
  severity?: string
  status?: string
  page?: number | null
  documentVersionId?: number | null
  excerpt?: string
  region?: {x: number, y: number, width: number, height: number, normalized?: boolean} | null
}

const props = defineProps<{issues: IssueItem[], versions: any[]}>()
const activeId = ref<string | number | null>(null)
const focusKey = ref(0)
const pdfPane = ref<HTMLElement>()
const active = computed(() => props.issues.find(issue => issue.id === activeId.value) || props.issues[0] || null)
const version = computed(() => props.versions.find(v => v.id === active.value?.documentVersionId) || props.versions.find(v => v.mime === 'application/pdf') || props.versions[0] || null)
watch(() => props.issues, list => {
  if (!list.some(issue => issue.id === activeId.value)) activeId.value = list[0]?.id ?? null
}, {immediate: true})

function choose(issue: IssueItem) {
  activeId.value = issue.id
  focusKey.value += 1
  const reduce = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  pdfPane.value?.scrollIntoView({behavior: reduce ? 'auto' : 'smooth', block: 'nearest'})
}
</script>
<template>
<div v-if="!issues.length" class="empty-state">
  <h3>No findings yet</h3>
  <p>When analysis finishes, each item shows up here beside the document. Nothing is marked as fraud, and nothing is approved automatically.</p>
</div>
<div v-else class="split-review">
  <div ref="pdfPane" class="split-pdf">
    <h3>Source evidence</h3>
    <DocumentViewer v-if="version" :version="version" :page="active?.page || 1" :region="active?.region || undefined" :focus-key="focusKey"/>
    <p v-else class="alert">The document preview is not available for this file.</p>
    <p v-if="active && !active.region" class="micro">{{active.page ? `Showing page ${active.page}. A precise highlight is not available for this item, so the page is shown from the top.` : 'This item does not name a page yet.'}}</p>
  </div>
  <ol class="split-findings" aria-label="Findings">
    <li v-for="issue in issues" :key="issue.id">
      <button type="button" :aria-current="issue.id === active?.id ? 'true' : undefined" @click="choose(issue)">
        <span class="inline"><span class="badge" :class="issue.severity === 'high' ? 'red' : 'amber'">{{issue.severity || 'review'}}</span><span class="badge">{{issue.status || 'open'}}</span></span>
        <strong>{{issue.title}}</strong>
        <span>{{issue.detail}}</span>
        <small>{{issue.page ? `Page ${issue.page}` : 'Page not identified'}}{{issue.region ? ' · location highlighted' : ''}}</small>
      </button>
    </li>
  </ol>
</div>
</template>
