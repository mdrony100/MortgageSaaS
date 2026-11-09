<script setup lang="ts">
import {computed} from 'vue';
const props=defineProps<{title:string,data:{label:string,count:number,display?:string}[]}>();
const max=computed(()=>Math.max(1,...props.data.map(d=>d.count)));
const total=computed(()=>props.data.reduce((a,d)=>a+d.count,0));
</script>
<template><figure class="c-chart"><figcaption>{{title}}</figcaption><p v-if="!total" class="c-empty">No data recorded yet.</p>
<ul v-else class="c-hbars"><li v-for="d in data" :key="d.label"><span class="c-hbar-label">{{d.label}}</span><span class="c-hbar-track" aria-hidden="true"><span class="c-hbar-fill" :style="{width:(d.count/max*100)+'%'}"></span></span><span class="c-hbar-value">{{d.display??d.count}}</span></li></ul></figure></template>
