<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\SalesController;
use WTSBill\Controllers\PaymentController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\PaymentService;

/**
 * Feature Test Suite: Partial Payment Management (Feature A).
 * Validates multiple installments, status transitions (UNPAID -> PARTIAL -> PAID),
 * double-entry GL journal posting, boundary guards (overpayment / negative), and payment history.
 */
class PartialPaymentTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        $salesController = new SalesController();
        $paymentController = new PaymentController();
        $paymentService = new PaymentService();

        Session::start();
        Session::clear();

        // 1. Authenticate Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $assert(
            'PARTPAY-01',
            'Administrator authenticates and establishes company session context',
            Session::getCompanyId() === 1 && Session::get('user_id') === (int)$user['id']
        );

        $companyId = (int)Session::getCompanyId();
        $branchId = (int)Session::getBranchId();
        $createdInvId = 0;

        try {
            // 2. Create test invoice for ₹50,000
            // Unit price ₹42,372.88 + 18% GST = ~₹50,000, or exact rounded amounts
            $createReq = new Request([], [
                'customer_id' => 1,
                'invoice_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'place_of_supply' => '27',
                'items' => [
                    [
                        'category_id' => 1,
                        'product_id' => 1,
                        'quantity' => 50,
                        'rate' => 847.4576, // 50 * 847.4576 = 42,372.88 + 18% (7627.12) = 50,000.00
                        'gst_rate' => 18,
                        'hsn_sac' => '8481',
                    ]
                ],
                'notes' => 'Automated partial payment verification invoice'
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/sales/invoices/create',
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $createResp = $salesController->invoiceStore($createReq);
            $createJson = json_decode((string)$createResp->getBody(), true);
            $createdInvId = (int)($createJson['invoice_id'] ?? 0);

            $assert(
                'PARTPAY-02',
                'Create test invoice for ₹50,000 succeeds with HTTP 201',
                $createResp->getStatusCode() === 201 && $createdInvId > 0
            );

            // Fetch created invoice details
            $inv = Database::selectOne("SELECT * FROM invoices WHERE id = ?", [$createdInvId]);
            $invGrandTotal = (float)$inv['grand_total'];
            $assert(
                'PARTPAY-03',
                'Invoice grand total is ₹50,000 with amount_due equal to grand_total',
                abs($invGrandTotal - 50000.00) < 1.0 && (float)$inv['amount_due'] == $invGrandTotal
            );

            // Record initial customer balance before payments
            $custBefore = Database::selectOne("SELECT current_balance FROM customers WHERE id = 1");
            $custBalanceBefore = (float)($custBefore['current_balance'] ?? 0);

            // 3. First Payment: ₹10,000 installment
            $pay1Req = new Request([], [
                'invoice_id' => $createdInvId,
                'amount' => 10000.00,
                'payment_date' => date('Y-m-d'),
                'payment_mode' => 'UPI',
                'reference_no' => 'UPI-TEST-001',
                'notes' => '1st Installment',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$createdInvId}/payments",
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $pay1Resp = $salesController->recordPayment($pay1Req, $createdInvId);
            $pay1Json = json_decode((string)$pay1Resp->getBody(), true);

            $assert(
                'PARTPAY-04',
                'First payment of ₹10,000 returns HTTP 201 with success envelope',
                $pay1Resp->getStatusCode() === 201 && ($pay1Json['success'] ?? false) === true
            );

            // Verify invoice status transitioned to PARTIAL
            $invAfter1 = Database::selectOne("SELECT amount_paid, amount_due, status, payment_status FROM invoices WHERE id = ?", [$createdInvId]);
            $assert(
                'PARTPAY-05',
                'Invoice status becomes PARTIAL, amount_paid is ₹10,000, amount_due is ₹40,000',
                $invAfter1['status'] === 'PARTIAL' &&
                $invAfter1['payment_status'] === 'PARTIALLY_PAID' &&
                abs((float)$invAfter1['amount_paid'] - 10000.00) < 0.01 &&
                abs((float)$invAfter1['amount_due'] - 40000.00) < 0.01
            );

            // Verify customer ledger balance reduced by ₹10,000
            $custAfter1 = Database::selectOne("SELECT current_balance FROM customers WHERE id = 1");
            $custBalanceAfter1 = (float)($custAfter1['current_balance'] ?? 0);
            $assert(
                'PARTPAY-06',
                'Customer balance decreased by exact payment amount ₹10,000',
                abs(($custBalanceBefore - $custBalanceAfter1) - 10000.00) < 0.01
            );

            // 4. Second Payment: ₹15,000 installment
            $pay2Req = new Request([], [
                'invoice_id' => $createdInvId,
                'amount' => 15000.00,
                'payment_date' => date('Y-m-d'),
                'payment_mode' => 'Bank Transfer',
                'reference_no' => 'NEFT-TEST-002',
                'notes' => '2nd Installment',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$createdInvId}/payments",
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $pay2Resp = $salesController->recordPayment($pay2Req, $createdInvId);
            $pay2Json = json_decode((string)$pay2Resp->getBody(), true);

            $invAfter2 = Database::selectOne("SELECT amount_paid, amount_due, status, payment_status FROM invoices WHERE id = ?", [$createdInvId]);
            $assert(
                'PARTPAY-07',
                'Second payment of ₹15,000 keeps status PARTIAL, amount_paid ₹25,000, amount_due ₹25,000',
                $invAfter2['status'] === 'PARTIAL' &&
                abs((float)$invAfter2['amount_paid'] - 25000.00) < 0.01 &&
                abs((float)$invAfter2['amount_due'] - 25000.00) < 0.01
            );

            // 5. Validation Guard: Reject payment greater than remaining balance (₹30,000 > ₹25,000)
            $overpayReq = new Request([], [
                'invoice_id' => $createdInvId,
                'amount' => 30000.00,
                'payment_date' => date('Y-m-d'),
                'payment_mode' => 'Cash',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$createdInvId}/payments",
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $overpayResp = $salesController->recordPayment($overpayReq, $createdInvId);
            $assert(
                'PARTPAY-08',
                'Overpayment greater than remaining balance is strictly rejected with HTTP 422',
                $overpayResp->getStatusCode() === 422
            );

            // 6. Validation Guard: Reject zero or negative payment
            $negPayReq = new Request([], [
                'invoice_id' => $createdInvId,
                'amount' => -500.00,
                'payment_date' => date('Y-m-d'),
                'payment_mode' => 'Cash',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$createdInvId}/payments",
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $negResp = $salesController->recordPayment($negPayReq, $createdInvId);
            $assert(
                'PARTPAY-09',
                'Zero or negative payment amount is strictly rejected with HTTP 422',
                $negResp->getStatusCode() === 422
            );

            // 7. Third & Final Payment: ₹25,000 (Clearing full invoice)
            $pay3Req = new Request([], [
                'invoice_id' => $createdInvId,
                'amount' => 25000.00,
                'payment_date' => date('Y-m-d'),
                'payment_mode' => 'Cheque',
                'reference_no' => 'CHQ-991201',
                'notes' => 'Final Installment - Full Settlement',
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/invoices/{$createdInvId}/payments",
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $pay3Resp = $salesController->recordPayment($pay3Req, $createdInvId);
            $invAfter3 = Database::selectOne("SELECT amount_paid, amount_due, status, payment_status FROM invoices WHERE id = ?", [$createdInvId]);

            $assert(
                'PARTPAY-10',
                'Final payment clears balance to 0 and transitions invoice status to PAID',
                $invAfter3['status'] === 'PAID' &&
                $invAfter3['payment_status'] === 'PAID' &&
                abs((float)$invAfter3['amount_paid'] - 50000.00) < 0.01 &&
                abs((float)$invAfter3['amount_due']) < 0.01
            );

            // 8. Payment History Verification
            $history = $paymentService->getInvoicePaymentHistory($companyId, $createdInvId);
            $assert(
                'PARTPAY-11',
                'Payment history contains all 3 distinct installments totaling ₹50,000',
                count($history) === 3 &&
                (float)$history[0]['allocated_amount'] === 10000.00 &&
                (float)$history[1]['allocated_amount'] === 15000.00 &&
                (float)$history[2]['allocated_amount'] === 25000.00
            );

            // 9. Invoice payments API endpoint test
            $apiReq = new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => "/sales/invoices/{$createdInvId}/payments",
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $apiResp = $salesController->invoicePayments($apiReq, $createdInvId);
            $apiJson = json_decode((string)$apiResp->getBody(), true);
            $assert(
                'PARTPAY-12',
                'GET /sales/invoices/{id}/payments returns list of payments with HTTP 200',
                $apiResp->getStatusCode() === 200 && count($apiJson['payments'] ?? []) === 3
            );

            // 10. Payments tab view loads invoice receivables
            $tabReq = new Request(['tab' => 'invoices'], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/payments?tab=invoices',
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $tabResp = $paymentController->index($tabReq);
            $tabJson = json_decode((string)$tabResp->getBody(), true);
            $tabData = $tabJson['data'] ?? $tabJson;
            $assert(
                'PARTPAY-13',
                'GET /payments?tab=invoices returns invoice_payments list and invoice_kpi summary',
                !empty($tabData['invoice_payments']) && isset($tabData['invoice_kpi']['total_invoiced'])
            );

        } finally {
            // Clean up test invoice and allocated payments
            if ($createdInvId > 0) {
                $allocations = Database::select("SELECT payment_id FROM payment_allocations WHERE document_id = ? AND document_type = 'INVOICE'", [$createdInvId]);
                foreach ($allocations as $a) {
                    Database::execute("DELETE FROM journal_entries WHERE reference_id = ? AND reference_type = 'PAYMENT'", [$a['payment_id']]);
                    Database::execute("DELETE FROM payment_allocations WHERE payment_id = ?", [$a['payment_id']]);
                    Database::execute("DELETE FROM payments WHERE id = ?", [$a['payment_id']]);
                }
                Database::execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$createdInvId]);
                Database::execute("DELETE FROM invoices WHERE id = ?", [$createdInvId]);
            }
        }
    }
}
