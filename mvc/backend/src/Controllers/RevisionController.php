<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\Revision;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use InvalidArgumentException;

final class RevisionController {
    private Revision $revision;
    public function __construct(Database $db) { $this->revision=new Revision($db); }

    public function request(Request $request,Response $response,array $args): Response {
        Security::auth();Security::csrf($request);
        $this->revision->request((int)$args['id'],(int)$_SESSION['uid']);
        return Http::json($response,['ok'=>true,'estado'=>'pendiente']);
    }
    public function approve(Request $request,Response $response,array $args): Response {
        Security::auth();Security::csrf($request);
        $body=Http::jsonBody($request);
        if (!is_string($body['firma']??null)) throw new InvalidArgumentException('Falta la firma');
        $this->revision->approve((int)$args['id'],(int)$_SESSION['uid'],$body['firma']);
        return Http::json($response,['ok'=>true,'estado'=>'aprobado']);
    }
    public function returnForChanges(Request $request,Response $response,array $args): Response {
        Security::auth();Security::csrf($request);
        $body=Http::jsonBody($request);
        if (!is_string($body['motivo']??null)) throw new InvalidArgumentException('Indica el motivo de devolución');
        $this->revision->returnForChanges((int)$args['id'],(int)$_SESSION['uid'],$body['motivo']);
        return Http::json($response,['ok'=>true,'estado'=>'devuelto']);
    }
}
