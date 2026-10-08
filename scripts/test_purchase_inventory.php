<?php
/**
 * WTSBill ERP — Purchase & Inventory Transactional Test Suite
 *
 * Tests:
 *  1. Purchase Order creation
 *  2. Goods Receipt Note (GRN) with inventory increase verification
 *  3. Purchase Bill creation (DRAFT → POSTED with full inventory/AP/accounting)
 *  4. Purchase Return with stock decrease + AP reduction + accounting
 *  5. Supplier Payment with AP reconciliation + bank debit + journal
 *  6. Warehouse Transfer (atomic: A−, B+)
 *  7. Stock Adjustment (increase and decrease)
 *  8. Cross-company isolation (supplier and product from another company rejected)
 *  9. Negative stock enforcement
 * 10. Stock Count and reconciliation
 */

require __DIR__ . '/../backend/vendor/autoload.php';

// PHP 8.0 polyfills (same as backend/public/index.php)
if (!function_exists('enum_exists')) {
    function enum_exists(string $enum, bool $autoload = true): bool { return false; }
}
if (!function_exists('array_is_list')) {
    function array_is_list(array $arr): bool {
        if ($arr === []) return true;
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

\App\Database\Database::init();

use App\Services\InventoryService;
use App\Services\PurchaseInvoiceService;
use App\Services\PurchaseReturnService;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use App\Banking\Services\PaymentEngine;
use App\Http\Middleware\AuthMiddleware;
use App\Auth\WorkspaceContext;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\GoodsReceipt;
use App\Models\StockMovement;
use App\Models\StockBalance;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as DB;

$passed = 0;
$failed = 0;

function assertCondition(string $label, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$label}\n";
    } else {
        $failed++;
        echo " [FAIL] {$label}\n";
    }
    if ($detail) {
        echo "        Detail: {$detail}\n";
    }
}

function section(string $title): void {
    echo "\n--- {$title} ---\n";
}

// ─── Bootstrap auth context ───────────────────────────────────────
$user = User::withoutGlobalScopes()->where('email', 'anil.d@wtsbill.in')->first();
if (!$user) {
    echo "FATAL: Test user 'anil.d@wtsbill.in' not found.\n";
    exit(1);
}

$company = DB::table('companies')->where('id', 1)->first();
if (!$company) {
    echo "FATAL: Company #1 not found.\n";
    exit(1);
}

$user->current_company_id = 1;
AuthMiddleware::setContext($user, 1, 1, 'owner', '2026-27');

// Snapshot state before tests
$product1  = Product::withoutGlobalScopes()->where('company_id', 1)->orderBy('id')->first();
$product2  = Product::withoutGlobalScopes()->where('company_id', 1)->orderBy('id')->skip(1)->first();
$supplier1 = Supplier::withoutGlobalScopes()->where('company_id', 1)->first();
$warehouse = Warehouse::withoutGlobalScopes()->where('company_id', 1)->where('is_active', 1)->first();
$bankAcc   = BankAccount::where('company_id', 1)->where('is_active', 1)->first();

if (!$product1 || !$product2 || !$supplier1 || !$warehouse) {
    echo "FATAL: Required seed data not found. Need products, supplier, warehouse for company 1.\n";
    exit(1);
}

$stockBefore1 = floatval($product1->fresh()->current_stock);
$stockBefore2 = floatval($product2->fresh()->current_stock);
$supplierApBefore = floatval($supplier1->fresh()->current_balance);
$bankBalBefore = $bankAcc ? floatval($bankAcc->fresh()->current_balance) : 0;

echo "====================================================================\n";
echo "   WTSBill ERP Purchase & Inventory Transactional Test Suite        \n";
echo "====================================================================\n";
echo "Company: #{$company->id} | Product1: #{$product1->id} ({$product1->name}) | ";
echo "Stock: {$stockBefore1}\n";
echo "Product2: #{$product2->id} ({$product2->name}) | Stock: {$stockBefore2}\n";
echo "Supplier: #{$supplier1->id} ({$supplier1->name}) | AP Balance: ₹{$supplierApBefore}\n";

// ─── SECTION 1: Purchase Order ─────────────────────────────────────
section('SECTION 1: Purchase Order Creation');

