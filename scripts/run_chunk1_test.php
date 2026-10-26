<?php

declare(strict_types=1);

// WTSBill ERP — Chunk 1 Baseline Verification Test Suite

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

$totalTests = 0;
$passed = 0;
$failed = 0;

function assertTest(string $code, string $description, bool $condition, ?string $detail = null): void
{
    global $totalTests, $passed, $failed;
    $totalTests++;
    if ($condition) {
        $passed++;
        echo "\033[32m[PASS]\033[0m {$code} | {$description}\n";
    } else {
        $failed++;
        echo "\033[31m[FAIL]\033[0m {$code} | {$description}" . ($detail ? " -> {$detail}" : '') . "\n";
    }
}

echo "==================================================================================\n";
echo "           STARTING WTSBILL CHUNK 1 BASELINE ARCHITECTURE TEST SUITE              \n";
echo "==================================================================================\n\n";

// --- SECTION 1: ENVIRONMENT & CONFIGURATION ---
echo "--- SECTION 1: ENVIRONMENT & CONFIGURATION ---\n";
Env::load(ROOT_PATH . '/.env');
assertTest('ENV-01', 'Env file loaded successfully', Env::isLoaded());
assertTest('ENV-02', 'Read APP_NAME via env() helper', env('APP_NAME') === 'WTSBill ERP', 'Value: ' . env('APP_NAME'));
assertTest('ENV-03', 'Read DB_DATABASE via env() helper', env('DB_DATABASE') === 'wtsbill_db');
assertTest('ENV-04', 'Default value fallback when key missing', env('NON_EXISTENT_KEY_XYZ', 'fallback') === 'fallback');

Config::loadFromDirectory(ROOT_PATH . '/config');
assertTest('CONF-01', 'Config repository bootstrapped', Config::isBootstrapped());
assertTest('CONF-02', 'Read app.timezone from config', config('app.timezone') === 'Asia/Kolkata');
assertTest('CONF-03', 'Read database default connection', config('database.default') === 'mysql');
assertTest('CONF-04', 'Read nested database credentials', config('database.connections.mysql.database') === 'wtsbill_db');

// --- SECTION 2: NATIVE PDO DATABASE & SCHEMA INTEGRITY ---
echo "\n--- SECTION 2: NATIVE PDO DATABASE & SCHEMA INTEGRITY ---\n";
try {
    $pdo = Database::pdo();
    assertTest('DB-01', 'Native PDO Connection established', $pdo instanceof PDO);
    
    // Check attributes
    $errMode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
    assertTest('DB-02', 'PDO error mode is ERRMODE_EXCEPTION', $errMode === PDO::ERRMODE_EXCEPTION);
    
    // Check character set
    $charsetStmt = $pdo->query("SHOW VARIABLES LIKE 'character_set_connection'");
    $charsetRow = $charsetStmt->fetch(PDO::FETCH_ASSOC);
    assertTest('DB-03', 'Connection character set is utf8mb4', $charsetRow['Value'] === 'utf8mb4');

    // Query 161 tables in wtsbill_db
    $tables = Database::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'wtsbill_db'");
    assertTest('DB-04', 'All 161 core database tables verified in wtsbill_db', count($tables) === 161, 'Found: ' . count($tables));

    // Verify prepared parameter binding (SQL Injection immunity)
    $testAdmin = Database::selectOne("SELECT id, name, email FROM users WHERE email = :email", ['email' => 'anil.d@wtsbill.in']);
    assertTest('DB-05', 'Prepared statement selectOne executes safely', $testAdmin !== null && $testAdmin['email'] === 'anil.d@wtsbill.in');

} catch (\Throwable $e) {
    assertTest('DB-ERR', 'Database connection / query failed', false, $e->getMessage());
}

