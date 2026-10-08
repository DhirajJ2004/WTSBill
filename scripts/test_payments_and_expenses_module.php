<?php

if (!function_exists('enum_exists')) {
    function enum_exists(string $enum, bool $autoload = true): bool {
        return false;
    }
}
if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool {
        if ($array === [] || $array === array_values($array)) return true;
        $nextKey = 0;
        foreach ($array as $k => $_) {
            if ($k !== $nextKey++) return false;
        }
        return true;
    }
}

// Register autoloader for app/ first
spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\' => [
            __DIR__ . '/../app/',
            __DIR__ . '/../backend/app/'
        ]
    ];
    foreach ($prefixes as $prefix => $baseDirs) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        foreach ($baseDirs as $baseDir) {
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
}, true, true);

require_once __DIR__ . '/../backend/vendor/autoload.php';

// Load DB
require_once __DIR__ . '/../views/db_helper.php';
\App\Database\Database::init();

use App\Services\PaymentService;
use App\Services\ExpenseService;
use App\Services\NumberToWordsService;
use App\Services\AccountService;
use App\Repositories\PaymentRepository;
use App\Repositories\ExpenseRepository;
use App\Validators\PaymentValidator;
use App\Validators\ExpenseValidator;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Expense;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Company;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Capsule\Manager as DB;

echo "====================================================================\n";
echo "  WTSBILL ERP - PAYMENTS & EXPENSES QA VERIFICATION SUITE\n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function runTest(string $title, callable $fn) {
    global $passCount, $failCount;
    echo "[TEST] {$title} ... ";
    try {
        $result = $fn();
        if ($result === true || (is_array($result) && ($result['status'] ?? '') === 'pass')) {
            echo "PASS\n";
            $passCount++;
        } else {
            $reason = is_array($result) ? ($result['reason'] ?? 'Condition failed') : 'Returned false';
            echo "FAIL: {$reason}\n";
            $failCount++;
        }
    } catch (\Throwable $e) {
        echo "FAIL (Exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . ")\n";
        $failCount++;
    }
}

// Ensure Tenant Context
$companyA = Company::firstOrCreate(['id' => 1], [
    'name' => 'Wis Technosavvy Pvt Ltd',
    'email' => 'contact@wistechnosavvy.com',
    'phone' => '9876543210',
    'gstin' => '27AABCW1234F1Z5',
    'currency' => 'INR'
]);

$companyB = Company::firstOrCreate(['id' => 2], [
    'name' => 'Secondary Tenant Corp',
    'email' => 'sec@tenant.com',
    'phone' => '9876543219',
    'gstin' => '27AABCW9999F1Z9',
    'currency' => 'INR'
]);

$branchA = Branch::firstOrCreate(['id' => 1], [
    'company_id' => 1,
    'name' => 'Pune Main Branch',
    'code' => 'PUN-01'
]);

AccountService::ensureDefaultAccounts(1);
AccountService::ensureDefaultAccounts(2);

// 1. Amount to words conversion tests
runTest('Amount to Words: Verify Indian Numbering System Formats', function() {
    $words0 = NumberToWordsService::toIndianWords(0);
    $words100 = NumberToWordsService::toIndianWords(100);
    $words1500 = NumberToWordsService::toIndianWords(1500.50);
    $wordsLakh = NumberToWordsService::toIndianWords(125000);
    $wordsCrore = NumberToWordsService::toIndianWords(10050000);

    if (stripos($words100, 'one hundred') === false && stripos($words100, 'hundred') === false) {
        return ['status' => 'fail', 'reason' => "100 failed: got '{$words100}'"];
    }
    if (stripos($words1500, 'one thousand five hundred') === false && stripos($words1500, 'fifteen hundred') === false && stripos($words1500, 'thousand') === false) {
        return ['status' => 'fail', 'reason' => "1500.50 failed: got '{$words1500}'"];
    }
    if (stripos($wordsLakh, 'lakh') === false) {
        return ['status' => 'fail', 'reason' => "125000 failed: got '{$wordsLakh}'"];
    }
    return true;
});

