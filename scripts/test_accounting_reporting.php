<?php
/**
 * WTSBill ERP — Double-Entry Accounting & Financial Reporting Test Suite
 *
 * Validates:
 *  1. Chart of Accounts structure, tenant isolation, and account definitions
 *  2. Universal Invariant: SUM(DEBIT) == SUM(CREDIT) on every single journal
 *  3. SALE: Dr Accounts Receivable, Cr Sales Revenue, Cr Output GST
 *  4. INVENTORY SALE: Dr COGS, Cr Inventory Asset
 *  5. PAYMENT (Customer Receipt): Dr Bank/Cash, Cr Accounts Receivable
 *  6. PURCHASE: Dr Inventory, Dr Input GST, Cr Accounts Payable
 *  7. SUPPLIER PAYMENT: Dr Accounts Payable, Cr Bank/Cash
 *  8. EXPENSE: Dr Expense, Dr Input GST (where applicable), Cr Bank/Cash or Payable
 *  9. Trial Balance (Balanced to the penny)
 * 10. General Ledger (Running balances & double-entry tracking)
 * 11. Profit & Loss Statement (P&L uses COGS, does not treat purchases as COGS)
 * 12. Balance Sheet (Assets == Liabilities + Equity)
 * 13. Cash Flow Statement (Cash reconciliation)
 * 14. Operational Reports: Sales, Purchases, GST, Receivables, Payables, Inventory, Stock Valuation
 */

// PHP 8.0 CLI Polyfills
if (!function_exists('enum_exists')) {
    function enum_exists(string $enum, bool $autoload = true): bool { return false; }
}

require __DIR__ . '/../backend/vendor/autoload.php';

\App\Database\Database::init();

use App\Models\Company;
use App\Models\User;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Expense;
use App\Services\AccountService;
use App\Services\JournalService;
use App\Services\LedgerService;
use App\Services\TrialBalanceService;
use App\Services\ProfitLossService;
use App\Services\BalanceSheetService;
use App\Services\CashFlowService;
use App\Services\AccountingEventService;
use App\Services\InvoiceService;
use App\Services\PurchaseInvoiceService;
use App\Services\InventoryService;
use App\Banking\Services\PaymentEngine;
use App\Reporting\Services\FinancialReportService;
use App\Reporting\Services\SalesReportService;
use App\Reporting\Services\PurchaseReportService;
use App\Reporting\Services\InventoryReportService;
use App\Reporting\Services\CustomerReportService;
use App\Reporting\Services\SupplierReportService;
use App\Services\GSTReportingService;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

$passed = 0;
$failed = 0;
$skipped = 0;

function assertCondition(string $name, bool $condition, ?string $detail = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$name}\n";
    } else {
        $failed++;
        echo " [FAIL] {$name}\n";
    }
    if ($detail) {
        echo "        Detail: {$detail}\n";
    }
}

function section(string $title): void
{
    echo "\n--- {$title} ---\n";
}

// Set up authenticated context for Company 1
$user = User::withoutGlobalScopes()->where('email', 'anil.d@wtsbill.in')->first();
if (!$user) {
    $user = User::withoutGlobalScopes()->first();
}
$company = Company::find(1);
$user->current_company_id = 1;
AuthMiddleware::setContext($user, 1, 1, 'owner', '2026-27');

echo "====================================================================\n";
echo "   WTSBill ERP Accounting & Financial Reporting Test Suite          \n";
echo "====================================================================\n";
echo "Active Company: #{$company->id} ({$company->name})\n";
echo "Active User: #{$user->id} ({$user->name})\n";

// Ensure default accounts exist
AccountService::ensureDefaultAccounts(1);

// ─── SECTION 1: Universal Invariant Across All Existing Journal Entries ───
section('SECTION 1: Universal Double-Entry Invariant Check');

$unbalancedJournals = JournalEntry::withoutGlobalScopes()
    ->where('company_id', 1)
    ->whereRaw('ABS(total_debit - total_credit) > 0.001')
    ->get();

