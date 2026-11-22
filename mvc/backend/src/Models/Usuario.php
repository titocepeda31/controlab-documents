<?php
declare(strict_types=1);
namespace Demo\Models;

use Demo\Core\Database;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Usuario {
    public const ROLES = ['administrador','laboratorista','digitador'];
    public function __construct(private Database $db) {}

    public function findActiveByEmail(string $email): array|false {
        $q=$this->db->connection()->prepare(
            'SELECT u.id,u.password_hash,p.rol,p.nombre,p.version_credenciales '.
            'FROM usuarios u JOIN usuario_perfiles p ON p.usuario_id=u.id '.
            'WHERE u.email=? AND u.activo=1 LIMIT 1'
        );
        $q->execute([$email]);
        return $q->fetch();
    }

    public function findActiveById(int $id): array|false {
        if ($id < 1) return false;
        $q=$this->db->connection()->prepare(
            'SELECT u.id,u.email,p.nombre,p.rol,p.version_credenciales '.
            'FROM usuarios u JOIN usuario_perfiles p ON p.usuario_id=u.id '.
            'WHERE u.id=? AND u.activo=1 LIMIT 1'
        );
        $q->execute([$id]);
        return $q->fetch();
    }

    /** No expone hashes, claves, ni permisos de sesión. */
    public function all(): array {
        return $this->db->connection()->query(
            'SELECT u.id,u.email,u.activo,p.nombre,p.rol,p.fecha_alta '.
            'FROM usuarios u LEFT JOIN usuario_perfiles p ON p.usuario_id=u.id '.
            'ORDER BY u.id ASC LIMIT 500'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function validateName(mixed $value): string {
        if (!is_string($value)) throw new InvalidArgumentException('Indica el nombre del usuario');
        $name=trim($value);
        if (mb_strlen($name)<2 || mb_strlen($name)>160) throw new InvalidArgumentException('El nombre debe tener entre 2 y 160 caracteres');
        return $name;
    }

    private static function validateRole(mixed $value): string {
        if (!is_string($value) || !in_array($value,self::ROLES,true)) throw new InvalidArgumentException('Selecciona un perfil válido');
        return $value;
    }

    private static function validatePassword(mixed $value): string {
        if (!is_string($value) || strlen($value)<12 || strlen($value)>128)
            throw new InvalidArgumentException('La contraseña debe tener entre 12 y 128 bytes');
        if (preg_match('/[\r\n]/',$value)) throw new InvalidArgumentException('La contraseña no puede contener saltos de línea');
        return $value;
    }

    private static function duplicateEmail(Throwable $e): bool {
        return $e instanceof PDOException && $e->getCode()==='23000';
    }

    public function create(array $input): int {
        $name=self::validateName($input['nombre']??null);
        $role=self::validateRole($input['rol']??null);
        $email=is_string($input['email']??null)?mb_strtolower(trim($input['email'])):'';
        if (strlen($email)>180||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Correo electrónico inválido');
        $password=self::validatePassword($input['clave']??null);
        $hash=password_hash($password,PASSWORD_DEFAULT);
        $pdo=$this->db->connection();
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('INSERT INTO usuarios (email,password_hash,activo) VALUES (?,?,1)');
            $q->execute([$email,$hash]);
            $id=(int)$pdo->lastInsertId();
            $q=$pdo->prepare('INSERT INTO usuario_perfiles (usuario_id,nombre,rol) VALUES (?,?,?)');
            $q->execute([$id,$name,$role]);
            $pdo->commit();
            return $id;
        } catch(Throwable $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            if(self::duplicateEmail($e))throw new RuntimeException('El correo ya está registrado',409);
            throw $e;
        }
    }

    /** Cambios de perfil y estado. Nunca elimina al autor de un informe. */
    public function update(int $id,array $input): void {
        if ($id<1) throw new InvalidArgumentException('Usuario inválido');
        $name=self::validateName($input['nombre']??null);
        $role=self::validateRole($input['rol']??null);
        if (!is_bool($input['activo']??null)) throw new InvalidArgumentException('El estado activo debe ser verdadero o falso');
        $active=$input['activo'];
        $pdo=$this->db->connection();
        $pdo->beginTransaction();
        try {
            // Orden consistente al bloquear administradores activos evita dos degradaciones simultáneas.
            $admins=$pdo->query("SELECT p.usuario_id FROM usuario_perfiles p JOIN usuarios u ON u.id=p.usuario_id WHERE p.rol='administrador' AND u.activo=1 ORDER BY p.usuario_id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            $q=$pdo->prepare('SELECT u.id,u.activo,p.rol FROM usuarios u JOIN usuario_perfiles p ON p.usuario_id=u.id WHERE u.id=? FOR UPDATE');
            $q->execute([$id]);$current=$q->fetch(PDO::FETCH_ASSOC);
            if(!$current)throw new RuntimeException('Usuario no encontrado o sin perfil',404);
            if ($current['rol']==='administrador' && (int)$current['activo']===1
                && (!$active || $role!=='administrador') && count($admins)<=1)
                throw new RuntimeException('Debe quedar al menos un administrador activo',409);
            $q=$pdo->prepare('UPDATE usuario_perfiles SET nombre=?,rol=? WHERE usuario_id=?');
            $q->execute([$name,$role,$id]);
            $q=$pdo->prepare('UPDATE usuarios SET activo=? WHERE id=?');
            $q->execute([(int)$active,$id]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    /** Un reinicio de clave revoca todas las sesiones anteriores de esa persona. */
    public function resetPassword(int $id,mixed $newPassword): void {
        if($id<1)throw new InvalidArgumentException('Usuario inválido');
        $hash=password_hash(self::validatePassword($newPassword),PASSWORD_DEFAULT);
        $pdo=$this->db->connection();
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT u.id FROM usuarios u JOIN usuario_perfiles p ON p.usuario_id=u.id WHERE u.id=? FOR UPDATE');
            $q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('Usuario no encontrado',404);
            $q=$pdo->prepare('UPDATE usuarios SET password_hash=? WHERE id=?');
            $q->execute([$hash,$id]);
            $q=$pdo->prepare('UPDATE usuario_perfiles SET version_credenciales=version_credenciales+1 WHERE usuario_id=?');
            $q->execute([$id]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
