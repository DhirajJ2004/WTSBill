<?php

if (!function_exists('enum_exists')) {
    function enum_exists(string $enum, bool $autoload = true): bool {
        return false;
    }
}
if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool {
        if ($array === [] || $array === array_values($array)) return true;
        $nextKey = 0;
        foreach ($array as $k => $_) {
            if ($k !== $nextKey++) return false;
        }
        return true;
    }
}

require_once __DIR__ . '/../backend/vendor/autoload.php';

// Register autoloader for app/ (new MVC classes) and backend/app
spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\' => [
            __DIR__ . '/../app/',
            __DIR__ . '/../backend/app/'
        ]
    ];
    foreach ($prefixes as $prefix => $baseDirs) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        foreach ($baseDirs as $baseDir) {
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// Load DB
require_once __DIR__ . '/../views/db_helper.php';
\App\Database\Database::init();

use App\Services\PartyService;
use App\Repositories\PartyRepository;
use App\Validators\PartyValidator;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Company;
use App\Models\User;

echo "======================================================\n";
echo "       WTSBill ERP - Parties Module Verification      \n";
echo "======================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$description}\n";
        $passed++;
    } else {
        echo " [FAIL] {$description}\n";
        $failed++;
    }
}

// 1. Setup contexts
$company1 = Company::first();
if (!$company1) {
    echo "Fatal: No company found in DB.\n";
    exit(1);
}
$company2 = Company::where('id', '!=', $company1->id)->first();
if (!$company2) {
    // Create dummy company 2 for IDOR test
    $company2 = Company::create([
        'name' => 'Secondary Company Ltd',
        'email' => 'sec@example.com',
        'is_active' => 1
    ]);
}

$user = User::first();
$userId = $user ? $user->id : 1;

$partyService = new PartyService();
$partyRepo = new PartyRepository();

echo "--- 1. Customer CRUD & Validations ---\n";

// Test 1.1: Validation failure on invalid GSTIN
$invalidGstData = [
    'name' => 'Acme Corp',
    'gstin' => 'INVALID_GST_123',
    'opening_balance' => 1000
];
$valErrors = PartyValidator::validateCustomer($invalidGstData, $company1->id);
assertTest("Validator rejects invalid GSTIN format", isset($valErrors['gstin']));

// Test 1.2: Customer Creation
$uniqueGst1 = '27AAPFU' . rand(1000, 9999) . 'F1ZV';
$custData = [
    'name' => 'Test Customer Alpha',
    'email' => 'alpha@testcust.com',
    'phone' => '9876543210',
    'gstin' => $uniqueGst1,
    'tax_number' => substr($uniqueGst1, 2, 10),
    'opening_balance' => 5000.50,
    'credit_limit' => 25000.00,
    'billing_address' => '123 Test Street, Mumbai',
    'city' => 'Mumbai',
    'billing_city' => 'Mumbai',
    'billing_state' => 'Maharashtra',
    'billing_pincode' => '400001',
    'billing_country' => 'India',
    'is_active' => 1
];

$res1 = $partyService->createCustomer($custData, $company1->id, null, 'TestAdmin');
if (!$res1['success']) {
    echo "Create Customer Error: " . json_encode($res1) . "\n";
}
assertTest("Create Customer succeeds with valid data", $res1['success'] === true && !empty($res1['customer_id']));
$customerId1 = $res1['customer_id'] ?? null;

// Test 1.3: Duplicate GSTIN within same company rejected
$dupCustData = [
    'name' => 'Duplicate GST Customer',
    'gstin' => $uniqueGst1,
    'opening_balance' => 0
];
$valDup = PartyValidator::validateCustomer($dupCustData, $company1->id);
assertTest("Reject duplicate GSTIN within same company", isset($valDup['gstin']));

// Test 1.4: Update Customer
$updateData = [
    'name' => 'Test Customer Alpha (Updated)',
    'credit_limit' => 30000.00,
    'city' => 'Pune',
    'billing_city' => 'Pune'
];
$resUpdate = $partyService->updateCustomer($customerId1, $updateData, $company1->id, 'TestAdmin');
assertTest("Update Customer details successfully", $resUpdate['success'] === true);

$fetchedCust = $partyService->getCustomer($customerId1, $company1->id);
assertTest("Customer update persisted in DB", $fetchedCust && $fetchedCust['name'] === 'Test Customer Alpha (Updated)' && (float)$fetchedCust['credit_limit'] === 30000.00);

// Test 1.5: IDOR Protection on Customer
$idorFetch = $partyService->getCustomer($customerId1, $company2->id);
assertTest("IDOR Protection: Cannot access Company 1 customer from Company 2 context", $idorFetch === null);

$idorUpdate = $partyService->updateCustomer($customerId1, ['name' => 'Hacked Name'], $company2->id, $userId);
assertTest("IDOR Protection: Cannot update Company 1 customer from Company 2 context", $idorUpdate['success'] === false);


echo "\n--- 2. Supplier CRUD & Validations ---\n";

// Test 2.1: Supplier Creation
$uniqueGstSupp = '27AABCA' . rand(1000, 9999) . 'A1Z5';
$suppData = [
    'name' => 'Apex Supplies Pvt Ltd',
    'email' => 'sales@apexsupplies.com',
    'phone' => '9123456780',
    'gstin' => $uniqueGstSupp,
    'tax_number' => substr($uniqueGstSupp, 2, 10),
    'opening_balance' => 12000.00,
    'billing_address' => 'Plot 45, Industrial Area',
    'city' => 'Nagpur',
    'billing_city' => 'Nagpur',
    'billing_state' => 'Maharashtra',
    'billing_pincode' => '440001',
    'is_active' => 1
];

