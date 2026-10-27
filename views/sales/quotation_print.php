<?php
/**
 * WTSBill ERP — Pixel-Accurate Commercial Quotation Printable Document Template (A4 Portrait)
 * Exact match to WIS TECHNOSAVVY PVT LTD quotation layout (No. QN-2627-112).
 *
 * @var array  $quote
 * @var array  $items
 * @var array  $taxGroups
 * @var array  $seller
 * @var array  $buyer
 * @var array  $bank
 * @var float  $totalQty
 * @var float  $totalTaxable
 * @var float  $totalCgst
 * @var float  $totalSgst
 * @var float  $totalIgst
 * @var float  $totalGst
 * @var float  $roundOff
 * @var string $roundOffDisplay
 * @var float  $grandTotal
 * @var string $amountInWords
 * @var string $qrSvg
 * @var bool   $isIntraState
 */

$valOrBlank = function (?string $val): string {
    $t = trim((string)($val ?? ''));
    return ($t === '' || $t === '0' || strtolower($t) === 'null') ? '' : $t;
};

$formatDateOrBlank = function (?string $date): string {
    $t = trim((string)($date ?? ''));
    if ($t === '' || $t === '0000-00-00' || strtolower($t) === 'null') {
        return '';
    }
    $ts = strtotime($t);
    return $ts ? date('d-m-Y', $ts) : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation - <?= e($quote['quotation_number'] ?? 'QT') ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            color: #000000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
            line-height: 1.25;
        }

        /* Screen Floating Toolbar (Hidden on physical print) */
        .screen-toolbar {
            max-width: 210mm;
            margin: 12px auto;
            padding: 8px 16px;
            background: #1e293b;
            color: #ffffff;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .screen-toolbar a, .screen-toolbar button {
            color: #ffffff;
            text-decoration: none;
            background: #334155;
            border: 1px solid #475569;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .screen-toolbar a:hover, .screen-toolbar button:hover {
            background: #475569;
        }

        .screen-toolbar button.btn-print {
            background: #0284c7;
            border-color: #0369a1;
            font-weight: 600;
        }

        .screen-toolbar button.btn-print:hover {
            background: #0369a1;
        }

        /* Page Container (A4 Portrait) */
        .page-wrapper {
            width: 210mm;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 12mm 10mm;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* Typography & Helper Classes */
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        /* 1. Header (No outer box!) */
        .seller-header {
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
            gap: 16px;
            margin-bottom: 6px;
        }

        .seller-logo {
            flex-shrink: 0;
            width: 82px;
        }

        .seller-logo img, .seller-logo svg {
            width: 80px;
            height: auto;
            max-height: 65px;
            display: block;
        }

        .seller-info {
            flex-grow: 1;
            line-height: 1.3;
        }

        .seller-name {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
        }

        .seller-address-block {
            font-size: 8pt;
            color: #000000;
        }

        /* 2. Quotation Title */
        .quote-title-container {
            text-align: center;
            margin: 8px 0 10px 0;
        }

        .quote-title {
            color: #1D4ED8;
            font-size: 13.5pt;
            font-weight: bold;
            text-decoration: underline;
            letter-spacing: 0.4px;
        }

        /* 3. Party Block */
        .party-block {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 8.5pt;
        }

        .party-col-buyer {
            width: 60%;
            vertical-align: top;
            padding-right: 12px;
            line-height: 1.35;
        }

        .party-col-meta {
            width: 40%;
            vertical-align: top;
            text-align: right;
            line-height: 1.45;
        }

        .buyer-name {
            font-weight: bold;
            font-size: 9.5pt;
            margin-bottom: 1px;
        }

        .meta-kv-row {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            font-size: 8.5pt;
        }

        .meta-kv-label {
            width: 110px;
            text-align: right;
        }

        .meta-kv-val {
            width: 95px;
            text-align: left;
        }

        .kind-attn-row {
            text-align: center;
            font-weight: bold;
            font-size: 9pt;
            margin: 4px 0 8px 0;
        }

        /* 4. Line Item Table */
        .quote-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000000;
            font-size: 8pt;
            page-break-inside: auto;
        }

        .quote-table thead {
            display: table-header-group;
        }

        .quote-table th {
            border: 1px solid #000000;
            padding: 4px 2px;
            font-size: 7.8pt;
            font-weight: bold;
            text-align: center;
            background: #ffffff;
            vertical-align: middle;
        }

        .quote-table td {
            border-left: 1px solid #000000;
            border-right: 1px solid #000000;
            padding: 3px 3px;
            vertical-align: top;
        }

        .col-sn { width: 4%; text-align: center; }
        .col-desc { width: 33%; text-align: left; }
        .col-hsn { width: 8%; text-align: center; }
        .col-gst { width: 5%; text-align: center; }
        .col-qty { width: 6%; text-align: center; }
        .col-uqc { width: 5%; text-align: center; }
        .col-disc1 { width: 4%; text-align: center; }
        .col-price { width: 7.5%; text-align: right; }
        .col-disc2 { width: 4%; text-align: center; }
        .col-pwgst { width: 8%; text-align: right; }
        .col-taxrate { width: 7.5%; text-align: right; }
        .col-amt { width: 9%; text-align: right; }

        .item-category {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8pt;
            margin-bottom: 1px;
        }

        .item-desc {
            font-size: 7.5pt;
            line-height: 1.25;
            color: #111111;
            white-space: pre-line;
            word-break: break-word;
        }

        /* Spacer row providing form-like empty vertical height */
        .quote-spacer-row td {
            height: 110px;
        }

        /* Subtotal and Tax Split inside Table */
        .subtotal-border-row td {
            border-top: 1px solid #000000;
            padding: 3px 3px;
            vertical-align: top;
        }

        .qty-total-box {
            border: 1px solid #000000;
            font-weight: bold;
            text-align: center;
            padding: 2px 0;
            margin: 0 auto;
            width: 100%;
        }

        .tax-split-line {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 8pt;
            padding: 1px 0;
        }

        /* 5. Tax Summary & Grand Total Row (Below Line Items) */
        .tax-summary-container {
            display: flex;
            border: 1px solid #000000;
            border-top: none;
            background: #ffffff;
        }

        .tax-summary-left {
            width: 71%;
            border-right: 1px solid #000000;
        }

        .tax-mini-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tax-mini-table th {
            border-bottom: 1px solid #000000;
            border-right: 1px solid #000000;
            padding: 3px 4px;
            font-size: 7.8pt;
            font-weight: bold;
            text-align: center;
        }

        .tax-mini-table th:last-child {
            border-right: none;
        }

        .tax-mini-table td {
            border-right: 1px solid #000000;
            padding: 3px 4px;
            font-size: 8pt;
            text-align: center;
        }

        .tax-mini-table td:last-child {
            border-right: none;
        }

        .tax-summary-right {
            width: 29%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0;
        }

        .roundup-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 8px;
            font-size: 8pt;
        }

        .grand-total-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 8px;
            font-size: 9pt;
            font-weight: bold;
            border-top: 1px solid #000000;
        }

        /* 6. Amount in Words Box */
        .words-container {
            border: 1px solid #000000;
            border-top: none;
            padding: 4px 8px;
            font-size: 8pt;
            background: #ffffff;
        }

        .words-title {
            font-size: 7.5pt;
            color: #333333;
            margin-bottom: 1px;
        }

        .words-body {
            font-weight: bold;
            font-size: 8.5pt;
        }

        /* 7. Bank Details Band */
        .bank-band {
            margin-top: 10px;
            border: 1px solid #000000;
            background: #E0F7FA;
            padding: 6px 12px;
        }

        .bank-header-row {
            display: flex;
            font-weight: bold;
            font-size: 8.5pt;
            margin-bottom: 4px;
        }

        .bank-header-left {
            width: 55%;
        }

        .bank-header-center {
            width: 45%;
            text-align: left;
            padding-left: 20px;
        }

        .bank-body-row {
            display: flex;
            align-items: center;
        }

        .bank-details-col {
            width: 55%;
            font-size: 8pt;
            line-height: 1.45;
        }

        .bank-qr-col {
            width: 45%;
            text-align: left;
            padding-left: 20px;
        }

        .bank-qr-col svg {
            display: block;
            width: 78px;
            height: 78px;
        }

        /* 8. Terms & Conditions Band */
        .terms-band {
            margin-top: 10px;
            border: 1px solid #000000;
            background: #E0F7FA;
            padding: 6px 12px;
            font-size: 8pt;
            line-height: 1.4;
        }

        .terms-title {
            font-weight: bold;
            font-size: 8.5pt;
            margin-bottom: 4px;
        }

        .terms-item {
            margin-bottom: 2px;
        }

        /* 9. Signatory Footer */
        .signatory-footer {
            margin-top: 12px;
            display: flex;
            justify-content: flex-end;
            text-align: right;
        }

        .signatory-inner {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-end;
            min-width: 220px;
        }

        .stamp-signature-area {
            height: 60px;
            margin-bottom: 4px;
        }

        .stamp-signature-area svg, .stamp-signature-area img {
            max-height: 60px;
            max-width: 140px;
            display: block;
        }

        .signatory-company {
            font-weight: bold;
            font-size: 9pt;
            text-transform: uppercase;
        }

        .signatory-title {
            font-size: 7.8pt;
            margin-top: 2px;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .page-wrapper {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            .quote-table {
                page-break-inside: auto;
            }
            .quote-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>

    <!-- Screen Floating Control Bar (Hidden on print) -->
    <div class="screen-toolbar no-print">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/sales/quotations">&larr; Back to Quotations</a>
            <span style="font-weight: 600; font-size: 13px;">Quotation Preview: <?= e($quote['quotation_number']) ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <a href="mailto:<?= e($buyer['email']) ?>?subject=Quotation%20<?= urlencode($quote['quotation_number']) ?>&body=Dear%20<?= urlencode($buyer['name']) ?>,%0D%0APlease%20find%20attached%20quotation%20<?= urlencode($quote['quotation_number']) ?>%20for%20your%20kind%20review.%0D%0A%0D%0AThank%20you,%0D%0A<?= urlencode($seller['company_name']) ?>" class="btn-email">
                <span>✉️</span> Email to Customer
            </a>
            <button type="button" class="btn-print" onclick="window.print()">
                <span>🖨️</span> Print / Save PDF
            </button>
        </div>
    </div>

    <!-- Main Quotation Document Container -->
    <div class="page-wrapper">

        <!-- 1. Header (No outer box) -->
        <div class="seller-header">
            <div class="seller-logo">
                <?php if (!empty($seller['company_logo'])): ?>
                    <img src="<?= e($seller['company_logo']) ?>" alt="Logo">
                <?php else: ?>
                    <!-- Built-in authentic WTS Vector Logo -->
                    <svg viewBox="0 0 100 80" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="50" cy="40" rx="46" ry="34" fill="#0088cc"/>
                        <text x="50" y="50" font-family="Arial, Helvetica, sans-serif" font-weight="900" font-style="italic" font-size="34" fill="#ffffff" text-anchor="middle">wts</text>
                    </svg>
                <?php endif; ?>
            </div>
            <div class="seller-info">
                <div class="seller-name"><?= e($seller['company_legal_name'] ?: $seller['company_name']) ?></div>
                <div class="seller-address-block">
                    <?php if (!empty($seller['company_address_line1'])): ?>
                        <div><?= e($seller['company_address_line1']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($seller['company_address_line2'])): ?>
                        <div><?= e($seller['company_address_line2']) ?></div>
                    <?php endif; ?>
                    <div><?= e($seller['company_city']) ?>, <?= e($seller['company_state']) ?> <?= e($seller['company_pincode']) ?></div>
                    <div>
                        Tele : <?= e($seller['company_phone'] ?: '020 47252364') ?>, 
                        Email : <?= e($seller['company_email'] ?: 'info@wtsindia.co.in') ?>
                    </div>
                    <div>Web: <?= e($seller['company_website'] ?: 'http://www.wtsindia.co.in') ?></div>
                    <div><strong>GSTIN : <?= e($seller['company_gstin'] ?: '27AADCW7577N1ZE') ?></strong></div>
                </div>
            </div>
        </div>

        <!-- 2. Quotation Title -->
        <div class="quote-title-container">
            <span class="quote-title">Quotation</span>
        </div>

        <!-- 3. Party Block -->
        <table class="party-block">
            <tr>
                <td class="party-col-buyer">
                    <div>To,</div>
                    <div class="buyer-name"><?= e($buyer['name']) ?></div>
                    <?php if (!empty($buyer['address'])): ?>
                        <div><?= e($buyer['address']) ?></div>
                    <?php endif; ?>
                    <div><?= e($buyer['city']) ?> - <?= e($buyer['state']) ?></div>
                    <?php if (!empty($buyer['pincode'])): ?>
                        <div>PIN :<?= e($buyer['pincode']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($buyer['gstin'])): ?>
                        <div><strong>GSTIN : <?= e($buyer['gstin']) ?></strong></div>
                    <?php endif; ?>
                    <div><strong>POS : <?= e($buyer['pos_state']) ?> [State Code : <?= e($buyer['pos_code']) ?>]</strong></div>
                </td>
                <td class="party-col-meta">
                    <div class="meta-kv-row">
                        <span class="meta-kv-label">Quotation No.:</span>
                        <strong class="meta-kv-val"><?= e($quote['quotation_number']) ?></strong>
                    </div>
                    <div class="meta-kv-row">
                        <span class="meta-kv-label">Date :</span>
                        <strong class="meta-kv-val"><?= e($formatDateOrBlank($quote['quotation_date'])) ?></strong>
                    </div>
                    <?php if (!empty($quote['valid_until'])): ?>
                        <div class="meta-kv-row" style="color: #333333;">
                            <span class="meta-kv-label">Valid Until :</span>
                            <span class="meta-kv-val"><?= e($formatDateOrBlank($quote['valid_until'])) ?></span>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- Kind Attn (Centered below Party Block, hidden if empty) -->
        <?php if (!empty($quote['kind_attn'])): ?>
            <div class="kind-attn-row">Kind Attn.: <?= e($quote['kind_attn']) ?></div>
        <?php endif; ?>

        <!-- 4. Line Item Table -->
        <table class="quote-table">
            <thead>
                <tr>
                    <th class="col-sn">S/N</th>
                    <th class="col-desc" style="text-align: left; padding-left: 4px;">Description Of Goods / Service</th>
                    <th class="col-hsn">HSN/SAC</th>
                    <th class="col-gst">GST</th>
                    <th class="col-qty">Quantity</th>
                    <th class="col-uqc">UQC</th>
                    <th class="col-disc1">Disc</th>
                    <th class="col-price" style="text-align: right; padding-right: 4px;">Price</th>
                    <th class="col-disc2">Disc</th>
                    <th class="col-pwgst" style="text-align: right; padding-right: 4px;">Price With<br>GST</th>
                    <th class="col-taxrate" style="text-align: right; padding-right: 4px;">Taxable<br>Rate</th>
                    <th class="col-amt" style="text-align: right; padding-right: 4px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $it): ?>
                    <tr>
                        <td class="col-sn"><?= $idx + 1 ?>)</td>
                        <td class="col-desc">
                            <?php if (!empty($it['category'])): ?>
                                <div class="item-category"><?= e($it['category']) ?></div>
                            <?php endif; ?>
                            <div class="item-desc"><?= nl2br(e($it['description'])) ?></div>
                        </td>
                        <td class="col-hsn"><?= e($valOrBlank($it['hsn_sac'] ?? '')) ?></td>
                        <td class="col-gst"><?= (float)$it['gst_rate'] ?>%</td>
                        <td class="col-qty"><strong><?= (float)$it['quantity'] ?></strong></td>
                        <td class="col-uqc"><?= e(strtoupper($it['unit'] ?: 'NOS')) ?></td>
                        <td class="col-disc1"><?= (float)($it['discount_rate'] ?? 0) > 0 ? (float)$it['discount_rate'] . '%' : '' ?></td>
                        <td class="col-price"><?= format_inr_amount($it['unit_price'], false, 0) ?></td>
                        <td class="col-disc2"><?= (float)($it['discount_amount'] ?? 0) > 0 ? format_inr_amount($it['discount_amount'], false, 0) : '' ?></td>
                        <td class="col-pwgst"><?= format_inr_amount($it['price_with_gst'], false, ($it['price_with_gst'] == floor($it['price_with_gst']) ? 0 : 1)) ?></td>
                        <td class="col-taxrate"><?= format_inr_amount($it['taxable_rate'], false, 0) ?></td>
                        <td class="col-amt"><strong><?= format_inr_amount($it['taxable_value'], false, 0) ?></strong></td>
                    </tr>
                <?php endforeach; ?>

                <!-- Spacer row giving form-like vertical lines -->
                <tr class="quote-spacer-row">
                    <td class="col-sn"></td>
                    <td class="col-desc"></td>
                    <td class="col-hsn"></td>
                    <td class="col-gst"></td>
                    <td class="col-qty"></td>
                    <td class="col-uqc"></td>
                    <td class="col-disc1"></td>
                    <td class="col-price"></td>
                    <td class="col-disc2"></td>
                    <td class="col-pwgst"></td>
                    <td class="col-taxrate"></td>
                    <td class="col-amt"></td>
                </tr>

                <!-- Sub-total Row & Tax Breakdown -->
                <tr class="subtotal-border-row">
                    <td class="col-sn"></td>
                    <td class="col-desc"></td>
                    <td class="col-hsn"></td>
                    <td class="col-gst"></td>
                    <td class="col-qty">
                        <div class="qty-total-box"><?= (float)$totalQty ?></div>
                    </td>
                    <td class="col-uqc"></td>
                    <td class="col-disc1"></td>
                    <td class="col-price"></td>
                    <td class="col-disc2"></td>
                    <td class="col-pwgst"></td>
                    <td class="col-taxrate"></td>
                    <td class="col-amt">
                        <div style="font-weight: bold;"><?= format_inr_amount($totalTaxable, false, 0) ?></div>
                        
                        <?php if ($isIntraState): ?>
                            <?php foreach ($taxGroups as $grp): ?>
                                <div class="tax-split-line" style="margin-top: 4px;">
                                    <span>CGST @ <?= (float)$grp['cgst_rate'] ?>%</span>
                                    <span><?= format_inr_amount($grp['cgst'], false, 2) ?></span>
                                </div>
                                <div class="tax-split-line">
                                    <span>SGST @ <?= (float)$grp['sgst_rate'] ?>%</span>
                                    <span><?= format_inr_amount($grp['sgst'], false, 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($taxGroups as $grp): ?>
                                <div class="tax-split-line" style="margin-top: 4px;">
                                    <span>IGST @ <?= (float)$grp['igst_rate'] ?>%</span>
                                    <span><?= format_inr_amount($grp['igst'], false, 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 5. Tax Summary & Grand Total (Below Line Items) -->
        <div class="tax-summary-container">
            <div class="tax-summary-left">
                <table class="tax-mini-table">
                    <thead>
                        <tr>
                            <th>Taxable Amt</th>
                            <?php if ($isIntraState): ?>
                                <th>CGST</th>
                                <th>SGST</th>
                            <?php else: ?>
                                <th colspan="2">IGST</th>
                            <?php endif; ?>
                            <th>Total GST</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= format_inr_amount($totalTaxable, false, 0) ?></td>
                            <?php if ($isIntraState): ?>
                                <td><?= format_inr_amount($totalCgst, false, 2) ?></td>
                                <td><?= format_inr_amount($totalSgst, false, 2) ?></td>
                            <?php else: ?>
                                <td colspan="2"><?= format_inr_amount($totalIgst, false, 2) ?></td>
                            <?php endif; ?>
                            <td class="bold"><?= format_inr_amount($totalGst, false, ($totalGst == floor($totalGst) ? 0 : (round($totalGst, 1) == $totalGst ? 1 : 2))) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="tax-summary-right">
                <div class="roundup-row">
                    <span>Roundup</span>
                    <span><?= e($roundOffDisplay) ?></span>
                </div>
                <div class="grand-total-row">
                    <span>Grand Total</span>
                    <span><?= format_inr_amount($grandTotal, false, 0) ?></span>
                </div>
            </div>
        </div>

        <!-- 6. Amount in Words Box -->
        <div class="words-container">
            <div class="words-title">Amount Chargeable (In Words)</div>
            <div class="words-body"><?= e($amountInWords) ?></div>
        </div>

        <!-- 7. Bank Details Band -->
        <div class="bank-band">
            <div class="bank-header-row">
                <div class="bank-header-left">Bank Details:</div>
                <div class="bank-header-center">UPI Payment QR Code</div>
            </div>
            <div class="bank-body-row">
                <div class="bank-details-col">
                    <div>BANK NAME : <?= e(strtoupper($bank['bank_name'] ?? 'KOTAK MAHINDRA BANK')) ?></div>
                    <div>A/C NAME &nbsp;: <?= e(strtoupper($bank['account_name'] ?? ($seller['company_legal_name'] ?: 'WIS TECHNOSAVVY PRIVATE LIMITED'))) ?></div>
                    <div>A/C No. &nbsp;&nbsp;: <?= e($bank['account_number'] ?? '9881032297') ?></div>
                    <div>IFSC CODE : <?= e(strtoupper($bank['bank_ifsc'] ?? 'KKBK0001986')) ?></div>
                </div>
                <div class="bank-qr-col">
                    <?= $qrSvg ?>
                </div>
            </div>
        </div>

        <!-- 8. Terms & Conditions Band -->
        <div class="terms-band">
            <div class="terms-title">Terms & Conditions</div>
            <div class="terms-item">
                1)&nbsp;&nbsp;&nbsp;&nbsp;Payment Condition&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: On credit of <?= (int)($quote['payment_days'] ?? 0) ?> days.
            </div>
            <?php if (!empty($quote['quote_terms']) && !str_contains($quote['quote_terms'], 'On credit of')): ?>
                <div style="margin-top: 3px; white-space: pre-line;"><?= e($quote['quote_terms']) ?></div>
            <?php endif; ?>
        </div>

        <!-- 9. Signatory Footer -->
        <div class="signatory-footer">
            <div class="signatory-inner">
                <div class="stamp-signature-area">
                    <?php if (!empty($seller['company_stamp_url'])): ?>
                        <img src="<?= e($seller['company_stamp_url']) ?>" alt="Authorized Stamp">
                    <?php else: ?>
                        <!-- Built-in authentic WIS TECHNOSAVVY Round Stamp & Signature Vector -->
                        <svg viewBox="0 0 160 90" xmlns="http://www.w3.org/2000/svg">
                            <g transform="translate(85, 45)">
                                <circle cx="0" cy="0" r="38" fill="none" stroke="#253b80" stroke-width="2.5" stroke-dasharray="7,1"/>
                                <circle cx="0" cy="0" r="34" fill="none" stroke="#253b80" stroke-width="1.2"/>
                                <circle cx="0" cy="0" r="23" fill="none" stroke="#253b80" stroke-width="1.2"/>
                                <path id="quote-stamp-arc-top" d="M -28 0 A 28 28 0 0 1 28 0" fill="none"/>
                                <path id="quote-stamp-arc-bot" d="M 28 0 A 28 28 0 0 1 -28 0" fill="none"/>
                                <text font-family="Arial, Helvetica, sans-serif" font-size="6.8" font-weight="bold" fill="#253b80" letter-spacing="1">
                                    <textPath href="#quote-stamp-arc-top" startOffset="50%" text-anchor="middle">WIS TECHNOSAVVY PVT. LTD.</textPath>
                                </text>
                                <text font-family="Arial, Helvetica, sans-serif" font-size="7.5" font-weight="bold" fill="#253b80">
                                    <textPath href="#quote-stamp-arc-bot" startOffset="50%" text-anchor="middle">&#9733; PUNE &#9733;</textPath>
                                </text>
                                <text x="0" y="3" font-family="Arial, Helvetica, sans-serif" font-size="8.5" font-weight="bold" fill="#253b80" text-anchor="middle">PUNE</text>
                            </g>
                            <path d="M 20 65 Q 40 10 50 45 T 70 30 Q 95 15 120 40 T 150 25" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round"/>
                            <path d="M 45 40 Q 55 5 65 35 T 85 25" fill="none" stroke="#1d4ed8" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="signatory-company">For <?= e($seller['company_name']) ?></div>
                <div class="signatory-title">Authorized Signatory</div>
            </div>
        </div>

    </div>

</body>
</html>
