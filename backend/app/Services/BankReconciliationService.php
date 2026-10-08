<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\JournalLine;
use App\Models\ChartOfAccount;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class BankReconciliationService
{
    /**
     * Start or fetch an active reconciliation session
     */
    public static function startReconciliation(
        int $companyId,
        int $bankAccountId,
        string $startDate,
        string $endDate,
        float $statementClosingBalance,
        float $statementOpeningBalance = 0.0,
        ?string $userName = 'System'
    ): BankReconciliation {
        return DB::transaction(function () use (
            $companyId, $bankAccountId, $startDate, $endDate,
            $statementClosingBalance, $statementOpeningBalance, $userName
        ) {
            $bankAccount = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);
            $ledgerId = $bankAccount->ledger_account_id;

            // Compute Book balances from LedgerService
            $bookLedger = LedgerService::getAccountLedger($companyId, $ledgerId, $startDate, $endDate);
            $bookOpening = floatval($bookLedger['opening_balance'] ?? 0.0);
            $bookClosing = floatval($bookLedger['closing_balance'] ?? 0.0);

            // Compute Uncleared Deposits and Unpresented Cheques
            $unclearedDeposits = floatval(BankTransaction::where('company_id', $companyId)
                ->where('bank_account_id', $bankAccountId)
                ->where('debit_credit', 'CREDIT')
                ->where('reconciliation_status', 'UNRECONCILED')
                ->where('transaction_date', '<=', $endDate)
                ->sum('amount'));

            $unpresentedCheques = floatval(BankTransaction::where('company_id', $companyId)
                ->where('bank_account_id', $bankAccountId)
                ->where('debit_credit', 'DEBIT')
                ->where('reconciliation_status', 'UNRECONCILED')
                ->where('transaction_date', '<=', $endDate)
                ->sum('amount'));

            $reconciledBalance = round($statementClosingBalance + $unclearedDeposits - $unpresentedCheques, 2);
            $diff = round($bookClosing - $reconciledBalance, 2);

            $rec = BankReconciliation::where('company_id', $companyId)
                ->where('bank_account_id', $bankAccountId)
                ->where('statement_start_date', $startDate)
                ->where('statement_end_date', $endDate)
                ->first();

            if (!$rec) {
                $rec = BankReconciliation::create([
                    'company_id' => $companyId,
                    'branch_id' => $bankAccount->branch_id,
                    'bank_account_id' => $bankAccountId,
                    'statement_date' => $endDate,
                    'statement_start_date' => $startDate,
                    'statement_end_date' => $endDate,
                    'statement_balance' => $statementClosingBalance,
                    'statement_opening_balance' => $statementOpeningBalance,
                    'statement_closing_balance' => $statementClosingBalance,
                    'book_opening_balance' => $bookOpening,
                    'book_closing_balance' => $bookClosing,
                    'reconciled_balance' => $reconciledBalance,
                    'difference_amount' => $diff,
                    'uncleared_deposits' => $unclearedDeposits,
                    'unpresented_cheques' => $unpresentedCheques,
                    'status' => 'IN_PROGRESS',
                ]);
            } else {
                $rec->update([
                    'statement_balance' => $statementClosingBalance,
                    'statement_opening_balance' => $statementOpeningBalance,
                    'statement_closing_balance' => $statementClosingBalance,
                    'book_opening_balance' => $bookOpening,
                    'book_closing_balance' => $bookClosing,
                    'reconciled_balance' => $reconciledBalance,
                    'difference_amount' => $diff,
                    'uncleared_deposits' => $unclearedDeposits,
                    'unpresented_cheques' => $unpresentedCheques,
                ]);
            }

            return $rec;
        });
    }

    /**
     * Match bank transaction with journal entry line
     */
    public static function matchTransaction(
        int $companyId,
        int $reconciliationId,
        int $bankTransactionId,
        ?int $journalLineId = null,
        ?float $matchedAmount = null,
        string $matchType = 'ONE_TO_ONE',
        ?string $notes = null,
        ?string $userName = 'System'
    ): BankReconciliationMatch {
        return DB::transaction(function () use (
            $companyId, $reconciliationId, $bankTransactionId, $journalLineId,
            $matchedAmount, $matchType, $notes, $userName
        ) {
            $rec = BankReconciliation::where('company_id', $companyId)->findOrFail($reconciliationId);
            if ($rec->status === 'COMPLETED') {
                throw new Exception("Cannot modify matches on a completed reconciliation.");
            }

            $txn = BankTransaction::where('company_id', $companyId)->findOrFail($bankTransactionId);
            $amount = $matchedAmount !== null ? floatval($matchedAmount) : floatval($txn->amount);

            $jLine = null;
            if ($journalLineId) {
                $jLine = JournalLine::findOrFail($journalLineId);
            }

            $match = BankReconciliationMatch::create([
                'company_id' => $companyId,
                'reconciliation_id' => $reconciliationId,
                'bank_transaction_id' => $bankTransactionId,
                'journal_entry_id' => $jLine ? $jLine->journal_entry_id : null,
                'journal_line_id' => $journalLineId,
                'match_type' => $matchType,
                'confidence_score' => 'HIGH',
                'matched_amount' => $amount,
                'difference_amount' => round(floatval($txn->amount) - $amount, 2),
                'status' => 'CONFIRMED',
                'notes' => $notes,
                'matched_by' => $userName,
            ]);

            $txn->reconciliation_status = 'MATCHED';
            $txn->reconciled_at = date('Y-m-d H:i:s');
            $txn->reconciled_by = $userName;
            $txn->save();

            AuditLogService::log(
                $companyId,
                'BANK_TRANSACTION_MATCHED',
                'BankReconciliationMatch',
                $match->id,
                "Matched bank transaction #{$txn->id} (₹{$amount}) in Reconciliation #{$reconciliationId}",
                null,
                $match->toArray(),
                $userName
            );

            return $match;
        });
    }

    /**
     * Unmatch transaction
     */
    public static function unmatchTransaction(int $companyId, int $reconciliationId, int $matchId, ?string $userName = 'System'): bool
    {
        return DB::transaction(function () use ($companyId, $reconciliationId, $matchId, $userName) {
            $rec = BankReconciliation::where('company_id', $companyId)->findOrFail($reconciliationId);
            if ($rec->status === 'COMPLETED') {
                throw new Exception("Cannot unmatch on a completed reconciliation.");
            }

            $match = BankReconciliationMatch::where('company_id', $companyId)
                ->where('reconciliation_id', $reconciliationId)
                ->findOrFail($matchId);

            if ($match->bank_transaction_id) {
                $txn = BankTransaction::find($match->bank_transaction_id);
                if ($txn) {
                    $txn->reconciliation_status = 'UNRECONCILED';
                    $txn->reconciled_at = null;
                    $txn->reconciled_by = null;
                    $txn->save();
                }
            }

            $match->delete();

            AuditLogService::log(
                $companyId,
                'BANK_TRANSACTION_UNMATCHED',
                'BankReconciliation',
                $reconciliationId,
                "Unmatched transaction #{$matchId} from Reconciliation #{$reconciliationId}",
                null,
                ['match_id' => $matchId],
                $userName
            );

            return true;
        });
    }

    /**
     * Create missing accounting entry for bank-only transactions (e.g. Bank charge / Interest)
     */
    public static function createMissingEntry(
        int $companyId,
        int $reconciliationId,
        int $bankTransactionId,
        string $entryType, // BANK_CHARGE, INTEREST, EXPENSE, OTHER_INCOME
        ?int $contraAccountId = null,
        ?string $description = null,
        ?string $userName = 'System'
    ): BankReconciliationMatch {
        return DB::transaction(function () use (
            $companyId, $reconciliationId, $bankTransactionId,
            $entryType, $contraAccountId, $description, $userName
        ) {
            $rec = BankReconciliation::where('company_id', $companyId)->findOrFail($reconciliationId);
            $txn = BankTransaction::where('company_id', $companyId)->findOrFail($bankTransactionId);
            $bankAccount = BankAccount::where('company_id', $companyId)->findOrFail($txn->bank_account_id);
            $amount = floatval($txn->amount);
            $date = $txn->transaction_date ? $txn->transaction_date->format('Y-m-d') : date('Y-m-d');
            $desc = $description ?: $txn->description;

            $journal = null;
            if (strtoupper($entryType) === 'BANK_CHARGE') {
                $journal = AccountingEventService::recordBankChargeAccounting(
                    $companyId, $bankAccount, $amount, $desc, $txn->reference_number, $date, $userName
                );
            } elseif (strtoupper($entryType) === 'INTEREST') {
                $journal = AccountingEventService::recordBankInterestAccounting(
                    $companyId, $bankAccount, $amount, $desc, $txn->reference_number, $date, $userName
                );
            } else {
                // Generic entry
                AccountService::ensureDefaultAccounts($companyId);
                $contra = $contraAccountId ? ChartOfAccount::findOrFail($contraAccountId) : AccountService::getMappedAccount($companyId, 'OPERATING_EXPENSES');
                $isDebit = ($txn->debit_credit === 'DEBIT');

                $lines = [];
                if ($isDebit) {
                    $lines[] = ['account_id' => $contra->id, 'debit' => $amount, 'credit' => 0.00, 'description' => $desc];
                    $lines[] = ['account_id' => $bankAccount->ledger_account_id, 'debit' => 0.00, 'credit' => $amount, 'description' => "Bank payment for {$desc}"];
                } else {
                    $lines[] = ['account_id' => $bankAccount->ledger_account_id, 'debit' => $amount, 'credit' => 0.00, 'description' => "Bank receipt for {$desc}"];
                    $lines[] = ['account_id' => $contra->id, 'debit' => 0.00, 'credit' => $amount, 'description' => $desc];
                }

                $journal = JournalService::createJournalEntry([
                    'company_id' => $companyId,
                    'branch_id' => $bankAccount->branch_id,
                    'financial_year' => '2026-27',
                    'entry_date' => $date,
                    'entry_type' => 'JOURNAL',
                    'description' => "Reconciliation entry: {$desc}",
                    'status' => 'POSTED',
                    'lines' => $lines,
                ], $userName);
            }

            // Find the journal line corresponding to bank account
            $bankJLine = JournalLine::where('journal_entry_id', $journal->id)
                ->where('account_id', $bankAccount->ledger_account_id)
                ->first();

            return static::matchTransaction(
                companyId: $companyId,
                reconciliationId: $reconciliationId,
                bankTransactionId: $bankTransactionId,
                journalLineId: $bankJLine ? $bankJLine->id : null,
                matchedAmount: $amount,
                matchType: 'ONE_TO_ONE',
                notes: "Created missing {$entryType} entry from bank statement",
                userName: $userName
            );
        });
    }

    /**
     * Complete and lock bank reconciliation session
     */
    public static function completeReconciliation(int $companyId, int $reconciliationId, ?string $userName = 'System'): BankReconciliation
    {
        return DB::transaction(function () use ($companyId, $reconciliationId, $userName) {
            $rec = BankReconciliation::where('company_id', $companyId)->findOrFail($reconciliationId);
            if ($rec->status === 'COMPLETED') {
                return $rec;
            }

            $rec->status = 'COMPLETED';
            $rec->completed_at = date('Y-m-d H:i:s');
            $rec->completed_by = $userName;
            $rec->save();

            // Mark matched transactions as fully RECONCILED
            $matches = BankReconciliationMatch::where('reconciliation_id', $rec->id)->get();
            foreach ($matches as $m) {
                if ($m->bank_transaction_id) {
                    $txn = BankTransaction::find($m->bank_transaction_id);
                    if ($txn) {
                        $txn->reconciliation_status = 'RECONCILED';
                        $txn->is_reconciled = true;
                        $txn->reconciled_at = date('Y-m-d H:i:s');
                        $txn->reconciled_by = $userName;
                        $txn->save();
                    }
                }
            }

            AuditLogService::log(
                $companyId,
                'BANK_RECONCILIATION_COMPLETED',
                'BankReconciliation',
                $rec->id,
                "Finalized and locked Bank Reconciliation #{$rec->id} for {$rec->statement_start_date} to {$rec->statement_end_date}",
                null,
                $rec->toArray(),
                $userName
            );

            return $rec;
        });
    }

    /**
     * Reopen completed bank reconciliation session (Requires authorization)
     */
    public static function reopenReconciliation(int $companyId, int $reconciliationId, string $reason, ?string $userName = 'System'): BankReconciliation
    {
        return DB::transaction(function () use ($companyId, $reconciliationId, $reason, $userName) {
            $rec = BankReconciliation::where('company_id', $companyId)->findOrFail($reconciliationId);
            $old = $rec->toArray();

            $rec->status = 'IN_PROGRESS';
            $rec->reopened_at = date('Y-m-d H:i:s');
            $rec->reopened_by = $userName;
            $rec->reopen_reason = $reason;
            $rec->save();

            // Revert transaction statuses to MATCHED (editable)
            $matches = BankReconciliationMatch::where('reconciliation_id', $rec->id)->get();
            foreach ($matches as $m) {
                if ($m->bank_transaction_id) {
                    $txn = BankTransaction::find($m->bank_transaction_id);
                    if ($txn && $txn->reconciliation_status === 'RECONCILED') {
                        $txn->reconciliation_status = 'MATCHED';
                        $txn->is_reconciled = false;
                        $txn->save();
                    }
                }
            }

            AuditLogService::log(
                $companyId,
                'BANK_RECONCILIATION_REOPENED',
                'BankReconciliation',
                $rec->id,
                "Reopened Bank Reconciliation #{$rec->id}: {$reason}",
                $old,
                $rec->toArray(),
                $userName
            );

            return $rec;
        });
    }

    /**
     * Generate complete Bank Reconciliation Statement Report
     */
    public static function getReconciliationReport(int $companyId, int $reconciliationId): array
    {
        $rec = BankReconciliation::where('company_id', $companyId)->with('bankAccount')->findOrFail($reconciliationId);
        $matches = BankReconciliationMatch::where('reconciliation_id', $rec->id)
            ->with(['bankTransaction', 'journalEntry'])
            ->get();

        return [
            'reconciliation_id' => $rec->id,
            'bank_account_id' => $rec->bank_account_id,
            'bank_name' => $rec->bankAccount ? $rec->bankAccount->bank_name : 'Bank',
            'masked_account_number' => $rec->bankAccount ? $rec->bankAccount->masked_account_number : '',
            'statement_period' => "{$rec->statement_start_date} to {$rec->statement_end_date}",
            'statement_opening_balance' => floatval($rec->statement_opening_balance),
            'statement_closing_balance' => floatval($rec->statement_closing_balance),
            'book_opening_balance' => floatval($rec->book_opening_balance),
            'book_closing_balance' => floatval($rec->book_closing_balance),
            'uncleared_deposits' => floatval($rec->uncleared_deposits),
            'unpresented_cheques' => floatval($rec->unpresented_cheques),
            'reconciled_balance' => floatval($rec->reconciled_balance),
            'difference_amount' => floatval($rec->difference_amount),
            'status' => $rec->status,
            'completed_at' => $rec->completed_at,
            'completed_by' => $rec->completed_by,
            'reopened_at' => $rec->reopened_at,
            'reopen_reason' => $rec->reopen_reason,
            'total_matches' => count($matches),
            'matches' => $matches->map(function ($m) {
                return [
                    'id' => $m->id,
                    'match_type' => $m->match_type,
                    'confidence_score' => $m->confidence_score,
                    'matched_amount' => floatval($m->matched_amount),
                    'difference_amount' => floatval($m->difference_amount),
                    'status' => $m->status,
                    'bank_transaction' => $m->bankTransaction ? [
                        'id' => $m->bankTransaction->id,
                        'date' => $m->bankTransaction->transaction_date ? $m->bankTransaction->transaction_date->format('Y-m-d') : null,
                        'description' => $m->bankTransaction->description,
                        'reference' => $m->bankTransaction->reference_number ?: $m->bankTransaction->reference_no,
                        'amount' => floatval($m->bankTransaction->amount),
                        'type' => $m->bankTransaction->debit_credit,
                    ] : null,
                    'notes' => $m->notes,
                    'matched_by' => $m->matched_by,
                ];
            })->toArray(),
        ];
    }
}
