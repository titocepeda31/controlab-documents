<?php
declare(strict_types=1);
namespace Demo\Models;

use Demo\Core\Database;
use PDO;

final class Empresa {
    public function __construct(private Database $db) {}
    public function all(): array {
        return $this->db->connection()->query('SELECT id,razon_social,rut,contacto,email FROM empresas ORDER BY razon_social LIMIT 100')->fetchAll();
    }
    // Llamar dentro de la transacción de creación de informe.
    public function findOrCreateByName(string $name): int {
        $pdo=$this->db->connection();
        $q=$pdo->prepare('SELECT id FROM empresas WHERE razon_social=? LIMIT 1');
        $q->execute([$name]);
        $id=$q->fetchColumn();
        if ($id) return (int)$id;
        $q=$pdo->prepare('INSERT INTO empresas (razon_social) VALUES (?)');
        $q->execute([$name]);
        return (int)$pdo->lastInsertId();
    }
}
