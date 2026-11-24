window.ControlabPagination=class {
 constructor({root,request,endpoint,params=()=>({}),render,size=15}){Object.assign(this,{root,request,endpoint,params,render,size});this.page=1;this.sequence=0;}
 async load(page=this.page){
 const seq=++this.sequence;this.root.setAttribute('aria-busy','true');
 try{const query=new URLSearchParams({...this.params(),page,per_page:this.size});const result=await this.request(this.endpoint+'?'+query);
 if(seq!==this.sequence)return;if(!Array.isArray(result.data)||!result.pagination)throw new Error('Respuesta de paginación inválida');
 this.page=result.pagination.current_page;this.root.replaceChildren();this.render(result.data,result.pagination);this.footer(result.pagination);
 }catch(e){if(seq!==this.sequence)return;this.root.replaceChildren();const msg=document.createElement('p');msg.textContent=e.message;const retry=document.createElement('button');retry.className='btn';retry.textContent='Reintentar';retry.onclick=()=>this.load(page);this.root.append(msg,retry);}
 finally{if(seq===this.sequence)this.root.removeAttribute('aria-busy');}
 }
 footer(p){const bar=document.createElement('div');bar.className='card-footer compact-pagination';const info=document.createElement('span');info.className='text-secondary';info.textContent=p.total?((p.current_page-1)*p.per_page+1)+'–'+Math.min(p.current_page*p.per_page,p.total)+' de '+p.total:'0 registros';const nav=document.createElement('ul');nav.className='pagination pagination-controls';nav.style.cssText='list-style:none;margin:0;padding:0';const add=(label,page,disabled,active=false)=>{const li=document.createElement('li');li.className='page-item'+(active?' active':'')+(disabled?' disabled':'');const b=document.createElement('button');b.type='button';b.className='page-link btn compact-action';b.textContent=label;b.disabled=disabled||active;if(active)b.setAttribute('aria-current','page');b.onclick=()=>this.load(page);li.append(b);nav.append(li);};add('‹',p.current_page-1,p.current_page<=1);for(let i=Math.max(1,p.current_page-1);i<=Math.min(p.last_page,p.current_page+1);i++)add(String(i),i,false,i===p.current_page);add('›',p.current_page+1,p.current_page>=p.last_page);bar.append(info,nav);this.root.append(bar);}
};
