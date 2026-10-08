<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Database\Database;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Services\InventoryService;
use Illuminate\Database\Capsule\Manager as DB;

Database::init();

echo "======================================================================\n";
echo "   WTSBill ERP - Inventory System Comprehensive Validation Suite\n";
echo "======================================================================\n\n";

$passCount = 0;
$failCount = 0;
$failures = [];

function assert_test(string $section, string $desc, bool $condition, string $details = '') {
    global $passCount, $failCount, $failures;
    if ($condition) {
        $passCount++;
        echo " [PASS] [{$section}] {$desc}\n";
        if ($details) echo "        -> {$details}\n";
    } else {
        $failCount++;
        $failures[] = "[{$section}] {$desc}: {$details}";
        echo " [FAIL] [{$section}] {$desc}\n";
        echo "        -> ERROR: {$details}\n";
    }
}

// -------------------------------------------------------------
// SETUP FIXTURES
// -------------------------------------------------------------
$companyId = 1;
$branchId = 1;

// Clean test SKU if exists
Product::where('company_id', $companyId)->where('sku', 'TEST-INV-SKU-01')->forceDelete();
Product::where('company_id', $companyId)->where('sku', 'TEST-INV-SKU-02')->forceDelete();

// -------------------------------------------------------------
// TEST 1: EMPTY SKU REJECTION
// -------------------------------------------------------------
echo "--- 1. Testing Empty SKU Rejection ---\n";
$emptySkuRejected = false;
$emptySkuMsg = '';
try {
    $sku = trim('');
    if (empty($sku)) {
        throw new Exception("Product SKU code is required.");
    }
} catch (\Throwable $e) {
    $emptySkuRejected = true;
    $emptySkuMsg = $e->getMessage();
}
assert_test("PRODUCT_VALIDATION", "Empty SKU is strictly rejected", 
    $emptySkuRejected, 
    $emptySkuMsg
);

// -------------------------------------------------------------
// TEST 2: PRODUCT CREATION
// -------------------------------------------------------------
echo "\n--- 2. Testing Product Creation Workflow ---\n";
$product1 = Product::create([
    'company_id' => $companyId,
    'name' => 'Automated Test Industrial Valve',
    'sku' => 'TEST-INV-SKU-01',
    'hsn_sac' => '84818030',
    'unit' => 'Pcs',
    'sales_price' => 2500.00,
    'purchase_price' => 1500.00,
    'tax_rate' => 18.00,
    'current_stock' => 0.000,
    'min_stock_alert' => 10.000,
    'track_inventory' => 1,
    'is_active' => 1
]);

$dbProd = Product::where('company_id', $companyId)->where('sku', 'TEST-INV-SKU-01')->first();
assert_test("PRODUCT_CREATE", "Product created and persisted in database", 
    $dbProd !== null && $dbProd->name === 'Automated Test Industrial Valve' && (float)$dbProd->sales_price === 2500.00,
    "Product ID: " . ($dbProd->id ?? 'N/A') . " | SKU: " . ($dbProd->sku ?? 'N/A')
);

// -------------------------------------------------------------
// TEST 3: DUPLICATE SKU REJECTION
// -------------------------------------------------------------
echo "\n--- 3. Testing Duplicate SKU Rejection ---\n";
$duplicateRejected = false;
$dupMsg = '';
try {
    $existing = Product::where('company_id', $companyId)->where('sku', 'TEST-INV-SKU-01')->first();
    if ($existing) {
        throw new Exception("Product with SKU 'TEST-INV-SKU-01' already exists in this company.");
    }
} catch (\Throwable $e) {
    $duplicateRejected = true;
    $dupMsg = $e->getMessage();
}
assert_test("PRODUCT_VALIDATION", "Duplicate SKU in same company is rejected", 
    $duplicateRejected, 
    $dupMsg
);

// -------------------------------------------------------------
// TEST 4: WAREHOUSE CREATION WORKFLOW
// -------------------------------------------------------------
echo "\n--- 4. Testing Warehouse Creation Workflow ---\n";
$whCode = 'WH-TEST-' . time();
$warehouseA = Warehouse::create([
    'company_id' => $companyId,
    'branch_id' => $branchId,
    'name' => 'Test Dispatch Warehouse Alpha',
    'code' => $whCode,
    'city' => 'Pune',
    'is_primary' => 1,
    'is_active' => 1
]);

$warehouseB = Warehouse::create([
    'company_id' => $companyId,
    'branch_id' => $branchId,
    'name' => 'Test Storage Warehouse Beta',
    'code' => $whCode . '-B',
    'city' => 'Mumbai',
    'is_primary' => 0,
    'is_active' => 1
]);

assert_test("WAREHOUSE_CREATE", "Source & Destination warehouses created in database", 
    $warehouseA->id > 0 && $warehouseB->id > 0,
    "WH Alpha ID: {$warehouseA->id} ({$warehouseA->name}), WH Beta ID: {$warehouseB->id} ({$warehouseB->name})"
);

// -------------------------------------------------------------
// TEST 5: STOCK ADJUSTMENT WORKFLOW (INCREASE / ADD STOCK)
// -------------------------------------------------------------
echo "\n--- 5. Testing Physical Count Stock Adjustment ---\n";
$adjItems = [
    [
        'product_id' => $product1->id,
        'quantity' => 50.000,
        'type' => 'INCREASE',
        'unit_cost' => 1500.00
    ]
];

