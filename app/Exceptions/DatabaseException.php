<?php

declare(strict_types=1);

namespace WTSBill\Exceptions;

use PDOException;
use RuntimeException;
use Throwable;
use WTSBill\Core\Logger;

/**
 * Database Exception for WTSBill ERP.
 * Intercepts PDO errors, redacts credentials/sensitive tokens, and prevents internal SQL leaks in client responses.
 */
class DatabaseException extends RuntimeException
{
    protected string $sqlQuery;
    protected array $bindings;
    protected string $sqlState;
    protected int $sqlCode;

    public function __construct(
        string $message = 'A database error occurred while processing the request.',
        int $code = 0,
        string $sqlQuery = '',
        array $bindings = [],
        string $sqlState = '',
        ?Throwable $previous = null
    ) {
        $this->sqlQuery = Logger::redactString($sqlQuery);
        $this->bindings = Logger::redactArray($bindings);
        $this->sqlState = $sqlState;
        $this->sqlCode = $code;

        parent::__construct($message, $code, $previous);
    }

    public static function fromPDOException(PDOException $e, string $sql = '', array $bindings = []): self
    {
        $rawMessage = $e->getMessage();
        $code = (int) $e->getCode();
        $sqlState = '';

        if (preg_match('/SQLSTATE\[([0-9A-Z]+)\]/i', $rawMessage, $matches)) {
            $sqlState = $matches[1];
        }

        // Redact credentials from message
        $sanitizedMessage = Logger::redactString($rawMessage);

        // Strip password/user credentials from DSN failure messages
        $sanitizedMessage = preg_replace('/(password|user|host)=([^;\\s]+)/i', '$1=[REDACTED]', $sanitizedMessage);

        // Safe message for public display
        $safeDisplayMessage = 'A database error occurred. The operation could not be completed.';

        // Log detailed diagnostic internally (redacted)
        Logger::error("Database Exception [{$sqlState}]: {$sanitizedMessage}", [
            'query' => Logger::redactString($sql),
            'bindings' => Logger::redactArray($bindings),
            'code' => $code,
        ]);

        return new self($safeDisplayMessage, $code, $sql, $bindings, $sqlState, $e);
    }

    public function getSqlQuery(): string
    {
        return $this->sqlQuery;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getSqlState(): string
    {
        return $this->sqlState;
    }

    public function getSqlCode(): int
    {
        return $this->sqlCode;
    }
}

// Backward compatibility aliases
if (!class_exists('App\Exceptions\DatabaseException', false)) {
    class_alias(DatabaseException::class, 'App\Exceptions\DatabaseException');
}
if (!class_exists('WTSBill\Core\Exceptions\DatabaseException', false)) {
    class_alias(DatabaseException::class, 'WTSBill\Core\Exceptions\DatabaseException');
}
