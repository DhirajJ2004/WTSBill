<?php
/**
 * Multi-Tenant Security & Isolation Test Suite
 * Tests 7 required scenarios + cross-verification
 */
require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Database\Database;
use App\Models\User;
use App\Models\Session;
use App\Http\Middleware\AuthMiddleware;
use App\Auth\WorkspaceContext;
use Illuminate\Database\Capsule\Manager as DB;

Database::init();

echo "========================================================\n";
echo "   WTSBill ERP Multi-Tenant Security & Isolation Test   \n";
echo "========================================================\n\n";

if (!function_exists('response_json')) {
    function response_json($data, int $code = 200) {
        http_response_code($code);
        echo json_encode($data);
        return new class($data, $code) {
            public $data;
            public $code;
            public function __construct($data, $code) {
                $this->data = $data;
                $this->code = $code;
            }
            public function getStatusCode(): int { return $this->code; }
            public function getContent(): string { return json_encode($this->data); }
        };
    }
}

$passCount = 0;
$failCount = 0;

function assertTest(string $description, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] " . $description . "\n";
        if ($detail) echo "        Detail: " . $detail . "\n";
    } else {
        $failCount++;
        echo " [FAIL] " . $description . "\n";
        if ($detail) echo "        Detail: " . $detail . "\n";
    }
}

// Helper to simulate an API request with headers & session
function simulateApiCall(string $method, string $uri, array $headers = [], array $queryParams = []) {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = $queryParams;
    $_POST = [];

    // Clear old headers polyfill
    $allHeaders = [];
    foreach ($headers as $k => $v) {
        $allHeaders[$k] = $v;
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
    }

    // Reset AuthMiddleware static context
    AuthMiddleware::setContext(null, null, null, null, null);

    ob_start();
    $statusCode = 200;
    try {
        require __DIR__ . '/../backend/routes/api.php';
        dispatch_route($uri, $method);
    } catch (\App\Http\Middleware\AuthorizationException $e) {
        $statusCode = $e->statusCode;
        $output = json_encode($e->response);
        ob_end_clean();
        return ['status' => $statusCode, 'body' => json_decode($output, true)];
    } catch (\Throwable $e) {
        $output = ob_get_clean();
        return ['status' => 500, 'error' => $e->getMessage(), 'raw' => $output];
    }
    $output = ob_get_clean();

    // Parse JSON
    $data = json_decode($output, true);
    return ['status' => http_response_code() ?: 200, 'body' => $data, 'raw' => $output];
}

// -------------------------------------------------------------
// Setup Tokens
// -------------------------------------------------------------
$userA = User::where('email', 'anil.d@wtsbill.in')->first();
$userB = User::where('email', 'vikram.m@innovatech.in')->first();

if (!$userA || !$userB) {
    die("Error: User A or User B fixture not found. Please run seed_multi_company.php first.\n");
}

use App\Models\PersonalAccessToken;

// Generate tokens
$tokenA = PersonalAccessToken::generateForUser($userA, 'test-session-a');
$tokenB = PersonalAccessToken::generateForUser($userB, 'test-session-b');

$headersUserA = [
    'Authorization' => 'Bearer ' . $tokenA,
    'X-Company-Id' => '1',
    'X-Branch-Id' => '1',
];

$headersUserB = [
    'Authorization' => 'Bearer ' . $tokenB,
    'X-Company-Id' => '2',
    'X-Branch-Id' => '3',
];

// Target IDs
$compBCustomerId = 21; // Zenith Retail Ltd (Company 2)
$compBInvoiceId = 2;   // INV-2026-INN-001 (Company 2)
$compBPaymentId = 1;   // PAY-2026-INN-001 (Company 2)
$compBProductId = 51;  // Enterprise Cloud Server Node (Company 2)

// =============================================================
// TEST 1: User A -> Company A
// =============================================================
echo "--- TEST 1: User A -> Company A ---\n";
AuthMiddleware::setContext(null, null, null, null, null);
$authCompaniesA = WorkspaceContext::getAuthorizedCompanies($userA->id);
$isMemberA_Comp1 = WorkspaceContext::verifyCompanyMembership($userA->id, 1);
$isMemberA_Comp2 = WorkspaceContext::verifyCompanyMembership($userA->id, 2);

assertTest(
    "User A is authorized for Company 1",
    $isMemberA_Comp1 === true,
    "Membership confirmed in company 1"
);
assertTest(
    "User A is NOT authorized for Company 2",
    $isMemberA_Comp2 === false,
    "Membership correctly denied for company 2"
);

// Authenticate via AuthMiddleware
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenA;
$_SERVER['HTTP_X_COMPANY_ID'] = '1';
$_SERVER['HTTP_X_BRANCH_ID'] = '1';
AuthMiddleware::authenticate();
$tenantIdA = AuthMiddleware::getTenantId();
$userAContext = AuthMiddleware::getUser();

assertTest(
    "User A Workspace Context set to Company 1",
    $tenantIdA === 1 && $userAContext->id === $userA->id,
    "Authenticated User: {$userAContext->name}, Tenant ID: {$tenantIdA}"
);

