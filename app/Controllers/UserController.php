<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\AuditLogger;

/**
 * User & Permissions Controller.
 * Manages operator access, invitation, role assignments, and branch authorizations.
 */
class UserController
{
    /**
     * User directory and roles view.
     */
    public function index(Request $req): Response
    {
        Gate::authorize('users.manage', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);

        $users = Database::select(
            "SELECT u.id, u.name, u.email, u.phone, u.role, u.role_id, COALESCE(u.current_company_id, 1) as company_id, 
                    u.status, u.is_active, u.created_at,
                    r.name AS role_name, r.slug AS role_slug, r.is_system
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             WHERE u.deleted_at IS NULL
             ORDER BY u.id ASC"
        );

        // Fetch assigned branches for each user
        foreach ($users as &$u) {
            $branches = Database::select(
                "SELECT b.id, b.name FROM user_branches ub JOIN branches b ON ub.branch_id = b.id WHERE ub.user_id = ?",
                [$u['id']]
            );
            $u['branches'] = $branches;
            $u['branch_ids'] = array_column($branches, 'id');
        }
        unset($u);

        $roles = Database::select("SELECT id, name, slug, description, is_system FROM roles ORDER BY id ASC");
        $branches = Database::select("SELECT id, name, branch_code FROM branches WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC", [$companyId]);

        $html = View::renderWithLayout('users/index', [
            'title' => 'Users & Permissions',
            'currentUri' => '/users',
            'users' => $users,
            'roles' => $roles,
            'branches' => $branches,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Create / invite new user.
     */
    public function store(Request $req): Response
    {
        Gate::authorize('users.manage', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $name = trim((string)($payload['name'] ?? ''));
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $phone = trim((string)($payload['phone'] ?? ''));
        $roleId = (int)($payload['role_id'] ?? 0);
        $roleStr = strtolower(trim((string)($payload['role'] ?? '')));
        $password = (string)($payload['password'] ?? 'Password@123');
        $branchIds = (array)($payload['branch_ids'] ?? [1]);

        if (empty($name)) {
            return $this->errorResponse($req, 'Full name is required.', 422);
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse($req, 'Valid email address is required.', 422);
        }

        // Check duplicate email
        $existing = Database::selectOne("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL", [$email]);
        if ($existing) {
            return $this->errorResponse($req, "User with email '{$email}' already exists.", 422);
        }

        // Validate role by role_id or slug/name
        $roleRecord = null;
        if ($roleId > 0) {
            $roleRecord = Database::selectOne("SELECT id, name, slug FROM roles WHERE id = ?", [$roleId]);
        }
        if (!$roleRecord && !empty($roleStr)) {
            $roleRecord = Database::selectOne("SELECT id, name, slug FROM roles WHERE slug = ? OR LOWER(name) = ?", [$roleStr, $roleStr]);
        }
        if (!$roleRecord) {
            $roleRecord = Database::selectOne("SELECT id, name, slug FROM roles WHERE slug = 'viewer'") ?? ['id' => 5, 'name' => 'Viewer', 'slug' => 'viewer'];
        }

        try {
            $userId = Database::insert('users', [
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $phone ?: null,
                'role' => strtoupper($roleRecord['slug']),
                'role_id' => $roleRecord['id'],
                'current_company_id' => $companyId,
                'status' => 'ACTIVE',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Assign allowed branches
            foreach (array_unique(array_filter(array_map('intval', $branchIds))) as $bid) {
                Database::insert('user_branches', [
                    'user_id' => $userId,
                    'branch_id' => $bid,
                    'company_id' => $companyId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Sync user_roles junction
            Database::insert('user_roles', [
                'user_id' => $userId,
                'role_id' => $roleRecord['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            AuditLogger::log(
                action: 'create',
                module: 'users',
                entityType: 'USER',
                entityId: $userId,
                entityLabel: $email,
                newValues: ['name' => $name, 'email' => $email, 'role' => $roleRecord['slug'], 'role_id' => $roleRecord['id']],
                status: 'success',
                notes: "Created user '{$name}' with role '{$roleRecord['name']}'"
            );

            $msg = "User '{$name}' created and assigned '{$roleRecord['name']}' role successfully.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => $msg], 201);
            }

            Session::flash('success', $msg);
            return Response::redirect('/users');
        } catch (\Throwable $e) {
            return $this->errorResponse($req, 'Failed to create user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update user details and role.
     */
    public function update(Request $req, mixed $id = null): Response
    {
        Gate::authorize('users.manage', $req);

        $userId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $targetUser = Database::selectOne("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        if (!$targetUser) {
            return $this->errorResponse($req, 'User record not found.', 404);
        }

        $name = trim((string)($payload['name'] ?? $targetUser['name']));
        $phone = trim((string)($payload['phone'] ?? $targetUser['phone']));
        $newRoleId = isset($payload['role_id']) ? (int)$payload['role_id'] : (int)$targetUser['role_id'];
        $branchIds = isset($payload['branch_ids']) ? (array)$payload['branch_ids'] : null;

        // Privilege Escalation Prevention
        if ($newRoleId !== (int)$targetUser['role_id']) {
            if (!Gate::canAssignRole($userId, $newRoleId)) {
                return $this->errorResponse($req, 'Privilege escalation denied: You cannot alter your own role, or demote the last remaining Administrator.', 403);
            }
        }

        $roleRecord = Database::selectOne("SELECT id, name, slug FROM roles WHERE id = ?", [$newRoleId]);
        if (!$roleRecord) {
            $roleRecord = ['id' => $targetUser['role_id'], 'name' => $targetUser['role'], 'slug' => strtolower($targetUser['role'])];
        }

        try {
            Database::update('users', [
                'name' => $name,
                'phone' => $phone ?: null,
                'role_id' => $roleRecord['id'],
                'role' => strtoupper($roleRecord['slug']),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$userId]);

            // Sync user_roles junction
            Database::statement("DELETE FROM user_roles WHERE user_id = ?", [$userId]);
            Database::insert('user_roles', ['user_id' => $userId, 'role_id' => $roleRecord['id'], 'created_at' => date('Y-m-d H:i:s')]);

            // Update branches if supplied
            if ($branchIds !== null) {
                Database::statement("DELETE FROM user_branches WHERE user_id = ?", [$userId]);
                foreach (array_unique(array_filter(array_map('intval', $branchIds))) as $bid) {
                    Database::insert('user_branches', [
                        'user_id' => $userId,
                        'branch_id' => $bid,
                        'company_id' => $companyId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Invalidate gate cache if updating self
            Gate::flushCache();

            AuditLogger::log(
                action: ($newRoleId !== (int)$targetUser['role_id']) ? 'role_changed' : 'update',
                module: 'users',
                entityType: 'USER',
                entityId: $userId,
                entityLabel: (string)$targetUser['email'],
                oldValues: ['role_id' => $targetUser['role_id'], 'name' => $targetUser['name']],
                newValues: ['role_id' => $roleRecord['id'], 'name' => $name],
                status: 'success',
                notes: "Updated user '{$name}'" . ($newRoleId !== (int)$targetUser['role_id'] ? " & changed role to '{$roleRecord['name']}'" : '')
            );

            $msg = "User '{$name}' updated successfully.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => $msg]);
            }

            Session::flash('success', $msg);
            return Response::redirect('/users');
        } catch (\Throwable $e) {
            return $this->errorResponse($req, 'Failed to update user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle user active/inactive status (deactivate).
     */
    public function toggleStatus(Request $req, mixed $id = null): Response
    {
        Gate::authorize('users.manage', $req);

        $userId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $targetUser = Database::selectOne("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        if (!$targetUser) {
            return $this->errorResponse($req, 'User record not found.', 404);
        }

        // Prevent deactivating last Admin or self
        if (!Gate::canDeleteUser($userId)) {
            return $this->errorResponse($req, 'Action forbidden: Cannot deactivate yourself or the last remaining Administrator.', 403);
        }

        $newActive = (int)$targetUser['is_active'] === 1 ? 0 : 1;
        $newStatus = $newActive === 1 ? 'ACTIVE' : 'INACTIVE';

        Database::update('users', [
            'is_active' => $newActive,
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$userId]);

        AuditLogger::log(
            action: 'update',
            module: 'users',
            entityType: 'USER',
            entityId: $userId,
            entityLabel: (string)$targetUser['email'],
            oldValues: ['is_active' => $targetUser['is_active']],
            newValues: ['is_active' => $newActive, 'status' => $newStatus],
            status: 'success',
            notes: "Changed user status to {$newStatus}"
        );

        $msg = "User '{$targetUser['name']}' is now {$newStatus}.";
        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'message' => $msg, 'status' => $newStatus, 'is_active' => $newActive]);
        }

        Session::flash('success', $msg);
        return Response::redirect('/users');
    }

    /**
     * Reset user password by Administrator.
     */
    public function resetPassword(Request $req, mixed $id = null): Response
    {
        Gate::authorize('users.manage', $req);

        $userId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $targetUser = Database::selectOne("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        if (!$targetUser) {
            return $this->errorResponse($req, 'User record not found.', 404);
        }

        $newPassword = trim((string)($req->post('password') ?? 'Password@123'));
        if (strlen($newPassword) < 6) {
            return $this->errorResponse($req, 'Password must be at least 6 characters.', 422);
        }

        Database::update('users', [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$userId]);

        AuditLogger::log(
            action: 'update',
            module: 'users',
            entityType: 'USER',
            entityId: $userId,
            entityLabel: (string)$targetUser['email'],
            status: 'success',
            notes: "Administrator reset password for user '{$targetUser['email']}'"
        );

        $msg = "Password reset successfully for user '{$targetUser['name']}'.";
        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'message' => $msg]);
        }

        Session::flash('success', $msg);
        return Response::redirect('/users');
    }

    protected function errorResponse(Request $req, string $message, int $status): Response
    {
        if ($this->expectsJson($req)) {
            return Response::json(['success' => false, 'message' => $message], $status);
        }
        Session::flash('error', $message);
        return Response::redirect('/users');
    }

    protected function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $contentType = (string)($req->header('content-type') ?? '');
        $xRequestedWith = (string)($req->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}
