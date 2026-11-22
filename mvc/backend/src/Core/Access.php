<?php
declare(strict_types=1);
namespace Demo\Core;

use RuntimeException;

final class Access {
    public static function user(Database $db,int $userId): ?array {
        if ($userId<1) return null;
        $q=$db->connection()->prepare(
            'SELECT u.id,u.email,a.nombre,a.rol,a.empresa_id FROM usuarios u '
            .'JOIN usuario_accesos a ON a.usuario_id=u.id '
            .'WHERE u.id=? AND u.activo=1 LIMIT 1'
        );
        $q->execute([$userId]);
        $u=$q->fetch();
        if (!$u || ($u['rol']==='cliente' && !(int)$u['empresa_id'])) return null;
        $u['id']=(int)$u['id'];
        $u['empresa_id']=isset($u['empresa_id'])?(int)$u['empresa_id']:null;
        return $u;
    }
    public static function current(Database $db): array {
        $userId=(int)($_SESSION['uid']??0);
        if (!$userId) throw new RuntimeException('Inicia sesión',401);
        $u=self::user($db,$userId);
        if (!$u) throw new RuntimeException('Usuario sin acceso autorizado',403);
        return $u;
    }
    public static function internal(Database $db): array {
        $u=self::current($db);
        if ($u['rol']==='cliente') throw new RuntimeException('Esta operación no está disponible para clientes',403);
        return $u;
    }
    /** Solo laboratoristas y administradores pueden registrar o corregir datos de ensayo. */
    public static function canEditReports(Database $db): array {
        $u=self::current($db);
        if (!in_array($u['rol'],['administrador','laboratorista'],true))
            throw new RuntimeException('Este perfil no puede crear ni modificar ensayos',403);
        return $u;
    }
    /** Los digitadores únicamente preparan el envío de registros aprobados. */
    public static function canDispatch(Database $db): array {
        $u=self::current($db);
        if (!in_array($u['rol'],['administrador','laboratorista','digitalizador'],true))
            throw new RuntimeException('Este perfil no puede enviar informes',403);
        return $u;
    }
    public static function admin(Database $db): array {
        $u=self::current($db);
        if ($u['rol']!=='administrador') throw new RuntimeException('Se requiere un administrador',403);
        return $u;
    }
}
