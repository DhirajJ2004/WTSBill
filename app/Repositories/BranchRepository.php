<?php

declare(strict_types=1);

namespace WTSBill\Repositories;

use WTSBill\Core\Database;
use WTSBill\Repositories\BaseRepository;

/**
 * Branch Repository for multi-branch organization management and access control.
 */
class BranchRepository extends BaseRepository
{
    protected string $table = 'branches';
    protected bool $tenantScoped = true;

    /**
     * Get all branches for a company.
     */
    public function findAllByCompany(int $companyId): array
    {
        return Database::select(
            "SELECT b.*, 
                    (SELECT COUNT(*) FROM `user_branches` ub WHERE ub.branch_id = b.id) AS assigned_users_count
             FROM `branches` b 
             WHERE b.company_id = :company_id AND b.deleted_at IS NULL 
             ORDER BY b.is_main_branch DESC, b.name ASC",
            ['company_id' => $companyId]
        );
    }

    /**
     * Get all active branches for a company.
     */
    public function findActiveByCompany(int $companyId): array
    {
        return Database::select(
            "SELECT * FROM `branches` 
             WHERE company_id = :company_id AND is_active = 1 AND deleted_at IS NULL 
             ORDER BY is_main_branch DESC, name ASC",
            ['company_id' => $companyId]
        );
    }

    /**
     * Find active branches authorized for a specific user within a company.
     * Administrators have access to all active branches in the company.
     * Other users are restricted to explicitly assigned branches in user_branches.
     */
    public function findUserAuthorizedBranches(int $userId, int $companyId, string $role = 'STAFF'): array
    {
        $roleUpper = strtoupper($role);
        if ($roleUpper === 'ADMIN' || $roleUpper === 'SUPERADMIN' || $roleUpper === 'OWNER') {
            return $this->findActiveByCompany($companyId);
        }

        return Database::select(
            "SELECT b.* 
             FROM `branches` b
             INNER JOIN `user_branches` ub ON ub.branch_id = b.id
             WHERE ub.user_id = :user_id 
               AND ub.company_id = :company_id 
               AND b.company_id = :company_id2
               AND b.is_active = 1 
               AND b.deleted_at IS NULL
             ORDER BY b.is_main_branch DESC, b.name ASC",
            [
                'user_id' => $userId,
                'company_id' => $companyId,
                'company_id2' => $companyId,
            ]
        );
    }

    /**
     * Find branches to display in the header switcher dropdown.
     * Administrators see all operating branches for the company (with active/inactive status).
     * Non-administrators only see their active, explicitly assigned branches.
     */
    public function findSwitcherBranches(int $userId, int $companyId, string $role = 'STAFF'): array
    {
        $roleUpper = strtoupper($role);
        if ($roleUpper === 'ADMIN' || $roleUpper === 'SUPERADMIN' || $roleUpper === 'OWNER') {
            return Database::select(
                "SELECT * FROM `branches` 
                 WHERE company_id = :company_id AND deleted_at IS NULL 
                 ORDER BY is_main_branch DESC, is_active DESC, name ASC",
                ['company_id' => $companyId]
            );
        }

        return $this->findUserAuthorizedBranches($userId, $companyId, $role);
    }

    /**
     * Check if user is authorized to access a given branch.
     */
    public function isUserAuthorized(int $userId, int $branchId, int $companyId, string $role = 'STAFF'): bool
    {
        // Branch must exist, belong to company, and be active
        $branch = Database::selectOne(
            "SELECT id, is_active FROM `branches` 
             WHERE id = :branch_id AND company_id = :company_id AND deleted_at IS NULL",
            ['branch_id' => $branchId, 'company_id' => $companyId]
        );

        if (!$branch || (int)$branch['is_active'] !== 1) {
            return false;
        }

        $roleUpper = strtoupper($role);
        if ($roleUpper === 'ADMIN' || $roleUpper === 'SUPERADMIN' || $roleUpper === 'OWNER') {
            return true;
        }

        $assigned = Database::selectOne(
            "SELECT id FROM `user_branches` 
             WHERE user_id = :user_id AND branch_id = :branch_id AND company_id = :company_id",
            [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'company_id' => $companyId,
            ]
        );

        return !empty($assigned);
    }

