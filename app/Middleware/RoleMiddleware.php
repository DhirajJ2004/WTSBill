<?php

namespace App\Middleware;

use App\Auth\WorkspaceContext;

class RoleMiddleware
{
    public static function handle(array $allowedRoles, bool $isApi = false): void
    {
        $currentRole = strtoupper(WorkspaceContext::getRole());

        // Super Admin & Owner have universal privilege
        if ($currentRole === 'SUPER_ADMIN' || $currentRole === 'OWNER') {
            return;
        }

        $upperAllowed = array_map('strtoupper', $allowedRoles);

        if (!in_array($currentRole, $upperAllowed, true)) {
            if ($isApi) {
                throw new AuthorizationException("Forbidden: Role '{$currentRole}' lacks permission to perform this action.", 403);
            }
            http_response_code(403);
            echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style='font-family: sans-serif; text-align: center; padding: 60px;'><h2 style='color:#dc2626;'>403 Forbidden</h2><p>Your role ({$currentRole}) is not authorized to access this page.</p><p><a href='" . (function_exists('url') ? url('/dashboard') : '/') . "'>Return to Dashboard</a></p></body></html>";
            exit();
        }
    }
}