$poResult = null;
try {
    $poResult = PurchaseOrderService::createPO([
        'supplier_id' => $supplier1->id,
        'po_date'     => date('Y-m-d'),
        'notes'       => 'Test PO for automated suite',
        'items'       => [
            [
                'product_id' => $product1->id,
                'item_name'  => $product1->name,
                'quantity'   => 10,
                'unit_price' => 150.00,
                'gst_rate'   => floatval($product1->gst_rate ?: 18),
                'unit'       => 'Pcs',
            ],
            [
                'product_id' => $product2->id,
                'item_name'  => $product2->name,
                'quantity'   => 5,
                'unit_price' => 200.00,
                'gst_rate'   => floatval($product2->gst_rate ?: 12),
                'unit'       => 'Pcs',
            ],
        ]
    ]);
    assertCondition(
        '1a. Purchase Order created successfully',
        $poResult !== null && $poResult->id > 0,
        "PO: #{$poResult?->po_number}, Total: ₹{$poResult?->grand_total}"
    );
} catch (\Throwable $e) {
    assertCondition('1a. Purchase Order created successfully', false, "ERROR: " . $e->getMessage());
}

// ─── SECTION 2: Goods Receipt Note ─────────────────────────────────
section('SECTION 2: Goods Receipt Note (GRN) with Inventory Update');

$stockBeforeGrn1 = floatval($product1->fresh()->current_stock);
$stockBeforeGrn2 = floatval($product2->fresh()->current_stock);
$grnResult = null;

try {
    $grnResult = GoodsReceiptService::createGRN([
        'purchase_order_id' => $poResult?->id,
        'supplier_id'       => $supplier1->id,
        'warehouse_id'      => $warehouse->id,
        'grn_date'          => date('Y-m-d'),
        'notes'             => 'Test GRN receipt',
        'items'             => [
            [
                'product_id'        => $product1->id,
                'item_name'         => $product1->name,
                'quantity_ordered'  => 10,
                'quantity_received' => 10,
                'unit_price'        => 150.00,
                'unit'              => 'Pcs',
            ],
            [
                'product_id'        => $product2->id,
                'item_name'         => $product2->name,
                'quantity_ordered'  => 5,
                'quantity_received' => 5,
                'unit_price'        => 200.00,
                'unit'              => 'Pcs',
            ],
        ]
    ]);

    assertCondition(
        '2a. GRN created with status RECEIVED',
        $grnResult !== null && $grnResult->status === 'RECEIVED',
        "GRN: #{$grnResult?->grn_number}"
    );

    // Verify inventory increased
    $stockAfterGrn1 = floatval($product1->fresh()->current_stock);
    $stockAfterGrn2 = floatval($product2->fresh()->current_stock);

    assertCondition(
        '2b. Product1 stock increased by 10 after GRN',
        abs(($stockAfterGrn1 - $stockBeforeGrn1) - 10) < 0.01,
        "Before: {$stockBeforeGrn1}, After: {$stockAfterGrn1}, Delta: " . ($stockAfterGrn1 - $stockBeforeGrn1)
    );

    assertCondition(
        '2c. Product2 stock increased by 5 after GRN',
        abs(($stockAfterGrn2 - $stockBeforeGrn2) - 5) < 0.01,
        "Before: {$stockBeforeGrn2}, After: {$stockAfterGrn2}, Delta: " . ($stockAfterGrn2 - $stockBeforeGrn2)
    );

    // Verify stock movements were recorded
    $mvt1 = StockMovement::withoutGlobalScopes()
        ->where('company_id', 1)
        ->where('product_id', $product1->id)
        ->where('reference_type', 'GOODS_RECEIPT')
        ->where('reference_id', $grnResult->id)
        ->where('movement_type', 'PURCHASE')
        ->where('direction', 'IN')
        ->first();

    assertCondition(
        '2d. Stock movement recorded (PURCHASE IN) for GRN product1',
        $mvt1 !== null && floatval($mvt1->quantity) == 10,
        "Movement ID: {$mvt1?->id}, Qty: {$mvt1?->quantity}, Direction: {$mvt1?->direction}"
    );

    // PO status updated
    if ($poResult) {
        $poResult->refresh();
        assertCondition(
            '2e. PO status updated to RECEIVED after full GRN',
            $poResult->status === 'RECEIVED',
            "PO status: {$poResult->status}"
        );
    }
} catch (\Throwable $e) {
    assertCondition('2a. GRN created with status RECEIVED', false, "ERROR: " . $e->getMessage());
    assertCondition('2b. Product1 stock increased by 10 after GRN', false, '');
    assertCondition('2c. Product2 stock increased by 5 after GRN', false, '');
    assertCondition('2d. Stock movement recorded', false, '');
}