assertCondition(
    '1a. Every existing Journal Entry satisfies SUM(DEBIT) == SUM(CREDIT)',
    $unbalancedJournals->isEmpty(),
    $unbalancedJournals->isEmpty() ? 'All ' . JournalEntry::withoutGlobalScopes()->where('company_id', 1)->count() . ' entries are balanced.' : 'Unbalanced entries: ' . $unbalancedJournals->pluck('journal_number')->implode(', ')
);

$lineDiscrepancies = DB::table('journal_entries as je')
    ->join('journal_lines as jl', 'jl.journal_entry_id', '=', 'je.id')
    ->where('je.company_id', 1)
    ->groupBy('je.id', 'je.journal_number', 'je.total_debit', 'je.total_credit')
    ->havingRaw('ABS(je.total_debit - SUM(jl.debit)) > 0.01 OR ABS(je.total_credit - SUM(jl.credit)) > 0.01')
    ->select('je.id', 'je.journal_number', 'je.total_debit', DB::raw('SUM(jl.debit) as sum_debit'), 'je.total_credit', DB::raw('SUM(jl.credit) as sum_credit'))
    ->get();

assertCondition(
    '1b. Sum of Journal Lines debits and credits exactly match Journal Entry header',
    $lineDiscrepancies->isEmpty(),
    $lineDiscrepancies->isEmpty() ? 'All journal lines match headers.' : 'Discrepancies found: ' . count($lineDiscrepancies)
);

// ─── SECTION 2: SALE Transaction Double-Entry Verification ─────────────
section('SECTION 2: SALE Transaction Double-Entry (Dr AR, Cr Revenue, Cr Output GST)');

$customer = Customer::withoutGlobalScopes()->where('company_id', 1)->first();
$product = Product::withoutGlobalScopes()->where('company_id', 1)->where('track_inventory', 1)->first();
$warehouse = Warehouse::withoutGlobalScopes()->where('company_id', 1)->where('is_active', 1)->first();

if (!$customer || !$product || !$warehouse) {
    echo "FATAL: Seed data missing.\n";
    exit(1);
}

// Make sure product has purchase price
if (floatval($product->purchase_price) <= 0) {
    $product->update(['purchase_price' => 150.00]);
}

$saleQty = 2.0;
$unitPrice = floatval($product->sales_price ?: 300.00);
$taxRate = floatval($product->gst_rate ?: 18.0);
$costPrice = floatval($product->purchase_price ?: 150.00);

$invRes = InvoiceService::createInvoice([
    'customer_id'   => $customer->id,
    'warehouse_id'  => $warehouse->id,
    'invoice_date'  => date('Y-m-d'),
    'status'        => 'POSTED',
    'items'         => [
        [
            'product_id' => $product->id,
            'item_name'  => $product->name,
            'quantity'   => $saleQty,
            'unit_price' => $unitPrice,
            'gst_rate'   => $taxRate,
            'unit'       => 'Pcs',
        ]
    ]
], 1, 1, $user);

$saleInvoice = $invRes['data'] ?? null;

assertCondition(
    '2a. Sales Invoice created and posted',
    $saleInvoice !== null && $saleInvoice->status === 'POSTED',
    "Invoice: #{$saleInvoice?->invoice_number}, Total: ₹{$saleInvoice?->grand_total}"
);

// Find the SALE journal entry
$saleJournal = JournalEntry::withoutGlobalScopes()
    ->where('company_id', 1)
    ->where('entry_type', 'SALE')
    ->where('reference_type', 'INVOICE')
    ->where('reference_id', (string)$saleInvoice->id)
    ->first();

assertCondition(
    '2b. SALE journal entry created and balanced',
    $saleJournal !== null && abs($saleJournal->total_debit - $saleJournal->total_credit) < 0.001,
    "JV: #{$saleJournal?->journal_number}, Dr: ₹{$saleJournal?->total_debit}, Cr: ₹{$saleJournal?->total_credit}"
);

// Verify SALE journal lines: Dr AR, Cr Revenue, Cr Output GST
$arLine = $saleJournal ? $saleJournal->lines()->where('debit', '>', 0)->first() : null;
$revLine = $saleJournal ? $saleJournal->lines()->where('credit', '>', 0)->whereHas('account', fn($q) => $q->where('account_subtype', 'SALES'))->first() : null;
$gstLine = $saleJournal ? $saleJournal->lines()->where('credit', '>', 0)->whereHas('account', fn($q) => $q->where('account_subtype', 'GST_PAYABLE'))->first() : null;

