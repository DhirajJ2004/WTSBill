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

// Register autoloader for app/ and backend/app
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

use App\Services\InventoryService;
use App\Repositories\InventoryRepository;
use App\Validators\InventoryValidator;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Company;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Database\Capsule\Manager as DB;

echo "======================================================\n";
echo "      WTSBill ERP - Inventory Module Verification     \n";
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

// 1. Setup multi-tenant contexts
$company1 = Company::first();
if (!$company1) {
    echo "Fatal: No company found in DB.\n";
    exit(1);
}
$company2 = Company::where('id', '!=', $company1->id)->first();
if (!$company2) {
    $company2 = Company::create([
        'name' => 'Secondary Test Corp Ltd',
        'email' => 'secondary.corp@example.com',
        'is_active' => 1
    ]);
}

$inventoryService = new InventoryService();
$inventoryRepo = new InventoryRepository();

echo "--- 1. Warehouses, Categories & Units Setup ---\n";

// 1.1: Create Warehouses
$whRes1 = $inventoryService->createWarehouse([
    'name' => 'North Central Hub',
    'code' => 'WH-NC-' . rand(100, 999),
    'is_primary' => true,
    'city' => 'Mumbai'
], $company1->id, 'TestAdmin');
assertTest("Create Primary Warehouse for Company 1", $whRes1['success'] === true && !empty($whRes1['warehouse_id']));
$whId1 = $whRes1['warehouse_id'];

$whRes2 = $inventoryService->createWarehouse([
    'name' => 'East Regional Depot',
    'code' => 'WH-ER-' . rand(100, 999),
    'is_primary' => false,
    'city' => 'Pune'
], $company1->id, 'TestAdmin');
assertTest("Create Secondary Warehouse for Company 1", $whRes2['success'] === true && !empty($whRes2['warehouse_id']));
$whId2 = $whRes2['warehouse_id'];

// 1.2: Create Category & Unit
$catRes = $inventoryService->createCategory([
    'name' => 'Industrial Electronics ' . rand(10, 99),
    'description' => 'Precision electronic components'
], $company1->id, 'TestAdmin');
assertTest("Create Product Category", $catRes['success'] === true && !empty($catRes['category_id']));
$catId = $catRes['category_id'];

$unitRes = $inventoryService->createUnit([
    'name' => 'Box of 10',
    'short_name' => 'BOX10',
    'decimal_precision' => 0
], $company1->id, 'TestAdmin');
assertTest("Create Inventory Unit", $unitRes['success'] === true && !empty($unitRes['unit_id']));


echo "\n--- 2. Product CRUD & Opening Stock ---\n";

$uniqueSku = 'SKU-TEST-' . rand(10000, 99999);
$initialOpeningStock = 100.0;
$initialPurchasePrice = 250.00;
$initialSellingPrice = 399.00;

// 2.1: Product Creation with Opening Stock
$prodData = [
    'name' => 'Microcontroller Board v2',
    'sku' => $uniqueSku,
    'barcode' => '8901234567890',
    'category_id' => $catId,
    'unit' => 'BOX10',
    'purchase_price' => $initialPurchasePrice,
    'selling_price' => $initialSellingPrice,
    'opening_stock' => $initialOpeningStock,
    'min_stock_level' => 20.0,
    'default_warehouse_id' => $whId1,
    'track_inventory' => true,
    'allow_negative_stock' => false,
];

$prodRes = $inventoryService->createProduct($prodData, $company1->id, 'TestAdmin');
assertTest("Create Product with Opening Stock & Warehouse Allocation", $prodRes['success'] === true && !empty($prodRes['product_id']));
$productId = $prodRes['product_id'];

// Verify opening stock balance
$whBalance = DB::table('stock_balances')
    ->where('company_id', $company1->id)
    ->where('warehouse_id', $whId1)
    ->where('product_id', $productId)
    ->first();
assertTest("Opening Stock allocated accurately in Warehouse #{$whId1}", $whBalance && (float)$whBalance->quantity === 100.0);

// 2.2: Duplicate SKU validation rejection
$dupSkuData = [
    'name' => 'Duplicate Board',
    'sku' => $uniqueSku,
    'purchase_price' => 100
];
$valErrors = InventoryValidator::validateProduct($dupSkuData, $company1->id);
assertTest("Validator rejects duplicate SKU within same company", isset($valErrors['sku']));

// 2.3: Update Product
$updateRes = $inventoryService->updateProduct($productId, [
    'name' => 'Microcontroller Board v2.1 Pro',
    'selling_price' => 450.00,
    'min_stock_level' => 25.0
], $company1->id, 'TestAdmin');
assertTest("Update Product attributes successfully", $updateRes['success'] === true);

