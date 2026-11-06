<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;

/**
 * Central In-App Notification Center Service.
 * Manages system alerts, low-stock warnings, overdue payment triggers,
 * and user read-state tracking.
 */
class NotificationService
{
    /**
     * Get paginated notifications for the company and optional user.
     */
    public function getNotifications(int $companyId, ?int $userId = null, array $filters = []): array
    {
        $status = strtolower(trim((string)($filters['status'] ?? 'all'))); // all, unread, read
        $category = strtoupper(trim((string)($filters['category'] ?? 'ALL')));

        $where = "company_id = ?";
        $params = [$companyId];

        if ($userId !== null && $userId > 0) {
            $where .= " AND (user_id = ? OR user_id IS NULL)";
            $params[] = $userId;
        }

        if ($status === 'unread') {
            $where .= " AND is_read = 0";
        } elseif ($status === 'read') {
            $where .= " AND is_read = 1";
        }

        if ($category !== 'ALL' && !empty($category)) {
            $where .= " AND category = ?";
            $params[] = $category;
        }

        $notifications = Database::select(
            "SELECT id, company_id, user_id, category, priority, title, message, entity_type, entity_id, is_read, read_at, created_at, action_url 
             FROM in_app_notifications 
             WHERE {$where} 
             ORDER BY is_read ASC, id DESC 
             LIMIT 50",
            $params
        );

        $unreadCount = $this->getUnreadCount($companyId, $userId);

        return [
            'total' => count($notifications),
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ];
    }

    /**
     * Get unread notification count.
     */
    public function getUnreadCount(int $companyId, ?int $userId = null): int
    {
        $where = "company_id = ? AND is_read = 0";
        $params = [$companyId];

        if ($userId !== null && $userId > 0) {
            $where .= " AND (user_id = ? OR user_id IS NULL)";
            $params[] = $userId;
        }

        $row = Database::selectOne(
            "SELECT COUNT(id) AS unread FROM in_app_notifications WHERE {$where}",
            $params
        );

        return (int)($row['unread'] ?? 0);
    }

    /**
     * Mark single notification as read.
     */
    public function markAsRead(int $notificationId, int $companyId): bool
    {
        return Database::execute(
            "UPDATE in_app_notifications 
             SET is_read = 1, read_at = NOW(), updated_at = NOW() 
             WHERE id = ? AND company_id = ?",
            [$notificationId, $companyId]
        ) > 0;
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(int $companyId, ?int $userId = null): bool
    {
        $where = "company_id = ? AND is_read = 0";
        $params = [$companyId];

        if ($userId !== null && $userId > 0) {
            $where .= " AND (user_id = ? OR user_id IS NULL)";
            $params[] = $userId;
        }

        return Database::execute(
            "UPDATE in_app_notifications 
             SET is_read = 1, read_at = NOW(), updated_at = NOW() 
             WHERE {$where}",
            $params
        );
    }

    /**
     * Create an in-app notification.
     */
    public function createNotification(
        int $companyId,
        ?int $userId,
        string $title,
        string $message,
        string $category = 'SYSTEM',
        string $priority = 'NORMAL',
        ?string $actionUrl = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): int {
        return (int)Database::insert('in_app_notifications', [
            'company_id' => $companyId,
            'user_id' => $userId,
            'category' => $category,
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'action_url' => $actionUrl,
        ]);
    }

    /**
     * Automatically scan business state and generate alerts for low stock and overdue invoices.
     */
    public function generateSystemAlerts(int $companyId): array
    {
        $createdAlerts = 0;

        // 1. Scan for low-stock products
        $lowStockProducts = Database::select(
            "SELECT id, name, sku, current_stock, min_stock_alert 
             FROM products 
             WHERE company_id = ? AND is_active = 1 AND current_stock <= min_stock_alert AND deleted_at IS NULL 
             LIMIT 10",
            [$companyId]
        );

        foreach ($lowStockProducts as $p) {
            // Check if unread alert already exists for this product
            $existing = Database::selectOne(
                "SELECT id FROM in_app_notifications 
                 WHERE company_id = ? AND entity_type = 'PRODUCT' AND entity_id = ? AND is_read = 0 LIMIT 1",
                [$companyId, (int)$p['id']]
            );

            if (!$existing) {
                $this->createNotification(
                    $companyId,
                    null,
                    "Low Stock Alert: {$p['name']}",
                    "Product '{$p['name']}' (SKU: {$p['sku']}) has only {$p['current_stock']} units remaining (threshold: {$p['min_stock_alert']}).",
                    'INVENTORY',
                    'HIGH',
                    "/inventory/velocity",
                    'PRODUCT',
                    (int)$p['id']
                );
                $createdAlerts++;
            }
        }

        // 2. Scan for overdue invoices
        $overdueInvoices = Database::select(
            "SELECT i.id, i.invoice_number, i.amount_due, i.due_date, COALESCE(c.name, i.customer_name) AS customer_name 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id
             WHERE i.company_id = ? AND i.amount_due > 0 AND i.due_date < CURDATE() AND i.deleted_at IS NULL 
             LIMIT 10",
            [$companyId]
        );

        foreach ($overdueInvoices as $inv) {
            $existing = Database::selectOne(
                "SELECT id FROM in_app_notifications 
                 WHERE company_id = ? AND entity_type = 'INVOICE' AND entity_id = ? AND is_read = 0 LIMIT 1",
                [$companyId, (int)$inv['id']]
            );

            if (!$existing) {
                $this->createNotification(
                    $companyId,
                    null,
                    "Overdue Invoice: {$inv['invoice_number']}",
                    "Invoice {$inv['invoice_number']} for {$inv['customer_name']} is overdue with ₹" . number_format((float)$inv['amount_due'], 2) . " unpaid.",
                    'FINANCIAL',
                    'URGENT',
                    "/sales/invoices/{$inv['id']}",
                    'INVOICE',
                    (int)$inv['id']
                );
                $createdAlerts++;
            }
        }

        return [
            'alerts_generated' => $createdAlerts,
            'low_stock_count' => count($lowStockProducts),
            'overdue_count' => count($overdueInvoices),
        ];
    }
}
