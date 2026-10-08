<?php

namespace App\Http\Controllers\Api;

use App\Automation\Notifications\NotificationEngine;
use App\Models\Notification;
use App\Http\Middleware\AuthMiddleware;
use Exception;
use Throwable;

class NotificationController
{
    /**
     * List notifications
     */
    public function getNotifications()
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
            $category = $_GET['category'] ?? null;
            $readStatus = $_GET['read_status'] ?? null;

            $data = NotificationEngine::getNotifications($companyId, $userId, $category, $readStatus);

            return response_json(['status' => 'success', 'success' => true, 'data' => $data]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function index($request = null)
    {
        return $this->getNotifications();
    }

    public function unreadCount()
    {
        $companyId = AuthMiddleware::getTenantId();
        $count = Notification::where('company_id', $companyId)->where('is_read', false)->count();

        return response_json(['status' => 'success', 'data' => ['unread_count' => $count]]);
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(int $id)
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $res = NotificationEngine::markAsRead($companyId, intval($id));

            return response_json(['status' => 'success', 'success' => true, 'data' => ['updated' => $res]]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        try {
            $input = get_json_input();
            $companyId = AuthMiddleware::getTenantId();
            $userId = isset($input['user_id']) ? intval($input['user_id']) : null;

            $updatedCount = NotificationEngine::markAllAsRead($companyId, $userId);

            return response_json(['status' => 'success', 'success' => true, 'data' => ['count' => $updatedCount]]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
