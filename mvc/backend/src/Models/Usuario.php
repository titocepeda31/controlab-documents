<?php
declare(strict_types=1);
namespace Demo\Models;

use Demo\Core\Database;

final class Usuario {
    public function __construct(private Database $db) {}
    public function findActiveByEmail(string $email): array|false {
        $q=$this->db->connection()->prepare('SELECT id,password_hash FROM usuarios WHERE email=? AND activo=1 LIMIT 1');
        $q->execute([$email]);
        return $q->fetch();
    }
}
