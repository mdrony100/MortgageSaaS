<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useAuth } from '../stores/auth'
import { api } from '../api'

const auth = useAuth()
const message = ref('')

async function load() {
  message.value = ''
  if (!auth.orgId) return
  try {
    const data = await api('/api/organizations/' + auth.orgId + '/page-credits')
    if (data?.warning && data.warning_message) message.value = data.warning_message
  } catch {
    message.value = ''
  }
}

onMounted(load)
watch(() => auth.orgId, load)
</script>
<template>
<p v-if="message" class="credit-banner" role="status">{{ message }} <a href="/settings/plans">Open Plan</a></p>
</template>
<style>
.credit-banner{margin:0 0 16px;padding:12px 14px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;color:#78350f;font-size:16px;line-height:1.45}
.credit-banner a{font-weight:700;color:#78350f}
</style>
