<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchUser;
use App\Models\Company;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Warehouse;
use InvalidArgumentException;
use RuntimeException;

class BranchManagementService
{
    /**
     * Ensure a Main Branch exists for the company.
     */
    public static function ensureMainBranch(int $companyId): Branch
    {
        $company = Company::find($companyId);
        $mainBranch = Branch::where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('is_main_branch', true)->orWhere('code', 'MAIN')->orWhere('code', 'HQ');
            })
            ->first();

        if (!$mainBranch) {
            $firstBranch = Branch::where('company_id', $companyId)->first();
            if ($firstBranch) {
                $firstBranch->update(['is_main_branch' => true]);
                $mainBranch = $firstBranch;
            } else {
                $mainBranch = Branch::create([
                    'company_id' => $companyId,
                    'name' => ($company?->name ? $company->name . ' - ' : '') . 'Main Branch',
                    'code' => 'MAIN',
                    'legal_name' => $company?->legal_name ?: $company?->name,
                    'gstin' => $company?->gstin,
                    'pan' => $company?->pan,
                    'address' => $company?->address_line1,
                    'city' => $company?->city ?: 'Mumbai',
                    'state' => $company?->state ?: 'Maharashtra',
                    'state_code' => $company?->state_code ?: '27',
                    'pincode' => $company?->pincode ?: '400001',
                    'phone' => $company?->phone,
                    'email' => $company?->email,
                    'is_main_branch' => true,
                    'is_active' => true,
                ]);
            }
        }

        // Ensure default branch settings
        self::ensureBranchSettings($mainBranch->id, $companyId);

        return $mainBranch;
    }

    /**
     * Create a new branch.
     */
    public static function createBranch(int $companyId, array $data): Branch
    {
        $code = strtoupper(trim($data['code'] ?? ''));
        if (empty($code)) {
            throw new InvalidArgumentException("Branch code is required.");
        }

        $existing = Branch::where('company_id', $companyId)->where('code', $code)->first();
        if ($existing) {
            throw new InvalidArgumentException("Branch code '{$code}' already exists for this business.");
        }

        $isMain = !empty($data['is_main_branch']);
        if ($isMain) {
            // Unset previous main branch
            Branch::where('company_id', $companyId)->update(['is_main_branch' => false]);
        }

        $branch = Branch::create([
            'company_id' => $companyId,
            'name' => $data['name'] ?? $code,
            'code' => $code,
            'legal_name' => $data['legal_name'] ?? null,
            'gstin' => !empty($data['gstin']) ? strtoupper(trim($data['gstin'])) : null,
            'pan' => !empty($data['pan']) ? strtoupper(trim($data['pan'])) : null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? 'Maharashtra',
            'state_code' => $data['state_code'] ?? '27',
            'pincode' => $data['pincode'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_main_branch' => $isMain,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Provision default warehouse for this branch
        WarehouseManagementService::ensureMainWarehouse($companyId, $branch->id);

        // Provision branch settings
        self::ensureBranchSettings($branch->id, $companyId, $data['settings'] ?? []);

        return $branch;
    }

    /**
     * Update an existing branch.
     */
    public static function updateBranch(int $branchId, array $data): Branch
    {
        $branch = Branch::findOrFail($branchId);
        $companyId = $branch->company_id;

        if (!empty($data['code'])) {
            $code = strtoupper(trim($data['code']));
            $existing = Branch::where('company_id', $companyId)
                ->where('code', $code)
                ->where('id', '!=', $branchId)
                ->first();
            if ($existing) {
                throw new InvalidArgumentException("Branch code '{$code}' is already used by another branch.");
            }
            $branch->code = $code;
        }

        if (isset($data['name'])) $branch->name = $data['name'];
        if (isset($data['legal_name'])) $branch->legal_name = $data['legal_name'];
        if (isset($data['gstin'])) $branch->gstin = strtoupper(trim($data['gstin']));
        if (isset($data['pan'])) $branch->pan = strtoupper(trim($data['pan']));
        if (isset($data['address'])) $branch->address = $data['address'];
        if (isset($data['city'])) $branch->city = $data['city'];
        if (isset($data['state'])) $branch->state = $data['state'];
        if (isset($data['state_code'])) $branch->state_code = $data['state_code'];
        if (isset($data['pincode'])) $branch->pincode = $data['pincode'];
        if (isset($data['phone'])) $branch->phone = $data['phone'];
        if (isset($data['email'])) $branch->email = $data['email'];

        if (!empty($data['is_main_branch']) && !$branch->is_main_branch) {
            Branch::where('company_id', $companyId)->update(['is_main_branch' => false]);
            $branch->is_main_branch = true;
        }

        if (isset($data['is_active'])) {
            if (!$data['is_active'] && $branch->is_main_branch) {
                throw new RuntimeException("Cannot deactivate the Main Branch of the business.");
            }
            $branch->is_active = (bool)$data['is_active'];
        }

        $branch->save();

        if (!empty($data['settings'])) {
            self::saveBranchSettings($branchId, $data['settings']);
        }

        return $branch;
    }

    /**
     * Deactivate a branch with safety checks.
     */
    public static function deactivateBranch(int $branchId): Branch
    {
        $branch = Branch::findOrFail($branchId);
        if ($branch->is_main_branch) {
            throw new RuntimeException("The Main Branch cannot be deleted or deactivated.");
        }

        // Safety: check for open draft / pending transactions
        $openInvoices = Invoice::where('branch_id', $branchId)->where('status', 'DRAFT')->count();
        if ($openInvoices > 0) {
            throw new RuntimeException("Cannot deactivate branch with {$openInvoices} pending draft invoices.");
        }

        $branch->update(['is_active' => false]);
        return $branch;
    }

    /**
     * Toggle multi-branch mode for the business.
     */
    public static function toggleBranchManagement(int $companyId, bool $enabled): bool
    {
        $company = Company::findOrFail($companyId);
        $company->update(['branch_management_enabled' => $enabled]);
        return $enabled;
    }

    /**
     * Ensure branch settings row exists.
     */
    public static function ensureBranchSettings(int $branchId, int $companyId, array $custom = []): BranchSetting
    {
        $setting = BranchSetting::where('branch_id', $branchId)->first();
        if (!$setting) {
            $branch = Branch::find($branchId);
            $code = $branch ? $branch->code : 'BR';
            $defaultWh = Warehouse::where('branch_id', $branchId)->where('is_default', true)->first();

            $setting = BranchSetting::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'invoice_prefix' => $custom['invoice_prefix'] ?? "{$code}/INV/",
                'quotation_prefix' => $custom['quotation_prefix'] ?? "{$code}/QTN/",
                'purchase_prefix' => $custom['purchase_prefix'] ?? "{$code}/PINV/",
                'receipt_prefix' => $custom['receipt_prefix'] ?? "{$code}/REC/",
                'default_warehouse_id' => $defaultWh?->id,
                'terms_conditions' => $custom['terms_conditions'] ?? 'Standard Branch Terms Apply',
            ]);
        }
        return $setting;
    }

    /**
     * Save branch settings.
     */
    public static function saveBranchSettings(int $branchId, array $settings): BranchSetting
    {
        $branch = Branch::findOrFail($branchId);
        $setting = self::ensureBranchSettings($branchId, $branch->company_id);

        $setting->update([
            'invoice_prefix' => $settings['invoice_prefix'] ?? $setting->invoice_prefix,
            'quotation_prefix' => $settings['quotation_prefix'] ?? $setting->quotation_prefix,
            'purchase_prefix' => $settings['purchase_prefix'] ?? $setting->purchase_prefix,
            'receipt_prefix' => $settings['receipt_prefix'] ?? $setting->receipt_prefix,
            'default_warehouse_id' => $settings['default_warehouse_id'] ?? $setting->default_warehouse_id,
            'logo_url' => $settings['logo_url'] ?? $setting->logo_url,
            'terms_conditions' => $settings['terms_conditions'] ?? $setting->terms_conditions,
            'bank_account_id' => $settings['bank_account_id'] ?? $setting->bank_account_id,
            'settings_json' => $settings['settings_json'] ?? $setting->settings_json,
        ]);

        return $setting;
    }

    /**
     * Assign user to branches and assign default branch.
     */
    public static function assignUserBranches(int $companyId, int $userId, array $branchIds, ?int $defaultBranchId = null): void
    {
        BranchUser::where('company_id', $companyId)->where('user_id', $userId)->delete();

        if (empty($branchIds)) {
            // Assign all branches
            return;
        }

        foreach ($branchIds as $bId) {
            BranchUser::create([
                'company_id' => $companyId,
                'branch_id' => $bId,
                'user_id' => $userId,
                'is_default' => ($bId === $defaultBranchId),
                'can_switch' => true,
            ]);
        }
    }

    /**
     * Get accessible branches for a user.
     */
    public static function getUserAccessibleBranches(User $user, int $companyId): array
    {
        // Admin and Accountant have access to all branches
        $role = strtoupper($user->role ?: 'STAFF');
        if (in_array($role, ['ADMIN', 'SUPER_ADMIN', 'ACCOUNTANT', 'AUDITOR'])) {
            return Branch::where('company_id', $companyId)->where('is_active', true)->get()->toArray();
        }

        $mappings = BranchUser::where('company_id', $companyId)->where('user_id', $user->id)->pluck('branch_id')->toArray();
        if (empty($mappings)) {
            return Branch::where('company_id', $companyId)->where('is_active', true)->get()->toArray();
        }

        return Branch::where('company_id', $companyId)
            ->whereIn('id', $mappings)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }
}
