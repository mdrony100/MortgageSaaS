<script setup lang="ts">
import {computed} from 'vue'
import {explainStatus} from '../customerLanguage'
const props = defineProps<{code?: string | null}>()
const copy = computed(() => explainStatus(props.code))
</script>
<template>
<span class="status-pair">
  <span class="badge" :class="copy.tone">{{copy.label}}</span>
  <span class="status-help">{{copy.help}}</span>
</span>
</template>
