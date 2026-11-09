import {describe,it,expect,beforeEach} from 'vitest';
(globalThis as any).__SITE_NO_AUTO_INIT__=true;
const site=await import('../../resources/js/site');

const tabsHtml=(id:string,n:number,vertical=false)=>`<div data-tabs="${id}"><div role="tablist" ${vertical?'aria-orientation="vertical"':''}>${Array.from({length:n},(_,i)=>`<button role="tab" id="${id}-t${i}" aria-controls="${id}-p${i}" aria-selected="${i===0}" tabindex="${i===0?0:-1}">T${i}</button>`).join('')}</div>${Array.from({length:n},(_,i)=>`<div role="tabpanel" id="${id}-p${i}" class="${i===0?'is-active':''}">Panel ${i}</div>`).join('')}</div>`;

beforeEach(()=>{document.body.innerHTML='';document.documentElement.className='';});

describe('public site tabs',()=>{
 it('clicking and arrow keys switch the visible panel and aria state',()=>{
  document.body.innerHTML=tabsHtml('preview',3);
  const ctl=site.initTabs(document.querySelector('[data-tabs]') as HTMLElement)!;
  const p=(i:number)=>document.getElementById(`preview-p${i}`)!;
  expect([p(0).hidden,p(1).hidden,p(2).hidden]).toEqual([false,true,true]);
  (document.getElementById('preview-t2') as HTMLButtonElement).click();
  expect(ctl.index).toBe(2);expect(p(2).hidden).toBe(false);expect(p(0).hidden).toBe(true);
  expect(document.getElementById('preview-t2')!.getAttribute('aria-selected')).toBe('true');
  document.getElementById('preview-t2')!.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}));
  expect(ctl.index).toBe(0);expect(document.activeElement?.id).toBe('preview-t0');
  document.getElementById('preview-t0')!.dispatchEvent(new KeyboardEvent('keydown',{key:'End',bubbles:true}));
  expect(ctl.index).toBe(2);
 });
});

describe('interactive walkthrough',()=>{
 it('Previous/Next update the step, status and progress, and the start link activates step 1',()=>{
  document.body.innerHTML=`<a href="#walkthrough" data-walkthrough-start>See How It Works</a><section id="walkthrough">${tabsHtml('walk',4,true).replace('</div><div role="tabpanel"','</div><button data-walk-prev>Prev</button><button data-walk-next>Next</button><p data-walk-status></p><div data-walk-progress></div><div role="tabpanel"')}</section>`;
  const root=document.querySelector('[data-tabs]') as HTMLElement;
  const ctl=site.initWalkthrough(root)!;
  const prev=document.querySelector('[data-walk-prev]') as HTMLButtonElement, next=document.querySelector('[data-walk-next]') as HTMLButtonElement;
  const status=()=>document.querySelector('[data-walk-status]')!.textContent;
  expect(status()).toBe('Step 1 of 4');expect(prev.disabled).toBe(true);
  next.click();next.click();
  expect(ctl.index).toBe(2);expect(status()).toBe('Step 3 of 4');
  expect(document.querySelector('[data-walk-progress]')!.getAttribute('aria-valuenow')).toBe('3');
  next.click();expect(next.disabled).toBe(true);
  prev.click();expect(ctl.index).toBe(2);
  document.getElementById('walk-t2')!.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true}));
  expect(ctl.index).toBe(3);
  (Element.prototype as any).scrollIntoView=()=>{};
  site.initWalkthroughStart(document,ctl);
  (document.querySelector('[data-walkthrough-start]') as HTMLAnchorElement).click();
  expect(ctl.index).toBe(0);expect(document.activeElement?.id).toBe('walk-t0');
 });
});

describe('sample DSCR calculator widget',()=>{
 it('updates the result live and shows accessible inline errors',()=>{
  document.body.innerHTML=`<form data-dscr><input id="dscr-rent" data-dscr-rent value="3,300"><p id="dscr-rent-error" aria-live="polite"></p><input id="dscr-payment" data-dscr-payment value="3,000"><p id="dscr-payment-error" aria-live="polite"></p><p data-dscr-result>1.10</p><p data-dscr-equation></p><p data-dscr-explanation></p><button type="button" data-dscr-reset>Reset</button></form>`;
  site.initDscr(document.querySelector('[data-dscr]') as HTMLElement);
  const rent=document.getElementById('dscr-rent') as HTMLInputElement, pay=document.getElementById('dscr-payment') as HTMLInputElement;
  const result=()=>document.querySelector('[data-dscr-result]')!.textContent;
  rent.value='4125';rent.dispatchEvent(new Event('input'));pay.value='3300';pay.dispatchEvent(new Event('input'));
  expect(result()).toBe('1.25');
  expect(document.querySelector('[data-dscr-explanation]')!.textContent).toBe('In this simplified example, rent is 1.25 times the monthly property payment.');
  pay.value='0';pay.dispatchEvent(new Event('input'));
  expect(result()).toBe('—');expect(pay.getAttribute('aria-invalid')).toBe('true');
  expect(document.getElementById('dscr-payment-error')!.textContent).toBe('The monthly payment must be greater than $0.');
  rent.value='';rent.dispatchEvent(new Event('input'));
  expect(document.getElementById('dscr-rent-error')!.textContent).toBe('Enter a monthly rent amount.');
  (document.querySelector('[data-dscr-reset]') as HTMLButtonElement).click();
  expect(result()).toBe('1.10');expect(pay.hasAttribute('aria-invalid')).toBe(false);expect(document.getElementById('dscr-payment-error')!.textContent).toBe('');
 });
});

describe('reveal animation safety',()=>{
 it('does not hide content when reduced motion is requested',()=>{
  document.body.innerHTML='<section data-reveal>Visible</section>';
  const orig=window.matchMedia;
  (window as any).matchMedia=(q:string)=>({matches:q.includes('reduce'),media:q,addEventListener(){},removeEventListener(){}});
  site.initReveal(document);
  expect(document.documentElement.classList.contains('reveal-on')).toBe(false);
  (window as any).matchMedia=orig;
 });
});