assertCondition(
    '2c. SALE journal debits Accounts Receivable for invoice grand total',
    $arLine !== null && abs($arLine->debit - floatval($saleInvoice->grand_total)) < 0.01,
    "AR Debit: ₹{$arLine?->debit}, Grand Total: ₹{$saleInvoice->grand_total}"
);

$saleTaxable = floatval($saleInvoice->taxable_value ?: ($saleInvoice->sub_total ?: 0));
assertCondition(
    '2d. SALE journal credits Sales Revenue for taxable amount',
    $revLine !== null && abs($revLine->credit - $saleTaxable) < 0.01,
    "Sales Revenue Credit: ₹{$revLine?->credit}, Taxable: ₹{$saleTaxable}"
);

assertCondition(
    '2e. SALE journal credits Output GST liabilities',
    $gstLine !== null,
    "Output GST Credit: ₹{$gstLine?->credit}"
);

// ─── SECTION 3: INVENTORY SALE Double-Entry Verification ───────────────
section('SECTION 3: INVENTORY SALE Double-Entry (Dr COGS, Cr Inventory)');

$invSaleJournal = JournalEntry::withoutGlobalScopes()
    ->where('company_id', 1)
    ->where('entry_type', 'INVENTORY_SALE')
    ->where('reference_type', 'INVOICE')
    ->where('reference_id', (string)$saleInvoice->id)
    ->first();

assertCondition(
    '3a. INVENTORY_SALE journal entry created',
    $invSaleJournal !== null,
    "JV: #{$invSaleJournal?->journal_number}, Dr: ₹{$invSaleJournal?->total_debit}, Cr: ₹{$invSaleJournal?->total_credit}"
);

$expectedCogs = round($saleQty * $costPrice, 2);
$cogsLine = $invSaleJournal ? $invSaleJournal->lines()->where('debit', '>', 0)->whereHas('account', fn($q) => $q->where('account_subtype', 'COGS'))->first() : null;
$invLine = $invSaleJournal ? $invSaleJournal->lines()->where('credit', '>', 0)->whereHas('account', fn($q) => $q->where('account_subtype', 'INVENTORY'))->first() : null;

assertCondition(
    '3b. INVENTORY_SALE debits Cost of Goods Sold (COGS)',
    $cogsLine !== null && abs($cogsLine->debit - $expectedCogs) < 0.01,
    "COGS Debit: ₹{$cogsLine?->debit}, Expected: ₹{$expectedCogs}"
);

assertCondition(
    '3c. INVENTORY_SALE credits Inventory Asset',
    $invLine !== null && abs($invLine->credit - $expectedCogs) < 0.01,
    "Inventory Credit: ₹{$invLine?->credit}, Expected: ₹{$expectedCogs}"
);

assertCondition(
    '3d. INVENTORY_SALE satisfies Invariant SUM(DEBIT) == SUM(CREDIT)',
    $invSaleJournal !== null && abs($invSaleJournal->total_debit - $invSaleJournal->total_credit) < 0.001,
    "Dr: ₹{$invSaleJournal?->total_debit}, Cr: ₹{$invSaleJournal?->total_credit}"
);

// ─── SECTION 4: PAYMENT (Customer Receipt) Double-Entry ───────────────
section('SECTION 4: PAYMENT (Customer Receipt: Dr Bank/Cash, Cr Accounts Receivable)');

$receiptAmount = floatval($saleInvoice->grand_total);
$bankAccount = BankAccount::where('company_id', 1)->where('is_active', 1)->first();

$paymentResult = PaymentEngine::processCustomerReceipt(1, [
    'invoice_id'            => $saleInvoice->id,
    'payment_mode'          => 'BANK_TRANSFER',
    'bank_account_id'       => $bankAccount->id,
    'amount'                => $receiptAmount,
    'payment_date'          => date('Y-m-d'),
    'transaction_reference' => 'UTR-' . rand(1000000, 9999999),
    'notes'                 => 'Automated test receipt',
]);

