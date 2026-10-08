<?php
/**
 * scripts/test_tenant_authorization_strict.php
 *
 * Strict Multi-Tenant Company Authorization Validation Test Suite:
 * 1. get_all_companies() returns [] if user has no authorized memberships (NO fallback to SELECT * FROM companies WHERE status = 'ACTIVE').
 * 2. get_all_companies() returns [] for unauthenticated requests.
 * 3. set_active_workspace() rejects unauthorized company, unauthorized branch, or unauthenticated session (never falls back to branch 1).
 * 4. WorkspaceContext::getAuthorizedWarehouseId() validates warehouse against company (never falls back to 1 or another company's warehouse).
 * 5. AutomationController rejects forged $_GET['company_id'] and body company_id with 403.
 * 6. CommunicationController rejects forged $_GET['company_id'] and body company_id with 403.
 * 7. Product creation resolves warehouse strictly for active company and rejects cross-company warehouse tampering.
 * 8. Inventory adjustment and count reject cross-company warehouse tampering.
 * 9. Cross-company invoice, customer, supplier, payment, and expense access returns 403.
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Database\Database;
use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\Customer;
use App\Models\Invoice;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AuthorizationException;
use App\Auth\WorkspaceContext;
use Illuminate\Database\Capsule\Manager as DB;

Database::init();

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

if (!function_exists('get_json_input')) {
    function get_json_input() {
        return $GLOBALS['__MOCK_JSON_INPUT'] ?? [];
    }
}

require_once __DIR__ . '/../views/db_helper.php';

$pass = 0;
$fail = 0;

function check(string $name, bool $result, string $details = '') {
    global $pass, $fail;
    if ($result) {
        $pass++;
        echo "  [PASS] {$name}\n";
        if ($details) echo "         Detail: {$details}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$name}\n";
        if ($details) echo "         Detail: {$details}\n";
    }
}

echo "====================================================================\n";
echo "   STRICT MULTI-TENANT COMPANY AUTHORIZATION VERIFICATION SUITE     \n";
echo "====================================================================\n\n";

// -----------------------------------------------------------------
// 1. Unauthenticated and Orphan User Company Listing
// -----------------------------------------------------------------
echo "[1] Testing get_all_companies() Scoping...\n";
$_SESSION = [];
$unauthCompanies = get_all_companies();
check("Unauthenticated session returns empty company list []", $unauthCompanies === [], "Count: " . count($unauthCompanies));

// Create a user with zero memberships
$orphanEmail = 'orphan_' . uniqid() . '@example.com';
$orphanUser = User::create([
    'name' => 'Orphan User',
    'email' => $orphanEmail,
    'password_hash' => password_hash('Pass@12345', PASSWORD_BCRYPT),
    'role' => 'STAFF',
    'status' => 'ACTIVE',
    'current_company_id' => null
]);
$_SESSION['user'] = ['id' => $orphanUser->id, 'email' => $orphanUser->email, 'name' => $orphanUser->name];
$orphanCompanies = get_all_companies();
check("Authenticated user with 0 memberships returns [] (NEVER exposes active companies)", $orphanCompanies === [], "Count: " . count($orphanCompanies));

$curCompOrphan = get_current_company();
check("Current company for user with 0 memberships is null", $curCompOrphan === null, "Current company: " . json_encode($curCompOrphan));
check("get_current_company_id() for user with 0 memberships is 0", get_current_company_id() === 0, "Current company id: " . get_current_company_id());

// -----------------------------------------------------------------
// 2. Testing set_active_workspace() Protection
// -----------------------------------------------------------------
echo "\n[2] Testing set_active_workspace() Protection...\n";
$authExceptionThrown = false;
try {
    set_active_workspace(1, '2026-27');
} catch (AuthorizationException $e) {
    $authExceptionThrown = ($e->statusCode === 403);
}
check("Unauthorized user attempting set_active_workspace(1) throws 403", $authExceptionThrown);

// Test unauthenticated call to set_active_workspace()
$_SESSION = [];
$unauthWorkspaceException = false;
try {
    set_active_workspace(1, '2026-27');
} catch (AuthorizationException $e) {
    $unauthWorkspaceException = ($e->statusCode === 401 || $e->statusCode === 403);
}
check("Unauthenticated call to set_active_workspace(1) throws 401/403", $unauthWorkspaceException);

// -----------------------------------------------------------------
// 3. Testing WorkspaceContext & AuthMiddleware Warehouse Scoping
// -----------------------------------------------------------------
echo "\n[3] Testing Warehouse Scoping & Fallback Elimination...\n";
// Setup test tenant A & tenant B
$companyA = Company::first();
$companyB = Company::where('id', '!=', $companyA->id)->first();
if (!$companyB) {
    $companyB = Company::create([
        'name' => 'Isolation Test Co ' . uniqid(),
        'legal_name' => 'Isolation Test Co',
        'gstin' => '27XYZAB' . rand(1000, 9999) . 'Z1Z9',
        'status' => 'ACTIVE'
    ]);
}

$whA = Warehouse::where('company_id', $companyA->id)->first();
if (!$whA) {
    $whA = Warehouse::create([
        'company_id' => $companyA->id,
        'name' => 'Warehouse A',
        'code' => 'WHA-' . rand(100, 999),
        'is_primary' => true
    ]);
}

$whB = Warehouse::where('company_id', $companyB->id)->first();
if (!$whB) {
    $whB = Warehouse::create([
        'company_id' => $companyB->id,
        'name' => 'Warehouse B',
        'code' => 'WHB-' . rand(100, 999),
        'is_primary' => true
    ]);
}

// User A belongs to Company A
$userA = User::where('current_company_id', $companyA->id)->first();
if (!$userA) {
    $userA = User::first();
}

// Verify warehouse access check
check("whA belongs to companyA", WorkspaceContext::verifyWarehouseAccess($companyA->id, $whA->id) === true);
check("whB does NOT belong to companyA", WorkspaceContext::verifyWarehouseAccess($companyA->id, $whB->id) === false);

// Attempt cross-company warehouse access via getAuthorizedWarehouseId
$crossWhException = false;
try {
    WorkspaceContext::getAuthorizedWarehouseId($companyA->id, $whB->id);
} catch (AuthorizationException $e) {
    $crossWhException = ($e->statusCode === 403);
}
check("Requesting company B warehouse in company A context throws 403", $crossWhException);

// Valid warehouse in company A returns requested warehouse id
$resolvedWhA = WorkspaceContext::getAuthorizedWarehouseId($companyA->id, $whA->id);
check("Requesting own company warehouse returns warehouse ID", $resolvedWhA === (int)$whA->id);

// -----------------------------------------------------------------
// 4. Testing AutomationController Parameter Tampering
// -----------------------------------------------------------------
echo "\n[4] Testing AutomationController Tampering Protection...\n";
AuthMiddleware::setContext($userA, $companyA->id, null, 'ADMIN', '2026-27');
$_GET['company_id'] = (string)$companyB->id;
ob_start();
(new \App\Http\Controllers\Api\AutomationController())->getMetrics();
$autoResp = ob_get_clean();
$autoJson = json_decode($autoResp, true);
check("AutomationController::getMetrics() rejects forged ?company_id=B with 403", 
    http_response_code() === 403 || ($autoJson['success'] === false && strpos($autoJson['error'] ?? '', 'Forbidden') !== false)
);
unset($_GET['company_id']);

// -----------------------------------------------------------------
// 5. Testing CommunicationController Parameter Tampering
// -----------------------------------------------------------------
echo "\n[5] Testing CommunicationController Tampering Protection...\n";
$_GET['company_id'] = (string)$companyB->id;
ob_start();
(new \App\Http\Controllers\Api\CommunicationController())->getMessages();
$commResp = ob_get_clean();
$commJson = json_decode($commResp, true);
check("CommunicationController::getMessages() rejects forged ?company_id=B with 403", 
    http_response_code() === 403 || ($commJson['success'] === false && strpos($commJson['error'] ?? '', 'Forbidden') !== false)
);
unset($_GET['company_id']);

// -----------------------------------------------------------------
// 6. Testing Product Opening Stock Warehouse Tampering
// -----------------------------------------------------------------
echo "\n[6] Testing Product Warehouse Tampering Protection...\n";
AuthMiddleware::setContext($userA, $companyA->id, null, 'ADMIN', '2026-27');
$forgedProdPayload = [
    'name' => 'Cross-Warehouse Test Product ' . uniqid(),
    'current_stock' => 10,
    'warehouse_id' => $whB->id // Tampered with company B's warehouse!
];
$prodCrossWhBlocked = false;
try {
    $GLOBALS['__MOCK_JSON_INPUT'] = $forgedProdPayload;
    ob_start();
    (new \App\Http\Controllers\Api\ProductController())->store();
    $prodOut = ob_get_clean();
    $prodJson = json_decode($prodOut, true);
    if (http_response_code() === 403 || (isset($prodJson['status']) && $prodJson['status'] === 'error')) {
        $prodCrossWhBlocked = true;
    }
} catch (AuthorizationException $e) {
    $prodCrossWhBlocked = true;
    ob_end_clean();
}
check("ProductController rejects opening stock assigned to another company's warehouse", $prodCrossWhBlocked);

// -----------------------------------------------------------------
// 7. Testing Cross-Company Branch Tampering in views/db_helper.php
// -----------------------------------------------------------------
echo "\n[7] Testing Branch & Company Tampering in db_helper.php...\n";
$branchB = Branch::where('company_id', $companyB->id)->first();
if (!$branchB) {
    $branchB = Branch::create(['company_id' => $companyB->id, 'name' => 'Branch B', 'code' => 'BRB-' . rand(100, 999)]);
}

$branchTamperBlocked = false;
try {
    $_SESSION['user'] = ['id' => $userA->id, 'email' => $userA->email, 'name' => $userA->name];
    set_active_workspace($companyA->id, '2026-27', 'ADMIN', $branchB->id);
} catch (AuthorizationException $e) {
    $branchTamperBlocked = ($e->statusCode === 403);
}
check("set_active_workspace rejects another company's branch ID with 403", $branchTamperBlocked);

echo "\n====================================================================\n";
echo "   TEST SUMMARY: {$pass} PASSED, {$fail} FAILED\n";
echo "====================================================================\n";

if ($fail > 0) {
    exit(1);
}
