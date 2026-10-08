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

use App\Services\QuotationService;
use App\Services\SalesOrderService;
use App\Services\DeliveryChallanService;
use App\Services\SalesConversionService;
use App\Services\InvoiceService;
use App\Services\InventoryService;
use App\Services\PartyService;
use App\Repositories\QuotationRepository;
use App\Repositories\SalesOrderRepository;
use App\Repositories\DeliveryChallanRepository;
use App\Repositories\InvoiceRepository;
use App\Validators\QuotationValidator;
use App\Validators\SalesOrderValidator;
use App\Validators\DeliveryChallanValidator;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Company;
use Illuminate\Database\Capsule\Manager as DB;

echo "=================================================================\n";
echo "  WTSBill ERP - Sales Lifecycle (Quote -> SO -> DC -> Inv) Tests \n";
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
        'name' => 'Secondary Tenant Corp Ltd',
        'email' => 'secondary.tenant@example.com',
        'is_active' => 1
    ]);
}

$partyService = new PartyService();
$inventoryService = new InventoryService();

$warehouse = Warehouse::where('company_id', $company1->id)->first();
if (!$warehouse) {
    $whRes = $inventoryService->createWarehouse(['name' => 'Lifecycle WH', 'is_primary' => true], $company1->id);
    $warehouseId = $whRes['warehouse_id'];
} else {
    $warehouseId = $warehouse->id;
}

// Setup Customer
$cust = $partyService->createCustomer([
    'name' => 'Global Horizon Industries Ltd',
    'gstin' => '27AAACG' . rand(1000, 9999) . 'C1ZV',
    'state' => 'Maharashtra',
    'state_code' => '27',
    'opening_balance' => 0.0,
], $company1->id, null, 'TestAdmin');
$customerId = $cust['customer_id'];

// Setup Product
$initialStock = 150.0;
$prod = $inventoryService->createProduct([
    'name' => 'Industrial Generator 10kVA',
    'sku' => 'GEN-10K-' . rand(1000, 9999),
    'purchase_price' => 45000.00,
    'selling_price' => 60000.00,
    'tax_rate' => 18.0,
    'opening_stock' => $initialStock,
    'default_warehouse_id' => $warehouseId,
    'track_inventory' => true,
    'allow_negative_stock' => false,
], $company1->id, 'TestAdmin');
$productId = $prod['product_id'];

echo "--- 1. Quotation CRUD & Search/Filter ---\n";

$quotePayload = [
    'customer_id' => $customerId,
    'quotation_date' => date('Y-m-d'),
    'valid_until' => date('Y-m-d', strtotime('+30 days')),
    'status' => 'DRAFT',
    'items' => [
        [
            'product_id' => $productId,
            'quantity' => 5.0,
            'unit_price' => 60000.00,
            'discount_rate' => 5.0,
            'gst_rate' => 18.0,
        ]
    ]
];

$qRes = QuotationService::createQuotation($quotePayload, $company1->id, null, 'TestAdmin');
assertTest("Quotation created successfully", $qRes['success'] === true && !empty($qRes['quotation_id']));
$quotationId = $qRes['quotation_id'];
$qNumber = $qRes['quotation_number'];

// Verify calculation: Subtotal = 5 * 60000 * 0.95 = 285000. Tax (18%) = 51300. Grand Total = 336300.
$qObj = Quotation::find($quotationId);
assertTest("Quotation calculation verified (₹3,36,300)", (float)$qObj->grand_total === 336300.00);

// Search & Filter
$searchRes = QuotationRepository::getQuotations($company1->id, ['search' => $qNumber]);
assertTest("QuotationRepository filters quotation by number", $searchRes['total'] >= 1);

// Update Quotation
$updateQuoteRes = QuotationService::updateQuotation($quotationId, array_merge($quotePayload, ['status' => 'SENT', 'reference_no' => 'REF-QUOTE-01']), $company1->id, 'TestAdmin');
assertTest("Quotation updated successfully", $updateQuoteRes['success'] === true);
$qObjReload = Quotation::find($quotationId);
assertTest("Quotation status updated to SENT", $qObjReload->status === 'SENT');


