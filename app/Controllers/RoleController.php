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
 * Roles & Permissions Management Controller.
 * Provides granular permission matrix configuration and role management.
 */
class RoleController
{
    /**
     * Render roles and permissions matrix.
     */
    public function index(Request $req): Response
    {
        Gate::authorize('roles.manage', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $roles = Database::select("SELECT * FROM roles ORDER BY id ASC");
        $permissions = Database::select("SELECT * FROM permissions ORDER BY module ASC, id ASC");
        $rolePermRows = Database::select("SELECT role_id, permission_id FROM role_permissions");

        // Map: role_id => [permission_id => true]
        $rolePermissionsMap = [];
        foreach ($rolePermRows as $rp) {
            $rolePermissionsMap[(int)$rp['role_id']][(int)$rp['permission_id']] = true;
        }

        // Group permissions by module
        $modules = [];
        foreach ($permissions as $p) {
            $mod = $p['module'] ?: 'general';
            if (!isset($modules[$mod])) {
                $modules[$mod] = [
                    'label' => ucwords(str_replace('_', ' ', $mod)),
                    'permissions' => []
                ];
            }
            $modules[$mod]['permissions'][] = $p;
        }

        // Selected active role for the matrix view (default to role 2: Accountant or query param)
        $selectedRoleId = (int)($req->query('role_id') ?? ($roles[1]['id'] ?? ($roles[0]['id'] ?? 1)));
        $selectedRole = null;
        foreach ($roles as $r) {
            if ((int)$r['id'] === $selectedRoleId) {
                $selectedRole = $r;
                break;
            }
        }
        if (!$selectedRole && !empty($roles)) {
            $selectedRole = $roles[0];
            $selectedRoleId = (int)$selectedRole['id'];
        }

        $html = View::renderWithLayout('roles/index', [
            'title' => 'Roles & Permissions Matrix',
            'currentUri' => '/roles',
            'roles' => $roles,
            'permissions' => $permissions,
            'modules' => $modules,
            'rolePermissionsMap' => $rolePermissionsMap,
            'selectedRole' => $selectedRole,
            'selectedRoleId' => $selectedRoleId,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Create a new custom role.
     */
    public function store(Request $req): Response
    {
        Gate::authorize('roles.manage', $req);

        $payload = !empty($req->json()) ? $req->json() : $req->post();
        $name = trim((string)($payload['name'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $permIds = isset($payload['permissions']) ? (array)$payload['permissions'] : [];

        if (empty($name)) {
            return $this->errorResponse($req, 'Role name is required.', 422);
        }

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', trim($name)));
        $slug = trim($slug, '_');

        $existing = Database::selectOne("SELECT id FROM roles WHERE slug = ?", [$slug]);
        if ($existing) {
            $slug .= '_' . time();
        }

        $companyId = (int)(Session::getCompanyId() ?? 1);

        try {
            $roleId = Database::insert('roles', [
                'company_id' => $companyId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description ?: "Custom {$name} role",
                'is_system' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            foreach (array_unique(array_filter(array_map('intval', $permIds))) as $pid) {
                Database::insert('role_permissions', [
                    'role_id' => $roleId,
                    'permission_id' => $pid,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Gate::flushCache();

            AuditLogger::log(
                action: 'create',
                module: 'roles',
                entityType: 'ROLE',
                entityId: $roleId,
                entityLabel: $name,
                newValues: ['name' => $name, 'slug' => $slug, 'permission_count' => count($permIds)],
                status: 'success',
                notes: "Created new role '{$name}' with " . count($permIds) . " permissions"
            );

            $msg = "Role '{$name}' created successfully.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => $msg, 'role_id' => $roleId], 201);
            }

            Session::flash('success', $msg);
            return Response::redirect('/roles?role_id=' . $roleId);
        } catch (\Throwable $e) {
            return $this->errorResponse($req, 'Failed to create role: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update permissions and details of an existing role.
     */
    public function update(Request $req, mixed $id = null): Response
    {
        Gate::authorize('roles.manage', $req);

        $roleId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $role = Database::selectOne("SELECT * FROM roles WHERE id = ?", [$roleId]);
        if (!$role) {
            return $this->errorResponse($req, 'Role not found.', 404);
        }

        $payload = !empty($req->json()) ? $req->json() : $req->post();
        $name = trim((string)($payload['name'] ?? $role['name']));
        $description = trim((string)($payload['description'] ?? $role['description']));
        $permIds = isset($payload['permissions']) ? (array)$payload['permissions'] : [];

        // Cannot alter administrator system permissions (must keep all)
        if ($role['slug'] === 'admin') {
            // Admin must always retain all permissions
            $allPermIds = Database::select("SELECT id FROM permissions");
            $permIds = array_column($allPermIds, 'id');
        }

        try {
            Database::update('roles', [
                'name' => $name,
                'description' => $description,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$roleId]);

            // Sync role_permissions
            Database::statement("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
            foreach (array_unique(array_filter(array_map('intval', $permIds))) as $pid) {
                Database::insert('role_permissions', [
                    'role_id' => $roleId,
                    'permission_id' => $pid,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Gate::flushCache();

            AuditLogger::log(
                action: 'update',
                module: 'roles',
                entityType: 'ROLE',
                entityId: $roleId,
                entityLabel: $role['name'],
                newValues: ['name' => $name, 'permission_count' => count($permIds)],
                status: 'success',
                notes: "Updated permissions for role '{$name}'"
            );

            $msg = "Role '{$name}' permissions updated successfully.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => $msg]);
            }

            Session::flash('success', $msg);
            return Response::redirect('/roles?role_id=' . $roleId);
        } catch (\Throwable $e) {
            return $this->errorResponse($req, 'Failed to update role: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Duplicate a role with all its permissions.
     */
    public function duplicate(Request $req, mixed $id = null): Response
    {
        Gate::authorize('roles.manage', $req);

        $roleId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $sourceRole = Database::selectOne("SELECT * FROM roles WHERE id = ?", [$roleId]);
        if (!$sourceRole) {
            return $this->errorResponse($req, 'Source role not found.', 404);
        }

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $newName = 'Copy of ' . $sourceRole['name'];
        $newSlug = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $newName)) . '_' . time();

        try {
            $newRoleId = Database::insert('roles', [
                'company_id' => $companyId,
                'name' => $newName,
                'slug' => $newSlug,
                'description' => "Duplicated from {$sourceRole['name']}",
                'is_system' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $sourcePerms = Database::select("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$roleId]);
            foreach ($sourcePerms as $sp) {
                Database::insert('role_permissions', [
                    'role_id' => $newRoleId,
                    'permission_id' => $sp['permission_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Gate::flushCache();

            AuditLogger::log(
                action: 'create',
                module: 'roles',
                entityType: 'ROLE',
                entityId: $newRoleId,
                entityLabel: $newName,
                status: 'success',
                notes: "Duplicated role from '{$sourceRole['name']}'"
            );

            $msg = "Role duplicated as '{$newName}' successfully.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => $msg, 'role_id' => $newRoleId], 201);
            }

            Session::flash('success', $msg);
            return Response::redirect('/roles?role_id=' . $newRoleId);
        } catch (\Throwable $e) {
            return $this->errorResponse($req, 'Failed to duplicate role: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a custom role.
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        Gate::authorize('roles.manage', $req);

        $roleId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $role = Database::selectOne("SELECT * FROM roles WHERE id = ?", [$roleId]);
        if (!$role) {
            return $this->errorResponse($req, 'Role not found.', 404);
        }

        if ((int)$role['is_system'] === 1) {
            return $this->errorResponse($req, 'System roles cannot be deleted.', 403);
        }

        $userCount = (int)(Database::selectOne("SELECT COUNT(*) as count FROM users WHERE role_id = ? AND deleted_at IS NULL", [$roleId])['count'] ?? 0);
        if ($userCount > 0) {
            return $this->errorResponse($req, "Cannot delete role: {$userCount} active users are currently assigned to this role. Reassign them first.", 422);
        }

        try {
            Database::statement("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
            Database::statement("DELETE FROM roles WHERE id = ?", [$roleId]);

            Gate::flushCache();

            AuditLogger::log(
                action: 'delete',
                module: 'roles',
                entityType: 'ROLE',
                entityId: $roleId,
                entityLabel: $role['name'],
                status: 'success',
                notes: "Deleted role '{$role['name']}'"
            );

            $msg = "Role '{$role['name']}' deleted successfully.";
            if ($this->expectsJson($req)) {
                return Response::json(['success' => true, 'message' => $msg]);
            }

            Session::flash('success', $msg);
            return Response::redirect('/roles');
        } catch (\Throwable $e) {
            return $this->errorResponse($req, 'Failed to delete role: ' . $e->getMessage(), 500);
        }
    }

    protected function errorResponse(Request $req, string $message, int $status): Response
    {
        if ($this->expectsJson($req)) {
            return Response::json(['success' => false, 'message' => $message], $status);
        }
        Session::flash('error', $message);
        return Response::redirect('/roles');
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
