<?php
namespace App\Core;

use PDO;
use PDOException;

class Database 
{
    private ?PDO $pdo = null;

    public function getConnection(): PDO 
    {
        if ($this->pdo === null) {
            $config = require __DIR__ . '/../../config/database.php';
            $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
            
            try {
                $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                throw new \RuntimeException("Veritabanı bağlantı hatası: " . $e->getMessage());
            }
        }

        return $this->pdo;
    }

    public function beginTransaction(): bool 
    {
        return $this->getConnection()->beginTransaction();
    }

    public function commit(): bool 
    {
        return $this->getConnection()->commit();
    }

    public function rollBack(): bool 
    {
        return $this->getConnection()->rollBack();
    }
}