<?php

namespace App\Recurring\Generators;

use App\Models\RecurringTransaction;
use App\Models\Payment;
use RuntimeException;

class RecurringPaymentGenerator
{
    /**
     * Generate draft payment instruction record.
     */
    public static function generate(RecurringTransaction $template, string $occurrenceDate): Payment
    {
        $companyId = $template->company_id;
        $branchId = $template->branch_id;
        $payload = $template->template_payload_json ?: [];

        $amount = floatval($payload['amount'] ?? 0.0);
        if ($amount <= 0) {
            throw new RuntimeException("Recurring payment amount must be greater than zero.");
        }

        $payment = Payment::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $payload['customer_id'] ?? null,
            'supplier_id' => $payload['supplier_id'] ?? null,
            'payment_type' => $payload['payment_type'] ?? 'PAYMENT_MADE',
            'payment_number' => $payload['payment_number_prefix'] ?? ('PAY-REC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4))),
            'payment_date' => $occurrenceDate,
            'amount' => $amount,
            'payment_mode' => $payload['payment_mode'] ?? 'BANK_TRANSFER',
            'reference_number' => "REC-TPL-{$template->id}",
            'notes' => "Draft scheduled payment from recurring template: {$template->name}",
            'status' => 'DRAFT',
        ]);

        return $payment;
    }
}
