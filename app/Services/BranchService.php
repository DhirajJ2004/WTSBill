<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class BranchService
{
    /**
     * Get all branches for a company.
     */
    public static function getBranches(int $companyId, array $filters = []): array
    {
        $query = Branch::where('company_id', $companyId)->whereNull('deleted_at');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool)$filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', $term)
                  ->orWhere('code', 'LIKE', $term)
                  ->orWhere('branch_code', 'LIKE', $term)
                  ->orWhere('city', 'LIKE', $term);
            });
        }

        return $query->orderBy('is_main_branch', 'desc')->orderBy('name', 'asc')->get()->toArray();
    }

    /**
     * Get single branch by ID with tenant security.
     */
    public static function getBranchById(int $companyId, int $branchId): ?Branch
    {
        return Branch::where('company_id', $companyId)->whereNull('deleted_at')->find($branchId);
    }

    /**
     * Create a new branch.
     */
    public static function createBranch(int $companyId, array $data, string $userName = 'Admin'): array
    {
        $name = trim($data['name'] ?? '');
        $code = strtoupper(trim($data['code'] ?? ($data['branch_code'] ?? '')));

        if (empty($name)) {
            return ['success' => false, 'message' => 'Branch name is required.'];
        }

        if (empty($code)) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6));
        }

        // Check unique code per company
        $exists = Branch::where('company_id', $companyId)
            ->where(function ($q) use ($code) {
                $q->where('code', $code)->orWhere('branch_code', $code);
            })
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return ['success' => false, 'message' => "Branch code '{$code}' already exists in this company."];
        }

        return DB::transaction(function () use ($companyId, $data, $name, $code, $userName) {
            $isMain = !empty($data['is_main_branch']);
            if ($isMain) {
                Branch::where('company_id', $companyId)->update(['is_main_branch' => false]);
            }

            $branch = Branch::create([
                'company_id'    => $companyId,
                'name'          => $name,
                'code'          => $code,
                'branch_code'   => $code,
                'legal_name'    => trim($data['legal_name'] ?? $name),
                'gstin'         => !empty($data['gstin']) ? strtoupper(trim($data['gstin'])) : null,
                'pan'           => !empty($data['pan']) ? strtoupper(trim($data['pan'])) : null,
                'address'       => trim($data['address'] ?? ''),
                'city'          => trim($data['city'] ?? ''),
                'state'         => trim($data['state'] ?? ''),
                'state_code'    => trim($data['state_code'] ?? '27'),
                'pincode'       => trim($data['pincode'] ?? ''),
                'phone'         => trim($data['phone'] ?? ''),
                'email'         => !empty($data['email']) ? strtolower(trim($data['email'])) : null,
                'is_main_branch'=> $isMain,
                'is_active'     => true,
            ]);

            // Create default branch settings
            BranchSetting::firstOrCreate(
                ['company_id' => $companyId, 'branch_id' => $branch->id],
                [
                    'invoice_prefix'   => trim($data['invoice_prefix'] ?? 'INV-'),
                    'quotation_prefix' => trim($data['quotation_prefix'] ?? 'QTN-'),
                    'purchase_prefix'  => trim($data['purchase_prefix'] ?? 'PUR-'),
                    'receipt_prefix'   => trim($data['receipt_prefix'] ?? 'RCP-'),
                ]
            );

            // Create dedicated warehouse for this branch
            Warehouse::firstOrCreate(
                ['company_id' => $companyId, 'branch_id' => $branch->id, 'name' => $branch->name . ' Warehouse'],
                [
                    'code'      => 'WH-' . $branch->code,
                    'is_active' => true,
                ]
            );

            AuditLogService::record([
                'company_id'  => $companyId,
                'branch_id'   => $branch->id,
                'user_name'   => $userName,
                'action'      => 'BRANCH_CREATE',
                'entity'      => 'Branch',
                'entity_id'   => $branch->id,
                'description' => "Created branch '{$branch->name}' [{$branch->code}]",
                'new_values'  => $branch->toArray(),
            ]);

            return [
                'success'   => true,
                'branch_id' => $branch->id,
                'branch'    => $branch,
                'message'   => "Branch '{$branch->name}' created successfully."
            ];
        });
    }

    /**
     * Update an existing branch.
     */
    public static function updateBranch(int $companyId, int $branchId, array $data, string $userName = 'Admin'): array
    {
        $branch = Branch::where('company_id', $companyId)->whereNull('deleted_at')->find($branchId);
        if (!$branch) {
            return ['success' => false, 'message' => 'Branch not found.'];
        }

        return DB::transaction(function () use ($companyId, $branch, $data, $userName) {
            $oldValues = $branch->toArray();

            if (isset($data['name'])) $branch->name = trim($data['name']);
            if (isset($data['legal_name'])) $branch->legal_name = trim($data['legal_name']);
            if (isset($data['gstin'])) $branch->gstin = !empty($data['gstin']) ? strtoupper(trim($data['gstin'])) : null;
            if (isset($data['pan'])) $branch->pan = !empty($data['pan']) ? strtoupper(trim($data['pan'])) : null;
            if (isset($data['address'])) $branch->address = trim($data['address']);
            if (isset($data['city'])) $branch->city = trim($data['city']);
            if (isset($data['state'])) $branch->state = trim($data['state']);
            if (isset($data['state_code'])) $branch->state_code = trim($data['state_code']);
            if (isset($data['pincode'])) $branch->pincode = trim($data['pincode']);
            if (isset($data['phone'])) $branch->phone = trim($data['phone']);
            if (isset($data['email'])) $branch->email = !empty($data['email']) ? strtolower(trim($data['email'])) : null;
            if (isset($data['is_active'])) $branch->is_active = (bool)$data['is_active'];

            if (!empty($data['is_main_branch'])) {
                Branch::where('company_id', $companyId)->where('id', '!=', $branch->id)->update(['is_main_branch' => false]);
                $branch->is_main_branch = true;
            }

            $branch->save();

            AuditLogService::record([
                'company_id'  => $companyId,
                'branch_id'   => $branch->id,
                'user_name'   => $userName,
                'action'      => 'BRANCH_UPDATE',
                'entity'      => 'Branch',
                'entity_id'   => $branch->id,
                'description' => "Updated branch '{$branch->name}'",
                'old_values'  => $oldValues,
                'new_values'  => $branch->toArray(),
            ]);

            return [
                'success' => true,
                'branch'  => $branch,
                'message' => "Branch '{$branch->name}' updated successfully."
            ];
        });
    }
}
