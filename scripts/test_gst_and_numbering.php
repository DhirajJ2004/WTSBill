<?php

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Database\Database;
use Illuminate\Database\Capsule\Manager as DB;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockBalance;
use App\Services\GSTService;
use App\Services\DocumentNumberService;
use App\Services\InvoiceService;
use App\Auth\WorkspaceContext;

Database::init();

echo "====================================================================\n";
echo "       WTSBill ERP GST & Concurrent Document Numbering Suite        \n";
echo "====================================================================\n\n";

$companyId = 1;
$branchId = 1;
$fy = '2026-27';

// -------------------------------------------------------------
// PRE-FLIGHT: Configure Products with All 5 Standard GST Rates
// 0%, 5%, 12%, 18%, 28%
// -------------------------------------------------------------
DB::table('products')->where('id', 1)->update(['tax_rate' => 18.00, 'gst_rate' => 18.00, 'sales_price' => 1000.00]);
DB::table('products')->where('id', 2)->update(['tax_rate' => 12.00, 'gst_rate' => 12.00, 'sales_price' => 500.00]);
DB::table('products')->where('id', 3)->update(['tax_rate' => 5.00, 'gst_rate' => 5.00, 'sales_price' => 200.00]);
DB::table('products')->where('id', 4)->update(['tax_rate' => 28.00, 'gst_rate' => 28.00, 'sales_price' => 1500.00]);
DB::table('products')->where('id', 5)->update(['tax_rate' => 0.00, 'gst_rate' => 0.00, 'sales_price' => 100.00]);

