<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class UserController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('employees', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $users = User::where('current_company_id', $companyId)
            ->select('id', 'name', 'email', 'phone', 'role', 'is_active', 'last_login_at', 'created_at')
            ->orderBy('id', 'asc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $users,
        ], 200);
    }

    public function show($id)
    {
        $adminUser = AuthMiddleware::authorize('employees', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $targetUser = User::find(intval($id));
        if (!$targetUser) {
            return response_json(['status' => 'error', 'message' => 'User not found.'], 404);
        }

        if ((int)$targetUser->current_company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: Cannot access users from another company.'], 403);
        }

        return response_json([
            'status' => 'success',
            'data' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->role,
                'phone' => $targetUser->phone,
                'is_active' => (bool)$targetUser->is_active,
                'last_login_at' => $targetUser->last_login_at,
                'created_at' => $targetUser->created_at,
            ],
        ], 200);
    }

    public function store()
    {
        $adminUser = AuthMiddleware::authorize('settings', 'manage_users');
        $companyId = AuthMiddleware::getTenantId();
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? 'Password@123';
        $role = trim($input['role'] ?? 'Staff');
        $phone = trim($input['phone'] ?? '');

        if (empty($name) || strlen($name) < 2) {
            return response_json(['status' => 'error', 'message' => 'User Name is required and must be at least 2 characters.'], 422);
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response_json(['status' => 'error', 'message' => 'A valid email address is required.'], 422);
        }

        if (User::where('email', $email)->exists()) {
            return response_json(['status' => 'error', 'message' => "A user with email '{$email}' already exists."], 409);
        }

        $newUser = DB::transaction(function () use ($name, $email, $password, $phone, $role, $companyId, $branchId, $adminUser) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'phone' => $phone,
                'role' => $role,
                'current_company_id' => $companyId,
                'is_active' => true,
                'status' => 'ACTIVE',
            ]);

            // Assign company membership role in user_roles
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'role_name' => $role,
                'branch_id' => $branchId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Audit Log: CREATE_USER (passwords NEVER logged)
            AuditLogService::record([
                'action' => AuditLogService::CREATE_USER,
                'entity' => 'User',
                'entity_id' => $user->id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => $adminUser->id,
                'user_name' => $adminUser->name,
                'old_values' => null,
                'new_values' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                ],
                'description' => "Created User '{$user->name}' ({$user->email}) with Role '{$role}'",
            ]);

            return $user;
        });

        return response_json([
            'status' => 'success',
            'message' => 'User created successfully.',
            'data' => [
                'id' => $newUser->id,
                'name' => $newUser->name,
                'email' => $newUser->email,
                'role' => $newUser->role,
                'phone' => $newUser->phone,
            ],
        ], 201);
    }

    public function update($id)
    {
        $adminUser = AuthMiddleware::authorize('settings', 'manage_users');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $targetUser = User::find(intval($id));
        if (!$targetUser) {
            return response_json(['status' => 'error', 'message' => 'User not found.'], 404);
        }

        if ((int)$targetUser->current_company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: Cannot edit users from another company.'], 403);
        }

        $oldValues = [
            'name' => $targetUser->name,
            'phone' => $targetUser->phone,
            'is_active' => (bool)$targetUser->is_active,
        ];

        $updates = [];
        if (isset($input['name'])) {
            $name = trim($input['name']);
            if (strlen($name) < 2) {
                return response_json(['status' => 'error', 'message' => 'User Name must be at least 2 characters.'], 422);
            }
            $updates['name'] = $name;
        }

        if (isset($input['phone'])) {
            $updates['phone'] = trim($input['phone']);
        }

        if (isset($input['is_active'])) {
            $updates['is_active'] = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $targetUser->update($updates);

        $newValues = [
            'name' => $targetUser->name,
            'phone' => $targetUser->phone,
            'is_active' => (bool)$targetUser->is_active,
        ];

        // Audit Log: UPDATE_USER
        AuditLogService::record([
            'action' => AuditLogService::UPDATE_USER,
            'entity' => 'User',
            'entity_id' => $targetUser->id,
            'company_id' => $companyId,
            'user_id' => $adminUser->id,
            'user_name' => $adminUser->name,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => "Updated user profile for '{$targetUser->name}' ({$targetUser->email})",
        ]);

        return response_json([
            'status' => 'success',
            'message' => 'User updated successfully.',
            'data' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->role,
                'phone' => $targetUser->phone,
                'is_active' => (bool)$targetUser->is_active,
            ],
        ], 200);
    }

    public function changeRole($id)
    {
        $adminUser = AuthMiddleware::authorize('settings', 'manage_users');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $newRole = trim($input['role'] ?? '');
        if (empty($newRole)) {
            return response_json(['status' => 'error', 'message' => 'Role is required.'], 422);
        }

        $allowedRoles = ['Owner', 'Admin', 'Manager', 'Accountant', 'Staff', 'Viewer'];
        if (!in_array(ucfirst(strtolower($newRole)), $allowedRoles, true)) {
            return response_json(['status' => 'error', 'message' => "Invalid role. Allowed roles: " . implode(', ', $allowedRoles)], 422);
        }
        $newRole = ucfirst(strtolower($newRole));

        $targetUser = User::find(intval($id));
        if (!$targetUser) {
            return response_json(['status' => 'error', 'message' => 'User not found.'], 404);
        }

        if ((int)$targetUser->current_company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: Cannot change roles for users from another company.'], 403);
        }

        $oldRole = $targetUser->role;
        $targetUser->role = $newRole;
        $targetUser->save();

        DB::table('user_roles')
            ->where('user_id', $targetUser->id)
            ->where('company_id', $companyId)
            ->update(['role_name' => $newRole, 'updated_at' => date('Y-m-d H:i:s')]);

        // Audit Log: CHANGE_ROLE
        AuditLogService::record([
            'action' => AuditLogService::CHANGE_ROLE,
            'entity' => 'User',
            'entity_id' => $targetUser->id,
            'company_id' => $companyId,
            'user_id' => $adminUser->id,
            'user_name' => $adminUser->name,
            'old_values' => ['role' => $oldRole],
            'new_values' => ['role' => $newRole],
            'description' => "Changed role for user '{$targetUser->name}' from '{$oldRole}' to '{$newRole}'",
        ]);

        return response_json([
            'status' => 'success',
            'message' => "User role updated to '{$newRole}'.",
            'data' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->role,
            ],
        ], 200);
    }
}
