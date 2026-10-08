<?php

/**
 * WTSBill Comprehensive Empirical Audit Probe (V2)
 * Executes live HTTP requests with cookie jar against http://127.0.0.1:8000
 */

$baseUrl = 'http://127.0.0.1:8000';
$cookieFile = __DIR__ . '/audit_cookies.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function http_req($url, $method = 'GET', $data = null, $headers = [], $followRedirects = false) {
    global $cookieFile;
    $ch = curl_init();
    $start = microtime(true);
    
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_FOLLOWLOCATION => $followRedirects,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];
    
    $reqHeaders = [];
    foreach ($headers as $k => $v) {
        $reqHeaders[] = "$k: $v";
    }
    
    if ($data !== null) {
        if (is_array($data)) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
        } else {
            $opts[CURLOPT_POSTFIELDS] = $data;
        }
    }
    
    $opts[CURLOPT_HTTPHEADER] = $reqHeaders;
    curl_setopt_array($ch, $opts);
    
    $response = curl_exec($ch);
    $elapsed = (microtime(true) - $start) * 1000;
    
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    $headerStr = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    preg_match('/^Location:\s*(.*)$/mi', $headerStr, $locMatches);
    $location = isset($locMatches[1]) ? trim($locMatches[1]) : null;
    
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'headers' => $headerStr,
        'body' => $body,
        'location' => $location,
        'elapsed_ms' => round($elapsed, 2),
        'content_type' => $contentType
    ];
}

echo "=================================================================\n";
echo "           STARTING LIVE EMPIRICAL HTTP & MODULE AUDIT (V2)      \n";
echo "=================================================================\n\n";

// 1. ROUTE DISCOVERY & UNAUTHENTICATED INVARIANTS
echo "--- 1. Testing Unauthenticated Route Invariants ---\n";
$unauthRoutes = [
    ['GET', '/', 302, 'Redirect to /login for unauth user'],
    ['GET', '/login', 200, 'Login page rendered'],
    ['GET', '/forgot-password', 200, 'Forgot password page'],
    ['GET', '/reset-password', 200, 'Reset password page'],
    ['GET', '/dashboard', 302, 'Protected page redirects to /login'],
    ['GET', '/invoices', 302, 'Protected invoices page redirects'],
    ['GET', '/purchases', 302, 'Protected purchases page redirects'],
    ['GET', '/inventory', 302, 'Protected inventory page redirects'],
    ['GET', '/accounting', 302, 'Protected accounting page redirects'],
    ['GET', '/reports', 302, 'Protected reports page redirects'],
    ['GET', '/settings', 302, 'Protected settings page redirects'],
    ['GET', '/api/v1/invoices', 401, 'Unauthenticated API endpoint'],
    ['GET', '/api/v1/customers', 401, 'Unauthenticated API customers'],
];

foreach ($unauthRoutes as $r) {
    list($method, $path, $expectedStatus, $desc) = $r;
    $res = http_req($baseUrl . $path, $method);
    $pass = ($res['code'] === $expectedStatus);
    echo sprintf("[%s] %-6s %-25s Expected: %3d | Actual: %3d | %s (%0.1f ms)\n",
        $pass ? 'PASS' : 'FAIL', $method, $path, $expectedStatus, $res['code'], $desc, $res['elapsed_ms']);
}

// 2. AUTHENTICATION LIFECYCLE
echo "\n--- 2. Testing Authentication Lifecycle ---\n";
// Get fresh CSRF token
$resLogin = http_req($baseUrl . '/login', 'GET');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resLogin['body'], $csrfMatches);
$csrfToken = $csrfMatches[1] ?? '';

// Post login with correct credentials
$loginPost = http_req($baseUrl . '/login', 'POST', [
    'csrf_token' => $csrfToken,
    'identifier' => 'anil.d@wtsbill.in',
    'password' => 'password123',
    'remember' => '1'
]);

$isAuthSuccess = ($loginPost['code'] === 302 && (strpos($loginPost['location'], 'select-company') !== false || strpos($loginPost['location'], 'dashboard') !== false));
echo sprintf("[%s] POST /login valid credentials -> Status: %d | Redirect: %s (Session established)\n",
    $isAuthSuccess ? 'PASS' : 'FAIL', $loginPost['code'], $loginPost['location']);

// Select Company Context
$selectCompRes = http_req($baseUrl . '/switch-company?id=1&branch_id=1&fy=2026-2027', 'GET');
echo sprintf("[%s] GET /switch-company?id=1 -> Status: %d | Redirect: %s\n",
    $selectCompRes['code'] === 302 ? 'PASS' : 'FAIL', $selectCompRes['code'], $selectCompRes['location']);

