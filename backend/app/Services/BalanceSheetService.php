<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;

class BalanceSheetService
{
    /**
     * Generate authentic Balance Sheet from posted ledger balances and integrated P&L net income.
     */
    public static function getBalanceSheet(int $companyId, ?string $asOfDate = null, ?int $branchId = null, ?string $financialYear = null): array
    {
        AccountService::ensureDefaultAccounts($companyId);
        $asOfDate = $asOfDate ?: date('Y-m-d');

        // Pre-query all journal line sums up to asOfDate grouped by account_id
        $baseLinesQuery = JournalLine::where('journal_lines.company_id', $companyId)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['POSTED', 'REVERSED'])
            ->where('journal_entries.entry_date', '<=', $asOfDate);

        if ($branchId) {
            $baseLinesQuery->where('journal_lines.branch_id', $branchId);
        }

        $lineSumsByAccount = $baseLinesQuery->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) as deb, SUM(journal_lines.credit) as cred')
            ->groupBy('journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        // Helper to sum cumulative balance as of $asOfDate for given accounts
        $computeAccountsNet = function ($accounts, string $normalNature = 'DEBIT') use ($lineSumsByAccount) {
            $total = 0.0;
            $items = [];

            foreach ($accounts as $acc) {
                $sums = $lineSumsByAccount->get($acc->id);
                $deb = floatval($sums ? $sums->deb : 0);
                $cred = floatval($sums ? $sums->cred : 0);

                $initialOpening = floatval($acc->opening_balance);
                $initialType = strtoupper($acc->opening_balance_type ?: ($acc->nature ?: ($normalNature === 'DEBIT' ? 'DEBIT' : 'CREDIT')));

                if ($initialType === 'DEBIT') {
                    $deb += $initialOpening;
                } else {
                    $cred += $initialOpening;
                }

                $net = ($normalNature === 'DEBIT') ? round($deb - $cred, 2) : round($cred - $deb, 2);

                if (abs($net) > 0.001) {
                    $items[] = [
                        'account_id' => $acc->id,
                        'account_code' => $acc->account_code,
                        'account_name' => $acc->account_name,
                        'amount' => $net,
                    ];
                    $total += $net;
                }
            }

            return ['total' => round($total, 2), 'items' => $items];
        };

        // All active accounts for the company
        $allAccounts = ChartOfAccount::where('company_id', $companyId)->get();

        // 1. ASSETS
        $assetAccounts = $allAccounts->filter(function ($a) {
            return strtoupper($a->account_type) === 'ASSET' || in_array(strtoupper($a->account_subtype ?? ''), ['CASH', 'BANK', 'ACCOUNTS_RECEIVABLE', 'INVENTORY', 'TAX_RECEIVABLE', 'FIXED_ASSETS', 'OTHER_CURRENT_ASSET', 'OTHER_ASSETS', 'SUPPLIER_ADVANCE']);
        });

        $cashAccs = $assetAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['CASH']));
        $bankAccs = $assetAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['BANK']));
        $recAccs = $assetAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['ACCOUNTS_RECEIVABLE']));
        $invAccs = $assetAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['INVENTORY']));
        $taxAccs = $assetAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['TAX_RECEIVABLE']));
        $fixedAccs = $assetAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['FIXED_ASSETS', 'OTHER_ASSETS']));
        
        $categorizedAssetIds = array_merge(
            $cashAccs->pluck('id')->all(),
            $bankAccs->pluck('id')->all(),
            $recAccs->pluck('id')->all(),
            $invAccs->pluck('id')->all(),
            $taxAccs->pluck('id')->all(),
            $fixedAccs->pluck('id')->all()
        );
        $otherAssetAccs = $assetAccounts->filter(fn($a) => !in_array($a->id, $categorizedAssetIds));

        $cashAccounts = $computeAccountsNet($cashAccs, 'DEBIT');
        $bankAccounts = $computeAccountsNet($bankAccs, 'DEBIT');
        $receivables = $computeAccountsNet($recAccs, 'DEBIT');
        $inventory = $computeAccountsNet($invAccs, 'DEBIT');
        $inputTaxes = $computeAccountsNet($taxAccs, 'DEBIT');
        $otherCurrentAssets = $computeAccountsNet($otherAssetAccs, 'DEBIT');
        $fixedAssets = $computeAccountsNet($fixedAccs, 'DEBIT');

        $totalCurrentAssets = round(
            $cashAccounts['total'] + $bankAccounts['total'] + $receivables['total'] +
            $inventory['total'] + $inputTaxes['total'] + $otherCurrentAssets['total'],
            2
        );
        $totalNonCurrentAssets = $fixedAssets['total'];
        $totalAssets = round($totalCurrentAssets + $totalNonCurrentAssets, 2);

        // 2. LIABILITIES
        $liabilityAccounts = $allAccounts->filter(function ($a) {
            return strtoupper($a->account_type) === 'LIABILITY' || in_array(strtoupper($a->account_subtype ?? ''), ['ACCOUNTS_PAYABLE', 'GST_PAYABLE', 'TAX_PAYABLE', 'OTHER_CURRENT_LIABILITY', 'CUSTOMER_ADVANCE', 'LOANS', 'OTHER_LIABILITIES']);
        });

        $payableAccs = $liabilityAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['ACCOUNTS_PAYABLE']));
        $gstPayAccs = $liabilityAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['GST_PAYABLE', 'TAX_PAYABLE']));
        $loanAccs = $liabilityAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['LOANS', 'OTHER_LIABILITIES']));
        
        $categorizedLiabIds = array_merge(
            $payableAccs->pluck('id')->all(),
            $gstPayAccs->pluck('id')->all(),
            $loanAccs->pluck('id')->all()
        );
        $otherLiabAccs = $liabilityAccounts->filter(fn($a) => !in_array($a->id, $categorizedLiabIds));

        $payables = $computeAccountsNet($payableAccs, 'CREDIT');
        $gstPayable = $computeAccountsNet($gstPayAccs, 'CREDIT');
        $customerAdvances = $computeAccountsNet($otherLiabAccs, 'CREDIT');
        $loans = $computeAccountsNet($loanAccs, 'CREDIT');

        $totalCurrentLiabilities = round($payables['total'] + $gstPayable['total'] + $customerAdvances['total'], 2);
        $totalLongTermLiabilities = $loans['total'];
        $totalLiabilities = round($totalCurrentLiabilities + $totalLongTermLiabilities, 2);

        // 3. EQUITY
        $equityAccounts = $allAccounts->filter(function ($a) {
            return strtoupper($a->account_type) === 'EQUITY' || in_array(strtoupper($a->account_subtype ?? ''), ['CAPITAL', 'RETAINED_EARNINGS', 'DRAWINGS']);
        });

        $retainedAccs = $equityAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['RETAINED_EARNINGS']));
        $drawingAccs = $equityAccounts->filter(fn($a) => in_array(strtoupper($a->account_subtype ?? ''), ['DRAWINGS']));
        $capitalAccs = $equityAccounts->filter(fn($a) => !in_array($a->id, array_merge($retainedAccs->pluck('id')->all(), $drawingAccs->pluck('id')->all())));

        $capital = $computeAccountsNet($capitalAccs, 'CREDIT');
        $retainedEarnings = $computeAccountsNet($retainedAccs, 'CREDIT');
        $drawings = $computeAccountsNet($drawingAccs, 'DEBIT');

        // Current period profit from P&L up to $asOfDate
        $fyStart = date('Y-04-01');
        if (date('m') < 4) {
            $fyStart = (date('Y') - 1) . '-04-01';
        }
        $pnl = ProfitLossService::getProfitAndLoss($companyId, $fyStart, $asOfDate, $branchId, $financialYear);
        $currentPeriodProfit = round(floatval($pnl['net_profit'] ?? 0), 2);

        $totalEquity = round($capital['total'] + $retainedEarnings['total'] - $drawings['total'] + $currentPeriodProfit, 2);

        // 4. BALANCING RECONCILIATION
        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);
        $difference = round(abs($totalAssets - $totalLiabilitiesAndEquity), 2);
        $isBalanced = ($difference <= 0.05);

        return [
            'as_of_date' => $asOfDate,
            'financial_year' => $financialYear ?: '2026-27',
            'is_balanced' => $isBalanced,
            'difference' => $difference,
            'assets' => [
                'current_assets' => [
                    'cash' => $cashAccounts,
                    'bank' => $bankAccounts,
                    'receivables' => $receivables,
                    'inventory' => $inventory,
                    'input_tax_credit' => $inputTaxes,
                    'other_current_assets' => $otherCurrentAssets,
                    'total' => $totalCurrentAssets,
                ],
                'non_current_assets' => [
                    'fixed_assets' => $fixedAssets,
                    'total' => $totalNonCurrentAssets,
                ],
                'total_assets' => $totalAssets,
            ],
            'liabilities' => [
                'current_liabilities' => [
                    'payables' => $payables,
                    'gst_payable' => $gstPayable,
                    'customer_advances' => $customerAdvances,
                    'total' => $totalCurrentLiabilities,
                ],
                'long_term_liabilities' => [
                    'loans' => $loans,
                    'total' => $totalLongTermLiabilities,
                ],
                'total_liabilities' => $totalLiabilities,
            ],
            'equity' => [
                'capital' => $capital,
                'retained_earnings' => $retainedEarnings,
                'drawings' => $drawings,
                'current_period_profit' => $currentPeriodProfit,
                'total_equity' => $totalEquity,
            ],
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
        ];
    }
}
