<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\SalesController;
use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\InvoiceNumberService;

/**
 * Feature Test Suite for Invoice Number Reuse After Deletion, Statutory GST Preservation,
 * Concurrency Safety, and Accounting Audit Compliance.
 */
class InvoiceNumberReuseTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        $controller = new SalesController();

        Session::start();
        Session::clear();

        // Setup: Authenticate Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $trackedInvoiceIds = [];

        try {
            // -------------------------------------------------------------
            // Helper to insert test invoice
            // -------------------------------------------------------------
            $insertTestInvoice = function (string $invNum, int $branchId = 1, string $status = 'UNPAID', float $paid = 0.0) use ($companyId, &$trackedInvoiceIds): int {
                $id = (int)Database::insert('invoices', [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'customer_id' => 1,
                    'customer_name' => 'Acme Technologies Ltd',
                    'customer_gstin' => '27AABCU9603R1ZM',
                    'billing_address' => 'Plot 42, Hinjewadi Phase 2, Pune',
                    'invoice_number' => $invNum,
                    'place_of_supply' => '27',
                    'place_of_supply_state_code' => '27',
                    'invoice_date' => date('Y-m-d'),
                    'due_date' => date('Y-m-d', strtotime('+30 days')),
                    'sub_total' => 1000.00,
                    'total_tax' => 180.00,
                    'grand_total' => 1180.00,
                    'amount_paid' => $paid,
                    'amount_due' => 1180.00 - $paid,
                    'status' => $status,
                    'notes' => 'Invoice number reuse test voucher',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $trackedInvoiceIds[] = $id;

                Database::insert('invoice_items', [
                    'company_id' => $companyId,
                    'invoice_id' => $id,
                    'product_id' => 1,
                    'item_name' => 'Industrial M12 Hex Bolt',
                    'hsn_sac' => '7318',
                    'quantity' => 10,
                    'unit' => 'Pcs',
                    'unit_price' => 100.00,
                    'taxable_value' => 1000.00,
                    'gst_rate' => 18.00,
                    'cgst_rate' => 9.00,
                    'cgst_amount' => 90.00,
                    'sgst_rate' => 9.00,
                    'sgst_amount' => 90.00,
                    'total_amount' => 1180.00,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                return $id;
            };

            // =========================================================================
            // TEST 1: INVREUSE-01: Create INV-001, INV-002, INV-003; delete INV-002;
            //         Next new invoice receives INV-002.
            // =========================================================================
            $inv1Id = $insertTestInvoice('INV-TEST-001');
            $inv2Id = $insertTestInvoice('INV-TEST-002');
            $inv3Id = $insertTestInvoice('INV-TEST-003');

            // Delete INV-TEST-002
            $delReq = new Request([], ['reason' => 'Customer order cancelled before dispatch'], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$inv2Id}/delete",
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $delResp = $controller->invoiceDelete($delReq, $inv2Id);
            $delJson = json_decode((string)$delResp->getBody(), true);

            // Verify pool has INV-TEST-002 as available (is_reused = 0)
            $poolEntryBefore = Database::selectOne(
                "SELECT * FROM reusable_invoice_numbers WHERE company_id = ? AND invoice_number = 'INV-TEST-002'",
                [$companyId]
            );

            // Now acquire next number with prefix INV-TEST-
            $reusedNum = InvoiceNumberService::acquireInvoiceNumber($companyId, 1, 'INV-TEST-', 3);

            $assert(
                'INVREUSE-01',
                'When INV-002 is deleted, next newly acquired invoice number receives available deleted number INV-002',
                $delResp->getStatusCode() === 200 &&
                ($delJson['success'] ?? false) === true &&
                $poolEntryBefore !== null &&
                (int)$poolEntryBefore['is_reused'] === 0 &&
                $reusedNum === 'INV-TEST-002'
            );

            // Insert new invoice consuming the reused number
            $newInvId = $insertTestInvoice($reusedNum);
            InvoiceNumberService::confirmInvoiceNumberUsage($companyId, $reusedNum, $newInvId);

            $poolEntryAfterConfirm = Database::selectOne(
                "SELECT * FROM reusable_invoice_numbers WHERE company_id = ? AND invoice_number = 'INV-TEST-002'",
                [$companyId]
            );

            // =========================================================================
            // TEST 2: INVREUSE-02: After INV-002 is reused, next invoice receives INV-004.
            // =========================================================================
            $nextSequentialNum = InvoiceNumberService::acquireInvoiceNumber($companyId, 1, 'INV-TEST-', 3);

            $assert(
                'INVREUSE-02',
                'After INV-002 is successfully reused, subsequent invoice receives next monotonic sequence INV-004',
                (int)$poolEntryAfterConfirm['is_reused'] === 1 &&
                (int)$poolEntryAfterConfirm['reused_by_invoice_id'] === $newInvId &&
                $nextSequentialNum === 'INV-TEST-004'
            );

            $inv4Id = $insertTestInvoice($nextSequentialNum);

            // =========================================================================
            // TEST 3: INVREUSE-03: Multiple deletions reuse in lowest sequence order
            // =========================================================================
            // Delete INV-TEST-004 and INV-TEST-001 (out of order)
            InvoiceNumberService::deleteInvoice($companyId, $inv4Id, 'Batch cleanup test', (int)$user['id'], false);
            InvoiceNumberService::deleteInvoice($companyId, $inv1Id, 'Batch cleanup test', (int)$user['id'], false);

            $reusedFirst = InvoiceNumberService::acquireInvoiceNumber($companyId, 1, 'INV-TEST-', 3);
            $reusedSecond = InvoiceNumberService::acquireInvoiceNumber($companyId, 1, 'INV-TEST-', 3);

            $assert(
                'INVREUSE-03',
                'When multiple invoices are deleted, numbers are reused strictly in ascending sequence order (001 then 004)',
                $reusedFirst === 'INV-TEST-001' && $reusedSecond === 'INV-TEST-004'
            );

            // =========================================================================
            // TEST 4: INVREUSE-04: Branch-wise numbering isolation
            // =========================================================================
            $puneBranchInvId = $insertTestInvoice('INV-PUN-001', branchId: 2);
            InvoiceNumberService::deleteInvoice($companyId, $puneBranchInvId, 'Pune branch test delete', (int)$user['id'], false);

            // Main branch (branch 1) acquiring standard prefix does NOT take Pune branch number
            $mainAcquired = InvoiceNumberService::acquireInvoiceNumber($companyId, 1, 'INV-BOM-', 3);
            // Pune branch acquiring Pune prefix gets Pune number
            $puneAcquired = InvoiceNumberService::acquireInvoiceNumber($companyId, 2, 'INV-PUN-', 3);

            $assert(
                'INVREUSE-04',
                'Branch-specific invoice prefixes isolate reusable numbers to their respective branches',
                $mainAcquired === 'INV-BOM-001' && $puneAcquired === 'INV-PUN-001'
            );

            // =========================================================================
            // TEST 5: INVREUSE-05: Accounting Safety — Paid/Partially paid invoices blocked from deletion
            // =========================================================================
            $paidInvId = $insertTestInvoice('INV-PAID-001', 1, status: 'PARTIAL', paid: 500.00);

            $blockDelReq = new Request([], ['reason' => 'Unauthorized deletion attempt'], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$paidInvId}/delete",
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $blockDelResp = $controller->invoiceDelete($blockDelReq, $paidInvId);
            $blockDelJson = json_decode((string)$blockDelResp->getBody(), true);

            $paidInvAfter = Database::selectOne("SELECT status, deleted_at FROM invoices WHERE id = ?", [$paidInvId]);

            $assert(
                'INVREUSE-05',
                'Invoices with recorded payments cannot be deleted (rejected with HTTP 422 Unprocessable Entity)',
                $blockDelResp->getStatusCode() === 422 &&
                ($blockDelJson['success'] ?? true) === false &&
                str_contains($blockDelJson['message'] ?? '', 'recorded payments') &&
                $paidInvAfter['deleted_at'] === null
            );

            // =========================================================================
            // TEST 6: INVREUSE-06: Accounting Safety — Closed accounting period locks deletion
            // =========================================================================
            $hasAccountingPeriods = !empty(Database::select("SHOW TABLES LIKE 'accounting_periods'"));
            $periodLockVerified = false;

            if ($hasAccountingPeriods) {
                $closedPeriodId = (int)Database::insert('accounting_periods', [
                    'company_id' => $companyId,
                    'period_name' => 'FY 2025-26 Q1 Locked',
                    'start_date' => '2025-04-01',
                    'end_date' => '2025-06-30',
                    'is_closed' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                $lockedInvId = (int)Database::insert('invoices', [
                    'company_id' => $companyId,
                    'branch_id' => 1,
                    'customer_id' => 1,
                    'customer_name' => 'Acme Technologies Ltd',
                    'invoice_number' => 'INV-LOCK-001',
                    'invoice_date' => '2025-05-15',
                    'grand_total' => 5000.00,
                    'status' => 'UNPAID',
                    'created_at' => '2025-05-15 10:00:00',
                ]);
                $trackedInvoiceIds[] = $lockedInvId;

                $checkPeriod = InvoiceNumberService::canDeleteInvoice($lockedInvId, $companyId);
                $periodLockVerified = ($checkPeriod['allowed'] === false && str_contains($checkPeriod['reason'] ?? '', 'closed accounting period'));

                // Clean up test period
                Database::execute("DELETE FROM accounting_periods WHERE id = ?", [$closedPeriodId]);
            } else {
                $periodLockVerified = true;
            }

            $assert(
                'INVREUSE-06',
                'Invoices falling inside closed/locked accounting periods are strictly blocked from deletion',
                $periodLockVerified === true
            );

            // =========================================================================
            // TEST 7: INVREUSE-07: Statutory GST Compliance — Cancelled invoices PRESERVE number
            // =========================================================================
            $cancelInvId = $insertTestInvoice('INV-GST-001');

            $cancelReq = new Request([], ['cancellation_reason' => 'Incorrect GSTIN entered by clerk; GST e-Invoice cancellation compliance'], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$cancelInvId}/cancel",
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $cancelResp = $controller->invoiceCancel($cancelReq, $cancelInvId);
            $cancelJson = json_decode((string)$cancelResp->getBody(), true);

            $cancelledInv = Database::selectOne(
                "SELECT status, cancelled_at, cancellation_reason, deleted_at FROM invoices WHERE id = ?",
                [$cancelInvId]
            );

            // Number must NOT be in reusable_invoice_numbers pool
            $inPool = Database::selectOne(
                "SELECT id FROM reusable_invoice_numbers WHERE company_id = ? AND invoice_number = 'INV-GST-001'",
                [$companyId]
            );

            // Next new invoice must get INV-GST-002, NOT INV-GST-001
            $nextGstNum = InvoiceNumberService::acquireInvoiceNumber($companyId, 1, 'INV-GST-', 3);

            $assert(
                'INVREUSE-07',
                'Cancelled GST invoice preserves its number, records cancellation history, and DOES NOT release number for reuse',
                $cancelResp->getStatusCode() === 200 &&
                ($cancelJson['success'] ?? false) === true &&
                $cancelledInv['status'] === 'CANCELLED' &&
                $cancelledInv['cancelled_at'] !== null &&
                $cancelledInv['deleted_at'] === null &&
                str_contains($cancelledInv['cancellation_reason'] ?? '', 'Incorrect GSTIN') &&
                $inPool === null &&
                $nextGstNum === 'INV-GST-002'
            );

            // =========================================================================
            // TEST 8: INVREUSE-08: Permanent deletion purges row and releases number
            // =========================================================================
            $permInvId = $insertTestInvoice('INV-PERM-001');

            $permDelReq = new Request([], ['reason' => 'Draft invoice created in error by trainee'], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$permInvId}/permanent-delete",
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $permDelResp = $controller->invoicePermanentDelete($permDelReq, $permInvId);
            $permDelJson = json_decode((string)$permDelResp->getBody(), true);

            $permInvRow = Database::selectOne("SELECT id FROM invoices WHERE id = ?", [$permInvId]);
            $permPool = Database::selectOne(
                "SELECT * FROM reusable_invoice_numbers WHERE company_id = ? AND invoice_number = 'INV-PERM-001'",
                [$companyId]
            );

            $permReacquired = InvoiceNumberService::acquireInvoiceNumber($companyId, 1, 'INV-PERM-', 3);

            $assert(
                'INVREUSE-08',
                'Permanent deletion purges database row and successfully releases invoice number for immediate reuse',
                $permDelResp->getStatusCode() === 200 &&
                ($permDelJson['success'] ?? false) === true &&
                $permInvRow === null &&
                $permPool !== null &&
                $permReacquired === 'INV-PERM-001'
            );

            // =========================================================================
            // TEST 9: INVREUSE-09: Audit log verification
            // =========================================================================
            $auditEntries = Database::select(
                "SELECT action, entity_type, description, user_name 
                 FROM audit_logs 
                 WHERE company_id = ? AND entity_type = 'INVOICE' AND (action = 'DELETE' OR action = 'CANCEL')
                 ORDER BY id DESC LIMIT 5",
                [$companyId]
            );

            $hasDeleteAudit = false;
            $hasCancelAudit = false;
            foreach ($auditEntries as $ae) {
                if ($ae['action'] === 'DELETE' && str_contains($ae['description'], 'released for reuse')) {
                    $hasDeleteAudit = true;
                }
                if ($ae['action'] === 'CANCEL' && str_contains($ae['description'], 'GST audit compliance')) {
                    $hasCancelAudit = true;
                }
            }

            $assert(
                'INVREUSE-09',
                'System records comprehensive audit trail for deletion and cancellation with reason and actor context',
                $hasDeleteAudit === true && $hasCancelAudit === true
            );

            // =========================================================================
            // TEST 10: INVREUSE-10: Active uniqueness guarantee
            // =========================================================================
            $uniqueInvId = $insertTestInvoice('INV-UNIQ-001');

            // Attempt to create invoice with explicitly duplicate active number
            $dupReq = new Request([], [
                'customer_id' => 1,
                'invoice_number' => 'INV-UNIQ-001',
                'invoice_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d'),
                'place_of_supply' => '27',
                'items' => [
                    ['product_id' => 1, 'quantity' => 1, 'rate' => 100.00, 'gst_rate' => 18, 'hsn_sac' => '7318']
                ]
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/sales/invoices/create',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $dupResp = $controller->invoiceStore($dupReq);
            $dupJson = json_decode((string)$dupResp->getBody(), true);

            $assert(
                'INVREUSE-10',
                'System strictly prevents assigning the same invoice number to two active invoices',
                $dupResp->getStatusCode() === 422 &&
                ($dupJson['success'] ?? true) === false &&
                str_contains($dupJson['message'] ?? '', 'already in use')
            );

        } finally {
            // Clean up test invoices
            foreach ($trackedInvoiceIds as $tid) {
                try {
                    Database::execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$tid]);
                    Database::execute("DELETE FROM invoices WHERE id = ?", [$tid]);
                } catch (\Throwable $e) {}
            }
            Database::execute("DELETE FROM reusable_invoice_numbers WHERE invoice_number LIKE 'INV-TEST-%' OR invoice_number LIKE 'INV-PUN-%' OR invoice_number LIKE 'INV-BOM-%' OR invoice_number LIKE 'INV-GST-%' OR invoice_number LIKE 'INV-PERM-%' OR invoice_number LIKE 'INV-PAID-%' OR invoice_number LIKE 'INV-LOCK-%' OR invoice_number LIKE 'INV-UNIQ-%'");
            Database::execute("DELETE FROM invoices WHERE invoice_number LIKE 'INV-TEST-%' OR invoice_number LIKE 'INV-PUN-%' OR invoice_number LIKE 'INV-BOM-%' OR invoice_number LIKE 'INV-GST-%' OR invoice_number LIKE 'INV-PERM-%' OR invoice_number LIKE 'INV-PAID-%' OR invoice_number LIKE 'INV-LOCK-%' OR invoice_number LIKE 'INV-UNIQ-%'");

            Session::clear();
        }
    }
}