// 3. AUTHENTICATED WEB VIEWS
echo "\n--- 3. Testing Authenticated Web Views ---\n";
$authViews = [
    '/dashboard' => 'Dashboard Overview',
    '/invoices' => 'Sales Invoices',
    '/create-invoice' => 'Create Sales Invoice',
    '/quotations' => 'Quotations',
    '/create-quotation' => 'Create Quotation',
    '/recurring-invoices' => 'Recurring Invoices',
    '/credit-notes' => 'Credit Notes',
    '/debit-notes' => 'Debit Notes',
    '/purchases' => 'Purchase Invoices',
    '/inventory' => 'Inventory & Warehouses',
    '/parties' => 'Parties (Customers & Suppliers)',
    '/payments' => 'Payment Register',
    '/expenses' => 'Expenses Management',
    '/accounting' => 'Accounting & Chart of Accounts',
    '/reports' => 'Financial Reports & GST',
    '/settings' => 'System & Company Settings',
    '/users' => 'User Management & Roles',
    '/audit-logs' => 'Security Audit Logs'
];

foreach ($authViews as $viewPath => $name) {
    $viewRes = http_req($baseUrl . $viewPath, 'GET');
    $pass = ($viewRes['code'] === 200 && strpos($viewRes['body'], 'Fatal error') === false && strpos($viewRes['body'], 'Uncaught') === false);
    echo sprintf("[%s] GET %-22s -> %3d | %-32s (%0.1f ms)\n",
        $pass ? 'PASS' : 'FAIL', $viewPath, $viewRes['code'], $name, $viewRes['elapsed_ms']);
}

// 4. API ENDPOINTS (VERSIONED REST)
echo "\n--- 4. Testing Authenticated Versioned REST Endpoints ---\n";
$apiEndpoints = [
    ['GET', '/api/v1/dashboard/metrics', 200],
    ['GET', '/api/v1/invoices', 200],
    ['GET', '/api/v1/customers', 200],
    ['GET', '/api/v1/suppliers', 200],
    ['GET', '/api/v1/products', 200],
    ['GET', '/api/v1/inventory/warehouses', 200],
    ['GET', '/api/v1/purchases', 200],
    ['GET', '/api/v1/payments', 200],
    ['GET', '/api/v1/expenses', 200],
    ['GET', '/api/v1/trial-balance', 200],
    ['GET', '/api/v1/reports/profit-loss', 200],
    ['GET', '/api/v1/reports/balance-sheet', 200],
    ['GET', '/api/v1/reports/gstr1', 200],
    ['GET', '/api/v1/users', 200],
    ['GET', '/api/v1/nonexistent-endpoint', 404]
];

$authHeaders = [
    'X-Company-Id' => '1',
    'X-Branch-Id' => '1',
    'X-Financial-Year' => '2026-2027',
    'Accept' => 'application/json'
];

foreach ($apiEndpoints as $api) {
    list($method, $path, $expectedStatus) = $api;
    $res = http_req($baseUrl . $path, $method, null, $authHeaders);
    $pass = ($res['code'] === $expectedStatus);
    $json = json_decode($res['body'], true);
    $hasValidStructure = is_array($json);
    echo sprintf("[%s] %-6s %-30s Expected: %3d | Actual: %3d | JSON: %s (%0.1f ms)\n",
        ($pass && ($expectedStatus === 404 || $hasValidStructure)) ? 'PASS' : 'FAIL',
        $method, $path, $expectedStatus, $res['code'], $hasValidStructure ? 'VALID' : 'INVALID', $res['elapsed_ms']);
}

// 5. PRINT / EXPORT
echo "\n--- 5. Testing Print & Document Rendering ---\n";
$printRoutes = [
    '/print-invoice?id=1' => 'Print Invoice #1',
    '/print-quotation?id=1' => 'Print Quotation #1',
    '/print-receipt?id=1' => 'Print Receipt #1',
    '/print-order?id=1' => 'Print Sales Order #1',
    '/print-challan?id=1' => 'Print Delivery Challan #1',
    '/print-credit-note?id=1' => 'Print Credit Note #1',
    '/print-debit-note?id=1' => 'Print Debit Note #1',
    '/print-purchase?id=1' => 'Print Purchase Bill #1',
];

foreach ($printRoutes as $path => $desc) {
    $res = http_req($baseUrl . $path, 'GET');
    $pass = ($res['code'] === 200);
    echo sprintf("[%s] GET %-35s -> %3d | %s (%0.1f ms)\n",
        $pass ? 'PASS' : 'FAIL', $path, $res['code'], $desc, $res['elapsed_ms']);
}

// 6. LOGOUT
echo "\n--- 6. Testing Logout and Session Invalidation ---\n";
$logoutRes = http_req($baseUrl . '/logout', 'GET');
echo sprintf("[%s] GET /logout -> Status: %d | Redirect: %s\n",
    $logoutRes['code'] === 302 ? 'PASS' : 'FAIL', $logoutRes['code'], $logoutRes['location']);

$postLogoutDashboard = http_req($baseUrl . '/dashboard', 'GET');
echo sprintf("[%s] GET /dashboard after logout -> Status: %d (Redirected to login)\n",
    $postLogoutDashboard['code'] === 302 ? 'PASS' : 'FAIL', $postLogoutDashboard['code']);

if (file_exists($cookieFile)) unlink($cookieFile);

echo "\n=================================================================\n";
echo "                  LIVE HTTP AUDIT SUITE COMPLETED                \n";
echo "=================================================================\n";
