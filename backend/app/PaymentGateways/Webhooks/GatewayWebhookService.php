<?php

namespace App\PaymentGateways\Webhooks;

use App\Models\PaymentGatewayWebhook;
use App\Models\PaymentGatewayTransaction;
use App\Models\PaymentLink;
use App\Models\Payment;
use App\Models\Invoice;
use App\Payments\Services\PaymentService;
use App\Payments\Allocations\PaymentAllocationService;
use App\Services\AccountingEventService;
use App\Services\JournalService;
use App\Services\AccountService;
use App\Notifications\Events\BusinessEvent;
use App\Notifications\Events\EventDispatcher;
use Illuminate\Database\Capsule\Manager as DB;
use Carbon\Carbon;
use Exception;
use RuntimeException;

class GatewayWebhookService
{
    /**
     * Process incoming gateway webhook event atomically with idempotency and signature validation.
     */
    public static function processWebhook(
        int $companyId,
        string $provider,
        string $eventId,
        string $eventType,
        string $rawPayload,
        string $signature,
        string $secret
    ): array {
        $provider = strtoupper($provider);

        // 1. Signature Verification
        $expectedSignature = hash_hmac('sha256', $rawPayload, $secret);
        if (!hash_equals($expectedSignature, $signature)) {
            return [
                'success' => false,
                'status' => 'INVALID_SIGNATURE',
                'message' => 'Gateway webhook signature verification failed.',
            ];
        }

        // 2. Idempotency Check: (provider + event_id)
        $existingWebhook = PaymentGatewayWebhook::where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();

        if ($existingWebhook) {
            return [
                'success' => true,
                'status' => 'DUPLICATE',
                'message' => 'Webhook event already processed previously.',
                'webhook_id' => $existingWebhook->id,
            ];
        }

        $payload = json_decode($rawPayload, true) ?: [];

        // Log Webhook Record
        $webhookLog = PaymentGatewayWebhook::create([
            'company_id' => $companyId,
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'signature' => $signature,
            'payload_json' => $payload,
            'status' => 'PROCESSED',
        ]);

        if ($eventType === 'PAYMENT_SUCCESS' || $eventType === 'payment.captured') {
            return self::handlePaymentSuccess($companyId, $provider, $payload, $webhookLog->id);
        }

        return [
            'success' => true,
            'status' => 'IGNORED_EVENT_TYPE',
            'message' => "Handled event {$eventType}",
        ];
    }

