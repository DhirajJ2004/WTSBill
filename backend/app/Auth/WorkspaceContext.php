<?php

namespace App\Auth;

use App\Models\User;
use App\Models\Company;
use App\Http\Middleware\AuthorizationException;
use Illuminate\Database\Capsule\Manager as DB;

class WorkspaceContext
{
    private static ?User $user = null;
    private static ?Company $company = null;
    private static ?object $branch = null;
    private static ?string $financialYear = null;
    private static ?string $role = null;
    private static array $permissions = [];

    public static function setContext(User $user, Company $company, ?object $branch, string $financialYear, string $role, array $permissions = []): void
    {
        self::$user = $user;
        self::$company = $company;
        self::$branch = $branch;
        self::$financialYear = $financialYear;
        self::$role = $role;
        self::$permissions = $permissions;
    }

    public static function reset(): void
    {
        self::$user = null;
        self::$company = null;
        self::$branch = null;
        self::$financialYear = null;
        self::$role = null;
        self::$permissions = [];
    }

    public static function getUser(): ?User
    {
        return self::$user;
    }

    public static function getUserId(): ?int
    {
        return self::$user?->id;
    }

    public static function getCompany(): ?Company
    {
        return self::$company;
    }

    public static function getCompanyId(): int
    {
        if (self::$company === null) {
            throw new AuthorizationException('Forbidden: Active company workspace context is required.', 403);
        }
        return (int)self::$company->id;
    }

    public static function getBranch(): ?object
    {
        return self::$branch;
    }

    public static function getBranchId(): ?int
    {
        return self::$branch?->id ? (int)self::$branch->id : null;
    }

    public static function getFinancialYear(): string
    {
        if (self::$financialYear) {
            return self::$financialYear;
        }
        $month = (int)date('m');
        $year = (int)date('Y');
        $fyStart = $month >= 4 ? $year : $year - 1;
        $fyEnd = ($fyStart + 1) % 100;
        return sprintf('%04d-%02d', $fyStart, $fyEnd);
    }

    public static function getRole(): string
    {
        return self::$role ?? (self::$user?->role ?? 'User');
    }

    public static function getPermissions(): array
    {
        return self::$permissions;
    }

    public static function hasPermission(string $module, string $action): bool
    {
        $role = strtolower(self::getRole());
        if ($role === 'admin' || $role === 'owner') {
            return true;
        }
        return in_array("{$module}.{$action}", self::$permissions) || in_array($module, self::$permissions);
    }

    private static array $authorizedCompaniesCache = [];
    private static array $companyMembershipCache = [];
    private static array $authorizedBranchesCache = [];
    private static array $userRoleCache = [];

    /**
     * Get only authorized companies for a given user.
     */
    public static function getAuthorizedCompanies(int $userId): array
    {
        if (isset(self::$authorizedCompaniesCache[$userId])) {
            return self::$authorizedCompaniesCache[$userId];
        }

        // 1. Companies via user_roles
        $roleCompanyIds = DB::table('user_roles')
            ->where('user_id', $userId)
            ->pluck('company_id')
            ->toArray();

        // 2. Direct user company (if active and valid)
        $user = DB::table('users')->where('id', $userId)->first();
        if ($user && !empty($user->current_company_id)) {
            $roleCompanyIds[] = (int)$user->current_company_id;
        }

        $uniqueIds = array_unique(array_filter($roleCompanyIds));
        if (empty($uniqueIds)) {
            return self::$authorizedCompaniesCache[$userId] = [];
        }

        $companies = DB::table('companies')
            ->whereIn('id', $uniqueIds)
            ->where('status', 'ACTIVE')
            ->whereNull('deleted_at')
            ->get();

        $res = json_decode(json_encode($companies), true);
        return self::$authorizedCompaniesCache[$userId] = $res;
    }

    /**
     * Check if user is an authorized member of the company.
     */
    public static function verifyCompanyMembership(int $userId, int $companyId): bool
    {
        $key = "{$userId}_{$companyId}";
        if (isset(self::$companyMembershipCache[$key])) {
            return self::$companyMembershipCache[$key];
        }

        $isMember = DB::table('user_roles')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->exists();

        if ($isMember) {
            return self::$companyMembershipCache[$key] = true;
        }

        $user = DB::table('users')->where('id', $userId)->first();
        $valid = $user && (int)$user->current_company_id === $companyId;
        return self::$companyMembershipCache[$key] = $valid;
    }

