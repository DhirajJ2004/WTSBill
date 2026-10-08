<?php

namespace App\Http\Controllers\Api;

use App\Models\CommunicationMessage;
use App\Models\CommunicationTemplate;
use App\Automation\Email\EmailService;
use App\Automation\WhatsApp\WhatsAppService;
use App\Automation\Delivery\CommunicationQueue;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AuthorizationException;
use Exception;
use Throwable;

class CommunicationController
{
    /**
     * List communication queue / logs
     */
    public function getMessages(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'view');
            $companyId = AuthMiddleware::getTenantId();
            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $channel = $_GET['channel'] ?? null;
            $status = $_GET['status'] ?? null;

            $query = CommunicationMessage::where('company_id', $companyId);
            if ($channel && $channel !== 'all') {
                $query->where('channel', strtoupper($channel));
            }
            if ($status && $status !== 'all') {
                $query->where('status', strtoupper($status));
            }

            $messages = $query->orderBy('id', 'desc')->limit(100)->get();

            response_json(['success' => true, 'data' => $messages]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send or queue email
     */
    public function sendEmail(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'manage');
            $companyId = AuthMiddleware::getTenantId();
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            if (isset($input['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$input['company_id']);
            }
            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $msg = EmailService::queueEmail(
                companyId: $companyId,
                recipient: $input['recipient'] ?? '',
                templateCode: $input['template_code'] ?? 'MANUAL_EMAIL',
                variables: $input['variables'] ?? [],
                entityType: $input['entity_type'] ?? null,
                entityId: isset($input['entity_id']) ? intval($input['entity_id']) : null,
                subject: $input['subject'] ?? null,
                body: $input['body'] ?? null,
                attachmentPath: $input['attachment_path'] ?? null
            );

            // Trigger immediate background send attempt
            $processed = EmailService::processSend($msg);

            response_json(['success' => true, 'data' => $processed]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Send or queue WhatsApp
     */
    public function sendWhatsApp(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'manage');
            $companyId = AuthMiddleware::getTenantId();
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            if (isset($input['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$input['company_id']);
            }
            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $msg = WhatsAppService::queueWhatsApp(
                companyId: $companyId,
                recipient: $input['recipient'] ?? '',
                templateCode: $input['template_code'] ?? 'MANUAL_WA',
                variables: $input['variables'] ?? [],
                entityType: $input['entity_type'] ?? null,
                entityId: isset($input['entity_id']) ? intval($input['entity_id']) : null,
                body: $input['body'] ?? null,
                documentUrl: $input['document_url'] ?? null
            );

            // Trigger immediate background send attempt
            $processed = WhatsAppService::processSend($msg);

            response_json(['success' => true, 'data' => $processed]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Process queue
     */
    public function processQueue(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'manage');
            $companyId = AuthMiddleware::getTenantId();

            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }
            if (isset($_POST['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_POST['company_id']);
            }

            $results = CommunicationQueue::processQueue($companyId);

            response_json(['success' => true, 'data' => $results]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retry failed message
     */
    public function retry(int $id): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'manage');
            $companyId = AuthMiddleware::getTenantId();

            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $msg = CommunicationQueue::retryFailed($companyId, $id);

            response_json(['success' => true, 'data' => $msg]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Resend message
     */
    public function resend(int $id): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'manage');
            $companyId = AuthMiddleware::getTenantId();

            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $msg = CommunicationQueue::manualResend($companyId, $id);

            response_json(['success' => true, 'data' => $msg]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get templates
     */
    public function getTemplates(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'view');
            $companyId = AuthMiddleware::getTenantId();

            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $templates = CommunicationTemplate::where('company_id', $companyId)->get();

            response_json(['success' => true, 'data' => $templates]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create template
     */
    public function createTemplate(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'manage');
            $companyId = AuthMiddleware::getTenantId();
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            if (isset($input['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$input['company_id']);
            }
            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $tpl = CommunicationTemplate::create([
                'company_id' => $companyId,
                'channel' => strtoupper($input['channel'] ?? 'EMAIL'),
                'template_code' => strtoupper($input['template_code'] ?? 'CUSTOM_TPL'),
                'name' => $input['name'] ?? 'Custom Template',
                'category' => $input['category'] ?? 'Sales',
                'subject' => $input['subject'] ?? null,
                'body' => $input['body'] ?? '',
                'variables_json' => $input['variables'] ?? [],
                'is_active' => true,
            ]);

            response_json(['success' => true, 'data' => $tpl], 201);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
