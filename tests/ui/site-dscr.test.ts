import {describe,it,expect} from 'vitest';
import {parseMoney,ratioHalfUp2,sampleDscr,formatDollars} from '../../resources/js/site/dscr';

describe('public site DSCR example (integer cents, HALF_UP to 2 decimals)',()=>{
 it('computes the default example 3,300 / 3,000 = 1.10',()=>{
  const r=sampleDscr('3,300','3,000');
  expect(r).toEqual({ok:true,dscr:'1.10',equation:'$3,300 ÷ $3,000 = 1.10',explanation:'In this simplified example, rent is 1.10 times the monthly property payment.'});
 });
 it('rounds half up exactly like the backend BigDecimal HALF_UP convention',()=>{
  expect(ratioHalfUp2(100n,800n)).toBe('0.13');        // 0.125 -> 0.13
  expect(ratioHalfUp2(100n,1600n)).toBe('0.06');       // 0.0625 -> 0.06
  expect(ratioHalfUp2(1n,200n)).toBe('0.01');          // 0.005 -> 0.01
  expect(ratioHalfUp2(1n,201n)).toBe('0.00');          // 0.004975 -> 0.00
  expect(ratioHalfUp2(100000n,300n)).toBe('333.33');
  expect(ratioHalfUp2(200000n,300n)).toBe('666.67');
  expect(ratioHalfUp2(299999n,300000n)).toBe('1.00');  // 0.999996...
  expect(ratioHalfUp2(0n,300000n)).toBe('0.00');
  // values where binary floating point would misround: 1.005 and 1.115
  expect(ratioHalfUp2(100500n,100000n)).toBe('1.01');
  expect(ratioHalfUp2(111500n,100000n)).toBe('1.12');
 });
 it('parses dollar input without floating point',()=>{
  expect(parseMoney('$3,300.5')).toEqual({ok:true,cents:330050n});
  expect(parseMoney(' 3300 ')).toEqual({ok:true,cents:330000n});
  expect(parseMoney('.75')).toEqual({ok:true,cents:75n});
  expect(parseMoney('0.1')).toEqual({ok:true,cents:10n});
  expect(formatDollars(330050n)).toBe('$3,300.50');
  expect(formatDollars(123456700n)).toBe('$1,234,567');
 });
 it('validates empty, non-numeric, negative, precision, size and zero payment',()=>{
  expect(parseMoney('')).toEqual({ok:false,error:'empty'});
  expect(parseMoney('   ')).toEqual({ok:false,error:'empty'});
  expect(parseMoney('abc')).toEqual({ok:false,error:'invalid'});
  expect(parseMoney('3,30')).toEqual({ok:false,error:'invalid'});
  expect(parseMoney('1e3')).toEqual({ok:false,error:'invalid'});
  expect(parseMoney('-5')).toEqual({ok:false,error:'negative'});
  expect(parseMoney('$-5')).toEqual({ok:false,error:'negative'});
  expect(parseMoney('-abc')).toEqual({ok:false,error:'invalid'});
  expect(parseMoney('1.234')).toEqual({ok:false,error:'decimals'});
  expect(parseMoney('10000000')).toEqual({ok:false,error:'too_large'});
  expect(parseMoney('0',false)).toEqual({ok:false,error:'zero'});
  expect(parseMoney('0.00',false)).toEqual({ok:false,error:'zero'});
  const r=sampleDscr('','0');
  expect(r.ok).toBe(false);
  if(!r.ok){expect(r.errors.rent).toBe('Enter a monthly rent amount.');expect(r.errors.payment).toBe('The monthly payment must be greater than $0.');}
  const n=sampleDscr('3300','-1');
  expect(n.ok===false&&n.errors.payment).toBe('The payment can’t be a negative amount.');
  expect(sampleDscr('0','3000')).toMatchObject({ok:true,dscr:'0.00'});
 });
 it('never mentions approval, eligibility or pass/fail',()=>{
  const r=sampleDscr('1000','3000');
  expect(JSON.stringify(r)).not.toMatch(/approv|eligib|pass|fail|qualif(?:y|ies) for/i);
 });
});
