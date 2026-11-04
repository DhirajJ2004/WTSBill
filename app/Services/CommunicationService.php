<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Session;

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
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_encryption' => 'tls',
                'smtp_from_address' => 'billing@wtsbill.in',
                'smtp_from_name' => 'WTSBill Enterprise',
                'email_configured' => 0,
                'whatsapp_configured' => 0,
            ];
        }

        return $settings;
    }

    /**
     * Persist communication settings for a company.
     */
    public static function saveSettings(int $companyId, array $data): bool
    {
        $existing = Database::selectOne(
            "SELECT id, smtp_password, whatsapp_access_token FROM business_communication_settings WHERE company_id = ? LIMIT 1",
            [$companyId]
        );

        $smtpPassword = !empty($data['smtp_password']) ? (string)$data['smtp_password'] : ($existing['smtp_password'] ?? '');
        $whatsappToken = !empty($data['whatsapp_access_token']) ? (string)$data['whatsapp_access_token'] : ($existing['whatsapp_access_token'] ?? '');

        $emailConfigured = (!empty($data['smtp_host']) && !empty($data['smtp_from_address'])) ? 1 : 0;
        $whatsappConfigured = (!empty($data['whatsapp_phone_number_id']) && !empty($whatsappToken)) ? 1 : 0;

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
            $msg .= "\nView & Download Invoice: {$documentUrl}\n";
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
            $msg .= "\nReview Quotation: {$documentUrl}\n";
        }

        $msg .= "\nPlease let us know if you have any questions or require revisions.\n{$companyName}";
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
        ?int $userId = null
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
                $message
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
        string $text
    ): array {
        $endpoint = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";
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
        ?int $userId = null
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
        $fromEmail = $settings['smtp_from_address'] ?: 'billing@wtsbill.in';
        $fromName = $settings['smtp_from_name'] ?: 'WTSBill Enterprise';

        $status = 'FAILED';
        $errorMessage = null;

        // Attempt SMTP socket if configured
        if (!empty($settings['smtp_host'])) {
            $smtpRes = self::sendSmtpMail(
                $settings['smtp_host'],
                (int)($settings['smtp_port'] ?: 587),
                $settings['smtp_username'] ?? '',
                $settings['smtp_password'] ?? '',
                $settings['smtp_encryption'] ?? 'tls',
                $fromEmail,
                $fromName,
                $toEmail,
                $toName,
                $subject,
                $htmlBody,
                $attachmentData,
                $attachmentFilename
            );

            if ($smtpRes['success']) {
                $status = 'SENT';
            } else {
                $errorMessage = $smtpRes['error'];
            }
        } else {
            // Native PHP mail() fallback
            $mailRes = self::sendNativeMail(
                $fromEmail,
                $fromName,
                $toEmail,
                $subject,
                $htmlBody,
                $attachmentData,
                $attachmentFilename
            );

            if ($mailRes['success']) {
                $status = 'SENT';
            } else {
                $errorMessage = $mailRes['error'];
            }
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
            'provider_name' => !empty($settings['smtp_host']) ? 'CUSTOM_SMTP' : 'NATIVE_MAIL',
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
     * Socket-based pure PHP SMTP Client.
     */
    private static function sendSmtpMail(
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
        ?string $attachmentFilename
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
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
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

        $dataRes = $sendCommand("DATA");
        if (!str_starts_with($dataRes, '354')) {
            fclose($socket);
            return ['success' => false, 'error' => 'SMTP DATA command failed: ' . trim($dataRes)];
        }

        $boundary = '=_wtsbill_' . md5((string)microtime());
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "To: {$toName} <{$toEmail}>\r\n";
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
        ?string $attachmentFilename
    ): array {
        $boundary = '=_wtsbill_' . md5((string)microtime());
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
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
            'error' => $sent ? null : 'Native PHP mail() transfer failed or is unconfigured on local environment.',
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

