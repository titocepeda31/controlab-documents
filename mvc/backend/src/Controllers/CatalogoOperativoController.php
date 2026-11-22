<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\CatalogoOperativo;
use Demo\Models\MapaObras;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/** Solo administradores y administrativos pueden alterar registros operativos. */
final class CatalogoOperativoController {
    private CatalogoOperativo $catalogo;
    private MapaObras $mapa;
    public function __construct(Database $db){$this->catalogo=new CatalogoOperativo($db);$this->mapa=new MapaObras($db);}

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

    public function buscarDireccion(Request $request,Response $response): Response {
        Security::requireRole('administrador','administrativo');
        $q=$request->getQueryParams()['q']??'';
        if(!is_string($q))throw new InvalidArgumentException('Búsqueda inválida');
        return Http::json($response,['resultados'=>$this->mapa->buscar($q)]);
    }

    public function guardarUbicacion(Request $request,Response $response,array $args): Response {
        Security::requireRole('administrador','administrativo');Security::csrf($request);
        $this->mapa->guardar((int)$args['id'],Http::jsonBody($request));
        return Http::json($response,['ok'=>true]);
    }
}
