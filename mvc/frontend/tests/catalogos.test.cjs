'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const panel=fs.readFileSync('mvc/frontend/catalogos.html','utf8');
const planner=fs.readFileSync('mvc/frontend/planificador.html','utf8');
const model=fs.readFileSync('mvc/backend/src/Models/OrdenTrabajo.php','utf8');
const routes=fs.readFileSync('mvc/backend/routes/api.php','utf8');
const access=fs.readFileSync('mvc/backend/src/Controllers/CatalogoOperativoController.php','utf8');
const deploy=fs.readFileSync('scripts/cpanel_mvc_deploy.sh','utf8');
for(const id of ['empresaForm','obraForm','personalForm','obraEmpresa','obraNombre','obraDireccion','obraComuna','personalNombre','personalCargo','geoSearch','obraMap','geoSaveBtn','geoRouteBtn','obraActivo','personalActivo']){
 assert.ok(panel.includes('id="'+id+'"'),'Falta control de catálogos '+id);
}
for(const id of ['empresaCatalogo','obraCatalogo','fieldChoices','teamChoices','catalogLink']){
 assert.ok(planner.includes('id="'+id+'"'),'Falta control OT '+id);
}
for(const path of ['/api/catalogo/empresas','/api/catalogo/obras','/api/catalogo/personal']){
 assert.ok(routes.includes(path),'Falta ruta '+path);
}
for(const action of ['empresas','crearEmpresa','obras','crearObra','editarObra','personal','crearPersonal','editarPersonal']){
 const i=access.indexOf('function '+action+'(');
 assert.ok(i>0,action+' no definido');
 assert.ok(access.slice(i,i+210).includes("Security::requireRole('administrador','administrativo')"),'Falta permisos en '+action);
}
assert.ok(model.includes('ot_personal_terreno'),'OT no guarda personal sin usuario');
assert.ok(model.includes('ot_obra_vinculos'),'OT no vincula obra');
assert.ok(model.includes('saveFieldAssignments'),'No valida choque horarios de personal de terreno');
assert.ok(panel.includes('nominatim.openstreetmap.org')===false,'No consultar Nominatim directamente desde JS del cliente');
assert.ok(panel.includes('https://tile.openstreetmap.org/{z}/{x}/{y}.png'),'Falta capa OSM');
assert.ok(model.includes('applyObraCatalog'),'No toma dirección desde catálogo');
assert.ok(planner.includes('function fillFieldCheckboxes'),'No permite asignar terreno');
assert.ok(planner.includes('function applyWork'),'No autocompleta ubicación');
assert.ok(planner.includes('data.personal_terreno_ids'),'No envía equipo sin usuario');
for(const item of ['catalogos.html','CatalogoOperativo.php','CatalogoOperativoController.php','ot_obra_vinculos','personal_terreno','ot_personal_terreno']){
 assert.ok(deploy.includes(item),'Falta en deploy: '+item);
}
const match=panel.match(/<script>([\s\S]*?)<\/script>/);
assert.ok(match,'Falta script de catálogos');
fs.writeFileSync('/tmp/controlab-catalogos.js',match[1]);
console.log('OK: catálogos de constructoras/obras y personal de terreno con permisos, agenda y deploy');
