<script setup lang="ts">import {statusTone} from '../util';defineProps<{value:string,label?:string}>()</script>
<template><span class="c-badge" :class="'tone-'+(statusTone[value]||'slate')">{{label||value.replace('_',' ')}}</span></template>
