<?php
require_once __DIR__ . '/../backend/vendor/autoload.php';
\App\Database\Database::init();
use Illuminate\Database\Capsule\Manager as DB;

function printTableCols($table) {
    echo "=== TABLE: $table ===\n";
    $cols = DB::select("DESCRIBE $table");
    foreach ($cols as $c) {
        echo "  {$c->Field} ({$c->Type})\n";
    }
}

printTableCols('customers');
printTableCols('suppliers');
$bas = DB::table('bank_accounts')->get();
echo "Bank accounts count: " . count($bas) . "\n";
foreach ($bas as $b) {
    echo "ID: {$b->id}, Company: {$b->company_id}, Name: {$b->bank_name}, Active: {$b->is_active}, Balance: {$b->current_balance}\n";
}
