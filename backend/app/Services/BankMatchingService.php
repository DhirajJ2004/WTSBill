<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\Cheque;
use App\Models\BankReconciliationMatch;
use Illuminate\Database\Capsule\Manager as DB;

class BankMatchingService
{
    /**
     * Scan and produce matching suggestions between Bank Transactions and Book Entries
     */
    public static function findMatches(
        int $companyId,
        int $bankAccountId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $bankAccount = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);
        $ledgerId = $bankAccount->ledger_account_id;

        // 1. Get Unreconciled Bank Transactions
        $bankTxnQuery = BankTransaction::where('company_id', $companyId)
            ->where('bank_account_id', $bankAccountId)
            ->where('reconciliation_status', 'UNRECONCILED');

        if ($startDate) $bankTxnQuery->where('transaction_date', '>=', $startDate);
        if ($endDate) $bankTxnQuery->where('transaction_date', '<=', $endDate);

        $bankTxns = $bankTxnQuery->orderBy('transaction_date', 'asc')->get();

        // 2. Get Book Entries from Journal Lines for this bank ledger account
        $bookQuery = JournalLine::whereHas('journalEntry', function ($q) use ($companyId, $startDate, $endDate) {
            $q->where('company_id', $companyId)
              ->where('status', 'POSTED');
            if ($startDate) $q->where('entry_date', '>=', $startDate);
            if ($endDate) $q->where('entry_date', '<=', $endDate);
        })->where('account_id', $ledgerId);

        // Exclude already matched journal lines
        $matchedLineIds = BankReconciliationMatch::where('company_id', $companyId)
            ->where('status', 'CONFIRMED')
            ->whereNotNull('journal_line_id')
            ->pluck('journal_line_id')
            ->toArray();

        if (!empty($matchedLineIds)) {
            $bookQuery->whereNotIn('id', $matchedLineIds);
        }

        $bookLines = $bookQuery->with('journalEntry')->get();

        $suggestions = [];
        $usedBookLineIds = [];

