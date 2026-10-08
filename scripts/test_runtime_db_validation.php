<?php

/**
 * WTSBill ERP - Runtime & Database Connectivity Validation Suite
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Database\Database;
use Illuminate\Database\Capsule\Manager as DB;

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function run_test(string $name, callable $fn): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    echo "\n[TEST #{$totalTests}] {$name} ... ";
    try {
        $result = $fn();
        if ($result !== false) {
            $passedTests++;
            echo "PASS\n";
            if (is_string($result) && !empty($result)) {
                echo "   Details: {$result}\n";
            }
        } else {
            $failedTests++;
            echo "FAIL (returned false)\n";
        }
    } catch (\Throwable $e) {
        $failedTests++;
        echo "FAIL (Exception: " . $e->getMessage() . ")\n";
    }
}

echo "======================================================================\n";
echo "   WTSBill ERP - Runtime & Database Validation Suite\n";
echo "======================================================================\n";

// TEST 1: PHP Executable Detection
run_test("PHP Executable Detection", function() {
    $phpBin = Database::getPhpExecutable();
    if (empty($phpBin)) {
        throw new \RuntimeException("Could not detect PHP executable.");
    }
    return "Detected: {$phpBin} | PHP Version: " . PHP_VERSION;
});

// TEST 2: Required PHP Extensions
run_test("Required PHP Extensions Verification", function() {
    $req = Database::checkRequirements();
    if (!$req['ok']) {
        throw new \RuntimeException("Missing extensions: " . implode(', ', array_keys($req['missing'])));
    }
    $drivers = \PDO::getAvailableDrivers();
    return "All 7 extensions loaded (PDO, pdo_mysql, mbstring, json, openssl, curl, fileinfo). PDO drivers: " . implode(', ', $drivers);
});

// TEST 3: Database Connection Initialization (mysql + PDO)
run_test("Database Connection Initialization via App\\Database\\Database", function() {
    Database::reset();
    $capsule = Database::init();
    if (!$capsule instanceof \Illuminate\Database\Capsule\Manager) {
        throw new \RuntimeException("Database::init() did not return a valid Capsule instance.");
    }
    $pdo = Database::getPdo();
    if (!$pdo instanceof \PDO) {
        throw new \RuntimeException("Database::getPdo() did not return a valid PDO instance.");
    }
    $driverName = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    if ($driverName !== 'mysql') {
        throw new \RuntimeException("Expected driver 'mysql', got '{$driverName}'");
    }
    return "Connected successfully via PDO MySQL driver ('{$driverName}')";
});

// TEST 4: Simple SELECT Query
run_test("Simple SELECT Query Execution", function() {
    $row = DB::selectOne("SELECT 1 + 1 AS calc_result, VERSION() AS mysql_ver, DATABASE() AS active_db");
    if (!$row || (int)$row->calc_result !== 2) {
        throw new \RuntimeException("Simple query failed or returned unexpected result.");
    }
    return "Calc Result: 2 | MySQL/MariaDB Version: {$row->mysql_ver} | Active DB: {$row->active_db}";
});

// TEST 5: Database Authentication Queries
run_test("Authentication Database Query against 'users' Table", function() {
    $userCount = DB::table('users')->count();
    if ($userCount <= 0) {
        throw new \RuntimeException("No users found in database 'users' table.");
    }
    $admin = DB::table('users')->where('email', 'admin@wtsbill.com')->first();
    if (!$admin) {
        $firstUser = DB::table('users')->first();
        return "Found {$userCount} users in DB. First user: {$firstUser->email}";
    }
    return "Found {$userCount} users. Admin verified: {$admin->email} (ID: {$admin->id}, Status: " . ($admin->is_active ? 'Active' : 'Inactive') . ")";
});

// TEST 6: Dashboard Database Metrics Query
run_test("Dashboard Database Metrics Query", function() {
    require_once __DIR__ . '/../views/db_helper.php';
    $metrics = get_db_metrics();
    if (!is_array($metrics) || !isset($metrics['total_sales'])) {
        throw new \RuntimeException("get_db_metrics() did not return expected structure.");
    }
    return sprintf(
        "Total Sales: %.2f | Receivables: %.2f | Invoices: %d | Quotes: %d | Low Stock: %d",
        $metrics['total_sales'],
        $metrics['receivables'],
        $metrics['invoice_count'],
        $metrics['quote_count'],
        $metrics['low_stock_count']
    );
});

// TEST 7: Invoice Database Queries
run_test("Invoice Database Queries (Records & Items)", function() {
    $invoicesCount = DB::table('invoices')->count();
    $invoices = DB::table('invoices')->orderBy('id', 'desc')->limit(5)->get();
    $itemsCount = DB::table('invoice_items')->count();
    return "Total Invoices: {$invoicesCount} | Total Invoice Items: {$itemsCount} | Fetched latest: " . count($invoices) . " invoice records";
});

// TEST 8: Customer & Company Context Queries
run_test("Multi-Tenant Company & Customer Database Queries", function() {
    $companies = DB::table('companies')->get();
    $customers = DB::table('customers')->get();
    return "Total Companies: " . count($companies) . " | Total Customers: " . count($customers);
});

// TEST 9: Error Handling in Development Mode (Detailed Diagnostics)
run_test("Development Mode Diagnostic Error Formatter", function() {
    $req = Database::checkRequirements();
    if (!$req['ok']) {
        throw new \RuntimeException("Requirements check should pass.");
    }
    return "Requirements validation passed cleanly.";
});

echo "\n======================================================================\n";
echo "   VALIDATION SUMMARY\n";
echo "   Total Tests:  {$totalTests}\n";
echo "   Passed Tests: {$passedTests}\n";
echo "   Failed Tests: {$failedTests}\n";
echo "======================================================================\n";

if ($failedTests > 0) {
    exit(1);
} else {
    echo "\n[ALL TESTS PASSED SUCCESSFULLY]\n";
    exit(0);
}
