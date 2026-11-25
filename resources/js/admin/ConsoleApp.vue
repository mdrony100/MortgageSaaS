<script setup lang="ts">
import {ref,onMounted,onBeforeUnmount,watch,provide} from 'vue';import {useRoute} from 'vue-router';
import {LayoutDashboard,BarChart3,Wallet,ClipboardCheck,Building2,Users,Layers,CreditCard,Inbox,MessagesSquare,LifeBuoy,FileDown,ScrollText,SlidersHorizontal,Activity,Menu,X,LogOut,ShieldAlert} from 'lucide-vue-next';
import {api} from './util';
const route=useRoute(),open=ref(false),me=ref<any>(null),error=ref(''),brandIcon='/images/brand/non-qm-doc-icon.png';
provide('consoleMe',me);
const nav=[
 {to:'/',label:'Overview',icon:LayoutDashboard},
 {to:'/analytics',label:'Analytics',icon:BarChart3},
 {to:'/revenue',label:'Revenue',icon:Wallet},
 {to:'/approvals',label:'Approvals',icon:ClipboardCheck,badge:'pending_approvals'},
 {to:'/organizations',label:'Organizations',icon:Building2},
 {to:'/users',label:'Users',icon:Users},
 {to:'/plans',label:'Plans & billing',icon:Layers},
 {to:'/payment-requests',label:'Payment requests',icon:CreditCard,badge:'pending_payment_requests'},
 {to:'/inquiries',label:'Website inquiries',icon:Inbox,badge:'new_inquiries'},
 {to:'/demo-requests',label:'Demo requests',icon:MessagesSquare,badge:'new_demo_requests'},
 {to:'/support-tickets',label:'Support tickets',icon:LifeBuoy,badge:'open_tickets'},
 {to:'/reports',label:'Reports',icon:FileDown},
 {to:'/audit',label:'Audit log',icon:ScrollText},
 {to:'/settings',label:'Settings',icon:SlidersHorizontal},
 {to:'/system',label:'System status',icon:Activity},
] as const;
async function load(){try{me.value=await api('/api/admin/me')}catch(e:any){error.value=e.message}}
function active(to:string){return to==='/'?route.path==='/':route.path.startsWith(to)}
async function signOut(){await api('/api/auth/logout','POST').catch(()=>null);location.href='/admin/login'}
watch(()=>route.fullPath,()=>{open.value=false;load()});
function onKey(e:KeyboardEvent){if(e.key==='Escape'&&open.value){open.value=false;(document.querySelector('.c-menu') as HTMLElement|null)?.focus()}}
onMounted(()=>{load();document.addEventListener('keydown',onKey)});onBeforeUnmount(()=>document.removeEventListener('keydown',onKey));
</script>
<template><div class="console">
<header class="c-top"><button class="c-icon-btn c-menu" :aria-expanded="open" aria-controls="console-nav" :aria-label="open?'Close navigation':'Open navigation'" @click="open=!open"><X v-if="open" :size="22"/><Menu v-else :size="22"/></button>
<RouterLink to="/" class="c-brand"><img class="c-brand-logo" :src="brandIcon" alt="Non-QM Doc AI" width="38" height="38"><span><strong>Non-QM Doc AI</strong><small>Owner Console</small></span></RouterLink>
<div class="c-top-right"><span v-if="me" class="c-user" :title="me.user.email"><span class="c-avatar" aria-hidden="true">{{me.user.name.slice(0,1)}}</span><span class="c-user-name">{{me.user.name}}</span></span><button class="c-icon-btn" aria-label="Sign out" @click="signOut"><LogOut :size="19"/></button></div></header>
<div class="c-body">
<nav id="console-nav" class="c-nav" :class="{open}" aria-label="Owner console"><RouterLink v-for="n in nav" :key="n.to" :to="n.to" :class="{active:active(n.to)}" :aria-current="active(n.to)?'page':undefined"><component :is="n.icon" :size="19" aria-hidden="true"/><span>{{n.label}}</span><span v-if="'badge' in n && me?.[n.badge]" class="c-nav-count" :aria-label="me[n.badge]+' waiting'">{{me[n.badge]}}</span></RouterLink>
</nav>
<div v-if="open" class="c-scrim" @click="open=false"></div>
<main id="console-main" class="c-main" tabindex="-1">
<p v-if="error" class="c-alert error" role="alert">{{error}}</p>
<RouterView :key="route.path"/>
<aside v-if="me" class="c-privacy" aria-label="Privacy notice"><ShieldAlert :size="18" aria-hidden="true"/><p>{{me.privacy_notice}}</p></aside>
</main></div></div></template>
