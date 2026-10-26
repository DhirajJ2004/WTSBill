<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Logger;
use WTSBill\Core\Session;
use WTSBill\Repositories\BranchRepository;

/**
 * Service for managing branches, user assignments, and secure branch switching.
 */
class BranchService
{
    private BranchRepository $branchRepo;

    public function __construct(?BranchRepository $branchRepo = null)
    {
        $this->branchRepo = $branchRepo ?? new BranchRepository();
    }

    /**
     * Switch the currently active branch in the user's session.
     *
     * @param int $userId Authenticated user ID
     * @param int $branchId Target branch ID
     * @param int $companyId Current company ID
     * @param string $userRole User role
     * @return array The activated branch details
     * @throws ValidationException If branch is invalid, inactive, or unauthorized
     */
    public function switchBranch(int $userId, int $branchId, int $companyId, string $userRole): array
    {
        if ($branchId <= 0) {
            throw ValidationException::withMessages(['branch_id' => 'Invalid branch identifier provided.']);
        }

        // Fetch branch
        $branch = $this->branchRepo->find($branchId);
        if (!$branch || (int)($branch['company_id'] ?? 0) !== $companyId || !empty($branch['deleted_at'])) {
            Logger::warning("Unauthorized branch switch attempt: user {$userId} tried accessing non-existent/cross-tenant branch {$branchId}");
            throw ValidationException::withMessages(['branch_id' => 'The selected branch does not exist or belongs to another organization.']);
        }

        // Check if branch is active
        if ((int)($branch['is_active'] ?? 0) !== 1) {
            Logger::warning("Branch switch attempt to inactive branch: user {$userId}, branch {$branchId}");
            throw ValidationException::withMessages(['branch_id' => 'The selected branch is inactive and cannot be accessed.']);
        }

        // Verify authorization
        $isAuthorized = $this->branchRepo->isUserAuthorized($userId, $branchId, $companyId, $userRole);
        if (!$isAuthorized) {
            Logger::warning("Access denied: user {$userId} (role: {$userRole}) attempted unauthorized switch to branch {$branchId}");
            throw ValidationException::withMessages(['branch_id' => 'You do not have permission to access this branch. Contact your administrator.']);
        }

        // Establish branch session context
        Session::start();
        Session::set('current_branch_id', (int)$branch['id']);
        Session::set('current_branch_name', (string)$branch['name']);
        Session::set('current_branch_code', (string)($branch['branch_code'] ?? $branch['code'] ?? ''));

        Logger::info("User {$userId} switched active branch to #{$branch['id']} ({$branch['name']})");

        return $branch;
    }

    /**
     * Get authorized branches for the currently logged-in user.
     */
    public function getAuthorizedBranchesForUser(int $userId, int $companyId, string $userRole): array
    {
        return $this->branchRepo->findUserAuthorizedBranches($userId, $companyId, $userRole);
    }

    /**
     * Get active branch details for current session.
     */
    public function getActiveBranch(int $companyId, ?int $branchId = null): ?array
    {
        if ($branchId === null || $branchId <= 0) {
            $branchId = Session::getBranchId();
        }

        if ($branchId > 0) {
            $branch = $this->branchRepo->find($branchId);
            if ($branch && (int)$branch['company_id'] === $companyId && (int)$branch['is_active'] === 1) {
                return $branch;
            }
        }

        return $this->branchRepo->findMainBranch($companyId);
    }

