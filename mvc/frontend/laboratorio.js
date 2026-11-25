(()=>{'use strict';
const $=id=>document.getElementById(id),el=(tag,cls='',txt='')=>{const n=document.createElement(tag);n.className=cls;n.textContent=txt;return n;};
let csrf='',config={},subjects={muestras:[],puntos:[]},revision=0,types=[],materials=[],editingType=null,classifying=null,orders=[],currentOT=null,task=null,orderRequest=0,opening=0,taskState={tareas:[],avisos:[],bloqueados:[]},summary={},pendingTask=null,activePane='inbox',standalone=false,referenceRequest=0,materialLoading=false;
const standaloneWork=new URLSearchParams(location.search).get('obra');
const AREA=new URLSearchParams(location.search).get('area')==='terreno'?'terreno':'laboratorio';
const isField=AREA==='terreno';
const areaGroups=new Set(['dens','dens_nuclear','dens_cono','cal','por']);
function areaServices(o){return (o.servicios||[]).filter(s=>(s.ensayos||[]).some(g=>isField?areaGroups.has(g):!areaGroups.has(g)));}
function notice(t,ok=false){const target=activePane==='results'?'inlineNotice':'notice';for(const id of ['notice','inlineNotice']){$(id).textContent='';$(id).hidden=true;}const n=$(target);n.className='alert '+(ok?'alert-success':'alert-warning');n.textContent=t;n.hidden=!t;}
async function api(path,body,method='POST'){const opt={credentials:'same-origin',cache:'no-store'};if(body!==undefined){opt.method=method;opt.headers={'Content-Type':'application/json','X-CSRF-Token':csrf};opt.body=JSON.stringify({...body,csrf});}const r=await fetch('./api'+path,opt);const v=await r.json();if(!r.ok)throw Error(v.error||'Error de servidor');return v;}
function options(id,rows,label,placeholder){const s=$(id);s.replaceChildren();const empty=el('option','',placeholder);empty.value='';s.append(empty);for(const r of rows){const o=el('option','',label(r));o.value=r.id??r.codigo;s.append(o);}}
function chosen(id){const v=Number($(id).value);if(!Number.isInteger(v)||v<1)throw Error('Selecciona '+id);return v;}
function fieldTest(){return ['S14','S15'].includes($('test').value);}
function renderFields(){const root=$('fields');root.replaceChildren();for(const [key,label] of config.campos?.[$('test').value]||[]){const col=el('div','col-12 col-md-6');const l=el('label','form-label',label),i=el('input','form-control');i.id='lab-'+key;l.htmlFor=i.id;i.required=true;i.inputMode='decimal';i.dataset.key=key;col.append(l,i);root.append(col);}renderSubjects();}
function renderSubjects(){const keep=$('activeSample').value||$('subject').value;options('activeSample',subjects.muestras,r=>r.codigo,'Seleccionar muestra');options('subject',subjects.muestras,r=>r.codigo,'Seleccionar muestra');if(keep&&subjects.muestras.some(s=>String(s.id)===keep)){$('subject').value=keep;$('activeSample').value=keep;}else if(subjects.muestras.length===1){$('subject').value=subjects.muestras[0].id;$('activeSample').value=subjects.muestras[0].id;}$('sampleInput').hidden=isField;$('pointInput').hidden=!isField;$('subject').required=!isField;$('pointCapture').required=isField;}
function renderResults(rows,target='results'){const root=$(target);root.replaceChildren();if(!rows.length)root.append(el('p','p-3 text-secondary','Sin resultados registrados.'));for(const r of rows){const item=el('div','list-group-item');item.append(el('div','fw-bold',r.ensayo_codigo+' · '+r.nombre),el('div','small text-secondary',r.identificacion+' · '+r.fecha+' · '+r.estado+' · v'+r.version));if(r.servicio_nombre)item.append(el('div','small',r.servicio_nombre));for(const [k,v] of Object.entries(r.datos.resultados||{}))item.append(el('div','small',k+': '+Number(v).toLocaleString('es-CL',{maximumFractionDigits:6})));root.append(item);}}
const norm=v=>String(v).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
function renderTypeOptions(){const keep=$('materialType').value;const q=norm($('typeSearch').value);options('materialType',types.filter(t=>Number(t.activo)&&(norm(t.nombre).includes(q)||String(t.id)===keep)),t=>t.nombre,'Seleccionar tipo');$('materialType').value=keep;}
function resetType(){editingType=null;$('typeForm').reset();$('typeActiveWrap').hidden=true;$('cancelType').hidden=true;$('saveType').textContent='Agregar tipo';}
function renderTypes(){const root=$('typesList');root.replaceChildren();for(const t of types){const row=el('div','list-group-item d-flex align-items-center justify-content-between gap-2');const left=el('div');left.append(el('div','fw-semibold',t.nombre),el('small','text-secondary',Number(t.activo)?'Activo':'Inactivo'));const b=el('button','btn btn-sm btn-outline-secondary','Editar');b.type='button';b.onclick=()=>{editingType=Number(t.id);$('typeName').value=t.nombre;$('typeActive').checked=!!Number(t.activo);$('typeActiveWrap').hidden=false;$('cancelType').hidden=false;$('saveType').textContent='Guardar cambios';$('typeName').focus();};row.append(left,b);root.append(row);}renderTypeOptions();}
async function loadTypes(){types=(await api('/laboratorio/tipos-material')).tipos;renderTypes();}
function resetMaterialForm(){classifying=null;$('newMaterial').reset();$('saveMaterial').textContent='Registrar material';$('cancelClassify').hidden=true;renderTypeOptions();}
function showMaterial(){const root=$('materialInfo');root.replaceChildren();const m=materials.find(x=>String(x.id)===$('material').value);if(!m)return;if(!m.tipo_id){root.append(el('div','text-warning','Material anterior sin tipo asignado.'));const b=el('button','btn btn-sm btn-outline-primary mt-2','Clasificar material existente');b.onclick=()=>{resetMaterialForm();classifying=Number(m.id);$('materialFormWrap').open=true;$('saveMaterial').textContent='Asignar tipo al material existente';$('cancelClassify').hidden=false;};root.append(b);return;}for(const [k,v] of [['Tipo',m.tipo],['Identificación',m.identificacion],['Procedencia',m.procedencia],['Proveedor',m.proveedor],['Observaciones',m.observaciones]])if(v)root.append(el('div','',k+': '+v));}
$('typeSearch').oninput=renderTypeOptions;$('cancelType').onclick=resetType;$('cancelClassify').onclick=resetMaterialForm;
async function loadMaterials(){
 const n=++revision;materials=[];resetMaterialForm();options('material',[],x=>x.nombre,'Seleccionar material');showMaterial();subjects={muestras:[],puntos:[]};renderSubjects();renderResults([],'historical');$('materialHint').textContent='Cargando materiales de la obra…';$('referenceInfo').textContent='Selecciona el material para consultar sus antecedentes.';
 if(!$('work').value)return;const r=await api('/laboratorio/materiales?obra_id='+chosen('work'));if(n!==revision)return;
 materials=r.materiales;options('material',materials,x=>x.nombre+(x.tipo_id?'':' · Sin clasificar'),'Seleccionar material');
 $('materialHint').textContent=materials.length?'Selecciona el material que realmente corresponde al trabajo.':'Esta obra aún no tiene materiales registrados. Agrega el material una vez y podrás reutilizarlo en otras OT.';
 $('materialFormWrap').open=!materials.length;
}
async function loadReference(){
 const n=++referenceRequest;const root=$('referenceInfo');root.textContent='Selecciona el material para consultar sus antecedentes.';
 if(!isField||standalone||!$('material').value)return;
 root.textContent='Consultando antecedentes…';const m=chosen('material');
 try{const r=await api('/laboratorio/estudios-material?material_id='+m);if(n!==referenceRequest)return;root.replaceChildren();
 if(!r.estudios.length){root.append(el('div','fw-semibold text-warning','Sin estudios registrados para este material'));return;}
 for(const s of r.estudios){const origin={propio:'Controlab',cliente:'Cliente',proveedor:'Proveedor externo'}[s.origen]||s.origen;
 const item=el('div','border rounded p-2 mb-2');item.append(el('strong','',s.codigo+' · '+origin),el('div','small text-secondary','Estado del estudio: '+s.estado));
 if(s.metodo)item.append(el('div','small','Referencia '+(s.metodo==='proctor'?'Proctor':'densidad relativa')+' · v'+s.version+' · '+s.referencia_estado));root.append(item);}
 }catch(e){if(n===referenceRequest)root.textContent='No se pudieron consultar los estudios: '+e.message;}
}
async function loadSubjects(){
 showMaterial();const n=++revision;subjects={muestras:[],puntos:[]};options('activeSample',[],x=>x.codigo,'Seleccionar muestra');renderSubjects();renderResults([],'historical');
 $('sampleMaterial').textContent=$('material').value?'Selecciona una muestra existente o registra la recibida.':'Selecciona primero el material.';
 loadReference();if(!$('material').value){$('sampleWrap').open=false;return;}
 const m=chosen('material');materialLoading=true;
 try{const [a,b]=await Promise.all([api('/laboratorio/sujetos?material_id='+m),api('/laboratorio/resultados?material_id='+m)]);if(n!==revision)return;subjects=a;renderSubjects();renderResults(b.resultados,'historical');$('sampleWrap').open=!subjects.muestras.length&&!isField&&!standalone;}
 finally{if(n===revision)materialLoading=false;}
}
function pane(name){
 if(name==='results'&&!currentOT&&!standalone){notice('Abre primero una orden de trabajo.');return;}
 activePane=name;notice('');for(const s of document.querySelectorAll('.lab-section'))s.hidden=s.id!=='pane-'+name;
 for(const b of document.querySelectorAll('[data-pane]')){const active=b.dataset.pane===name;b.classList.toggle('btn-primary',active);b.setAttribute('aria-current',active?'page':'false');}
 $('selectedOrder').hidden=name!=='results';$('samplesSection').hidden=isField||standalone;$('referenceCard').hidden=!isField||standalone;$('taskSection').hidden=standalone;
 if(name==='results'&&!standalone){renderTasks();loadOrderResults().catch(e=>notice(e.message));}
}
function goTo(id){$(id).scrollIntoView({block:'start',behavior:'smooth'});}
function resumeTask(){if(pendingTask&&!materialLoading&&$('material').value&&(isField||$('activeSample').value)){const t=pendingTask;pendingTask=null;startTask(t);}}
function renderOrders(){
 const q=norm($('orderSearch').value),root=$('orders');root.replaceChildren();
 const relevant=orders.filter(o=>areaServices(o).length||summary[o.id]?.[AREA]);
 const filtered=relevant.filter(o=>norm([o.numero_ot,o.cliente,o.obra].join(' ')).includes(q));
 const totals=relevant.reduce((a,o)=>{const v=summary[o.id]?.[AREA];if(v){a.total+=v.total;a.done+=v.registrados;}else a.unprepared++;return a;},{total:0,done:0,unprepared:0});
 const metric=$('metrics');metric.replaceChildren();
 for(const [label,n] of [['OT del área',relevant.length],['Por preparar (OT)',totals.unprepared],['Tareas registradas',totals.done],['Tareas por registrar',totals.total-totals.done]]){const col=el('div','col-6 col-lg-3');const card=el('div','card card-sm');const b=el('div','card-body');b.append(el('div','text-secondary small',label),el('div','lab-metric',String(n)));card.append(b);col.append(card);metric.append(col);}
 if(!filtered.length)root.append(el('div','list-group-item text-secondary','No hay trabajos de esta área en el período.'));
 for(const o of filtered){const row=el('div','list-group-item lab-order');const top=el('div','d-flex align-items-center justify-content-between gap-2 flex-wrap');top.append(el('strong','',o.numero_ot||'OT sin número'),el('span','text-secondary small',String(o.inicio||'').slice(0,10)));row.append(top,el('div','fw-semibold mt-1',o.obra||'Sin obra'),el('div','text-secondary small',o.cliente||''));
 for(const s of areaServices(o))row.append(el('div','small mt-1',s.nombre+' · '+s.cantidad+' '+s.unidad));
 const v=summary[o.id]?.[AREA];row.append(el('div','small fw-semibold mt-2',v?v.registrados+' de '+v.total+' tareas registradas':'Tareas por preparar'));
 const b=el('button','btn btn-outline-primary btn-sm mt-2 order-action','Abrir trabajo');b.onclick=()=>openOrder(o.id).catch(e=>notice(e.message));row.append(b);root.append(row);}
 const missing=orders.filter(o=>!(o.servicios||[]).length||(o.servicios||[]).some(s=>!(s.ensayos||[]).length));
 if(missing.length){const d=el('details','list-group-item');d.append(el('summary','text-warning',missing.length+' OT con servicios sin configurar'));for(const o of missing)d.append(el('div','small mt-2',(o.numero_ot||'OT')+' · '+o.obra+' — revisar Servicios / OT'));root.append(d);}
}
async function loadOrders(){const token=++orderRequest;if(!$('from').value||!$('to').value||$('from').value>$('to').value)throw Error('Selecciona un rango válido.');const r=await api('/ot?'+new URLSearchParams({desde:$('from').value,hasta:$('to').value}));if(token!==orderRequest)return;orders=(r.ordenes||[]).filter(o=>o.estado!=='cancelada');const ids=orders.map(o=>o.id).join(',');const s=await api('/laboratorio/tareas/resumen?ids='+encodeURIComponent(ids));if(token!==orderRequest)return;summary=s.resumen||{};renderOrders();}
async function openOrder(id){
 const token=++opening;const r=await api('/ot/'+id);if(token!==opening)return;
 const o=r.orden;if(!o||o.estado==='cancelada'||!o.obra_id)throw Error('La OT está cancelada o no tiene una obra vinculada.');
 currentOT=o;task=null;pendingTask=null;standalone=false;taskState={tareas:[],avisos:[],bloqueados:[]};$('captureWrap').hidden=true;options('work',[{id:o.obra_id,nombre:o.obra}],x=>x.nombre,'Obra');$('work').value=o.obra_id;
 $('workContext').textContent=o.cliente+' · '+o.obra;
 const ctx=$('selectedOrder');ctx.replaceChildren(el('div','fw-bold',o.numero_ot||'OT sin número'),el('div','small',o.cliente+' · '+o.obra));for(const s of areaServices(o))ctx.append(el('div','small mt-1',s.nombre+' · '+s.cantidad+' '+s.unidad));const back=el('button','btn btn-sm btn-outline-secondary mt-2','Volver a bandeja');back.onclick=()=>{pendingTask=null;task=null;$('captureWrap').hidden=true;pane('inbox');};ctx.append(back);
 const state=await api('/laboratorio/tareas/preparar',{ot_id:Number(o.id)});if(token!==opening)return;taskState=state;updateSummary();await loadMaterials();if(token!==opening)return;pane('results');goTo('selectedOrder');
}
const groupNames={dens:'Densidad',dens_nuclear:'Densidad nuclear',dens_cono:'Cono de arena',proctor:'Proctor',proctor_normal:'Proctor normal',proctor_modificado:'Proctor modificado',ll:'Límite líquido',lp:'Límite plástico',gran:'Granulometría',dmin:'Densidad mínima',dmax:'Densidad máxima',uscs:'Clasificación USCS',cal:'Calicata',por:'Infiltración'};
function updateSummary(){if(!currentOT)return;const row={};for(const a of ['laboratorio','terreno']){const tasks=taskState.tareas.filter(t=>t.area===a);if(tasks.length)row[a]={total:tasks.length,registrados:tasks.filter(t=>t.ejecucion_id!==null).length};}summary[currentOT.id]=row;renderOrders();}
function renderTasks(){
 const root=$('tasks'),sum=$('taskSummary');root.replaceChildren();sum.replaceChildren();if(!currentOT)return;
 const tasks=taskState.tareas.filter(t=>t.area===AREA),done=tasks.filter(t=>t.ejecucion_id!==null).length;
 sum.append(el('div','fw-bold',done+' de '+tasks.length+' tareas registradas'),el('div','small text-secondary',(tasks.length-done)+' por registrar · borradores, no aprobados'));
 const bar=el('div','progress mt-2');const fill=el('div','progress-bar');fill.style.width=(tasks.length?done/tasks.length*100:0)+'%';bar.append(fill);sum.append(bar);
 for(const a of taskState.avisos||[])sum.append(el('div','small text-warning mt-2',a));
 if(!tasks.length){root.append(el('div','list-group-item text-secondary','No hay tareas de esta área. Revisa los servicios y su unidad de cobro.'));return;}
 const names=Object.fromEntries((config.ensayos||[]).map(c=>[c.codigo,c]));let previous='';
 for(const t of tasks){
 const group=t.servicio_id+':'+t.grupo;if(group!==previous){root.append(el('div','list-group-item bg-light fw-semibold',[...new Set([t.servicio_nombre,groupNames[t.grupo]||names[t.alternativas]?.nombre||t.grupo])].join(' · ')));previous=group;}
 const row=el('div','list-group-item lab-task');const info=el('div');const done=t.ejecucion_id!==null;
 info.append(el('strong','',(isField?'Control ':'Ensayo ')+t.ordinal),el('div','small '+(done?'text-success':'text-secondary'),done?'Resultado registrado':'Pendiente de registro'));row.append(info);
 const codes=t.alternativas.split(',').filter(c=>config.campos?.[c]);
 if(!done&&codes.length&&!taskState.cambio&&!(taskState.bloqueados||[]).map(Number).includes(Number(t.servicio_id))){const b=el('button','btn btn-sm btn-outline-primary','Registrar');b.onclick=()=>startTask(t);row.append(b);}else if(!done&&!codes.length)row.append(el('span','small text-secondary','Formulario pendiente'));
 root.append(row);}
}
function startTask(t){
 if(materialLoading){notice('Espera a que se carguen las muestras del material.');return;}
 if(!$('material').value){pendingTask=t;notice('Selecciona o agrega el material de este control. Después continuarás automáticamente.');goTo('materialSection');return;}
 if(!isField&&!$('activeSample').value){pendingTask=t;notice('Selecciona o recibe la muestra que se va a ensayar.');goTo('samplesSection');return;}
 pendingTask=null;task=t;const codes=t.alternativas.split(',').filter(c=>config.campos?.[c]);const names=Object.fromEntries(config.ensayos.map(c=>[c.codigo,c.nombre]));
 options('test',codes.map(c=>({codigo:c,nombre:names[c]})),x=>x.nombre,'Seleccionar método');if(codes.length===1)$('test').value=codes[0];$('test').disabled=codes.length===1;renderFields();
 const m=materials.find(x=>String(x.id)===$('material').value);$('captureMaterial').textContent='Material: '+(m?.nombre||'');
 $('pointCapture').value='';$('subject').value=$('activeSample').value;
 $('taskContext').replaceChildren(el('h3','h4',t.servicio_nombre+' · '+(isField?'Control ':'Ensayo ')+t.ordinal),el('div','small text-secondary',currentOT.numero_ot));
 $('captureWrap').hidden=false;densityPreview();goTo('captureWrap');notice('');
}
function densityPreview(){
 const root=$('densityPreview');root.hidden=true;if(!fieldTest())return;
 const a=document.getElementById('lab-dch'),b=document.getElementById('lab-humedad');if(!a||!b||!a.value.trim()||!b.value.trim())return;
 const d=Number(a.value.replace(',','.')),h=Number(b.value.replace(',','.'));if(!Number.isFinite(d)||!Number.isFinite(h)||d<=0||h<0)return;
 root.textContent='Densidad seca estimada: '+(d/(1+h/100)).toLocaleString('es-CL',{minimumFractionDigits:3,maximumFractionDigits:3})+' g/cm³ · sin evaluación de compactación';root.hidden=false;
}
async function refreshTasks(){if(!currentOT)return;const id=currentOT.id;const s=await api('/laboratorio/tareas?ot_id='+id);if(String(currentOT?.id)!==String(id))return;taskState=s;updateSummary();renderTasks();}
async function loadOrderResults(){if(!currentOT||standalone)return;const id=currentOT.id;const r=await api('/laboratorio/resultados-ot?ot_id='+id);if(String(currentOT?.id)===String(id))renderResults(r.resultados.filter(x=>isField?['S14','S15','S16','S17'].includes(x.ensayo_codigo):!['S14','S15','S16','S17'].includes(x.ensayo_codigo)));}

function bind(id,fn){$(id).addEventListener('submit',async e=>{e.preventDefault();const b=e.submitter;if(b)b.disabled=true;try{await fn();notice('Guardado correctamente.',true);}catch(err){notice(err.message);}finally{if(b)b.disabled=false;}});}
for(const b of document.querySelectorAll('[data-pane],[data-go]'))b.onclick=()=>pane(b.dataset.pane||b.dataset.go);
$('reloadOrders').onclick=()=>loadOrders().catch(e=>notice(e.message));$('orderSearch').oninput=renderOrders;
$('material').onchange=()=>{task=null;$('captureWrap').hidden=true;resetMaterialForm();$('materialFormWrap').open=false;loadSubjects().then(resumeTask).catch(e=>notice(e.message));};
$('activeSample').onchange=()=>{$('subject').value=$('activeSample').value;resumeTask();};$('subject').onchange=()=>{$('activeSample').value=$('subject').value;};
$('cancelCapture').onclick=()=>{task=null;pendingTask=null;$('captureWrap').hidden=true;notice('');goTo('taskSection');};$('fields').oninput=densityPreview;

bind('typeForm',async()=>{const data={nombre:$('typeName').value,activo:$('typeActive').checked};if(editingType===null)await api('/laboratorio/tipos-material',data);else await api('/laboratorio/tipos-material/'+editingType,data,'PUT');resetType();await loadTypes();});
bind('newMaterial',async()=>{const data={tipo_id:chosen('materialType'),identificacion:$('materialName').value,procedencia:$('source').value,proveedor:$('supplier').value,observaciones:$('materialNotes').value};const r=classifying===null?await api('/laboratorio/materiales',{...data,obra_id:chosen('work')}):await api('/laboratorio/materiales/clasificar',{...data,material_id:classifying});resetMaterialForm();await loadMaterials();$('material').value=r.id;await loadSubjects();$('materialFormWrap').open=false;resumeTask();});
bind('newSample',async()=>{const r=await api('/laboratorio/muestras',{material_id:chosen('material'),codigo:$('sampleCode').value,fecha:$('sampleDate').value});$('sampleCode').value='';await loadSubjects();$('activeSample').value=r.id;$('subject').value=r.id;$('sampleWrap').open=false;resumeTask();});

bind('capture',async()=>{if(!currentOT||!task)throw Error('Selecciona una tarea de la OT.');const material=chosen('material'),code=$('test').value;if(!code)throw Error('Selecciona el método de ensayo.');const valores=Object.fromEntries(Array.from($('fields').querySelectorAll('input'),i=>[i.dataset.key,i.value.trim()]));await api('/laboratorio/resultados',{ot_id:Number(currentOT.id),tarea_id:Number(task.id),material_id:material,ensayo_codigo:code,fecha:$('date').value,...(isField?{punto_codigo:$('pointCapture').value}:{muestra_id:chosen('subject')}),valores});$('captureWrap').hidden=true;task=null;await loadSubjects();await refreshTasks();await loadOrderResults();goTo('taskSection');});
$('test').onchange=()=>{renderFields();densityPreview();};
$('areaTitle').textContent=isField?'Controles en terreno':'Laboratorio';
$('otherArea').href=isField?'./laboratorio.html':'./laboratorio.html?area=terreno';$('otherArea').textContent=isField?'Laboratorio':'Controles en terreno';
$('tasksTitle').textContent='3. '+(isField?'Controles solicitados':'Ensayos solicitados');

async function openStandalone(id){
 if(!/^\d+$/.test(id)||Number(id)<1)throw Error('Obra inválida.');
 standalone=true;currentOT=null;task=null;pendingTask=null;$('areaTitle').textContent='Materiales de obra';$('labNav').hidden=true;
 const q=new URLSearchParams(location.search);const name=q.get('nombre')||('Obra '+id);
 options('work',[{id,nombre:name}],x=>x.nombre,'Obra');$('work').value=id;
 const ctx=$('selectedOrder');ctx.replaceChildren(el('div','fw-bold',name));const back=el('a','btn btn-sm btn-outline-secondary mt-2','Volver a empresas y obras');back.href='./empresas.html';ctx.append(back);
 await loadMaterials();pane('results');
}
const d=new Date(),today=[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');$('date').value=$('sampleDate').value=today;$('from').value=today.slice(0,7)+'-01';$('to').value=today;
(async()=>{try{const s=await api('/session',{});if(!s.autenticado||!['administrador','administrativo'].includes(s.rol))throw Error('Acceso administrativo requerido');csrf=s.csrf;config=await api('/laboratorio/catalogo');await loadTypes();options('test',config.ensayos.filter(x=>Number(x.captura_piloto)),x=>x.codigo+' · '+x.nombre,'Seleccionar ensayo');for(const x of config.ensayos)$('catalog').append(el('div','list-group-item',x.codigo+' · '+x.nombre+' — '+(Number(x.captura_piloto)?'Captura parcial':'Catalogado')));for(const x of config.requisitos){const row=el('div','list-group-item');row.append(el('strong','',x.codigo+' · '+x.nombre),el('div','small',x.requisitos),el('div','small text-secondary',x.condicion_tecnica));$('rules').append(row);} $('workspace').hidden=false;if(standaloneWork){await openStandalone(standaloneWork);}else{pane('inbox');await loadOrders();}}catch(e){notice(e.message);}})();
})();