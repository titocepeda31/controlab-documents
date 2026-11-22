'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const planner=fs.readFileSync('mvc/frontend/planificador.html','utf8');
const form=fs.readFileSync('mvc/frontend/formulario.html','utf8');
const api=fs.readFileSync('mvc/backend/routes/api.php','utf8');
const controller=fs.readFileSync('mvc/backend/src/Controllers/OrdenTrabajoController.php','utf8');
const deploy=fs.readFileSync('scripts/cpanel_mvc_deploy.sh','utf8');
for(const id of ['calendarGrid','dayList','agendaList','teamOverview','teamChoices','teamDay','teamFullMonth','destino','cliente','obra','inicio','fin','estado','saveBtn','personFilter','stateFilter','loadNotice']){
 assert.ok(planner.includes('id="'+id+'"'),'Falta elemento de planificación '+id);
}
for(const endpoint of ["/api/ot'","/api/ot/equipo'","/api/ot/{id:[0-9]+}'"]){
 assert.ok(api.includes(endpoint),'Falta endpoint OT '+endpoint);
}
assert.ok(controller.includes("Security::requireRole('administrador','administrativo')"),'Servidor no comprueba rol administrativo');
assert.ok(controller.includes('Security::csrf($request)'),'Servidor debe exigir CSRF');
assert.ok(planner.includes('async function load('),'Falta carga de agenda');
assert.ok(planner.includes('function renderCalendar('),'Falta calendario mensual');
assert.ok(planner.includes('function renderTeam('),'Falta distribución de equipo');
assert.ok(planner.includes('function jobsForDay('),'Falta lista de trabajos diarios');
assert.ok(planner.includes("window.open('https://www.google.com/maps/search/"),'Falta búsqueda de destino');
assert.ok(planner.includes('textContent'),'Datos técnicos deben mostrarse con texto escapado');
assert.ok(!planner.includes('localStorage.')&&!planner.includes('indexedDB'),'La agenda privada no se guarda offline');
assert.ok(form.includes('id="planificadorBtn"'),'No aparece menú de planificador');
for(const x of ['planificador.html','OrdenTrabajo.php','OrdenTrabajoController.php','ot_ordenes','ot_asignaciones','ot_eventos'])assert.ok(deploy.includes(x),'Despliegue incompleto '+x);
const script=planner.match(/<script>([\s\S]*?)<\/script>/);
assert.ok(script,'Falta script HTML');
fs.writeFileSync('/tmp/controlab-planificador.js',script[1]);
console.log('OK: calendario, agenda, equipos, mapa de destino, permisos y despliegue');
