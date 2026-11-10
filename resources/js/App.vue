<script setup lang="ts">
import {ref,computed,watch,nextTick,onBeforeUnmount} from 'vue';import {useAuth} from './stores/auth';import {useRoute} from 'vue-router';import {api} from './api';import {roleName} from './customerLanguage';import {LayoutDashboard,Settings,BookOpen,LifeBuoy,LogOut,Menu,Building2,UserRound,Landmark,ShieldCheck,Eye} from 'lucide-vue-next';
const auth=useAuth(),route=useRoute(),open=ref(false),ending=ref(false);const nav=[['/dashboard','Loan files',LayoutDashboard],['/guidelines','Guidelines',BookOpen],['/settings/organization','Team',Building2],['/settings/profile','My account',UserRound],['/settings/plans','Plan',Settings],['/help','Help',LifeBuoy]] as const;const authOnly=computed(()=>!auth.user||['/verify-email','/two-factor-challenge'].includes(route.path));
const blocked=computed(()=>{const o=auth.org;if(!o)return null;if(o.approval_status==='pending')return {title:'Waiting for approval',text:'Your organization has been created and is waiting for approval by MortgageLens. We will email you as soon as it is approved.'};if(o.approval_status==='rejected')return {title:'Organization not approved',text:'This organization was not approved. Contact MortgageLens support for details.'};if(o.suspended_at)return {title:'Organization suspended',text:'This organization is suspended, so its files and reviews are unavailable. Contact MortgageLens support to restore access.'};return null});
const until=computed(()=>auth.impersonation?new Date(auth.impersonation.expires_at).toLocaleTimeString([], {hour:'numeric',minute:'2-digit'}):'');
const crumb=computed(()=>{const p=route.path;if(p.startsWith('/files/'))return 'This loan file';if(p.startsWith('/settings/organization'))return 'Team';if(p.startsWith('/settings/profile'))return 'My account';if(p.startsWith('/settings/plans'))return 'Plan';if(p.startsWith('/guidelines'))return 'Guidelines';if(p.startsWith('/help'))return 'Help';return ''});
async function endSupport(){ending.value=true;try{const r=await api('/api/impersonation/stop','POST');location.href=r.redirect||'/admin/users'}catch{location.href='/admin/users'}}
const bannerEl=ref<HTMLElement|null>(null);const bannerH=ref(0);let ro:ResizeObserver|null=null;
watch(bannerEl,el=>{ro?.disconnect();if(el&&typeof ResizeObserver!=='undefined'){ro=new ResizeObserver(()=>{bannerH.value=Math.ceil(el.getBoundingClientRect().height)});ro.observe(el);bannerH.value=Math.ceil(el.getBoundingClientRect().height)}});
onBeforeUnmount(()=>ro?.disconnect());
watch(()=>route.path,async()=>{open.value=false;await nextTick()});
</script>
<template>
<div v-if="auth.impersonation" ref="bannerEl" class="support-banner" role="region" aria-label="Owner support session"><Eye :size="20" aria-hidden="true"/><strong>Viewing as {{auth.user?.name}}</strong><span>Owner support session by {{auth.impersonation.admin_name}} · ends {{until}} · password, billing, role and download actions are blocked</span><button @click="endSupport" :disabled="ending">{{ending?'Returning…':'Return to owner console'}}</button></div>
<div v-if="authOnly" class="auth-shell"><RouterView/></div>
<div v-else class="app-shell" :class="{'with-support':auth.impersonation}" :style="{'--accent':auth.org?.branding?.accent||'#0f766e',...(bannerH?{'--support-h':bannerH+'px'}:{})}">
<aside :class="{open}">
<RouterLink class="brand" to="/dashboard"><span class="brand-mark"><Landmark :size="23"/></span>{{auth.org?.branding?.product_name||'MortgageLens'}}</RouterLink>
<div class="workspace-switch"><label for="org">Your company</label><select id="org" :value="auth.orgId" @change="auth.selectOrg(Number(($event.target as HTMLSelectElement).value));$router.push('/dashboard');open=false"><option v-for="m in auth.memberships" :value="m.organization_id">{{m.organization.name}}</option></select></div>
<nav id="app-nav" aria-label="Main navigation"><RouterLink v-for="[url,label,icon] in nav" :key="url" :to="url" @click="open=false"><component :is="icon" :size="19"/>{{label}}</RouterLink></nav>
<a v-if="auth.user.platform_admin&&!auth.impersonation" class="owner-link" href="/admin"><ShieldCheck :size="16" aria-hidden="true"/>Owner console</a>
<div class="sidebar-foot"><span class="status-dot"></span>A person makes the decision<p>The tool only flags potential issues. Practice files are labeled.</p></div>
</aside>
<div class="main-shell">
<header>
<button class="icon-btn mobile-menu" aria-label="Open menu" :aria-expanded="open" aria-controls="app-nav" @click="open=!open"><Menu/></button>
<nav class="breadcrumb" aria-label="Current location"><RouterLink to="/dashboard">Loan files</RouterLink><template v-if="crumb"><span aria-hidden="true">/</span><span aria-current="page">{{crumb}}</span></template></nav>
<div class="header-user"><span class="avatar" aria-hidden="true">{{auth.user.name.slice(0,1)}}</span><div>{{auth.user.name}}<small>{{roleName(auth.membership?.role)}}</small></div><button class="icon-btn" @click="auth.logout()" aria-label="Sign out"><LogOut :size="18"/></button></div>
</header>
<main id="main"><section v-if="blocked&&!route.path.startsWith('/settings/profile')" class="blocked-org card" role="alert"><ShieldCheck :size="40" aria-hidden="true"/><h1>{{blocked.title}}</h1><p>{{blocked.text}}</p><p v-if="auth.memberships.length>1" class="muted">You can switch to another company from the menu.</p></section><RouterView v-else :key="route.path+'-'+auth.orgId"/></main>
<footer>MortgageLens <span>A person reviews every file. This is not lending advice.</span></footer>
</div>
</div>
</template>
