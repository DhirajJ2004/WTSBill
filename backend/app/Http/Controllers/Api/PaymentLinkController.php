<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentLink;
use App\PaymentGateways\Services\PaymentLinkService;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class PaymentLinkController
{
    /**
     * Create payment link for invoice.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();

        if ($user && !PermissionManager::can($user, 'payment_links', 'create')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $invoiceId = intval($request->input('invoice_id'));
        $amount = $request->has('amount') ? floatval($request->input('amount')) : null;
        $expiresAt = $request->input('expires_at');
        $description = $request->input('description');

        try {
            $link = PaymentLinkService::createPaymentLink($companyId, $invoiceId, $amount, $expiresAt, $description);
            return response()->json([
                'status' => 'success',
                'message' => 'Payment link created',
                'payment_link' => $link,
                'shareable_url' => "https://pay.wtsbill.in/link/{$link->token}",
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Public payment link details (Sanitized, no internal credentials or internal IDs).
     */
    public function showPublic(string $token, Request $request): JsonResponse
    {
        try {
            $details = PaymentLinkService::getPublicLinkDetails($token, $request->ip(), $request->userAgent());
            return response()->json([
                'status' => 'success',
                'data' => $details,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Cancel payment link.
     */
    public function cancel(int $id): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getUser();

        if ($user && !PermissionManager::can($user, 'payment_links', 'cancel')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $link = PaymentLink::where('company_id', $companyId)->findOrFail($id);
        $link->update(['status' => 'CANCELLED']);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment link cancelled',
            'payment_link' => $link,
        ]);
    }
}
