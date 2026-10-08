<?php

namespace App\Payments\Refunds;

use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\CustomerCredit;
use App\Models\CustomerAdvance;
use App\Services\AccountingEventService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use RuntimeException;

class PaymentRefundService
{
    /**
     * Issue refund against a payment, customer advance or unallocated credit.
     */
    public static function processRefund(int $companyId, array $data, ?string $userName = 'System'): PaymentRefund
    {
        $paymentId = intval($data['payment_id'] ?? 0);
        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0.001) {
            throw new InvalidArgumentException("Refund amount must be greater than zero.");
        }

        return DB::transaction(function () use ($companyId, $paymentId, $amount, $data, $userName) {
            $payment = Payment::where('company_id', $companyId)->lockForUpdate()->findOrFail($paymentId);

            // Calculate existing refunds
            $existingRefunds = PaymentRefund::where('payment_id', $paymentId)
                ->whereIn('status', ['COMPLETED', 'PROCESSING', 'REQUESTED'])
                ->sum('amount');

            $maxRefundable = round($payment->amount - $existingRefunds, 2);

            // Invariant: Cannot refund more than refundable balance
            if ($amount > ($maxRefundable + 0.001)) {
                throw new RuntimeException("Refund amount (₹{$amount}) exceeds maximum refundable balance (₹{$maxRefundable}).");
            }

            $refundNumber = $data['refund_number'] ?? ('REF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

            $refund = PaymentRefund::create([
                'company_id' => $companyId,
                'branch_id' => $payment->branch_id,
                'financial_year' => $payment->financial_year ?? '2026-27',
                'payment_id' => $payment->id,
                'party_type' => $payment->party_type ?: 'CUSTOMER',
                'party_id' => $payment->party_id ?: 0,
                'refund_number' => $refundNumber,
                'refund_date' => $data['refund_date'] ?? date('Y-m-d'),
                'amount' => $amount,
                'reason' => $data['reason'] ?? 'Customer Refund Request',
                'payment_mode' => $data['payment_mode'] ?? $payment->payment_mode,
                'refund_mode' => $data['payment_mode'] ?? $payment->payment_mode,
                'bank_account_id' => $data['bank_account_id'] ?? $payment->bank_account_id,
                'reference_no' => $data['reference_no'] ?? null,
                'status' => 'COMPLETED',
                'created_by' => (string)($data['created_by'] ?? $userName ?: 'System'),
            ]);

            // Update payment status
            $totalRefunded = $existingRefunds + $amount;
            $newStatus = (abs($totalRefunded - $payment->amount) < 0.001) ? 'REFUNDED' : 'PARTIALLY_REFUNDED';
            $payment->update(['status' => $newStatus]);

            // Adjust unallocated balance or customer credit if any
            if ($payment->unallocated_amount > 0) {
                $deductFromUnallocated = min($payment->unallocated_amount, $amount);
                $payment->update([
                    'unallocated_amount' => max(0.00, round($payment->unallocated_amount - $deductFromUnallocated, 2)),
                ]);

                $credit = CustomerCredit::where('company_id', $companyId)
                    ->where('source_id', $payment->id)
                    ->where('credit_type', 'OVERPAYMENT')
                    ->first();
                if ($credit) {
                    $credit->remaining_amount = max(0.00, round($credit->remaining_amount - $deductFromUnallocated, 2));
                    $credit->status = ($credit->remaining_amount <= 0.001) ? 'FULLY_APPLIED' : 'ACTIVE';
                    $credit->save();
                }
            }

            // Post accounting refund journal entry
            AccountingEventService::recordPaymentRefundAccounting($refund, $userName);

            return $refund;
        });
    }
}
