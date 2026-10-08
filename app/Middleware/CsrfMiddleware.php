<?php

namespace App\Middleware;

class CsrfMiddleware
{
    /**
     * Verify CSRF token on state-changing HTTP methods.
     *
     * @param bool $isApi
     * @throws AuthorizationException
     */
    public static function handle(bool $isApi = false): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            // API requests with Bearer tokens can bypass session CSRF
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if ($isApi && preg_match('/Bearer\s+(\S+)/i', $authHeader)) {
                return;
            }

            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? null;
            $sessionToken = $_SESSION['csrf_token'] ?? null;

            if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
                if ($isApi) {
                    throw new AuthorizationException('Invalid or expired CSRF token.', 403);
                }
                http_response_code(403);
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style="font-family: sans-serif; text-align: center; padding: 50px;"><h2 style="color: #dc2626;">403 Forbidden</h2><p>Security token verification failed or expired. Please refresh the page and try again.</p><p><a href="javascript:history.back()">Return</a></p></body></html>';
                exit();
            }
        }
    }

    /**
     * Generate or retrieve the session CSRF token.
     */
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Generate hidden HTML input element with CSRF token.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
