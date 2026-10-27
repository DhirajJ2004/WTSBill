<?php

declare(strict_types=1);

namespace WTSBill\Auth;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\HttpException;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Services\AuditLogger;

/**
 * Enterprise Central Authorization Gate for WTSBill ERP.
 * Enforces Role-Based Access Control (RBAC), database permission caching per request,
 * default DENY policy, direct object access controls, and privilege escalation prevention.
 */
class Gate
{
    private static ?array $cachedUser = null;
    private static ?string $cachedRoleSlug = null;
    private static ?array $cachedPermissions = null;
    private static ?int $cachedUserId = null;

    /**
     * Clear per-request in-memory cache (e.g. for testing or user switch).
     */
    public static function flushCache(): void
    {
        self::$cachedUser = null;
        self::$cachedRoleSlug = null;
        self::$cachedPermissions = null;
        self::$cachedUserId = null;
    }

    /**
     * Get the currently authenticated user record fresh from DB or request cache.
     */
    public static function user(): ?array
    {
        $userId = Session::getUserId();
        if (!$userId || $userId <= 0) {
            return null;
        }

        if (self::$cachedUserId === $userId && self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        try {
            $user = Database::selectOne(
                "SELECT u.id, u.name, u.email, u.phone, u.role, u.role_id, u.current_company_id, u.is_active, u.status,
                        r.slug AS role_slug, r.name AS role_name, r.is_system
                 FROM `users` u
                 LEFT JOIN `roles` r ON u.role_id = r.id
                 WHERE u.id = :id AND u.deleted_at IS NULL LIMIT 1",
                ['id' => $userId]
            );

            if (!$user) {
                $sessUser = Session::getUser();
                if ($sessUser && !empty($sessUser['role'])) {
                    $role = strtolower(trim((string)$sessUser['role']));
                    self::$cachedUserId = (int)($sessUser['id'] ?? $userId);
                    self::$cachedUser = [
                        'id' => self::$cachedUserId,
                        'name' => $sessUser['name'] ?? 'User',
                        'email' => $sessUser['email'] ?? '',
                        'role' => strtoupper($role),
                        'role_slug' => $role,
                        'role_id' => ($role === 'admin' ? 1 : 5),
                        'is_active' => 1,
                        'status' => 'ACTIVE',
                    ];
                    self::$cachedRoleSlug = $role;
                    return self::$cachedUser;
                }
                self::$cachedUser = null;
                return null;
            }

            if ((int)$user['is_active'] !== 1 || strtoupper((string)$user['status']) !== 'ACTIVE') {
                self::$cachedUser = null;
                return null;
            }

            self::$cachedUserId = $userId;
            self::$cachedUser = $user;
            self::$cachedRoleSlug = strtolower(trim((string)($user['role_slug'] ?? $user['role'] ?? '')));

            return self::$cachedUser;
        } catch (\Throwable $e) {
            error_log('Gate::user error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current user's role slug (e.g. 'admin', 'accountant', 'inventory_manager').
     */
    public static function role(): string
    {
        $sessRole = Session::get('user_role');
        if ($sessRole !== null && $sessRole !== '') {
            return strtolower(trim((string)$sessRole));
        }

        $user = self::user();
        if (!$user) {
            return 'guest';
        }
        return self::$cachedRoleSlug ?? 'guest';
    }

    /**
     * Check if user has specific role or one of given roles.
     */
    public static function isRole(string|array $roles): bool
    {
        $currentRole = self::role();
        if ($currentRole === 'guest') {
            return false;
        }

        $roles = array_map('strtolower', (array)$roles);
        return in_array($currentRole, $roles, true);
    }

    /**
     * Load all active permission slugs for the user (Role permissions + user overrides).
     */
    public static function permissions(?int $userId = null): array
    {
        $user = self::user();
        if (!$user) {
            return [];
        }

        $activeRole = self::role();

        if ($userId === null || $userId === (int)$user['id']) {
            if (self::$cachedPermissions !== null && (self::$cachedRoleSlug === $activeRole)) {
                return self::$cachedPermissions;
            }
        }

        $targetUserId = $userId ?? (int)$user['id'];
        
        // Resolve role_id from activeRole
        $roleRecord = Database::selectOne("SELECT id FROM roles WHERE slug = ?", [$activeRole]);
        $roleId = $roleRecord ? (int)$roleRecord['id'] : (int)($user['role_id'] ?? 0);

        try {
            // 1. Fetch role-assigned permissions
            $rolePerms = Database::select(
                "SELECT p.slug 
                 FROM `role_permissions` rp
                 JOIN `permissions` p ON rp.permission_id = p.id
                 WHERE rp.role_id = :role_id",
                ['role_id' => $roleId]
            );
            $permSlugs = array_column($rolePerms, 'slug');

            // 2. Fetch user-specific overrides
            $overrides = Database::select(
                "SELECT p.slug, upo.is_allowed
                 FROM `user_permission_overrides` upo
                 JOIN `permissions` p ON upo.permission_id = p.id
                 WHERE upo.user_id = :user_id",
                ['user_id' => $targetUserId]
            );

            foreach ($overrides as $ov) {
                $slug = $ov['slug'];
                $allowed = (int)$ov['is_allowed'] === 1;
                if ($allowed && !in_array($slug, $permSlugs, true)) {
                    $permSlugs[] = $slug;
                } elseif (!$allowed) {
                    $permSlugs = array_values(array_diff($permSlugs, [$slug]));
                }
            }

            if ($targetUserId === (int)$user['id']) {
                self::$cachedPermissions = $permSlugs;
            }

            return $permSlugs;
        } catch (\Throwable $e) {
            error_log('Gate::permissions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if current user has permission (Default = DENY).
     */
    public static function can(string $permissionSlug, ?array $context = null): bool
    {
        $user = self::user();
        if (!$user) {
            return false; // Unauthenticated -> DENY
        }

        $role = self::role();

        // Administrator role has universal system permission
        if ($role === 'admin' || $role === 'administrator') {
            return true;
        }

        // Check if permission is in user's active permissions list
        $perms = self::permissions((int)$user['id']);
        if (!in_array($permissionSlug, $perms, true)) {
            return false; // Missing permission -> DENY
        }

        // Direct Object Access Verification (e.g. Sales Executive only seeing own created records)
        if ($context !== null) {
            if (!self::verifyContext($user, $permissionSlug, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Authorize action or immediately abort with HTTP 403 Forbidden.
     *
     * @param string $permissionSlug Required permission
     * @param Request|null $request Current request
     * @param array|null $context Contextual object parameters
     * @throws HttpException
     */
    public static function authorize(string $permissionSlug, ?Request $request = null, ?array $context = null): void
    {
        if (!self::can($permissionSlug, $context)) {
            $user = self::user();
            $role = self::role();
            $userId = $user ? (int)$user['id'] : null;
            $userName = $user ? (string)$user['name'] : 'Guest';
            $url = $_SERVER['REQUEST_URI'] ?? '/';

            // Log denied security attempt
            AuditLogger::log(
                action: 'permission_denied',
                module: explode('.', $permissionSlug)[0] ?? 'auth',
                entityType: 'PERMISSION',
                entityId: null,
                entityLabel: $permissionSlug,
                oldValues: null,
                newValues: ['attempted_permission' => $permissionSlug, 'role' => $role, 'url' => $url],
                status: 'denied',
                notes: "Access denied to permission '{$permissionSlug}' for user {$userName} (Role: {$role}) on {$url}",
                userId: $userId,
                userName: $userName,
                userRole: $role
            );

            throw new HttpException(403, "You do not have permission '{$permissionSlug}' to perform this action.");
        }
    }

    /**
     * Contextual and Direct Object Access Verification.
     */
    private static function verifyContext(array $user, string $permissionSlug, array $context): bool
    {
        // 1. Company tenant isolation check
        $companyId = (int)(Session::getCompanyId() ?? 1);
        if (isset($context['company_id']) && (int)$context['company_id'] !== $companyId) {
            return false;
        }

        // 2. Branch isolation check
        $activeBranchId = Session::getBranchId();
        if ($activeBranchId !== null && isset($context['branch_id']) && $context['branch_id'] !== null) {
            if ((int)$context['branch_id'] !== (int)$activeBranchId) {
                return false;
            }
        }

        // 3. Sales Executive own-record restriction: can only view/edit invoices they created
        if (self::role() === 'sales_executive') {
            if (str_starts_with($permissionSlug, 'invoices.') && isset($context['created_by_user_id'])) {
                if ((int)$context['created_by_user_id'] !== (int)$user['id']) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Prevent privilege escalation on user role modification.
     */
    public static function canAssignRole(int $targetUserId, int $newRoleId): bool
    {
        if (!self::can('users.manage')) {
            return false;
        }

        $actingUser = self::user();
        if (!$actingUser) {
            return false;
        }

        // Users cannot edit their own role
        if ((int)$actingUser['id'] === $targetUserId) {
            return false;
        }

        // Check if target user is currently the last remaining Administrator
        $targetUser = Database::selectOne("SELECT role_id FROM users WHERE id = ?", [$targetUserId]);
        if ($targetUser && (int)$targetUser['role_id'] === 1 && $newRoleId !== 1) {
            $adminCount = (int)(Database::selectOne("SELECT COUNT(*) AS total FROM users WHERE role_id = 1 AND is_active = 1 AND deleted_at IS NULL")['total'] ?? 0);
            if ($adminCount <= 1) {
                return false; // Cannot downgrade the last remaining Administrator
            }
        }

        return true;
    }

    /**
     * Check if a user can be deleted or deactivated (protects last Admin).
     */
    public static function canDeleteUser(int $targetUserId): bool
    {
        if (!self::can('users.manage')) {
            return false;
        }

        $actingUser = self::user();
        if (!$actingUser) {
            return false;
        }

        // Nobody can delete themselves
        if ((int)$actingUser['id'] === $targetUserId) {
            return false;
        }

        // Protect last Administrator
        $targetUser = Database::selectOne("SELECT role_id FROM users WHERE id = ?", [$targetUserId]);
        if ($targetUser && (int)$targetUser['role_id'] === 1) {
            $adminCount = (int)(Database::selectOne("SELECT COUNT(*) AS total FROM users WHERE role_id = 1 AND is_active = 1 AND deleted_at IS NULL")['total'] ?? 0);
            if ($adminCount <= 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Friendly Enterprise Dark-Themed 403 Page.
     */
    private static function render403Page(string $permissionSlug, string $role): string
    {
        $permSafe = htmlspecialchars($permissionSlug, ENT_QUOTES, 'UTF-8');
        $roleSafe = htmlspecialchars(strtoupper($role), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden &mdash; WTSBill ERP</title>
    <style>
        :root {
            --bg-base: #0b1120;
            --bg-card: #151f32;
            --border: #22324e;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --primary: #38bdf8;
            --primary-soft: rgba(56, 189, 248, 0.12);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-base);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .error-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            max-width: 520px;
            width: 100%;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            text-align: center;
        }
        .badge-lock {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            margin-bottom: 1.25rem;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #f87171;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        .details-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: left;
            font-size: 0.85rem;
            margin-bottom: 1.75rem;
        }
        .details-row {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            color: var(--text-muted);
        }
        .details-val {
            color: var(--text-main);
            font-family: ui-monospace, monospace;
            font-weight: 600;
        }
        .btn-group {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .btn-primary {
            background: #0284c7;
            color: #fff;
        }
        .btn-primary:hover {
            background: #0369a1;
        }
        .btn-secondary {
            background: rgba(148, 163, 184, 0.1);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover {
            background: rgba(148, 163, 184, 0.18);
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="badge-lock">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>
        <h1>403 &mdash; Access Restricted</h1>
        <p class="subtitle">Your account role does not have authorization to access this resource. This event has been recorded in the security audit trail.</p>
        <div class="details-box">
            <div class="details-row"><span>Required Permission:</span><span class="details-val">{$permSafe}</span></div>
            <div class="details-row"><span>Current Assigned Role:</span><span class="details-val">{$roleSafe}</span></div>
            <div class="details-row"><span>Enforcement Policy:</span><span class="details-val">Strict Server-Side RBAC</span></div>
        </div>
        <div class="btn-group">
            <a href="/" class="btn btn-primary">&larr; Return to Dashboard</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
    </div>
</body>
</html>
HTML;
    }
}

// Global and App namespace backward compatibility aliases
if (!class_exists('App\Auth\Gate', false)) {
    class_alias(Gate::class, 'App\Auth\Gate');
}
if (!class_exists('Gate', false)) {
    class_alias(Gate::class, 'Gate');
}
