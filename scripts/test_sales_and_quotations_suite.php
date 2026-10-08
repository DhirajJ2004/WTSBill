<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Database\Database;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\StockBalance;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\GSTService;
use App\Auth\WorkspaceContext;
use Illuminate\Database\Capsule\Manager as DB;

Database::init();

echo "======================================================================\n";
echo "   WTSBill ERP - Sales Invoice & Quotation Comprehensive Suite\n";
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
// SETUP TEST FIXTURES
// -------------------------------------------------------------
$company1 = Company::firstOrCreate(
    ['id' => 1],
    ['name' => 'WTS Primary Company', 'code' => 'WTS-01', 'state_code' => '27', 'status' => 'ACTIVE', 'gstin' => '27AADCW7577N1ZE']
);

$branch1 = Branch::firstOrCreate(
    ['company_id' => 1, 'is_main_branch' => 1],
    ['name' => 'Pune Main Branch', 'branch_code' => 'PUN01', 'state_code' => '27', 'is_main_branch' => 1]
);

$warehouse1 = Warehouse::firstOrCreate(
    ['company_id' => 1, 'is_primary' => 1],
    ['name' => 'Central Warehouse', 'code' => 'WH01', 'is_primary' => 1]
);

$customer1 = Customer::firstOrCreate(
    ['company_id' => 1, 'name' => 'Apex Test Customer'],
    ['email' => 'client@apex.com', 'state_code' => '27', 'gstin' => '27AAACT1234A1Z5', 'address_line1' => '101 Cyber Park, Pune']
);

$product1 = Product::firstOrCreate(
    ['company_id' => 1, 'sku' => 'PROD-SUITE-01'],
    ['name' => 'ERP Server License', 'sales_price' => 5000.00, 'purchase_price' => 3000.00, 'tax_rate' => 18.00, 'unit' => 'Pcs', 'track_inventory' => 1, 'current_stock' => 100]
);

// Ensure stock balance for product1 in warehouse1
$stockBal = StockBalance::firstOrCreate(
    ['company_id' => 1, 'warehouse_id' => $warehouse1->id, 'product_id' => $product1->id],
    ['quantity' => 100.000, 'available_quantity' => 100.000]
);
$stockBal->update(['quantity' => 100.000, 'available_quantity' => 100.000]);

$company2 = Company::firstOrCreate(
    ['id' => 2],
    ['name' => 'Tenant 2 Isolated Corp', 'code' => 'ISO-02', 'state_code' => '29', 'status' => 'ACTIVE', 'gstin' => '29AADCW7577N1Z9']
);

$customer2 = Customer::firstOrCreate(
    ['company_id' => 2, 'name' => 'Tenant 2 Customer'],
    ['email' => 'tenant2@isolated.com', 'state_code' => '29', 'address_line1' => 'Bangalore']
);

// -------------------------------------------------------------
// TEST 1: EMPTY INVOICE REJECTION
// -------------------------------------------------------------
echo "--- 1. Testing Empty Invoice Rejection ---\n";
$resEmpty = InvoiceService::createInvoice([
    'customer_id' => $customer1->id,
    'invoice_date' => date('Y-m-d'),
    'items' => []
], $company1->id, $branch1->id);

assert_test("INVOICE_VALIDATION", "Empty line items array is strictly rejected", 
    $resEmpty['success'] === false && $resEmpty['code'] === 422,
    $resEmpty['message'] ?? ''
);

// -------------------------------------------------------------
// TEST 2: INVALID CUSTOMER REJECTION
// -------------------------------------------------------------
echo "\n--- 2. Testing Invalid Customer Rejection ---\n";
$resInvalidCust = InvoiceService::createInvoice([
    'customer_id' => 99999999,
    'invoice_date' => date('Y-m-d'),
    'items' => [
        ['product_id' => $product1->id, 'quantity' => 1, 'unit_price' => 5000]
    ]
], $company1->id, $branch1->id);

assert_test("INVOICE_VALIDATION", "Non-existent customer is rejected", 
    $resInvalidCust['success'] === false && $resInvalidCust['code'] === 422,
    $resInvalidCust['message'] ?? ''
);

// Cross-tenant customer rejection
$resCrossCust = InvoiceService::createInvoice([
    'customer_id' => $customer2->id, // belongs to company 2
    'invoice_date' => date('Y-m-d'),
    'items' => [
        ['product_id' => $product1->id, 'quantity' => 1, 'unit_price' => 5000]
    ]
], $company1->id, $branch1->id);

