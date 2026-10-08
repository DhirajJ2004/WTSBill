<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Cheque;
use App\Models\JournalLine;

class BankingReportService
{
    /**
     * Generate Cash Book strictly from double-entry accounting ledger
     */
    public static function getCashBook(int $companyId, ?string $startDate = null, ?string $endDate = null): array
    {
        AccountService::ensureDefaultAccounts($companyId);
        $cashAccounts = ChartOfAccount::where('company_id', $companyId)
            ->where('account_type', 'ASSET')
            ->where('account_subtype', 'CASH')
            ->where('is_active', true)
            ->get();

        $rows = [];
        $totalCashIn = 0.0;
        $totalCashOut = 0.0;
        $openingBalance = 0.0;

        foreach ($cashAccounts as $acc) {
            $ledger = LedgerService::getAccountLedger($companyId, $acc->id, $startDate, $endDate);
            $openingBalance += floatval($ledger['opening_balance'] ?? 0.0);

            foreach (($ledger['transactions'] ?? []) as $entry) {
                $dr = floatval($entry['debit'] ?? 0.0);
                $cr = floatval($entry['credit'] ?? 0.0);
                $totalCashIn += $dr;
                $totalCashOut += $cr;

                $rows[] = [
                    'date' => $entry['date'],
                    'journal_number' => $entry['journal_number'] ?? '',
                    'reference_type' => $entry['reference_type'] ?? '',
                    'reference_id' => $entry['reference_id'] ?? '',
                    'description' => $entry['description'] ?? '',
                    'account_name' => $acc->account_name,
                    'cash_in' => $dr,
                    'cash_out' => $cr,
                    'balance' => floatval($entry['running_balance'] ?? 0.0),
                ];
            }
        }

        // Sort by date ascending
        usort($rows, function ($a, $b) {
            return strcmp((string)$a['date'], (string)$b['date']);
        });

        // Recalculate combined running balance
        $running = $openingBalance;
        foreach ($rows as &$r) {
            $running = round($running + $r['cash_in'] - $r['cash_out'], 2);
            $r['balance'] = $running;
        }

        return [
            'report_name' => 'Cash Book',
            'period' => ($startDate && $endDate) ? "{$startDate} to {$endDate}" : 'All Time',
            'opening_balance' => round($openingBalance, 2),
            'total_cash_in' => round($totalCashIn, 2),
            'total_cash_out' => round($totalCashOut, 2),
            'closing_balance' => round($running, 2),
            'entries' => $rows,
        ];
    }

    /**
     * Generate Bank Book strictly from double-entry accounting ledger
     */
    public static function getBankBook(int $companyId, ?int $bankAccountId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        AccountService::ensureDefaultAccounts($companyId);

        $bankAccounts = [];
        if ($bankAccountId) {
            $bankAccounts = BankAccount::where('company_id', $companyId)->where('id', $bankAccountId)->get();
        } else {
            $bankAccounts = BankAccount::where('company_id', $companyId)->where('is_active', true)->get();
        }

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $openingBalance = 0.0;

        foreach ($bankAccounts as $acc) {
            if (!$acc->ledger_account_id) continue;

            $ledger = LedgerService::getAccountLedger($companyId, $acc->ledger_account_id, $startDate, $endDate);
            $openingBalance += floatval($ledger['opening_balance'] ?? 0.0);

            foreach (($ledger['transactions'] ?? []) as $entry) {
                $dr = floatval($entry['debit'] ?? 0.0);
                $cr = floatval($entry['credit'] ?? 0.0);
                $totalDebit += $dr;
                $totalCredit += $cr;

                $rows[] = [
                    'date' => $entry['date'],
                    'bank_name' => $acc->bank_name,
                    'account_number' => $acc->masked_account_number,
                    'journal_number' => $entry['journal_number'] ?? '',
                    'reference_type' => $entry['reference_type'] ?? '',
                    'reference_id' => $entry['reference_id'] ?? '',
                    'description' => $entry['description'] ?? '',
                    'debit' => $dr, // Deposits / Inflow
                    'credit' => $cr, // Payments / Outflow
                    'balance' => floatval($entry['running_balance'] ?? 0.0),
                ];
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp((string)$a['date'], (string)$b['date']);
        });

        $running = $openingBalance;
        foreach ($rows as &$r) {
            $running = round($running + $r['debit'] - $r['credit'], 2);
            $r['balance'] = $running;
        }

        return [
            'report_name' => 'Bank Book',
            'bank_account_id' => $bankAccountId,
            'period' => ($startDate && $endDate) ? "{$startDate} to {$endDate}" : 'All Time',
            'opening_balance' => round($openingBalance, 2),
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'closing_balance' => round($running, 2),
            'entries' => $rows,
        ];
    }

    /**
     * Generate Cheque Register Report
     */
    public static function getChequeRegister(int $companyId, array $filters = []): array
    {
        $cheques = ChequeService::getCheques($companyId, $filters);

        $summary = [
            'total_received_count' => 0,
            'total_received_amount' => 0.0,
            'total_issued_count' => 0,
            'total_issued_amount' => 0.0,
            'cleared_count' => 0,
            'pending_count' => 0,
            'bounced_count' => 0,
        ];

        foreach ($cheques as $chq) {
            $amt = $chq['amount'];
            if ($chq['cheque_type'] === 'RECEIVED') {
                $summary['total_received_count']++;
                $summary['total_received_amount'] += $amt;
            } else {
                $summary['total_issued_count']++;
                $summary['total_issued_amount'] += $amt;
            }

            if ($chq['status'] === 'CLEARED') $summary['cleared_count']++;
            elseif ($chq['status'] === 'BOUNCED') $summary['bounced_count']++;
            elseif (in_array($chq['status'], ['RECEIVED', 'PREPARED', 'ISSUED', 'DEPOSITED', 'PRESENTED'])) $summary['pending_count']++;
        }

        return [
            'report_name' => 'Cheque Register',
            'summary' => $summary,
            'cheques' => $cheques,
        ];
    }
}