$resSupp = $partyService->createSupplier($suppData, $company1->id, null, 'TestAdmin');
assertTest("Create Supplier succeeds with valid data", $resSupp['success'] === true && !empty($resSupp['supplier_id']));
$supplierId1 = $resSupp['supplier_id'] ?? null;

// Test 2.2: Supplier Update
$suppUpdate = [
    'name' => 'Apex Supplies Global',
    'city' => 'Thane',
    'billing_city' => 'Thane'
];
$resSuppUpdate = $partyService->updateSupplier($supplierId1, $suppUpdate, $company1->id, 'TestAdmin');
assertTest("Update Supplier details successfully", $resSuppUpdate['success'] === true);

$fetchedSupp = $partyService->getSupplier($supplierId1, $company1->id);
assertTest("Supplier update persisted in DB", $fetchedSupp && $fetchedSupp['name'] === 'Apex Supplies Global');

// Test 2.3: IDOR Protection on Supplier
$idorSupp = $partyService->getSupplier($supplierId1, $company2->id);
assertTest("IDOR Protection: Cannot access Company 1 supplier from Company 2 context", $idorSupp === null);


echo "\n--- 3. Search, Filter & Pagination ---\n";

// Search Customer
$searchCust = $partyRepo->getCustomers($company1->id, ['search' => 'Alpha', 'per_page' => 10]);
assertTest("Search customer by query 'Alpha' returns match", $searchCust['total'] >= 1 && str_contains($searchCust['data'][0]['name'], 'Alpha'));

// Search by Phone / GST
$searchGst = $partyRepo->getCustomers($company1->id, ['search' => $uniqueGst1]);
assertTest("Search customer by GSTIN returns exact match", $searchGst['total'] >= 1 && $searchGst['data'][0]['gstin'] === $uniqueGst1);

// Filter by City
$filterCity = $partyRepo->getCustomers($company1->id, ['search' => 'Pune']);
assertTest("Filter/Search customer by City 'Pune' returns match", $filterCity['total'] >= 1 && $filterCity['data'][0]['city'] === 'Pune');

// Search Supplier
$searchSupp = $partyRepo->getSuppliers($company1->id, ['search' => 'Apex']);
assertTest("Search supplier by query 'Apex' returns match", $searchSupp['total'] >= 1 && str_contains($searchSupp['data'][0]['name'], 'Apex'));

// Pagination check
$paginated = $partyRepo->getCustomers($company1->id, ['page' => 1, 'per_page' => 1]);
assertTest("Pagination structure contains per_page, current_page, and total", 
    count($paginated['data']) <= 1 && $paginated['per_page'] === 1 && isset($paginated['total'])
);


echo "\n--- 4. Ledger & Transaction History ---\n";

$ledgerCust = $partyService->getCustomerLedger($customerId1, $company1->id);
assertTest("Customer ledger includes opening balance entry", 
    !empty($ledgerCust) && $ledgerCust[0]['type'] === 'OPENING_BALANCE' && (float)$ledgerCust[0]['debit'] === 5000.50
);

$ledgerSupp = $partyService->getSupplierLedger($supplierId1, $company1->id);
assertTest("Supplier ledger includes opening balance entry", 
    !empty($ledgerSupp) && $ledgerSupp[0]['type'] === 'OPENING_BALANCE' && (float)$ledgerSupp[0]['credit'] === 12000.00
);


echo "\n--- 5. Delete & Reference Integrity Checks ---\n";

// Delete newly created supplier
$delSuppRes = $partyService->deleteSupplier($supplierId1, $company1->id, 'TestAdmin');
assertTest("Delete unused supplier succeeds", $delSuppRes['success'] === true);

$checkSuppDeleted = $partyService->getSupplier($supplierId1, $company1->id);
assertTest("Deleted supplier is no longer returned in active queries", $checkSuppDeleted === null);

// Delete customer
$delCustRes = $partyService->deleteCustomer($customerId1, $company1->id, 'TestAdmin');
assertTest("Delete unused customer succeeds", $delCustRes['success'] === true);

$checkCustDeleted = $partyService->getCustomer($customerId1, $company1->id);
assertTest("Deleted customer is no longer returned in active queries", $checkCustDeleted === null);

// Test 5.3: Reference integrity protection on Customer with active invoices
$custWithInvoice = $partyService->createCustomer([
    'name' => 'Customer With Invoice',
    'email' => 'invoice.party@test.com',
    'phone' => '9888877777',
    'is_active' => 1
], $company1->id, null, 'TestAdmin');
$custWithInvoiceId = $custWithInvoice['customer_id'];

// Insert mock invoice for this customer
\Illuminate\Database\Capsule\Manager::table('invoices')->insert([
    'company_id' => $company1->id,
    'branch_id' => 1,
    'customer_id' => $custWithInvoiceId,
    'invoice_number' => 'INV-TEST-REF-' . rand(1000, 9999),
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d'),
    'grand_total' => 1000.00,
    'status' => 'DRAFT',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
]);

$delBlocked = $partyService->deleteCustomer($custWithInvoiceId, $company1->id, 'TestAdmin');
assertTest("Reference protection prevents deleting customer with active invoices", $delBlocked['success'] === false && str_contains($delBlocked['message'], 'active invoice'));

// Clean up mock invoice and re-test deletion
\Illuminate\Database\Capsule\Manager::table('invoices')
    ->where('company_id', $company1->id)
    ->where('customer_id', $custWithInvoiceId)
    ->delete();

$delAllowed = $partyService->deleteCustomer($custWithInvoiceId, $company1->id, 'TestAdmin');
assertTest("Customer deletion succeeds once referencing invoice is cleared", $delAllowed['success'] === true);

echo "\n======================================================\n";
echo " Parties Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "======================================================\n";

exit($failed > 0 ? 1 : 0);