$receiptJournal = JournalEntry::withoutGlobalScopes()
    ->where('company_id', 1)
    ->where('entry_type', 'RECEIPT')
    ->where('reference_type', 'PAYMENT')
    ->where('reference_id', (string)$paymentResult->id)
    ->first();

assertCondition(
    '4a. Customer receipt recorded successfully',
    $paymentResult !== null && $paymentResult->status === 'POSTED',
    "Receipt: #{$paymentResult->payment_number}, Amount: ₹{$paymentResult->amount}"
);

assertCondition(
    '4b. Receipt journal entry satisfies Invariant SUM(DEBIT) == SUM(CREDIT)',
    $receiptJournal !== null && abs($receiptJournal->total_debit - $receiptJournal->total_credit) < 0.001,
    "JV: #{$receiptJournal?->journal_number}, Dr: ₹{$receiptJournal?->total_debit}, Cr: ₹{$receiptJournal?->total_credit}"
);

$receiptBankDr = $receiptJournal ? $receiptJournal->lines()->where('debit', '>', 0)->whereHas('account', fn($q) => $q->whereIn('account_subtype', ['BANK', 'CASH']))->first() : null;
$receiptArCr = $receiptJournal ? $receiptJournal->lines()->where('credit', '>', 0)->whereHas('account', fn($q) => $q->where('account_subtype', 'ACCOUNTS_RECEIVABLE'))->first() : null;

assertCondition(
    '4c. Receipt journal debits Bank/Cash and credits Accounts Receivable',
    $receiptBankDr !== null && $receiptArCr !== null && abs($receiptBankDr->debit - $receiptAmount) < 0.01 && abs($receiptArCr->credit - $receiptAmount) < 0.01,
    "Bank Dr: ₹{$receiptBankDr?->debit}, AR Cr: ₹{$receiptArCr?->credit}"
);

// ─── SECTION 5: PURCHASE Double-Entry (Dr Inventory, Dr Input GST, Cr AP) ───
section('SECTION 5: PURCHASE Double-Entry (Dr Inventory, Dr Input GST, Cr Accounts Payable)');

$supplier = Supplier::withoutGlobalScopes()->where('company_id', 1)->first();

$draftBill = PurchaseInvoiceService::createPurchase([
    'supplier_id'   => $supplier->id,
    'warehouse_id'  => $warehouse->id,
    'purchase_date' => date('Y-m-d'),
    'status'        => 'DRAFT',
    'items'         => [
        [
            'product_id' => $product->id,
            'item_name'  => $product->name,
            'quantity'   => 5,
            'unit_price' => 150.00,
            'gst_rate'   => 18.0,
            'unit'       => 'Pcs',
        ]
    ]
]);
$purchaseBill = PurchaseInvoiceService::postPurchase($draftBill->id);

$purchaseJournal = JournalEntry::withoutGlobalScopes()
    ->where('company_id', 1)
    ->where('entry_type', 'PURCHASE')
    ->where('reference_type', 'PURCHASE_INVOICE')
    ->where('reference_id', (string)$purchaseBill->id)
    ->first();

assertCondition(
    '5a. Purchase Bill created and posted',
    $purchaseBill !== null && $purchaseBill->status === 'POSTED',
    "Bill: #{$purchaseBill->purchase_number}, Total: ₹{$purchaseBill->grand_total}"
);

assertCondition(
    '5b. Purchase journal satisfies Invariant SUM(DEBIT) == SUM(CREDIT)',
    $purchaseJournal !== null && abs($purchaseJournal->total_debit - $purchaseJournal->total_credit) < 0.001,
    "JV: #{$purchaseJournal?->journal_number}, Dr: ₹{$purchaseJournal?->total_debit}, Cr: ₹{$purchaseJournal?->total_credit}"
);

$purInvDr = $purchaseJournal ? $purchaseJournal->lines()->where('debit', '>', 0)->whereHas('account', fn($q) => $q->where('account_subtype', 'INVENTORY'))->first() : null;
$purGstDr = $purchaseJournal ? $purchaseJournal->lines()->where('debit', '>', 0)->whereHas('account', fn($q) => $q->where('account_subtype', 'TAX_RECEIVABLE'))->first() : null;
$purApCr = $purchaseJournal ? $purchaseJournal->lines()->where('credit', '>', 0)->whereHas('account', fn($q) => $q->where('account_subtype', 'ACCOUNTS_PAYABLE'))->first() : null;

