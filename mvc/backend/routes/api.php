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
$app->post('/api/informes/{id:[0-9]+}/duplicar',[$informes,'duplicate']);