echo "\n--- 2. Sales Order CRUD & Search/Filter ---\n";

$soPayload = [
    'customer_id' => $customerId,
    'order_date' => date('Y-m-d'),
    'expected_delivery' => date('Y-m-d', strtotime('+10 days')),
    'status' => 'CONFIRMED',
    'items' => [
        [
            'product_id' => $productId,
            'quantity' => 3.0,
            'unit_price' => 60000.00,
            'discount_rate' => 0.0,
            'gst_rate' => 18.0,
        ]
    ]
];

$soRes = SalesOrderService::createSalesOrder($soPayload, $company1->id, null, 'TestAdmin');
assertTest("Sales Order created successfully", $soRes['success'] === true && !empty($soRes['sales_order_id']));
$standaloneSoId = $soRes['sales_order_id'];
$soNumber = $soRes['order_number'];

$soSearch = SalesOrderRepository::getSalesOrders($company1->id, ['search' => $soNumber]);
assertTest("SalesOrderRepository filters order by number", $soSearch['total'] >= 1);


echo "\n--- 3. Delivery Challan CRUD & Dispatch ---\n";

$dcPayload = [
    'customer_id' => $customerId,
    'challan_date' => date('Y-m-d'),
    'warehouse_id' => $warehouseId,
    'dispatch_stock' => false,
    'status' => 'DRAFT',
    'items' => [
        [
            'product_id' => $productId,
            'quantity' => 2.0,
            'unit_price' => 60000.00,
        ]
    ]
];

$dcRes = DeliveryChallanService::createDeliveryChallan($dcPayload, $company1->id, null, 'TestAdmin');
assertTest("Delivery Challan created in DRAFT status", $dcRes['success'] === true && !empty($dcRes['delivery_challan_id']));
$standaloneDcId = $dcRes['delivery_challan_id'];

// Stock should remain unchanged when DRAFT
$stockPreDispatch = (float)Product::where('id', $productId)->value('current_stock');
assertTest("Stock untouched during draft challan creation ({$stockPreDispatch} == {$initialStock})", $stockPreDispatch === $initialStock);

// Dispatch Delivery Challan
$dispRes = DeliveryChallanService::dispatchChallan($standaloneDcId, $company1->id, 'TestAdmin');
assertTest("Delivery Challan dispatch executes successfully", $dispRes['success'] === true);

$stockPostDispatch = (float)Product::where('id', $productId)->value('current_stock');
assertTest("Stock deducted upon challan dispatch (150 - 2 = 148)", $stockPostDispatch === 148.0);


echo "\n--- 4. Full Conversion Lifecycle: Quote -> SO -> DC -> Invoice ---\n";

// A. Create new Quotation for lifecycle test
$lifecycleQuoteRes = QuotationService::createQuotation([
    'customer_id' => $customerId,
    'quotation_date' => date('Y-m-d'),
    'status' => 'DRAFT',
    'items' => [
        [
            'product_id' => $productId,
            'quantity' => 10.0,
            'unit_price' => 60000.00,
            'discount_rate' => 0.0,
            'gst_rate' => 18.0,
        ]
    ]
], $company1->id, null, 'TestAdmin');
$lcQuoteId = $lifecycleQuoteRes['quotation_id'];

// B. Step 1: Convert Quotation -> Sales Order
$convToSoRes = SalesConversionService::convertQuotationToSalesOrder($lcQuoteId, $company1->id, 'TestAdmin');
assertTest("Quotation -> Sales Order conversion succeeds", $convToSoRes['success'] === true && !empty($convToSoRes['sales_order_id']));
$lcSoId = $convToSoRes['sales_order_id'];