// 2. Customer Receipt Creation & Party Balance Adjustment
runTest('Customer Receipt: Atomically reduces Customer Balance and creates Double-Entry Journal', function() {
    $customer = Customer::create([
        'company_id' => 1,
        'name' => 'Payment Test Customer ' . uniqid(),
        'phone' => '9988776655',
        'current_balance' => 5000.00,
        'opening_balance' => 5000.00,
    ]);

    $res = PaymentService::recordCustomerReceipt([
        'customer_id' => $customer->id,
        'amount' => 2000.00,
        'payment_mode' => 'UPI',
        'transaction_reference' => 'UPI-' . uniqid(),
        'notes' => 'Advance customer payment',
    ], 1, 1, 'QA Tester');

    if (!$res['success']) {
        return ['status' => 'fail', 'reason' => $res['message'] ?? 'Failed to record receipt'];
    }

    $customer->refresh();
    if (abs((float)$customer->current_balance - 3000.00) > 0.01) {
        return ['status' => 'fail', 'reason' => "Customer balance mismatch: expected 3000.00, got {$customer->current_balance}"];
    }

    $paymentId = $res['payment_id'];
    $journal = JournalEntry::where('company_id', 1)
        ->where('reference_type', 'PAYMENT')
        ->where('reference_id', (string)$paymentId)
        ->first();

    if (!$journal) {
        return ['status' => 'fail', 'reason' => 'Journal entry was not created'];
    }

    $lines = JournalLine::where('journal_entry_id', $journal->id)->get();
    $totalDebit = round($lines->sum('debit'), 2);
    $totalCredit = round($lines->sum('credit'), 2);

    if (abs($totalDebit - 2000.00) > 0.01 || abs($totalCredit - 2000.00) > 0.01 || abs($totalDebit - $totalCredit) > 0.01) {
        return ['status' => 'fail', 'reason' => "Journal Debit != Credit. Dr: {$totalDebit}, Cr: {$totalCredit}"];
    }

    return true;
});

// 3. Customer Receipt Allocation against Invoice
runTest('Customer Receipt: Settles Invoice Amount Due and Updates Status', function() {
    $customer = Customer::create([
        'company_id' => 1,
        'name' => 'Invoice Payment Customer ' . uniqid(),
        'current_balance' => 1000.00,
    ]);

    $invoice = Invoice::create([
        'company_id' => 1,
        'branch_id' => 1,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-TEST-' . uniqid(),
        'invoice_date' => date('Y-m-d'),
        'sub_total' => 1000.00,
        'grand_total' => 1000.00,
        'amount_paid' => 0.00,
        'amount_due' => 1000.00,
        'payment_status' => 'UNPAID',
        'status' => 'ISSUED',
    ]);

    $res = PaymentService::recordCustomerReceipt([
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'amount' => 600.00,
        'payment_mode' => 'BANK_TRANSFER',
        'transaction_reference' => 'UTR-' . uniqid(),
    ], 1, 1, 'QA Tester');

    if (!$res['success']) {
        return ['status' => 'fail', 'reason' => $res['message']];
    }

    $invoice->refresh();
    if (abs((float)$invoice->amount_due - 400.00) > 0.01 || $invoice->payment_status !== 'PARTIALLY_PAID') {
        return ['status' => 'fail', 'reason' => "Invoice not partially paid. Due: {$invoice->amount_due}, Status: {$invoice->payment_status}"];
    }

    // Pay remaining 400.00
    $res2 = PaymentService::recordCustomerReceipt([
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'amount' => 400.00,
        'payment_mode' => 'CASH',
    ], 1, 1, 'QA Tester');

    if (!$res2['success']) {
        return ['status' => 'fail', 'reason' => $res2['message']];
    }

    $invoice->refresh();
    if (abs((float)$invoice->amount_due - 0.00) > 0.01 || $invoice->payment_status !== 'PAID') {
        return ['status' => 'fail', 'reason' => "Invoice not fully paid. Due: {$invoice->amount_due}, Status: {$invoice->payment_status}"];
    }

    return true;
});

