<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Session;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Enterprise Communication Service for WhatsApp and Email Document Sharing.
 * Supports Meta WhatsApp Business Cloud API, WhatsApp Click-to-Chat fallback,
 * native SMTP socket delivery with PDF attachments, and audit logging.
 */
class CommunicationService
{
    /**
     * Retrieve communication settings for a tenant company.
     */
    public static function getSettings(int $companyId): array
    {
        $settings = Database::selectOne(
            "SELECT * FROM business_communication_settings WHERE company_id = ? LIMIT 1",
            [$companyId]
        );

        if (!$settings) {
            return [
                'company_id' => $companyId,
                'default_invoice_channel' => 'BOTH',
                'default_quotation_channel' => 'BOTH',
                'whatsapp_provider' => 'CLOUD_API',
                'whatsapp_phone_number_id' => '',
                'whatsapp_access_token' => '',
                'whatsapp_business_account_id' => '',
                'whatsapp_api_version' => 'v19.0',
                'whatsapp_template_name' => 'document_share',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_username' => 'systemmail132@gmail.com',
                'smtp_password' => 'eubojueuxgsfptit',
                'smtp_encryption' => 'tls',
                'smtp_from_address' => 'systemmail132@gmail.com',
                'smtp_from_name' => 'WTSBill Enterprise',
                'email_configured' => 1,
                'whatsapp_configured' => 0,
                'whatsapp_enabled' => 1,
                'email_enabled' => 1,
                'default_email_message' => "Please find attached your document from {company_name}.",
                'default_whatsapp_message' => "Dear {customer_name},\nPlease find your document attached.",
            ];
        }

        $extra = [];
        if (!empty($settings['settings_json'])) {
            $extra = json_decode((string)$settings['settings_json'], true) ?: [];
        }
        $settings['whatsapp_enabled'] = isset($extra['whatsapp_enabled']) ? (int)$extra['whatsapp_enabled'] : 1;
        $settings['email_enabled'] = isset($extra['email_enabled']) ? (int)$extra['email_enabled'] : 1;
        $settings['default_email_message'] = $extra['default_email_message'] ?? "Please find attached your document from {company_name}.";
        $settings['default_whatsapp_message'] = $extra['default_whatsapp_message'] ?? "Dear {customer_name},\nPlease find your document attached.";

        return $settings;
    }

