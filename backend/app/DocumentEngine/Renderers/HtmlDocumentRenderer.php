<?php

namespace App\DocumentEngine\Renderers;

use App\DocumentEngine\Models\DocumentModel;
use App\DocumentEngine\Formatters\IndianCurrencyFormatter;
use App\Models\DocumentTemplate;

class HtmlDocumentRenderer
{
    /**
     * Render full HTML document string ready for preview, printing, or PDF conversion
     */
    public static function render(DocumentModel $doc, ?DocumentTemplate $template = null): string
    {
        $brandColor = $template ? ($template->brand_color ?: '#1e3a8a') : '#1e3a8a';
        $accentColor = $template ? ($template->accent_color ?: '#3b82f6') : '#3b82f6';
        $fontFamily = $template ? ($template->font_family ?: 'Inter, -apple-system, sans-serif') : 'Inter, -apple-system, sans-serif';
        $paperSize = strtolower($template ? ($template->paper_size ?: 'a4') : 'a4');
        $orientation = strtolower($template ? ($template->orientation ?: 'portrait') : 'portrait');

        $watermarkEnabled = $template ? (bool)$template->watermark_enabled : false;
        $watermarkText = $template ? ($template->watermark_text ?: ($doc->metadata['watermark'] ?? '')) : ($doc->metadata['watermark'] ?? '');
        $watermarkOpacity = $template ? ($template->watermark_opacity ?: 0.08) : 0.08;

        $logoPos = $template ? ($template->logo_position ?: 'LEFT') : 'LEFT';
        $showBank = $template ? (bool)$template->show_bank_details : true;
        $showUpi = $template ? (bool)$template->show_upi_qr : true;
        $showSig = $template ? (bool)$template->show_signature : true;
        $showHsn = $template ? (bool)$template->show_hsn_summary : true;
        $showTaxBreakdown = $template ? (bool)$template->show_tax_breakdown : true;
        $showWords = $template ? (bool)$template->show_amount_in_words : true;

        $company = $doc->company;
        $party = $doc->party;
        $totals = $doc->totals;

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($doc->title . ' - ' . $doc->documentNumber) ?></title>
    <style>
        @page {
            size: <?= $paperSize ?> <?= $orientation ?>;
            margin: 10mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: <?= $fontFamily ?>;
            font-size: 12px;
            line-height: 1.4;
            color: #1f2937;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .document-container {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            padding: 15px;
            position: relative;
            background: #ffffff;
        }
        .watermark {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 58px;
            font-weight: 800;
            color: rgba(30, 58, 138, <?= $watermarkOpacity ?>);
            text-transform: uppercase;
            letter-spacing: 5px;
            pointer-events: none;
            z-index: 0;
            white-space: nowrap;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid <?= $brandColor ?>;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }
        .company-logo {
            max-height: 65px;
            max-width: 180px;
            object-fit: contain;
        }
        .company-info h1 {
            font-size: 18px;
            font-weight: 700;
            color: <?= $brandColor ?>;
            margin-bottom: 2px;
        }
        .company-meta, .party-meta, .doc-meta {
            font-size: 11px;
            color: #4b5563;
        }
        .doc-title-badge {
            text-align: right;
        }
        .doc-title-badge h2 {
            font-size: 18px;
            font-weight: 800;
            color: <?= $brandColor ?>;
            letter-spacing: 0.5px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }
        .card-box {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 8px 10px;
            background-color: #f9fafb;
        }
        .card-box h3 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: <?= $brandColor ?>;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }
        .doc-info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .doc-info-table td {
            padding: 2px 4px;
        }
        .doc-info-table .label {
            font-weight: 600;
            color: #374151;
            width: 45%;
        }
        table.items-table, table.tax-table, table.statement-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 11px;
        }
        table.items-table th, table.tax-table th, table.statement-table th {
            background-color: <?= $brandColor ?>;
            color: #ffffff;
            font-weight: 600;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid <?= $brandColor ?>;
        }
        table.items-table td, table.tax-table td, table.statement-table td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.items-table tbody tr:nth-child(even), table.statement-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        
        .summary-section {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .summary-left {
            flex: 1.2;
        }
        .summary-right {
            flex: 0.8;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .totals-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #e5e7eb;
        }
        .totals-table tr.grand-total-row td {
            background-color: #f3f4f6;
            font-size: 13px;
            font-weight: 800;
            color: <?= $brandColor ?>;
            border-top: 2px solid <?= $brandColor ?>;
            border-bottom: 2px solid <?= $brandColor ?>;
        }
        .words-box {
            border: 1px dashed #9ca3af;
            padding: 6px 8px;
            border-radius: 4px;
            background-color: #fbfbfb;
            margin-bottom: 8px;
            font-size: 11px;
        }
        .bank-details-box {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 6px 8px;
            background-color: #f9fafb;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .upi-qr-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            background-color: #fff;
            margin-bottom: 8px;
        }
        .upi-qr-img {
            width: 55px;
            height: 55px;
        }
        .bottom-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            page-break-inside: avoid;
        }
        .terms-box {
            flex: 1.4;
            font-size: 10px;
            color: #4b5563;
        }
        .signatory-box {
            flex: 0.6;
            text-align: right;
            font-size: 11px;
        }
        .signature-space {
            height: 45px;
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
        }
        .signature-img {
            max-height: 45px;
            max-width: 120px;
            object-fit: contain;
        }
        .page-footer {
            margin-top: 15px;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
            border-top: 1px dashed #e5e7eb;
            padding-top: 4px;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: none;
                margin: 0;
            }
            .document-container {
                max-width: 100%;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="document-container">
        <?php if ($watermarkEnabled && !empty($watermarkText)): ?>
            <div class="watermark"><?= htmlspecialchars($watermarkText) ?></div>
        <?php endif; ?>

        <!-- HEADER SECTION -->
        <div class="header-section">
            <div style="display: flex; gap: 15px; align-items: center;">
                <?php if ($logoPos !== 'HIDDEN' && !empty($company['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="Logo" class="company-logo">
                <?php endif; ?>
                <div class="company-info">
                    <h1><?= htmlspecialchars($company['name'] ?? 'Company Name') ?></h1>
                    <div class="company-meta">
                        <?php if (!empty($company['address_line1'])): ?>
                            <div><?= htmlspecialchars($company['address_line1'] . ($company['city'] ? ', ' . $company['city'] : '') . ($company['pincode'] ? ' - ' . $company['pincode'] : '')) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($company['state'])): ?>
                            <div>State: <?= htmlspecialchars($company['state']) ?> (Code: <?= htmlspecialchars($company['state_code'] ?? '') ?>)</div>
                        <?php endif; ?>
                        <?php if (!empty($company['gstin'])): ?>
                            <div><strong>GSTIN:</strong> <?= htmlspecialchars($company['gstin']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($company['pan'])): ?>
                            <div><strong>PAN:</strong> <?= htmlspecialchars($company['pan']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($company['phone']) || !empty($company['email'])): ?>
                            <div>Phone: <?= htmlspecialchars($company['phone'] ?? '') ?> | Email: <?= htmlspecialchars($company['email'] ?? '') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="doc-title-badge">
                <h2><?= htmlspecialchars($doc->title) ?></h2>
                <div style="margin-top: 6px;">
                    <table class="doc-info-table">
                        <tr>
                            <td class="label"><?= str_contains($doc->documentType, 'PURCHASE') ? 'Purchase No:' : (str_contains($doc->documentType, 'ORDER') ? 'Order No:' : 'Doc No:') ?></td>
                            <td class="font-bold"><?= htmlspecialchars($doc->documentNumber) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Date:</td>
                            <td><?= htmlspecialchars($doc->documentDate) ?></td>
                        </tr>
                        <?php if (!empty($doc->dueDate)): ?>
                            <tr>
                                <td class="label">Due Date:</td>
                                <td><?= htmlspecialchars($doc->dueDate) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($doc->referenceNumber)): ?>
                            <tr>
                                <td class="label">Ref No:</td>
                                <td><?= htmlspecialchars($doc->referenceNumber) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($doc->placeOfSupply)): ?>
                            <tr>
                                <td class="label">Place of Supply:</td>
                                <td>State Code <?= htmlspecialchars($doc->placeOfSupply) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- PARTY & BILLING GRID -->
        <?php if ($party): ?>
            <div class="meta-grid">
                <div class="card-box">
                    <h3><?= ($party['party_type'] === 'SUPPLIER') ? 'Supplier (Bill From):' : 'Billed To (Customer):' ?></h3>
                    <div style="font-weight: 700; font-size: 12px; margin-bottom: 2px;"><?= htmlspecialchars($party['name'] ?? '') ?></div>
                    <div class="party-meta">
                        <?php if (!empty($party['billing_address'])): ?>
                            <div><?= nl2br(htmlspecialchars($party['billing_address'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($party['state'])): ?>
                            <div>State: <?= htmlspecialchars($party['state']) ?> (Code: <?= htmlspecialchars($party['state_code'] ?? '') ?>)</div>
                        <?php endif; ?>
                        <?php if (!empty($party['gstin'])): ?>
                            <div><strong>GSTIN:</strong> <?= htmlspecialchars($party['gstin']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($party['pan'])): ?>
                            <div><strong>PAN:</strong> <?= htmlspecialchars($party['pan']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($party['phone'])): ?>
                            <div>Phone: <?= htmlspecialchars($party['phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-box">
                    <h3>Shipped To:</h3>
                    <?php if (!empty($party['shipping_address'])): ?>
                        <div style="font-weight: 700; font-size: 12px; margin-bottom: 2px;"><?= htmlspecialchars($party['name'] ?? '') ?></div>
                        <div class="party-meta"><?= nl2br(htmlspecialchars($party['shipping_address'])) ?></div>
                    <?php else: ?>
                        <div class="party-meta" style="color: #6b7280; font-style: italic;">Same as billing address</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- LINE ITEMS TABLE -->
        <?php if (!empty($doc->items)): ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;" class="text-center">#</th>
                        <th style="width: 32%;">Item & Description</th>
                        <th style="width: 11%;" class="text-center">HSN/SAC</th>
                        <th style="width: 8%;" class="text-right">Qty</th>
                        <th style="width: 10%;" class="text-right">Rate</th>
                        <th style="width: 8%;" class="text-right">Disc</th>
                        <th style="width: 12%;" class="text-right">Taxable</th>
                        <th style="width: 14%;" class="text-right">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doc->items as $idx => $item): ?>
                        <tr>
                            <td class="text-center"><?= $item['sr_no'] ?? ($idx + 1) ?></td>
                            <td>
                                <div class="font-semibold"><?= htmlspecialchars($item['name'] ?? 'Item') ?></div>
                                <?php if (!empty($item['description'])): ?>
                                    <div style="font-size: 10px; color: #6b7280;"><?= htmlspecialchars($item['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= htmlspecialchars($item['hsn_sac'] ?? '') ?></td>
                            <td class="text-right"><?= htmlspecialchars($item['quantity'] ?? '1') ?> <?= htmlspecialchars($item['unit'] ?? '') ?></td>
                            <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($item['unit_price'] ?? 0), false) ?></td>
                            <td class="text-right"><?= floatval($item['discount_amount'] ?? 0) > 0 ? IndianCurrencyFormatter::format(floatval($item['discount_amount']), false) : '-' ?></td>
                            <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($item['taxable_amount'] ?? 0), false) ?></td>
                            <td class="text-right font-semibold"><?= IndianCurrencyFormatter::format(floatval($item['total_amount'] ?? 0), false) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- STATEMENTS / BOOK ENTRIES TABLE (IF STATEMENT/LEDGER TYPE) -->
        <?php if (!empty($doc->statementData['entries'])): ?>
            <div style="margin-bottom: 8px; font-weight: 700; font-size: 12px; color: <?= $brandColor ?>;">
                Period: <?= htmlspecialchars($doc->statementData['period'] ?? '') ?> | Opening Balance: <?= IndianCurrencyFormatter::format(floatval($doc->statementData['opening_balance'] ?? 0)) ?>
            </div>
            <table class="statement-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">Date</th>
                        <th style="width: 15%;">Reference</th>
                        <th style="width: 35%;">Particulars / Description</th>
                        <th style="width: 12%;" class="text-right">Debit (₹)</th>
                        <th style="width: 12%;" class="text-right">Credit (₹)</th>
                        <th style="width: 14%;" class="text-right">Balance (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doc->statementData['entries'] as $entry): ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['date'] ?? '') ?></td>
                            <td><?= htmlspecialchars($entry['journal_number'] ?? ($entry['reference_id'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($entry['description'] ?? '') ?></td>
                            <td class="text-right"><?= floatval($entry['debit'] ?? ($entry['cash_in'] ?? 0)) > 0 ? IndianCurrencyFormatter::format(floatval($entry['debit'] ?? ($entry['cash_in'] ?? 0)), false) : '-' ?></td>
                            <td class="text-right"><?= floatval($entry['credit'] ?? ($entry['cash_out'] ?? 0)) > 0 ? IndianCurrencyFormatter::format(floatval($entry['credit'] ?? ($entry['cash_out'] ?? 0)), false) : '-' ?></td>
                            <td class="text-right font-semibold"><?= IndianCurrencyFormatter::format(floatval($entry['balance'] ?? 0), false) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- RECONCILIATION SUMMARY (IF BANK RECONCILIATION) -->
        <?php if (!empty($doc->reconciliationData)): ?>
            <div class="card-box" style="margin-bottom: 12px;">
                <h3>Bank Reconciliation Summary (Period: <?= htmlspecialchars($doc->reconciliationData['statement_period'] ?? '') ?>)</h3>
                <table class="totals-table">
                    <tr><td>Bank Statement Closing Balance:</td><td class="text-right font-semibold"><?= IndianCurrencyFormatter::format(floatval($doc->reconciliationData['statement_closing_balance'] ?? 0)) ?></td></tr>
                    <tr><td>Add: Uncleared Deposits:</td><td class="text-right"><?= IndianCurrencyFormatter::format(floatval($doc->reconciliationData['uncleared_deposits'] ?? 0)) ?></td></tr>
                    <tr><td>Less: Unpresented Cheques:</td><td class="text-right">- <?= IndianCurrencyFormatter::format(floatval($doc->reconciliationData['unpresented_cheques'] ?? 0)) ?></td></tr>
                    <tr style="background:#f3f4f6;"><td><strong>Reconciled Bank Balance:</strong></td><td class="text-right font-bold"><?= IndianCurrencyFormatter::format(floatval($doc->reconciliationData['reconciled_balance'] ?? 0)) ?></td></tr>
                    <tr><td>Authoritative Book Closing Balance:</td><td class="text-right"><?= IndianCurrencyFormatter::format(floatval($doc->reconciliationData['book_closing_balance'] ?? 0)) ?></td></tr>
                    <tr style="border-top: 1px solid <?= $brandColor ?>;"><td><strong>Difference / Discrepancy:</strong></td><td class="text-right font-bold" style="color: <?= floatval($doc->reconciliationData['difference_amount'] ?? 0) == 0 ? 'green' : 'red' ?>;"><?= IndianCurrencyFormatter::format(floatval($doc->reconciliationData['difference_amount'] ?? 0)) ?></td></tr>
                </table>
            </div>
        <?php endif; ?>

        <!-- SUMMARY & TOTALS SECTION -->
        <?php if (!empty($totals)): ?>
            <div class="summary-section">
                <div class="summary-left">
                    <?php if ($showWords && !empty($totals['amount_in_words'])): ?>
                        <div class="words-box">
                            <strong>Amount in Words:</strong><br>
                            <?= htmlspecialchars($totals['amount_in_words']) ?>
                        </div>
                    <?php endif; ?>

                    <!-- HSN / GST Tax Breakdown -->
                    <?php if ($showTaxBreakdown && !empty($doc->taxBreakdown)): ?>
                        <table class="tax-table">
                            <thead>
                                <tr>
                                    <th>HSN/SAC</th>
                                    <th class="text-right">Taxable</th>
                                    <th class="text-right">CGST</th>
                                    <th class="text-right">SGST</th>
                                    <th class="text-right">IGST</th>
                                    <th class="text-right">Total Tax</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doc->taxBreakdown as $tRow): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tRow['hsn_sac'] ?? 'Total') ?></td>
                                        <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($tRow['taxable_amount'] ?? 0), false) ?></td>
                                        <td class="text-right"><?= floatval($tRow['cgst_amount'] ?? 0) > 0 ? IndianCurrencyFormatter::format(floatval($tRow['cgst_amount']), false) : '-' ?></td>
                                        <td class="text-right"><?= floatval($tRow['sgst_amount'] ?? 0) > 0 ? IndianCurrencyFormatter::format(floatval($tRow['sgst_amount']), false) : '-' ?></td>
                                        <td class="text-right"><?= floatval($tRow['igst_amount'] ?? 0) > 0 ? IndianCurrencyFormatter::format(floatval($tRow['igst_amount']), false) : '-' ?></td>
                                        <td class="text-right font-semibold"><?= IndianCurrencyFormatter::format(floatval($tRow['total_tax'] ?? 0), false) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <!-- Bank Details -->
                    <?php if ($showBank && !empty($doc->bankDetails)): ?>
                        <div class="bank-details-box">
                            <strong>Bank Account Details for Payment:</strong><br>
                            Bank: <?= htmlspecialchars($doc->bankDetails['bank_name'] ?? '') ?> | A/C Name: <?= htmlspecialchars($doc->bankDetails['account_name'] ?? '') ?><br>
                            A/C No: <?= htmlspecialchars($doc->bankDetails['masked_account_number'] ?? ($doc->bankDetails['account_number'] ?? '')) ?> | IFSC: <?= htmlspecialchars($doc->bankDetails['ifsc_code'] ?? '') ?>
                            <?php if (!empty($doc->bankDetails['upi_id'])): ?>
                                <br>UPI ID: <?= htmlspecialchars($doc->bankDetails['upi_id']) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="summary-right">
                    <table class="totals-table">
                        <tr>
                            <td>Taxable Amount:</td>
                            <td class="text-right font-semibold"><?= IndianCurrencyFormatter::format(floatval($totals['taxable_amount'] ?? 0)) ?></td>
                        </tr>
                        <?php if (floatval($totals['cgst_amount'] ?? 0) > 0): ?>
                            <tr>
                                <td>CGST:</td>
                                <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($totals['cgst_amount'])) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (floatval($totals['sgst_amount'] ?? 0) > 0): ?>
                            <tr>
                                <td>SGST:</td>
                                <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($totals['sgst_amount'])) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (floatval($totals['igst_amount'] ?? 0) > 0): ?>
                            <tr>
                                <td>IGST:</td>
                                <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($totals['igst_amount'])) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (floatval($totals['cess_amount'] ?? 0) > 0): ?>
                            <tr>
                                <td>Cess:</td>
                                <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($totals['cess_amount'])) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (isset($totals['round_off']) && floatval($totals['round_off']) != 0): ?>
                            <tr>
                                <td>Round Off:</td>
                                <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($totals['round_off'])) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="grand-total-row">
                            <td>Grand Total:</td>
                            <td class="text-right"><?= IndianCurrencyFormatter::format(floatval($totals['grand_total'] ?? 0)) ?></td>
                        </tr>
                        <?php if (isset($doc->paymentInfo['amount_paid']) && floatval($doc->paymentInfo['amount_paid']) > 0): ?>
                            <tr>
                                <td>Amount Received:</td>
                                <td class="text-right" style="color: #059669;"><?= IndianCurrencyFormatter::format(floatval($doc->paymentInfo['amount_paid'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Balance Due:</strong></td>
                                <td class="text-right font-bold" style="color: #dc2626;"><?= IndianCurrencyFormatter::format(floatval($doc->paymentInfo['amount_due'])) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- BOTTOM TERMS & SIGNATURE SECTION -->
        <div class="bottom-section">
            <div class="terms-box">
                <?php if ($doc->terms): ?>
                    <strong>Terms & Conditions:</strong><br>
                    <?= nl2br(htmlspecialchars($doc->terms)) ?>
                <?php endif; ?>
                <?php if ($doc->notes): ?>
                    <div style="margin-top: 4px;"><strong>Notes:</strong> <?= htmlspecialchars($doc->notes) ?></div>
                <?php endif; ?>
            </div>

            <?php if ($showSig): ?>
                <div class="signatory-box">
                    <div>For <strong><?= htmlspecialchars($company['name'] ?? 'Company') ?></strong></div>
                    <div class="signature-space">
                        <?php if (!empty($doc->signatory['signature_url'])): ?>
                            <img src="<?= htmlspecialchars($doc->signatory['signature_url']) ?>" alt="Signature" class="signature-img">
                        <?php endif; ?>
                    </div>
                    <div style="border-top: 1px solid #9ca3af; padding-top: 2px; font-weight: 600;"><?= htmlspecialchars($doc->signatory['authorized_label'] ?? 'Authorized Signatory') ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="page-footer">
            Generated on <?= htmlspecialchars($doc->metadata['generated_at'] ?? date('Y-m-d H:i:s')) ?> | <?= htmlspecialchars($doc->documentNumber) ?> | WTSBill Commercial System
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