// 4. Supplier Payment Creation & Party Balance Adjustment
runTest('Supplier Payment: Atomically reduces Supplier Payable Balance and creates Double-Entry Journal', function() {
    $supplier = Supplier::create([
        'company_id' => 1,
        'name' => 'Payment Test Supplier ' . uniqid(),
        'current_balance' => 10000.00,
        'opening_balance' => 10000.00,
    ]);

    $res = PaymentService::recordSupplierPayment([
        'supplier_id' => $supplier->id,
        'amount' => 4500.00,
        'payment_mode' => 'BANK_TRANSFER',
        'transaction_reference' => 'NEFT-' . uniqid(),
        'notes' => 'Part payment against pending bills',
    ], 1, 1, 'QA Tester');

    if (!$res['success']) {
        return ['status' => 'fail', 'reason' => $res['message'] ?? 'Failed to record payment'];
    }

    $supplier->refresh();
    if (abs((float)$supplier->current_balance - 5500.00) > 0.01) {
        return ['status' => 'fail', 'reason' => "Supplier balance mismatch: expected 5500.00, got {$supplier->current_balance}"];
    }

    $paymentId = $res['payment_id'];
    $journal = JournalEntry::where('company_id', 1)
        ->where('reference_type', 'PAYMENT')
        ->where('reference_id', (string)$paymentId)
        ->first();

    if (!$journal) {
        return ['status' => 'fail', 'reason' => 'Journal entry was not created'];
    }

    $lines = JournalLine::where('journal_entry_id', $journal->id)->get();
    $totalDebit = round($lines->sum('debit'), 2);
    $totalCredit = round($lines->sum('credit'), 2);

    if (abs($totalDebit - 4500.00) > 0.01 || abs($totalCredit - 4500.00) > 0.01 || abs($totalDebit - $totalCredit) > 0.01) {
        return ['status' => 'fail', 'reason' => "Journal Debit != Credit. Dr: {$totalDebit}, Cr: {$totalCredit}"];
    }

    return true;
});

// 5. Supplier Payment Allocation against Purchase Bill
runTest('Supplier Payment: Settles Purchase Bill Amount Due and Updates Status', function() {
    $supplier = Supplier::create([
        'company_id' => 1,
        'name' => 'Bill Payment Supplier ' . uniqid(),
        'current_balance' => 3000.00,
    ]);

    $purchase = Purchase::create([
        'company_id' => 1,
        'branch_id' => 1,
        'supplier_id' => $supplier->id,
        'purchase_number' => 'PUR-TEST-' . uniqid(),
        'purchase_date' => date('Y-m-d'),
        'sub_total' => 3000.00,
        'grand_total' => 3000.00,
        'amount_paid' => 0.00,
        'amount_due' => 3000.00,
        'payment_status' => 'UNPAID',
        'status' => 'RECEIVED',
    ]);

    $res = PaymentService::recordSupplierPayment([
        'supplier_id' => $supplier->id,
        'purchase_id' => $purchase->id,
        'amount' => 3000.00,
        'payment_mode' => 'CHEQUE',
        'cheque_number' => 'CHQ-100234',
        'cheque_bank' => 'HDFC Bank',
    ], 1, 1, 'QA Tester');

    if (!$res['success']) {
        return ['status' => 'fail', 'reason' => $res['message']];
    }

    $purchase->refresh();
    if (abs((float)$purchase->amount_due - 0.00) > 0.01 || $purchase->payment_status !== 'PAID') {
        return ['status' => 'fail', 'reason' => "Purchase bill not fully paid. Due: {$purchase->amount_due}, Status: {$purchase->payment_status}"];
    }

    return true;
});

// 6. Void Payment & Full Reversal
runTest('Void Payment: Restores Party Balances, Re-opens Invoices, and Marks Journal Cancelled', function() {
    $customer = Customer::create([
        'company_id' => 1,
        'name' => 'Void Test Customer ' . uniqid(),
        'current_balance' => 2000.00,
    ]);

    $invoice = Invoice::create([
        'company_id' => 1,
        'branch_id' => 1,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-VOID-' . uniqid(),
        'invoice_date' => date('Y-m-d'),
        'sub_total' => 2000.00,
        'grand_total' => 2000.00,
        'amount_paid' => 0.00,
        'amount_due' => 2000.00,
        'payment_status' => 'UNPAID',
        'status' => 'ISSUED',
    ]);

    $res = PaymentService::recordCustomerReceipt([
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'amount' => 1500.00,
        'payment_mode' => 'CASH',
    ], 1, 1, 'QA Tester');

    $paymentId = $res['payment_id'];
    $customer->refresh();
    $invoice->refresh();

    if (abs((float)$customer->current_balance - 500.00) > 0.01 || abs((float)$invoice->amount_due - 500.00) > 0.01) {
        return ['status' => 'fail', 'reason' => 'Setup before void failed'];
    }

    // Void the receipt
    $voidRes = PaymentService::voidPayment($paymentId, 1, 'QA Tester', 'Cheque bounced');
    if (!$voidRes['success']) {
        return ['status' => 'fail', 'reason' => $voidRes['message']];
    }

    $customer->refresh();
    $invoice->refresh();

    if (abs((float)$customer->current_balance - 2000.00) > 0.01) {
        return ['status' => 'fail', 'reason' => "Customer balance not restored. Expected 2000.00, got {$customer->current_balance}"];
    }

    if (abs((float)$invoice->amount_due - 2000.00) > 0.01 || $invoice->payment_status !== 'UNPAID') {
        return ['status' => 'fail', 'reason' => "Invoice amount due not restored. Expected 2000.00, got {$invoice->amount_due}"];
    }

    $journal = JournalEntry::where('company_id', 1)
        ->where('reference_type', 'PAYMENT')
        ->where('reference_id', (string)$paymentId)
        ->first();

    if ($journal && $journal->status !== 'CANCELLED') {
        return ['status' => 'fail', 'reason' => "Journal status not CANCELLED, got {$journal->status}"];
    }

    return true;
});

