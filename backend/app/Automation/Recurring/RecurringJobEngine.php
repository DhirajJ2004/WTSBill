<?php

namespace App\Automation\Recurring;

use App\Models\RecurringTemplate;
use App\Models\RecurringRunLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\GSTLedgerService;
use App\Services\DocumentNumberingService;
use App\Automation\Notifications\NotificationEngine;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use Exception;

class RecurringJobEngine
{
    /**
     * Create a recurring template
     */
    public static function createTemplate(int $companyId, array $data, ?string $userName = 'System'): RecurringTemplate
    {
        $type = strtoupper($data['transaction_type'] ?? 'SALES_INVOICE');
        $title = trim($data['title'] ?? ($type . ' Recurring Template'));
        $freq = strtoupper($data['frequency'] ?? 'MONTHLY');
        $startDate = $data['start_date'] ?? date('Y-m-d');
        $mode = strtoupper($data['generation_mode'] ?? 'AUTO_DRAFT');
        $amount = round(floatval($data['amount'] ?? 0), 2);

        $templateNumber = 'REC-TPL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        return RecurringTemplate::create([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'] ?? null,
            'template_number' => $templateNumber,
            'transaction_type' => $type,
            'party_type' => $data['party_type'] ?? null,
            'party_id' => $data['party_id'] ?? null,
            'title' => $title,
            'payload_json' => $data['payload'] ?? $data['payload_json'] ?? [],
            'amount' => $amount,
            'frequency' => $freq,
            'month_end_policy' => $data['month_end_policy'] ?? 'LAST_DAY',
            'custom_cron' => $data['custom_cron'] ?? null,
            'start_date' => $startDate,
            'end_date' => $data['end_date'] ?? null,
            'next_run_date' => $startDate,
            'generation_mode' => $mode,
            'status' => 'ACTIVE',
            'version' => 1,
            'created_by' => $userName,
        ]);
    }

    /**
     * Calculate next scheduled run date
     */
    public static function calculateNextRunDate(string $currentDate, string $frequency, string $monthEndPolicy = 'LAST_DAY'): string
    {
        $ts = strtotime($currentDate);
        switch (strtoupper($frequency)) {
            case 'DAILY':
                return date('Y-m-d', strtotime('+1 day', $ts));

            case 'WEEKLY':
                return date('Y-m-d', strtotime('+1 week', $ts));

            case 'MONTHLY':
                $year = (int)date('Y', $ts);
                $month = (int)date('m', $ts);
                $day = (int)date('d', $ts);

                $month++;
                if ($month > 12) {
                    $month = 1;
                    $year++;
                }

                $daysInNewMonth = (int)date('t', strtotime("{$year}-{$month}-01"));
                $targetDay = $day;
                if ($day > $daysInNewMonth) {
                    if ($monthEndPolicy === 'LAST_DAY') {
                        $targetDay = $daysInNewMonth;
                    } elseif ($monthEndPolicy === 'NEXT_VALID') {
                        return date('Y-m-d', strtotime("+1 month +1 day", $ts));
                    }
                }
                return sprintf('%04d-%02d-%02d', $year, $month, $targetDay);

            case 'QUARTERLY':
                return date('Y-m-d', strtotime('+3 months', $ts));

            case 'YEARLY':
                return date('Y-m-d', strtotime('+1 year', $ts));

            default:
                return date('Y-m-d', strtotime('+1 month', $ts));
        }
    }

    /**
     * Execute all due recurring templates for a company
     */
    public static function processDueTemplates(int $companyId, ?string $asOfDate = null, ?string $userName = 'System'): array
    {
        $today = $asOfDate ?: date('Y-m-d');

        $dueTemplates = RecurringTemplate::where('company_id', $companyId)
            ->where('status', 'ACTIVE')
            ->whereDate('next_run_date', '<=', $today)
            ->get();

        $results = [];

        foreach ($dueTemplates as $tpl) {
            $results[] = self::executeSingleTemplate($tpl, $today, $userName);
        }

        return $results;
    }

    /**
     * Execute a single recurring template run safely
     */
    public static function executeSingleTemplate(RecurringTemplate $tpl, ?string $runDate = null, ?string $userName = 'System'): array
    {
        $companyId = $tpl->company_id;
        $date = $runDate ?: ($tpl->next_run_date instanceof \DateTimeInterface ? $tpl->next_run_date->format('Y-m-d') : strval($tpl->next_run_date ?: date('Y-m-d')));
        $runId = 'RUN-' . $tpl->id . '-' . date('Ymd', strtotime($date));

        // Check if end date reached
        if ($tpl->end_date) {
            $endDateStr = $tpl->end_date instanceof \DateTimeInterface ? $tpl->end_date->format('Y-m-d') : strval($tpl->end_date);
            if (strtotime($date) > strtotime($endDateStr)) {
                $tpl->status = 'COMPLETED';
                $tpl->save();
                return ['template_id' => $tpl->id, 'status' => 'COMPLETED', 'message' => 'Template expired.'];
            }
        }

        // Idempotency: Concurrency protection
        $existingRun = RecurringRunLog::where('company_id', $companyId)
            ->where('template_id', $tpl->id)
            ->where('run_id', $runId)
            ->first();

        if ($existingRun) {
            return [
                'template_id' => $tpl->id,
                'status' => 'SKIPPED',
                'message' => "Run {$runId} already executed.",
                'run_log' => $existingRun,
            ];
        }

        return DB::transaction(function () use ($companyId, $tpl, $date, $runId, $userName) {
            try {
                // Period lock check
                GSTLedgerService::assertPeriodNotLocked($companyId, $date);

                $generatedEntity = null;
                $entityType = $tpl->transaction_type;
                $payload = $tpl->payload_json ?: [];

                switch ($tpl->transaction_type) {
                    case 'SALES_INVOICE':
                        $generatedEntity = self::generateInvoice($companyId, $tpl, $date, $payload, $userName);
                        break;

                    case 'PURCHASE_BILL':
                        $generatedEntity = self::generatePurchase($companyId, $tpl, $date, $payload, $userName);
                        break;

                    case 'EXPENSE':
                        $generatedEntity = self::generateExpense($companyId, $tpl, $date, $payload, $userName);
                        break;

                    case 'JOURNAL':
                        $generatedEntity = self::generateJournal($companyId, $tpl, $date, $payload, $userName);
                        break;

                    default:
                        throw new Exception("Unsupported recurring transaction type: {$tpl->transaction_type}");
                }

                // Record Run Log
                $runLog = RecurringRunLog::create([
                    'company_id' => $companyId,
                    'template_id' => $tpl->id,
                    'run_id' => $runId,
                    'run_date' => $date,
                    'generated_entity_type' => $entityType,
                    'generated_entity_id' => $generatedEntity ? $generatedEntity->id : null,
                    'generation_mode' => $tpl->generation_mode,
                    'status' => 'SUCCESS',
                ]);

                // Advance Next Run Date
                $tpl->last_run_date = $date;
                $tpl->next_run_date = self::calculateNextRunDate($date, $tpl->frequency, $tpl->month_end_policy);
                $tpl->save();

                // Send notification
                NotificationEngine::createNotification(
                    companyId: $companyId,
                    category: 'system',
                    title: "Recurring Transaction Generated: {$tpl->title}",
                    message: "Successfully generated {$entityType} ({$tpl->generation_mode}) for date {$date}",
                    entityType: $entityType,
                    entityId: $generatedEntity ? $generatedEntity->id : null,
                    priority: 'NORMAL'
                );

                return [
                    'template_id' => $tpl->id,
                    'status' => 'SUCCESS',
                    'run_id' => $runId,
                    'entity' => $generatedEntity,
                ];

            } catch (Exception $e) {
                RecurringRunLog::create([
                    'company_id' => $companyId,
                    'template_id' => $tpl->id,
                    'run_id' => $runId,
                    'run_date' => $date,
                    'generated_entity_type' => $tpl->transaction_type,
                    'generation_mode' => $tpl->generation_mode,
                    'status' => 'FAILED',
                    'error_message' => $e->getMessage(),
                ]);

                NotificationEngine::createNotification(
                    companyId: $companyId,
                    category: 'system',
                    title: "Recurring Run Failed: {$tpl->title}",
                    message: "Failed to generate recurring {$tpl->transaction_type}: " . $e->getMessage(),
                    priority: 'HIGH'
                );

                return [
                    'template_id' => $tpl->id,
                    'status' => 'FAILED',
                    'run_id' => $runId,
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    /**
     * Generate Sales Invoice
     */
    protected static function generateInvoice(int $companyId, RecurringTemplate $tpl, string $date, array $payload, ?string $userName): Invoice
    {
        $status = ($tpl->generation_mode === 'AUTO_POST') ? 'POSTED' : 'DRAFT';
        $invNumber = 'INV-REC-' . date('Ymd', strtotime($date)) . '-' . strtoupper(substr(uniqid(), -4));

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $tpl->branch_id,
            'customer_id' => $tpl->party_id ?: ($payload['customer_id'] ?? 1),
            'invoice_number' => $invNumber,
            'invoice_date' => $date,
            'due_date' => date('Y-m-d', strtotime('+15 days', strtotime($date))),
            'status' => $status,
            'payment_status' => 'UNPAID',
            'subtotal' => $tpl->amount ?: floatval($payload['subtotal'] ?? 1000),
            'grand_total' => $tpl->amount ?: floatval($payload['grand_total'] ?? 1000),
            'amount_due' => $tpl->amount ?: floatval($payload['grand_total'] ?? 1000),
            'amount_paid' => 0.00,
            'notes' => "Generated automatically from Recurring Template #{$tpl->template_number}",
        ]);

        return $invoice;
    }

    /**
     * Generate Purchase Bill
     */
    protected static function generatePurchase(int $companyId, RecurringTemplate $tpl, string $date, array $payload, ?string $userName): Purchase
    {
        $status = ($tpl->generation_mode === 'AUTO_POST') ? 'POSTED' : 'DRAFT';
        $purNumber = 'BILL-REC-' . date('Ymd', strtotime($date)) . '-' . strtoupper(substr(uniqid(), -4));

        return Purchase::create([
            'company_id' => $companyId,
            'branch_id' => $tpl->branch_id,
            'supplier_id' => $tpl->party_id ?: ($payload['supplier_id'] ?? 1),
            'purchase_number' => $purNumber,
            'purchase_date' => $date,
            'due_date' => date('Y-m-d', strtotime('+30 days', strtotime($date))),
            'status' => $status,
            'payment_status' => 'UNPAID',
            'subtotal' => $tpl->amount ?: floatval($payload['subtotal'] ?? 1000),
            'grand_total' => $tpl->amount ?: floatval($payload['grand_total'] ?? 1000),
            'amount_due' => $tpl->amount ?: floatval($payload['grand_total'] ?? 1000),
            'amount_paid' => 0.00,
            'notes' => "Generated from Recurring Template #{$tpl->template_number}",
        ]);
    }

    /**
     * Generate Expense
     */
    protected static function generateExpense(int $companyId, RecurringTemplate $tpl, string $date, array $payload, ?string $userName): Expense
    {
        $expNumber = 'EXP-REC-' . date('Ymd', strtotime($date)) . '-' . strtoupper(substr(uniqid(), -4));

        return Expense::create([
            'company_id' => $companyId,
            'branch_id' => $tpl->branch_id,
            'expense_number' => $expNumber,
            'expense_date' => $date,
            'category_id' => $payload['category_id'] ?? null,
            'payment_mode' => $payload['payment_mode'] ?? 'BANK_TRANSFER',
            'amount' => $tpl->amount ?: floatval($payload['amount'] ?? 1000),
            'status' => ($tpl->generation_mode === 'AUTO_POST') ? 'PAID' : 'PENDING',
            'description' => "Recurring Expense: {$tpl->title}",
        ]);
    }

    /**
     * Generate Journal Entry
     */
    protected static function generateJournal(int $companyId, RecurringTemplate $tpl, string $date, array $payload, ?string $userName): JournalEntry
    {
        $jnNumber = 'JN-REC-' . date('Ymd', strtotime($date)) . '-' . strtoupper(substr(uniqid(), -4));

        return JournalEntry::create([
            'company_id' => $companyId,
            'branch_id' => $tpl->branch_id,
            'entry_number' => $jnNumber,
            'entry_date' => $date,
            'status' => ($tpl->generation_mode === 'AUTO_POST') ? 'POSTED' : 'DRAFT',
            'narration' => "Recurring Journal: {$tpl->title}",
            'created_by' => $userName,
        ]);
    }

    /**
     * Pause recurring template
     */
    public static function pauseTemplate(int $companyId, int $templateId): RecurringTemplate
    {
        $tpl = RecurringTemplate::where('company_id', $companyId)->findOrFail($templateId);
        $tpl->status = 'PAUSED';
        $tpl->save();
        return $tpl;
    }

    /**
     * Resume recurring template
     */
    public static function resumeTemplate(int $companyId, int $templateId): RecurringTemplate
    {
        $tpl = RecurringTemplate::where('company_id', $companyId)->findOrFail($templateId);
        $tpl->status = 'ACTIVE';
        $tpl->save();
        return $tpl;
    }

    /**
     * Cancel recurring template
     */
    public static function cancelTemplate(int $companyId, int $templateId): RecurringTemplate
    {
        $tpl = RecurringTemplate::where('company_id', $companyId)->findOrFail($templateId);
        $tpl->status = 'CANCELLED';
        $tpl->save();
        return $tpl;
    }
}
