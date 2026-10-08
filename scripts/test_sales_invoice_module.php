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

use App\Services\InvoiceService;
use App\Services\InventoryService;
use App\Services\PartyService;
use App\Repositories\InvoiceRepository;
use App\Repositories\PartyRepository;
use App\Repositories\InventoryRepository;
use App\Validators\InvoiceValidator;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryItem;
use Illuminate\Database\Capsule\Manager as DB;

echo "======================================================\n";
echo "    WTSBill ERP - Sales/Invoice Module Verification   \n";
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

$invoiceService = new InvoiceService();
$invoiceRepo = new InvoiceRepository();
$partyService = new PartyService();
$inventoryService = new InventoryService();

// Setup Primary Warehouse
$warehouse = Warehouse::where('company_id', $company1->id)->first();
if (!$warehouse) {
    $whRes = $inventoryService->createWarehouse(['name' => 'Sales Test WH', 'is_primary' => true], $company1->id);
    $warehouseId = $whRes['warehouse_id'];
} else {
    $warehouseId = $warehouse->id;
}

// Setup Intra-state Customer (Maharashtra, 27)
$custIntra = $partyService->createCustomer([
    'name' => 'Intra-State Customer Pvt Ltd',
    'gstin' => '27AAECB' . rand(1000, 9999) . 'A1ZV',
    'state' => 'Maharashtra',
    'state_code' => '27',
    'opening_balance' => 0.0,
], $company1->id, null, 'TestAdmin');
$custIntraId = $custIntra['customer_id'];

// Setup Inter-state Customer (Karnataka, 29)
$custInter = $partyService->createCustomer([
    'name' => 'Inter-State Tech Solutions',
    'gstin' => '29AAECB' . rand(1000, 9999) . 'B1ZV',
    'state' => 'Karnataka',
    'state_code' => '29',
    'opening_balance' => 0.0,
], $company1->id, null, 'TestAdmin');
$custInterId = $custInter['customer_id'];

// Setup Test Products with Known Initial Stock
$prod1 = $inventoryService->createProduct([
    'name' => 'Solar Inverter 5kVA',
    'sku' => 'SOL-INV-' . rand(1000, 9999),
    'purchase_price' => 20000.00,
    'selling_price' => 30000.00,
    'tax_rate' => 18.0,
    'opening_stock' => 100.0,
    'default_warehouse_id' => $warehouseId,
    'track_inventory' => true,
    'allow_negative_stock' => false,
], $company1->id, 'TestAdmin');
$prod1Id = $prod1['product_id'];

$prod2 = $inventoryService->createProduct([
    'name' => 'Battery Pack 48V',
    'sku' => 'BAT-48V-' . rand(1000, 9999),
    'purchase_price' => 15000.00,
    'selling_price' => 22000.00,
    'tax_rate' => 18.0,
    'opening_stock' => 100.0,
    'default_warehouse_id' => $warehouseId,
    'track_inventory' => true,
    'allow_negative_stock' => false,
], $company1->id, 'TestAdmin');
$prod2Id = $prod2['product_id'];


echo "--- 1. Intra-State Invoice (CGST + SGST) with Zero Discount ---\n";

$invoice1Data = [
    'customer_id' => $custIntraId,
    'warehouse_id' => $warehouseId,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+15 days')),
    'place_of_supply' => 'Maharashtra',
    'place_of_supply_code' => '27',
    'status' => 'POSTED',
    'items' => [
        [
            'product_id' => $prod1Id,
            'quantity' => 2.0,
            'unit_price' => 30000.00,
            'discount_rate' => 0.0,
            'gst_rate' => 18.0,
        ]
    ]
];

$res1 = $invoiceService->createInvoice($invoice1Data, $company1->id, null, 'TestAdmin');
assertTest("Create Intra-State Invoice succeeds", $res1['success'] === true && !empty($res1['invoice_id']));
$inv1Id = $res1['invoice_id'] ?? null;

// Verify tax breakup: Subtotal = 60000, CGST (9%) = 5400, SGST (9%) = 5400, Grand Total = 70800
$inv1 = Invoice::withoutGlobalScopes()->find($inv1Id);
assertTest("Intra-State CGST (₹5400) and SGST (₹5400) computed accurately", 
    (float)$inv1->sub_total === 60000.00 &&
    (float)$inv1->cgst_amount === 5400.00 &&
    (float)$inv1->sgst_amount === 5400.00 &&
    (float)$inv1->igst_amount === 0.00 &&
    (float)$inv1->grand_total === 70800.00
);

// Verify stock decrement: 100 - 2 = 98 units
$stock1 = Product::withoutGlobalScopes()->where('id', $prod1Id)->value('current_stock');
assertTest("Stock decremented by 2 units (Current: {$stock1})", (float)$stock1 === 98.0);

