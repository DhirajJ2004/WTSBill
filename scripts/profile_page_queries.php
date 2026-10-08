<?php
require_once __DIR__ . '/../views/db_helper.php';
use Illuminate\Database\Capsule\Manager as DB;

$_SESSION['user'] = ['id' => 1, 'name' => 'Admin', 'role' => 'ADMIN'];
$_SESSION['company_id'] = 1;
$_SESSION['branch_id'] = 1;

DB::connection()->enableQueryLog();

ob_start();
include __DIR__ . '/../views/dashboard.php';
ob_end_clean();

$queries = DB::connection()->getQueryLog();
$grouped = [];
foreach ($queries as $q) {
    $grouped[$q['query']] = ($grouped[$q['query']] ?? 0) + 1;
}

echo "Total queries on Dashboard: " . count($queries) . "\n";
echo "Distinct SQL patterns:\n";
foreach ($grouped as $sql => $count) {
    echo "  [{$count}x] {$sql}\n";
}