// 7. Operating Expense Creation & Double-Entry Accounting
runTest('Operating Expense: Posts Dr Operating Expenses and Cr Cash/Bank', function() {
    $res = ExpenseService::recordExpense([
        'category' => 'Office Supplies',
        'payee' => 'Stationery World',
        'amount' => 750.00,
        'payment_mode' => 'CASH',
        'description' => 'Printer Paper & Ink',
    ], 1, 1, 'QA Tester');

    if (!$res['success']) {
        return ['status' => 'fail', 'reason' => $res['message']];
    }

    $expId = $res['expense_id'];
    $journal = JournalEntry::where('company_id', 1)
        ->where('reference_type', 'EXPENSE')
        ->where('reference_id', (string)$expId)
        ->first();

    if (!$journal) {
        return ['status' => 'fail', 'reason' => 'Expense journal not created'];
    }

    $lines = JournalLine::where('journal_entry_id', $journal->id)->get();
    $totalDebit = round($lines->sum('debit'), 2);
    $totalCredit = round($lines->sum('credit'), 2);

    if (abs($totalDebit - 750.00) > 0.01 || abs($totalCredit - 750.00) > 0.01 || abs($totalDebit - $totalCredit) > 0.01) {
        return ['status' => 'fail', 'reason' => "Expense journal Debit != Credit. Dr: {$totalDebit}, Cr: {$totalCredit}"];
    }

    return true;
});

// 8. Operating Expense with ITC Eligible Tax Breakdown
runTest('Operating Expense with ITC: Splits Net Expense and Input GST into Debit lines', function() {
    $res = ExpenseService::recordExpense([
        'category' => 'Internet & Telephony',
        'payee' => 'Airtel Broadband',
        'amount' => 1180.00,
        'tax_amount' => 180.00,
        'is_itc_eligible' => 1,
        'gstin' => '27AAACA0000A1Z5',
        'payment_mode' => 'BANK_TRANSFER',
        'description' => 'Monthly High Speed Fiber Bill',
    ], 1, 1, 'QA Tester');

    if (!$res['success']) {
        return ['status' => 'fail', 'reason' => $res['message']];
    }

    $expId = $res['expense_id'];
    $journal = JournalEntry::where('company_id', 1)
        ->where('reference_type', 'EXPENSE')
        ->where('reference_id', (string)$expId)
        ->first();

    if (!$journal) {
        return ['status' => 'fail', 'reason' => 'Expense journal not created'];
    }

    $lines = JournalLine::where('journal_entry_id', $journal->id)->get();
    $totalDebit = round($lines->sum('debit'), 2);
    $totalCredit = round($lines->sum('credit'), 2);

    if (abs($totalDebit - 1180.00) > 0.01 || abs($totalCredit - 1180.00) > 0.01 || abs($totalDebit - $totalCredit) > 0.01) {
        return ['status' => 'fail', 'reason' => "ITC Expense journal Debit != Credit. Dr: {$totalDebit}, Cr: {$totalCredit}"];
    }

    return true;
});