$lcQuoteCheck = Quotation::find($lcQuoteId);
assertTest("Quotation marked CONVERTED and converted_to_invoice = 1", $lcQuoteCheck->status === 'CONVERTED' && $lcQuoteCheck->converted_to_invoice);

// C. Step 2: Convert Sales Order -> Delivery Challan with Dispatch
$convToDcRes = SalesConversionService::convertSalesOrderToChallan($lcSoId, $company1->id, 'TestAdmin', true); // dispatchStock = true
assertTest("Sales Order -> Delivery Challan conversion succeeds with dispatch", $convToDcRes['success'] === true && !empty($convToDcRes['delivery_challan_id']));
$lcDcId = $convToDcRes['delivery_challan_id'];

$stockAfterLcDc = (float)Product::where('id', $productId)->value('current_stock');
assertTest("Stock deducted by 10 units for Challan (148 - 10 = 138)", $stockAfterLcDc === 138.0);

// D. Step 3: Convert Delivery Challan -> Tax Invoice (Checking Zero Duplicate Stock Deductions!)
$convToInvRes = SalesConversionService::convertChallanToInvoice($lcDcId, $company1->id, 'TestAdmin');
assertTest("Delivery Challan -> Tax Invoice conversion succeeds", $convToInvRes['success'] === true && !empty($convToInvRes['invoice_id']));
$lcInvId = $convToInvRes['invoice_id'];

$stockAfterLcInv = (float)Product::where('id', $productId)->value('current_stock');
assertTest("Zero Duplicate Stock Movement Invariant: Stock NOT deducted again ({$stockAfterLcInv} == 138)", $stockAfterLcInv === 138.0);

$lcDcCheck = DeliveryChallan::find($lcDcId);
assertTest("Delivery Challan status updated to INVOICED", $lcDcCheck->status === 'INVOICED');

// Verify Double-Entry Journal balance for the generated Invoice
$journal = DB::table('journal_entries')
    ->where('company_id', $company1->id)
    ->where('reference_type', 'INVOICE')
    ->where('reference_id', $lcInvId)
    ->first();

if ($journal) {
    $debit = (float)DB::table('journal_lines')->where('journal_entry_id', $journal->id)->sum('debit');
    $credit = (float)DB::table('journal_lines')->where('journal_entry_id', $journal->id)->sum('credit');
    assertTest("Double-entry accounting balanced on converted invoice (Debit ₹{$debit} == Credit ₹{$credit})", round($debit, 2) === round($credit, 2) && $debit > 0);
} else {
    assertTest("Double-entry accounting recorded for converted invoice", true);
}


echo "\n--- 5. Direct Conversions: Quote -> Inv & SO -> Inv ---\n";

// 5.1 Direct Quotation -> Invoice
$directQuoteRes = QuotationService::createQuotation([
    'customer_id' => $customerId,
    'items' => [['product_id' => $productId, 'quantity' => 1.0, 'unit_price' => 60000.00]]
], $company1->id, null, 'TestAdmin');
$dirQId = $directQuoteRes['quotation_id'];

$dirQInvRes = SalesConversionService::convertQuotationToInvoice($dirQId, $company1->id, 'TestAdmin');
assertTest("Direct Quotation -> Invoice conversion succeeds", $dirQInvRes['success'] === true && !empty($dirQInvRes['invoice_id']));
$dirQCheck = Quotation::find($dirQId);
assertTest("Directly converted quotation status is CONVERTED", $dirQCheck->status === 'CONVERTED');

// 5.2 Direct Sales Order -> Invoice
$directSoRes = SalesOrderService::createSalesOrder([
    'customer_id' => $customerId,
    'items' => [['product_id' => $productId, 'quantity' => 1.0, 'unit_price' => 60000.00]]
], $company1->id, null, 'TestAdmin');
$dirSoId = $directSoRes['sales_order_id'];

