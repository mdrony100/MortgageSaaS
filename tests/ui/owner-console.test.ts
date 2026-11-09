import {describe,it,expect,vi,beforeEach} from 'vitest';
import {mount,flushPromises} from '@vue/test-utils';
import {createRouter,createMemoryHistory} from 'vue-router';
import {setActivePinia,createPinia} from 'pinia';
import ConsoleApp from '../../resources/js/admin/ConsoleApp.vue';
import Users from '../../resources/js/admin/views/Users.vue';
import Revenue from '../../resources/js/admin/views/Revenue.vue';
import BarChart from '../../resources/js/admin/components/BarChart.vue';
import App from '../../resources/js/App.vue';
import {useAuth} from '../../resources/js/stores/auth';
import {api} from '../../resources/js/api';
vi.mock('../../resources/js/api',()=>({api:vi.fn(),csrf:vi.fn()}));
const mocked=vi.mocked(api);
const stub={template:'<div>page</div>'};
function router(paths:string[]){return createRouter({history:createMemoryHistory(),routes:paths.map(p=>({path:p,component:stub}))})}
const me={user:{id:1,name:'Fictional Platform Admin',email:'admin@example.test',two_factor:true},privacy_notice:'The owner console shows account and operational metadata only.',impersonation:{enabled:true,allowed:true,minutes:30},pending_approvals:2,new_inquiries:0};
beforeEach(()=>{mocked.mockReset()});
describe('owner console layout',()=>{
 it('has its own branded shell, console navigation, workspace link and no customer workspace switcher',async()=>{mocked.mockResolvedValue(me);const r=router(['/','/analytics','/revenue','/approvals','/organizations','/users','/plans','/inquiries','/reports','/audit','/system']);r.push('/organizations');await r.isReady();const w=mount(ConsoleApp,{global:{plugins:[r]}});await flushPromises();
  expect(w.text()).toContain('MortgageLens Platform');expect(w.text()).toContain('Owner Console');
  const labels=w.findAll('nav[aria-label="Owner console"] a').map(a=>a.text());
  for(const l of ['Overview','Analytics','Revenue','Approvals','Organizations','Users','Plans & billing','Website inquiries','Reports','Audit log','System status'])expect(labels.some(x=>x.startsWith(l))).toBe(true);
  expect(w.find('a.active').attributes('aria-current')).toBe('page');expect(w.find('a.active').text()).toContain('Organizations');
  expect(w.find('nav a[href="/approvals"]').text()).toContain('2');
  expect(w.findAll('a[href="/dashboard"]').length).toBeGreaterThan(0);expect(w.text()).toContain('Open customer workspace');
  expect(w.find('select#org').exists()).toBe(false);expect(w.text()).not.toContain('Workspace');
  expect(w.find('[aria-label="Privacy notice"]').text()).toContain('operational metadata only');
 });
 it('mobile menu button toggles the navigation with aria-expanded',async()=>{mocked.mockResolvedValue(me);const r=router(['/']);r.push('/');await r.isReady();const w=mount(ConsoleApp,{global:{plugins:[r]}});await flushPromises();const b=w.find('button.c-menu');expect(b.attributes('aria-expanded')).toBe('false');await b.trigger('click');expect(b.attributes('aria-expanded')).toBe('true');expect(w.find('#console-nav').classes()).toContain('open');});
});
describe('owner console views',()=>{
 const user={id:5,name:'Fictional Customer',email:'c@example.test',verified:true,two_factor:false,platform_admin:false,disabled_at:null,last_login_at:null,created_at:'2026-11-01T00:00:00Z',organizations:[{id:3,name:'Fictional Lending',role:'owner'}]};
 it('requires a reason before starting a support session and posts it',async()=>{mocked.mockResolvedValueOnce({users:{data:[user],current_page:1,last_page:1,total:1},current_user_id:1,impersonation:{enabled:true,allowed:true,minutes:30}}).mockResolvedValueOnce({sessions:[]});const r=router(['/users']);r.push('/users');await r.isReady();const w=mount(Users,{global:{plugins:[r]},attachTo:document.body});await flushPromises();
  await w.findAll('button').find(b=>b.text().includes('View as customer'))!.trigger('click');expect(w.text()).toContain('Start a support session as Fictional Customer');
  await w.find('.c-support form').trigger('submit');await flushPromises();expect(w.find('[role=alert]').text()).toContain('at least 10 characters');expect(mocked).not.toHaveBeenCalledWith('/api/admin/impersonation','POST',expect.anything());
  mocked.mockResolvedValueOnce({redirect:'/dashboard'});await w.find('#support-reason').setValue('Customer asked for help with an upload');await w.find('.c-support form').trigger('submit');await flushPromises();expect(mocked).toHaveBeenCalledWith('/api/admin/impersonation','POST',{user_id:5,reason:'Customer asked for help with an upload'});w.unmount()});
 it('disables the disable-sign-in button for the current admin and platform admins',async()=>{mocked.mockResolvedValueOnce({users:{data:[{...user,id:1,platform_admin:true}],current_page:1,last_page:1,total:1},current_user_id:1,impersonation:{enabled:true,allowed:true,minutes:30}}).mockResolvedValueOnce({sessions:[]});const r=router(['/users']);r.push('/users');await r.isReady();const w=mount(Users,{global:{plugins:[r]}});await flushPromises();expect(w.findAll('button').find(b=>b.text()==='Disable sign-in')!.attributes('disabled')).toBeDefined();expect(w.findAll('button').some(b=>b.text().includes('View as customer'))).toBe(false)});
 it('revenue shows Pricing not configured instead of $0 and labels test data',async()=>{mocked.mockResolvedValueOnce({billing:{provider:'fake',notice:'Test provider only.',live_provider_configured:false},pricing_configured:false,mrr:null,arr:null,active_subscriptions:0,message:'Pricing not configured. Revenue cannot be calculated.',subscriptions:[],events:[],test_data:true,invoices_notice:'Invoices are not stored.'});const w=mount(Revenue,{global:{stubs:{RouterLink:true}}});await flushPromises();expect(w.text()).toContain('Pricing not configured');expect(w.text()).not.toContain('$0');expect(w.text()).toContain('Test provider data')});
 it('charts show an empty state instead of fake bars when there is no data',()=>{const w=mount(BarChart,{props:{title:'Signups',data:[{label:'2026-11-01',count:0}]}});expect(w.text()).toContain('No data recorded');expect(w.find('svg').exists()).toBe(false);const w2=mount(BarChart,{props:{title:'Signups',data:[{label:'a',count:2},{label:'b',count:1}]}});expect(w2.findAll('rect')).toHaveLength(2);expect(w2.find('svg').attributes('aria-label')).toContain('a 2')});
});
describe('customer workspace shell',()=>{
 async function mountApp(state:any){setActivePinia(createPinia());const auth=useAuth();Object.assign(auth,state);const r=router(['/dashboard']);r.push('/dashboard');await r.isReady();return mount(App,{global:{plugins:[r]}})}
 const membership={organization_id:3,role:'owner',organization:{id:3,name:'Fictional Lending',approval_status:'approved',suspended_at:null}};
 it('no longer shows platform administration or website inquiries; platform admins get a small Owner console link',async()=>{const w=await mountApp({user:{id:1,name:'Admin',email_verified_at:'x',platform_admin:true},memberships:[membership],orgId:3});expect(w.text()).not.toContain('Platform administration');expect(w.text()).not.toContain('Website inquiries');expect(w.find('a.owner-link').attributes('href')).toBe('/admin');expect(w.find('a.owner-link').text()).toBe('Owner console');
  const c=await mountApp({user:{id:2,name:'Customer',email_verified_at:'x',platform_admin:false},memberships:[membership],orgId:3});expect(c.find('a.owner-link').exists()).toBe(false)});
 it('shows an unmissable support-session banner with a return button and hides the console link',async()=>{const w=await mountApp({user:{id:2,name:'Fictional Customer',email_verified_at:'x',platform_admin:false},memberships:[membership],orgId:3,impersonation:{admin_name:'Fictional Platform Admin',expires_at:'2026-11-09T12:30:00Z'}});const b=w.find('[aria-label="Owner support session"]');expect(b.text()).toContain('Viewing as Fictional Customer');expect(b.text()).toContain('Owner support session by Fictional Platform Admin');mocked.mockResolvedValueOnce({redirect:'/admin/users'});await b.find('button').trigger('click');expect(mocked).toHaveBeenCalledWith('/api/impersonation/stop','POST')});
 it('shows a clear blocked screen for suspended or pending organizations',async()=>{const w=await mountApp({user:{id:2,name:'Customer',email_verified_at:'x',platform_admin:false},memberships:[{...membership,organization:{...membership.organization,suspended_at:'2026-11-09'}}],orgId:3});expect(w.find('.blocked-org').text()).toContain('Organization suspended');expect(w.text()).not.toContain('page');
  const p=await mountApp({user:{id:2,name:'Customer',email_verified_at:'x',platform_admin:false},memberships:[{...membership,organization:{...membership.organization,approval_status:'pending'}}],orgId:3});expect(p.find('.blocked-org').text()).toContain('Waiting for approval')});
});
