<script setup lang="ts">
import { ref, watch } from 'vue'
import { ChevronDown } from 'lucide-vue-next'

const props = defineProps<{ title: string; hint?: string; open?: boolean }>()
const emit = defineEmits<{ toggle: [open: boolean] }>()
const expanded = ref(!!props.open)
watch(() => props.open, (value) => { if (value !== undefined) expanded.value = value })
function onToggle(event: Event) {
  const el = event.target as HTMLDetailsElement
  if (event.target !== el) return
  expanded.value = el.open
  emit('toggle', el.open)
}
</script>
<template>
<details class="fold" :open="open" @toggle="onToggle">
  <summary :aria-expanded="expanded ? 'true' : 'false'">
    <span class="fold-copy">
      <strong>{{ title }}</strong>
      <small v-if="hint">{{ hint }}</small>
    </span>
    <ChevronDown class="fold-chevron" :size="20" aria-hidden="true" />
  </summary>
  <div class="fold-body"><slot /></div>
</details>
</template>
