<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\NotificationService;

/**
 * Notification Center Controller.
 * Handles in-app notifications hub, real-time unread badges,
 * and alert acknowledge actions.
 */
class NotificationController
{
    protected NotificationService $notificationService;

    public function __construct(?NotificationService $notificationService = null)
    {
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * In-App Notifications Hub View.
     * Route: GET /notifications
     */
    public function index(Request $req): Response
    {
        Gate::authorize('dashboard.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = (int)(Session::getUserId() ?? 0);
        $status = (string)$req->get('status', 'all');
        $category = (string)$req->get('category', 'ALL');

        // Automatically trigger system alert sync to catch fresh low stock or overdue invoices
        $this->notificationService->generateSystemAlerts($companyId);

        $result = $this->notificationService->getNotifications($companyId, $userId, [
            'status' => $status,
            'category' => $category,
        ]);

        $html = View::renderWithLayout('notifications/index', [
            'title' => 'Notifications & System Alerts',
            'currentUri' => '/notifications',
            'notifications' => $result['notifications'],
            'unreadCount' => $result['unread_count'],
            'status' => $status,
            'category' => $category,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Unread Notifications Counter JSON API.
     * Route: GET /api/notifications/unread-count
     */
    public function unreadCount(Request $req): Response
    {
        Gate::authorize('dashboard.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = (int)(Session::getUserId() ?? 0);

        $count = $this->notificationService->getUnreadCount($companyId, $userId);

        return Response::json(['success' => true, 'unread_count' => $count]);
    }

    /**
     * Mark single notification as read.
     * Route: POST /api/notifications/{id}/read
     */
    public function markRead(Request $req, int|string $id): Response
    {
        Gate::authorize('dashboard.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $success = $this->notificationService->markAsRead((int)$id, $companyId);

        return Response::json(['success' => $success]);
    }

    /**
     * Mark all notifications as read.
     * Route: POST /api/notifications/mark-all-read
     */
    public function markAllRead(Request $req): Response
    {
        Gate::authorize('dashboard.view', $req);

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = (int)(Session::getUserId() ?? 0);

        $success = $this->notificationService->markAllAsRead($companyId, $userId);

        return Response::json(['success' => $success]);
    }
}
