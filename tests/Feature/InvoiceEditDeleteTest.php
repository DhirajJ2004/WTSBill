<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\SalesController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Feature Test Suite for Tax Invoice Edit and Delete Actions.
 */
class InvoiceEditDeleteTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        $controller = new SalesController();

        Session::start();
        Session::clear();

        // 1. Authenticate Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $assert(
            'INVED-01',
            'Administrator authenticates and sets tenant context',
            Session::getCompanyId() === 1 && Session::get('user_id') === (int)$user['id']
        );

        $createdInvId = 0;
        try {
            // 2. Create test invoice with 2 items
            $createReq = new Request([], [
                'customer_id' => 1,
                'invoice_date' => '2026-10-27',
                'due_date' => '2026-11-26',
                'place_of_supply' => '27',
                'items' => [
                    ['product_id' => 1, 'quantity' => 2, 'rate' => 500.00, 'gst_rate' => 18, 'hsn_sac' => '7318'],
                    ['product_id' => 2, 'quantity' => 1, 'rate' => 1000.00, 'gst_rate' => 18, 'hsn_sac' => '8481']
                ],
                'notes' => 'Test invoice for edit and delete'
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/sales/invoices/create',
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $createResp = $controller->invoiceStore($createReq);
            $createJson = json_decode((string)$createResp->getBody(), true);
            $createdInvId = (int)($createJson['invoice_id'] ?? 0);

            $assert(
                'INVED-02',
                'Create invoice with multiple items succeeds (HTTP 201)',
                $createResp->getStatusCode() === 201 && $createdInvId > 0
            );

            // 3. GET /sales/invoices/{id}/edit renders populated edit form
            $editFormReq = new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => "/sales/invoices/{$createdInvId}/edit",
            ]);
            $editFormResp = $controller->invoiceEditForm($editFormReq, $createdInvId);
            $formHtml = (string)$editFormResp->getBody();

            $assert(
                'INVED-03',
                'GET /sales/invoices/{id}/edit returns HTTP 200 with populated line items and form controls',
                $editFormResp->getStatusCode() === 200 &&
                str_contains($formHtml, 'Edit Invoice Form') &&
                str_contains($formHtml, 'existing-items-data')
            );

            // 4. Update invoice line items via POST /sales/invoices/{id}/update
            $updateReq = new Request([], [
                'customer_id' => 1,
                'invoice_date' => '2026-10-27',
                'due_date' => '2026-11-26',
                'place_of_supply' => '27',
                'items' => [
                    ['product_id' => 1, 'quantity' => 5, 'rate' => 600.00, 'gst_rate' => 18, 'hsn_sac' => '7318']
                ],
                'notes' => 'Updated invoice notes'
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$createdInvId}/update",
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $updateResp = $controller->invoiceUpdate($updateReq, $createdInvId);
            $updateJson = json_decode((string)$updateResp->getBody(), true);
            $updatedInv = Database::selectOne("SELECT sub_total, grand_total, notes FROM invoices WHERE id = ?", [$createdInvId]);
            $itemsCount = Database::selectOne("SELECT COUNT(*) as cnt FROM invoice_items WHERE invoice_id = ?", [$createdInvId]);

            $assert(
                'INVED-04',
                'POST /sales/invoices/{id}/update recalculates taxes and persists updated line items',
                $updateResp->getStatusCode() === 200 &&
                ($updateJson['success'] ?? false) === true &&
                (float)$updatedInv['sub_total'] === 3000.00 &&
                (int)$itemsCount['cnt'] === 1 &&
                $updatedInv['notes'] === 'Updated invoice notes'
            );

            // 5. Delete invoice via POST /sales/invoices/{id}/delete rolls back customer ledger balance
            $delReq = new Request([], [], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$createdInvId}/delete",
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $delResp = $controller->invoiceDelete($delReq, $createdInvId);
            $delJson = json_decode((string)$delResp->getBody(), true);
            $deletedInv = Database::selectOne("SELECT status, deleted_at FROM invoices WHERE id = ?", [$createdInvId]);

            $assert(
                'INVED-05',
                'POST /sales/invoices/{id}/delete soft-deletes invoice and sets status to CANCELLED',
                $delResp->getStatusCode() === 200 &&
                ($delJson['success'] ?? false) === true &&
                $deletedInv['status'] === 'CANCELLED' &&
                $deletedInv['deleted_at'] !== null
            );

            // 6. Invoices table view renders Edit and Delete action buttons
            $listReq = new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/sales/invoices',
            ]);
            $listResp = $controller->invoicesIndex($listReq);
            $listHtml = (string)$listResp->getBody();

            $assert(
                'INVED-06',
                'Invoices list view renders Edit and Delete actions in the table and modal footer',
                $listResp->getStatusCode() === 200 &&
                str_contains($listHtml, '/edit') &&
                str_contains($listHtml, 'deleteInvoice') &&
                str_contains($listHtml, 'inv-modal-edit-btn') &&
                !str_contains($listHtml, "invoice-row-{$createdInvId}")
            );
        } finally {
            // Clean up test rows
            if ($createdInvId > 0) {
                Database::execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$createdInvId]);
                Database::execute("DELETE FROM invoices WHERE id = ?", [$createdInvId]);
            }
        }
    }
}