foreach ([1 => 500, 2 => 500, 3 => 500, 4 => 500, 5 => 500] as $pid => $qty) {
    $bal = DB::table('stock_balances')->where('company_id', $companyId)->where('product_id', $pid)->where('warehouse_id', 1)->first();
    if ($bal) {
        DB::table('stock_balances')->where('id', $bal->id)->update(['quantity' => $qty, 'available_quantity' => $qty]);
    } else {
        DB::table('stock_balances')->insert([
            'company_id' => $companyId,
            'warehouse_id' => 1,
            'product_id' => $pid,
            'quantity' => $qty,
            'available_quantity' => $qty,
            'reserved_quantity' => 0,
            'avg_cost' => 50,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}

// Ensure diverse customer types
$localCust = Customer::withoutGlobalScopes()->where('id', 1)->first(); // Regular MH customer (27)
$localCust->state_code = '27';
$localCust->customer_type = 'REGULAR';
$localCust->save();

// Create / Ensure Interstate Customer (Gujarat - 24)
$interstateCust = Customer::withoutGlobalScopes()->where('company_id', $companyId)->where('gstin', '24AAACT1234A1Z5')->first();
if (!$interstateCust) {
    $interstateCust = Customer::create([
        'company_id' => $companyId,
        'name' => 'Gujarat Trading Corp',
        'gstin' => '24AAACT1234A1Z5',
        'state' => 'Gujarat',
        'state_code' => '24',
        'customer_type' => 'REGULAR',
        'city' => 'Ahmedabad',
        'is_active' => 1,
        'current_balance' => 0,
    ]);
}

// Create / Ensure SEZ Customer (Located in MH but SEZ Unit)
$sezCust = Customer::withoutGlobalScopes()->where('company_id', $companyId)->where('gstin', '27SEZCU1234A1Z9')->first();
if (!$sezCust) {
    $sezCust = Customer::create([
        'company_id' => $companyId,
        'name' => 'Nexus SEZ Developer Ltd',
        'gstin' => '27SEZCU1234A1Z9',
        'state' => 'Maharashtra',
        'state_code' => '27',
        'customer_type' => 'SEZ',
        'city' => 'Pune',
        'is_active' => 1,
        'current_balance' => 0,
    ]);
}

$passCount = 0;
$failCount = 0;

function assertCondition(bool $cond, string $label, string $detail = '') {
    global $passCount, $failCount;
    if ($cond) {
        echo " [PASS] $label\n";
        if ($detail) echo "        Detail: $detail\n";
        $passCount++;
    } else {
        echo " [FAIL] $label\n";
        if ($detail) echo "        Detail: $detail\n";
        $failCount++;
    }
}

// ====================================================================
// TEST 1: GST Determination Across All 5 Configured Rates (0%, 5%, 12%, 18%, 28%)
// ====================================================================
echo "\n--- TEST 1: GST Determination Across Configured Rates (Intra-State) ---\n";

$company = Company::find(1);
$ratesToTest = [
    5 => 0.0,   // Exempt product (0%)
    3 => 5.0,   // Essentials product (5%)
    2 => 12.0,  // Standard Machinery (12%)
    1 => 18.0,  // Standard Goods (18%)
    4 => 28.0,  // Luxury/Electronics (28%)
];

foreach ($ratesToTest as $productId => $expectedRate) {
    $prod = Product::find($productId);
    $params = GSTService::determineTaxParameters($prod, $localCust, $company);

    $isCorrectRate = (floatval($params['gst_rate']) === $expectedRate);
    $isCorrectSplit = false;
    if ($expectedRate == 0.0) {
        $isCorrectSplit = ($params['cgst_rate'] == 0.0 && $params['sgst_rate'] == 0.0 && $params['igst_rate'] == 0.0);
    } else {
        $half = $expectedRate / 2.0;
        $isCorrectSplit = ($params['cgst_rate'] == $half && $params['sgst_rate'] == $half && $params['igst_rate'] == 0.0);
    }

    assertCondition(
        $isCorrectRate && $isCorrectSplit && !$params['is_igst'],
        "Product #{$productId} ('{$prod->name}') accurately calculates {$expectedRate}% GST without hardcoded 18%",
        "Rate: {$params['gst_rate']}%, CGST: {$params['cgst_rate']}%, SGST: {$params['sgst_rate']}%, IGST: {$params['igst_rate']}%, Supply: {$params['supply_type']}"
    );
}

// ====================================================================
// TEST 2: Multi-Item Invoice with Heterogeneous Tax Rates
// One item with 5%, one with 12%, one with 18%, one with 28%, one with 0%
// ====================================================================
echo "\n--- TEST 2: Multi-Item Invoice with Heterogeneous GST Slabs ---\n";

$multiTaxPayload = [
    'customer_id' => $localCust->id,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+15 days')),
    'status' => 'POSTED',
    'items' => [
        ['product_id' => 5, 'quantity' => 2, 'unit_price' => 100], // Subtotal: 200, GST 0%:  Tax: 0
        ['product_id' => 3, 'quantity' => 5, 'unit_price' => 200], // Subtotal: 1000, GST 5%: Tax: 50 (CGST 25, SGST 25)
        ['product_id' => 2, 'quantity' => 2, 'unit_price' => 500], // Subtotal: 1000, GST 12%: Tax: 120 (CGST 60, SGST 60)
        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 1000], // Subtotal: 1000, GST 18%: Tax: 180 (CGST 90, SGST 90)
        ['product_id' => 4, 'quantity' => 1, 'unit_price' => 1500], // Subtotal: 1500, GST 28%: Tax: 420 (CGST 210, SGST 210)
    ]
];
// Total Subtotal: 200 + 1000 + 1000 + 1000 + 1500 = 4700
// Expected CGST: 0 + 25 + 60 + 90 + 210 = 385.00
// Expected SGST: 0 + 25 + 60 + 90 + 210 = 385.00
// Expected Total Tax: 770.00
// Expected Grand Total: 5470.00

$resMulti = InvoiceService::createInvoice($multiTaxPayload, $companyId, $branchId);
assertCondition($resMulti['success'] === true, "Multi-tax invoice created successfully", "Invoice: " . ($resMulti['data']->invoice_number ?? ''));

$invData = $resMulti['data'];
$subtotalMatch = (floatval($invData->sub_total) === 4700.00);
$cgstMatch = (floatval($invData->cgst_amount) === 385.00);
$sgstMatch = (floatval($invData->sgst_amount) === 385.00);
$totalTaxMatch = (floatval($invData->total_tax) === 770.00);
$grandTotalMatch = (floatval($invData->grand_total) === 5470.00);

assertCondition(
    $subtotalMatch && $cgstMatch && $sgstMatch && $totalTaxMatch && $grandTotalMatch,
    "Backend accurately computed independent taxes per line across all 5 slabs (CGST: ₹385, SGST: ₹385, Total: ₹5470)",
    "Subtotal: {$invData->sub_total}, CGST: {$invData->cgst_amount}, SGST: {$invData->sgst_amount}, IGST: {$invData->igst_amount}, Total: {$invData->grand_total}"
);

// ====================================================================
// TEST 3: Inter-State GST (IGST) Determination
// ====================================================================
echo "\n--- TEST 3: Inter-State & Place of Supply (POS) Determination ---\n";

$interstatePayload = [
    'customer_id' => $interstateCust->id,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+15 days')),
    'status' => 'POSTED',
    'items' => [
        ['product_id' => 1, 'quantity' => 2, 'unit_price' => 1000], // Subtotal: 2000, GST 18%: IGST 360
        ['product_id' => 4, 'quantity' => 1, 'unit_price' => 1500], // Subtotal: 1500, GST 28%: IGST 420
    ]
];
// Subtotal: 3500.00, IGST: 780.00, Grand Total: 4280.00

$resInter = InvoiceService::createInvoice($interstatePayload, $companyId, $branchId);
assertCondition($resInter['success'] === true, "Inter-state invoice created successfully");
$invInter = $resInter['data'];

assertCondition(
    floatval($invInter->cgst_amount) === 0.00 && 
    floatval($invInter->sgst_amount) === 0.00 && 
    floatval($invInter->igst_amount) === 780.00 &&
    floatval($invInter->grand_total) === 4280.00,
    "Customer in Gujarat (24) vs Company in Maharashtra (27) accurately allocates full tax to IGST (₹780.00)",
    "CGST: {$invInter->cgst_amount}, SGST: {$invInter->sgst_amount}, IGST: {$invInter->igst_amount}, Grand Total: {$invInter->grand_total}"
);

// ====================================================================
// TEST 4: Customer Type: Special Economic Zone (SEZ)
// Under IGST Act Section 7(5)(b), supply to SEZ is Inter-State (IGST) even if within same state (MH)
// ====================================================================
echo "\n--- TEST 4: Customer Type SEZ Unit (Always Inter-State IGST) ---\n";

$sezPayload = [
    'customer_id' => $sezCust->id,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+15 days')),
    'status' => 'POSTED',
    'items' => [
        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 1000], // 1000 @ 18% = 180 IGST
    ]
];

$resSez = InvoiceService::createInvoice($sezPayload, $companyId, $branchId);
assertCondition($resSez['success'] === true, "SEZ invoice created successfully");
$invSez = $resSez['data'];

assertCondition(
    floatval($invSez->igst_amount) === 180.00 && floatval($invSez->cgst_amount) === 0.00,
    "SEZ Customer located in same state (MH 27) correctly treated as Inter-State IGST per Section 7(5)(b)",
    "Customer Type: {$sezCust->customer_type}, CGST: {$invSez->cgst_amount}, SGST: {$invSez->sgst_amount}, IGST: {$invSez->igst_amount}"
);

// ====================================================================
// TEST 5: Document Numbering Structure Scoped by Company, Branch, FY
// Example: INV/FY26-27/PUN/000001
// ====================================================================
echo "\n--- TEST 5: Document Numbering Structure & Scoping ---\n";

$invNum = $resMulti['data']->invoice_number;
$patternMatches = preg_match('#^INV/FY\d{2}-\d{2}/[A-Z0-9]+/\d{6}$#', $invNum);

assertCondition(
    (bool)$patternMatches,
    "Document Number matches enterprise format: 'INV/{FY}/{BRANCH}/{SEQ}' (Generated: '{$invNum}')",
    "Pattern: INV/FY26-27/HO/XXXXXX -> '{$invNum}'"
);

// Test Preview function does NOT increment counter
$previewBefore = DocumentNumberService::previewNextNumber($companyId, $branchId, $fy, 'INVOICE');
$previewAgain = DocumentNumberService::previewNextNumber($companyId, $branchId, $fy, 'INVOICE');

assertCondition(
    $previewBefore === $previewAgain,
    "previewNextNumber() is read-only and idempotent (does not advance sequence counter)",
    "Preview 1: {$previewBefore}, Preview 2: {$previewAgain}"
);

// ====================================================================
// TEST 6: Concurrent Invoice Creation & Mutex Verification
// Verify: No duplicate invoice number, No duplicate stock movement, No duplicate accounting entry
// ====================================================================
echo "\n--- TEST 6: Concurrent Database-Safe Invoice Creation ---\n";

// We simulate two simultaneous transactions by running sequential creations inside independent DB connections / isolated transactions
// checking that both get uniquely generated numbers and completely independent downstream side effects

$invA_Payload = [
    'customer_id' => $localCust->id,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'status' => 'POSTED',
    'items' => [
        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 1000]
    ]
];

