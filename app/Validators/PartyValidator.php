<?php

namespace App\Validators;

use Illuminate\Database\Capsule\Manager as DB;

class PartyValidator
{
    /**
     * Validate Customer/Supplier payload.
     *
     * @param array $data Input data
     * @param int $companyId Active tenant company ID
     * @param string $type 'customer' | 'supplier'
     * @param int|null $excludeId ID to exclude on update
     * @return array Array of errors (empty if valid)
     */
    public static function validate(array $data, int $companyId, string $type = 'customer', ?int $excludeId = null): array
    {
        $errors = [];

        // 1. Name validation
        $name = trim($data['name'] ?? ($data['party_name'] ?? ''));
        if (empty($name)) {
            $errors['name'] = 'Party Name is required.';
        } elseif (strlen($name) < 2 || strlen($name) > 255) {
            $errors['name'] = 'Party Name must be between 2 and 255 characters.';
        }

        // 2. Email validation
        $email = trim($data['email'] ?? '');
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // 3. Phone validation
        $phone = trim($data['phone'] ?? '');
        if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $errors['phone'] = 'Please enter a valid phone number (7-20 digits).';
        }

        // 4. GSTIN validation & duplicate check per company
        $gstin = strtoupper(trim($data['gstin'] ?? ''));
        if (!empty($gstin)) {
            $gstRegex = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
            if (!preg_match($gstRegex, $gstin)) {
                $errors['gstin'] = 'Invalid GSTIN format. Expected 15-character Indian GST format (e.g., 27AAAAA0000A1Z5).';
            } else {
                $table = ($type === 'supplier') ? 'suppliers' : 'customers';
                $dupQuery = DB::table($table)
                    ->where('company_id', $companyId)
                    ->where('gstin', $gstin)
                    ->whereNull('deleted_at');

                if ($excludeId !== null && $excludeId > 0) {
                    $dupQuery->where('id', '!=', $excludeId);
                }

                if ($dupQuery->exists()) {
                    $errors['gstin'] = "A {$type} with GSTIN '{$gstin}' already exists in this company.";
                }
            }
        }

        // 5. PAN validation
        $pan = strtoupper(trim($data['pan'] ?? ''));
        if (!empty($pan) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan)) {
            $errors['pan'] = 'Invalid PAN format. Expected 10-character PAN (e.g., AAAAA0000A).';
        }

        // 6. Monetary amounts validation
        if (isset($data['opening_balance']) && $data['opening_balance'] !== '') {
            if (!is_numeric($data['opening_balance']) || (float)$data['opening_balance'] < 0) {
                $errors['opening_balance'] = 'Opening balance must be a non-negative number.';
            }
        }

        if (isset($data['credit_limit']) && $data['credit_limit'] !== '') {
            if (!is_numeric($data['credit_limit']) || (float)$data['credit_limit'] < 0) {
                $errors['credit_limit'] = 'Credit limit must be a non-negative number.';
            }
        }

        if (isset($data['payment_terms_days']) && $data['payment_terms_days'] !== '') {
            if (!is_numeric($data['payment_terms_days']) || (int)$data['payment_terms_days'] < 0) {
                $errors['payment_terms_days'] = 'Payment terms must be a non-negative integer of days.';
            }
        }

        return $errors;
    }

    public static function validateCustomer(array $data, int $companyId, ?int $excludeId = null): array
    {
        return self::validate($data, $companyId, 'customer', $excludeId);
    }

    public static function validateSupplier(array $data, int $companyId, ?int $excludeId = null): array
    {
        return self::validate($data, $companyId, 'supplier', $excludeId);
    }
}
