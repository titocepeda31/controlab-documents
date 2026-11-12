<?php
declare(strict_types=1);

use Controlab\HttpError;
use Controlab\Support;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
session_name('controlab_demo');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));

$app = AppFactory::create();

// La misma app web y la API comparten origen: no hay acceso CORS a datos privados.
$app->add(function (Request $request, $handler): Response {
    if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '') {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $originPort = parse_url($origin, PHP_URL_PORT);
            $originName = $originHost . ($originPort ? ':' . $originPort : '');
            if (!is_string($originHost) || strcasecmp($originName, $request->getUri()->getAuthority()) !== 0) {
                throw new HttpError('Origen no autorizado', 403);
            }
        }
        if (!hash_equals((string)$_SESSION['csrf'], $request->getHeaderLine('X-CSRF-Token'))) {
            throw new HttpError('Token de sesión vencido, recarga la página', 403);
        }
    }
    return $handler->handle($request)->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('X-Frame-Options', 'DENY')->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

$app->get('/api/health', fn(Request $r, Response $p) => Support::json($p, ['ok' => true, 'framework' => 'Slim 4']));

$app->get('/api/session', function (Request $request, Response $response): Response {
    return Support::json($response, [
        'authenticated' => !empty($_SESSION['user_id']),
        'user' => !empty($_SESSION['user_id']) ? ['username' => $_SESSION['username']] : null,
        'csrf' => $_SESSION['csrf'],
    ]);
});

$app->post('/api/login', function (Request $request, Response $response): Response {
    $input = Support::input($request);
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');
    if ($username === '' || strlen($username) > 100 || strlen($password) > 512 || $password === '') {
        throw new HttpError('Credenciales inválidas', 401);
    }
    $stmt = Support::db()->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        usleep(250000);
        throw new HttpError('Usuario o contraseña incorrectos', 401);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return Support::json($response, ['user' => ['username' => $user['username']], 'csrf' => $_SESSION['csrf']]);
});

$app->post('/api/logout', function (Request $request, Response $response): Response {
    Support::auth();
    $_SESSION = [];
    session_destroy();
    return Support::json($response, ['ok' => true]);
});

$app->get('/api/orders', function (Request $request, Response $response): Response {
    Support::auth();
    $rows = Support::db()->query('SELECT id, ot_number, client_name, revision, updated_at FROM orders ORDER BY updated_at DESC LIMIT 200')->fetchAll();
    $orders = array_map(static fn(array $r) => [
        'id' => $r['id'], 'ot' => $r['ot_number'], 'clientName' => $r['client_name'],
        'revision' => (int)$r['revision'], 'updated_at' => $r['updated_at'],
    ], $rows);
    return Support::json($response, ['orders' => $orders]);
});

$app->get('/api/orders/{id}', function (Request $request, Response $response, array $args): Response {
    Support::auth();
    $id = Support::id($args['id'] ?? null);
    $stmt = Support::db()->prepare('SELECT data_json, revision FROM orders WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) throw new HttpError('Orden no encontrada', 404);
    return Support::json($response, ['order' => json_decode($row['data_json'], true), 'revision' => (int)$row['revision']]);
});

$app->post('/api/orders', function (Request $request, Response $response): Response {
    return Support::save(Support::input($request), null, Support::auth(), $response);
});

$app->put('/api/orders/{id}', function (Request $request, Response $response, array $args): Response {
    return Support::save(Support::input($request), Support::id($args['id'] ?? null), Support::auth(), $response);
});

$app->addRoutingMiddleware();
$error = $app->addErrorMiddleware(false, true, true);
$error->setDefaultErrorHandler(function (Request $request, Throwable $exception, bool $display, bool $log, bool $details) use ($app): Response {
    if ($exception instanceof HttpError) {
        $status = $exception->status;
        $message = $exception->getMessage();
    } elseif ($exception instanceof \Slim\Exception\HttpNotFoundException) {
        $status = 404; $message = 'Ruta no encontrada';
    } elseif ($exception instanceof \Slim\Exception\HttpMethodNotAllowedException) {
        $status = 405; $message = 'Método no permitido';
    } else {
        error_log('[controlab-api] ' . $exception->getMessage());
        $status = 500; $message = 'Error interno del servidor; revisa los registros privados del hosting';
    }
    return Support::json($app->getResponseFactory()->createResponse(), ['error' => $message], $status);
});
$app->run();
