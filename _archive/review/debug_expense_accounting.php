<?php
require __DIR__ . '/../backend/vendor/autoload.php';
\App\Database\Database::init();

use App\Models\Expense;
use App\Services\AccountingEventService;
use App\Services\DocumentNumberService;

// Load an existing expense
$expense = Expense::withoutGlobalScopes()->find(5);
if (!$expense) {
    echo "No expense to test with\n";
    exit(1);
}

echo "Expense loaded: #{$expense->expense_number}, amount: {$expense->amount}\n";

// Test DocumentNumberService
try {
    $expNum = DocumentNumberService::generateNextNumber(1, 1, '2026-27', 'EXPENSE');
    echo "DocumentNumber OK: {$expNum}\n";
} catch (\Throwable $e) {
    echo "DocumentNumber ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test AccountingEventService::recordExpenseAccounting
try {
    $je = AccountingEventService::recordExpenseAccounting($expense, 'Debug Test');
    echo "AccountingEvent OK: JV #{$je?->entry_number}\n";
} catch (\Throwable $e) {
    echo "AccountingEvent ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
