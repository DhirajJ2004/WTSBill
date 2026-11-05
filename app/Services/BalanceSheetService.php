<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;

/**
 * BalanceSheetService — Accountant-grade financial balance sheet engine.
 * Computes double-entry ledger balances, retained earnings, financial year context,
 * and multi-period comparative variance metrics.
 */
class BalanceSheetService
{
    /**
     * Compute full statement data for a given company, branch, and as-of date with comparison.
     *
     * @param int $companyId
     * @param int $branchId (0 for all branches or specific branch ID)
     * @param string $asOfDate YYYY-MM-DD
     * @param string $compare 'none'|'prev_month'|'prev_quarter'|'prev_year'
     * @return array
     */
    public function getStatement(int $companyId, int $branchId, string $asOfDate, string $compare = 'none'): array
    {
        // 1. Sanitize & validate dates
        $asOfDate = $this->sanitizeDate($asOfDate);
        $compare = in_array($compare, ['prev_month', 'prev_quarter', 'prev_year'], true) ? $compare : 'none';
        $compareDate = $this->calculateCompareDate($asOfDate, $compare);

        // 2. Resolve Financial Year & Dates formatting
        $fy = $this->resolveFinancialYear($asOfDate);
        $asOfFormatted = date('d F Y', strtotime($asOfDate));
        $compareFormatted = $compareDate ? date('d F Y', strtotime($compareDate)) : null;

        // 3. Fetch Company & Branch Context
        $company = Database::selectOne(
            "SELECT id, name, legal_name, gstin, pan, city, state, currency FROM companies WHERE id = ?",
            [$companyId]
        ) ?: [
            'name' => 'Wis Technosavvy Pvt Ltd',
            'legal_name' => 'Wis Technosavvy Pvt Ltd',
            'gstin' => '27AADCW7577N1ZE',
            'city' => 'Pune',
            'currency' => 'INR',
        ];

        $branchName = 'All Branches';
        if ($branchId > 0) {
            $branchRow = Database::selectOne("SELECT name FROM branches WHERE id = ? AND company_id = ?", [$branchId, $companyId]);
            if ($branchRow) {
                $branchName = (string)$branchRow['name'];
            }
        }

        // 4. Compute Current Period Balances
        $currentBalances = $this->computeLedgerBalances($companyId, $branchId, $asOfDate);

        // 5. Compute Previous Period Balances if comparison enabled
        $previousBalances = null;
        if ($compareDate !== null) {
            $previousBalances = $this->computeLedgerBalances($companyId, $branchId, $compareDate);
        }

        // 6. Build Statement Structure (Assets & Liabilities + Equity)
        $statement = $this->assembleStatement(
            $companyId,
            $currentBalances,
            $previousBalances,
            $compare !== 'none'
        );

        $difference = round($statement['assets']['total'] - $statement['liabilities_equity']['total'], 2);
        $balanced = abs($difference) <= 0.01;

        return [
            'success' => true,
            'as_of' => $asOfDate,
            'as_of_formatted' => $asOfFormatted,
            'compare' => $compare,
            'compare_date' => $compareDate,
            'compare_formatted' => $compareFormatted,
            'financial_year' => $fy,
            'company' => [
                'name' => $company['name'] ?: ($company['legal_name'] ?? 'Wis Technosavvy Pvt Ltd'),
                'legal_name' => $company['legal_name'] ?: $company['name'],
                'gstin' => $company['gstin'] ?: '27AADCW7577N1ZE',
                'city' => $company['city'] ?: 'Pune',
                'currency' => $company['currency'] ?: 'INR',
            ],
            'branch' => [
                'id' => $branchId,
                'name' => $branchName,
            ],
            'balanced' => $balanced,
            'difference' => $difference,
            'difference_formatted' => $this->formatCurrency(abs($difference)),
            'assets' => $statement['assets'],
            'liabilities_equity' => $statement['liabilities_equity'],
            'generated_at' => date('d-M-Y H:i:s T'),
        ];
    }

