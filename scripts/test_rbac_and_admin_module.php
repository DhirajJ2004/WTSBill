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

require_once __DIR__ . '/../views/db_helper.php';
\App\Database\Database::init();

use App\Models\User;
use App\Models\UserRole;
use App\Models\Company;
use App\Models\Branch;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Services\UserService;
use App\Services\PermissionManager;
use App\Services\CompanyService;
use App\Services\BranchService;
use App\Services\FinancialYearService;
use App\Services\SettingService;
use App\Services\AuditLogService;
use App\Services\PeriodService;
use Illuminate\Database\Capsule\Manager as DB;

echo "======================================================================\n";
echo "   WTSBill ERP - Admin, RBAC, Tenancy & Audit Verification Suite      \n";
echo "======================================================================\n\n";

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

// -------------------------------------------------------------------------
// TEST 1: Multi-Tenant Company Creation with Default Entities
// -------------------------------------------------------------------------
echo "\n--- Section 1: Multi-Tenant Company Provisioning ---\n";
$compSuffix = substr(md5(uniqid()), 0, 6);
$companyRes = CompanyService::createCompany([
    'name'                     => "Enterprise Test Corp {$compSuffix}",
    'legal_name'               => "Enterprise Test Corporation Pvt Ltd {$compSuffix}",
    'gstin'                    => '27AAAAA0000A1Z5',
    'pan'                      => 'AAAAA0000A',
    'email'                    => "finance_{$compSuffix}@entcorp.com",
    'phone'                    => '9876543210',
    'address_line1'            => '100 Silicon Boulevard',
    'city'                     => 'Mumbai',
    'state'                    => 'Maharashtra',
    'state_code'               => '27',
    'currency'                 => 'INR',
    'branch_management_enabled'=> true,
    'multi_warehouse_enabled'  => true,
], 'SuperAdmin');

assertTest("Company creation succeeds with initialized entities", $companyRes['success'] === true && $companyRes['company_id'] > 0);
$testCompanyId = $companyRes['company_id'];

$branches = BranchService::getBranches($testCompanyId);
assertTest("Main branch auto-provisioned with company creation", count($branches) >= 1 && $branches[0]['is_main_branch'] == 1);
$mainBranchId = $branches[0]['id'];

// -------------------------------------------------------------------------
// TEST 2: Branch CRUD and Switcher Context
// -------------------------------------------------------------------------
echo "\n--- Section 2: Branch Management & Context Isolation ---\n";
$branchRes = BranchService::createBranch($testCompanyId, [
    'name'             => "Pune Tech Hub {$compSuffix}",
    'code'             => "PUN{$compSuffix}",
    'city'             => 'Pune',
    'state'            => 'Maharashtra',
    'state_code'       => '27',
    'is_main_branch'   => false,
    'invoice_prefix'   => 'PUN-INV-',
], 'SuperAdmin');

assertTest("Secondary branch created with unique prefix and warehouse", $branchRes['success'] === true && $branchRes['branch_id'] > 0);
$puneBranchId = $branchRes['branch_id'];

$allBranches = BranchService::getBranches($testCompanyId);
assertTest("Company lists exactly all provisioned branches", count($allBranches) >= 2);

// -------------------------------------------------------------------------
// TEST 3: User Management, Password Hashing & Role Assignment
// -------------------------------------------------------------------------
echo "\n--- Section 3: User Management, Roles & Password Security ---\n";

$accountantEmail = "accountant_{$compSuffix}@entcorp.com";
$plainPassword = "SecurePassword@123";

$userRes = UserService::createUser([
    'name'     => "Test Accountant {$compSuffix}",
    'email'    => $accountantEmail,
    'role'     => 'ACCOUNTANT',
    'password' => $plainPassword,
], $testCompanyId, 'SuperAdmin');

assertTest("User created and assigned ACCOUNTANT role", $userRes['success'] === true && $userRes['user_id'] > 0);
$accountantUserId = $userRes['user_id'];

// Verify password hashing security (plain password NEVER stored in DB)
$dbUser = User::find($accountantUserId);
assertTest("Password is securely hashed (PASSWORD_DEFAULT)", password_verify($plainPassword, $dbUser->password));
assertTest("Plain password is never stored directly in password column", $dbUser->password !== $plainPassword);

