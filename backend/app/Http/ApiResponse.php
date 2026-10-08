<?php

namespace App\Http;

class ApiResponse
{
    /**
     * Return standard SUCCESS response:
     * {
     *   "status": "success",
     *   "data": {}
     * }
     */
    public static function success($data = [], int $code = 200): void
    {
        response_json([
            'status' => 'success',
            'data'   => is_object($data) && method_exists($data, 'toArray') ? $data->toArray() : $data,
        ], $code);
    }

    /**
     * Return standard CREATED response (HTTP 201).
     */
    public static function created($data = []): void
    {
        static::success($data, 201);
    }

    /**
     * Return standard ERROR response:
     * {
     *   "status": "error",
     *   "message": "...",
     *   "errors": {}
     * }
     */
    public static function error(string $message, $errors = [], int $code = 400): void
    {
        $errObj = empty($errors) ? (object)[] : $errors;
        response_json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errObj,
        ], $code);
    }

    public static function badRequest(string $message = 'Bad Request', $errors = []): void
    {
        static::error($message, $errors, 400);
    }

    public static function unauthorized(string $message = 'Authentication required.'): void
    {
        static::error($message, [], 401);
    }

    public static function forbidden(string $message = 'Access denied. You lack permission for this action.'): void
    {
        static::error($message, [], 403);
    }

    public static function notFound(string $message = 'Resource not found.'): void
    {
        static::error($message, [], 404);
    }

    public static function validationError($errors, string $message = 'Validation failed.'): void
    {
        static::error($message, $errors, 422);
    }

    public static function tooManyRequests(string $message = 'Too many requests. Please slow down.'): void
    {
        static::error($message, [], 429);
    }

    public static function serverError(string $message = 'An internal server error occurred.'): void
    {
        static::error($message, [], 500);
    }
}