// --- SECTION 3: ACID TRANSACTION & SAVEPOINT INTEGRITY ---
echo "\n--- SECTION 3: ACID TRANSACTION & SAVEPOINT INTEGRITY ---\n";
try {
    // Test transaction rollback
    $initialCount = (int) Database::selectOne("SELECT COUNT(*) as cnt FROM tags")['cnt'];

    Database::beginTransaction();
    Database::insert('tags', [
        'company_id' => 1,
        'name' => 'TEMP_TRANSACTION_ROLLBACK_TEST',
        'color' => '#FF0000',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    Database::rollBack();

    $afterRollbackCount = (int) Database::selectOne("SELECT COUNT(*) as cnt FROM tags")['cnt'];
    assertTest('TX-01', 'ACID transaction rollback restores database state', $initialCount === $afterRollbackCount);

    // Test transaction commit via transaction() wrapper
    $newId = Database::transaction(function () {
        return Database::insert('tags', [
            'company_id' => 1,
            'name' => 'TEMP_TRANSACTION_COMMIT_TEST',
            'color' => '#00FF00',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    });

    $inserted = Database::selectOne("SELECT * FROM tags WHERE id = :id", ['id' => $newId]);
    assertTest('TX-02', 'ACID transaction callback commits successfully', $inserted !== null && $inserted['name'] === 'TEMP_TRANSACTION_COMMIT_TEST');

    // Cleanup test record
    Database::delete('tags', 'id = :id', ['id' => $newId]);
    $deletedCount = (int) Database::selectOne("SELECT COUNT(*) as cnt FROM tags WHERE id = :id", ['id' => $newId])['cnt'];
    assertTest('TX-03', 'Cleanup of test record verified', $deletedCount === 0);

} catch (\Throwable $e) {
    assertTest('TX-ERR', 'Transaction verification failed', false, $e->getMessage());
}

// --- SECTION 4: HTTP REQUEST & RESPONSE ENGINE ---
echo "\n--- SECTION 4: HTTP REQUEST & RESPONSE ENGINE ---\n";
$mockServer = [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => '/api/v1/invoices?page=2',
    'HTTP_CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer sample_secret_token_123',
    'REMOTE_ADDR' => '192.168.1.50',
];
$mockQuery = ['page' => '2'];
$mockPost = [];
$mockJson = '{"customer_id": 105, "total_amount": 1499.50}';

$req = new Request($mockQuery, $mockPost, $mockServer, $mockJson);
assertTest('REQ-01', 'Request method identified correctly', $req->getMethod() === 'POST');
assertTest('REQ-02', 'Request path extracted cleanly', $req->getPath() === '/api/v1/invoices');
assertTest('REQ-03', 'Request query parameter accessible', $req->query('page') === '2');
assertTest('REQ-04', 'Request JSON payload parsed into input()', $req->input('customer_id') === 105);
assertTest('REQ-05', 'Bearer token extracted from headers', $req->getBearerToken() === 'sample_secret_token_123');
assertTest('REQ-06', 'expectsJson() detects API request', $req->expectsJson() === true);

$resSuccess = Response::success(['invoice_id' => 42], 'Invoice created', 201);
assertTest('RES-01', 'Response status code is 201', $resSuccess->getStatusCode() === 201);
$payload = json_decode($resSuccess->getContent(), true);
assertTest('RES-02', 'Response success payload structure valid', $payload['success'] === true && $payload['data']['invoice_id'] === 42);

$resError = Response::error('SQLSTATE[42S02]: Base table or view not found: 1146 Table not found', 500);
$errPayload = json_decode($resError->getContent(), true);
assertTest('RES-03', 'Database error redaction prevents SQL leak', !str_contains($errPayload['message'], 'SQLSTATE'));

// --- SECTION 5: ROUTER & DISPATCH CYCLE ---
echo "\n--- SECTION 5: ROUTER & DISPATCH CYCLE ---\n";
$router = new Router();
$router->get('/health', fn() => Response::json(['status' => 'healthy']));
$router->get('/customers/{id}', function (Request $r, $id) {
    return Response::success(['customer_id' => (int) $id]);
});
$router->post('/api/invoices', function (Request $r) {
    return Response::success(['created' => true], 'Saved', 201);
});

// Test health route
$healthReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health']);
$healthRes = $router->dispatch($healthReq);
$healthData = json_decode($healthRes->getContent(), true);
assertTest('ROUT-01', 'Exact route match dispatches correctly', $healthData['status'] === 'healthy');

// Test param route
$paramReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/customers/450']);
$paramRes = $router->dispatch($paramReq);
$paramData = json_decode($paramRes->getContent(), true);
assertTest('ROUT-02', 'Parameterized route extracts ID correctly', $paramData['data']['customer_id'] === 450);

// Test 404
$notFoundReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/non-existent-page', 'HTTP_ACCEPT' => 'application/json']);
$notFoundRes = $router->dispatch($notFoundReq);
assertTest('ROUT-03', 'Unmatched route returns 404 status', $notFoundRes->getStatusCode() === 404);

echo "\n==================================================================================\n";
echo "                       CHUNK 1 VERIFICATION REPORT SUMMARY                        \n";
echo "==================================================================================\n";
echo "Total Assertions: {$totalTests}\n";
echo "Passed:           {$passed}\n";
echo "Failed:           {$failed}\n";
$percentage = $totalTests > 0 ? round(($passed / $totalTests) * 100, 2) : 0;
echo "Pass Percentage:  {$percentage}%\n\n";

if ($failed === 0) {
    echo "\033[32mSTATUS: ALL CHUNK 1 ARCHITECTURAL CRITERIA VERIFIED & PASSED (100%)\033[0m\n";
    exit(0);
} else {
    echo "\033[31mSTATUS: CHUNK 1 HAS FAILING ASSERTIONS\033[0m\n";
    exit(1);
}
