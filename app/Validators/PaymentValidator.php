<?php

namespace App\Validators;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Payment;

class PaymentValidator
{
    /**
     * Validate payment / receipt payload.
     *
     * @param array $data
     * @param int $companyId
     * @return array Validation errors map (empty if valid)
     */
    public static function validate(array $data, int $companyId): array
    {
        $errors = [];

        // 1. Payment Type
        $type = strtoupper($data['payment_type'] ?? 'RECEIPT');
        if (!in_array($type, ['RECEIPT', 'PAYMENT', 'CUSTOMER_PAYMENT', 'SUPPLIER_PAYMENT'], true)) {
            $errors['payment_type'] = 'Payment type must be RECEIPT or PAYMENT.';
        }

        // 2. Party Type & Party ID (Strict Multi-Tenant Scoping)
        $partyType = strtoupper($data['party_type'] ?? ($type === 'RECEIPT' || $type === 'CUSTOMER_PAYMENT' ? 'CUSTOMER' : 'SUPPLIER'));
        $partyId = isset($data['party_id']) ? (int)$data['party_id'] : (isset($data['customer_id']) ? (int)$data['customer_id'] : (isset($data['supplier_id']) ? (int)$data['supplier_id'] : 0));

        if ($partyId <= 0) {
            $errors['party_id'] = ($partyType === 'CUSTOMER' ? 'Customer' : 'Supplier') . ' is required. Please select a valid party.';
        } else {
            if ($partyType === 'CUSTOMER') {
                $customer = Customer::withoutGlobalScopes()
                    ->where('id', $partyId)
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$customer) {
                    $otherComp = Customer::withoutGlobalScopes()->where('id', $partyId)->first();
                    if ($otherComp && (int)$otherComp->company_id !== $companyId) {
                        $errors['party_id'] = 'Forbidden: Selected customer belongs to another company context.';
                    } else {
                        $errors['party_id'] = 'Selected customer does not exist or is unauthorized.';
                    }
                }
            } else {
                $supplier = Supplier::withoutGlobalScopes()
                    ->where('id', $partyId)
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$supplier) {
                    $otherComp = Supplier::withoutGlobalScopes()->where('id', $partyId)->first();
                    if ($otherComp && (int)$otherComp->company_id !== $companyId) {
                        $errors['party_id'] = 'Forbidden: Selected supplier belongs to another company context.';
                    } else {
                        $errors['party_id'] = 'Selected supplier does not exist or is unauthorized.';
                    }
                }
            }
        }

        // 3. Amount Validation (Positive non-zero amount)
        $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
        if ($amount <= 0) {
            $errors['amount'] = 'Payment amount must be greater than zero. Negative or zero values are not allowed.';
        }

        // 4. Date Validation
        if (!empty($data['payment_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['payment_date'])) {
            $errors['payment_date'] = 'Payment date must be in YYYY-MM-DD format.';
        }

        // 5. Payment Mode Validation
        $mode = strtoupper($data['payment_mode'] ?? 'CASH');
        $validModes = ['CASH', 'BANK_TRANSFER', 'UPI', 'CHEQUE', 'CARD', 'NET_BANKING', 'NEFT', 'RTGS', 'OTHER'];
        if (!in_array($mode, $validModes, true)) {
            $errors['payment_mode'] = 'Invalid payment mode selected.';
        }

        // 6. Cheque details if CHEQUE
        if ($mode === 'CHEQUE' && empty($data['cheque_number'])) {
            $errors['cheque_number'] = 'Cheque number is required when payment mode is Cheque.';
        }

        // 7. Duplicate transaction reference / UTR check
        $ref = !empty($data['transaction_reference']) ? trim($data['transaction_reference']) : (!empty($data['utr']) ? trim($data['utr']) : null);
        if (!empty($ref)) {
            $dupExists = Payment::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($ref) {
                    $q->where('transaction_reference', $ref)
                      ->orWhere('utr', $ref);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($dupExists) {
                $errors['transaction_reference'] = "Duplicate payment: Transaction reference / UTR '{$ref}' has already been processed.";
            }
        }

        return $errors;
    }
}
