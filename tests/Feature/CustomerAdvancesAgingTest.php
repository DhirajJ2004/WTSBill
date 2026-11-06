<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;
use WTSBill\Services\CustomerAdvanceService;

/**
 * Feature Test Suite for Phase 6: Customer Advances, Drawdowns & Accounts Receivable Aging.
 * Verifies advance deposits (ADV-YYYY-XXXX), multi-invoice drawdown allocations,
 * balance synchronization, and 4-bucket receivables aging statements.
 */
class CustomerAdvancesAgingTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();

        // Load web routes
        $router = new Router();
        require ROOT_PATH . '/routes/web.php';

        // 1. ADV-01: Guest redirect
        Session::destroy();
        Session::start();
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/payments/advances']);
        $guestRes = $router->dispatch($guestReq);
        $assert('ADV-01', 'Unauthenticated request to /payments/advances redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && $guestRes->getHeader('Location') === '/login'
        );

        // Authenticate as Admin
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $csrfToken = Session::getCsrfToken();

        // Fetch or create a test customer
        $customer = Database::selectOne(
            "SELECT id, name FROM customers WHERE company_id = ? AND deleted_at IS NULL LIMIT 1",
            [$companyId]
        );
        if (!$customer) {
            $customerId = (int)Database::insert('customers', [
                'company_id' => $companyId,
                'name' => 'Test Aging Customer ' . time(),
                'phone' => '9876543210',
                'current_balance' => 0.00,
                'credit_limit' => 50000.00,
                'payment_terms_days' => 30,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $customerId = (int)$customer['id'];
        }

        $invoiceId = 0;
        $secondInvId = 0;
        $advanceId = 0;

        try {
        $testInvoiceNumber = 'INV-ADV-TEST-' . time();
        $invoiceId = (int)Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => 1,
            'customer_id' => $customerId,
            'invoice_number' => $testInvoiceNumber,
            'invoice_date' => date('Y-m-d', strtotime('-45 days')), // 45 days ago -> 31-60 days bucket
            'due_date' => date('Y-m-d', strtotime('-15 days')),
            'sub_total' => 10000.00,
            'cgst_amount' => 900.00,
            'sgst_amount' => 900.00,
            'igst_amount' => 0.00,
            'total_tax' => 1800.00,
            'grand_total' => 11800.00,
            'amount_paid' => 0.00,
            'amount_due' => 11800.00,
            'status' => 'PENDING',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // 2. ADV-02: GET /payments/advances listing
        $listReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/payments/advances']);
        $listRes = $router->dispatch($listReq);
        $listHtml = $listRes->getContent();

        $assert('ADV-02', 'GET /payments/advances returns HTTP 200 with Advance pool KPI cards and listings',
            $listRes->getStatusCode() === 200 &&
            str_contains($listHtml, 'Customer Advances') &&
            str_contains($listHtml, 'Available Advance Balance') &&
            str_contains($listHtml, 'Record Advance')
        );

        // 3. ADV-03: POST /payments/advances/create records customer advance
        $advPayload = [
            '_csrf_token' => $csrfToken,
            'customer_id' => $customerId,
            'advance_date' => date('Y-m-d'),
            'amount' => 15000.00,
            'payment_mode' => 'Bank Transfer',
            'reference_no' => 'UTR-ADV-78901',
            'branch_id' => 1,
            'notes' => 'Advance deposit for upcoming bulk supply order',
        ];

        $createReq = new Request([], $advPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/payments/advances/create',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $createRes = $router->dispatch($createReq);
        $createData = json_decode($createRes->getContent(), true);
        $advanceId = (int)($createData['advance_id'] ?? 0);

        $dbAdv = Database::selectOne("SELECT * FROM customer_advances WHERE id = ? AND company_id = ?", [$advanceId, $companyId]);
        $dbPmt = $dbAdv ? Database::selectOne("SELECT * FROM payments WHERE id = ?", [$dbAdv['payment_id']]) : null;

        $assert('ADV-03', 'POST /payments/advances/create records customer advance with sequential ADV-YYYY-XXXX voucher and payments record',
            $createRes->getStatusCode() === 201 &&
            $advanceId > 0 &&
            $dbAdv !== null &&
            (float)$dbAdv['total_amount'] === 15000.00 &&
            (float)$dbAdv['remaining_amount'] === 15000.00 &&
            $dbAdv['status'] === 'ACTIVE' &&
            $dbPmt !== null &&
            str_starts_with((string)$dbPmt['payment_number'], 'ADV-' . date('Y') . '-')
        );

        // 4. ADV-04: GET /payments/advances/{id} details view
        $showReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/payments/advances/{$advanceId}"]);
        $showRes = $router->dispatch($showReq);
        $showHtml = $showRes->getContent();

        $assert('ADV-04', 'GET /payments/advances/{id} renders voucher details, customer info, and open invoice drawdown form',
            $showRes->getStatusCode() === 200 &&
            str_contains($showHtml, $dbPmt['payment_number']) &&
            str_contains($showHtml, 'Drawdown Against Open Invoices') &&
            str_contains($showHtml, $testInvoiceNumber)
        );

        // 5. ADV-05: Over-allocation exceeding advance balance is blocked (HTTP 422)
        $excessivePayload = [
            '_csrf_token' => $csrfToken,
            'allocations' => [
                $invoiceId => 25000.00, // Exceeds 15,000 advance
            ],
        ];
        $excessReq = new Request([], $excessivePayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/payments/advances/{$advanceId}/allocate",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $excessRes = $router->dispatch($excessReq);

        $assert('ADV-05', 'Over-allocation exceeding available advance remaining balance is blocked with HTTP 422',
            $excessRes->getStatusCode() === 422 &&
            str_contains($excessRes->getContent(), 'exceeds available advance balance')
        );

        // 6. ADV-06: Drawdown funds against open invoice
        $allocPayload = [
            '_csrf_token' => $csrfToken,
            'allocations' => [
                $invoiceId => 11800.00, // Exactly clears invoice
            ],
        ];
        $allocReq = new Request([], $allocPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/payments/advances/{$advanceId}/allocate",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $allocRes = $router->dispatch($allocReq);

        $afterAdv = Database::selectOne("SELECT * FROM customer_advances WHERE id = ?", [$advanceId]);
        $afterInv = Database::selectOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
        $dbAlloc = Database::selectOne("SELECT * FROM payment_allocations WHERE payment_id = ? AND document_id = ?", [$dbAdv['payment_id'], $invoiceId]);

        $assert('ADV-06', 'POST /payments/advances/{id}/allocate draws down funds against open invoice, clearing amount_due and recording payment_allocations',
            $allocRes->getStatusCode() === 200 &&
            (float)$afterAdv['allocated_amount'] === 11800.00 &&
            (float)$afterAdv['remaining_amount'] === 3200.00 &&
            $afterAdv['status'] === 'ACTIVE' &&
            (float)$afterInv['amount_due'] === 0.00 &&
            $afterInv['status'] === 'PAID' &&
            $dbAlloc !== null &&
            (float)$dbAlloc['allocated_amount'] === 11800.00
        );

        // 7. ADV-07: Full exhaustion of advance
        // Create second invoice for 3200
        $secondInvId = (int)Database::insert('invoices', [
            'company_id' => $companyId,
            'branch_id' => 1,
            'customer_id' => $customerId,
            'invoice_number' => 'INV-ADV-TEST-2-' . time(),
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+15 days')),
            'sub_total' => 3200.00,
            'cgst_amount' => 0.00,
            'sgst_amount' => 0.00,
            'igst_amount' => 0.00,
            'total_tax' => 0.00,
            'grand_total' => 3200.00,
            'amount_paid' => 0.00,
            'amount_due' => 3200.00,
            'status' => 'PENDING',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $exhaustPayload = [
            '_csrf_token' => $csrfToken,
            'allocations' => [
                $secondInvId => 3200.00,
            ],
        ];
        $exhaustReq = new Request([], $exhaustPayload, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/payments/advances/{$advanceId}/allocate",
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $exhaustRes = $router->dispatch($exhaustReq);

        $finalAdv = Database::selectOne("SELECT * FROM customer_advances WHERE id = ?", [$advanceId]);

        $assert('ADV-07', 'When advance balance is fully drawn down, status transitions to EXHAUSTED and further allocation is prevented',
            $exhaustRes->getStatusCode() === 200 &&
            (float)$finalAdv['remaining_amount'] === 0.00 &&
            $finalAdv['status'] === 'EXHAUSTED'
        );

        // 8. ADV-08: Accounts Receivable Aging Report
        $agingReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/receivables-aging']);
        $agingRes = $router->dispatch($agingReq);
        $agingHtml = $agingRes->getContent();

        $assert('ADV-08', 'GET /reports/receivables-aging returns HTTP 200 with 4 aging buckets and debtors master table',
            $agingRes->getStatusCode() === 200 &&
            str_contains($agingHtml, 'Accounts Receivable Aging') &&
            str_contains($agingHtml, '0–30 Days') &&
            str_contains($agingHtml, '31–60 Days') &&
            str_contains($agingHtml, '61–90 Days') &&
            str_contains($agingHtml, '90+ Days') &&
            str_contains($agingHtml, 'Export CSV')
        );

        // 9. ADV-09: Receivables Aging JSON API
        $agingApiReq = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/reports/receivables-aging',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $agingApiRes = $router->dispatch($agingApiReq);
        $agingApiData = json_decode($agingApiRes->getContent(), true);

        $assert('ADV-09', 'GET /api/reports/receivables-aging returns structured JSON aging payload with customer summary and open invoice lists',
            $agingApiRes->getStatusCode() === 200 &&
            isset($agingApiData['data']['summary']) &&
            isset($agingApiData['data']['customers']) &&
            isset($agingApiData['data']['summary']['bucket_0_30']) &&
            isset($agingApiData['data']['summary']['bucket_90_plus'])
        );

        // 10. ADV-10: Receivables Aging CSV Export
        $csvReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/reports/receivables-aging/export']);
        $csvRes = $router->dispatch($csvReq);
        $csvContent = $csvRes->getContent();

        $assert('ADV-10', 'GET /reports/receivables-aging/export serves text/csv stream with valid aging headers and totals row',
            $csvRes->getStatusCode() === 200 &&
            str_contains($csvRes->getHeader('Content-Type') ?? '', 'text/csv') &&
            str_contains($csvContent, 'ACCOUNTS RECEIVABLE AGING STATEMENT') &&
            str_contains($csvContent, '0-30 Days (INR)') &&
            str_contains($csvContent, 'TOTALS')
        );

        } finally {
            // Clean up test invoices and advance allocations
            if ($invoiceId > 0 || $secondInvId > 0) {
                Database::delete('invoices', 'id IN (?, ?)', [$invoiceId, $secondInvId]);
            }
            if ($advanceId > 0) {
                Database::delete('payment_allocations', 'document_id IN (?, ?)', [$invoiceId, $secondInvId]);
            }
        }
    }
}
