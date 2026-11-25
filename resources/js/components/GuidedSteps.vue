<script setup lang="ts">
import type { FlowStep } from '../customerLanguage'
defineProps<{ steps: FlowStep[] }>()
</script>
<template>
<ol class="step-row" aria-label="Steps for this loan file">
  <li v-for="step in steps" :key="step.id" :data-step="step.id" :class="step.state" :aria-current="step.state === 'current' || step.state === 'blocked' ? 'step' : undefined">
    <span class="file-step-index" aria-hidden="true">{{ step.state === 'done' ? '✓' : step.id }}</span>
    <span class="step-row-copy">
      <strong>{{ step.label }}</strong>
      <small>{{ step.state === 'locked' ? step.lockReason : step.summary }}</small>
    </span>
  </li>
</ol>
</template>