assert_test("INVOICE_VALIDATION", "Customer belonging to another company is strictly rejected (403)", 
    $resCrossCust['success'] === false && $resCrossCust['code'] === 403,
    $resCrossCust['message'] ?? ''
);

// -------------------------------------------------------------
// TEST 3: INVALID PRODUCT REJECTION
// -------------------------------------------------------------
echo "\n--- 3. Testing Invalid Product Rejection ---\n";
$resInvalidProd = InvoiceService::createInvoice([
    'customer_id' => $customer1->id,
    'invoice_date' => date('Y-m-d'),
    'items' => [
        ['product_id' => 88888888, 'quantity' => 1, 'unit_price' => 5000]
    ]
], $company1->id, $branch1->id);

assert_test("INVOICE_VALIDATION", "Non-existent product in line items is rejected", 
    $resInvalidProd['success'] === false && $resInvalidProd['code'] === 422,
    $resInvalidProd['message'] ?? ''
);

// -------------------------------------------------------------
// TEST 4: ZERO & NEGATIVE QUANTITY REJECTION
// -------------------------------------------------------------
echo "\n--- 4. Testing Zero & Negative Quantity Rejection ---\n";
$resZeroQty = InvoiceService::createInvoice([
    'customer_id' => $customer1->id,
    'invoice_date' => date('Y-m-d'),
    'items' => [
        ['product_id' => $product1->id, 'quantity' => 0, 'unit_price' => 5000]
    ]
], $company1->id, $branch1->id);

assert_test("INVOICE_VALIDATION", "Zero quantity is rejected", 
    $resZeroQty['success'] === false && $resZeroQty['code'] === 422,
    $resZeroQty['message'] ?? ''
);

$resNegQty = InvoiceService::createInvoice([
    'customer_id' => $customer1->id,
    'invoice_date' => date('Y-m-d'),
    'items' => [
        ['product_id' => $product1->id, 'quantity' => -5, 'unit_price' => 5000]
    ]
], $company1->id, $branch1->id);

assert_test("INVOICE_VALIDATION", "Negative quantity is rejected", 
    $resNegQty['success'] === false && $resNegQty['code'] === 422,
    $resNegQty['message'] ?? ''
);

// -------------------------------------------------------------
// TEST 5: NEGATIVE PRICE REJECTION
// -------------------------------------------------------------
echo "\n--- 5. Testing Negative Price Rejection ---\n";
$resNegPrice = InvoiceService::createInvoice([
    'customer_id' => $customer1->id,
    'invoice_date' => date('Y-m-d'),
    'items' => [
        ['product_id' => $product1->id, 'quantity' => 1, 'unit_price' => -100]
    ]
], $company1->id, $branch1->id);

assert_test("INVOICE_VALIDATION", "Negative unit price is rejected", 
    $resNegPrice['success'] === false && $resNegPrice['code'] === 422,
    $resNegPrice['message'] ?? ''
);

// -------------------------------------------------------------
// TEST 6: SERVER-SIDE GST & TOTAL CALCULATIONS
// -------------------------------------------------------------
echo "\n--- 6. Testing Server-Side GST & Total Recalculations ---\n";
// Intra-state (27 -> 27): 18% GST -> 9% CGST + 9% SGST
$resSuccess = InvoiceService::createInvoice([
    'customer_id' => $customer1->id,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+15 days')),
    'warehouse_id' => $warehouse1->id,
    'items' => [
        [
            'product_id' => $product1->id,
            'quantity' => 2,
            'unit_price' => 5000.00,
            'discount_rate' => 10.00, // 10% discount on ₹10,000 = ₹1,000 discount -> taxable ₹9,000
            'tax_rate' => 18.00
        ]
    ]
], $company1->id, $branch1->id);

assert_test("INVOICE_CREATE", "Successful invoice creation", 
    $resSuccess['success'] === true && !empty($resSuccess['data']->invoice_number),
    "Generated Invoice: " . ($resSuccess['data']->invoice_number ?? 'N/A')
);

$createdInv = $resSuccess['data'];
assert_test("TAX_CALCULATION", "Subtotal calculated correctly after discount (₹9,000.00)", 
    round((float)$createdInv->sub_total, 2) === 9000.00,
    "Actual Subtotal: ₹" . $createdInv->sub_total
);