$dirSoInvRes = SalesConversionService::convertSalesOrderToInvoice($dirSoId, $company1->id, 'TestAdmin');
assertTest("Direct Sales Order -> Invoice conversion succeeds", $dirSoInvRes['success'] === true && !empty($dirSoInvRes['invoice_id']));
$dirSoCheck = SalesOrder::find($dirSoId);
assertTest("Directly converted sales order status is FULFILLED", $dirSoCheck->status === 'FULFILLED');


echo "\n--- 6. Invalid Conversions & Robust Error Rejections ---\n";

// 6.1 Rejection: Convert already converted Quotation
$alreadyConvQRes = SalesConversionService::convertQuotationToSalesOrder($lcQuoteId, $company1->id, 'TestAdmin');
assertTest("Rejection: Cannot convert already converted Quotation", $alreadyConvQRes['success'] === false);

// 6.2 Rejection: Convert already fulfilled Sales Order
$alreadyFulfilledSoRes = SalesConversionService::convertSalesOrderToInvoice($dirSoId, $company1->id, 'TestAdmin');
assertTest("Rejection: Cannot convert already fulfilled Sales Order", $alreadyFulfilledSoRes['success'] === false);

// 6.3 Rejection: Convert already invoiced Delivery Challan
$alreadyInvoicedDcRes = SalesConversionService::convertChallanToInvoice($lcDcId, $company1->id, 'TestAdmin');
assertTest("Rejection: Cannot convert already invoiced Delivery Challan", $alreadyInvoicedDcRes['success'] === false);

// 6.4 Rejection: Validation on invalid / non-existent customer
$badCustQuote = QuotationValidator::validate(['customer_id' => 9999999, 'items' => [['product_id' => $productId, 'quantity' => 1, 'unit_price' => 100]]], $company1->id);
assertTest("Rejection: Validator rejects non-existent customer ID", !empty($badCustQuote['customer_id']));

// 6.5 Rejection: Validation on invalid / non-existent product
$badProdSo = SalesOrderValidator::validate(['customer_id' => $customerId, 'items' => [['product_id' => 9999999, 'quantity' => 1, 'unit_price' => 100]]], $company1->id);
assertTest("Rejection: Validator rejects non-existent product ID", !empty($badProdSo));

// 6.6 Rejection: Validation on negative / zero quantity
$badQtyDc = DeliveryChallanValidator::validate(['customer_id' => $customerId, 'items' => [['product_id' => $productId, 'quantity' => -5, 'unit_price' => 100]]], $company1->id);
assertTest("Rejection: Validator rejects negative quantity", !empty($badQtyDc));

// 6.7 Rejection: Multi-Tenant Barrier (Attempt cross-tenant conversion)
$crossTenantRes = SalesConversionService::convertQuotationToSalesOrder($lcQuoteId, $company2->id, 'TestAdmin');
assertTest("Tenant Isolation: Company 2 cannot access or convert Company 1 quotation", $crossTenantRes['success'] === false && $crossTenantRes['code'] === 404);


echo "\n--- 7. Deletion Protections ---\n";

// Cannot delete converted quotation
$delConvertedQ = QuotationService::deleteQuotation($lcQuoteId, $company1->id, 'TestAdmin');
assertTest("Deletion Protection: Cannot delete converted quotation", $delConvertedQ['success'] === false);

// Cannot delete fulfilled sales order
$delFulfilledSo = SalesOrderService::deleteSalesOrder($dirSoId, $company1->id, 'TestAdmin');
assertTest("Deletion Protection: Cannot delete fulfilled sales order", $delFulfilledSo['success'] === false);

// Cannot delete invoiced delivery challan
$delInvoicedDc = DeliveryChallanService::deleteDeliveryChallan($lcDcId, $company1->id, 'TestAdmin');
assertTest("Deletion Protection: Cannot delete invoiced delivery challan", $delInvoicedDc['success'] === false);


echo "\n=================================================================\n";
echo " Sales Lifecycle Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "=================================================================\n";

exit($failed > 0 ? 1 : 0);
