<?php

declare(strict_types=1);

namespace WTSBill\Core\Exceptions;

/**
 * Validation Exception for business rules & request payload validation.
 */
class ValidationException extends HttpException
{
    public function __construct(array $errors = [], string $message = 'The given data was invalid.')
    {
        parent::__construct(422, $message, $errors);
    }

    public static function withMessages(array $errors): self
    {
        return new self($errors);
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Exceptions\ValidationException', false)) {
    class_alias(ValidationException::class, 'App\Core\Exceptions\ValidationException');
}
