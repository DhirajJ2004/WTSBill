<?php

declare(strict_types=1);

namespace WTSBill\Middleware;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Services\AuthService;

/**
 * Role-Based Access Control (RBAC) Authorization Middleware.
 */
class AuthorizationMiddleware
{
    private array $allowedRoles;
    private AuthService $authService;

    public function __construct(array|string $allowedRoles = [], ?AuthService $authService = null)
    {
        $this->allowedRoles = (array) $allowedRoles;
        $this->authService = $authService ?? new AuthService();
    }

    public function handle(Request $request): ?Response
    {
        // 1. Must be authenticated first
        if (!$this->authService->check()) {
            if ($request->expectsJson()) {
                return Response::error('Unauthenticated.', 401);
            }
            return Response::redirect('/login');
        }

        // 2. If no specific roles specified, allow through
        if (empty($this->allowedRoles)) {
            return null;
        }

        // 3. Check role authorization
        if (!$this->authService->hasRole($this->allowedRoles)) {
            if ($request->expectsJson()) {
                return Response::error('Forbidden: You do not have permission to access this resource.', 403);
            }

            return Response::html(
                '<!DOCTYPE html><html><head><title>403 Forbidden</title>' .
                '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}' .
                '.card{background:#1e293b;padding:2.5rem;border-radius:1rem;text-align:center;max-width:440px;border:1px solid #ef4444;}' .
                'h1{color:#f87171;font-size:1.75rem;margin-bottom:0.5rem;}p{color:#94a3b8;font-size:0.95rem;line-height:1.5;}' .
                'a{color:#38bdf8;text-decoration:none;font-weight:500;}</style></head><body>' .
                '<div class="card"><h1>403 Forbidden</h1>' .
                '<p>Your current role does not have authorization to access this page.</p>' .
                '<p><a href="/">&larr; Return to Dashboard</a></p></div></body></html>',
                403
            );
        }

        return null;
    }

    public function __invoke(Request $request): ?Response
    {
        return $this->handle($request);
    }
}

// Backward compatibility alias
if (!class_exists('App\Middleware\AuthorizationMiddleware', false)) {
    class_alias(AuthorizationMiddleware::class, 'App\Middleware\AuthorizationMiddleware');
}