    /**
     * Persist communication settings for a company.
     */
    public static function saveSettings(int $companyId, array $data): bool
    {
        $existing = Database::selectOne(
            "SELECT id, smtp_password, whatsapp_access_token, settings_json FROM business_communication_settings WHERE company_id = ? LIMIT 1",
            [$companyId]
        );

        $smtpPassword = !empty($data['smtp_password']) ? (string)$data['smtp_password'] : ($existing['smtp_password'] ?? '');
        $whatsappToken = !empty($data['whatsapp_access_token']) ? (string)$data['whatsapp_access_token'] : ($existing['whatsapp_access_token'] ?? '');

        $emailConfigured = (!empty($data['smtp_host']) && !empty($data['smtp_from_address'])) ? 1 : 0;
        $whatsappConfigured = (!empty($data['whatsapp_phone_number_id']) && !empty($whatsappToken)) ? 1 : 0;

        $existingExtra = !empty($existing['settings_json']) ? (json_decode((string)$existing['settings_json'], true) ?: []) : [];
        $extra = [
            'whatsapp_enabled' => isset($data['whatsapp_enabled']) ? (int)$data['whatsapp_enabled'] : ($existingExtra['whatsapp_enabled'] ?? 1),
            'email_enabled' => isset($data['email_enabled']) ? (int)$data['email_enabled'] : ($existingExtra['email_enabled'] ?? 1),
            'default_email_message' => isset($data['default_email_message']) ? trim((string)$data['default_email_message']) : ($existingExtra['default_email_message'] ?? ''),
            'default_whatsapp_message' => isset($data['default_whatsapp_message']) ? trim((string)$data['default_whatsapp_message']) : ($existingExtra['default_whatsapp_message'] ?? ''),
        ];

        $fields = [
            'default_invoice_channel' => strtoupper(trim((string)($data['default_invoice_channel'] ?? 'BOTH'))),
            'default_quotation_channel' => strtoupper(trim((string)($data['default_quotation_channel'] ?? 'BOTH'))),
            'whatsapp_provider' => trim((string)($data['whatsapp_provider'] ?? 'CLOUD_API')),
            'whatsapp_phone_number_id' => trim((string)($data['whatsapp_phone_number_id'] ?? '')),
            'whatsapp_access_token' => $whatsappToken,
            'whatsapp_business_account_id' => trim((string)($data['whatsapp_business_account_id'] ?? '')),
            'whatsapp_api_version' => trim((string)($data['whatsapp_api_version'] ?? 'v19.0')),
            'whatsapp_template_name' => trim((string)($data['whatsapp_template_name'] ?? 'document_share')),
            'smtp_host' => trim((string)($data['smtp_host'] ?? '')),
            'smtp_port' => (int)($data['smtp_port'] ?? 587),
            'smtp_username' => trim((string)($data['smtp_username'] ?? '')),
            'smtp_password' => $smtpPassword,
            'smtp_encryption' => strtolower(trim((string)($data['smtp_encryption'] ?? 'tls'))),
            'smtp_from_address' => trim((string)($data['smtp_from_address'] ?? 'billing@wtsbill.in')),
            'smtp_from_name' => trim((string)($data['smtp_from_name'] ?? 'WTSBill Enterprise')),
            'email_configured' => $emailConfigured,
            'whatsapp_configured' => $whatsappConfigured,
            'settings_json' => json_encode($extra),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            Database::update('business_communication_settings', $fields, 'company_id = ?', [$companyId]);
        } else {
            $fields['company_id'] = $companyId;
            $fields['created_at'] = date('Y-m-d H:i:s');
            Database::insert('business_communication_settings', $fields);
        }

        return true;
    }

    /**
     * Normalize international/Indian phone numbers to E.164 digits string.
     * Examples:
     * - "9876543210" -> "919876543210"
     * - "+91 98765-43210" -> "919876543210"
     * - "09876543210" -> "919876543210"
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === '') {
            return '';
        }

        // 10-digit Indian standard mobile
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        // 11-digit with leading trunk 0
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91' . substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Format phone for human presentation.
     */
    public static function formatDisplayPhone(string $phone): string
    {
        $normalized = self::normalizePhone($phone);
        if (strlen($normalized) === 12 && str_starts_with($normalized, '91')) {
            return '+91 ' . substr($normalized, 2, 5) . ' ' . substr($normalized, 7);
        }
        return '+' . $normalized;
    }

    /**
     * Build standard, professional text message for a Tax Invoice.
     */
    public static function buildInvoiceMessage(array $invoice, array $company, string $documentUrl = ''): string
    {
        $customerName = trim((string)($invoice['customer_name'] ?? 'Valued Customer'));
        $invNumber = $invoice['invoice_number'] ?? ('INV-' . ($invoice['id'] ?? ''));
        $invDate = $invoice['invoice_date'] ?? date('Y-m-d');
        $grandTotal = number_format((float)($invoice['grand_total'] ?? 0), 2);
        $amountPaid = number_format((float)($invoice['amount_paid'] ?? 0), 2);
        $balance = number_format((float)($invoice['amount_due'] ?? ($invoice['grand_total'] ?? 0)), 2);
        $companyName = trim((string)($company['name'] ?? 'WTSBill Enterprise'));

        $msg = "Dear {$customerName},\n\n";
        $msg .= "Please find the details for your Tax Invoice from {$companyName}:\n\n";
        $msg .= "• Invoice Number: {$invNumber}\n";
        $msg .= "• Invoice Date: {$invDate}\n";
        $msg .= "• Grand Total: INR {$grandTotal}\n";
        $msg .= "• Amount Paid: INR {$amountPaid}\n";
        $msg .= "• Outstanding Balance: INR {$balance}\n";

        if (!empty($documentUrl)) {
            $msg .= "\n📄 Download / View PDF Invoice: {$documentUrl}\n";
        }

        $msg .= "\nThank you for doing business with us.\n{$companyName}";
        return $msg;
    }