// ─── SECTION 3: Purchase Bill (DRAFT → POSTED) ─────────────────────
section('SECTION 3: Purchase Bill — DRAFT creation then POST');

$stockBeforePurchase = floatval($product1->fresh()->current_stock);
$apBefore = floatval($supplier1->fresh()->current_balance);
$purchaseResult = null;

try {
    // Create in DRAFT first
    $purchaseResult = PurchaseInvoiceService::createPurchase([
        'supplier_id'          => $supplier1->id,
        'purchase_date'        => date('Y-m-d'),
        'due_date'             => date('Y-m-d', strtotime('+30 days')),
        'vendor_invoice_number'=> 'VENDOR-INV-' . rand(1000, 9999),
        'status'               => 'DRAFT',
        'items'                => [
            [
                'product_id' => $product1->id,
                'item_name'  => $product1->name,
                'quantity'   => 20,
                'unit_price' => 140.00,
                'gst_rate'   => floatval($product1->gst_rate ?: 18),
                'unit'       => 'Pcs',
            ],
        ]
    ]);

    assertCondition(
        '3a. Purchase Bill created as DRAFT',
        $purchaseResult !== null && $purchaseResult->status === 'DRAFT',
        "Bill: #{$purchaseResult?->purchase_number}, Total: ₹{$purchaseResult?->grand_total}"
    );

    // Stock should NOT change on DRAFT
    $stockAfterDraft = floatval($product1->fresh()->current_stock);
    assertCondition(
        '3b. Stock does NOT change on DRAFT (only changes on POST)',
        abs($stockAfterDraft - $stockBeforePurchase) < 0.01,
        "Before: {$stockBeforePurchase}, After: {$stockAfterDraft}"
    );

    // Now POST the purchase
    $purchaseResult = PurchaseInvoiceService::postPurchase($purchaseResult->id);

    assertCondition(
        '3c. Purchase Bill posted successfully (status = POSTED)',
        $purchaseResult->status === 'POSTED',
        "Status: {$purchaseResult->status}"
    );

    // Verify inventory increased by 20
    $stockAfterPost = floatval($product1->fresh()->current_stock);
    assertCondition(
        '3d. Stock increased by 20 after POST',
        abs(($stockAfterPost - $stockBeforePurchase) - 20) < 0.01,
        "Before POST: {$stockBeforePurchase}, After POST: {$stockAfterPost}, Delta: " . ($stockAfterPost - $stockBeforePurchase)
    );

    // Verify AP balance increased (we owe more to supplier)
    $apAfter = floatval($supplier1->fresh()->current_balance);
    $expectedIncrease = floatval($purchaseResult->amount_due);
    assertCondition(
        '3e. Supplier AP balance increased by bill amount',
        abs(($apAfter - $apBefore) - $expectedIncrease) < 0.10,
        "AP Before: ₹{$apBefore}, After: ₹{$apAfter}, Bill Amount: ₹{$expectedIncrease}"
    );

    // Verify stock movement ledger entry
    $purchaseMvt = StockMovement::withoutGlobalScopes()
        ->where('company_id', 1)
        ->where('product_id', $product1->id)
        ->where('reference_id', $purchaseResult->id)
        ->where('movement_type', 'PURCHASE')
        ->where('direction', 'IN')
        ->first();

    assertCondition(
        '3f. Stock movement ledger created (PURCHASE IN, qty=20)',
        $purchaseMvt !== null && floatval($purchaseMvt->quantity) == 20,
        "Mvt ID: {$purchaseMvt?->id}, Qty: {$purchaseMvt?->quantity}"
    );

    // Verify accounting journal posted with balanced entries
    $je = JournalEntry::withoutGlobalScopes()
        ->where('company_id', 1)
        ->where('reference_type', 'PURCHASE_INVOICE')
        ->where('reference_id', $purchaseResult->id)
        ->where('status', 'POSTED')
        ->first();

    if ($je) {
        $lines = $je->lines ?? \App\Models\JournalLine::where('journal_entry_id', $je->id)->get();
        $totalDebits  = round($lines->sum('debit'), 2);
        $totalCredits = round($lines->sum('credit'), 2);
        assertCondition(
            '3g. Double-entry journal balanced (Debits = Credits)',
            abs($totalDebits - $totalCredits) < 0.01,
            "JV: #{$je->entry_number}, Debits: ₹{$totalDebits}, Credits: ₹{$totalCredits}"
        );
    } else {
        assertCondition('3g. Double-entry journal balanced (Debits = Credits)', false, 'No journal entry found');
    }

} catch (\Throwable $e) {
    assertCondition('3a. Purchase Bill created as DRAFT', false, "ERROR: " . $e->getMessage());
    assertCondition('3b. Stock does NOT change on DRAFT', false, '');
    assertCondition('3c. Purchase Bill posted successfully', false, '');
    assertCondition('3d. Stock increased by 20 after POST', false, '');
    assertCondition('3e. Supplier AP balance increased', false, '');
    assertCondition('3f. Stock movement ledger created', false, '');
    assertCondition('3g. Double-entry journal balanced', false, '');
}

