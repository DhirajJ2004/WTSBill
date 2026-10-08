<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class CompanyService
{
    /**
     * Get all companies accessible to the system or filtered by IDs.
     */
    public static function getAllCompanies(array $filters = []): array
    {
        $query = Company::whereNull('deleted_at');

        if (!empty($filters['status'])) {
            $query->where('status', strtoupper(trim($filters['status'])));
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('legal_name', 'LIKE', $search)
                  ->orWhere('gstin', 'LIKE', $search)
                  ->orWhere('email', 'LIKE', $search);
            });
        }

        return $query->orderBy('name', 'asc')->get()->toArray();
    }

    /**
     * Get single company by ID.
     */
    public static function getCompanyById(int $companyId): ?Company
    {
        return Company::whereNull('deleted_at')->find($companyId);
    }

    /**
     * Create a new company with default Main Branch, Default Warehouse, and FY.
     */
    public static function createCompany(array $data, string $userName = 'Admin'): array
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            return ['success' => false, 'message' => 'Company name is required.'];
        }

        return DB::transaction(function () use ($data, $name, $userName) {
            $company = Company::create([
                'name'                     => $name,
                'legal_name'               => trim($data['legal_name'] ?? $name),
                'gstin'                    => !empty($data['gstin']) ? strtoupper(trim($data['gstin'])) : null,
                'pan'                      => !empty($data['pan']) ? strtoupper(trim($data['pan'])) : null,
                'email'                    => !empty($data['email']) ? strtolower(trim($data['email'])) : null,
                'phone'                    => trim($data['phone'] ?? ''),
                'address_line1'            => trim($data['address_line1'] ?? ''),
                'address_line2'            => trim($data['address_line2'] ?? ''),
                'city'                     => trim($data['city'] ?? ''),
                'state'                    => trim($data['state'] ?? 'Maharashtra'),
                'state_code'               => trim($data['state_code'] ?? '27'),
                'pincode'                  => trim($data['pincode'] ?? ''),
                'currency'                 => trim($data['currency'] ?? 'INR'),
                'financial_year_start'     => trim($data['financial_year_start'] ?? '04-01'),
                'logo_url'                 => trim($data['logo_url'] ?? ''),
                'business_type'            => trim($data['business_type'] ?? 'Trading'),
                'status'                   => 'ACTIVE',
                'branch_management_enabled'=> !empty($data['branch_management_enabled']),
                'multi_warehouse_enabled'  => !empty($data['multi_warehouse_enabled']),
                'allow_negative_stock'     => !empty($data['allow_negative_stock']),
            ]);

            // 1. Create Main Branch
            $mainBranch = Branch::create([
                'company_id'    => $company->id,
                'name'          => 'Main Branch',
                'code'          => 'MAIN',
                'branch_code'   => 'MAIN',
                'legal_name'    => $company->legal_name,
                'gstin'         => $company->gstin,
                'pan'           => $company->pan,
                'address'       => $company->address_line1,
                'city'          => $company->city,
                'state'         => $company->state,
                'state_code'    => $company->state_code,
                'pincode'       => $company->pincode,
                'phone'         => $company->phone,
                'email'         => $company->email,
                'is_main_branch'=> true,
                'is_active'     => true,
            ]);

            // 2. Create Default Warehouse
            $warehouse = Warehouse::create([
                'company_id' => $company->id,
                'branch_id'  => $mainBranch->id,
                'name'       => 'Default Warehouse',
                'code'       => 'WH-MAIN',
                'is_active'  => true,
            ]);

            // 3. Create Branch Settings
            BranchSetting::create([
                'company_id'           => $company->id,
                'branch_id'            => $mainBranch->id,
                'invoice_prefix'       => 'INV-',
                'quotation_prefix'     => 'QTN-',
                'purchase_prefix'      => 'PUR-',
                'receipt_prefix'       => 'RCP-',
                'default_warehouse_id' => $warehouse->id,
            ]);

            // 4. Create Standard Roles
            $standardRoles = [
                ['name' => 'Owner', 'slug' => 'owner', 'description' => 'Owner with full administrative and financial authority'],
                ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Full administrative access'],
                ['name' => 'Accountant', 'slug' => 'accountant', 'description' => 'Financial ledgers, invoicing, and tax filing access'],
                ['name' => 'Auditor', 'slug' => 'auditor', 'description' => 'Read-only financial audit and compliance inspection'],
                ['name' => 'Inventory Manager', 'slug' => 'inventory_manager', 'description' => 'Stock adjustments, warehousing, and purchase receipts'],
                ['name' => 'Sales Executive', 'slug' => 'sales_executive', 'description' => 'Sales quotations, invoices, and customer management'],
                ['name' => 'Staff', 'slug' => 'staff', 'description' => 'General operational staff'],
            ];

            foreach ($standardRoles as $r) {
                \App\Models\Role::firstOrCreate(
                    ['company_id' => $company->id, 'slug' => $r['slug']],
                    ['name' => $r['name'], 'description' => $r['description']]
                );
            }

            // 5. Create Current Accounting Period (FY)
            $currentYear = (int)date('Y');
            $currentMonth = (int)date('m');
            $fyStartYear = ($currentMonth >= 4) ? $currentYear : ($currentYear - 1);
            $fyEndYear = $fyStartYear + 1;
            $fyString = "{$fyStartYear}-" . substr((string)$fyEndYear, -2);

            AccountingPeriod::create([
                'company_id'     => $company->id,
                'period_name'    => "FY {$fyString}",
                'financial_year' => $fyString,
                'start_date'     => "{$fyStartYear}-04-01",
                'end_date'       => "{$fyEndYear}-03-31",
                'is_locked'      => false,
            ]);

            AuditLogService::record([
                'company_id'  => $company->id,
                'user_name'   => $userName,
                'action'      => 'CREATE_COMPANY',
                'entity'      => 'Company',
                'entity_id'   => $company->id,
                'description' => "Provisioned company '{$company->name}' with Main Branch and Default FY {$fyString}",
            ]);

            return [
                'success'    => true,
                'company_id' => $company->id,
                'company'    => $company,
                'message'    => "Company '{$company->name}' created successfully."
            ];
        });
    }

    /**
     * Update existing company profile.
     */
    public static function updateCompany(int $companyId, array $data, string $userName = 'Admin'): array
    {
        $company = Company::whereNull('deleted_at')->find($companyId);
        if (!$company) {
            return ['success' => false, 'message' => 'Company not found.'];
        }

        $oldData = $company->toArray();

        if (isset($data['name'])) $company->name = trim($data['name']);
        if (isset($data['legal_name'])) $company->legal_name = trim($data['legal_name']);
        if (isset($data['gstin'])) $company->gstin = !empty($data['gstin']) ? strtoupper(trim($data['gstin'])) : null;
        if (isset($data['pan'])) $company->pan = !empty($data['pan']) ? strtoupper(trim($data['pan'])) : null;
        if (isset($data['email'])) $company->email = !empty($data['email']) ? strtolower(trim($data['email'])) : null;
        if (isset($data['phone'])) $company->phone = trim($data['phone']);
        if (isset($data['address_line1'])) $company->address_line1 = trim($data['address_line1']);
        if (isset($data['address_line2'])) $company->address_line2 = trim($data['address_line2']);
        if (isset($data['city'])) $company->city = trim($data['city']);
        if (isset($data['state'])) $company->state = trim($data['state']);
        if (isset($data['state_code'])) $company->state_code = trim($data['state_code']);
        if (isset($data['pincode'])) $company->pincode = trim($data['pincode']);
        if (isset($data['currency'])) $company->currency = trim($data['currency']);
        if (isset($data['logo_url'])) $company->logo_url = trim($data['logo_url']);
        if (isset($data['business_type'])) $company->business_type = trim($data['business_type']);
        if (isset($data['status'])) $company->status = strtoupper(trim($data['status']));
        if (isset($data['branch_management_enabled'])) $company->branch_management_enabled = (bool)$data['branch_management_enabled'];
        if (isset($data['multi_warehouse_enabled'])) $company->multi_warehouse_enabled = (bool)$data['multi_warehouse_enabled'];
        if (isset($data['allow_negative_stock'])) $company->allow_negative_stock = (bool)$data['allow_negative_stock'];

        $company->save();

        AuditLogService::record([
            'company_id'  => $company->id,
            'user_name'   => $userName,
            'action'      => 'UPDATE_COMPANY',
            'entity'      => 'Company',
            'entity_id'   => $company->id,
            'description' => "Updated company profile for '{$company->name}'",
            'old_values'  => $oldData,
            'new_values'  => $company->toArray(),
        ]);

        return [
            'success' => true,
            'company' => $company,
            'message' => "Company '{$company->name}' updated successfully."
        ];
    }
}
