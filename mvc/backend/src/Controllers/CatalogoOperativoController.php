<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\CatalogoOperativo;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/** Solo administradores y administrativos pueden alterar registros operativos. */
final class CatalogoOperativoController {
    private CatalogoOperativo $catalogo;
    public function __construct(Database $db){$this->catalogo=new CatalogoOperativo($db);}

    public function empresas(Request $request,Response $response): Response {
        Security::requireRole('administrador','administrativo');
        return Http::json($response,['empresas'=>$this->catalogo->empresas()]);
    }
    public function crearEmpresa(Request $request,Response $response): Response {
        Security::requireRole('administrador','administrativo');Security::csrf($request);
        return Http::json($response,['id'=>$this->catalogo->createEmpresa(Http::jsonBody($request))],201);
    }
    public function obras(Request $request,Response $response): Response {
        Security::requireRole('administrador','administrativo');
        return Http::json($response,['obras'=>$this->catalogo->obras()]);
    }
    public function crearObra(Request $request,Response $response): Response {
        Security::requireRole('administrador','administrativo');Security::csrf($request);
        return Http::json($response,['id'=>$this->catalogo->saveObra(Http::jsonBody($request))],201);
    }
    public function editarObra(Request $request,Response $response,array $args): Response {
        Security::requireRole('administrador','administrativo');Security::csrf($request);
        $id=$this->catalogo->saveObra(Http::jsonBody($request),(int)$args['id']);
        return Http::json($response,['id'=>$id,'ok'=>true]);
    }
    public function personal(Request $request,Response $response): Response {
        Security::requireRole('administrador','administrativo');
        return Http::json($response,['personal'=>$this->catalogo->personal()]);
    }
    public function crearPersonal(Request $request,Response $response): Response {
        Security::requireRole('administrador','administrativo');Security::csrf($request);
        return Http::json($response,['id'=>$this->catalogo->savePersonal(Http::jsonBody($request))],201);
    }
    public function editarPersonal(Request $request,Response $response,array $args): Response {
        Security::requireRole('administrador','administrativo');Security::csrf($request);
        $id=$this->catalogo->savePersonal(Http::jsonBody($request),(int)$args['id']);
        return Http::json($response,['id'=>$id,'ok'=>true]);
    }
}
