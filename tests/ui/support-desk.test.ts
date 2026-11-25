import {reactive, nextTick} from 'vue'
import {describe, it, expect, vi, beforeEach} from 'vitest'
import {mount, flushPromises} from '@vue/test-utils'
import {createPinia, setActivePinia} from 'pinia'
import SupportTickets from '../../resources/js/admin/views/SupportTickets.vue'
import SupportDesk from '../../resources/js/views/SupportDesk.vue'
import {useAuth} from '../../resources/js/stores/auth'
import {api} from '../../resources/js/api'

let testRoute: any
const push = vi.fn()
vi.mock('vue-router', () => ({useRoute: () => testRoute, useRouter: () => ({push})}))
vi.mock('../../resources/js/api', () => ({api: vi.fn(), csrf: vi.fn()}))
const mocked = vi.mocked(api)

const statuses = {open: 'Open', pending: 'Pending', resolved: 'Resolved', closed: 'Closed'}
const categories = {technical: 'Technical', billing: 'Billing'}
const priorities = {low: 'Low', normal: 'Normal', high: 'High', urgent: 'Urgent'}
const rows = [
  {id: 4, subject: 'Technical Problem', category: 'technical', priority: 'high', status: 'open', organization: 'SovtVence', requester: 'Md Rony', updated_at: '2026-11-25T18:00:00Z'},
  {id: 5, subject: 'Billing question', category: 'billing', priority: 'normal', status: 'pending', organization: 'SovtVence', requester: 'Md Rony', updated_at: '2026-11-24T15:00:00Z'},
]
const detail = {
  ...rows[0],
  messages: [
    {id: 1, body: 'I am facing a technical problem, please check my account.', staff: false, author: 'Md Rony', created_at: '2026-11-25T18:00:00Z', attachments: []},
    {id: 2, body: 'We are looking at this.', staff: true, author: 'Platform Admin', created_at: '2026-11-25T18:30:00Z', attachments: [{id: 9, name: 'note.pdf', scan_status: 'Clean'}]},
  ],
}

beforeEach(() => {
  sessionStorage.clear()
  mocked.mockReset()
  push.mockReset()
  testRoute = reactive({path: '/support', fullPath: '/support', params: {}})
})

describe('owner console support desk', () => {
  function listPayload() {
    return {tickets: {data: rows, current_page: 1, last_page: 1, total: rows.length}, statuses, categories, priorities}
  }
  beforeEach(() => {
    mocked.mockImplementation(async (url: string, method = 'GET') => {
      if (method === 'POST' && url.endsWith('/replies')) return {ticket: detail}
      if (method === 'PATCH') return {ticket: {...detail, status: 'pending'}}
      if (/\/support-tickets\/\d+$/.test(url)) return {ticket: detail}
      return listPayload()
    })
  })

  it('lays out a selectable list and an empty conversation until a ticket is opened', async () => {
    const w = mount(SupportTickets, {attachTo: document.body})
    await flushPromises()
    expect(w.find('table').exists()).toBe(false)
    expect(w.find('.desk-panes').exists()).toBe(true)
    expect(w.find('.desk-empty').text()).toContain('Select a ticket')
    expect(w.find('.c-pager-count').text()).toContain('2 total')
    const first = w.findAll('.desk-row')
    expect(first).toHaveLength(2)
    expect(first[0].text()).toContain('Technical Problem')
    expect(first[0].text()).toContain('SovtVence · Md Rony')
    expect(first[0].text()).toContain('High')
    expect(first[0].text()).toContain('Open')
    expect(first[0].text()).toContain('Unread')
    expect(first[0].classes()).toContain('is-unread')
    await first[0].trigger('click')
    await flushPromises()
    expect(first[0].classes()).not.toContain('is-unread')
    expect(w.find('.desk-empty').exists()).toBe(false)
    expect(w.find('#ticket-heading').text()).toBe('Technical Problem')
    expect(w.find('.desk-status select').element.value).toBe('open')
    expect(w.find('.is-customer').text()).toContain('Md Rony')
    expect(w.find('.is-customer').text()).toContain('Customer')
    expect(w.find('.is-customer').text()).toContain('I am facing a technical problem')
    expect(w.find('.is-admin').text()).toContain('Platform Admin')
    expect(w.find('.is-admin').text()).toContain('Admin')
    expect(w.find('.is-admin a').attributes('href')).toBe('/api/admin/support-ticket-attachments/9')
    const send = w.find('.desk-send')
    expect(send.text()).toBe('Send reply')
    expect(send.classes()).toContain('c-btn')
    await w.find('#ticket-reply').setValue('We can see the ticket.')
    await w.find('.desk-reply').trigger('submit')
    await flushPromises()
    expect(mocked).toHaveBeenCalledWith('/api/admin/support-tickets/4/replies', 'POST', {message: 'We can see the ticket.'})
    await w.find('.desk-back').trigger('click')
    expect(w.find('.desk-empty').text()).toContain('Select a ticket')
    w.unmount()
  })
})