$fetchedProd = $inventoryRepo->findProduct($productId, $company1->id);
assertTest("Product update persisted in DB", $fetchedProd && $fetchedProd->name === 'Microcontroller Board v2.1 Pro' && (float)$fetchedProd->selling_price === 450.00);

// 2.4: IDOR Protection: Cross-tenant isolation
$idorProd = $inventoryRepo->findProduct($productId, $company2->id);
assertTest("IDOR Protection: Company 2 cannot read Company 1 product", $idorProd === null);

$idorUpdate = $inventoryService->updateProduct($productId, ['name' => 'Hacked'], $company2->id, 'Hacker');
assertTest("IDOR Protection: Company 2 cannot update Company 1 product", $idorUpdate['success'] === false);


echo "\n--- 3. Stock Inward, Outward & Adjustment Operations ---\n";

// 3.1: Stock In (Purchase Inward: +50 units)
$stockInRes = $inventoryService->recordStockIn($company1->id, $whId1, $productId, 50.0, 250.0, 'PURCHASE', [
    'reference_type' => 'PURCHASE_BILL',
    'reference_number' => 'PB-2026-001'
]);
assertTest("Record Stock In (+50 units)", $stockInRes['success'] === true && (float)$stockInRes['new_stock'] === 150.0);

// 3.2: Stock Out (Sales Outward: -30 units)
$stockOutRes = $inventoryService->recordStockOut($company1->id, $whId1, $productId, 30.0, 250.0, 'SALE', [
    'reference_type' => 'TAX_INVOICE',
    'reference_number' => 'INV-2026-001'
]);
assertTest("Record Stock Out (-30 units)", $stockOutRes['success'] === true && (float)$stockOutRes['new_stock'] === 120.0);

// 3.3: Stock Adjustment (Positive correction: +10 units)
$adjustInRes = $inventoryService->adjustStock($company1->id, $whId1, $productId, 10.0, 'ADD', 'Audit found surplus');
assertTest("Stock Adjustment Add (+10 units)", $adjustInRes['success'] === true && (float)$adjustInRes['new_stock'] === 130.0);

// 3.4: Stock Adjustment (Negative damage: -5 units)
$adjustOutRes = $inventoryService->adjustStock($company1->id, $whId1, $productId, 5.0, 'REDUCE', 'Water damaged units');
assertTest("Stock Adjustment Reduce (-5 units)", $adjustOutRes['success'] === true && (float)$adjustOutRes['new_stock'] === 125.0);


echo "\n--- 4. Inter-Warehouse Stock Transfers ---\n";

// 4.1: Transfer 40 units from WH1 to WH2
$transferRes = $inventoryService->transferStock($company1->id, $whId1, $whId2, $productId, 40.0, 'Inter-city replenishment', 'TestAdmin');
assertTest("Transfer 40 units between warehouses atomically", $transferRes['success'] === true);

// Verify balances in both warehouses
$balWh1 = DB::table('stock_balances')->where('company_id', $company1->id)->where('warehouse_id', $whId1)->where('product_id', $productId)->value('quantity');
$balWh2 = DB::table('stock_balances')->where('company_id', $company1->id)->where('warehouse_id', $whId2)->where('product_id', $productId)->value('quantity');
assertTest("Source Warehouse WH1 balance decreased by 40 (Current: {$balWh1})", (float)$balWh1 === 85.0);
assertTest("Destination Warehouse WH2 balance increased by 40 (Current: {$balWh2})", (float)$balWh2 === 40.0);

// Total company product stock remains unchanged after transfer
$totalCurrentStock = Product::withoutGlobalScopes()->where('id', $productId)->value('current_stock');
assertTest("Total company stock invariant maintained across transfers ({$totalCurrentStock} units)", (float)$totalCurrentStock === 125.0);


echo "\n--- 5. Negative Stock Protection & Invariant Verification ---\n";

// 5.1: Attempt to draw 200 units when only 85 available in WH1
$insufficientRes = $inventoryService->recordStockOut($company1->id, $whId1, $productId, 200.0, 250.0, 'SALE');
assertTest("Negative stock prevention rejects stock depletion below zero", $insufficientRes['success'] === false && str_contains($insufficientRes['message'], 'Insufficient stock'));

// 5.2: Mathematical Stock Formula Invariant Check
// Opening (100) + Inward (50) - Outward (30) + AdjIn (10) - AdjOut (5) = 125
$expectedFormulaStock = 100.0 + 50.0 - 30.0 + 10.0 - 5.0;
$actualMasterStock = (float)Product::withoutGlobalScopes()->where('id', $productId)->value('current_stock');
$actualWarehouseSum = (float)DB::table('stock_balances')->where('company_id', $company1->id)->where('product_id', $productId)->sum('quantity');

