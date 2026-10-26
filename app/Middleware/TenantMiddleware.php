<?php

declare(strict_types=1);

namespace WTSBill\Middleware;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Tenant Isolation & Context Verification Middleware.
 * Prevents cross-tenant access and enforces company and branch boundary security.
 */
class TenantMiddleware
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function handle(Request $request): ?Response
    {
        // Must be authenticated to access tenant-scoped routes
        if (!$this->authService->check()) {
            if ($request->expectsJson()) {
                return Response::error('Unauthenticated.', 401);
            }
            return Response::redirect('/login');
        }

        $sessionCompanyId = Session::getCompanyId();

        // If company context is missing from session, populate from user
        if ($sessionCompanyId === null) {
            $user = $this->authService->user();
            $sessionCompanyId = (int) ($user['current_company_id'] ?? 1);
            Session::setTenantContext($sessionCompanyId);
        }

        // Cross-Tenant Boundary Check:
        // If client explicitly passes a company_id in URL/body/headers, it MUST match the session's company_id
        $requestedCompanyId = $request->input('company_id') ?? $request->getHeader('x-company-id');
        if ($requestedCompanyId !== null) {
            $reqComp = (int) $requestedCompanyId;
            if ($reqComp !== $sessionCompanyId) {
                // Reject unauthorized cross-tenant attempt
                return Response::error('Forbidden: Cross-tenant access is strictly prohibited.', 403);
            }
        }

        return null;
    }

    public function __invoke(Request $request): ?Response
    {
        return $this->handle($request);
    }
}

// Backward compatibility alias
if (!class_exists('App\Middleware\TenantMiddleware', false)) {
    class_alias(TenantMiddleware::class, 'App\Middleware\TenantMiddleware');
}