    /**
     * Build standard, professional text message for a Quotation.
     */
    public static function buildQuotationMessage(array $quotation, array $company, string $documentUrl = ''): string
    {
        $customerName = trim((string)($quotation['customer_name'] ?? 'Valued Customer'));
        $qtNumber = $quotation['quotation_number'] ?? ('QT-' . ($quotation['id'] ?? ''));
        $qtDate = $quotation['quotation_date'] ?? date('Y-m-d');
        $validUntil = $quotation['valid_until'] ?? date('Y-m-d', strtotime('+30 days'));
        $grandTotal = number_format((float)($quotation['grand_total'] ?? 0), 2);
        $companyName = trim((string)($company['name'] ?? 'WTSBill Enterprise'));

        $msg = "Dear {$customerName},\n\n";
        $msg .= "We are pleased to provide your Commercial Quotation from {$companyName}:\n\n";
        $msg .= "• Quotation Number: {$qtNumber}\n";
        $msg .= "• Quotation Date: {$qtDate}\n";
        $msg .= "• Valid Until: {$validUntil}\n";
        $msg .= "• Quotation Amount: INR {$grandTotal}\n";

        if (!empty($documentUrl)) {
            $msg .= "\n📄 Download / View PDF Quotation: {$documentUrl}\n";
        }

        $msg .= "\nPlease let us know if you have any questions or require revisions.\n{$companyName}";
        return $msg;
    }

    /**
     * Build standard, professional text message for a Purchase Vendor Bill.
     */
    public static function buildPurchaseBillMessage(array $bill, array $company, string $documentUrl = ''): string
    {
        $supplierName = trim((string)($bill['supplier_name'] ?? 'Supplier / Vendor'));
        $billNumber = $bill['purchase_number'] ?? ('PB-' . ($bill['id'] ?? ''));
        $vendorInv = !empty($bill['vendor_invoice_number']) ? " (Vendor Ref: {$bill['vendor_invoice_number']})" : '';
        $billDate = $bill['bill_date'] ?? date('Y-m-d');
        $grandTotal = number_format((float)($bill['total_amount'] ?? 0), 2);
        $amountPaid = number_format((float)($bill['paid_amount'] ?? 0), 2);
        $balance = number_format((float)($bill['balance_due'] ?? ($bill['total_amount'] ?? 0)), 2);
        $companyName = trim((string)($company['name'] ?? 'WTSBill Enterprise'));

        $msg = "Dear {$supplierName},\n\n";
        $msg .= "Please find the recorded Purchase Bill details from {$companyName}:\n\n";
        $msg .= "• Purchase Bill #: {$billNumber}{$vendorInv}\n";
        $msg .= "• Bill Date: {$billDate}\n";
        $msg .= "• Total Bill Amount: INR {$grandTotal}\n";
        $msg .= "• Amount Paid: INR {$amountPaid}\n";
        $msg .= "• Outstanding Balance: INR {$balance}\n";

        if (!empty($documentUrl)) {
            $msg .= "\n📄 Download / View Purchase Bill PDF: {$documentUrl}\n";
        }

        $msg .= "\nThank you for your partnership.\n{$companyName}";
        return $msg;
    }

    /**
     * Generate Click-to-Chat URL for WhatsApp.
     */
    public static function getClickToChatUrl(string $phone, string $message): string
    {
        $normalized = self::normalizePhone($phone);
        return 'https://wa.me/' . $normalized . '?text=' . rawurlencode($message);
    }

