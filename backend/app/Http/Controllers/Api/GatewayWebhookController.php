<?php

namespace App\Http\Controllers\Api;

use App\PaymentGateways\Webhooks\GatewayWebhookService;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class GatewayWebhookController
{
    /**
     * Handle incoming webhook from payment gateway.
     */
    public function handle(string $provider, Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature') ?: ($request->header('X-Webhook-Signature') ?: ($request->input('signature') ?: ''));
        $eventId = $request->input('event_id') ?: ('evt_' . md5($rawPayload));
        $eventType = $request->input('event') ?: ($request->input('event_type') ?: 'PAYMENT_SUCCESS');
        $secret = 'sandbox_secret_key_123';

        try {
            $result = GatewayWebhookService::processWebhook(
                $companyId,
                $provider,
                $eventId,
                $eventType,
                $rawPayload,
                $signature,
                $secret
            );

            $httpCode = ($result['success'] ?? false) ? 200 : 400;
            return response()->json($result, $httpCode);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
