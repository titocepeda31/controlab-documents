<?php
declare(strict_types=1);

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Controllers\AuthController;
use Demo\Controllers\EmpresaController;
use Demo\Controllers\InformeController;
use Demo\Controllers\LaboratoristaFirmaController;
use Demo\Controllers\UsuariosController;
use Demo\Controllers\OrdenTrabajoController;
use Demo\Controllers\CatalogoOperativoController;
use Demo\Middleware\Security;
use Demo\Controllers\RevisionController;
use Demo\Controllers\CorreoController;
use Demo\Controllers\LaboratorioController;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

// Variables $config y $private se preparan en public_html/demo/index.php.
if (!isset($config) || !is_array($config)) {
    throw new RuntimeException('Configuración no cargada');
}
foreach (['db_host','db_name','db_user','db_pass','base_path','session_name'] as $required) {
    if (!isset($config[$required]) || !is_string($config[$required])) {
        throw new RuntimeException('Configuración incompleta: ' . $required);
    }
}

spl_autoload_register(static function (string $class) use ($private): void {
    $prefix = 'Demo\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = $private . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require_once $file;
});

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if ($https) header('Strict-Transport-Security: max-age=31536000');

session_name($config['session_name']);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $https,
    'samesite' => 'Lax',
    'path' => rtrim($config['base_path'], '/'),
]);
if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
    throw new RuntimeException('No se pudo iniciar la sesión');
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(24));

$db = new Database($config);
Security::configure($db);
$auth = new AuthController($db);
$empresas = new EmpresaController($db);
$informes = new InformeController($db);
$revisiones = new RevisionController($db);
$firmasLaboratorista = new LaboratoristaFirmaController($db);
$usuariosController = new UsuariosController($db);
$ordenTrabajoController = new OrdenTrabajoController($db);
$catalogoOperativoController = new CatalogoOperativoController($db);
$correoController = new CorreoController($db,$config,$private);
$laboratorioController = new LaboratorioController($db);

$app = AppFactory::create();
$app->setBasePath(rtrim($config['base_path'], '/'));
$app->addBodyParsingMiddleware();

// Conservamos los endpoints originales para que el HTML existente no cambie.
require $private . '/routes/api.php';

$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$errorMiddleware->setDefaultErrorHandler(
    static function (Request $request, Throwable $e, bool $display, bool $log, bool $details) use ($app) {
        $status = $e instanceof InvalidArgumentException ? 422 : (int)$e->getCode();
        $errorId = null;
        if ($status < 400 || $status > 499) {
            $status = 500;
            // Código público de correlación: no expone claves, SQL ni rutas privadas.
            $errorId = bin2hex(random_bytes(5));
            error_log('[CONTROLAB '.$errorId.'] '.$e);
        }
        $message = $status === 500 ? 'Error interno del servidor' : $e->getMessage();
        $payload = ['error' => $message];
        if ($errorId !== null) $payload['referencia'] = $errorId;
        return Http::json($app->getResponseFactory()->createResponse(), $payload, $status);
    }
);
$app->run();