    /**
     * Compute raw account balances from chart_of_accounts and posted journal entries up to $upToDate.
     */
    private function computeLedgerBalances(int $companyId, int $branchId, string $upToDate): array
    {
        // Fetch all chart of accounts
        $accounts = Database::select(
            "SELECT id, account_code, account_name, account_type, nature, opening_balance 
             FROM chart_of_accounts 
             WHERE company_id = ? AND is_active = 1 
             ORDER BY account_code ASC",
            [$companyId]
        );

        // Fetch journal sums up to target date
        $journalParams = [$companyId, $upToDate];
        $branchClause = "";
        if ($branchId > 0) {
            $branchClause = " AND (je.branch_id = ? OR je.branch_id IS NULL)";
            $journalParams[] = $branchId;
        }

        $journalRows = Database::select(
            "SELECT 
                jel.account_id,
                COALESCE(SUM(jel.debit_amount), 0) AS total_debit,
                COALESCE(SUM(jel.credit_amount), 0) AS total_credit
             FROM journal_entry_lines jel
             INNER JOIN journal_entries je ON jel.journal_entry_id = je.id
             WHERE je.company_id = ? 
               AND je.status = 'POSTED' 
               AND je.entry_date <= ?
               {$branchClause}
             GROUP BY jel.account_id",
            $journalParams
        );

        $journalsByAccount = [];
        foreach ($journalRows as $jr) {
            $journalsByAccount[(int)$jr['account_id']] = [
                'debit' => (float)$jr['total_debit'],
                'credit' => (float)$jr['total_credit'],
            ];
        }

        $balances = [];
        $totalIncome = 0.0;
        $totalExpense = 0.0;

        foreach ($accounts as $acc) {
            $id = (int)$acc['id'];
            $type = strtoupper((string)$acc['account_type']);
            $open = (float)$acc['opening_balance'];
            $debit = $journalsByAccount[$id]['debit'] ?? 0.0;
            $credit = $journalsByAccount[$id]['credit'] ?? 0.0;

            // Double-entry calculation according to accounting nature
            if ($type === 'ASSET') {
                $bal = $open + $debit - $credit;
            } elseif ($type === 'LIABILITY' || $type === 'EQUITY') {
                $bal = $open + $credit - $debit;
            } elseif ($type === 'INCOME' || $type === 'REVENUE') {
                $bal = $open + $credit - $debit;
                $totalIncome += $bal;
            } elseif ($type === 'EXPENSE') {
                $bal = $open + $debit - $credit;
                $totalExpense += $bal;
            } else {
                $bal = $open + $debit - $credit;
            }

            $balances[$id] = [
                'id' => $id,
                'code' => $acc['account_code'],
                'name' => $acc['account_name'],
                'type' => $type,
                'balance' => round($bal, 2),
            ];
        }

        // P&L Net Profit = Income - Expense
        $netProfit = round($totalIncome - $totalExpense, 2);

        return [
            'accounts' => $balances,
            'net_profit' => $netProfit,
        ];
    }

    /**
     * Classify an account into standard Balance Sheet categories.
     */
    public function classifyAccountGroup(string $type, string $code, string $name): ?array
    {
        $code = trim($code);
        $nameLower = strtolower(trim($name));

        if ($type === 'ASSET') {
            if ($code === '100100' || str_contains($nameLower, 'cash')) {
                return ['side' => 'assets', 'key' => 'cash_in_hand', 'title' => 'Cash in Hand'];
            }
            if ($code === '100200' || str_contains($nameLower, 'bank') || str_contains($nameLower, 'hdfc') || str_contains($nameLower, 'sbi') || str_contains($nameLower, 'current account') || str_contains($nameLower, 'savings')) {
                return ['side' => 'assets', 'key' => 'bank_accounts', 'title' => 'Bank Accounts'];
            }
            if ($code === '100300' || str_contains($nameLower, 'receivable') || str_contains($nameLower, 'debtor')) {
                return ['side' => 'assets', 'key' => 'accounts_receivable', 'title' => 'Accounts Receivable'];
            }
            if ($code === '100400' || str_contains($nameLower, 'inventory') || str_contains($nameLower, 'stock')) {
                return ['side' => 'assets', 'key' => 'inventory_stock', 'title' => 'Inventory/Stock Value'];
            }
            if (str_contains($nameLower, 'fixed') || str_contains($nameLower, 'plant') || str_contains($nameLower, 'machinery') || str_contains($nameLower, 'equipment') || str_contains($nameLower, 'furniture')) {
                return ['side' => 'assets', 'key' => 'fixed_assets', 'title' => 'Fixed Assets'];
            }
            if (str_contains($nameLower, 'gst input') || str_contains($nameLower, 'advance') || str_contains($nameLower, 'prepaid') || str_starts_with($code, '1')) {
                return ['side' => 'assets', 'key' => 'other_current_assets', 'title' => 'Other Current Assets'];
            }
            if (!in_array($type, ['ASSET', 'LIABILITY', 'EQUITY'], true)) {
            return null;
        }
        }

        if ($type === 'LIABILITY') {
            if ($code === '200100' || str_contains($nameLower, 'creditor') || str_contains($nameLower, 'accounts payable') || str_contains($nameLower, 'trade payable')) {
                return ['side' => 'liabilities_equity', 'key' => 'accounts_payable', 'title' => 'Accounts Payable'];
            }
            if (str_contains($nameLower, 'outstanding') || str_contains($nameLower, 'accrued') || str_contains($nameLower, 'expense payable')) {
                return ['side' => 'liabilities_equity', 'key' => 'outstanding_expenses', 'title' => 'Outstanding Expenses'];
            }
            if (str_contains($nameLower, 'long') || str_contains($nameLower, 'term loan') || str_contains($nameLower, 'debenture')) {
                return ['side' => 'liabilities_equity', 'key' => 'long_term_liabilities', 'title' => 'Long-term Liabilities'];
            }
            if (str_contains($nameLower, 'loan') || str_contains($nameLower, 'borrowing') || str_contains($nameLower, 'credit line')) {
                return ['side' => 'liabilities_equity', 'key' => 'loans_borrowings', 'title' => 'Loans and Borrowings'];
            }
            return ['side' => 'liabilities_equity', 'key' => 'other_current_liabilities', 'title' => 'Other Current Liabilities'];
        }

        if ($type === 'EQUITY') {
            if ($code === '300100' || str_contains($nameLower, 'capital') || str_contains($nameLower, 'owner') || str_contains($nameLower, 'share')) {
                return ['side' => 'liabilities_equity', 'key' => 'owners_capital', 'title' => "Owner's Capital"];
            }
            if (str_contains($nameLower, 'retained') || str_contains($nameLower, 'reserve') || str_contains($nameLower, 'surplus')) {
                return ['side' => 'liabilities_equity', 'key' => 'retained_earnings', 'title' => 'Retained Earnings'];
            }
            return ['side' => 'liabilities_equity', 'key' => 'other_equity', 'title' => 'Other Equity Accounts'];
        }

        if (!in_array($type, ['ASSET', 'LIABILITY', 'EQUITY'], true)) {
            return null;
        }
    }

