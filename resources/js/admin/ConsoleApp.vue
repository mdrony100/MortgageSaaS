<script setup lang="ts">
import {ref,onMounted,onBeforeUnmount,watch,provide} from 'vue';import {useRoute} from 'vue-router';
import {LayoutDashboard,BarChart3,Wallet,ClipboardCheck,Building2,Users,Layers,Inbox,FileDown,ScrollText,SlidersHorizontal,Activity,Menu,X,LogOut,ExternalLink,ShieldAlert} from 'lucide-vue-next';
import {api} from './util';
const route=useRoute(),open=ref(false),me=ref<any>(null),error=ref('');
provide('consoleMe',me);
const nav=[
 {to:'/',label:'Overview',icon:LayoutDashboard},
 {to:'/analytics',label:'Analytics',icon:BarChart3},
 {to:'/revenue',label:'Revenue',icon:Wallet},
 {to:'/approvals',label:'Approvals',icon:ClipboardCheck,badge:'pending_approvals'},
 {to:'/organizations',label:'Organizations',icon:Building2},
 {to:'/users',label:'Users',icon:Users},
 {to:'/plans',label:'Plans & billing',icon:Layers},
 {to:'/inquiries',label:'Website inquiries',icon:Inbox,badge:'new_inquiries'},
 {to:'/reports',label:'Reports',icon:FileDown},
 {to:'/audit',label:'Audit log',icon:ScrollText},
 {to:'/settings',label:'Settings',icon:SlidersHorizontal},
 {to:'/system',label:'System status',icon:Activity},
] as const;
async function load(){try{me.value=await api('/api/admin/me')}catch(e:any){error.value=e.message}}
function active(to:string){return to==='/'?route.path==='/':route.path.startsWith(to)}
async function signOut(){await api('/api/auth/logout','POST').catch(()=>null);location.href='/login'}
watch(()=>route.fullPath,()=>{open.value=false;load()});
function onKey(e:KeyboardEvent){if(e.key==='Escape'&&open.value){open.value=false;(document.querySelector('.c-menu') as HTMLElement|null)?.focus()}}
onMounted(()=>{load();document.addEventListener('keydown',onKey)});onBeforeUnmount(()=>document.removeEventListener('keydown',onKey));
</script>
<template><div class="console">
<header class="c-top"><button class="c-icon-btn c-menu" :aria-expanded="open" aria-controls="console-nav" :aria-label="open?'Close navigation':'Open navigation'" @click="open=!open"><X v-if="open" :size="22"/><Menu v-else :size="22"/></button>
<RouterLink to="/" class="c-brand"><span class="c-brand-mark" aria-hidden="true">ML</span><span><strong>MortgageLens Platform</strong><small>Owner Console</small></span></RouterLink>
<div class="c-top-right"><a href="/dashboard" class="c-top-link"><ExternalLink :size="17"/><span>Open customer workspace</span></a><span v-if="me" class="c-user" :title="me.user.email"><span class="c-avatar" aria-hidden="true">{{me.user.name.slice(0,1)}}</span><span class="c-user-name">{{me.user.name}}</span></span><button class="c-icon-btn" aria-label="Sign out" @click="signOut"><LogOut :size="19"/></button></div></header>
<div class="c-body">
<nav id="console-nav" class="c-nav" :class="{open}" aria-label="Owner console"><RouterLink v-for="n in nav" :key="n.to" :to="n.to" :class="{active:active(n.to)}" :aria-current="active(n.to)?'page':undefined"><component :is="n.icon" :size="19" aria-hidden="true"/><span>{{n.label}}</span><span v-if="'badge' in n && me?.[n.badge]" class="c-nav-count" :aria-label="me[n.badge]+' waiting'">{{me[n.badge]}}</span></RouterLink>
<div class="c-nav-foot"><a href="/dashboard"><ExternalLink :size="17"/>Open customer workspace</a></div></nav>
<div v-if="open" class="c-scrim" @click="open=false"></div>
<main id="console-main" class="c-main" tabindex="-1">
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<RouterView :key="route.path"/>
<aside v-if="me" class="c-privacy" aria-label="Privacy notice"><ShieldAlert :size="18" aria-hidden="true"/><p>{{me.privacy_notice}}</p></aside>
</main></div></div></template>
