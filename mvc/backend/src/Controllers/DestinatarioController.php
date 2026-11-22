<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Access;
use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use InvalidArgumentException;
use RuntimeException;

final class DestinatarioController {
    public function __construct(private Database $db) {}
    public function assign(Request $request,Response $response,array $args):Response {
        $user=Access::admin($this->db);
        Security::csrf($request);
        $body=Http::jsonBody($request);
        $id=(int)$args['id'];$pdo=$this->db->connection();
        $q=$pdo->prepare('SELECT id FROM informes WHERE id=?');$q->execute([$id]);
        if(!$q->fetchColumn())throw new RuntimeException('Informe inexistente',404);
        if(empty($body['empresa_id'])){
            $q=$pdo->prepare('DELETE FROM informe_destinatarios WHERE informe_id=?');$q->execute([$id]);
            return Http::json($response,['ok'=>true,'empresa_id'=>null]);
        }
        $eid=filter_var($body['empresa_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$eid)throw new InvalidArgumentException('Empresa destinataria inválida');
        $q=$pdo->prepare('SELECT id FROM empresas WHERE id=?');$q->execute([$eid]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('Empresa inexistente');
        $q=$pdo->prepare(
            'INSERT INTO informe_destinatarios(informe_id,empresa_id,asignado_por) VALUES(?,?,?) '
            .'ON DUPLICATE KEY UPDATE empresa_id=VALUES(empresa_id),asignado_por=VALUES(asignado_por)'
        );
        $q->execute([$id,$eid,$user['id']]);
        return Http::json($response,['ok'=>true,'empresa_id'=>(int)$eid]);
    }
}
