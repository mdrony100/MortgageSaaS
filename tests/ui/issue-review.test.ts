import {describe,it,expect} from 'vitest';
import {mount} from '@vue/test-utils';
import IssueReview from '../../resources/js/components/IssueReview.vue';
import {reviewIssues} from '../../resources/js/customerLanguage';

describe('split finding review',()=>{
  it('opens the stored page and highlight when an issue is clicked',async()=>{
    const issues=reviewIssues([{id:9,source_page:2,source_document_version_id:4,bbox_x:'0.100000',bbox_y:'0.200000',bbox_w:'0.300000',bbox_h:'0.050000',data:{code:'payroll_deposit',observation:'Fictional payroll deposit needs a person to check it'}}]);
    expect(issues[0].page).toBe(2);
    expect(issues[0].region?.normalized).toBe(true);
    const versions=[{id:4,original_name:'personal-2025-01.pdf',mime:'application/pdf'}];
    const w=mount(IssueReview,{props:{issues,versions},global:{stubs:{DocumentViewer:{name:'DocumentViewer',props:['version','page','region','focus'],template:'<div class="pdf-stub">pdf</div>'}}}});
    expect(w.findComponent({name:'DocumentViewer'}).props('region')).toBeUndefined();
    await w.get('button').trigger('click');
    const viewer=w.findComponent({name:'DocumentViewer'});
    expect(viewer.props('page')).toBe(2);
    expect(viewer.props('region')).toMatchObject({x:0.1,y:0.2,width:0.3,height:0.05,normalized:true});
    expect(viewer.props('focus')).toBe(1);
    expect(w.text()).toContain('Page 2 · highlighted on the document');
  });
  it('keeps a page click useful when no box was stored',async()=>{
    const issues=reviewIssues([{id:3,data:{title:'Missing page',observation:'Confirm the page order',page:4,document_version_id:8}}]);
    expect(issues[0].region).toBeUndefined();
    expect(issues[0].page).toBe(4);
    const w=mount(IssueReview,{props:{issues,versions:[{id:8,original_name:'scan.pdf',mime:'application/pdf'}]},global:{stubs:{DocumentViewer:{name:'DocumentViewer',props:['version','page','region','focus'],template:'<div>pdf</div>'}}}});
    await w.get('button').trigger('click');
    expect(w.findComponent({name:'DocumentViewer'}).props('page')).toBe(4);
    expect(w.findComponent({name:'DocumentViewer'}).props('region')).toBeUndefined();
    expect(w.text()).toContain('Page 4');
    expect(w.text()).not.toContain('highlighted');
  });
});
