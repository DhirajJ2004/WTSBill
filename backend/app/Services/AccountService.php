<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\AccountMapping;
use Illuminate\Database\Capsule\Manager as DB;

class AccountService
{
    /**
     * Standard Indian Business Default Chart of Accounts Definition
     */
    public static function getDefaultAccountDefinitions(): array
    {
        return [
            // --- ASSETS (1000 - 1999) ---
            ['code' => '1000', 'name' => 'Cash in Hand', 'type' => 'ASSET', 'subtype' => 'CASH', 'nature' => 'DEBIT', 'mapping' => 'DEFAULT_CASH_ACCOUNT'],
            ['code' => '1050', 'name' => 'Undeposited Funds / Cheques in Hand', 'type' => 'ASSET', 'subtype' => 'CASH', 'nature' => 'DEBIT', 'mapping' => 'UNDEPOSITED_FUNDS'],
            ['code' => '1100', 'name' => 'Bank Account', 'type' => 'ASSET', 'subtype' => 'BANK', 'nature' => 'DEBIT', 'mapping' => 'DEFAULT_BANK_ACCOUNT'],
            ['code' => '1110', 'name' => 'Payment Gateway Clearing', 'type' => 'ASSET', 'subtype' => 'BANK', 'nature' => 'DEBIT', 'mapping' => 'PAYMENT_GATEWAY_CLEARING'],
            ['code' => '1200', 'name' => 'Accounts Receivable (Debtors)', 'type' => 'ASSET', 'subtype' => 'ACCOUNTS_RECEIVABLE', 'nature' => 'DEBIT', 'mapping' => 'ACCOUNTS_RECEIVABLE'],
            ['code' => '1300', 'name' => 'Inventory Asset', 'type' => 'ASSET', 'subtype' => 'INVENTORY', 'nature' => 'DEBIT', 'mapping' => 'INVENTORY_ASSET'],
            ['code' => '1400', 'name' => 'Input CGST', 'type' => 'ASSET', 'subtype' => 'TAX_RECEIVABLE', 'nature' => 'DEBIT', 'mapping' => 'INPUT_CGST'],
            ['code' => '1410', 'name' => 'Input SGST', 'type' => 'ASSET', 'subtype' => 'TAX_RECEIVABLE', 'nature' => 'DEBIT', 'mapping' => 'INPUT_SGST'],
            ['code' => '1420', 'name' => 'Input IGST', 'type' => 'ASSET', 'subtype' => 'TAX_RECEIVABLE', 'nature' => 'DEBIT', 'mapping' => 'INPUT_IGST'],
            ['code' => '1500', 'name' => 'Fixed Assets', 'type' => 'ASSET', 'subtype' => 'FIXED_ASSETS', 'nature' => 'DEBIT', 'mapping' => 'FIXED_ASSETS'],
            ['code' => '1600', 'name' => 'Supplier Advances Paid', 'type' => 'ASSET', 'subtype' => 'OTHER_CURRENT_ASSET', 'nature' => 'DEBIT', 'mapping' => 'SUPPLIER_ADVANCE'],

            // --- LIABILITIES (2000 - 2999) ---
            ['code' => '2000', 'name' => 'Accounts Payable (Creditors)', 'type' => 'LIABILITY', 'subtype' => 'ACCOUNTS_PAYABLE', 'nature' => 'CREDIT', 'mapping' => 'ACCOUNTS_PAYABLE'],
            ['code' => '2100', 'name' => 'Output CGST', 'type' => 'LIABILITY', 'subtype' => 'GST_PAYABLE', 'nature' => 'CREDIT', 'mapping' => 'OUTPUT_CGST'],
            ['code' => '2110', 'name' => 'Output SGST', 'type' => 'LIABILITY', 'subtype' => 'GST_PAYABLE', 'nature' => 'CREDIT', 'mapping' => 'OUTPUT_SGST'],
            ['code' => '2120', 'name' => 'Output IGST', 'type' => 'LIABILITY', 'subtype' => 'GST_PAYABLE', 'nature' => 'CREDIT', 'mapping' => 'OUTPUT_IGST'],
            ['code' => '2200', 'name' => 'Customer Advances Received', 'type' => 'LIABILITY', 'subtype' => 'OTHER_CURRENT_LIABILITY', 'nature' => 'CREDIT', 'mapping' => 'CUSTOMER_ADVANCE'],
            ['code' => '2250', 'name' => 'Cheques Issued Clearing', 'type' => 'LIABILITY', 'subtype' => 'OTHER_CURRENT_LIABILITY', 'nature' => 'CREDIT', 'mapping' => 'CHEQUES_ISSUED_CLEARING'],
            ['code' => '2300', 'name' => 'Loans & Borrowings', 'type' => 'LIABILITY', 'subtype' => 'LOANS', 'nature' => 'CREDIT', 'mapping' => 'LOANS'],

            // --- EQUITY (3000 - 3999) ---
            ['code' => '3000', 'name' => 'Owner Capital', 'type' => 'EQUITY', 'subtype' => 'CAPITAL', 'nature' => 'CREDIT', 'mapping' => 'OWNER_CAPITAL'],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'EQUITY', 'subtype' => 'RETAINED_EARNINGS', 'nature' => 'CREDIT', 'mapping' => 'RETAINED_EARNINGS'],
            ['code' => '3200', 'name' => 'Owner Drawings', 'type' => 'EQUITY', 'subtype' => 'DRAWINGS', 'nature' => 'DEBIT', 'mapping' => 'DRAWINGS'],

            // --- INCOME (4000 - 4999) ---
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'INCOME', 'subtype' => 'SALES', 'nature' => 'CREDIT', 'mapping' => 'SALES_REVENUE'],
            ['code' => '4100', 'name' => 'Service Income', 'type' => 'INCOME', 'subtype' => 'SERVICE_INCOME', 'nature' => 'CREDIT', 'mapping' => 'SERVICE_INCOME'],
            ['code' => '4200', 'name' => 'Sales Returns / Credit Notes', 'type' => 'INCOME', 'subtype' => 'SALES_RETURN', 'nature' => 'DEBIT', 'mapping' => 'SALES_RETURN'],
            ['code' => '4300', 'name' => 'Discount Received', 'type' => 'INCOME', 'subtype' => 'OTHER_INCOME', 'nature' => 'CREDIT', 'mapping' => 'DISCOUNT_RECEIVED'],
            ['code' => '4400', 'name' => 'Round Off Gain', 'type' => 'INCOME', 'subtype' => 'OTHER_INCOME', 'nature' => 'CREDIT', 'mapping' => 'ROUND_OFF_GAIN'],
            ['code' => '4500', 'name' => 'Other Income', 'type' => 'INCOME', 'subtype' => 'OTHER_INCOME', 'nature' => 'CREDIT', 'mapping' => 'OTHER_INCOME'],
            ['code' => '4600', 'name' => 'Bank Interest Income', 'type' => 'INCOME', 'subtype' => 'OTHER_INCOME', 'nature' => 'CREDIT', 'mapping' => 'INTEREST_INCOME'],

