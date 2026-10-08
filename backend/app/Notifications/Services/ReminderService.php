<?php

namespace App\Notifications\Services;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\NotificationRule;
use App\Models\NotificationTemplate;
use App\Models\NotificationLog;
use App\Models\Company;
use App\Notifications\Templates\TemplateService;
use Carbon\Carbon;
use Exception;

class ReminderService
{
    /**
     * Scan invoices and send automated reminders based on rules.
     */
    public static function processDailyReminders(int $companyId, ?string $asOfDate = null): array
    {
        $today = $asOfDate ? Carbon::parse($asOfDate) : Carbon::today();
        $todayStr = $today->toDateString();
        $company = Company::find($companyId);

        // Ensure default rules and templates
        self::ensureDefaultRules($companyId);
        TemplateService::ensureDefaultTemplates($companyId);

        $rules = NotificationRule::where('company_id', $companyId)->where('is_active', true)->get();
        $activeInvoices = Invoice::where('company_id', $companyId)
            ->whereNotIn('status', ['PAID', 'CANCELLED'])
            ->with(['customer', 'branch'])
            ->get();

        $processed = 0;
        $sentCount = 0;
        $skippedCount = 0;
        $logs = [];

        foreach ($activeInvoices as $inv) {
            $processed++;

            // 1. Invariant Check: Remaining balance
            $balanceDue = floatval($inv->amount_due ?? ($inv->grand_total - ($inv->amount_paid ?? 0)));
            if ($balanceDue <= 0.001) {
                $skippedCount++;
                continue; // Fully settled
            }

            // 2. Customer Preference Check
            $customer = $inv->customer;
            if ($customer && $customer->reminder_enabled === false) {
                $skippedCount++;
                continue; // Opted out
            }

            $dueDate = Carbon::parse($inv->due_date ?: $inv->invoice_date);
            $diffDays = $dueDate->diffInDays($today, false); // Negative if in future, positive if overdue

            foreach ($rules as $rule) {
                $offset = intval($rule->days_offset);

                // Match days offset
                if ($diffDays === $offset) {
                    $log = self::sendInvoiceReminder($inv, $rule, $todayStr);
                    if ($log) {
                        $logs[] = $log;
                        if (in_array($log->status, ['SENT', 'DELIVERED', 'PENDING'])) {
                            $sentCount++;
                        }
                    }
                }
            }
        }

        return [
            'date' => $todayStr,
            'invoices_scanned' => $processed,
            'reminders_sent' => $sentCount,
            'skipped' => $skippedCount,
            'logs' => $logs,
        ];
    }

    /**
     * Send specific reminder for an invoice.
     */
    public static function sendInvoiceReminder(Invoice $inv, NotificationRule $rule, string $dateStr, ?string $manualChannel = null): ?NotificationLog
    {
        $companyId = $inv->company_id;
        $customer = $inv->customer;
        $company = Company::find($companyId);

        $balanceDue = floatval($inv->amount_due ?? ($inv->grand_total - ($inv->amount_paid ?? 0)));
        if ($balanceDue <= 0.001) {
            return null;
        }

        // Determine Channel Priority
        $preferredChannel = $manualChannel ?: ($customer?->preferred_channel ?: 'WHATSAPP');
        if (empty($preferredChannel) || $preferredChannel === 'NONE') {
            $preferredChannel = 'EMAIL';
        }

        // Determine Template
        $isOverdue = $rule->days_offset > 0;
        $tmplKey = $isOverdue ? 'OVERDUE_PAYMENT' : ($preferredChannel === 'WHATSAPP' ? 'PAYMENT_REMINDER_WA' : 'PAYMENT_REMINDER');

        $template = NotificationTemplate::where('company_id', $companyId)
            ->where('template_key', $tmplKey)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            $template = NotificationTemplate::where('company_id', $companyId)
                ->where('category', 'PAYMENTS')
                ->first();
        }

        $recipient = match ($preferredChannel) {
            'WHATSAPP' => $customer?->whatsapp_number ?: ($customer?->phone ?: ''),
            'SMS' => $customer?->phone ?: '',
            default => $customer?->email ?: '',
        };

        // If recipient is empty for preferred channel, fallback to Email
        if (empty($recipient) && $preferredChannel !== 'EMAIL' && !empty($customer?->email)) {
            $preferredChannel = 'EMAIL';
            $recipient = $customer->email;
        }

        $variables = [
            'customer_name' => $customer?->name ?: 'Customer',
            'invoice_number' => $inv->invoice_number,
            'invoice_total' => '₹ ' . number_format($inv->grand_total, 2),
            'balance_due' => '₹ ' . number_format($balanceDue, 2),
            'due_date' => date('d/m/Y', strtotime($inv->due_date ?: $inv->invoice_date)),
            'days_overdue' => (string)max(0, $rule->days_offset),
            'business_name' => $company?->name ?: 'WTSBill Business',
            'payment_link' => '', // Will populate if payment link exists
        ];

