<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\CommunicationService;
use WTSBill\Services\PdfGenerator;

/**
 * Feature Test Suite for WhatsApp and Email Document Sharing.
 */
class CommunicationSharingTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // Authenticate Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = Session::getCompanyId() ?? 1;
        $branchId = Session::getBranchId() ?? 1;
        $csrfToken = Session::csrfToken();

        $assert('COMM-01', 'Administrator authenticates and establishes communication context',
            $user !== null && $companyId > 0);

        // 1. Phone Normalization (E.164, Indian Mobiles)
        $n1 = CommunicationService::normalizePhone('9876543210');
        $n2 = CommunicationService::normalizePhone('+91 98765-43210');
        $n3 = CommunicationService::normalizePhone('09876543210');
        $n4 = CommunicationService::normalizePhone('+1 (555) 234-5678');

        $assert('COMM-02', 'Phone numbers accurately normalized to E.164 digit strings',
            $n1 === '919876543210' &&
            $n2 === '919876543210' &&
            $n3 === '919876543210' &&
            $n4 === '15552345678'
        );

        // 2. Communication Settings Persistence
        $saveReq = new Request([], [
            '_csrf_token' => $csrfToken,
            'default_invoice_channel' => 'BOTH',
            'default_quotation_channel' => 'WHATSAPP',
            'whatsapp_provider' => 'CLOUD_API',
            'whatsapp_phone_number_id' => '10987654321',
            'whatsapp_access_token' => 'EAATestTokenSecurityVerify123',
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_from_address' => 'billing@wtsbill.in',
            'smtp_from_name' => 'WTSBill Enterprise Billing',
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/settings/communication',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $saveRes = $router->dispatch($saveReq);
        $saveJson = json_decode($saveRes->getContent(), true);

        $savedSettings = CommunicationService::getSettings($companyId);

        $assert('COMM-03', 'POST /settings/communication persists channel defaults and provider configurations',
            $saveRes->getStatusCode() === 200 &&
            isset($saveJson['success']) && $saveJson['success'] === true &&
            $savedSettings['default_invoice_channel'] === 'BOTH' &&
            $savedSettings['default_quotation_channel'] === 'WHATSAPP' &&
            $savedSettings['whatsapp_phone_number_id'] === '10987654321' &&
            $savedSettings['smtp_host'] === 'smtp.mailtrap.io'
        );

        // 3. Message Builders for Invoices & Quotations
        $mockCompany = ['name' => 'WTS Technologies Pvt Ltd', 'gstin' => '27AADCW7577N1ZE'];
        $mockInvoice = [
            'id' => 999,
            'invoice_number' => 'INV-2026-TEST',
            'invoice_date' => '2026-11-04',
            'customer_name' => 'Acme Heavy Engineering',
            'grand_total' => 50000.00,
            'amount_paid' => 20000.00,
            'amount_due' => 30000.00,
        ];
        $invMsg = CommunicationService::buildInvoiceMessage($mockInvoice, $mockCompany, 'http://localhost/sales/invoices/999/print');

        $assert('COMM-04', 'buildInvoiceMessage produces professional message with totals and remaining balance',
            str_contains($invMsg, 'INV-2026-TEST') &&
            str_contains($invMsg, '50,000.00') &&
            str_contains($invMsg, '20,000.00') &&
            str_contains($invMsg, '30,000.00') &&
            str_contains($invMsg, 'Acme Heavy Engineering')
        );

        // 4. WhatsApp Click-to-Chat Fallback URL
        $waUrl = CommunicationService::getClickToChatUrl('9876543210', $invMsg);
        $assert('COMM-05', 'getClickToChatUrl generates valid wa.me link with URL-encoded message',
            str_starts_with($waUrl, 'https://wa.me/919876543210?text=') &&
            str_contains($waUrl, rawurlencode('INV-2026-TEST'))
        );

        // 5. Standalone PDF-1.4 Generation
        $pdfData = PdfGenerator::generateInvoicePdf($mockInvoice, $mockCompany);
        $assert('COMM-06', 'PdfGenerator generates valid PDF-1.4 binary stream with xref and EOF trailer',
            str_starts_with($pdfData, "%PDF-1.4") &&
            str_contains($pdfData, "%%EOF") &&
            str_contains($pdfData, "INV-2026-TEST")
        );

        // 6. Share Real Invoice via POST /sales/invoices/{id}/share
        $testInv = Database::selectOne("SELECT id, invoice_number FROM invoices WHERE company_id = ? ORDER BY id DESC LIMIT 1", [$companyId]);
        if (!$testInv) {
            $testInvId = (int)Database::insert('invoices', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => 1,
                'invoice_number' => 'INV-COMM-001',
                'invoice_date' => date('Y-m-d'),
                'subtotal' => 10000,
                'tax_total' => 1800,
                'grand_total' => 11800,
                'amount_paid' => 0,
                'amount_due' => 11800,
                'status' => 'UNPAID',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $testInv = ['id' => $testInvId, 'invoice_number' => 'INV-COMM-001'];
        }
        $targetInvId = (int)$testInv['id'];

        $shareInvReq = new Request([], [
            '_csrf_token' => $csrfToken,
            'channel' => 'BOTH',
            'phone' => '9822012345',
            'email' => 'client.accounts@wtsindia.co.in',
            'note' => 'Please process payment per terms.',
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/invoices/{$targetInvId}/share",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $shareInvRes = $router->dispatch($shareInvReq);
        $shareInvJson = json_decode($shareInvRes->getContent(), true);

        $assert('COMM-07', 'POST /sales/invoices/{id}/share initiates multi-channel dispatch',
            $shareInvRes->getStatusCode() === 200 &&
            isset($shareInvJson['success']) &&
            isset($shareInvJson['results']['whatsapp'])
        );

        // 7. Verify Communication History for Invoice
        $histReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$targetInvId}/communication-history"]);
        $histRes = $router->dispatch($histReq);
        $histJson = json_decode($histRes->getContent(), true);

        $assert('COMM-08', 'GET /sales/invoices/{id}/communication-history returns logged dispatch records',
            $histRes->getStatusCode() === 200 &&
            isset($histJson['success']) && $histJson['success'] === true &&
            isset($histJson['data']) && is_array($histJson['data']) &&
            count($histJson['data']) > 0
        );

        // 8. Share Quotation via POST /sales/quotations/{id}/share
        $testQt = Database::selectOne("SELECT id, quotation_number FROM quotations WHERE company_id = ? ORDER BY id DESC LIMIT 1", [$companyId]);
        if (!$testQt) {
            $testQtId = (int)Database::insert('quotations', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => 1,
                'quotation_number' => 'QT-COMM-001',
                'quotation_date' => date('Y-m-d'),
                'sub_total' => 20000,
                'total_tax' => 3600,
                'grand_total' => 23600,
                'status' => 'SENT',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $testQt = ['id' => $testQtId, 'quotation_number' => 'QT-COMM-001'];
        }
        $targetQtId = (int)$testQt['id'];

        $shareQtReq = new Request([], [
            '_csrf_token' => $csrfToken,
            'channel' => 'WHATSAPP',
            'phone' => '9822012345',
            'note' => 'Valid for 15 days.',
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/quotations/{$targetQtId}/share",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $shareQtRes = $router->dispatch($shareQtReq);
        $shareQtJson = json_decode($shareQtRes->getContent(), true);

        $assert('COMM-09', 'POST /sales/quotations/{id}/share initiates quotation WhatsApp dispatch',
            $shareQtRes->getStatusCode() === 200 &&
            isset($shareQtJson['success']) &&
            isset($shareQtJson['results']['whatsapp'])
        );

        // 9. Missing Recipient Error Safeguard
        $badReq = new Request([], [
            '_csrf_token' => $csrfToken,
            'channel' => 'EMAIL',
            'email' => 'invalid-email-format',
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/invoices/{$targetInvId}/share",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $badRes = $router->dispatch($badReq);
        $badJson = json_decode($badRes->getContent(), true);

        $assert('COMM-10', 'Invalid recipient email or missing phone is safely rejected with error feedback',
            isset($badJson['results']['email']['success']) &&
            $badJson['results']['email']['success'] === false &&
            str_contains(strtolower($badJson['results']['email']['error'] ?? ''), 'invalid')
        );

        // 10. Click-to-chat Fallback Never Falsely Claims Delivered
        CommunicationService::saveSettings($companyId, [
            'whatsapp_phone_number_id' => '',
            'whatsapp_access_token' => '',
        ]);

        $fallbackRes = CommunicationService::sendWhatsApp(
            $companyId,
            $branchId,
            '9876543210',
            'Test message',
            null,
            'INVOICE',
            $targetInvId
        );
        $lastLog = Database::selectOne(
            "SELECT * FROM communication_messages WHERE entity_type = 'INVOICE' AND entity_id = ? ORDER BY id DESC LIMIT 1",
            [$targetInvId]
        );

        $assert('COMM-11', 'WhatsApp click-to-chat fallback is strictly logged as CLICK_TO_CHAT_GENERATED, never DELIVERED',
            ($fallbackRes['mode'] ?? '') === 'CLICK_TO_CHAT' &&
            $lastLog !== null &&
            $lastLog['status'] === 'CLICK_TO_CHAT_GENERATED' &&
            $lastLog['status'] !== 'DELIVERED'
        );

        // 11. Secure Public PDF Link & HMAC Token Verification
        $invRecord = Database::selectOne("SELECT * FROM invoices WHERE id = ?", [$targetInvId]);
        $validToken = CommunicationService::getDocumentPublicToken('INVOICE', $targetInvId, (string)$invRecord['created_at'], $companyId);
        $publicPdfUrl = CommunicationService::getPublicPdfUrl('INVOICE', $invRecord);

        $assert('COMM-12', 'CommunicationService produces secure public PDF URL containing verified HMAC token',
            str_contains($publicPdfUrl, "/doc/invoices/{$targetInvId}/pdf?token=") &&
            str_contains($publicPdfUrl, $validToken)
        );

        // 12. Public PDF Route Serves Valid PDF-1.4 Stream with Valid Token
        $pubPdfReq = new Request(['token' => $validToken], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/doc/invoices/{$targetInvId}/pdf?token={$validToken}",
        ]);
        $pubPdfRes = $router->dispatch($pubPdfReq);

        $assert('COMM-13', 'GET /doc/invoices/{id}/pdf with valid HMAC token serves binary PDF stream without authentication barrier',
            $pubPdfRes->getStatusCode() === 200 &&
            str_starts_with($pubPdfRes->getContent(), '%PDF-1.4')
        );

        // 13. Public PDF Route Rejects Forged or Missing Token with HTTP 403
        $badPdfReq = new Request(['token' => 'forged_fake_token_123'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "/doc/invoices/{$targetInvId}/pdf?token=forged_fake_token_123",
        ]);
        $badPdfRes = $router->dispatch($badPdfReq);

        $assert('COMM-14', 'GET /doc/invoices/{id}/pdf strictly rejects invalid or forged HMAC token with HTTP 403 Forbidden',
            $badPdfRes->getStatusCode() === 403
        );
    }
}
