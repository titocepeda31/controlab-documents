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
$app->get('/api/empresas',[$empresas,'index']);
$app->get('/api/informes',[$informes,'index']);
$app->get('/api/informes/{id:[0-9]+}',[$informes,'show']);
$app->post('/api/informes',[$informes,'store']);
$app->put('/api/informes/{id:[0-9]+}',[$informes,'update']);
$app->post('/api/informes/{id:[0-9]+}/duplicar',[$informes,'duplicate']);
$app->post('/api/informes/{id:[0-9]+}/solicitar-revision',[$revisiones,'request']);
$app->post('/api/informes/{id:[0-9]+}/aprobar',[$revisiones,'approve']);
$app->post('/api/informes/{id:[0-9]+}/devolver',[$revisiones,'returnForChanges']);

// Administración y visibilidad por empresa: permisos validados en servidor.
$app->get('/api/usuarios',[$usuarios,'index']);
$app->post('/api/usuarios',[$usuarios,'store']);
$app->put('/api/usuarios/{id:[0-9]+}',[$usuarios,'update']);
$app->post('/api/usuario/password',[$usuarios,'password']);
$app->post('/api/empresas',[$empresas,'store']);
$app->post('/api/informes/{id:[0-9]+}/destinatario',[$destinatarios,'assign']);