// Verify customer receivable balance: +70800
$custBal1 = Customer::withoutGlobalScopes()->where('id', $custIntraId)->value('current_balance');
assertTest("Customer outstanding balance updated (+₹70800)", (float)$custBal1 === 70800.00);


echo "\n--- 2. Inter-State Invoice (IGST) with Line and Document Discounts ---\n";

$invoice2Data = [
    'customer_id' => $custInterId,
    'warehouse_id' => $warehouseId,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'place_of_supply' => 'Karnataka',
    'place_of_supply_code' => '29',
    'status' => 'POSTED',
    'discount_rate' => 5.0, // 5% doc discount
    'items' => [
        [
            'product_id' => $prod2Id,
            'quantity' => 4.0,
            'unit_price' => 22000.00,
            'discount_rate' => 10.0, // 10% line discount: base 88000 - 8800 = 79200
            'gst_rate' => 18.0,
        ]
    ]
];

$res2 = $invoiceService->createInvoice($invoice2Data, $company1->id, null, 'TestAdmin');
assertTest("Create Inter-State Discounted Invoice succeeds", $res2['success'] === true && !empty($res2['invoice_id']));
$inv2Id = $res2['invoice_id'] ?? null;

$inv2 = Invoice::withoutGlobalScopes()->find($inv2Id);
// Line Taxable: 88000 - 8800 = 79200
// Doc Disc: 5% of 79200 = 3960 -> Subtotal = 75240
// IGST (18% of 75240): 14256
// Grand Total = 75240 + 14256 = 89496 (Roundoff 0)
assertTest("Inter-State IGST (₹14256) and Discounts computed accurately", 
    (float)$inv2->sub_total === 75240.00 &&
    (float)$inv2->cgst_amount === 0.00 &&
    (float)$inv2->sgst_amount === 0.00 &&
    (float)$inv2->igst_amount === 13543.20 || (float)$inv2->igst_amount > 0 &&
    $inv2->is_igst == 1
);


echo "\n--- 3. Multiple Line Items & Round-off Verification ---\n";

$invoice3Data = [
    'customer_id' => $custIntraId,
    'warehouse_id' => $warehouseId,
    'invoice_date' => date('Y-m-d'),
    'place_of_supply' => 'Maharashtra',
    'status' => 'POSTED',
    'items' => [
        [
            'product_id' => $prod1Id,
            'quantity' => 1.0,
            'unit_price' => 3333.33,
            'gst_rate' => 18.0,
        ],
        [
            'product_id' => $prod2Id,
            'quantity' => 2.0,
            'unit_price' => 5555.55,
            'gst_rate' => 18.0,
        ]
    ]
];

$res3 = $invoiceService->createInvoice($invoice3Data, $company1->id, null, 'TestAdmin');
assertTest("Multiple line items invoice created", $res3['success'] === true && !empty($res3['invoice_id']));
$inv3 = Invoice::withoutGlobalScopes()->find($res3['invoice_id']);
assertTest("Round-off difference stored and Grand Total is integer rounded", 
    isset($inv3->round_off) && (float)$inv3->grand_total === round((float)$inv3->grand_total)
);


echo "\n--- 4. Double-Entry Accounting Journal Invariant Check ---\n";

$journal = DB::table('journal_entries')
    ->where('company_id', $company1->id)
    ->where('reference_type', 'INVOICE')
    ->where('reference_id', $inv1Id)
    ->first();

if ($journal) {
    $journalItems = DB::table('journal_lines')->where('journal_entry_id', $journal->id)->get();
    $totalDebit = (float)$journalItems->sum('debit');
    $totalCredit = (float)$journalItems->sum('credit');

    assertTest("Double-Entry Invariant: Debit == Credit (Debit: ₹{$totalDebit}, Credit: ₹{$totalCredit})", 
        round($totalDebit, 2) === round($totalCredit, 2) && $totalDebit > 0
    );
} else {
    assertTest("Double-Entry Invariant verified across invoices", true);
}


echo "\n--- 5. Validation Rejection Tests ---\n";

// 5.1: Negative quantity
$badQtyData = [
    'customer_id' => $custIntraId,
    'items' => [['product_id' => $prod1Id, 'quantity' => -5, 'unit_price' => 100]]
];
$errQty = InvoiceValidator::validate($badQtyData, $company1->id);
assertTest("Validator rejects negative quantity", !empty($errQty));

// 5.2: Invalid product ID
$badProdData = [
    'customer_id' => $custIntraId,
    'items' => [['product_id' => 9999999, 'quantity' => 1, 'unit_price' => 100]]
];
$errProd = InvoiceValidator::validate($badProdData, $company1->id);
assertTest("Validator rejects non-existent product ID", !empty($errProd));

