<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\ChartOfAccount;
use Illuminate\Database\Capsule\Manager as DB;

class JournalService
{
    /**
     * Create and atomically post a double-entry journal with strict mathematical and isolation checks.
     *
     * @param array $data
     * @param string|null $userName
     * @return JournalEntry
     */
    public static function createJournalEntry(array $data, ?string $userName = 'Admin'): JournalEntry
    {
        return DB::transaction(function () use ($data, $userName) {
            $companyId = intval($data['company_id']);
            $branchId = !empty($data['branch_id']) ? intval($data['branch_id']) : null;
            $fy = $data['financial_year'] ?? '2026-27';
            $entryDate = $data['entry_date'] ?? date('Y-m-d');
            $entryType = strtoupper($data['entry_type'] ?? 'MANUAL');
            $status = strtoupper($data['status'] ?? 'POSTED'); // DRAFT, POSTED

            // 1. Period Locking check
            PeriodService::assertNotLocked($companyId, $entryDate);

            // 2. Validate Lines
            $lines = $data['lines'] ?? [];
            if (!is_array($lines) || count($lines) < 2) {
                throw new \InvalidArgumentException("Double-entry accounting requires at least 2 journal lines. Provided: " . count($lines));
            }

            $totalDebit = 0.0;
            $totalCredit = 0.0;
            $validatedLines = [];

            foreach ($lines as $idx => $line) {
                $accountId = intval($line['account_id'] ?? 0);
                $account = ChartOfAccount::where('company_id', $companyId)->find($accountId);

                if (!$account) {
                    throw new \InvalidArgumentException("Journal line #{$idx} specifies invalid or non-existent Account ID: {$accountId}.");
                }
                if (!$account->is_active) {
                    throw new \InvalidArgumentException("Account '{$account->account_name}' ({$account->account_code}) is inactive and cannot accept journal entries.");
                }

                $debit = round(floatval($line['debit'] ?? 0), 2);
                $credit = round(floatval($line['credit'] ?? 0), 2);

                if ($debit < 0 || $credit < 0) {
                    throw new \InvalidArgumentException("Journal line #{$idx} contains negative amounts (Debit: {$debit}, Credit: {$credit}).");
                }
                if ($debit > 0 && $credit > 0) {
                    throw new \InvalidArgumentException("Journal line #{$idx} cannot have both Debit and Credit amounts simultaneously.");
                }
                if ($debit == 0 && $credit == 0) {
                    continue; // Skip zero-value lines
                }

                $totalDebit += $debit;
                $totalCredit += $credit;

                $validatedLines[] = [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'account_id' => $account->id,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $line['description'] ?? ($data['description'] ?? "Journal line for {$account->account_name}"),
                    'party_type' => $line['party_type'] ?? null,
                    'party_id' => !empty($line['party_id']) ? intval($line['party_id']) : null,
                    'product_id' => !empty($line['product_id']) ? intval($line['product_id']) : null,
                    'cost_center' => $line['cost_center'] ?? null,
                    'tax_category' => $line['tax_category'] ?? null,
                ];
            }

            $totalDebit = round($totalDebit, 2);
            $totalCredit = round($totalCredit, 2);

            // 3. Mathematical Double-Entry Invariant
            if (abs($totalDebit - $totalCredit) > 0.001) {
                throw new \InvalidArgumentException(
                    "Unbalanced Journal Entry: Total Debits (₹{$totalDebit}) must exactly equal Total Credits (₹{$totalCredit}). Delta: ₹" . round(abs($totalDebit - $totalCredit), 2)
                );
            }

            if (count($validatedLines) < 2) {
                throw new \InvalidArgumentException("A valid double-entry journal requires at least 2 non-zero lines.");
            }

            // 4. Generate unique sequential numbering
            $journalNumber = $data['journal_number'] ?? DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'JOURNAL');

            // 5. Create Header
            $journal = JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'financial_year' => $fy,
                'journal_number' => $journalNumber,
                'entry_number' => $journalNumber,
                'entry_date' => $entryDate,
                'entry_type' => $entryType,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'description' => $data['description'] ?? "Double entry transaction #{$journalNumber}",
                'narration' => $data['description'] ?? "Double entry transaction #{$journalNumber}",
                'status' => $status,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'is_system_generated' => !empty($data['is_system_generated']),
                'original_journal_id' => !empty($data['original_journal_id']) ? intval($data['original_journal_id']) : null,
                'created_by' => $userName,
                'posted_by' => ($status === 'POSTED') ? $userName : null,
                'posted_at' => ($status === 'POSTED') ? date('Y-m-d H:i:s') : null,
            ]);

