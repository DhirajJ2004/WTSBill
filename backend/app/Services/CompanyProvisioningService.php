<?php

namespace App\Services;

use App\Models\User;
use App\Models\Company;
use App\Auth\PasswordService;
use App\Auth\SessionService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class CompanyProvisioningService
{
    /**
     * Atomically provision a new company along with all foundational structures.
     *
     * @param array $input Input parameters including company and optional user details
     * @param User|null $existingUser If company is being created by an already authenticated user
     * @param string|null $simulateFailureStep For automated testing of mid-transaction rollback
     * @return array Standardized result response
     */
    public static function provisionCompany(array $input, ?User $existingUser = null, ?string $simulateFailureStep = null): array
    {
        // -------------------------------------------------------------
        // 1. Validation
        // -------------------------------------------------------------
        $companyName = trim($input['company_name'] ?? $input['name'] ?? '');
        $legalName = trim($input['legal_name'] ?? $companyName);
        $gstin = strtoupper(trim($input['gstin'] ?? ''));
        $pan = strtoupper(trim($input['pan'] ?? ''));
        $phone = trim($input['phone'] ?? '');
        $email = trim($input['email'] ?? '');
        $address = trim($input['address'] ?? $input['address_line1'] ?? '');
        $city = trim($input['city'] ?? 'Mumbai');
        $state = trim($input['state'] ?? 'Maharashtra');
        $stateCode = trim($input['state_code'] ?? '27');
        $businessType = trim($input['business_type'] ?? 'Private Limited Company');

        if (empty($companyName)) {
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Company Name is required and cannot be blank.'
            ];
        }

        if (strlen($companyName) < 2) {
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Company Name must be at least 2 characters.'
            ];
        }

        // Validate GSTIN if provided
        if (!empty($gstin)) {
            if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => 'Invalid GSTIN format. Must be a valid 15-character Indian GSTIN.'
                ];
            }

            // Check duplicate GSTIN
            $existingComp = DB::table('companies')
                ->where('gstin', $gstin)
                ->whereNull('deleted_at')
                ->first();

            if ($existingComp) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => "A company with GSTIN '{$gstin}' is already registered."
                ];
            }

            if (empty($pan) && strlen($gstin) === 15) {
                $pan = substr($gstin, 2, 10);
            }
        }

        // Validate user details if registering a new account
        $userName = trim($input['user_name'] ?? $input['name'] ?? '');
        $userEmail = trim($input['user_email'] ?? $input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (!$existingUser) {
            if (empty($userName)) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => 'User Name is required.'
                ];
            }

            if (empty($userEmail)) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => 'User Email is required.'
                ];
            }

            if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => 'Invalid email address format.'
                ];
            }

            if (empty($password)) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => 'Password is required.'
                ];
            }

            if (strlen($password) < 8) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => 'Password must be at least 8 characters in length.'
                ];
            }

            if (DB::table('users')->where('email', $userEmail)->whereNull('deleted_at')->exists()) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => 'An account with this email address already exists.'
                ];
            }
        }

        // -------------------------------------------------------------
        // 2. ATOMIC DATABASE TRANSACTION
        // -------------------------------------------------------------
        DB::beginTransaction();
        try {
            // STEP 2A: Create or resolve User
            if ($existingUser) {
                $user = $existingUser;
            } else {
                $user = User::create([
                    'name' => $userName,
                    'email' => $userEmail,
                    'password' => PasswordService::hash($password),
                    'phone' => $phone ?: null,
                    'role' => 'Owner',
                    'is_active' => true,
                    'status' => 'ACTIVE',
                ]);
            }

            if ($simulateFailureStep === 'after_user') {
                throw new \RuntimeException('Simulated failure after user creation for rollback verification.');
            }

            // STEP 2B: Create Company
            $company = Company::create([
                'name' => $companyName,
                'legal_name' => $legalName ?: $companyName,
                'gstin' => $gstin ?: null,
                'pan' => $pan ?: null,
                'email' => $email ?: ($user->email ?? null),
                'phone' => $phone ?: ($user->phone ?? null),
                'address_line1' => $address ?: 'Corporate Office',
                'city' => $city,
                'state' => $state,
                'state_code' => $stateCode,
                'currency' => 'INR',
                'financial_year_start' => '04-01',
                'business_type' => $businessType,
                'status' => 'ACTIVE',
                'branch_management_enabled' => 1,
                'multi_warehouse_enabled' => 1,
                'allow_negative_stock' => 0,
            ]);

            // Update user default company if not set
            if (!$user->current_company_id) {
                $user->current_company_id = $company->id;
                $user->save();
            }

            if ($simulateFailureStep === 'after_company') {
                throw new \RuntimeException('Simulated failure after company creation for rollback verification.');
            }

            // STEP 2C: Create Owner Membership (Roles & User_Roles)
            $ownerRoleId = DB::table('roles')->insertGetId([
                'company_id' => $company->id,
                'name' => 'Owner',
                'slug' => 'owner',
                'description' => 'Owner with full administrative and financial authority',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Create remaining standard role templates
            $standardRoles = [
                'Admin' => 'Full administrative access',
                'Accountant' => 'Financial ledgers, invoicing, and tax filing access',
                'Auditor' => 'Read-only financial audit and compliance inspection',
                'Inventory Manager' => 'Stock adjustments, warehousing, and purchase receipts',
                'Sales Executive' => 'Sales quotations, invoices, and customer management',
            ];
            foreach ($standardRoles as $rName => $rDesc) {
                DB::table('roles')->insert([
                    'company_id' => $company->id,
                    'name' => $rName,
                    'slug' => strtolower(str_replace(' ', '_', $rName)),
                    'description' => $rDesc,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Assign Owner Role to User
            DB::table('user_roles')->insert([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role_id' => $ownerRoleId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // STEP 2D: Create Default Branch
            $branchId = DB::table('branches')->insertGetId([
                'company_id' => $company->id,
                'name' => 'Head Office',
                'code' => 'HO-01',
                'branch_code' => 'HO-01',
                'legal_name' => $company->legal_name ?: $company->name,
                'gstin' => $company->gstin,
                'pan' => $company->pan,
                'address' => $company->address_line1 ?: 'Head Office Address',
                'city' => $company->city ?: 'Mumbai',
                'state' => $company->state ?: 'Maharashtra',
                'state_code' => $company->state_code ?: '27',
                'phone' => $company->phone,
                'email' => $company->email,
                'is_main_branch' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // User Branch Assignment
            DB::table('user_branches')->insert([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($simulateFailureStep === 'after_branch') {
                throw new \RuntimeException('Simulated failure after branch creation for rollback verification.');
            }

            // STEP 2E: Create Default Warehouse
            $warehouseId = DB::table('warehouses')->insertGetId([
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'name' => 'Main Warehouse',
                'code' => 'MWH-01',
                'city' => $company->city ?: 'Mumbai',
                'state' => $company->state ?: 'Maharashtra',
                'address' => $company->address_line1 ?: 'Main Warehouse Facility',
                'is_primary' => 1,
                'is_default' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // STEP 2F: Create Financial Year (accounting_periods)
            $nowMonth = (int)date('n');
            $nowYear = (int)date('Y');
            $fyStartYear = ($nowMonth >= 4) ? $nowYear : $nowYear - 1;
            $fyEndYear = $fyStartYear + 1;
            $fyLabel = sprintf('%04d-%02d', $fyStartYear, $fyEndYear % 100);
            $startDate = "{$fyStartYear}-04-01";
            $endDate = "{$fyEndYear}-03-31";

            $periodId = DB::table('accounting_periods')->insertGetId([
                'company_id' => $company->id,
                'period_name' => "FY {$fyLabel}",
                'start_date' => $startDate,
                'end_date' => $endDate,
                'financial_year' => $fyLabel,
                'is_closed' => 0,
                'is_locked' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // STEP 2G: Create Chart of Accounts
            $defaultAccounts = [
                // Assets (Nature: DEBIT)
                ['code' => '100100', 'name' => 'Cash in Hand', 'type' => 'ASSET', 'nature' => 'DEBIT', 'desc' => 'Physical cash drawer balance', 'sys' => 1],
                ['code' => '100200', 'name' => 'Bank Current Account', 'type' => 'ASSET', 'nature' => 'DEBIT', 'desc' => 'Primary operating bank account', 'sys' => 1],
                ['code' => '100300', 'name' => 'Accounts Receivable (Debtors)', 'type' => 'ASSET', 'nature' => 'DEBIT', 'desc' => 'Outstanding trade customer receivables', 'sys' => 1],
                ['code' => '100400', 'name' => 'Inventory Stock Asset', 'type' => 'ASSET', 'nature' => 'DEBIT', 'desc' => 'Valuation of inventory stock on hand', 'sys' => 1],
                ['code' => '100500', 'name' => 'GST Input Tax Credit (ITC)', 'type' => 'ASSET', 'nature' => 'DEBIT', 'desc' => 'Input GST credit receivable from tax authority', 'sys' => 1],
                
                // Liabilities (Nature: CREDIT)
                ['code' => '200100', 'name' => 'Accounts Payable (Creditors)', 'type' => 'LIABILITY', 'nature' => 'CREDIT', 'desc' => 'Trade payables owed to suppliers', 'sys' => 1],
                ['code' => '200200', 'name' => 'GST Output Tax Payable', 'type' => 'LIABILITY', 'nature' => 'CREDIT', 'desc' => 'GST collected on outward supplies payable to government', 'sys' => 1],
                ['code' => '200300', 'name' => 'TDS / Withholding Tax Payable', 'type' => 'LIABILITY', 'nature' => 'CREDIT', 'desc' => 'TDS deducted payable to tax department', 'sys' => 1],
                
                // Equity (Nature: CREDIT)
                ['code' => '300100', 'name' => "Owner's Equity Capital", 'type' => 'EQUITY', 'nature' => 'CREDIT', 'desc' => 'Initial capital invested by owners', 'sys' => 1],
                ['code' => '300200', 'name' => 'Retained Earnings', 'type' => 'EQUITY', 'nature' => 'CREDIT', 'desc' => 'Accumulated business profits/reserves', 'sys' => 1],
                
                // Income (Nature: CREDIT)
                ['code' => '400100', 'name' => 'Product Sales Revenue', 'type' => 'INCOME', 'nature' => 'CREDIT', 'desc' => 'Gross income from sale of products', 'sys' => 1],
                ['code' => '400200', 'name' => 'Services Revenue', 'type' => 'INCOME', 'nature' => 'CREDIT', 'desc' => 'Gross income from professional services', 'sys' => 1],
                ['code' => '400300', 'name' => 'Other Income / Discounts Earned', 'type' => 'INCOME', 'nature' => 'CREDIT', 'desc' => 'Miscellaneous income and interest', 'sys' => 1],
                
                // Expenses (Nature: DEBIT)
                ['code' => '500100', 'name' => 'Cost of Goods Sold (COGS)', 'type' => 'EXPENSE', 'nature' => 'DEBIT', 'desc' => 'Direct cost of goods/materials sold', 'sys' => 1],
                ['code' => '500200', 'name' => 'Rent & Facility Expenses', 'type' => 'EXPENSE', 'nature' => 'DEBIT', 'desc' => 'Premises rental and maintenance costs', 'sys' => 0],
                ['code' => '500300', 'name' => 'Salaries & Wages', 'type' => 'EXPENSE', 'nature' => 'DEBIT', 'desc' => 'Staff payroll and benefits', 'sys' => 0],
                ['code' => '500400', 'name' => 'Office & Operational Expenses', 'type' => 'EXPENSE', 'nature' => 'DEBIT', 'desc' => 'General utilities and administrative expenses', 'sys' => 0],
                ['code' => '500500', 'name' => 'Bank Charges & Payment Processing', 'type' => 'EXPENSE', 'nature' => 'DEBIT', 'desc' => 'Bank transaction charges and fees', 'sys' => 0],
            ];

            foreach ($defaultAccounts as $acc) {
                DB::table('chart_of_accounts')->insert([
                    'company_id' => $company->id,
                    'branch_id' => $branchId,
                    'account_code' => $acc['code'],
                    'account_name' => $acc['name'],
                    'account_type' => $acc['type'],
                    'nature' => $acc['nature'],
                    'current_balance' => 0.00,
                    'opening_balance' => 0.00,
                    'opening_balance_type' => $acc['nature'],
                    'description' => $acc['desc'],
                    'is_system_account' => $acc['sys'],
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // STEP 2H: Create Default Tax Configuration
            DB::table('gst_configurations')->insert([
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'gstin' => $company->gstin ?: 'UNREGISTERED',
                'gst_registered' => !empty($company->gstin) ? 1 : 0,
                'composition_scheme' => 'NO',
                'is_composition' => 0,
                'legal_business_name' => $company->legal_name ?: $company->name,
                'trade_name' => $company->name,
                'pan' => $company->pan,
                'registered_state' => $company->state ?: 'Maharashtra',
                'state_code' => $company->state_code ?: '27',
                'tax_registration_type' => !empty($company->gstin) ? 'REGULAR' : 'UNREGISTERED',
                'default_place_of_supply' => $company->state ?: 'Maharashtra',
                'einvoice_applicable' => 0,
                'einvoice_threshold' => 50000000.00,
                'eway_bill_applicable' => 1,
                'eway_threshold' => 50000.00,
                'filing_frequency' => 'MONTHLY',
                'api_environment' => 'SANDBOX',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $standardRates = [
                ['name' => 'GST 0%', 'rate' => 0.00, 'type' => 'GST', 'desc' => 'Nil Rated / Exempt goods'],
                ['name' => 'GST 5%', 'rate' => 5.00, 'type' => 'GST', 'desc' => 'Essential items (2.5% CGST + 2.5% SGST)'],
                ['name' => 'GST 12%', 'rate' => 12.00, 'type' => 'GST', 'desc' => 'Standard Rate 1 (6% CGST + 6% SGST)'],
                ['name' => 'GST 18%', 'rate' => 18.00, 'type' => 'GST', 'desc' => 'Standard Rate 2 (9% CGST + 9% SGST)'],
                ['name' => 'GST 28%', 'rate' => 28.00, 'type' => 'GST', 'desc' => 'Luxury / Demerit Goods (14% CGST + 14% SGST)'],
            ];

            foreach ($standardRates as $sr) {
                DB::table('tax_rates')->insert([
                    'company_id' => $company->id,
                    'name' => $sr['name'],
                    'rate' => $sr['rate'],
                    'tax_type' => $sr['type'],
                    'status' => 'ACTIVE',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                DB::table('gst_rates')->insert([
                    'company_id' => $company->id,
                    'rate' => $sr['rate'],
                    'cess_rate' => 0.00,
                    'description' => $sr['desc'],
                    'is_active' => 1,
                    'is_system_default' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // STEP 2I: Create Document Numbering Configuration
            $docConfigs = [
                ['type' => 'INVOICE', 'prefix' => 'INV-', 'padding' => 4],
                ['type' => 'QUOTATION', 'prefix' => 'QTN-', 'padding' => 4],
                ['type' => 'PAYMENT', 'prefix' => 'PAY-', 'padding' => 4],
                ['type' => 'PURCHASE', 'prefix' => 'PUR-', 'padding' => 4],
                ['type' => 'CREDIT_NOTE', 'prefix' => 'CN-', 'padding' => 4],
                ['type' => 'DEBIT_NOTE', 'prefix' => 'DN-', 'padding' => 4],
                ['type' => 'DELIVERY_CHALLAN', 'prefix' => 'DC-', 'padding' => 4],
            ];

            foreach ($docConfigs as $dc) {
                DB::table('document_numbering_configs')->insert([
                    'company_id' => $company->id,
                    'branch_id' => $branchId,
                    'document_type' => $dc['type'],
                    'prefix' => $dc['prefix'],
                    'suffix' => '/' . $fyLabel,
                    'starting_number' => 1,
                    'current_number' => 0,
                    'number_padding' => $dc['padding'],
                    'reset_frequency' => 'YEARLY',
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                DB::table('document_number_settings')->insert([
                    'company_id' => $company->id,
                    'branch_id' => $branchId,
                    'financial_year' => $fyLabel,
                    'document_type' => $dc['type'],
                    'prefix' => $dc['prefix'],
                    'starting_number' => 1,
                    'current_number' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // STEP 2J: Create Audit Log
            AuditLogService::log(
                $company->id,
                $user->name,
                'COMPANY_PROVISIONED',
                'Company',
                $company->id,
                "Provisioned company '{$company->name}' (ID: {$company->id}) with branch 'Head Office', warehouse 'Main Warehouse', FY {$fyLabel}, standard COA, tax rates, and numbering sequences for owner user {$user->email}."
            );

            // STEP 2K: Commit Database Transaction
            DB::commit();

            // Generate session token if this was a fresh registration
            $token = null;
            if (!$existingUser) {
                $token = SessionService::createSession($user, 'registration-token');
            }

            return [
                'success' => true,
                'code' => 201,
                'message' => 'Company and workspace provisioned successfully.',
                'access_token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'legal_name' => $company->legal_name,
                    'gstin' => $company->gstin,
                    'currency' => $company->currency,
                ],
                'branch' => [
                    'id' => $branchId,
                    'name' => 'Head Office',
                    'code' => 'HO-01',
                ],
                'warehouse' => [
                    'id' => $warehouseId,
                    'name' => 'Main Warehouse',
                    'code' => 'MWH-01',
                ],
                'financial_year' => $fyLabel,
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Company provisioning failed: ' . $e->getMessage()
            ];
        }
    }
}
