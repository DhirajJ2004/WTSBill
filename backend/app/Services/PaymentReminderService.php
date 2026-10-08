<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\PaymentReminder;
use App\Models\PaymentAllocation;
use App\Models\CreditNote;

class PaymentReminderService
{
    /**
     * Fetch pending overdue payment reminders.
     */
    public static function getOverdueReminders(int $companyId, ?int $customerId = null): array
    {
        $today = date('Y-m-d');
        $query = Invoice::where('company_id', $companyId)
            ->where('status', '!=', 'CANCELLED')
            ->where('status', '!=', 'DRAFT')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->with('customer');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $overdueInvoices = $query->orderBy('due_date', 'asc')->get();
        $reminders = [];

        foreach ($overdueInvoices as $inv) {
            $paid = floatval(PaymentAllocation::where('company_id', $companyId)->where('document_type', 'INVOICE')->where('document_id', $inv->id)->sum('allocated_amount'));
            $cn = floatval(CreditNote::where('company_id', $companyId)->where('invoice_id', $inv->id)->where('status', '!=', 'CANCELLED')->sum('amount'));
            $due = max(0, round(floatval($inv->grand_total) - $paid - $cn, 2));

            if ($due <= 0.001) continue;

            $daysOverdue = intval((strtotime($today) - strtotime($inv->due_date)) / 86400);

            // Last reminder sent
            $lastReminder = PaymentReminder::where('company_id', $companyId)
                ->where('invoice_id', $inv->id)
                ->orderBy('sent_date', 'desc')
                ->first();

            $reminders[] = [
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->invoice_date,
                'due_date' => $inv->due_date,
                'customer_id' => $inv->customer_id,
                'customer_name' => $inv->customer ? $inv->customer->name : 'Customer',
                'customer_phone' => $inv->customer ? $inv->customer->phone : null,
                'customer_email' => $inv->customer ? $inv->customer->email : null,
                'grand_total' => floatval($inv->grand_total),
                'amount_paid' => $paid,
                'amount_due' => $due,
                'days_overdue' => $daysOverdue,
                'last_sent_date' => $lastReminder ? $lastReminder->sent_date : null,
                'last_channel' => $lastReminder ? $lastReminder->channel : null,
            ];
        }

        return $reminders;
    }

    /**
     * Record a payment reminder sent through a specific communication channel (WhatsApp / Email / SMS).
     */
    public static function sendReminder(int $companyId, array $data, ?string $userName = 'Admin'): PaymentReminder
    {
        $invoiceId = intval($data['invoice_id']);
        $invoice = Invoice::where('company_id', $companyId)->with('customer')->findOrFail($invoiceId);

        $today = date('Y-m-d');
        $daysOverdue = $invoice->due_date && $invoice->due_date < $today
            ? intval((strtotime($today) - strtotime($invoice->due_date)) / 86400)
            : 0;

        $reminder = PaymentReminder::create([
            'company_id' => $companyId,
            'branch_id' => $invoice->branch_id,
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'channel' => strtoupper($data['channel'] ?? 'WHATSAPP'),
            'sent_date' => $today,
            'days_overdue' => $daysOverdue,
            'outstanding_amount' => floatval($invoice->amount_due ?: $invoice->grand_total),
            'status' => 'SENT',
            'message_template_id' => $data['message_template_id'] ?? 'OVERDUE_PAYMENT_REMINDER',
            'notes' => $data['notes'] ?? "Reminder sent for Invoice #{$invoice->invoice_number}",
            'created_by' => $userName,
        ]);

        AuditLogService::log(
            $companyId,
            $userName,
            'PAYMENT_REMINDER_SENT',
            'PaymentReminder',
            $reminder->id,
            "Sent {$reminder->channel} reminder for Invoice #{$invoice->invoice_number} to Customer '{$invoice->customer->name}' (Overdue: {$daysOverdue} days)"
        );

        return $reminder->fresh(['customer', 'invoice']);
    }

    /**
     * Retrieve reminder history.
     */
    public static function getReminderHistory(int $companyId, ?int $customerId = null): array
    {
        $query = PaymentReminder::where('company_id', $companyId)
            ->with(['customer', 'invoice']);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        return $query->orderBy('sent_date', 'desc')->orderBy('id', 'desc')->get()->toArray();
    }
}
