<script setup lang="ts">
import {computed} from 'vue';
// Accessible SVG bar chart for real counts. Shows an empty state instead of zero-height bars.
const props=defineProps<{title:string,data:{label:string,count:number}[],unit?:string}>();
const max=computed(()=>Math.max(1,...props.data.map(d=>d.count)));
const total=computed(()=>props.data.reduce((a,d)=>a+d.count,0));
const w=computed(()=>Math.max(props.data.length,1));
</script>
<template><figure class="c-chart"><figcaption>{{title}}<span class="c-chart-total">{{total}} {{unit||'total'}}</span></figcaption>
<p v-if="!total" class="c-empty">No data recorded in this range yet.</p>
<template v-else><svg :viewBox="`0 0 ${w*20} 100`" preserveAspectRatio="none" role="img" :aria-label="title+': '+data.map(d=>d.label+' '+d.count).join(', ')" class="c-chart-svg"><rect v-for="(d,i) in data" :key="d.label" :x="i*20+3" :width="14" :y="100-(d.count/max)*96" :height="(d.count/max)*96" rx="2" class="c-bar"><title>{{d.label}}: {{d.count}}</title></rect></svg>
<div class="c-chart-axis"><span>{{data[0]?.label}}</span><span>{{data[data.length-1]?.label}}</span></div>
<details class="c-chart-table"><summary>Show data table</summary><table><thead><tr><th scope="col">Period</th><th scope="col">Count</th></tr></thead><tbody><tr v-for="d in data" :key="d.label"><td>{{d.label}}</td><td>{{d.count}}</td></tr></tbody></table></details></template>
</figure></template>
