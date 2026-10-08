<?php

namespace App\Recurring\Generators;

use App\Models\RecurringTransaction;
use App\Models\JournalEntry;
use App\Services\JournalService;
use RuntimeException;

class RecurringJournalGenerator
{
    /**
     * Generate double-entry recurring journal entry with strict Debit = Credit validation.
     */
    public static function generate(RecurringTransaction $template, string $occurrenceDate): JournalEntry
    {
        $companyId = $template->company_id;
        $branchId = $template->branch_id;
        $payload = $template->template_payload_json ?: [];

        $lines = $payload['lines'] ?? [];
        if (empty($lines) || count($lines) < 2) {
            throw new RuntimeException("Recurring journal entry requires at least 2 debit/credit lines.");
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $totalDebit += floatval($line['debit'] ?? 0.0);
            $totalCredit += floatval($line['credit'] ?? 0.0);
        }

        // Strict Invariant: Debit == Credit
        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new RuntimeException("Recurring journal entry is unbalanced: Total Debits (₹{$totalDebit}) != Total Credits (₹{$totalCredit}).");
        }

        $status = $template->auto_post ? 'POSTED' : 'DRAFT';

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'financial_year' => '2026-27',
            'entry_date' => $occurrenceDate,
            'entry_type' => 'MANUAL',
            'status' => $status,
            'description' => "Generated from recurring schedule: {$template->name}",
            'narration' => $template->name,
            'lines' => $lines,
        ]);
    }
}
