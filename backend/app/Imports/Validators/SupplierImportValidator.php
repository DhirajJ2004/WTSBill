<?php

namespace App\Imports\Validators;

use App\Models\Supplier;

class SupplierImportValidator extends BaseImportValidator
{
    public function validateRow(int $companyId, array $row, int $rowIndex): array
    {
        $issues = [];
        $this->validateRequired($row, 'supplier_name', 'Supplier Name', $issues);

        $name = trim((string)($row['supplier_name'] ?? ''));
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

        // Check duplicate
        $isDuplicate = false;
        $existing = null;
        if ($phone !== '') {
            $existing = Supplier::where('company_id', $companyId)->where('phone', $phone)->first();
        }
        if (!$existing && $gstin !== '') {
            $existing = Supplier::where('company_id', $companyId)->where('gstin', $gstin)->first();
        }
        if (!$existing && $name !== '') {
            $existing = Supplier::where('company_id', $companyId)->where('name', $name)->first();
        }

        if ($existing) {
            $isDuplicate = true;
            $issues[] = [
                'type' => 'WARNING',
                'field' => 'supplier_name',
                'message' => "Duplicate supplier identified (Matches existing supplier #{$existing->id}: '{$existing->name}').",
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
                'state' => $row['state'] ?? null,
                'address' => $row['address'] ?? null,
                'opening_balance' => $openingBal,
                'bank_name' => $row['bank_name'] ?? null,
                'bank_account_number' => $row['bank_account_number'] ?? null,
                'ifsc_code' => $row['ifsc_code'] ?? null,
            ],
        ];
    }
}
