<?php
// Punto de entrada público del piloto. No guardar contraseñas aquí.
$base = dirname(__DIR__, 2);
$private = $base . '/demo-informes';

require_once $base . '/tito-pos-privado/api/vendor/autoload.php';
$config = require $private . '/config.php';
require $private . '/bootstrap.php';
