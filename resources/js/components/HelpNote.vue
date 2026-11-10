<script setup lang="ts">
defineProps<{text: string}>()
</script>
<template>
<p class="help-note"><span class="help-note-label">What this means</span> {{text}}</p>
</template>
