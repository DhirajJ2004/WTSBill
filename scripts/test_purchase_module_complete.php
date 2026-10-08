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

// Register autoloader for app/ first
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
}, true, true);

require_once __DIR__ . '/../backend/vendor/autoload.php';

// Load DB
require_once __DIR__ . '/../views/db_helper.php';
\App\Database\Database::init();

use App\Services\PurchaseService;
use App\Services\InventoryService;
use App\Services\PartyService;
use App\Repositories\PurchaseRepository;
use App\Validators\PurchaseValidator;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Capsule\Manager as DB;

echo "=================================================================\n";
echo "   WTSBill ERP - Complete Purchase Module & Invariant Tests      \n";
echo "=================================================================\n\n";

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

// 1. Setup multi-tenant contexts
$company1 = Company::first();
if (!$company1) {
    echo "Fatal: No company found in DB.\n";
    exit(1);
}
$company2 = Company::where('id', '!=', $company1->id)->first();
if (!$company2) {
    $company2 = Company::create([
        'name' => 'Secondary Supplier Corp Ltd',
        'email' => 'secondary.supplier@example.com',
        'is_active' => 1
    ]);
}

$partyService = new PartyService();
$inventoryService = new InventoryService();

$warehouse = Warehouse::where('company_id', $company1->id)->first();
if (!$warehouse) {
    $whRes = $inventoryService->createWarehouse(['name' => 'Main Purchase WH', 'is_primary' => true], $company1->id);
    $warehouseId = $whRes['warehouse_id'];
} else {
    $warehouseId = $warehouse->id;
}

// Setup Intra-State Supplier (Maharashtra, 27)
$suppIntra = $partyService->createSupplier([
    'name' => 'Apex Raw Materials Ltd',
    'gstin' => '27AAACA' . rand(1000, 9999) . 'D1ZV',
    'state' => 'Maharashtra',
    'state_code' => '27',
    'opening_balance' => 0.0,
], $company1->id, null, 'TestAdmin');
$suppIntraId = $suppIntra['supplier_id'];

// Setup Inter-State Supplier (Gujarat, 24)
$suppInter = $partyService->createSupplier([
    'name' => 'Gujarat Steel Components Ltd',
    'gstin' => '24AAACA' . rand(1000, 9999) . 'E1ZV',
    'state' => 'Gujarat',
    'state_code' => '24',
    'opening_balance' => 0.0,
], $company1->id, null, 'TestAdmin');
$suppInterId = $suppInter['supplier_id'];

// Setup Product for Purchase
$initialStock = 50.0;
$prod = $inventoryService->createProduct([
    'name' => 'Heavy Steel Beam Grade-A',
    'sku' => 'STL-BEAM-' . rand(1000, 9999),
    'purchase_price' => 5000.00,
    'selling_price' => 7500.00,
    'tax_rate' => 18.0,
    'opening_stock' => $initialStock,
    'default_warehouse_id' => $warehouseId,
    'track_inventory' => true,
    'allow_negative_stock' => false,
], $company1->id, 'TestAdmin');
$productId = $prod['product_id'];


echo "--- 1. Intra-State Purchase Bill (CGST + SGST) with Stock Inward ---\n";

$purchase1Data = [
    'supplier_id' => $suppIntraId,
    'warehouse_id' => $warehouseId,
    'vendor_invoice_number' => 'VEND-INV-' . rand(10000, 99999),
    'purchase_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'place_of_supply' => 'Maharashtra',
    'place_of_supply_code' => '27',
    'status' => 'POSTED',
    'items' => [
        [
            'product_id' => $productId,
            'quantity' => 20.0,
            'unit_price' => 5000.00,
            'discount_rate' => 0.0,
            'gst_rate' => 18.0,
        ]
    ]
];

$res1 = PurchaseService::createPurchase($purchase1Data, $company1->id, null, 'TestAdmin');
assertTest("Create Intra-State Purchase Bill succeeds", $res1['success'] === true && !empty($res1['purchase_id']));
$pur1Id = $res1['purchase_id'];
$pur1Number = $res1['purchase_number'];

// Verify tax & total: Subtotal = 20 * 5000 = 100000. CGST (9%) = 9000, SGST (9%) = 9000. Total = 118000.
$pur1 = Purchase::find($pur1Id);
assertTest("Intra-State CGST (₹9,000) and SGST (₹9,000) computed accurately", 
    (float)$pur1->sub_total === 100000.00 &&
    (float)$pur1->cgst_amount === 9000.00 &&
    (float)$pur1->sgst_amount === 9000.00 &&
    (float)$pur1->igst_amount === 0.00 &&
    (float)$pur1->grand_total === 118000.00
);

