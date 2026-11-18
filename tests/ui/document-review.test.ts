import {describe,it,expect,vi,beforeEach} from 'vitest';
import {mount,flushPromises} from '@vue/test-utils';
import DocumentViewer from '../../resources/js/components/DocumentViewer.vue';
import {formatMoney} from '../../resources/js/formatMoney';
import {explainIntegrity,integrityCode} from '../../resources/js/customerLanguage';

vi.mock('pdfjs-dist',()=>({
  GlobalWorkerOptions:{workerSrc:''},
  TextLayer:class {constructor(){} render(){return Promise.resolve()} cancel(){}},
  getDocument:vi.fn(()=>({
    destroy:vi.fn(async()=>{}),
    promise:Promise.resolve({
      numPages:3,
      destroy:vi.fn(async()=>{}),
      getPage:vi.fn(async()=>({
        getViewport({scale}:{scale:number}){return {width:612*scale,height:792*scale,scale,rawDims:{pageWidth:612,pageHeight:792,pageX:0,pageY:0}}},
        render:vi.fn(()=>({promise:Promise.resolve(),cancel(){}})),
        streamTextContent:()=>new ReadableStream({start(controller){controller.close()}}),
      })),
    }),
  })),
}));

const version={id:4,mime:'application/pdf',original_name:'personal-2025-01.pdf',scan_status:'Clean'};

describe('money and integrity labels',()=>{
  it('converts minor units and leaves statement dollar strings in dollars',()=>{
    expect(formatMoney(400000)).toBe('$4,000.00');
    expect(formatMoney(0)).toBe('$0.00');
    expect(formatMoney('5000.00')).toBe('$5,000.00');
    expect(formatMoney('1500')).toBe('$1,500.00');
    expect(formatMoney(null)).toBe('—');
    expect(formatMoney('not-money')).toBe('—');
  });
  it('maps scan verdicts onto the four reader badges',()=>{
    expect(integrityCode({scan_status:'Clean'})).toBe('Clean');
    expect(integrityCode({scan_status:'Unscanned'})).toBe('Unscanned');
    expect(integrityCode({scan_status:'not_configured'})).toBe('Unscanned');
    expect(integrityCode({scan_status:'Pending'})).toBe('Unscanned');
    expect(integrityCode({latest_scan_status:'Unavailable',scan_status:'Clean'})).toBe('Unavailable');
    expect(integrityCode({scan_status:'infected'})).toBe('Quarantined');
    expect(integrityCode({scan_status:'Rejected'})).toBe('Quarantined');
    expect(integrityCode({scan_status:'ScanFailed'})).toBe('Quarantined');
    expect(explainIntegrity('Clean').label).toBe('Clean');
    expect(explainIntegrity('Quarantined').tone).toBe('red');
    expect(explainIntegrity('Unscanned').help).toMatch(/not certified clean/i);
  });
});

describe('pdf reader controls',()=>{
  beforeEach(()=>document.body.innerHTML='');
  async function reader(props:Record<string,unknown>={}){
    const w=mount(DocumentViewer,{props:{version,page:1,...props},attachTo:document.body});
    await flushPromises();
    await flushPromises();
    return w;
  }
  it('shows the file name, integrity badge, thumbnails, zoom and page controls',async()=>{
    const w=await reader();
    expect(w.text()).toContain('personal-2025-01.pdf');
    expect(w.text()).toContain('Clean');
    expect(w.text()).toContain('Original bytes are unchanged');
    expect(w.get('a').attributes('href')).toBe('/api/document-versions/4/content?download=1');
    expect(w.findAll('button.thumb')).toHaveLength(3);
    expect(w.get('button.thumb.active').text()).toBe('1');
    expect(w.text()).toContain('Fit width');
    expect(w.text()).toContain('Fit page');
    expect(w.get('[aria-label="Zoom percent"]').exists()).toBe(true);
    w.unmount();
  });
  it('jumps from a thumbnail and from the keyboard',async()=>{
    const w=await reader();
    await w.get('[aria-label="Go to page 3"]').trigger('click');
    await flushPromises();
    expect(w.get('button.thumb.active').text()).toBe('3');
    await w.get('.doc-reader').trigger('keydown',{key:'ArrowLeft'});
    await flushPromises();
    expect(w.get('button.thumb.active').text()).toBe('2');
    await w.get('.page-input').setValue('1');
    await w.get('.page-input').trigger('change');
    await flushPromises();
    expect(w.get('button.thumb.active').text()).toBe('1');
    w.unmount();
  });
  it('zooms from the percent control and keeps the finding highlight on that page',async()=>{
    const w=await reader({region:{x:0.05,y:0.3,width:0.4,height:0.02,normalized:true}});
    expect(w.get('.source-highlight').exists()).toBe(true);
    const before=Number((w.get('[aria-label="Zoom percent"]').element as HTMLInputElement).value);
    await w.get('[aria-label="Zoom in"]').trigger('click');
    await flushPromises();
    const after=Number((w.get('[aria-label="Zoom percent"]').element as HTMLInputElement).value);
    expect(after).toBeGreaterThan(before);
    expect(w.findAll('button').find((button)=>button.text()==='Fit width')!.attributes('aria-pressed')).toBe('false');
    await w.get('[aria-label="Next page"]').trigger('click');
    await flushPromises();
    expect(w.find('.source-highlight').exists()).toBe(false);
    w.unmount();
  });
  it('labels an unscanned original without calling it clean',async()=>{
    const w=await reader({version:{...version,scan_status:'not_configured',latest_scan_status:'Unscanned'}});
    expect(w.text()).toContain('Unscanned');
    expect(w.text()).toContain('not certified clean');
    expect(w.text()).not.toContain('Clean');
    w.unmount();
  });
});
