export class ApiError extends Error { constructor(public status:number,message:string){super(message)} }
export async function api<T=any>(url:string,method='GET',body?:unknown):Promise<T>{
 const token=decodeURIComponent(document.cookie.split('; ').find(x=>x.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=')||'');
 const headers:Record<string,string>={'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-XSRF-TOKEN':token};
 if(body && !(body instanceof FormData))headers['Content-Type']='application/json';
 const r=await fetch(url,{method,headers,credentials:'same-origin',body:body instanceof FormData?body:body?JSON.stringify(body):undefined});
 const data=r.status===204?null:await r.json().catch(()=>({message:'Unable to read response.'}));
 if(r.status===401&&data?.code==='impersonation_ended'){location.href=data.redirect||'/admin/users'}
 if(r.status===401&&data?.code==='account_disabled'){location.href='/login?disabled=1'}
 if(!r.ok)throw new ApiError(r.status,Object.values(data?.errors||{}).flat().join(' ')||data?.message||'Request failed.');return data;
}
export async function csrf(){await fetch('/sanctum/csrf-cookie',{credentials:'same-origin'})}
