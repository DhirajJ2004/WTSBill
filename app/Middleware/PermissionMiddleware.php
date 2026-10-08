<?php

namespace App\Middleware;

use App\Auth\WorkspaceContext;

class PermissionMiddleware
{
    /**
     * Check if the authenticated user has the required module & action permission.
     *
     * @param string $module e.g. 'invoices', 'parties', 'inventory'
     * @param string $action e.g. 'view', 'create', 'edit', 'delete'
     * @param bool $isApi
     * @throws AuthorizationException
     */
    public static function handle(string $module, string $action = 'view', bool $isApi = false): void
    {
        $role = strtoupper(WorkspaceContext::getRole());

        // Super Admin & Owner bypass individual permission checks
        if ($role === 'SUPER_ADMIN' || $role === 'ADMIN' || $role === 'OWNER') {
            return;
        }

        if (!WorkspaceContext::hasPermission($module, $action)) {
            if ($isApi) {
                throw new AuthorizationException("Forbidden: You do not have permission to {$action} {$module}.", 403);
            }
            http_response_code(403);
            echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style='font-family: sans-serif; text-align: center; padding: 60px;'><h2 style='color:#dc2626;'>403 Forbidden</h2><p>You do not have permission to {$action} {$module}.</p><p><a href='" . (function_exists('url') ? url('/dashboard') : '/') . "'>Return to Dashboard</a></p></body></html>";
            exit();
        }
    }
}
