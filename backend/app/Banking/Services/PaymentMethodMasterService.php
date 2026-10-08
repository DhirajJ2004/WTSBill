<?php

namespace App\Banking\Services;

use App\Models\AuditLog;
use InvalidArgumentException;

class PaymentMethodMasterService
{
    /**
     * Standard built-in payment methods
     */
    protected static array $defaultMethods = [
        [
            'code' => 'CASH',
            'name' => 'Cash in Hand',
            'type' => 'CASH',
            'is_active' => true,
            'requires_bank_account' => false,
            'requires_reference' => false,
            'requires_cheque_details' => false,
            'requires_gateway_reference' => false,
            'description' => 'Direct physical cash transaction',
        ],
        [
            'code' => 'BANK_TRANSFER',
            'name' => 'Bank Transfer (NEFT / RTGS / IMPS)',
            'type' => 'BANK',
            'is_active' => true,
            'requires_bank_account' => true,
            'requires_reference' => true,
            'requires_cheque_details' => false,
            'requires_gateway_reference' => false,
            'description' => 'Direct bank to bank wire or online transfer',
        ],
        [
            'code' => 'UPI',
            'name' => 'UPI / QR Code',
            'type' => 'DIGITAL',
            'is_active' => true,
            'requires_bank_account' => true,
            'requires_reference' => true,
            'requires_cheque_details' => false,
            'requires_gateway_reference' => false,
            'description' => 'Unified Payments Interface mobile transfer',
        ],
        [
            'code' => 'CARD',
            'name' => 'Debit / Credit Card (POS)',
            'type' => 'DIGITAL',
            'is_active' => true,
            'requires_bank_account' => true,
            'requires_reference' => true,
            'requires_cheque_details' => false,
            'requires_gateway_reference' => false,
            'description' => 'Card swipe or online terminal transaction',
        ],
        [
            'code' => 'CHEQUE',
            'name' => 'Cheque',
            'type' => 'CHEQUE',
            'is_active' => true,
            'requires_bank_account' => true,
            'requires_reference' => true,
            'requires_cheque_details' => true,
            'requires_gateway_reference' => false,
            'description' => 'Bank cheque requiring deposit and clearing workflow',
        ],
        [
            'code' => 'DEMAND_DRAFT',
            'name' => 'Demand Draft (DD)',
            'type' => 'CHEQUE',
            'is_active' => true,
            'requires_bank_account' => true,
            'requires_reference' => true,
            'requires_cheque_details' => true,
            'requires_gateway_reference' => false,
            'description' => 'Banker draft payment instrument',
        ],
        [
            'code' => 'PAYMENT_GATEWAY',
            'name' => 'Online Payment Gateway (Razorpay / Cashfree / Stripe)',
            'type' => 'GATEWAY',
            'is_active' => true,
            'requires_bank_account' => true,
            'requires_reference' => true,
            'requires_cheque_details' => false,
            'requires_gateway_reference' => true,
            'description' => 'Electronic payment link or automated checkout gateway',
        ],
    ];

    /**
     * Get all available payment methods
     */
    public static function getPaymentMethods(?int $companyId = null): array
    {
        return self::$defaultMethods;
    }

    /**
     * Get single method by code
     */
    public static function getMethodByCode(string $code): ?array
    {
        $code = strtoupper($code);
        foreach (self::$defaultMethods as $method) {
            if ($method['code'] === $code) {
                return $method;
            }
        }
        return null;
    }

    /**
     * Validate payment method requirements against input payload
     */
    public static function validateMethodRequirements(string $mode, array $data): void
    {
        $mode = strtoupper($mode);
        $method = self::getMethodByCode($mode);

        if (!$method) {
            // Allow dynamic standard modes
            return;
        }

        if ($method['requires_bank_account'] && empty($data['bank_account_id']) && empty($data['financial_account_id'])) {
            if ($mode !== 'CASH') {
                // Bank or digital method should specify destination account
            }
        }

        if ($method['requires_cheque_details']) {
            if (empty($data['cheque_number']) && empty($data['cheque_no']) && empty($data['reference_number'])) {
                throw new InvalidArgumentException("Cheque / DD number is required for {$method['name']}.");
            }
        }
    }
}