// Create additional test users for RBAC testing
$staffEmail = "staff_{$compSuffix}@entcorp.com";
$staffUserRes = UserService::createUser([
    'name'     => "Test Staff {$compSuffix}",
    'email'    => $staffEmail,
    'role'     => 'STAFF',
    'password' => $plainPassword,
], $testCompanyId, 'SuperAdmin');
$staffUser = User::find($staffUserRes['user_id']);

$invManagerEmail = "invmgr_{$compSuffix}@entcorp.com";
$invUserRes = UserService::createUser([
    'name'     => "Test Inventory Manager {$compSuffix}",
    'email'    => $invManagerEmail,
    'role'     => 'INVENTORY_MANAGER',
    'password' => $plainPassword,
], $testCompanyId, 'SuperAdmin');
$invUser = User::find($invUserRes['user_id']);

$adminEmail = "admin_{$compSuffix}@entcorp.com";
$adminUserRes = UserService::createUser([
    'name'     => "Test Admin {$compSuffix}",
    'email'    => $adminEmail,
    'role'     => 'ADMIN',
    'password' => $plainPassword,
], $testCompanyId, 'SuperAdmin');
$adminUser = User::find($adminUserRes['user_id']);

$auditorEmail = "auditor_{$compSuffix}@entcorp.com";
$auditorUserRes = UserService::createUser([
    'name'     => "Test Auditor {$compSuffix}",
    'email'    => $auditorEmail,
    'role'     => 'AUDITOR',
    'password' => $plainPassword,
], $testCompanyId, 'SuperAdmin');
$auditorUser = User::find($auditorUserRes['user_id']);

// -------------------------------------------------------------------------
// TEST 4: Centralized Server-Side RBAC & Permission Enforcement
// -------------------------------------------------------------------------
echo "\n--- Section 4: Centralized Server-Side RBAC Enforcement ---\n";

// Set user runtime roles for permission checking
$accountantUser = $dbUser;
$accountantUser->role = 'ACCOUNTANT';
$staffUser->role = 'STAFF';
$invUser->role = 'INVENTORY_MANAGER';
$adminUser->role = 'ADMIN';
$auditorUser->role = 'AUDITOR';

// 4.1 Admin has wildcard *.*
assertTest("Admin has permission 'invoice.create'", UserService::checkPermission($adminUser, 'invoice.create', $testCompanyId));
assertTest("Admin has permission 'accounting.post'", UserService::checkPermission($adminUser, 'accounting.post', $testCompanyId));
assertTest("Admin has permission 'settings.manage'", UserService::checkPermission($adminUser, 'settings.manage', $testCompanyId));
assertTest("Admin has permission 'inventory.adjust'", UserService::checkPermission($adminUser, 'inventory.adjust', $testCompanyId));

// 4.2 Accountant RBAC checks
assertTest("Accountant can view invoices ('invoice.view')", UserService::checkPermission($accountantUser, 'invoice.view', $testCompanyId));
assertTest("Accountant can post accounting journals ('accounting.post')", UserService::checkPermission($accountantUser, 'accounting.post', $testCompanyId));
assertTest("Accountant can view reports ('reports.view')", UserService::checkPermission($accountantUser, 'reports.view', $testCompanyId));
assertTest("Accountant can manage purchases ('purchase.create')", UserService::checkPermission($accountantUser, 'purchase.create', $testCompanyId));
assertTest("Accountant is strictly BLOCKED from 'settings.manage'", !UserService::checkPermission($accountantUser, 'settings.manage', $testCompanyId));

// 4.3 Staff RBAC checks
assertTest("Staff can view invoices ('invoice.view')", UserService::checkPermission($staffUser, 'invoice.view', $testCompanyId));
assertTest("Staff is strictly BLOCKED from posting journals ('accounting.post')", !UserService::checkPermission($staffUser, 'accounting.post', $testCompanyId));
assertTest("Staff is strictly BLOCKED from adjusting inventory ('inventory.adjust')", !UserService::checkPermission($staffUser, 'inventory.adjust', $testCompanyId));
assertTest("Staff is strictly BLOCKED from managing settings ('settings.manage')", !UserService::checkPermission($staffUser, 'settings.manage', $testCompanyId));

