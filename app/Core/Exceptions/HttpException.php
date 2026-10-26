<?php

declare(strict_types=1);

namespace WTSBill\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Standard HTTP Exception for WTSBill ERP.
 */
class HttpException extends RuntimeException
{
    protected int $statusCode;
    protected array $errors;
    protected array $headers;

    public function __construct(
        int $statusCode = 500,
        string $message = 'Internal Server Error',
        array $errors = [],
        array $headers = [],
        ?Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->errors = $errors;
        $this->headers = $headers;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public static function badRequest(string $message = 'Bad Request', array $errors = []): self
    {
        return new self(400, $message, $errors);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self(401, $message);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return new self(404, $message);
    }

    public static function methodNotAllowed(string $message = 'Method Not Allowed'): self
    {
        return new self(405, $message);
    }

    public static function internal(string $message = 'Internal Server Error'): self
    {
        return new self(500, $message);
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Exceptions\HttpException', false)) {
    class_alias(HttpException::class, 'App\Core\Exceptions\HttpException');
}
