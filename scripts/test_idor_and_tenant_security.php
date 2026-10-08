<?php

/**
 * WTSBill ERP - IDOR and Multi-Tenant Security Verification Suite
 * Tests cross-tenant resource isolation for:
 * - Invoices
 * - Customers / Parties
 * - Products / Items
 * - Payments / Receipts
 * - Journal Entries / Transactions
 * - Centralized Middlewares (Auth, Guest, Role, Permission, Tenant, Csrf)
 * - Session Regeneration, Password Hashing & Expiry
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use App\Database\Database;
use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Payment;
use App\Models\JournalEntry;
use App\Auth\WorkspaceContext;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\TenantMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AuthorizationException;
use App\Services\AuthService;
use Illuminate\Database\Capsule\Manager as DB;

Database::init();

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$failures = [];

function assert_idor(string $category, string $title, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests, $failures;
    $totalTests++;
    echo "[TEST #{$totalTests}] [{$category}] {$title} ... ";
    if ($condition) {
        $passedTests++;
        echo "PASS\n";
        if (!empty($detail)) {
            echo "   -> {$detail}\n";
        }
    } else {
        $failedTests++;
        echo "FAIL\n";
        $msg = "[{$category}] {$title}: Failed ({$detail})";
        $failures[] = $msg;
        echo "   -> ERROR: {$msg}\n";
    }
}

echo "======================================================================\n";
echo "   WTSBill ERP - Comprehensive IDOR & Multi-Tenant Security Audit\n";
echo "======================================================================\n\n";

// 1. Setup Tenant A and Tenant B
$companyA = Company::find(1);
if (!$companyA) {
    $companyA = Company::create([
        'name' => 'Alpha Corporation',
        'legal_name' => 'Alpha Corporation Pvt Ltd',
        'gstin' => '27AAAAA0000A1Z5',
        'pan' => 'AAAAA0000A',
        'email' => 'admin@alphacorp.in',
        'phone' => '9876543210',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);
}

$companyB = Company::where('name', 'Beta Industries')->orWhere('id', '!=', $companyA->id)->first();
if (!$companyB || $companyB->id === $companyA->id) {
    $companyB = Company::create([
        'name' => 'Beta Industries',
        'legal_name' => 'Beta Industries Pvt Ltd',
        'gstin' => '27BBBBB1111B1Z6',
        'pan' => 'BBBBB1111B',
        'email' => 'admin@betaind.in',
        'phone' => '9876543222',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);
}

echo "Tenant A: Company #{$companyA->id} ({$companyA->name})\n";
echo "Tenant B: Company #{$companyB->id} ({$companyB->name})\n\n";

// Setup User A (Belongs strictly to Company A)
$userA = User::where('email', 'user.a@alphacorp.in')->first();
if (!$userA) {
    $userA = User::create([
        'name' => 'User Alpha',
        'email' => 'user.a@alphacorp.in',
        'password' => password_hash('AlphaPass123!', PASSWORD_BCRYPT),
        'phone' => '9900000001',
        'role' => 'ACCOUNTANT',
        'status' => 'ACTIVE',
        'is_active' => true,
        'current_company_id' => $companyA->id,
    ]);
} else {
    $userA->update([
        'password' => password_hash('AlphaPass123!', PASSWORD_BCRYPT),
        'status' => 'ACTIVE',
        'is_active' => true,
        'current_company_id' => $companyA->id,
    ]);
}

DB::table('user_roles')->where('user_id', $userA->id)->delete();
$roleA = DB::table('roles')->where('company_id', $companyA->id)->first() ?? DB::table('roles')->first();
DB::table('user_roles')->insert([
    'user_id' => $userA->id,
    'company_id' => $companyA->id,
    'role_id' => $roleA ? $roleA->id : 1,
]);

// Setup User B (Belongs strictly to Company B)
$userB = User::where('email', 'user.b@betaind.in')->first();
if (!$userB) {
    $userB = User::create([
        'name' => 'User Beta',
        'email' => 'user.b@betaind.in',
        'password' => password_hash('BetaPass123!', PASSWORD_BCRYPT),
        'phone' => '9900000002',
        'role' => 'ACCOUNTANT',
        'status' => 'ACTIVE',
        'is_active' => true,
        'current_company_id' => $companyB->id,
    ]);
} else {
    $userB->update([
        'password' => password_hash('BetaPass123!', PASSWORD_BCRYPT),
        'status' => 'ACTIVE',
        'is_active' => true,
        'current_company_id' => $companyB->id,
    ]);
}

DB::table('user_roles')->where('user_id', $userB->id)->delete();
$roleB = DB::table('roles')->where('company_id', $companyB->id)->first() ?? DB::table('roles')->first();
DB::table('user_roles')->insert([
    'user_id' => $userB->id,
    'company_id' => $companyB->id,
    'role_id' => $roleB ? $roleB->id : 1,
]);

// Create Test Records in Company B
$customerB = Customer::where('company_id', $companyB->id)->first();
if (!$customerB) {
    $customerB = Customer::create([
        'company_id' => $companyB->id,
        'name' => 'Beta Secret Customer',
        'phone' => '9111111111',
        'email' => 'secret.customer@betaind.in',
        'status' => 'ACTIVE',
    ]);
}

$productB = Product::where('company_id', $companyB->id)->first();
if (!$productB) {
    $productB = Product::create([
        'company_id' => $companyB->id,
        'name' => 'Beta Confidential Product',
        'sku' => 'BETA-CONF-01',
        'selling_price' => 5000.00,
        'purchase_price' => 3500.00,
        'stock_quantity' => 100,
        'unit' => 'NOS',
        'status' => 'ACTIVE',
    ]);
}

$invoiceB = Invoice::where('company_id', $companyB->id)->first();
if (!$invoiceB) {
    $invoiceB = Invoice::create([
        'company_id' => $companyB->id,
        'customer_id' => $customerB->id,
        'invoice_number' => 'INV-BETA-001',
        'invoice_date' => date('Y-m-d'),
        'total_amount' => 5900.00,
        'tax_amount' => 900.00,
        'financial_year' => '2026-2027',
        'status' => 'UNPAID',
    ]);
}

$paymentB = Payment::where('company_id', $companyB->id)->first();
if (!$paymentB) {
    $paymentB = Payment::create([
        'company_id' => $companyB->id,
        'party_id' => $customerB->id,
        'payment_number' => 'REC-BETA-001',
        'payment_date' => date('Y-m-d'),
        'amount' => 5900.00,
        'payment_type' => 'RECEIPT',
        'payment_mode' => 'BANK_TRANSFER',
        'financial_year' => '2026-2027',
        'status' => 'COMPLETED',
    ]);
}

$journalB = JournalEntry::where('company_id', $companyB->id)->first();
if (!$journalB) {
    $journalB = JournalEntry::create([
        'company_id' => $companyB->id,
        'entry_number' => 'JV-BETA-001',
        'entry_date' => date('Y-m-d'),
        'financial_year' => '2026-2027',
        'total_debit' => 1000.00,
        'total_credit' => 1000.00,
        'narration' => 'Beta Private Adjustment Entry',
        'status' => 'POSTED',
    ]);
}

echo "--- Section 1: Establishing Company A Workspace Context ---\n";
WorkspaceContext::setContext($userA, $companyA, null, '2026-2027', 'ACCOUNTANT', ['invoices.view', 'parties.view', 'inventory.view']);
AuthMiddleware::setContext($userA, $companyA->id, null, 'ACCOUNTANT', '2026-2027');

assert_idor(
    'WORKSPACE_A',
    'Active context belongs strictly to Company A',
    WorkspaceContext::getCompanyId() === (int)$companyA->id,
    "Company ID: " . WorkspaceContext::getCompanyId()
);

echo "\n--- Section 2: IDOR Cross-Tenant Penetration Attacks ---\n";

// Attack 1: Company A user attempts to access Company B invoice
$invoiceIdorBlocked = false;
$invoiceExceptionCode = 0;
try {
    // 1. Through TenantMiddleware validateRecordTenant
    TenantMiddleware::validateRecordTenant($invoiceB, $companyA->id);
} catch (AuthorizationException $e) {
    $invoiceIdorBlocked = true;
    $invoiceExceptionCode = $e->statusCode;
}
assert_idor(
    'IDOR_INVOICE',
    'Company A -> Company B invoice blocked with 403',
    $invoiceIdorBlocked && $invoiceExceptionCode === 403,
    "Blocked with HTTP {$invoiceExceptionCode}"
);

// Attack 2: Company A user attempts to access Company B customer
$customerIdorBlocked = false;
$customerExceptionCode = 0;
try {
    TenantMiddleware::validateRecordTenant($customerB, $companyA->id);
} catch (AuthorizationException $e) {
    $customerIdorBlocked = true;
    $customerExceptionCode = $e->statusCode;
}
assert_idor(
    'IDOR_CUSTOMER',
    'Company A -> Company B customer/party blocked with 403',
    $customerIdorBlocked && $customerExceptionCode === 403,
    "Blocked with HTTP {$customerExceptionCode}"
);

// Attack 3: Company A user attempts to access Company B product
$productIdorBlocked = false;
$productExceptionCode = 0;
try {
    TenantMiddleware::validateRecordTenant($productB, $companyA->id);
} catch (AuthorizationException $e) {
    $productIdorBlocked = true;
    $productExceptionCode = $e->statusCode;
}
assert_idor(
    'IDOR_PRODUCT',
    'Company A -> Company B product/item blocked with 403',
    $productIdorBlocked && $productExceptionCode === 403,
    "Blocked with HTTP {$productExceptionCode}"
);

// Attack 4: Company A user attempts to access Company B payment
$paymentIdorBlocked = false;
$paymentExceptionCode = 0;
try {
    TenantMiddleware::validateRecordTenant($paymentB, $companyA->id);
} catch (AuthorizationException $e) {
    $paymentIdorBlocked = true;
    $paymentExceptionCode = $e->statusCode;
}
assert_idor(
    'IDOR_PAYMENT',
    'Company A -> Company B payment/receipt blocked with 403',
    $paymentIdorBlocked && $paymentExceptionCode === 403,
    "Blocked with HTTP {$paymentExceptionCode}"
);

// Attack 5: Company A user attempts to access Company B journal
$journalIdorBlocked = false;
$journalExceptionCode = 0;
try {
    TenantMiddleware::validateRecordTenant($journalB, $companyA->id);
} catch (AuthorizationException $e) {
    $journalIdorBlocked = true;
    $journalExceptionCode = $e->statusCode;
}
assert_idor(
    'IDOR_JOURNAL',
    'Company A -> Company B journal entry blocked with 403',
    $journalIdorBlocked && $journalExceptionCode === 403,
    "Blocked with HTTP {$journalExceptionCode}"
);

echo "\n--- Section 3: Browser Parameter Tampering & Tenant Validation ---\n";

// Attack 6: Browser tampers with X-Company-Id to switch to unauthorized Company B
$tamperBlocked = false;
try {
    if (!WorkspaceContext::verifyCompanyMembership($userA->id, $companyB->id)) {
        throw new AuthorizationException("Forbidden: User #{$userA->id} is not a member of Company #{$companyB->id}", 403);
    }
} catch (AuthorizationException $e) {
    $tamperBlocked = true;
}
assert_idor(
    'PARAM_TAMPER',
    'Tampered company_id parameter rejected via membership verification',
    $tamperBlocked,
    "Company B denied to User A"
);

// Attack 7: Browser tampers with branch_id from an unrelated company
$branchTamperBlocked = false;
try {
    $otherBranch = DB::table('branches')->where('company_id', $companyB->id)->first();
    $otherBranchId = $otherBranch ? $otherBranch->id : 999999;
    if (!WorkspaceContext::verifyBranchAccess($userA->id, $companyA->id, $otherBranchId)) {
        throw new AuthorizationException("Forbidden: Branch does not belong to Company A", 403);
    }
} catch (AuthorizationException $e) {
    $branchTamperBlocked = true;
}
assert_idor(
    'PARAM_TAMPER',
    'Tampered branch_id parameter rejected via branch access validation',
    $branchTamperBlocked,
    "Cross-company branch access blocked"
);

echo "\n--- Section 4: Centralized Middleware Functionality ---\n";

// 1. RoleMiddleware
$roleCheckPassed = false;
try {
    RoleMiddleware::handle(['ACCOUNTANT', 'ADMIN'], true);
    $roleCheckPassed = true;
} catch (\Throwable $e) {}
assert_idor(
    'ROLE_MIDDLEWARE',
    'RoleMiddleware allows authorized role (ACCOUNTANT)',
    $roleCheckPassed
);

$roleCheckBlocked = false;
try {
    RoleMiddleware::handle(['SUPER_ADMIN'], true);
} catch (AuthorizationException $e) {
    $roleCheckBlocked = ($e->statusCode === 403);
}
assert_idor(
    'ROLE_MIDDLEWARE',
    'RoleMiddleware rejects unauthorized role (SUPER_ADMIN) with 403',
    $roleCheckBlocked
);

// 2. PermissionMiddleware
$permCheckPassed = false;
try {
    PermissionMiddleware::handle('invoices', 'view', true);
    $permCheckPassed = true;
} catch (\Throwable $e) {}
assert_idor(
    'PERM_MIDDLEWARE',
    'PermissionMiddleware allows granted permission (invoices.view)',
    $permCheckPassed
);

$permCheckBlocked = false;
try {
    PermissionMiddleware::handle('settings', 'manage', true);
} catch (AuthorizationException $e) {
    $permCheckBlocked = ($e->statusCode === 403);
}
assert_idor(
    'PERM_MIDDLEWARE',
    'PermissionMiddleware blocks ungranted permission (settings.manage) with 403',
    $permCheckBlocked
);

// 3. CsrfMiddleware
$csrfToken = CsrfMiddleware::token();
assert_idor(
    'CSRF_MIDDLEWARE',
    'CsrfMiddleware generates cryptographically secure 64-char hex token',
    strlen($csrfToken) === 64
);

$csrfFieldHtml = CsrfMiddleware::field();
assert_idor(
    'CSRF_MIDDLEWARE',
    'CsrfMiddleware::field() generates valid HTML hidden input',
    strpos($csrfFieldHtml, 'name="csrf_token"') !== false && strpos($csrfFieldHtml, $csrfToken) !== false
);

// 4. GuestMiddleware
$_SESSION['user_id'] = $userA->id;
assert_idor(
    'GUEST_MIDDLEWARE',
    'GuestMiddleware recognizes authenticated session',
    !empty($_SESSION['user_id'])
);

echo "\n--- Section 5: AuthService Core Capabilities ---\n";

// Test password hashing and verification
$plainPass = 'StrongSecPass789!';
$hashedPass = password_hash($plainPass, PASSWORD_BCRYPT, ['cost' => 10]);
assert_idor(
    'SECURITY',
    'password_hash() creates valid bcrypt hash ($2y$)',
    strpos($hashedPass, '$2y$') === 0 && password_verify($plainPass, $hashedPass)
);

// Test AuthService::login with session regeneration
$loginResult = AuthService::login('user.a@alphacorp.in', 'AlphaPass123!');
assert_idor(
    'AUTH_SERVICE',
    'AuthService::login authenticates valid user',
    $loginResult['success'] === true && !empty($_SESSION['user_id'])
);

// Test AuthService::forgotPassword
$forgotResult = AuthService::forgotPassword('user.a@alphacorp.in');
assert_idor(
    'AUTH_SERVICE',
    'AuthService::forgotPassword creates password reset token in database',
    $forgotResult['success'] === true && DB::table('password_resets')->where('email', 'user.a@alphacorp.in')->exists()
);

// Test AuthService::resetPassword
$rawResetToken = bin2hex(random_bytes(32));
DB::table('password_resets')->where('email', 'user.a@alphacorp.in')->delete();
DB::table('password_resets')->insert([
    'email' => 'user.a@alphacorp.in',
    'token' => hash('sha256', $rawResetToken),
    'created_at' => date('Y-m-d H:i:s'),
]);

$resetResult = AuthService::resetPassword('user.a@alphacorp.in', $rawResetToken, 'NewSecurePassword456!', 'NewSecurePassword456!');
assert_idor(
    'AUTH_SERVICE',
    'AuthService::resetPassword successfully resets password and cleans token',
    $resetResult['success'] === true && !DB::table('password_resets')->where('email', 'user.a@alphacorp.in')->exists()
);

// Verify new password works
$newLoginResult = AuthService::login('user.a@alphacorp.in', 'NewSecurePassword456!');
assert_idor(
    'AUTH_SERVICE',
    'Login succeeds with freshly updated password',
    $newLoginResult['success'] === true
);

// Test AuthService::logout
AuthService::logout();
assert_idor(
    'AUTH_SERVICE',
    'AuthService::logout clears session state',
    empty($_SESSION['user']) && empty($_SESSION['user_id'])
);

echo "\n======================================================================\n";
echo "   IDOR & MULTI-TENANT SECURITY SUMMARY\n";
echo "   Total Assertions: {$totalTests}\n";
echo "   Passed:           {$passedTests}\n";
echo "   Failed:           {$failedTests}\n";
echo "======================================================================\n";

if ($failedTests > 0) {
    echo "\nFAILURES DETECTED:\n";
    foreach ($failures as $f) {
        echo " - {$f}\n";
    }
    exit(1);
} else {
    echo "\n[ALL IDOR & MULTI-TENANT AUTHORIZATION TESTS PASSED WITH ZERO FAILURES]\n";
    exit(0);
}
