import {describe,it,expect,vi,beforeEach} from 'vitest';
import {mount,flushPromises} from '@vue/test-utils';
import WebsiteInquiries from '../../resources/js/views/WebsiteInquiries.vue';
import {initMobileMenu} from '../../resources/js/site';
import {api} from '../../resources/js/api';
vi.mock('vue-router',()=>({useRoute:()=>({query:{}})}));
vi.mock('../../resources/js/api',()=>({api:vi.fn(),csrf:vi.fn()}));
const mocked=vi.mocked(api);
const inquiry={id:5,name:'Fictional Prospect',email:'p@example.test',company:'Example Lending',role:null,inquiry_type:'demo',status:'new',is_spam:false,spam_reason:null,notification_status:'not_configured',created_at:'2026-11-09T00:00:00Z',message:'Show us the fictional demo.',notes:[]};
const listing=(rows:any[]=[inquiry])=>({inquiries:{data:rows},types:{demo:'Request a demo',pricing:'Pricing and plans'},statuses:{new:'New',in_progress:'In Progress',closed:'Closed'},notification_configured:false});
beforeEach(()=>{mocked.mockReset()});
describe('Website inquiries admin view',()=>{
 it('lists inquiries, hides spam by default and warns when notifications are not configured',async()=>{mocked.mockResolvedValueOnce(listing());const w=mount(WebsiteInquiries,{global:{stubs:{RouterLink:true}}});await flushPromises();expect(mocked).toHaveBeenCalledWith('/api/platform/inquiries?spam=exclude');expect(w.text()).toContain('Fictional Prospect');expect(w.text()).toContain('Request a demo');expect(w.text()).toContain('Email notification is not configured');});
 it('applies type and status filters through the API',async()=>{mocked.mockResolvedValue(listing([]));const w=mount(WebsiteInquiries);await flushPromises();const selects=w.findAll('select');await selects[0].setValue('pricing');await flushPromises();await selects[1].setValue('closed');await flushPromises();expect(mocked).toHaveBeenLastCalledWith('/api/platform/inquiries?type=pricing&status=closed&spam=exclude');expect(w.text()).toContain('No inquiries match these filters.');});
 it('opens details, changes status, marks spam and adds an internal note',async()=>{mocked.mockResolvedValueOnce(listing()).mockResolvedValueOnce({inquiry});const w=mount(WebsiteInquiries);await flushPromises();await w.find('button.text-button').trigger('click');await flushPromises();expect(w.text()).toContain('Show us the fictional demo.');
  mocked.mockResolvedValueOnce({inquiry:{...inquiry,status:'in_progress'}}).mockResolvedValueOnce(listing());await w.find('article select').setValue('in_progress');await flushPromises();expect(mocked).toHaveBeenCalledWith('/api/platform/inquiries/5','PATCH',{status:'in_progress'});expect(w.find('[role=status]').text()).toBe('Status updated.');
  mocked.mockResolvedValueOnce({inquiry:{...inquiry,is_spam:true}}).mockResolvedValueOnce(listing());await w.findAll('article button').find(b=>b.text()==='Mark as spam')!.trigger('click');await flushPromises();expect(mocked).toHaveBeenCalledWith('/api/platform/inquiries/5','PATCH',{is_spam:true});
  mocked.mockResolvedValueOnce({inquiry:{...inquiry,notes:[{id:1,body:'Called back',author:{name:'Staff Member'},created_at:'2026-11-09T01:00:00Z'}]}}).mockResolvedValueOnce(listing());await w.find('article textarea').setValue('Called back');await w.find('article form').trigger('submit');await flushPromises();expect(mocked).toHaveBeenCalledWith('/api/platform/inquiries/5/notes','POST',{body:'Called back'});expect(w.text()).toContain('Staff Member');});
 it('shows the server refusal for non-staff users without listing data',async()=>{mocked.mockRejectedValueOnce(Object.assign(new Error('Platform staff only.'),{status:403}));const w=mount(WebsiteInquiries);await flushPromises();expect(w.find('[role=alert]').text()).toBe('Platform staff only.');expect(w.find('tbody').text()).not.toContain('Fictional Prospect');});
});
describe('public site mobile menu',()=>{
 it('toggles aria-expanded, reveals the menu and closes on Escape returning focus',()=>{document.body.innerHTML='<button data-menu-button aria-expanded="false" aria-controls="m">Menu</button><nav id="m" data-mobile-nav hidden><a href="/features">Features</a></nav>';initMobileMenu(document);const b=document.querySelector('button')!;const nav=document.querySelector('nav')!;b.click();expect(b.getAttribute('aria-expanded')).toBe('true');expect(nav.hidden).toBe(false);expect(document.activeElement?.textContent).toBe('Features');document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}));expect(b.getAttribute('aria-expanded')).toBe('false');expect(nav.hidden).toBe(true);expect(document.activeElement).toBe(b);});
});
