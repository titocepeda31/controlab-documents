<?php
declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$app->get('/',static function(Request $request,Response $response) use ($config) {
    return $response->withHeader('Location',rtrim($config['base_path'],'/').'/formulario.html')->withStatus(302);
});
$app->get('/api/session',[$auth,'session']);
// Version POST no cacheable del mismo endpoint (sin cambios de estado).
$app->post('/api/session',[$auth,'session']);
$app->post('/api/login',[$auth,'login']);
$app->post('/api/logout',[$auth,'logout']);
$app->get('/api/catalogo/empresas',[$catalogoOperativoController,'empresas']);
$app->post('/api/catalogo/empresas',[$catalogoOperativoController,'crearEmpresa']);
$app->get('/api/catalogo/obras',[$catalogoOperativoController,'obras']);
$app->get('/api/catalogo/buscar-direccion',[$catalogoOperativoController,'buscarDireccion']);
$app->put('/api/catalogo/obras/{id:[0-9]+}/ubicacion',[$catalogoOperativoController,'guardarUbicacion']);
$app->post('/api/catalogo/obras',[$catalogoOperativoController,'crearObra']);
$app->put('/api/catalogo/obras/{id:[0-9]+}',[$catalogoOperativoController,'editarObra']);
$app->get('/api/catalogo/personal',[$catalogoOperativoController,'personal']);
$app->post('/api/catalogo/personal',[$catalogoOperativoController,'crearPersonal']);
$app->put('/api/catalogo/personal/{id:[0-9]+}',[$catalogoOperativoController,'editarPersonal']);
$app->get('/api/ot',[$ordenTrabajoController,'index']);
$app->get('/api/ot/equipo',[$ordenTrabajoController,'equipo']);
$app->get('/api/ot/{id:[0-9]+}',[$ordenTrabajoController,'show']);
$app->post('/api/ot',[$ordenTrabajoController,'create']);
$app->put('/api/ot/{id:[0-9]+}',[$ordenTrabajoController,'update']);
$app->get('/api/usuarios',[$usuariosController,'index']);
$app->post('/api/usuarios',[$usuariosController,'create']);
$app->patch('/api/usuarios/{id:[0-9]+}',[$usuariosController,'update']);
$app->post('/api/usuarios/{id:[0-9]+}/restablecer-clave',[$usuariosController,'resetPassword']);
$app->get('/api/mi-firma',[$firmasLaboratorista,'show']);
$app->put('/api/mi-firma',[$firmasLaboratorista,'store']);
$app->delete('/api/mi-firma',[$firmasLaboratorista,'delete']);
$app->get('/api/empresas',[$empresas,'index']);
$app->get('/api/informes',[$informes,'index']);
$app->get('/api/informes/{id:[0-9]+}',[$informes,'show']);
$app->post('/api/informes',[$informes,'store']);
$app->put('/api/informes/{id:[0-9]+}',[$informes,'update']);
$app->post('/api/informes/{id:[0-9]+}/duplicar',[$informes,'duplicate']);
$app->post('/api/informes/{id:[0-9]+}/solicitar-revision',[$revisiones,'request']);
$app->post('/api/informes/{id:[0-9]+}/aprobar',[$revisiones,'approve']);
$app->post('/api/informes/{id:[0-9]+}/devolver',[$revisiones,'returnForChanges']);
