<script setup lang="ts">
import { ref } from 'vue'
const open = ref(false)
</script>
<template>
<span class="field-tip how-help">
  <button type="button" class="tip-btn" :aria-expanded="open ? 'true' : 'false'" aria-label="How this works" @click="open = !open">?</button>
  <span v-if="open" class="tip-pop" role="note">Add the documents and loan details, run processing, then review what was flagged and export. You can leave while processing runs.</span>
</span>
</template>
