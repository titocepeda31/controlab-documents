<?php
declare(strict_types=1);

namespace Demo\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Demo\Core\Database;
use Demo\Models\Usuario;

/**
 * Proteccion de endpoints privados por sesion y CSRF.
 * Acepta el token en X-CSRF-Token o en el campo csrf del JSON.
 * Ambas vias deben coincidir con el token de la sesion PHP activa.
 */
final class Security
{
    private static ?Database $database=null;

    public static function configure(Database $db): void { self::$database=$db; }

    /** Revalida estado, perfil y versión de sesión contra MySQL en cada solicitud. */
    public static function sessionUser(): ?array {
        $uid=(int)($_SESSION['uid']??0);
        $sv=(int)($_SESSION['session_version']??0);
        if($uid<1||$sv<1)return null;
        if(self::$database===null)throw new RuntimeException('Seguridad sin configurar');
        $current=(new Usuario(self::$database))->findActiveById($uid);
        if(!$current||$sv!==(int)$current['version_credenciales']){
            unset($_SESSION['uid'],$_SESSION['session_version']);
            return null;
        }
        return $current;
    }

    public static function auth(): array {
        $user=self::sessionUser();
        if(!$user)throw new RuntimeException('Inicia sesión nuevamente',401);
        return $user;
    }

    public static function requireRole(string ...$roles): array {
        $user=self::auth();
        if(!in_array($user['rol'],$roles,true))throw new RuntimeException('Tu perfil no tiene permiso para esta acción',403);
        return $user;
    }

    public static function csrf(Request $request): void
    {
        $expected = $_SESSION['csrf'] ?? null;
        $headerToken = trim($request->getHeaderLine('X-CSRF-Token'));
        $bodyToken = '';

        // cPanel/Apache/FastCGI puede tratar de forma distinta cabeceras
        // HTTP personalizadas. Como alternativa, se permite enviar el
        // mismo token CSRF en el cuerpo JSON, sin quitar la validacion.
        $raw = (string) $request->getBody();
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['csrf']) && is_string($json['csrf'])) {
                $bodyToken = trim($json['csrf']);
            }
        }

        $validExpected = is_string($expected) && $expected !== '';
        $validHeader = $validExpected && $headerToken !== ''
            && hash_equals($expected, $headerToken);
        $validBody = $validExpected && $bodyToken !== ''
            && hash_equals($expected, $bodyToken);

        if (!$validHeader && !$validBody) {
            // No registrar tokens ni cookies: solo indicadores.
            error_log(sprintf(
                '[DEMO CSRF] mismatch; active=%d; cookie=%d; expected=%d; header=%d; json=%d; eqHeader=%d; eqBody=%d',
                session_status() === PHP_SESSION_ACTIVE ? 1 : 0,
                isset($_COOKIE[session_name()]) ? 1 : 0,
                $validExpected ? 1 : 0,
                $headerToken !== '' ? 1 : 0,
                $bodyToken !== '' ? 1 : 0,
                $validHeader ? 1 : 0,
                $validBody ? 1 : 0
            ));
            throw new RuntimeException('Token de seguridad inválido', 403);
        }
    }
}
