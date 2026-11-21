<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Check, ChevronDown } from 'lucide-vue-next'
import { useAuth } from '../stores/auth'
import { roleName } from '../customerLanguage'

const auth = useAuth()
const router = useRouter()
const open = ref(false)
const buttonEl = ref<HTMLButtonElement | null>(null)
const optionEls = ref<HTMLButtonElement[]>([])
const focusAt = ref(0)
const root = ref<HTMLElement | null>(null)

const many = computed(() => auth.memberships.length > 1)
const current = computed(() => auth.membership)
const plan = computed(() => auth.org?.plan?.name || '')
const subtitle = computed(() => [roleName(current.value?.role), plan.value].filter(Boolean).join(' · '))

function initials(name: string) {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  const letters = parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '')
  return letters.join('') || 'C'
}

function close(restore = true) {
  open.value = false
  if (restore) nextTick(() => buttonEl.value?.focus())
}

function choose(id: number) {
  open.value = false
  if (id === auth.orgId) return
  auth.selectOrg(id)
  router.push('/dashboard')
}

function focusOption(index: number) {
  const count = auth.memberships.length
  if (!count) return
  focusAt.value = (index + count) % count
  nextTick(() => optionEls.value[focusAt.value]?.focus())
}

function onButtonKey(event: KeyboardEvent) {
  if (!many.value) return
  if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    open.value = true
    const currentIndex = Math.max(0, auth.memberships.findIndex((item) => item.organization_id === auth.orgId))
    focusAt.value = currentIndex
    nextTick(() => optionEls.value[currentIndex]?.focus())
  }
}

function onMenuKey(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
  } else if (event.key === 'ArrowDown') {
    event.preventDefault()
    focusOption(focusAt.value + 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    focusOption(focusAt.value - 1)
  } else if (event.key === 'Home') {
    event.preventDefault()
    focusOption(0)
  } else if (event.key === 'End') {
    event.preventDefault()
    focusOption(auth.memberships.length - 1)
  } else if (event.key === 'Tab') {
    close(false)
  }
}

function onDoc(event: MouseEvent) {
  if (open.value && root.value && !root.value.contains(event.target as Node)) open.value = false
}

function onEsc(event: KeyboardEvent) {
  if (event.key === 'Escape' && open.value) close()
}

watch(() => auth.orgId, () => { open.value = false })
onMounted(() => {
  document.addEventListener('pointerdown', onDoc)
  document.addEventListener('keydown', onEsc)
})
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDoc)
  document.removeEventListener('keydown', onEsc)
})
</script>

<template>
<div ref="root" class="org-switch" :class="{ open, single: !many }">
  <button v-if="many" ref="buttonEl" type="button" class="org-trigger" aria-haspopup="listbox" :aria-expanded="open" aria-controls="org-menu" :title="auth.org?.name" @click="open = !open" @keydown="onButtonKey">
    <span class="org-avatar" aria-hidden="true">{{ initials(auth.org?.name || 'Company') }}</span>
    <span class="org-copy">
      <span class="org-kicker">Your company</span>
      <strong>{{ auth.org?.name || 'Select a company' }}</strong>
      <small v-if="subtitle">{{ subtitle }}</small>
    </span>
    <ChevronDown class="org-chevron" :size="18" aria-hidden="true"/>
  </button>
  <div v-else class="org-trigger is-static" :title="auth.org?.name">
    <span class="org-avatar" aria-hidden="true">{{ initials(auth.org?.name || 'Company') }}</span>
    <span class="org-copy">
      <span class="org-kicker">Your company</span>
      <strong>{{ auth.org?.name || 'Your company' }}</strong>
      <small v-if="subtitle">{{ subtitle }}</small>
    </span>
  </div>
  <ul v-if="many && open" id="org-menu" class="org-menu" role="listbox" aria-label="Your companies" @keydown="onMenuKey">
    <li v-for="(item, index) in auth.memberships" :key="item.organization_id">
      <button ref="optionEls" type="button" role="option" :aria-selected="item.organization_id === auth.orgId" :class="{ selected: item.organization_id === auth.orgId }" @click="choose(item.organization_id)" @focus="focusAt = index">
        <span class="org-avatar" aria-hidden="true">{{ initials(item.organization?.name || 'Company') }}</span>
        <span class="org-copy">
          <strong>{{ item.organization?.name }}</strong>
          <small>{{ roleName(item.role) }}<template v-if="item.organization?.plan?.name"> · {{ item.organization.plan.name }}</template></small>
        </span>
        <Check v-if="item.organization_id === auth.orgId" :size="16" aria-hidden="true"/>
      </button>
    </li>
  </ul>
</div>
</template>
