<?php

namespace App\Http\Controllers\Api;

use App\DocumentEngine\Services\DocumentService;
use App\DocumentEngine\Sharing\DocumentShareService;
use App\DocumentEngine\Templates\DocumentTemplateRegistry;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\DocumentNumberSetting;
use App\Http\Middleware\AuthMiddleware;
use Exception;

class DocumentController
{
    /**
     * Preview Document (Read-Only)
     */
    public function preview(string $type, string $id): void
    {
        $user = AuthMiddleware::authorize('documents', 'view');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();

        $templateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : null;

        try {
            $result = DocumentService::previewDocument($type, $id, $companyId, $branchId, $templateId);
            response_json($result);
        } catch (Exception $e) {
            response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Generate & Download PDF
     */
    public function pdf(string $type, string $id): void
    {
        $user = AuthMiddleware::authorize('documents', 'pdf');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();

        $templateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : null;

        try {
            $result = DocumentService::generateDocument($type, $id, $companyId, $branchId, $templateId, 'PDF', $user->name);
            response_json($result);
        } catch (Exception $e) {
            response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get Print-Ready Document
     */
    public function printDoc(string $type, string $id): void
    {
        $user = AuthMiddleware::authorize('documents', 'print');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();

        $templateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : null;

        try {
            $result = DocumentService::previewDocument($type, $id, $companyId, $branchId, $templateId);
            header('Content-Type: text/html; charset=utf-8');
            echo $result['html'];
            exit;
        } catch (Exception $e) {
            response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create Secure Share Link
     */
    public function createShare(string $type, string $id): void
    {
        $user = AuthMiddleware::authorize('documents', 'share');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        try {
            $genResult = DocumentService::generateDocument($type, $id, $companyId, $branchId, null, 'PDF', $user->name);
            $share = DocumentShareService::createShareLink(
                generatedDocumentId: $genResult['generated_document_id'],
                recipientName: $input['recipient_name'] ?? null,
                recipientContact: $input['recipient_contact'] ?? null,
                expiryDays: isset($input['expiry_days']) ? (int)$input['expiry_days'] : 7,
                allowDownload: $input['allow_download'] ?? true,
                allowPrint: $input['allow_print'] ?? true,
                password: $input['password'] ?? null,
                channel: $input['channel'] ?? 'LINK',
                userName: $user->name
            );

            response_json([
                'status' => 'success',
                'share_token' => $share->share_token,
                'share_url' => "/shared/document/{$share->share_token}",
                'expires_at' => $share->expires_at ? $share->expires_at->toIso8601String() : null,
            ], 201);
        } catch (Exception $e) {
            response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Send Document via Email
     */
    public function email(string $type, string $id): void
    {
        $user = AuthMiddleware::authorize('documents', 'share');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $toEmail = $input['to_email'] ?? '';
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            response_json(['status' => 'error', 'message' => 'Valid recipient email address is required.'], 422);
            return;
        }

        try {
            $genResult = DocumentService::generateDocument($type, $id, $companyId, $branchId, null, 'PDF', $user->name);
            $res = DocumentShareService::sendEmail(
                generatedDocumentId: $genResult['generated_document_id'],
                toEmail: $toEmail,
                customSubject: $input['subject'] ?? null,
                customBody: $input['body'] ?? null,
                userName: $user->name
            );

            response_json($res);
        } catch (Exception $e) {
            response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Send Document via WhatsApp
     */
    public function whatsapp(string $type, string $id): void
    {
        $user = AuthMiddleware::authorize('documents', 'share');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $phone = $input['phone'] ?? '';
        if (empty($phone)) {
            response_json(['status' => 'error', 'message' => 'Recipient phone number is required.'], 422);
            return;
        }

        try {
            $genResult = DocumentService::generateDocument($type, $id, $companyId, $branchId, null, 'PDF', $user->name);
            $res = DocumentShareService::sendWhatsApp(
                generatedDocumentId: $genResult['generated_document_id'],
                phoneNumber: $phone,
                customMessage: $input['message'] ?? null,
                userName: $user->name
            );

            response_json($res);
        } catch (Exception $e) {
            response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Public Shared Document Access
     */
    public function accessShared(string $token): void
    {
        $password = $_GET['password'] ?? ($_POST['password'] ?? null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $res = DocumentShareService::accessSharedDocument($token, $password, $ip, $ua);
        response_json($res, $res['code'] ?? 200);
    }

    /**
     * Revoke Shared Document Link
     */
    public function revokeShare(string $token): void
    {
        $user = AuthMiddleware::authorize('documents', 'share');
        $success = DocumentShareService::revokeShareLink($token, $user->name);
        response_json(['status' => $success ? 'success' : 'error', 'message' => $success ? 'Link revoked.' : 'Token not found.']);
    }

    /**
     * List Templates
     */
    public function listTemplates(): void
    {
        $user = AuthMiddleware::authorize('documents', 'view');
        $companyId = $user->current_company_id;

        DocumentTemplateRegistry::seedDefaultTemplates($companyId);
        $templates = DocumentTemplate::where('company_id', $companyId)->get();
        response_json(['status' => 'success', 'data' => $templates]);
    }

    /**
     * Update Template
     */
    public function updateTemplate(string $id): void
    {
        $user = AuthMiddleware::authorize('documents', 'template_manage');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $tmpl = DocumentTemplate::where('company_id', $companyId)->findOrFail($id);

        // Versioning Snapshot
        DocumentTemplateVersion::create([
            'company_id' => $companyId,
            'template_id' => $tmpl->id,
            'version_number' => $tmpl->version,
            'config_json' => $tmpl->toArray(),
            'created_by' => $user->name,
        ]);

        $tmpl->update(array_merge($input, ['version' => $tmpl->version + 1]));
        response_json(['status' => 'success', 'data' => $tmpl]);
    }

    /**
     * List Document Numbering Series
     */
    public function listNumbering(): void
    {
        $user = AuthMiddleware::authorize('documents', 'view');
        $companyId = $user->current_company_id;

        $series = DocumentNumberSetting::where('company_id', $companyId)->get();
        response_json(['status' => 'success', 'data' => $series]);
    }

    /**
     * Save / Update Document Numbering Series
     */
    public function saveNumbering(): void
    {
        $user = AuthMiddleware::authorize('documents', 'numbering_manage');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $docType = strtoupper($input['document_type'] ?? 'INVOICE');
        $branchId = isset($input['branch_id']) ? (int)$input['branch_id'] : null;
        $fy = $input['financial_year'] ?? '2026-27';

        $setting = DocumentNumberSetting::updateOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'financial_year' => $fy,
                'document_type' => $docType,
            ],
            [
                'prefix' => $input['prefix'] ?? 'INV-',
                'suffix' => $input['suffix'] ?? null,
                'starting_number' => (int)($input['starting_number'] ?? 1),
                'padding_length' => (int)($input['padding_length'] ?? 6),
                'is_active' => true,
            ]
        );

        response_json(['status' => 'success', 'data' => $setting]);
    }
}
