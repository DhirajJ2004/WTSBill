<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\NotificationService;

/**
 * Feature Test Suite for Phase 10: Central In-App Notification Center
 * & Verification of Complete AI Assistant Removal.
 */
class NotificationCenterHubTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();

        // Load web routes
        $router = new Router();
        require ROOT_PATH . '/routes/web.php';

        // 1. NOTIF-01: Guest redirect for notifications
        Session::destroy();
        Session::start();
        $guestNotif = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/notifications']);
        $guestRes = $router->dispatch($guestNotif);
        $assert('NOTIF-01', 'Unauthenticated request to /notifications redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && $guestRes->getHeader('Location') === '/login'
        );

        // Authenticate as Admin
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $userId = (int)(Session::getUserId() ?? 1);

        $notifService = new NotificationService();

        // 2. NOTIF-02: Notifications Hub View
        $notifReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/notifications']);
        $notifRes = $router->dispatch($notifReq);
        $notifBody = $notifRes->getBody();
        $assert('NOTIF-02', 'GET /notifications returns HTTP 200 with notification center hub and category filters',
            $notifRes->getStatusCode() === 200 &&
            str_contains($notifBody, 'Notification Center') &&
            str_contains($notifBody, 'All Categories')
        );

        // 3. NOTIF-03: Unread Notifications Count API
        $countReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/notifications/unread-count']);
        $countRes = $router->dispatch($countReq);
        $countJson = json_decode($countRes->getBody(), true);
        $assert('NOTIF-03', 'GET /api/notifications/unread-count returns structured JSON with integer unread_count',
            $countRes->getStatusCode() === 200 &&
            isset($countJson['unread_count']) &&
            is_int($countJson['unread_count'])
        );

        // 4. NOTIF-04: Create Notification Persistence
        $testNotifId = $notifService->createNotification(
            $companyId,
            $userId,
            'Critical Test Alert',
            'Automated test stock threshold reached for QA validation',
            'INVENTORY',
            'HIGH',
            '/inventory'
        );
        $assert('NOTIF-04', 'createNotification successfully persists unread notification into in_app_notifications',
            $testNotifId > 0
        );

        // 5. NOTIF-05: Mark Single Notification As Read
        $markReq = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/api/notifications/{$testNotifId}/read"]);
        $markRes = $router->dispatch($markReq);
        $markJson = json_decode($markRes->getBody(), true);
        $dbNotif = Database::selectOne("SELECT is_read, read_at FROM in_app_notifications WHERE id = ?", [$testNotifId]);
        $assert('NOTIF-05', 'POST /api/notifications/{id}/read marks notification as read and sets timestamp',
            $markRes->getStatusCode() === 200 &&
            ($markJson['success'] ?? false) === true &&
            (int)($dbNotif['is_read'] ?? 0) === 1 &&
            !empty($dbNotif['read_at'])
        );

        // 6. NOTIF-06: Mark All Notifications As Read
        $markAllReq = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/notifications/mark-all-read']);
        $markAllRes = $router->dispatch($markAllReq);
        $markAllJson = json_decode($markAllRes->getBody(), true);
        $remainingUnread = $notifService->getUnreadCount($companyId, $userId);
        $assert('NOTIF-06', 'POST /api/notifications/mark-all-read clears unread queue to zero',
            $markAllRes->getStatusCode() === 200 &&
            ($markAllJson['success'] ?? false) === true &&
            $remainingUnread === 0
        );

        // 7. NOTIF-07: Automated System Alert Generation (Stock & Overdue Debts)
        $systemAlerts = $notifService->generateSystemAlerts($companyId);
        $assert('NOTIF-07', 'generateSystemAlerts automatically scans inventory and overdue debts without error',
            is_array($systemAlerts) &&
            isset($systemAlerts['alerts_generated']) &&
            isset($systemAlerts['low_stock_count']) &&
            isset($systemAlerts['overdue_count'])
        );

        // Cleanup test notification
        Database::execute("DELETE FROM in_app_notifications WHERE id = ?", [$testNotifId]);

        // 8. AIREM-01: Verify AI Assistant Web Route /assistant Returns HTTP 404
        $aiReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/assistant']);
        $aiRes = $router->dispatch($aiReq);
        $assert('AIREM-01', 'Request to removed /assistant route returns HTTP 404 Not Found',
            $aiRes->getStatusCode() === 404
        );

        // 9. AIREM-02: Verify AI Assistant API Route /api/assistant/query Returns HTTP 404
        $aiApiReq = new Request([], ['query' => 'test'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/assistant/query']);
        $aiApiRes = $router->dispatch($aiApiReq);
        $assert('AIREM-02', 'Request to removed /api/assistant/query API endpoint returns HTTP 404 Not Found',
            $aiApiRes->getStatusCode() === 404
        );

        // 10. AIREM-03: Verify AI Assistant is completely absent from navigation menu configuration
        $menuConfig = require ROOT_PATH . '/config/menu.php';
        $hasAiNav = false;
        foreach ($menuConfig as $sectionKey => $section) {
            if (is_array($section)) {
                foreach ($section as $item) {
                    if (is_array($item)) {
                        if (($item['id'] ?? '') === 'assistant' || str_contains(strtolower($item['label'] ?? ''), 'ai assistant')) {
                            $hasAiNav = true;
                        }
                    }
                }
            }
        }
        $assert('AIREM-03', 'config/menu.php contains zero AI Assistant sidebar navigation entries',
            $hasAiNav === false
        );
    }
}

