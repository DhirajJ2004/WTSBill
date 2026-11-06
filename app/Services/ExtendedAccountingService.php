<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Session;

/**
 * Extended Accounting Reports Business Service.
 * Produces double-entry Trial Balance statements, Cash Book ledgers,
 * Bank Book registers, and standard Cash Flow Statements.
 */
class ExtendedAccountingService
{
    /**
     * Generate double-entry Trial Balance statement.
     */
    public function getTrialBalance(int $companyId, array $filters = []): array
    {
        $asOfDate = !empty($filters['as_of_date']) ? (string)$filters['as_of_date'] : date('Y-m-d');
        $branchId = (int)($filters['branch_id'] ?? 0);

        // Fetch all chart of accounts
        $accounts = Database::select(
            "SELECT id, account_code, account_name, account_type, account_subtype, nature, opening_balance, opening_balance_type 
             FROM chart_of_accounts 
             WHERE company_id = ? AND deleted_at IS NULL AND is_active = 1 
             ORDER BY account_code ASC",
            [$companyId]
        );

        $branchSql = "";
        $branchParams = [];
        if ($branchId > 0) {
            $branchSql = " AND (je.branch_id = ? OR je.branch_id IS NULL)";
            $branchParams = [$branchId];
        }

        $groups = [
            'ASSET' => ['title' => 'Assets', 'accounts' => [], 'subtotal_debit' => 0.0, 'subtotal_credit' => 0.0],
            'LIABILITY' => ['title' => 'Liabilities', 'accounts' => [], 'subtotal_debit' => 0.0, 'subtotal_credit' => 0.0],
            'EQUITY' => ['title' => 'Equity', 'accounts' => [], 'subtotal_debit' => 0.0, 'subtotal_credit' => 0.0],
            'INCOME' => ['title' => 'Revenue & Income', 'accounts' => [], 'subtotal_debit' => 0.0, 'subtotal_credit' => 0.0],
            'EXPENSE' => ['title' => 'Expenses', 'accounts' => [], 'subtotal_debit' => 0.0, 'subtotal_credit' => 0.0],
        ];

        $totalDebits = 0.0;
        $totalCredits = 0.0;

        foreach ($accounts as $acc) {
            $accId = (int)$acc['id'];
            $type = strtoupper((string)$acc['account_type']);
            if (!isset($groups[$type])) {
                $type = 'ASSET';
            }

            // Calculate transactions from journal_entry_lines up to asOfDate
            $txParams = array_merge([$accId, $companyId, $asOfDate], $branchParams);
            $tx = Database::selectOne(
                "SELECT 
                    COALESCE(SUM(jel.debit_amount), 0) AS total_dr,
                    COALESCE(SUM(jel.credit_amount), 0) AS total_cr
                 FROM journal_entry_lines jel
                 INNER JOIN journal_entries je ON jel.journal_entry_id = je.id
                 WHERE jel.account_id = ? 
                   AND je.company_id = ? 
                   AND je.entry_date <= ?
                   AND je.status = 'POSTED'
                   AND je.deleted_at IS NULL
                   {$branchSql}",
                $txParams
            );

            $dr = (float)($tx['total_dr'] ?? 0);
            $cr = (float)($tx['total_cr'] ?? 0);

            // Factor in opening balance according to accounting nature
            $openBal = (float)($acc['opening_balance'] ?? 0);
            if ($openBal > 0) {
                if (in_array($type, ['LIABILITY', 'EQUITY', 'INCOME', 'REVENUE'], true)) {
                    $cr += $openBal;
                } else {
                    $dr += $openBal;
                }
            }

            // Net balance for trial balance
            $debitBalance = 0.0;
            $creditBalance = 0.0;

            if ($dr > $cr) {
                $debitBalance = round($dr - $cr, 2);
            } elseif ($cr > $dr) {
                $creditBalance = round($cr - $dr, 2);
            }

            $accRow = [
                'id' => $accId,
                'account_code' => $acc['account_code'],
                'account_name' => $acc['account_name'],
                'account_type' => $type,
                'nature' => $acc['nature'],
                'total_dr_tx' => $dr,
                'total_cr_tx' => $cr,
                'debit_balance' => $debitBalance,
                'credit_balance' => $creditBalance,
            ];

            $groups[$type]['accounts'][] = $accRow;
            $groups[$type]['subtotal_debit'] += $debitBalance;
            $groups[$type]['subtotal_credit'] += $creditBalance;

            $totalDebits += $debitBalance;
            $totalCredits += $creditBalance;
        }

        $totalDebits = round($totalDebits, 2);
        $totalCredits = round($totalCredits, 2);
        $diff = round(abs($totalDebits - $totalCredits), 2);
        $isBalanced = ($diff <= 0.01);

        return [
            'as_of_date' => $asOfDate,
            'branch_id' => $branchId,
            'groups' => $groups,
            'summary' => [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'difference' => $diff,
                'is_balanced' => $isBalanced,
                'total_accounts' => count($accounts),
            ],
        ];
    }