        $subject = $template ? TemplateService::renderSubject($template->subject, $variables) : "Payment Reminder for Invoice {$inv->invoice_number}";
        $body = $template ? TemplateService::render($template->body_template, $variables) : "Invoice {$inv->invoice_number} balance due: ₹{$balanceDue}";

        $idempotencyKey = "rem_{$companyId}_{$inv->id}_{$rule->id}_{$dateStr}";

        return NotificationDeliveryService::deliver(
            $companyId,
            $rule->event_type,
            'INVOICE',
            $inv->id,
            $preferredChannel,
            $recipient,
            $subject,
            $body,
            $template?->id,
            $inv->branch_id,
            $idempotencyKey,
            [
                'customer_id' => $customer?->id,
                'balance_due' => $balanceDue,
                'category' => 'PAYMENTS',
            ]
        );
    }

    /**
     * Send manual instant reminder.
     */
    public static function sendManualReminder(int $invoiceId, ?string $channel = null, ?string $customMessage = null): NotificationLog
    {
        $inv = Invoice::with(['customer', 'branch'])->findOrFail($invoiceId);
        $companyId = $inv->company_id;
        $customer = $inv->customer;
        $company = Company::find($companyId);

        $balanceDue = floatval($inv->amount_due ?? ($inv->grand_total - ($inv->amount_paid ?? 0)));
        if ($balanceDue <= 0.001) {
            throw new Exception("Invoice is fully paid. No reminder needed.");
        }

        $channel = strtoupper($channel ?: ($customer?->preferred_channel ?: 'EMAIL'));
        $recipient = match ($channel) {
            'WHATSAPP' => $customer?->whatsapp_number ?: ($customer?->phone ?: ''),
            'SMS' => $customer?->phone ?: '',
            default => $customer?->email ?: '',
        };

        if (empty($recipient)) {
            throw new Exception("No recipient contact found for channel '{$channel}'.");
        }

        $variables = [
            'customer_name' => $customer?->name ?: 'Customer',
            'invoice_number' => $inv->invoice_number,
            'invoice_total' => '₹ ' . number_format($inv->grand_total, 2),
            'balance_due' => '₹ ' . number_format($balanceDue, 2),
            'due_date' => date('d/m/Y', strtotime($inv->due_date ?: $inv->invoice_date)),
            'days_overdue' => (string)max(0, Carbon::today()->diffInDays(Carbon::parse($inv->due_date), false)),
            'business_name' => $company?->name ?: 'WTSBill Business',
        ];

        $subject = "Payment Reminder: Invoice {$inv->invoice_number}";
        $body = $customMessage ?: TemplateService::render(
            "Dear {{customer_name}}, payment reminder for Invoice {{invoice_number}} totaling {{invoice_total}}. Outstanding Due: {{balance_due}} (Due Date: {{due_date}}). Regards, {{business_name}}",
            $variables
        );

        $idempotencyKey = "manual_rem_{$companyId}_{$inv->id}_" . time();

        return NotificationDeliveryService::deliver(
            $companyId,
            'MANUAL_REMINDER',
            'INVOICE',
            $inv->id,
            $channel,
            $recipient,
            $subject,
            $body,
            null,
            $inv->branch_id,
            $idempotencyKey,
            ['category' => 'PAYMENTS']
        );
    }

    /**
     * Ensure default notification rules for company.
     */
    public static function ensureDefaultRules(int $companyId): void
    {
        $defaultRules = [
            ['event_type' => 'INVOICE_DUE_SOON', 'rule_name' => '3 Days Before Due Date', 'days_offset' => -3, 'channel_priority' => 'WHATSAPP,EMAIL'],
            ['event_type' => 'INVOICE_DUE_TODAY', 'rule_name' => 'On Due Date', 'days_offset' => 0, 'channel_priority' => 'WHATSAPP,EMAIL'],
            ['event_type' => 'INVOICE_OVERDUE', 'rule_name' => '1 Day Overdue', 'days_offset' => 1, 'channel_priority' => 'WHATSAPP,EMAIL'],
            ['event_type' => 'INVOICE_OVERDUE', 'rule_name' => '7 Days Overdue', 'days_offset' => 7, 'channel_priority' => 'WHATSAPP,EMAIL'],
            ['event_type' => 'INVOICE_OVERDUE', 'rule_name' => '15 Days Overdue', 'days_offset' => 15, 'channel_priority' => 'WHATSAPP,EMAIL'],
            ['event_type' => 'INVOICE_OVERDUE', 'rule_name' => '30 Days Overdue', 'days_offset' => 30, 'channel_priority' => 'WHATSAPP,EMAIL'],
        ];

        foreach ($defaultRules as $r) {
            NotificationRule::firstOrCreate(
                ['company_id' => $companyId, 'rule_name' => $r['rule_name'], 'days_offset' => $r['days_offset']],
                [
                    'event_type' => $r['event_type'],
                    'channel_priority' => $r['channel_priority'],
                    'is_active' => true,
                ]
            );
        }
    }
}
