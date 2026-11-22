<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Access;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\Usuario;
use Demo\Models\Revision;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;

final class AuthController {
    private Usuario $users;
    private Revision $revision;
    private Database $db;
    public function __construct(Database $db) { $this->db=$db; $this->users=new Usuario($db); $this->revision=new Revision($db); }
    public function session(Request $request, Response $response): Response {
        $uid=(int)($_SESSION['uid']??0);
        $account=Access::user($this->db,$uid);
        $valid=$account!==null;
        return Http::json($response,[
            'autenticado'=>$valid,'csrf'=>$_SESSION['csrf'],'usuario_id'=>$valid?$uid:null,
            'rol'=>$valid?$account['rol']:null,'nombre'=>$valid?$account['nombre']:null,
            'empresa_id'=>$valid?$account['empresa_id']:null,
            'puede_editar'=>$valid && in_array($account['rol'],['administrador','laboratorista'],true),
            'puede_enviar'=>$valid && in_array($account['rol'],['administrador','laboratorista','digitalizador'],true),
            'puede_administrar'=>$valid && $account['rol']==='administrador',
            'puede_revisar'=>$valid && $account['rol']!=='cliente' && $this->revision->canReview($uid)
        ]);
    }
    public function login(Request $request, Response $response): Response {
        Security::csrf($request);
        $body=Http::jsonBody($request);
        $email=mb_substr(trim((string)($body['email'] ?? '')),0,180);
        $user=$this->users->findActiveByEmail($email);
        if (!$user || !password_verify((string)($body['password'] ?? ''),$user['password_hash'])) {
            throw new RuntimeException('Credenciales incorrectas',401);
        }
        if (!Access::user($this->db,(int)$user['id'])) throw new RuntimeException('Cuenta sin permisos de acceso configurados',403);
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
