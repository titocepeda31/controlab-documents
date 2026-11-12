<?php
declare(strict_types=1);

namespace Controlab;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

final class HttpError extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}

final class Support
{
    public static function config(): array
    {
        $file = dirname(__DIR__) . '/private/config.php';
        if (!is_file($file)) throw new RuntimeException('Falta configuración privada');
        $config = require $file;
        if (!is_array($config)) throw new RuntimeException('Configuración privada inválida');
        return $config;
    }

    public static function db(): PDO
    {
        static $db = null;
        if ($db instanceof PDO) return $db;
        $c = self::config();
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $c['db_host'], $c['db_port'] ?? 3306, $c['db_name']);
        $db = new PDO($dsn, $c['db_user'], $c['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $db;
    }

    public static function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    public static function input(Request $request): array
    {
        $raw = (string)$request->getBody();
        if (strlen($raw) > 1_000_000) throw new HttpError('Solicitud supera 1 MB', 413);
        try {
            $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpError('JSON inválido', 400);
        }
        if (!is_array($value) || array_is_list($value)) throw new HttpError('Se requiere objeto JSON', 400);
        return $value;
    }

    public static function auth(): int
    {
        if (empty($_SESSION['user_id'])) throw new HttpError('Debes iniciar sesión', 401);
        return (int)$_SESSION['user_id'];
    }

    public static function id(mixed $id): string
    {
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $id)) {
            throw new HttpError('Identificador de orden inválido', 422);
        }
        return $id;
    }

    public static function order(mixed $order): array
    {
        if (!is_array($order) || array_is_list($order)) throw new HttpError('Orden inválida', 422);
        self::id($order['id'] ?? null);
        $ot = $order['ot'] ?? null;
        if (!is_string($ot) || trim($ot) === '' || strlen($ot) > 120) {
            throw new HttpError('Nº de OT obligatorio (máximo 120 caracteres)', 422);
        }
        if (!is_string($order['clientName'] ?? '')) throw new HttpError('Cliente inválido', 422);
        if (strlen($order['clientName'] ?? '') > 255) throw new HttpError('Cliente demasiado largo', 422);
        if (!isset($order['tests']) || !is_array($order['tests']) || count($order['tests']) > 50) {
            throw new HttpError('Cantidad de ensayos inválida (máximo 50)', 422);
        }
        foreach ($order['tests'] as $test) {
            if (!is_array($test) || !in_array($test['type'] ?? '', ['density', 'custom'], true)) {
                throw new HttpError('Tipo de ensayo no permitido', 422);
            }
            if (!isset($test['controls'], $test['items']) || !is_array($test['controls']) || !is_array($test['items'])
                || count($test['controls']) > 120 || count($test['items']) > 120) {
                throw new HttpError('Máximo 120 controles o resultados por ensayo', 422);
            }
        }
        return $order;
    }

    public static function save(array $input, ?string $pathId, int $userId, Response $response): Response
    {
        $order = self::order($input['order'] ?? null);
        $id = $order['id'];
        if ($pathId !== null && $pathId !== $id) throw new HttpError('El ID no coincide con la ruta', 422);
        $expected = $input['expected_revision'] ?? null;
        if (!is_int($expected) || $expected < 0) throw new HttpError('Revisión esperada inválida', 422);
        $data = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($data) > 950_000) throw new HttpError('Orden demasiado grande', 413);
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT revision FROM orders WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            if ($current) {
                $revision = (int)$current['revision'];
                if ($expected !== $revision) {
                    $pdo->rollBack();
                    return self::json($response, ['error' => 'Conflicto: versión más reciente en el servidor', 'current_revision' => $revision], 409);
                }
                $revision++;
                $stmt = $pdo->prepare('UPDATE orders SET ot_number=?, client_name=?, data_json=?, revision=?, updated_by=? WHERE id=?');
                $stmt->execute([$order['ot'], $order['clientName'] ?? '', $data, $revision, $userId, $id]);
            } else {
                if ($expected !== 0) {
                    $pdo->rollBack();
                    return self::json($response, ['error' => 'Orden no encontrada', 'current_revision' => 0], 409);
                }
                $revision = 1;
                $stmt = $pdo->prepare('INSERT INTO orders (id, ot_number, client_name, data_json, revision, updated_by) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$id, $order['ot'], $order['clientName'] ?? '', $data, $revision, $userId]);
            }
            $pdo->commit();
            return self::json($response, ['saved' => true, 'revision' => $revision], $revision === 1 ? 201 : 200);
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((string)$e->getCode() === '23000') {
                return self::json($response, ['error' => 'La orden ya existe, vuelve a cargarla antes de guardar'], 409);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
