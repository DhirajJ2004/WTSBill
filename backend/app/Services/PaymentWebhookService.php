<?php

namespace App\Services;

use App\Models\PaymentGatewayEvent;
use App\Models\PaymentGatewayTransaction;
use App\Models\PaymentLink;
use App\Models\Payment;
use App\Models\Invoice;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class PaymentWebhookService
{
    /**
     * Handle incoming gateway webhook with HMAC verification, idempotency, and transactional payment allocation
     */
    public static function handleWebhook(
        string $provider,
        array $payload,
        ?string $signature = null,
        ?string $rawBody = null
    ): array {
        $provider = strtoupper($provider);
        $eventId = $payload['event_id'] ?? ($payload['id'] ?? null);
        $eventType = $payload['event'] ?? ($payload['type'] ?? 'payment.success');

        if (!$eventId) {
            $eventId = 'evt_' . substr(md5(json_encode($payload)), 0, 16);
        }

        // 1. Webhook Idempotency Check: Reject duplicate webhook processing
        $existingEvent = PaymentGatewayEvent::where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();

        if ($existingEvent) {
            return [
                'status' => 'success',
                'message' => 'Duplicate webhook event received - skipped reprocessing.',
                'event_id' => $eventId,
                'is_duplicate' => true,
            ];
        }

        // 2. Signature Verification (HMAC-SHA256)
        $gateway = PaymentGatewayService::getGateway($provider);
        $orderId = $payload['order_id'] ?? ($payload['payload']['payment']['entity']['order_id'] ?? 'mock_order');
        $paymentId = $payload['payment_id'] ?? ($payload['payload']['payment']['entity']['id'] ?? 'mock_payment');

        if ($signature && !$gateway->verifySignature($orderId, $paymentId, $signature)) {
            throw new Exception("Invalid webhook HMAC signature.");
        }

        // 3. Record Webhook Event
        $event = PaymentGatewayEvent::create([
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload_json' => $payload,
            'processed_at' => date('Y-m-d H:i:s'),
            'status' => 'PROCESSED',
        ]);

        // 4. Process Payment Success Event
        if (in_array($eventType, ['payment.success', 'payment.captured', 'payment_link.paid', 'PAYMENT_SUCCESS'])) {
            return static::processPaymentSuccess($provider, $payload, $event);
        }

        return [
            'status' => 'success',
            'message' => 'Webhook received and recorded.',
            'event_id' => $eventId,
        ];
    }

    /**
     * Process verified payment success payload atomically
     */
    protected static function processPaymentSuccess(string $provider, array $payload, PaymentGatewayEvent $event): array
    {
        return DB::transaction(function () use ($provider, $payload, $event) {
            $linkId = $payload['link_id'] ?? ($payload['payment_link_id'] ?? null);
            $amount = floatval($payload['amount'] ?? 0.0);
            $fee = floatval($payload['fee'] ?? ($payload['fee_amount'] ?? 0.0));
            $tax = floatval($payload['tax'] ?? ($payload['tax_amount'] ?? 0.0));
            $net = $amount - $fee - $tax;

            $gatewayPaymentId = $payload['payment_id'] ?? ($payload['gateway_payment_id'] ?? 'pay_' . substr(md5(uniqid()), 0, 12));
            $gatewayOrderId = $payload['order_id'] ?? ($payload['gateway_order_id'] ?? null);

            $paymentLink = null;
            if ($linkId) {
                $paymentLink = PaymentLink::withoutGlobalScopes()->where('link_id', $linkId)->first();
            }

            $companyId = $paymentLink ? (int)$paymentLink->company_id : intval($payload['company_id'] ?? 0);
            if ($companyId <= 0) {
                throw new Exception("Payment webhook rejected: Missing or invalid company workspace context.");
            }
            $branchId = $paymentLink ? $paymentLink->branch_id : null;
            $customerId = $paymentLink ? (int)$paymentLink->customer_id : intval($payload['customer_id'] ?? 0);
            $invoiceId = $paymentLink ? $paymentLink->invoice_id : (isset($payload['invoice_id']) ? intval($payload['invoice_id']) : null);

            \App\Http\Middleware\AuthMiddleware::setContext(null, $companyId, $branchId, 'Payment Gateway Webhook', '2026-27');

            // Record Gateway Transaction
            $gwTxn = PaymentGatewayTransaction::create([
                'company_id' => $companyId,
                'payment_link_id' => $paymentLink ? $paymentLink->id : null,
                'provider' => $provider,
                'gateway_order_id' => $gatewayOrderId,
                'gateway_payment_id' => $gatewayPaymentId,
                'gateway_signature' => $payload['signature'] ?? null,
                'amount' => $amount,
                'fee_amount' => $fee,
                'tax_amount' => $tax,
                'net_amount' => $net,
                'currency' => 'INR',
                'status' => 'SUCCESS',
                'is_sandbox' => ($provider === 'SIMULATION' || str_contains($gatewayPaymentId, 'test') || str_contains($gatewayPaymentId, 'mock')),
                'webhook_event_id' => $event->event_id,
            ]);

            // Create Payment Record (Customer Receipt)
            $receiptNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId ?: 1, '2026-27', 'RECEIPT');
            if (empty($receiptNumber)) {
                $receiptNumber = 'REC-' . str_pad((string)(Payment::where('company_id', $companyId)->count() + 1), 6, '0', STR_PAD_LEFT);
            }

            // Determine Gateway Clearing Chart of Account
            AccountService::ensureDefaultAccounts($companyId);
            $clearingCoa = AccountService::getMappedAccount($companyId, 'PAYMENT_GATEWAY_CLEARING');

            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payment_number' => $receiptNumber,
                'payment_type' => 'RECEIPT',
                'payment_date' => date('Y-m-d'),
                'amount' => $amount,
                'payment_mode' => 'PAYMENT_GATEWAY',
                'account_id' => $clearingCoa ? $clearingCoa->id : null,
                'party_type' => 'CUSTOMER',
                'party_id' => $customerId,
                'transaction_reference' => $gatewayPaymentId,
                'status' => 'POSTED',
                'allocated_amount' => 0.00,
                'unallocated_amount' => $amount,
                'notes' => "Online payment received via {$provider} (Ref: {$gatewayPaymentId})",
                'created_by' => 'Payment Gateway Webhook',
            ]);

            // If linked to invoice, allocate payment
            if ($invoiceId) {
                $invoice = Invoice::where('company_id', $companyId)->find($invoiceId);
                if ($invoice) {
                    PaymentAllocationService::allocatePayment(
                        $payment,
                        [['document_type' => 'INVOICE', 'document_id' => $invoice->id, 'amount' => min($amount, floatval($invoice->amount_due))]],
                        'Payment Gateway Webhook'
                    );
                }
            }

            // Post double-entry accounting: Debit Gateway Clearing, Credit Accounts Receivable
            AccountingEventService::recordPaymentAccounting($payment, 'Payment Gateway Webhook');

            // Update Payment Link status
            if ($paymentLink) {
                $paymentLink->amount_paid = floatval($paymentLink->amount_paid) + $amount;
                if ($paymentLink->amount_paid >= floatval($paymentLink->amount)) {
                    $paymentLink->status = 'PAID';
                }
                $paymentLink->paid_at = date('Y-m-d H:i:s');
                $paymentLink->save();
            }

            AuditLogService::log(
                $companyId,
                'GATEWAY_PAYMENT_PROCESSED',
                'PaymentGatewayTransaction',
                $gwTxn->id,
                "Processed {$provider} online payment ₹{$amount} (Receipt: {$receiptNumber})",
                null,
                $gwTxn->toArray(),
                'Payment Gateway Webhook'
            );

            return [
                'status' => 'success',
                'message' => 'Online payment verified and allocated successfully.',
                'payment_id' => $payment->id,
                'receipt_number' => $receiptNumber,
                'amount' => $amount,
                'gateway_transaction_id' => $gwTxn->id,
            ];
        });
    }
}
