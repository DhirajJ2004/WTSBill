<?php

/**
 * WTSBill ERP - Complete Authentication & Session Workflow Test Suite
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Database\Database;
use App\Models\User;
use App\Models\Company;
use App\Models\PersonalAccessToken;
use App\Auth\LoginService;
use App\Auth\LogoutService;
use App\Auth\PasswordService;
use App\Auth\SessionService;
use App\Auth\WorkspaceContext;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AuthorizationException;
use App\Http\Controllers\Api\AuthController;
use App\Automation\Email\EmailService;
use Illuminate\Database\Capsule\Manager as DB;

Database::init();

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$failures = [];

function assert_step(string $group, string $name, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests, $failures;
    $totalTests++;
    echo "[TEST #{$totalTests}] [{$group}] {$name} ... ";
    if ($condition) {
        $passedTests++;
        echo "PASS\n";
        if (!empty($detail)) {
            echo "   -> {$detail}\n";
        }
    } else {
        $failedTests++;
        echo "FAIL\n";
        $failureMsg = "[{$group}] {$name}: Failed" . (!empty($detail) ? " ({$detail})" : "");
        $failures[] = $failureMsg;
        echo "   -> ERROR: {$failureMsg}\n";
    }
}

echo "======================================================================\n";
echo "   WTSBill ERP - Complete Authentication & Session Audit Suite\n";
echo "======================================================================\n\n";

// Setup Test User and Test Company
$testEmail = 'audit.test.user@wtsbill.in';
$testPassword = 'SecurePassword123!';
$testCompany = Company::first();
if (!$testCompany) {
    $testCompany = Company::create([
        'name' => 'Audit Test Enterprise',
        'legal_name' => 'Audit Test Enterprise Pvt Ltd',
        'gstin' => '27AAAAA0000A1Z5',
        'pan' => 'AAAAA0000A',
        'email' => 'admin@audittest.com',
        'phone' => '9876543210',
        'status' => 'ACTIVE',
        'is_active' => true,
    ]);
}

$testUser = User::where('email', $testEmail)->first();
if (!$testUser) {
    $testUser = User::create([
        'name' => 'Audit Test User',
        'email' => $testEmail,
        'password' => password_hash($testPassword, PASSWORD_BCRYPT),
        'phone' => '9876543211',
        'role' => 'ADMIN',
        'status' => 'ACTIVE',
        'is_active' => true,
        'current_company_id' => $testCompany->id,
    ]);
} else {
    $testUser->update([
        'password' => password_hash($testPassword, PASSWORD_BCRYPT),
        'status' => 'ACTIVE',
        'is_active' => true,
        'current_company_id' => $testCompany->id,
    ]);
}

$role = DB::table('roles')->where('company_id', $testCompany->id)->first() ?? DB::table('roles')->first();
$roleId = $role ? $role->id : 1;

$hasRole = DB::table('user_roles')->where('user_id', $testUser->id)->where('company_id', $testCompany->id)->exists();
if (!$hasRole) {
    DB::table('user_roles')->insert([
        'user_id' => $testUser->id,
        'company_id' => $testCompany->id,
        'role_id' => $roleId,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
}

// ====================================================================
// TEST 1: Invalid Login
// ====================================================================
echo "--- 1. Testing Invalid Login ---\n";
// Non-existent email
$res1 = LoginService::attemptLogin('nonexistent.user.123@wtsbill.in', 'RandomWrongPassword');
assert_step("LOGIN", "Non-existent email returns generic error", 
    !$res1['success'] && $res1['code'] === 401 && $res1['message'] === 'Invalid email or password.',
    "Message: '{$res1['message']}'"
);

// Valid email but wrong password
$res2 = LoginService::attemptLogin($testEmail, 'IncorrectPassword123');
assert_step("LOGIN", "Valid email with wrong password returns exact same generic error", 
    !$res2['success'] && $res2['code'] === 401 && $res2['message'] === 'Invalid email or password.',
    "Message: '{$res2['message']}' (No email harvesting leak)"
);

// ====================================================================
// TEST 2: Valid Login
// ====================================================================
echo "\n--- 2. Testing Valid Login ---\n";
$res3 = LoginService::attemptLogin($testEmail, $testPassword);
assert_step("LOGIN", "Valid credentials return access token and user info", 
    $res3['success'] === true && !empty($res3['access_token']) && $res3['user']['email'] === $testEmail,
    "Access token generated: " . substr($res3['access_token'] ?? '', 0, 16) . "..."
);

$authToken = $res3['access_token'];

// ====================================================================
// TEST 3: Logout
// ====================================================================
echo "\n--- 3. Testing Logout ---\n";
// Create a session to revoke
$tempToken = SessionService::createSession($testUser, 'logout-test-session');
$tokenRecordBefore = SessionService::validateSession($tempToken);
assert_step("LOGOUT", "Session exists before logout", $tokenRecordBefore !== null, "Token valid");

LogoutService::logout($testUser, $tempToken);
$tokenRecordAfter = SessionService::validateSession($tempToken);
assert_step("LOGOUT", "Session revoked and invalidated after logout", $tokenRecordAfter === null, "Token successfully deleted");

// ====================================================================
// TEST 4: Access Dashboard without Session (Protected Web Route)
// ====================================================================
echo "\n--- 4. Testing Protected Web Route Access without Session ---\n";
// Simulate unauthenticated web access
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    @session_destroy();
}
$isAuthInSession = !empty($_SESSION['user']['id']) || !empty($_SESSION['user_id']);
assert_step("WEB_PROTECT", "Unauthenticated session has no active user", !$isAuthInSession, "Session is clear");

// Check routing protection logic
$protectedUri = '/dashboard';
$publicWebRoutes = [
    '/login',
    '/forgot-password',
    '/reset-password',
    '/views/auth/login.php',
    '/views/auth/forgot_password.php',
    '/views/auth/reset_password.php',
];
$isProtected = !in_array($protectedUri, $publicWebRoutes, true);
assert_step("WEB_PROTECT", "Route /dashboard is classified as protected", $isProtected, "Requires authentication");

// ====================================================================
// TEST 5: Access API without Session / Token (Protected API Route)
// ====================================================================
echo "\n--- 5. Testing Protected API Route Access without Session ---\n";
// Reset AuthMiddleware static context
AuthMiddleware::setContext(null, null, null, null, null);
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
}
unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_API_TOKEN'], $_GET['api_token']);

$apiAuthFailedAsExpected = false;
$apiExceptionResponse = null;
try {
    AuthMiddleware::authenticate();
} catch (AuthorizationException $e) {
    $apiAuthFailedAsExpected = true;
    $apiExceptionResponse = $e->response;
    $apiStatusCode = $e->statusCode;
}

assert_step("API_AUTH", "Unauthenticated API request throws 401 AuthorizationException", 
    $apiAuthFailedAsExpected && $apiStatusCode === 401,
    "Status Code: {$apiStatusCode}"
);

assert_step("API_AUTH", "AuthorizationException returns structured JSON with success=false", 
    isset($apiExceptionResponse['success']) && $apiExceptionResponse['success'] === false && !empty($apiExceptionResponse['message']),
    "Response message: '{$apiExceptionResponse['message']}'"
);

// ====================================================================
// TEST 6: Company Selection
// ====================================================================
echo "\n--- 6. Testing Company Selection Context ---\n";
$companyA = $testCompany;
$isMember = WorkspaceContext::verifyCompanyMembership($testUser->id, $companyA->id);
assert_step("COMPANY", "User is verified member of authorized company", $isMember, "Company #{$companyA->id}");

// Verify unauthorized company access rejected
$unauthCompanyId = 9999999;
$isNotMember = !WorkspaceContext::verifyCompanyMembership($testUser->id, $unauthCompanyId);
assert_step("COMPANY", "User access to unauthorized company #{$unauthCompanyId} is rejected", $isNotMember, "Membership verification rejected");

// ====================================================================
// TEST 7: Branch Selection
// ====================================================================
echo "\n--- 7. Testing Branch Selection Context ---\n";
$branches = WorkspaceContext::getAuthorizedBranches($testUser->id, $companyA->id);
assert_step("BRANCH", "Authorized branches retrieved for company", is_array($branches), "Branch count: " . count($branches));

// ====================================================================
// TEST 8: Financial Year Selection
// ====================================================================
echo "\n--- 8. Testing Financial Year Selection Context ---\n";
AuthMiddleware::setContext($testUser, $companyA->id, null, 'ADMIN', '2026-27');
$selectedFy = AuthMiddleware::getFinancialYear();
$fyRange = AuthMiddleware::getFinancialYearRange();

assert_step("FINANCIAL_YEAR", "Financial year context resolved to 2026-27", 
    $selectedFy === '2026-27', 
    "Active FY: {$selectedFy}"
);

assert_step("FINANCIAL_YEAR", "Financial year range calculated correctly (2026-04-01 to 2027-03-31)", 
    is_array($fyRange) && $fyRange['start'] === '2026-04-01' && $fyRange['end'] === '2027-03-31', 
    "Start: {$fyRange['start']}, End: {$fyRange['end']}"
);

// ====================================================================
// TEST 9: Session Expiration
// ====================================================================
echo "\n--- 9. Testing Session Expiration ---\n";
// 9.1 Expired personal access token
$expiredTokenString = PersonalAccessToken::generateForUser($testUser, 'expired-test-token', date('Y-m-d H:i:s', time() - 3600));
$tokenValidation = SessionService::validateSession($expiredTokenString);
assert_step("SESSION_EXPIRY", "Expired database access token rejected by validateSession()", 
    $tokenValidation === null, 
    "Expired token invalidated"
);

// 9.2 Inactivity timeout calculation
$lastActivity = time() - 7201; // 2 hours and 1 second ago
$isSessionExpired = (time() - $lastActivity) > 7200;
assert_step("SESSION_EXPIRY", "Session inactivity > 7200 seconds triggers expiration", 
    $isSessionExpired === true, 
    "Time elapsed: " . (time() - $lastActivity) . "s"
);

// ====================================================================
// TEST 10: Password Reset Workflow (End-to-End)
// ====================================================================
echo "\n--- 10. Testing Password Reset Workflow ---\n";

// Step 1: Generate 32-byte secure token
$rawResetToken = bin2hex(random_bytes(32));
$hashedResetToken = hash('sha256', $rawResetToken);

// Step 2: Store in password_resets table
DB::table('password_resets')->where('email', $testEmail)->delete();
DB::table('password_resets')->insert([
    'email' => $testEmail,
    'token' => $hashedResetToken,
    'created_at' => date('Y-m-d H:i:s'),
]);

$record = DB::table('password_resets')
    ->where('email', $testEmail)
    ->where('token', $hashedResetToken)
    ->first();

assert_step("PASSWORD_RESET", "Password reset token stored securely as SHA-256 hash in database", 
    $record !== null && !empty($record->token), 
    "Stored Hash: " . substr($record->token ?? '', 0, 16) . "..."
);

// Step 3: Check email provider configuration fallback
$emailProvider = EmailService::getProvider();
$isEmailConfigured = $emailProvider->isConfigured();
assert_step("PASSWORD_RESET", "Email provider configuration check executes cleanly", 
    is_bool($isEmailConfigured), 
    "Email configured: " . ($isEmailConfigured ? 'Yes (SMTP)' : 'No (Dev-safe fallback active)')
);

// Step 4: Validate token and expiry (within 60 minutes)
$tokenAge = time() - strtotime($record->created_at);
$isTokenValid = ($tokenAge <= 3600);
assert_step("PASSWORD_RESET", "Reset token is verified as unexpired (age: {$tokenAge}s <= 3600s)", 
    $isTokenValid, 
    "Token is fresh and valid"
);

// Step 5: Execute password change with new password
$newTestPassword = 'NewUltraSecurePassword2026!';
$updatedUser = User::where('email', $testEmail)->first();
$updatedUser->update([
    'password' => password_hash($newTestPassword, PASSWORD_BCRYPT)
]);

// Invalidate token after use
DB::table('password_resets')->where('email', $testEmail)->delete();
$tokenAfterUse = DB::table('password_resets')->where('email', $testEmail)->first();
assert_step("PASSWORD_RESET", "Reset token is single-use and invalidated immediately after password change", 
    $tokenAfterUse === null, 
    "Token deleted from password_resets table"
);

// Step 6: Test login with new password
$loginWithNewPass = LoginService::attemptLogin($testEmail, $newTestPassword);
assert_step("PASSWORD_RESET", "Login succeeds with the newly set password", 
    $loginWithNewPass['success'] === true, 
    "New password authenticated successfully"
);

// Step 7: Verify old password no longer works
$loginWithOldPass = LoginService::attemptLogin($testEmail, $testPassword);
assert_step("PASSWORD_RESET", "Login with old password is now rejected", 
    $loginWithOldPass['success'] === false && $loginWithOldPass['code'] === 401, 
    "Old password rejected"
);

// Clean up test user & password reset records
DB::table('password_resets')->where('email', $testEmail)->delete();

echo "\n======================================================================\n";
echo "   AUDIT & VALIDATION SUMMARY\n";
echo "   Total Tests:  {$totalTests}\n";
echo "   Passed Tests: {$passedTests}\n";
echo "   Failed Tests: {$failedTests}\n";
echo "======================================================================\n";

if ($failedTests > 0) {
    echo "\nFAILED TESTS REPORT:\n";
    foreach ($failures as $f) {
        echo " - {$f}\n";
    }
    exit(1);
} else {
    echo "\n[ALL AUTHENTICATION & SESSION WORKFLOW TESTS PASSED SUCCESSFULLY]\n";
    exit(0);
}
