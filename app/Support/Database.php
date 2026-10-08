<?php

namespace App\Support;

use PDO;
use Exception;

class Database
{
    private static ?PDO $pdo = null;

    public static function getPdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $config = require __DIR__ . '/../../config/database.php';
        $db = $config['connections'][$config['default']] ?? $config['connections']['mysql'];

        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
        
        $options = $db['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $db['username'], $db['password'], $options);
            return self::$pdo;
        } catch (Exception $e) {
            error_log("[DATABASE CONNECTION ERROR] " . $e->getMessage());
            throw $e;
        }
    }

    public static function select(string $query, array $bindings = []): array
    {
        $stmt = self::getPdo()->prepare($query);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function selectOne(string $query, array $bindings = []): ?array
    {
        $stmt = self::getPdo()->prepare($query);
        $stmt->execute($bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function insert(string $query, array $bindings = []): int
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare($query);
        $stmt->execute($bindings);
        return (int)$pdo->lastInsertId();
    }

    public static function update(string $query, array $bindings = []): int
    {
        $stmt = self::getPdo()->prepare($query);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public static function delete(string $query, array $bindings = []): int
    {
        $stmt = self::getPdo()->prepare($query);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public static function transaction(callable $callback)
    {
        $pdo = self::getPdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
