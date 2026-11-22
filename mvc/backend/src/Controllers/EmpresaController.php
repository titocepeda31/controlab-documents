<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Access;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\Empresa;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

final class EmpresaController {
    private Empresa $empresa;
    private Database $db;
    public function __construct(Database $db) { $this->db=$db; $this->empresa=new Empresa($db); }
    public function index(Request $request, Response $response): Response {
        Access::internal($this->db);
        return Http::json($response,['empresas'=>$this->empresa->all()]);
    }
    public function store(Request $request,Response $response): Response {
        Access::admin($this->db);Security::csrf($request);
        $body=Http::jsonBody($request);
        $name=trim((string)($body['razon_social']??''));
        if(mb_strlen($name)<3 || mb_strlen($name)>255)throw new InvalidArgumentException('Nombre de empresa inválido');
        $q=$this->db->connection()->prepare('SELECT id FROM empresas WHERE razon_social=? LIMIT 1');
        $q->execute([$name]);$id=$q->fetchColumn();
        if($id)return Http::json($response,['id'=>(int)$id,'razon_social'=>$name]);
        $q=$this->db->connection()->prepare('INSERT INTO empresas (razon_social) VALUES (?)');
        $q->execute([$name]);
        return Http::json($response,['id'=>(int)$this->db->connection()->lastInsertId(),'razon_social'=>$name],201);
    }
}
