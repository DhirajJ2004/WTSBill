<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\JournalEntry;

class CashFlowService
{
    /**
     * Generate Cash Flow statement by analyzing cash & bank ledger movements.
     */
    public static function getCashFlow(int $companyId, ?string $fromDate = null, ?string $toDate = null, ?int $branchId = null): array
    {
        AccountService::ensureDefaultAccounts($companyId);
        $fromDate = $fromDate ?: date('Y-01-01');
        $toDate = $toDate ?: date('Y-m-d');

        $moneyAccounts = ChartOfAccount::where('company_id', $companyId)
            ->whereIn('account_subtype', ['CASH', 'BANK'])
            ->pluck('id')
            ->toArray();

        // 1. Calculate Opening Cash & Bank prior to $fromDate
        $priorDebits = JournalLine::where('journal_lines.company_id', $companyId)
            ->whereIn('journal_lines.account_id', $moneyAccounts)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['POSTED', 'REVERSED'])
            ->where('journal_entries.entry_date', '<', $fromDate)
            ->sum('journal_lines.debit');

        $priorCredits = JournalLine::where('journal_lines.company_id', $companyId)
            ->whereIn('journal_lines.account_id', $moneyAccounts)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['POSTED', 'REVERSED'])
            ->where('journal_entries.entry_date', '<', $fromDate)
            ->sum('journal_lines.credit');

        $openingCash = round(floatval($priorDebits) - floatval($priorCredits), 2);

        // 2. Fetch all period transactions involving cash/bank
        $lines = JournalLine::where('journal_lines.company_id', $companyId)
            ->whereIn('journal_lines.account_id', $moneyAccounts)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['POSTED', 'REVERSED'])
            ->whereBetween('journal_entries.entry_date', [$fromDate, $toDate])
            ->select('journal_lines.*', 'journal_entries.entry_type', 'journal_entries.journal_number', 'journal_entries.entry_date', 'journal_entries.description as journal_desc')
            ->get();

        $operatingInflows = 0.0;
        $operatingOutflows = 0.0;
        $investingInflows = 0.0;
        $investingOutflows = 0.0;
        $financingInflows = 0.0;
        $financingOutflows = 0.0;

        $operatingItems = [];
        $investingItems = [];
        $financingItems = [];

        foreach ($lines as $line) {
            $deb = floatval($line->debit);
            $cred = floatval($line->credit);
            $type = strtoupper($line->entry_type ?? 'MANUAL');

            // Classify by transaction type
            if (in_array($type, ['RECEIPT', 'CUSTOMER_PAYMENT', 'SALE'])) {
                $operatingInflows += $deb;
                $operatingItems[] = ['desc' => "Customer Receipt #{$line->journal_number}", 'amount' => $deb];
            } elseif (in_array($type, ['PAYMENT', 'SUPPLIER_PAYMENT', 'PURCHASE'])) {
                $operatingOutflows += $cred;
                $operatingItems[] = ['desc' => "Supplier Payment #{$line->journal_number}", 'amount' => -$cred];
            } elseif ($type === 'EXPENSE') {
                $operatingOutflows += $cred;
                $operatingItems[] = ['desc' => "Expense #{$line->journal_number}", 'amount' => -$cred];
            } elseif ($type === 'REFUND') {
                if ($deb > 0) {
                    $operatingInflows += $deb;
                    $operatingItems[] = ['desc' => "Supplier Refund #{$line->journal_number}", 'amount' => $deb];
                } else {
                    $operatingOutflows += $cred;
                    $operatingItems[] = ['desc' => "Customer Refund #{$line->journal_number}", 'amount' => -$cred];
                }
            } elseif ($type === 'FIXED_ASSET_PURCHASE' || str_contains(strtolower($line->description ?? ''), 'asset')) {
                if ($deb > 0) {
                    $investingInflows += $deb;
                    $investingItems[] = ['desc' => "Asset Sale #{$line->journal_number}", 'amount' => $deb];
                } else {
                    $investingOutflows += $cred;
                    $investingItems[] = ['desc' => "Asset Purchase #{$line->journal_number}", 'amount' => -$cred];
                }
            } elseif (in_array($type, ['CAPITAL', 'LOAN', 'DRAWINGS']) || str_contains(strtolower($line->description ?? ''), 'capital') || str_contains(strtolower($line->description ?? ''), 'drawing')) {
                if ($deb > 0) {
                    $financingInflows += $deb;
                    $financingItems[] = ['desc' => "Capital/Loan Inflow #{$line->journal_number}", 'amount' => $deb];
                } else {
                    $financingOutflows += $cred;
                    $financingItems[] = ['desc' => "Drawings/Loan Outflow #{$line->journal_number}", 'amount' => -$cred];
                }
            } else {
                // Default fallback to operating
                if ($deb > 0) {
                    $operatingInflows += $deb;
                    $operatingItems[] = ['desc' => $line->description ?: "Cash Inflow #{$line->journal_number}", 'amount' => $deb];
                } else {
                    $operatingOutflows += $cred;
                    $operatingItems[] = ['desc' => $line->description ?: "Cash Outflow #{$line->journal_number}", 'amount' => -$cred];
                }
            }
        }

        $netOperating = round($operatingInflows - $operatingOutflows, 2);
        $netInvesting = round($investingInflows - $investingOutflows, 2);
        $netFinancing = round($financingInflows - $financingOutflows, 2);
        $netMovement = round($netOperating + $netInvesting + $netFinancing, 2);
        $closingCash = round($openingCash + $netMovement, 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'opening_cash_balance' => $openingCash,
            'operating_activities' => [
                'inflows' => round($operatingInflows, 2),
                'outflows' => round($operatingOutflows, 2),
                'net' => $netOperating,
                'items' => $operatingItems,
            ],
            'investing_activities' => [
                'inflows' => round($investingInflows, 2),
                'outflows' => round($investingOutflows, 2),
                'net' => $netInvesting,
                'items' => $investingItems,
            ],
            'financing_activities' => [
                'inflows' => round($financingInflows, 2),
                'outflows' => round($financingOutflows, 2),
                'net' => $netFinancing,
                'items' => $financingItems,
            ],
            'net_cash_movement' => $netMovement,
            'closing_cash_balance' => $closingCash,
        ];
    }
}