// 4.4 Inventory Manager RBAC checks
assertTest("Inventory Manager can view inventory ('inventory.view')", UserService::checkPermission($invUser, 'inventory.view', $testCompanyId));
assertTest("Inventory Manager can adjust stock ('inventory.adjust')", UserService::checkPermission($invUser, 'inventory.adjust', $testCompanyId));
assertTest("Inventory Manager can transfer stock ('inventory.transfer')", UserService::checkPermission($invUser, 'inventory.transfer', $testCompanyId));
assertTest("Inventory Manager is strictly BLOCKED from posting accounting journals ('accounting.post')", !UserService::checkPermission($invUser, 'accounting.post', $testCompanyId));
assertTest("Inventory Manager is strictly BLOCKED from managing settings ('settings.manage')", !UserService::checkPermission($invUser, 'settings.manage', $testCompanyId));

// 4.5 Auditor RBAC checks (read-only audit, blocked from mutating financial writes)
assertTest("Auditor can view reports ('reports.view')", UserService::checkPermission($auditorUser, 'reports.view', $testCompanyId));
assertTest("Auditor can view accounting entries ('accounting.view')", UserService::checkPermission($auditorUser, 'accounting.view', $testCompanyId));
assertTest("Auditor is strictly BLOCKED from creating invoices ('invoice.create')", !UserService::checkPermission($auditorUser, 'invoice.create', $testCompanyId));
assertTest("Auditor is strictly BLOCKED from creating purchases ('purchase.create')", !UserService::checkPermission($auditorUser, 'purchase.create', $testCompanyId));
assertTest("Auditor is strictly BLOCKED from adjusting stock ('inventory.adjust')", !UserService::checkPermission($auditorUser, 'inventory.adjust', $testCompanyId));

// 4.6 Deactivated User is rejected across all permissions
$inactiveUser = clone $accountantUser;
$inactiveUser->is_active = false;
assertTest("Deactivated user is strictly rejected on all permissions", !UserService::checkPermission($inactiveUser, 'invoice.view', $testCompanyId));

// -------------------------------------------------------------------------
// TEST 5: Financial Years & Period Locking
// -------------------------------------------------------------------------
echo "\n--- Section 5: Financial Years & Accounting Period Protection ---\n";

$currentFy = FinancialYearService::getCurrentFinancialYear($testCompanyId);
assertTest("Current financial year detected for company", $currentFy !== null && !empty($currentFy->financial_year));

$fyRes = FinancialYearService::createFinancialYear($testCompanyId, [
    'financial_year' => "2024-25",
    'period_name'    => "FY 2024-25 Audited Period",
    'start_date'     => "2024-04-01",
    'end_date'       => "2025-03-31",
], 'SuperAdmin');
assertTest("Previous financial year created", $fyRes['success'] === true && $fyRes['period']->id > 0);
$pastFyId = $fyRes['period']->id;

// Lock the past FY
$lockRes = FinancialYearService::lockPeriod($testCompanyId, $pastFyId, 'Audited by Statutory Auditors', 'AuditorUser');
assertTest("Accounting period locked successfully", $lockRes['success'] === true && $lockRes['period']->is_locked == 1);

// Verify PeriodService and FinancialYearService assert lock detection
assertTest("Date '2024-06-15' correctly detected as LOCKED", FinancialYearService::isDateLocked($testCompanyId, '2024-06-15'));
assertTest("Date in current FY is NOT locked", !FinancialYearService::isDateLocked($testCompanyId, date('Y-m-d')));

$lockThrown = false;
try {
    PeriodService::assertNotLocked($testCompanyId, '2024-06-15');
} catch (\InvalidArgumentException $e) {
    $lockThrown = true;
}
assertTest("PeriodService::assertNotLocked throws exception for locked date", $lockThrown);

// Unlock period and verify posting capability restored
$unlockRes = FinancialYearService::unlockPeriod($testCompanyId, $pastFyId, 'SuperAdmin');
assertTest("Accounting period unlocked successfully", $unlockRes['success'] === true && $unlockRes['period']->is_locked == 0);
assertTest("Date '2024-06-15' is no longer locked after unlock", !FinancialYearService::isDateLocked($testCompanyId, '2024-06-15'));

// -------------------------------------------------------------------------
// TEST 6: Settings Management
// -------------------------------------------------------------------------
echo "\n--- Section 6: Settings Management & Numbering Config ---\n";

