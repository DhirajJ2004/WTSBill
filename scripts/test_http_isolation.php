<?php
/**
 * End-to-End HTTP Multi-Tenant Isolation Test Suite
 * Executed against running HTTP server http://127.0.0.1:8000
 */

$baseUrl = 'http://127.0.0.1:8000/api/v1';

$passCount = 0;
$failCount = 0;

function runHttp(string $method, string $url, array $headers = [], $body = null) {
    $ch = curl_init();
    $curlHeaders = [];
    foreach ($headers as $k => $v) {
        $curlHeaders[] = "{$k}: {$v}";
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($body !== null) {
        $json = is_string($body) ? $body : json_encode($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $curlHeaders[] = 'Content-Type: application/json';
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

    $raw = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $headerStr = substr($raw, 0, $headerSize);
    $bodyStr = substr($raw, $headerSize);
    $data = json_decode($bodyStr, true);

    return [
        'code' => $httpCode,
        'headers' => $headerStr,
        'body' => $data,
        'raw_body' => $bodyStr
    ];
}

function testAssertion(string $testName, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$testName}\n";
        if ($detail) echo "        Detail: {$detail}\n";
    } else {
        $failCount++;
        echo " [FAIL] {$testName}\n";
        if ($detail) echo "        Detail: {$detail}\n";
    }
}

echo "====================================================================\n";
echo "   WTSBill ERP Multi-Tenant Security & Isolation E2E HTTP Suite    \n";
echo "====================================================================\n\n";

// -------------------------------------------------------------
// STEP 1: Authenticate User A -> Company A
// -------------------------------------------------------------
echo "--- TEST 1: User A -> Company A ---\n";
$loginResA = runHttp('POST', "{$baseUrl}/auth/login", [], [
    'email' => 'anil.d@wtsbill.in',
    'password' => 'password123'
]);

$tokenA = $loginResA['body']['access_token'] ?? $loginResA['body']['token'] ?? null;
testAssertion(
    "User A Login (anil.d@wtsbill.in) returns HTTP 200 with Bearer token",
    $loginResA['code'] === 200 && !empty($tokenA),
    "HTTP {$loginResA['code']}, User: " . ($loginResA['body']['user']['name'] ?? '')
);

$companiesResA = runHttp('GET', "{$baseUrl}/auth/companies", [
    'Authorization' => "Bearer {$tokenA}",
    'X-Company-Id' => '1'
]);
$compIdsA = array_column($companiesResA['body']['companies'] ?? [], 'id');
testAssertion(
    "User A sees ONLY authorized Company 1 (Wis Technosavvy)",
    in_array(1, $compIdsA) && !in_array(2, $compIdsA),
    "Visible company IDs for User A: " . json_encode($compIdsA)
);

// -------------------------------------------------------------
// STEP 2: Authenticate User B -> Company B
// -------------------------------------------------------------
echo "\n--- TEST 2: User B -> Company B ---\n";
$loginResB = runHttp('POST', "{$baseUrl}/auth/login", [], [
    'email' => 'vikram.m@innovatech.in',
    'password' => 'password123'
]);

$tokenB = $loginResB['body']['access_token'] ?? $loginResB['body']['token'] ?? null;
testAssertion(
    "User B Login (vikram.m@innovatech.in) returns HTTP 200 with Bearer token",
    $loginResB['code'] === 200 && !empty($tokenB),
    "HTTP {$loginResB['code']}, User: " . ($loginResB['body']['user']['name'] ?? '')
);

$companiesResB = runHttp('GET', "{$baseUrl}/auth/companies", [
    'Authorization' => "Bearer {$tokenB}",
    'X-Company-Id' => '2'
]);
$compIdsB = array_column($companiesResB['body']['companies'] ?? [], 'id');
testAssertion(
    "User B sees ONLY authorized Company 2 (Innovatech Dynamics)",
    in_array(2, $compIdsB) && !in_array(1, $compIdsB),
    "Visible company IDs for User B: " . json_encode($compIdsB)
);

// -------------------------------------------------------------
// STEP 3: User A attempts Company B customer (ID: 21)
// -------------------------------------------------------------
echo "\n--- TEST 3: User A attempts Company B customer (ID: 21) ---\n";
$custResA_B = runHttp('GET', "{$baseUrl}/customers/21", [
    'Authorization' => "Bearer {$tokenA}",
    'X-Company-Id' => '1'
]);
testAssertion(
    "User A attempting Company B Customer 21 fails with HTTP 403 Forbidden",
    $custResA_B['code'] === 403,
    "HTTP {$custResA_B['code']}, Response: " . ($custResA_B['body']['message'] ?? $custResA_B['raw_body'])
);

// -------------------------------------------------------------
// STEP 4: User A attempts Company B invoice (ID: 2)
// -------------------------------------------------------------
echo "\n--- TEST 4: User A attempts Company B invoice (ID: 2) ---\n";
$invResA_B = runHttp('GET', "{$baseUrl}/invoices/2", [
    'Authorization' => "Bearer {$tokenA}",
    'X-Company-Id' => '1'
]);
testAssertion(
    "User A attempting Company B Invoice 2 fails with HTTP 403 Forbidden",
    $invResA_B['code'] === 403,
    "HTTP {$invResA_B['code']}, Response: " . ($invResA_B['body']['message'] ?? $invResA_B['raw_body'])
);

// -------------------------------------------------------------
// STEP 5: User A attempts Company B payment (ID: 1)
// -------------------------------------------------------------
echo "\n--- TEST 5: User A attempts Company B payment (ID: 1) ---\n";
$payResA_B = runHttp('GET', "{$baseUrl}/payments/1", [
    'Authorization' => "Bearer {$tokenA}",
    'X-Company-Id' => '1'
]);
testAssertion(
    "User A attempting Company B Payment 1 fails with HTTP 403 Forbidden",
    $payResA_B['code'] === 403,
    "HTTP {$payResA_B['code']}, Response: " . ($payResA_B['body']['message'] ?? $payResA_B['raw_body'])
);

// -------------------------------------------------------------
// STEP 6: User A attempts Company B report
// -------------------------------------------------------------
echo "\n--- TEST 6: User A attempts Company B report ---\n";
// Attempt with forged query param
$repResA_B = runHttp('GET', "{$baseUrl}/reports/profit-loss?company_id=2", [
    'Authorization' => "Bearer {$tokenA}",
    'X-Company-Id' => '1'
]);
testAssertion(
    "User A requesting Company B Profit & Loss report fails with HTTP 403 Forbidden",
    $repResA_B['code'] === 403,
    "HTTP {$repResA_B['code']}, Response: " . ($repResA_B['body']['message'] ?? $repResA_B['raw_body'])
);

// Attempt with forged header X-Company-Id: 2
$forgeResA = runHttp('GET', "{$baseUrl}/reports/profit-loss", [
    'Authorization' => "Bearer {$tokenA}",
    'X-Company-Id' => '2'
]);
testAssertion(
    "User A forging X-Company-Id: 2 header fails with HTTP 403 Forbidden",
    $forgeResA['code'] === 403,
    "HTTP {$forgeResA['code']}, Response: " . ($forgeResA['body']['message'] ?? $forgeResA['raw_body'])
);

// -------------------------------------------------------------
// STEP 7: User A attempts Company B product (ID: 51)
// -------------------------------------------------------------
echo "\n--- TEST 7: User A attempts Company B product (ID: 51) ---\n";
$prodResA_B = runHttp('GET', "{$baseUrl}/products/51", [
    'Authorization' => "Bearer {$tokenA}",
    'X-Company-Id' => '1'
]);
testAssertion(
    "User A attempting Company B Product 51 fails with HTTP 403 Forbidden",
    $prodResA_B['code'] === 403,
    "HTTP {$prodResA_B['code']}, Response: " . ($prodResA_B['body']['message'] ?? $prodResA_B['raw_body'])
);

// -------------------------------------------------------------
// STEP 8: Cross-Check - User B Legitimate Access
// -------------------------------------------------------------
echo "\n--- TEST 8: User B Legitimate Access to Company B Resources ---\n";
$custLegitB = runHttp('GET', "{$baseUrl}/customers/21", [
    'Authorization' => "Bearer {$tokenB}",
    'X-Company-Id' => '2'
]);
testAssertion(
    "User B can access own Company B Customer 21",
    $custLegitB['code'] === 200 && ($custLegitB['body']['data']['name'] ?? '') === 'Zenith Retail Ltd',
    "HTTP {$custLegitB['code']}, Customer: " . ($custLegitB['body']['data']['name'] ?? '')
);

$prodLegitB = runHttp('GET', "{$baseUrl}/products/51", [
    'Authorization' => "Bearer {$tokenB}",
    'X-Company-Id' => '2'
]);
testAssertion(
    "User B can access own Company B Product 51",
    $prodLegitB['code'] === 200 && ($prodLegitB['body']['data']['name'] ?? '') === 'Enterprise Cloud Server Node',
    "HTTP {$prodLegitB['code']}, Product: " . ($prodLegitB['body']['data']['name'] ?? '')
);

$invLegitB = runHttp('GET', "{$baseUrl}/invoices/2", [
    'Authorization' => "Bearer {$tokenB}",
    'X-Company-Id' => '2'
]);
testAssertion(
    "User B can access own Company B Invoice 2",
    $invLegitB['code'] === 200 && ($invLegitB['body']['data']['invoice_number'] ?? '') === 'INV-2026-INN-001',
    "HTTP {$invLegitB['code']}, Invoice Number: " . ($invLegitB['body']['data']['invoice_number'] ?? '')
);

// -------------------------------------------------------------
// STEP 9: Cross-Check - User B attempting Company A Resources
// -------------------------------------------------------------
echo "\n--- TEST 9: User B attempting Company A Resources ---\n";
$custResB_A = runHttp('GET', "{$baseUrl}/customers/1", [
    'Authorization' => "Bearer {$tokenB}",
    'X-Company-Id' => '2'
]);
testAssertion(
    "User B attempting Company A Customer 1 fails with HTTP 403 Forbidden",
    $custResB_A['code'] === 403,
    "HTTP {$custResB_A['code']}, Response: " . ($custResB_A['body']['message'] ?? $custResB_A['raw_body'])
);

$prodResB_A = runHttp('GET', "{$baseUrl}/products/1", [
    'Authorization' => "Bearer {$tokenB}",
    'X-Company-Id' => '2'
]);
testAssertion(
    "User B attempting Company A Product 1 fails with HTTP 403 Forbidden",
    $prodResB_A['code'] === 403,
    "HTTP {$prodResB_A['code']}, Response: " . ($prodResB_A['body']['message'] ?? $prodResB_A['raw_body'])
);

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n====================================================================\n";
echo "   TEST SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
