<?php

namespace App\Database;

use App\Models\Company;
use App\Models\User;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Expense;
use App\Models\BankAccount;
use App\Models\StockMovement;
use App\Models\AuditLog;
use Illuminate\Database\Capsule\Manager as DB;

class DatabaseSeeder
{
    public static function seed(): void
    {
        SchemaManager::migrate();

        if (User::count() > 0) {
            return; // Already populated
        }

        // 1. Company
        $company = Company::create([
            'name' => 'Wis Technosavvy Pvt Ltd',
            'legal_name' => 'Wis Technosavvy Pvt Ltd',
            'gstin' => '27AADCW7577N1ZE',
            'pan' => 'AADCW7577N',
            'email' => 'info@wtsindia.co.in',
            'phone' => '020 47252364',
            'address_line1' => 'Office No-B-7, 2nd floor, Shreya Business Hub, Pari chowk, Opp CNG Pump, Narhe',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'pincode' => '411041',
            'currency' => 'INR',
            'financial_year_start' => '04-01',
            'business_type' => 'Technology & IT Services',
            'status' => 'ACTIVE',
        ]);

        // 2. Branches
        $branchMain = DB::table('branches')->insertGetId([
            'company_id' => $company->id,
            'name' => 'Main Branch - Mumbai',
            'branch_code' => 'BOM-01',
            'gstin' => '27AAAAA0000A1Z5',
            'address' => 'Bandra Kurla Complex, Mumbai',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'is_main_branch' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $branchPune = DB::table('branches')->insertGetId([
            'company_id' => $company->id,
            'name' => 'Pune Regional Branch',
            'branch_code' => 'PUN-02',
            'gstin' => '27AAAAA0000A1Z5',
            'address' => 'Hinjewadi IT Park, Pune',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'is_main_branch' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // 3. Warehouses
        $whA = DB::table('warehouses')->insertGetId([
            'company_id' => $company->id,
            'branch_id' => $branchMain,
            'name' => 'Warehouse A - Andheri MIDC',
            'code' => 'WH-A',
            'city' => 'Mumbai',
            'is_primary' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // 4. Users (5 Users)
        $usersData = [
            ['name' => 'Anil Desai', 'email' => 'anil.d@wtsbill.in', 'role' => 'ADMIN', 'phone' => '+91 98201 11111'],
            ['name' => 'Priya Sharma', 'email' => 'priya.s@wtsbill.in', 'role' => 'ACCOUNTANT', 'phone' => '+91 98202 22222'],
            ['name' => 'Rohan Mehta', 'email' => 'rohan.m@external.com', 'role' => 'AUDITOR', 'phone' => '+91 98203 33333'],
            ['name' => 'Suresh Patel', 'email' => 'suresh.p@wtsbill.in', 'role' => 'INVENTORY_MANAGER', 'phone' => '+91 98204 44444'],
            ['name' => 'Neha Gupta', 'email' => 'neha.g@wtsbill.in', 'role' => 'SALES_EXECUTIVE', 'phone' => '+91 98205 55555'],
        ];

        $rolesMap = [];
        $defaultRoles = ['ADMIN', 'ACCOUNTANT', 'AUDITOR', 'INVENTORY_MANAGER', 'SALES_EXECUTIVE'];
        foreach ($defaultRoles as $rName) {
            $slug = strtolower(str_replace(' ', '_', $rName));
            $rId = DB::table('roles')->insertGetId([
                'company_id' => $company->id,
                'name' => $rName,
                'slug' => $slug,
                'description' => "Default company role for {$rName}",
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $rolesMap[$rName] = $rId;
        }

        foreach ($usersData as $u) {
            $userModel = User::create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => password_hash('password123', PASSWORD_BCRYPT),
                'phone' => $u['phone'],
                'role' => $u['role'],
                'current_company_id' => $company->id,
                'is_active' => true,
                'status' => 'ACTIVE',
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);

            // Assign User Role
            if (isset($rolesMap[$u['role']])) {
                DB::table('user_roles')->insert([
                    'company_id' => $company->id,
                    'user_id' => $userModel->id,
                    'role_id' => $rolesMap[$u['role']],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Assign Branch Access
            $assignedBranches = [];
            if ($u['role'] === 'ADMIN' || $u['role'] === 'ACCOUNTANT') {
                $assignedBranches = [$branchMain, $branchPune];
            } else if ($u['role'] === 'INVENTORY_MANAGER') {
                $assignedBranches = [$branchPune];
            } else {
                $assignedBranches = [$branchMain];
            }

            foreach ($assignedBranches as $bId) {
                DB::table('user_branches')->insert([
                    'company_id' => $company->id,
                    'user_id' => $userModel->id,
                    'branch_id' => $bId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // 4b. Seed standard Tax Rates
        $taxRates = [
            ['name' => 'GST 18%', 'rate' => 18.00, 'type' => 'GST'],
            ['name' => 'GST 12%', 'rate' => 12.00, 'type' => 'GST'],
            ['name' => 'GST 5%', 'rate' => 5.00, 'type' => 'GST'],
            ['name' => 'GST 0%', 'rate' => 0.00, 'type' => 'GST'],
            ['name' => 'GST 28%', 'rate' => 28.00, 'type' => 'GST'],
        ];
        foreach ($taxRates as $tr) {
            DB::table('tax_rates')->insert([
                'company_id' => $company->id,
                'name' => $tr['name'],
                'rate' => $tr['rate'],
                'tax_type' => $tr['type'],
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 4c. Seed standard Units of Measurement
        $defaultUnits = [
            ['name' => 'Pieces', 'short' => 'Pcs', 'type' => 'QUANTITY', 'precision' => 0],
            ['name' => 'Kilograms', 'short' => 'KG', 'type' => 'WEIGHT', 'precision' => 3],
            ['name' => 'Boxes', 'short' => 'Box', 'type' => 'QUANTITY', 'precision' => 0],
            ['name' => 'Meters', 'short' => 'Mtrs', 'type' => 'LENGTH', 'precision' => 2],
        ];
        foreach ($defaultUnits as $du) {
            DB::table('units')->insert([
                'company_id' => $company->id,
                'name' => $du['name'],
                'short_name' => $du['short'],
                'type' => $du['type'],
                'decimal_precision' => $du['precision'],
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 4d. Seed Chart of Accounts
        $defaultAccounts = [
            ['code' => '100100', 'name' => 'Cash in Hand', 'type' => 'ASSET', 'description' => 'Physical cash drawer balance', 'is_system' => true],
            ['code' => '100200', 'name' => 'HDFC Current Account', 'type' => 'ASSET', 'description' => 'Primary business checking account', 'is_system' => true],
            ['code' => '100300', 'name' => 'Accounts Receivable (Debtors)', 'type' => 'ASSET', 'description' => 'Outstanding customer dues ledger', 'is_system' => true],
            ['code' => '100400', 'name' => 'Closing Inventory Stock', 'type' => 'ASSET', 'description' => 'Asset value of physical product catalog', 'is_system' => true],
            ['code' => '100500', 'name' => 'GST Input Credit', 'type' => 'ASSET', 'description' => 'Input Tax Credit receivable', 'is_system' => true],
            ['code' => '200100', 'name' => 'Accounts Payable (Creditors)', 'type' => 'LIABILITY', 'description' => 'Outstanding supplier bills ledger', 'is_system' => true],
            ['code' => '200200', 'name' => 'GST Output Payable', 'type' => 'LIABILITY', 'description' => 'Consolidated tax liability', 'is_system' => true],
            ['code' => '300100', 'name' => "Owner's Equity Capital", 'type' => 'EQUITY', 'description' => 'Initial owner investment capital', 'is_system' => true],
            ['code' => '400100', 'name' => 'Product Sales Revenue', 'type' => 'INCOME', 'description' => 'Earned sales revenue', 'is_system' => true],
            ['code' => '500100', 'name' => 'Cost of Goods Sold (COGS)', 'type' => 'EXPENSE', 'description' => 'Direct material acquisition cost', 'is_system' => true],
            ['code' => '500200', 'name' => 'Office Rent & Utilities', 'type' => 'EXPENSE', 'description' => 'Facility rent and electrical costs', 'is_system' => false],
        ];
        foreach ($defaultAccounts as $acc) {
            DB::table('chart_of_accounts')->insert([
                'company_id' => $company->id,
                'account_name' => $acc['name'],
                'account_code' => $acc['code'],
                'account_type' => $acc['type'],
                'description' => $acc['description'],
                'is_system_account' => $acc['is_system'],
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 5. Product Categories
        $catHardware = DB::table('categories')->insertGetId(['company_id' => $company->id, 'name' => 'Hardware', 'code' => 'CAT-HW']);

        // 6. 50 Products
        $productCatalog = [
            ['name' => 'Industrial Brass Valve 2inch', 'sku' => 'HW-BRV-020', 'hsn' => '84818020', 'unit' => 'Pcs', 'sales' => 1250.00, 'purchase' => 850.00, 'gst' => 18, 'stock' => 145],
            ['name' => 'PVC Pipe 4-inch Heavy Duty', 'sku' => 'PL-PVC-400H', 'hsn' => '39172310', 'unit' => 'Mtrs', 'sales' => 450.00, 'purchase' => 310.00, 'gst' => 18, 'stock' => 12],
            ['name' => 'Copper Wire 1.5sqmm Red', 'sku' => 'EL-CWR-15R', 'hsn' => '85444999', 'unit' => 'Coils', 'sales' => 1850.00, 'purchase' => 1350.00, 'gst' => 18, 'stock' => 0],
            ['name' => 'LED Panel Light 12W Square', 'sku' => 'EL-LED-12S', 'hsn' => '94054090', 'unit' => 'Pcs', 'sales' => 350.00, 'purchase' => 210.00, 'gst' => 18, 'stock' => 450],
            ['name' => 'SS Fastener M8 x 75mm', 'sku' => 'HW-SSF-M875', 'hsn' => '73181500', 'unit' => 'Box', 'sales' => 420.00, 'purchase' => 280.00, 'gst' => 18, 'stock' => 85],
        ];

        for ($i = 6; $i <= 50; $i++) {
            $productCatalog[] = [
                'name' => "Industrial Equipment Component Part #{$i}",
                'sku' => "EQ-PRT-" . str_pad($i, 3, '0', STR_PAD_LEFT),
                'hsn' => '8481' . rand(10, 90),
                'unit' => ($i % 3 === 0) ? 'Box' : 'Pcs',
                'sales' => 500.00 + ($i * 40),
                'purchase' => 300.00 + ($i * 25),
                'gst' => 18,
                'stock' => rand(15, 300),
            ];
        }

        $createdProducts = [];
        foreach ($productCatalog as $p) {
            $prod = Product::create([
                'company_id' => $company->id,
                'category_id' => $catHardware,
                'name' => $p['name'],
                'sku' => $p['sku'],
                'hsn_sac' => $p['hsn'],
                'unit' => $p['unit'],
                'sales_price' => $p['sales'],
                'purchase_price' => $p['purchase'],
                'tax_rate' => $p['gst'],
                'current_stock' => $p['stock'],
                'min_stock_alert' => 20,
                'barcode' => 'BAR-' . $p['sku'],
                'is_active' => true,
            ]);
            $createdProducts[] = $prod;

            DB::table('stock_balances')->insert([
                'company_id' => $company->id,
                'warehouse_id' => $whA,
                'product_id' => $prod->id,
                'quantity' => $p['stock'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 7. 20 Customers
        $customerList = [
            ['name' => 'Ramesh Hardware Stores', 'gstin' => '27BBBBB1234C1Z5', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'state_code' => '27', 'bal' => 45200.00],
            ['name' => 'TechCorp Distributors Ltd', 'gstin' => '27AAACT8877K1Z3', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'state_code' => '27', 'bal' => 112500.00],
            ['name' => 'Sharma Electronics', 'gstin' => '27AABCS5544N1ZX', 'city' => 'Thane', 'state' => 'Maharashtra', 'state_code' => '27', 'bal' => 18450.00],
            ['name' => 'Office Supplies Co', 'gstin' => '27AAACO9988D1Z2', 'city' => 'Navi Mumbai', 'state' => 'Maharashtra', 'state_code' => '27', 'bal' => 4200.00],
        ];

        for ($i = 5; $i <= 20; $i++) {
            $customerList[] = [
                'name' => "Bharat Client Enterprise #{$i}",
                'gstin' => "27AABCC" . (1000 + $i) . "F1Z" . ($i % 9),
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'state_code' => '27',
                'bal' => rand(5000, 60000),
            ];
        }

        $createdCustomers = [];
        foreach ($customerList as $c) {
            $cust = Customer::create([
                'company_id' => $company->id,
                'name' => $c['name'],
                'company_name' => $c['name'],
                'gstin' => $c['gstin'],
                'pan' => substr($c['gstin'], 2, 10),
                'email' => strtolower(str_replace(' ', '', $c['name'])) . '@domain.com',
                'phone' => '+91 98' . rand(10000000, 99999999),
                'address_line1' => 'Industrial Estate Road, ' . $c['city'],
                'city' => $c['city'],
                'state' => $c['state'],
                'state_code' => $c['state_code'],
                'pincode' => '4000' . rand(10, 99),
                'opening_balance' => 0.00,
                'current_balance' => $c['bal'],
                'credit_limit' => 500000.00,
                'is_active' => true,
            ]);
            $createdCustomers[] = $cust;
        }

        // 8. 10 Suppliers
        $supplierList = [
            ['name' => 'Acme Industrial Supplies Pvt Ltd', 'gstin' => '27AABCA1234D1Z5', 'city' => 'Mumbai'],
            ['name' => 'Mahindra Precision Steel Tubes', 'gstin' => '27AABCM7766P1ZQ', 'city' => 'Pune'],
            ['name' => 'Siemens Industrial Automation', 'gstin' => '27AAACS1122M1ZY', 'city' => 'Mumbai'],
        ];

        for ($i = 4; $i <= 10; $i++) {
            $supplierList[] = [
                'name' => "Industrial Vendor Corp #{$i}",
                'gstin' => "27AAACV" . (2000 + $i) . "K1Z7",
                'city' => 'Mumbai',
            ];
        }

        $createdSuppliers = [];
        foreach ($supplierList as $s) {
            $supp = Supplier::create([
                'company_id' => $company->id,
                'name' => $s['name'],
                'company_name' => $s['name'],
                'gstin' => $s['gstin'],
                'email' => strtolower(str_replace(' ', '', $s['name'])) . '@vendor.in',
                'phone' => '+91 22 28' . rand(100000, 999999),
                'city' => $s['city'],
                'state' => 'Maharashtra',
                'state_code' => '27',
                'pincode' => '400018',
                'opening_balance' => 0.00,
                'current_balance' => 125000.00,
                'is_active' => true,
            ]);
            $createdSuppliers[] = $supp;
        }

        // 9. Sample Invoices
        $inv1 = Invoice::create([
            'company_id' => $company->id,
            'branch_id' => $branchMain,
            'customer_id' => $createdCustomers[0]->id,
            'invoice_number' => 'INV-2023-0451',
            'reference_po_number' => 'PO/44/23',
            'invoice_date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'place_of_supply' => '27',
            'is_igst' => false,
            'sub_total' => 60000.00,
            'discount_rate' => 5.00,
            'discount_amount' => 3000.00,
            'cgst_amount' => 5130.00,
            'sgst_amount' => 5130.00,
            'igst_amount' => 0.00,
            'total_tax' => 10260.00,
            'grand_total' => 67260.00,
            'amount_paid' => 67260.00,
            'amount_due' => 0.00,
            'status' => 'PAID',
            'payment_mode' => 'Bank Transfer',
            'notes' => 'Thanks for your business.',
            'terms_and_conditions' => "1. Goods once sold will not be taken back.\n2. Interest @ 18% p.a. will be charged if delayed beyond 30 days.\n3. Subject to Mumbai jurisdiction.",
        ]);

        InvoiceItem::create([
            'company_id' => $company->id,
            'invoice_id' => $inv1->id,
            'product_id' => $createdProducts[0]->id,
            'item_name' => $createdProducts[0]->name,
            'hsn_sac' => $createdProducts[0]->hsn_sac,
            'quantity' => 50,
            'unit' => 'Pcs',
            'unit_price' => 1200.00,
            'taxable_value' => 57000.00,
            'gst_rate' => 18.00,
            'cgst_rate' => 9.00,
            'cgst_amount' => 5130.00,
            'sgst_rate' => 9.00,
            'sgst_amount' => 5130.00,
            'igst_rate' => 0.00,
            'igst_amount' => 0.00,
            'total_amount' => 67260.00,
        ]);

        // 10. Sample Purchase Invoice
        $pur1 = Purchase::create([
            'company_id' => $company->id,
            'supplier_id' => $createdSuppliers[0]->id,
            'purchase_number' => 'ACME/23-24/492',
            'vendor_invoice_number' => 'ACME/23-24/492',
            'purchase_date' => '2026-08-01',
            'due_date' => '2026-08-30',
            'sub_total' => 150750.00,
            'cgst_amount' => 13567.50,
            'sgst_amount' => 13567.50,
            'igst_amount' => 0.00,
            'total_tax' => 27135.00,
            'grand_total' => 177885.00,
            'amount_paid' => 177885.00,
            'amount_due' => 0.00,
            'status' => 'PAID',
            'notes' => 'Procurement of Industrial Steel Grade A and Welding Rods.',
        ]);

        PurchaseItem::create([
            'company_id' => $company->id,
            'purchase_id' => $pur1->id,
            'product_id' => $createdProducts[0]->id,
            'item_name' => 'Industrial Steel Grade A',
            'hsn_sac' => '7208',
            'quantity' => 1500,
            'unit' => 'KGS',
            'unit_price' => 85.50,
            'taxable_value' => 128250.00,
            'gst_rate' => 18.00,
            'tax_amount' => 23085.00,
            'total_amount' => 151335.00,
        ]);

        // 11. Expenses
        Expense::create([
            'company_id' => $company->id,
            'expense_number' => 'EXP-2026-0089',
            'category' => 'Rent & Facilities',
            'payee' => 'K Raheja Corp Industrial Parks',
            'expense_date' => '2026-08-01',
            'amount' => 65000.00,
            'tax_amount' => 11700.00,
            'payment_mode' => 'Bank Transfer',
            'gstin' => '27AAACK4433F1Z1',
            'is_itc_eligible' => true,
            'description' => 'Monthly Office Space Lease',
        ]);

        // 12. Bank Account
        BankAccount::create([
            'company_id' => $company->id,
            'bank_name' => 'HDFC Bank',
            'account_name' => 'HDFC Current A/c',
            'account_number' => '50200048899211',
            'ifsc_code' => 'HDFC0000240',
            'branch_name' => 'BKC Branch',
            'current_balance' => 1240500.00,
            'is_primary' => true,
        ]);
    }
}
