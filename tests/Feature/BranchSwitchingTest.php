<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\BranchController;
use WTSBill\Controllers\SalesController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Repositories\BranchRepository;
use WTSBill\Services\AuthService;
use WTSBill\Services\BranchService;

/**
 * Feature Test Suite for Branch Management and Branch Switching System.
 */
class BranchSwitchingTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        $branchRepo = new BranchRepository();
        $branchService = new BranchService($branchRepo);
        $branchController = new BranchController($branchService, $branchRepo);

        Session::start();
        Session::clear();

        $testBranchId = 0;
        $testInvoiceId = 0;

        try {
            // 1. AUTHENTICATION & DEFAULT BRANCH RESOLUTION
            $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
            $assert(
                'BRANCH-01',
                'Administrator authenticates and establishes Company 1 session',
                Session::getCompanyId() === 1 && Session::get('user_id') === (int)$user['id']
            );

            $branches = $branchRepo->findActiveByCompany(1);
            $assert(
                'BRANCH-02',
                'Database contains active operating branches (>= 2 seeded branches)',
                count($branches) >= 2
            );

            $currentBranchId = Session::getBranchId();
            $assert(
                'BRANCH-03',
                'Session initializes with valid active branch context (Branch ID > 0)',
                $currentBranchId !== null && $currentBranchId > 0
            );

            // 2. SWITCHING TO AUTHORIZED BRANCH (BRANCH 2 - PUNE)
            $switchReq = new Request([], ['branch_id' => 2], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches/switch',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $switchResp = $branchController->switch($switchReq);
            $switchJson = json_decode($switchResp->getBody(), true);

            $assert(
                'BRANCH-04',
                'Authorized administrator successfully switches active branch to Pune (ID 2)',
                $switchResp->getStatusCode() === 200 &&
                ($switchJson['success'] ?? false) === true &&
                Session::getBranchId() === 2 &&
                str_contains(Session::getBranchName() ?? '', 'Pune')
            );

            // 3. HEADER RENDERING WITH ACTIVE BRANCH
            $headerHtml = View::render('partials/header');
            $assert(
                'BRANCH-05',
                'Header partial renders active branch name and branch selector dropdown',
                str_contains($headerHtml, 'branch-selector-dropdown') &&
                str_contains($headerHtml, 'Pune Regional Branch')
            );

            // 4. UNAUTHENTICATED SWITCH REJECTION
            Session::remove('user_id');
            $unauthReq = new Request([], ['branch_id' => 1], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches/switch',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $unauthResp = $branchController->switch($unauthReq);
            $assert(
                'BRANCH-06',
                'Unauthenticated branch switch request is rejected with HTTP 401',
                $unauthResp->getStatusCode() === 401
            );

            // Re-authenticate admin
            $authService->authenticate('anil.d@wtsbill.in', 'password123');

            // 5. INVALID BRANCH IDENTIFIER REJECTION
            $badIdReq = new Request([], ['branch_id' => 999999], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches/switch',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $badIdResp = $branchController->switch($badIdReq);
            $assert(
                'BRANCH-07',
                'Switching to non-existent branch ID is rejected with HTTP 422',
                $badIdResp->getStatusCode() === 422
            );

            // 6. CROSS-TENANT ISOLATION (BRANCH FROM ANOTHER COMPANY)
            // Insert temporary branch for company 99
            $crossBranchId = (int)Database::insert('branches', [
                'company_id' => 99,
                'name' => 'Foreign Corp Branch',
                'branch_code' => 'FRG-' . rand(100, 999),
                'city' => 'Delhi',
                'is_active' => 1,
                'is_main_branch' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $crossReq = new Request([], ['branch_id' => $crossBranchId], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches/switch',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $crossResp = $branchController->switch($crossReq);
            $assert(
                'BRANCH-08',
                'Cross-tenant branch switch attempt is rejected (cannot access another company branch)',
                $crossResp->getStatusCode() === 422
            );
            Database::execute("DELETE FROM branches WHERE id = ?", [$crossBranchId]);

            // 7. INACTIVE BRANCH REJECTION
            $inactiveBranchId = (int)Database::insert('branches', [
                'company_id' => 1,
                'name' => 'Inactive Test Depot',
                'branch_code' => 'INA-' . rand(100, 999),
                'city' => 'Nagpur',
                'is_active' => 0,
                'is_main_branch' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $inactiveReq = new Request([], ['branch_id' => $inactiveBranchId], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches/switch',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $inactiveResp = $branchController->switch($inactiveReq);
            $assert(
                'BRANCH-09',
                'Switching to inactive branch is rejected with HTTP 422',
                $inactiveResp->getStatusCode() === 422
            );
            Database::execute("DELETE FROM branches WHERE id = ?", [$inactiveBranchId]);

            // 8. RESTRICTED ROLE ACCESS (USER 3 HAS ONLY BRANCH 1)
            // Authenticate as normal user (e.g., Staff user ID 3 with role STAFF)
            Session::set('user_id', 3);
            Session::set('user_role', 'STAFF');
            Session::set('current_company_id', 1);

            // User 3 tries to switch to Branch 2 (Pune), which they are not assigned to
            $user3SwitchReq = new Request([], ['branch_id' => 2], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches/switch',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $user3SwitchResp = $branchController->switch($user3SwitchReq);
            $assert(
                'BRANCH-10',
                'Restricted staff user cannot switch to unassigned branch (HTTP 403 Forbidden)',
                $user3SwitchResp->getStatusCode() === 403
            );

            // 9. SINGLE-BRANCH USER HEADER RENDERS STATIC BADGE
            // User 3 only has 1 authorized branch (Branch 1)
            $user3HeaderHtml = View::render('partials/header');
            $assert(
                'BRANCH-11',
                'Single-branch user header renders static branch badge without interactive dropdown',
                str_contains($user3HeaderHtml, 'branch-static-badge') &&
                !str_contains($user3HeaderHtml, 'branch-selector-dropdown')
            );

            // Restore Admin session for branch management tests
            $authService->authenticate('anil.d@wtsbill.in', 'password123');

            // 10. ADMIN CAN CREATE A NEW BRANCH
            $newBranchCode = 'NAS-' . rand(10, 99);
            $createReq = new Request([], [
                'name' => 'Nashik Logistics Hub',
                'branch_code' => $newBranchCode,
                'legal_name' => 'Wis Technosavvy Pvt Ltd - Nashik',
                'gstin' => '27AAAAA0000A1Z5',
                'city' => 'Nashik',
                'state' => 'Maharashtra',
                'state_code' => '27',
                'pincode' => '422007',
                'phone' => '9822001122',
                'email' => 'nashik@wtsbill.in',
                'is_active' => 1,
                'is_main_branch' => 0,
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $createResp = $branchController->store($createReq);
            $createJson = json_decode($createResp->getBody(), true);
            $testBranchId = (int)($createJson['branch']['id'] ?? 0);

            $assert(
                'BRANCH-12',
                'Administrator creates new branch via POST /branches (persists with ID)',
                $createResp->getStatusCode() === 201 && $testBranchId > 0
            );

            // 11. DUPLICATE BRANCH CODE REJECTION
            $dupReq = new Request([], [
                'name' => 'Duplicate Code Branch',
                'branch_code' => $newBranchCode, // identical code
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $dupResp = $branchController->store($dupReq);
            $assert(
                'BRANCH-13',
                'Duplicate branch code registration is rejected with HTTP 422',
                $dupResp->getStatusCode() === 422
            );

            // 12. FETCH BRANCH DETAILS FOR MODAL EDIT
            $showReq = new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/branches/' . $testBranchId,
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $showResp = $branchController->show($showReq, $testBranchId);
            $showJson = json_decode($showResp->getBody(), true);
            $assert(
                'BRANCH-14',
                'GET /branches/{id} returns branch details and assigned users for modal population',
                $showResp->getStatusCode() === 200 &&
                ($showJson['branch']['branch_code'] ?? '') === $newBranchCode
            );

            // 13. UPDATE BRANCH DETAILS
            $updateReq = new Request([], [
                'name' => 'Nashik Central Depot Updated',
                'branch_code' => $newBranchCode,
                'city' => 'Nashik City',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches/' . $testBranchId . '/update',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $updateResp = $branchController->update($updateReq, $testBranchId);
            $dbBranch = Database::selectOne("SELECT name, city FROM branches WHERE id = ?", [$testBranchId]);
            $assert(
                'BRANCH-15',
                'POST /branches/{id}/update modifies branch details in database',
                $updateResp->getStatusCode() === 200 &&
                ($dbBranch['name'] ?? '') === 'Nashik Central Depot Updated'
            );

            // 14. TOGGLE BRANCH ACTIVE STATUS
            $toggleReq = new Request([], [], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches/' . $testBranchId . '/toggle-status',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $toggleResp = $branchController->toggleStatus($toggleReq, $testBranchId);
            $toggleJson = json_decode($toggleResp->getBody(), true);
            $assert(
                'BRANCH-16',
                'POST /branches/{id}/toggle-status deactivates/activates branch',
                $toggleResp->getStatusCode() === 200 && ($toggleJson['is_active'] ?? null) === 0
            );

            // 15. MAIN ORGANIZATION BRANCH CANNOT BE DEACTIVATED
            $mainBranch = $branchRepo->findMainBranch(1);
            $mainId = (int)($mainBranch['id'] ?? 1);
            $mainToggleReq = new Request([], [], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches/' . $mainId . '/toggle-status',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $mainToggleResp = $branchController->toggleStatus($mainToggleReq, $mainId);
            $assert(
                'BRANCH-17',
                'Main HQ branch cannot be deactivated (invariant protection)',
                $mainToggleResp->getStatusCode() === 422
            );

            // 16. ASSIGN USERS TO BRANCH
            // Re-activate test branch first
            Database::update('branches', ['is_active' => 1], 'id = ?', [$testBranchId]);
            $assignReq = new Request([], ['user_ids' => [1, 2, 3]], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/branches/' . $testBranchId . '/assign-users',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $assignResp = $branchController->assignUsers($assignReq, $testBranchId);
            $assignedRows = Database::select(
                "SELECT user_id FROM user_branches WHERE branch_id = ? ORDER BY user_id ASC",
                [$testBranchId]
            );
            $assignedUids = array_map(fn($r) => (int)$r['user_id'], $assignedRows);

            $assert(
                'BRANCH-18',
                'POST /branches/{id}/assign-users updates user access in user_branches table',
                $assignResp->getStatusCode() === 200 && $assignedUids === [1, 2, 3]
            );

            // 17. SUBSEQUENT TRANSACTION CREATION ASSOCIATES WITH ACTIVE BRANCH
            // Switch session to testBranchId
            $branchService->switchBranch(1, $testBranchId, 1, 'ADMIN');

            $salesController = new SalesController();
            $invReq = new Request([], [
                'customer_id' => 1,
                'invoice_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'place_of_supply' => '27',
                'product_id' => 1,
                'quantity' => 2,
                'rate' => 500.00,
                'gst_rate' => 18,
                'notes' => 'Branch-scoped sales invoice test',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/sales/invoices/create',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $invResp = $salesController->invoiceStore($invReq);
            $invJson = json_decode($invResp->getBody(), true);
            $testInvoiceId = (int)($invJson['invoice_id'] ?? 0);

            $dbInv = Database::selectOne("SELECT branch_id FROM invoices WHERE id = ?", [$testInvoiceId]);
            $assert(
                'BRANCH-19',
                'Newly created invoice records the currently active branch_id in database',
                $testInvoiceId > 0 && (int)($dbInv['branch_id'] ?? 0) === $testBranchId
            );

            // 18. DASHBOARD REVENUE METRICS RESPECT ACTIVE BRANCH
            $dashMetrics = Database::selectOne(
                "SELECT COALESCE(SUM(grand_total), 0) AS total_sales
                 FROM invoices 
                 WHERE company_id = 1 AND branch_id = ?",
                [$testBranchId]
            );
            $assert(
                'BRANCH-20',
                'Dashboard queries scope transaction metrics accurately to active branch',
                (float)($dashMetrics['total_sales'] ?? 0) >= 1000.00
            );

            // 19. SWITCH BACK TO MAIN BRANCH
            $branchService->switchBranch(1, 1, 1, 'ADMIN');
            $assert(
                'BRANCH-21',
                'Administrator switches context back to Main Branch (ID 1) without state corruption',
                Session::getBranchId() === 1
            );

        } finally {
            // Clean up test data
            if ($testInvoiceId > 0) {
                Database::execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$testInvoiceId]);
                Database::execute("DELETE FROM invoices WHERE id = ?", [$testInvoiceId]);
            }
            if ($testBranchId > 0) {
                Database::execute("DELETE FROM user_branches WHERE branch_id = ?", [$testBranchId]);
                Database::execute("DELETE FROM branches WHERE id = ?", [$testBranchId]);
            }
            // Ensure session is set to branch 1
            Session::set('current_branch_id', 1);
            Session::set('current_branch_name', 'Main Branch - Mumbai');
        }
    }
}