            // --- EXPENSES (5000 - 6999) ---
            ['code' => '5000', 'name' => 'Cost of Goods Sold (COGS)', 'type' => 'EXPENSE', 'subtype' => 'COGS', 'nature' => 'DEBIT', 'mapping' => 'COGS'],
            ['code' => '5100', 'name' => 'Purchase Expense', 'type' => 'EXPENSE', 'subtype' => 'PURCHASES', 'nature' => 'DEBIT', 'mapping' => 'PURCHASE_EXPENSE'],
            ['code' => '5200', 'name' => 'Inventory Loss & Damages', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSES', 'nature' => 'DEBIT', 'mapping' => 'INVENTORY_LOSS'],
            ['code' => '5300', 'name' => 'Sales Discounts Allowed', 'type' => 'EXPENSE', 'subtype' => 'SALES_DISCOUNT', 'nature' => 'DEBIT', 'mapping' => 'SALES_DISCOUNT'],
            ['code' => '6000', 'name' => 'General Operating Expenses', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSES', 'nature' => 'DEBIT', 'mapping' => 'OPERATING_EXPENSES'],
            ['code' => '6100', 'name' => 'Rent Expense', 'type' => 'EXPENSE', 'subtype' => 'RENT', 'nature' => 'DEBIT', 'mapping' => 'RENT_EXPENSE'],
            ['code' => '6200', 'name' => 'Salaries & Wages', 'type' => 'EXPENSE', 'subtype' => 'SALARY', 'nature' => 'DEBIT', 'mapping' => 'SALARY_EXPENSE'],
            ['code' => '6300', 'name' => 'Electricity & Utilities', 'type' => 'EXPENSE', 'subtype' => 'UTILITIES', 'nature' => 'DEBIT', 'mapping' => 'UTILITIES_EXPENSE'],
            ['code' => '6400', 'name' => 'Transportation & Freight', 'type' => 'EXPENSE', 'subtype' => 'TRANSPORTATION', 'nature' => 'DEBIT', 'mapping' => 'FREIGHT_EXPENSE'],
            ['code' => '6500', 'name' => 'Bank Charges', 'type' => 'EXPENSE', 'subtype' => 'BANK_CHARGES', 'nature' => 'DEBIT', 'mapping' => 'BANK_CHARGES'],
            ['code' => '6550', 'name' => 'Payment Gateway Charges', 'type' => 'EXPENSE', 'subtype' => 'BANK_CHARGES', 'nature' => 'DEBIT', 'mapping' => 'PAYMENT_GATEWAY_CHARGES'],
            ['code' => '6600', 'name' => 'Round Off Loss', 'type' => 'EXPENSE', 'subtype' => 'OTHER_EXPENSES', 'nature' => 'DEBIT', 'mapping' => 'ROUND_OFF_LOSS'],
        ];
    }

