<?php

/**
 * WTSBill Database Audit & SQL Foundation Probe
 * Validates DECIMAL data types, Charsets, Engines, and Indexes across all 160 tables.
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
\App\Database\Database::init();

use Illuminate\Database\Capsule\Manager as DB;

$pdo = DB::connection()->getPdo();
$dbName = DB::connection()->getDatabaseName();

echo "=================================================================\n";
echo "       CHUNK 2: DATABASE AUDIT & SQL FOUNDATION PROBE           \n";
echo "=================================================================\n";
echo "Database: {$dbName}\n\n";

// 1. Audit Table Engines and Collations
$tables = $pdo->query("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$dbName}'")->fetchAll(PDO::FETCH_ASSOC);

$nonInnoDB = [];
$nonUtf8mb4 = [];

foreach ($tables as $t) {
    if (strtoupper($t['ENGINE'] ?? '') !== 'INNODB') {
        $nonInnoDB[] = $t['TABLE_NAME'];
    }
    if (strpos($t['TABLE_COLLATION'] ?? '', 'utf8mb4') === false) {
        $nonUtf8mb4[] = $t['TABLE_NAME'];
    }
}

echo "1. Table Storage Engine Check:\n";
echo "   Total Tables: " . count($tables) . "\n";
echo "   InnoDB Tables: " . (count($tables) - count($nonInnoDB)) . "\n";
if (!empty($nonInnoDB)) {
    echo "   [WARNING] Non-InnoDB Tables: " . implode(', ', $nonInnoDB) . "\n";
} else {
    echo "   [PASS] 100% of tables are using InnoDB engine.\n";
}

echo "\n2. Collation & Charset Check:\n";
if (!empty($nonUtf8mb4)) {
    echo "   [WARNING] Non-utf8mb4 Tables: " . implode(', ', $nonUtf8mb4) . "\n";
} else {
    echo "   [PASS] 100% of tables are using utf8mb4 collation.\n";
}

// 2. Audit Column Data Types for Financial Fields (Check for FLOAT / DOUBLE)
echo "\n3. Financial & Quantity Column Data Type Check:\n";
$columns = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = '{$dbName}' 
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
        COLUMN_NAME LIKE '%balance%' OR 
        COLUMN_NAME LIKE '%quantity%' OR 
        COLUMN_NAME LIKE '%qty%'
    )
")->fetchAll(PDO::FETCH_ASSOC);

$floatColumns = [];
$decimalColumns = 0;
$intColumns = 0;

foreach ($columns as $c) {
    $type = strtolower($c['DATA_TYPE']);
    if ($type === 'float' || $type === 'double') {
        $floatColumns[] = "{$c['TABLE_NAME']}.{$c['COLUMN_NAME']} ({$c['COLUMN_TYPE']})";
    } elseif ($type === 'decimal') {
        $decimalColumns++;
    } else {
        $intColumns++;
    }
}

echo "   Total Financial/Numeric Columns Audited: " . count($columns) . "\n";
echo "   DECIMAL Columns: {$decimalColumns}\n";
echo "   INT/Quantity Count Columns: {$intColumns}\n";

if (!empty($floatColumns)) {
    echo "   [FAIL] Detected FLOAT/DOUBLE on financial columns:\n";
    foreach ($floatColumns as $fc) {
        echo "     - {$fc}\n";
    }
} else {
    echo "   [PASS] ZERO FLOAT/DOUBLE columns detected. 100% compliant with DECIMAL rule.\n";
}

// 3. Audit Tenant Indexing (company_id on tenant tables)
echo "\n4. Multi-Tenant Indexing Check:\n";
$tenantTables = $pdo->query("
    SELECT DISTINCT TABLE_NAME 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = '{$dbName}' AND COLUMN_NAME = 'company_id'
")->fetchAll(PDO::FETCH_COLUMN);

$unindexedCompanyId = [];
foreach ($tenantTables as $tbl) {
    $idx = $pdo->query("
        SELECT INDEX_NAME 
        FROM information_schema.STATISTICS 
        WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = '{$tbl}' AND COLUMN_NAME = 'company_id'
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($idx)) {
        $unindexedCompanyId[] = $tbl;
    }
}

echo "   Total Multi-Tenant Tables (with company_id): " . count($tenantTables) . "\n";
if (!empty($unindexedCompanyId)) {
    echo "   [WARNING] Unindexed company_id on: " . implode(', ', $unindexedCompanyId) . "\n";
} else {
    echo "   [PASS] 100% of tenant tables have index on company_id.\n";
}

echo "\n=================================================================\n";
echo "       CHUNK 2: DATABASE AUDIT COMPLETED SUCCESSFULLY            \n";
echo "=================================================================\n";