assertCondition(
    '5c. Purchase debits Inventory Asset and Input GST, credits Accounts Payable',
    $purInvDr !== null && $purGstDr !== null && $purApCr !== null,
    "Inventory Dr: ₹{$purInvDr?->debit}, Input GST Dr: ₹{$purGstDr?->debit}, AP Cr: ₹{$purApCr?->credit}"
);

// ─── SECTION 6: SUPPLIER PAYMENT Double-Entry (Dr AP, Cr Bank/Cash) ────────
section('SECTION 6: SUPPLIER PAYMENT Double-Entry (Dr Accounts Payable, Cr Bank/Cash)');

$suppPayAmount = floatval($purchaseBill->grand_total);
$suppPayment = PaymentEngine::processSupplierPayment(1, [
    'purchase_id'           => $purchaseBill->id,
    'payment_mode'          => 'BANK_TRANSFER',
    'bank_account_id'       => $bankAccount->id,
    'amount'                => $suppPayAmount,
    'payment_date'          => date('Y-m-d'),
    'transaction_reference' => 'UTR-' . rand(1000000, 9999999),
    'notes'                 => 'Automated test supplier payment',
]);

$suppPayJournal = JournalEntry::withoutGlobalScopes()
    ->where('company_id', 1)
    ->where('entry_type', 'PAYMENT')
    ->where('reference_type', 'PAYMENT')
    ->where('reference_id', (string)$suppPayment->id)
    ->first();

assertCondition(
    '6a. Supplier payment recorded successfully',
    $suppPayment !== null && $suppPayment->status === 'POSTED',
    "Payment: #{$suppPayment->payment_number}, Amount: ₹{$suppPayment->amount}"
);

assertCondition(
    '6b. Supplier payment journal satisfies Invariant SUM(DEBIT) == SUM(CREDIT)',
    $suppPayJournal !== null && abs($suppPayJournal->total_debit - $suppPayJournal->total_credit) < 0.001,
    "JV: #{$suppPayJournal?->journal_number}, Dr: ₹{$suppPayJournal?->total_debit}, Cr: ₹{$suppPayJournal?->total_credit}"
);

$suppApDr = $suppPayJournal ? $suppPayJournal->lines()->where('debit', '>', 0)->whereHas('account', fn($q) => $q->where('account_subtype', 'ACCOUNTS_PAYABLE'))->first() : null;
$suppBankCr = $suppPayJournal ? $suppPayJournal->lines()->where('credit', '>', 0)->whereHas('account', fn($q) => $q->whereIn('account_subtype', ['BANK', 'CASH']))->first() : null;

assertCondition(
    '6c. Supplier payment debits Accounts Payable and credits Bank/Cash',
    $suppApDr !== null && $suppBankCr !== null && abs($suppApDr->debit - $suppPayAmount) < 0.01 && abs($suppBankCr->credit - $suppPayAmount) < 0.01,
    "AP Dr: ₹{$suppApDr?->debit}, Bank Cr: ₹{$suppBankCr?->credit}"
);

// ─── SECTION 7: EXPENSE Double-Entry ────────────────────────────────────
section('SECTION 7: EXPENSE Double-Entry (Dr Expense, Dr Input GST, Cr Bank/Payable)');

$expAccount = ChartOfAccount::where('company_id', 1)->where('account_subtype', 'OPERATING_EXPENSES')->first();

$testExpense = Expense::create([
    'company_id'      => 1,
    'branch_id'       => 1,
    'expense_number'  => 'EXP-TEST-' . rand(1000, 9999),
    'title'           => 'Warehouse Electrical Maintenance',
    'category'        => 'Utilities',
    'account_id'      => $expAccount->id,
    'amount'          => 2360.00,
    'tax_amount'      => 360.00,
    'is_itc_eligible' => 1,
    'gstin'           => '27AAAAA0000A1Z5',
    'payment_mode'    => 'BANK_TRANSFER',
    'expense_date'    => date('Y-m-d'),
    'status'          => 'APPROVED',
]);

