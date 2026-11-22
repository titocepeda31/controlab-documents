<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\OrdenTrabajo;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use InvalidArgumentException;

final class OrdenTrabajoController {
    private OrdenTrabajo $ot;
    public function __construct(Database $db){$this->ot=new OrdenTrabajo($db);}

    public function index(Request $request,Response $response): Response {
        $viewer=Security::auth();
        $params=$request->getQueryParams();
        $from=$params['desde']??date('Y-m-01');
        $until=$params['hasta']??date('Y-m-t');
        if(!is_string($from)||!is_string($until))throw new InvalidArgumentException('Fechas de consulta inválidas');
        return Http::json($response,['ordenes'=>$this->ot->all($viewer,$from,$until)]);
    }

    public function equipo(Request $request,Response $response): Response {
        Security::requireRole('administrador','administrativo');
        return Http::json($response,['equipo'=>$this->ot->team()]);
    }

    public function show(Request $request,Response $response,array $args): Response {
        $viewer=Security::auth();
        return Http::json($response,['orden'=>$this->ot->find((int)$args['id'],$viewer)]);
    }

    public function create(Request $request,Response $response): Response {
        $viewer=Security::requireRole('administrador','administrativo');Security::csrf($request);
        $id=$this->ot->create(Http::jsonBody($request),(int)$viewer['id']);
        return Http::json($response,['id'=>$id,'ok'=>true],201);
    }

    public function update(Request $request,Response $response,array $args): Response {
        $viewer=Security::requireRole('administrador','administrativo');Security::csrf($request);
        $id=(int)$args['id'];
        $this->ot->update($id,Http::jsonBody($request),(int)$viewer['id']);
        return Http::json($response,['ok'=>true,'id'=>$id]);
    }
}
