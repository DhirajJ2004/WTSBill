<?php
/**
 * Comprehensive Automated Test Suite for WTSBill ERP Invoice System
 * Tests:
 * 1. Invalid Customer (Non-existent & cross-company)
 * 2. Invalid Product (Non-existent & cross-company)
 * 3. Zero Quantity
 * 4. Negative Quantity
 * 5. Insufficient Stock Validation
 * 6. Valid Single-Item Invoice (Intra-state, CGST + SGST)
 * 7. Verification: Numbering, Stock deduction, Accounting Journal, Customer Balance, Audit Log
 * 8. Valid Multi-Item Invoice with Discount & Inter-state (IGST)
 * 9. Cancellation Flow (Restores stock, reverses customer balance, voids journal)
 * 10. Repeat cancellation rejection
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/../backend/app/Http/Request.php';
App\Database\Database::init();

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StockMovement;
use App\Models\StockBalance;
use App\Models\JournalEntry;
use App\Models\AuditLog;
use App\Services\InvoiceService;
use App\Services\AccountService;
use Illuminate\Database\Capsule\Manager as DB;

$passCount = 0;
$failCount = 0;

function assertCondition(string $title, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$title}\n";
        if ($detail) echo "        Detail: {$detail}\n";
    } else {
        $failCount++;
        echo " [FAIL] {$title}\n";
        if ($detail) echo "        Detail: {$detail}\n";
    }
}

echo "====================================================================\n";
echo "           WTSBill ERP Transactional Invoice Test Suite             \n";
echo "====================================================================\n\n";

$companyAId = 1;
$companyBId = 2;
$userA = User::find(1); // Anil Desai

// Ensure default chart of accounts and account mappings exist for Company 1
AccountService::ensureDefaultAccounts($companyAId);

// -------------------------------------------------------------
// TEST 1: Invalid Customer Validations
// -------------------------------------------------------------
echo "--- TEST 1: Customer Validations ---\n";
// 1a: Non-existent customer
$resInvCust = InvoiceService::createInvoice([
    'customer_id' => 999999,
    'items' => [
        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 100]
    ]
], $companyAId, 1, $userA);

assertCondition(
    "1a. Non-existent customer is rejected with 422",
    ($resInvCust['success'] ?? true) === false && ($resInvCust['code'] ?? 0) === 422,
    "Message: " . ($resInvCust['message'] ?? '')
);

// 1b: Customer belonging to Company B attempted by Company A
$resCrossCust = InvoiceService::createInvoice([
    'customer_id' => 21, // Zenith Retail Ltd in Company 2
    'items' => [
        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 100]
    ]
], $companyAId, 1, $userA);

assertCondition(
    "1b. Foreign company customer rejected (cross-company isolation)",
    ($resCrossCust['success'] ?? true) === false && in_array($resCrossCust['code'] ?? 0, [403, 422]),
    "Code: " . ($resCrossCust['code'] ?? 0) . ", Message: " . ($resCrossCust['message'] ?? '')
);

// -------------------------------------------------------------
// TEST 2: Invalid Product Validations
// -------------------------------------------------------------
echo "\n--- TEST 2: Product Validations ---\n";
// 2a: Non-existent product
$resInvProd = InvoiceService::createInvoice([
    'customer_id' => 1,
    'items' => [
        ['product_id' => 999999, 'quantity' => 1, 'unit_price' => 100]
    ]
], $companyAId, 1, $userA);

assertCondition(
    "2a. Non-existent product rejected with 422",
    ($resInvProd['success'] ?? true) === false && ($resInvProd['code'] ?? 0) === 422,
    "Message: " . ($resInvProd['message'] ?? '')
);

// 2b: Foreign product belonging to Company B
$resCrossProd = InvoiceService::createInvoice([
    'customer_id' => 1,
    'items' => [
        ['product_id' => 51, 'quantity' => 1, 'unit_price' => 100] // Product 51 in Company 2
    ]
], $companyAId, 1, $userA);

assertCondition(
    "2b. Foreign company product rejected (cross-company isolation)",
    ($resCrossProd['success'] ?? true) === false && in_array($resCrossProd['code'] ?? 0, [403, 422]),
    "Code: " . ($resCrossProd['code'] ?? 0) . ", Message: " . ($resCrossProd['message'] ?? '')
);

// -------------------------------------------------------------
// TEST 3: Zero Quantity Validation
// -------------------------------------------------------------
echo "\n--- TEST 3: Zero & Negative Quantity Validations ---\n";
$resZeroQty = InvoiceService::createInvoice([
    'customer_id' => 1,
    'items' => [
        ['product_id' => 1, 'quantity' => 0, 'unit_price' => 1250]
    ]
], $companyAId, 1, $userA);

assertCondition(
    "3. Zero quantity rejected with 422",
    ($resZeroQty['success'] ?? true) === false && ($resZeroQty['code'] ?? 0) === 422,
    "Message: " . ($resZeroQty['message'] ?? '')
);

$resNegQty = InvoiceService::createInvoice([
    'customer_id' => 1,
    'items' => [
        ['product_id' => 1, 'quantity' => -3, 'unit_price' => 1250]
    ]
], $companyAId, 1, $userA);

assertCondition(
    "3b. Negative quantity rejected with 422",
    ($resNegQty['success'] ?? true) === false && ($resNegQty['code'] ?? 0) === 422,
    "Message: " . ($resNegQty['message'] ?? '')
);

// -------------------------------------------------------------
// TEST 4: Insufficient Stock Validation
// -------------------------------------------------------------
echo "\n--- TEST 4: Insufficient Stock Validation ---\n";
// Requesting 10000 units when warehouse stock is 500
$resOOS = InvoiceService::createInvoice([
    'customer_id' => 1,
    'items' => [
        ['product_id' => 3, 'quantity' => 10000, 'unit_price' => 1850]
    ]
], $companyAId, 1, $userA);

assertCondition(
    "4. Insufficient stock rejected with 422",
    ($resOOS['success'] ?? true) === false && ($resOOS['code'] ?? 0) === 422,
    "Message: " . ($resOOS['message'] ?? '')
);

// -------------------------------------------------------------
// TEST 5: Valid Single-Item Invoice (Intra-state, CGST 9% + SGST 9%)
// -------------------------------------------------------------
echo "\n--- TEST 5: Valid Single-Item Invoice (Intra-State) ---\n";
// Product 1: Industrial Brass Valve 2inch (Price: 1250, Qty: 2, Total Taxable: 2500, GST: 18%)
$initialProd1Stock = floatval(Product::find(1)->current_stock);
$cust1 = Customer::find(1);
$initialCust1Bal = floatval($cust1->current_balance);

$resSingle = InvoiceService::createInvoice([
    'customer_id' => 1,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'place_of_supply' => '27', // Maharashtra (Intra-state)
    'status' => 'POSTED',
    'items' => [
        [
            'product_id' => 1,
            'quantity' => 2,
            'unit_price' => 1250.00,
            'discount_rate' => 0.00,
            'gst_rate' => 18.00,
        ]
    ]
], $companyAId, 1, $userA);

assertCondition(
    "5. Single-item invoice created successfully with code 201",
    ($resSingle['success'] ?? false) === true && ($resSingle['code'] ?? 0) === 201,
    "Invoice ID: " . ($resSingle['data']->id ?? 0) . ", Number: " . ($resSingle['data']->invoice_number ?? '')
);

$inv1 = $resSingle['data'];

// Verify Financial Recalculations
// Subtotal = 2 * 1250 = 2500
// CGST (9%) = 225.00
// SGST (9%) = 225.00
// IGST = 0.00
// Total Tax = 450.00
// Grand Total = 2950.00
assertCondition(
    "5b. Backend recalculated intra-state GST (CGST: ₹225, SGST: ₹225, Total: ₹2950)",
    floatval($inv1->sub_total) === 2500.00 &&
    floatval($inv1->cgst_amount) === 225.00 &&
    floatval($inv1->sgst_amount) === 225.00 &&
    floatval($inv1->igst_amount) === 0.00 &&
    floatval($inv1->grand_total) === 2950.00,
    "Subtotal: {$inv1->sub_total}, CGST: {$inv1->cgst_amount}, SGST: {$inv1->sgst_amount}, Grand Total: {$inv1->grand_total}"
);

// -------------------------------------------------------------
// TEST 6: Verification of Downstream Side-Effects
// -------------------------------------------------------------
echo "\n--- TEST 6: Downstream Side-Effects Verification ---\n";
// 6a. Document Numbering Sequence
assertCondition(
    "6a. Unique Document Number generated with prefix INV",
    !empty($inv1->invoice_number) && (str_starts_with($inv1->invoice_number, 'INV/') || str_starts_with($inv1->invoice_number, 'INV-')),
    "Invoice Number: {$inv1->invoice_number}"
);

// 6b. Inventory Deduction
$newProd1Stock = floatval(Product::find(1)->current_stock);
$stockMovement = StockMovement::where('company_id', $companyAId)
    ->where('reference_type', 'SALES_INVOICE')
    ->where('reference_id', $inv1->id)
    ->first();

assertCondition(
    "6b. Inventory deducted by 2 units and StockMovement ledger record created",
    $newProd1Stock === ($initialProd1Stock - 2) && $stockMovement !== null && floatval($stockMovement->quantity) === 2.0,
    "Previous Stock: {$initialProd1Stock}, Current Stock: {$newProd1Stock}, Movement Ref: " . ($stockMovement->reference_number ?? 'NONE')
);

// 6c. Customer Receivable Balance
$newCust1Bal = floatval(Customer::find(1)->current_balance);
assertCondition(
    "6c. Customer balance increased by invoice grand total (₹2950)",
    $newCust1Bal === ($initialCust1Bal + 2950.00),
    "Previous Balance: {$initialCust1Bal}, New Balance: {$newCust1Bal}"
);

// 6d. Accounting Journal Entry
$journal = JournalEntry::where('company_id', $companyAId)
    ->where('reference_type', 'INVOICE')
    ->where('reference_id', (string)$inv1->id)
    ->with('lines')
    ->first();

$journalDebit = $journal ? $journal->lines->sum('debit') : 0;
$journalCredit = $journal ? $journal->lines->sum('credit') : 0;

assertCondition(
    "6d. Double-entry accounting journal posted and perfectly balanced",
    $journal !== null && floatval($journalDebit) === 2950.00 && floatval($journalCredit) === 2950.00,
    "Journal Entry: " . ($journal->entry_number ?? 'NONE') . ", Debits: ₹{$journalDebit}, Credits: ₹{$journalCredit}"
);

// 6e. Audit Log
$audit = AuditLog::where('company_id', $companyAId)
    ->where('action', 'INVOICE_CREATE')
    ->where('entity_id', $inv1->id)
    ->first();

assertCondition(
    "6e. Audit log recorded for invoice creation",
    $audit !== null,
    "Action: " . ($audit->action ?? 'NONE') . ", Description: " . ($audit->description ?? '')
);

// -------------------------------------------------------------
// TEST 7: Multi-Item Invoice with Discount & Inter-State GST (IGST)
// -------------------------------------------------------------
echo "\n--- TEST 7: Multi-Item Invoice with Discount & Inter-State (IGST) ---\n";
// Item 1: Product 1 (Qty: 1, Rate: 1000, 10% discount -> Taxable 900, GST 18% -> IGST 162)
// Item 2: Product 2 (Qty: 2, Rate: 500, no discount -> Taxable 1000, GST 18% -> IGST 180)
// Total Taxable = 1900.00
// IGST = 342.00
// Grand Total = 2242.00
$resMulti = InvoiceService::createInvoice([
    'customer_id' => 1,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'place_of_supply' => '29', // Karnataka (Inter-state supply)
    'status' => 'POSTED',
    'items' => [
        [
            'product_id' => 1,
            'quantity' => 1,
            'unit_price' => 1000.00,
            'discount_rate' => 10.00,
            'gst_rate' => 18.00,
        ],
        [
            'product_id' => 2,
            'quantity' => 2,
            'unit_price' => 500.00,
            'discount_rate' => 0.00,
            'gst_rate' => 18.00,
        ],
    ]
], $companyAId, 1, $userA);

assertCondition(
    "7. Multi-item invoice created successfully with code 201",
    ($resMulti['success'] ?? false) === true && ($resMulti['code'] ?? 0) === 201,
    "Invoice ID: " . ($resMulti['data']->id ?? 0) . ", Number: " . ($resMulti['data']->invoice_number ?? '')
);

$inv2 = $resMulti['data'];

assertCondition(
    "7b. Backend recalculated discount and inter-state IGST (Subtotal: ₹1900, IGST: ₹342, Grand Total: ₹2242)",
    floatval($inv2->sub_total) === 1900.00 &&
    floatval($inv2->cgst_amount) === 0.00 &&
    floatval($inv2->sgst_amount) === 0.00 &&
    floatval($inv2->igst_amount) === 342.00 &&
    floatval($inv2->grand_total) === 2242.00,
    "Subtotal: {$inv2->sub_total}, Discount: {$inv2->discount_amount}, IGST: {$inv2->igst_amount}, Total: {$inv2->grand_total}"
);

// -------------------------------------------------------------
// TEST 8: Invoice Cancellation Workflow
// -------------------------------------------------------------
echo "\n--- TEST 8: Invoice Cancellation Workflow ---\n";
// Cancel Invoice 1
$preCancelStock = floatval(Product::find(1)->current_stock);
$preCancelCustBal = floatval(Customer::find(1)->current_balance);

$resCancel = InvoiceService::cancelInvoice($inv1->id, 'Customer cancellation request', $companyAId, $userA);

assertCondition(
    "8. Invoice cancellation returns success: true",
    ($resCancel['success'] ?? false) === true,
    "Message: " . ($resCancel['message'] ?? '')
);

// Verify status changed
$cancelledInv = Invoice::find($inv1->id);
assertCondition(
    "8b. Invoice status updated to CANCELLED",
    $cancelledInv->status === 'CANCELLED',
    "Status: {$cancelledInv->status}"
);

// Verify stock restored
$postCancelStock = floatval(Product::find(1)->current_stock);
assertCondition(
    "8c. Inventory stock restored by 2 units on cancellation",
    $postCancelStock === ($preCancelStock + 2),
    "Pre-cancel: {$preCancelStock}, Post-cancel: {$postCancelStock}"
);

// Verify customer balance reversed
$postCancelCustBal = floatval(Customer::find(1)->current_balance);
assertCondition(
    "8d. Customer receivable balance reduced by ₹2950",
    $postCancelCustBal === ($preCancelCustBal - 2950.00),
    "Pre-cancel: {$preCancelCustBal}, Post-cancel: {$postCancelCustBal}"
);

// Verify journal entry cancelled
$cancelledJournal = JournalEntry::where('company_id', $companyAId)
    ->where('reference_type', 'INVOICE')
    ->where('reference_id', (string)$inv1->id)
    ->first();

assertCondition(
    "8e. Accounting journal marked as CANCELLED",
    $cancelledJournal !== null && $cancelledJournal->status === 'CANCELLED',
    "Journal Status: " . ($cancelledJournal->status ?? 'NONE')
);

// 8f: Attempt to cancel again should fail
$resRepeatCancel = InvoiceService::cancelInvoice($inv1->id, 'Repeat cancel attempt', $companyAId, $userA);
assertCondition(
    "8f. Repeat cancellation of already cancelled invoice is rejected with 422",
    ($resRepeatCancel['success'] ?? true) === false && ($resRepeatCancel['code'] ?? 0) === 422,
    "Message: " . ($resRepeatCancel['message'] ?? '')
);

echo "\n====================================================================\n";
echo "   INVOICE TEST SUITE SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
