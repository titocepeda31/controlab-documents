<?php
declare(strict_types=1);
namespace Demo\Core;

use PDO;

final class Database {
    private ?PDO $pdo = null;
    public function __construct(private array $config) {}
    public function connection(): PDO {
        if ($this->pdo === null) {
            $dsn = 'mysql:host=' . $this->config['db_host'] . ';dbname=' . $this->config['db_name'] . ';charset=utf8mb4';
            $this->pdo = new PDO($dsn, $this->config['db_user'], $this->config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return $this->pdo;
    }
}
