<?php

namespace App\Auth;

use App\Models\User;
use App\Services\AuditLogService;

class LogoutService
{
    public static function logout(User $user, ?string $rawToken): void
    {
        if ($rawToken) {
            SessionService::revokeSession($rawToken);
        }

        AuditLogService::log(
            $user->current_company_id,
            $user->name,
            'LOGOUT',
            'User',
            $user->id,
            "User {$user->email} logged out."
        );
    }
}