describe('customer support desk', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    const auth = useAuth()
    auth.user = {id: 2, name: 'Md Rony', email_verified_at: '2026-01-01', platform_admin: false}
    auth.orgId = 3
    auth.memberships = [{organization_id: 3, role: 'owner', organization: {id: 3, name: 'SovtVence', approval_status: 'approved', suspended_at: null}}]
    mocked.mockImplementation(async (url: string, method = 'GET', body?: unknown) => {
      if (method === 'POST' && String(url).endsWith('/replies')) return {ticket: detail}
      if (method === 'POST') return {ticket: {...detail, id: 8}}
      if (/\/support-tickets\/\d+$/.test(url)) return {ticket: detail}
      return {tickets: {data: rows}, statuses, categories, priorities}
    })
  })

  it('uses the same fitted list and conversation instead of a wide table', async () => {
    const w = mount(SupportDesk, {global: {stubs: {RouterLink: {props: ['to'], template: '<a :href="to"><slot/></a>'}}}})
    await flushPromises()
    expect(w.find('table').exists()).toBe(false)
    expect(w.find('.support-desk').exists()).toBe(true)
    expect(w.find('.support-empty').text()).toContain('Select a ticket')
    const link = w.find('.support-row')
    expect(link.text()).toContain('Technical Problem')
    expect(link.text()).toContain('Technical')
    expect(link.text()).toContain('Md Rony')
    expect(link.text()).toContain('High')
    expect(link.text()).toContain('Unread')
    expect(link.attributes('href')).toBe('/support/4')
    testRoute.path = '/support/4'
    testRoute.fullPath = '/support/4'
    testRoute.params = {id: '4'}
    await nextTick()
    await flushPromises()
    expect(w.find('.support-back').attributes('href')).toBe('/support')
    expect(w.find('.support-thread h2').text()).toBe('Technical Problem')
    expect(w.find('.is-customer').text()).toContain('Your company')
    expect(w.find('.is-staff').text()).toContain('Non-QM Doc AI')
    expect(w.find('.is-staff a').attributes('href')).toBe('/api/support-ticket-attachments/9/content')
    expect(w.find('.support-reply-bar button').text()).toBe('Send reply')
    const file = new File(['x'], 'note.pdf', {type: 'application/pdf'})
    await w.find('.support-reply textarea').setValue('It is still happening.')
    const input = w.find('.support-reply input[type=file]')
    Object.defineProperty(input.element, 'files', {value: [file]})
    await input.trigger('change')
    await w.find('.support-reply').trigger('submit')
    await flushPromises()
    const replyCall = mocked.mock.calls.find((call) => String(call[0]).endsWith('/replies'))
    expect(replyCall?.[1]).toBe('POST')
    expect(replyCall?.[2]).toBeInstanceOf(FormData)
    const body = replyCall?.[2] as FormData
    expect(body.get('message')).toBe('It is still happening.')
    expect((body.get('file') as File).name).toBe('note.pdf')
    w.unmount()
  })
})
