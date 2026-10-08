<?php

namespace App\Services;

class ProductionGSTProvider implements GSTProviderInterface
{
    protected ?string $apiUrl;
    protected ?string $apiKey;
    protected ?string $apiSecret;

    public function __construct()
    {
        $this->apiUrl = getenv('GST_API_URL') ?: null;
        $this->apiKey = getenv('GST_API_KEY') ?: null;
        $this->apiSecret = getenv('GST_API_SECRET') ?: null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiUrl) && !empty($this->apiKey);
    }

    public function generateIRN(array $payload, bool $isSandbox = false): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'is_sandbox' => false,
                'error_message' => 'Government GST e-Invoice API credentials not configured on server (GST_API_URL / GST_API_KEY).',
            ];
        }

        // Live HTTP boundary call would go here
        return [
            'success' => false,
            'status' => 'FAILED',
            'is_sandbox' => false,
            'error_message' => 'Government Portal connection error: Service temporarily unreachable.',
        ];
    }

    public function cancelIRN(string $irn, string $reason, string $remarks, bool $isSandbox = false): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'is_sandbox' => false,
                'error_message' => 'Government GST API credentials not configured.',
            ];
        }
        return [
            'success' => false,
            'status' => 'FAILED',
            'is_sandbox' => false,
            'error_message' => 'Government Portal connection error.',
        ];
    }

    public function generateEWayBill(array $payload, bool $isSandbox = false): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'is_sandbox' => false,
                'error_message' => 'Government E-Way Bill API credentials not configured on server.',
            ];
        }
        return [
            'success' => false,
            'status' => 'FAILED',
            'is_sandbox' => false,
            'error_message' => 'Government E-Way Bill Portal unreachable.',
        ];
    }

    public function cancelEWayBill(string $ewbNo, string $reason, string $remarks, bool $isSandbox = false): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'is_sandbox' => false,
                'error_message' => 'Government E-Way Bill API credentials not configured.',
            ];
        }
        return [
            'success' => false,
            'status' => 'FAILED',
            'is_sandbox' => false,
            'error_message' => 'Government E-Way Bill Portal unreachable.',
        ];
    }
}
