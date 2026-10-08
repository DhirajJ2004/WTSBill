<?php

namespace App\SystemAdmin\Services;

use App\Models\UserSession;
use App\Audit\Services\AuditTrailService;
use Carbon\Carbon;

class SessionManagementService
{
    /**
     * Register a new active session.
     */
    public static function createSession(int $userId, int $companyId, string $token, ?string $device = 'Web Browser'): UserSession
    {
        return UserSession::create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'session_token' => $token,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Web',
            'device_info' => $device,
            'last_activity_at' => Carbon::now(),
            'is_revoked' => false,
        ]);
    }

    /**
     * List active sessions for an enterprise.
     */
    public static function listActiveSessions(int $companyId): array
    {
        return UserSession::with('user')
            ->where('company_id', $companyId)
            ->where('is_revoked', false)
            ->orderBy('last_activity_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Revoke a specific session.
     */
    public static function revokeSession(int $sessionId, ?string $userName = 'Admin'): bool
    {
        $session = UserSession::findOrFail($sessionId);
        $session->update(['is_revoked' => true]);

        AuditTrailService::log(
            $session->company_id,
            'SESSION_REVOKED',
            'USER_SESSION',
            $session->id,
            "Revoked session #{$session->id} for user #{$session->user_id}",
            null,
            ['session_id' => $session->id, 'user_id' => $session->user_id],
            null,
            1,
            $userName,
            null,
            null,
            'SUCCESS',
            'WARNING'
        );

        return true;
    }
}
