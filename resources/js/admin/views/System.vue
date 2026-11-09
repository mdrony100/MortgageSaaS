<script setup lang="ts">
import {ref,onMounted,computed} from 'vue';import {api} from '../util';
const d=ref<any>(null),error=ref('');
const groups=computed(()=>{const g:Record<string,any[]>={};for(const i of d.value?.items||[])(g[i.group]??=[]).push(i);return g});
onMounted(async()=>{try{d.value=await api('/api/admin/system')}catch(e:any){error.value=e.message}});
</script>
<template><section>
<div class="c-head"><div><span class="c-eyebrow">Owner console</span><h1>System status</h1><p class="c-lead">Configuration presence only. Secret values are never shown.</p></div></div>
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<div v-if="d" class="c-grid-2"><div v-for="(items,name) in groups" :key="name" class="c-card"><h2>{{name}}</h2><dl class="c-kv"><div v-for="i in items" :key="i.label" :class="{warn:i.warn}"><dt>{{i.label}}</dt><dd><a v-if="i.link" :href="i.link">{{i.value}}</a><template v-else>{{i.value}}</template><span v-if="i.warn" class="c-badge tone-amber">Check</span></dd></div></dl></div></div>
<p v-else-if="!error" class="c-loading" role="status">Loading…</p>
</section></template>
