<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\LaboratoristaFirma;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/** Firma visual reutilizable del usuario, independiente de aprobación de informes. */
final class LaboratoristaFirmaController {
    private LaboratoristaFirma $firmas;
    public function __construct(Database $db) { $this->firmas=new LaboratoristaFirma($db); }

    public function show(Request $request, Response $response): Response {
        Security::auth();
        $imagen=$this->firmas->getForUser((int)$_SESSION['uid']);
        return Http::json($response,['registrada'=>$imagen!==null,'firma_imagen'=>$imagen]);
    }

    public function store(Request $request,Response $response): Response {
        Security::auth(); Security::csrf($request);
        $body=Http::jsonBody($request);
        if (!is_string($body['firma']??null)) throw new InvalidArgumentException('Selecciona o dibuja la firma');
        $this->firmas->save((int)$_SESSION['uid'],$body['firma']);
        return Http::json($response,['ok'=>true]);
    }

    public function delete(Request $request,Response $response): Response {
        Security::auth(); Security::csrf($request);
        $this->firmas->delete((int)$_SESSION['uid']);
        return Http::json($response,['ok'=>true]);
    }
}
