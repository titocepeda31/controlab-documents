<?php
declare(strict_types=1);
namespace Demo\Controllers;
use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\Laboratorio;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use InvalidArgumentException;
use PDOException;
use RuntimeException;
final class LaboratorioController {
    private Laboratorio $lab;
    public function __construct(Database $db){$this->lab=new Laboratorio($db);}
    private function auth(): array {return Security::requireRole('administrador','administrativo');}
    private function queryId(Request $r,string $key): int {
        $id=filter_var($r->getQueryParams()[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($id===false)throw new InvalidArgumentException('Identificador inválido');return $id;
    }
    public function catalogo(Request $r,Response $s): Response {$this->auth();return Http::json($s,$this->lab->catalogo());}
    public function materiales(Request $r,Response $s): Response {$this->auth();return Http::json($s,['materiales'=>$this->lab->materiales($this->queryId($r,'obra_id'))]);}
    public function sujetos(Request $r,Response $s): Response {$this->auth();return Http::json($s,$this->lab->sujetos($this->queryId($r,'material_id')));}
    public function resultadosOT(Request $r,Response $s): Response {$this->auth();return Http::json($s,['resultados'=>$this->lab->resultadosOT($this->queryId($r,'ot_id'))]);}
    public function resultados(Request $r,Response $s): Response {$this->auth();return Http::json($s,['resultados'=>$this->lab->resultados($this->queryId($r,'material_id'))]);}
    private function write(Request $r,Response $s,string $method): Response {
        $u=$this->auth();Security::csrf($r);
        try{$id=$this->lab->$method(Http::jsonBody($r),(int)$u['id']);}
        catch(PDOException $e){if($e->getCode()==='23000')throw new RuntimeException('Registro duplicado o vínculo inválido',409);throw $e;}
        return Http::json($s,['id'=>$id],201);
    }
    public function tiposMaterial(Request $r,Response $s): Response {$this->auth();return Http::json($s,['tipos'=>$this->lab->tiposMaterial()]);}
    public function crearTipoMaterial(Request $r,Response $s): Response {return $this->write($r,$s,'crearTipoMaterial');}
    public function editarTipoMaterial(Request $r,Response $s,array $args): Response {
        $u=$this->auth();Security::csrf($r);
        try{$this->lab->editarTipoMaterial((int)$args['id'],Http::jsonBody($r),(int)$u['id']);}
        catch(PDOException $e){if($e->getCode()==='23000')throw new RuntimeException('Ya existe un tipo con ese nombre',409);throw $e;}
        return Http::json($s,['ok'=>true]);
    }
    public function clasificarMaterial(Request $r,Response $s): Response {return $this->write($r,$s,'clasificarMaterial');}
    public function crearMaterial(Request $r,Response $s): Response {return $this->write($r,$s,'crearMaterial');}
    public function crearMuestra(Request $r,Response $s): Response {return $this->write($r,$s,'crearMuestra');}
    public function crearPunto(Request $r,Response $s): Response {return $this->write($r,$s,'crearPunto');}
    public function registrar(Request $r,Response $s): Response {return $this->write($r,$s,'registrar');}
}
