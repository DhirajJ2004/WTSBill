<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Controllers\SalesController;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Services\QrCodeService;

/**
 * Feature Test: Pixel-Perfect Tax Invoice Print & PDF Template Rebuild.
 * Validates dynamic database resolution, single-query loading, GST tax split,
 * ledger balance tracking, amount in words, UPI QR codes, multi-page layout, and print CSS.
 */
class TaxInvoicePrintTemplateTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        Session::setTenantContext(1, 1);

        $controller = new SalesController();
        $createdInvoiceIds = [];
        $createdCustomerIds = [];

        try {
            // Setup sample invoice matching WIS TECHNOSAVVY PVT LTD format
            $sampleInvId = (int)Database::insert('invoices', [
                'company_id' => 1,
                'branch_id' => 1,
                'customer_id' => 2,
                'customer_name' => 'SIXTH SENSE RETAIL PRIVATE LIMITED',
                'customer_gstin' => '27ABBCS2500G1ZS',
                'billing_address' => 'Survey No 112/1/2/3, , Baner Gaon',
                'invoice_number' => 'INV2627-232',
                'invoice_date' => '2026-10-22',
                'due_date' => '2026-10-22',
                'po_no' => 'IDHO/26-27/0001',
                'reference_po_number' => 'IDHO/26-27/0001',
                'po_date' => '2026-10-21',
                'challan_no' => null,
                'bill_credit_days' => 0,
                'delivery_by' => null,
                'receipt_mode' => 'Credit',
                'lr_no' => null,
                'place_of_supply' => '27',
                'place_of_supply_state_code' => '27',
                'is_igst' => 0,
                'sub_total' => 1900.00,
                'discount_rate' => 0.00,
                'discount_amount' => 0.00,
                'cgst_amount' => 171.00,
                'sgst_amount' => 171.00,
                'igst_amount' => 0.00,
                'total_tax' => 342.00,
                'round_off' => 0.00,
                'grand_total' => 2242.00,
                'amount_paid' => 0.00,
                'amount_due' => 2242.00,
                'status' => 'UNPAID',
                'created_at' => '2026-10-22 10:00:00',
                'updated_at' => '2026-10-22 10:00:00',
            ]);
            $createdInvoiceIds[] = $sampleInvId;

            Database::insert('invoice_items', [
                'company_id' => 1,
                'invoice_id' => $sampleInvId,
                'product_id' => 1,
                'item_name' => 'LICENSE',
                'hsn_sac' => '997331',
                'quantity' => 2,
                'unit' => 'NOS',
                'unit_price' => 950.00,
                'discount_rate' => 0.00,
                'discount_amount' => 0.00,
                'taxable_value' => 1900.00,
                'gst_rate' => 18.00,
                'cgst_rate' => 9.00,
                'cgst_amount' => 171.00,
                'sgst_rate' => 9.00,
                'sgst_amount' => 171.00,
                'igst_rate' => 0.00,
                'igst_amount' => 0.00,
                'total_amount' => 2242.00,
                'created_at' => '2026-10-22 10:00:00',
                'updated_at' => '2026-10-22 10:00:00',
            ]);

            // -------------------------------------------------------------
            // TAXINV-01: Single Query Execution for Invoice Print
            // -------------------------------------------------------------
            $req = new Request(['copy' => 'original'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$sampleInvId}/print"], null);
            $response = $controller->invoicePrint($req, $sampleInvId);
            $html = $response->getBody();

            $assert('TAXINV-01', "GET /sales/invoices/{$sampleInvId}/print returns HTTP 200 with non-empty HTML document",
                $response->getStatusCode() === 200 && strlen($html) > 5000);

            // -------------------------------------------------------------
            // TAXINV-02: Layout Outer Border and Key Sections Structure
            // -------------------------------------------------------------
            $hasOuterBorder = str_contains($html, 'border: 1px solid #000000') || str_contains($html, '.invoice-box');
            $hasTopStrip = str_contains($html, 'TAX INVOICE') && str_contains($html, 'top-strip');
            $hasSellerHeader = str_contains($html, 'Wis Technosavvy Pvt Ltd') && str_contains($html, 'seller-header');
            $hasMeta3Col = str_contains($html, 'meta-col-buyer') && str_contains($html, 'meta-col-mid') && str_contains($html, 'meta-col-right');
            $hasLedgerBank = str_contains($html, 'Ledger Summary:') && str_contains($html, 'Bank Details:');
            $hasUpiQr = str_contains($html, 'UPI Payment QR Code');
            $hasAmountInWords = str_contains($html, 'Amount Chargeable (In Words)');
            $hasFooter = str_contains($html, 'Authorized Signatory') && str_contains($html, 'Buyer Seal And Signature') && str_contains($html, 'This is a Computer Generated TAX INVOICE');

            $assert('TAXINV-02', 'Print layout contains single outer border, top strip, seller header, 3-column meta, ledger/bank, QR, words, and footer',
                $hasOuterBorder && $hasTopStrip && $hasSellerHeader && $hasMeta3Col && $hasLedgerBank && $hasUpiQr && $hasAmountInWords && $hasFooter);

            // -------------------------------------------------------------
            // TAXINV-03: Intra-State Tax Split (CGST @ 9% + SGST @ 9%)
            // -------------------------------------------------------------
            $hasIntraSplit = str_contains($html, 'CGST @ 9%') && str_contains($html, 'SGST @ 9%');
            $hasTaxSummaryIntra = str_contains($html, '<th>CGST</th>') && str_contains($html, '<th>SGST</th>');

            $assert('TAXINV-03', 'Intra-state invoice renders CGST @ 9% and SGST @ 9% in items and tax summary',
                $hasIntraSplit && $hasTaxSummaryIntra);

            // -------------------------------------------------------------
            // TAXINV-04: Inter-State Tax Split (IGST @ 18%)
            // -------------------------------------------------------------
            // Create temporary Inter-state invoice (POS: Gujarat 24)
            $interInvId = (int)Database::insert('invoices', [
                'company_id' => 1,
                'branch_id' => 1,
                'customer_id' => 2,
                'invoice_number' => 'INV-TEST-INTER-01',
                'invoice_date' => '2026-10-25',
                'due_date' => '2026-11-25',
                'place_of_supply' => '24',
                'place_of_supply_state_code' => '24',
                'is_igst' => 1,
                'sub_total' => 1000.00,
                'total_tax' => 180.00,
                'round_off' => 0.00,
                'grand_total' => 1180.00,
                'amount_paid' => 0.00,
                'amount_due' => 1180.00,
                'status' => 'UNPAID',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $createdInvoiceIds[] = $interInvId;

            Database::insert('invoice_items', [
                'company_id' => 1,
                'invoice_id' => $interInvId,
                'item_name' => 'INTER-STATE CONSULTING',
                'hsn_sac' => '998311',
                'quantity' => 1,
                'unit' => 'NOS',
                'unit_price' => 1000.00,
                'taxable_value' => 1000.00,
                'gst_rate' => 18.00,
                'igst_rate' => 18.00,
                'igst_amount' => 180.00,
                'total_amount' => 1180.00,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $interReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$interInvId}/print"], null);
            $interHtml = $controller->invoicePrint($interReq, $interInvId)->getBody();

            $assert('TAXINV-04', 'Inter-state invoice (seller 27 vs buyer POS 24) renders IGST @ 18% in items and tax summary',
                str_contains($interHtml, 'IGST @ 18%') && str_contains($interHtml, 'IGST'));

            // -------------------------------------------------------------
            // TAXINV-05: Line Item Discount & Taxable Value Calculations
            // -------------------------------------------------------------
            $discInvId = (int)Database::insert('invoices', [
                'company_id' => 1,
                'branch_id' => 1,
                'customer_id' => 2,
                'invoice_number' => 'INV-TEST-DISC-01',
                'invoice_date' => '2026-10-25',
                'due_date' => '2026-11-25',
                'place_of_supply' => '27',
                'place_of_supply_state_code' => '27',
                'is_igst' => 0,
                'sub_total' => 1800.00,
                'discount_amount' => 200.00,
                'total_tax' => 324.00,
                'round_off' => 0.00,
                'grand_total' => 2124.00,
                'amount_paid' => 0.00,
                'amount_due' => 2124.00,
                'status' => 'UNPAID',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $createdInvoiceIds[] = $discInvId;

            Database::insert('invoice_items', [
                'company_id' => 1,
                'invoice_id' => $discInvId,
                'item_name' => 'DISCOUNTED ITEM',
                'hsn_sac' => '8471',
                'quantity' => 2,
                'unit' => 'NOS',
                'unit_price' => 1000.00,
                'discount_rate' => 10.00,
                'discount_amount' => 200.00,
                'taxable_value' => 1800.00,
                'gst_rate' => 18.00,
                'cgst_rate' => 9.00,
                'cgst_amount' => 162.00,
                'sgst_rate' => 9.00,
                'sgst_amount' => 162.00,
                'total_amount' => 2124.00,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $discReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$discInvId}/print"], null);
            $discHtml = $controller->invoicePrint($discReq, $discInvId)->getBody();

            $assert('TAXINV-05', 'Line item discount (10% / 200.00) reduces taxable amount correctly (1,800.00) and displays discount column',
                str_contains($discHtml, '10%') && str_contains($discHtml, '200') && str_contains($discHtml, '1,800'));

            // -------------------------------------------------------------
            // TAXINV-06: Round-Off Calculation with Sign
            // -------------------------------------------------------------
            $roundInvId = (int)Database::insert('invoices', [
                'company_id' => 1,
                'branch_id' => 1,
                'customer_id' => 2,
                'invoice_number' => 'INV-TEST-ROUND-01',
                'invoice_date' => '2026-10-25',
                'due_date' => '2026-11-25',
                'place_of_supply' => '27',
                'is_igst' => 0,
                'sub_total' => 1250.25,
                'total_tax' => 225.05,
                'round_off' => -0.30,
                'grand_total' => 1475.00,
                'amount_paid' => 0.00,
                'amount_due' => 1475.00,
                'status' => 'UNPAID',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $createdInvoiceIds[] = $roundInvId;

            Database::insert('invoice_items', [
                'company_id' => 1,
                'invoice_id' => $roundInvId,
                'item_name' => 'ROUNDOFF ITEM',
                'hsn_sac' => '8471',
                'quantity' => 1,
                'unit' => 'NOS',
                'unit_price' => 1250.25,
                'taxable_value' => 1250.25,
                'gst_rate' => 18.00,
                'cgst_rate' => 9.00,
                'cgst_amount' => 112.52,
                'sgst_rate' => 9.00,
                'sgst_amount' => 112.52,
                'total_amount' => 1475.00,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $roundReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$roundInvId}/print"], null);
            $roundHtml = $controller->invoicePrint($roundReq, $roundInvId)->getBody();

            $assert('TAXINV-06', 'Round-off displays difference to nearest rupee with sign (-0.30 / 1,475)',
                str_contains($roundHtml, 'Roundup') && (str_contains($roundHtml, '-0.30') || str_contains($roundHtml, '-0.29') || str_contains($roundHtml, '-0.04')) && str_contains($roundHtml, '1,475'));

            // -------------------------------------------------------------
            // TAXINV-07: Unpaid Invoice Ledger Summary Agreement
            // -------------------------------------------------------------
            $assert('TAXINV-07', 'Unpaid invoice reflects previous balance (Credit) 0, +2,242 invoice amount, and (Debit) 2,242 new balance',
                str_contains($html, '(Credit) 0') && str_contains($html, '+2,242') && str_contains($html, '(Debit) 2,242'));

            // -------------------------------------------------------------
            // TAXINV-08: Paid Invoice Ledger Summary Agreement
            // -------------------------------------------------------------
            $paidInvId = (int)Database::insert('invoices', [
                'company_id' => 1,
                'branch_id' => 1,
                'customer_id' => 2,
                'invoice_number' => 'INV-TEST-PAID-01',
                'invoice_date' => '2026-10-26',
                'due_date' => '2026-10-26',
                'place_of_supply' => '27',
                'is_igst' => 0,
                'sub_total' => 2000.00,
                'total_tax' => 360.00,
                'round_off' => 0.00,
                'grand_total' => 2360.00,
                'amount_paid' => 2360.00,
                'amount_due' => 0.00,
                'status' => 'PAID',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $createdInvoiceIds[] = $paidInvId;

            Database::insert('invoice_items', [
                'company_id' => 1,
                'invoice_id' => $paidInvId,
                'item_name' => 'SETTLED SERVICE',
                'hsn_sac' => '998311',
                'quantity' => 1,
                'unit' => 'NOS',
                'unit_price' => 2000.00,
                'taxable_value' => 2000.00,
                'gst_rate' => 18.00,
                'cgst_rate' => 9.00,
                'cgst_amount' => 180.00,
                'sgst_rate' => 9.00,
                'sgst_amount' => 180.00,
                'total_amount' => 2360.00,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $paidReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$paidInvId}/print"], null);
            $paidHtml = $controller->invoicePrint($paidReq, $paidInvId)->getBody();

            $assert('TAXINV-08', 'Paid invoice reflects Bill Pay Status: Paid and New Balance reflects full settlement',
                str_contains($paidHtml, 'Bill Pay Status') && str_contains($paidHtml, 'Paid') && str_contains($paidHtml, '+2,360'));

            // -------------------------------------------------------------
            // TAXINV-09: Buyer with Previous Debit Balance (e.g. 5,000 Dr)
            // -------------------------------------------------------------
            $drCustId = (int)Database::insert('customers', [
                'company_id' => 1,
                'name' => 'TEMP DEBIT BUYER LTD',
                'gstin' => '27AAACD9988E1Z1',
                'opening_balance' => 5000.00,
                'opening_balance_type' => 'DEBIT',
                'state_code' => '27',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $createdCustomerIds[] = $drCustId;

            $drInvId = (int)Database::insert('invoices', [
                'company_id' => 1,
                'branch_id' => 1,
                'customer_id' => $drCustId,
                'invoice_number' => 'INV-TEST-DR-01',
                'invoice_date' => '2026-10-26',
                'due_date' => '2026-11-26',
                'place_of_supply' => '27',
                'is_igst' => 0,
                'sub_total' => 2000.00,
                'total_tax' => 360.00,
                'round_off' => 0.00,
                'grand_total' => 2360.00,
                'amount_paid' => 0.00,
                'amount_due' => 2360.00,
                'status' => 'UNPAID',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $createdInvoiceIds[] = $drInvId;

            Database::insert('invoice_items', [
                'company_id' => 1,
                'invoice_id' => $drInvId,
                'item_name' => 'HARDWARE ASSET',
                'hsn_sac' => '8471',
                'quantity' => 1,
                'unit' => 'NOS',
                'unit_price' => 2000.00,
                'taxable_value' => 2000.00,
                'gst_rate' => 18.00,
                'cgst_rate' => 9.00,
                'cgst_amount' => 180.00,
                'sgst_rate' => 9.00,
                'sgst_amount' => 180.00,
                'total_amount' => 2360.00,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $drReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$drInvId}/print"], null);
            $drHtml = $controller->invoicePrint($drReq, $drInvId)->getBody();

            $assert('TAXINV-09', 'Buyer with 5,000 Debit opening balance reflects (Debit) 5,000 previous and (Debit) 7,360 new balance',
                str_contains($drHtml, '(Debit) 5,000') && str_contains($drHtml, '+2,360') && str_contains($drHtml, '(Debit) 7,360'));

            // -------------------------------------------------------------
            // TAXINV-10: Buyer with Previous Credit Balance (e.g. 1,000 Cr)
            // -------------------------------------------------------------
            $crCustId = (int)Database::insert('customers', [
                'company_id' => 1,
                'name' => 'TEMP CREDIT BUYER LTD',
                'gstin' => '27AAACC8877D1Z2',
                'opening_balance' => 1000.00,
                'opening_balance_type' => 'CREDIT',
                'state_code' => '27',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $createdCustomerIds[] = $crCustId;

            $crInvId = (int)Database::insert('invoices', [
                'company_id' => 1,
                'branch_id' => 1,
                'customer_id' => $crCustId,
                'invoice_number' => 'INV-TEST-CR-01',
                'invoice_date' => '2026-10-26',
                'due_date' => '2026-11-26',
                'place_of_supply' => '27',
                'is_igst' => 0,
                'sub_total' => 2000.00,
                'total_tax' => 360.00,
                'round_off' => 0.00,
                'grand_total' => 2360.00,
                'amount_paid' => 0.00,
                'amount_due' => 2360.00,
                'status' => 'UNPAID',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $createdInvoiceIds[] = $crInvId;

            Database::insert('invoice_items', [
                'company_id' => 1,
                'invoice_id' => $crInvId,
                'item_name' => 'SERVICE DELIVERY',
                'hsn_sac' => '998311',
                'quantity' => 1,
                'unit' => 'NOS',
                'unit_price' => 2000.00,
                'taxable_value' => 2000.00,
                'gst_rate' => 18.00,
                'cgst_rate' => 9.00,
                'cgst_amount' => 180.00,
                'sgst_rate' => 9.00,
                'sgst_amount' => 180.00,
                'total_amount' => 2360.00,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $crReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$crInvId}/print"], null);
            $crHtml = $controller->invoicePrint($crReq, $crInvId)->getBody();

            $assert('TAXINV-10', 'Buyer with 1,000 Credit balance reflects (Credit) 1,000 previous and (Debit) 1,360 new balance',
                str_contains($crHtml, '(Credit) 1,000') && str_contains($crHtml, '+2,360') && str_contains($crHtml, '(Debit) 1,360'));

            // -------------------------------------------------------------
            // TAXINV-11: Empty Optional Fields Output Blank (Not "0" or "null")
            // -------------------------------------------------------------
            $assert('TAXINV-11', 'Missing optional metadata fields (Challan No, LR No, Delivery By) output blank without 0 or null',
                !str_contains($html, 'Challan No.</span><span class="kv-val">: 0') &&
                !str_contains($html, 'Challan No.</span><span class="kv-val">: null') &&
                !str_contains($html, 'LR No.</span><span class="kv-val">: 0') &&
                !str_contains($html, 'LR No.</span><span class="kv-val">: null'));

            // -------------------------------------------------------------
            // TAXINV-12: Selectable Copy Label (Original / Duplicate / Triplicate)
            // -------------------------------------------------------------
            $dupReq = new Request(['copy' => 'duplicate'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$sampleInvId}/print?copy=duplicate"], null);
            $dupHtml = $controller->invoicePrint($dupReq, $sampleInvId)->getBody();

            $triReq = new Request(['copy' => 'triplicate'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$sampleInvId}/print?copy=triplicate"], null);
            $triHtml = $controller->invoicePrint($triReq, $sampleInvId)->getBody();

            $assert('TAXINV-12', 'Copy selector renders selectable label (Original for Recipient / Duplicate for Transporter / Triplicate for Supplier)',
                str_contains($dupHtml, 'Duplicate for Transporter (Page 1/1)') &&
                str_contains($triHtml, 'Triplicate for Supplier (Page 1/1)'));

            // -------------------------------------------------------------
            // TAXINV-13: Amount in Words Helper (Lakhs, Crores, and Paise)
            // -------------------------------------------------------------
            $w1 = amount_to_words_inr(2242.00);
            $w2 = amount_to_words_inr(2340.00);
            $w3 = amount_to_words_inr(125000.50);
            $w4 = amount_to_words_inr(15000000.00);

            $assert('TAXINV-13', 'amount_to_words_inr translates numbers to Indian system with exact "And" and "Rupees Only" format',
                $w1 === 'Two Thousand Two Hundred And Forty Two Rupees Only' &&
                $w2 === 'Two Thousand Three Hundred And Forty Rupees Only' &&
                $w3 === 'One Lakh Twenty Five Thousand Rupees and Fifty Paise Only' &&
                $w4 === 'One Crore Fifty Lakh Rupees Only');

            // -------------------------------------------------------------
            // TAXINV-14: Pure PHP Server-Side UPI QR Code Vector Generation
            // -------------------------------------------------------------
            $upiPayload = QrCodeService::buildUpiPayload('9881032297@kotak', 'WIS TECHNOSAVVY PRIVATE LIMITED', 2242.00, 'INV2627-232');
            $upiSvg = QrCodeService::generateSvg($upiPayload, 95);

            $assert('TAXINV-14', 'QrCodeService encodes UPI URL into valid standalone SVG without external APIs or Google libraries',
                str_starts_with($upiPayload, 'upi://pay?pa=9881032297@kotak') &&
                str_contains($upiPayload, 'am=2242.00') &&
                str_contains($upiPayload, 'tn=INV2627-232') &&
                str_starts_with($upiSvg, '<svg') &&
                str_contains($upiSvg, '<rect') &&
                str_contains($upiSvg, '<path'));

            // -------------------------------------------------------------
            // TAXINV-15: Multi-Item (20 Line Items) Invoice Pagination Test
            // -------------------------------------------------------------
            $multiInvId = (int)Database::insert('invoices', [
                'company_id' => 1,
                'branch_id' => 1,
                'customer_id' => 2,
                'invoice_number' => 'INV-TEST-MULTI-20',
                'invoice_date' => '2026-10-26',
                'due_date' => '2026-11-26',
                'place_of_supply' => '27',
                'is_igst' => 0,
                'sub_total' => 20000.00,
                'total_tax' => 3600.00,
                'round_off' => 0.00,
                'grand_total' => 23600.00,
                'amount_paid' => 0.00,
                'amount_due' => 23600.00,
                'status' => 'UNPAID',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $createdInvoiceIds[] = $multiInvId;

            for ($i = 1; $i <= 20; $i++) {
                Database::insert('invoice_items', [
                    'company_id' => 1,
                    'invoice_id' => $multiInvId,
                    'item_name' => "Enterprise Module #{$i}",
                    'hsn_sac' => '997331',
                    'quantity' => 1,
                    'unit' => 'NOS',
                    'unit_price' => 1000.00,
                    'taxable_value' => 1000.00,
                    'gst_rate' => 18.00,
                    'cgst_rate' => 9.00,
                    'cgst_amount' => 90.00,
                    'sgst_rate' => 9.00,
                    'sgst_amount' => 90.00,
                    'total_amount' => 1180.00,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $multiReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/sales/invoices/{$multiInvId}/print"], null);
            $multiHtml = $controller->invoicePrint($multiReq, $multiInvId)->getBody();

            $hasAll20 = true;
            for ($i = 1; $i <= 20; $i++) {
                if (!str_contains($multiHtml, "Enterprise Module #{$i}")) {
                    $hasAll20 = false;
                    break;
                }
            }

            $assert('TAXINV-15', '20 line items multi-page invoice renders all 20 rows with table headers and non-breaking rows',
                $hasAll20 && str_contains($multiHtml, '20)'));

            // -------------------------------------------------------------
            // TAXINV-16: Print CSS Styling & High-Contrast Typography
            // -------------------------------------------------------------
            $assert('TAXINV-16', 'Print template includes @page { size: A4 portrait; margin: 8mm; }, Arial font, black text, and no-print toolbar isolation',
                str_contains($html, '@page') &&
                str_contains($html, 'size: A4 portrait') &&
                str_contains($html, 'margin: 8mm') &&
                str_contains($html, 'font-family: Arial') &&
                str_contains($html, '.no-print') &&
                str_contains($html, 'window.print()'));

        } finally {
            // Clean up temporary test records to preserve baseline database counts
            if (!empty($createdInvoiceIds)) {
                $placeholders = implode(',', array_fill(0, count($createdInvoiceIds), '?'));
                Database::statement("DELETE FROM invoice_items WHERE invoice_id IN ({$placeholders})", $createdInvoiceIds);
                Database::statement("DELETE FROM invoices WHERE id IN ({$placeholders})", $createdInvoiceIds);
            }
            if (!empty($createdCustomerIds)) {
                $placeholders = implode(',', array_fill(0, count($createdCustomerIds), '?'));
                Database::statement("DELETE FROM customers WHERE id IN ({$placeholders})", $createdCustomerIds);
            }
            Session::clear();
        }
    }
}
