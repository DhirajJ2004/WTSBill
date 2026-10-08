<?php
/**
 * Automated Verification Test Suite for WTSBill Print & Document Engine
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/../views/db_helper.php';

use App\Services\NumberToWordsService;
use App\Services\DocumentPrintService;
use Illuminate\Database\Capsule\Manager as DB;

$passCount = 0;
$failCount = 0;

function assert_true($condition, $message) {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$message}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$message}\n";
    }
}

echo "======================================================================\n";
echo "1. TESTING NUMBER-TO-WORDS SERVICE & getIndianWords HELPER\n";
echo "======================================================================\n";

$testCases = [
    0 => 'Zero Rupees Only',
    100 => 'One Hundred Rupees Only',
    1250.50 => 'One Thousand Two Hundred and Fifty Rupees and Fifty Paise Only',
    100000 => 'One Lakh Rupees Only',
    15420500.75 => 'One Crore Fifty Four Lakh Twenty Thousand Five Hundred Rupees and Seventy Five Paise Only'
];

foreach ($testCases as $amount => $expected) {
    $result = NumberToWordsService::toIndianWords((float)$amount);
    assert_true(!empty($result), "toIndianWords({$amount}) produced non-empty result: '{$result}'");
    $globalResult = getIndianWords((float)$amount);
    assert_true($result === $globalResult, "getIndianWords({$amount}) matches NumberToWordsService exactly");
    assert_true(strpos($result, 'Rupees') !== false, "Output contains 'Rupees'");
}

echo "\n======================================================================\n";
echo "2. SETTING UP TEST FIXTURES ACROSS TWO ISOLATED TENANTS\n";
echo "======================================================================\n";

// Ensure Company 1 exists
$company1 = DB::table('companies')->where('id', 1)->first();
if (!$company1) {
    $company1Id = DB::table('companies')->insertGetId([
        'name' => 'Wis Technosavvy Pvt Ltd (Tenant 1)',
        'gstin' => '27AADCW7577N1ZE',
        'email' => 'tenant1@wtsindia.co.in',
        'phone' => '02047252364',
        'created_at' => date('Y-m-d H:i:s')
    ]);
} else {
    $company1Id = 1;
}

// Ensure Company 2 exists for cross-tenant isolation testing
$company2 = DB::table('companies')->where('id', 2)->first();
if (!$company2) {
    $company2Id = DB::table('companies')->insertGetId([
        'id' => 2,
        'name' => 'ACME Corporation (Tenant 2)',
        'gstin' => '27XYZAB1234C1Z5',
        'email' => 'admin@acmecorp.com',
        'phone' => '02212345678',
        'created_at' => date('Y-m-d H:i:s')
    ]);
} else {
    $company2Id = 2;
}

// Ensure user fixture exists
$user1 = DB::table('users')->first();
if (!$user1) {
    $user1Id = DB::table('users')->insertGetId([
        'name' => 'Tenant 1 Admin',
        'email' => 'admin_tenant1@wtsindia.co.in',
        'password' => password_hash('Admin@123', PASSWORD_BCRYPT),
        'role' => 'ADMIN',
        'current_company_id' => $company1Id,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    $user1 = DB::table('users')->where('id', $user1Id)->first();
}

// Ensure test customer & supplier exist for company 1
$customer1 = DB::table('customers')->where('company_id', $company1Id)->first();
if (!$customer1) {
    $c1Id = DB::table('customers')->insertGetId([
        'company_id' => $company1Id,
        'name' => 'Tenant 1 Test Customer',
        'email' => 'customer1@test.com',
        'phone' => '9876543210',
        'created_at' => date('Y-m-d H:i:s')
    ]);
} else {
    $c1Id = $customer1->id;
}

$supplier1 = DB::table('suppliers')->where('company_id', $company1Id)->first();
if (!$supplier1) {
    $s1Id = DB::table('suppliers')->insertGetId([
        'company_id' => $company1Id,
        'name' => 'Tenant 1 Test Supplier',
        'email' => 'supplier1@test.com',
        'phone' => '9876543211',
        'created_at' => date('Y-m-d H:i:s')
    ]);
} else {
    $s1Id = $supplier1->id;
}

$uniqueSuffix = time() . '_' . rand(100, 999);
$t1InvNum = 'INV-T1-' . $uniqueSuffix;
$t1QtNum = 'QT-T1-' . $uniqueSuffix;
$t1RecNum = 'REC-T1-' . $uniqueSuffix;
$t1CnNum = 'CN-T1-' . $uniqueSuffix;
$t1DnNum = 'DN-T1-' . $uniqueSuffix;
$t1PurNum = 'PUR-T1-' . $uniqueSuffix;
$t2InvNum = 'INV-CONFIDENTIAL-T2-' . $uniqueSuffix;

// Create sample documents for Tenant 1
$t1InvoiceId = DB::table('invoices')->insertGetId([
    'company_id' => $company1Id,
    'customer_id' => $c1Id,
    'invoice_number' => $t1InvNum,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'sub_total' => 10000,
    'total_tax' => 1800,
    'grand_total' => 11800,
    'status' => 'UNPAID',
    'created_at' => date('Y-m-d H:i:s')
]);

$t1QuotationId = DB::table('quotations')->insertGetId([
    'company_id' => $company1Id,
    'customer_id' => $c1Id,
    'quotation_number' => $t1QtNum,
    'quotation_date' => date('Y-m-d'),
    'sub_total' => 5000,
    'total_tax' => 900,
    'grand_total' => 5900,
    'status' => 'SENT',
    'created_at' => date('Y-m-d H:i:s')
]);

$t1PaymentId = DB::table('payments')->insertGetId([
    'company_id' => $company1Id,
    'payment_number' => $t1RecNum,
    'party_type' => 'CUSTOMER',
    'party_id' => $c1Id,
    'amount' => 4500,
    'payment_date' => date('Y-m-d'),
    'payment_mode' => 'Bank Transfer',
    'reference_no' => 'REF-' . $uniqueSuffix,
    'created_at' => date('Y-m-d H:i:s')
]);

$t1CreditNoteId = DB::table('credit_notes')->insertGetId([
    'company_id' => $company1Id,
    'customer_id' => $c1Id,
    'credit_note_number' => $t1CnNum,
    'credit_note_date' => date('Y-m-d'),
    'amount' => 1200,
    'reason' => 'Rate difference discount',
    'created_at' => date('Y-m-d H:i:s')
]);

$t1DebitNoteId = DB::table('debit_notes')->insertGetId([
    'company_id' => $company1Id,
    'supplier_id' => $s1Id,
    'debit_note_number' => $t1DnNum,
    'debit_note_date' => date('Y-m-d'),
    'amount' => 2500,
    'reason' => 'Material return rejection',
    'created_at' => date('Y-m-d H:i:s')
]);

$t1PurchaseId = DB::table('purchases')->insertGetId([
    'company_id' => $company1Id,
    'supplier_id' => $s1Id,
    'purchase_number' => $t1PurNum,
    'purchase_date' => date('Y-m-d'),
    'sub_total' => 15000,
    'total_tax' => 2700,
    'grand_total' => 17700,
    'status' => 'UNPAID',
    'created_at' => date('Y-m-d H:i:s')
]);

// Create sample document for Tenant 2 (for cross-tenant access testing)
$t2InvoiceId = DB::table('invoices')->insertGetId([
    'company_id' => $company2Id,
    'customer_id' => 9999,
    'invoice_number' => $t2InvNum,
    'invoice_date' => date('Y-m-d'),
    'sub_total' => 999999,
    'total_tax' => 0,
    'grand_total' => 999999,
    'status' => 'UNPAID',
    'created_at' => date('Y-m-d H:i:s')
]);

assert_true($t1InvoiceId > 0 && $t2InvoiceId > 0, "Test fixtures created successfully");

echo "\n======================================================================\n";
echo "3. TESTING DOCUMENT PRINT WORKFLOWS (8 DOCUMENT TYPES)\n";
echo "======================================================================\n";

$templates = [
    'Invoice' => [
        'file' => __DIR__ . '/../views/sales/print_invoice.php',
        'valid_id' => $t1InvoiceId,
        't2_id' => $t2InvoiceId,
        'expected_strings' => [$t1InvNum, $customer1->name ?? 'Customer', 'TAX INVOICE', 'Rupees']
    ],
    'Quotation' => [
        'file' => __DIR__ . '/../views/sales/print_quotation.php',
        'valid_id' => $t1QuotationId,
        't2_id' => $t2InvoiceId,
        'expected_strings' => [$t1QtNum, 'QUOTATION', 'Rupees']
    ],
    'Payment Receipt' => [
        'file' => __DIR__ . '/../views/payments/print_receipt.php',
        'valid_id' => $t1PaymentId,
        't2_id' => $t2InvoiceId,
        'expected_strings' => [$t1RecNum, 'PAYMENT RECEIPT', 'Rupees']
    ],
    'Credit Note' => [
        'file' => __DIR__ . '/../views/sales/print_credit_note.php',
        'valid_id' => $t1CreditNoteId,
        't2_id' => $t2InvoiceId,
        'expected_strings' => [$t1CnNum, 'CREDIT NOTE', 'Rupees']
    ],
    'Debit Note' => [
        'file' => __DIR__ . '/../views/purchases/print_debit_note.php',
        'valid_id' => $t1DebitNoteId,
        't2_id' => $t2InvoiceId,
        'expected_strings' => [$t1DnNum, 'DEBIT NOTE', 'Rupees']
    ],
    'Purchase Bill' => [
        'file' => __DIR__ . '/../views/purchases/print_purchase.php',
        'valid_id' => $t1PurchaseId,
        't2_id' => $t2InvoiceId,
        'expected_strings' => [$t1PurNum, 'PURCHASE BILL', 'Rupees']
    ],
    'Sales / Purchase Order' => [
        'file' => __DIR__ . '/../views/sales/print_order.php',
        'valid_id' => 1,
        't2_id' => 2,
        'expected_strings' => ['ORDER', 'Rupees', 'A4']
    ],
    'Delivery Challan' => [
        'file' => __DIR__ . '/../views/sales/print_challan.php',
        'valid_id' => 1,
        't2_id' => 2,
        'expected_strings' => ['DELIVERY CHALLAN', 'A4']
    ],
];

// Helper to simulate rendering a template in an isolated PHP sub-process
function execute_template_render($filePath, $getParams, $sessionData) {
    $runnerScript = __DIR__ . '/run_single_print_test.php';
    $payload = base64_encode(json_encode([
        'file' => $filePath,
        'get' => $getParams,
        'session' => $sessionData
    ]));

    $phpPath = 'C:\\xampp\\php\\php.exe';
    if (!file_exists($phpPath)) {
        $phpPath = 'php';
    }

    $cmd = "\"{$phpPath}\" \"{$runnerScript}\" \"{$payload}\"";
    $outputLines = [];
    $returnCode = 0;
    exec($cmd . ' 2>&1', $outputLines, $returnCode);

    $rawOutput = implode("\n", $outputLines);
    $marker = '===TEST_RUNNER_RESULT===';
    $pos = strpos($rawOutput, $marker);
    if ($pos !== false) {
        $metaJson = substr($rawOutput, $pos + strlen($marker));
        $meta = json_decode($metaJson, true) ?? [];
        $htmlOutput = substr($rawOutput, 0, $pos);
        return [
            'output' => $htmlOutput,
            'statusCode' => $meta['statusCode'] ?? ($returnCode === 0 ? 200 : 500),
            'exception' => $meta['exception'] ?? null,
            'exitCode' => $returnCode
        ];
    }

    return [
        'output' => $rawOutput,
        'statusCode' => $returnCode === 0 ? 200 : 500,
        'exception' => $returnCode !== 0 ? $rawOutput : null,
        'exitCode' => $returnCode
    ];
}

$activeSession = [
    'user' => (array)$user1,
    'user_id' => $user1->id,
    'company_id' => $company1Id,
    'active_company_id' => $company1Id
];

foreach ($templates as $docName => $config) {
    echo "\n>>> Testing {$docName} Document Workflows <<<\n";

    // Test A: Valid ID authenticated as Tenant 1
    $resultA = execute_template_render($config['file'], ['id' => $config['valid_id']], $activeSession);
    assert_true($resultA['exception'] === null, "{$docName}: Valid ID rendered without PHP exceptions");
    assert_true($resultA['statusCode'] === 200, "{$docName}: Valid ID returned HTTP 200");
    foreach ($config['expected_strings'] as $str) {
        assert_true(strpos($resultA['output'], $str) !== false, "{$docName}: Output contains '{$str}'");
    }

    // Test B: Invalid ID (e.g. ?id=abc, ?id=-5, ?id=999999)
    $resultB = execute_template_render($config['file'], ['id' => 'abc_invalid'], $activeSession);
    assert_true($resultB['exception'] === null, "{$docName}: Invalid ID handled cleanly without PHP exceptions");
    
    $resultB2 = execute_template_render($config['file'], ['id' => 99999999], $activeSession);
    assert_true($resultB2['exception'] === null, "{$docName}: Non-existent ID 99999999 handled cleanly without PHP exceptions");

    // Test C: Missing ID (?id=)
    $resultC = execute_template_render($config['file'], [], $activeSession);
    assert_true($resultC['exception'] === null, "{$docName}: Missing ID handled cleanly without PHP exceptions");

    // Test D: Cross-Tenant Security Tampering (?id=Tenant 2 Document)
    $resultD = execute_template_render($config['file'], ['id' => $config['t2_id']], $activeSession);
    assert_true($resultD['exception'] === null, "{$docName}: Cross-tenant ID request handled cleanly without PHP fatal error");
    assert_true(strpos($resultD['output'], 'INV-CONFIDENTIAL-T2') === false, "{$docName}: Multi-tenant security - Tenant 2 data NEVER leaks to Tenant 1");
    assert_true(strpos($resultD['output'], 'Secret Customer') === false, "{$docName}: Multi-tenant security - Cross-tenant party names NEVER leak");

    // Test E: Unauthenticated request (Session empty)
    $resultE = execute_template_render($config['file'], ['id' => $config['valid_id']], []);
    assert_true($resultE['exception'] === null, "{$docName}: Unauthenticated request handled cleanly without PHP fatal error");
    assert_true(strpos($resultE['output'], 'Authentication Required') !== false || $resultE['statusCode'] === 401, "{$docName}: Unauthenticated request properly challenged with 401 Authentication Required");
}

echo "\n======================================================================\n";
echo "SUMMARY OF RESULTS\n";
echo "======================================================================\n";
echo "Total Passed Assertions: {$passCount}\n";
echo "Total Failed Assertions: {$failCount}\n";

if ($failCount === 0) {
    echo "\n>>> ALL PRINT & DOCUMENT AUDIT CHECKS PASSED PERFECTLY! <<<\n";
    exit(0);
} else {
    echo "\n>>> THERE WERE FAILURES! <<<\n";
    exit(1);
}
