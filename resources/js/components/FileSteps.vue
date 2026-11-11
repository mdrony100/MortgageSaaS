<script setup lang="ts">
import {fileSteps} from '../customerLanguage'
defineProps<{current: number, settled?: boolean, failed?: boolean}>()
</script>
<template>
<ol class="file-steps" aria-label="Steps for this loan file">
  <li v-for="step in fileSteps" :key="step.id" :class="{done: step.id < current || (settled && step.id === current), current: step.id === current && !settled && !failed, failed: failed && step.id === current, upcoming: settled && step.id === current + 1}" :aria-current="step.id === current && !settled ? 'step' : undefined">
    <span class="file-step-index" aria-hidden="true">{{step.id < current || (settled && step.id === current) ? '✓' : step.id}}</span>
    <span class="file-step-copy">
      <strong>{{step.label}}</strong>
      <small v-if="step.id === current && !settled">{{step.hint}}</small>
      <small v-else-if="settled && step.id === current + 1">Up next</small>
    </span>
  </li>
</ol>
</template>
