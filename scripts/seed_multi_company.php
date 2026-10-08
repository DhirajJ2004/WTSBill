<?php
require_once __DIR__ . '/../backend/vendor/autoload.php';
use App\Database\Database;
use Illuminate\Database\Capsule\Manager as DB;

Database::init();

echo "Seeding Company B (Multi-Tenant Isolation Test Fixture)..." . PHP_EOL;

// 1. Company 2
$company2 = DB::table('companies')->where('id', 2)->first();
if (!$company2) {
    DB::table('companies')->insert([
        'id' => 2,
        'name' => 'Innovatech Dynamics Pvt Ltd',
        'legal_name' => 'Innovatech Dynamics Pvt Ltd',
        'email' => 'contact@innovatech.in',
        'phone' => '020 87654321',
        'address_line1' => 'Plot 42, Tech Park, Baner',
        'city' => 'Pune',
        'state' => 'Maharashtra',
        'state_code' => '27',
        'pincode' => '411045',
        'gstin' => '27AABCI9999M1Z9',
        'pan' => 'AABCI9999M',
        'currency' => 'INR',
        'status' => 'ACTIVE',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "Created Company 2: Innovatech Dynamics Pvt Ltd" . PHP_EOL;
} else {
    echo "Company 2 already exists." . PHP_EOL;
}

// 2. Branch for Company 2
$branch2 = DB::table('branches')->where('id', 3)->first();
if (!$branch2) {
    DB::table('branches')->insert([
        'id' => 3,
        'company_id' => 2,
        'name' => 'Innovatech Pune HO',
        'branch_code' => 'INN-PUN',
        'address' => 'Plot 42, Tech Park, Baner, Pune, MH - 411045',
        'city' => 'Pune',
        'state' => 'Maharashtra',
        'state_code' => '27',
        'gstin' => '27AABCI9999M1Z9',
        'is_main_branch' => 1,
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "Created Branch 3: Innovatech Pune HO (Company 2)" . PHP_EOL;
} else {
    echo "Branch 3 already exists." . PHP_EOL;
}

// 3. Role for Company 2
$role2 = DB::table('roles')->where('company_id', 2)->where('slug', 'admin')->first();
if (!$role2) {
    $role2Id = DB::table('roles')->insertGetId([
        'company_id' => 2,
        'name' => 'ADMIN',
        'slug' => 'admin',
        'description' => 'Administrator for Innovatech Dynamics',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "Created Role ADMIN for Company 2 (ID: $role2Id)" . PHP_EOL;
} else {
    $role2Id = $role2->id;
    echo "Role ADMIN for Company 2 already exists (ID: $role2Id)" . PHP_EOL;
}

// 4. User B (Vikram Malhotra)
$userB = DB::table('users')->where('email', 'vikram.m@innovatech.in')->first();
if (!$userB) {
    $userBId = DB::table('users')->insertGetId([
        'name' => 'Vikram Malhotra',
        'email' => 'vikram.m@innovatech.in',
        'password' => password_hash('password123', PASSWORD_BCRYPT),
        'phone' => '+91 9922334455',
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "Created User B: Vikram Malhotra (ID: $userBId)" . PHP_EOL;
} else {
    $userBId = $userB->id;
    echo "User B already exists (ID: $userBId)" . PHP_EOL;
}

// Assign User B to Company 2 Role
$userRole2 = DB::table('user_roles')->where('company_id', 2)->where('user_id', $userBId)->first();
if (!$userRole2) {
    DB::table('user_roles')->insert([
        'company_id' => 2,
        'user_id' => $userBId,
        'role_id' => $role2Id,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "Assigned User B to Company 2 Role" . PHP_EOL;
}

// Assign User B to Branch 3
$userBranch2 = DB::table('user_branches')->where('company_id', 2)->where('user_id', $userBId)->first();
if (!$userBranch2) {
    DB::table('user_branches')->insert([
        'company_id' => 2,
        'user_id' => $userBId,
        'branch_id' => 3,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "Assigned User B to Branch 3" . PHP_EOL;
}

// 5. Customer for Company 2
$customerB = DB::table('customers')->where('company_id', 2)->first();
if (!$customerB) {
    $customerBId = DB::table('customers')->insertGetId([
        'company_id' => 2,
        'name' => 'Zenith Retail Ltd',
        'company_name' => 'Zenith Retail Ltd',
        'email' => 'procurement@zenithretail.in',
        'phone' => '+91 9822001122',
        'gstin' => '27AABCZ1234F1Z5',
        'pan' => 'AABCZ1234F',
        'address_line1' => 'Sector 18, Vashi, Navi Mumbai, MH',
        'city' => 'Navi Mumbai',
        'state' => 'Maharashtra',
        'state_code' => '27',
        'credit_limit' => 500000.00,
        'is_active' => 1,
        'status' => 'ACTIVE',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "Created Customer for Company 2 (ID: $customerBId): Zenith Retail Ltd" . PHP_EOL;
} else {
    $customerBId = $customerB->id;
    echo "Customer for Company 2 already exists (ID: $customerBId)" . PHP_EOL;
}

// 6. Product for Company 2
$productB = DB::table('products')->where('company_id', 2)->first();
if (!$productB) {
    $productBId = DB::table('products')->insertGetId([
        'company_id' => 2,
        'name' => 'Enterprise Cloud Server Node',
        'sku' => 'SRV-CLOUD-01',
        'hsn_sac' => '998313',
        'unit' => 'Pcs',
        'sales_price' => 75000.00,
        'purchase_price' => 55000.00,
        'tax_rate' => 18.00,
        'current_stock' => 20,
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "Created Product for Company 2 (ID: $productBId): Enterprise Cloud Server Node" . PHP_EOL;
} else {
    $productBId = $productB->id;
    echo "Product for Company 2 already exists (ID: $productBId)" . PHP_EOL;
}

// 7. Invoice for Company 2
$invoiceB = DB::table('invoices')->where('company_id', 2)->first();
if (!$invoiceB) {
    $invoiceBId = DB::table('invoices')->insertGetId([
        'company_id' => 2,
        'branch_id' => 3,
        'customer_id' => $customerBId,
        'invoice_number' => 'INV-2026-INN-001',
        'invoice_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'place_of_supply' => '27',
        'sub_total' => 75000.00,
        'cgst_amount' => 6750.00,
        'sgst_amount' => 6750.00,
        'total_tax' => 13500.00,
        'grand_total' => 88500.00,
        'amount_paid' => 88500.00,
        'amount_due' => 0.00,
        'status' => 'PAID',
        'payment_status' => 'PAID',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    $itemData = [
        'company_id' => 2,
        'invoice_id' => $invoiceBId,
        'product_id' => $productBId,
        'item_name' => 'Enterprise Cloud Server Node',
        'hsn_sac' => '998313',
        'quantity' => 1,
        'unit' => 'Pcs',
        'unit_price' => 75000.00,
        'taxable_value' => 75000.00,
        'gst_rate' => 18.00,
        'cgst_rate' => 9.00,
        'cgst_amount' => 6750.00,
        'sgst_rate' => 9.00,
        'sgst_amount' => 6750.00,
        'total_amount' => 88500.00,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    DB::table('invoice_items')->insert($itemData);
    echo "Created Invoice for Company 2 (ID: $invoiceBId): INV-2026-INN-001" . PHP_EOL;
} else {
    $invoiceBId = $invoiceB->id;
    echo "Invoice for Company 2 already exists (ID: $invoiceBId)" . PHP_EOL;
}

// 8. Payment for Company 2
$paymentB = DB::table('payments')->where('company_id', 2)->first();
if (!$paymentB) {
    $paymentData = [
        'company_id' => 2,
        'branch_id' => 3,
        'party_type' => 'CUSTOMER',
        'party_id' => $customerBId,
        'payment_number' => 'PAY-2026-INN-001',
        'payment_date' => date('Y-m-d'),
        'amount' => 88500.00,
        'payment_mode' => 'BANK_TRANSFER',
        'payment_type' => 'RECEIPT',
        'reference_no' => 'NEFT-INN-998822',
        'financial_year' => '2026-2027',
        'status' => 'COMPLETED',
        'created_by' => $userBId,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $paymentBId = DB::table('payments')->insertGetId($paymentData);
    echo "Created Payment for Company 2 (ID: $paymentBId)" . PHP_EOL;
} else {
    echo "Payment for Company 2 already exists (ID: {$paymentB->id})" . PHP_EOL;
}

echo "=== Seeding Complete ===" . PHP_EOL;
