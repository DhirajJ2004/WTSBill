<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\CustomerController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;

/**
 * Feature Test: Customer Deletion Options & Workflow.
 * Verifies the presence of delete options in table and modal, deletion endpoint,
 * soft-deletion status update, removal from active listing, and error handling.
 */
class CustomerDeleteTest
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

        $controller = new CustomerController();
        $testCustomerId = 0;

        try {
            // 1. Verify Customer Directory renders Delete button in table actions
            $indexReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/customers'], null);
            $indexRes = $controller->index($indexReq);
            $html = $indexRes->getContent();

            $assert(
                'CUSTDEL-01',
                'Customer directory view renders Delete button in table actions',
                str_contains($html, 'window.deleteCustomer(') && str_contains($html, 'btn-outline-danger')
            );

            $assert(
                'CUSTDEL-02',
                'Customer edit modal renders Delete Customer button in modal footer',
                str_contains($html, 'id="cust-modal-delete-btn"') && str_contains($html, 'window.deleteCurrentModalCustomer()')
            );

            // 2. Create a test customer to be deleted
            $testGstin = '27AABCT9999Z1Z5';
            $testCustomerId = (int)Database::insert('customers', [
                'company_id' => 1,
                'name' => 'Temp Customer For Deletion Test',
                'company_name' => 'Temp Corp',
                'gstin' => $testGstin,
                'phone' => '+91 9999999999',
                'is_active' => 1,
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $assert(
                'CUSTDEL-03',
                'Test customer is created and verified active',
                $testCustomerId > 0
            );

            // 3. Verify customer is present in search before deletion
            $custRepo = new \WTSBill\Repositories\CustomerRepository();
            $foundBefore = $custRepo->search('Temp Customer For Deletion Test');
            $assert(
                'CUSTDEL-04',
                'Customer appears in active customer directory before deletion',
                count($foundBefore) >= 1
            );

            // 4. Delete customer via POST /customers/{id}/delete (AJAX)
            $delReq = new Request(
                [],
                ['_csrf_token' => Session::getCsrfToken()],
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => "/customers/{$testCustomerId}/delete",
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
                json_encode(['_csrf_token' => Session::getCsrfToken()])
            );

            $delRes = $controller->delete($delReq, $testCustomerId);
            $delJson = json_decode($delRes->getContent(), true);

            $assert(
                'CUSTDEL-05',
                'POST /customers/{id}/delete soft-deletes customer and returns HTTP 200 with success JSON',
                $delRes->getStatusCode() === 200 && ($delJson['success'] ?? false) === true
            );

            // 5. Verify customer is marked deleted and excluded from search
            $rowInDb = Database::selectOne(
                "SELECT id, is_active, status, deleted_at FROM customers WHERE id = ?",
                [$testCustomerId]
            );

            $assert(
                'CUSTDEL-06',
                'Customer record in database has deleted_at timestamp set and is_active = 0',
                !empty($rowInDb['deleted_at']) && (int)$rowInDb['is_active'] === 0
            );

            $foundAfter = $custRepo->search('Temp Customer For Deletion Test');
            $assert(
                'CUSTDEL-07',
                'Customer no longer appears in Customer Directory listing after deletion',
                count($foundAfter) === 0
            );

            // 6. Attempting to delete non-existent customer returns 404
            $nonExistentReq = new Request(
                [],
                [],
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => "/customers/999999/delete",
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
                null
            );
            $notFoundRes = $controller->delete($nonExistentReq, 999999);
            $assert(
                'CUSTDEL-08',
                'Attempting to delete non-existent customer returns HTTP 404 Not Found',
                $notFoundRes->getStatusCode() === 404
            );

        } finally {
            // Clean up test customer completely so 21 seeded customers remain intact
            if ($testCustomerId > 0) {
                Database::delete('customers', 'id = :id', ['id' => $testCustomerId]);
            }
        }
    }
}
