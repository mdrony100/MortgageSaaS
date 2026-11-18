<script setup lang="ts">
import {computed} from 'vue'
import {explainIntegrity, explainStatus} from '../customerLanguage'
const props = defineProps<{code?: string | null; kind?: 'status' | 'integrity'}>()
const copy = computed(() => props.kind === 'integrity' ? explainIntegrity(props.code) : explainStatus(props.code))
</script>
<template>
<span class="status-pair">
  <span class="badge" :class="copy.tone">{{copy.label}}</span>
  <span class="status-help">{{copy.help}}</span>
</span>
</template>
