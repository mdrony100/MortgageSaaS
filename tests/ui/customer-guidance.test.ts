import {describe,it,expect,vi,beforeEach} from 'vitest';
import {mount,flushPromises} from '@vue/test-utils';
import {createPinia,setActivePinia} from 'pinia';
import Dashboard from '../../resources/js/views/Dashboard.vue';
import FileSteps from '../../resources/js/components/FileSteps.vue';
import StatusBadge from '../../resources/js/components/StatusBadge.vue';
import {useAuth} from '../../resources/js/stores/auth';
import {api} from '../../resources/js/api';
import {explainStatus,fileSteps,journeyFor} from '../../resources/js/customerLanguage';

vi.mock('vue-router',()=>({useRouter:()=>({push:vi.fn()})}));
vi.mock('../../resources/js/api',()=>({api:vi.fn()}));

describe('customer-facing language',()=>{
  it('explains unscanned files and a failed processing run',()=>{
    expect(explainStatus('Unscanned').label).toBe('Not virus-scanned');
    expect(explainStatus('Unscanned').help).toMatch(/not certified clean/i);
    expect(explainStatus('Failed').label).toBe('Processing failed');
    expect(explainStatus('Failed').help).toMatch(/unlocked PDF/);
    expect(fileSteps.map(step=>step.label)).toEqual(['Upload','Processing','Review findings','Export report']);
    const ready=journeyFor({documents:2,runStatus:null,fileStatus:'draft'});
    expect(ready.phase).toBe('start');
    expect(ready.step).toBe(1);
    expect(ready.settled).toBe(true);
    expect(ready.badge.label).toBe('Ready to start');
    expect(journeyFor({documents:2,runStatus:'Extracting'}).phase).toBe('processing');
    expect(journeyFor({documents:2,runStatus:'NeedsReview'}).step).toBe(3);
    expect(journeyFor({documents:0}).title).toBe('Upload bank statements');
  });
  it('names the four loan-file steps and marks the current one',()=>{
    const w=mount(FileSteps,{props:{current:1}});
    expect(w.text()).toContain('Upload bank statements');
    expect(w.find('[aria-current="step"]').text()).toContain('Upload');
    expect(w.text()).toContain('Export report');
  });
  it('shows the unscanned explanation next to the badge',()=>{
    const w=mount(StatusBadge,{props:{code:'Unscanned'}});
    expect(w.text()).toContain('Not virus-scanned');
    expect(w.text()).toContain('not certified clean');
  });
});

describe('empty loan-file list',()=>{
  beforeEach(()=>{
    setActivePinia(createPinia());
    const auth=useAuth();
    auth.user={id:1,name:'Avery Sample',email:'avery@example.test',email_verified_at:'2026-01-01'} as any;
    auth.orgId=1;
    auth.memberships=[{organization_id:1,role:'processor',organization:{id:1,name:'Sample Co',plan_id:1}}] as any;
    vi.mocked(api).mockImplementation(async(url:string)=>url.includes('/files')?{data:[]}:[]);
  });
  it('tells a new customer to create a loan file and then upload statements',async()=>{
    const w=mount(Dashboard,{global:{directives:{'focus-trap':{}},stubs:{RouterLink:{template:'<a><slot/></a>'}}}});
    await flushPromises();
    expect(w.text()).toContain('Create your first loan file');
    expect(w.text()).toContain('Upload bank statements');
    expect(w.text()).toContain('does not approve or decline');
  });
  it('tells the customer which file to open next',async()=>{
    vi.mocked(api).mockImplementation(async(url:string)=>url.includes('/files')?{data:[{id:4,name:'Sample Street',status:'draft',module:'bank',is_demo:false,archived_at:null,borrower:{name:'Ada Sample'},progress:{documents:2,run_status:null,has_report:false}}]}:[]);
    const w=mount(Dashboard,{global:{directives:{'focus-trap':{}},stubs:{RouterLink:{template:'<a><slot/></a>'}}}});
    await flushPromises();
    expect(w.text()).toContain('Do this next');
    expect(w.text()).toContain('Start analysis');
    expect(w.text()).toContain('Sample Street');
    expect(w.text()).toContain('Ready to start');
  });
});
