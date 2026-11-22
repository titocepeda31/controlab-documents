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
use PDO;
use Throwable;

final class UsuarioController {
    public function __construct(private Database $db) {}

    public function index(Request $request,Response $response): Response {
        Access::admin($this->db);
        $rows=$this->db->connection()->query(
            'SELECT u.id,u.email,u.activo,a.nombre,a.rol,a.empresa_id,e.razon_social AS empresa '
            .'FROM usuarios u JOIN usuario_accesos a ON a.usuario_id=u.id '
            .'LEFT JOIN empresas e ON e.id=a.empresa_id ORDER BY u.id DESC LIMIT 200'
        )->fetchAll();
        return Http::json($response,['usuarios'=>$rows]);
    }

    private function validateProfile(array $body): array {
        $name=trim((string)($body['nombre']??''));
        $email=mb_strtolower(trim((string)($body['email']??'')));
        $role=(string)($body['rol']??'');
        $eid=filter_var($body['empresa_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
        if(mb_strlen($name)<2 || mb_strlen($name)>160)throw new InvalidArgumentException('Nombre de 2 a 160 caracteres obligatorio');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>180)throw new InvalidArgumentException('Correo inválido');
        if(!in_array($role,['administrador','laboratorista','digitalizador','cliente'],true))throw new InvalidArgumentException('Perfil no permitido');
        if($role==='cliente' && !$eid)throw new InvalidArgumentException('Debes asociar la empresa del cliente');
        if($role!=='cliente')$eid=null;
        if($eid){
            $q=$this->db->connection()->prepare('SELECT id FROM empresas WHERE id=?');
            $q->execute([$eid]);
            if(!$q->fetchColumn())throw new InvalidArgumentException('Empresa inexistente');
        }
        return [$name,$email,$role,$eid];
    }

    public function store(Request $request,Response $response): Response {
        Access::admin($this->db);Security::csrf($request);
        $body=Http::jsonBody($request);
        [$name,$email,$role,$eid]=$this->validateProfile($body);
        $pdo=$this->db->connection();
        $q=$pdo->prepare('SELECT id FROM usuarios WHERE email=? LIMIT 1');
        $q->execute([$email]);
        if($q->fetchColumn())throw new InvalidArgumentException('Ya existe una cuenta con ese correo');
        // Contraseña aleatoria generada en el servidor; solo se devuelve al crearla.
        $password=bin2hex(random_bytes(12));
        $hash=password_hash($password,PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('INSERT INTO usuarios (email,password_hash,activo) VALUES (?,?,1)');
            $q->execute([$email,$hash]);$id=(int)$pdo->lastInsertId();
            $q=$pdo->prepare('INSERT INTO usuario_accesos (usuario_id,nombre,rol,empresa_id) VALUES (?,?,?,?)');
            $q->execute([$id,$name,$role,$eid]);
            if(in_array($role,['administrador','laboratorista'],true)){
                $q=$pdo->prepare('INSERT INTO informe_revisores(usuario_id,habilitado) VALUES (?,1) ON DUPLICATE KEY UPDATE habilitado=1');
                $q->execute([$id]);
            }
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return Http::json($response,['id'=>$id,'email'=>$email,'nombre'=>$name,'rol'=>$role,'contrasena_temporal'=>$password],201);
    }

    public function update(Request $request,Response $response,array $args): Response {
        $actor=Access::admin($this->db);Security::csrf($request);
        $id=(int)$args['id'];$body=Http::jsonBody($request);
        if($id===$actor['id'])throw new InvalidArgumentException('Para cambiar tu propio perfil contacta a otro administrador');
        $pdo=$this->db->connection();
        $q=$pdo->prepare('SELECT u.email,a.nombre,a.rol,a.empresa_id,u.activo FROM usuarios u JOIN usuario_accesos a ON a.usuario_id=u.id WHERE u.id=?');
        $q->execute([$id]);$old=$q->fetch();
        if(!$old)throw new RuntimeException('Usuario no encontrado',404);
        $input=[
            'nombre'=>$body['nombre']??$old['nombre'],
            'email'=>$old['email'],
            'rol'=>$body['rol']??$old['rol'],
            'empresa_id'=>array_key_exists('empresa_id',$body)?$body['empresa_id']:$old['empresa_id']
        ];
        [$name,$email,$role,$eid]=$this->validateProfile($input);
        $active=isset($body['activo'])?(int)(bool)$body['activo']:(int)$old['activo'];
        $pdo->beginTransaction();
        try {
            if($old['rol']==='administrador' && ($role!=='administrador'||!$active)){
                $q=$pdo->query("SELECT COUNT(*) FROM usuarios u JOIN usuario_accesos a ON a.usuario_id=u.id WHERE a.rol='administrador' AND u.activo=1");
                if((int)$q->fetchColumn()<=1)throw new InvalidArgumentException('No puedes deshabilitar el último administrador');
            }
            $q=$pdo->prepare('UPDATE usuarios SET activo=? WHERE id=?');$q->execute([$active,$id]);
            $q=$pdo->prepare('UPDATE usuario_accesos SET nombre=?,rol=?,empresa_id=? WHERE usuario_id=?');
            $q->execute([$name,$role,$eid,$id]);
            $canReview=$active && in_array($role,['administrador','laboratorista'],true);
            $q=$pdo->prepare('INSERT INTO informe_revisores(usuario_id,habilitado) VALUES (?,?) ON DUPLICATE KEY UPDATE habilitado=VALUES(habilitado)');
            $q->execute([$id,(int)$canReview]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return Http::json($response,['ok'=>true]);
    }

    public function password(Request $request,Response $response): Response {
        $user=Access::current($this->db);Security::csrf($request);
        $body=Http::jsonBody($request);
        $old=(string)($body['actual']??'');$new=(string)($body['nueva']??'');
        if(strlen($new)<12 || strlen($new)>128)throw new InvalidArgumentException('Contraseña nueva de 12 a 128 caracteres');
        $q=$this->db->connection()->prepare('SELECT password_hash FROM usuarios WHERE id=?');
        $q->execute([$user['id']]);
        if(!password_verify($old,(string)$q->fetchColumn()))throw new RuntimeException('Contraseña actual incorrecta',403);
        $q=$this->db->connection()->prepare('UPDATE usuarios SET password_hash=? WHERE id=?');
        $q->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);
        return Http::json($response,['ok'=>true]);
    }
}
