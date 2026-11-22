<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\Empresa;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

final class EmpresaController {
    private Empresa $empresa;
    public function __construct(Database $db) { $this->empresa=new Empresa($db); }
    public function index(Request $request, Response $response): Response {
        Security::auth();
        return Http::json($response,['empresas'=>$this->empresa->all()]);
    }
}
