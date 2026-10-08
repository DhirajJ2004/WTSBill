<?php

namespace App\Services;

class SandboxPaymentGateway implements PaymentGatewayInterface
{
    public function createPaymentLink(array $data): array
    {
        $linkId = 'plink_' . substr(md5(uniqid('sandbox_', true)), 0, 16);
        $amount = floatval($data['amount'] ?? 0);

        return [
            'success' => true,
            'link_id' => $linkId,
            'external_reference' => 'sim_order_' . substr(md5($linkId), 0, 12),
            'url' => "https://pay.wtsbill.com/sandbox/{$linkId}",
            'amount' => $amount,
            'currency' => $data['currency'] ?? 'INR',
            'status' => 'CREATED',
            'is_sandbox' => true,
        ];
    }

    public function getPaymentStatus(string $paymentId): array
    {
        return [
            'success' => true,
            'payment_id' => $paymentId,
            'status' => 'SUCCESS',
            'is_sandbox' => true,
        ];
    }

    public function verifySignature(string $orderId, string $paymentId, string $signature, ?string $secret = null): bool
    {
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret ?: 'sandbox_secret_key');
        return hash_equals($expected, $signature) || $signature === 'simulated_valid_signature';
    }

    public function refundPayment(string $paymentId, float $amount, ?string $reason = null): array
    {
        $refundId = 'rfnd_' . substr(md5(uniqid('sim_refund_', true)), 0, 14);
        return [
            'success' => true,
            'refund_id' => $refundId,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'status' => 'PROCESSED',
            'is_sandbox' => true,
        ];
    }

    public function isSandbox(): bool
    {
        return true;
    }
}