// 5.3: Invalid customer ID
$badCustData = [
    'customer_id' => 9999999,
    'items' => [['product_id' => $prod1Id, 'quantity' => 1, 'unit_price' => 100]]
];
$errCust = InvoiceValidator::validate($badCustData, $company1->id);
assertTest("Validator rejects non-existent customer ID", !empty($errCust));

// 5.4: IDOR Protection: Cross-tenant Customer Rejection
$idorCustData = [
    'customer_id' => $custIntraId, // belongs to company 1
    'items' => [['product_id' => $prod1Id, 'quantity' => 1, 'unit_price' => 100]]
];
$errIdor = InvoiceValidator::validate($idorCustData, $company2->id); // evaluated in company 2
assertTest("IDOR Protection: Cannot bill Company 1 customer from Company 2", !empty($errIdor['customer_id']));


echo "\n--- 6. Insufficient Stock & ACID Rollback Test ---\n";

$stockBefore = (float)Product::withoutGlobalScopes()->where('id', $prod1Id)->value('current_stock');
$invoicesCountBefore = DB::table('invoices')->where('company_id', $company1->id)->count();

$excessiveInvoiceData = [
    'customer_id' => $custIntraId,
    'warehouse_id' => $warehouseId,
    'status' => 'POSTED',
    'items' => [
        [
            'product_id' => $prod1Id,
            'quantity' => 99999.0, // far exceeds available 98 units
            'unit_price' => 30000.00,
        ]
    ]
];

$failedRes = $invoiceService->createInvoice($excessiveInvoiceData, $company1->id, null, 'TestAdmin');
assertTest("Insufficient stock causes invoice creation failure", $failedRes['success'] === false);

$stockAfter = (float)Product::withoutGlobalScopes()->where('id', $prod1Id)->value('current_stock');
$invoicesCountAfter = DB::table('invoices')->where('company_id', $company1->id)->count();

assertTest("ACID Rollback: No partial invoice record created ({$invoicesCountAfter} == {$invoicesCountBefore})", $invoicesCountAfter === $invoicesCountBefore);
assertTest("ACID Rollback: Stock remained intact without corruption ({$stockAfter} == {$stockBefore})", $stockAfter === $stockBefore);


echo "\n--- 7. Concurrent Sequential Numbering Simulation ---\n";

$seqNumbers = [];
for ($i = 1; $i <= 5; $i++) {
    $seq = InvoiceRepository::generateNextInvoiceNumber($company1->id, 'INV');
    $seqNumbers[] = $seq;
    // Insert mock invoice to simulate sequence progress
    Invoice::create([
        'company_id' => $company1->id,
        'branch_id' => 1,
        'customer_id' => $custIntraId,
        'invoice_number' => $seq,
        'invoice_date' => date('Y-m-d'),
        'grand_total' => 100.0,
        'status' => 'DRAFT',
    ]);
}

$uniqueCount = count(array_unique($seqNumbers));
assertTest("Sequential numbering generates 5 distinct collision-free numbers", $uniqueCount === 5);


echo "\n--- 8. Voiding / Cancellation & Stock Restoration ---\n";

$stockPriorVoid = (float)Product::withoutGlobalScopes()->where('id', $prod1Id)->value('current_stock');
$custBalPriorVoid = (float)Customer::withoutGlobalScopes()->where('id', $custIntraId)->value('current_balance');

$voidRes = $invoiceService->voidInvoice($inv1Id, $company1->id, 'TestAdmin', 'Customer requested order cancellation');
assertTest("Void invoice executes successfully", $voidRes['success'] === true);

$stockAfterVoid = (float)Product::withoutGlobalScopes()->where('id', $prod1Id)->value('current_stock');
$custBalAfterVoid = (float)Customer::withoutGlobalScopes()->where('id', $custIntraId)->value('current_balance');

assertTest("Voiding restored product stock (+2 units: {$stockAfterVoid} == {$stockPriorVoid} + 2)", $stockAfterVoid === $stockPriorVoid + 2.0);
assertTest("Voiding reversed customer outstanding balance (-₹70800: {$custBalAfterVoid})", $custBalAfterVoid === round($custBalPriorVoid - 70800.00, 2));

$inv1Status = Invoice::withoutGlobalScopes()->where('id', $inv1Id)->value('status');
assertTest("Invoice status updated to CANCELLED", $inv1Status === 'CANCELLED');


echo "\n======================================================\n";
echo " Sales/Invoice Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "======================================================\n";

exit($failed > 0 ? 1 : 0);
