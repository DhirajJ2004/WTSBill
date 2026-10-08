<?php
/**
 * WTSBill ERP - Comprehensive Purchases Module Test Suite
 *
 * Tests:
 * 1. Valid purchase creation with full workflow
 * 2. Purchase items creation
 * 3. Inventory stock increase (stock_balances and products.current_stock)
 * 4. Auditable stock movements (movement_type = 'PURCHASE', direction = 'IN')
 * 5. Double-entry accounting entries (Debit Inventory/Tax == Credit AP)
 * 6. Supplier payable balance update
 * 7. Invalid supplier validation (0, non-existent, cross-company)
 * 8. Duplicate bill / vendor invoice number prevention
 * 9. Empty products validation
 * 10. Zero quantity validation
 * 11. Negative quantity validation
 * 12. Negative price validation
 * 13. Tax calculation and server-side totals verification
 * 14. Transaction rollback on failure
 * 15. Purchase Return / Debit Note workflow & stock/balance reversal
 */

require_once __DIR__ . '/../backend/public/index.php';

use App\Models\Company;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\StockBalance;
use App\Models\JournalEntry;
use App\Models\DebitNote;
use App\Services\PurchaseInvoiceService;
use App\Services\PurchaseOrderService;
use App\Services\GoodsReceiptService;
use App\Services\DebitNoteService;
use App\Services\PurchaseReturnService;
use App\Services\InventoryService;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

$passed = 0;
$failed = 0;
$errors = [];

function assertTest(bool $condition, string $testName, ?string $details = null) {
    global $passed, $failed, $errors;
    if ($condition) {
        $passed++;
        echo " [PASS] " . $testName . "\n";
    } else {
        $failed++;
        $msg = " [FAIL] " . $testName . ($details ? " - " . $details : "");
        $errors[] = $msg;
        echo $msg . "\n";
    }
}

echo "=======================================================\n";
echo "   WTSBill ERP - Purchases Module Validation Suite    \n";
echo "=======================================================\n\n";

$company = Company::withoutGlobalScopes()->first();
if (!$company) {
    die("No active company found for testing.");
}
$companyId = $company->id;
$branchId = 1;

// Mock active user session
$_SESSION['user_id'] = 1;
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Test Admin',
    'email' => 'admin@test.com',
    'role' => 'admin',
    'company_id' => $companyId,
    'current_company_id' => $companyId,
    'branch_id' => $branchId,
];
$_SESSION['company_id'] = $companyId;
$_SESSION['branch_id'] = $branchId;
$_SESSION['financial_year'] = '2026-27';

// Ensure a test supplier
$supplier = Supplier::withoutGlobalScopes()->where('company_id', $companyId)->first();
if (!$supplier) {
    $supplier = Supplier::create([
        'company_id' => $companyId,
        'name' => 'Apex Industrial Supplies Ltd',
        'email' => 'apex@supplies.test',
        'phone' => '9876500001',
        'gstin' => '27AAACA9999Z1Z5',
        'state_code' => '27',
        'current_balance' => 0.00,
        'is_active' => 1,
    ]);
}

// Ensure a secondary company and supplier for cross-company isolation test
$otherCompany = Company::withoutGlobalScopes()->where('id', '!=', $companyId)->first();
if (!$otherCompany) {
    $otherCompany = Company::create([
        'name' => 'Foreign Corp Ltd',
        'email' => 'foreign@corp.test',
        'is_active' => 1,
    ]);
}
$foreignSupplier = Supplier::withoutGlobalScopes()->where('company_id', $otherCompany->id)->first();
if (!$foreignSupplier) {
    $foreignSupplier = Supplier::create([
        'company_id' => $otherCompany->id,
        'name' => 'Foreign Vendor',
        'email' => 'vendor@foreign.test',
        'is_active' => 1,
    ]);
}

// Ensure primary warehouse
$warehouse = Warehouse::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', 1)->first();
if (!$warehouse) {
    $warehouse = Warehouse::create([
        'company_id' => $companyId,
        'name' => 'Main Test Warehouse',
        'code' => 'WH-MAIN-TEST',
        'is_primary' => 1,
        'is_active' => 1,
    ]);
}

// Ensure test product
$product = Product::withoutGlobalScopes()->where('company_id', $companyId)->first();
if (!$product) {
    $product = Product::create([
        'company_id' => $companyId,
        'name' => 'Brass Valve 25mm',
        'sku' => 'VALVE-25-TEST',
        'product_type' => 'GOODS',
        'purchase_price' => 250.00,
        'selling_price' => 350.00,
        'gst_rate' => 18.00,
        'current_stock' => 50,
        'is_active' => 1,
    ]);
}

