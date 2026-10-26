<?php

declare(strict_types=1);

namespace WTSBill\Validators;

use WTSBill\Core\Exceptions\ValidationException;

/**
 * Login Request Validator for WTSBill ERP.
 */
class LoginValidator
{
    /**
     * Validate login input data.
     *
     * @param array $data Input data
     * @return array Cleaned and sanitized credentials
     * @throws ValidationException If validation fails
     */
    public static function validate(array $data): array
    {
        $errors = [];

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        // Validate email
        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // Validate password
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'email' => strtolower($email),
            'password' => $password,
        ];
    }
}

// Backward compatibility alias
if (!class_exists('App\Validators\LoginValidator', false)) {
    class_alias(LoginValidator::class, 'App\Validators\LoginValidator');
}
