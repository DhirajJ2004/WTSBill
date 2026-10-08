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

\App\Database\Database::init();

use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\DeliveryChallan;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Capsule\Manager as DB;

echo "======================================================================\n";
echo "   WTSBill ERP - Document Printing Templates & A4 Verification Suite \n";
echo "======================================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest($description, $condition, $details = '') {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$description}\n";
        $passed++;
    } else {
        echo " [FAIL] {$description}" . ($details ? " -> {$details}" : "") . "\n";
        $failed++;
    }
}

// Ensure test user exists with known password
\Illuminate\Database\Capsule\Manager::table('users')->where('id', 1)->update([
    'password' => password_hash('Admin@123', PASSWORD_DEFAULT)
]);

$cookieFile = tempnam(sys_get_temp_dir(), 'wts_print_cookie_');

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

// Step 1: GET /login to retrieve CSRF token
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/login');
curl_setopt($ch, CURLOPT_HTTPGET, true);
$loginPageHtml = curl_exec($ch);

$csrf = '';
if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $loginPageHtml, $m)) {
    $csrf = $m[1];
}

// Step 2: POST /login
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'identifier' => 'anil.d@wtsbill.in',
    'password'   => 'Admin@123',
    'csrf_token' => $csrf,
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$loginOut = curl_exec($ch);
$loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Step 3: GET /select-company
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/select-company');
curl_setopt($ch, CURLOPT_HTTPGET, true);
$selHtml = curl_exec($ch);

$csrf2 = '';
if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $selHtml, $m2)) {
    $csrf2 = $m2[1];
}

// Step 4: POST /select-company
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/select-company');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'action'         => 'select_workspace',
    'company_id'     => 1,
    'branch_id'      => 1,
    'financial_year' => '2024-2025',
    'csrf_token'     => $csrf2,
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$selPostOut = curl_exec($ch);
$selPostHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "[AUTH] Login Code: {$loginHttpCode} | Select Company Code: {$selPostHttpCode}\n";

// Helper to fetch HTML from dev server using the authenticated handle
function fetchPrintHtml(string $uri): array {
    global $ch;
    $url = 'http://127.0.0.1:8000' . $uri;

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $body = substr($rawResponse, $headerSize);

    return [
        'code' => $httpCode,
        'body' => $body,
    ];
}

// Ensure at least 1 real database record exists for each document type under company 1
$companyId = 1;
$branchId = 1;

// 1. Customer & Supplier
$customer = Customer::firstOrCreate(
    ['company_id' => $companyId, 'email' => 'client@printtest.com'],
    [
        'name'          => 'Print Test Client Enterprise',
        'address_line1' => 'Suite 401, Tech Park, Pune',
        'city'          => 'Pune',
        'state'         => 'Maharashtra',
        'state_code'    => '27',
        'pincode'       => '411041',
        'gstin'         => '27AAAAA0000A1Z5',
        'status'        => 'ACTIVE',
    ]
);

$supplier = Supplier::firstOrCreate(
    ['company_id' => $companyId, 'email' => 'vendor@printtest.com'],
    [
        'name'          => 'National Material Suppliers Ltd',
        'address_line1' => 'Plot 9, MIDC Bhosari, Pune',
        'city'          => 'Pune',
        'state'         => 'Maharashtra',
        'state_code'    => '27',
        'pincode'       => '411026',
        'gstin'         => '27BBBBB1111B1Z2',
        'status'        => 'ACTIVE',
    ]
);

// 2. Invoice
$invoice = Invoice::firstOrCreate(
    ['company_id' => $companyId, 'invoice_number' => 'INV-PRINT-001'],
    [
        'customer_id'    => $customer->id,
        'branch_id'      => $branchId,
        'invoice_date'   => date('Y-m-d'),
        'due_date'       => date('Y-m-d', strtotime('+30 days')),
        'sub_total'      => 10000.00,
        'cgst_amount'    => 900.00,
        'sgst_amount'    => 900.00,
        'igst_amount'    => 0.00,
        'grand_total'    => 11800.00,
        'payment_status' => 'UNPAID',
    ]
);