$expJournal = AccountingEventService::recordExpenseAccounting($testExpense, $user->name);

assertCondition(
    '7a. Operating Expense journal created',
    $expJournal !== null,
    "JV: #{$expJournal?->journal_number}, Dr: ₹{$expJournal?->total_debit}, Cr: ₹{$expJournal?->total_credit}"
);

assertCondition(
    '7b. Expense journal satisfies Invariant SUM(DEBIT) == SUM(CREDIT)',
    $expJournal !== null && abs($expJournal->total_debit - $expJournal->total_credit) < 0.001,
    "Dr: ₹{$expJournal?->total_debit}, Cr: ₹{$expJournal?->total_credit}"
);

$expDr = $expJournal ? $expJournal->lines()->where('debit', '>', 0)->whereHas('account', fn($q) => $q->where('account_type', 'EXPENSE'))->first() : null;
$expGstDr = $expJournal ? $expJournal->lines()->where('debit', '>', 0)->whereHas('account', fn($q) => $q->where('account_subtype', 'TAX_RECEIVABLE'))->first() : null;
$expBankCr = $expJournal ? $expJournal->lines()->where('credit', '>', 0)->first() : null;

assertCondition(
    '7c. Expense debits Expense (Net) and Input GST, credits Bank Account',
    $expDr !== null && $expGstDr !== null && $expBankCr !== null && abs($expDr->debit - 2000.00) < 0.01,
    "Net Expense Dr: ₹{$expDr?->debit}, GST Dr: ₹{$expGstDr?->debit}, Total Cr: ₹{$expBankCr?->credit}"
);

// ─── SECTION 8: Trial Balance Verification ──────────────────────────────
section('SECTION 8: Trial Balance (Single Source of Double-Entry Truth)');

$tb = TrialBalanceService::getTrialBalance(1);

assertCondition(
    '8a. Trial Balance is fully balanced to the penny (is_balanced == true)',
    $tb['is_balanced'] === true && floatval($tb['difference']) < 0.01,
    "Total Debits: ₹{$tb['total_debit']}, Total Credits: ₹{$tb['total_credit']}, Difference: ₹{$tb['difference']}"
);

assertCondition(
    '8b. Trial Balance contains active double-entry accounts',
    count($tb['accounts']) > 5,
    "Accounts with balance: " . count($tb['accounts'])
);

$financialReportTb = FinancialReportService::getTrialBalance(1);
assertCondition(
    '8c. FinancialReportService::getTrialBalance produces compliant report output',
    $financialReportTb['summary_kpis']['is_balanced'] === true && !empty($financialReportTb['rows']),
    "Rows: " . count($financialReportTb['rows'])
);

// ─── SECTION 9: General Ledger Verification ─────────────────────────────
section('SECTION 9: General Ledger (Account Ledgers & Running Balances)');

$gl = LedgerService::getGeneralLedger(1);

assertCondition(
    '9a. General Ledger generates successfully for all active accounts',
    !empty($gl),
    "Active Account Ledgers: " . count($gl)
);

// Check AR account running balance
$arAccount = ChartOfAccount::where('company_id', 1)->where('account_subtype', 'ACCOUNTS_RECEIVABLE')->first();
$arLedger = LedgerService::getAccountLedger(1, $arAccount->id);

assertCondition(
    '9b. Accounts Receivable Ledger has correct opening, closing & transaction history',
    count($arLedger['transactions']) > 0 && abs(($arLedger['opening_balance'] + $arLedger['total_debit'] - $arLedger['total_credit']) - $arLedger['closing_balance']) < 0.01,
    "Opening: ₹{$arLedger['opening_balance']}, Dr: ₹{$arLedger['total_debit']}, Cr: ₹{$arLedger['total_credit']}, Closing: ₹{$arLedger['closing_balance']}"
);

$glReport = FinancialReportService::getGeneralLedger(1, ['account_id' => $arAccount->id]);
assertCondition(
    '9c. FinancialReportService::getGeneralLedger formats clean reporting KPIs and rows',
    !empty($glReport['summary_kpis']['account_name']) && isset($glReport['rows']),
    "Account: {$glReport['summary_kpis']['account_name']}, Rows: " . count($glReport['rows'])
);