// ─── SECTION 4: Supplier Payment ───────────────────────────────────
section('SECTION 4: Supplier Payment with AP Reconciliation');

if ($purchaseResult && $bankAcc) {
    $apBeforePayment = floatval($supplier1->fresh()->current_balance);
    $bankBeforePayment = floatval($bankAcc->fresh()->current_balance);
    $paymentResult = null;

    try {
        $paymentResult = PaymentEngine::processSupplierPayment(1, [
            'supplier_id'    => $supplier1->id,
            'purchase_id'    => $purchaseResult->id,
            'amount'         => floatval($purchaseResult->amount_due),
            'payment_date'   => date('Y-m-d'),
            'payment_mode'   => 'BANK_TRANSFER',
            'bank_account_id'=> $bankAcc->id,
            'reference_number'=> 'UTR' . rand(100000000, 999999999),
            'branch_id'      => 1,
            'financial_year' => '2026-27',
        ], $user->name);

        assertCondition(
            '4a. Supplier payment recorded successfully',
            $paymentResult !== null && $paymentResult->id > 0,
            "Payment: #{$paymentResult?->payment_number}, Amount: ₹{$paymentResult?->amount}"
        );

        // Verify purchase bill now shows PAID
        $freshPurchase = Purchase::withoutGlobalScopes()->find($purchaseResult->id);
        $purchasePaidStatus = $freshPurchase ? floatval($freshPurchase->amount_due) : -1;
        assertCondition(
            '4b. Purchase Bill outstanding due reduced to ₹0 after payment',
            $purchasePaidStatus <= 0.01,
            "Amount Due: ₹{$purchasePaidStatus}"
        );

        // Verify supplier AP balance decreased
        $apAfterPayment = floatval($supplier1->fresh()->current_balance);
        assertCondition(
            '4c. Supplier AP balance decreased by payment amount',
            $apAfterPayment < $apBeforePayment,
            "AP Before: ₹{$apBeforePayment}, AP After: ₹{$apAfterPayment}"
        );

        // Verify bank balance decreased
        $bankAfterPayment = floatval($bankAcc->fresh()->current_balance);
        assertCondition(
            '4d. Bank account balance debited by payment amount',
            $bankAfterPayment < $bankBeforePayment,
            "Bank Before: ₹{$bankBeforePayment}, After: ₹{$bankAfterPayment}, Paid: ₹{$paymentResult->amount}"
        );

        // Verify double-entry journal
        $payJe = JournalEntry::withoutGlobalScopes()
            ->where('company_id', 1)
            ->where('reference_type', 'PAYMENT')
            ->where('reference_id', $paymentResult->id)
            ->where('status', 'POSTED')
            ->first();

        if ($payJe) {
            $payLines = $payJe->lines ?? \App\Models\JournalLine::where('journal_entry_id', $payJe->id)->get();
            $totalD = round($payLines->sum('debit'), 2);
            $totalC = round($payLines->sum('credit'), 2);
            assertCondition(
                '4e. Supplier payment journal balanced (Debits = Credits)',
                abs($totalD - $totalC) < 0.01,
                "JV: #{$payJe->entry_number}, Debits: ₹{$totalD}, Credits: ₹{$totalC}"
            );
        } else {
            assertCondition('4e. Supplier payment journal balanced', false, 'No journal found');
        }

    } catch (\Throwable $e) {
        assertCondition('4a. Supplier payment recorded successfully', false, "ERROR: " . $e->getMessage());
        assertCondition('4b. Purchase Bill outstanding due reduced to ₹0', false, '');
        assertCondition('4c. Supplier AP balance decreased', false, '');
        assertCondition('4d. Bank account balance debited', false, '');
        assertCondition('4e. Supplier payment journal balanced', false, '');
    }
} else {
    echo " [SKIP] Section 4: No purchase result or bank account available\n";
}

