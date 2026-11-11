<script setup lang="ts">
import { computed, ref } from 'vue'
import DocumentViewer from './DocumentViewer.vue'
import { smoothScroll, type ReviewIssue } from '../customerLanguage'

const props = defineProps<{ issues: ReviewIssue[]; versions: any[]; empty?: string }>()
const picked = ref<ReviewIssue | null>(null)
const focus = ref(0)
const version = computed(() => props.versions.find((v) => v.id === (picked.value?.documentVersionId ?? props.versions[0]?.id)))
const page = computed(() => picked.value?.page || 1)
const region = computed(() => picked.value?.region)

function choose(issue: ReviewIssue, event: Event) {
  picked.value = issue
  focus.value += 1
  const root = (event.currentTarget as HTMLElement | null)?.closest('.split-review')
  root?.querySelector('.split-pdf')?.scrollIntoView({ behavior: smoothScroll(), block: 'nearest' })
}
function place(issue: ReviewIssue) {
  if (issue.region) return `Page ${issue.page} · highlighted on the document`
  if (issue.page) return `Page ${issue.page}`
  return 'No page location yet'
}
</script>
<template>
<section id="issue-review" class="split-review" aria-label="Findings beside the document">
  <div class="split-pdf">
    <DocumentViewer v-if="version && version.mime === 'application/pdf'" :version="version" :page="page" :region="region" :focus="focus" />
    <img v-else-if="version" class="source-image" :src="'/api/document-versions/' + version.id + '/content'" alt="Uploaded document" />
    <div v-else class="empty-state">
      <h3>The document will show here</h3>
      <p>Upload a PDF, then open an issue to jump to its page.</p>
    </div>
  </div>
  <div class="split-issues">
    <h2>What was flagged</h2>
    <p class="micro">Click an issue to open that page. A box is drawn when we know where on the page it is.</p>
    <ol>
      <li v-for="issue in issues" :key="issue.id">
        <button type="button" :class="{ active: picked?.id === issue.id }" :aria-current="picked?.id === issue.id ? 'true' : undefined" @click="choose(issue, $event)">
          <strong>{{ issue.title }}</strong>
          <span>{{ issue.detail }}</span>
          <small>{{ place(issue) }}</small>
        </button>
      </li>
    </ol>
    <div v-if="!issues.length" class="empty-state">
      <h3>Nothing to review yet</h3>
      <p>{{ empty || 'When analysis flags something, it shows up here beside the document. A person decides what it means.' }}</p>
    </div>
  </div>
</section>
</template>