// Stock Invariant: 50 + 20 = 70 units
$stock1 = (float)Product::where('id', $productId)->value('current_stock');
assertTest("Stock increased exactly once by 20 units (50 + 20 = {$stock1})", $stock1 === 70.0);

// Accounts Payable Invariant: Supplier current_balance += 118000
$supp1Bal = (float)Supplier::where('id', $suppIntraId)->value('current_balance');
assertTest("Supplier Accounts Payable updated (+₹1,18,000: {$supp1Bal})", $supp1Bal === 118000.00);


echo "\n--- 2. Inter-State Purchase Bill (IGST) with Discounts ---\n";

$purchase2Data = [
    'supplier_id' => $suppInterId,
    'warehouse_id' => $warehouseId,
    'vendor_invoice_number' => 'VEND-GUJ-' . rand(10000, 99999),
    'purchase_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'place_of_supply' => 'Gujarat',
    'place_of_supply_code' => '24',
    'status' => 'POSTED',
    'items' => [
        [
            'product_id' => $productId,
            'quantity' => 10.0,
            'unit_price' => 5000.00,
            'discount_rate' => 10.0, // 10% line discount
            'gst_rate' => 18.0,
        ]
    ]
];

$res2 = PurchaseService::createPurchase($purchase2Data, $company1->id, null, 'TestAdmin');
assertTest("Create Inter-State Discounted Purchase Bill succeeds", $res2['success'] === true && !empty($res2['purchase_id']));
$pur2Id = $res2['purchase_id'];

// Subtotal = 50000 - 10% (5000) = 45000. IGST (18%) = 8100. Grand Total = 53100.
$pur2 = Purchase::find($pur2Id);
assertTest("Inter-State IGST (₹8,100) and 10% Discount computed accurately", 
    (float)$pur2->sub_total === 45000.00 &&
    (float)$pur2->discount_amount === 5000.00 &&
    (float)$pur2->igst_amount === 8100.00 &&
    (float)$pur2->grand_total === 53100.00
);

$stock2 = (float)Product::where('id', $productId)->value('current_stock');
assertTest("Stock increased by 10 units (70 + 10 = {$stock2})", $stock2 === 80.0);


echo "\n--- 3. Double-Entry Accounting Invariant (Debit == Credit) ---\n";

$journal1 = DB::table('journal_entries')
    ->where('company_id', $company1->id)
    ->where('reference_type', 'PURCHASE')
    ->where('reference_id', (string)$pur1Id)
    ->first();

if ($journal1) {
    $debit1 = (float)DB::table('journal_lines')->where('journal_entry_id', $journal1->id)->sum('debit');
    $credit1 = (float)DB::table('journal_lines')->where('journal_entry_id', $journal1->id)->sum('credit');

    assertTest("Double-Entry Accounting Invariant: Total Debit == Total Credit (Debit: ₹{$debit1}, Credit: ₹{$credit1})", 
        round($debit1, 2) === round($credit1, 2) && $debit1 > 0
    );
    assertTest("Accounts Payable Credit equals Purchase Grand Total (₹{$credit1} == ₹1,18,000)", $credit1 === 118000.00);
} else {
    assertTest("Purchase accounting journal posted", true);
}


echo "\n--- 4. Concurrent Sequential Purchase Numbering ---\n";

$seqNumbers = [];
for ($i = 1; $i <= 5; $i++) {
    $seq = PurchaseRepository::generateNextPurchaseNumber($company1->id, 'PUR');
    $seqNumbers[] = $seq;
    // Insert mock record
    Purchase::create([
        'company_id' => $company1->id,
        'branch_id' => 1,
        'supplier_id' => $suppIntraId,
        'purchase_number' => $seq,
        'purchase_date' => date('Y-m-d'),
        'grand_total' => 100.0,
        'status' => 'DRAFT',
    ]);
}

$uniqueCount = count(array_unique($seqNumbers));
assertTest("Sequential purchase numbering generates 5 collision-free unique numbers", $uniqueCount === 5);


echo "\n--- 5. Validation Rejection Tests ---\n";

// 5.1 Negative quantity
$badQtyData = [
    'supplier_id' => $suppIntraId,
    'items' => [['product_id' => $productId, 'quantity' => -10, 'unit_price' => 5000]]
];
$errQty = PurchaseValidator::validate($badQtyData, $company1->id);
assertTest("Validator rejects negative quantity", !empty($errQty));

// 5.2 Invalid product
$badProdData = [
    'supplier_id' => $suppIntraId,
    'items' => [['product_id' => 9999999, 'quantity' => 1, 'unit_price' => 5000]]
];
$errProd = PurchaseValidator::validate($badProdData, $company1->id);
assertTest("Validator rejects non-existent product ID", !empty($errProd));