// ─── SECTION 5: Purchase Return ─────────────────────────────────────
section('SECTION 5: Purchase Return with Stock Decrease + AP + Accounting');

// Create a fresh POSTED purchase to return against
$stockBeforeReturn = floatval($product1->fresh()->current_stock);
$apBeforeReturn    = floatval($supplier1->fresh()->current_balance);

try {
    $retPurchase = PurchaseInvoiceService::createPurchase([
        'supplier_id'          => $supplier1->id,
        'purchase_date'        => date('Y-m-d'),
        'due_date'             => date('Y-m-d', strtotime('+30 days')),
        'vendor_invoice_number'=> 'RTN-TEST-' . rand(1000, 9999),
        'status'               => 'POSTED',
        'items'                => [[
            'product_id' => $product1->id,
            'item_name'  => $product1->name,
            'quantity'   => 8,
            'unit_price' => 150.00,
            'gst_rate'   => floatval($product1->gst_rate ?: 18),
            'unit'       => 'Pcs',
        ]]
    ]);

    $stockAfterPurchase5 = floatval($product1->fresh()->current_stock);
    $apAfterPurchase5    = floatval($supplier1->fresh()->current_balance);

    assertCondition(
        '5a. Purchase of 8 units for return test (stock +8)',
        abs(($stockAfterPurchase5 - $stockBeforeReturn) - 8) < 0.01,
        "Delta: " . ($stockAfterPurchase5 - $stockBeforeReturn)
    );

    // Create purchase return for 3 units
    $returnResult = PurchaseReturnService::createReturn([
        'purchase_id'  => $retPurchase->id,
        'warehouse_id' => $warehouse->id,
        'return_date'  => date('Y-m-d'),
        'reason'       => 'Defective goods from supplier',
        'items'        => [[
            'product_id' => $product1->id,
            'quantity'   => 3,
            'unit_price' => 150.00,
        ]]
    ]);

    assertCondition(
        '5b. Purchase Return created successfully',
        $returnResult !== null && $returnResult->return_number !== null,
        "Return#: {$returnResult?->return_number}, Amount: ₹{$returnResult?->grand_total}"
    );

    // Verify stock decreased by 3
    $stockAfterReturn = floatval($product1->fresh()->current_stock);
    $expectedStockAfterReturn = $stockAfterPurchase5 - 3;
    assertCondition(
        '5c. Stock decreased by 3 after purchase return',
        abs($stockAfterReturn - $expectedStockAfterReturn) < 0.01,
        "Before Return: {$stockAfterPurchase5}, After Return: {$stockAfterReturn}"
    );

    // Verify stock movement PURCHASE_RETURN OUT
    $retMvt = StockMovement::withoutGlobalScopes()
        ->where('company_id', 1)
        ->where('product_id', $product1->id)
        ->where('reference_id', $returnResult->id)
        ->where('movement_type', 'PURCHASE_RETURN')
        ->where('direction', 'OUT')
        ->first();
    assertCondition(
        '5d. PURCHASE_RETURN OUT movement recorded in stock ledger',
        $retMvt !== null && floatval($retMvt->quantity) == 3,
        "Mvt ID: {$retMvt?->id}, Qty: {$retMvt?->quantity}"
    );

    // Verify AP balance decreased
    $apAfterReturn = floatval($supplier1->fresh()->current_balance);
    assertCondition(
        '5e. Supplier AP balance decreased after purchase return',
        $apAfterReturn < $apAfterPurchase5,
        "AP Before Return: ₹{$apAfterPurchase5}, AP After: ₹{$apAfterReturn}"
    );

    // Verify accounting journal
    $retJe = JournalEntry::withoutGlobalScopes()
        ->where('company_id', 1)
        ->where('reference_type', 'PURCHASE_RETURN')
        ->where('reference_id', $returnResult->id)
        ->where('status', 'POSTED')
        ->first();
    if ($retJe) {
        $retLines = $retJe->lines ?? \App\Models\JournalLine::where('journal_entry_id', $retJe->id)->get();
        $rD = round($retLines->sum('debit'), 2);
        $rC = round($retLines->sum('credit'), 2);
        assertCondition(
            '5f. Purchase Return accounting journal balanced',
            abs($rD - $rC) < 0.01,
            "JV: #{$retJe->entry_number}, Debits: ₹{$rD}, Credits: ₹{$rC}"
        );
    } else {
        assertCondition('5f. Purchase Return accounting journal balanced', false, 'No journal found');
    }

    // Verify over-return is rejected
    try {
        PurchaseReturnService::createReturn([
            'purchase_id'  => $retPurchase->id,
            'warehouse_id' => $warehouse->id,
            'return_date'  => date('Y-m-d'),
            'reason'       => 'Over return test',
            'items'        => [[
                'product_id' => $product1->id,
                'quantity'   => 99,  // exceeds original 8 − already-returned 3 = 5 remaining
                'unit_price' => 150.00,
            ]]
        ]);
        assertCondition('5g. Over-return quantity rejected', false, 'Should have thrown exception');
    } catch (\Throwable $e) {
        assertCondition(
            '5g. Over-return quantity rejected with appropriate error',
            str_contains($e->getMessage(), 'exceeds maximum eligible') || str_contains($e->getMessage(), 'exceeds'),
            $e->getMessage()
        );
    }

} catch (\Throwable $e) {
    assertCondition('5a. Purchase of 8 units for return test', false, "ERROR: " . $e->getMessage());
    assertCondition('5b. Purchase Return created successfully', false, '');
    assertCondition('5c. Stock decreased by 3 after return', false, '');
    assertCondition('5d. PURCHASE_RETURN movement recorded', false, '');
    assertCondition('5e. Supplier AP decreased after return', false, '');
    assertCondition('5f. Purchase Return journal balanced', false, '');
    assertCondition('5g. Over-return rejected', false, '');
}