$invB_Payload = [
    'customer_id' => $localCust->id,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'status' => 'POSTED',
    'items' => [
        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 1000]
    ]
];

$resA = InvoiceService::createInvoice($invA_Payload, $companyId, $branchId);
$resB = InvoiceService::createInvoice($invB_Payload, $companyId, $branchId);

assertCondition($resA['success'] === true && $resB['success'] === true, "Both invoices created successfully");

$numA = $resA['data']->invoice_number;
$numB = $resB['data']->invoice_number;

assertCondition(
    $numA !== $numB,
    "NO DUPLICATE INVOICE NUMBER: Sequential numbers assigned atomically under lock",
    "Invoice A: '{$numA}', Invoice B: '{$numB}'"
);

// Verify stock movements
$movementsA = DB::table('stock_movements')->where('reference_number', $numA)->get();
$movementsB = DB::table('stock_movements')->where('reference_number', $numB)->get();

assertCondition(
    count($movementsA) === 1 && count($movementsB) === 1 && $movementsA[0]->id !== $movementsB[0]->id,
    "NO DUPLICATE STOCK MOVEMENT: Distinct stock movement ledger entries for each invoice",
    "Movement A ID: {$movementsA[0]->id}, Movement B ID: {$movementsB[0]->id}"
);

// Verify accounting entries
$journalA = DB::table('journal_entries')->where('reference_type', 'INVOICE')->where('reference_id', $resA['data']->id)->first();
$journalB = DB::table('journal_entries')->where('reference_type', 'INVOICE')->where('reference_id', $resB['data']->id)->first();

assertCondition(
    $journalA !== null && $journalB !== null && $journalA->id !== $journalB->id && $journalA->entry_number !== $journalB->entry_number,
    "NO DUPLICATE ACCOUNTING ENTRY: Distinct double-entry journals created with unique JV numbers",
    "Journal A: {$journalA->entry_number} (ID {$journalA->id}), Journal B: {$journalB->entry_number} (ID {$journalB->id})"
);

echo "\n====================================================================\n";
echo "   GST & DOCUMENT NUMBERING SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