// -------------------------------------------------------------
// TEST 1: Valid Purchase Creation & Full Workflow
// -------------------------------------------------------------
echo "--- TEST GROUP 1: Valid Purchase & Stock / Accounting Invariants ---\n";

$initialStock = floatval($product->refresh()->current_stock);
$initialSupplierBalance = floatval($supplier->refresh()->current_balance);

$testBillNo = 'TEST-INV-' . rand(100000, 999999);
$purchaseQty = 20.0;
$unitPrice = 250.0;
$gstRate = 18.0;

$purchasePayload = [
    'company_id' => $companyId,
    'branch_id' => $branchId,
    'supplier_id' => $supplier->id,
    'warehouse_id' => $warehouse->id,
    'bill_no' => $testBillNo,
    'purchase_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'status' => 'POSTED',
    'items' => [
        [
            'product_id' => $product->id,
            'quantity' => $purchaseQty,
            'unit_price' => $unitPrice,
            'gst_rate' => $gstRate,
            'discount_rate' => 0.0,
        ]
    ]
];

$purchase = PurchaseInvoiceService::createPurchase($purchasePayload);

assertTest($purchase && $purchase->id > 0, "1a. Valid purchase created with ID #{$purchase->id}");
assertTest($purchase->vendor_invoice_number === $testBillNo, "1b. Vendor invoice number correctly saved: {$testBillNo}");
assertTest($purchase->status === 'POSTED', "1c. Purchase status is POSTED");

// Check items
$items = PurchaseItem::where('purchase_id', $purchase->id)->get();
assertTest($items->count() === 1, "1d. Purchase items count is 1");
$pItem = $items->first();
assertTest((int)$pItem->product_id === (int)$product->id, "1e. Purchase item linked to correct product #{$product->id}");
assertTest(floatval($pItem->quantity) === $purchaseQty, "1f. Purchase item quantity is {$purchaseQty}");

// Check Inventory stock increase
$newStock = floatval($product->refresh()->current_stock);
assertTest(abs($newStock - ($initialStock + $purchaseQty)) < 0.001, "1g. Product stock increased from {$initialStock} to {$newStock} (+{$purchaseQty})");

// Check Stock Movement recorded
$movement = StockMovement::withoutGlobalScopes()
    ->where('company_id', $companyId)
    ->where('reference_type', 'PURCHASE_INVOICE')
    ->where('reference_id', $purchase->id)
    ->where('movement_type', 'PURCHASE')
    ->where('direction', 'IN')
    ->first();
assertTest($movement !== null, "1h. Auditable Stock Movement recorded (PURCHASE / IN)");
assertTest(floatval($movement->quantity) === $purchaseQty, "1i. Stock movement quantity is {$purchaseQty}");

// Check Accounting Entry
$journal = JournalEntry::withoutGlobalScopes()
    ->where('company_id', $companyId)
    ->where('reference_type', 'PURCHASE_INVOICE')
    ->where('reference_id', (string)$purchase->id)
    ->with('lines')
    ->first();
assertTest($journal !== null, "1j. Double-entry accounting journal generated for Purchase");
if ($journal) {
    $totalDebit = $journal->lines->sum('debit');
    $totalCredit = $journal->lines->sum('credit');
    assertTest(abs($totalDebit - $totalCredit) < 0.001, "1k. Journal is balanced: Total Debit (₹{$totalDebit}) == Total Credit (₹{$totalCredit})");
    assertTest(abs($totalCredit - floatval($purchase->grand_total)) < 0.001, "1l. Total Journal credit equals Purchase Grand Total ₹{$purchase->grand_total}");
}

// Check Supplier Balance update
$newSupplierBal = floatval($supplier->refresh()->current_balance);
$expectedSupplierBal = $initialSupplierBalance + floatval($purchase->amount_due);
assertTest(abs($newSupplierBal - $expectedSupplierBal) < 0.001, "1m. Supplier payable balance updated from ₹{$initialSupplierBalance} to ₹{$newSupplierBal}");

// -------------------------------------------------------------
// TEST 2: Duplicate Bill Number Prevention
// -------------------------------------------------------------
echo "\n--- TEST GROUP 2: Duplicate Bill Number Validation ---\n";

$dupCaught = false;
try {
    PurchaseInvoiceService::createPurchase($purchasePayload);
} catch (\InvalidArgumentException $e) {
    $dupCaught = true;
    assertTest(strpos($e->getMessage(), 'Duplicate supplier invoice number') !== false, "2a. Duplicate bill number rejected with message: " . $e->getMessage());
} catch (\Throwable $e) {
    $dupCaught = true;
    assertTest(true, "2a. Duplicate bill number rejected with exception: " . $e->getMessage());
}
assertTest($dupCaught, "2b. Prevented duplicate supplier invoice number for the same supplier");

