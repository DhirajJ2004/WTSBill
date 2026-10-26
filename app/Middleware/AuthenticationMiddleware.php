<?php

declare(strict_types=1);

namespace WTSBill\Middleware;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Authentication Middleware for WTSBill ERP.
 * Enforces authenticated session boundaries and handles login redirection.
 */
class AuthenticationMiddleware
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function handle(Request $request): ?Response
    {
        if (!$this->authService->check()) {
            if ($request->expectsJson()) {
                return Response::error('Unauthenticated. Please log in to continue.', 401);
            }

            // Save intended target URL for post-login redirect (Strict Open Redirect Prevention)
            $uri = $request->getUri();
            if (is_string($uri) && str_starts_with($uri, '/') && !str_starts_with($uri, '//') && !str_contains($uri, '\\') && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $uri)) {
                Session::set('url.intended', $uri);
            } else {
                Session::set('url.intended', '/');
            }

            return Response::redirect('/login');
        }

        return null;
    }

    public function __invoke(Request $request): ?Response
    {
        return $this->handle($request);
    }
}

// Backward compatibility alias
if (!class_exists('App\Middleware\AuthenticationMiddleware', false)) {
    class_alias(AuthenticationMiddleware::class, 'App\Middleware\AuthenticationMiddleware');
}
