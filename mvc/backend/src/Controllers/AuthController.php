<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\Usuario;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;

final class AuthController {
    private Usuario $users;
    public function __construct(Database $db) { $this->users=new Usuario($db); }
    public function session(Request $request, Response $response): Response {
        return Http::json($response,['autenticado'=>!empty($_SESSION['uid']),'csrf'=>$_SESSION['csrf'],'usuario_id'=>!empty($_SESSION['uid'])?(int)$_SESSION['uid']:null]);
    }
    public function login(Request $request, Response $response): Response {
        Security::csrf($request);
        $body=Http::jsonBody($request);
        $email=mb_substr(trim((string)($body['email'] ?? '')),0,180);
        $user=$this->users->findActiveByEmail($email);
        if (!$user || !password_verify((string)($body['password'] ?? ''),$user['password_hash'])) {
            throw new RuntimeException('Credenciales incorrectas',401);
        }
        session_regenerate_id(true);
        $_SESSION['uid']=(int)$user['id'];
        $_SESSION['csrf']=bin2hex(random_bytes(24));
        return Http::json($response,['ok'=>true]);
    }
    public function logout(Request $request, Response $response): Response {
        Security::csrf($request);
        $_SESSION=[];
        if (ini_get('session.use_cookies')) {
            $params=session_get_cookie_params();
            setcookie(session_name(),'',[
                'expires'=>time()-3600,'path'=>$params['path'],
                'domain'=>$params['domain'],'secure'=>$params['secure'],
                'httponly'=>$params['httponly'],'samesite'=>$params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        return Http::json($response,['ok'=>true]);
    }
}
