<script setup lang="ts">
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import { useAuth } from './stores/auth'
import { useRoute } from 'vue-router'
import { api } from './api'
import { roleName } from './customerLanguage'
import CompanySwitcher from './components/CompanySwitcher.vue'
import CreditWarning from './components/CreditWarning.vue'
import { LayoutDashboard, FolderOpen, BookOpen, Building2, UserRound, Settings, LifeBuoy, Ticket, LogOut, Menu, ShieldCheck, Eye } from 'lucide-vue-next'

const auth = useAuth(), route = useRoute(), open = ref(false), ending = ref(false)
const nav = [
  ['/dashboard', 'Dashboard', LayoutDashboard],
  ['/files', 'Loan files', FolderOpen],
  ['/guidelines', 'Guidelines', BookOpen],
  ['/settings/organization', 'Team', Building2],
  ['/settings/profile', 'My account', UserRound],
  ['/settings/plans', 'Plan', Settings],
  ['/support', 'Support tickets', Ticket],
  ['/help', 'Help', LifeBuoy],
] as const
const authOnly = computed(() => !auth.user || ['/verify-email', '/two-factor-challenge'].includes(route.path))
const brandIcon = '/images/brand/non-qm-doc-icon.png'
const logoOk = ref(true)
function onAccent(hex: string) { const h = (hex || '#22c6e6').replace('#', ''); const r = parseInt(h.slice(0, 2), 16) || 0, g = parseInt(h.slice(2, 4), 16) || 0, b = parseInt(h.slice(4, 6), 16) || 0; const l = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255; return l > 0.62 ? '#102840' : '#ffffff' }
const blocked = computed(() => { const o = auth.org; if (!o) return null; if (o.approval_status === 'pending') return { title: 'Waiting for approval', text: 'Your organization has been created and is waiting for approval by Non-QM Doc AI. We will email you as soon as it is approved.' }; if (o.approval_status === 'rejected') return { title: 'Organization not approved', text: 'This organization was not approved. Contact Non-QM Doc AI support for details.' }; if (o.suspended_at) return { title: 'Organization suspended', text: 'This organization is suspended, so its files and reviews are unavailable. Contact Non-QM Doc AI support to restore access.' }; return null })
const until = computed(() => auth.impersonation ? new Date(auth.impersonation.expires_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '')
function navOn(url: string) {
  const path = route.path
  if (url === '/files') return path === '/files' || path.startsWith('/files/')
  if (url === '/support') return path.startsWith('/support')
  return path === url
}
const crumbs = computed(() => {
  const path = route.path
  if (path === '/dashboard') return [{ label: 'Dashboard' }]
  const base: { to?: string, label: string }[] = [{ to: '/dashboard', label: 'Dashboard' }]
  if (path === '/files') return [...base, { label: 'Loan files' }]
  if (path === '/files/create') return [...base, { to: '/files', label: 'Loan files' }, { label: 'New loan file' }]
  if (path.startsWith('/files/')) return [...base, { to: '/files', label: 'Loan files' }, { label: 'This loan file' }]
  if (path === '/support/new') return [...base, { to: '/support', label: 'Support tickets' }, { label: 'New ticket' }]
  if (path.startsWith('/support/')) return [...base, { to: '/support', label: 'Support tickets' }, { label: 'Ticket' }]
  if (path.startsWith('/support')) return [...base, { label: 'Support tickets' }]
  if (path.startsWith('/settings/organization')) return [...base, { label: 'Team' }]
  if (path.startsWith('/settings/profile')) return [...base, { label: 'My account' }]
  if (path.startsWith('/settings/plans')) return [...base, { label: 'Plan' }]
  if (path.startsWith('/guidelines')) return [...base, { label: 'Guidelines' }]
  if (path.startsWith('/help')) return [...base, { label: 'Help' }]
  return [{ label: 'Dashboard' }]
})
async function endSupport() { ending.value = true; try { const r = await api('/api/impersonation/stop', 'POST'); location.href = r.redirect || '/admin' } catch { location.href = '/admin' } }
const bannerEl = ref<HTMLElement | null>(null); const bannerH = ref(0); let ro: ResizeObserver | null = null
watch(bannerEl, el => { ro?.disconnect(); if (el && typeof ResizeObserver !== 'undefined') { ro = new ResizeObserver(() => { bannerH.value = Math.ceil(el.getBoundingClientRect().height) }); ro.observe(el); bannerH.value = Math.ceil(el.getBoundingClientRect().height) } })
onBeforeUnmount(() => ro?.disconnect())
watch(() => route.path, async () => { open.value = false; await nextTick() })
</script>
<template>
<div v-if="auth.impersonation" ref="bannerEl" class="support-banner" role="region" aria-label="Owner support session"><Eye :size="20" aria-hidden="true"/><strong>You are viewing as {{ auth.user?.name }}</strong><span>— session by {{ auth.impersonation.admin_name }} · ends {{ until }} · password, billing, role and download actions are blocked</span><button @click="endSupport" :disabled="ending">{{ ending ? 'Returning…' : 'Return to admin' }}</button></div>
<div v-if="authOnly" class="auth-shell"><RouterView/></div>
<div v-else class="app-shell" :class="{ 'with-support': auth.impersonation }" :style="{ '--accent': auth.org?.branding?.accent || '#22c6e6', '--on-accent': onAccent(auth.org?.branding?.accent || '#22c6e6'), ...(bannerH ? { '--support-h': bannerH + 'px' } : {}) }">
<aside :class="{ open }">
<RouterLink class="brand" to="/dashboard"><img v-if="logoOk" class="brand-logo" :src="brandIcon" alt="" width="40" height="40" @error="logoOk = false"><span v-else class="brand-mark" aria-hidden="true">NQ</span>{{ auth.org?.branding?.product_name || 'Non-QM Doc AI' }}</RouterLink>
<CompanySwitcher/>
<nav id="app-nav" aria-label="Main navigation"><RouterLink v-for="[url, label, icon] in nav" :key="url" :to="url" :class="{ 'is-active': navOn(url) }" @click="open = false"><component :is="icon" :size="22"/>{{ label }}</RouterLink></nav>
<div class="sidebar-foot"><span class="status-dot"></span>A person makes the decision<p>The tool only flags potential issues. Practice files are labeled.</p></div>
</aside>
<div class="main-shell">
<header>
<button class="icon-btn mobile-menu" aria-label="Open menu" :aria-expanded="open" aria-controls="app-nav" @click="open = !open"><Menu/></button>
<nav class="breadcrumb" aria-label="Current location"><template v-for="(crumb, index) in crumbs" :key="crumb.label + index"><span v-if="index" aria-hidden="true">/</span><RouterLink v-if="crumb.to" :to="crumb.to">{{ crumb.label }}</RouterLink><span v-else aria-current="page">{{ crumb.label }}</span></template></nav>
<div class="header-user"><a v-if="auth.user.platform_admin && !auth.impersonation" class="user-menu-admin" href="/admin">Admin</a><span class="avatar" aria-hidden="true">{{ auth.user.name.slice(0, 1) }}</span><div>{{ auth.user.name }}<small>{{ roleName(auth.membership?.role) }}</small></div><button class="icon-btn" @click="auth.logout()" aria-label="Sign out"><LogOut :size="18"/></button></div>
</header>
<main id="main"><CreditWarning v-if="route.path === '/dashboard' && !(blocked && !route.path.startsWith('/settings/profile'))"/><section v-if="blocked && !route.path.startsWith('/settings/profile')" class="blocked-org card" role="alert"><ShieldCheck :size="40" aria-hidden="true"/><h1>{{ blocked.title }}</h1><p>{{ blocked.text }}</p><p v-if="auth.memberships.length > 1" class="muted">You can switch to another company from the menu.</p></section><RouterView v-else :key="route.path + '-' + auth.orgId"/></main>
<footer>Non-QM Doc AI <span>A person reviews every file. This is not lending advice.</span></footer>
</div>
</div>
</template>