    /**
     * Export Trial Balance as CSV stream.
     */
    public function exportTrialBalanceCsv(int $companyId, array $filters = []): string
    {
        $tb = $this->getTrialBalance($companyId, $filters);
        $asOf = $tb['as_of_date'];

        $out = fopen('php://memory', 'w+');
        fputcsv($out, ['TRIAL BALANCE STATEMENT', "As of: {$asOf}"]);
        fputcsv($out, ['Account Code', 'Account Name', 'Account Group', 'Debit Balance (INR)', 'Credit Balance (INR)']);

        foreach ($tb['groups'] as $groupKey => $group) {
            foreach ($group['accounts'] as $acc) {
                if ($acc['debit_balance'] == 0 && $acc['credit_balance'] == 0) {
                    continue; // Skip zero accounts in standard export
                }
                fputcsv($out, [
                    $acc['account_code'],
                    $acc['account_name'],
                    $group['title'],
                    number_format($acc['debit_balance'], 2, '.', ''),
                    number_format($acc['credit_balance'], 2, '.', ''),
                ]);
            }
            fputcsv($out, [
                '',
                "SUBTOTAL {$group['title']}",
                '',
                number_format($group['subtotal_debit'], 2, '.', ''),
                number_format($group['subtotal_credit'], 2, '.', ''),
            ]);
        }

        $s = $tb['summary'];
        fputcsv($out, [
            'TOTALS',
            $s['is_balanced'] ? 'BALANCED' : 'OUT OF BALANCE',
            '',
            number_format($s['total_debits'], 2, '.', ''),
            number_format($s['total_credits'], 2, '.', ''),
        ]);

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv ?: '';
    }

