<script setup lang="ts">
import type { FlowStep } from '../customerLanguage'
defineProps<{ steps: FlowStep[]; openId: number }>()
const emit = defineEmits<{ toggle: [id: number] }>()
</script>
<template>
<ol class="guided-steps" aria-label="Steps for this loan file">
  <li v-for="step in steps" :key="step.id" :data-step="step.id" :class="['guided-step', step.state, { open: openId === step.id }]" :aria-current="step.state === 'current' || step.state === 'blocked' ? 'step' : undefined">
    <header class="guided-step-head">
      <span class="file-step-index" aria-hidden="true">{{ step.state === 'done' ? '✓' : step.id }}</span>
      <div class="guided-step-copy">
        <strong>{{ step.label }}</strong>
        <small v-if="openId !== step.id">{{ step.state === 'locked' ? step.lockReason : step.summary }}</small>
      </div>
      <button v-if="step.state === 'done' && openId !== step.id" type="button" class="text-button step-edit" @click="emit('toggle', step.id)">Edit</button>
      <button v-else-if="step.state === 'ready' && openId !== step.id" type="button" class="text-button step-open" @click="emit('toggle', step.id)">Open</button>
      <button v-else-if="(step.state === 'done' || step.state === 'ready') && openId === step.id" type="button" class="text-button step-close" @click="emit('toggle', step.id)">Close</button>
    </header>
    <div v-if="step.state !== 'locked'" v-show="openId === step.id" class="guided-step-body">
      <slot :name="'step-' + step.id" />
    </div>
  </li>
</ol>
</template>