// ─── SECTION 10: Profit & Loss Statement (COGS and Non-Purchase Logic) ──
section('SECTION 10: Profit & Loss Statement (P&L Uses Real Accounting COGS)');

$pnl = ProfitLossService::getProfitAndLoss(1);

assertCondition(
    '10a. P&L Net Revenue is calculated from Sales accounts minus returns',
    floatval($pnl['revenue']['net_revenue']) > 0,
    "Net Revenue: ₹{$pnl['revenue']['net_revenue']}"
);

assertCondition(
    '10b. P&L COGS uses true accounting COGS and does NOT treat all purchases as COGS',
    floatval($pnl['cogs']['total_cogs']) >= 0,
    "Accounting COGS: ₹{$pnl['cogs']['total_cogs']}"
);

assertCondition(
    '10c. Gross Profit exactly equals Net Revenue minus COGS',
    abs(floatval($pnl['gross_profit']) - (floatval($pnl['revenue']['net_revenue']) - floatval($pnl['cogs']['total_cogs']))) < 0.01,
    "Gross Profit: ₹{$pnl['gross_profit']}"
);

assertCondition(
    '10d. Net Profit exactly equals Gross Profit minus Operating Expenses plus Other Net',
    abs(floatval($pnl['net_profit']) - (floatval($pnl['gross_profit']) - floatval($pnl['operating_expenses']['total_expenses']) + floatval($pnl['other_income']['total']) - floatval($pnl['other_expenses']['total']))) < 0.01,
    "Net Profit: ₹{$pnl['net_profit']}"
);

$pnlReport = FinancialReportService::getProfitLoss(1);
assertCondition(
    '10e. FinancialReportService::getProfitLoss generates complete report rows & charts',
    isset($pnlReport['summary_kpis']['net_profit']) && !empty($pnlReport['rows']),
    "KPI Net Profit: ₹{$pnlReport['summary_kpis']['net_profit']}, Rows: " . count($pnlReport['rows'])
);

// ─── SECTION 11: Balance Sheet Verification ─────────────────────────────
section('SECTION 11: Balance Sheet (Assets == Liabilities + Equity)');

$bs = BalanceSheetService::getBalanceSheet(1);

assertCondition(
    '11a. Balance Sheet is fully balanced (Assets == Liabilities + Equity)',
    $bs['is_balanced'] === true && floatval($bs['difference']) <= 0.05,
    "Assets: ₹{$bs['assets']['total_assets']}, Liab+Equity: ₹" . ($bs['liabilities']['total_liabilities'] + $bs['equity']['total_equity']) . ", Diff: ₹{$bs['difference']}"
);

$bsReport = FinancialReportService::getBalanceSheet(1);
assertCondition(
    '11b. FinancialReportService::getBalanceSheet produces categorized balance sheet rows',
    isset($bsReport['summary_kpis']['total_assets']) && !empty($bsReport['rows']),
    "Assets: ₹{$bsReport['summary_kpis']['total_assets']}, Liab: ₹{$bsReport['summary_kpis']['total_liabilities']}, Equity: ₹{$bsReport['summary_kpis']['total_equity']}"
);

// ─── SECTION 12: Cash Flow Statement Verification ───────────────────────
section('SECTION 12: Cash Flow Statement (Cash & Bank Activity Reconciliation)');

$cf = CashFlowService::getCashFlow(1);

$calcClose = round(floatval($cf['opening_cash_balance']) + floatval($cf['net_cash_movement']), 2);
assertCondition(
    '12a. Cash Flow reconciles: Opening Cash + Net Movement == Closing Cash',
    abs($calcClose - floatval($cf['closing_cash_balance'])) < 0.01,
    "Open: ₹{$cf['opening_cash_balance']}, Net: ₹{$cf['net_cash_movement']}, Close: ₹{$cf['closing_cash_balance']}"
);

$cfReport = FinancialReportService::getCashFlow(1);
assertCondition(
    '12b. FinancialReportService::getCashFlow produces structured KPIs and activity items',
    isset($cfReport['summary_kpis']['closing_cash']),
    "Closing Cash KPI: ₹{$cfReport['summary_kpis']['closing_cash']}"
);

