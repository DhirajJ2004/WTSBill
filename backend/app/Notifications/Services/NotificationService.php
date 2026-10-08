<?php

namespace App\Notifications\Services;

use App\Models\InAppNotification;
use App\Models\NotificationTemplate;
use App\Notifications\Events\BusinessEvent;
use App\Notifications\Templates\TemplateService;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Process business event and deliver relevant in-app & external notifications.
     */
    public static function processEvent(BusinessEvent $event): void
    {
        $companyId = $event->companyId;
        $eventType = $event->eventType;
        $payload = $event->payload;

        TemplateService::ensureDefaultTemplates($companyId);

        switch ($eventType) {
            case 'PAYMENT_RECEIVED':
                self::createInAppAlert(
                    $companyId,
                    "Payment Received: ₹" . number_format($payload['amount'] ?? 0, 2),
                    "Received payment from " . ($payload['customer_name'] ?? 'Customer') . " for Invoice " . ($payload['invoice_number'] ?? ''),
                    'PAYMENTS',
                    'NORMAL',
                    'PAYMENT',
                    $event->entityId
                );
                break;

            case 'LOW_STOCK_DETECTED':
                self::createInAppAlert(
                    $companyId,
                    "Low Stock Alert: " . ($payload['product_name'] ?? 'Product'),
                    "Product SKU {$payload['sku']} stock is at {$payload['current_stock']} (Min: {$payload['minimum_stock']})",
                    'INVENTORY',
                    'HIGH',
                    'PRODUCT',
                    $event->entityId
                );
                break;

            case 'BATCH_EXPIRING':
                self::createInAppAlert(
                    $companyId,
                    "Batch Expiry Alert: " . ($payload['batch_number'] ?? 'Batch'),
                    "Batch #{$payload['batch_number']} for {$payload['product_name']} expires on {$payload['expiry_date']}",
                    'INVENTORY',
                    'HIGH',
                    'PRODUCT',
                    $event->entityId
                );
                break;

            case 'TRANSFER_RECEIVED':
                self::createInAppAlert(
                    $companyId,
                    "Stock Transfer Received: #" . ($payload['transfer_number'] ?? ''),
                    "Transfer of {$payload['total_quantity']} items received at {$payload['destination_warehouse']}",
                    'TRANSFERS',
                    'NORMAL',
                    'STOCK_TRANSFER',
                    $event->entityId
                );
                break;

            case 'RECURRING_INVOICE_DUE':
            case 'RECURRING_INVOICE_CREATED':
                self::createInAppAlert(
                    $companyId,
                    "Recurring Invoice Generated: #" . ($payload['invoice_number'] ?? ''),
                    "Generated scheduled invoice for {$payload['customer_name']} totaling ₹" . number_format($payload['invoice_total'] ?? 0, 2),
                    'SALES',
                    'NORMAL',
                    'INVOICE',
                    $event->entityId
                );
                break;

            case 'SYSTEM_ALERT':
                self::createInAppAlert(
                    $companyId,
                    $payload['title'] ?? 'System Notice',
                    $payload['message'] ?? 'Notice',
                    'SYSTEM',
                    $payload['priority'] ?? 'HIGH'
                );
                break;
        }
    }

    /**
     * Create In-App notification alert.
     */
    public static function createInAppAlert(
        int $companyId,
        string $title,
        string $message,
        string $category = 'SYSTEM',
        string $priority = 'NORMAL',
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $userId = null
    ): InAppNotification {
        return InAppNotification::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'category' => strtoupper($category),
            'priority' => strtoupper($priority),
            'title' => $title,
            'message' => $message,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_read' => false,
        ]);
    }

    /**
     * Get In-App notifications with filters and pagination.
     */
    public static function getInAppNotifications(int $companyId, ?int $userId = null, array $filters = []): array
    {
        $query = InAppNotification::where('company_id', $companyId)
            ->where(function ($q) use ($userId) {
                if ($userId) $q->where('user_id', $userId)->orWhereNull('user_id');
                else $q->whereNull('user_id');
            })
            ->orderBy('id', 'desc');

        if (!empty($filters['category']) && $filters['category'] !== 'ALL') {
            $query->where('category', strtoupper($filters['category']));
        }

        if (isset($filters['is_read']) && $filters['is_read'] !== 'ALL') {
            $query->where('is_read', (bool)$filters['is_read']);
        }

        $items = $query->paginate(25);
        $unreadCount = self::getUnreadCount($companyId, $userId);

        return [
            'unread_count' => $unreadCount,
            'notifications' => $items,
        ];
    }

    /**
     * Get unread notifications count.
     */
    public static function getUnreadCount(int $companyId, ?int $userId = null): int
    {
        return InAppNotification::where('company_id', $companyId)
            ->where('is_read', false)
            ->where(function ($q) use ($userId) {
                if ($userId) $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->count();
    }

    /**
     * Mark single notification as read.
     */
    public static function markAsRead(int $id): InAppNotification
    {
        $notification = InAppNotification::findOrFail($id);
        $notification->update([
            'is_read' => true,
            'read_at' => Carbon::now(),
        ]);
        return $notification;
    }

    /**
     * Mark all notifications as read.
     */
    public static function markAllAsRead(int $companyId, ?int $userId = null): int
    {
        return InAppNotification::where('company_id', $companyId)
            ->where('is_read', false)
            ->where(function ($q) use ($userId) {
                if ($userId) $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->update([
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);
    }
}
