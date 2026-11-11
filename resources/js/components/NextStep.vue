<script setup lang="ts">
import type { Guide } from '../customerLanguage'
defineProps<{ guide: Guide }>()
defineEmits<{ act: [] }>()
</script>
<template>
<article class="guide-card" :class="[guide.tone, { 'is-waiting': guide.waiting, 'is-done': guide.cta === 'export' }]" :key="guide.cta + guide.title" aria-live="polite">
  <p class="eyebrow">What to do next</p>
  <h2>{{ guide.title }}</h2>
  <p>{{ guide.body }}</p>
  <div v-if="guide.waiting" class="processing-bar" role="progressbar" aria-label="Analysis in progress"><span></span></div>
  <button v-if="guide.action" class="primary" type="button" @click="$emit('act')">{{ guide.action }}</button>
</article>
</template>
