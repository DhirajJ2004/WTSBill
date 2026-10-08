<?php

namespace App\Automation\Services;

use App\Models\AutomationRule;
use App\Models\AutomationEvent;
use App\Models\AutomationExecutionLog;
use App\Models\Reminder;
use App\Models\CommunicationMessage;
use App\Models\RecurringTemplate;
use App\Models\InAppNotification;
use Illuminate\Database\Capsule\Manager as DB;

class AutomationAnalyticsService
{
    /**
     * Get aggregate KPIs and chart datasets for the Automation Dashboard
     */
    public static function getDashboardMetrics(int $companyId): array
    {
        $today = date('Y-m-d');

        $activeRulesCount = AutomationRule::where('company_id', $companyId)->where('is_active', true)->count();
        $eventsTodayCount = AutomationEvent::where('company_id', $companyId)->whereDate('occurred_at', $today)->count();
        $failedActionsCount = AutomationExecutionLog::where('company_id', $companyId)->where('status', 'FAILED')->count();

        $pendingRemindersCount = Reminder::where('company_id', $companyId)->whereIn('status', ['SCHEDULED', 'PENDING'])->count();
        $activeRecurringCount = RecurringTemplate::where('company_id', $companyId)->where('status', 'ACTIVE')->count();
        $unreadNotifsCount = InAppNotification::where('company_id', $companyId)->where('read_status', 'UNREAD')->count();

        $queuedCommsCount = CommunicationMessage::where('company_id', $companyId)->where('status', 'QUEUED')->count();
        $sentCommsCount = CommunicationMessage::where('company_id', $companyId)->whereIn('status', ['SENT', 'DELIVERED', 'READ'])->count();
        $failedCommsCount = CommunicationMessage::where('company_id', $companyId)->where('status', 'FAILED')->count();

        // Execution status breakdown
        $executionBreakdown = [
            'success' => AutomationExecutionLog::where('company_id', $companyId)->where('status', 'SUCCESS')->count(),
            'failed' => $failedActionsCount,
            'skipped' => AutomationExecutionLog::where('company_id', $companyId)->where('status', 'SKIPPED')->count(),
        ];

        // Channel breakdown
        $emailStats = [
            'queued' => CommunicationMessage::where('company_id', $companyId)->where('channel', 'EMAIL')->where('status', 'QUEUED')->count(),
            'sent' => CommunicationMessage::where('company_id', $companyId)->where('channel', 'EMAIL')->where('status', 'SENT')->count(),
            'failed' => CommunicationMessage::where('company_id', $companyId)->where('channel', 'EMAIL')->where('status', 'FAILED')->count(),
        ];

        $waStats = [
            'queued' => CommunicationMessage::where('company_id', $companyId)->where('channel', 'WHATSAPP')->where('status', 'QUEUED')->count(),
            'sent' => CommunicationMessage::where('company_id', $companyId)->where('channel', 'WHATSAPP')->where('status', 'SENT')->count(),
            'failed' => CommunicationMessage::where('company_id', $companyId)->where('channel', 'WHATSAPP')->where('status', 'FAILED')->count(),
        ];

        return [
            'kpis' => [
                'active_rules' => $activeRulesCount,
                'events_today' => $eventsTodayCount,
                'failed_actions' => $failedActionsCount,
                'pending_reminders' => $pendingRemindersCount,
                'active_recurring' => $activeRecurringCount,
                'unread_notifications' => $unreadNotifsCount,
                'queued_messages' => $queuedCommsCount,
                'sent_messages' => $sentCommsCount,
                'failed_messages' => $failedCommsCount,
            ],
            'execution_breakdown' => $executionBreakdown,
            'email_stats' => $emailStats,
            'whatsapp_stats' => $waStats,
        ];
    }
}