    /**
     * Generate secure HMAC token for public document access without authentication.
     */
    public static function getDocumentPublicToken(string $type, int $id, string $createdAt, int $companyId): string
    {
        $key = \WTSBill\Core\Config::get('app.key') ?: 'wtsbill_secret_token_2026';
        return substr(hash_hmac('sha256', "{$type}:{$id}:{$createdAt}:{$companyId}", $key), 0, 32);
    }

    /**
     * Get base URL of the application.
     */
    public static function getBaseUrl(): string
    {
        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return "{$scheme}://{$_SERVER['HTTP_HOST']}";
        }
        return \WTSBill\Core\Config::get('app.url', 'http://localhost');
    }

    /**
     * Get direct public PDF URL for an Invoice or Quotation.
     */
    public static function getPublicPdfUrl(string $type, array $entity): string
    {
        $id = (int)($entity['id'] ?? 0);
        $createdAt = (string)($entity['created_at'] ?? date('Y-m-d'));
        $companyId = (int)($entity['company_id'] ?? 1);
        $token = self::getDocumentPublicToken($type, $id, $createdAt, $companyId);
        
        $baseUrl = self::getBaseUrl();
        $lower = strtolower($type);
        if ($lower === 'quotation') {
            $segment = 'quotations';
        } elseif (in_array($lower, ['bill', 'purchase_bill', 'purchase', 'purchases'], true)) {
            $segment = 'purchases';
        } else {
            $segment = 'invoices';
        }
        return "{$baseUrl}/doc/{$segment}/{$id}/pdf?token={$token}";
    }

    /**
     * Get direct public web view URL for an Invoice or Quotation.
     */
    public static function getPublicDocumentUrl(string $type, array $entity): string
    {
        $id = (int)($entity['id'] ?? 0);
        $createdAt = (string)($entity['created_at'] ?? date('Y-m-d'));
        $companyId = (int)($entity['company_id'] ?? 1);
        $token = self::getDocumentPublicToken($type, $id, $createdAt, $companyId);
        
        $baseUrl = self::getBaseUrl();
        $lower = strtolower($type);
        if ($lower === 'quotation') {
            $segment = 'quotations';
        } elseif (in_array($lower, ['bill', 'purchase_bill', 'purchase', 'purchases'], true)) {
            $segment = 'purchases';
        } else {
            $segment = 'invoices';
        }
        return "{$baseUrl}/doc/{$segment}/{$id}?token={$token}";
    }

    /**
     * Send WhatsApp document notification.
     * Uses Cloud API when credentials are present; otherwise generates click-to-chat fallback.
     */
    public static function sendWhatsApp(
        int $companyId,
        int $branchId,
        string $phone,
        string $message,
        ?string $documentUrl = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $userId = null,
        ?string $filename = null
    ): array {
        $settings = self::getSettings($companyId);
        $normalized = self::normalizePhone($phone);

        if (empty($normalized)) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'error' => 'Invalid or missing customer mobile phone number.'
            ];
        }

        $isCloudApiConfigured = !empty($settings['whatsapp_phone_number_id']) && !empty($settings['whatsapp_access_token']);

        if ($isCloudApiConfigured) {
            $result = self::sendCloudApiMessage(
                $settings['whatsapp_phone_number_id'],
                $settings['whatsapp_access_token'],
                $settings['whatsapp_api_version'] ?: 'v19.0',
                $normalized,
                $message,
                $documentUrl,
                $filename
            );

            self::logMessage([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'channel' => 'WHATSAPP',
                'entity_type' => $entityType ?? 'GENERAL',
                'entity_id' => $entityId,
                'recipient' => $normalized,
                'subject' => 'WhatsApp Notification',
                'body' => $message,
                'provider_name' => 'META_WHATSAPP_CLOUD_API',
                'provider_reference' => $result['provider_ref'] ?? null,
                'status' => $result['success'] ? 'SENT' : 'FAILED',
                'error_message' => $result['error'] ?? null,
            ]);

            return $result;
        }

        // Click-to-Chat Fallback
        $clickToChatUrl = self::getClickToChatUrl($normalized, $message);

        // Record as CLICK_TO_CHAT_GENERATED, never falsely claiming delivered
        self::logMessage([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'channel' => 'WHATSAPP',
            'entity_type' => $entityType ?? 'GENERAL',
            'entity_id' => $entityId,
            'recipient' => $normalized,
            'subject' => 'WhatsApp Click-to-Chat Generated',
            'body' => $message,
            'provider_name' => 'WHATSAPP_WEB_DIRECT',
            'provider_reference' => null,
            'status' => 'CLICK_TO_CHAT_GENERATED',
            'error_message' => null,
        ]);

        return [
            'success' => true,
            'mode' => 'CLICK_TO_CHAT',
            'status' => 'CLICK_TO_CHAT_GENERATED',
            'url' => $clickToChatUrl,
            'phone' => $normalized,
            'document_url' => $documentUrl,
            'filename' => $filename,
            'message' => 'WhatsApp click-to-chat link created. Note: Please click Send in WhatsApp to deliver the message.',
        ];
    }

    /**
     * Dispatch message via Meta WhatsApp Business Cloud API.
     */
    private static function sendCloudApiMessage(
        string $phoneNumberId,
        string $accessToken,
        string $version,
        string $toPhone,
        string $text,
        ?string $documentUrl = null,
        ?string $filename = null
    ): array {
        $endpoint = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";
        
        // If public document URL is available, send as a native WhatsApp document attachment with caption
        if (!empty($documentUrl) && (str_starts_with($documentUrl, 'http://') || str_starts_with($documentUrl, 'https://'))) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $toPhone,
                'type' => 'document',
                'document' => [
                    'link' => $documentUrl,
                    'caption' => $text,
                    'filename' => $filename ?: 'Invoice.pdf',
                ],
            ];
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $toPhone,
                'type' => 'text',
                'text' => [
                    'preview_url' => true,
                    'body' => $text,
                ],
            ];
        }

        $ch = curl_init($endpoint);
        if (!$ch) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'error' => 'Unable to initialize cURL for WhatsApp API.',
            ];
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'error' => 'WhatsApp API Connection Error: ' . $curlErr,
            ];
        }

        $resJson = json_decode((string)$response, true);
        if ($httpCode >= 200 && $httpCode < 300 && isset($resJson['messages'][0]['id'])) {
            return [
                'success' => true,
                'status' => 'SENT',
                'provider_ref' => (string)$resJson['messages'][0]['id'],
                'error' => null,
            ];
        }

        $errorMsg = $resJson['error']['message'] ?? ('WhatsApp API returned HTTP ' . $httpCode);
        return [
            'success' => false,
            'status' => 'FAILED',
            'error' => $errorMsg,
        ];
    }

    /**
     * Dispatch email notification with optional PDF attachment.
     */
    public static function sendEmail(
        int $companyId,
        int $branchId,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $attachmentData = null,
        ?string $attachmentFilename = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $userId = null,
        ?string $ccEmail = null
    ): array {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'error' => 'Invalid customer recipient email address: ' . $toEmail,
            ];
        }

        $settings = self::getSettings($companyId);
        $host = !empty($settings['smtp_host']) ? (string)$settings['smtp_host'] : 'smtp.gmail.com';
        $port = (int)(!empty($settings['smtp_port']) ? $settings['smtp_port'] : 587);
        $username = !empty($settings['smtp_username']) ? (string)$settings['smtp_username'] : 'systemmail132@gmail.com';
        $password = !empty($settings['smtp_password']) ? (string)$settings['smtp_password'] : 'eubojueuxgsfptit';
        $encryption = !empty($settings['smtp_encryption']) ? (string)$settings['smtp_encryption'] : 'tls';
        $fromEmail = !empty($settings['smtp_from_address']) ? (string)$settings['smtp_from_address'] : 'systemmail132@gmail.com';
        $fromName = !empty($settings['smtp_from_name']) ? (string)$settings['smtp_from_name'] : 'WTSBill Enterprise';

        $status = 'FAILED';
        $errorMessage = null;

        // Dispatch via PHPMailer SMTP client
        $smtpRes = self::sendSmtpMail(
            $host,
            $port,
            $username,
            $password,
            $encryption,
            $fromEmail,
            $fromName,
            $toEmail,
            $toName,
            $subject,
            $htmlBody,
            $attachmentData,
            $attachmentFilename,
            $ccEmail
        );

        if ($smtpRes['success']) {
            $status = 'SENT';
        } else {
            $errorMessage = $smtpRes['error'];
        }

        self::logMessage([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'channel' => 'EMAIL',
            'entity_type' => $entityType ?? 'GENERAL',
            'entity_id' => $entityId,
            'recipient' => $toEmail,
            'subject' => $subject,
            'body' => $htmlBody,
            'attachment_path' => $attachmentFilename,
            'provider_name' => 'PHPMAILER_SMTP',
            'provider_reference' => null,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        return [
            'success' => ($status === 'SENT'),
            'status' => $status,
            'error' => $errorMessage,
        ];
    }

    /**
     * Enterprise SMTP Client using PHPMailer with IPv4 DNS resolution,
     * TLS/SSL encryption, custom SSL options, and inline PDF attachments.
     */
    public static function sendSmtpMail(
        string $host,
        int $port,
        string $username,
        string $password,
        string $encryption,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $attachmentData = null,
        ?string $attachmentFilename = null,
        ?string $ccEmail = null
    ): array {
        $host       = trim($host) ?: 'smtp.gmail.com';
        $port       = (int)($port ?: 587);
        $username   = trim($username) ?: 'systemmail132@gmail.com';
        $password   = str_replace(' ', '', (string)($password ?: 'eubojueuxgsfptit'));
        $encryption = strtolower(trim($encryption ?: 'tls'));
        $fromEmail  = trim($fromEmail) ?: 'systemmail132@gmail.com';
        $fromName   = trim($fromName) ?: 'WTSBill Enterprise';

        // Resolve IPv4 to prevent dead IPv6 connection timeouts on Windows
        $connectHost = ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) ? $host : gethostbyname($host);

        if (class_exists(PHPMailer::class)) {
            $mail = new PHPMailer(true);
            try {
                $mail->CharSet = 'UTF-8';
                $mail->isSMTP();
                $mail->Host       = $connectHost;
                $mail->SMTPAuth   = (!empty($username) && !empty($password));
                $mail->Username   = $username;
                $mail->Password   = $password;

                if ($encryption === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($encryption === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->SMTPSecure = false;
                    $mail->SMTPAutoTLS = false;
                }

                $mail->Port = $port;
                $mail->Timeout = 15;

                // Allow internal company mail servers with private/self-signed SSL certs and SNI matching
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                        'peer_name' => $host,
                    ]
                ];

                // Sender & Recipient
                $mail->setFrom($fromEmail, $fromName);
                $mail->addAddress($toEmail, $toName ?: $toEmail);

                if (!empty($ccEmail) && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($ccEmail);
                }

                // PDF Attachment
                if (!empty($attachmentData) && !empty($attachmentFilename)) {
                    $mail->addStringAttachment($attachmentData, $attachmentFilename, 'base64', 'application/pdf');
                }

                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '<p>', '</p>'], ["\n", "\n", "\n", "\n", "\n\n"], $htmlBody));

                $mail->send();
                return ['success' => true, 'status' => 'sent', 'error' => null];
            } catch (MailerException $e) {
                $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
                error_log("PHPMailer send failure to {$toEmail}: " . $errorMsg);
                return ['success' => false, 'status' => 'failed', 'error' => $errorMsg];
            } catch (\Throwable $t) {
                $errorMsg = $t->getMessage();
                error_log("PHPMailer exception to {$toEmail}: " . $errorMsg);
                return ['success' => false, 'status' => 'failed', 'error' => $errorMsg];
            }
        }

        // Socket fallback
        return self::sendSocketFallbackMail(
            $connectHost,
            $port,
            $username,
            $password,
            $encryption,
            $fromEmail,
            $fromName,
            $toEmail,
            $toName,
            $subject,
            $htmlBody,
            $attachmentData,
            $attachmentFilename,
            $ccEmail
        );
    }

    /**
     * Transmit email immediately via SMTP with PHPMailer matching user reference pattern.
     * Returns array: ['status' => 'sent'|'failed', 'error' => ?string]
     */
    public static function sendImmediateSmtpMail(
        string $recipient,
        string $subject,
        string $bodyHtml,
        ?string $attachmentData = null,
        ?string $attachmentFilename = null,
        ?string $ccEmail = null
    ): array {
        $res = self::sendSmtpMail(
            'smtp.gmail.com',
            587,
            'systemmail132@gmail.com',
            'eubojueuxgsfptit',
            'tls',
            'systemmail132@gmail.com',
            'WTSBill Enterprise',
            $recipient,
            $recipient,
            $subject,
            $bodyHtml,
            $attachmentData,
            $attachmentFilename,
            $ccEmail
        );

        return [
            'status' => $res['success'] ? 'sent' : 'failed',
            'error' => $res['error'],
        ];
    }

    /**
     * Socket-based pure PHP SMTP fallback.
     */
    private static function sendSocketFallbackMail(
        string $host,
        int $port,
        string $username,
        string $password,
        string $encryption,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $attachmentData,
        ?string $attachmentFilename,
        ?string $ccEmail = null
    ): array {
        $prefix = (strtolower($encryption) === 'ssl') ? 'ssl://' : '';
        $timeout = 10;
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, $timeout);

        if (!$socket) {
            return ['success' => false, 'error' => "SMTP Connection failed to {$host}:{$port} ({$errstr})"];
        }

        $getResponse = function() use ($socket): string {
            $data = '';
            while ($str = fgets($socket, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) === ' ') break;
            }
            return $data;
        };

        $sendCommand = function(string $cmd) use ($socket, $getResponse): string {
            fputs($socket, $cmd . "\r\n");
            return $getResponse();
        };

        $greeting = $getResponse();
        if (!str_starts_with($greeting, '220')) {
            fclose($socket);
            return ['success' => false, 'error' => 'SMTP server did not return 220 greeting.'];
        }

        $sendCommand('EHLO localhost');

        if (strtolower($encryption) === 'tls') {
            $tlsRes = $sendCommand('STARTTLS');
            if (str_starts_with($tlsRes, '220')) {
                @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $sendCommand('EHLO localhost');
            }
        }

        if (!empty($username) && !empty($password)) {
            $authRes = $sendCommand('AUTH LOGIN');
            if (!str_starts_with($authRes, '334')) {
                fclose($socket);
                return ['success' => false, 'error' => 'SMTP AUTH LOGIN failed: ' . trim($authRes)];
            }
            $sendCommand(base64_encode($username));
            $passRes = $sendCommand(base64_encode($password));
            if (!str_starts_with($passRes, '235')) {
                fclose($socket);
                return ['success' => false, 'error' => 'SMTP Authentication failed.'];
            }
        }

        $sendCommand("MAIL FROM:<{$fromEmail}>");
        $rcptRes = $sendCommand("RCPT TO:<{$toEmail}>");
        if (!str_starts_with($rcptRes, '250')) {
            fclose($socket);
            return ['success' => false, 'error' => 'SMTP Recipient rejected: ' . trim($rcptRes)];
        }

        if (!empty($ccEmail) && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
            $sendCommand("RCPT TO:<{$ccEmail}>");
        }

        $dataRes = $sendCommand("DATA");
        if (!str_starts_with($dataRes, '354')) {
            fclose($socket);
            return ['success' => false, 'error' => 'SMTP DATA command failed: ' . trim($dataRes)];
        }

        $boundary = '=_wtsbill_' . md5((string)microtime());
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "To: {$toName} <{$toEmail}>\r\n";
        if (!empty($ccEmail) && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
            $headers .= "Cc: {$ccEmail}\r\n";
        }
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";

        if (!empty($attachmentData) && !empty($attachmentFilename)) {
            $encodedAttachment = chunk_split(base64_encode($attachmentData));
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/pdf; name=\"{$attachmentFilename}\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$attachmentFilename}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $encodedAttachment . "\r\n\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        fputs($socket, $headers . $body . ".\r\n");
        $finalRes = $getResponse();
        $sendCommand("QUIT");
        fclose($socket);

        if (str_starts_with($finalRes, '250')) {
            return ['success' => true, 'error' => null];
        }

        return ['success' => false, 'error' => 'SMTP message submission failed: ' . trim($finalRes)];
    }

    /**
     * Native PHP mail() delivery.
     */
    private static function sendNativeMail(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        ?string $attachmentData,
        ?string $attachmentFilename,
        ?string $ccEmail = null
    ): array {
        $boundary = '=_wtsbill_' . md5((string)microtime());
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        if (!empty($ccEmail) && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
            $headers .= "Cc: {$ccEmail}\r\n";
        }
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";

        if (!empty($attachmentData) && !empty($attachmentFilename)) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/pdf; name=\"{$attachmentFilename}\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$attachmentFilename}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($attachmentData)) . "\r\n\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $sent = @mail($toEmail, $subject, $body, $headers);
        return [
            'success' => $sent,
            'error' => $sent ? null : 'SMTP email server is not configured in Settings. Please set up your SMTP server (e.g. Gmail / Outlook / Custom SMTP) in Settings → Communication & Sharing, or use "Download PDF & Open Email App".',
        ];
    }

    /**
     * Log communication entry into communication_messages table.
     */
    public static function logMessage(array $data): int
    {
        $payload = [
            'company_id' => $data['company_id'] ?? 1,
            'branch_id' => $data['branch_id'] ?? 1,
            'channel' => strtoupper((string)($data['channel'] ?? 'WHATSAPP')),
            'entity_type' => strtoupper((string)($data['entity_type'] ?? 'GENERAL')),
            'entity_id' => $data['entity_id'] ?? null,
            'recipient' => (string)($data['recipient'] ?? ''),
            'subject' => (string)($data['subject'] ?? ''),
            'body' => (string)($data['body'] ?? ''),
            'attachment_path' => $data['attachment_path'] ?? null,
            'provider_name' => $data['provider_name'] ?? 'SYSTEM',
            'provider_reference' => $data['provider_reference'] ?? null,
            'status' => strtoupper((string)($data['status'] ?? 'SENT')),
            'error_message' => $data['error_message'] ?? null,
            'sent_at' => in_array(strtoupper((string)($data['status'] ?? '')), ['SENT', 'DELIVERED', 'CLICK_TO_CHAT_GENERATED']) ? date('Y-m-d H:i:s') : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (int)Database::insert('communication_messages', $payload);
    }

    /**
     * Retrieve document communication history.
     */
    public static function getHistory(string $entityType, int $entityId, int $companyId): array
    {
        return Database::select(
            "SELECT id, channel, recipient, subject, status, provider_name, provider_reference, error_message, sent_at, created_at
             FROM communication_messages
             WHERE company_id = ? AND entity_type = ? AND entity_id = ?
             ORDER BY id DESC",
            [$companyId, strtoupper($entityType), $entityId]
        );
    }
}