    /**
     * Assemble grouped balance sheet structure with comparison calculations.
     */
    private function assembleStatement(
        int $companyId,
        array $current,
        ?array $previous,
        bool $hasComparison
    ): array {
        $curAccounts = $current['accounts'] ?? [];
        $prevAccounts = $previous['accounts'] ?? [];
        $curNetProfit = (float)($current['net_profit'] ?? 0.0);
        $prevNetProfit = $previous ? (float)($previous['net_profit'] ?? 0.0) : 0.0;

        // --- ASSETS SIDE (Categorized per standards) ---
        $assetGroups = [
            'cash_in_hand' => [
                'title' => 'Cash in Hand',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'bank_accounts' => [
                'title' => 'Bank Accounts',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'accounts_receivable' => [
                'title' => 'Accounts Receivable',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'inventory_stock' => [
                'title' => 'Inventory/Stock Value',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'other_current_assets' => [
                'title' => 'Other Current Assets',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'fixed_assets' => [
                'title' => 'Fixed Assets',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'other_assets' => [
                'title' => 'Other Assets',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
        ];

        // --- LIABILITIES & EQUITY SIDE (Categorized per standards) ---
        $liabilityGroups = [
            'accounts_payable' => [
                'title' => 'Accounts Payable',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'outstanding_expenses' => [
                'title' => 'Outstanding Expenses',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'loans_borrowings' => [
                'title' => 'Loans and Borrowings',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'other_current_liabilities' => [
                'title' => 'Other Current Liabilities',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'long_term_liabilities' => [
                'title' => 'Long-term Liabilities',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'owners_capital' => [
                'title' => "Owner's Capital",
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'retained_earnings' => [
                'title' => 'Retained Earnings',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'current_period_pnl' => [
                'title' => 'Current Period Profit or Loss',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
            'other_equity' => [
                'title' => 'Other Equity Accounts',
                'accounts' => [],
                'subtotal' => 0.0,
                'previous_subtotal' => 0.0,
            ],
        ];

        foreach ($curAccounts as $id => $acc) {
            $type = $acc['type'];
            $curAmt = (float)$acc['balance'];
            $prevAmt = $hasComparison ? (float)($prevAccounts[$id]['balance'] ?? 0.0) : 0.0;

            $classification = $this->classifyAccountGroup($type, $acc['code'], $acc['name']);
            if ($classification === null) {
                continue;
            }

            $item = $this->buildLineItem($acc['name'], $id, $acc['code'], $curAmt, $prevAmt, $hasComparison);

            if ($classification['side'] === 'assets') {
                $k = $classification['key'];
                if (!isset($assetGroups[$k])) {
                    $k = 'other_assets';
                }
                $assetGroups[$k]['accounts'][] = $item;
                $assetGroups[$k]['subtotal'] += $curAmt;
                $assetGroups[$k]['previous_subtotal'] += $prevAmt;
            } else {
                $k = $classification['key'];
                if (!isset($liabilityGroups[$k])) {
                    $k = 'other_current_liabilities';
                }
                $liabilityGroups[$k]['accounts'][] = $item;
                $liabilityGroups[$k]['subtotal'] += $curAmt;
                $liabilityGroups[$k]['previous_subtotal'] += $prevAmt;
            }
        }

        // Add Current Period Profit or Loss to Equity
        $netProfitItem = $this->buildLineItem(
            'Current Period Profit or Loss',
            0,
            'P&L-NP',
            $curNetProfit,
            $prevNetProfit,
            $hasComparison
        );
        $liabilityGroups['current_period_pnl']['accounts'][] = $netProfitItem;
        $liabilityGroups['current_period_pnl']['subtotal'] += $curNetProfit;
        $liabilityGroups['current_period_pnl']['previous_subtotal'] += $prevNetProfit;

        // Clean empty groups — only include categories supported by existing chart of accounts
        $finalAssetGroups = [];
        $totalAssets = 0.0;
        $prevTotalAssets = 0.0;
        foreach ($assetGroups as $k => $grp) {
            if (!empty($grp['accounts'])) {
                $grp['subtotal'] = round($grp['subtotal'], 2);
                $grp['previous_subtotal'] = round($grp['previous_subtotal'], 2);
                $grp['subtotal_formatted'] = $this->formatCurrency($grp['subtotal']);
                $grp['previous_subtotal_formatted'] = $this->formatCurrency($grp['previous_subtotal']);
                $grp = array_merge($grp, $this->computeVariance($grp['subtotal'], $grp['previous_subtotal'], $hasComparison));
                $finalAssetGroups[] = $grp;
                $totalAssets += $grp['subtotal'];
                $prevTotalAssets += $grp['previous_subtotal'];
            }
        }

        $finalLiabilityGroups = [];
        $totalLiabEquity = 0.0;
        $prevTotalLiabEquity = 0.0;
        foreach ($liabilityGroups as $k => $grp) {
            if (!empty($grp['accounts'])) {
                $grp['subtotal'] = round($grp['subtotal'], 2);
                $grp['previous_subtotal'] = round($grp['previous_subtotal'], 2);
                $grp['subtotal_formatted'] = $this->formatCurrency($grp['subtotal']);
                $grp['previous_subtotal_formatted'] = $this->formatCurrency($grp['previous_subtotal']);
                $grp = array_merge($grp, $this->computeVariance($grp['subtotal'], $grp['previous_subtotal'], $hasComparison));
                $finalLiabilityGroups[] = $grp;
                $totalLiabEquity += $grp['subtotal'];
                $prevTotalLiabEquity += $grp['previous_subtotal'];
            }
        }

        $totalAssets = round($totalAssets, 2);
        $prevTotalAssets = round($prevTotalAssets, 2);
        $totalLiabEquity = round($totalLiabEquity, 2);
        $prevTotalLiabEquity = round($prevTotalLiabEquity, 2);

        $assetsVariance = $this->computeVariance($totalAssets, $prevTotalAssets, $hasComparison);
        $liabEquityVariance = $this->computeVariance($totalLiabEquity, $prevTotalLiabEquity, $hasComparison);

        return [
            'assets' => [
                'groups' => $finalAssetGroups,
                'total' => $totalAssets,
                'previous_total' => $prevTotalAssets,
                'total_formatted' => $this->formatCurrency($totalAssets),
                'previous_total_formatted' => $this->formatCurrency($prevTotalAssets),
                'change_amount' => $assetsVariance['change_amount'],
                'change_amount_formatted' => $assetsVariance['change_amount_formatted'],
                'change_percent' => $assetsVariance['change_percent'],
                'change_direction' => $assetsVariance['change_direction'],
            ],
            'liabilities_equity' => [
                'groups' => $finalLiabilityGroups,
                'total' => $totalLiabEquity,
                'previous_total' => $prevTotalLiabEquity,
                'total_formatted' => $this->formatCurrency($totalLiabEquity),
                'previous_total_formatted' => $this->formatCurrency($prevTotalLiabEquity),
                'change_amount' => $liabEquityVariance['change_amount'],
                'change_amount_formatted' => $liabEquityVariance['change_amount_formatted'],
                'change_percent' => $liabEquityVariance['change_percent'],
                'change_direction' => $liabEquityVariance['change_direction'],
            ],
        ];
    }

    /**
     * Build an individual account line item with formatting and variance.
     */
    private function buildLineItem(
        string $name,
        int $id,
        string $code,
        float $curAmt,
        float $prevAmt,
        bool $hasComparison
    ): array {
        $curAmt = round($curAmt, 2);
        $prevAmt = round($prevAmt, 2);
        $variance = $this->computeVariance($curAmt, $prevAmt, $hasComparison);

        return [
            'id' => $id,
            'account_id' => $id,
            'code' => $code,
            'name' => $name,
            'amount' => $curAmt,
            'amount_formatted' => $this->formatCurrency($curAmt),
            'previous_amount' => $prevAmt,
            'previous_amount_formatted' => $this->formatCurrency($prevAmt),
            'change_amount' => $variance['change_amount'],
            'change_amount_formatted' => $variance['change_amount_formatted'],
            'change_percent' => $variance['change_percent'],
            'change_direction' => $variance['change_direction'],
        ];
    }

    /**
     * Compute variance metrics between current and previous amounts.
     */
    private function computeVariance(float $cur, float $prev, bool $hasComparison): array
    {
        if (!$hasComparison) {
            return [
                'change_amount' => 0.0,
                'change_amount_formatted' => '-',
                'change_percent' => 0.0,
                'change_direction' => 'none',
            ];
        }

        $change = round($cur - $prev, 2);
        $direction = 'neutral';
        if ($change > 0.001) {
            $direction = 'up';
        } elseif ($change < -0.001) {
            $direction = 'down';
        }

        $percent = 0.0;
        if (abs($prev) > 0.001) {
            $percent = round(($change / abs($prev)) * 100, 1);
        } elseif (abs($cur) > 0.001) {
            $percent = 100.0;
        }

        return [
            'change_amount' => $change,
            'change_amount_formatted' => ($change >= 0 ? '+' : '') . $this->formatCurrency($change),
            'change_percent' => $percent,
            'change_direction' => $direction,
        ];
    }

    /**
     * Format a floating point number according to Indian Currency notation.
     * Negative numbers are formatted in parentheses: (1,84,500.00).
     */
    public function formatCurrency(float $number): string
    {
        $isNegative = $number < -0.001;
        $abs = abs($number);

        $parts = explode('.', sprintf('%.2f', $abs));
        $integerPart = $parts[0];
        $decimalPart = $parts[1] ?? '00';

        // Indian numbering grouping (last 3, then groups of 2)
        if (strlen($integerPart) > 3) {
            $lastThree = substr($integerPart, -3);
            $remaining = substr($integerPart, 0, -3);
            $groups = [];
            while (strlen($remaining) > 2) {
                $groups[] = substr($remaining, -2);
                $remaining = substr($remaining, 0, -2);
            }
            if ($remaining !== '') {
                $groups[] = $remaining;
            }
            $formattedInteger = implode(',', array_reverse($groups)) . ',' . $lastThree;
        } else {
            $formattedInteger = $integerPart;
        }

        $formatted = '₹ ' . $formattedInteger . '.' . $decimalPart;
        return $isNegative ? '(' . $formatted . ')' : $formatted;
    }

    /**
     * Determine comparison target date from base date and preset.
     */
    public function calculateCompareDate(string $asOfDate, string $compare): ?string
    {
        $time = strtotime($asOfDate);
        if ($time === false) {
            return null;
        }

        return match ($compare) {
            'prev_month' => date('Y-m-d', strtotime('-1 month', $time)),
            'prev_quarter' => date('Y-m-d', strtotime('-3 months', $time)),
            'prev_year' => date('Y-m-d', strtotime('-1 year', $time)),
            default => null,
        };
    }

    /**
     * Resolve Indian Financial Year (e.g. FY 2026-27).
     */
    public function resolveFinancialYear(string $date): string
    {
        $time = strtotime($date);
        if ($time === false) {
            $time = time();
        }
        $y = (int)date('Y', $time);
        $m = (int)date('n', $time);

        if ($m >= 4) {
            $nextY = ($y + 1) % 100;
            return sprintf('FY %d-%02d', $y, $nextY);
        } else {
            $curY = $y % 100;
            return sprintf('FY %d-%02d', $y - 1, $curY);
        }
    }

    /**
     * Validate and sanitize date string to YYYY-MM-DD.
     */
    public function sanitizeDate(?string $date): string
    {
        if (empty($date) || !is_string($date)) {
            return date('Y-m-d');
        }

        $trimmed = trim($date);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            $t = strtotime($trimmed);
            if ($t !== false && date('Y-m-d', $t) === $trimmed) {
                return $trimmed;
            }
        }

        return date('Y-m-d');
    }
}
