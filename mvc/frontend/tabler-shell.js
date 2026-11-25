/* Controlab / Tabler shared application shell. Keeps existing module DOM and event bindings intact. */
(()=>{'use strict';
const page=location.pathname.split('/').pop()||'dashboard.html';
const modules=[['dashboard.html','Dashboard gerencial','all','layout-dashboard'],['cotizaciones.html','Cotizaciones','manager','file-text'],['planificador.html','Órdenes de trabajo','all','calendar-days'],['empresas.html','Empresas y obras','manager','building-2'],['servicios.html','Servicios','manager','clipboard-list'],['personal.html','Personal de terreno','manager','users'],['laboratorio.html','Laboratorio','manager','flask-conical'],['informes.html','Informes','all','file-text'],['usuarios.html','Usuarios','admin','settings']];

const isForm=page==='formulario.html';
if(!isForm)document.documentElement.classList.add('cl-session-pending');
const st=document.createElement('link');st.rel='stylesheet';st.href='./assets/css/shell.css?v=20261124-2';document.head.append(st);
function init(){
 if(document.getElementById('cl-shell-top'))return;
 const top=document.createElement('header');top.id='cl-shell-top';top.className='navbar navbar-dark';top.innerHTML='<a href="./dashboard.html" class="cl-brand"><span class="cl-mark">RC</span><span><strong>CONTROLAB</strong><small>Gestión de laboratorio</small></span></a><button id="cl-shell-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="cl-shell-sidebar">☰</button>';
 const sidebar=document.createElement('aside');sidebar.id='cl-shell-sidebar';sidebar.className='navbar navbar-vertical';sidebar.setAttribute('aria-label','Navegación principal');sidebar.innerHTML='<div class="d-flex align-items-center justify-content-between d-lg-none mb-3"><strong>MENÚ PRINCIPAL</strong><button type="button" id="cl-shell-close" class="btn btn-sm btn-outline-secondary">Cerrar ✕</button></div><div class="cl-caption">ADMINISTRACIÓN</div>';
 const nav=document.createElement('nav');nav.className='navbar-nav w-100';
 for(const [href,title,role] of modules){const a=document.createElement('a');a.className='nav-link';a.href='./'+href;a.textContent=title;a.dataset.role=role;if(page===href){a.classList.add('active');a.setAttribute('aria-current','page')}if(role!=='all')a.hidden=true;nav.append(a);}
 sidebar.append(nav);
 if(page==='formulario.html'){const actions=document.createElement('div');actions.className='border-top mt-3 pt-3';for(const id of ['historyBtn','reviewsBtn','profileFirmaBtn','logoutBtn']){const b=document.getElementById(id);if(b){b.classList.add('d-block','w-100','my-1','text-start');actions.append(b)}}sidebar.append(actions);}
 if(!isForm){const logout=document.createElement('button');logout.type='button';logout.className='btn btn-outline-secondary w-100';logout.textContent='Cerrar sesión';logout.addEventListener('click',async()=>{try{const s=await fetch('./api/session',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:'{}'}).then(r=>r.json());const r=await fetch('./api/logout',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':s.csrf},body:JSON.stringify({csrf:s.csrf})});if(!r.ok)throw Error();location.replace('./formulario.html');}catch(e){alert('No fue posible cerrar sesión. Intenta nuevamente.');}});sidebar.append(logout);}
 const foot=document.createElement('div');foot.className='cl-foot';foot.textContent='Controlab · Piloto';sidebar.append(foot);
 const backdrop=document.createElement('div');backdrop.id='cl-shell-backdrop';
 // Retain old DOM: module scripts may still reference legacy header controls by ID.
 document.body.prepend(top,sidebar,backdrop);document.body.classList.add('cl-tabler-shell');
 const toggle=top.querySelector('#cl-shell-toggle');
 const setOpen=(open)=>{sidebar.classList.toggle('open',open);backdrop.classList.toggle('open',open);toggle.setAttribute('aria-expanded',String(open))};
 toggle.addEventListener('click',()=>setOpen(!sidebar.classList.contains('open')));sidebar.querySelector('#cl-shell-close').addEventListener('click',()=>setOpen(false));backdrop.addEventListener('click',()=>setOpen(false));document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false)});
 fetch('./api/session',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:'{}'}).then(r=>r.json()).then(s=>{if(!s.autenticado){if(!isForm)location.replace('./formulario.html?next='+encodeURIComponent(page+location.search));return;}document.documentElement.classList.remove('cl-session-pending');const manager=['administrador','administrativo'].includes(s.rol);nav.querySelectorAll('a').forEach(a=>a.hidden=a.dataset.role==='manager'?!manager:a.dataset.role==='admin'?s.rol!=='administrador':false)}).catch(()=>{if(!isForm)location.replace('./formulario.html?next='+encodeURIComponent(page+location.search));});
 if(page==='formulario.html'){const app=document.getElementById('appView');if(app){const sync=()=>{top.hidden=app.hidden;sidebar.hidden=app.hidden;toggle.hidden=app.hidden};new MutationObserver(sync).observe(app,{attributes:true,attributeFilter:['hidden']});sync()}}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();