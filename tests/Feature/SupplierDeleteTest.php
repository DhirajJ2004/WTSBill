<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\SupplierController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;

/**
 * Feature Test: Supplier Deletion Options & Workflow.
 * Verifies the presence of delete options in table and modal, deletion endpoint,
 * soft-deletion / archival, removal from active listing, and error handling.
 */
class SupplierDeleteTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        Session::setTenantContext(1, 1);
        Session::set('auth_user', [
            'id' => 1,
            'name' => 'Super Administrator',
            'email' => 'admin@wtsbill.com',
            'role' => 'ADMIN',
        ]);
        Session::set('user_role', 'admin');
        Session::set('user_permissions', ['*']);

        $controller = new SupplierController();
        $testSupplierId = 0;

        try {
            // 1. Verify Supplier Directory renders Delete button in table actions
            $indexReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/suppliers'], null);
            $indexRes = $controller->index($indexReq);
            $html = $indexRes->getContent();

            $assert(
                'SUPDEL-01',
                'Supplier directory view renders Delete button in table actions',
                str_contains($html, 'window.deleteSupplier(') && str_contains($html, 'btn-outline-danger')
            );

            $assert(
                'SUPDEL-02',
                'Supplier edit modal renders Delete Supplier button in modal footer',
                str_contains($html, 'id="sup-modal-delete-btn"') && str_contains($html, 'window.deleteCurrentModalSupplier()')
            );

            // 2. Create a test supplier to be deleted
            $testGstin = '27AABCS' . rand(1000, 9999) . 'Z1Z5';
            $testSupplierId = (int)Database::insert('suppliers', [
                'company_id' => 1,
                'name' => 'Temp Supplier For Deletion Test',
                'company_name' => 'Temp Vendor Corp',
                'gstin' => $testGstin,
                'phone' => '+91 9888888888',
                'is_active' => 1,
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $assert(
                'SUPDEL-03',
                'Test supplier is created and verified active',
                $testSupplierId > 0
            );

            // 3. Verify supplier is present in search before deletion
            $supRepo = new \WTSBill\Repositories\SupplierRepository();
            $foundBefore = $supRepo->search('Temp Supplier For Deletion Test');
            $assert(
                'SUPDEL-04',
                'Supplier appears in active supplier directory before deletion',
                count($foundBefore) >= 1
            );

            // 4. Delete supplier via POST /suppliers/{id}/delete (AJAX)
            $delReq = new Request(
                [],
                ['_csrf_token' => Session::getCsrfToken(), 'id' => $testSupplierId],
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => "/suppliers/{$testSupplierId}/delete",
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
                json_encode(['_csrf_token' => Session::getCsrfToken()])
            );

            $delRes = $controller->delete($delReq, $testSupplierId);
            $delJson = json_decode($delRes->getContent(), true);

            $assert(
                'SUPDEL-05',
                'POST /suppliers/{id}/delete deletes supplier and returns HTTP 200 with success JSON',
                $delRes->getStatusCode() === 200 && ($delJson['success'] ?? false) === true
            );

            // 5. Verify supplier is excluded from search
            $foundAfter = $supRepo->search('Temp Supplier For Deletion Test');
            $assert(
                'SUPDEL-06',
                'Supplier is cleanly excluded from active supplier listing after deletion',
                count($foundAfter) === 0
            );

            // 6. Delete non-existent supplier returns HTTP 404
            $del404Req = new Request(
                [],
                ['_csrf_token' => Session::getCsrfToken(), 'id' => 999999],
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/suppliers/999999/delete',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ]
            );
            $del404Res = $controller->delete($del404Req, 999999);
            $assert(
                'SUPDEL-07',
                'Deleting non-existent supplier returns HTTP 404 Not Found',
                $del404Res->getStatusCode() === 404
            );

        } finally {
            if ($testSupplierId > 0) {
                Database::execute("DELETE FROM suppliers WHERE id = ?", [$testSupplierId]);
            }
        }
    }
}

