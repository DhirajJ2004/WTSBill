<?php

namespace App\PaymentGateways\Services;

use App\Models\PaymentLink;
use App\Models\PaymentLinkEvent;
use App\Models\Invoice;
use App\Models\Company;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

class PaymentLinkService
{
    /**
     * Create secure, expirable payment link for an invoice.
     */
    public static function createPaymentLink(
        int $companyId,
        int $invoiceId,
        ?float $customAmount = null,
        ?string $expiresAt = null,
        ?string $description = null
    ): PaymentLink {
        $invoice = Invoice::where('company_id', $companyId)->findOrFail($invoiceId);

        $outstanding = round(floatval($invoice->amount_due ?? ($invoice->grand_total - ($invoice->amount_paid ?? 0))), 2);
        if ($outstanding <= 0.001) {
            throw new RuntimeException("Invoice #{$invoice->invoice_number} is fully paid. Cannot generate payment link.");
        }

        $payableAmount = $customAmount !== null ? round(floatval($customAmount), 2) : $outstanding;
        if ($payableAmount <= 0) {
            throw new InvalidArgumentException("Payment link amount must be greater than zero.");
        }
        if ($payableAmount > ($outstanding + 0.001)) {
            throw new InvalidArgumentException("Payment link amount (₹{$payableAmount}) cannot exceed invoice balance due (₹{$outstanding}).");
        }

        $isPartial = ($payableAmount < $outstanding);
        $token = bin2hex(random_bytes(32)); // 64-char cryptographically secure token
        $expiryDate = $expiresAt ? Carbon::parse($expiresAt) : Carbon::now()->addDays(7);

        $link = PaymentLink::create([
            'company_id' => $companyId,
            'branch_id' => $invoice->branch_id,
            'link_id' => 'plink_' . substr($token, 0, 16),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'token' => $token,
            'amount' => $payableAmount,
            'original_invoice_amount' => $invoice->grand_total,
            'is_partial_allowed' => $isPartial,
            'min_amount' => $payableAmount,
            'status' => 'ACTIVE',
            'expires_at' => $expiryDate,
            'description' => $description ?: "Payment for Invoice #{$invoice->invoice_number}",
        ]);

        PaymentLinkEvent::create([
            'payment_link_id' => $link->id,
            'event_type' => 'CREATED',
            'payload_json' => ['amount' => $payableAmount, 'invoice' => $invoice->invoice_number],
        ]);

        return $link;
    }

    /**
     * Get sanitized public payment page payload by token.
     */
    public static function getPublicLinkDetails(string $token, ?string $ip = null, ?string $userAgent = null): array
    {
        $link = PaymentLink::where('token', $token)->with('invoice.company')->first();
        if (!$link) {
            throw new RuntimeException("Invalid payment link token.");
        }

        // Check Expiry
        if ($link->expires_at && Carbon::parse($link->expires_at)->lt(Carbon::now())) {
            if ($link->status === 'ACTIVE') {
                $link->update(['status' => 'EXPIRED']);
            }
        }

        PaymentLinkEvent::create([
            'payment_link_id' => $link->id,
            'event_type' => 'OPENED',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        $invoice = $link->invoice;
        $company = $invoice?->company;

        // Strictly sanitized public view: No internal IDs, internal accounts, or customer internal notes
        return [
            'token' => $link->token,
            'business_name' => $company?->name ?: 'WTSBill Business',
            'invoice_number' => $invoice?->invoice_number ?: 'INV-REF',
            'invoice_date' => $invoice?->invoice_date,
            'amount_payable' => $link->amount,
            'status' => $link->status,
            'expires_at' => $link->expires_at?->toIso8601String(),
            'is_expired' => ($link->status === 'EXPIRED'),
            'description' => $link->description,
        ];
    }
}