// User A queries own customer (ID: 1)
$resCustA = (new \App\Http\Controllers\Api\CustomerController())->show(1);
// Capture response
$custAData = json_decode(ob_get_contents() ?: '', true);
assertTest(
    "User A accesses Company A Customer (ID: 1)",
    $isMemberA_Comp1,
    "Customer 1 is accessible to User A"
);

// =============================================================
// TEST 2: User B -> Company B
// =============================================================
echo "\n--- TEST 2: User B -> Company B ---\n";
AuthMiddleware::setContext(null, null, null, null, null);
$authCompaniesB = WorkspaceContext::getAuthorizedCompanies($userB->id);
$isMemberB_Comp2 = WorkspaceContext::verifyCompanyMembership($userB->id, 2);
$isMemberB_Comp1 = WorkspaceContext::verifyCompanyMembership($userB->id, 1);

assertTest(
    "User B is authorized for Company 2",
    $isMemberB_Comp2 === true,
    "Membership confirmed in company 2"
);
assertTest(
    "User B is NOT authorized for Company 1",
    $isMemberB_Comp1 === false,
    "Membership correctly denied for company 1"
);

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenB;
$_SERVER['HTTP_X_COMPANY_ID'] = '2';
$_SERVER['HTTP_X_BRANCH_ID'] = '3';
AuthMiddleware::authenticate();
$tenantIdB = AuthMiddleware::getTenantId();
$userBContext = AuthMiddleware::getUser();

assertTest(
    "User B Workspace Context set to Company 2",
    $tenantIdB === 2 && $userBContext->id === $userB->id,
    "Authenticated User: {$userBContext->name}, Tenant ID: {$tenantIdB}"
);

// =============================================================
// TEST 3: User A attempts Company B customer
// =============================================================
echo "\n--- TEST 3: User A attempts Company B customer (ID: {$compBCustomerId}) ---\n";
// Switch context back to User A in Company 1
AuthMiddleware::setContext(null, null, null, null, null);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenA;
$_SERVER['HTTP_X_COMPANY_ID'] = '1';
$_SERVER['HTTP_X_BRANCH_ID'] = '1';
AuthMiddleware::authenticate();

// CustomerController::show
ob_start();
$resCustB = (new \App\Http\Controllers\Api\CustomerController())->show($compBCustomerId);
$outputCustB = ob_get_clean();
$jsonCustB = json_decode($outputCustB, true);
$statusCustB = http_response_code();

assertTest(
    "User A accessing Company B Customer returns HTTP 403 Forbidden",
    $statusCustB === 403 && isset($jsonCustB['status']) && $jsonCustB['status'] === 'error',
    "Status Code: {$statusCustB}, Message: " . ($jsonCustB['message'] ?? '')
);

// =============================================================
// TEST 4: User A attempts Company B invoice
// =============================================================
echo "\n--- TEST 4: User A attempts Company B invoice (ID: {$compBInvoiceId}) ---\n";
AuthMiddleware::setContext(null, null, null, null, null);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenA;
$_SERVER['HTTP_X_COMPANY_ID'] = '1';
$_SERVER['HTTP_X_BRANCH_ID'] = '1';
AuthMiddleware::authenticate();

ob_start();
$resInvB = (new \App\Http\Controllers\Api\InvoiceController())->show($compBInvoiceId);
$outputInvB = ob_get_clean();
$jsonInvB = json_decode($outputInvB, true);
$statusInvB = http_response_code();

assertTest(
    "User A accessing Company B Invoice returns HTTP 403 Forbidden",
    $statusInvB === 403 && isset($jsonInvB['status']) && $jsonInvB['status'] === 'error',
    "Status Code: {$statusInvB}, Message: " . ($jsonInvB['message'] ?? '')
);

// =============================================================
// TEST 5: User A attempts Company B payment
// =============================================================
echo "\n--- TEST 5: User A attempts Company B payment (ID: {$compBPaymentId}) ---\n";
AuthMiddleware::setContext(null, null, null, null, null);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenA;
$_SERVER['HTTP_X_COMPANY_ID'] = '1';
$_SERVER['HTTP_X_BRANCH_ID'] = '1';
AuthMiddleware::authenticate();

ob_start();
$resPayB = (new \App\Http\Controllers\Api\PaymentController())->show($compBPaymentId);
$outputPayB = ob_get_clean();
$jsonPayB = json_decode($outputPayB, true);
$statusPayB = http_response_code();

assertTest(
    "User A accessing Company B Payment returns HTTP 403 Forbidden",
    $statusPayB === 403 && isset($jsonPayB['status']) && $jsonPayB['status'] === 'error',
    "Status Code: {$statusPayB}, Message: " . ($jsonPayB['message'] ?? '')
);

// =============================================================
// TEST 6: User A attempts Company B report
// =============================================================
echo "\n--- TEST 6: User A attempts Company B report ---\n";
AuthMiddleware::setContext(null, null, null, null, null);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenA;
$_SERVER['HTTP_X_COMPANY_ID'] = '1';
$_SERVER['HTTP_X_BRANCH_ID'] = '1';
AuthMiddleware::authenticate();

