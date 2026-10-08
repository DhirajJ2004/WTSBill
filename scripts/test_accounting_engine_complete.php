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

use App\Services\AccountingService;
use App\Services\AccountService;
use App\Services\JournalService;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Company;
use App\Models\Branch;
use Illuminate\Database\Capsule\Manager as DB;

echo "====================================================================\n";
echo "  WTSBILL ERP - COMPLETE ACCOUNTING ENGINE QA AUDIT SUITE\n";
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

$companyId = 1;
AccountService::ensureDefaultAccounts($companyId);

// 1. Chart of Accounts structure test
runTest('Chart of Accounts: Default Structure and Account Mappings exist', function() use ($companyId) {
    $accounts = AccountingService::getChartOfAccounts($companyId);
    if (empty($accounts)) {
        return ['status' => 'fail', 'reason' => 'Chart of Accounts is empty'];
    }

    $cashAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT');
    $bankAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT');
    $arAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');
    $apAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_PAYABLE');
    $salesAcc = AccountService::getMappedAccount($companyId, 'SALES_REVENUE');

    if (!$cashAcc || !$bankAcc || !$arAcc || !$apAcc || !$salesAcc) {
        return ['status' => 'fail', 'reason' => 'Missing essential mapped default accounts'];
    }

    return true;
});

// 2. Balanced Journal Creation via AccountingService
runTest('Core Invariant: Post Balanced Journal Entry (Dr == Cr)', function() use ($companyId) {
    $cashAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT');
    $salesAcc = AccountService::getMappedAccount($companyId, 'SALES_REVENUE');

    $amount = 12500.50;

    $journal = AccountingService::postJournal([
        'company_id' => $companyId,
        'entry_date' => date('Y-m-d'),
        'entry_type' => 'MANUAL',
        'description' => 'Direct cash consulting revenue',
        'lines' => [
            [
                'account_id' => $cashAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => 'Debit to Cash account',
            ],
            [
                'account_id' => $salesAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => 'Credit to Sales Revenue account',
            ],
        ],
    ], $companyId, 1, 'QA Accountant');

    if (!$journal || $journal->status !== 'POSTED') {
        return ['status' => 'fail', 'reason' => 'Journal entry not created in POSTED status'];
    }

    $lines = JournalLine::where('journal_entry_id', $journal->id)->get();
    $debSum = round($lines->sum('debit'), 2);
    $credSum = round($lines->sum('credit'), 2);

    if (abs($debSum - $amount) > 0.001 || abs($credSum - $amount) > 0.001 || abs($debSum - $credSum) > 0.001) {
        return ['status' => 'fail', 'reason' => "Debit != Credit on created journal. Dr: {$debSum}, Cr: {$credSum}"];
    }

    return true;
});

// 3. Rejection of Unbalanced Journal Entry
runTest('Core Invariant: Rejects Unbalanced Journal with non-zero discrepancy', function() use ($companyId) {
    $cashAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT');
    $salesAcc = AccountService::getMappedAccount($companyId, 'SALES_REVENUE');

    try {
        AccountingService::postJournal([
            'company_id' => $companyId,
            'entry_date' => date('Y-m-d'),
            'entry_type' => 'MANUAL',
            'description' => 'Intentionally unbalanced entry',
            'lines' => [
                ['account_id' => $cashAcc->id, 'debit' => 1000.00, 'credit' => 0.00],
                ['account_id' => $salesAcc->id, 'debit' => 0.00, 'credit' => 950.00], // mismatch of 50.00
            ],
        ], $companyId);

        return ['status' => 'fail', 'reason' => 'Allowed posting of unbalanced journal!'];
    } catch (\InvalidArgumentException $e) {
        if (stripos($e->getMessage(), 'unbalanced') !== false || stripos($e->getMessage(), 'equal') !== false) {
            return true;
        }
        return ['status' => 'fail', 'reason' => "Unexpected exception message: " . $e->getMessage()];
    }
});

// 4. Rejection of Invalid Lines (Single line, negative values)
runTest('Core Invariant: Rejects single line journals and negative amounts', function() use ($companyId) {
    $cashAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT');

    // Single line rejection
    $singleLineFailed = false;
    try {
        AccountingService::postJournal([
            'company_id' => $companyId,
            'lines' => [
                ['account_id' => $cashAcc->id, 'debit' => 500.00, 'credit' => 0.00]
            ],
        ], $companyId);
    } catch (\InvalidArgumentException $e) {
        $singleLineFailed = true;
    }

    if (!$singleLineFailed) {
        return ['status' => 'fail', 'reason' => 'Allowed single line journal entry'];
    }

    // Negative amount rejection
    $negFailed = false;
    $salesAcc = AccountService::getMappedAccount($companyId, 'SALES_REVENUE');
    try {
        AccountingService::postJournal([
            'company_id' => $companyId,
            'lines' => [
                ['account_id' => $cashAcc->id, 'debit' => -500.00, 'credit' => 0.00],
                ['account_id' => $salesAcc->id, 'debit' => 0.00, 'credit' => -500.00],
            ],
        ], $companyId);
    } catch (\InvalidArgumentException $e) {
        $negFailed = true;
    }

    if (!$negFailed) {
        return ['status' => 'fail', 'reason' => 'Allowed negative debit/credit amounts'];
    }

    return true;
});