    /**
     * Handle verified successful gateway payment.
     */
    protected static function handlePaymentSuccess(int $companyId, string $provider, array $payload, int $webhookLogId): array
    {
        return DB::transaction(function () use ($companyId, $provider, $payload, $webhookLogId) {
            $token = $payload['token'] ?? ($payload['notes']['token'] ?? null);
            $gatewayPaymentId = $payload['payment_id'] ?? ($payload['id'] ?? ('pay_' . uniqid()));
            $gatewayOrderId = $payload['order_id'] ?? null;
            $grossAmount = round(floatval($payload['amount'] ?? 0), 2);
            $feeAmount = round(floatval($payload['fee'] ?? 0), 2);
            $taxAmount = round(floatval($payload['tax'] ?? 0), 2);
            $netAmount = round($grossAmount - ($feeAmount + $taxAmount), 2);

            $link = null;
            $invoice = null;

            if ($token) {
                $link = PaymentLink::where('company_id', $companyId)->where('token', $token)->first();
                if ($link) {
                    $invoice = Invoice::find($link->invoice_id);

                    // Verify amount matches expected payment link amount
                    if (abs($grossAmount - $link->amount) > 0.01) {
                        throw new RuntimeException("Gateway amount (₹{$grossAmount}) does not match expected Payment Link amount (₹{$link->amount}).");
                    }
                }
            }

            // Create Master Payment Record
            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $link?->branch_id,
                'financial_year' => '2026-27',
                'payment_number' => 'REC-GW-' . date('Ymd') . '-' . substr($gatewayPaymentId, -6),
                'receipt_number' => 'RCP-GW-' . date('Ymd') . '-' . substr($gatewayPaymentId, -6),
                'payment_type' => 'RECEIPT',
                'payment_date' => date('Y-m-d'),
                'party_type' => 'CUSTOMER',
                'party_id' => $invoice?->customer_id,
                'amount' => $grossAmount,
                'allocated_amount' => 0.00,
                'unallocated_amount' => $grossAmount,
                'currency' => 'INR',
                'payment_mode' => 'PAYMENT_GATEWAY',
                'transaction_reference' => $gatewayPaymentId,
                'notes' => "Payment via {$provider} Gateway (ID: {$gatewayPaymentId})",
                'status' => 'POSTED',
                'created_by' => $invoice?->created_by ?? \App\Http\Middleware\AuthMiddleware::getUser()?->id,
            ]);

            // Allocate to Invoice
            if ($invoice) {
                PaymentAllocationService::allocateCustomerPayment($payment, [
                    ['invoice_id' => $invoice->id, 'amount' => $grossAmount],
                ]);
            }

            // Record Gateway Transaction
            PaymentGatewayTransaction::create([
                'company_id' => $companyId,
                'payment_id' => $payment->id,
                'payment_link_id' => $link?->id,
                'provider' => $provider,
                'gateway_order_id' => $gatewayOrderId,
                'gateway_payment_id' => $gatewayPaymentId,
                'amount' => $grossAmount,
                'fee_amount' => $feeAmount,
                'tax_amount' => $taxAmount,
                'net_amount' => $netAmount,
                'status' => 'SUCCESS',
                'raw_payload_json' => $payload,
            ]);

            // Mark link as PAID
            if ($link) {
                $link->update([
                    'status' => 'PAID',
                    'paid_at' => Carbon::now(),
                    'payment_id' => $payment->id,
                ]);
            }

            // Accounting Entry: Separates Gross Customer AR vs Gateway Fee vs Net Bank Settlement
            $bankAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT');
            $recAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');
            $feeAcc = AccountService::getMappedAccount($companyId, 'PAYMENT_GATEWAY_CHARGES'); // Gateway fee account

            $lines = [
                [
                    'account_id' => $bankAcc->id,
                    'debit' => $netAmount,
                    'credit' => 0.00,
                    'narration' => "Net gateway settlement from {$provider}",
                ],
            ];

            if ($feeAmount > 0) {
                $lines[] = [
                    'account_id' => $feeAcc->id,
                    'debit' => round($feeAmount + $taxAmount, 2),
                    'credit' => 0.00,
                    'narration' => "{$provider} Gateway Processing Fee & Taxes",
                ];
            }

            $lines[] = [
                'account_id' => $recAcc->id,
                'debit' => 0.00,
                'credit' => $grossAmount,
                'narration' => "Gross settlement of Invoice #" . ($invoice?->invoice_number ?: 'INV'),
                'party_type' => 'CUSTOMER',
                'party_id' => $invoice?->customer_id,
            ];

            JournalService::createJournalEntry([
                'company_id' => $companyId,
                'financial_year' => '2026-27',
                'entry_date' => date('Y-m-d'),
                'entry_type' => 'RECEIPT',
                'reference_type' => 'GATEWAY_PAYMENT',
                'reference_id' => $gatewayPaymentId,
                'description' => "Gateway Settlement via {$provider}: Gross ₹{$grossAmount} (Fee ₹{$feeAmount})",
                'narration' => "Ref #{$gatewayPaymentId}",
                'status' => 'POSTED',
                'lines' => $lines,
            ]);

            // Dispatch Business Event
            EventDispatcher::dispatch(new BusinessEvent(
                $companyId,
                'PAYMENT_RECEIVED',
                'PAYMENT',
                $payment->id,
                $payment->branch_id,
                [
                    'amount' => $grossAmount,
                    'customer_name' => $invoice?->customer?->name ?: 'Customer',
                    'invoice_number' => $invoice?->invoice_number,
                ]
            ));

            return [
                'success' => true,
                'status' => 'SETTLED',
                'payment' => $payment,
                'gross_amount' => $grossAmount,
                'fee_amount' => $feeAmount,
                'net_amount' => $netAmount,
            ];
        });
    }
}
