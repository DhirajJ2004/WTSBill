<?php
/**
 * Performance Measurement & Benchmarking Tool for WTSBill ERP
 */
require_once __DIR__ . '/../views/db_helper.php';
use Illuminate\Database\Capsule\Manager as DB;

// Enable query logging
DB::connection()->enableQueryLog();

// Set mock session for testing
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Anil Desai',
    'email' => 'admin@wtsbill.in',
    'role' => 'ADMIN',
    'avatar' => 'AD'
];
$_SESSION['company_id'] = 1;
$_SESSION['branch_id'] = 1;
$_SESSION['financial_year'] = '2026-27';

$assets = [
    'CSS' => [
        'app.css' => __DIR__ . '/../public/assets/css/app.css'
    ],
    'JS' => [
        'app.js' => __DIR__ . '/../public/assets/js/app.js'
    ]
];

$results = [];

echo "====================================================================\n";
echo "=== WTSBILL ERP: PERFORMANCE MEASUREMENT & BENCHMARK SUITE ===\n";
echo "====================================================================\n\n";

// 1. Static Asset Sizes
echo "--- 1. STATIC ASSET SIZES ---\n";
$totalCssSize = 0;
foreach ($assets['CSS'] as $name => $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        $totalCssSize += $size;
        $kb = round($size / 1024, 2);
        echo " CSS: $name => $size bytes ($kb KB)\n";
    }
}
$totalJsSize = 0;
foreach ($assets['JS'] as $name => $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        $totalJsSize += $size;
        $kb = round($size / 1024, 2);
        echo " JS:  $name => $size bytes ($kb KB)\n";
    }
}
echo " Total CSS Size: " . round($totalCssSize / 1024, 2) . " KB\n";
echo " Total JS Size:  " . round($totalJsSize / 1024, 2) . " KB\n\n";

// 2. Benchmark Key Pages
$testPages = [
    'Dashboard' => __DIR__ . '/../views/dashboard.php',
    'Invoices' => __DIR__ . '/../views/sales/invoices.php',
    'Inventory' => __DIR__ . '/../views/inventory/index.php',
    'Purchases' => __DIR__ . '/../views/purchases/purchases.php',
    'Parties' => __DIR__ . '/../views/parties/index.php',
    'Accounting' => __DIR__ . '/../views/accounting/index.php'
];

echo "--- 2. SERVER RENDERING & DATABASE QUERY BENCHMARK ---\n";

foreach ($testPages as $pageName => $filePath) {
    if (!file_exists($filePath)) continue;

    DB::connection()->flushQueryLog();
    
    // Warm-up run
    ob_start();
    include $filePath;
    ob_end_clean();

    // Measurement run
    DB::connection()->flushQueryLog();
    $startTime = microtime(true);

    ob_start();
    include $filePath;
    $output = ob_get_clean();

    $endTime = microtime(true);
    $execTimeMs = round(($endTime - $startTime) * 1000, 2);
    
    $queryLog = DB::connection()->getQueryLog();
    $queryCount = count($queryLog);
    $totalQueryTime = 0;
    foreach ($queryLog as $q) {
        $totalQueryTime += ($q['time'] ?? 0);
    }
    $totalQueryTimeMs = round($totalQueryTime, 2);
    $htmlSize = strlen($output);
    $htmlKb = round($htmlSize / 1024, 2);

    $results[$pageName] = [
        'exec_time_ms' => $execTimeMs,
        'query_count' => $queryCount,
        'query_time_ms' => $totalQueryTimeMs,
        'html_size_kb' => $htmlKb,
        'html_size_bytes' => $htmlSize
    ];

    echo " [$pageName]\n";
    echo "   Execution Time (TTFB): {$execTimeMs} ms\n";
    echo "   DB Query Count:        {$queryCount} queries\n";
    echo "   DB Query Time:         {$totalQueryTimeMs} ms\n";
    echo "   HTML Output Size:      {$htmlKb} KB ({$htmlSize} bytes)\n\n";
}

echo "====================================================================\n";
echo "=== SUMMARY BENCHMARK METRICS ===\n";
$avgTtfb = round(array_sum(array_column($results, 'exec_time_ms')) / count($results), 2);
$totalQueries = array_sum(array_column($results, 'query_count'));
$avgHtml = round(array_sum(array_column($results, 'html_size_kb')) / count($results), 2);

echo " Average Server Render (TTFB): {$avgTtfb} ms\n";
echo " Total DB Queries across views: {$totalQueries}\n";
echo " Average HTML Output Size:     {$avgHtml} KB\n";
echo " Total Static CSS:             " . round($totalCssSize / 1024, 2) . " KB\n";
echo " Total Static JS:              " . round($totalJsSize / 1024, 2) . " KB\n";
echo "====================================================================\n";
