<?php

namespace App\Automation\Notifications;

use App\Models\InAppNotification;

class NotificationEngine
{
    /**
     * Create an in-app notification.
     */
    public static function createNotification(
        int $companyId,
        ?int $userId = null,
        string $category = 'system',
        string $title = 'Notification',
        string $message = '',
        ?string $entityType = null,
        ?int $entityId = null,
        string $priority = 'NORMAL',
        ?string $actionUrl = null
    ): InAppNotification {
        return InAppNotification::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'category' => strtolower($category),
            'type' => strtoupper($priority),
            'title' => $title,
            'message' => $message,
            'entity_type' => $entityType ? strtoupper($entityType) : null,
            'entity_id' => $entityId,
            'action_url' => $actionUrl,
            'read_status' => 'UNREAD',
            'priority' => strtoupper($priority),
        ]);
    }

    /**
     * Get notifications with filtering and unread metrics.
     */
    public static function getNotifications(
        int $companyId,
        ?int $userId = null,
        ?string $category = null,
        ?string $readStatus = null,
        int $limit = 50
    ): array {
        $query = InAppNotification::where('company_id', $companyId);

        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            });
        }

        if ($category && $category !== 'all') {
            $query->where('category', strtolower($category));
        }

        if ($readStatus && $readStatus !== 'all') {
            $query->where('read_status', strtoupper($readStatus));
        }

        $notifications = $query->orderBy('created_at', 'desc')->limit($limit)->get();
        $unreadCount = self::getUnreadCount($companyId, $userId);

        return [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ];
    }

    /**
     * Get count of unread notifications.
     */
    public static function getUnreadCount(int $companyId, ?int $userId = null): int
    {
        $query = InAppNotification::where('company_id', $companyId)->where('read_status', 'UNREAD');
        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            });
        }
        return $query->count();
    }

    /**
     * Mark single notification as read.
     */
    public static function markAsRead(int $companyId, int $notificationId): bool
    {
        $notif = InAppNotification::where('company_id', $companyId)->find($notificationId);
        if ($notif) {
            $notif->read_status = 'READ';
            $notif->save();
            return true;
        }
        return false;
    }

    /**
     * Mark all notifications as read.
     */
    public static function markAllAsRead(int $companyId, ?int $userId = null): int
    {
        $query = InAppNotification::where('company_id', $companyId)->where('read_status', 'UNREAD');
        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            });
        }
        return $query->update(['read_status' => 'READ']);
    }
}