    /**
     * Validate and create a new branch.
     */
    public function createBranch(array $data, int $companyId): array
    {
        $this->validateBranchData($data, $companyId);

        $branchCode = strtoupper(trim($data['branch_code'] ?? $data['code'] ?? ''));
        $name = trim($data['name'] ?? '');
        $gstin = !empty($data['gstin']) ? strtoupper(trim($data['gstin'])) : null;
        $pan = !empty($data['pan']) ? strtoupper(trim($data['pan'])) : null;
        $stateCode = !empty($data['state_code']) ? trim($data['state_code']) : '27';
        $isMain = !empty($data['is_main_branch']) ? 1 : 0;
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        $insertData = [
            'company_id' => $companyId,
            'name' => $name,
            'code' => $branchCode,
            'branch_code' => $branchCode,
            'legal_name' => trim($data['legal_name'] ?? '') ?: $name,
            'gstin' => $gstin,
            'pan' => $pan,
            'address' => trim($data['address'] ?? '') ?: null,
            'city' => trim($data['city'] ?? '') ?: null,
            'state' => trim($data['state'] ?? '') ?: 'Maharashtra',
            'state_code' => $stateCode,
            'pincode' => trim($data['pincode'] ?? '') ?: null,
            'phone' => trim($data['phone'] ?? '') ?: null,
            'email' => trim($data['email'] ?? '') ?: null,
            'is_main_branch' => $isMain,
            'is_active' => $isActive,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $id = $this->branchRepo->createBranch($insertData);
        $insertData['id'] = $id;

        Logger::info("Branch '{$name}' ({$branchCode}) created with ID {$id} for company {$companyId}");

        return $insertData;
    }

    /**
     * Validate and update an existing branch.
     */
    public function updateBranch(int $id, array $data, int $companyId): array
    {
        $existing = $this->branchRepo->find($id);
        if (!$existing || (int)$existing['company_id'] !== $companyId) {
            throw ValidationException::withMessages(['id' => 'Branch not found.']);
        }

        $this->validateBranchData($data, $companyId, $id);

        $branchCode = strtoupper(trim($data['branch_code'] ?? $data['code'] ?? ''));
        $name = trim($data['name'] ?? '');
        $gstin = !empty($data['gstin']) ? strtoupper(trim($data['gstin'])) : null;
        $pan = !empty($data['pan']) ? strtoupper(trim($data['pan'])) : null;
        $stateCode = !empty($data['state_code']) ? trim($data['state_code']) : '27';
        $isMain = isset($data['is_main_branch']) ? (int)$data['is_main_branch'] : (int)$existing['is_main_branch'];
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : (int)$existing['is_active'];

        $updateData = [
            'name' => $name,
            'code' => $branchCode,
            'branch_code' => $branchCode,
            'legal_name' => trim($data['legal_name'] ?? '') ?: $name,
            'gstin' => $gstin,
            'pan' => $pan,
            'address' => trim($data['address'] ?? '') ?: null,
            'city' => trim($data['city'] ?? '') ?: null,
            'state' => trim($data['state'] ?? '') ?: 'Maharashtra',
            'state_code' => $stateCode,
            'pincode' => trim($data['pincode'] ?? '') ?: null,
            'phone' => trim($data['phone'] ?? '') ?: null,
            'email' => trim($data['email'] ?? '') ?: null,
            'is_main_branch' => $isMain,
            'is_active' => $isActive,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->branchRepo->updateBranch($id, $companyId, $updateData);

        // If currently active branch in session was updated, refresh session name
        if (Session::getBranchId() === $id) {
            Session::set('current_branch_name', $name);
            Session::set('current_branch_code', $branchCode);
        }

        return array_merge($existing, $updateData);
    }

    /**
     * Toggle branch active status.
     */
    public function toggleStatus(int $id, int $companyId): array
    {
        $branch = $this->branchRepo->find($id);
        if (!$branch || (int)$branch['company_id'] !== $companyId) {
            throw ValidationException::withMessages(['id' => 'Branch not found.']);
        }

        // Cannot deactivate main branch if it's the only active branch
        $newStatus = (int)$branch['is_active'] === 1 ? 0 : 1;
        if ($newStatus === 0 && (int)$branch['is_main_branch'] === 1) {
            throw ValidationException::withMessages(['is_active' => 'Cannot deactivate the main branch of an organization.']);
        }

        $this->branchRepo->updateBranch($id, $companyId, [
            'is_active' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $branch['is_active'] = $newStatus;
        return $branch;
    }

    /**
     * Assign users to a branch.
     */
    public function assignUsers(int $branchId, int $companyId, array $userIds): void
    {
        $branch = $this->branchRepo->find($branchId);
        if (!$branch || (int)$branch['company_id'] !== $companyId) {
            throw ValidationException::withMessages(['branch_id' => 'Branch not found.']);
        }

        $this->branchRepo->syncBranchUsers($branchId, $companyId, $userIds);
    }

    /**
     * Validation helper.
     */
    private function validateBranchData(array $data, int $companyId, ?int $ignoreId = null): void
    {
        $errors = [];

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Branch name is required.';
        }

        $code = strtoupper(trim($data['branch_code'] ?? $data['code'] ?? ''));
        if ($code === '') {
            $errors['branch_code'] = 'Branch code is required (e.g., BOM-01, PUN-02).';
        } else {
            $existing = $this->branchRepo->findByCode($code, $companyId);
            if ($existing && ($ignoreId === null || (int)$existing['id'] !== $ignoreId)) {
                $errors['branch_code'] = "Branch code '{$code}' is already assigned to another branch.";
            }
        }

        if (!empty($data['gstin'])) {
            $gstin = strtoupper(trim($data['gstin']));
            if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
                $errors['gstin'] = 'GSTIN format is invalid. Must be a 15-character valid Indian GSTIN.';
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
