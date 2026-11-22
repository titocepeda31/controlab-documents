'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const backend='mvc/backend';
const html=fs.readFileSync('mvc/frontend/usuarios.html','utf8');
const main=fs.readFileSync('mvc/frontend/formulario.html','utf8');
const routes=fs.readFileSync(backend+'/routes/api.php','utf8');
const security=fs.readFileSync(backend+'/src/Middleware/Security.php','utf8');
const controller=fs.readFileSync(backend+'/src/Controllers/UsuariosController.php','utf8');
const model=fs.readFileSync(backend+'/src/Models/Usuario.php','utf8');
const config=fs.readFileSync('scripts/cpanel_mvc_deploy.sh','utf8');
const sql=fs.readFileSync('mvc/database/crear_perfiles_usuarios.sql','utf8');
for(const role of ['administrador','administrativo','laboratorista','digitador']){
 assert.ok(html.includes('value="'+role+'"'),role+' no aparece en UI');
 assert.ok(model.includes("'"+role+"'"),role+' no aparece en servidor');
 assert.ok(sql.includes("'"+role+"'"),role+' no aparece en migración');
}
for(const route of ["get('/api/usuarios'","post('/api/usuarios'","patch('/api/usuarios/","/restablecer-clave"]){
 assert.ok(routes.includes(route),'Falta API: '+route);
}
for(const endpoint of ['index','create','update','resetPassword']){
 const start=controller.indexOf('function '+endpoint+'(');
 assert.ok(start>0,'No existe acción '+endpoint);
 assert.ok(controller.slice(start,start+240).includes("Security::requireRole('administrador')"),'Sin permiso en '+endpoint);
}
assert.ok(security.includes('version_credenciales'),'Sesión debe revocarse al cambiar la contraseña');
assert.ok(model.includes('version_credenciales=version_credenciales+1'),'Restablecer clave debe invalidar sesiones');
assert.ok(model.includes('Debe quedar al menos un administrador activo'),'Debe proteger último administrador');
assert.ok(main.includes('id="usuariosBtn"')&&main.includes('!canAdminUsers'),'Enlace de usuarios se restringe en móvil');
assert.ok(main.includes('!canUseProfileSignature'),'Firma solo para laboratoristas');
assert.ok(config.includes('usuario_perfiles')&&config.includes('usuarios.html')&&config.includes('UsuariosController.php'),'Despliegue incompleto');
const scripts=[...html.matchAll(/<script>([\s\S]*?)<\/script>/g)];
assert.equal(scripts.length,1,'Se espera un script de administración');
fs.writeFileSync('/tmp/controlab-usuarios.js',scripts[0][1]);
console.log('OK: roles, APIs, privilegios y despliegue de módulo de usuarios');
