<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\QuotationController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\AuthService;

/**
 * Feature Test Suite: Dedicated Quotation Management & Sales Order Conversion (Feature B).
 * Validates quotation generation, line item category associations, editing, duplication,
 * one-click conversion to Tax Invoice, and duplicate conversion guards.
 */
class QuotationManagementTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        $controller = new QuotationController();

        Session::start();
        Session::clear();

        // 1. Authenticate Administrator
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $assert(
            'QUOTE-01',
            'Administrator authenticates and establishes quotation session context',
            Session::getCompanyId() === 1 && Session::get('user_id') === (int)$user['id']
        );

        $companyId = (int)Session::getCompanyId();
        $createdQuoteId = 0;
        $duplicatedQuoteId = 0;
        $convertedInvoiceId = 0;

        try {
            // 2. Create Quotation with multiple line items across different categories
            $createReq = new Request([], [
                'customer_id' => 1,
                'quotation_date' => date('Y-m-d'),
                'valid_until' => date('Y-m-d', strtotime('+30 days')),
                'place_of_supply' => '27',
                'reference_no' => 'RFQ-AUTOTEST-99',
                'kind_attn' => 'Mr. Vikram Patil',
                'payment_days' => 15,
                'status' => 'SENT',
                'notes' => 'Commercial bid with warranty for 1 year',
                'items' => [
                    [
                        'category_id' => 1, // Hardware
                        'product_id' => 1,
                        'quantity' => 10,
                        'rate' => 1250.00,
                        'gst_rate' => 18,
                        'hsn_sac' => '8481',
                    ],
                    [
                        'category_id' => 121, // Plumbing
                        'product_id' => 2,
                        'quantity' => 20,
                        'rate' => 450.00,
                        'gst_rate' => 18,
                        'hsn_sac' => '3917',
                    ]
                ]
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/sales/quotations/create',
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $createResp = $controller->store($createReq);
            $createJson = json_decode((string)$createResp->getBody(), true);
            $createdQuoteId = (int)($createJson['quotation_id'] ?? 0);

            $assert(
                'QUOTE-02',
                'Create sales quotation with multi-category items succeeds with HTTP 201',
                $createResp->getStatusCode() === 201 && $createdQuoteId > 0
            );

            // Fetch stored quotation
            $quote = Database::selectOne("SELECT * FROM quotations WHERE id = ?", [$createdQuoteId]);
            $assert(
                'QUOTE-03',
                'Quotation persists sequential number format (QT-YYYYMM-XXXX) and status SENT',
                preg_match('/^QT-\d{6}-\d{4}$/', (string)$quote['quotation_number']) === 1 &&
                $quote['status'] === 'SENT' &&
                (int)$quote['converted_to_invoice'] === 0
            );

            // Verify Line Items and categories
            $items = Database::select("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id ASC", [$createdQuoteId]);
            $assert(
                'QUOTE-04',
                'Quotation items accurately persist distinct category_id on each line item',
                count($items) === 2 &&
                (int)$items[0]['category_id'] === 1 &&
                (int)$items[1]['category_id'] === 121
            );

            // Verify Calculation
            // Line 1: 10 * 1250 = 12,500. Line 2: 20 * 450 = 9,000. Subtotal: 21,500. GST 18%: 3,870. Grand: 25,370.
            $assert(
                'QUOTE-05',
                'Quotation calculations correctly compute subtotal (₹21,500) and grand total (₹25,370)',
                abs((float)$quote['sub_total'] - 21500.00) < 0.01 &&
                abs((float)$quote['grand_total'] - 25370.00) < 0.01
            );

            // 3. GET /sales/quotations/{id} JSON API
            $showReq = new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => "/sales/quotations/{$createdQuoteId}",
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $showResp = $controller->show($showReq, $createdQuoteId);
            $showJson = json_decode((string)$showResp->getBody(), true);

            $assert(
                'QUOTE-06',
                'GET /sales/quotations/{id} returns quotation details and item categories via JSON',
                $showResp->getStatusCode() === 200 &&
                ($showJson['quotation']['quotation_number'] ?? '') === $quote['quotation_number'] &&
                count($showJson['items'] ?? []) === 2
            );

            // 4. Update Quotation line items (Edit workflow)
            $updateReq = new Request([], [
                'customer_id' => 1,
                'quotation_date' => date('Y-m-d'),
                'valid_until' => date('Y-m-d', strtotime('+45 days')),
                'place_of_supply' => '27',
                'status' => 'ACCEPTED',
                'notes' => 'Updated quotation notes after price negotiation',
                'items' => [
                    [
                        'category_id' => 1,
                        'product_id' => 1,
                        'quantity' => 12, // Increased qty
                        'rate' => 1200.00, // Negotiated discount
                        'gst_rate' => 18,
                        'hsn_sac' => '8481',
                    ]
                ]
            ], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/quotations/{$createdQuoteId}/update",
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $updateResp = $controller->update($updateReq, $createdQuoteId);
            $updatedQuote = Database::selectOne("SELECT * FROM quotations WHERE id = ?", [$createdQuoteId]);

            $assert(
                'QUOTE-07',
                'POST /sales/quotations/{id}/update successfully updates status to ACCEPTED and recalculates total',
                $updateResp->getStatusCode() === 200 &&
                $updatedQuote['status'] === 'ACCEPTED' &&
                abs((float)$updatedQuote['sub_total'] - 14400.00) < 0.01 // 12 * 1200 = 14400
            );

            // 5. Duplicate Quotation (Clone workflow)
            $dupReq = new Request([], [], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/quotations/{$createdQuoteId}/duplicate",
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $dupResp = $controller->duplicate($dupReq, $createdQuoteId);
            $dupJson = json_decode((string)$dupResp->getBody(), true);
            $duplicatedQuoteId = (int)($dupJson['quotation_id'] ?? 0);

            $dupQuote = Database::selectOne("SELECT * FROM quotations WHERE id = ?", [$duplicatedQuoteId]);
            $assert(
                'QUOTE-08',
                'POST /sales/quotations/{id}/duplicate creates a clone with new quotation number and DRAFT status',
                $dupResp->getStatusCode() === 201 &&
                $duplicatedQuoteId > 0 &&
                $dupQuote['quotation_number'] !== $quote['quotation_number'] &&
                $dupQuote['status'] === 'DRAFT'
            );

            // 6. Convert Quotation to Tax Invoice (Conversion workflow)
            $convReq = new Request([], [], [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => "/sales/quotations/{$createdQuoteId}/convert",
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $convResp = $controller->convertToInvoice($convReq, $createdQuoteId);
            $convJson = json_decode((string)$convResp->getBody(), true);
            $convertedInvoiceId = (int)($convJson['invoice_id'] ?? 0);

            $assert(
                'QUOTE-09',
                'POST /sales/quotations/{id}/convert generates sequential Tax Invoice with HTTP 201',
                $convResp->getStatusCode() === 201 && $convertedInvoiceId > 0
            );

            // Check converted quotation status and invoice link
            $quoteAfterConv = Database::selectOne("SELECT * FROM quotations WHERE id = ?", [$createdQuoteId]);
            $invAfterConv = Database::selectOne("SELECT * FROM invoices WHERE id = ?", [$convertedInvoiceId]);

            $assert(
                'QUOTE-10',
                'Quotation is marked CONVERTED and invoice links back to source_quotation_id',
                $quoteAfterConv['status'] === 'CONVERTED' &&
                (int)$quoteAfterConv['converted_to_invoice'] === 1 &&
                (int)$quoteAfterConv['converted_invoice_id'] === $convertedInvoiceId &&
                (int)$invAfterConv['source_quotation_id'] === $createdQuoteId
            );

            // 7. Duplicate Conversion Guard: Cannot convert already converted quotation
            $convAgainResp = $controller->convertToInvoice($convReq, $createdQuoteId);
            $assert(
                'QUOTE-11',
                'Attempting to re-convert an already converted quotation is strictly rejected with HTTP 422',
                $convAgainResp->getStatusCode() === 422
            );

        } finally {
            // Clean up test records
            if ($convertedInvoiceId > 0) {
                Database::execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$convertedInvoiceId]);
                Database::execute("DELETE FROM invoices WHERE id = ?", [$convertedInvoiceId]);
            }
            if ($duplicatedQuoteId > 0) {
                Database::execute("DELETE FROM quotation_items WHERE quotation_id = ?", [$duplicatedQuoteId]);
                Database::execute("DELETE FROM quotations WHERE id = ?", [$duplicatedQuoteId]);
            }
            if ($createdQuoteId > 0) {
                Database::execute("DELETE FROM quotation_items WHERE quotation_id = ?", [$createdQuoteId]);
                Database::execute("DELETE FROM quotations WHERE id = ?", [$createdQuoteId]);
            }
        }
    }
}

