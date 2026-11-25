(()=>{'use strict';
const $=id=>document.getElementById(id),el=(tag,cls='',txt='')=>{const n=document.createElement(tag);n.className=cls;n.textContent=txt;return n;};
let csrf='',config={},subjects={muestras:[],puntos:[]},revision=0,types=[],materials=[],editingType=null,classifying=null,orders=[],currentOT=null,task=null,orderRequest=0,opening=0;
function notice(t,ok=false){$('notice').className=t?'alert '+(ok?'alert-success':'alert-danger'):'mb-3';$('notice').textContent=t;}
async function api(path,body,method='POST'){const opt={credentials:'same-origin',cache:'no-store'};if(body!==undefined){opt.method=method;opt.headers={'Content-Type':'application/json','X-CSRF-Token':csrf};opt.body=JSON.stringify({...body,csrf});}const r=await fetch('./api'+path,opt);const v=await r.json();if(!r.ok)throw Error(v.error||'Error de servidor');return v;}
function options(id,rows,label,placeholder){const s=$(id);s.replaceChildren();const empty=el('option','',placeholder);empty.value='';s.append(empty);for(const r of rows){const o=el('option','',label(r));o.value=r.id??r.codigo;s.append(o);}}
function chosen(id){const v=Number($(id).value);if(!Number.isInteger(v)||v<1)throw Error('Selecciona '+id);return v;}
function fieldTest(){return ['S14','S15'].includes($('test').value);}
function renderFields(){const root=$('fields');root.replaceChildren();for(const [key,label] of config.campos?.[$('test').value]||[]){const col=el('div','col-12 col-md-6');const l=el('label','form-label',label),i=el('input','form-control');i.id='lab-'+key;l.htmlFor=i.id;i.required=true;i.inputMode='decimal';i.dataset.key=key;col.append(l,i);root.append(col);}renderSubjects();}
function renderSubjects(){const f=fieldTest();$('subjectLabel').textContent=f?'Punto de terreno':'Muestra';options('subject',f?subjects.puntos.filter(p=>String(p.ot_id)===String(currentOT?.id)):subjects.muestras,r=>r.codigo+(r.numero_ot?' · '+r.numero_ot:''),'Seleccionar');}
function renderResults(rows,target='results'){const root=$(target);root.replaceChildren();if(!rows.length)root.append(el('p','p-3 text-secondary','Sin resultados registrados.'));for(const r of rows){const item=el('div','list-group-item');item.append(el('div','fw-bold',r.ensayo_codigo+' · '+r.nombre),el('div','small text-secondary',r.identificacion+' · '+r.fecha+' · '+r.estado+' · v'+r.version));if(r.servicio_nombre)item.append(el('div','small',r.servicio_nombre));for(const [k,v] of Object.entries(r.datos.resultados||{}))item.append(el('div','small',k+': '+Number(v).toLocaleString('es-CL',{maximumFractionDigits:6})));root.append(item);}}
const norm=v=>String(v).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
function renderTypeOptions(){const keep=$('materialType').value;const q=norm($('typeSearch').value);options('materialType',types.filter(t=>Number(t.activo)&&(norm(t.nombre).includes(q)||String(t.id)===keep)),t=>t.nombre,'Seleccionar tipo');$('materialType').value=keep;}
function resetType(){editingType=null;$('typeForm').reset();$('typeActiveWrap').hidden=true;$('cancelType').hidden=true;$('saveType').textContent='Agregar tipo';}
function renderTypes(){const root=$('typesList');root.replaceChildren();for(const t of types){const row=el('div','list-group-item d-flex align-items-center justify-content-between gap-2');const left=el('div');left.append(el('div','fw-semibold',t.nombre),el('small','text-secondary',Number(t.activo)?'Activo':'Inactivo'));const b=el('button','btn btn-sm btn-outline-secondary','Editar');b.type='button';b.onclick=()=>{editingType=Number(t.id);$('typeName').value=t.nombre;$('typeActive').checked=!!Number(t.activo);$('typeActiveWrap').hidden=false;$('cancelType').hidden=false;$('saveType').textContent='Guardar cambios';$('typeName').focus();};row.append(left,b);root.append(row);}renderTypeOptions();}
async function loadTypes(){types=(await api('/laboratorio/tipos-material')).tipos;renderTypes();}
function resetMaterialForm(){classifying=null;$('newMaterial').reset();$('saveMaterial').textContent='Registrar material';$('cancelClassify').hidden=true;renderTypeOptions();}
function showMaterial(){const root=$('materialInfo');root.replaceChildren();const m=materials.find(x=>String(x.id)===$('material').value);if(!m)return;if(!m.tipo_id){root.append(el('div','text-warning','Material anterior sin tipo asignado.'));const b=el('button','btn btn-sm btn-outline-primary mt-2','Clasificar material existente');b.onclick=()=>{resetMaterialForm();classifying=Number(m.id);$('materialFormWrap').open=true;$('saveMaterial').textContent='Asignar tipo al material existente';$('cancelClassify').hidden=false;};root.append(b);return;}for(const [k,v] of [['Tipo',m.tipo],['Identificación',m.identificacion],['Procedencia',m.procedencia],['Proveedor',m.proveedor],['Observaciones',m.observaciones]])if(v)root.append(el('div','',k+': '+v));}
$('typeSearch').oninput=renderTypeOptions;$('cancelType').onclick=resetType;$('cancelClassify').onclick=resetMaterialForm;
async function loadMaterials(){const n=++revision;materials=[];resetMaterialForm();options('material',[],x=>x.nombre,'Seleccionar material');showMaterial();subjects={muestras:[],puntos:[]};renderSubjects();renderResults([],'historical');if(!$('work').value)return;const r=await api('/laboratorio/materiales?obra_id='+chosen('work'));if(n!==revision)return;materials=r.materiales;options('material',materials,x=>x.nombre+(x.tipo_id?'':' · Sin clasificar'),'Seleccionar material');}
async function loadSubjects(){showMaterial();const n=++revision;subjects={muestras:[],puntos:[]};renderSubjects();renderResults([],'historical');if(!$('material').value)return;const m=chosen('material');const [a,b]=await Promise.all([api('/laboratorio/sujetos?material_id='+m),api('/laboratorio/resultados?material_id='+m)]);if(n!==revision)return;subjects=a;renderSubjects();renderResults(b.resultados,'historical');}
function pane(name){
 if(['materials','samples','results'].includes(name)&&!currentOT){notice('Abre primero una orden de trabajo.');return;}
 if(['samples','results'].includes(name)&&!$('material').value&&name==='samples'){notice('Selecciona o registra un material primero.');name='materials';}
 for(const s of document.querySelectorAll('.lab-section'))s.hidden=s.id!=='pane-'+name;
 for(const b of document.querySelectorAll('[data-pane]')){const active=b.dataset.pane===name;b.classList.toggle('btn-primary',active);b.setAttribute('aria-current',active?'page':'false');}
 $('selectedOrder').hidden=!currentOT||['inbox','settings'].includes(name);
 const m=materials.find(x=>String(x.id)===$('material').value);
 $('sampleMaterial').textContent=m?'Material seleccionado: '+m.nombre:'Sin material seleccionado.';
 if(name==='results'){renderTasks();loadOrderResults().catch(e=>notice(e.message));}
}
function renderOrders(){
 const q=norm($('orderSearch').value),root=$('orders');root.replaceChildren();
 const filtered=orders.filter(o=>norm([o.numero_ot,o.cliente,o.obra].join(' ')).includes(q));
 const metric=$('metrics');metric.replaceChildren();
 for(const [label,n] of [['OT del período',orders.length],['Planificadas',orders.filter(o=>o.estado==='planificada').length],['En curso',orders.filter(o=>o.estado==='en_curso').length],['Finalizadas (OT)',orders.filter(o=>o.estado==='finalizada').length]]){const col=el('div','col-6 col-lg-3');const card=el('div','card card-sm');const b=el('div','card-body');b.append(el('div','text-secondary small',label),el('div','lab-metric',String(n)));card.append(b);col.append(card);metric.append(col);}
 if(!filtered.length)root.append(el('div','list-group-item text-secondary','No se encontraron órdenes en este período.'));
 const states={planificada:'Planificada',en_curso:'En curso',finalizada:'Finalizada'};
 for(const o of filtered){const row=el('div','list-group-item lab-order');const top=el('div','d-flex align-items-center justify-content-between gap-2 flex-wrap');top.append(el('strong','','OT '+(o.numero_ot||'Sin número')),el('span','text-secondary small',states[o.estado]||o.estado));row.append(top,el('div','fw-semibold mt-1',o.obra||'Sin obra'),el('div','text-secondary small',o.cliente||''),el('div','small text-secondary mt-1',String(o.inicio||'').slice(0,10)));
 for(const s of o.servicios||[])row.append(el('div','small',s.nombre+' · '+s.cantidad+' '+s.unidad));
 if(!(o.servicios||[]).length)row.append(el('div','small text-warning','Sin servicios registrados.'));
 const b=el('button','btn btn-outline-primary btn-sm mt-2 order-action','Abrir trabajo');b.onclick=()=>openOrder(o.id).catch(e=>notice(e.message));row.append(b);root.append(row);}
}
async function loadOrders(){const token=++orderRequest;if(!$('from').value||!$('to').value||$('from').value>$('to').value)throw Error('Selecciona un rango válido.');const r=await api('/ot?'+new URLSearchParams({desde:$('from').value,hasta:$('to').value}));if(token!==orderRequest)return;orders=(r.ordenes||[]).filter(o=>o.estado!=='cancelada');renderOrders();}
async function openOrder(id){
 const token=++opening;const r=await api('/ot/'+id);if(token!==opening)return;
 const o=r.orden;if(!o||o.estado==='cancelada'||!o.obra_id)throw Error('La OT está cancelada o no tiene una obra vinculada.');
 currentOT=o;task=null;$('captureWrap').hidden=true;options('work',[{id:o.obra_id,nombre:o.obra}],x=>x.nombre,'Obra');$('work').value=o.obra_id;$('otId').value=o.id;
 $('workContext').textContent=o.cliente+' · '+o.obra;$('pointContext').textContent='Asociado automáticamente a OT '+o.numero_ot;
 const ctx=$('selectedOrder');ctx.replaceChildren(el('div','fw-bold','OT '+o.numero_ot),el('div','small',o.cliente+' · '+o.obra));const back=el('button','btn btn-sm btn-outline-secondary mt-2','Volver a bandeja');back.onclick=()=>pane('inbox');ctx.append(back);
 await loadMaterials();if(token!==opening)return;pane('materials');
}
function renderTasks(){
 const root=$('tasks');root.replaceChildren();if(!currentOT)return;
 const names=Object.fromEntries((config.ensayos||[]).map(c=>[c.codigo,c]));
 const map=config.servicio_ensayos||{};
 for(const service of currentOT.servicios||[]){const title=el('div','list-group-item bg-light');title.append(el('strong','',service.nombre),el('div','small text-secondary','Contratado: '+service.cantidad+' '+service.unidad));root.append(title);
 const groups=service.ensayos||[];const codes=[...new Set(groups.flatMap(g=>map[g]||[]))];
 if(!codes.length)root.append(el('div','list-group-item small text-secondary',groups.includes('uscs')?'Clasificación: resultado derivado; motor pendiente.':'Sin ensayos vinculados. Configura este servicio en Servicios.'));
 if(groups.includes('proctor')||groups.includes('dens'))root.append(el('div','list-group-item small text-secondary','Selecciona únicamente la variante técnicamente indicada; las opciones no representan ensayos adicionales contratados.'));
 for(const code of codes){const c=names[code];if(!c)continue;const row=el('div','list-group-item lab-task');const info=el('div');info.append(el('strong','',c.nombre),el('div','small text-secondary',Number(c.captura_piloto)?'Captura parcial disponible':'Formulario técnico pendiente'));row.append(info);if(Number(c.captura_piloto)){const b=el('button','btn btn-sm btn-outline-primary','Registrar');b.onclick=()=>startTask(service,code);row.append(b);}root.append(row);}
 }
 if(!(currentOT.servicios||[]).length)root.append(el('div','list-group-item text-secondary','No hay servicios en esta OT.'));
}
function startTask(service,code){
 if(!$('material').value){notice('Selecciona un material antes de registrar el ensayo.');pane('materials');return;}
 const candidates=['S14','S15'].includes(code)?subjects.puntos.filter(p=>String(p.ot_id)===String(currentOT.id)):subjects.muestras;
 if(!candidates.length){notice('Registra primero '+(['S14','S15'].includes(code)?'un punto de terreno':'una muestra')+' para el material.');pane('samples');return;}
 task={service,code};options('test',[{codigo:code}],x=>x.codigo,'Ensayo');$('test').value=code;renderFields();
 const label=(config.ensayos||[]).find(c=>c.codigo===code)?.nombre||code;
 $('taskContext').replaceChildren(el('h3','h4',label),el('div','small text-secondary',service.nombre+' · OT '+currentOT.numero_ot));
 $('captureWrap').hidden=false;$('captureWrap').scrollIntoView({block:'start',behavior:'smooth'});notice('');
}
async function loadOrderResults(){if(!currentOT)return;const id=currentOT.id;const r=await api('/laboratorio/resultados-ot?ot_id='+id);if(String(currentOT?.id)===String(id))renderResults(r.resultados);}

function bind(id,fn){$(id).addEventListener('submit',async e=>{e.preventDefault();const b=e.submitter;if(b)b.disabled=true;try{await fn();notice('Guardado correctamente.',true);}catch(err){notice(err.message);}finally{if(b)b.disabled=false;}});}
for(const b of document.querySelectorAll('[data-pane],[data-go]'))b.onclick=()=>pane(b.dataset.pane||b.dataset.go);
$('reloadOrders').onclick=()=>loadOrders().catch(e=>notice(e.message));$('orderSearch').oninput=renderOrders;
$('material').onchange=()=>{task=null;$('captureWrap').hidden=true;resetMaterialForm();loadSubjects().catch(e=>notice(e.message));};

bind('typeForm',async()=>{const data={nombre:$('typeName').value,activo:$('typeActive').checked};if(editingType===null)await api('/laboratorio/tipos-material',data);else await api('/laboratorio/tipos-material/'+editingType,data,'PUT');resetType();await loadTypes();});
bind('newMaterial',async()=>{const data={tipo_id:chosen('materialType'),identificacion:$('materialName').value,procedencia:$('source').value,proveedor:$('supplier').value,observaciones:$('materialNotes').value};const r=classifying===null?await api('/laboratorio/materiales',{...data,obra_id:chosen('work')}):await api('/laboratorio/materiales/clasificar',{...data,material_id:classifying});resetMaterialForm();await loadMaterials();$('material').value=r.id;await loadSubjects();$('materialFormWrap').open=false;});
bind('newSample',async()=>{const r=await api('/laboratorio/muestras',{material_id:chosen('material'),codigo:$('sampleCode').value,fecha:$('sampleDate').value});$('sampleCode').value='';await loadSubjects();if(!fieldTest())$('subject').value=r.id;});
bind('newPoint',async()=>{const r=await api('/laboratorio/puntos',{material_id:chosen('material'),codigo:$('pointCode').value,ot_id:chosen('otId')});$('pointCode').value='';await loadSubjects();if(fieldTest())$('subject').value=r.id;});
bind('capture',async()=>{if(!currentOT||!task)throw Error('Selecciona un ensayo de la OT.');chosen('material');const subject=chosen('subject');const valores=Object.fromEntries(Array.from($('fields').querySelectorAll('input'),i=>[i.dataset.key,i.value.trim()]));await api('/laboratorio/resultados',{ot_id:Number(currentOT.id),servicio_id:Number(task.service.servicio_id),ensayo_codigo:task.code,fecha:$('date').value,...(fieldTest()?{punto_id:subject}:{muestra_id:subject}),valores});await loadSubjects();await loadOrderResults();$('captureWrap').hidden=true;task=null;});
const d=new Date(),today=[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');$('date').value=$('sampleDate').value=today;$('from').value=today.slice(0,7)+'-01';$('to').value=today;
(async()=>{try{const s=await api('/session',{});if(!s.autenticado||!['administrador','administrativo'].includes(s.rol))throw Error('Acceso administrativo requerido');csrf=s.csrf;config=await api('/laboratorio/catalogo');await loadTypes();options('test',config.ensayos.filter(x=>Number(x.captura_piloto)),x=>x.codigo+' · '+x.nombre,'Seleccionar ensayo');for(const x of config.ensayos)$('catalog').append(el('div','list-group-item',x.codigo+' · '+x.nombre+' — '+(Number(x.captura_piloto)?'Captura parcial':'Catalogado')));for(const x of config.requisitos){const row=el('div','list-group-item');row.append(el('strong','',x.codigo+' · '+x.nombre),el('div','small',x.requisitos),el('div','small text-secondary',x.condicion_tecnica));$('rules').append(row);} $('workspace').hidden=false;pane('inbox');await loadOrders();}catch(e){notice(e.message);}})();
})();