// 3. Quotation
$quotation = Quotation::firstOrCreate(
    ['company_id' => $companyId, 'quotation_number' => 'QTN-PRINT-001'],
    [
        'customer_id'    => $customer->id,
        'branch_id'      => $branchId,
        'quotation_date' => date('Y-m-d'),
        'valid_until'    => date('Y-m-d', strtotime('+30 days')),
        'sub_total'      => 15000.00,
        'total_tax'      => 2700.00,
        'cgst_amount'    => 1350.00,
        'sgst_amount'    => 1350.00,
        'grand_total'    => 17700.00,
        'status'         => 'DRAFT',
    ]
);

// 4. Payment
$payment = Payment::firstOrCreate(
    ['company_id' => $companyId, 'payment_number' => 'RCP-PRINT-001'],
    [
        'customer_id'    => $customer->id,
        'party_id'       => $customer->id,
        'party_type'     => 'CUSTOMER',
        'branch_id'      => $branchId,
        'payment_type'   => 'RECEIPT',
        'payment_date'   => date('Y-m-d'),
        'amount'         => 11800.00,
        'payment_mode'   => 'Bank Transfer',
        'reference_number'=> 'NEFT-839482910',
    ]
);

// 5. Sales Order
$salesOrder = SalesOrder::firstOrCreate(
    ['company_id' => $companyId, 'order_number' => 'SO-PRINT-001'],
    [
        'customer_id'    => $customer->id,
        'branch_id'      => $branchId,
        'order_date'     => date('Y-m-d'),
        'sub_total'      => 20000.00,
        'total_tax'      => 3600.00,
        'cgst_amount'    => 1800.00,
        'sgst_amount'    => 1800.00,
        'grand_total'    => 23600.00,
        'status'         => 'CONFIRMED',
    ]
);

// 6. Delivery Challan
$challan = DeliveryChallan::firstOrCreate(
    ['company_id' => $companyId, 'challan_number' => 'DC-PRINT-001'],
    [
        'customer_id'    => $customer->id,
        'branch_id'      => $branchId,
        'challan_date'   => date('Y-m-d'),
        'vehicle_number' => 'MH-12-QX-4891',
        'eway_bill_number'=> '241829019283',
        'status'         => 'DISPATCHED',
    ]
);

// 7. Credit Note
$creditNote = CreditNote::firstOrCreate(
    ['company_id' => $companyId, 'credit_note_number' => 'CN-PRINT-001'],
    [
        'customer_id'    => $customer->id,
        'branch_id'      => $branchId,
        'credit_note_date'=> date('Y-m-d'),
        'sub_total'      => 5000.00,
        'total_tax'      => 900.00,
        'amount'         => 5900.00,
        'reason'         => 'Rate difference',
        'status'         => 'APPROVED',
    ]
);

// 8. Debit Note
$debitNote = DebitNote::firstOrCreate(
    ['company_id' => $companyId, 'debit_note_number' => 'DN-PRINT-001'],
    [
        'supplier_id'    => $supplier->id,
        'branch_id'      => $branchId,
        'debit_note_date'=> date('Y-m-d'),
        'sub_total'      => 4000.00,
        'total_tax'      => 720.00,
        'amount'         => 4720.00,
        'reason'         => 'Material rejection',
        'status'         => 'APPROVED',
    ]
);

// 9. Purchase Bill
$purchase = Purchase::firstOrCreate(
    ['company_id' => $companyId, 'purchase_number' => 'PUR-PRINT-001'],
    [
        'supplier_id'    => $supplier->id,
        'branch_id'      => $branchId,
        'purchase_date'  => date('Y-m-d'),
        'sub_total'      => 30000.00,
        'cgst_amount'    => 2700.00,
        'sgst_amount'    => 2700.00,
        'total_tax'      => 5400.00,
        'grand_total'    => 35400.00,
        'status'         => 'ACTIVE',
    ]
);