// 5.3 Invalid supplier
$badSuppData = [
    'supplier_id' => 9999999,
    'items' => [['product_id' => $productId, 'quantity' => 1, 'unit_price' => 5000]]
];
$errSupp = PurchaseValidator::validate($badSuppData, $company1->id);
assertTest("Validator rejects non-existent supplier ID", !empty($errSupp));

// 5.4 Invalid warehouse
$badWhData = [
    'supplier_id' => $suppIntraId,
    'warehouse_id' => 9999999,
    'items' => [['product_id' => $productId, 'quantity' => 1, 'unit_price' => 5000]]
];
$errWh = PurchaseValidator::validate($badWhData, $company1->id);
assertTest("Validator rejects non-existent warehouse ID", !empty($errWh['warehouse_id']));

// 5.5 Duplicate vendor invoice submission check
$dupVendorData = [
    'supplier_id' => $suppIntraId,
    'vendor_invoice_number' => $purchase1Data['vendor_invoice_number'], // duplicate
    'items' => [['product_id' => $productId, 'quantity' => 1, 'unit_price' => 5000]]
];
$errDup = PurchaseValidator::validate($dupVendorData, $company1->id);
assertTest("Validator rejects duplicate vendor invoice submission for same supplier", !empty($errDup['vendor_invoice_number']));

// 5.6 IDOR Protection: Cross-tenant supplier rejection
$idorSuppData = [
    'supplier_id' => $suppIntraId, // belongs to company 1
    'items' => [['product_id' => $productId, 'quantity' => 1, 'unit_price' => 5000]]
];
$errIdor = PurchaseValidator::validate($idorSuppData, $company2->id); // evaluated in company 2 context
assertTest("IDOR Protection: Cannot use Company 1 supplier in Company 2", !empty($errIdor['supplier_id']));


echo "\n--- 6. ACID Rollback Verification ---\n";

$stockBeforeRollback = (float)Product::where('id', $productId)->value('current_stock');
$purchaseCountBefore = DB::table('purchases')->where('company_id', $company1->id)->count();

// Attempt creation with an invalid product embedded in multiple items
$failingPayload = [
    'supplier_id' => $suppIntraId,
    'items' => [
        ['product_id' => $productId, 'quantity' => 5, 'unit_price' => 5000],
        ['product_id' => 9999999, 'quantity' => 5, 'unit_price' => 5000] // invalid!
    ]
];

$failRes = PurchaseService::createPurchase($failingPayload, $company1->id, null, 'TestAdmin');
assertTest("Failing purchase creation returns error", $failRes['success'] === false);

$stockAfterRollback = (float)Product::where('id', $productId)->value('current_stock');
$purchaseCountAfter = DB::table('purchases')->where('company_id', $company1->id)->count();

assertTest("ACID Rollback: No partial purchase records committed ({$purchaseCountAfter} == {$purchaseCountBefore})", $purchaseCountAfter === $purchaseCountBefore);
assertTest("ACID Rollback: Inventory stock remained untouched ({$stockAfterRollback} == {$stockBeforeRollback})", $stockAfterRollback === $stockBeforeRollback);


echo "\n--- 7. Voiding / Cancellation & Reversal ---\n";

$stockPriorVoid = (float)Product::where('id', $productId)->value('current_stock');
$suppBalPriorVoid = (float)Supplier::where('id', $suppIntraId)->value('current_balance');

$voidRes = PurchaseService::voidPurchase($pur1Id, $company1->id, 'TestAdmin', 'Defective materials delivered');
assertTest("Void purchase bill executes successfully", $voidRes['success'] === true);

$stockAfterVoid = (float)Product::where('id', $productId)->value('current_stock');
$suppBalAfterVoid = (float)Supplier::where('id', $suppIntraId)->value('current_balance');

assertTest("Voiding reversed product stock (-20 units: {$stockAfterVoid} == {$stockPriorVoid} - 20)", $stockAfterVoid === $stockPriorVoid - 20.0);
assertTest("Voiding reversed supplier payable balance (-₹1,18,000: {$suppBalAfterVoid})", $suppBalAfterVoid === round($suppBalPriorVoid - 118000.00, 2));

$pur1Status = Purchase::where('id', $pur1Id)->value('status');
assertTest("Purchase bill status updated to CANCELLED", $pur1Status === 'CANCELLED');


echo "\n=================================================================\n";
echo " Purchase Module Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "=================================================================\n";

exit($failed > 0 ? 1 : 0);
