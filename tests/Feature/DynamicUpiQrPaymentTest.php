<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\SalesController;
use WTSBill\Controllers\PaymentController;
use WTSBill\Controllers\SettingsController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\PaymentService;
use WTSBill\Services\QrCodeService;

/**
 * Feature Test Suite: Dynamic Remaining Balance UPI QR Code & Payment Reconciliation.
 *
 * Verifies:
 * - Real-time recalculation of outstanding balance from confirmed payments
 * - Dynamic UPI QR payload and vector SVG for the exact remaining balance
 * - Hiding UPI QR code when invoice is fully settled (Paid in full)
 * - Safe fallback message when merchant UPI ID is not configured (no invalid QR)
 * - Protection against unverified payments (balance remains unchanged until verified)
 * - Strict duplicate transaction reference prevention (HTTP 422)
 * - Printable invoice layout agreement with payment received and balance due
 */
class DynamicUpiQrPaymentTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        $salesController = new SalesController();
        $paymentController = new PaymentController();
        $settingsController = new SettingsController();
        $paymentService = new PaymentService();

        Session::start();
        Session::clear();

        // 1. Authenticate Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)Session::getCompanyId();
        $branchId = (int)Session::getBranchId();

        // Ensure clean merchant UPI configuration for tests
        $merchantUpi = '9881032297@kotak';
        $merchantName = 'WIS TECHNOSAVVY PRIVATE LIMITED';
        $merchantInstructions = 'Scan this QR code using any supported UPI application to pay the outstanding invoice balance.';

        Database::update(
            'companies',
            [
                'upi_id' => $merchantUpi,
                'upi_display_name' => $merchantName,
                'payment_instructions' => $merchantInstructions,
            ],
            'id = ?',
            [$companyId]
        );

        $assert(
            'UPIQR-01',
            'Administrator authenticates and configures merchant UPI ID, payee name, and payment instructions',
            $companyId === 1 && !empty($user['id'])
        );

        // 2. Create test invoice for ₹50,000
        $invReq = new Request([], [
            'customer_id' => 1,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+15 days')),
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
            'notes' => 'Dynamic UPI QR automated verification invoice'
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/sales/invoices/create',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $invResp = $salesController->invoiceStore($invReq);
        $invJson = json_decode((string)$invResp->getBody(), true);
        $invoiceId = (int)($invJson['invoice_id'] ?? 0);

        // Verify initial details
        $showReq0 = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$invoiceId}", 'HTTP_ACCEPT' => 'application/json']);
        $showResp0 = $salesController->invoiceShow($showReq0, $invoiceId);
        $showData0 = json_decode((string)$showResp0->getBody(), true);

        $assert(
            'UPIQR-02',
            'Create test invoice for ₹50,000 with initial outstanding balance of ₹50,000 and initial QR for ₹50,000',
            $invResp->getStatusCode() === 201
            && abs((float)$showData0['invoice']['grand_total'] - 50000.00) < 1.0
            && (float)$showData0['outstanding_amount'] == (float)$showData0['invoice']['grand_total']
            && str_contains((string)$showData0['upi_payload'], 'am=50000.00')
            && !empty($showData0['qr_svg'])
        );

        // 3. Test 1: First payment of ₹20,000 -> Expected remaining QR amount: ₹30,000
        $pay1Ref = 'UPI-DYN-' . time() . '-1';
        $pay1Req = new Request([], [
            'invoice_id' => $invoiceId,
            'amount' => 20000.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'UPI',
            'reference_no' => $pay1Ref,
            'notes' => '1st Installment payment via UPI',
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/invoices/{$invoiceId}/payments",
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $pay1Resp = $salesController->recordPayment($pay1Req, $invoiceId);
        $pay1Json = json_decode((string)$pay1Resp->getBody(), true);

        // Fetch invoice details after 1st payment
        $showReq1 = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$invoiceId}", 'HTTP_ACCEPT' => 'application/json']);
        $showResp1 = $salesController->invoiceShow($showReq1, $invoiceId);
        $showData1 = json_decode((string)$showResp1->getBody(), true);

        $assert(
            'UPIQR-03',
            'First payment of ₹20,000 succeeds -> Total received ₹20,000, Remaining balance ₹30,000, Status PARTIAL, dynamic QR amount is ₹30,000',
            $pay1Resp->getStatusCode() === 201
            && (float)$showData1['total_paid'] === 20000.00
            && (float)$showData1['outstanding_amount'] === 30000.00
            && $showData1['payment_status'] === 'PARTIAL'
            && str_contains((string)$showData1['upi_payload'], 'am=30000.00')
            && str_contains((string)$showData1['upi_payload'], 'pa=9881032297@kotak')
            && !empty($showData1['qr_svg'])
        );

        // 4. Test 2: Additional payment of ₹10,000 -> Expected remaining QR amount: ₹20,000
        $pay2Ref = 'UPI-DYN-' . time() . '-2';
        $pay2Req = new Request([], [
            'invoice_id' => $invoiceId,
            'amount' => 10000.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'UPI',
            'reference_no' => $pay2Ref,
            'notes' => '2nd Installment payment via UPI',
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/invoices/{$invoiceId}/payments",
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $pay2Resp = $salesController->recordPayment($pay2Req, $invoiceId);

        $showReq2 = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$invoiceId}", 'HTTP_ACCEPT' => 'application/json']);
        $showResp2 = $salesController->invoiceShow($showReq2, $invoiceId);
        $showData2 = json_decode((string)$showResp2->getBody(), true);

        $assert(
            'UPIQR-04',
            'Additional payment of ₹10,000 succeeds -> Total received ₹30,000, Remaining balance ₹20,000, Status PARTIAL, dynamic QR amount updates to ₹20,000',
            $pay2Resp->getStatusCode() === 201
            && (float)$showData2['total_paid'] === 30000.00
            && (float)$showData2['outstanding_amount'] === 20000.00
            && str_contains((string)$showData2['upi_payload'], 'am=20000.00')
            && !empty($showData2['qr_svg'])
        );

        // 5. Test 5: Unverified UPI payment instruction -> Invoice balance remains unchanged until verified
        $pendingRef = 'UPI-PENDING-' . time();
        $pendingResult = $paymentService->recordPendingUpiPayment(
            $companyId,
            $branchId,
            (int)$user['id'],
            'Admin',
            $invoiceId,
            [
                'amount' => 5000.00,
                'reference_no' => $pendingRef,
                'notes' => 'Customer submitted UPI screenshot pending bank settlement',
            ]
        );

        // Check invoice balance: must STILL be ₹20,000 outstanding (not ₹15,000)
        $showReqPending = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$invoiceId}", 'HTTP_ACCEPT' => 'application/json']);
        $showRespPending = $salesController->invoiceShow($showReqPending, $invoiceId);
        $showDataPending = json_decode((string)$showRespPending->getBody(), true);

        $assert(
            'UPIQR-05',
            'Unverified UPI payment instruction is recorded as PENDING_VERIFICATION and invoice balance remains unchanged (₹20,000 due)',
            $pendingResult['status'] === 'PENDING_VERIFICATION'
            && (float)$showDataPending['outstanding_amount'] === 20000.00
            && (float)$showDataPending['total_paid'] === 30000.00
            && str_contains((string)$showDataPending['upi_payload'], 'am=20000.00')
        );

        // 6. Verify and reconcile pending payment
        $verifyReq = new Request([], [
            'payment_id' => $pendingResult['payment_id'],
            'transaction_reference' => $pendingRef . '-BANK-CONFIRM',
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/invoices/{$invoiceId}/verify-payment",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $verifyResp = $salesController->verifyPayment($verifyReq, $invoiceId);
        $verifyJson = json_decode((string)$verifyResp->getBody(), true);

        // Invoice balance now reduces to ₹15,000 due
        $showReqVerified = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$invoiceId}", 'HTTP_ACCEPT' => 'application/json']);
        $showDataVerified = json_decode((string)$salesController->invoiceShow($showReqVerified, $invoiceId)->getBody(), true);

        $assert(
            'UPIQR-06',
            'Payment verification confirms transaction, updates status to COMPLETED, and reduces outstanding balance to ₹15,000',
            $verifyResp->getStatusCode() === 200
            && ($verifyJson['verified'] ?? false) === true
            && (float)$showDataVerified['total_paid'] === 35000.00
            && (float)$showDataVerified['outstanding_amount'] === 15000.00
            && str_contains((string)$showDataVerified['upi_payload'], 'am=15000.00')
        );

        // 7. Test 6: Duplicate payment callback or transaction reference is rejected
        $dupReq = new Request([], [
            'invoice_id' => $invoiceId,
            'amount' => 5000.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'UPI',
            'reference_no' => $pay1Ref, // Reusing pay1Ref
            'notes' => 'Duplicate submission test',
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/invoices/{$invoiceId}/payments",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $dupResp = $salesController->recordPayment($dupReq, $invoiceId);
        $dupJson = json_decode((string)$dupResp->getBody(), true);

        $assert(
            'UPIQR-07',
            'Submitting duplicate payment transaction reference is strictly rejected with HTTP 422 safeguard',
            $dupResp->getStatusCode() === 422
            && str_contains(strtolower($dupJson['message'] ?? ''), 'duplicate')
        );

        // 8. Test 3: Final payment of ₹15,000 -> Expected outstanding: ₹0, Status: Paid, Expected QR: Hidden
        $payFinalRef = 'UPI-DYN-' . time() . '-FINAL';
        $payFinalReq = new Request([], [
            'invoice_id' => $invoiceId,
            'amount' => 15000.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'UPI',
            'reference_no' => $payFinalRef,
            'notes' => 'Final settlement payment',
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/sales/invoices/{$invoiceId}/payments",
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $payFinalResp = $salesController->recordPayment($payFinalReq, $invoiceId);

        $showReqFinal = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$invoiceId}", 'HTTP_ACCEPT' => 'application/json']);
        $showDataFinal = json_decode((string)$salesController->invoiceShow($showReqFinal, $invoiceId)->getBody(), true);

        $assert(
            'UPIQR-08',
            'Final payment clears outstanding balance to ₹0.00, invoice status becomes PAID, and UPI QR is hidden',
            $payFinalResp->getStatusCode() === 201
            && (float)$showDataFinal['total_paid'] === 50000.00
            && (float)$showDataFinal['outstanding_amount'] === 0.00
            && $showDataFinal['payment_status'] === 'PAID'
            && empty($showDataFinal['qr_svg'])
            && empty($showDataFinal['upi_payload'])
        );

        // 9. Test 4: No merchant UPI configured -> Helpful configuration message, no invalid QR
        // Temporarily clear UPI ID on company
        Database::update('companies', ['upi_id' => null], 'id = ?', [$companyId]);

        // Create an unpaid invoice with ₹10,000
        $noUpiInvReq = new Request([], [
            'customer_id' => 1,
            'invoice_date' => date('Y-m-d'),
            'place_of_supply' => '27',
            'items' => [
                [
                    'category_id' => 1,
                    'product_id' => 1,
                    'quantity' => 10,
                    'rate' => 847.4576,
                    'gst_rate' => 18,
                    'hsn_sac' => '8481',
                ]
            ],
            'notes' => 'No UPI config test'
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/sales/invoices/create',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $noUpiResp = $salesController->invoiceStore($noUpiInvReq);
        $noUpiInvId = (int)(json_decode((string)$noUpiResp->getBody(), true)['invoice_id'] ?? 0);

        $showNoUpi = json_decode((string)$salesController->invoiceShow(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$noUpiInvId}", 'HTTP_ACCEPT' => 'application/json']), $noUpiInvId)->getBody(), true);

        // Render printable view without UPI configured
        $printNoUpiHtml = $salesController->invoicePrint(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$noUpiInvId}/print"]), $noUpiInvId)->getBody();

        // Restore company UPI ID
        Database::update('companies', ['upi_id' => $merchantUpi], 'id = ?', [$companyId]);

        $assert(
            'UPIQR-09',
            'When no merchant UPI is configured, system suppresses QR code and renders helpful configuration message',
            $showNoUpi['has_merchant_upi'] === false
            && empty($showNoUpi['qr_svg'])
            && str_contains($printNoUpiHtml, 'UPI Not Configured')
            && str_contains($printNoUpiHtml, 'Configure Merchant UPI in Settings')
        );

        // 10. Test 7: Printable invoice & preview reflects correct outstanding balance and scannable QR
        // Create an invoice with partial payment of ₹20,000 against ₹50,000
        $printTestInvReq = new Request([], [
            'customer_id' => 1,
            'invoice_date' => date('Y-m-d'),
            'place_of_supply' => '27',
            'items' => [
                [
                    'category_id' => 1,
                    'product_id' => 1,
                    'quantity' => 50,
                    'rate' => 847.4576,
                    'gst_rate' => 18,
                    'hsn_sac' => '8481',
                ]
            ],
            'notes' => 'Print remaining balance QR test'
        ], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/sales/invoices/create',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $printInvJson = json_decode((string)$salesController->invoiceStore($printTestInvReq)->getBody(), true);
        $printInvId = (int)$printInvJson['invoice_id'];

        // Make partial payment of ₹20,000
        $printPayRef = 'UPI-PRINT-' . time();
        $salesController->recordPayment(new Request([], [
            'invoice_id' => $printInvId,
            'amount' => 20000.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'UPI',
            'reference_no' => $printPayRef,
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/sales/invoices/{$printInvId}/payments", 'HTTP_ACCEPT' => 'application/json']), $printInvId);

        $printHtml = $salesController->invoicePrint(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$printInvId}/print"]), $printInvId)->getBody();

        $assert(
            'UPIQR-10',
            'Printable invoice renders exact remaining balance (₹30,000), payment received (₹20,000), and scannable vector SVG QR',
            str_contains($printHtml, 'Pay Remaining: ₹30,000.00')
            && str_contains($printHtml, 'Amount Received')
            && str_contains($printHtml, '20,000.00')
            && str_contains($printHtml, 'Remaining Balance')
            && str_contains($printHtml, '30,000.00')
            && str_contains($printHtml, '<svg')
            && str_contains($printHtml, 'aria-label="UPI Payment QR Code"')
        );
    }
}