        // 3. Match Algorithm
        foreach ($bankTxns as $txn) {
            $txnAmount = floatval($txn->amount);
            $txnDate = '';
            if ($txn->transaction_date) {
                $txnDate = is_string($txn->transaction_date) ? substr($txn->transaction_date, 0, 10) : $txn->transaction_date->format('Y-m-d');
            }
            $txnRef = trim((string)($txn->reference_number ?: $txn->reference_no));
            $txnDesc = strtolower($txn->description);
            $isDeposit = ($txn->debit_credit === 'CREDIT');

            $bestMatch = null;
            $bestScore = 0; // 0: None, 1: Low, 2: Medium, 3: High

            foreach ($bookLines as $bLine) {
                if (in_array($bLine->id, $usedBookLineIds)) continue;

                $bookDebit = floatval($bLine->debit);
                $bookCredit = floatval($bLine->credit);
                $bookAmount = $isDeposit ? $bookDebit : $bookCredit;

                if (abs($bookAmount - $txnAmount) > 0.01) {
                    continue; // For 1-to-1 exact amount
                }

                $bookDate = '';
                if ($bLine->journalEntry && $bLine->journalEntry->entry_date) {
                    $ed = $bLine->journalEntry->entry_date;
                    $bookDate = is_string($ed) ? substr($ed, 0, 10) : $ed->format('Y-m-d');
                }
                $daysDiff = $txnDate && $bookDate ? abs((strtotime($txnDate) - strtotime($bookDate)) / 86400) : 99;

                $score = 1; // Amount matched
                $confidence = 'LOW';

                if ($daysDiff <= 3) {
                    $score = 2;
                    $confidence = 'MEDIUM';
                }

                // Reference / description check
                $bookDesc = strtolower($bLine->description . ' ' . ($bLine->journalEntry ? $bLine->journalEntry->description : ''));
                if ($daysDiff <= 1 || (!empty($txnRef) && str_contains($bookDesc, strtolower($txnRef)))) {
                    $score = 3;
                    $confidence = 'HIGH';
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = [
                        'match_type' => 'ONE_TO_ONE',
                        'confidence_score' => $confidence,
                        'journal_line_id' => $bLine->id,
                        'journal_entry_id' => $bLine->journal_entry_id,
                        'book_description' => $bLine->description,
                        'book_date' => $bookDate,
                        'book_amount' => $bookAmount,
                        'matched_amount' => $txnAmount,
                        'difference_amount' => 0.00,
                    ];
                    if ($score === 3) break;
                }
            }

            if ($bestMatch) {
                $usedBookLineIds[] = $bestMatch['journal_line_id'];
                $suggestions[] = [
                    'bank_transaction_id' => $txn->id,
                    'bank_date' => $txnDate,
                    'bank_description' => $txn->description,
                    'bank_reference' => $txnRef,
                    'bank_amount' => $txnAmount,
                    'bank_type' => $txn->debit_credit,
                    'has_match' => true,
                    'match' => $bestMatch,
                ];
            } else {
                // Check for potential One-to-Many match (e.g. 1 bank deposit matches 2 invoices/receipts)
                $multiCandidates = [];
                $runningSum = 0.0;
                foreach ($bookLines as $bLine) {
                    if (in_array($bLine->id, $usedBookLineIds)) continue;
                    $bookAmount = $isDeposit ? floatval($bLine->debit) : floatval($bLine->credit);
                    if ($bookAmount <= 0) continue;

                    if ($runningSum + $bookAmount <= $txnAmount + 0.01) {
                        $runningSum += $bookAmount;
                        $multiCandidates[] = $bLine;
                        if (abs($runningSum - $txnAmount) <= 0.01 && count($multiCandidates) > 1) {
                            break;
                        }
                    }
                }

                if (abs($runningSum - $txnAmount) <= 0.01 && count($multiCandidates) > 1) {
                    foreach ($multiCandidates as $mc) {
                        $usedBookLineIds[] = $mc->id;
                    }
                    $suggestions[] = [
                        'bank_transaction_id' => $txn->id,
                        'bank_date' => $txnDate,
                        'bank_description' => $txn->description,
                        'bank_reference' => $txnRef,
                        'bank_amount' => $txnAmount,
                        'bank_type' => $txn->debit_credit,
                        'has_match' => true,
                        'match' => [
                            'match_type' => 'ONE_TO_MANY',
                            'confidence_score' => 'MEDIUM',
                            'journal_line_ids' => array_map(fn($c) => $c->id, $multiCandidates),
                            'matched_amount' => $txnAmount,
                            'difference_amount' => 0.00,
                            'details' => array_map(fn($c) => [
                                'journal_line_id' => $c->id,
                                'journal_entry_id' => $c->journal_entry_id,
                                'description' => $c->description,
                                'amount' => $isDeposit ? floatval($c->debit) : floatval($c->credit),
                            ], $multiCandidates),
                        ],
                    ];
                } else {
                    $suggestions[] = [
                        'bank_transaction_id' => $txn->id,
                        'bank_date' => $txnDate,
                        'bank_description' => $txn->description,
                        'bank_reference' => $txnRef,
                        'bank_amount' => $txnAmount,
                        'bank_type' => $txn->debit_credit,
                        'has_match' => false,
                        'match' => null,
                    ];
                }
            }
        }

        // List unmatched book transactions (Books Only)
        $unmatchedBookEntries = [];
        foreach ($bookLines as $bLine) {
            if (!in_array($bLine->id, $usedBookLineIds)) {
                $bookDebit = floatval($bLine->debit);
                $bookCredit = floatval($bLine->credit);
                $bookDate = null;
                if ($bLine->journalEntry && $bLine->journalEntry->entry_date) {
                    $ed = $bLine->journalEntry->entry_date;
                    $bookDate = is_string($ed) ? substr($ed, 0, 10) : $ed->format('Y-m-d');
                }
                $unmatchedBookEntries[] = [
                    'journal_line_id' => $bLine->id,
                    'journal_entry_id' => $bLine->journal_entry_id,
                    'entry_date' => $bookDate,
                    'description' => $bLine->description,
                    'debit' => $bookDebit,
                    'credit' => $bookCredit,
                    'amount' => max($bookDebit, $bookCredit),
                    'type' => ($bookDebit > 0) ? 'DEBIT' : 'CREDIT',
                ];
            }
        }

        return [
            'total_bank_transactions' => count($bankTxns),
            'matched_count' => count(array_filter($suggestions, fn($s) => $s['has_match'])),
            'unmatched_bank_count' => count(array_filter($suggestions, fn($s) => !$s['has_match'])),
            'unmatched_book_count' => count($unmatchedBookEntries),
            'suggestions' => $suggestions,
            'unmatched_book_entries' => $unmatchedBookEntries,
        ];
    }
}
