<?php

declare(strict_types=1);

namespace WTSBill\Middleware;

use WTSBill\Auth\Gate;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Services\AuthService;

/**
 * Role-Based Access Control (RBAC) Authorization Middleware.
 * Enforces permissions or role boundary checks with strict DEFAULT DENY.
 */
class AuthorizationMiddleware
{
    private array $requiredPermissionsOrRoles;
    private AuthService $authService;

    public function __construct(array|string $required = [], ?AuthService $authService = null)
    {
        $this->requiredPermissionsOrRoles = (array) $required;
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

        // 2. Default DENY: if no permission/role specified, deny
        if (empty($this->requiredPermissionsOrRoles)) {
            return $this->forbiddenResponse($request, 'DENIED');
        }

        // 3. Check authorization against Gate
        $isAuthorized = false;
        $attempted = implode(', ', $this->requiredPermissionsOrRoles);

        foreach ($this->requiredPermissionsOrRoles as $req) {
            if (str_contains($req, '.')) {
                // It is a permission slug (e.g. invoices.view)
                if (Gate::can($req)) {
                    $isAuthorized = true;
                    break;
                }
            } else {
                // It is a role name (e.g. ADMIN, ACCOUNTANT)
                if (Gate::isRole($req) || $this->authService->hasRole($req)) {
                    $isAuthorized = true;
                    break;
                }
            }
        }

        if (!$isAuthorized) {
            // Log security audit for denied attempt
            $user = Gate::user();
            $role = Gate::role();
            \WTSBill\Services\AuditLogger::log(
                action: 'permission_denied',
                module: 'route',
                entityType: 'ROUTE',
                entityId: null,
                entityLabel: $request->getUri() ?: '/',
                oldValues: null,
                newValues: ['attempted' => $attempted, 'role' => $role],
                status: 'denied',
                notes: "Permission denied for '{$attempted}' on {$request->getUri()}",
                userId: $user ? (int)$user['id'] : null,
                userName: $user ? (string)$user['name'] : 'Guest',
                userRole: $role
            );

            return $this->forbiddenResponse($request, $attempted);
        }

        return null;
    }

    private function forbiddenResponse(Request $request, string $attempted): Response
    {
        if ($request->expectsJson()) {
            return Response::error("Forbidden: You do not have permission '{$attempted}' to access this resource.", 403);
        }

        return Response::html(
            '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>403 Forbidden &mdash; WTSBill ERP</title>' .
            '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0b1120;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}' .
            '.card{background:#151f32;padding:2.5rem;border-radius:1rem;text-align:center;max-width:480px;border:1px solid #ef4444;box-shadow:0 20px 40px rgba(0,0,0,0.5);}' .
            'h1{color:#f87171;font-size:1.75rem;margin-bottom:0.5rem;}p{color:#94a3b8;font-size:0.95rem;line-height:1.5;margin-bottom:1.5rem;}' .
            '.meta{background:rgba(15,23,42,0.6);padding:0.75rem;border-radius:0.5rem;font-size:0.8rem;color:#cbd5e1;font-family:monospace;margin-bottom:1.5rem;text-align:left;}' .
            'a{display:inline-block;background:#0284c7;color:#fff;padding:0.6rem 1.25rem;border-radius:0.5rem;text-decoration:none;font-weight:500;}</style></head><body>' .
            '<div class="card"><h1>403 Forbidden</h1>' .
            '<p>Your current role does not have authorization to access this page.</p>' .
            '<div class="meta">Target Resource: ' . htmlspecialchars($request->getUri(), ENT_QUOTES, 'UTF-8') . '<br>Required: ' . htmlspecialchars($attempted, ENT_QUOTES, 'UTF-8') . '</div>' .
            '<p><a href="/">&larr; Return to Dashboard</a></p></div></body></html>',
            403
        );
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