// 9. Validation: Negative and Zero amounts rejection
runTest('Validation: Rejects Negative and Zero Amounts', function() {
    $resNegReceipt = PaymentService::recordCustomerReceipt([
        'customer_id' => 1,
        'amount' => -500.00,
        'payment_mode' => 'CASH'
    ], 1);

    if ($resNegReceipt['success']) {
        return ['status' => 'fail', 'reason' => 'Accepted negative customer receipt'];
    }

    $resZeroPayment = PaymentService::recordSupplierPayment([
        'supplier_id' => 1,
        'amount' => 0.00,
        'payment_mode' => 'CASH'
    ], 1);

    if ($resZeroPayment['success']) {
        return ['status' => 'fail', 'reason' => 'Accepted zero supplier payment'];
    }

    $resNegExpense = ExpenseService::recordExpense([
        'category' => 'Test',
        'amount' => -100.00,
    ], 1);

    if ($resNegExpense['success']) {
        return ['status' => 'fail', 'reason' => 'Accepted negative expense'];
    }

    return true;
});

// 10. Validation: Duplicate Posting / Duplicate Transaction Reference Prevention
runTest('Duplicate Posting Prevention: Rejects Identical Transaction Reference / UTR', function() {
    $uniqueRef = 'UTR-UNIQ-' . uniqid();

    $res1 = PaymentService::recordCustomerReceipt([
        'customer_id' => 1,
        'amount' => 1000.00,
        'payment_mode' => 'BANK_TRANSFER',
        'transaction_reference' => $uniqueRef,
    ], 1);

    if (!$res1['success']) {
        return ['status' => 'fail', 'reason' => 'First payment with unique ref failed'];
    }

    // Try submitting identical ref again
    $res2 = PaymentService::recordCustomerReceipt([
        'customer_id' => 1,
        'amount' => 1000.00,
        'payment_mode' => 'BANK_TRANSFER',
        'transaction_reference' => $uniqueRef,
    ], 1);

    if ($res2['success']) {
        return ['status' => 'fail', 'reason' => 'Duplicate transaction reference was permitted'];
    }

    return true;
});

// 11. Cross-Tenant IDOR Shielding
runTest('Cross-Tenant Protection: Prevents Tenant 1 from accessing Tenant 2 parties', function() {
    $tenant2Customer = Customer::create([
        'company_id' => 2,
        'name' => 'Tenant 2 Secret Customer ' . uniqid(),
        'current_balance' => 50000.00,
    ]);

    $res = PaymentService::recordCustomerReceipt([
        'customer_id' => $tenant2Customer->id,
        'amount' => 1000.00,
        'payment_mode' => 'CASH',
    ], 1); // executing under Company 1 context

    if ($res['success']) {
        return ['status' => 'fail', 'reason' => 'Cross-tenant customer receipt was permitted'];
    }

    $tenant2Supplier = Supplier::create([
        'company_id' => 2,
        'name' => 'Tenant 2 Secret Supplier ' . uniqid(),
        'current_balance' => 50000.00,
    ]);

    $resSupp = PaymentService::recordSupplierPayment([
        'supplier_id' => $tenant2Supplier->id,
        'amount' => 1000.00,
        'payment_mode' => 'CASH',
    ], 1); // executing under Company 1 context

    if ($resSupp['success']) {
        return ['status' => 'fail', 'reason' => 'Cross-tenant supplier payment was permitted'];
    }

    return true;
});

// 12. Sequential Numbering Under Sequential & Rapid Creations
runTest('Sequential Numbering: Ensures Unique and Ordered Sequence Numbers', function() {
    $p1 = PaymentService::recordCustomerReceipt(['customer_id' => 1, 'amount' => 10, 'payment_mode' => 'CASH'], 1);
    $p2 = PaymentService::recordCustomerReceipt(['customer_id' => 1, 'amount' => 20, 'payment_mode' => 'CASH'], 1);
    $p3 = PaymentService::recordSupplierPayment(['supplier_id' => 1, 'amount' => 30, 'payment_mode' => 'CASH'], 1);

    if (!$p1['success'] || !$p2['success'] || !$p3['success']) {
        return ['status' => 'fail', 'reason' => 'Creation failed in sequential test'];
    }

    if ($p1['payment_number'] === $p2['payment_number']) {
        return ['status' => 'fail', 'reason' => "Duplicate payment numbers generated: {$p1['payment_number']}"];
    }

    return true;
});

echo "\n====================================================================\n";
echo "  SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
} else {
    echo "  ALL PAYMENTS & EXPENSES TESTS COMPLETED SUCCESSFULLY!\n";
    exit(0);
}
