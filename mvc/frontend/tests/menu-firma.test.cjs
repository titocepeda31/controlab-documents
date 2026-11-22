'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const html=fs.readFileSync('mvc/frontend/formulario.html','utf8');
const routes=fs.readFileSync('mvc/backend/routes/api.php','utf8');
const deploy=fs.readFileSync('scripts/cpanel_mvc_deploy.sh','utf8');
for(const id of ['menuToggle','topActions','profileFirmaBtn','profileFirmaScreen','profileFirmaCanvas','profileFirmaFile','saveProfileFirma','deleteProfileFirma','printLaboratorista']){
 assert.ok(html.includes('id="'+id+'"'),'Falta control '+id);
}
assert.ok(html.includes('aria-expanded="false"'),'Menu debe ser accesible');
assert.ok(html.includes('top-actions:not(.open)'),'Menu movil debe poder ocultarse');
assert.ok(html.includes('async function loadProfileFirma'),'Debe existir descarga de firma visual');
assert.ok(html.includes("storagePut('history',{key:ownerKey('firmaPerfil')"),'Firma local debe ir a IndexedDB cifrado');
assert.ok(html.includes('renderPrintFirmaLaboratorista'),'La firma debe mostrarse automaticamente en la impresion');
for(const method of ["get('/api/mi-firma'","put('/api/mi-firma'","delete('/api/mi-firma'"])assert.ok(routes.includes(method),'Falta endpoint '+method);
for(const file of ['LaboratoristaFirma.php','LaboratoristaFirmaController.php','laboratorista_firmas'])assert.ok(deploy.includes(file),'Falta comprobacion de despliegue '+file);
console.log('OK: menu movil, firma visual, cifrado local, rutas y despliegue');