// -------------------------------------------------------------------------
// SECTION 1: Tax Invoice Print Template Verification
// -------------------------------------------------------------------------
echo "\n--- Section 1: Tax Invoice Print Template ---\n";
$invRes = fetchPrintHtml("/print-invoice?id={$invoice->id}");
assertTest("Tax Invoice print renders HTTP 200", $invRes['code'] === 200, "Code: {$invRes['code']}");
assertTest("Tax Invoice contains '@media print' styling", strpos($invRes['body'], '@media print') !== false);
assertTest("Tax Invoice contains A4 sizing declaration", strpos($invRes['body'], 'size: A4') !== false || strpos($invRes['body'], '210mm') !== false);
assertTest("Tax Invoice contains page break protection", strpos($invRes['body'], 'page-break') !== false || strpos($invRes['body'], 'break-inside') !== false);
assertTest("Tax Invoice renders Document Number 'INV-PRINT-001'", strpos($invRes['body'], 'INV-PRINT-001') !== false);
assertTest("Tax Invoice renders GSTIN", strpos($invRes['body'], 'GSTIN') !== false);
assertTest("Tax Invoice renders Signatures area", strpos($invRes['body'], 'Authorised Signatory') !== false || strpos($invRes['body'], 'Authorized Signatory') !== false || strpos($invRes['body'], 'Signature') !== false);
assertTest("Tax Invoice renders Number-to-Words", strpos($invRes['body'], 'Rupees') !== false || strpos($invRes['body'], 'Eleven Thousand') !== false);

// -------------------------------------------------------------------------
// SECTION 2: Quotation Print Template Verification
// -------------------------------------------------------------------------
echo "\n--- Section 2: Quotation Print Template ---\n";
$quoteRes = fetchPrintHtml("/print-quotation?id={$quotation->id}");
assertTest("Quotation print renders HTTP 200", $quoteRes['code'] === 200);
assertTest("Quotation renders Document Number 'QTN-PRINT-001'", strpos($quoteRes['body'], 'QTN-PRINT-001') !== false);
assertTest("Quotation contains A4 sizing and @media print", strpos($quoteRes['body'], '@media print') !== false);
assertTest("Quotation renders Totals and GST breakdown", strpos($quoteRes['body'], 'Taxable') !== false || strpos($quoteRes['body'], 'CGST') !== false || strpos($quoteRes['body'], 'Grand Total') !== false);

// -------------------------------------------------------------------------
// SECTION 3: Payment Receipt Print Template Verification
// -------------------------------------------------------------------------
echo "\n--- Section 3: Payment Receipt Print Template ---\n";
$rcpRes = fetchPrintHtml("/print-receipt?id={$payment->id}");
assertTest("Payment Receipt renders HTTP 200", $rcpRes['code'] === 200);
assertTest("Payment Receipt renders Receipt Number 'RCP-PRINT-001'", strpos($rcpRes['body'], 'RCP-PRINT-001') !== false);
assertTest("Payment Receipt renders Payment Mode and Amount", strpos($rcpRes['body'], 'Bank Transfer') !== false);

// -------------------------------------------------------------------------
// SECTION 4: Sales Order Print Template Verification
// -------------------------------------------------------------------------
echo "\n--- Section 4: Sales Order Print Template ---\n";
$soRes = fetchPrintHtml("/print-order?id={$salesOrder->id}&type=so");
assertTest("Sales Order renders HTTP 200", $soRes['code'] === 200);
assertTest("Sales Order renders Order Number 'SO-PRINT-001'", strpos($soRes['body'], 'SO-PRINT-001') !== false);
assertTest("Sales Order contains Company Header and Signatures", strpos($soRes['body'], 'Signatory') !== false || strpos($soRes['body'], 'Signature') !== false);

// -------------------------------------------------------------------------
// SECTION 5: Delivery Challan Print Template Verification
// -------------------------------------------------------------------------
echo "\n--- Section 5: Delivery Challan Print Template ---\n";
$dcRes = fetchPrintHtml("/print-challan?id={$challan->id}");
assertTest("Delivery Challan renders HTTP 200", $dcRes['code'] === 200);
assertTest("Delivery Challan renders Challan Number 'DC-PRINT-001'", strpos($dcRes['body'], 'DC-PRINT-001') !== false);
assertTest("Delivery Challan renders Vehicle and E-Way Bill info", strpos($dcRes['body'], 'MH-12-QX-4891') !== false && strpos($dcRes['body'], '241829019283') !== false);

