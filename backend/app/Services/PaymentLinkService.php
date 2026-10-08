<?php

namespace App\Services;

use App\Models\PaymentLink;
use App\Models\Invoice;
use App\Models\Customer;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class PaymentLinkService
{
    /**
     * Generate a new secure payment link for an invoice or customer
     */
    public static function createPaymentLink(
        int $companyId,
        ?int $invoiceId = null,
        ?int $customerId = null,
        ?float $amount = null,
        bool $allowPartial = false,
        int $expiryDays = 7,
        string $provider = 'SIMULATION',
        ?int $branchId = null,
        ?string $userName = 'System'
    ): PaymentLink {
        return DB::transaction(function () use (
            $companyId, $invoiceId, $customerId, $amount, $allowPartial, $expiryDays, $provider, $branchId, $userName
        ) {
            $invoice = null;
            $customer = null;
            $payableAmount = 0.0;

            if ($invoiceId) {
                $invoice = Invoice::where('company_id', $companyId)->findOrFail($invoiceId);
                if ($invoice->status === 'DRAFT' || $invoice->status === 'CANCELLED') {
                    throw new Exception("Cannot create payment link for an invoice in {$invoice->status} status.");
                }

                $due = round(floatval($invoice->amount_due ?: ($invoice->grand_total - floatval($invoice->amount_paid))), 2);
                if ($due <= 0) {
                    throw new Exception("Invoice #{$invoice->invoice_number} is already fully paid.");
                }

                $payableAmount = ($amount !== null && $amount > 0) ? min(floatval($amount), $due) : $due;
                $customerId = $invoice->customer_id;
                $customer = Customer::find($customerId);
            } elseif ($customerId) {
                $customer = Customer::where('company_id', $companyId)->findOrFail($customerId);
                if ($amount === null || $amount <= 0) {
                    throw new Exception("Amount is required when creating a standalone payment link for customer.");
                }
                $payableAmount = round(floatval($amount), 2);
            } else {
                throw new Exception("Either invoice_id or customer_id must be provided.");
            }

            // Generate through gateway provider abstraction
            $gateway = PaymentGatewayService::getGateway($provider);
            $gatewayRes = $gateway->createPaymentLink([
                'amount' => $payableAmount,
                'currency' => 'INR',
                'description' => $invoice ? "Payment for Invoice #{$invoice->invoice_number}" : "Payment from {$customer->name}",
            ]);

            $expiryDate = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));

            $link = PaymentLink::create([
                'company_id' => $companyId,
                'branch_id' => $branchId ?: ($invoice ? $invoice->branch_id : null),
                'link_id' => $gatewayRes['link_id'],
                'invoice_id' => $invoiceId,
                'customer_id' => $customerId,
                'amount' => $payableAmount,
                'amount_paid' => 0.00,
                'currency' => 'INR',
                'provider' => strtoupper($provider),
                'external_reference' => $gatewayRes['external_reference'] ?? null,
                'url' => $gatewayRes['url'] ?? null,
                'status' => 'CREATED',
                'allow_partial' => $allowPartial,
                'expiry_date' => $expiryDate,
            ]);

            AuditLogService::log(
                $companyId,
                'PAYMENT_LINK_CREATED',
                'PaymentLink',
                $link->id,
                "Generated payment link {$link->link_id} for ₹{$payableAmount}",
                null,
                $link->toArray(),
                $userName
            );

            return $link;
        });
    }

    /**
     * Get Payment Link by ID or Link ID
     */
    public static function getPaymentLink(int $companyId, string $identifier): ?array
    {
        $query = PaymentLink::where('company_id', $companyId)->with(['invoice', 'customer', 'transactions']);
        if (is_numeric($identifier)) {
            $query->where('id', intval($identifier));
        } else {
            $query->where('link_id', $identifier);
        }

        $link = $query->first();
        if (!$link) return null;

        return [
            'id' => $link->id,
            'company_id' => $link->company_id,
            'link_id' => $link->link_id,
            'invoice_id' => $link->invoice_id,
            'invoice_number' => $link->invoice ? $link->invoice->invoice_number : null,
            'customer_id' => $link->customer_id,
            'customer_name' => $link->customer ? $link->customer->name : null,
            'amount' => floatval($link->amount),
            'amount_paid' => floatval($link->amount_paid),
            'currency' => $link->currency,
            'provider' => $link->provider,
            'url' => $link->url,
            'status' => $link->status,
            'allow_partial' => (bool)$link->allow_partial,
            'expiry_date' => $link->expiry_date ? $link->expiry_date->toDateTimeString() : null,
            'paid_at' => $link->paid_at ? $link->paid_at->toDateTimeString() : null,
            'created_at' => $link->created_at ? $link->created_at->toDateTimeString() : null,
        ];
    }

    /**
     * Cancel Payment Link
     */
    public static function cancelPaymentLink(int $companyId, int $id, ?string $userName = 'System'): bool
    {
        $link = PaymentLink::where('company_id', $companyId)->findOrFail($id);
        if ($link->status === 'PAID') {
            throw new Exception("Cannot cancel an already paid payment link.");
        }

        $link->status = 'CANCELLED';
        $link->save();

        AuditLogService::log(
            $companyId,
            'PAYMENT_LINK_CANCELLED',
            'PaymentLink',
            $link->id,
            "Cancelled payment link {$link->link_id}",
            null,
            $link->toArray(),
            $userName
        );

        return true;
    }

    /**
     * List payment links
     */
    public static function listPaymentLinks(int $companyId, array $filters = []): array
    {
        $query = PaymentLink::where('company_id', $companyId)->with(['invoice', 'customer']);

        if (!empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }
        if (!empty($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        $items = $query->orderBy('id', 'desc')->get();

        return $items->map(function ($link) {
            return [
                'id' => $link->id,
                'link_id' => $link->link_id,
                'invoice_id' => $link->invoice_id,
                'invoice_number' => $link->invoice ? $link->invoice->invoice_number : null,
                'customer_id' => $link->customer_id,
                'customer_name' => $link->customer ? $link->customer->name : null,
                'amount' => floatval($link->amount),
                'amount_paid' => floatval($link->amount_paid),
                'status' => $link->status,
                'url' => $link->url,
                'expiry_date' => $link->expiry_date ? $link->expiry_date->toDateTimeString() : null,
                'paid_at' => $link->paid_at ? $link->paid_at->toDateTimeString() : null,
                'created_at' => $link->created_at ? $link->created_at->toDateTimeString() : null,
            ];
        })->toArray();
    }
}
