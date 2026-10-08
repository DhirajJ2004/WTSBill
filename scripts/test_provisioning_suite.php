<?php
/**
 * Comprehensive Automated Test Suite for Company Provisioning & Registration Workflow
 * Tests atomic transactions, rollback, validation, and all foundational entities.
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/../backend/app/Http/Request.php';
App\Database\Database::init();

use App\Models\User;
use App\Models\Company;
use App\Services\CompanyProvisioningService;
use App\Auth\RegistrationService;
use Illuminate\Database\Capsule\Manager as DB;

$passCount = 0;
$failCount = 0;

function assertCondition(string $title, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$title}\n";
        if ($detail) echo "        Detail: {$detail}\n";
    } else {
        $failCount++;
        echo " [FAIL] {$title}\n";
        if ($detail) echo "        Detail: {$detail}\n";
    }
}

echo "====================================================================\n";
echo "      WTSBill ERP Company Provisioning & Registration Test Suite    \n";
echo "====================================================================\n\n";

// Cleanup any remnants of previous test runs if any
$testEmail = 'test.owner@apexhorizon.in';
$testCompName = 'Apex Horizon Technologies Pvt Ltd';
$testGstin = '27AAACA9999Z1Z5';

$existingUser = User::where('email', $testEmail)->first();
if ($existingUser) {
    DB::table('user_branches')->where('user_id', $existingUser->id)->delete();
    DB::table('user_roles')->where('user_id', $existingUser->id)->delete();
    $existingUser->delete();
}
$existingComp = Company::where('name', $testCompName)->first();
if ($existingComp) {
    DB::table('accounting_periods')->where('company_id', $existingComp->id)->delete();
    DB::table('chart_of_accounts')->where('company_id', $existingComp->id)->delete();
    DB::table('gst_configurations')->where('company_id', $existingComp->id)->delete();
    DB::table('tax_rates')->where('company_id', $existingComp->id)->delete();
    DB::table('gst_rates')->where('company_id', $existingComp->id)->delete();
    DB::table('document_numbering_configs')->where('company_id', $existingComp->id)->delete();
    DB::table('document_number_settings')->where('company_id', $existingComp->id)->delete();
    DB::table('audit_logs')->where('company_id', $existingComp->id)->delete();
    DB::table('warehouses')->where('company_id', $existingComp->id)->delete();
    DB::table('branches')->where('company_id', $existingComp->id)->delete();
    DB::table('roles')->where('company_id', $existingComp->id)->delete();
    $existingComp->delete();
}

// -------------------------------------------------------------
// TEST 1: Successful Registration
// -------------------------------------------------------------
echo "--- TEST 1: Successful Registration ---\n";
$regPayload = [
    'company_name' => $testCompName,
    'legal_name' => 'Apex Horizon Technologies Private Limited',
    'gstin' => $testGstin,
    'name' => 'Aditya Kulkarni',
    'email' => $testEmail,
    'password' => 'SecurePass#2026!',
    'phone' => '+91 98209 88888',
    'address' => 'Floor 8, Horizon Tech Park, Viman Nagar',
    'city' => 'Pune',
    'state' => 'Maharashtra',
    'state_code' => '27',
    'business_type' => 'Information Technology',
];

$res1 = RegistrationService::register($regPayload);

assertCondition(
    "1. Successful registration returns success: true and code 201",
    ($res1['success'] ?? false) === true && ($res1['code'] ?? 0) === 201,
    "Company ID: " . ($res1['company']['id'] ?? 'N/A') . ", User ID: " . ($res1['user']['id'] ?? 'N/A')
);

assertCondition(
    "1b. Registration returns valid access_token and does NOT expose sensitive fields",
    !empty($res1['access_token']) && !isset($res1['user']['password']) && !isset($res1['password']),
    "Token generated: " . substr($res1['access_token'] ?? '', 0, 10) . "..."
);

$companyId = $res1['company']['id'] ?? 0;
$userId = $res1['user']['id'] ?? 0;

// -------------------------------------------------------------
// TEST 2: Duplicate Email Validation
// -------------------------------------------------------------
echo "\n--- TEST 2: Duplicate Email Validation ---\n";
$res2 = RegistrationService::register([
    'company_name' => 'Another Company Ltd',
    'name' => 'Duplicate Attempt',
    'email' => $testEmail,
    'password' => 'AnotherPass123!',
]);

assertCondition(
    "2. Duplicate email is rejected with 422",
    ($res2['success'] ?? true) === false && ($res2['code'] ?? 0) === 422,
    "Response message: " . ($res2['message'] ?? '')
);

// -------------------------------------------------------------
// TEST 3: Invalid Input Validation
// -------------------------------------------------------------
echo "\n--- TEST 3: Invalid Input Validation ---\n";
$res3a = RegistrationService::register([
    'company_name' => '', // blank company
    'name' => 'Valid Name',
    'email' => 'valid.email@example.com',
    'password' => 'ValidPass123!',
]);
assertCondition(
    "3a. Blank company name rejected with 422",
    ($res3a['success'] ?? true) === false && ($res3a['code'] ?? 0) === 422,
    "Message: " . ($res3a['message'] ?? '')
);

$res3b = RegistrationService::register([
    'company_name' => 'Some Company',
    'name' => 'Valid Name',
    'email' => 'invalid-email-string', // invalid email
    'password' => 'ValidPass123!',
]);
assertCondition(
    "3b. Invalid email format rejected with 422",
    ($res3b['success'] ?? true) === false && ($res3b['code'] ?? 0) === 422,
    "Message: " . ($res3b['message'] ?? '')
);

$res3c = RegistrationService::register([
    'company_name' => 'Some Company',
    'name' => 'Valid Name',
    'email' => 'valid2@example.com',
    'password' => 'short', // short password
]);
assertCondition(
    "3c. Short password (<8 chars) rejected with 422",
    ($res3c['success'] ?? true) === false && ($res3c['code'] ?? 0) === 422,
    "Message: " . ($res3c['message'] ?? '')
);

$res3d = RegistrationService::register([
    'company_name' => 'Some Company',
    'name' => 'Valid Name',
    'email' => 'valid3@example.com',
    'password' => 'ValidPassword123!',
    'gstin' => 'INVALID_GSTIN_123',
]);
assertCondition(
    "3d. Malformed GSTIN rejected with 422",
    ($res3d['success'] ?? true) === false && ($res3d['code'] ?? 0) === 422,
    "Message: " . ($res3d['message'] ?? '')
);

$res3e = RegistrationService::register([
    'company_name' => 'Different Company',
    'name' => 'Different Name',
    'email' => 'unique4@example.com',
    'password' => 'ValidPassword123!',
    'gstin' => $testGstin, // Duplicate GSTIN
]);
assertCondition(
    "3e. Duplicate GSTIN rejected with 422",
    ($res3e['success'] ?? true) === false && ($res3e['code'] ?? 0) === 422,
    "Message: " . ($res3e['message'] ?? '')
);

// -------------------------------------------------------------
// TEST 4 & 5: Mid-Transaction Failure & Atomic Rollback Verification
// -------------------------------------------------------------
echo "\n--- TEST 4 & 5: Mid-Transaction Failure & Rollback Verification ---\n";
$rollbackEmail = 'fail.user@rollbacktest.in';
$rollbackComp = 'Rollback Fail Company Pvt Ltd';

// Ensure clean slate for rollback test
User::where('email', $rollbackEmail)->delete();
Company::where('name', $rollbackComp)->delete();

$resFail = CompanyProvisioningService::provisionCompany([
    'company_name' => $rollbackComp,
    'name' => 'Rollback User',
    'email' => $rollbackEmail,
    'password' => 'SecurePass#2026!',
], null, 'after_branch'); // simulate database failure after branch creation

assertCondition(
    "4. Mid-transaction failure caught and reported as error",
    ($resFail['success'] ?? true) === false && ($resFail['code'] ?? 0) === 500,
    "Failure Message: " . ($resFail['message'] ?? '')
);

$userExistsAfterRollback = User::where('email', $rollbackEmail)->exists();
$compExistsAfterRollback = Company::where('name', $rollbackComp)->exists();
$branchesAfterRollback = DB::table('branches')->where('name', 'Head Office')->where('address', 'Head Office Address')->where('email', $rollbackEmail)->count();

assertCondition(
    "5. Transaction ROLLBACK: User was completely rolled back",
    !$userExistsAfterRollback,
    "User exists in DB: " . ($userExistsAfterRollback ? 'YES (FAIL)' : 'NO (ROLLED BACK)')
);

assertCondition(
    "5b. Transaction ROLLBACK: Company was completely rolled back",
    !$compExistsAfterRollback,
    "Company exists in DB: " . ($compExistsAfterRollback ? 'YES (FAIL)' : 'NO (ROLLED BACK)')
);

// -------------------------------------------------------------
// TEST 6: Verify Owner Membership
// -------------------------------------------------------------
echo "\n--- TEST 6: Verify Owner Membership ---\n";
$ownerRole = DB::table('user_roles')
    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
    ->where('user_roles.user_id', $userId)
    ->where('user_roles.company_id', $companyId)
    ->select('roles.name as role_name', 'roles.slug')
    ->first();

assertCondition(
    "6. User is assigned 'Owner' role for the provisioned company",
    $ownerRole !== null && strtolower($ownerRole->role_name) === 'owner',
    "Role: " . ($ownerRole->role_name ?? 'NONE')
);

// -------------------------------------------------------------
// TEST 7: Verify Default Branch
// -------------------------------------------------------------
echo "\n--- TEST 7: Verify Default Branch ---\n";
$branch = DB::table('branches')
    ->where('company_id', $companyId)
    ->where('is_main_branch', 1)
    ->first();

$userBranchLink = DB::table('user_branches')
    ->where('company_id', $companyId)
    ->where('user_id', $userId)
    ->where('branch_id', $branch->id ?? 0)
    ->exists();

assertCondition(
    "7. Default branch 'Head Office' created with code 'HO-01' and user linked",
    $branch !== null && $branch->branch_code === 'HO-01' && $userBranchLink,
    "Branch: " . ($branch->name ?? 'NONE') . " (ID: " . ($branch->id ?? 0) . "), User Linked: " . ($userBranchLink ? 'YES' : 'NO')
);

// -------------------------------------------------------------
// TEST 8: Verify Default Warehouse
// -------------------------------------------------------------
echo "\n--- TEST 8: Verify Default Warehouse ---\n";
$warehouse = DB::table('warehouses')
    ->where('company_id', $companyId)
    ->where('is_primary', 1)
    ->first();

assertCondition(
    "8. Default warehouse 'Main Warehouse' created with code 'MWH-01' linked to branch",
    $warehouse !== null && $warehouse->code === 'MWH-01' && (int)$warehouse->branch_id === (int)$branch->id,
    "Warehouse: " . ($warehouse->name ?? 'NONE') . ", Branch ID: " . ($warehouse->branch_id ?? 0)
);

// -------------------------------------------------------------
// TEST 9: Verify Financial Year
// -------------------------------------------------------------
echo "\n--- TEST 9: Verify Financial Year ---\n";
$period = DB::table('accounting_periods')
    ->where('company_id', $companyId)
    ->first();

assertCondition(
    "9. Financial year accounting period created and active",
    $period !== null && !empty($period->financial_year) && (int)$period->is_closed === 0,
    "Period Name: " . ($period->period_name ?? 'NONE') . ", FY: " . ($period->financial_year ?? '') . ", Closed: " . ($period->is_closed ?? 0)
);

// -------------------------------------------------------------
// TEST 10: Verify Chart of Accounts
// -------------------------------------------------------------
echo "\n--- TEST 10: Verify Chart of Accounts ---\n";
$coaCount = DB::table('chart_of_accounts')->where('company_id', $companyId)->count();
$assetCount = DB::table('chart_of_accounts')->where('company_id', $companyId)->where('account_type', 'ASSET')->count();
$liabilityCount = DB::table('chart_of_accounts')->where('company_id', $companyId)->where('account_type', 'LIABILITY')->count();
$equityCount = DB::table('chart_of_accounts')->where('company_id', $companyId)->where('account_type', 'EQUITY')->count();
$incomeCount = DB::table('chart_of_accounts')->where('company_id', $companyId)->where('account_type', 'INCOME')->count();
$expenseCount = DB::table('chart_of_accounts')->where('company_id', $companyId)->where('account_type', 'EXPENSE')->count();

assertCondition(
    "10. Standard Chart of Accounts seeded across all 5 account types",
    $coaCount >= 10 && $assetCount > 0 && $liabilityCount > 0 && $equityCount > 0 && $incomeCount > 0 && $expenseCount > 0,
    "Total Accounts: {$coaCount} (Assets: {$assetCount}, Liabilities: {$liabilityCount}, Equity: {$equityCount}, Income: {$incomeCount}, Expenses: {$expenseCount})"
);

// -------------------------------------------------------------
// TEST 11: Verify Audit Log
// -------------------------------------------------------------
echo "\n--- TEST 11: Verify Audit Log ---\n";
$auditLog = DB::table('audit_logs')
    ->where('company_id', $companyId)
    ->where('action', 'COMPANY_PROVISIONED')
    ->first();

assertCondition(
    "11. Audit log recorded for company provisioning with detailed description",
    $auditLog !== null && !empty($auditLog->description),
    "Action: " . ($auditLog->action ?? 'NONE') . ", User: " . ($auditLog->user_name ?? '') . ", Desc: " . substr($auditLog->description ?? '', 0, 75) . "..."
);

// -------------------------------------------------------------
// TEST 12: Verify Default Tax & Numbering Configuration
// -------------------------------------------------------------
echo "\n--- TEST 12: Verify Tax & Numbering Configuration ---\n";
$gstConfig = DB::table('gst_configurations')->where('company_id', $companyId)->first();
$taxRatesCount = DB::table('tax_rates')->where('company_id', $companyId)->count();
$docNumCount = DB::table('document_numbering_configs')->where('company_id', $companyId)->count();

assertCondition(
    "12. GST configurations, standard tax rates, and document numbering configs created",
    $gstConfig !== null && $taxRatesCount >= 5 && $docNumCount >= 6,
    "GST Registered: " . ($gstConfig->gst_registered ?? 0) . ", Tax Rates: {$taxRatesCount}, Doc Sequences: {$docNumCount}"
);

// -------------------------------------------------------------
// TEST 13: HTTP REST API Registration Endpoint (/api/v1/auth/register)
// -------------------------------------------------------------
echo "\n--- TEST 13: HTTP REST API Registration (/api/v1/auth/register) ---\n";
$httpEmail = 'api.owner@novasolutions.in';
$httpComp = 'Nova Cloud Solutions Pvt Ltd';
$cleanUser = User::where('email', $httpEmail)->first();
if ($cleanUser) {
    DB::table('user_branches')->where('user_id', $cleanUser->id)->delete();
    DB::table('user_roles')->where('user_id', $cleanUser->id)->delete();
    $cleanUser->delete();
}
$cleanComp = Company::where('name', $httpComp)->first();
if ($cleanComp) {
    DB::table('accounting_periods')->where('company_id', $cleanComp->id)->delete();
    DB::table('chart_of_accounts')->where('company_id', $cleanComp->id)->delete();
    DB::table('gst_configurations')->where('company_id', $cleanComp->id)->delete();
    DB::table('tax_rates')->where('company_id', $cleanComp->id)->delete();
    DB::table('gst_rates')->where('company_id', $cleanComp->id)->delete();
    DB::table('document_numbering_configs')->where('company_id', $cleanComp->id)->delete();
    DB::table('document_number_settings')->where('company_id', $cleanComp->id)->delete();
    DB::table('audit_logs')->where('company_id', $cleanComp->id)->delete();
    DB::table('warehouses')->where('company_id', $cleanComp->id)->delete();
    DB::table('branches')->where('company_id', $cleanComp->id)->delete();
    DB::table('roles')->where('company_id', $cleanComp->id)->delete();
    $cleanComp->delete();
}

$ch = curl_init('http://127.0.0.1:8000/api/v1/auth/register');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'company_name' => $httpComp,
    'name' => 'Kavita Singhania',
    'email' => $httpEmail,
    'password' => 'NovaStrongPass#2026',
    'phone' => '+91 99887 66554',
    'city' => 'Bengaluru',
    'state' => 'Karnataka',
    'state_code' => '29',
]));
$rawRes = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$jsonRes = json_decode($rawRes, true);

assertCondition(
    "13. HTTP API registration returns status: success (HTTP 201) with access_token",
    $httpCode === 201 && ($jsonRes['status'] ?? '') === 'success' && !empty($jsonRes['access_token']),
    "HTTP {$httpCode}, Company: " . ($jsonRes['company']['name'] ?? '') . ", User: " . ($jsonRes['user']['name'] ?? '')
);

echo "\n====================================================================\n";
echo "   PROVISIONING TEST SUITE SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
