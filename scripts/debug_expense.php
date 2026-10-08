<?php
// Quick debug: test expense creation and customer creation APIs
$baseUrl = 'http://127.0.0.1:8000/api/v1';

function runHttp(string $method, string $url, array $headers = [], $body = null): array {
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
    $bodyStr = substr($raw, $headerSize);
    return ['code' => $httpCode, 'body' => json_decode($bodyStr, true), 'raw' => $bodyStr];
}

// Login
$auth = runHttp('POST', "{$baseUrl}/auth/login", [], ['email' => 'anil.d@wtsbill.in', 'password' => 'password123']);
$token = $auth['body']['access_token'] ?? '';
echo "Login: HTTP {$auth['code']}, Token: " . (empty($token) ? 'NONE' : substr($token,0,20).'...') . "\n";

$headers = [
    'Authorization' => "Bearer {$token}",
    'X-Company-Id' => '1',
    'Accept' => 'application/json'
];

// Test customer creation
echo "\n=== CUSTOMER CREATION ===\n";
$uniqueSuffix = substr(uniqid(), -4);
$custGstin = "27AAACA" . rand(1000, 9999) . "A1Z" . rand(1, 9);
$res = runHttp('POST', "{$baseUrl}/customers", $headers, [
    'name'         => "Debug Customer {$uniqueSuffix}",
    'phone'        => '9820199999',
    'email'        => "dbg.{$uniqueSuffix}@test.com",
    'gstin'        => $custGstin,
    'pan'          => substr($custGstin, 2, 10),
    'address_line1'=> 'Plot 42, MIDC',
    'city'         => 'Pune',
    'state'        => 'Maharashtra',
    'state_code'   => '27'
]);
echo "HTTP: {$res['code']}\n";
echo "Response: " . json_encode($res['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Test expense creation
echo "\n=== EXPENSE CREATION ===\n";
$expRes = runHttp('POST', "{$baseUrl}/expenses", $headers, [
    'category'    => 'Cloud Infrastructure',
    'payee'       => 'AWS Cloud Services India',
    'expense_date'=> date('Y-m-d'),
    'amount'      => 11800.00,
    'tax_amount'  => 1800.00,
    'payment_mode'=> 'BANK_TRANSFER',
    'gstin'       => '27AAACA1234A1Z5',
    'is_itc_eligible' => true,
    'reference_no'=> 'AWS-INV-9901',
    'description' => 'EC2, RDS and S3 monthly hosting'
]);
echo "HTTP: {$expRes['code']}\n";
echo "Response: " . json_encode($expRes['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