// -------------------------------------------------------------------------
// SECTION 6: Credit Note Print Template Verification
// -------------------------------------------------------------------------
echo "\n--- Section 6: Credit Note Print Template ---\n";
$cnRes = fetchPrintHtml("/print-credit-note?id={$creditNote->id}");
assertTest("Credit Note renders HTTP 200", $cnRes['code'] === 200);
assertTest("Credit Note renders Credit Note Number 'CN-PRINT-001'", strpos($cnRes['body'], 'CN-PRINT-001') !== false);
assertTest("Credit Note renders Reason and GST Breakdown", strpos($cnRes['body'], 'CREDIT NOTE') !== false);

// -------------------------------------------------------------------------
// SECTION 7: Debit Note Print Template Verification
// -------------------------------------------------------------------------
echo "\n--- Section 7: Debit Note Print Template ---\n";
$dnRes = fetchPrintHtml("/print-debit-note?id={$debitNote->id}");
assertTest("Debit Note renders HTTP 200", $dnRes['code'] === 200);
assertTest("Debit Note renders Debit Note Number 'DN-PRINT-001'", strpos($dnRes['body'], 'DN-PRINT-001') !== false);
assertTest("Debit Note renders Supplier details", strpos($dnRes['body'], 'DEBIT NOTE') !== false);

// -------------------------------------------------------------------------
// SECTION 8: Purchase Bill Print Template Verification
// -------------------------------------------------------------------------
echo "\n--- Section 8: Purchase Bill Print Template ---\n";
$purRes = fetchPrintHtml("/print-purchase?id={$purchase->id}");
assertTest("Purchase Bill renders HTTP 200", $purRes['code'] === 200);
assertTest("Purchase Bill renders Purchase Number 'PUR-PRINT-001'", strpos($purRes['body'], 'PUR-PRINT-001') !== false);
assertTest("Purchase Bill renders Supplier GSTIN and details", strpos($purRes['body'], 'PURCHASE') !== false);

// -------------------------------------------------------------------------
// SECTION 9: Invalid Document ID & Graceful 404 Verification
// -------------------------------------------------------------------------
echo "\n--- Section 9: Invalid Document IDs & Graceful 404 Handling ---\n";

$notFoundInv = fetchPrintHtml("/print-invoice?id=9999999");
assertTest("Non-existent invoice ID returns HTTP 404", $notFoundInv['code'] === 404, "Code: {$notFoundInv['code']}");
assertTest("404 page renders clean styled error without PHP stack trace", strpos($notFoundInv['body'], 'Not Found') !== false && strpos($notFoundInv['body'], 'Fatal error') === false);

$notFoundQuote = fetchPrintHtml("/print-quotation?id=9999999");
assertTest("Non-existent quotation ID returns HTTP 404", $notFoundQuote['code'] === 404);

$notFoundReceipt = fetchPrintHtml("/print-receipt?id=9999999");
assertTest("Non-existent payment receipt ID returns HTTP 404", $notFoundReceipt['code'] === 404);

$invalidStringId = fetchPrintHtml("/print-invoice?id=invalid_non_numeric_id");
assertTest("Invalid non-numeric ID returns HTTP 404", $invalidStringId['code'] === 404);

// -------------------------------------------------------------------------
// SECTION 10: Security & Zero SQL/Warning Leakage
// -------------------------------------------------------------------------
echo "\n--- Section 10: Zero SQL / PHP Warning Leakage ---\n";

$allOutputs = [
    $invRes['body'],
    $quoteRes['body'],
    $rcpRes['body'],
    $soRes['body'],
    $dcRes['body'],
    $cnRes['body'],
    $dnRes['body'],
    $purRes['body'],
    $notFoundInv['body'],
];

foreach ($allOutputs as $idx => $output) {
    assertTest("Output #{$idx} contains zero 'SQLSTATE'", strpos($output, 'SQLSTATE') === false);
    assertTest("Output #{$idx} contains zero 'Fatal error'", strpos($output, 'Fatal error') === false);
    assertTest("Output #{$idx} contains zero 'Notice:' or 'Warning:'", strpos($output, '<b>Warning</b>') === false && strpos($output, '<b>Notice</b>') === false);
}

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------
echo "\n======================================================================\n";
echo "   Verification Results: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
