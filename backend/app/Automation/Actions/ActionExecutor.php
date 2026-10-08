<?php

namespace App\Automation\Actions;

use App\Models\AutomationRule;
use App\Models\AutomationEvent;
use App\Models\AutomationExecutionLog;
use App\Automation\Notifications\NotificationEngine;
use App\Automation\Reminders\ReminderEngine;
use App\Automation\Email\EmailService;
use App\Automation\WhatsApp\WhatsAppService;
use Exception;

class ActionExecutor
{
    /**
     * Execute a list of configured actions for a matched rule.
     */
    public static function executeActions(AutomationRule $rule, AutomationEvent $event): array
    {
        $actions = $rule->actions_json ?: [];
        $companyId = $rule->company_id;
        $results = [];

        foreach ($actions as $action) {
            $actionType = strtoupper($action['type'] ?? $action['action'] ?? 'NOTIFICATION');
            $status = 'SUCCESS';
            $error = null;
            $resData = [];

            try {
                switch ($actionType) {
                    case 'CREATE_NOTIFICATION':
                    case 'IN_APP_NOTIFICATION':
                        $resData = NotificationEngine::createNotification(
                            companyId: $companyId,
                            userId: $action['user_id'] ?? null,
                            category: $action['category'] ?? 'system',
                            title: $action['title'] ?? "Automation: {$rule->name}",
                            message: $action['message'] ?? "Event {$event->event_type} triggered rule {$rule->name}.",
                            entityType: $event->entity_type,
                            entityId: $event->entity_id,
                            priority: $action['priority'] ?? 'NORMAL',
                            actionUrl: $action['action_url'] ?? null
                        );
                        break;

                    case 'SEND_EMAIL':
                    case 'EMAIL':
                        $recipient = $action['recipient'] ?? ($event->metadata_json['recipient_email'] ?? 'customer@example.com');
                        $resData = EmailService::queueEmail(
                            companyId: $companyId,
                            recipient: $recipient,
                            templateCode: $action['template_code'] ?? 'INVOICE_REMINDER',
                            variables: array_merge($event->metadata_json ?: [], $action['variables'] ?? []),
                            entityType: $event->entity_type,
                            entityId: $event->entity_id,
                            subject: $action['subject'] ?? null,
                            body: $action['body'] ?? null
                        );
                        break;

                    case 'SEND_WHATSAPP':
                    case 'WHATSAPP':
                        $recipient = $action['recipient'] ?? ($event->metadata_json['recipient_phone'] ?? '+919876543210');
                        $resData = WhatsAppService::queueWhatsApp(
                            companyId: $companyId,
                            recipient: $recipient,
                            templateCode: $action['template_code'] ?? 'PAYMENT_REMINDER_WA',
                            variables: array_merge($event->metadata_json ?: [], $action['variables'] ?? []),
                            entityType: $event->entity_type,
                            entityId: $event->entity_id,
                            body: $action['body'] ?? null
                        );
                        break;

                    case 'CREATE_REMINDER':
                    case 'REMINDER':
                        $resData = ReminderEngine::createReminder(
                            companyId: $companyId,
                            reminderType: $action['reminder_type'] ?? 'INVOICE_OVERDUE',
                            entityType: $event->entity_type,
                            entityId: $event->entity_id,
                            recipient: $action['recipient'] ?? null,
                            dueDate: $action['due_date'] ?? date('Y-m-d'),
                            scheduleType: $action['schedule_type'] ?? 'AFTER_DUE',
                            offsetDays: intval($action['offset_days'] ?? 3),
                            notes: $action['notes'] ?? "Automated reminder for {$event->entity_type} #{$event->entity_id}"
                        );
                        break;

                    case 'INTERNAL_ALERT':
                        $resData = NotificationEngine::createNotification(
                            companyId: $companyId,
                            userId: $action['user_id'] ?? null,
                            category: 'system',
                            title: $action['title'] ?? "ALERT: {$rule->name}",
                            message: $action['message'] ?? "High-priority alert triggered by {$event->event_type}",
                            entityType: $event->entity_type,
                            entityId: $event->entity_id,
                            priority: 'HIGH'
                        );
                        break;

                    default:
                        $status = 'SKIPPED';
                        $resData = ['message' => "Unknown action type: {$actionType}"];
                }
            } catch (Exception $e) {
                $status = 'FAILED';
                $error = $e->getMessage();
            }

            // Log execution
            AutomationExecutionLog::create([
                'company_id' => $companyId,
                'rule_id' => $rule->id,
                'event_id' => $event->event_id,
                'action_type' => $actionType,
                'entity_type' => $event->entity_type,
                'entity_id' => $event->entity_id,
                'status' => $status,
                'result_data_json' => $resData ?: null,
                'error_message' => $error,
                'executed_at' => date('Y-m-d H:i:s'),
            ]);

            $results[] = [
                'action' => $actionType,
                'status' => $status,
                'error' => $error,
            ];
        }

        return $results;
    }
}
