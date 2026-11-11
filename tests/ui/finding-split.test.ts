import {describe,it,expect} from 'vitest'
import {mount} from '@vue/test-utils'
import FindingSplit from '../../resources/js/components/FindingSplit.vue'

const versions=[{id:4,mime:'application/pdf',original_name:'FICTIONAL.pdf',scan_status:'Clean'}]
const stub={DocumentViewer:{name:'DocumentViewer',props:['version','page','region','focusKey'],template:'<div class="stub-viewer">page {{page}} region {{region && region.y}}</div>'}}

describe('split PDF review',()=>{
  it('scrolls a verified finding to its page and box',async()=>{
    const w=mount(FindingSplit,{props:{versions,issues:[
      {id:1,title:'Repeated page',detail:'Page 2 matches an earlier page.',severity:'high',status:'open',page:2,documentVersionId:4,region:{x:0.1,y:0.25,width:0.4,height:0.08,normalized:true}},
      {id:2,title:'Font change',detail:'More than four fonts on the page.',status:'open',page:3,documentVersionId:4,region:null},
    ]},global:{stubs:stub}})
    expect(w.find('.stub-viewer').text()).toContain('page 2')
    expect(w.find('.stub-viewer').text()).toContain('0.25')
    await w.findAll('.split-findings button')[1].trigger('click')
    expect(w.find('.stub-viewer').text()).toContain('page 3')
    expect(w.text()).toContain('precise highlight is not available')
    expect(w.find('.stub-viewer').text()).not.toContain('0.25')
  })
  it('explains an empty review',()=>{
    const w=mount(FindingSplit,{props:{versions,issues:[]},global:{stubs:stub}})
    expect(w.text()).toContain('No findings yet')
    expect(w.text()).toContain('approved automatically')
  })
})
