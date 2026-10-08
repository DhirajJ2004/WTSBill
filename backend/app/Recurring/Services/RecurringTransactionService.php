<?php

namespace App\Recurring\Services;

use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionRun;
use App\Models\User;
use App\Recurring\Schedules\RecurrenceCalculator;
use App\Recurring\Generators\RecurringInvoiceGenerator;
use App\Recurring\Generators\RecurringExpenseGenerator;
use App\Recurring\Generators\RecurringJournalGenerator;
use App\Recurring\Generators\RecurringPaymentGenerator;
use Carbon\Carbon;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class RecurringTransactionService
{
    /**
     * Create a new recurring transaction template.
     */
    public static function createRecurringTransaction(int $companyId, array $data, ?User $user = null): RecurringTransaction
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new InvalidArgumentException("Recurring transaction name is required.");
        }

        $type = strtoupper($data['type'] ?? 'SALES_INVOICE');
        $frequency = strtoupper($data['frequency'] ?? 'MONTHLY');
        $startDate = $data['start_date'] ?? date('Y-m-d');
        $endDate = $data['end_date'] ?? null;
        $totalOccurrences = !empty($data['total_occurrences']) ? intval($data['total_occurrences']) : null;

        $template = RecurringTransaction::create([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'] ?? null,
            'type' => $type,
            'name' => $name,
            'frequency' => $frequency,
            'interval_count' => intval($data['interval_count'] ?? 1),
            'month_end_policy' => $data['month_end_policy'] ?? 'LAST_VALID_DAY',
            'missed_schedule_policy' => $data['missed_schedule_policy'] ?? 'GENERATE_MISSED',
            'pricing_policy' => $data['pricing_policy'] ?? 'PRESERVE_TEMPLATE_PRICE',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'next_run_at' => $startDate,
            'total_occurrences' => $totalOccurrences,
            'remaining_occurrences' => $totalOccurrences,
            'status' => 'ACTIVE',
            'template_payload_json' => $data['template_payload'] ?? [],
            'auto_post' => $data['auto_post'] ?? true,
            'auto_send' => $data['auto_send'] ?? false,
            'created_by' => $user?->name ?: 'System',
        ]);

        RecurringAuditService::log($companyId, $template->id, 'CREATED', [
            'type' => $type,
            'frequency' => $frequency,
            'start_date' => $startDate,
        ], $user?->id, $user?->name ?: 'System');

        return $template;
    }

    /**
     * Pause recurring template.
     */
    public static function pause(int $id, ?User $user = null): RecurringTransaction
    {
        $template = RecurringTransaction::findOrFail($id);
        if ($template->status !== 'ACTIVE' && $template->status !== 'NEEDS_ATTENTION') {
            throw new RuntimeException("Cannot pause template in status '{$template->status}'.");
        }

        $template->update(['status' => 'PAUSED']);

        RecurringAuditService::log($template->company_id, $template->id, 'PAUSED', [], $user?->id, $user?->name ?: 'System');

        return $template;
    }

    /**
     * Resume recurring template.
     */
    public static function resume(int $id, ?User $user = null): RecurringTransaction
    {
        $template = RecurringTransaction::findOrFail($id);
        if ($template->status !== 'PAUSED' && $template->status !== 'NEEDS_ATTENTION') {
            throw new RuntimeException("Cannot resume template in status '{$template->status}'.");
        }

        // Re-align next run date if past
        $today = Carbon::today();
        $nextRun = Carbon::parse($template->next_run_at);
        if ($nextRun->lt($today) && $template->missed_schedule_policy === 'GENERATE_NEXT_ONLY') {
            $template->next_run_at = $today->toDateString();
        }

        $template->update(['status' => 'ACTIVE']);

        RecurringAuditService::log($template->company_id, $template->id, 'RESUMED', [
            'next_run_at' => $template->next_run_at,
        ], $user?->id, $user?->name ?: 'System');

        return $template;
    }

    /**
     * Cancel recurring template.
     */
    public static function cancel(int $id, ?User $user = null): RecurringTransaction
    {
        $template = RecurringTransaction::findOrFail($id);
        $template->update(['status' => 'CANCELLED']);

        RecurringAuditService::log($template->company_id, $template->id, 'CANCELLED', [], $user?->id, $user?->name ?: 'System');

        return $template;
    }

    /**
     * Execute a specific occurrence of a recurring template.
     */
    public static function executeOccurrence(RecurringTransaction $template, string $occurrenceDate, ?User $user = null): array
    {
        $companyId = $template->company_id;

        // 1. Idempotency Check: Don't execute same occurrence twice
        $dateOnly = Carbon::parse($occurrenceDate)->toDateString();
        $existingRun = RecurringTransactionRun::withoutGlobalScopes()
            ->where('recurring_transaction_id', $template->id)
            ->whereDate('occurrence_date', $dateOnly)
            ->first();

        if ($existingRun && $existingRun->status === 'SUCCESS') {
            return [
                'status' => 'SKIPPED',
                'message' => 'Occurrence already executed successfully',
                'run' => $existingRun,
            ];
        }

        $run = $existingRun ?: new RecurringTransactionRun([
            'company_id' => $companyId,
            'recurring_transaction_id' => $template->id,
            'occurrence_date' => $dateOnly,
            'scheduled_at' => Carbon::now(),
            'attempt_count' => 0,
        ]);

        $run->attempt_count = $run->attempt_count + 1;

        try {
            $generatedEntity = match ($template->type) {
                'SALES_INVOICE' => RecurringInvoiceGenerator::generate($template, $occurrenceDate),
                'PURCHASE_EXPENSE' => RecurringExpenseGenerator::generate($template, $occurrenceDate),
                'JOURNAL_ENTRY' => RecurringJournalGenerator::generate($template, $occurrenceDate),
                'PAYMENT' => RecurringPaymentGenerator::generate($template, $occurrenceDate),
                default => throw new RuntimeException("Unsupported recurring transaction type: '{$template->type}'"),
            };

            $run->status = 'SUCCESS';
            $run->executed_at = Carbon::now();
            $run->generated_entity_type = strtoupper(class_basename($generatedEntity));
            $run->generated_entity_id = $generatedEntity->id;
            $run->error_message = null;
            $run->save();

            // Calculate Next Run Date
            $currentRunDate = Carbon::parse($occurrenceDate);
            $endDate = $template->end_date ? Carbon::parse($template->end_date) : null;
            $nextDate = RecurrenceCalculator::computeNextRunDate(
                $currentRunDate,
                $template->frequency,
                $template->interval_count,
                $template->month_end_policy,
                $endDate
            );

            $remaining = $template->remaining_occurrences !== null ? max(0, $template->remaining_occurrences - 1) : null;
            $isComplete = ($remaining !== null && $remaining === 0) || ($nextDate === null);

            $template->last_run_at = $occurrenceDate;
            $template->remaining_occurrences = $remaining;

            if ($isComplete) {
                $template->status = 'COMPLETED';
            } else {
                $template->next_run_at = $nextDate->toDateString();
            }
            $template->save();

            RecurringAuditService::log($companyId, $template->id, 'GENERATED', [
                'occurrence_date' => $occurrenceDate,
                'entity_type' => $run->generated_entity_type,
                'entity_id' => $run->generated_entity_id,
            ], $user?->id, $user?->name ?: 'System');

            return [
                'status' => 'SUCCESS',
                'generated_entity' => $generatedEntity,
                'run' => $run,
            ];
        } catch (Exception $e) {
            $run->status = 'FAILED';
            $run->error_message = $e->getMessage();
            $run->save();

            $template->update(['status' => 'NEEDS_ATTENTION']);

            RecurringAuditService::log($companyId, $template->id, 'FAILED', [
                'occurrence_date' => $occurrenceDate,
                'error' => $e->getMessage(),
            ], $user?->id, $user?->name ?: 'System');

            return [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'run' => $run,
            ];
        }
    }

    /**
     * Process all due recurring transactions for a company.
     */
    public static function processDueRecurringTransactions(int $companyId, ?string $asOfDate = null): array
    {
        $today = $asOfDate ? Carbon::parse($asOfDate) : Carbon::today();
        $todayStr = $today->toDateString();

        $dueTemplates = RecurringTransaction::where('company_id', $companyId)
            ->where('status', 'ACTIVE')
            ->where('next_run_at', '<=', $todayStr)
            ->get();

        $results = [];
        foreach ($dueTemplates as $tmpl) {
            $results[] = self::executeOccurrence($tmpl, $tmpl->next_run_at);
        }

        return [
            'date' => $todayStr,
            'processed_count' => count($dueTemplates),
            'results' => $results,
        ];
    }
}
