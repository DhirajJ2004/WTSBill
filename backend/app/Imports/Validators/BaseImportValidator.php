<?php

namespace App\Imports\Validators;

abstract class BaseImportValidator
{
    /**
     * Validate a mapped row of data.
     * Returns: ['status' => 'VALID'|'WARNING'|'ERROR'|'DUPLICATE', 'issues' => [...], 'parsed' => [...], 'duplicate_key' => '...']
     */
    abstract public function validateRow(int $companyId, array $row, int $rowIndex): array;

    protected function validateRequired(array $data, string $field, string $label, array &$issues): bool
    {
        $val = trim((string)($data[$field] ?? ''));
        if ($val === '') {
            $issues[] = [
                'type' => 'ERROR',
                'field' => $field,
                'message' => "{$label} is required.",
                'suggested_fix' => "Please provide a valid {$label} value.",
            ];
            return false;
        }
        return true;
    }

    protected function validateGstin(string $gstin, array &$issues): bool
    {
        $clean = strtoupper(trim($gstin));
        if ($clean === '') {
            return true;
        }
        // Indian 15-character GSTIN pattern
        $pattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
        if (!preg_match($pattern, $clean)) {
            $issues[] = [
                'type' => 'ERROR',
                'field' => 'gstin',
                'value' => $gstin,
                'message' => 'Invalid GSTIN format. Expected 15-character alphanumeric (e.g. 27ABCDE1234F1Z5).',
                'suggested_fix' => 'Verify the 15-character State Code + PAN + Entity + Z + Check Digit format.',
            ];
            return false;
        }
        return true;
    }

    protected function validatePan(string $pan, array &$issues): bool
    {
        $clean = strtoupper(trim($pan));
        if ($clean === '') {
            return true;
        }
        $pattern = '/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/';
        if (!preg_match($pattern, $clean)) {
            $issues[] = [
                'type' => 'ERROR',
                'field' => 'pan',
                'value' => $pan,
                'message' => 'Invalid PAN format. Expected 10-character alphanumeric (e.g. ABCDE1234F).',
                'suggested_fix' => 'Verify the 5 letters + 4 digits + 1 letter structure.',
            ];
            return false;
        }
        return true;
    }

    protected function validatePhone(string $phone, array &$issues): bool
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if ($clean === '') {
            return true;
        }
        if (strlen($clean) < 10 || strlen($clean) > 12) {
            $issues[] = [
                'type' => 'WARNING',
                'field' => 'phone',
                'value' => $phone,
                'message' => 'Phone number format may be irregular (expected 10-digit mobile).',
                'suggested_fix' => 'Ensure a valid 10-digit Indian phone number.',
            ];
            return true;
        }
        return true;
    }

    protected function validateEmail(string $email, array &$issues): bool
    {
        $clean = trim($email);
        if ($clean === '') {
            return true;
        }
        if (!filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            $issues[] = [
                'type' => 'ERROR',
                'field' => 'email',
                'value' => $email,
                'message' => 'Invalid email address format.',
                'suggested_fix' => 'Use standard user@domain.com email format.',
            ];
            return false;
        }
        return true;
    }

    protected function validateNumeric(array $data, string $field, string $label, array &$issues, bool $allowNegative = false, float $default = 0.0): float
    {
        $val = trim((string)($data[$field] ?? ''));
        if ($val === '') {
            return $default;
        }
        if (!is_numeric($val)) {
            $issues[] = [
                'type' => 'ERROR',
                'field' => $field,
                'value' => $val,
                'message' => "{$label} must be a valid numeric value.",
                'suggested_fix' => 'Enter a numeric amount/number.',
            ];
            return $default;
        }
        $num = (float)$val;
        if (!$allowNegative && $num < 0) {
            $issues[] = [
                'type' => 'ERROR',
                'field' => $field,
                'value' => $val,
                'message' => "{$label} cannot be negative.",
                'suggested_fix' => 'Enter a value greater than or equal to 0.',
            ];
            return $default;
        }
        return $num;
    }
}
