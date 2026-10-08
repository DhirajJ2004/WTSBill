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
use App\Models\Role;
use App\Models\UserRole;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Capsule\Manager as DB;

echo "======================================================================\n";
echo "       WTSBill ERP - REST API Normalization & Verification Suite      \n";
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

function callApi(string $method, string $uri, array $payload = [], array $headers = []): array {
    $baseUrl = 'http://127.0.0.1:8000';
    $url = $baseUrl . $uri;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headerLines = ['Content-Type: application/json', 'Accept: application/json'];
    foreach ($headers as $k => $v) {
        $headerLines[] = "{$k}: {$v}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);

    if (!empty($payload) || in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $body = substr($rawResponse, $headerSize);
    $json = json_decode($body, true);

    return [
        'code' => $httpCode,
        'body' => $body,
        'json' => $json,
    ];
}

// -------------------------------------------------------------------------
// 1. Setup Active Admin User & Perform Authentication
// -------------------------------------------------------------------------
echo "\n--- Section 1: Authentication & Standard Login ---\n";

$testEmail = 'admin@wtsbill.test';
$testPass = 'Admin@123';

$adminUser = User::where('email', $testEmail)->first();
if (!$adminUser) {
    $adminUser = User::create([
        'name'               => 'Super Admin',
        'email'              => $testEmail,
        'password'           => password_hash($testPass, PASSWORD_BCRYPT),
        'role'               => 'ADMIN',
        'current_company_id' => 1,
        'is_active'          => true,
        'status'             => 'ACTIVE',
    ]);
} else {
    $adminUser->password = password_hash($testPass, PASSWORD_BCRYPT);
    $adminUser->is_active = true;
    $adminUser->status = 'ACTIVE';
    $adminUser->current_company_id = 1;
    $adminUser->save();
}

$adminRole = Role::firstOrCreate(['company_id' => 1, 'slug' => 'admin'], ['name' => 'Admin']);
UserRole::firstOrCreate(['company_id' => 1, 'user_id' => $adminUser->id], ['role_id' => $adminRole->id]);
DB::table('user_branches')->insertOrIgnore(['company_id' => 1, 'user_id' => $adminUser->id, 'branch_id' => 1]);

$loginRes = callApi('POST', '/api/v1/auth/login', [
    'email'    => $testEmail,
    'password' => $testPass,
]);

assertTest("Login endpoint returns HTTP 200", $loginRes['code'] === 200, "Code: {$loginRes['code']}");
assertTest("Login response has status: 'success'", ($loginRes['json']['status'] ?? '') === 'success');
assertTest("Login response contains token and user payload in 'data'", !empty($loginRes['json']['data']['token']) && !empty($loginRes['json']['data']['user']));

$token = $loginRes['json']['data']['token'] ?? ($loginRes['json']['data']['access_token'] ?? '');
$authHeaders = [
    'Authorization' => "Bearer {$token}",
    'X-Company-Id'  => '1',
    'X-Branch-Id'   => '1',
];

// -------------------------------------------------------------------------
// 2. Consistent Success Schema Checks
// -------------------------------------------------------------------------
echo "\n--- Section 2: Consistent Success Schema Format (status: 'success', data: {}) ---\n";

$meRes = callApi('GET', '/api/v1/auth/me', [], $authHeaders);
assertTest("/api/v1/auth/me returns HTTP 200", $meRes['code'] === 200, "Code: {$meRes['code']}");
assertTest("/api/v1/auth/me matches exact schema {status: 'success', data: {...}}", 
    isset($meRes['json']['status']) && $meRes['json']['status'] === 'success' && 
    array_key_exists('data', $meRes['json']) && !isset($meRes['json']['errors'])
);

$productsRes = callApi('GET', '/api/v1/products', [], $authHeaders);
assertTest("/api/v1/products returns HTTP 200", $productsRes['code'] === 200);
assertTest("/api/v1/products matches {status: 'success', data: {...}}", 
    ($productsRes['json']['status'] ?? '') === 'success' && array_key_exists('data', $productsRes['json'])
);

$customersRes = callApi('GET', '/api/v1/customers', [], $authHeaders);
assertTest("/api/v1/customers returns HTTP 200", $customersRes['code'] === 200);
assertTest("/api/v1/customers matches {status: 'success', data: {...}}", 
    ($customersRes['json']['status'] ?? '') === 'success' && array_key_exists('data', $customersRes['json'])
);

$invoicesRes = callApi('GET', '/api/v1/invoices', [], $authHeaders);
assertTest("/api/v1/invoices returns HTTP 200", $invoicesRes['code'] === 200);
assertTest("/api/v1/invoices matches {status: 'success', data: {...}}", 
    ($invoicesRes['json']['status'] ?? '') === 'success' && array_key_exists('data', $invoicesRes['json'])
);

// -------------------------------------------------------------------------
// 3. Consistent Error Schema Checks & HTTP Status Codes
// -------------------------------------------------------------------------
echo "\n--- Section 3: Consistent Error Schema (status: 'error', message: '...', errors: {}) ---\n";

// 3.1 HTTP 401 Unauthorized (Missing / Invalid Token)
$unauthRes = callApi('GET', '/api/v1/products', [], ['Authorization' => 'Bearer invalid_token_xyz']);
assertTest("Unauthenticated request returns HTTP 401", $unauthRes['code'] === 401, "Code: {$unauthRes['code']}");
assertTest("HTTP 401 matches schema {status: 'error', message: '...', errors: {}}", 
    ($unauthRes['json']['status'] ?? '') === 'error' && !empty($unauthRes['json']['message']) && isset($unauthRes['json']['errors'])
);

// 3.2 HTTP 403 Forbidden (Cross-Tenant Access / Unauthorized Role)
$forbiddenRes = callApi('GET', '/api/v1/customers', [], [
    'Authorization' => "Bearer {$token}",
    'X-Company-Id'  => '99999', // Unauthorized company
]);
assertTest("Cross-tenant request returns HTTP 403", $forbiddenRes['code'] === 403, "Code: {$forbiddenRes['code']}");
assertTest("HTTP 403 matches schema {status: 'error', message: '...', errors: {}}", 
    ($forbiddenRes['json']['status'] ?? '') === 'error' && !empty($forbiddenRes['json']['message'])
);

// 3.3 HTTP 404 Not Found (Resource / Route Not Found)
$notFoundRouteRes = callApi('GET', '/api/v1/non-existent-endpoint-xyz', [], $authHeaders);
assertTest("Unknown API route returns HTTP 404", $notFoundRouteRes['code'] === 404, "Code: {$notFoundRouteRes['code']}");
assertTest("HTTP 404 route matches schema {status: 'error', message: '...', errors: {}}", 
    ($notFoundRouteRes['json']['status'] ?? '') === 'error' && !empty($notFoundRouteRes['json']['message'])
);

$notFoundItemRes = callApi('GET', '/api/v1/customers/9999999', [], $authHeaders);
assertTest("Non-existent resource returns HTTP 404", $notFoundItemRes['code'] === 404, "Code: {$notFoundItemRes['code']}");
assertTest("HTTP 404 resource matches schema {status: 'error', message: '...', errors: {}}", 
    ($notFoundItemRes['json']['status'] ?? '') === 'error' && !empty($notFoundItemRes['json']['message'])
);

// 3.4 HTTP 422 / 400 Validation Error
$validationRes = callApi('POST', '/api/v1/customers', [
    'phone' => '12345',
], $authHeaders);
assertTest("Invalid customer payload returns HTTP 400 or 422 validation error", in_array($validationRes['code'], [400, 422], true), "Code: {$validationRes['code']}");
assertTest("Validation failure matches schema {status: 'error', message: '...', errors: {...}}", 
    ($validationRes['json']['status'] ?? '') === 'error' && !empty($validationRes['json']['message'])
);

// -------------------------------------------------------------------------
// 4. Backward-Compatible Routing: /api/* -> /api/v1/*
// -------------------------------------------------------------------------
echo "\n--- Section 4: Backward-Compatible Routing (/api/* -> /api/v1/*) ---\n";

$legacyLogin = callApi('POST', '/api/auth/login', [
    'email'    => $testEmail,
    'password' => $testPass,
]);
assertTest("Legacy route '/api/auth/login' resolves successfully (HTTP 200)", $legacyLogin['code'] === 200, "Code: {$legacyLogin['code']}");
assertTest("Legacy route response matches normalized schema", ($legacyLogin['json']['status'] ?? '') === 'success');

$legacyProducts = callApi('GET', '/api/products', [], $authHeaders);
assertTest("Legacy route '/api/products' resolves successfully (HTTP 200)", $legacyProducts['code'] === 200, "Code: {$legacyProducts['code']}");
assertTest("Legacy route returns same data structure as /api/v1/products", 
    ($legacyProducts['json']['status'] ?? '') === 'success' && array_key_exists('data', $legacyProducts['json'])
);

$legacyCustomers = callApi('GET', '/api/customers', [], $authHeaders);
assertTest("Legacy route '/api/customers' resolves successfully (HTTP 200)", $legacyCustomers['code'] === 200, "Code: {$legacyCustomers['code']}");

$legacyInvoices = callApi('GET', '/api/invoices', [], $authHeaders);
assertTest("Legacy route '/api/invoices' resolves successfully (HTTP 200)", $legacyInvoices['code'] === 200, "Code: {$legacyInvoices['code']}");

$legacyMetrics = callApi('GET', '/api/dashboard/metrics', [], $authHeaders);
assertTest("Legacy route '/api/dashboard/metrics' resolves successfully (HTTP 200)", $legacyMetrics['code'] === 200, "Code: {$legacyMetrics['code']}");

// -------------------------------------------------------------------------
// 5. Zero Database Exception Leakage Under Fault Injections
// -------------------------------------------------------------------------
echo "\n--- Section 5: Zero Database Exception Leakage & Security Shielding ---\n";

$badSqlPayload = callApi('GET', "/api/v1/customers?search=" . urlencode("'; DROP TABLE customers; --"), [], $authHeaders);
assertTest("SQL injection string safely handled without exposing database error", $badSqlPayload['code'] === 200, "Code: {$badSqlPayload['code']}");

$rawBody = $badSqlPayload['body'];
assertTest("Response body contains zero 'SQLSTATE'", strpos($rawBody, 'SQLSTATE') === false);
assertTest("Response body contains zero 'PDOException'", strpos($rawBody, 'PDOException') === false);
assertTest("Response body contains zero 'QueryException'", strpos($rawBody, 'QueryException') === false);
assertTest("Response body contains zero raw SQL queries", strpos(strtolower($rawBody), 'select * from') === false);

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------
echo "\n======================================================================\n";
echo "   Verification Results: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
