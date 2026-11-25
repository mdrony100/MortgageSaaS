import {describe,it,expect,vi,beforeEach} from 'vitest';
import {mount,flushPromises} from '@vue/test-utils';
import PagePlan from '../../resources/js/components/PagePlan.vue';
import PaymentRequests from '../../resources/js/admin/views/PaymentRequests.vue';
import Plans from '../../resources/js/admin/views/Plans.vue';
import DemoRequests from '../../resources/js/admin/views/DemoRequests.vue';
import {failureGuide} from '../../resources/js/customerLanguage';
import {api} from '../../resources/js/api';

vi.mock('../../resources/js/api',()=>({api:vi.fn(),csrf:vi.fn()}));
vi.mock('vue-router',()=>({useRoute:()=>({query:{}})}));
const mocked=vi.mocked(api);

const credits={
  plan:{id:1,name:'Starter',display_monthly_price:'49.00',monthly_page_quota:500,trial_page_allowance:0,per_file_page_cap:null,is_active:true,topup_packs:[{pages:500,price:'79.00',label:'500 pages'}]},
  balance:100,used_this_period:400,percent_used:80,warning:true,
  warning_message:'You have used 80% of your page credits. Request more pages from Plan before processing stops.',
  offline_notice:'Payment is arranged offline. Send a request and the team will contact you. Nothing on this page charges a card.',
  period_ends_at:'2026-12-25T00:00:00Z',can_request:true,
  plans:[{id:1,name:'Starter',display_monthly_price:'49.00',monthly_page_quota:500,trial_page_allowance:0,is_active:true},{id:3,name:'Pro',display_monthly_price:'199.00',monthly_page_quota:2000,trial_page_allowance:0,is_active:true}],
  history:[{id:1,type:'consumption',pages:-400,balance_after:100,reason:'Bank statement pages processed',created_at:'2026-11-20T00:00:00Z'}],
  requests:[{id:9,kind:'pack',status:'pending',pack_pages:500,amount:'79.00',plan:null,created_at:'2026-11-21T00:00:00Z',decision_reason:null}],
};

beforeEach(()=>{mocked.mockReset()});

describe('page credits',()=>{
  it('shows the plan, usage, the 80 percent warning, and offline requests',async()=>{
    mocked.mockResolvedValueOnce(credits);
    const w=mount(PagePlan,{props:{organizationId:3,canRequest:true}});
    await flushPromises();
    expect(w.text()).toContain('Starter');
    expect(w.text()).toContain('400');
    expect(w.text()).toContain('100');
    expect(w.text()).toContain('You have used 80% of your page credits');
    expect(w.text()).toContain('Payment is arranged offline');
    expect(w.text()).toContain('Top-up of 500 pages');
    expect(w.text()).toContain('pending');
    mocked.mockResolvedValueOnce({request:{id:10,status:'pending'},message:'Request sent. Payment is arranged offline, and the team will contact you. Nothing has been charged.'});
    mocked.mockResolvedValueOnce(credits);
    await w.findAll('button').find(b=>b.text()==='Request plan change')!.trigger('click');
    await w.find('form')!.trigger('submit');
    await flushPromises();
    expect(mocked).toHaveBeenCalledWith('/api/organizations/3/payment-requests','POST',expect.objectContaining({kind:'plan',plan_id:3}));
    expect(w.text()).toContain('Nothing has been charged');
  });

  it('keeps the out-of-pages message in plain language',()=>{
    expect(failureGuide('Your page credits are used up. Request more pages from Plan.').body).toBe('Your page credits are used up. Request more pages from Plan.');
    expect(failureGuide('Per-run page limit exceeded').body).not.toBe('Your page credits are used up. Request more pages from Plan.');
  });
});

