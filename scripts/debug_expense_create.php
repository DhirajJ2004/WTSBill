<?php
require __DIR__ . '/../backend/vendor/autoload.php';
\App\Database\Database::init();

use App\Models\Expense;

try {
    $exp = Expense::create([
        'company_id'    => 1,
        'branch_id'     => 1,
        'expense_number'=> 'EXP-TEST-001',
        'category'      => 'Test Category',
        'payee'         => 'Test Vendor',
        'expense_date'  => date('Y-m-d'),
        'amount'        => 100.00,
        'tax_amount'    => 0.00,
        'payment_mode'  => 'CASH',
        'is_itc_eligible' => 0,
        'reference_no'  => '',
        'description'   => 'Test',
    ]);
    echo "OK - created expense id: {$exp->id}\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