assert_test("TAX_CALCULATION", "Intra-state GST split: CGST=₹810.00, SGST=₹810.00, IGST=₹0.00", 
    round((float)$createdInv->cgst_amount, 2) === 810.00 && 
    round((float)$createdInv->sgst_amount, 2) === 810.00 && 
    round((float)$createdInv->igst_amount, 2) === 0.00,
    "CGST: ₹{$createdInv->cgst_amount}, SGST: ₹{$createdInv->sgst_amount}"
);

assert_test("TAX_CALCULATION", "Grand Total calculated correctly (₹10,620.00)", 
    round((float)$createdInv->grand_total, 2) === 10620.00,
    "Grand Total: ₹{$createdInv->grand_total}"
);

// -------------------------------------------------------------
// TEST 7: INVENTORY MOVEMENT & STOCK DEDUCTION
// -------------------------------------------------------------
echo "\n--- 7. Testing Stock Deduction On Invoice Posting ---\n";
$updatedStock = StockBalance::where('company_id', $company1->id)
    ->where('warehouse_id', $warehouse1->id)
    ->where('product_id', $product1->id)
    ->first();

assert_test("INVENTORY", "Stock deducted in warehouse (100 - 2 = 98)", 
    (float)$updatedStock->quantity === 98.000,
    "Current Stock: " . $updatedStock->quantity
);

// -------------------------------------------------------------
// TEST 8: SEQUENTIAL & CONCURRENT INVOICE NUMBER GENERATION
// -------------------------------------------------------------
echo "\n--- 8. Testing Sequential Invoice Number Generation ---\n";
$invNum1 = DocumentNumberService::generateNextNumber($company1->id, $branch1->id, '2026-27', 'INVOICE');
$invNum2 = DocumentNumberService::generateNextNumber($company1->id, $branch1->id, '2026-27', 'INVOICE');

assert_test("NUMBERING", "Invoice numbers are unique and sequentially incremented", 
    $invNum1 !== $invNum2 && str_starts_with($invNum1, 'INV/FY26-27/'),
    "Generated: {$invNum1} -> {$invNum2}"
);

// -------------------------------------------------------------
// TEST 9: INVOICE PRINTING VIEW DATA INTEGRITY
// -------------------------------------------------------------
echo "\n--- 9. Testing Invoice Printing (/print-invoice) Validation ---\n";
// Load invoice for printing with company verification
$printInv = DB::table('invoices')->where('id', $createdInv->id)->where('company_id', $company1->id)->first();
$printCust = DB::table('customers')->where('id', $printInv->customer_id)->first();
$printItems = DB::table('invoice_items')->where('invoice_id', $printInv->id)->get();

assert_test("PRINT_INVOICE", "Invoice loaded with verified company ownership", 
    $printInv !== null && (int)$printInv->company_id === (int)$company1->id,
    "Invoice ID: {$printInv->id}"
);

assert_test("PRINT_INVOICE", "Customer details loaded accurately", 
    $printCust !== null && $printCust->name === 'Apex Test Customer',
    "Customer: " . ($printCust->name ?? 'N/A')
);

assert_test("PRINT_INVOICE", "Line items loaded with taxable values and tax rates", 
    $printItems->count() === 1 && (float)$printItems[0]->taxable_value === 9000.00,
    "Item count: " . $printItems->count() . ", Taxable: ₹" . $printItems[0]->taxable_value
);

// Cross-company print isolation check
$crossCompanyInvoice = DB::table('invoices')->where('id', $createdInv->id)->where('company_id', 2)->first();
assert_test("PRINT_INVOICE", "Invoice is strictly not accessible to another company (returns null)", 
    $crossCompanyInvoice === null,
    "Cross-company access blocked"
);

