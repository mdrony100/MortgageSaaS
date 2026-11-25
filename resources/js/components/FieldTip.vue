<script setup lang="ts">
import { ref } from 'vue'
defineProps<{ text: string; label?: string }>()
const open = ref(false)
</script>
<template>
<span class="field-tip">
  <button type="button" class="tip-btn" :aria-expanded="open ? 'true' : 'false'" :aria-label="label || 'More about this field'" @click="open = !open">?</button>
  <span v-if="open" class="tip-pop" role="note">{{ text }}</span>
</span>
</template>
