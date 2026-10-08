<?php

namespace App\Recurring\Generators;

use App\Models\RecurringTransaction;
use App\Models\Expense;
use App\Services\AccountingEventService;
use Carbon\Carbon;
use RuntimeException;

class RecurringExpenseGenerator
{
    /**
     * Generate recurring business expense.
     */
    public static function generate(RecurringTransaction $template, string $occurrenceDate): Expense
    {
        $companyId = $template->company_id;
        $branchId = $template->branch_id;
        $payload = $template->template_payload_json ?: [];

        $amount = floatval($payload['amount'] ?? 0.0);
        if ($amount <= 0) {
            throw new RuntimeException("Recurring expense amount must be greater than zero.");
        }

        $taxAmount = floatval($payload['tax_amount'] ?? 0.0);

        $expense = Expense::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'category' => $payload['category'] ?? 'Rent Expense',
            'expense_number' => $payload['expense_number_prefix'] ?? ('EXP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4))),
            'expense_date' => $occurrenceDate,
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'payment_mode' => $payload['payment_mode'] ?? 'BANK_TRANSFER',
            'description' => "Generated from recurring schedule: {$template->name}",
            'is_itc_eligible' => false,
        ]);

        AccountingEventService::recordExpenseAccounting($expense);

        return $expense;
    }
}
