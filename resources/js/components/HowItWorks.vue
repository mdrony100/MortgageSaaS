<script setup lang="ts">
import { ref } from 'vue'
const storageKey = 'nqm-how-review-works'
const open = ref(typeof localStorage === 'undefined' || localStorage.getItem(storageKey) !== 'dismissed')
function dismiss() {
  open.value = false
  try { localStorage.setItem(storageKey, 'dismissed') } catch { /* private mode */ }
}
</script>
<template>
<aside v-if="open" class="how-works" aria-label="How this works">
  <div>
    <strong>How this works</strong>
    <p>Add the documents, confirm the loan details, then run processing. When it finishes, work through each flagged item and export the report. You can leave while processing runs.</p>
  </div>
  <button type="button" @click="dismiss">Dismiss</button>
</aside>
</template>