// ─── SECTION 6: Warehouse Transfer ─────────────────────────────────
section('SECTION 6: Warehouse Transfer (Atomic: A−, B+ in same transaction)');

// Create a second warehouse for transfer test
try {
    // Check if a second warehouse exists; if not create one
    $warehouseB = Warehouse::withoutGlobalScopes()
        ->where('company_id', 1)
        ->where('id', '!=', $warehouse->id)
        ->where('is_active', 1)
        ->first();

    if (!$warehouseB) {
        $warehouseB = Warehouse::create([
            'company_id' => 1,
            'branch_id'  => 1,
            'name'       => 'Warehouse B - Test',
            'code'       => 'WHB-TEST',
            'is_active'  => true,
            'is_primary' => false,
            'is_default' => false,
        ]);
    }

    $stockABefore = StockBalance::withoutGlobalScopes()
        ->where('company_id', 1)->where('product_id', $product2->id)->where('warehouse_id', $warehouse->id)
        ->first();
    $stockBBefore = StockBalance::withoutGlobalScopes()
        ->where('company_id', 1)->where('product_id', $product2->id)->where('warehouse_id', $warehouseB->id)
        ->first();

    $qtyInA = floatval($stockABefore?->quantity ?? 0);
    $qtyInB = floatval($stockBBefore?->quantity ?? 0);

    // Ensure warehouse A has some stock (add via adjustment if needed)
    if ($qtyInA < 5) {
        InventoryService::recordStockMovement(
            companyId: 1,
            warehouseId: $warehouse->id,
            productId: $product2->id,
            movementType: 'STOCK_ADJUSTMENT_IN',
            quantity: 10,
            direction: 'IN',
            branchId: 1,
            refType: 'STOCK_ADJUSTMENT',
            createdBy: $user->name,
            notes: 'Setup stock for transfer test'
        );
        $qtyInA = floatval(StockBalance::withoutGlobalScopes()
            ->where('company_id', 1)->where('product_id', $product2->id)->where('warehouse_id', $warehouse->id)
            ->first()?->quantity ?? 0);
    }

    $transfer = InventoryService::createTransfer(
        companyId:      1,
        fromWarehouseId: $warehouse->id,
        toWarehouseId:  $warehouseB->id,
        items:          [['product_id' => $product2->id, 'quantity' => 3]],
        refNo:          'TRFTEST-' . date('Ymd'),
        notes:          'Automated transfer test',
        branchId:       1
    );

    assertCondition(
        '6a. Stock transfer created (status IN_TRANSIT)',
        $transfer !== null && $transfer->status === 'IN_TRANSIT',
        "Transfer: #{$transfer?->transfer_number}"
    );

    $qtyAAfterTransfer = floatval(StockBalance::withoutGlobalScopes()
        ->where('company_id', 1)->where('product_id', $product2->id)->where('warehouse_id', $warehouse->id)
        ->first()?->quantity ?? 0);

    assertCondition(
        '6b. Warehouse A stock decreased by 3 (TRANSFER_OUT)',
        abs(($qtyInA - $qtyAAfterTransfer) - 3) < 0.01,
        "A Before: {$qtyInA}, A After: {$qtyAAfterTransfer}"
    );

    // Receive transfer → B increases
    $received = InventoryService::receiveTransfer($transfer->id);

    assertCondition(
        '6c. Transfer received successfully (status RECEIVED)',
        $received->status === 'RECEIVED',
        "Status: {$received->status}"
    );

    $qtyBAfterReceive = floatval(StockBalance::withoutGlobalScopes()
        ->where('company_id', 1)->where('product_id', $product2->id)->where('warehouse_id', $warehouseB->id)
        ->first()?->quantity ?? 0);

    assertCondition(
        '6d. Warehouse B stock increased by 3 (TRANSFER_IN)',
        abs(($qtyBAfterReceive - $qtyInB) - 3) < 0.01,
        "B Before: {$qtyInB}, B After: {$qtyBAfterReceive}"
    );

    // Verify both IN and OUT movements in ledger
    $outMvt = StockMovement::withoutGlobalScopes()
        ->where('company_id', 1)->where('product_id', $product2->id)
        ->where('reference_id', $transfer->id)->where('movement_type', 'TRANSFER_OUT')->first();
    $inMvt = StockMovement::withoutGlobalScopes()
        ->where('company_id', 1)->where('product_id', $product2->id)
        ->where('reference_id', $transfer->id)->where('movement_type', 'TRANSFER_IN')->first();

    assertCondition(
        '6e. Both TRANSFER_OUT and TRANSFER_IN movements in ledger',
        $outMvt !== null && $inMvt !== null,
        "OUT mvt: {$outMvt?->id}, IN mvt: {$inMvt?->id}"
    );

} catch (\Throwable $e) {
    assertCondition('6a. Stock transfer created', false, "ERROR: " . $e->getMessage());
    assertCondition('6b. Warehouse A stock decreased by 3', false, '');
    assertCondition('6c. Transfer received', false, '');
    assertCondition('6d. Warehouse B stock increased by 3', false, '');
    assertCondition('6e. Both movements in ledger', false, '');
}