// 5. Existing Dataset Integrity Audit (715+ / 848 journals)
runTest('Audit: Scan all 848+ existing journal entries in DB to verify ZERO unbalanced journals', function() {
    $audit = AccountingService::auditLedgerIntegrity(null); // global audit across all companies

    if ($audit['total_journals_audited'] < 715) {
        return ['status' => 'fail', 'reason' => "Expected at least 715+ journals, found {$audit['total_journals_audited']}"];
    }

    if ($audit['unbalanced_journals_count'] > 0) {
        return ['status' => 'fail', 'reason' => "Found {$audit['unbalanced_journals_count']} unbalanced journal entries!"];
    }

    if ($audit['global_discrepancy'] > 0.001) {
        return ['status' => 'fail', 'reason' => "Global ledger discrepancy of ₹{$audit['global_discrepancy']}"];
    }

    return true;
});

// 6. Trial Balance Verification
runTest('Trial Balance Report: Mathematical proof that Total Debit === Total Credit', function() use ($companyId) {
    $tb = AccountingService::getTrialBalance($companyId);

    if (!$tb['is_balanced']) {
        return ['status' => 'fail', 'reason' => "Trial balance is out of balance. Delta: ₹{$tb['difference']}"];
    }

    if ($tb['total_debit'] <= 0 || $tb['total_credit'] <= 0) {
        return ['status' => 'fail', 'reason' => 'Trial balance totals are zero or negative'];
    }

    if (abs($tb['total_debit'] - $tb['total_credit']) > 0.001) {
        return ['status' => 'fail', 'reason' => "Trial balance Debits ({$tb['total_debit']}) != Credits ({$tb['total_credit']})"];
    }

    return true;
});

// 7. Profit & Loss Statement Verification
runTest('Profit & Loss Statement: Computes Revenue, COGS, Gross Profit, Expenses, and Net Profit', function() use ($companyId) {
    $pl = AccountingService::getProfitAndLoss($companyId, '2020-01-01', date('Y-m-d'));

    if (!isset($pl['revenue']['net_revenue']) || !isset($pl['gross_profit']) || !isset($pl['net_profit'])) {
        return ['status' => 'fail', 'reason' => 'Missing expected P&L calculation fields'];
    }

    $expectedGross = round($pl['revenue']['net_revenue'] - $pl['cogs']['total_cogs'], 2);
    if (abs($pl['gross_profit'] - $expectedGross) > 0.01) {
        return ['status' => 'fail', 'reason' => "Gross profit formula mismatch: {$pl['gross_profit']} != {$expectedGross}"];
    }

    $expectedNet = round($pl['operating_profit'] + $pl['other_income']['total'] - $pl['other_expenses']['total'], 2);
    if (abs($pl['net_profit'] - $expectedNet) > 0.01) {
        return ['status' => 'fail', 'reason' => "Net profit formula mismatch: {$pl['net_profit']} != {$expectedNet}"];
    }

    return true;
});

// 8. Balance Sheet Statement Verification
runTest('Balance Sheet Statement: Assets and Liabilities Structure', function() use ($companyId) {
    $bs = AccountingService::getBalanceSheet($companyId);

    if (!isset($bs['assets']['total_assets']) || !isset($bs['liabilities']['total_liabilities']) || !isset($bs['total_liabilities_and_equity'])) {
        return ['status' => 'fail', 'reason' => 'Missing expected Balance Sheet summary sections'];
    }

    return true;
});

// 9. General Ledger & Cash / Bank Book Retrieval
runTest('General Ledger, Cash Book & Bank Book Reports', function() use ($companyId) {
    $gl = AccountingService::getGeneralLedger($companyId);
    if (!is_array($gl)) {
        return ['status' => 'fail', 'reason' => 'General Ledger response invalid'];
    }

    $cashBook = AccountingService::getCashBook($companyId);
    if (!isset($cashBook['account']) || !isset($cashBook['transactions'])) {
        return ['status' => 'fail', 'reason' => 'Cash Book response invalid'];
    }

    $bankBook = AccountingService::getBankBook($companyId);
    if (!isset($bankBook['account']) || !isset($bankBook['transactions'])) {
        return ['status' => 'fail', 'reason' => 'Bank Book response invalid'];
    }

    return true;
});

// 10. Receivables & Payables Ledger Verification
runTest('Receivables & Payables Ledgers', function() use ($companyId) {
    $rec = AccountingService::getReceivables($companyId);
    if (!isset($rec['total_receivable']) || !isset($rec['customers'])) {
        return ['status' => 'fail', 'reason' => 'Receivables summary missing expected fields'];
    }

    $pay = AccountingService::getPayables($companyId);
    if (!isset($pay['total_payable']) || !isset($pay['suppliers'])) {
        return ['status' => 'fail', 'reason' => 'Payables summary missing expected fields'];
    }

    return true;
});

echo "\n====================================================================\n";
echo "  SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
} else {
    echo "  ALL ACCOUNTING ENGINE AUDIT TESTS COMPLETED SUCCESSFULLY!\n";
    exit(0);
}
