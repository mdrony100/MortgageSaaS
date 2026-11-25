import {describe,it,expect,vi,beforeEach} from 'vitest';
import {mount,flushPromises} from '@vue/test-utils';
import {createPinia,setActivePinia} from 'pinia';
import Dashboard from '../../resources/js/views/Dashboard.vue';
import CreateFile from '../../resources/js/views/CreateFile.vue';
import FileSteps from '../../resources/js/components/FileSteps.vue';
import StatusBadge from '../../resources/js/components/StatusBadge.vue';
import {useAuth} from '../../resources/js/stores/auth';
import {api} from '../../resources/js/api';
import {explainStatus,failureGuide,fileSteps,guideFor,plainFailure} from '../../resources/js/customerLanguage';

vi.mock('vue-router',()=>({useRouter:()=>({push:vi.fn()})}));
vi.mock('../../resources/js/api',()=>({api:vi.fn()}));

describe('customer-facing language',()=>{
  it('explains unscanned files and a failed processing run',()=>{
    expect(explainStatus('Unscanned').label).toBe('Not virus-scanned');
    expect(explainStatus('Unscanned').help).toMatch(/not certified clean/i);
    expect(explainStatus('Failed').label).toBe('Processing failed');
    expect(explainStatus('Failed').help).toMatch(/unlocked PDF/);
    expect(fileSteps.map(step=>step.label)).toEqual(['Documents & details','Process','Review & export']);
  });
  it('names the three loan-file steps and marks the current one',()=>{
    const w=mount(FileSteps,{props:{current:1}});
    expect(w.text()).toContain('Upload bank statements');
    expect(w.find('[aria-current="step"]').text()).toContain('Documents');
    expect(w.text()).toContain('Review & export');
  });
  it('keeps the step and the badge on the same moment',()=>{
    const ready=guideFor({documents:2,runStatus:null,canReview:true});
    expect(ready.step).toBe(1);
    expect(ready.badge).toBe('Loan details');
    expect(ready.waiting).toBe(false);
    expect(ready.cta).toBe('details');
    const reading=guideFor({documents:2,runStatus:'Extracting'});
    expect(reading.step).toBe(2);
    expect(reading.badge).toBe('Reading the document');
    expect(reading.waiting).toBe(true);
    const review=guideFor({documents:2,runStatus:'NeedsReview'});
    expect(review.step).toBe(3);
    expect(review.cta).toBe('review');
    const failed=guideFor({documents:1,runStatus:'Failed',failure:'Malware scan Unavailable'});
    expect(failed.step).toBe(2);
    expect(failed.body).toMatch(/not certified clean/i);
    expect(failed.body).not.toMatch(/Malware scan/);
    expect(failed.action).toBe('Try again');
    expect(failed.cta).toBe('retry');
    expect(failed.support).toBe(true);
    expect(guideFor({documents:1,runStatus:'Failed',failure:'Approved processing budget unavailable'}).action).toBe('Try again');
    expect(guideFor({documents:0}).step).toBe(1);
    expect(guideFor({documents:1,runStatus:'Completed'}).cta).toBe('export');
    expect(guideFor({documents:1,runStatus:'Completed'}).step).toBe(3);
    expect(plainFailure('Approved processing budget unavailable')).toBe("Processing budget isn't set up or is used up — ask an administrator.");
    expect(plainFailure('Organization budget exhausted')).toBe("Processing budget isn't set up or is used up — ask an administrator.");
    expect(plainFailure('Approved processing budget unavailable')).not.toMatch(/virus|certified clean/i);
    expect(plainFailure('Service unavailable')).not.toMatch(/certified clean/i);
    expect(plainFailure('scanner unavailable')).toMatch(/not certified clean/i);
    for (const status of ['Queued','Validating','Extracting','Reconciling','Analyzing','NeedsReview','PartiallyCompleted','Completed','Failed','Cancelled','draft','in_review','complete','archived']) {
      const guide = guideFor({documents:1,runStatus:status,canReview:true,archived:status==='archived'});
      expect(guide.body.length).toBeGreaterThan(10);
      expect(guide.title.length).toBeGreaterThan(2);
    }
    expect(failureGuide('Malware scan Rejected').cta).toBe('replace');
    expect(failureGuide('Malware scan ScanFailed').cta).toBe('retry');
    expect(failureGuide(null).body).not.toMatch(/undefined/);
  });
  it('shows the unscanned explanation next to the badge',()=>{
    const w=mount(StatusBadge,{props:{code:'Unscanned'}});
    expect(w.text()).toContain('Not virus-scanned');
    expect(w.text()).toContain('not certified clean');
  });
});

describe('create loan file',()=>{
  beforeEach(()=>{
    setActivePinia(createPinia());
    const auth=useAuth();
    auth.user={id:1,name:'Avery Sample',email:'avery@example.test',email_verified_at:'2026-01-01'} as any;
    auth.orgId=1;
    auth.memberships=[{organization_id:1,role:'owner',organization:{id:1,name:'Sample Co'}}] as any;
    vi.mocked(api).mockResolvedValue([]);
  });
  it('does not offer a practice file and uses production placeholders',async()=>{
    const w=mount(CreateFile,{global:{stubs:{RouterLink:{template:'<a><slot/></a>'}}}});
    await flushPromises();
    expect(w.text()).not.toMatch(/practice file/i);
    expect(w.text()).not.toMatch(/fictional/i);
    expect(w.find('input[type="checkbox"]').exists()).toBe(false);
    expect(w.find('input[placeholder="e.g. 123 Main St refinance"]').exists()).toBe(true);
    expect(w.find('input[placeholder="Borrower full name"]').exists()).toBe(true);
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
});
