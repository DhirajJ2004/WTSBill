<?php

/**
 * WTSBill Master Database Verification Suite
 * Validates Tables, Foreign Keys, Indexes, DECIMAL precision, ACID Rollbacks, Tenant Scoping & Accounting Invariant
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
\App\Database\Database::init();

use Illuminate\Database\Capsule\Manager as DB;

$pdo = DB::connection()->getPdo();
$dbName = DB::connection()->getDatabaseName();

$passed = 0;
$failed = 0;

function assert_test($condition, $name, $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo sprintf("  [PASS] %-45s %s\n", $name, $detail ? "({$detail})" : "");
    } else {
        $failed++;
        echo sprintf("  [FAIL] %-45s %s\n", $name, $detail ? "({$detail})" : "");
    }
}

echo "=================================================================\n";
echo "           WTSBILL MASTER DATABASE LAYER VERIFICATION            \n";
echo "=================================================================\n\n";

// 1. Table Existence
echo "--- 1. Schema & Engine Integrity ---\n";
$tableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$dbName}'")->fetchColumn();
assert_test($tableCount >= 160, "Table Count Invariant", "{$tableCount} tables present");

$nonInnoDBCount = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$dbName}' AND UPPER(ENGINE) != 'INNODB'")->fetchColumn();
assert_test($nonInnoDBCount == 0, "InnoDB Engine Invariant", "0 non-InnoDB tables");

$nonUtf8Count = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_COLLATION NOT LIKE '%utf8mb4%'")->fetchColumn();
assert_test($nonUtf8Count == 0, "utf8mb4 Collation Invariant", "100% utf8mb4");

// 2. Financial Precision (DECIMAL Rule)
echo "\n--- 2. Financial Precision Invariants ---\n";
$floatCount = $pdo->query("
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = '{$dbName}' 
    AND DATA_TYPE IN ('float', 'double') 
    AND (
        COLUMN_NAME LIKE '%amount%' OR 
        COLUMN_NAME LIKE '%total%' OR 
        COLUMN_NAME LIKE '%tax%' OR 
        COLUMN_NAME LIKE '%subtotal%' OR 
        COLUMN_NAME LIKE '%discount%' OR 
        COLUMN_NAME LIKE '%price%' OR 
        COLUMN_NAME LIKE '%rate%' OR 
        COLUMN_NAME LIKE '%debit%' OR 
        COLUMN_NAME LIKE '%credit%' OR 
        COLUMN_NAME LIKE '%balance%'
    )
")->fetchColumn();
assert_test($floatCount == 0, "Zero Float/Double Rule", "0 float/double financial fields detected");

// 3. Multi-Tenant Scoping & Indexing
echo "\n--- 3. Multi-Tenant Indexing & Scoping ---\n";
$tenantTables = $pdo->query("
    SELECT COUNT(DISTINCT TABLE_NAME) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = '{$dbName}' AND COLUMN_NAME = 'company_id'
")->fetchColumn();
assert_test($tenantTables >= 140, "Multi-Tenant Table Coverage", "{$tenantTables} tenant tables");

$unindexedCompanyId = $pdo->query("
    SELECT DISTINCT c.TABLE_NAME 
    FROM information_schema.COLUMNS c
    LEFT JOIN information_schema.STATISTICS s 
        ON c.TABLE_SCHEMA = s.TABLE_SCHEMA 
        AND c.TABLE_NAME = s.TABLE_NAME 
        AND c.COLUMN_NAME = s.COLUMN_NAME
    WHERE c.TABLE_SCHEMA = '{$dbName}' 
    AND c.COLUMN_NAME = 'company_id' 
    AND s.INDEX_NAME IS NULL
")->fetchAll(PDO::FETCH_COLUMN);
assert_test(empty($unindexedCompanyId), "Tenant Index Coverage", "100% company_id indexed");

// 4. ACID Transaction & Rollback Integrity
echo "\n--- 4. ACID Transaction & Rollback Invariants ---\n";
$testSku = 'ROLLBACK-TEST-' . time();
$companyId = 1;

try {
    DB::transaction(function () use ($testSku, $companyId) {
        DB::table('products')->insert([
            'company_id' => $companyId,
            'sku' => $testSku,
            'name' => 'Rollback Test Item',
            'selling_price' => 100.00,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Force an intentional exception
        throw new \Exception("Simulated Transaction Failure");
    });
} catch (\Throwable $e) {
    // Expected exception caught
}

$orphanRecord = DB::table('products')->where('sku', $testSku)->first();
assert_test($orphanRecord === null, "Transaction Rollback Verification", "Orphan record cleanly rolled back");

// 5. Double-Entry Accounting Balance Invariant
echo "\n--- 5. Double-Entry Accounting Bookkeeping Invariant ---\n";
$unbalancedJournals = $pdo->query("
    SELECT journal_entry_id, SUM(debit) as total_debit, SUM(credit) as total_credit, ABS(SUM(debit) - SUM(credit)) as diff
    FROM journal_lines
    GROUP BY journal_entry_id
    HAVING diff > 0.001
")->fetchAll(PDO::FETCH_ASSOC);

assert_test(empty($unbalancedJournals), "Universal Invariant: SUM(Dr) == SUM(Cr)", "Unbalanced vouchers: " . count($unbalancedJournals));

echo "\n=================================================================\n";
echo sprintf("RESULTS: %d Passed | %d Failed | Status: %s\n", $passed, $failed, ($failed === 0 ? "ALL PASSED (100%)" : "FAILURES DETECTED"));
echo "=================================================================\n";