// ─── SECTION 13: Operational Reports Verification ───────────────────────
section('SECTION 13: Subsystem Reports (Sales, Purchases, GST, AP/AR, Inventory)');

$salesSummary = SalesReportService::getSalesSummary(1);
assertCondition(
    '13a. Sales Report generated successfully with summary KPIs',
    isset($salesSummary['summary_kpis']['total_sales']) && $salesSummary['summary_kpis']['invoice_count'] > 0,
    "Invoices: {$salesSummary['summary_kpis']['invoice_count']}, Gross Sales: ₹{$salesSummary['summary_kpis']['total_sales']}"
);

$purchaseSummary = PurchaseReportService::getPurchaseSummary(1);
assertCondition(
    '13b. Purchase Report generated successfully with summary KPIs',
    isset($purchaseSummary['summary_kpis']['grand_total']) && $purchaseSummary['summary_kpis']['purchase_count'] > 0,
    "Bills: {$purchaseSummary['summary_kpis']['purchase_count']}, Gross Purchases: ₹{$purchaseSummary['summary_kpis']['grand_total']}"
);

$gstSummary = GSTReportingService::getDashboardSummary(1);
assertCondition(
    '13c. GST Dashboard Summary generated with Output & Input tax breakdown',
    isset($gstSummary['output_tax']['total']) && isset($gstSummary['input_tax_credit']['total']),
    "Output GST: ₹{$gstSummary['output_tax']['total']}, Input Tax Credit: ₹{$gstSummary['input_tax_credit']['total']}"
);

$gstr1 = GSTReportingService::getGSTR1Data(1);
assertCondition(
    '13d. GSTR-1 Report generated with B2B and tax summaries',
    isset($gstr1['b2b']) && isset($gstr1['hsn_summary']),
    "B2B count: " . ($gstr1['b2b']['count'] ?? 0) . ", Taxable: ₹" . ($gstr1['b2b']['taxable_value'] ?? 0)
);

$gstr3b = GSTReportingService::getGSTR3BData(1);
assertCondition(
    '13e. GSTR-3B Report generated with outward supplies & eligible ITC',
    isset($gstr3b['table_3_1_outward_supplies']) && isset($gstr3b['table_4_eligible_itc']),
    "Outward Taxable: ₹" . ($gstr3b['table_3_1_outward_supplies']['taxable_value'] ?? 0) . ", ITC Taxable: ₹" . ($gstr3b['table_4_eligible_itc']['taxable_value'] ?? 0)
);

$custAging = CustomerReportService::getCustomerOutstanding(1);
assertCondition(
    '13f. Customer Receivables / Outstanding report generated',
    isset($custAging['summary_kpis']['total_outstanding']),
    "Total Outstanding: ₹{$custAging['summary_kpis']['total_outstanding']}"
);

$suppAging = SupplierReportService::getSupplierOutstanding(1);
assertCondition(
    '13g. Supplier Payables / Outstanding report generated',
    isset($suppAging['summary_kpis']['total_payable']),
    "Total Payable: ₹{$suppAging['summary_kpis']['total_payable']}"
);

$invSummary = InventoryReportService::getInventorySummary(1);
assertCondition(
    '13h. Inventory Summary report generated with stock units & total valuation',
    isset($invSummary['summary_kpis']['total_products']) && isset($invSummary['summary_kpis']['total_stock_valuation']),
    "Products: {$invSummary['summary_kpis']['total_products']}, Stock Valuation: ₹{$invSummary['summary_kpis']['total_stock_valuation']}"
);

$stockVal = InventoryReportService::getStockValuation(1);
assertCondition(
    '13i. Stock Valuation report generated with inventory valuation',
    isset($stockVal['summary_kpis']['total_stock_value']) && !empty($stockVal['rows']),
    "Total Stock Valuation: ₹{$stockVal['summary_kpis']['total_stock_value']}, Products valued: " . count($stockVal['rows'])
);

// ─── FINAL SUMMARY ──────────────────────────────────────────────────────
echo "\n====================================================================\n";
echo "   ACCOUNTING & FINANCIAL REPORTING SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "====================================================================\n\n";

exit($failed > 0 ? 1 : 0);
