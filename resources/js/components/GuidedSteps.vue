<script setup lang="ts">
import { ChevronRight, ChevronDown } from 'lucide-vue-next'
import type { FlowStep } from '../customerLanguage'
defineProps<{ steps: FlowStep[] }>()
</script>
<template>
<ol class="step-row" aria-label="Steps for this loan file">
  <template v-for="(step, index) in steps" :key="step.id">
    <li v-if="index" class="step-arrow" aria-hidden="true">
      <ChevronRight class="step-arrow-right" :size="18" />
      <ChevronDown class="step-arrow-down" :size="18" />
    </li>
    <li :data-step="step.id" :class="step.state" :aria-current="step.state === 'current' || step.state === 'blocked' ? 'step' : undefined">
      <span class="file-step-index" aria-hidden="true">{{ step.state === 'done' ? '✓' : step.id }}</span>
      <span class="step-row-copy">
        <strong>{{ step.label }}</strong>
        <small>{{ step.state === 'locked' ? step.lockReason : step.summary }}</small>
      </span>
    </li>
  </template>
</ol>
</template>
