<?php

namespace App\Http\Controllers\Api;

use App\Http\Middleware\AuthMiddleware;
use App\Services\PaymentLinkService;
use App\Services\PaymentWebhookService;
use App\Services\PaymentSettlementService;
use App\Services\PermissionManager;
use Exception;

class PaymentGatewayController
{
    protected function getAuth()
    {
        $user = AuthMiddleware::getUser();
        $companyId = AuthMiddleware::getCompanyId();
        $branchId = AuthMiddleware::getBranchId();
        return [$user, $companyId, $branchId];
    }

    public function getLinks()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'payment_link', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $links = PaymentLinkService::listPaymentLinks($companyId, $_GET ?? []);
            return response_json(['status' => 'success', 'data' => $links]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function createLink()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'payment_link', 'create')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            $link = PaymentLinkService::createPaymentLink(
                companyId: $companyId,
                invoiceId: isset($input['invoice_id']) ? intval($input['invoice_id']) : null,
                customerId: isset($input['customer_id']) ? intval($input['customer_id']) : null,
                amount: isset($input['amount']) ? floatval($input['amount']) : null,
                allowPartial: !empty($input['allow_partial']),
                expiryDays: intval($input['expiry_days'] ?? 7),
                provider: $input['provider'] ?? 'SIMULATION',
                branchId: $branchId,
                userName: $user->name
            );

            return response_json(['status' => 'success', 'data' => $link], 201);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function showLink(string $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'payment_link', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $link = PaymentLinkService::getPaymentLink($companyId, $id);
            if (!$link) {
                return response_json(['status' => 'error', 'message' => 'Payment link not found'], 404);
            }
            return response_json(['status' => 'success', 'data' => $link]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function cancelLink(int $id)
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'payment_link', 'create')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            PaymentLinkService::cancelPaymentLink($companyId, $id, $user->name);
            return response_json(['status' => 'success', 'message' => 'Payment link cancelled successfully']);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function handleWebhook(string $provider)
    {
        $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? null);
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?: get_json_input();

        try {
            $result = PaymentWebhookService::handleWebhook($provider, $payload, $signature, $raw);
            return response_json($result, 200);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getSettlements()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'view')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        try {
            $settlements = PaymentSettlementService::listSettlements($companyId, $_GET ?? []);
            return response_json(['status' => 'success', 'data' => $settlements]);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function storeSettlement()
    {
        [$user, $companyId, $branchId] = $this->getAuth();
        if (!PermissionManager::can($user, 'banking', 'create')) {
            return response_json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $input = get_json_input();
        try {
            $settlement = PaymentSettlementService::recordSettlement(
                companyId: $companyId,
                settlementId: $input['settlement_id'] ?? ('SETTL-' . time()),
                provider: $input['provider'] ?? 'SIMULATION',
                settlementDate: $input['settlement_date'] ?? date('Y-m-d'),
                grossAmount: floatval($input['gross_amount'] ?? 0),
                feeAmount: floatval($input['fee_amount'] ?? 0),
                taxAmount: floatval($input['tax_amount'] ?? 0),
                bankAccountId: intval($input['bank_account_id'] ?? 0),
                userName: $user->name
            );

            return response_json(['status' => 'success', 'data' => $settlement], 201);
        } catch (Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
