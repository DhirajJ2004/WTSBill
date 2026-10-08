<?php

namespace App\Validators;

class ExpenseValidator
{
    /**
     * Validate operating expense payload.
     *
     * @param array $data
     * @param int $companyId
     * @return array Validation errors map (empty if valid)
     */
    public static function validate(array $data, int $companyId): array
    {
        $errors = [];

        // 1. Expense Category
        if (empty($data['category'])) {
            $errors['category'] = 'Expense category is required.';
        }

        // 2. Amount Validation (Positive non-zero amount)
        $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
        if ($amount <= 0) {
            $errors['amount'] = 'Expense amount must be greater than zero. Negative or zero values are not permitted.';
        }

        // 3. Date Validation
        if (!empty($data['expense_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['expense_date'])) {
            $errors['expense_date'] = 'Expense date must be in YYYY-MM-DD format.';
        }

        // 4. Tax Amount Validation
        $taxAmount = isset($data['tax_amount']) ? (float)$data['tax_amount'] : 0.0;
        if ($taxAmount < 0) {
            $errors['tax_amount'] = 'Tax amount cannot be negative.';
        }

        // 5. Payment Mode Validation
        $mode = strtoupper($data['payment_mode'] ?? 'CASH');
        $validModes = ['CASH', 'BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE', 'NET_BANKING', 'CREDIT', 'PAYABLE', 'OTHER'];
        if (!in_array($mode, $validModes, true)) {
            $errors['payment_mode'] = 'Invalid payment mode selected.';
        }

        return $errors;
    }
}
