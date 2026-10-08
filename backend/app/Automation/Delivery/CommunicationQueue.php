<?php

namespace App\Automation\Delivery;

use App\Models\CommunicationMessage;
use App\Automation\Email\EmailService;
use App\Automation\WhatsApp\WhatsAppService;
use Exception;

class CommunicationQueue
{
    /**
     * Process pending messages in the queue
     */
    public static function processQueue(int $companyId, int $batchSize = 20): array
    {
        $messages = CommunicationMessage::where('company_id', $companyId)
            ->where('status', 'QUEUED')
            ->orderBy('id', 'asc')
            ->limit($batchSize)
            ->get();

        $processed = [];

        foreach ($messages as $msg) {
            if ($msg->channel === 'EMAIL') {
                $result = EmailService::processSend($msg);
            } elseif ($msg->channel === 'WHATSAPP') {
                $result = WhatsAppService::processSend($msg);
            } else {
                $msg->status = 'FAILED';
                $msg->error_message = "Unsupported channel: {$msg->channel}";
                $msg->save();
                $result = $msg;
            }

            $processed[] = [
                'id' => $msg->id,
                'channel' => $msg->channel,
                'status' => $result->status,
                'error' => $result->error_message,
            ];
        }

        return $processed;
    }

    /**
     * Retry a failed communication message
     */
    public static function retryFailed(int $companyId, int $messageId): CommunicationMessage
    {
        $message = CommunicationMessage::where('company_id', $companyId)->findOrFail($messageId);

        if ($message->status !== 'FAILED') {
            throw new Exception("Only failed messages can be retried. Current status: {$message->status}");
        }

        if ($message->attempts >= $message->max_attempts) {
            $message->max_attempts += 1; // Allow authorized manual retry
        }

        $message->status = 'QUEUED';
        $message->error_message = null;
        $message->save();

        if ($message->channel === 'EMAIL') {
            return EmailService::processSend($message);
        } elseif ($message->channel === 'WHATSAPP') {
            return WhatsAppService::processSend($message);
        }

        return $message;
    }

    /**
     * Manual resend creating an audited new attempt
     */
    public static function manualResend(int $companyId, int $messageId, ?string $userName = 'System'): CommunicationMessage
    {
        $original = CommunicationMessage::where('company_id', $companyId)->findOrFail($messageId);

        $newMessage = $original->replicate();
        $newMessage->status = 'QUEUED';
        $newMessage->attempts = 0;
        $newMessage->provider_reference = null;
        $newMessage->sent_at = null;
        $newMessage->delivered_at = null;
        $newMessage->error_message = null;
        $newMessage->save();

        if ($newMessage->channel === 'EMAIL') {
            return EmailService::processSend($newMessage);
        } elseif ($newMessage->channel === 'WHATSAPP') {
            return WhatsAppService::processSend($newMessage);
        }

        return $newMessage;
    }

    /**
     * Get communication history for an entity (Invoice, Customer, Payment, etc.)
     */
    public static function getHistory(int $companyId, ?string $entityType = null, ?int $entityId = null, int $limit = 50): array
    {
        $query = CommunicationMessage::where('company_id', $companyId);

        if ($entityType) {
            $query->where('entity_type', strtoupper($entityType));
        }

        if ($entityId) {
            $query->where('entity_id', $entityId);
        }

        return $query->orderBy('created_at', 'desc')->limit($limit)->get()->toArray();
    }
}