    /**
     * Generate Cash Book ledger.
     */
    public function getCashBook(int $companyId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? (string)$filters['from_date'] : date('Y-m-01');
        $toDate = !empty($filters['to_date']) ? (string)$filters['to_date'] : date('Y-m-d');
        $branchId = (int)($filters['branch_id'] ?? 0);

        // Find Cash in Hand account
        $cashAcc = Database::selectOne(
            "SELECT id, account_code, account_name, opening_balance 
             FROM chart_of_accounts 
             WHERE company_id = ? AND (account_code = '100100' OR account_name LIKE '%Cash in Hand%') AND deleted_at IS NULL LIMIT 1",
            [$companyId]
        );

        $cashAccId = (int)($cashAcc['id'] ?? 1);

        $branchSql = "";
        $branchParams = [];
        if ($branchId > 0) {
            $branchSql = " AND (p.branch_id = ? OR p.branch_id IS NULL)";
            $branchParams = [$branchId];
        }

        // Opening Cash Balance prior to fromDate
        $openParams = array_merge([$companyId, $fromDate], $branchParams);
        $priorInflow = Database::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total_in 
             FROM payments p 
             WHERE p.company_id = ? AND p.payment_mode = 'Cash' AND p.payment_type = 'RECEIPT' AND p.payment_date < ? AND p.deleted_at IS NULL {$branchSql}",
            $openParams
        );
        $priorOutflow = Database::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total_out 
             FROM payments p 
             WHERE p.company_id = ? AND p.payment_mode = 'Cash' AND p.payment_type = 'PAYMENT' AND p.payment_date < ? AND p.deleted_at IS NULL {$branchSql}",
            $openParams
        );
        $initialAccOpening = (float)($cashAcc['opening_balance'] ?? 0);
        $openingBalance = round($initialAccOpening + (float)($priorInflow['total_in'] ?? 0) - (float)($priorOutflow['total_out'] ?? 0), 2);

        // Period Cash Transactions
        $periodParams = array_merge([$companyId, $fromDate, $toDate], $branchParams);
        $txList = Database::select(
            "SELECT 
                p.id,
                p.payment_number,
                p.payment_date,
                p.party_type,
                p.party_id,
                p.payment_type,
                p.amount,
                p.reference_no,
                p.notes,
                b.name AS branch_name,
                CASE 
                    WHEN p.party_type = 'CUSTOMER' THEN (SELECT name FROM customers WHERE id = p.party_id)
                    WHEN p.party_type = 'SUPPLIER' THEN (SELECT name FROM suppliers WHERE id = p.party_id)
                    ELSE 'General Cash'
                END AS party_name
             FROM payments p
             LEFT JOIN branches b ON p.branch_id = b.id
             WHERE p.company_id = ? 
               AND p.payment_mode = 'Cash' 
               AND p.payment_date BETWEEN ? AND ? 
               AND p.deleted_at IS NULL 
               {$branchSql}
             ORDER BY p.payment_date ASC, p.id ASC",
            $periodParams
        );

        $running = $openingBalance;
        $totalReceipts = 0.0;
        $totalPayments = 0.0;
        $entries = [];

        foreach ($txList as $row) {
            $amt = (float)$row['amount'];
            $isReceipt = ($row['payment_type'] === 'RECEIPT');

            $in = $isReceipt ? $amt : 0.0;
            $out = !$isReceipt ? $amt : 0.0;

            $totalReceipts += $in;
            $totalPayments += $out;

            $running = round($running + $in - $out, 2);

            $row['receipt_amount'] = $in;
            $row['payment_amount'] = $out;
            $row['running_balance'] = $running;
            $entries[] = $row;
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'account' => $cashAcc,
            'opening_balance' => $openingBalance,
            'total_receipts' => round($totalReceipts, 2),
            'total_payments' => round($totalPayments, 2),
            'closing_balance' => $running,
            'entries' => $entries,
        ];
    }

    /**
     * Generate Bank Book register.
     */
    public function getBankBook(int $companyId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? (string)$filters['from_date'] : date('Y-m-01');
        $toDate = !empty($filters['to_date']) ? (string)$filters['to_date'] : date('Y-m-d');
        $bankAccountId = (int)($filters['bank_account_id'] ?? 0);
        $branchId = (int)($filters['branch_id'] ?? 0);

        // Fetch bank accounts
        $bankAccounts = Database::select(
            "SELECT id, bank_name, account_number, current_balance, opening_balance 
             FROM bank_accounts 
             WHERE company_id = ? AND is_active = 1 
             ORDER BY is_primary DESC, bank_name ASC",
            [$companyId]
        );

        $selectedBank = null;
        if ($bankAccountId > 0) {
            foreach ($bankAccounts as $ba) {
                if ((int)$ba['id'] === $bankAccountId) {
                    $selectedBank = $ba;
                    break;
                }
            }
        }
        if (!$selectedBank && !empty($bankAccounts)) {
            $selectedBank = $bankAccounts[0];
            $bankAccountId = (int)$selectedBank['id'];
        }

        $branchSql = "";
        $branchParams = [];
        if ($branchId > 0) {
            $branchSql = " AND (p.branch_id = ? OR p.branch_id IS NULL)";
            $branchParams = [$branchId];
        }

        $openingBalance = (float)($selectedBank['opening_balance'] ?? 0);

        // Transactions via non-cash modes
        $periodParams = array_merge([$companyId, $fromDate, $toDate], $branchParams);
        $txList = Database::select(
            "SELECT 
                p.id,
                p.payment_number,
                p.payment_date,
                p.party_type,
                p.party_id,
                p.payment_type,
                p.payment_mode,
                p.amount,
                COALESCE(p.reference_no, p.reference_number, p.utr, p.cheque_number) AS reference_no,
                p.notes,
                b.name AS branch_name,
                CASE 
                    WHEN p.party_type = 'CUSTOMER' THEN (SELECT name FROM customers WHERE id = p.party_id)
                    WHEN p.party_type = 'SUPPLIER' THEN (SELECT name FROM suppliers WHERE id = p.party_id)
                    ELSE 'Bank Deposit/Transfer'
                END AS party_name
             FROM payments p
             LEFT JOIN branches b ON p.branch_id = b.id
             WHERE p.company_id = ? 
               AND p.payment_mode != 'Cash' 
               AND p.payment_date BETWEEN ? AND ? 
               AND p.deleted_at IS NULL 
               {$branchSql}
             ORDER BY p.payment_date ASC, p.id ASC",
            $periodParams
        );

        $running = $openingBalance;
        $totalDeposits = 0.0;
        $totalWithdrawals = 0.0;
        $entries = [];

        foreach ($txList as $row) {
            $amt = (float)$row['amount'];
            $isDeposit = ($row['payment_type'] === 'RECEIPT');

            $in = $isDeposit ? $amt : 0.0;
            $out = !$isDeposit ? $amt : 0.0;

            $totalDeposits += $in;
            $totalWithdrawals += $out;

            $running = round($running + $in - $out, 2);

            $row['deposit_amount'] = $in;
            $row['withdrawal_amount'] = $out;
            $row['running_balance'] = $running;
            $entries[] = $row;
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'bank_accounts' => $bankAccounts,
            'selected_bank' => $selectedBank,
            'opening_balance' => $openingBalance,
            'total_deposits' => round($totalDeposits, 2),
            'total_withdrawals' => round($totalWithdrawals, 2),
            'closing_balance' => $running,
            'entries' => $entries,
        ];
    }

    /**
     * Generate Cash Flow Statement.
     */
    public function getCashFlowStatement(int $companyId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? (string)$filters['from_date'] : date('Y-m-01');
        $toDate = !empty($filters['to_date']) ? (string)$filters['to_date'] : date('Y-m-d');
        $branchId = (int)($filters['branch_id'] ?? 0);

        $branchSql = "";
        $branchParams = [];
        if ($branchId > 0) {
            $branchSql = " AND (branch_id = ? OR branch_id IS NULL)";
            $branchParams = [$branchId];
        }

        // 1. Operating Activities:
        // Inflows from Customers
        $custParams = array_merge([$companyId, $fromDate, $toDate], $branchParams);
        $custRow = Database::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total 
             FROM payments 
             WHERE company_id = ? AND party_type = 'CUSTOMER' AND payment_type = 'RECEIPT' AND payment_date BETWEEN ? AND ? AND deleted_at IS NULL {$branchSql}",
            $custParams
        );
        $customerReceipts = (float)($custRow['total'] ?? 0);

        // Outflows to Suppliers
        $suppRow = Database::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total 
             FROM payments 
             WHERE company_id = ? AND party_type = 'SUPPLIER' AND payment_type = 'PAYMENT' AND payment_date BETWEEN ? AND ? AND deleted_at IS NULL {$branchSql}",
            $custParams
        );
        $supplierPayments = (float)($suppRow['total'] ?? 0);

        // Operating Expenses
        $expParams = array_merge([$companyId, $fromDate, $toDate], $branchParams);
        $expRow = Database::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total 
             FROM expenses 
             WHERE company_id = ? AND expense_date BETWEEN ? AND ? {$branchSql}",
            $expParams
        );
        $operatingExpenses = (float)($expRow['total'] ?? 0);

        $netOperatingCash = round($customerReceipts - $supplierPayments - $operatingExpenses, 2);

        // 2. Investing Activities: Fixed Assets / Equipment
        $netInvestingCash = 0.00;

        // 3. Financing Activities: Capital / Loans
        $netFinancingCash = 0.00;

        $netCashChange = round($netOperatingCash + $netInvestingCash + $netFinancingCash, 2);

        // Beginning and Ending Cash & Bank
        $cashAcc = Database::selectOne("SELECT current_balance, opening_balance FROM chart_of_accounts WHERE company_id = ? AND account_code = '100100'", [$companyId]);
        $bankSum = Database::selectOne("SELECT COALESCE(SUM(current_balance), 0) as total FROM bank_accounts WHERE company_id = ?", [$companyId]);

        $currentTotalCashBank = (float)($cashAcc['current_balance'] ?? 0) + (float)($bankSum['total'] ?? 0);
        $beginningCashBank = round($currentTotalCashBank - $netCashChange, 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'operating' => [
                'customer_receipts' => $customerReceipts,
                'supplier_payments' => $supplierPayments,
                'operating_expenses' => $operatingExpenses,
                'net_operating' => $netOperatingCash,
            ],
            'investing' => [
                'net_investing' => $netInvestingCash,
            ],
            'financing' => [
                'net_financing' => $netFinancingCash,
            ],
            'summary' => [
                'beginning_balance' => $beginningCashBank,
                'net_cash_change' => $netCashChange,
                'ending_balance' => $currentTotalCashBank,
            ],
        ];
    }
}