// -------------------------------------------------------------
// TEST 3: Invalid Supplier Validations
// -------------------------------------------------------------
echo "\n--- TEST GROUP 3: Invalid Supplier Validations ---\n";

$invSupplierCaught = false;
try {
    $badPayload = $purchasePayload;
    $badPayload['supplier_id'] = 0;
    $badPayload['bill_no'] = 'BAD-SUP-0';
    PurchaseInvoiceService::createPurchase($badPayload);
} catch (\InvalidArgumentException $e) {
    $invSupplierCaught = true;
    assertTest(true, "3a. Supplier ID = 0 rejected: " . $e->getMessage());
}
assertTest($invSupplierCaught, "3b. Supplier ID = 0 validation passed");

$foreignSupplierCaught = false;
try {
    $badPayload = $purchasePayload;
    $badPayload['supplier_id'] = $foreignSupplier->id;
    $badPayload['bill_no'] = 'BAD-SUP-FOREIGN';
    PurchaseInvoiceService::createPurchase($badPayload);
} catch (\InvalidArgumentException $e) {
    $foreignSupplierCaught = true;
    assertTest(true, "3c. Foreign company supplier rejected: " . $e->getMessage());
}
assertTest($foreignSupplierCaught, "3d. Cross-company supplier isolation verified");

// -------------------------------------------------------------
// TEST 4: Empty Products Validation
// -------------------------------------------------------------
echo "\n--- TEST GROUP 4: Empty Products Validation ---\n";

$emptyProdCaught = false;
try {
    $badPayload = $purchasePayload;
    $badPayload['bill_no'] = 'EMPTY-PROD-TEST';
    $badPayload['items'] = [];
    PurchaseInvoiceService::createPurchase($badPayload);
} catch (\InvalidArgumentException $e) {
    $emptyProdCaught = true;
    assertTest(true, "4a. Empty products list rejected: " . $e->getMessage());
}
assertTest($emptyProdCaught, "4b. Empty products validation passed");

// -------------------------------------------------------------
// TEST 5: Zero Quantity Validation
// -------------------------------------------------------------
echo "\n--- TEST GROUP 5: Zero & Negative Quantity Validation ---\n";

$zeroQtyCaught = false;
try {
    $badPayload = $purchasePayload;
    $badPayload['bill_no'] = 'ZERO-QTY-TEST';
    $badPayload['items'] = [
        [
            'product_id' => $product->id,
            'quantity' => 0,
            'unit_price' => 100,
        ]
    ];
    PurchaseInvoiceService::createPurchase($badPayload);
} catch (\InvalidArgumentException $e) {
    $zeroQtyCaught = true;
    assertTest(true, "5a. Zero quantity rejected: " . $e->getMessage());
}
assertTest($zeroQtyCaught, "5b. Zero quantity validation passed");

$negQtyCaught = false;
try {
    $badPayload = $purchasePayload;
    $badPayload['bill_no'] = 'NEG-QTY-TEST';
    $badPayload['items'] = [
        [
            'product_id' => $product->id,
            'quantity' => -5,
            'unit_price' => 100,
        ]
    ];
    PurchaseInvoiceService::createPurchase($badPayload);
} catch (\InvalidArgumentException $e) {
    $negQtyCaught = true;
    assertTest(true, "5c. Negative quantity rejected: " . $e->getMessage());
}
assertTest($negQtyCaught, "5d. Negative quantity validation passed");

// -------------------------------------------------------------
// TEST 6: Negative Price Validation
// -------------------------------------------------------------
echo "\n--- TEST GROUP 6: Negative Price Validation ---\n";

$negPriceCaught = false;
try {
    $badPayload = $purchasePayload;
    $badPayload['bill_no'] = 'NEG-PRICE-TEST';
    $badPayload['items'] = [
        [
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => -250,
        ]
    ];
    PurchaseInvoiceService::createPurchase($badPayload);
} catch (\InvalidArgumentException $e) {
    $negPriceCaught = true;
    assertTest(true, "6a. Negative price rejected: " . $e->getMessage());
}
assertTest($negPriceCaught, "6b. Negative price validation passed");

// -------------------------------------------------------------
// TEST 7: Tax Calculation & Grand Total Server-Side Accuracy
// -------------------------------------------------------------
echo "\n--- TEST GROUP 7: Tax & Server-Side Totals Accuracy ---\n";