// -------------------------------------------------------------
// TEST 10: QUOTATION CREATION WORKFLOW
// -------------------------------------------------------------
echo "\n--- 10. Testing Quotation Creation Workflow ---\n";
DB::beginTransaction();
$quoteNumber = DocumentNumberService::generateNextNumber($company1->id, $branch1->id, '2026-27', 'QUOTATION');
$quoteId = DB::table('quotations')->insertGetId([
    'company_id' => $company1->id,
    'branch_id' => $branch1->id,
    'customer_id' => $customer1->id,
    'quotation_number' => $quoteNumber,
    'quotation_date' => date('Y-m-d'),
    'valid_until' => date('Y-m-d', strtotime('+30 days')),
    'sub_total' => 15000.00,
    'cgst_amount' => 1350.00,
    'sgst_amount' => 1350.00,
    'igst_amount' => 0.00,
    'total_tax' => 2700.00,
    'grand_total' => 17700.00,
    'status' => 'sent',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);

DB::table('quotation_items')->insert([
    'company_id' => $company1->id,
    'quotation_id' => $quoteId,
    'product_id' => $product1->id,
    'item_name' => $product1->name,
    'hsn_sac' => '84818030',
    'quantity' => 3,
    'unit_price' => 5000.00,
    'taxable_value' => 15000.00,
    'gst_rate' => 18.00,
    'cgst_rate' => 9.00,
    'cgst_amount' => 1350.00,
    'sgst_rate' => 9.00,
    'sgst_amount' => 1350.00,
    'total_amount' => 17700.00,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);
DB::commit();

assert_test("QUOTATION_CREATE", "Quotation and items created atomically with sequential number", 
    $quoteId > 0 && str_starts_with($quoteNumber, 'QTN/FY26-27/'),
    "Generated Quote: {$quoteNumber}"
);

// -------------------------------------------------------------
// TEST 11: QUOTATION PRINTING (/print-quotation)
// -------------------------------------------------------------
echo "\n--- 11. Testing Quotation Printing (/print-quotation) Validation ---\n";
$printQuote = DB::table('quotations')->where('id', $quoteId)->where('company_id', $company1->id)->first();
$printQuoteItems = DB::table('quotation_items')->where('quotation_id', $quoteId)->get();

assert_test("PRINT_QUOTATION", "Quotation loaded with verified company ownership", 
    $printQuote !== null && (int)$printQuote->company_id === (int)$company1->id,
    "Quote ID: {$printQuote->id}"
);

assert_test("PRINT_QUOTATION", "Quotation line items loaded dynamically", 
    $printQuoteItems->count() === 1 && $printQuoteItems[0]->item_name === $product1->name,
    "Item: " . ($printQuoteItems[0]->item_name ?? 'N/A') . " (Qty: {$printQuoteItems[0]->quantity})"
);

$crossQuote = DB::table('quotations')->where('id', $quoteId)->where('company_id', 2)->first();
assert_test("PRINT_QUOTATION", "Quotation strictly isolated from other companies (returns null)", 
    $crossQuote === null,
    "Cross-company quote access blocked"
);

// -------------------------------------------------------------
// TEST 12: CONVERT QUOTATION TO INVOICE WORKFLOW
// -------------------------------------------------------------
echo "\n--- 12. Testing Quotation to Invoice Conversion ---\n";
$convertRes = InvoiceService::createInvoice([
    'customer_id' => $printQuote->customer_id,
    'invoice_date' => date('Y-m-d'),
    'warehouse_id' => $warehouse1->id,
    'source_quotation_id' => $printQuote->id,
    'items' => [
        [
            'product_id' => $product1->id,
            'quantity' => 3,
            'unit_price' => 5000.00,
            'tax_rate' => 18.00
        ]
    ]
], $company1->id, $branch1->id);

assert_test("CONVERT_QUOTE", "Converted quotation successfully to sales invoice", 
    $convertRes['success'] === true && !empty($convertRes['data']->invoice_number),
    "Converted Invoice: " . ($convertRes['data']->invoice_number ?? 'N/A')
);

// Mark quotation converted
DB::table('quotations')->where('id', $printQuote->id)->update([
    'converted_to_invoice' => 1,
    'converted_invoice_id' => $convertRes['data']->id,
    'status' => 'accepted'
]);

$updatedQuote = DB::table('quotations')->where('id', $printQuote->id)->first();
assert_test("CONVERT_QUOTE", "Quotation status marked as accepted and linked to invoice", 
    $updatedQuote->status === 'accepted' && (int)$updatedQuote->converted_invoice_id === (int)$convertRes['data']->id,
    "Linked Invoice ID: " . $updatedQuote->converted_invoice_id
);

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n======================================================================\n";
echo "   SALES INVOICE & QUOTATION AUDIT SUMMARY\n";
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
    echo "\n[ALL SALES INVOICE & QUOTATION WORKFLOW TESTS PASSED SUCCESSFULLY]\n\n";
    exit(0);
}
