/* Controlab / Tabler shared application shell. Keeps existing module DOM and event bindings intact. */
(()=>{'use strict';
const page=location.pathname.split('/').pop()||'dashboard.html';
const modules=[['dashboard.html','Dashboard gerencial','all','layout-dashboard'],['planificador.html','Órdenes de trabajo','all','calendar-days'],['empresas.html','Empresas','manager','building-2'],['obras.html','Obras','manager','map-pin'],['servicios.html','Servicios','manager','clipboard-list'],['personal.html','Personal de terreno','manager','users'],['formulario.html','Informes (prototipo)','all','file-text'],['usuarios.html','Usuarios','admin','settings']];
const css=`
#cl-shell-top{height:68px;min-height:68px;background:#10283d!important;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:10px 20px;position:relative;z-index:1002}
#cl-shell-top .cl-brand{display:flex;align-items:center;gap:12px;color:#fff;text-decoration:none}
#cl-shell-top .cl-mark{display:grid;place-items:center;width:42px;height:42px;border-radius:10px;background:#32b6a3;color:#073236;font-weight:800}
#cl-shell-top .cl-brand strong{display:block;font-size:17px;line-height:1.3}#cl-shell-top .cl-brand small{display:block;color:#bbd1dc;font-size:11px}
#cl-shell-toggle{display:none;border:1px solid #698294;border-radius:9px;background:transparent;color:white;padding:6px 12px;font-size:24px;line-height:1.2;cursor:pointer}
#cl-shell-sidebar{position:fixed;top:68px;bottom:0;left:0;width:250px;z-index:1001;overflow-y:auto;background:#fff;border-right:1px solid #dbe4eb;padding:22px 12px;box-sizing:border-box}
#cl-shell-sidebar .cl-caption{font-size:11px;letter-spacing:.08em;font-weight:800;color:#8194a1;margin:0 12px 14px}
#cl-shell-sidebar a{display:flex;align-items:center;gap:10px;text-decoration:none;color:#385367;font:650 14px/1.4 system-ui;padding:12px;border-radius:9px;margin-bottom:4px}
#cl-shell-sidebar a:hover{background:#edf5f4;color:#075f59}#cl-shell-sidebar a.active{background:#e3f4ef;color:#087d70}
#cl-shell-sidebar a[hidden]{display:none!important}#cl-shell-sidebar .cl-foot{font:11px/1.5 system-ui;color:#78909e;margin:20px 12px}
#cl-shell-backdrop{display:none}#cl-shell-top[hidden],#cl-shell-sidebar[hidden],#cl-shell-toggle[hidden]{display:none!important}
body.cl-tabler-shell{background:#f3f6f8}body.cl-tabler-shell > main,body.cl-tabler-shell > .app-view > main,body.cl-tabler-shell #appView > main{margin-left:250px!important;max-width:none!important}
body.cl-tabler-shell #appView > .layout,body.cl-tabler-shell .layout{margin-left:250px!important}
body.cl-tabler-shell #appView > .layout main,body.cl-tabler-shell .layout main{margin-left:0!important}
body.cl-tabler-shell #appView > .topbar,body.cl-tabler-shell > header.top,body.cl-tabler-shell > header.header,body.cl-tabler-shell > header.topbar,body.cl-tabler-shell > header.cl-dashboard-header{display:none!important}
body.cl-tabler-shell #sidebar,body.cl-tabler-shell #scrim,body.cl-tabler-shell #cl-nav-sidebar,body.cl-tabler-shell #cl-nav-toggle,body.cl-tabler-shell #cl-nav-scrim{display:none!important}
body.cl-tabler-shell .main-area{margin-left:250px!important}
body.cl-tabler-shell #cl-shell-top{display:flex!important}
@media(max-width:900px){#cl-shell-toggle{display:inline-flex}#cl-shell-sidebar{top:0;width:min(300px,85vw);transform:translateX(-110%);transition:transform .18s ease;z-index:1101;box-shadow:10px 0 40px #132c3c20}#cl-shell-sidebar.open{transform:translateX(0)}#cl-shell-backdrop.open{display:block;position:fixed;inset:0;background:#0c223a75;z-index:1100}body.cl-tabler-shell > main,body.cl-tabler-shell > .app-view > main,body.cl-tabler-shell #appView > main,body.cl-tabler-shell .main-area,body.cl-tabler-shell #appView > .layout,body.cl-tabler-shell .layout{margin-left:0!important}}
`;
const st=document.createElement('style');st.textContent=css;document.head.append(st);
function init(){
 if(document.getElementById('cl-shell-top'))return;
 const top=document.createElement('header');top.id='cl-shell-top';top.innerHTML='<a href="./dashboard.html" class="cl-brand"><span class="cl-mark">RC</span><span><strong>CONTROLAB</strong><small>Gestión de laboratorio</small></span></a><button id="cl-shell-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="cl-shell-sidebar">☰</button>';
 const sidebar=document.createElement('aside');sidebar.id='cl-shell-sidebar';sidebar.setAttribute('aria-label','Navegación principal');sidebar.innerHTML='<div class="d-flex align-items-center justify-content-between d-lg-none mb-3"><strong>MENÚ PRINCIPAL</strong><button type="button" id="cl-shell-close" class="btn btn-sm btn-outline-secondary">Cerrar ✕</button></div><div class="cl-caption">ADMINISTRACIÓN</div>';
 const nav=document.createElement('nav');
 for(const [href,title,role] of modules){const a=document.createElement('a');a.href='./'+href;a.textContent=title;a.dataset.role=role;if(page===href){a.classList.add('active');a.setAttribute('aria-current','page')}if(role!=='all')a.hidden=true;nav.append(a);}
 sidebar.append(nav);
 if(page==='formulario.html'){const actions=document.createElement('div');actions.style.cssText='border-top:1px solid #dbe4eb;margin-top:15px;padding-top:12px';for(const id of ['historyBtn','reviewsBtn','profileFirmaBtn','logoutBtn']){const b=document.getElementById(id);if(b){b.style.cssText='display:block;width:100%;margin:4px 0;text-align:left';actions.append(b)}}sidebar.append(actions);}
 const foot=document.createElement('div');foot.className='cl-foot';foot.textContent='Controlab · Piloto';sidebar.append(foot);
 const backdrop=document.createElement('div');backdrop.id='cl-shell-backdrop';
 // Retain old DOM: module scripts may still reference legacy header controls by ID.
 document.body.prepend(top,sidebar,backdrop);document.body.classList.add('cl-tabler-shell');
 const toggle=top.querySelector('#cl-shell-toggle');
 const setOpen=(open)=>{sidebar.classList.toggle('open',open);backdrop.classList.toggle('open',open);toggle.setAttribute('aria-expanded',String(open))};
 toggle.addEventListener('click',()=>setOpen(!sidebar.classList.contains('open')));sidebar.querySelector('#cl-shell-close').addEventListener('click',()=>setOpen(false));backdrop.addEventListener('click',()=>setOpen(false));document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false)});
 fetch('./api/session',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:'{}'}).then(r=>r.json()).then(s=>{if(!s.autenticado)return;const manager=['administrador','administrativo'].includes(s.rol);nav.querySelectorAll('a').forEach(a=>a.hidden=a.dataset.role==='manager'?!manager:a.dataset.role==='admin'?s.rol!=='administrador':false)}).catch(()=>{});
 if(page==='formulario.html'){const app=document.getElementById('appView');if(app){const sync=()=>{top.hidden=app.hidden;sidebar.hidden=app.hidden;toggle.hidden=app.hidden};new MutationObserver(sync).observe(app,{attributes:true,attributeFilter:['hidden']});sync()}}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();