<script setup lang="ts">
const props = defineProps<{checks: any[]; versions: any[]; busy?: boolean}>()
const emit = defineEmits<{rerun: [number]; show: [any]}>()

function nameFor(id: number) {
  return props.versions.find((version) => version.id === id)?.original_name || 'Statement'
}
function tone(level?: string) {
  return level === 'high' ? 'red' : level === 'medium' ? 'amber' : 'teal'
}
function riskLabel(level?: string) {
  return level === 'high' ? 'High risk signal' : level === 'medium' ? 'Medium risk signal' : 'Low risk signal'
}
function pending(check: any) {
  return check.status === 'queued' || check.status === 'running'
}
</script>
<template>
<section id="ai-fraud-check" class="card fraud-panel">
  <h2>AI fraud check</h2>
  <p>Automated risk signal from the statement data and parser notes. It is not a final determination, and it does not decide that a document is fraudulent.</p>
  <p v-if="!checks.length">No AI fraud check has been saved for this run yet.</p>
  <article v-for="check in checks" :key="check.id" class="finding-card">
    <h3>{{ nameFor(check.document_version_id) }}</h3>
    <p v-if="check.status === 'not_configured'" class="alert warning" role="status">{{ check.message || 'Add an OpenAI API key and bank model in Admin Settings. No document was sent.' }}</p>
    <p v-else-if="check.status === 'failed'" class="alert warning" role="status">{{ check.message || 'The AI fraud check did not finish. Run it again.' }}</p>
    <p v-else-if="pending(check)" role="status">AI fraud check is running.</p>
    <template v-else-if="check.status === 'completed'">
      <p class="fraud-score"><span class="badge" :class="tone(check.risk_level)">{{ riskLabel(check.risk_level) }}</span> Score {{ check.score }}</p>
      <p>{{ check.summary }}</p>
      <ul v-if="check.findings?.length" class="fraud-findings">
        <li v-for="(finding, index) in check.findings" :key="index">
          <strong>{{ finding.title }}</strong>
          <span v-if="finding.page"> · page {{ finding.page }}</span>
          <span class="fraud-evidence">{{ finding.evidence }}</span>
          <button v-if="finding.page" type="button" @click="emit('show', {document_version_id: check.document_version_id, page: finding.page})">View page {{ finding.page }}</button>
        </li>
      </ul>
      <p v-else>No specific findings were returned.</p>
    </template>
    <button type="button" :disabled="busy || pending(check)" @click="emit('rerun', check.document_version_id)">Re-run fraud check</button>
  </article>
</section>
</template>
<style scoped>
.fraud-panel{margin:12px 0}
.fraud-panel h2{font-size:16px;margin:0 0 4px}
.fraud-panel p,.fraud-panel li{font-size:16px}
.fraud-score{display:flex;align-items:center;gap:8px;margin:8px 0}
.fraud-findings{margin:8px 0;padding-left:18px}
.fraud-evidence{display:block}
</style>
