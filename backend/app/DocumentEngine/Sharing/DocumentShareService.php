<?php

namespace App\DocumentEngine\Sharing;

use App\Models\GeneratedDocument;
use App\Models\DocumentShare;
use App\Models\DocumentAuditLog;
use App\DocumentEngine\Models\DocumentModel;
use App\DocumentEngine\Renderers\PdfDocumentRenderer;
use Carbon\Carbon;

class DocumentShareService
{
    /**
     * Generate secure, revocable, and expirable share token
     */
    public static function createShareLink(
        int $generatedDocumentId,
        ?string $recipientName = null,
        ?string $recipientContact = null,
        ?int $expiryDays = 7,
        bool $allowDownload = true,
        bool $allowPrint = true,
        ?string $password = null,
        string $channel = 'LINK',
        string $userName = 'System'
    ): DocumentShare {
        $doc = GeneratedDocument::findOrFail($generatedDocumentId);

        $token = bin2hex(random_bytes(24)); // 48-char secure token
        $expiresAt = $expiryDays ? Carbon::now()->addDays($expiryDays) : null;
        $passwordHash = $password ? password_hash($password, PASSWORD_BCRYPT) : null;

        $share = DocumentShare::create([
            'company_id' => $doc->company_id,
            'generated_document_id' => $doc->id,
            'share_token' => $token,
            'channel' => strtoupper($channel),
            'recipient_name' => $recipientName,
            'recipient_contact' => $recipientContact,
            'allow_download' => $allowDownload,
            'allow_print' => $allowPrint,
            'password_hash' => $passwordHash,
            'access_count' => 0,
            'expires_at' => $expiresAt,
            'is_revoked' => false,
            'created_by' => $userName,
        ]);

        // Audit Log
        DocumentAuditLog::create([
            'company_id' => $doc->company_id,
            'generated_document_id' => $doc->id,
            'action' => 'SHARED',
            'user_name' => $userName,
            'metadata_json' => [
                'channel' => $channel,
                'token' => $token,
                'expires_at' => $expiresAt ? $expiresAt->toIso8601String() : null,
                'recipient' => $recipientContact,
            ]
        ]);

        return $share;
    }

    /**
     * Access shared document by public token
     */
    public static function accessSharedDocument(string $token, ?string $password = null, string $ipAddress = '127.0.0.1', ?string $userAgent = null): array
    {
        $share = DocumentShare::withoutGlobalScopes()->where('share_token', $token)->first();
        if (!$share || !$share->isValid()) {
            return [
                'status' => 'error',
                'message' => 'Document link is invalid, expired, or has been revoked.',
                'code' => 404,
            ];
        }

        if ($share->password_hash) {
            if (!$password || !password_verify($password, $share->password_hash)) {
                return [
                    'status' => 'error',
                    'message' => 'Password required or incorrect password provided.',
                    'requires_password' => true,
                    'code' => 401,
                ];
            }
        }

        $share->increment('access_count');

        $doc = GeneratedDocument::withoutGlobalScopes()->find($share->generated_document_id);
        if (!$doc) {
            return ['status' => 'error', 'message' => 'Source document not found.', 'code' => 404];
        }

        // Audit view
        DocumentAuditLog::create([
            'company_id' => $doc->company_id,
            'generated_document_id' => $doc->id,
            'action' => 'LINK_OPENED',
            'user_name' => $share->recipient_name ?: 'Public Client',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata_json' => ['token' => $token, 'access_count' => $share->access_count]
        ]);

        $snapshot = $doc->snapshot_json;
        $docModel = $snapshot ? DocumentModel::fromArray($snapshot) : null;

        return [
            'status' => 'success',
            'document' => [
                'document_number' => $doc->document_number,
                'document_type' => $doc->document_type,
                'document_date' => $doc->document_date,
                'file_name' => $doc->file_name,
                'allow_download' => $share->allow_download,
                'allow_print' => $share->allow_print,
            ],
            'snapshot' => $docModel ? $docModel->toArray() : null,
        ];
    }

    /**
     * Revoke share token
     */
    public static function revokeShareLink(string $token, string $userName = 'System'): bool
    {
        $share = DocumentShare::where('share_token', $token)->first();
        if (!$share) return false;

        $share->update([
            'is_revoked' => true,
            'revoked_at' => Carbon::now(),
        ]);

        DocumentAuditLog::create([
            'company_id' => $share->company_id,
            'generated_document_id' => $share->generated_document_id,
            'action' => 'REVOKED',
            'user_name' => $userName,
            'metadata_json' => ['token' => $token]
        ]);

        return true;
    }

    /**
     * Send document via Email provider
     */
    public static function sendEmail(
        int $generatedDocumentId,
        string $toEmail,
        ?string $customSubject = null,
        ?string $customBody = null,
        string $userName = 'System',
        ?EmailProviderInterface $provider = null
    ): array {
        $doc = GeneratedDocument::findOrFail($generatedDocumentId);
        $provider = $provider ?: new MockEmailProvider();

        $snapshot = $doc->snapshot_json ?: [];
        $docModel = DocumentModel::fromArray($snapshot);
        $rendered = PdfDocumentRenderer::renderToPdf($docModel, $doc->template);

        $subject = $customSubject ?: ($doc->document_type . ' ' . $doc->document_number . ' from ' . ($docModel->company['name'] ?? 'Company'));
        $body = $customBody ?: "Dear Customer,\n\nPlease find attached {$doc->document_type} ({$doc->document_number}) for your reference.\n\nThank you,\n" . ($docModel->company['name'] ?? 'WTSBill');

        $result = $provider->sendDocument($toEmail, $subject, $body, $rendered['pdf_content'], $doc->file_name);

        DocumentAuditLog::create([
            'company_id' => $doc->company_id,
            'generated_document_id' => $doc->id,
            'action' => 'EMAILED',
            'user_name' => $userName,
            'metadata_json' => [
                'recipient' => $toEmail,
                'subject' => $subject,
                'result' => $result,
            ]
        ]);

        return $result;
    }

    /**
     * Send document via WhatsApp provider
     */
    public static function sendWhatsApp(
        int $generatedDocumentId,
        string $phoneNumber,
        ?string $customMessage = null,
        string $userName = 'System',
        ?WhatsAppProviderInterface $provider = null
    ): array {
        $doc = GeneratedDocument::findOrFail($generatedDocumentId);
        $provider = $provider ?: new MockWhatsAppProvider();

        // Create secure 14-day link for WhatsApp viewing
        $share = static::createShareLink($doc->id, null, $phoneNumber, 14, true, true, null, 'WHATSAPP', $userName);
        $shareUrl = "/shared/document/{$share->share_token}";

        $message = $customMessage ?: "Hello, here is your {$doc->document_type} ({$doc->document_number}). View or download your invoice here: {$shareUrl}";

        $result = $provider->sendMessage($phoneNumber, $message, $shareUrl);

        DocumentAuditLog::create([
            'company_id' => $doc->company_id,
            'generated_document_id' => $doc->id,
            'action' => 'WHATSAPP_SENT',
            'user_name' => $userName,
            'metadata_json' => [
                'recipient_phone' => $phoneNumber,
                'share_token' => $share->share_token,
                'result' => $result,
            ]
        ]);

        return $result;
    }
}
