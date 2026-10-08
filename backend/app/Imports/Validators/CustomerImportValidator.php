<?php

namespace App\Imports\Validators;

use App\Models\Customer;

class CustomerImportValidator extends BaseImportValidator
{
    public function validateRow(int $companyId, array $row, int $rowIndex): array
    {
        $issues = [];
        $this->validateRequired($row, 'customer_name', 'Customer Name', $issues);

        $name = trim((string)($row['customer_name'] ?? ''));
        $phone = trim((string)($row['phone'] ?? ''));
        $email = trim((string)($row['email'] ?? ''));
        $gstin = strtoupper(trim((string)($row['gstin'] ?? '')));
        $pan = strtoupper(trim((string)($row['pan'] ?? '')));

        if ($phone !== '') {
            $this->validatePhone($phone, $issues);
        }
        if ($email !== '') {
            $this->validateEmail($email, $issues);
        }
        if ($gstin !== '') {
            $this->validateGstin($gstin, $issues);
        }
        if ($pan !== '') {
            $this->validatePan($pan, $issues);
        }

        $openingBal = $this->validateNumeric($row, 'opening_balance', 'Opening Balance', $issues, true, 0.0);
        $creditLimit = $this->validateNumeric($row, 'credit_limit', 'Credit Limit', $issues, false, 0.0);
        $creditDays = (int)$this->validateNumeric($row, 'credit_period_days', 'Credit Period Days', $issues, false, 0.0);

        // Check duplicate
        $isDuplicate = false;
        $existing = null;
        if ($phone !== '') {
            $existing = Customer::where('company_id', $companyId)->where('phone', $phone)->first();
        }
        if (!$existing && $gstin !== '') {
            $existing = Customer::where('company_id', $companyId)->where('gstin', $gstin)->first();
        }
        if (!$existing && $name !== '') {
            $existing = Customer::where('company_id', $companyId)->where('name', $name)->first();
        }

        if ($existing) {
            $isDuplicate = true;
            $issues[] = [
                'type' => 'WARNING',
                'field' => 'customer_name',
                'message' => "Duplicate customer identified (Matches existing customer #{$existing->id}: '{$existing->name}').",
                'suggested_fix' => 'Choose duplicate action: Skip, Update, or Create New.',
            ];
        }

        $hasErrors = count(array_filter($issues, fn($i) => $i['type'] === 'ERROR')) > 0;
        $hasWarnings = count(array_filter($issues, fn($i) => $i['type'] === 'WARNING')) > 0;

        $status = 'VALID';
        if ($hasErrors) {
            $status = 'ERROR';
        } elseif ($isDuplicate) {
            $status = 'DUPLICATE';
        } elseif ($hasWarnings) {
            $status = 'WARNING';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'is_duplicate' => $isDuplicate,
            'existing_id' => $existing?->id,
            'parsed' => [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'gstin' => $gstin,
                'pan' => $pan,
                'tax_type' => $row['tax_type'] ?? ($gstin ? 'REGISTERED_REGULAR' : 'UNREGISTERED'),
                'state' => $row['state'] ?? null,
                'billing_address' => $row['billing_address'] ?? null,
                'shipping_address' => $row['shipping_address'] ?? null,
                'opening_balance' => $openingBal,
                'credit_limit' => $creditLimit,
                'credit_period_days' => $creditDays,
            ],
        ];
    }
}