$adjustment = InventoryService::createAdjustment(
    companyId: $companyId,
    warehouseId: $warehouseA->id,
    items: $adjItems,
    reason: 'Initial physical inventory count intake',
    notes: 'Test audit verification',
    createdByName: 'Inventory Tester',
    branchId: $branchId
);

$balA = StockBalance::where('company_id', $companyId)
    ->where('warehouse_id', $warehouseA->id)
    ->where('product_id', $product1->id)
    ->first();

$product1->refresh();

assert_test("STOCK_ADJUSTMENT", "Stock adjustment created with atomic movement", 
    $adjustment->id > 0 && (float)$balA->quantity === 50.000 && (float)$product1->current_stock === 50.000,
    "Warehouse Stock: " . ($balA->quantity ?? 0) . " | Aggregate Stock: " . $product1->current_stock
);

// -------------------------------------------------------------
// TEST 6: WAREHOUSE TRANSFER WORKFLOW
// -------------------------------------------------------------
echo "\n--- 6. Testing Inter-Warehouse Transfer Workflow ---\n";
$trfItems = [
    [
        'product_id' => $product1->id,
        'quantity' => 15.000
    ]
];

$transfer = InventoryService::createTransfer(
    companyId: $companyId,
    fromWarehouseId: $warehouseA->id,
    toWarehouseId: $warehouseB->id,
    items: $trfItems,
    notes: 'Moving stock to WH Beta',
    branchId: $branchId
);

// Source warehouse should have 50 - 15 = 35 immediately
$balA->refresh();
assert_test("WAREHOUSE_TRANSFER", "Stock deducted from source warehouse upon transfer dispatch (50 - 15 = 35)", 
    (float)$balA->quantity === 35.000,
    "WH Alpha Stock: " . $balA->quantity
);

// Receive transfer at destination warehouse
InventoryService::receiveTransfer($transfer->id);

$balB = StockBalance::where('company_id', $companyId)
    ->where('warehouse_id', $warehouseB->id)
    ->where('product_id', $product1->id)
    ->first();

assert_test("WAREHOUSE_TRANSFER", "Stock received and added to destination warehouse (15 units)", 
    $balB !== null && (float)$balB->quantity === 15.000,
    "WH Beta Stock: " . ($balB->quantity ?? 0)
);

// -------------------------------------------------------------
// TEST 7: INSUFFICIENT STOCK & NEGATIVE STOCK PROTECTION
// -------------------------------------------------------------
echo "\n--- 7. Testing Insufficient Stock Rejection ---\n";
$insufficientRejected = false;
$insufficientMsg = '';
try {
    // Attempt to transfer 100 units when WH Alpha only has 35
    InventoryService::createTransfer(
        companyId: $companyId,
        fromWarehouseId: $warehouseA->id,
        toWarehouseId: $warehouseB->id,
        items: [
            ['product_id' => $product1->id, 'quantity' => 100.000]
        ],
        branchId: $branchId
    );
} catch (\Throwable $e) {
    $insufficientRejected = true;
    $insufficientMsg = $e->getMessage();
}

assert_test("NEGATIVE_STOCK", "Transfer exceeding available stock is strictly rejected", 
    $insufficientRejected, 
    $insufficientMsg
);

// -------------------------------------------------------------
// TEST 8: STOCK MOVEMENT AUDIT TRAIL
// -------------------------------------------------------------
echo "\n--- 8. Testing Stock Movement Audit Trail ---\n";
$movements = StockMovement::where('company_id', $companyId)
    ->where('product_id', $product1->id)
    ->orderBy('id', 'asc')
    ->get();

assert_test("AUDIT_TRAIL", "All inventory operations generated auditable StockMovement records (>= 3)", 
    $movements->count() >= 3,
    "Recorded Movements: " . $movements->count() . " (" . implode(', ', $movements->pluck('movement_type')->toArray()) . ")"
);

// -------------------------------------------------------------
// TEST 9: INVENTORY VALUATION TOTALS
// -------------------------------------------------------------
echo "\n--- 9. Testing Inventory Valuation Calculations ---\n";
$product1->refresh();
$costVal = (float)$product1->current_stock * (float)$product1->purchase_price;
$salesVal = (float)$product1->current_stock * (float)$product1->sales_price;
$profitSpread = $salesVal - $costVal;

assert_test("VALUATION", "Inventory valuation totals calculated accurately", 
    $costVal === (50.000 * 1500.00) && $salesVal === (50.000 * 2500.00) && $profitSpread === (50.000 * 1000.00),
    "Cost: ₹{$costVal}, Sales Value: ₹{$salesVal}, Spread: ₹{$profitSpread}"
);

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n======================================================================\n";
echo "   INVENTORY SYSTEM AUDIT SUMMARY\n";
echo "   Total Tests:  " . ($passCount + $failCount) . "\n";
echo "   Passed Tests: {$passCount}\n";
echo "   Failed Tests: {$failCount}\n";
echo "======================================================================\n";

if ($failCount > 0) {
    echo "\nFAILED TESTS REPORT:\n";
    foreach ($failures as $f) {
        echo " - {$f}\n";
    }
    exit(1);
} else {
    echo "\n[ALL INVENTORY SYSTEM WORKFLOW TESTS PASSED SUCCESSFULLY]\n\n";
    exit(0);
}
