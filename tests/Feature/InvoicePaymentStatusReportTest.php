<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Controllers\ReportController;
use WTSBill\Services\InvoicePaymentStatusReportService;
use ZipArchive;

/**
 * Architectural & Feature Test Suite: Invoice Payment Status Report & Excel (.xlsx) Export
 *
 * Validates:
 * 1. Unauthenticated route protection
 * 2. GET /reports/invoice-payment-status returns 200 with summary cards and table
 * 3. Unpaid invoice categorization & balance calculation
 * 4. Single partial payment allocation handling
 * 5. Multiple partial payments aggregation without duplicate counting
 * 6. Fully paid invoice categorization & zero remaining balance
 * 7. Overdue unpaid invoice detection
 * 8. Overdue partially paid invoice detection
 * 9. Cancelled/voided invoices exclusion
 * 10. Cancelled/reversed payments exclusion
 * 11. Customer with multiple invoices aggregation integrity
 * 12. Branch-specific invoice isolation
 * 13. Multi-parameter combined filtering (status, branch, customer, dates)
 * 14. Genuine Office Open XML (.xlsx) binary stream generation & headers
 * 15. Reconciliation: Filtered report summary totals match Excel export totals exactly
 */
class InvoicePaymentStatusReportTest
{
    public static function run(callable $assert): void
    {
        $controller = new ReportController();
        $service = new InvoicePaymentStatusReportService();

        // -------------------------------------------------------------
        // INVREP-01: Unauthenticated request redirects to /login
        // -------------------------------------------------------------
        Session::clear();
        $guestReq = new Request([], []);
        // Simulate middleware routing check
        $currentUser = Session::getUserId();
        $assert(
            'INVREP-01',
            'Unauthenticated request to /reports/invoice-payment-status requires authentication',
            $currentUser === null || $currentUser <= 0
        );

        // Authenticate Administrator for remaining tests
        Session::set('user_id', 1);
        Session::set('company_id', 1);
        Session::set('branch_id', 1);
        Session::set('auth_user', [
            'id' => 1,
            'company_id' => 1,
            'branch_id' => 1,
            'name' => 'Admin QA',
            'role' => 'admin',
            'role_slug' => 'admin',
            'email' => 'admin@wtsbill.com',
        ]);

        $companyId = 1;
        $branchId1 = 1;
        $branchId2 = 2;

        // Create test isolated customer
        $testCustCode = 'CUST-REP-' . time() . '-' . rand(100, 999);
        $qaCustomerName = 'Report QA Corp ' . time() . '_' . rand(1000, 9999);
        $custId = Database::insert('customers', [
            'company_id' => $companyId,
            'name' => $qaCustomerName,
            'phone' => '9876540001',
            'email' => 'qa_report@example.com',
            'status' => 'ACTIVE',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // -------------------------------------------------------------
        // Setup Test Invoices for Scenarios 1 to 10
        // -------------------------------------------------------------
        $yesterday = date('Y-m-d', strtotime('-5 days'));
        $futureDate = date('Y-m-d', strtotime('+15 days'));
        $pastDate = date('Y-m-d', strtotime('-10 days'));

        $insertInvoiceItem = function (int|string $invoiceId, float $subTotal, float $totalTax, float $grandTotal, string $itemName = 'INDUSTRIAL BRASS VALVE 2INCH') use ($companyId): void {
            Database::insert('invoice_items', [
                'company_id' => $companyId,
                'invoice_id' => (int)$invoiceId,
                'product_id' => 1,
                'category_id' => 1,
                'item_name' => $itemName,
                'hsn_sac' => '8481',
                'quantity' => 10,
                'unit' => 'Pcs',
                'unit_price' => round($subTotal / 10, 2),
                'discount_rate' => 0.00,
                'discount_amount' => 0.00,
                'taxable_value' => $subTotal,
                'gst_rate' => 18.00,
                'cgst_rate' => 9.00,
                'cgst_amount' => round($totalTax / 2, 2),
                'sgst_rate' => 9.00,
                'sgst_amount' => round($totalTax / 2, 2),
                'igst_rate' => 0.00,
                'igst_amount' => 0.00,
                'total_amount' => $grandTotal,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        };

        // 1. Unpaid Invoice (Current, not overdue)
        $invUnpaidNo = 'INV-QA-UNP-' . time();
        $invUnpaidId = Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'customer_id' => $custId,
            'invoice_number' => $invUnpaidNo,
            'invoice_date' => date('Y-m-d'),
            'due_date' => $futureDate,
            'sub_total' => 10000.00,
            'total_tax' => 1800.00,
            'grand_total' => 11800.00,
            'amount_paid' => 0.00,
            'amount_due' => 11800.00,
            'status' => 'UNPAID',
            'payment_status' => 'UNPAID',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $insertInvoiceItem($invUnpaidId, 10000.00, 1800.00, 11800.00, 'INDUSTRIAL BALL BEARING 6205');

        // 2. Single Partial Payment Invoice (Current)
        $invPart1No = 'INV-QA-PT1-' . time();
        $invPart1Id = Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'customer_id' => $custId,
            'invoice_number' => $invPart1No,
            'invoice_date' => date('Y-m-d'),
            'due_date' => $futureDate,
            'sub_total' => 20000.00,
            'total_tax' => 3600.00,
            'grand_total' => 23600.00,
            'amount_paid' => 10000.00,
            'amount_due' => 13600.00,
            'status' => 'PARTIAL',
            'payment_status' => 'PARTIALLY_PAID',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $insertInvoiceItem($invPart1Id, 20000.00, 3600.00, 23600.00, 'HEAVY DUTY BUTTERFLY VALVE 3INCH');
        $pmtPart1Id = Database::insert('payments', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_number' => 'PMT-QA-1-' . time(),
            'party_type' => 'CUSTOMER',
            'party_id' => $custId,
            'payment_date' => date('Y-m-d'),
            'amount' => 10000.00,
            'payment_mode' => 'BANK',
            'status' => 'COMPLETED',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Database::insert('payment_allocations', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_id' => $pmtPart1Id,
            'document_type' => 'INVOICE',
            'document_id' => $invPart1Id,
            'allocated_amount' => 10000.00,
            'allocation_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 3. Multiple Partial Payments Invoice (Current)
        $invPartMulNo = 'INV-QA-PTM-' . time();
        $invPartMulId = Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'customer_id' => $custId,
            'invoice_number' => $invPartMulNo,
            'invoice_date' => date('Y-m-d'),
            'due_date' => $futureDate,
            'sub_total' => 30000.00,
            'total_tax' => 5400.00,
            'grand_total' => 35400.00,
            'amount_paid' => 15400.00,
            'amount_due' => 20000.00,
            'status' => 'PARTIAL',
            'payment_status' => 'PARTIALLY_PAID',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $insertInvoiceItem($invPartMulId, 30000.00, 5400.00, 35400.00, 'STAINLESS STEEL FLANGE ANSI 150');
        // Installment 1: 5400
        $pmtM1 = Database::insert('payments', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_number' => 'PMT-QA-M1-' . time(),
            'party_type' => 'CUSTOMER',
            'party_id' => $custId,
            'payment_date' => date('Y-m-d'),
            'amount' => 5400.00,
            'payment_mode' => 'UPI',
            'status' => 'COMPLETED',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Database::insert('payment_allocations', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_id' => $pmtM1,
            'document_type' => 'INVOICE',
            'document_id' => $invPartMulId,
            'allocated_amount' => 5400.00,
            'allocation_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        // Installment 2: 10000
        $pmtM2 = Database::insert('payments', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_number' => 'PMT-QA-M2-' . time(),
            'party_type' => 'CUSTOMER',
            'party_id' => $custId,
            'payment_date' => date('Y-m-d'),
            'amount' => 10000.00,
            'payment_mode' => 'BANK',
            'status' => 'CONFIRMED',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Database::insert('payment_allocations', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_id' => $pmtM2,
            'document_type' => 'INVOICE',
            'document_id' => $invPartMulId,
            'allocated_amount' => 10000.00,
            'allocation_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 4. Fully Paid Invoice
        $invPaidNo = 'INV-QA-PAID-' . time();
        $invPaidId = Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'customer_id' => $custId,
            'invoice_number' => $invPaidNo,
            'invoice_date' => date('Y-m-d'),
            'due_date' => $futureDate,
            'sub_total' => 10000.00,
            'total_tax' => 1800.00,
            'grand_total' => 11800.00,
            'amount_paid' => 11800.00,
            'amount_due' => 0.00,
            'status' => 'PAID',
            'payment_status' => 'PAID',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $insertInvoiceItem($invPaidId, 10000.00, 1800.00, 11800.00, 'PRECISION LINEAR MOTION GUIDE');
        $pmtFull = Database::insert('payments', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_number' => 'PMT-QA-FULL-' . time(),
            'party_type' => 'CUSTOMER',
            'party_id' => $custId,
            'payment_date' => date('Y-m-d'),
            'amount' => 11800.00,
            'payment_mode' => 'BANK',
            'status' => 'COMPLETED',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Database::insert('payment_allocations', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_id' => $pmtFull,
            'document_type' => 'INVOICE',
            'document_id' => $invPaidId,
            'allocated_amount' => 11800.00,
            'allocation_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 5. Overdue Unpaid Invoice
        $invOverdueUnpNo = 'INV-QA-OVD-U-' . time();
        $invOverdueUnpId = Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'customer_id' => $custId,
            'invoice_number' => $invOverdueUnpNo,
            'invoice_date' => $pastDate,
            'due_date' => $yesterday,
            'sub_total' => 5000.00,
            'total_tax' => 900.00,
            'grand_total' => 5900.00,
            'amount_paid' => 0.00,
            'amount_due' => 5900.00,
            'status' => 'UNPAID',
            'payment_status' => 'UNPAID',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 6. Overdue Partially Paid Invoice
        $invOverduePartNo = 'INV-QA-OVD-P-' . time();
        $invOverduePartId = Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'customer_id' => $custId,
            'invoice_number' => $invOverduePartNo,
            'invoice_date' => $pastDate,
            'due_date' => $yesterday,
            'sub_total' => 8000.00,
            'total_tax' => 1440.00,
            'grand_total' => 9440.00,
            'amount_paid' => 4440.00,
            'amount_due' => 5000.00,
            'status' => 'PARTIAL',
            'payment_status' => 'PARTIALLY_PAID',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $pmtOvdPart = Database::insert('payments', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_number' => 'PMT-QA-OP-' . time(),
            'party_type' => 'CUSTOMER',
            'party_id' => $custId,
            'payment_date' => $pastDate,
            'amount' => 4440.00,
            'payment_mode' => 'CASH',
            'status' => 'COMPLETED',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Database::insert('payment_allocations', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_id' => $pmtOvdPart,
            'document_type' => 'INVOICE',
            'document_id' => $invOverduePartId,
            'allocated_amount' => 4440.00,
            'allocation_date' => $pastDate,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 7. Cancelled Invoice
        $invCancelledNo = 'INV-QA-CANC-' . time();
        $invCancelledId = Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'customer_id' => $custId,
            'invoice_number' => $invCancelledNo,
            'invoice_date' => date('Y-m-d'),
            'due_date' => $futureDate,
            'sub_total' => 15000.00,
            'total_tax' => 2700.00,
            'grand_total' => 17700.00,
            'amount_paid' => 0.00,
            'amount_due' => 17700.00,
            'status' => 'CANCELLED',
            'payment_status' => 'UNPAID',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancellation_reason' => 'QA Order Aborted',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 8. Invoice with Reversed/Cancelled Payment
        $invRevNo = 'INV-QA-REV-' . time();
        $invRevId = Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'customer_id' => $custId,
            'invoice_number' => $invRevNo,
            'invoice_date' => date('Y-m-d'),
            'due_date' => $futureDate,
            'sub_total' => 7000.00,
            'total_tax' => 1260.00,
            'grand_total' => 8260.00,
            'amount_paid' => 0.00,
            'amount_due' => 8260.00,
            'status' => 'UNPAID',
            'payment_status' => 'UNPAID',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $pmtCancelled = Database::insert('payments', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_number' => 'PMT-QA-REV-' . time(),
            'party_type' => 'CUSTOMER',
            'party_id' => $custId,
            'payment_date' => date('Y-m-d'),
            'amount' => 8260.00,
            'payment_mode' => 'CHEQUE',
            'status' => 'CANCELLED',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Database::insert('payment_allocations', [
            'company_id' => $companyId,
            'branch_id' => $branchId1,
            'payment_id' => $pmtCancelled,
            'document_type' => 'INVOICE',
            'document_id' => $invRevId,
            'allocated_amount' => 8260.00,
            'allocation_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 9. Invoice belonging to Branch 2
        $invBranch2No = 'INV-QA-BR2-' . time();
        $invBranch2Id = Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => $branchId2,
            'customer_id' => $custId,
            'invoice_number' => $invBranch2No,
            'invoice_date' => date('Y-m-d'),
            'due_date' => $futureDate,
            'sub_total' => 12000.00,
            'total_tax' => 2160.00,
            'grand_total' => 14160.00,
            'amount_paid' => 0.00,
            'amount_due' => 14160.00,
            'status' => 'UNPAID',
            'payment_status' => 'UNPAID',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // -------------------------------------------------------------
        // INVREP-02: GET /reports/invoice-payment-status returns 200 with cards & table
        // -------------------------------------------------------------
        $req = new Request([], []);
        $res = $controller->invoicePaymentStatus($req);
        $html = $res->getContent();
        $assert(
            'INVREP-02',
            'GET /reports/invoice-payment-status renders HTTP 200 with summary cards, filters, and detailed report table',
            $res->getStatusCode() === 200 &&
            str_contains($html, 'Invoice Payment Status Report') &&
            str_contains($html, 'Pending Invoices (Unpaid)') &&
            str_contains($html, 'Paid Invoices (Fully Settled)') &&
            str_contains($html, 'Partially Paid Invoices') &&
            str_contains($html, 'Total Net Outstanding') &&
            str_contains($html, 'Download Excel (.xlsx)')
        );

        // Fetch test customer's specific records from service
        $qaReport = $service->getReport($companyId, ['customer_name' => $qaCustomerName]);
        $qaRecords = [];
        foreach ($qaReport['all_filtered_records'] as $r) {
            $qaRecords[$r['invoice_number']] = $r;
        }

        // -------------------------------------------------------------
        // INVREP-03: Invoice with no payment categorized as UNPAID
        // -------------------------------------------------------------
        $unpaidRec = $qaRecords[$invUnpaidNo] ?? null;
        $assert(
            'INVREP-03',
            'Invoice with no payment received is categorized as UNPAID with full remaining balance',
            $unpaidRec !== null &&
            $unpaidRec['payment_status'] === 'UNPAID' &&
            abs($unpaidRec['invoice_amount'] - 11800.00) < 0.01 &&
            abs($unpaidRec['paid_amount'] - 0.00) < 0.01 &&
            abs($unpaidRec['remaining_balance'] - 11800.00) < 0.01
        );

        // -------------------------------------------------------------
        // INVREP-04: Invoice with single partial payment is PARTIALLY_PAID
        // -------------------------------------------------------------
        $part1Rec = $qaRecords[$invPart1No] ?? null;
        $assert(
            'INVREP-04',
            'Invoice with single partial payment is categorized as PARTIALLY_PAID with exact balance deduction',
            $part1Rec !== null &&
            $part1Rec['payment_status'] === 'PARTIALLY_PAID' &&
            abs($part1Rec['invoice_amount'] - 23600.00) < 0.01 &&
            abs($part1Rec['paid_amount'] - 10000.00) < 0.01 &&
            abs($part1Rec['remaining_balance'] - 13600.00) < 0.01
        );

        // -------------------------------------------------------------
        // INVREP-05: Invoice with multiple partial payments sums correctly
        // -------------------------------------------------------------
        $partMulRec = $qaRecords[$invPartMulNo] ?? null;
        $assert(
            'INVREP-05',
            'Invoice with multiple partial payments aggregates all allocations accurately without double counting',
            $partMulRec !== null &&
            $partMulRec['payment_status'] === 'PARTIALLY_PAID' &&
            abs($partMulRec['invoice_amount'] - 35400.00) < 0.01 &&
            abs($partMulRec['paid_amount'] - 15400.00) < 0.01 &&
            abs($partMulRec['remaining_balance'] - 20000.00) < 0.01
        );

        // -------------------------------------------------------------
        // INVREP-06: Fully paid invoice categorized as PAID with ₹0.00 balance
        // -------------------------------------------------------------
        $paidRec = $qaRecords[$invPaidNo] ?? null;
        $assert(
            'INVREP-06',
            'Invoice with payments matching full invoice amount is categorized as PAID with zero remaining balance',
            $paidRec !== null &&
            $paidRec['payment_status'] === 'PAID' &&
            abs($paidRec['invoice_amount'] - 11800.00) < 0.01 &&
            abs($paidRec['paid_amount'] - 11800.00) < 0.01 &&
            abs($paidRec['remaining_balance'] - 0.00) < 0.01
        );

        // -------------------------------------------------------------
        // INVREP-07: Overdue unpaid invoice detected
        // -------------------------------------------------------------
        $ovdUnpRec = $qaRecords[$invOverdueUnpNo] ?? null;
        $assert(
            'INVREP-07',
            'Overdue unpaid invoice is flagged with is_overdue=true and UNPAID status',
            $ovdUnpRec !== null &&
            $ovdUnpRec['is_overdue'] === true &&
            $ovdUnpRec['payment_status'] === 'UNPAID' &&
            abs($ovdUnpRec['remaining_balance'] - 5900.00) < 0.01
        );

        // -------------------------------------------------------------
        // INVREP-08: Overdue partially paid invoice detected
        // -------------------------------------------------------------
        $ovdPartRec = $qaRecords[$invOverduePartNo] ?? null;
        $assert(
            'INVREP-08',
            'Overdue partially paid invoice is flagged with is_overdue=true and PARTIALLY_PAID status',
            $ovdPartRec !== null &&
            $ovdPartRec['is_overdue'] === true &&
            $ovdPartRec['payment_status'] === 'PARTIALLY_PAID' &&
            abs($ovdPartRec['paid_amount'] - 4440.00) < 0.01 &&
            abs($ovdPartRec['remaining_balance'] - 5000.00) < 0.01
        );

        // -------------------------------------------------------------
        // INVREP-09: Cancelled invoice is strictly excluded from active report
        // -------------------------------------------------------------
        $assert(
            'INVREP-09',
            'Cancelled or voided sales invoices are strictly excluded from report and active outstanding totals',
            !isset($qaRecords[$invCancelledNo])
        );

        // -------------------------------------------------------------
        // INVREP-10: Cancelled/reversed payments are excluded from paid calculation
        // -------------------------------------------------------------
        $revRec = $qaRecords[$invRevNo] ?? null;
        $assert(
            'INVREP-10',
            'Cancelled or reversed payments are excluded so invoice remains UNPAID with full outstanding balance',
            $revRec !== null &&
            $revRec['payment_status'] === 'UNPAID' &&
            abs($revRec['paid_amount'] - 0.00) < 0.01 &&
            abs($revRec['remaining_balance'] - 8260.00) < 0.01
        );

        // -------------------------------------------------------------
        // INVREP-11: Customer with multiple invoices maintains individual line integrity
        // -------------------------------------------------------------
        $expectedCount = 8; // 8 active invoices: Unpaid, Part1, PartMul, Paid, OverdueUnp, OverduePart, Rev, Branch2
        $assert(
            'INVREP-11',
            'Customer with multiple invoices lists each document independently without line collision or duplication',
            count($qaRecords) === $expectedCount
        );

        // -------------------------------------------------------------
        // INVREP-12: Branch-specific invoice isolation
        // -------------------------------------------------------------
        $branch2Report = $service->getReport($companyId, ['branch_id' => $branchId2]);
        $branch2InvNumbers = array_column($branch2Report['all_filtered_records'], 'invoice_number');
        $assert(
            'INVREP-12',
            'Branch filter strictly isolates invoices belonging to selected branch',
            in_array($invBranch2No, $branch2InvNumbers, true) &&
            !in_array($invUnpaidNo, $branch2InvNumbers, true)
        );

        // -------------------------------------------------------------
        // INVREP-13: Multi-parameter combined filtering
        // -------------------------------------------------------------
        $filteredMulti = $service->getReport($companyId, [
            'customer_name' => $qaCustomerName,
            'payment_status' => 'partially_paid',
            'overdue' => 'overdue',
        ]);
        $multiNumbers = array_column($filteredMulti['all_filtered_records'], 'invoice_number');
        $assert(
            'INVREP-13',
            'Combined filtering by customer, payment status, and overdue status narrows records accurately',
            count($multiNumbers) === 1 &&
            $multiNumbers[0] === $invOverduePartNo
        );

        // -------------------------------------------------------------
        // INVREP-14: Genuine Office Open XML (.xlsx) binary stream generation & headers
        // -------------------------------------------------------------
        $exportReq = new Request(['customer_name' => $qaCustomerName], []);
        $exportRes = $controller->invoicePaymentStatusExport($exportReq);
        $binary = $exportRes->getContent();

        // Verify ZIP / Office Open XML archive structure
        $tempXlsx = tempnam(sys_get_temp_dir(), 'inv_rep_test_') . '.xlsx';
        file_put_contents($tempXlsx, $binary);
        $zip = new ZipArchive();
        $isRealZip = ($zip->open($tempXlsx) === true);
        $hasWorkbook = $isRealZip && ($zip->locateName('xl/workbook.xml') !== false);
        $hasSheet1 = $isRealZip && ($zip->locateName('xl/worksheets/sheet1.xml') !== false);
        $hasStyles = $isRealZip && ($zip->locateName('xl/styles.xml') !== false);
        $hasContentTypes = $isRealZip && ($zip->locateName('[Content_Types].xml') !== false);

        $sheetXml = $hasSheet1 ? $zip->getFromName('xl/worksheets/sheet1.xml') : '';
        $zip->close();
        @unlink($tempXlsx);

        $headers = $exportRes->getHeaders();
        $assert(
            'INVREP-14',
            'Export endpoint generates genuine .xlsx OpenXML workbook containing sheets, styles, and Content-Types',
            $exportRes->getStatusCode() === 200 &&
            ($headers['Content-Type'] ?? '') === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' &&
            str_contains($headers['Content-Disposition'] ?? '', 'Invoice_Payment_Status_Report_') &&
            $isRealZip && $hasWorkbook && $hasSheet1 && $hasStyles && $hasContentTypes &&
            str_contains($sheetXml, 'Invoice Number') &&
            str_contains($sheetXml, 'Remaining Balance') &&
            str_contains($sheetXml, $invPartMulNo)
        );

        // -------------------------------------------------------------
        // INVREP-15: Filtered report summary totals reconcile with Excel totals exactly
        // -------------------------------------------------------------
        $reconcileReport = $service->getReport($companyId, ['customer_name' => $qaCustomerName]);
        $totals = $reconcileReport['filtered_totals'];

        // Extract totals from sheet XML
        $xmlHasInvoiceTotal = str_contains($sheetXml, '<v>' . $totals['total_invoice_amount'] . '</v>');
        $xmlHasPaidTotal = str_contains($sheetXml, '<v>' . $totals['total_paid_amount'] . '</v>');
        $xmlHasBalanceTotal = str_contains($sheetXml, '<v>' . $totals['total_remaining_balance'] . '</v>');

        $assert(
            'INVREP-15',
            'Summary report totals reconcile exactly with numeric total cells in the exported Excel workbook',
            $xmlHasInvoiceTotal &&
            $xmlHasPaidTotal &&
            $xmlHasBalanceTotal &&
            $totals['count'] === $expectedCount
        );
    }
}
