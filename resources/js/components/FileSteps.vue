<script setup lang="ts">
import {fileSteps} from '../customerLanguage'
defineProps<{current: number; hint?: string; waiting?: boolean}>()
</script>
<template>
<ol class="file-steps" aria-label="Steps for this loan file" :aria-busy="waiting ? 'true' : undefined">
  <li v-for="step in fileSteps" :key="step.id" :class="{done: step.id < current, current: step.id === current, waiting: waiting && step.id === current}" :aria-current="step.id === current ? 'step' : undefined">
    <span class="file-step-index" aria-hidden="true">{{step.id}}</span>
    <span class="file-step-copy">
      <strong>{{step.label}}</strong>
      <small v-if="step.id === current">{{hint || step.hint}}</small>
    </span>
  </li>
</ol>
</template>
