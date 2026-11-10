import {describe,it,expect,vi,beforeEach} from 'vitest';
import {mount,flushPromises} from '@vue/test-utils';
import Settings from '../../resources/js/admin/views/Settings.vue';
import {api} from '../../resources/js/api';
vi.mock('../../resources/js/api',()=>({api:vi.fn(),csrf:vi.fn()}));
const mocked=vi.mocked(api);
beforeEach(()=>mocked.mockReset());
describe('owner settings',()=>{
 it('shows set or not set for secrets and a sandbox warning, never the secret',async()=>{
  mocked.mockResolvedValue({settings:{
    openai_api_key:{secret:true,set:true,last4:'WXYZ',source:'database'},
    parser_sandbox:{secret:false,value:'none',source:'database'},
    impersonation_enabled:{secret:false,value:true,source:'database'},
  },parser:{ok:false,issues:['Python path is empty.'],warning:'The parser sandbox is off.',sandbox:'none'}});
  const w=mount(Settings);await flushPromises();
  expect(w.text()).toContain('ends in WXYZ');
  expect(w.text()).toContain('Python path is empty.');
  expect(w.text()).toContain('not isolated with bubblewrap');
  expect(w.text()).not.toContain('sk-');
  const input=w.find('#setting-openai_api_key');
  expect(input.attributes('type')).toBe('password');
  expect((input.element as HTMLInputElement).value).toBe('');
  expect(w.find('button').text()).not.toContain('WXYZ');
 });
});
