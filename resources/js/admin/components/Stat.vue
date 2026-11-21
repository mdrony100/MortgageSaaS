<script setup lang="ts">
import { BarChart3 } from 'lucide-vue-next'
defineProps<{ label: string, value: string | number, hint?: string, to?: string }>()
</script>
<template>
<component :is="to ? 'RouterLink' : 'div'" :to="to" class="c-stat">
  <BarChart3 class="c-stat-icon" :size="18" aria-hidden="true"/>
  <span class="c-stat-copy">
    <span class="c-stat-label">{{ label }}</span>
    <strong class="c-stat-value">{{ value }}</strong>
    <span v-if="hint" class="c-stat-hint">{{ hint }}</span>
  </span>
</component>
</template>
