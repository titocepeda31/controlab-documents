<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\Informe;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use InvalidArgumentException;

final class InformeController {
    private Informe $informes;
    public function __construct(Database $db) { $this->informes=new Informe($db); }
    public function index(Request $request, Response $response): Response {
        Security::auth();
        return Http::json($response,['informes'=>$this->informes->all()]);
    }
    public function show(Request $request, Response $response, array $args): Response {
        Security::auth();
        return Http::json($response,$this->informes->find((int)$args['id']));
    }
    public function store(Request $request, Response $response): Response {
        Security::auth(); Security::csrf($request);
        $input=Http::jsonBody($request);
        if (!isset($input['datos']) || !is_array($input['datos'])) throw new InvalidArgumentException('Datos del informe inválidos');
        $uuid=$input['client_uuid'] ?? null;
        if ($uuid !== null && (!is_string($uuid) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',$uuid))) {
            throw new InvalidArgumentException('Identificador de envío inválido');
        }
        return Http::json($response,['id'=>$this->informes->create($input['datos'],$uuid)],201);
    }
    public function update(Request $request, Response $response, array $args): Response {
        Security::auth(); Security::csrf($request);
        $input=Http::jsonBody($request);
        if (!isset($input['datos']) || !is_array($input['datos'])) throw new InvalidArgumentException('Datos del informe inválidos');
        $this->informes->update((int)$args['id'],(int)$_SESSION['uid'],$input['datos']);
        return Http::json($response,['ok'=>true,'id'=>(int)$args['id']]);
    }
    public function duplicate(Request $request, Response $response, array $args): Response {
        Security::auth(); Security::csrf($request);
        $input=Http::jsonBody($request);
        $mode=$input['modo'] ?? '';
        if (!in_array($mode,['completo','antecedentes'],true)) throw new InvalidArgumentException('Modo inválido');
        $old=$this->informes->find((int)$args['id']);
        $data=$old['datos'];
        if ($mode==='antecedentes') {
            $keep=['estructura','zona','predio','camino','sector','kmp','contratista','mandante','responsable'];
            $data=array_intersect_key($data,array_flip($keep));
        }
        unset($data['nInforme'],$data['muestra']);
        $data['fechaRegistro']=date('Y-m-d');
        $data['fechaEnsayo']=date('Y-m-d');
        $id=$this->informes->create($data);
        return Http::json($response,['id'=>$id,'datos'=>$data],201);
    }
}
