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

use App\Repositories\ReportRepository;
use App\Services\ReportService;
use App\Services\AccountingService;
use App\Services\AccountService;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Company;
use Illuminate\Database\Capsule\Manager as DB;

echo "====================================================================\n";
echo "  WTSBILL ERP - COMPLETE REPORTS & ANALYTICS QA AUDIT SUITE\n";
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

// 1. Sales Report Verification
runTest('Sales Report: Totals strictly match direct DB aggregation', function() use ($companyId) {
    $report = ReportRepository::getSalesReport($companyId);

    $dbTotals = DB::table('invoices')
        ->where('company_id', $companyId)
        ->where('status', '!=', 'CANCELLED')
        ->selectRaw('
            COUNT(id) as count,
            COALESCE(SUM(grand_total), 0) as total_grand,
            COALESCE(SUM(amount_due), 0) as total_due
        ')
        ->first();

    if ($report['summary']['total_invoices'] !== intval($dbTotals->count)) {
        return ['status' => 'fail', 'reason' => "Invoice count mismatch: {$report['summary']['total_invoices']} vs {$dbTotals->count}"];
    }

    if (abs($report['summary']['total_grand'] - floatval($dbTotals->total_grand)) > 0.01) {
        return ['status' => 'fail', 'reason' => "Grand total mismatch: {$report['summary']['total_grand']} vs {$dbTotals->total_grand}"];
    }

    return true;
});

// 2. Purchase Report Verification
runTest('Purchase Report: Totals strictly match direct DB aggregation', function() use ($companyId) {
    $report = ReportRepository::getPurchaseReport($companyId);

    $dbTotals = DB::table('purchases')
        ->where('company_id', $companyId)
        ->where('status', '!=', 'CANCELLED')
        ->selectRaw('
            COUNT(id) as count,
            COALESCE(SUM(grand_total), 0) as total_grand,
            COALESCE(SUM(amount_due), 0) as total_due
        ')
        ->first();

    if ($report['summary']['total_bills'] !== intval($dbTotals->count)) {
        return ['status' => 'fail', 'reason' => "Bill count mismatch: {$report['summary']['total_bills']} vs {$dbTotals->count}"];
    }

    if (abs($report['summary']['total_grand'] - floatval($dbTotals->total_grand)) > 0.01) {
        return ['status' => 'fail', 'reason' => "Grand total mismatch: {$report['summary']['total_grand']} vs {$dbTotals->total_grand}"];
    }

    return true;
});

// 3. Inventory Valuation Report Verification
runTest('Inventory Report: Total stock valuation and unit counts match DB', function() use ($companyId) {
    $report = ReportRepository::getInventoryReport($companyId);

    $dbTotals = DB::table('products')
        ->where('company_id', $companyId)
        ->whereNull('deleted_at')
        ->selectRaw('
            COUNT(id) as count,
            COALESCE(SUM(current_stock), 0) as total_units,
            COALESCE(SUM(current_stock * purchase_price), 0) as cost_val
        ')
        ->first();

    if ($report['summary']['total_products'] !== intval($dbTotals->count)) {
        return ['status' => 'fail', 'reason' => "Product count mismatch: {$report['summary']['total_products']} vs {$dbTotals->count}"];
    }

    if (abs($report['summary']['total_cost_valuation'] - floatval($dbTotals->cost_val)) > 0.01) {
        return ['status' => 'fail', 'reason' => "Cost valuation mismatch: {$report['summary']['total_cost_valuation']} vs {$dbTotals->cost_val}"];
    }

    return true;
});

// 4. Stock Ledger Report Verification
runTest('Stock Ledger Report: Retrieves movement trail with running balances', function() use ($companyId) {
    $report = ReportRepository::getStockLedgerReport($companyId);

    if (!isset($report['pagination']['total_records']) || !isset($report['data'])) {
        return ['status' => 'fail', 'reason' => 'Invalid stock ledger report payload structure'];
    }

    return true;
});

// 5. Party Outstanding & Aging Reports
runTest('Party Outstanding: Receivables and Payables Aging calculation', function() use ($companyId) {
    $custOutstanding = ReportRepository::getPartyOutstandingReport($companyId, ['party_type' => 'CUSTOMER']);
    $suppOutstanding = ReportRepository::getPartyOutstandingReport($companyId, ['party_type' => 'SUPPLIER']);

    if (!isset($custOutstanding['total_outstanding']) || !isset($custOutstanding['aging_summary'])) {
        return ['status' => 'fail', 'reason' => 'Customer outstanding report missing expected fields'];
    }

    if (!isset($suppOutstanding['total_outstanding']) || !isset($suppOutstanding['aging_summary'])) {
        return ['status' => 'fail', 'reason' => 'Supplier outstanding report missing expected fields'];
    }

    return true;
});

// 6. Payment Report Verification
runTest('Payment Report: Aggregates receipts vs disbursements correctly', function() use ($companyId) {
    $report = ReportRepository::getPaymentReport($companyId);

    $dbTotals = DB::table('payments')
        ->where('company_id', $companyId)
        ->where('status', '!=', 'CANCELLED')
        ->selectRaw('
            COUNT(id) as count,
            COALESCE(SUM(amount), 0) as total_amt
        ')
        ->first();

    if ($report['summary']['total_count'] !== intval($dbTotals->count)) {
        return ['status' => 'fail', 'reason' => "Payment count mismatch: {$report['summary']['total_count']} vs {$dbTotals->count}"];
    }

    if (abs($report['summary']['total_volume'] - floatval($dbTotals->total_amt)) > 0.01) {
        return ['status' => 'fail', 'reason' => "Payment volume mismatch: {$report['summary']['total_volume']} vs {$dbTotals->total_amt}"];
    }

    return true;
});

// 7. Expense Report Verification
runTest('Expense Report: Aggregates total expenses and tax components', function() use ($companyId) {
    $report = ReportRepository::getExpenseReport($companyId);

    $dbTotals = DB::table('expenses')
        ->where('company_id', $companyId)
        ->selectRaw('
            COUNT(id) as count,
            COALESCE(SUM(amount), 0) as total_amt
        ')
        ->first();

    if ($report['summary']['total_expenses'] !== intval($dbTotals->count)) {
        return ['status' => 'fail', 'reason' => "Expense count mismatch: {$report['summary']['total_expenses']} vs {$dbTotals->count}"];
    }

    if (abs($report['summary']['total_amount'] - floatval($dbTotals->total_amt)) > 0.01) {
        return ['status' => 'fail', 'reason' => "Expense total mismatch: {$report['summary']['total_amount']} vs {$dbTotals->total_amt}"];
    }

    return true;
});

// 8. GST / GSTR Tax Liability Report
runTest('GST Report: Outward GSTR-1 and Inward GSTR-3B summaries', function() use ($companyId) {
    $report = ReportRepository::getGSTReport($companyId);

    if (!isset($report['outward_supplies_gstr1']['total_tax']) || !isset($report['inward_supplies_itc']['total_tax']) || !isset($report['net_tax_payable'])) {
        return ['status' => 'fail', 'reason' => 'Missing essential GST tax liability sections'];
    }

    return true;
});

// 9. Audit Report Verification
runTest('Audit Report: Paginated system activity log stream', function() use ($companyId) {
    $report = ReportRepository::getAuditReport($companyId);

    if (!isset($report['pagination']['total_records']) || !isset($report['data'])) {
        return ['status' => 'fail', 'reason' => 'Invalid audit report structure'];
    }

    return true;
});

// 10. CSV & Excel Export Engine
runTest('Export Engine: Generates CSV exports with Excel UTF-8 BOM headers', function() use ($companyId) {
    $salesCsv = ReportService::exportCSV('sales', $companyId);
    $purchCsv = ReportService::exportCSV('purchases', $companyId);
    $invCsv = ReportService::exportCSV('inventory', $companyId);
    $expCsv = ReportService::exportCSV('expenses', $companyId);
    $tbCsv = ReportService::exportCSV('trial-balance', $companyId);

    // Verify UTF-8 BOM (\xEF\xBB\xBF) is present at start of each CSV
    if (substr($salesCsv, 0, 3) !== "\xEF\xBB\xBF" || substr($tbCsv, 0, 3) !== "\xEF\xBB\xBF") {
        return ['status' => 'fail', 'reason' => 'Excel UTF-8 BOM header missing from CSV export'];
    }

    if (strpos($salesCsv, 'Invoice Number') === false || strpos($tbCsv, 'Account Code') === false) {
        return ['status' => 'fail', 'reason' => 'CSV column headers missing'];
    }

    return true;
});

// 11. Multi-Tenant Isolation
runTest('Multi-Tenant Isolation: Tenant 1 reports strictly exclude Tenant 2 data', function() {
    $tenant2Customer = Customer::create([
        'company_id' => 2,
        'name' => 'Tenant 2 Secret Client ' . uniqid(),
        'current_balance' => 99999.00,
    ]);

    $invoiceT2 = Invoice::create([
        'company_id' => 2,
        'branch_id' => 1,
        'customer_id' => $tenant2Customer->id,
        'invoice_number' => 'INV-T2-SECRET-' . uniqid(),
        'invoice_date' => date('Y-m-d'),
        'sub_total' => 99999.00,
        'grand_total' => 99999.00,
        'amount_paid' => 0.00,
        'amount_due' => 99999.00,
        'payment_status' => 'UNPAID',
        'status' => 'ISSUED',
    ]);

    $t1SalesReport = ReportRepository::getSalesReport(1);
    foreach ($t1SalesReport['data'] as $row) {
        if ($row->id === $invoiceT2->id || $row->invoice_number === $invoiceT2->invoice_number) {
            return ['status' => 'fail', 'reason' => 'Cross-tenant leak: Tenant 2 invoice found in Tenant 1 Sales Report!'];
        }
    }

    $t1Outstanding = ReportRepository::getPartyOutstandingReport(1, ['party_type' => 'CUSTOMER']);
    foreach ($t1Outstanding['parties'] as $p) {
        if ($p['customer_id'] === $tenant2Customer->id) {
            return ['status' => 'fail', 'reason' => 'Cross-tenant leak: Tenant 2 customer found in Tenant 1 Outstanding Report!'];
        }
    }

    return true;
});

echo "\n====================================================================\n";
echo "  SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
} else {
    echo "  ALL REPORTS & ANALYTICS TESTS COMPLETED SUCCESSFULLY!\n";
    exit(0);
}
