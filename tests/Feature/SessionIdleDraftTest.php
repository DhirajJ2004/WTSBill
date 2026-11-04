<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Config;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\DraftService;

/**
 * Feature Test Suite for 15-Minute Inactivity Session Expiration & Form Draft Auto-Saving.
 */
class SessionIdleDraftTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Authenticate user
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $userId = (int)$user['id'];
        $companyId = Session::getCompanyId() ?? 1;
        $branchId = Session::getBranchId() ?? 1;
        $csrfToken = Session::csrfToken();

        $assert('DRAFT-01', 'User authenticates and session inactivity lifetime is configured to 900 seconds (15 minutes)',
            $user !== null &&
            (int)Config::get('session.lifetime', 900) === 900
        );

        // 2. Active User Activity Resets Timestamp
        $initialTime = time() - 200;
        Session::set('last_activity_at', $initialTime);

        // Simulated qualifying user action
        $authService->check();
        $updatedTime = (int)Session::get('last_activity_at');

        $assert('DRAFT-02', 'Qualifying user request refreshes last_activity_at timestamp',
            $updatedTime > $initialTime &&
            $updatedTime >= (time() - 2)
        );

        // 3. Background Requests with X-Session-KeepAlive: 0 Do NOT Extend Session
        $frozenTime = time() - 300;
        Session::set('last_activity_at', $frozenTime);

        $_SERVER['HTTP_X_SESSION_KEEPALIVE'] = '0';
        $authService->check();
        $afterBgCheck = (int)Session::get('last_activity_at');
        unset($_SERVER['HTTP_X_SESSION_KEEPALIVE']);

        $assert('DRAFT-03', 'Background request with X-Session-KeepAlive: 0 strictly preserves inactivity timer',
            $afterBgCheck === $frozenTime
        );

        // 4. Session Expiration after 15 Minutes (901 seconds) of Inactivity
        $expiredTime = time() - 905; // > 900 seconds
        Session::set('last_activity_at', $expiredTime);

        $isStillAuth = $authService->check();
        $assert('DRAFT-04', 'Inactivity exceeding 15 minutes (900 seconds) terminates session on server-side',
            $isStillAuth === false &&
            Session::get('user_id') === null
        );

        // Re-authenticate for Draft testing
        $authService->authenticate('anil.d@wtsbill.in', 'password123');

        // 5. Save In-Flight Form Draft
        $sampleInvoiceData = [
            'customer_id' => '1',
            'invoice_date' => date('Y-m-d'),
            'payment_terms' => 'NET30',
            'notes' => 'In-flight enterprise quotation draft',
            'items' => [
                ['product_id' => 1, 'quantity' => 5, 'unit_price' => 1250],
                ['product_id' => 2, 'quantity' => 10, 'unit_price' => 1850],
            ]
        ];

        $draftRes = DraftService::saveDraft($companyId, $branchId, $userId, 'INVOICE', '/sales/invoices/create', $sampleInvoiceData);
        $draftId = (int)($draftRes['draft_id'] ?? 0);

        $dbDraft = Database::selectOne("SELECT * FROM user_drafts WHERE id = ?", [$draftId]);

        $assert('DRAFT-05', 'DraftService::saveDraft persists form data to user_drafts table with status DRAFT',
            $draftRes['success'] === true &&
            $draftId > 0 &&
            $dbDraft !== null &&
            $dbDraft['form_type'] === 'INVOICE' &&
            $dbDraft['status'] === 'DRAFT'
        );

        // 6. Draft Isolation Safeguard: Incomplete Drafts Do NOT Affect Invoices or Journal Entries
        $invoicesWithDraftNotes = Database::select(
            "SELECT id FROM invoices WHERE notes LIKE '%In-flight enterprise quotation draft%'"
        );
        $journalsWithDraftNotes = Database::select(
            "SELECT id FROM journal_entries WHERE narration LIKE '%In-flight enterprise quotation draft%'"
        );

        $assert('DRAFT-06', 'Unsaved form drafts are strictly isolated and never appear in invoices or accounting journals',
            empty($invoicesWithDraftNotes) &&
            empty($journalsWithDraftNotes)
        );

        // 7. Retrieve Saved Draft
        $retrieved = DraftService::getDraft($companyId, $userId, 'INVOICE', '/sales/invoices/create');

        $assert('DRAFT-07', 'DraftService::getDraft cleanly recovers structured form data and items',
            $retrieved !== null &&
            $retrieved['id'] === $draftId &&
            isset($retrieved['data']['customer_id']) &&
            $retrieved['data']['customer_id'] === '1' &&
            isset($retrieved['data']['items']) &&
            count($retrieved['data']['items']) === 2
        );

        // 8. Auto-Save API Endpoint
        $apiSaveReq = new Request([], [
            'form_type' => 'QUOTATION',
            'form_key' => '/sales/quotations/create',
            'form_data' => [
                'customer_id' => '2',
                'valid_until' => date('Y-m-d', strtotime('+30 days')),
                'items' => [['product_id' => 3, 'qty' => 2, 'rate' => 1060]]
            ]
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/drafts/auto-save',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_X_SESSION_KEEPALIVE' => '0',
        ]);
        $apiSaveRes = $router->dispatch($apiSaveReq);
        $apiSaveJson = json_decode($apiSaveRes->getContent(), true);

        $assert('DRAFT-08', 'POST /api/drafts/auto-save accepts background auto-save payload with HTTP 200',
            $apiSaveRes->getStatusCode() === 200 &&
            isset($apiSaveJson['success']) && $apiSaveJson['success'] === true &&
            isset($apiSaveJson['draft_id'])
        );

        // 9. Check Draft API Endpoint
        $apiCheckReq = new Request(['form_type' => 'QUOTATION', 'form_key' => '/sales/quotations/create'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/drafts/check',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $apiCheckRes = $router->dispatch($apiCheckReq);
        $apiCheckJson = json_decode($apiCheckRes->getContent(), true);

        $assert('DRAFT-09', 'GET /api/drafts/check verifies active draft availability for client banner prompt',
            $apiCheckRes->getStatusCode() === 200 &&
            isset($apiCheckJson['success']) && $apiCheckJson['success'] === true &&
            $apiCheckJson['has_draft'] === true &&
            isset($apiCheckJson['draft']['data']['customer_id'])
        );

        // 10. Discard Draft
        DraftService::discardDraft($companyId, $userId, 'INVOICE', '/sales/invoices/create');
        $afterDiscard = DraftService::getDraft($companyId, $userId, 'INVOICE', '/sales/invoices/create');

        $assert('DRAFT-10', 'DraftService::discardDraft cleanly purges restored draft after form submission or user discard',
            $afterDiscard === null
        );

        // 11. Client Session Idle Script and Layout Linking
        $jsPath = dirname(__DIR__, 2) . '/public/assets/js/session-idle.js';
        $layoutHtml = file_get_contents(dirname(__DIR__, 2) . '/views/layouts/app.php');

        $assert('DRAFT-11', 'session-idle.js exists and is linked in app.php layout for multi-tab activity monitoring',
            file_exists($jsPath) &&
            str_contains($layoutHtml, '/assets/js/session-idle.js') &&
            str_contains(file_get_contents($jsPath), 'TIMEOUT_SECONDS = 900')
        );
    }
}