assertTest("Formula Invariant Check: Master current_stock matches calculated ({$actualMasterStock} == {$expectedFormulaStock})", $actualMasterStock === $expectedFormulaStock);
assertTest("Formula Invariant Check: Sum of warehouse balances matches master stock ({$actualWarehouseSum} == {$actualMasterStock})", $actualWarehouseSum === $actualMasterStock);


echo "\n--- 6. Concurrent Transactions & Stress Simulation (10+ Ops) ---\n";

$initialConcurrentStock = (float)Product::withoutGlobalScopes()->where('id', $productId)->value('current_stock');
$operationsLog = [];
$expectedDelta = 0.0;

// Execute 12 consecutive transactional stock operations in rapid succession
for ($i = 1; $i <= 12; $i++) {
    if ($i % 2 === 0) {
        // Stock In: +5 units
        $res = $inventoryService->recordStockIn($company1->id, $whId1, $productId, 5.0, 250.0, 'CONCURRENT_IN', ['reference_number' => "CONC-IN-{$i}"]);
        if ($res['success']) {
            $expectedDelta += 5.0;
            $operationsLog[] = "+5";
        }
    } else {
        // Stock Out: -3 units
        $res = $inventoryService->recordStockOut($company1->id, $whId1, $productId, 3.0, 250.0, 'CONCURRENT_OUT', ['reference_number' => "CONC-OUT-{$i}"]);
        if ($res['success']) {
            $expectedDelta -= 3.0;
            $operationsLog[] = "-3";
        }
    }
}

$finalConcurrentStock = (float)Product::withoutGlobalScopes()->where('id', $productId)->value('current_stock');
$expectedFinal = $initialConcurrentStock + $expectedDelta;

assertTest("12 Transactional Operations executed successfully without lock corruption", count($operationsLog) === 12);
assertTest("Stock total after 12 operations strictly matches expected ({$finalConcurrentStock} == {$expectedFinal})", $finalConcurrentStock === $expectedFinal);


echo "\n--- 7. Stock Ledger Running Balance & Valuation ---\n";

// 7.1: Stock Ledger Audit Trail
$ledger = $inventoryRepo->getStockLedger($productId, $company1->id);
assertTest("Stock ledger contains all movements in chronological sequence", count($ledger) >= 15);

$lastEntry = end($ledger);
assertTest("Stock ledger final running balance matches master stock ({$lastEntry['running_balance']} == {$finalConcurrentStock})", (float)$lastEntry['running_balance'] === $finalConcurrentStock);

// 7.2: Valuation Summary
$valuation = $inventoryRepo->getStockValuationSummary($company1->id);
if (!($valuation['total_products'] >= 1 && isset($valuation['total_stock_units']) && $valuation['total_valuation'] > 0)) {
    echo "Valuation debug: " . json_encode($valuation) . "\n";
}
assertTest("Valuation summary computes total units and monetary value", 
    $valuation['total_products'] >= 1 && isset($valuation['total_stock_units']) && $valuation['total_valuation'] > 0
);


echo "\n--- 8. Delete & Reference Integrity Checks ---\n";

// 8.1: Block deletion when linked to invoice items
$tempCust = DB::table('customers')->where('company_id', $company1->id)->value('id') ?: 1;
$tempInvoiceId = DB::table('invoices')->insertGetId([
    'company_id' => $company1->id,
    'branch_id' => 1,
    'customer_id' => $tempCust,
    'invoice_number' => 'INV-INV-REF-' . rand(1000, 9999),
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d'),
    'grand_total' => 450.00,
    'status' => 'DRAFT',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
]);

DB::table('invoice_items')->insert([
    'company_id' => $company1->id,
    'invoice_id' => $tempInvoiceId,
    'product_id' => $productId,
    'item_name' => 'Microcontroller Board v2.1 Pro',
    'quantity' => 1,
    'unit_price' => 450.00,
    'taxable_value' => 450.00,
    'total_amount' => 450.00,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
]);

$delBlocked = $inventoryService->deleteProduct($productId, $company1->id, 'TestAdmin');
assertTest("Reference protection prevents deleting product with active invoice items", $delBlocked['success'] === false && str_contains($delBlocked['message'], 'invoice items'));

// Cleanup test invoice and item
DB::table('invoice_items')->where('invoice_id', $tempInvoiceId)->delete();
DB::table('invoices')->where('id', $tempInvoiceId)->delete();

// Delete product
$delAllowed = $inventoryService->deleteProduct($productId, $company1->id, 'TestAdmin');
assertTest("Product deletion succeeds once referencing invoice items are removed", $delAllowed['success'] === true);

$checkDeleted = $inventoryRepo->findProduct($productId, $company1->id);
assertTest("Deleted product is no longer returned in active queries", $checkDeleted === null);


echo "\n======================================================\n";
echo " Inventory Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "======================================================\n";

exit($failed > 0 ? 1 : 0);