    private static array $ensuredCompanies = [];

    /**
     * Ensure default Chart of Accounts and mappings exist for a company.
     */
    public static function ensureDefaultAccounts(int $companyId): array
    {
        if (isset(static::$ensuredCompanies[$companyId])) {
            return static::$ensuredCompanies[$companyId];
        }

        $defs = static::getDefaultAccountDefinitions();
        $existingAccounts = ChartOfAccount::where('company_id', $companyId)->get()->keyBy('account_code');
        $existingMappings = AccountMapping::where('company_id', $companyId)->get()->keyBy('mapping_key');
        
        $created = [];
        $needsTransaction = false;

        foreach ($defs as $item) {
            if (!$existingAccounts->has($item['code']) || (!empty($item['mapping']) && !$existingMappings->has($item['mapping']))) {
                $needsTransaction = true;
                break;
            }
            $created[$item['code']] = $existingAccounts->get($item['code']);
        }

        if (!$needsTransaction) {
            static::$ensuredCompanies[$companyId] = $created;
            return $created;
        }

        return DB::transaction(function () use ($companyId, $defs, $existingAccounts, $existingMappings) {
            $created = [];

            foreach ($defs as $item) {
                $account = $existingAccounts->get($item['code']);

                if (!$account) {
                    $account = ChartOfAccount::create([
                        'company_id' => $companyId,
                        'account_code' => $item['code'],
                        'account_name' => $item['name'],
                        'account_type' => $item['type'],
                        'account_subtype' => $item['subtype'],
                        'nature' => $item['nature'],
                        'is_system_account' => true,
                        'is_active' => true,
                        'description' => "Standard System Account: {$item['name']}",
                    ]);
                }

                if (!empty($item['mapping'])) {
                    if (!$existingMappings->has($item['mapping']) || $existingMappings->get($item['mapping'])->account_id != $account->id) {
                        AccountMapping::updateOrCreate(
                            ['company_id' => $companyId, 'mapping_key' => $item['mapping']],
                            ['account_id' => $account->id, 'description' => "Default mapping for {$item['mapping']}"]
                        );
                    }
                }

                $created[$item['code']] = $account;
            }

            static::$ensuredCompanies[$companyId] = $created;
            return $created;
        });
    }

    /**
     * Retrieve mapped account by key (e.g. 'SALES_REVENUE', 'ACCOUNTS_RECEIVABLE').
     */
    public static function getMappedAccount(int $companyId, string $mappingKey): ChartOfAccount
    {
        $mapping = AccountMapping::where('company_id', $companyId)
            ->where('mapping_key', strtoupper($mappingKey))
            ->first();

        if ($mapping && $mapping->account_id) {
            $account = ChartOfAccount::where('company_id', $companyId)->find($mapping->account_id);
            if ($account) return $account;
        }

        // Initialize if not present
        static::ensureDefaultAccounts($companyId);

        $mapping = AccountMapping::where('company_id', $companyId)
            ->where('mapping_key', strtoupper($mappingKey))
            ->first();

        if ($mapping && $mapping->account_id) {
            $account = ChartOfAccount::where('company_id', $companyId)->find($mapping->account_id);
            if ($account) return $account;
        }

        throw new \RuntimeException("Chart of Account mapping for '{$mappingKey}' is not configured for company #{$companyId}.");
    }

    /**
     * Get hierarchical tree of accounts.
     */
    public static function getAccountTree(int $companyId): array
    {
        static::ensureDefaultAccounts($companyId);

        $accounts = ChartOfAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('account_code', 'asc')
            ->get();

        $tree = [];
        $byParent = [];

        foreach ($accounts as $acc) {
            $pid = $acc->parent_account_id ?: 0;
            $byParent[$pid][] = $acc->toArray();
        }

        $buildTree = function ($parentId) use (&$buildTree, &$byParent) {
            $branch = [];
            if (isset($byParent[$parentId])) {
                foreach ($byParent[$parentId] as $node) {
                    $node['children'] = $buildTree($node['id']);
                    $branch[] = $node;
                }
            }
            return $branch;
        };

        return $buildTree(0);
    }
}
