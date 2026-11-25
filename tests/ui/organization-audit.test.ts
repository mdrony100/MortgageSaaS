import {reactive} from 'vue';
import {describe,it,expect,vi,beforeEach} from 'vitest';
import {mount,flushPromises} from '@vue/test-utils';
import {createPinia,setActivePinia} from 'pinia';
import SettingsView from '../../resources/js/views/SettingsView.vue';
import {useAuth} from '../../resources/js/stores/auth';
import {api} from '../../resources/js/api';

let testRoute:any;
vi.mock('vue-router',()=>({useRoute:()=>testRoute,useRouter:()=>({push:vi.fn()})}));
vi.mock('../../resources/js/api',()=>({api:vi.fn(),csrf:vi.fn()}));
const mocked=vi.mocked(api);

const orgPayload={organization:{name:'SovtVence',branding:{product_name:'Non-QM Doc AI',accent:'#0e7c71',logo_text:'NQ'},preferences:{timezone:'America/New_York',currency:'USD'}},members:[{id:2,user_id:2,role:'owner',org_wide_access:true,user:{id:2,name:'Md Rony',email:'demo.customer@qa.nonqmdoc.test'}}],invitations:[]};

function page(p:number,total:number,per=15){
  const last=Math.max(1,Math.ceil(total/per)||1);
  const start=(p-1)*per;
  const count=Math.max(0,Math.min(per,total-start));
  const data=Array.from({length:count},(_,i)=>{
    const n=start+i;
    return {id:total-n,loan_file_id:n%2?4:null,actor_id:2,action:n===0?'document.previewed':'organization.updated',created_at:'2026-11-25T05:13:19Z'};
  });
  return {current_page:p,data,from:count?start+1:null,to:count?start+count:null,total,last_page:total?last:1,per_page:per};
}

function install(total:number){
  mocked.mockImplementation(async(url:string)=>{
    if(url==='/api/organizations/1')return orgPayload;
    const match=String(url).match(/\/audit\?page=(\d+)/);
    if(match)return page(Number(match[1]),total);
    if(url==='/api/me')return {user:{id:2,name:'Md Rony',email:'demo.customer@qa.nonqmdoc.test'},memberships:[{organization_id:1,role:'owner'}]};
    return {};
  });
}

function render(){return mount(SettingsView,{global:{stubs:{DataPanel:true,PagePlan:true}}})}

beforeEach(()=>{
  setActivePinia(createPinia());
  const auth=useAuth();
  auth.user={id:2,name:'Md Rony',email:'demo.customer@qa.nonqmdoc.test',two_factor_confirmed_at:null};
  auth.orgId=1;
  auth.memberships=[{organization_id:1,role:'owner',organization:{name:'SovtVence'}}];
  testRoute=reactive({path:'/settings/organization',params:{section:'organization'},query:{}});
});

describe('organization audit history pagination',()=>{
  it('pages 15 events at a time with previous, next, page numbers and a range',async()=>{
    install(32);
    const w=render();
    await flushPromises();
    expect(w.text()).toContain('Organization audit history');
    expect(w.findAll('.audit-row')).toHaveLength(15);
    expect(w.text()).toContain('document · previewed');
    expect(w.text()).toContain('Showing 1–15 of 32');
    expect(w.find('.settings-grid .audit-card').exists()).toBe(false);
    expect(w.find('article.audit-card').exists()).toBe(true);
    const labels=w.findAll('.audit-pages button').map(b=>b.text());
    expect(labels).toEqual(['Previous','1','2','3','Next']);
    expect(w.get('.audit-pages button.page-num.current').text()).toBe('1');
    expect(w.get('.audit-pages button.page-num.current').attributes('aria-current')).toBe('page');
    expect(w.findAll('.audit-pages button.step')[0].attributes('disabled')).toBeDefined();
    expect(w.text().toLowerCase()).not.toMatch(/show all|view all|load all/);

    await w.findAll('.audit-pages button').find(b=>b.text()==='2')!.trigger('click');
    await flushPromises();
    expect(mocked).toHaveBeenCalledWith('/api/organizations/1/audit?page=2');
    expect(w.findAll('.audit-row')).toHaveLength(15);
    expect(w.text()).not.toContain('document · previewed');
    expect(w.text()).toContain('Showing 16–30 of 32');

    await w.findAll('.audit-pages button').find(b=>b.text()==='Next')!.trigger('click');
    await flushPromises();
    expect(mocked).toHaveBeenCalledWith('/api/organizations/1/audit?page=3');
    expect(w.findAll('.audit-row')).toHaveLength(2);
    expect(w.text()).toContain('Showing 31–32 of 32');
    expect(w.findAll('.audit-pages button.step')[1].attributes('disabled')).toBeDefined();
  });

  it('collapses distant page numbers and never offers an all-events control',async()=>{
    install(120);
    const w=render();
    await flushPromises();
    const labels=w.findAll('.audit-pages button').map(b=>b.text());
    expect(labels).toContain('1');
    expect(labels).toContain('2');
    expect(labels).toContain('8');
    expect(labels).not.toContain('5');
    expect(w.find('.audit-ellipsis').exists()).toBe(true);
    const names=w.findAll('button').map(b=>b.text().toLowerCase());
    for(const label of ['show all','view all','load all'])expect(names).not.toContain(label);
  });

  it('shows an empty history without pager controls',async()=>{
    install(0);
    const w=render();
    await flushPromises();
    expect(w.text()).toContain('No audit events yet.');
    expect(w.find('nav[aria-label="Audit history pages"]').exists()).toBe(false);
    expect(w.findAll('.audit-row')).toHaveLength(0);
  });

  it('hides the history from roles that cannot administer the organization',async()=>{
    install(32);
    useAuth().memberships=[{organization_id:1,role:'broker',organization:{name:'SovtVence'}}];
    const w=render();
    await flushPromises();
    expect(w.text()).not.toContain('Organization audit history');
    expect(mocked.mock.calls.some(call=>String(call[0]).includes('/audit'))).toBe(false);
  });
});