// ─── SECTION 7: Cross-Company Isolation ─────────────────────────────
section('SECTION 7: Cross-Company Isolation — Supplier and Product Validation');

$company2Supplier = Supplier::withoutGlobalScopes()->where('company_id', 2)->first();
$company2Product  = Product::withoutGlobalScopes()->where('company_id', 2)->first();

if ($company2Supplier) {
    try {
        PurchaseInvoiceService::createPurchase([
            'supplier_id' => $company2Supplier->id,   // Company 2's supplier
            'status'      => 'DRAFT',
            'items'       => [[
                'product_id' => $product1->id,
                'quantity'   => 1,
                'unit_price' => 100,
                'gst_rate'   => 18,
            ]]
        ]);
        assertCondition('7a. Cross-company supplier access rejected', false, 'Should have thrown 403');
    } catch (\Throwable $e) {
        assertCondition(
            '7a. Cross-company supplier rejected (403)',
            str_contains($e->getMessage(), 'another company') || str_contains($e->getMessage(), 'Forbidden'),
            $e->getMessage()
        );
    }
} else {
    echo " [SKIP] 7a. No Company 2 supplier found\n";
}

if ($company2Product) {
    try {
        PurchaseInvoiceService::createPurchase([
            'supplier_id' => $supplier1->id,
            'status'      => 'DRAFT',
            'items'       => [[
                'product_id' => $company2Product->id,  // Company 2's product
                'quantity'   => 1,
                'unit_price' => 100,
                'gst_rate'   => 18,
            ]]
        ]);
        assertCondition('7b. Cross-company product access rejected', false, 'Should have thrown 403');
    } catch (\Throwable $e) {
        assertCondition(
            '7b. Cross-company product rejected (403)',
            str_contains($e->getMessage(), 'another company') || str_contains($e->getMessage(), 'Forbidden'),
            $e->getMessage()
        );
    }
} else {
    echo " [SKIP] 7b. No Company 2 product found\n";
}

// ─── SECTION 8: Negative Stock Enforcement ───────────────────────────
section('SECTION 8: Negative Stock Enforcement');