    /**
     * Get authorized branches for user within a company.
     */
    public static function getAuthorizedBranches(int $userId, int $companyId): array
    {
        $key = "{$userId}_{$companyId}";
        if (isset(self::$authorizedBranchesCache[$key])) {
            return self::$authorizedBranchesCache[$key];
        }

        $userRole = self::getUserRoleForCompany($userId, $companyId);

        // Owners and Admins have access to all branches in their company
        if (strtolower($userRole) === 'owner' || strtolower($userRole) === 'admin') {
            $branches = DB::table('branches')
                ->where('company_id', $companyId)
                ->get();
            $res = json_decode(json_encode($branches), true);
            return self::$authorizedBranchesCache[$key] = $res;
        }

        // Other roles are scoped to assigned branches
        $branchIds = DB::table('user_branches')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->pluck('branch_id')
            ->toArray();

        if (empty($branchIds)) {
            // Default to main branch if none explicitly assigned
            $mainBranch = DB::table('branches')
                ->where('company_id', $companyId)
                ->where('is_main_branch', 1)
                ->first();
            $res = $mainBranch ? [json_decode(json_encode($mainBranch), true)] : [];
            return self::$authorizedBranchesCache[$key] = $res;
        }

        $branches = DB::table('branches')
            ->where('company_id', $companyId)
            ->whereIn('id', $branchIds)
            ->get();

        $res = json_decode(json_encode($branches), true);
        return self::$authorizedBranchesCache[$key] = $res;
    }

    public static function getUserRoleForCompany(int $userId, int $companyId): string
    {
        $key = "{$userId}_{$companyId}";
        if (isset(self::$userRoleCache[$key])) {
            return self::$userRoleCache[$key];
        }

        $role = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $userId)
            ->where('user_roles.company_id', $companyId)
            ->value('roles.name');

        if ($role) {
            return self::$userRoleCache[$key] = $role;
        }

        $user = DB::table('users')->where('id', $userId)->first();
        $r = $user->role ?? 'User';
        return self::$userRoleCache[$key] = $r;
    }

    /**
     * Verify branch access.
     */
    public static function verifyBranchAccess(int $userId, int $companyId, int $branchId): bool
    {
        $role = self::getUserRoleForCompany($userId, $companyId);
        if (strtolower($role) === 'owner' || strtolower($role) === 'admin') {
            return DB::table('branches')
                ->where('id', $branchId)
                ->where('company_id', $companyId)
                ->exists();
        }

        return DB::table('user_branches')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->exists();
    }

    /**
     * Get user permissions for a specific company membership.
     */
    public static function getUserPermissionsForCompany(int $userId, int $companyId): array
    {
        $role = self::getUserRoleForCompany($userId, $companyId);
        $permissions = \App\Services\PermissionManager::getPermissionsForRole($role);

        try {
            $dbPerms = DB::table('user_roles')
                ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
                ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->where('user_roles.user_id', $userId)
                ->where('user_roles.company_id', $companyId)
                ->pluck('permissions.slug')
                ->toArray();

            if (!empty($dbPerms)) {
                $permissions = array_unique(array_merge($permissions, $dbPerms));
            }
        } catch (\Throwable $e) {
            // Fallback gracefully to role permissions if table/columns differ
        }

        return $permissions;
    }

    /**
     * Check if warehouse belongs to the specified company.
     */
    public static function verifyWarehouseAccess(int $companyId, int $warehouseId): bool
    {
        if ($companyId <= 0 || $warehouseId <= 0) {
            return false;
        }
        return DB::table('warehouses')
            ->where('id', $warehouseId)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Resolve and validate warehouse ID within company. Never returns fallback from other companies.
     */
    public static function getAuthorizedWarehouseId(int $companyId, ?int $requestedWarehouseId = null): int
    {
        if ($companyId <= 0) {
            throw new AuthorizationException('Forbidden: Active company workspace context is required.', 403);
        }

        if ($requestedWarehouseId !== null && (int)$requestedWarehouseId > 0) {
            $reqId = (int)$requestedWarehouseId;
            if (!self::verifyWarehouseAccess($companyId, $reqId)) {
                throw new AuthorizationException("Forbidden: Warehouse #{$reqId} does not belong to Company #{$companyId}.", 403);
            }
            return $reqId;
        }

        // Return primary warehouse for this specific company
        $wh = DB::table('warehouses')
            ->where('company_id', $companyId)
            ->where('is_primary', 1)
            ->whereNull('deleted_at')
            ->first();

        if ($wh) {
            return (int)$wh->id;
        }

        // Return first active warehouse for this specific company
        $firstWh = DB::table('warehouses')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if ($firstWh) {
            return (int)$firstWh->id;
        }

        return 0;
    }
}