    /**
     * Find branch by code within company.
     */
    public function findByCode(string $code, int $companyId): ?array
    {
        $normalized = strtoupper(trim($code));
        return Database::selectOne(
            "SELECT * FROM `branches` 
             WHERE (UPPER(code) = :code OR UPPER(branch_code) = :code2) 
               AND company_id = :company_id AND deleted_at IS NULL LIMIT 1",
            ['code' => $normalized, 'code2' => $normalized, 'company_id' => $companyId]
        );
    }

    /**
     * Find main branch of company.
     */
    public function findMainBranch(int $companyId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM `branches` 
             WHERE company_id = :company_id AND is_main_branch = 1 AND is_active = 1 AND deleted_at IS NULL 
             LIMIT 1",
            ['company_id' => $companyId]
        );
    }

    /**
     * Get user default branch if configured.
     */
    public function getUserDefaultBranch(int $userId, int $companyId): ?array
    {
        // First check branch_users is_default = 1
        $default = Database::selectOne(
            "SELECT b.* 
             FROM `branches` b
             INNER JOIN `branch_users` bu ON bu.branch_id = b.id
             WHERE bu.user_id = :user_id 
               AND bu.company_id = :company_id 
               AND bu.is_default = 1
               AND b.is_active = 1 
               AND b.deleted_at IS NULL
             LIMIT 1",
            ['user_id' => $userId, 'company_id' => $companyId]
        );

        if ($default) {
            return $default;
        }

        // Fallback to first assigned branch in user_branches
        $firstAssigned = Database::selectOne(
            "SELECT b.* 
             FROM `branches` b
             INNER JOIN `user_branches` ub ON ub.branch_id = b.id
             WHERE ub.user_id = :user_id 
               AND ub.company_id = :company_id 
               AND b.is_active = 1 
               AND b.deleted_at IS NULL
             ORDER BY b.is_main_branch DESC, b.id ASC
             LIMIT 1",
            ['user_id' => $userId, 'company_id' => $companyId]
        );

        return $firstAssigned ?: $this->findMainBranch($companyId);
    }

    /**
     * Get user assignments for a branch.
     */
    public function getBranchUserIds(int $branchId, int $companyId): array
    {
        $rows = Database::select(
            "SELECT user_id FROM `user_branches` WHERE branch_id = :branch_id AND company_id = :company_id",
            ['branch_id' => $branchId, 'company_id' => $companyId]
        );
        return array_map(fn($r) => (int)$r['user_id'], $rows);
    }

    /**
     * Sync user assignments for a branch.
     */
    public function syncBranchUsers(int $branchId, int $companyId, array $userIds): void
    {
        Database::transaction(function() use ($branchId, $companyId, $userIds) {
            Database::execute(
                "DELETE FROM `user_branches` WHERE branch_id = :branch_id AND company_id = :company_id",
                ['branch_id' => $branchId, 'company_id' => $companyId]
            );

            foreach ($userIds as $userId) {
                $uid = (int)$userId;
                if ($uid > 0) {
                    Database::insert('user_branches', [
                        'company_id' => $companyId,
                        'user_id' => $uid,
                        'branch_id' => $branchId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        });
    }

    /**
     * Create a branch.
     */
    public function createBranch(array $data): int
    {
        return (int)Database::insert($this->table, $data);
    }

    /**
     * Update branch.
     */
    public function updateBranch(int $id, int $companyId, array $data): int
    {
        return Database::update(
            $this->table,
            $data,
            'id = ? AND company_id = ?',
            [$id, $companyId]
        );
    }
}
