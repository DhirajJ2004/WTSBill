<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Services\CommunicationService;
use WTSBill\Services\PdfGenerator;

/**
 * Controller for Document Communication Settings, WhatsApp Sharing, and Email Delivery.
 */
class CommunicationController
{
    /**
     * Save communication channel preferences and API/SMTP configurations.
     * Route: POST /settings/communication
     */
    public function saveSettings(Request $req): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $input = $req->isJson() ? $req->json() : $req->all();

        CommunicationService::saveSettings($companyId, $input);

        $msg = 'Communication settings saved successfully.';
        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'message' => $msg]);
        }

        Session::flash('success', $msg);
        return Response::redirect('/settings?tab=communication');
    }

    /**
     * Send test email to verify SMTP configuration.
     * Route: POST /api/settings/communication/test-email
     */
    public function testEmail(Request $req): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);
        $input = $req->isJson() ? $req->json() : $req->all();

        $toEmail = trim((string)($input['test_email'] ?? ''));
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::json(['success' => false, 'message' => 'Please provide a valid recipient email address.'], 422);
        }

        $subject = 'WTSBill ERP — Test Email Delivery';
        $body = '<h3>WTSBill Communication Engine</h3><p>This is a test email confirming that your SMTP email settings are working properly.</p><p>Sent on ' . date('Y-m-d H:i:s') . '</p>';

        $res = CommunicationService::sendEmail(
            $companyId,
            $branchId,
            $toEmail,
            'System Administrator',
            $subject,
            $body,
            null,
            null,
            'TEST',
            0,
            (int)(Session::getUserId() ?? 1)
        );

        if ($res['success']) {
            return Response::json(['success' => true, 'message' => "Test email dispatched successfully to {$toEmail}."]);
        }

        return Response::json(['success' => false, 'message' => 'Failed to send test email: ' . ($res['error'] ?? 'Unknown SMTP error.')], 500);
    }

    /**
     * Test WhatsApp API connection.
     * Route: POST /api/settings/communication/test-whatsapp
     */
    public function testWhatsApp(Request $req): Response
    {
        $this->ensureAdmin();
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);
        $input = $req->isJson() ? $req->json() : $req->all();

        $toPhone = trim((string)($input['test_phone'] ?? ''));
        if (empty($toPhone)) {
            return Response::json(['success' => false, 'message' => 'Please provide a test mobile phone number.'], 422);
        }

        $message = "WTSBill ERP — Test Notification.\nYour communication settings are active.";
        $res = CommunicationService::sendWhatsApp($companyId, $branchId, $toPhone, $message, null, 'TEST', 0, (int)(Session::getUserId() ?? 1));

        return Response::json([
            'success' => $res['success'],
            'mode' => $res['mode'] ?? 'API',
            'url' => $res['url'] ?? null,
            'message' => $res['success'] ? 'WhatsApp test initiated successfully.' : ($res['error'] ?? 'WhatsApp dispatch failed.'),
        ]);
    }

    /**
     * Share Tax Invoice via WhatsApp, Email, or Both.
     * Route: POST /sales/invoices/{id}/share
     */
    public function shareInvoice(Request $req, int $id): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);
        $userId = (int)(Session::getUserId() ?? 1);

        $invoice = Database::selectOne(
            "SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.gstin as customer_gstin 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.id = ? AND i.company_id = ?",
            [$id, $companyId]
        );

        if (!$invoice) {
            return Response::json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $items = Database::select(
            "SELECT ii.*, p.name as product_name FROM invoice_items ii LEFT JOIN products p ON ii.product_id = p.id WHERE ii.invoice_id = ?",
            [$id]
        );

        $company = Database::selectOne("SELECT * FROM companies WHERE id = ?", [$companyId]) ?? ['name' => 'WTSBill Enterprise'];
        $commSettings = CommunicationService::getSettings($companyId);

        $input = $req->isJson() ? $req->json() : $req->all();
        $channel = strtoupper(trim((string)($input['channel'] ?? ($commSettings['default_invoice_channel'] ?? 'BOTH'))));
        $recipientPhone = trim((string)($input['phone'] ?? ($invoice['customer_phone'] ?? '')));
        $recipientEmail = trim((string)($input['email'] ?? ($invoice['customer_email'] ?? '')));
        $customNote = trim((string)($input['note'] ?? ''));

        // Generate Invoice PDF
        $pdfData = PdfGenerator::generateInvoicePdf($invoice, $company, $items);
        $filename = 'Invoice_' . ($invoice['invoice_number'] ?? $id) . '.pdf';
        $publicPdfUrl = CommunicationService::getPublicPdfUrl('INVOICE', $invoice);

        $results = [];

        // 1. WhatsApp Delivery
        if (in_array($channel, ['WHATSAPP', 'BOTH'], true)) {
            if (empty($recipientPhone)) {
                $results['whatsapp'] = [
                    'success' => false,
                    'status' => 'FAILED',
                    'error' => 'Customer mobile phone number is not available for WhatsApp sharing.',
                ];
            } else {
                $waMessage = CommunicationService::buildInvoiceMessage($invoice, $company, $publicPdfUrl);
                if ($customNote !== '') {
                    $waMessage .= "\n\nNote: " . $customNote;
                }

                $results['whatsapp'] = CommunicationService::sendWhatsApp(
                    $companyId,
                    $branchId,
                    $recipientPhone,
                    $waMessage,
                    $publicPdfUrl,
                    'INVOICE',
                    $id,
                    $userId,
                    $filename
                );
                $results['whatsapp']['pdf_url'] = "/sales/invoices/{$id}/pdf";
                $results['whatsapp']['public_pdf_url'] = $publicPdfUrl;
                $results['whatsapp']['filename'] = $filename;
            }
        }

        // 2. Email Delivery
        if (in_array($channel, ['EMAIL', 'BOTH'], true)) {
            if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $results['email'] = [
                    'success' => false,
                    'status' => 'FAILED',
                    'error' => 'Customer email address is invalid or missing.',
                ];
            } else {
                $subject = "Tax Invoice {$invoice['invoice_number']} from {$company['name']}";
                $htmlBody = "<p>Dear " . htmlspecialchars($invoice['customer_name'] ?? 'Customer') . ",</p>"
                          . "<p>Please find attached your Tax Invoice <strong>" . htmlspecialchars($invoice['invoice_number']) . "</strong> for INR " . number_format((float)$invoice['grand_total'], 2) . ".</p>"
                          . ($customNote !== '' ? "<p><em>Note: " . htmlspecialchars($customNote) . "</em></p>" : "")
                          . "<p>Thank you for choosing {$company['name']}.</p>";

                $results['email'] = CommunicationService::sendEmail(
                    $companyId,
                    $branchId,
                    $recipientEmail,
                    (string)($invoice['customer_name'] ?? 'Customer'),
                    $subject,
                    $htmlBody,
                    $pdfData,
                    $filename,
                    'INVOICE',
                    $id,
                    $userId
                );
            }
        }

        $overallSuccess = (!empty($results['whatsapp']['success']) || !empty($results['email']['success']));
        return Response::json([
            'success' => $overallSuccess,
            'channel' => $channel,
            'results' => $results,
            'message' => $overallSuccess ? 'Invoice sharing initiated successfully.' : 'Failed to deliver document via requested channel(s).',
        ]);
    }

    /**
     * Share Commercial Quotation via WhatsApp, Email, or Both.
     * Route: POST /sales/quotations/{id}/share
     */
    public function shareQuotation(Request $req, int $id): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);
        $userId = (int)(Session::getUserId() ?? 1);

        $quotation = Database::selectOne(
            "SELECT q.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.gstin as customer_gstin 
             FROM quotations q 
             LEFT JOIN customers c ON q.customer_id = c.id 
             WHERE q.id = ? AND q.company_id = ?",
            [$id, $companyId]
        );

        if (!$quotation) {
            return Response::json(['success' => false, 'message' => 'Quotation not found.'], 404);
        }

        $items = Database::select(
            "SELECT qi.*, p.name as product_name FROM quotation_items qi LEFT JOIN products p ON qi.product_id = p.id WHERE qi.quotation_id = ?",
            [$id]
        );

        $company = Database::selectOne("SELECT * FROM companies WHERE id = ?", [$companyId]) ?? ['name' => 'WTSBill Enterprise'];
        $commSettings = CommunicationService::getSettings($companyId);

        $input = $req->isJson() ? $req->json() : $req->all();
        $channel = strtoupper(trim((string)($input['channel'] ?? ($commSettings['default_quotation_channel'] ?? 'BOTH'))));
        $recipientPhone = trim((string)($input['phone'] ?? ($quotation['customer_phone'] ?? '')));
        $recipientEmail = trim((string)($input['email'] ?? ($quotation['customer_email'] ?? '')));
        $customNote = trim((string)($input['note'] ?? ''));

        // Generate Quotation PDF
        $pdfData = PdfGenerator::generateQuotationPdf($quotation, $company, $items);
        $filename = 'Quotation_' . ($quotation['quotation_number'] ?? $id) . '.pdf';
        $publicPdfUrl = CommunicationService::getPublicPdfUrl('QUOTATION', $quotation);

        $results = [];

        // 1. WhatsApp Delivery
        if (in_array($channel, ['WHATSAPP', 'BOTH'], true)) {
            if (empty($recipientPhone)) {
                $results['whatsapp'] = [
                    'success' => false,
                    'status' => 'FAILED',
                    'error' => 'Customer mobile phone number is not available for WhatsApp sharing.',
                ];
            } else {
                $waMessage = CommunicationService::buildQuotationMessage($quotation, $company, $publicPdfUrl);
                if ($customNote !== '') {
                    $waMessage .= "\n\nNote: " . $customNote;
                }

                $results['whatsapp'] = CommunicationService::sendWhatsApp(
                    $companyId,
                    $branchId,
                    $recipientPhone,
                    $waMessage,
                    $publicPdfUrl,
                    'QUOTATION',
                    $id,
                    $userId,
                    $filename
                );
                $results['whatsapp']['pdf_url'] = "/sales/quotations/{$id}/pdf";
                $results['whatsapp']['public_pdf_url'] = $publicPdfUrl;
                $results['whatsapp']['filename'] = $filename;
            }
        }

        // 2. Email Delivery
        if (in_array($channel, ['EMAIL', 'BOTH'], true)) {
            if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $results['email'] = [
                    'success' => false,
                    'status' => 'FAILED',
                    'error' => 'Customer email address is invalid or missing.',
                ];
            } else {
                $subject = "Quotation {$quotation['quotation_number']} from {$company['name']}";
                $htmlBody = "<p>Dear " . htmlspecialchars($quotation['customer_name'] ?? 'Customer') . ",</p>"
                          . "<p>Please find attached our Commercial Quotation <strong>" . htmlspecialchars($quotation['quotation_number']) . "</strong> for INR " . number_format((float)$quotation['grand_total'], 2) . ".</p>"
                          . ($customNote !== '' ? "<p><em>Note: " . htmlspecialchars($customNote) . "</em></p>" : "")
                          . "<p>Please review and feel free to reach out with any questions.</p>";

                $results['email'] = CommunicationService::sendEmail(
                    $companyId,
                    $branchId,
                    $recipientEmail,
                    (string)($quotation['customer_name'] ?? 'Customer'),
                    $subject,
                    $htmlBody,
                    $pdfData,
                    $filename,
                    'QUOTATION',
                    $id,
                    $userId
                );
            }
        }

        $overallSuccess = (!empty($results['whatsapp']['success']) || !empty($results['email']['success']));
        return Response::json([
            'success' => $overallSuccess,
            'channel' => $channel,
            'results' => $results,
            'message' => $overallSuccess ? 'Quotation sharing initiated successfully.' : 'Failed to deliver document via requested channel(s).',
        ]);
    }

    /**
     * Get communication audit history for an invoice.
     * Route: GET /sales/invoices/{id}/communication-history
     */
    public function invoiceHistory(Request $req, int $id): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $history = CommunicationService::getHistory('INVOICE', $id, $companyId);
        return Response::json(['success' => true, 'data' => $history]);
    }

    /**
     * Get communication audit history for a quotation.
     * Route: GET /sales/quotations/{id}/communication-history
     */
    public function quotationHistory(Request $req, int $id): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $history = CommunicationService::getHistory('QUOTATION', $id, $companyId);
        return Response::json(['success' => true, 'data' => $history]);
    }

    /**
     * Download Invoice as PDF file.
     * Route: GET /sales/invoices/{id}/pdf
     */
    public function invoicePdf(Request $req, int $id): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $invoice = Database::selectOne(
            "SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.gstin as customer_gstin 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.id = ? AND i.company_id = ?",
            [$id, $companyId]
        );
        if (!$invoice) {
            return new Response('Invoice not found.', 404);
        }

        $items = Database::select(
            "SELECT ii.*, p.name as product_name FROM invoice_items ii LEFT JOIN products p ON ii.product_id = p.id WHERE ii.invoice_id = ?",
            [$id]
        );
        $company = Database::selectOne("SELECT * FROM companies WHERE id = ?", [$companyId]) ?? ['name' => 'WTSBill Enterprise'];

        $pdf = PdfGenerator::generateInvoicePdf($invoice, $company, $items);
        $filename = 'Invoice_' . ($invoice['invoice_number'] ?? $id) . '.pdf';
        $disposition = ($req->query('download') === '1') ? 'attachment' : 'inline';

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download Quotation as PDF file.
     * Route: GET /sales/quotations/{id}/pdf
     */
    public function quotationPdf(Request $req, int $id): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $quotation = Database::selectOne(
            "SELECT q.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.gstin as customer_gstin 
             FROM quotations q 
             LEFT JOIN customers c ON q.customer_id = c.id 
             WHERE q.id = ? AND q.company_id = ?",
            [$id, $companyId]
        );
        if (!$quotation) {
            return new Response('Quotation not found.', 404);
        }

        $items = Database::select(
            "SELECT qi.*, p.name as product_name FROM quotation_items qi LEFT JOIN products p ON qi.product_id = p.id WHERE qi.quotation_id = ?",
            [$id]
        );
        $company = Database::selectOne("SELECT * FROM companies WHERE id = ?", [$companyId]) ?? ['name' => 'WTSBill Enterprise'];

        $pdf = PdfGenerator::generateQuotationPdf($quotation, $company, $items);
        $filename = 'Quotation_' . ($quotation['quotation_number'] ?? $id) . '.pdf';
        $disposition = ($req->query('download') === '1') ? 'attachment' : 'inline';

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Public direct PDF download/stream via verified HMAC token (zero login required).
     * Route: GET /public/invoices/{id}/pdf
     */
    public function publicInvoicePdf(Request $req, int $id): Response
    {
        $invoice = Database::selectOne(
            "SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.gstin as customer_gstin 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.id = ?",
            [$id]
        );
        if (!$invoice) {
            return new Response('Document not found.', 404);
        }

        $expectedToken = CommunicationService::getDocumentPublicToken('INVOICE', (int)$id, (string)$invoice['created_at'], (int)$invoice['company_id']);
        $providedToken = (string)($req->query('token') ?? '');

        if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            return new Response('Unauthorized or invalid document access link.', 403);
        }

        $items = Database::select(
            "SELECT ii.*, p.name as product_name FROM invoice_items ii LEFT JOIN products p ON ii.product_id = p.id WHERE ii.invoice_id = ?",
            [$id]
        );
        $company = Database::selectOne("SELECT * FROM companies WHERE id = ?", [(int)$invoice['company_id']]) ?? ['name' => 'WTSBill Enterprise'];

        $pdf = PdfGenerator::generateInvoicePdf($invoice, $company, $items);
        $filename = 'Invoice_' . ($invoice['invoice_number'] ?? $id) . '.pdf';
        $disposition = ($req->query('download') === '1') ? 'attachment' : 'inline';

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Public direct Quotation PDF via verified HMAC token (zero login required).
     * Route: GET /public/quotations/{id}/pdf
     */
    public function publicQuotationPdf(Request $req, int $id): Response
    {
        $quotation = Database::selectOne(
            "SELECT q.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.gstin as customer_gstin 
             FROM quotations q 
             LEFT JOIN customers c ON q.customer_id = c.id 
             WHERE q.id = ?",
            [$id]
        );
        if (!$quotation) {
            return new Response('Document not found.', 404);
        }

        $expectedToken = CommunicationService::getDocumentPublicToken('QUOTATION', (int)$id, (string)$quotation['created_at'], (int)$quotation['company_id']);
        $providedToken = (string)($req->query('token') ?? '');

        if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            return new Response('Unauthorized or invalid document access link.', 403);
        }

        $items = Database::select(
            "SELECT qi.*, p.name as product_name FROM quotation_items qi LEFT JOIN products p ON qi.product_id = p.id WHERE qi.quotation_id = ?",
            [$id]
        );
        $company = Database::selectOne("SELECT * FROM companies WHERE id = ?", [(int)$quotation['company_id']]) ?? ['name' => 'WTSBill Enterprise'];

        $pdf = PdfGenerator::generateQuotationPdf($quotation, $company, $items);
        $filename = 'Quotation_' . ($quotation['quotation_number'] ?? $id) . '.pdf';
        $disposition = ($req->query('download') === '1') ? 'attachment' : 'inline';

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Public printable view of invoice via verified HMAC token.
     * Route: GET /public/invoices/{id}
     */
    public function publicInvoiceView(Request $req, int $id): Response
    {
        $invoice = Database::selectOne("SELECT * FROM invoices WHERE id = ?", [$id]);
        if (!$invoice) {
            return new Response('Document not found.', 404);
        }
        $expectedToken = CommunicationService::getDocumentPublicToken('INVOICE', (int)$id, (string)$invoice['created_at'], (int)$invoice['company_id']);
        $providedToken = (string)($req->query('token') ?? '');
        if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            return new Response('Unauthorized or invalid document access link.', 403);
        }

        $salesController = new \WTSBill\Controllers\SalesController();
        return $salesController->print($req, $id);
    }

    /**
     * Public printable view of quotation via verified HMAC token.
     * Route: GET /public/quotations/{id}
     */
    public function publicQuotationView(Request $req, int $id): Response
    {
        $quotation = Database::selectOne("SELECT * FROM quotations WHERE id = ?", [$id]);
        if (!$quotation) {
            return new Response('Document not found.', 404);
        }
        $expectedToken = CommunicationService::getDocumentPublicToken('QUOTATION', (int)$id, (string)$quotation['created_at'], (int)$quotation['company_id']);
        $providedToken = (string)($req->query('token') ?? '');
        if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            return new Response('Unauthorized or invalid document access link.', 403);
        }

        $quotationController = new \WTSBill\Controllers\QuotationController();
        return $quotationController->print($req, $id);
    }

    private function ensureAdmin(): void
    {
        $userRole = strtoupper((string)(Session::get('user_role') ?? (Session::get('role') ?? (Session::getUser()['role'] ?? (Session::get('auth_user')['role'] ?? 'STAFF')))));
        if (!in_array($userRole, ['ADMIN', 'SUPERADMIN', 'OWNER'], true)) {
            throw new \WTSBill\Core\Exceptions\HttpException(403, 'Unauthorized. Administrator permissions required.');
        }
    }

    private function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $contentType = (string)($req->header('content-type') ?? '');
        $xRequestedWith = (string)($req->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}