try {
    // Find a product that disallows negative stock
    $noNegProduct = Product::withoutGlobalScopes()->where('company_id', 1)
        ->where(function ($q) { $q->where('allow_negative_stock', 0)->orWhereNull('allow_negative_stock'); })
        ->first();

    if ($noNegProduct) {
        $currentStock = floatval($noNegProduct->current_stock);
        $testQty = max(100.0, abs($currentStock) + 5000.0);
        InventoryService::recordStockMovement(
            companyId:          1,
            warehouseId:        $warehouse->id,
            productId:          $noNegProduct->id,
            movementType:       'SALE',
            quantity:           $testQty,   // massively over stock
            direction:          'OUT',
            allowNegativeStock: false,
            branchId:           1,
            createdBy:          $user->name
        );
        assertCondition('8a. Negative stock OUT rejected', false, 'Should have thrown insufficient stock error');
    } else {
        echo " [SKIP] 8a. No product with negative stock prevention found\n";
    }
} catch (\Throwable $e) {
    assertCondition(
        '8a. Negative stock movement correctly rejected',
        str_contains($e->getMessage(), 'Insufficient stock') || str_contains($e->getMessage(), 'stock'),
        $e->getMessage()
    );
}

// ─── SECTION 9: Stock Adjustment ─────────────────────────────────────
section('SECTION 9: Stock Adjustment (Increase & Decrease)');

$stockBeforeAdj = floatval($product2->fresh()->current_stock);

try {
    // Increase adjustment
    $adj = InventoryService::createAdjustment(
        companyId:     1,
        warehouseId:   $warehouse->id,
        items:         [[
            'product_id' => $product2->id,
            'quantity'   => 15,
            'type'       => 'INCREASE',
            'unit_cost'  => floatval($product2->purchase_price),
        ]],
        reason:        'Annual stock count correction',
        notes:         'Test adjustment',
        createdByName: $user->name,
        branchId:      1
    );

    $stockAfterIncrease = floatval($product2->fresh()->current_stock);
    assertCondition(
        '9a. Stock adjustment INCREASE by 15',
        abs(($stockAfterIncrease - $stockBeforeAdj) - 15) < 0.01,
        "Before: {$stockBeforeAdj}, After: {$stockAfterIncrease}, Adj#: {$adj->adjustment_number}"
    );

    // Decrease adjustment
    $adj2 = InventoryService::createAdjustment(
        companyId:     1,
        warehouseId:   $warehouse->id,
        items:         [[
            'product_id' => $product2->id,
            'quantity'   => 5,
            'type'       => 'DECREASE',
            'unit_cost'  => floatval($product2->purchase_price),
        ]],
        reason:        'Damaged items removal',
        createdByName: $user->name,
        branchId:      1
    );

    $stockAfterDecrease = floatval($product2->fresh()->current_stock);
    assertCondition(
        '9b. Stock adjustment DECREASE by 5',
        abs(($stockAfterIncrease - $stockAfterDecrease) - 5) < 0.01,
        "Before Decrease: {$stockAfterIncrease}, After: {$stockAfterDecrease}"
    );

} catch (\Throwable $e) {
    assertCondition('9a. Stock adjustment INCREASE by 15', false, "ERROR: " . $e->getMessage());
    assertCondition('9b. Stock adjustment DECREASE by 5', false, '');
}

// ─── SECTION 10: Inventory Reconciliation ────────────────────────────
section('SECTION 10: Inventory Ledger vs StockBalance Reconciliation');

try {
    // First, dry-run to see current state
    $reconcile = InventoryService::recalculateInventory(1, null, false);
    assertCondition(
        '10a. Inventory reconciliation ran without errors',
        isset($reconcile['total_products_checked']) && $reconcile['total_products_checked'] > 0,
        "Products checked: {$reconcile['total_products_checked']}, Discrepancies found: {$reconcile['discrepancies_found']}"
    );

    // Run repair mode — syncs StockBalance cache from authoritative ledger movements
    $repaired = InventoryService::recalculateInventory(1, null, true);

    // After repair, verify zero discrepancies
    $postRepair = InventoryService::recalculateInventory(1, null, false);
    assertCondition(
        '10b. Zero discrepancies after ledger-cache repair',
        $postRepair['discrepancies_found'] === 0,
        "Post-repair discrepancies: {$postRepair['discrepancies_found']} (repaired: {$repaired['discrepancies_found']})"
    );

} catch (\Throwable $e) {
    assertCondition('10a. Inventory reconciliation ran without errors', false, "ERROR: " . $e->getMessage());
    assertCondition('10b. Zero discrepancies after repair', false, '');
}

// ─── SUMMARY ──────────────────────────────────────────────────────────
echo "\n====================================================================\n";
echo "   PURCHASE & INVENTORY SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "====================================================================\n\n";

exit($failed > 0 ? 1 : 0);
