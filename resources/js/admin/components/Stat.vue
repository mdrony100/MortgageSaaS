<script setup lang="ts">defineProps<{label:string,value:string|number,hint?:string,to?:string}>()</script>
<template><component :is="to?'RouterLink':'div'" :to="to" class="c-stat"><span class="c-stat-label">{{label}}</span><strong class="c-stat-value">{{value}}</strong><span v-if="hint" class="c-stat-hint">{{hint}}</span></component></template>
