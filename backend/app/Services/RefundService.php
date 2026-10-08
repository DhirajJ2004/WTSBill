<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Capsule\Manager as DB;

class RefundService
{
    /**
     * Create customer or supplier refund.
     */
    public static function createRefund(array $data, ?string $createdByName = 'Admin'): PaymentRefund
    {
        return DB::transaction(function () use ($data, $createdByName) {
            $companyId = intval($data['company_id']);
            $branchId = !empty($data['branch_id']) ? intval($data['branch_id']) : null;
            $fy = $data['financial_year'] ?? '2026-27';
            $partyType = strtoupper(trim($data['party_type'] ?? 'CUSTOMER'));
            $partyId = intval($data['party_id']);
            $amount = round(floatval($data['amount']), 2);
            $paymentId = !empty($data['payment_id']) ? intval($data['payment_id']) : null;

            if ($amount <= 0) {
                throw new \InvalidArgumentException("Refund amount must be greater than zero. Provided: {$amount}");
            }

            if ($partyType === 'CUSTOMER') {
                Customer::where('company_id', $companyId)->findOrFail($partyId);
            } else {
                Supplier::where('company_id', $companyId)->findOrFail($partyId);
            }

            if ($paymentId) {
                $payment = Payment::where('company_id', $companyId)->findOrFail($paymentId);
                $availableAdvance = floatval($payment->unallocated_amount);
                if ($amount > $availableAdvance + 0.001) {
                    throw new \InvalidArgumentException(
                        "Refund amount (₹{$amount}) exceeds available payment unallocated advance (₹{$availableAdvance})."
                    );
                }

                // Deduct from unallocated advance
                $newUnallocated = max(0, round($availableAdvance - $amount, 2));
                $payment->update(['unallocated_amount' => $newUnallocated]);
            }

            $refundNo = $data['refund_number'] ?? DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'REFUND');

            $refund = PaymentRefund::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'financial_year' => $fy,
                'refund_number' => $refundNo,
                'payment_id' => $paymentId,
                'party_type' => $partyType,
                'party_id' => $partyId,
                'amount' => $amount,
                'refund_date' => $data['refund_date'] ?? date('Y-m-d'),
                'refund_mode' => strtoupper($data['refund_mode'] ?? 'CASH'),
                'reference_no' => $data['reference_no'] ?? null,
                'reason' => $data['reason'] ?? 'Advance / Overpayment Refund',
                'status' => 'POSTED',
                'created_by' => $createdByName,
            ]);

            AuditLogService::log(
                $companyId,
                $createdByName,
                'PAYMENT_REFUND',
                'PaymentRefund',
                $refund->id,
                "Issued Refund #{$refund->refund_number} of ₹{$amount} to {$partyType} #{$partyId}: Reason - {$refund->reason}"
            );

            return $refund->fresh(['customer', 'supplier', 'payment']);
        });
    }

    /**
     * List refunds.
     */
    public static function getRefunds(int $companyId, ?string $partyType = null, ?int $partyId = null): array
    {
        $query = PaymentRefund::where('company_id', $companyId)
            ->with(['customer', 'supplier', 'payment']);

        if ($partyType) {
            $query->where('party_type', strtoupper($partyType));
        }
        if ($partyId) {
            $query->where('party_id', $partyId);
        }

        return $query->orderBy('refund_date', 'desc')->get()->toArray();
    }
}