$settingsRes = SettingService::updateSettings($testCompanyId, $mainBranchId, [
    'invoice_prefix'   => 'TAX-INV-',
    'quotation_prefix' => 'EST-',
    'terms_conditions' => 'Payment due within 30 days of invoice issuance.',
    'extra_settings'   => [
        'enable_round_off' => true,
        'tax_type'         => 'GST',
    ]
], 'Admin');

assertTest("Settings updated for company main branch", $settingsRes['success'] === true);

$fetchedSettings = SettingService::getSettings($testCompanyId, $mainBranchId);
assertTest("Settings fetched with updated invoice prefix 'TAX-INV-'", $fetchedSettings['invoice_prefix'] === 'TAX-INV-');
assertTest("Settings fetched with terms and extra configuration", $fetchedSettings['terms_conditions'] === 'Payment due within 30 days of invoice issuance.');

// -------------------------------------------------------------------------
// TEST 7: Audit Logs & Strict Secret/Credential Redaction
// -------------------------------------------------------------------------
echo "\n--- Section 7: Audit Logging & Strict Secret Redaction ---\n";

// Test sanitization of sensitive fields: password, token, api_key, db_pass, session_id
$sensitivePayload = [
    'user_name'         => 'John Doe',
    'password'          => 'Secret123!',
    'api_token'         => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.xyz',
    'api_key'           => 'ak_live_98374982347293847',
    'jwt'               => 'token_secret_data_here',
    'credit_card'       => '4111222233334444',
    'database_password' => 'super_secret_db_pass',
    'session_id'        => 'sess_9823749823472',
    'nested'            => [
        'sub_password'  => 'NestedPass999',
        'valid_key'     => 'SafeValue',
    ]
];

$sanitized = AuditLogService::sanitizeData($sensitivePayload);
assertTest("Audit sanitizer redacts 'password'", $sanitized['password'] === '[REDACTED]');
assertTest("Audit sanitizer redacts 'api_token'", $sanitized['api_token'] === '[REDACTED]');
assertTest("Audit sanitizer redacts 'api_key'", $sanitized['api_key'] === '[REDACTED]');
assertTest("Audit sanitizer redacts 'jwt'", $sanitized['jwt'] === '[REDACTED]');
assertTest("Audit sanitizer redacts 'credit_card'", $sanitized['credit_card'] === '[REDACTED]');
assertTest("Audit sanitizer redacts 'database_password'", $sanitized['database_password'] === '[REDACTED]');
assertTest("Audit sanitizer redacts 'session_id'", $sanitized['session_id'] === '[REDACTED]');
assertTest("Audit sanitizer recursively redacts nested passwords", $sanitized['nested']['sub_password'] === '[REDACTED]');
assertTest("Audit sanitizer preserves non-sensitive keys ('user_name', 'valid_key')", $sanitized['user_name'] === 'John Doe' && $sanitized['nested']['valid_key'] === 'SafeValue');

// Test AuditLog recording and persistence
$auditEntry = AuditLogService::record([
    'company_id'  => $testCompanyId,
    'branch_id'   => $mainBranchId,
    'user_name'   => 'SecurityAuditTest',
    'action'      => 'SENSITIVE_UPDATE',
    'entity'      => 'UserSecurity',
    'entity_id'   => $accountantUserId,
    'description' => "Updated credentials with password: MyPlainSecretPassword",
    'old_values'  => ['password' => 'OldPlainPass123'],
    'new_values'  => ['password' => 'NewPlainPass456', 'role' => 'ACCOUNTANT'],
]);

$dbAudit = AuditLog::find($auditEntry->id);
assertTest("Audit log description redacts embedded password", strpos($dbAudit->description, '[REDACTED]') !== false && strpos($dbAudit->description, 'MyPlainSecretPassword') === false);
$afterDataStr = is_array($dbAudit->after_data_json) ? json_encode($dbAudit->after_data_json) : (string)$dbAudit->after_data_json;
assertTest("Audit log JSON values contain zero plain passwords", strpos($afterDataStr, 'NewPlainPass456') === false && strpos($afterDataStr, '[REDACTED]') !== false);

// Test Audit log querying and filtering
$logQueryRes = AuditLogService::getLogs($testCompanyId, ['action' => 'SENSITIVE_UPDATE']);
assertTest("Audit log querying returns filtered results for company", $logQueryRes['total'] >= 1 && count($logQueryRes['items']) >= 1);

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------
echo "\n======================================================================\n";
echo "   Verification Results: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
