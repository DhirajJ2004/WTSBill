<?php

declare(strict_types=1);

namespace WTSBill\Core;

use PDO;
use Throwable;
use WTSBill\Exceptions\DatabaseException;

/**
 * ACID Transaction and Nested Savepoint Manager for WTSBill ERP.
 * Guarantees atomic transaction boundaries, nested savepoints, and automatic rollback on failure.
 */
class DatabaseTransaction
{
    private PDO $pdo;
    private int $depth = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Begin transaction or create savepoint if already inside transaction.
     */
    public function begin(): bool
    {
        if ($this->depth === 0) {
            $success = $this->pdo->beginTransaction();
            $this->depth = 1;
            return $success;
        }

        $this->depth++;
        $savepoint = 'TRANS_POINT_' . $this->depth;
        $this->pdo->exec("SAVEPOINT {$savepoint}");
        return true;
    }

    /**
     * Commit transaction or release savepoint if nested.
     */
    public function commit(): bool
    {
        if ($this->depth <= 1) {
            $success = $this->pdo->commit();
            $this->depth = 0;
            return $success;
        }

        $savepoint = 'TRANS_POINT_' . $this->depth;
        $this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");
        $this->depth--;
        return true;
    }

    /**
     * Rollback transaction or rollback to savepoint if nested.
     */
    public function rollBack(): bool
    {
        if ($this->depth <= 1) {
            $success = $this->pdo->rollBack();
            $this->depth = 0;
            return $success;
        }

        $savepoint = 'TRANS_POINT_' . $this->depth;
        $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
        $this->depth--;
        return true;
    }

    /**
     * Execute an ACID transaction callback with automatic rollback on error.
     */
    public function execute(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback($this->pdo);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function isActive(): bool
    {
        return $this->depth > 0;
    }

    public function reset(): void
    {
        $this->depth = 0;
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\DatabaseTransaction', false)) {
    class_alias(DatabaseTransaction::class, 'App\Core\DatabaseTransaction');
}
