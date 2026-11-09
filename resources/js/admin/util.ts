import {api} from '../api';
export {api};
export function when(v?:string|null){return v?new Date(v).toLocaleString(undefined,{dateStyle:'medium',timeStyle:'short'}):'—'}
export function day(v?:string|null){return v?new Date(v).toLocaleDateString(undefined,{dateStyle:'medium'}):'—'}
export function bytes(n?:number|null){if(n==null)return '—';const u=['B','KB','MB','GB','TB'];let i=0,v=Number(n);while(v>=1024&&i<u.length-1){v/=1024;i++}return (i?v.toFixed(v<10?1:0):String(v))+' '+u[i]}
export function qs(o:Record<string,unknown>){const p=new URLSearchParams();for(const [k,v] of Object.entries(o))if(v!==''&&v!=null)p.set(k,String(v));const s=p.toString();return s?'?'+s:''}
export const statusTone:Record<string,string>={trial:'blue',active:'green',past_due:'amber',suspended:'red',cancelled:'slate',pending:'amber',approved:'green',rejected:'red',new:'blue',in_progress:'amber',closed:'slate'};
export async function download(url:string){
 const r=await fetch(url,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
 if(!r.ok){let m='Export failed.';try{m=(await r.json()).message||m}catch{}throw new Error(m)}
 const name=(r.headers.get('Content-Disposition')||'').match(/filename="?([^";]+)"?/)?.[1]||'report';
 const blob=await r.blob();const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(a.href),1000);return name}
