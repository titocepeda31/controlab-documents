<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\Usuario;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

final class UsuariosController {
    private Usuario $usuarios;
    public function __construct(Database $db) { $this->usuarios=new Usuario($db); }

    public function index(Request $request, Response $response): Response {
        Security::requireRole('administrador');
        return Http::json($response,['usuarios'=>$this->usuarios->all()]);
    }
    public function create(Request $request,Response $response): Response {
        Security::requireRole('administrador'); Security::csrf($request);
        $id=$this->usuarios->create(Http::jsonBody($request));
        return Http::json($response,['ok'=>true,'id'=>$id],201);
    }
    public function update(Request $request,Response $response,array $args): Response {
        Security::requireRole('administrador'); Security::csrf($request);
        $this->usuarios->update((int)$args['id'],Http::jsonBody($request));
        return Http::json($response,['ok'=>true]);
    }
    public function resetPassword(Request $request,Response $response,array $args): Response {
        Security::requireRole('administrador'); Security::csrf($request);
        $body=Http::jsonBody($request);
        $this->usuarios->resetPassword((int)$args['id'],$body['clave']??null);
        return Http::json($response,['ok'=>true]);
    }
}