describe('owner console payment requests and plans',()=>{
  it('approves an offline payment and rejects with a reason',async()=>{
    const row={id:4,kind:'plan',status:'pending',amount:'199.00',note:'Moving up.',organization:{name:'Fictional Lending'},requester:{email:'a@example.test'},plan:{name:'Pro'},created_at:'2026-11-21T00:00:00Z'};
    mocked.mockResolvedValueOnce({requests:{data:[row],current_page:1,last_page:1,total:1},kinds:{plan:'Plan change',pack:'Page top-up'},statuses:{pending:'Pending',approved:'Approved',rejected:'Rejected',cancelled:'Cancelled'}});
    const w=mount(PaymentRequests);
    await flushPromises();
    expect(w.text()).toContain('Fictional Lending');
    expect(w.text()).toContain('No card is charged');
    await w.find('button.c-link-btn').trigger('click');
    expect(w.text()).toContain('Mark paid offline and apply');
    mocked.mockResolvedValueOnce({request:{...row,status:'approved',payment_method:'Wire',payment_reference:'WIRE-100'}});
    mocked.mockResolvedValueOnce({requests:{data:[],current_page:1,last_page:1,total:0},kinds:{plan:'Plan change'},statuses:{pending:'Pending',approved:'Approved'}});
    await w.find('input[maxlength="120"]').setValue('WIRE-100');
    await w.findAll('form')[0].trigger('submit');
    await flushPromises();
    expect(mocked).toHaveBeenCalledWith('/api/admin/payment-requests/4/approve','POST',expect.objectContaining({payment_method:'Wire',payment_reference:'WIRE-100'}));
  });

  it('saves an edited plan with a placeholder price and a page pack',async()=>{
    const plan={id:2,name:'Pro',display_monthly_price:'199.00',monthly_page_quota:2000,trial_page_allowance:0,per_file_page_cap:null,is_active:true,listed_publicly:true,summary:'Placeholder price.',file_limit:100,member_limit:15,storage_limit_bytes:1000,topup_packs:[{pages:500,price:'79.00',label:'500 pages'}],organizations:1,pricing_status:'Placeholder price — edit it here. Payment is arranged offline.'};
    mocked.mockResolvedValueOnce({plans:[plan],notice:'Prices are placeholders for display and for offline payment requests. No card is charged from this screen.',billing:{notice:'No live billing provider is configured.',provider:'disabled',live_provider_configured:false}});
    const w=mount(Plans);
    await flushPromises();
    expect(w.text()).toContain('No card is charged');
    expect(w.text()).toContain('$199.00 / month');
    await w.findAll('button').find(b=>b.text()==='Edit')!.trigger('click');
    mocked.mockResolvedValueOnce({plan});
    mocked.mockResolvedValueOnce({plans:[plan],notice:'Prices are placeholders',billing:{notice:'No live billing provider is configured.',provider:'disabled',live_provider_configured:false}});
    await w.find('form')!.trigger('submit');
    await flushPromises();
    expect(mocked).toHaveBeenCalledWith('/api/admin/plans/2','PUT',expect.objectContaining({name:'Pro',display_monthly_price:'199.00',monthly_page_quota:2000}));
  });

  it('lists a demo request and saves a note',async()=>{
    const row={id:6,name:'Fictional Prospect',email:'p@example.test',company:'Example Lending',phone:'555-0100',monthly_volume:'40 loans',status:'new',is_spam:false,message:'Show us the workflow.',notes:[],created_at:'2026-11-21T00:00:00Z'};
    mocked.mockResolvedValueOnce({requests:{data:[row],current_page:1,last_page:1,total:1},statuses:{new:'New',contacted:'Contacted',converted:'Converted',closed:'Closed'},notification_configured:true});
    const w=mount(DemoRequests);
    await flushPromises();
    expect(w.text()).toContain('Fictional Prospect');
    expect(w.text()).toContain('40 loans');
    mocked.mockResolvedValueOnce({request:row});
    await w.find('button.c-link-btn').trigger('click');
    await flushPromises();
    expect(w.text()).toContain('p@example.test');
    mocked.mockResolvedValueOnce({request:{...row,notes:[{id:1,body:'Called the office.',author:{name:'Staff'},created_at:'2026-11-21T01:00:00Z'}]}});
    mocked.mockResolvedValueOnce({requests:{data:[row],current_page:1,last_page:1,total:1},statuses:{new:'New',contacted:'Contacted',converted:'Converted',closed:'Closed'},notification_configured:true});
    await w.find('textarea').setValue('Called the office.');
    await w.find('form').trigger('submit');
    await flushPromises();
    expect(mocked).toHaveBeenCalledWith('/api/admin/demo-requests/6/notes','POST',{body:'Called the office.'});
    expect(w.text()).toContain('Staff');
  });
});
