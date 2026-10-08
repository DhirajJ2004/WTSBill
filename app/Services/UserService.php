<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRole;
use App\Models\Role;
use App\Models\Company;
use App\Services\PermissionManager;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class UserService
{
    /**
     * Get all users belonging to a company with their assigned roles.
     */
    public static function getUsers(int $companyId, array $filters = []): array
    {
        $query = DB::table('users as u')
            ->leftJoin('user_roles as ur', function ($join) use ($companyId) {
                $join->on('u.id', '=', 'ur.user_id')
                     ->where('ur.company_id', '=', $companyId);
            })
            ->leftJoin('roles as r', 'ur.role_id', '=', 'r.id')
            ->where(function ($q) use ($companyId) {
                $q->where('ur.company_id', $companyId)
                  ->orWhere('u.current_company_id', $companyId);
            })
            ->whereNull('u.deleted_at');

        if (!empty($filters['role'])) {
            $roleTerm = strtolower(trim($filters['role']));
            $query->where(function ($q) use ($roleTerm) {
                $q->where('r.slug', $roleTerm)
                  ->orWhere('r.name', $roleTerm)
                  ->orWhere('u.role', $roleTerm);
            });
        }
        if (isset($filters['is_active'])) {
            $query->where('u.is_active', (bool)$filters['is_active']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('u.name', 'LIKE', $term)
                  ->orWhere('u.email', 'LIKE', $term);
            });
        }

        $users = $query->select(
            'u.id',
            'u.name',
            'u.email',
            DB::raw("COALESCE(r.name, u.role, 'Staff') as role"),
            DB::raw("COALESCE(r.slug, LOWER(u.role), 'staff') as role_slug"),
            'u.is_active',
            'u.last_login_at',
            'u.created_at'
        )
        ->distinct()
        ->orderBy('u.name', 'asc')
        ->get();

        return json_decode(json_encode($users), true);
    }

    /**
     * Create a new User and assign company role atomically.
     */
    public static function createUser(array $input, int $companyId, string $createdByName = 'Admin'): array
    {
        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $roleName = strtoupper(trim($input['role'] ?? 'STAFF'));
        $password = $input['password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Name, email, and password are required.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address format.'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
        }

        return DB::transaction(function () use ($name, $email, $roleName, $password, $companyId, $createdByName) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name'               => $name,
                    'email'              => $email,
                    'password'           => password_hash($password, PASSWORD_DEFAULT),
                    'role'               => $roleName,
                    'current_company_id' => $companyId,
                    'is_active'          => true,
                    'status'             => 'ACTIVE',
                ]);
            }

            // Find or create role row for company
            $roleSlug = strtolower(str_replace(' ', '_', $roleName));
            $role = Role::firstOrCreate(
                ['company_id' => $companyId, 'slug' => $roleSlug],
                ['name' => ucwords(str_replace('_', ' ', $roleSlug)), 'description' => "{$roleName} role"]
            );

            // Check if already assigned
            $existingRole = UserRole::where('user_id', $user->id)->where('company_id', $companyId)->first();
            if ($existingRole) {
                return ['success' => false, 'message' => "User '{$email}' is already assigned to this company."];
            }

            UserRole::create([
                'user_id'    => $user->id,
                'company_id' => $companyId,
                'role_id'    => $role->id,
            ]);

            AuditLogService::record([
                'company_id'  => $companyId,
                'user_name'   => $createdByName,
                'action'      => 'USER_CREATE',
                'entity'      => 'User',
                'entity_id'   => $user->id,
                'description' => "Created user '{$user->name}' ({$email}) with role '{$roleName}'"
            ]);

            return [
                'success' => true,
                'user_id' => $user->id,
                'user'    => $user,
                'role'    => $roleName,
                'message' => "User '{$user->name}' created successfully."
            ];
        });
    }

    /**
     * Update user details and role.
     */
    public static function updateUser(int $userId, array $input, int $companyId, string $updatedByName = 'Admin'): array
    {
        return DB::transaction(function () use ($userId, $input, $companyId, $updatedByName) {
            $user = User::findOrFail($userId);

            if (!empty($input['name'])) {
                $user->name = trim($input['name']);
            }
            if (isset($input['is_active'])) {
                $user->is_active = (bool)$input['is_active'];
            }
            if (!empty($input['password'])) {
                $user->password = password_hash($input['password'], PASSWORD_DEFAULT);
            }

            if (!empty($input['role'])) {
                $newRoleName = strtoupper(trim($input['role']));
                $user->role = $newRoleName;
                
                $roleSlug = strtolower(str_replace(' ', '_', $newRoleName));
                $role = Role::firstOrCreate(
                    ['company_id' => $companyId, 'slug' => $roleSlug],
                    ['name' => ucwords(str_replace('_', ' ', $roleSlug)), 'description' => "{$newRoleName} role"]
                );

                $userRole = UserRole::where('user_id', $userId)->where('company_id', $companyId)->first();
                if ($userRole) {
                    $userRole->role_id = $role->id;
                    $userRole->save();
                } else {
                    UserRole::create([
                        'user_id'    => $userId,
                        'company_id' => $companyId,
                        'role_id'    => $role->id,
                    ]);
                }

                AuditLogService::record([
                    'company_id'  => $companyId,
                    'user_name'   => $updatedByName,
                    'action'      => 'CHANGE_ROLE',
                    'entity'      => 'User',
                    'entity_id'   => $userId,
                    'description' => "Changed role for user '{$user->name}' to '{$newRoleName}'"
                ]);
            }

            $user->save();

            return [
                'success' => true,
                'user'    => $user,
                'message' => "User '{$user->name}' updated successfully."
            ];
        });
    }

    /**
     * Centralized Server-Side Permission Check.
     */
    public static function checkPermission(User $user, string $permission, ?int $companyId = null): bool
    {
        if (!$user->is_active) {
            return false;
        }

        $role = $user->role ?: 'STAFF';
        if ($companyId) {
            $userRole = DB::table('user_roles as ur')
                ->join('roles as r', 'ur.role_id', '=', 'r.id')
                ->where('ur.user_id', $user->id)
                ->where('ur.company_id', $companyId)
                ->select('r.slug', 'r.name')
                ->first();

            if ($userRole) {
                $role = $userRole->slug ?: $userRole->name;
            }
        }

        $normalizedRole = strtoupper(str_replace([' ', '-'], '_', $role));
        if ($normalizedRole === 'ADMIN' || $normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'OWNER') {
            return true;
        }

        $runtimeUser = clone $user;
        $runtimeUser->role = $role;

        return PermissionManager::check($runtimeUser, $permission);
    }
}
