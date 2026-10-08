<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\Payment;
use App\Models\JournalLine;

class ReconciliationService
{
    /**
     * Run systemic double-entry and subledger reconciliation health check.
     */
    public static function runReconciliationCheck(int $companyId, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?: date('Y-m-d');
        AccountService::ensureDefaultAccounts($companyId);

        // 1. Trial Balance Check
        $tb = TrialBalanceService::getTrialBalance($companyId, $asOfDate);
        $tbPassed = $tb['is_balanced'];

        // 2. Accounts Receivable Subledger Reconciliation
        $recAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');
        $recLedger = LedgerService::getAccountLedger($companyId, $recAcc->id, '2026-01-01', $asOfDate);
        $recControlBalance = round(floatval($recLedger['closing_balance']), 2);

        $customerDues = Invoice::where('company_id', $companyId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT')
            ->where('invoice_date', '<=', $asOfDate)
            ->sum('amount_due');
        $recSubledgerSum = round(floatval($customerDues), 2);
        $recDelta = round(abs($recControlBalance - $recSubledgerSum), 2);

        // 3. Accounts Payable Subledger Reconciliation
        $payAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_PAYABLE');
        $payLedger = LedgerService::getAccountLedger($companyId, $payAcc->id, '2026-01-01', $asOfDate);
        $payControlBalance = round(floatval($payLedger['closing_balance']), 2);

        $supplierDues = Purchase::where('company_id', $companyId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT')
            ->where('purchase_date', '<=', $asOfDate)
            ->sum('amount_due');
        $paySubledgerSum = round(floatval($supplierDues), 2);
        $payDelta = round(abs($payControlBalance - $paySubledgerSum), 2);

        // 4. Inventory Valuation Reconciliation
        $invAcc = AccountService::getMappedAccount($companyId, 'INVENTORY_ASSET');
        $invLedger = LedgerService::getAccountLedger($companyId, $invAcc->id, '2026-01-01', $asOfDate);
        $invControlBalance = round(floatval($invLedger['closing_balance']), 2);

        $totalValuation = 0.0;
        $products = Product::where('company_id', $companyId)->get();
        foreach ($products as $p) {
            $totalValuation += (floatval($p->stock_quantity) * floatval($p->purchase_price));
        }
        $invValuationSum = round($totalValuation, 2);

        // 5. Balance Sheet Balancing
        $bs = BalanceSheetService::getBalanceSheet($companyId, $asOfDate);
        $bsPassed = $bs['is_balanced'];

        return [
            'as_of_date' => $asOfDate,
            'all_healthy' => $tbPassed && $bsPassed,
            'checks' => [
                'trial_balance' => [
                    'status' => $tbPassed ? 'MATCH' : 'MISMATCH',
                    'total_debit' => $tb['total_debit'],
                    'total_credit' => $tb['total_credit'],
                    'difference' => $tb['difference'],
                ],
                'balance_sheet' => [
                    'status' => $bsPassed ? 'MATCH' : 'MISMATCH',
                    'total_assets' => $bs['assets']['total_assets'],
                    'total_liabilities_equity' => $bs['total_liabilities_and_equity'],
                    'difference' => $bs['difference'],
                ],
                'accounts_receivable' => [
                    'control_account_balance' => $recControlBalance,
                    'subledger_unpaid_invoices' => $recSubledgerSum,
                    'difference' => $recDelta,
                ],
                'accounts_payable' => [
                    'control_account_balance' => $payControlBalance,
                    'subledger_unpaid_bills' => $paySubledgerSum,
                    'difference' => $payDelta,
                ],
                'inventory_valuation' => [
                    'inventory_ledger_balance' => $invControlBalance,
                    'stock_valuation_summary' => $invValuationSum,
                ],
            ],
        ];
    }
}
