<?php

namespace App\Banking\Reconciliation;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementRow;
use App\Models\BankTransaction;
use App\Models\BankAccount;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use RuntimeException;

class BankReconciliationService
{
    /**
     * Start bank reconciliation period statement.
     */
    public static function startReconciliation(
        int $companyId,
        int $bankAccountId,
        string $startDate,
        string $endDate,
        float $openingBalance,
        float $closingBalance
    ): BankReconciliation {
        $bank = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);

        return BankReconciliation::create([
            'company_id' => $companyId,
            'bank_account_id' => $bank->id,
            'statement_date' => $endDate,
            'statement_balance' => $closingBalance,
            'statement_start_date' => $startDate,
            'statement_end_date' => $endDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'statement_opening_balance' => $openingBalance,
            'statement_closing_balance' => $closingBalance,
            'book_opening_balance' => $bank->opening_balance ?: 0.00,
            'book_closing_balance' => $bank->current_balance,
            'system_balance' => $bank->current_balance,
            'unreconciled_difference' => round($closingBalance - $bank->current_balance, 2),
            'status' => 'OPEN',
        ]);
    }

    /**
     * Matching engine: scan unmatched statement rows and system transactions for matches.
     */
    public static function findSuggestedMatches(int $companyId, int $bankAccountId): array
    {
        $unmatchedRows = BankStatementRow::where('company_id', $companyId)
            ->where('bank_account_id', $bankAccountId)
            ->where('match_status', 'UNMATCHED')
            ->get();

        $unreconciledTxs = BankTransaction::where('company_id', $companyId)
            ->where('bank_account_id', $bankAccountId)
            ->where('is_reconciled', false)
            ->get();

        $suggestions = [];

        foreach ($unmatchedRows as $row) {
            $rowAmt = $row->credit > 0 ? $row->credit : $row->debit;
            $rowType = $row->credit > 0 ? 'CREDIT' : 'DEBIT';

            foreach ($unreconciledTxs as $tx) {
                if ($tx->type !== $rowType || abs($tx->amount - $rowAmt) > 0.001) {
                    continue;
                }

                // Confidence scoring
                $confidence = 'POSSIBLE_MATCH';
                if (!empty($row->reference_number) && !empty($tx->reference_number) && strtolower($row->reference_number) === strtolower($tx->reference_number)) {
                    $confidence = 'EXACT';
                } else {
                    $rowD = Carbon::parse($row->row_date);
                    $txD = Carbon::parse($tx->transaction_date);
                    if ($rowD->diffInDays($txD) <= 3) {
                        $confidence = 'HIGH_CONFIDENCE';
                    }
                }

                $suggestions[] = [
                    'statement_row_id' => $row->id,
                    'statement_row' => $row,
                    'bank_transaction_id' => $tx->id,
                    'bank_transaction' => $tx,
                    'confidence' => $confidence,
                    'matched_amount' => $rowAmt,
                ];
                break; // One suggestion per row
            }
        }

        return $suggestions;
    }

    /**
     * Confirm match between bank statement row and system transaction.
     */
    public static function matchTransaction(
        int $reconciliationId,
        int $statementRowId,
        int $bankTransactionId,
        string $confidence = 'EXACT',
        ?string $userName = 'System'
    ): BankReconciliationMatch {
        return DB::transaction(function () use ($reconciliationId, $statementRowId, $bankTransactionId, $confidence, $userName) {
            $rec = BankReconciliation::findOrFail($reconciliationId);
            $row = BankStatementRow::findOrFail($statementRowId);
            $tx = BankTransaction::findOrFail($bankTransactionId);

            $matchAmt = $row->credit > 0 ? $row->credit : $row->debit;

            $match = BankReconciliationMatch::create([
                'company_id' => $rec->company_id,
                'bank_reconciliation_id' => $rec->id,
                'reconciliation_id' => $rec->id,
                'bank_statement_row_id' => $row->id,
                'bank_transaction_id' => $tx->id,
                'matched_amount' => $matchAmt,
                'match_confidence' => $confidence,
                'confidence_score' => $confidence,
                'status' => 'CONFIRMED',
                'matched_by' => $userName,
            ]);

            $row->update(['match_status' => 'MATCHED']);
            $tx->update([
                'is_reconciled' => true,
                'reconciliation_status' => 'RECONCILED',
                'reconciled_at' => Carbon::now(),
                'reconciled_by' => $userName,
            ]);

            return $match;
        });
    }

    /**
     * Complete reconciliation statement.
     */
    public static function completeReconciliation(int $reconciliationId, ?string $userName = 'System'): BankReconciliation
    {
        $rec = BankReconciliation::with('matches')->findOrFail($reconciliationId);

        $matchedTotal = $rec->matches->sum('matched_amount');
        $rec->update([
            'status' => 'COMPLETED',
            'unreconciled_difference' => 0.00,
            'reconciled_at' => Carbon::now(),
            'reconciled_by' => $userName,
        ]);

        return $rec;
    }
}
