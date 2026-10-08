<?php

namespace App\Automation\WhatsApp;

use App\Models\CommunicationMessage;
use App\Models\CommunicationTemplate;
use App\Automation\Templates\TemplateEngine;
use Exception;

class WhatsAppService
{
    protected static ?WhatsAppProviderInterface $provider = null;

    public static function setProvider(WhatsAppProviderInterface $provider): void
    {
        self::$provider = $provider;
    }

    public static function getProvider(): WhatsAppProviderInterface
    {
        if (self::$provider === null) {
            self::$provider = new class implements WhatsAppProviderInterface {
                public function isConfigured(): bool
                {
                    return !empty(getenv('WHATSAPP_API_KEY')) || !empty(getenv('META_WA_TOKEN'));
                }
                public function getProviderName(): string
                {
                    return 'Meta WhatsApp Cloud';
                }
                public function send(string $phone, string $message, ?string $documentUrl = null): array
                {
                    if (!$this->isConfigured()) {
                        return [
                            'success' => false,
                            'status' => 'FAILED',
                            'error' => 'Communication provider is not configured.',
                        ];
                    }
                    return [
                        'success' => true,
                        'status' => 'SENT',
                        'provider_reference' => 'WA-' . strtoupper(uniqid()),
                    ];
                }
            };
        }
        return self::$provider;
    }

    /**
     * Queue a WhatsApp message for sending.
     */
    public static function queueWhatsApp(
        int $companyId,
        string $recipient,
        string $templateCode,
        array $variables = [],
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $body = null,
        ?string $documentUrl = null,
        ?int $branchId = null
    ): CommunicationMessage {
        $cleanPhone = preg_replace('/[^0-9+]/', '', $recipient);
        if (strlen($cleanPhone) < 10) {
            throw new Exception("Invalid recipient phone number format: {$recipient}");
        }

        $renderedBody = $body ?: "You have a new transaction update from WTSBill.";

        $template = CommunicationTemplate::where('company_id', $companyId)
            ->where('template_code', $templateCode)
            ->where('channel', 'WHATSAPP')
            ->first();

        if ($template) {
            $renderedBody = TemplateEngine::render($template->body, $variables);
        } elseif ($body) {
            $renderedBody = TemplateEngine::render($body, $variables);
        }

        $message = CommunicationMessage::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'channel' => 'WHATSAPP',
            'entity_type' => $entityType ? strtoupper($entityType) : null,
            'entity_id' => $entityId,
            'recipient' => $cleanPhone,
            'template_code' => $templateCode,
            'body' => $renderedBody,
            'variables_json' => $variables,
            'attachment_path' => $documentUrl,
            'status' => 'QUEUED',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        return $message;
    }

    /**
     * Process sending a queued WhatsApp message.
     */
    public static function processSend(CommunicationMessage $message): CommunicationMessage
    {
        $provider = self::getProvider();

        $message->attempts += 1;
        $message->status = 'SENDING';
        $message->save();

        if (!$provider->isConfigured()) {
            $message->status = 'FAILED';
            $message->error_message = 'Communication provider is not configured.';
            $message->provider_name = $provider->getProviderName();
            $message->save();
            return $message;
        }

        $res = $provider->send($message->recipient, $message->body, $message->attachment_path);

        if (!empty($res['success'])) {
            $message->status = 'SENT';
            $message->provider_name = $provider->getProviderName();
            $message->provider_reference = $res['provider_reference'] ?? null;
            $message->sent_at = date('Y-m-d H:i:s');
            $message->error_message = null;
        } else {
            $message->status = 'FAILED';
            $message->provider_name = $provider->getProviderName();
            $message->error_message = $res['error'] ?? 'Unable to send WhatsApp message.';
        }

        $message->save();
        return $message;
    }
}