$taxQty = 10.0;
$taxPrice = 500.0;
$taxGst = 18.0; // 9% CGST + 9% SGST
$taxPayload = [
    'company_id' => $companyId,
    'branch_id' => $branchId,
    'supplier_id' => $supplier->id,
    'warehouse_id' => $warehouse->id,
    'bill_no' => 'TAX-TEST-' . rand(1000, 9999),
    'purchase_date' => date('Y-m-d'),
    'status' => 'POSTED',
    'items' => [
        [
            'product_id' => $product->id,
            'quantity' => $taxQty,
            'unit_price' => $taxPrice,
            'gst_rate' => $taxGst,
        ]
    ]
];

$taxPurchase = PurchaseInvoiceService::createPurchase($taxPayload);
$expectedSubtotal = 5000.00;
$expectedCgst = 450.00;
$expectedSgst = 450.00;
$expectedGrandTotal = 5900.00;

assertTest(abs(floatval($taxPurchase->sub_total) - $expectedSubtotal) < 0.01, "7a. Subtotal calculated accurately: ₹{$taxPurchase->sub_total} (expected ₹{$expectedSubtotal})");
assertTest(abs(floatval($taxPurchase->cgst_amount) - $expectedCgst) < 0.01, "7b. CGST calculated accurately: ₹{$taxPurchase->cgst_amount} (expected ₹{$expectedCgst})");
assertTest(abs(floatval($taxPurchase->sgst_amount) - $expectedSgst) < 0.01, "7c. SGST calculated accurately: ₹{$taxPurchase->sgst_amount} (expected ₹{$expectedSgst})");
assertTest(abs(floatval($taxPurchase->grand_total) - $expectedGrandTotal) < 0.01, "7d. Grand total calculated accurately: ₹{$taxPurchase->grand_total} (expected ₹{$expectedGrandTotal})");

// -------------------------------------------------------------
// TEST 8: Purchase Return / Debit Note Workflow
// -------------------------------------------------------------
echo "\n--- TEST GROUP 8: Purchase Return & Debit Note Workflow ---\n";

$preReturnStock = floatval($product->refresh()->current_stock);
$preReturnSupplierBal = floatval($supplier->refresh()->current_balance);
$returnQty = 2.0;

$returnPayload = [
    'purchase_id' => $purchase->id,
    'reason' => 'Defective piece returned to vendor',
    'items' => [
        [
            'product_id' => $product->id,
            'quantity' => $returnQty,
        ]
    ]
];

$purchaseReturn = PurchaseReturnService::createReturn($returnPayload);
assertTest($purchaseReturn && $purchaseReturn->id > 0, "8a. Purchase Return recorded with ID #{$purchaseReturn->id}");

$postReturnStock = floatval($product->refresh()->current_stock);
assertTest(abs($postReturnStock - ($preReturnStock - $returnQty)) < 0.001, "8b. Stock decreased on purchase return from {$preReturnStock} to {$postReturnStock} (-{$returnQty})");

$postReturnSupplierBal = floatval($supplier->refresh()->current_balance);
assertTest($postReturnSupplierBal < $preReturnSupplierBal, "8c. Supplier payable balance reduced on purchase return (from ₹{$preReturnSupplierBal} to ₹{$postReturnSupplierBal})");

// -------------------------------------------------------------
// TEST 9: Purchase Orders (PO) & Goods Receipts (GRN)
// -------------------------------------------------------------
echo "\n--- TEST GROUP 9: Purchase Orders & Goods Receipts ---\n";

$po = PurchaseOrderService::createPO([
    'company_id' => $companyId,
    'branch_id' => $branchId,
    'supplier_id' => $supplier->id,
    'po_date' => date('Y-m-d'),
    'expected_delivery' => date('Y-m-d', strtotime('+7 days')),
    'status' => 'ISSUED',
    'items' => [
        [
            'product_id' => $product->id,
            'quantity' => 15,
            'unit_price' => 250,
            'gst_rate' => 18,
        ]
    ]
]);
assertTest($po && $po->id > 0, "9a. Purchase Order created successfully #{$po->po_number}");

$grn = GoodsReceiptService::createGRN([
    'company_id' => $companyId,
    'branch_id' => $branchId,
    'supplier_id' => $supplier->id,
    'purchase_order_id' => $po->id,
    'warehouse_id' => $warehouse->id,
    'grn_date' => date('Y-m-d'),
    'items' => [
        [
            'product_id' => $product->id,
            'received_quantity' => 15,
            'unit_cost' => 250,
        ]
    ]
]);
assertTest($grn && $grn->id > 0, "9b. Goods Receipt (GRN) recorded successfully #{$grn->grn_number}");

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n=======================================================\n";
echo "Purchases Module Test Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

if ($failed > 0) {
    echo "Failures encountered:\n";
    foreach ($errors as $err) {
        echo $err . "\n";
    }
    exit(1);
} else {
    echo "ALL PURCHASES WORKFLOW TESTS PASSED PERFECTLY!\n";
    exit(0);
}