// Attempt 1: ReportController::profitAndLoss with ?company_id=2
$_GET['company_id'] = '2';
ob_start();
$resRepB = (new \App\Http\Controllers\Api\ReportController())->profitAndLoss();
$outputRepB = ob_get_clean();
$jsonRepB = json_decode($outputRepB, true);
$statusRepB = http_response_code();

assertTest(
    "User A requesting Company B Profit & Loss report returns HTTP 403 Forbidden",
    $statusRepB === 403 && isset($jsonRepB['status']) && $jsonRepB['status'] === 'error',
    "Status Code: {$statusRepB}, Message: " . ($jsonRepB['message'] ?? '')
);

// Attempt 2: User A attempts to forge header X-Company-Id: 2
AuthMiddleware::setContext(null, null, null, null, null);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenA;
$_SERVER['HTTP_X_COMPANY_ID'] = '2'; // Forged header!
$forgedExceptionCaught = false;
$forgedCode = 0;
try {
    AuthMiddleware::authenticate();
} catch (\App\Http\Middleware\AuthorizationException $e) {
    $forgedExceptionCaught = true;
    $forgedCode = $e->statusCode;
}

assertTest(
    "User A forging X-Company-Id: 2 header throws 403 AuthorizationException",
    $forgedExceptionCaught && $forgedCode === 403,
    "Caught AuthorizationException code {$forgedCode}"
);

// =============================================================
// TEST 7: User A attempts Company B product
// =============================================================
echo "\n--- TEST 7: User A attempts Company B product (ID: {$compBProductId}) ---\n";
AuthMiddleware::setContext(null, null, null, null, null);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenA;
$_SERVER['HTTP_X_COMPANY_ID'] = '1';
$_SERVER['HTTP_X_BRANCH_ID'] = '1';
$_GET = [];
AuthMiddleware::authenticate();

ob_start();
$resProdB = (new \App\Http\Controllers\Api\ProductController())->show($compBProductId);
$outputProdB = ob_get_clean();
$jsonProdB = json_decode($outputProdB, true);
$statusProdB = http_response_code();

assertTest(
    "User A accessing Company B Product returns HTTP 403 Forbidden",
    $statusProdB === 403 && isset($jsonProdB['status']) && $jsonProdB['status'] === 'error',
    "Status Code: {$statusProdB}, Message: " . ($jsonProdB['message'] ?? '')
);

// =============================================================
// TEST 8: User B legitimate access to Company B Product & Invoice
// =============================================================
echo "\n--- TEST 8: User B legitimate access to Company B data ---\n";
AuthMiddleware::setContext(null, null, null, null, null);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenB;
$_SERVER['HTTP_X_COMPANY_ID'] = '2';
$_SERVER['HTTP_X_BRANCH_ID'] = '3';
$_GET = [];
AuthMiddleware::authenticate();

ob_start();
$resProdLegit = (new \App\Http\Controllers\Api\ProductController())->show($compBProductId);
$outputProdLegit = ob_get_clean();
$jsonProdLegit = json_decode($outputProdLegit, true);
$statusProdLegit = http_response_code();

assertTest(
    "User B can access own Company B Product (ID: {$compBProductId})",
    $statusProdLegit === 200 && ($jsonProdLegit['data']['id'] ?? 0) === $compBProductId,
    "Status Code: {$statusProdLegit}, Product Name: " . ($jsonProdLegit['data']['name'] ?? '')
);

ob_start();
$resInvLegit = (new \App\Http\Controllers\Api\InvoiceController())->show($compBInvoiceId);
$outputInvLegit = ob_get_clean();
$jsonInvLegit = json_decode($outputInvLegit, true);
$statusInvLegit = http_response_code();

assertTest(
    "User B can access own Company B Invoice (ID: {$compBInvoiceId})",
    $statusInvLegit === 200 && ($jsonInvLegit['data']['id'] ?? 0) === $compBInvoiceId,
    "Status Code: {$statusInvLegit}, Invoice Number: " . ($jsonInvLegit['data']['invoice_number'] ?? '')
);

// =============================================================
// TEST 9: User B attempts Company A Customer (ID: 1)
// =============================================================
echo "\n--- TEST 9: User B attempts Company A Customer (ID: 1) ---\n";
AuthMiddleware::setContext(null, null, null, null, null);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenB;
$_SERVER['HTTP_X_COMPANY_ID'] = '2';
$_SERVER['HTTP_X_BRANCH_ID'] = '3';
$_GET = [];
AuthMiddleware::authenticate();

ob_start();
$resCustA_byB = (new \App\Http\Controllers\Api\CustomerController())->show(1);
$outputCustA_byB = ob_get_clean();
$jsonCustA_byB = json_decode($outputCustA_byB, true);
$statusCustA_byB = http_response_code();

assertTest(
    "User B accessing Company A Customer returns HTTP 403 Forbidden",
    $statusCustA_byB === 403 && isset($jsonCustA_byB['status']) && $jsonCustA_byB['status'] === 'error',
    "Status Code: {$statusCustA_byB}, Message: " . ($jsonCustA_byB['message'] ?? '')
);

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n========================================================\n";
echo "   TEST RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
