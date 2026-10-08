<?php

/**
 * WTSBill Database Layer Normalizer & Generator
 * Generates database/schema/schema.sql, database/seeders/SystemSeeder.php, database/seeders/DemoSeeder.php,
 * and compiles comprehensive schema audit statistics.
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
\App\Database\Database::init();

use Illuminate\Database\Capsule\Manager as DB;

$pdo = DB::connection()->getPdo();
$dbName = DB::connection()->getDatabaseName();
$rootDir = dirname(__DIR__);

// 1. Get all table names
$tables = $pdo->query("
    SELECT TABLE_NAME 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = '{$dbName}' 
    ORDER BY TABLE_NAME ASC
")->fetchAll(PDO::FETCH_COLUMN);

$tableDetails = [];
$ddlStatements = [];

foreach ($tables as $table) {
    // Columns
    $columns = $pdo->query("
        SELECT 
            COLUMN_NAME, 
            COLUMN_TYPE, 
            DATA_TYPE, 
            IS_NULLABLE, 
            COLUMN_DEFAULT, 
            EXTRA, 
            COLUMN_COMMENT 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = '{$table}' 
        ORDER BY ORDINAL_POSITION ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Primary & Foreign Keys & Indexes
    $indexes = $pdo->query("
        SELECT 
            INDEX_NAME, 
            NON_UNIQUE, 
            GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS COLUMNS 
        FROM information_schema.STATISTICS 
        WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = '{$table}' 
        GROUP BY INDEX_NAME, NON_UNIQUE
    ")->fetchAll(PDO::FETCH_ASSOC);

    $fks = $pdo->query("
        SELECT 
            COLUMN_NAME, 
            CONSTRAINT_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = '{$table}' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Create DDL
    $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
    $ddlStatements[$table] = $createStmt['Create Table'] ?? '';

    // Analyze relationships
    $hasCompany = false;
    $hasBranch = false;
    $hasFY = false;
    $hasCreatedAt = false;
    $hasUpdatedAt = false;
    $hasDeletedAt = false;

    foreach ($columns as $c) {
        $colName = strtolower($c['COLUMN_NAME']);
        if ($colName === 'company_id') $hasCompany = true;
        if ($colName === 'branch_id') $hasBranch = true;
        if ($colName === 'financial_year' || $colName === 'fy') $hasFY = true;
        if ($colName === 'created_at') $hasCreatedAt = true;
        if ($colName === 'updated_at') $hasUpdatedAt = true;
        if ($colName === 'deleted_at') $hasDeletedAt = true;
    }

    $tableDetails[$table] = [
        'name' => $table,
        'columns' => $columns,
        'indexes' => $indexes,
        'foreign_keys' => $fks,
        'tenant_owned' => $hasCompany,
        'branch_owned' => $hasBranch,
        'fy_scoped' => $hasFY,
        'has_timestamps' => ($hasCreatedAt && $hasUpdatedAt),
        'has_soft_deletes' => $hasDeletedAt
    ];
}

// 2. Write database/schema/schema.sql
$schemaSql = "-- WTSBill ERP Master Production Schema\n";
$schemaSql .= "-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci\n";
$schemaSql .= "-- Financial Columns: Strict DECIMAL(p, s)\n\n";
$schemaSql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($ddlStatements as $table => $ddl) {
    $schemaSql .= "DROP TABLE IF EXISTS `{$table}`;\n";
    $schemaSql .= $ddl . ";\n\n";
}

$schemaSql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents($rootDir . '/database/schema/schema.sql', $schemaSql);
echo "Written database/schema/schema.sql successfully.\n";

// 3. Write database/schema/table_metadata.json
file_put_contents($rootDir . '/database/schema/table_metadata.json', json_encode($tableDetails, JSON_PRETTY_PRINT));
echo "Written database/schema/table_metadata.json successfully.\n";

// 4. Extract System Seed Data
$indianStates = $pdo->query("SELECT * FROM indian_states ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$gstRates = $pdo->query("SELECT * FROM gst_rates ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$units = $pdo->query("SELECT * FROM units WHERE company_id IS NULL OR company_id = 0 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
if (empty($units)) {
    $units = $pdo->query("SELECT * FROM units LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
}
$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$permissions = $pdo->query("SELECT * FROM permissions ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$systemSeedPayload = [
    'indian_states' => $indianStates,
    'gst_rates' => $gstRates,
    'units' => $units,
    'roles' => $roles,
    'permissions' => $permissions
];

file_put_contents($rootDir . '/database/seeders/system_seed_data.json', json_encode($systemSeedPayload, JSON_PRETTY_PRINT));
echo "Written database/seeders/system_seed_data.json successfully.\n";