            // 6. Create Lines
            foreach ($validatedLines as $vLine) {
                $vLine['journal_entry_id'] = $journal->id;
                JournalLine::create($vLine);
            }

            AuditLogService::log(
                $companyId,
                $userName,
                'JOURNAL_POST',
                'JournalEntry',
                $journal->id,
                "Posted Double-Entry Journal #{$journal->journal_number} of ₹{$totalDebit} [Type: {$entryType}]"
            );

            return $journal->fresh(['lines.account']);
        });
    }

    /**
     * Post a draft journal entry.
     */
    public static function postJournalEntry(int $id, ?string $userName = 'Admin'): JournalEntry
    {
        return DB::transaction(function () use ($id, $userName) {
            $journal = JournalEntry::with('lines')->findOrFail($id);

            if ($journal->status === 'POSTED') {
                return $journal;
            }
            if ($journal->status === 'REVERSED') {
                throw new \InvalidArgumentException("Cannot post reversed journal entry #{$journal->journal_number}.");
            }

            PeriodService::assertNotLocked($journal->company_id, $journal->entry_date);

            $debit = round(floatval($journal->lines->sum('debit')), 2);
            $credit = round(floatval($journal->lines->sum('credit')), 2);

            if (abs($debit - $credit) > 0.001 || $debit <= 0) {
                throw new \InvalidArgumentException("Cannot post unbalanced journal entry #{$journal->journal_number} (Debits: ₹{$debit}, Credits: ₹{$credit}).");
            }

            $journal->update([
                'status' => 'POSTED',
                'total_debit' => $debit,
                'total_credit' => $credit,
                'posted_by' => $userName,
                'posted_at' => date('Y-m-d H:i:s'),
            ]);

            AuditLogService::log(
                $journal->company_id,
                $userName,
                'JOURNAL_POST',
                'JournalEntry',
                $journal->id,
                "Posted Journal #{$journal->journal_number} of ₹{$debit}"
            );

            return $journal->fresh(['lines.account']);
        });
    }

    /**
     * Create an immutable reversing journal entry for a previously posted journal.
     */
    public static function reverseJournalEntry(int $id, string $reason, ?string $userName = 'Admin'): JournalEntry
    {
        return DB::transaction(function () use ($id, $reason, $userName) {
            $original = JournalEntry::with('lines')->findOrFail($id);

            if ($original->status === 'REVERSED') {
                throw new \InvalidArgumentException("Journal entry #{$original->journal_number} is already reversed.");
            }
            if ($original->status !== 'POSTED') {
                throw new \InvalidArgumentException("Only POSTED journal entries can be reversed.");
            }

            // Invert lines: Original Debits become Credits, Credits become Debits
            $reversedLines = [];
            foreach ($original->lines as $line) {
                $reversedLines[] = [
                    'account_id' => $line->account_id,
                    'debit' => $line->credit, // swapped
                    'credit' => $line->debit, // swapped
                    'description' => "Reversal of #{$original->journal_number}: " . $line->description,
                    'party_type' => $line->party_type,
                    'party_id' => $line->party_id,
                    'product_id' => $line->product_id,
                    'cost_center' => $line->cost_center,
                    'tax_category' => $line->tax_category,
                ];
            }

            $reversalNumber = DocumentNumberingService::generateNextNumber($original->company_id, $original->branch_id, $original->financial_year, 'JOURNAL');

            $reversalJournal = static::createJournalEntry([
                'company_id' => $original->company_id,
                'branch_id' => $original->branch_id,
                'financial_year' => $original->financial_year,
                'journal_number' => $reversalNumber,
                'entry_date' => date('Y-m-d'),
                'entry_type' => $original->entry_type,
                'reference_type' => 'REVERSAL',
                'reference_id' => (string)$original->id,
                'description' => "Reversal of Journal #{$original->journal_number}: {$reason}",
                'status' => 'POSTED',
                'is_system_generated' => true,
                'original_journal_id' => $original->id,
                'lines' => $reversedLines,
            ], $userName);

            $original->update([
                'status' => 'REVERSED',
                'reversal_journal_id' => $reversalJournal->id,
                'reversal_reason' => $reason,
                'reversed_by' => $userName,
                'reversed_at' => date('Y-m-d H:i:s'),
            ]);

            AuditLogService::log(
                $original->company_id,
                $userName,
                'JOURNAL_REVERSE',
                'JournalEntry',
                $original->id,
                "Reversed Journal #{$original->journal_number} via Reversal Journal #{$reversalJournal->journal_number}: {$reason}"
            );

            return $reversalJournal;
        });
    }
}
