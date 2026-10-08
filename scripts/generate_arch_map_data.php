<?php

/**
 * WTSBill Exhaustive Architecture Discovery Collector
 * Generates accurate inventories for ARCHITECTURE_MIGRATION_MAP.md
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
\App\Database\Database::init();

use Illuminate\Database\Capsule\Manager as DB;

$rootDir = dirname(__DIR__);
$pdo = DB::connection()->getPdo();
$dbName = DB::connection()->getDatabaseName();

// 1. Collect all PHP files
$allPhpFiles = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir));
foreach ($iter as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $path = $f->getPathname();
        if (strpos($path, 'vendor') === false && strpos($path, '.git') === false) {
            $allPhpFiles[] = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $path);
        }
    }
}
sort($allPhpFiles);

// 2. Database Tables
$tables = $pdo->query("
    SELECT TABLE_NAME, ENGINE, TABLE_ROWS, TABLE_COLLATION 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = '{$dbName}' 
    ORDER BY TABLE_NAME ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Foreign Keys
$foreignKeys = $pdo->query("
    SELECT 
        TABLE_NAME, 
        COLUMN_NAME, 
        CONSTRAINT_NAME, 
        REFERENCED_TABLE_NAME, 
        REFERENCED_COLUMN_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = '{$dbName}' 
    AND REFERENCED_TABLE_NAME IS NOT NULL 
    ORDER BY TABLE_NAME, COLUMN_NAME
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Indexes
$indexes = $pdo->query("
    SELECT 
        TABLE_NAME, 
        INDEX_NAME, 
        NON_UNIQUE, 
        GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = '{$dbName}' 
    GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE 
    ORDER BY TABLE_NAME, INDEX_NAME
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Models
$models = [];
$modelFiles = glob($rootDir . '/backend/app/Models/*.php');
foreach ($modelFiles as $mf) {
    $name = basename($mf, '.php');
    $content = file_get_contents($mf);
    $table = preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m) ? $m[1] : strtolower($name) . 's';
    $usesTenant = (strpos($content, 'BelongsToTenant') !== false);
    $models[] = [
        'class' => $name,
        'table' => $table,
        'tenant_scoped' => $usesTenant,
        'file' => str_replace($rootDir . DIRECTORY_SEPARATOR, '', $mf)
    ];
}

// 6. Services
$services = [];
$serviceFiles = glob($rootDir . '/backend/app/Services/*.php');
foreach ($serviceFiles as $sf) {
    $name = basename($sf, '.php');
    $services[] = [
        'name' => $name,
        'file' => str_replace($rootDir . DIRECTORY_SEPARATOR, '', $sf),
        'size' => filesize($sf)
    ];
}

// 7. Controllers
$controllers = [];
$controllerFiles = glob($rootDir . '/backend/app/Http/Controllers/Api/*.php');
foreach ($controllerFiles as $cf) {
    $name = basename($cf, '.php');
    $controllers[] = [
        'name' => $name,
        'file' => str_replace($rootDir . DIRECTORY_SEPARATOR, '', $cf),
        'size' => filesize($cf)
    ];
}

// 8. Views
$views = [];
$viewIter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir . '/views'));
foreach ($viewIter as $vf) {
    if ($vf->isFile() && $vf->getExtension() === 'php') {
        $views[] = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $vf->getPathname());
    }
}
sort($views);

// 9. Composer Dependencies
$composerJson = json_decode(file_get_contents($rootDir . '/backend/composer.json'), true);

$result = [
    'summary' => [
        'total_php_files' => count($allPhpFiles),
        'total_tables' => count($tables),
        'total_foreign_keys' => count($foreignKeys),
        'total_indexes' => count($indexes),
        'total_models' => count($models),
        'total_services' => count($services),
        'total_controllers' => count($controllers),
        'total_views' => count($views)
    ],
    'composer' => $composerJson,
    'tables' => $tables,
    'foreign_keys' => $foreignKeys,
    'indexes' => $indexes,
    'models' => $models,
    'services' => $services,
    'controllers' => $controllers,
    'views' => $views,
    'php_files' => $allPhpFiles
];

file_put_contents($rootDir . '/scripts/architecture_inventory_dump.json', json_encode($result, JSON_PRETTY_PRINT));
echo "Inventory Dump created successfully at scripts/architecture_inventory_dump.json\n";
echo "Total PHP files: " . count($allPhpFiles) . "\n";
echo "Total Tables: " . count($tables) . "\n";
echo "Total Foreign Keys: " . count($foreignKeys) . "\n";
echo "Total Indexes: " . count($indexes) . "\n";
echo "Total Models: " . count($models) . "\n";
echo "Total Services: " . count($services) . "\n";
echo "Total Controllers: " . count($controllers) . "\n";
echo "Total Views: " . count($views) . "\n";
