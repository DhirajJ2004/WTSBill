<?php

declare(strict_types=1);

namespace WTSBill\Core;

use PDO;
use PDOException;
use RuntimeException;
use WTSBill\Exceptions\DatabaseException;

/**
 * Native PHP 8+ PDO Database Manager for WTSBill ERP.
 * Provides strict prepared statements, ACID transaction encapsulation, connection pooling, and credential redaction.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static ?DatabaseTransaction $transaction = null;
    private static array $statements = [];
    private static array $queryCache = [];
    private static float $totalQueryTimeMs = 0.0;
    private static int $queryCount = 0;

    /**
     * Get or initialize the PDO connection instance.
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }
        return self::$pdo;
    }

    /**
     * Connect to MySQL/MariaDB database using Native PDO.
     */
    public static function connect(?array $config = null): PDO
    {
        if ($config === null) {
            $connectionName = Config::get('database.default', 'mysql');
            $config = Config::get("database.connections.{$connectionName}");
        }

        if (!$config) {
            throw new RuntimeException("Database configuration not found.");
        }

        // Validate required configuration fields
        self::validateConfig($config);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            self::$transaction = new DatabaseTransaction(self::$pdo);
            return self::$pdo;
        } catch (PDOException $e) {
            // Throw sanitized exception without credentials
            throw DatabaseException::fromPDOException($e, 'CONNECT');
        }
    }

    /**
     * Validate mandatory database connection configuration.
     */
    public static function validateConfig(array $config): void
    {
        $required = ['host', 'database', 'username'];
        $missing = [];

        foreach ($required as $field) {
            if (!isset($config[$field]) || trim((string) $config[$field]) === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new RuntimeException("Database configuration missing mandatory fields: " . implode(', ', $missing));
        }

        if (isset($config['port']) && (!is_numeric($config['port']) || (int) $config['port'] <= 0 || (int) $config['port'] > 65535)) {
            throw new RuntimeException("Database configuration has invalid port: " . $config['port']);
        }
    }

    /**
     * Get or create a cached PDOStatement instance for a given SQL query.
     */
    public static function prepare(string $sql): \PDOStatement
    {
        return self::$statements[$sql] ??= self::pdo()->prepare($sql);
    }

    /**
     * Clear all cached prepared statements.
     */
    public static function clearStatementCache(): void
    {
        self::$statements = [];
    }

    /**
     * Execute a SELECT query with prepared statement and return all matching records.
     */
    public static function select(string $sql, array $bindings = []): array
    {
        $start = microtime(true);
        try {
            $stmt = self::prepare($sql);
            $stmt->execute($bindings);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            self::$queryCount++;
            self::$totalQueryTimeMs += (microtime(true) - $start) * 1000.0;
            return $result;
        } catch (PDOException $e) {
            throw DatabaseException::fromPDOException($e, $sql, $bindings);
        }
    }

    /**
     * Execute a SELECT query and return the first matching record or null.
     */
    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $start = microtime(true);
        try {
            $stmt = self::prepare($sql);
            $stmt->execute($bindings);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            self::$queryCount++;
            self::$totalQueryTimeMs += (microtime(true) - $start) * 1000.0;
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            throw DatabaseException::fromPDOException($e, $sql, $bindings);
        }
    }

    /**
     * Execute an in-memory cached SELECT query with strict multi-tenant isolation.
     */
    public static function selectCached(string $sql, array $bindings = [], ?int $tenantId = null): array
    {
        $resolvedTenant = $tenantId ?? (class_exists(Session::class, false) ? (Session::getCompanyId() ?? 0) : 0);
        $cacheKey = $resolvedTenant . ':' . md5($sql . ':' . serialize($bindings));

        if (isset(self::$queryCache[$cacheKey])) {
            return self::$queryCache[$cacheKey];
        }

        $result = self::select($sql, $bindings);
        self::$queryCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Invalidate in-memory query cache (called automatically on mutations).
     */
    public static function clearQueryCache(): void
    {
        self::$queryCache = [];
    }

    public static function getQueryMetrics(): array
    {
        return [
            'count' => self::$queryCount,
            'time_ms' => round(self::$totalQueryTimeMs, 2),
            'cached_queries' => count(self::$queryCache),
            'cached_statements' => count(self::$statements),
        ];
    }

    public static function resetQueryMetrics(): void
    {
        self::$queryCount = 0;
        self::$totalQueryTimeMs = 0.0;
    }

    /**
     * Execute an INSERT query and return the last inserted ID.
     */
    public static function insert(string $table, array $data): int|string
    {
        self::clearQueryCache();

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $start = microtime(true);
        try {
            $stmt = self::prepare($sql);
            $stmt->execute($data);
            self::$queryCount++;
            self::$totalQueryTimeMs += (microtime(true) - $start) * 1000.0;
            return self::pdo()->lastInsertId();
        } catch (PDOException $e) {
            throw DatabaseException::fromPDOException($e, $sql, $data);
        }
    }

    /**
     * Execute an UPDATE query and return affected rows.
     */
    public static function update(string $table, array $data, string $whereClause, array $whereBindings = []): int
    {
        self::clearQueryCache();

        $setClauses = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $param = 'set_' . $column;
            $setClauses[] = "`{$column}` = :{$param}";
            $bindings[$param] = $value;
        }

        if (str_contains($whereClause, '?')) {
            $idx = 0;
            $whereClause = preg_replace_callback('/\?/', function() use (&$idx, &$bindings, $whereBindings) {
                $param = 'where_' . $idx;
                $bindings[$param] = $whereBindings[$idx] ?? null;
                $idx++;
                return ':' . $param;
            }, $whereClause);
            $mergedBindings = $bindings;
        } else {
            $mergedBindings = array_merge($bindings, $whereBindings);
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $setClauses),
            $whereClause
        );

        $start = microtime(true);
        try {
            $stmt = self::prepare($sql);
            $stmt->execute($mergedBindings);
            self::$queryCount++;
            self::$totalQueryTimeMs += (microtime(true) - $start) * 1000.0;
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw DatabaseException::fromPDOException($e, $sql, $mergedBindings);
        }
    }

    /**
     * Execute a DELETE query and return affected rows.
     */
    public static function delete(string $table, string $whereClause, array $whereBindings = []): int
    {
        self::clearQueryCache();

        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $whereClause);

        $start = microtime(true);
        try {
            $stmt = self::prepare($sql);
            $stmt->execute($whereBindings);
            self::$queryCount++;
            self::$totalQueryTimeMs += (microtime(true) - $start) * 1000.0;
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw DatabaseException::fromPDOException($e, $sql, $whereBindings);
        }
    }

    /**
     * Execute raw statement.
     */
    public static function statement(string $sql, array $bindings = []): bool
    {
        $start = microtime(true);
        try {
            $stmt = self::prepare($sql);
            $res = $stmt->execute($bindings);
            self::$queryCount++;
            self::$totalQueryTimeMs += (microtime(true) - $start) * 1000.0;
            return $res;
        } catch (PDOException $e) {
            throw DatabaseException::fromPDOException($e, $sql, $bindings);
        }
    }

    /**
     * Alias for statement() to execute arbitrary parameterized statements.
     */
    public static function execute(string $sql, array $bindings = []): bool
    {
        return self::statement($sql, $bindings);
    }


    // --- TRANSACTION LIFECYCLE DELEGATION ---

    public static function getTransaction(): DatabaseTransaction
    {
        if (self::$transaction === null) {
            self::connect();
        }
        return self::$transaction;
    }

    public static function beginTransaction(): bool
    {
        return self::getTransaction()->begin();
    }

    public static function commit(): bool
    {
        return self::getTransaction()->commit();
    }

    public static function rollBack(): bool
    {
        return self::getTransaction()->rollBack();
    }

    public static function transaction(callable $callback): mixed
    {
        return self::getTransaction()->execute($callback);
    }

    public static function isConnected(): bool
    {
        return self::$pdo !== null;
    }

    public static function disconnect(): void
    {
        if (self::$transaction !== null) {
            self::$transaction->reset();
        }
        self::$pdo = null;
        self::$transaction = null;
        self::$statements = [];
        self::$queryCache = [];
        self::$totalQueryTimeMs = 0.0;
        self::$queryCount = 0;
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Database', false)) {
    class_alias(Database::class, 'App\Core\Database');
}
