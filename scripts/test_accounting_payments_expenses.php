<?php

require_once __DIR__ . '/../views/db_helper.php';

use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\AccountService;
use App\Services\JournalService;
use App\Services\LedgerService;
use App\Services\TrialBalanceService;
use App\Services\AccountingEventService;
use App\Banking\Services\PaymentEngine;
use Illuminate\Database\Capsule\Manager as DB;

echo "=======================================================\n";
echo "=== AUDIT & TEST: PAYMENTS, EXPENSES, ACCOUNTING ===\n";
echo "=======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition, ?string $details = null) {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$desc}\n";
    } else {
        $failCount++;
        echo " [FAIL] {$desc}" . ($details ? " - Details: {$details}" : "") . "\n";
    }
}

try {
    // Bootstrap DB
    $bootstrapPath = __DIR__ . '/../backend/bootstrap.php';
    if (file_exists($bootstrapPath)) {
        require_once $bootstrapPath;
    }

    $company = Company::first();
    if (!$company) {
        die("No company found in database.\n");
    }
    $companyId = $company->id;
    $branch = Branch::where('company_id', $companyId)->first();
    $branchId = $branch ? $branch->id : 1;

    AccountService::ensureDefaultAccounts($companyId);

    // Setup Bank Account
    $bankAcc = BankAccount::where('company_id', $companyId)->first();
    if (!$bankAcc) {
        $bankAcc = BankAccount::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'bank_name' => 'HDFC Bank Test',
            'account_name' => 'Main Operating A/c',
            'account_number' => '502000' . rand(100000, 999999),
            'ifsc_code' => 'HDFC0001234',
            'opening_balance' => 500000.00,
            'current_balance' => 500000.00,
            'is_active' => true,
            'is_primary' => true,
        ]);
    }

    // -------------------------------------------------------------
    // 1. PAYMENTS MODULE TESTS
    // -------------------------------------------------------------
    echo "\n--- SECTION 1: PAYMENTS WORKFLOW & VALIDATION ---\n";

    // Setup a customer with balance
    $customer = Customer::create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'name' => 'Acme Test Corp ' . time(),
        'email' => 'acme' . time() . '@test.com',
        'current_balance' => 50000.00,
        'is_active' => true,
    ]);

    // Test 1.1: Customer Receipt Creation
    $refNo = 'UTR-TEST-' . rand(100000, 999999);
    $receipt = PaymentEngine::processCustomerReceipt($companyId, [
        'party_id' => $customer->id,
        'amount' => 15000.00,
        'payment_mode' => 'BANK_TRANSFER',
        'payment_date' => date('Y-m-d'),
        'reference_no' => $refNo,
        'reference_number' => $refNo,
        'notes' => 'Advance collection',
        'bank_account_id' => $bankAcc->id,
    ], 'TestRunner');

    assertTest("Customer Receipt created successfully (#{$receipt->payment_number})", $receipt && $receipt->id > 0);
    assertTest("Payment recorded amount is 15000.00", floatval($receipt->amount) == 15000.00);

    // Test 1.2: Customer balance updated
    $customer->refresh();
    assertTest("Customer balance reduced by 15000 (New balance: {$customer->current_balance})", floatval($customer->current_balance) == 35000.00);

    // Test 1.3: Payment appears in database query
    $foundPayment = Payment::where('company_id', $companyId)->where('payment_number', $receipt->payment_number)->first();
    assertTest("Payment appears in database list", $foundPayment !== null);

    // Test 1.4: Double-entry accounting entry created for Payment
    $paymentJournal = JournalEntry::where('company_id', $companyId)
        ->where('reference_type', 'PAYMENT')
        ->where('reference_id', (string)$receipt->id)
        ->with('lines')
        ->first();
    assertTest("Accounting journal generated for Payment", $paymentJournal !== null);
    if ($paymentJournal) {
        assertTest("Payment Journal is balanced (Dr: {$paymentJournal->total_debit}, Cr: {$paymentJournal->total_credit})", abs($paymentJournal->total_debit - $paymentJournal->total_credit) < 0.001);
    }

    // Test 1.5: Validate amount > 0
    $zeroAmountCaught = false;
    try {
        PaymentEngine::processCustomerReceipt($companyId, [
            'party_id' => $customer->id,
            'amount' => 0,
            'payment_mode' => 'BANK_TRANSFER',
        ], 'TestRunner');
    } catch (\Throwable $e) {
        $zeroAmountCaught = true;
    }
    assertTest("Rejects payment with amount <= 0", $zeroAmountCaught);

    // Test 1.6: Validate invalid party
    $invalidPartyCaught = false;
    try {
        PaymentEngine::processCustomerReceipt($companyId, [
            'party_id' => 99999999,
            'amount' => 5000,
            'payment_mode' => 'BANK_TRANSFER',
        ], 'TestRunner');
    } catch (\Throwable $e) {
        $invalidPartyCaught = true;
    }
    assertTest("Rejects payment with non-existent party", $invalidPartyCaught);

    // Test 1.7: Validate invalid payment mode
    $invalidModeCaught = false;
    try {
        PaymentEngine::processCustomerReceipt($companyId, [
            'party_id' => $customer->id,
            'amount' => 5000,
            'payment_mode' => 'MAGIC_POINTS',
        ], 'TestRunner');
    } catch (\Throwable $e) {
        $invalidModeCaught = true;
    }
    assertTest("Rejects payment with invalid payment mode", $invalidModeCaught);

    // Test 1.8: Validate duplicate reference
    $duplicateRefCaught = false;
    try {
        PaymentEngine::processCustomerReceipt($companyId, [
            'party_id' => $customer->id,
            'amount' => 5000,
            'payment_mode' => 'BANK_TRANSFER',
            'reference_no' => $refNo,
        ], 'TestRunner');
    } catch (\Throwable $e) {
        $duplicateRefCaught = true;
    }
    assertTest("Rejects duplicate payment reference/UTR number", $duplicateRefCaught);

    // -------------------------------------------------------------
    // 2. EXPENSES MODULE TESTS
    // -------------------------------------------------------------
    echo "\n--- SECTION 2: EXPENSES WORKFLOW & DOUBLE-ENTRY ---\n";

    $expNumber = 'EXP-TEST-' . rand(10000, 99999);
    $expense = Expense::create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'expense_number' => $expNumber,
        'category' => 'Cloud Infrastructure',
        'payee' => 'AWS Cloud Services',
        'expense_date' => date('Y-m-d'),
        'amount' => 12500.00,
        'tax_amount' => 2250.00,
        'payment_mode' => 'BANK_TRANSFER',
        'reference_no' => 'INV-AWS-8899',
        'description' => 'Monthly Production Cluster Servers',
    ]);

    // Record accounting entry
    AccountingEventService::recordExpenseAccounting($expense, 'TestRunner');

    assertTest("Expense created in database (#{$expense->expense_number})", $expense && $expense->id > 0);

    // Check Expense Accounting Entry
    $expJournal = JournalEntry::where('company_id', $companyId)
        ->where('reference_type', 'EXPENSE')
        ->where('reference_id', (string)$expense->id)
        ->with('lines.account')
        ->first();

    assertTest("Double-entry journal posted for Expense", $expJournal !== null);
    if ($expJournal) {
        assertTest("Expense Journal strictly balanced (Dr: {$expJournal->total_debit}, Cr: {$expJournal->total_credit})", abs($expJournal->total_debit - $expJournal->total_credit) < 0.001);
        assertTest("Expense Journal has at least 2 lines", $expJournal->lines->count() >= 2);
    }

    // -------------------------------------------------------------
    // 3. ACCOUNTING & JOURNAL VOUCHER TESTS
    // -------------------------------------------------------------
    echo "\n--- SECTION 3: DOUBLE-ENTRY JOURNAL INVARIANTS ---\n";

    $acc1 = ChartOfAccount::where('company_id', $companyId)->where('account_code', '1010')->first() ?: ChartOfAccount::where('company_id', $companyId)->first();
    $acc2 = ChartOfAccount::where('company_id', $companyId)->where('account_code', '4000')->first() ?: ChartOfAccount::where('company_id', $companyId)->where('id', '!=', $acc1->id)->first();

    // Test 3.1: Valid Balanced Manual Journal
    $manualJournal = JournalService::createJournalEntry([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'financial_year' => '2026-27',
        'entry_date' => date('Y-m-d'),
        'entry_type' => 'MANUAL',
        'description' => 'Test Manual Balancing Journal',
        'lines' => [
            ['account_id' => $acc2->id, 'debit' => 5000.00, 'credit' => 0.00, 'description' => 'Debit Line'],
            ['account_id' => $acc1->id, 'debit' => 0.00, 'credit' => 5000.00, 'description' => 'Credit Line'],
        ]
    ], 'TestRunner');

    assertTest("Balanced Manual Journal posted successfully (#{$manualJournal->journal_number})", $manualJournal && $manualJournal->id > 0);
    assertTest("Journal Total Debit == Total Credit", abs($manualJournal->total_debit - $manualJournal->total_credit) < 0.001);

    // Test 3.2: Rejection of Unbalanced Journal (Dr != Cr)
    $unbalancedCaught = false;
    try {
        JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'financial_year' => '2026-27',
            'entry_date' => date('Y-m-d'),
            'entry_type' => 'MANUAL',
            'description' => 'Unbalanced Attempt',
            'lines' => [
                ['account_id' => $acc2->id, 'debit' => 5000.00, 'credit' => 0.00],
                ['account_id' => $acc1->id, 'debit' => 0.00, 'credit' => 4500.00], // Mismatch!
            ]
        ], 'TestRunner');
    } catch (\Throwable $e) {
        $unbalancedCaught = true;
    }
    assertTest("Strictly rejects unbalanced journal (Delta: ₹500.00)", $unbalancedCaught);

    // -------------------------------------------------------------
    // 4. GENERAL LEDGER & FINANCIAL YEAR FILTER TESTS
    // -------------------------------------------------------------
    echo "\n--- SECTION 4: GENERAL LEDGER & FY FILTER ---\n";

    // Query ledger for acc1 in FY 2026-27
    $ledger = LedgerService::getAccountLedger($companyId, $acc1->id, '2026-04-01', '2027-03-31', $branchId, '2026-27');
    assertTest("General Ledger query executes without error", is_array($ledger) && (isset($ledger['transactions']) || isset($ledger['entries'])));
    assertTest("General Ledger calculates running balance", isset($ledger['closing_balance']));
    $txs = $ledger['transactions'] ?? ($ledger['entries'] ?? []);
    assertTest("General Ledger contains transaction entries", count($txs) > 0);

    // Test filtering by another FY (e.g. 2024-25 which has no entries)
    $pastLedger = LedgerService::getAccountLedger($companyId, $acc1->id, '2024-04-01', '2025-03-31', $branchId, '2024-25');
    $pastTxs = $pastLedger['transactions'] ?? ($pastLedger['entries'] ?? []);
    assertTest("FY filter restricts data correctly to selected date range", count($pastTxs) == 0 || $pastTxs[0]['date'] < '2025-04-01');

    // -------------------------------------------------------------
    // 5. TRIAL BALANCE TESTS
    // -------------------------------------------------------------
    echo "\n--- SECTION 5: TRIAL BALANCE BALANCING ---\n";

    $trialBal = TrialBalanceService::getTrialBalance($companyId, date('Y-m-d'), $branchId, '2026-27');
    assertTest("Trial Balance query executes successfully", is_array($trialBal));
    assertTest("Trial Balance has debit and credit totals", isset($trialBal['total_debit']) && isset($trialBal['total_credit']));
    assertTest("Trial Balance is balanced (Total Debit == Total Credit, Delta: ₹{$trialBal['difference']})", $trialBal['is_balanced']);

    // -------------------------------------------------------------
    // SUMMARY
    // -------------------------------------------------------------
    echo "\n=======================================================\n";
    echo "=== RESULTS: {$passCount} PASSED, {$failCount} FAILED ===\n";
    echo "=======================================================\n";

    if ($failCount > 0) {
        exit(1);
    }

} catch (\Throwable $e) {
    echo "\n[ERROR CRASH] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
