<?php
/**
 * WTSBill ERP — Tax Invoice Printable Document Template (A4 Portrait)
 * Pixel-accurate implementation matching WIS TECHNOSAVVY PVT LTD format.
 *
 * @var array  $invoice
 * @var array  $items
 * @var array  $buyer
 * @var array  $seller
 * @var array  $bank
 * @var array  $ledger
 * @var array  $taxSummary
 * @var string $amountInWords
 * @var string $qrSvg
 * @var string $copyLabel
 * @var string $copyKey
 * @var bool   $isIntraState
 */

// Helper closure to output blank (not "0" or "null") for empty optional fields
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
    <title>Tax Invoice - <?= e($invoice['invoice_number'] ?? 'INV') ?></title>
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

        /* Screen Toolbar (Hidden on Print) */
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

        .copy-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
        }

        .copy-selector select {
            background: #0f172a;
            color: #ffffff;
            border: 1px solid #475569;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        /* Printable Invoice Container */
        .invoice-wrapper {
            width: 210mm;
            margin: 0 auto 30px auto;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .invoice-box {
            border: 1px solid #000000;
            width: 100%;
            min-height: calc(297mm - 16mm);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #ffffff;
        }

        /* Table Resets & Typography */
        table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }

        th, td {
            padding: 2.5px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .border-bottom {
            border-bottom: 1px solid #000000;
        }

        .border-top {
            border-top: 1px solid #000000;
        }

        .border-right {
            border-right: 1px solid #000000;
        }

        .border-left {
            border-left: 1px solid #000000;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .bold {
            font-weight: bold;
        }

        .uppercase {
            text-transform: uppercase;
        }

        /* Top Strip */
        .top-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 8px;
            font-size: 8pt;
            border-bottom: 1px solid #000000;
        }

        .top-strip-left {
            width: 38%;
            line-height: 1.35;
        }

        .top-strip-center {
            width: 24%;
            text-align: center;
        }

        .top-strip-title {
            font-size: 11pt;
            font-weight: bold;
            text-decoration: underline;
            letter-spacing: 0.5px;
        }

        .top-strip-right {
            width: 38%;
            text-align: right;
            font-style: italic;
            font-size: 8pt;
        }

        /* Seller Header */
        .seller-header {
            display: flex;
            align-items: center;
            padding: 6px 12px;
            border-bottom: 1px solid #000000;
            position: relative;
        }

        .seller-logo {
            width: 80px;
            text-align: left;
            flex-shrink: 0;
        }

        .seller-logo img, .seller-logo svg {
            max-width: 65px;
            max-height: 52px;
            display: block;
        }

        .seller-details {
            flex-grow: 1;
            text-align: center;
            padding-right: 80px; /* Balance logo offset */
        }

        .seller-name {
            font-size: 12.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .seller-address {
            font-size: 7.8pt;
            line-height: 1.25;
            color: #111111;
        }

        /* 3-Column Meta Section */
        .meta-section {
            border-bottom: 1px solid #000000;
        }

        .meta-table td {
            padding: 3px 6px;
            font-size: 8pt;
            line-height: 1.3;
        }

        .meta-col-buyer {
            width: 44%;
            border-right: 1px solid #000000;
        }

        .meta-col-mid {
            width: 28%;
            border-right: 1px solid #000000;
        }

        .meta-col-right {
            width: 28%;
        }

        .buyer-label {
            font-size: 7.5pt;
            color: #333333;
            margin-bottom: 2px;
        }

        .buyer-name {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .kv-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5px;
        }

        .kv-label {
            color: #111111;
            white-space: nowrap;
        }

        .kv-val {
            font-weight: normal;
            text-align: right;
            word-break: break-word;
        }

        .kv-val.bold {
            font-weight: bold;
        }

        /* Ledger & Bank Section */
        .ledger-bank-section {
            border-bottom: 1px solid #000000;
            display: flex;
        }

        .ledger-bank-left {
            width: 72%;
            border-right: 1px solid #000000;
            padding: 4px 6px;
        }

        .ledger-summary-table {
            width: 100%;
            margin-bottom: 4px;
        }

        .ledger-summary-table td {
            padding: 1px 4px;
            font-size: 8pt;
        }

        .bank-details-box {
            border-top: 1px dashed #666666;
            padding-top: 3px;
            font-size: 8pt;
            line-height: 1.35;
        }

        .bank-details-box .bold {
            font-size: 8pt;
        }

        .qr-section-right {
            width: 28%;
            text-align: center;
            padding: 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .qr-title {
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .qr-container svg {
            display: block;
            margin: 0 auto;
        }

        /* Line Items Table */
        .items-section {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            border-bottom: 1px solid #000000;
            font-size: 8pt;
            font-weight: bold;
            padding: 4px 2px;
            background: #ffffff;
            text-align: center;
        }

        .items-table td {
            font-size: 8pt;
            padding: 3px 3px;
            border-right: 1px solid #000000;
            vertical-align: top;
        }

        .items-table td:last-child {
            border-right: none;
        }

        .col-sn { width: 4%; text-align: center; }
        .col-desc { width: 32%; text-align: left; }
        .col-hsn { width: 8%; text-align: center; }
        .col-gst { width: 5%; text-align: center; }
        .col-qty { width: 7%; text-align: center; font-weight: bold; }
        .col-uqc { width: 5%; text-align: center; }
        .col-disc1 { width: 5%; text-align: right; }
        .col-price { width: 8%; text-align: right; }
        .col-disc2 { width: 5%; text-align: right; }
        .col-pwgst { width: 8%; text-align: right; }
        .col-taxrate { width: 8%; text-align: right; }
        .col-amt { width: 9%; text-align: right; font-weight: bold; }

        .item-title {
            font-weight: bold;
            text-transform: uppercase;
        }

        .item-desc {
            font-size: 7.5pt;
            color: #222222;
            margin-top: 1px;
        }

        .item-id {
            font-size: 7.5pt;
            margin-top: 1px;
        }

        .item-tax-split {
            margin-top: 4px;
            text-align: right;
            font-weight: bold;
            font-size: 7.8pt;
            line-height: 1.25;
        }

        .amt-subrows {
            margin-top: 4px;
            text-align: right;
            font-weight: bold;
            font-size: 7.8pt;
            line-height: 1.25;
        }

        /* Spacer row to ensure footer sits at bottom */
        .spacer-row td {
            height: 100%;
            min-height: 180px;
            border-bottom: 1px solid #000000;
        }

        /* Tax Summary & Grand Total Box */
        .tax-summary-box {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            display: flex;
            background: #ffffff;
        }

        .tax-summary-left {
            width: 72%;
            border-right: 1px solid #000000;
        }

        .tax-summary-subtable {
            width: 100%;
        }

        .tax-summary-subtable th {
            font-size: 8pt;
            font-weight: bold;
            border-bottom: 1px solid #000000;
            padding: 3px 6px;
            text-align: center;
        }

        .tax-summary-subtable td {
            font-size: 8pt;
            padding: 3px 6px;
            text-align: center;
        }

        .tax-summary-right {
            width: 28%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 2.5px 8px;
            font-size: 8pt;
        }

        .totals-row.grand-total {
            border-top: 1px solid #000000;
            font-size: 9.5pt;
            font-weight: bold;
        }

        /* Amount in Words */
        .words-section {
            padding: 4px 8px;
            border-bottom: 1px solid #000000;
            font-size: 8pt;
            background: #ffffff;
        }

        .words-title {
            text-decoration: underline;
            font-weight: normal;
            font-size: 7.5pt;
            margin-bottom: 1px;
        }

        .words-content {
            font-weight: bold;
            font-size: 8.5pt;
        }

        /* Footer */
        .invoice-footer {
            display: flex;
            border-bottom: 1px solid #000000;
            background: #ffffff;
        }

        .footer-left {
            width: 60%;
            border-right: 1px solid #000000;
            padding: 6px 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 95px;
        }

        .terms-title {
            font-weight: bold;
            font-size: 7.5pt;
            margin-bottom: 2px;
        }

        .terms-body {
            font-size: 6.8pt;
            line-height: 1.25;
            color: #222222;
            white-space: pre-line;
        }

        .buyer-signature-line {
            font-size: 8pt;
            font-weight: bold;
            margin-top: 15px;
        }

        .footer-right {
            width: 40%;
            padding: 6px 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-end;
            text-align: right;
            min-height: 95px;
        }

        .signatory-company {
            font-size: 8.5pt;
            font-weight: bold;
        }

        .stamp-signature-area {
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            position: relative;
        }

        .stamp-signature-area img, .stamp-signature-area svg {
            max-height: 52px;
            max-width: 140px;
            display: block;
        }

        .signatory-title {
            font-size: 8pt;
            font-weight: bold;
        }

        .system-generated-bar {
            text-align: center;
            font-size: 7.5pt;
            padding: 3px 0;
            color: #111111;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
            }
            .no-print {
                display: none !important;
            }
            .invoice-wrapper {
                width: 100% !important;
                margin: 0 !important;
                box-shadow: none !important;
            }
            .invoice-box {
                border: 1px solid #000000 !important;
                min-height: calc(297mm - 16mm) !important;
                height: 100% !important;
            }
        }
    </style>
</head>
<body>

    <!-- On-screen Floating Control Bar (Hidden when printing or exporting) -->
    <div class="screen-toolbar no-print">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/sales/invoices">&larr; Back to Invoices</a>
            <span style="font-weight: 600; font-size: 13px;">WTSBill ERP — Tax Invoice Preview</span>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="copy-selector">
                <label for="copy-type-select">Invoice Copy:</label>
                <select id="copy-type-select" onchange="window.location.href='?copy=' + this.value">
                    <option value="original" <?= $copyKey === 'original' ? 'selected' : '' ?>>Original for Recipient</option>
                    <option value="duplicate" <?= $copyKey === 'duplicate' ? 'selected' : '' ?>>Duplicate for Transporter</option>
                    <option value="triplicate" <?= $copyKey === 'triplicate' ? 'selected' : '' ?>>Triplicate for Supplier</option>
                </select>
            </div>
            <button type="button" class="btn-print" onclick="window.print()">
                <span>🖨️</span> Print Invoice
            </button>
        </div>
    </div>

    <!-- Main Invoice Document Wrapper -->
    <div class="invoice-wrapper">
        <div class="invoice-box">
            <div>
                <!-- 1. Top Strip -->
                <div class="top-strip">
                    <div class="top-strip-left">
                        <div><strong>GSTIN:</strong> <?= e($seller['gstin'] ?? '') ?></div>
                        <div><strong>MSME No.:</strong> <?= e($seller['msme_no'] ?? '') ?></div>
                        <div><strong>PAN:</strong> <?= e($seller['pan'] ?? '') ?></div>
                    </div>
                    <div class="top-strip-center">
                        <div class="top-strip-title">TAX INVOICE</div>
                    </div>
                    <div class="top-strip-right">
                        <span id="copy-label-text"><?= e($copyLabel) ?> (Page 1/1)</span>
                    </div>
                </div>

                <!-- 2. Seller Header -->
                <div class="seller-header">
                    <div class="seller-logo">
                        <?php if (!empty($seller['logo_url'])): ?>
                            <img src="<?= e($seller['logo_url']) ?>" alt="Logo">
                        <?php else: ?>
                            <!-- Built-in authentic WTS Vector Logo -->
                            <svg viewBox="0 0 100 80" xmlns="http://www.w3.org/2000/svg">
                                <ellipse cx="50" cy="40" rx="46" ry="34" fill="#0088cc"/>
                                <text x="50" y="50" font-family="Arial, Helvetica, sans-serif" font-weight="900" font-style="italic" font-size="34" fill="#ffffff" text-anchor="middle">wts</text>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="seller-details">
                        <div class="seller-name"><?= e($seller['company_name']) ?></div>
                        <div class="seller-address">
                            <?php if (!empty($seller['address_line1'])): ?>
                                <div><?= e($seller['address_line1']) ?><?= !empty($seller['address_line2']) ? ', ' . e($seller['address_line2']) : '' ?></div>
                            <?php endif; ?>
                            <div><?= e($seller['city']) ?><?= !empty($seller['state']) ? ', ' . e($seller['state']) : '' ?><?= !empty($seller['pincode']) ? ' ' . e($seller['pincode']) : '' ?></div>
                            <div>
                                <?= !empty($seller['phone']) ? e($seller['phone']) : '' ?>
                                <?= (!empty($seller['phone']) && !empty($seller['email'])) ? ' &bull; ' : '' ?>
                                <?= !empty($seller['email']) ? e($seller['email']) : '' ?>
                            </div>
                            <?php if (!empty($seller['website'])): ?>
                                <div><?= e($seller['website']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 3. Buyer & Invoice Meta (3-Column Row) -->
                <div class="meta-section">
                    <table class="meta-table">
                        <tr>
                            <!-- Column 1: Buyer Details -->
                            <td class="meta-col-buyer">
                                <div class="buyer-label">Buyer</div>
                                <div class="buyer-name"><?= e($buyer['name']) ?></div>
                                <?php if (!empty($buyer['address'])): ?>
                                    <div><?= e($buyer['address']) ?></div>
                                <?php endif; ?>
                                <div><?= e($buyer['city']) ?><?= (!empty($buyer['city']) && !empty($buyer['state'])) ? ' - ' : '' ?><?= e($buyer['state']) ?></div>
                                <div><strong>PIN :</strong><?= e($buyer['pincode']) ?></div>
                                <div><strong>GSTIN :</strong><?= e($buyer['gstin']) ?></div>
                                <div><strong>GST Type :</strong><?= e($buyer['gst_type']) ?></div>
                                <div><strong>POS :</strong> <?= e($buyer['pos_state']) ?> [State Code : <?= e($buyer['pos_code']) ?>]</div>
                                <div><strong>Contact Person :</strong> <?= e($buyer['contact_person'] ?: $buyer['name']) ?></div>
                            </td>

                            <!-- Column 2: Invoice Metadata Middle -->
                            <td class="meta-col-mid">
                                <div class="kv-row">
                                    <span class="kv-label">Invoice No.</span>
                                    <span class="kv-val bold">: <?= e($invoice['invoice_number'] ?? '') ?></span>
                                </div>
                                <div class="kv-row">
                                    <span class="kv-label">P.O. No.</span>
                                    <span class="kv-val">: <?= e($valOrBlank($invoice['po_no'] ?? null)) ?></span>
                                </div>
                                <div class="kv-row">
                                    <span class="kv-label">Challan No.</span>
                                    <span class="kv-val">: <?= e($valOrBlank($invoice['challan_no'] ?? null)) ?></span>
                                </div>
                                <div class="kv-row">
                                    <span class="kv-label">Bill Credit</span>
                                    <span class="kv-val">: <?= (int)($invoice['bill_credit_days'] ?? 0) ?> Days</span>
                                </div>
                                <div class="kv-row">
                                    <span class="kv-label">Delivery By</span>
                                    <span class="kv-val">: <?= e($valOrBlank($invoice['delivery_by'] ?? null)) ?></span>
                                </div>
                                <div class="kv-row">
                                    <span class="kv-label">Receipt Mode</span>
                                    <span class="kv-val">: <?= e($valOrBlank($invoice['receipt_mode'] ?? 'Credit')) ?></span>
                                </div>
                            </td>

                            <!-- Column 3: Invoice Dates & Status Right -->
                            <td class="meta-col-right">
                                <div class="kv-row" style="font-size: 8.5pt;">
                                    <span class="kv-label bold">Invoice Date :</span>
                                    <span class="kv-val bold"><?= e($formatDateOrBlank($invoice['invoice_date'] ?? null)) ?></span>
                                </div>
                                <div class="kv-row">
                                    <span class="kv-label">P.O. Date</span>
                                    <span class="kv-val">: <?= e($formatDateOrBlank($invoice['po_date'] ?? null)) ?></span>
                                </div>
                                <div class="kv-row">
                                    <span class="kv-label">Bill Pay Status</span>
                                    <span class="kv-val">: <?= e($invoice['pay_status'] ?? 'Unpaid') ?></span>
                                </div>
                                <div class="kv-row">
                                    <span class="kv-label">Due Date</span>
                                    <span class="kv-val">: <?= e($formatDateOrBlank($invoice['due_date'] ?? null)) ?></span>
                                </div>
                                <div class="kv-row">
                                    <span class="kv-label">LR No.</span>
                                    <span class="kv-val">: <?= e($valOrBlank($invoice['lr_no'] ?? null)) ?></span>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 4. Ledger Summary & Bank Details (Left) + UPI Payment QR (Right) -->
                <div class="ledger-bank-section">
                    <div class="ledger-bank-left">
                        <div class="bold" style="margin-bottom: 2px;">Ledger Summary:</div>
                        <table class="ledger-summary-table">
                            <tr>
                                <td style="width: 50%;">Previous Balance</td>
                                <td style="width: 50%; text-align: right;">
                                    <?= e($ledger['prev_label']) ?> <?= e($ledger['prev_amount']) ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Add: Invoice Amount</td>
                                <td style="text-align: right;"><?= e($ledger['invoice_amount_formatted']) ?></td>
                            </tr>
                            <tr>
                                <td class="bold">New Balance</td>
                                <td style="text-align: right;" class="bold">
                                    <?= e($ledger['new_label']) ?> <?= e($ledger['new_amount']) ?>
                                </td>
                            </tr>
                        </table>

                        <div class="bank-details-box">
                            <div class="bold">Bank Details:</div>
                            <div>BANK NAME : <?= e(strtoupper($bank['bank_name'] ?? '')) ?></div>
                            <div>A/C NAME &nbsp;: <?= e(strtoupper($bank['account_name'] ?? '')) ?></div>
                            <div>A/C No. &nbsp;&nbsp;: <?= e($bank['account_number'] ?? '') ?></div>
                            <div>IFSC CODE : <?= e(strtoupper($bank['ifsc'] ?? '')) ?></div>
                        </div>
                    </div>

                    <div class="qr-section-right">
                        <div class="qr-title">UPI Payment QR Code</div>
                        <div class="qr-container">
                            <?= $qrSvg ?>
                        </div>
                    </div>
                </div>

                <!-- 5. Line Item Table -->
                <div class="items-section">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th class="col-sn">S/N</th>
                                <th class="col-desc">Description Of Goods / Service</th>
                                <th class="col-hsn">HSN/SAC</th>
                                <th class="col-gst">GST</th>
                                <th class="col-qty">Billed<br>Quantity</th>
                                <th class="col-uqc">UQC</th>
                                <th class="col-disc1">Disc</th>
                                <th class="col-price">Price</th>
                                <th class="col-disc2">Disc</th>
                                <th class="col-pwgst">Price With<br>GST</th>
                                <th class="col-taxrate">Taxable<br>Rate</th>
                                <th class="col-amt">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $idx => $it): ?>
                                <tr>
                                    <td class="col-sn"><?= $idx + 1 ?>)</td>
                                    <td class="col-desc">
                                        <div class="item-title"><?= e($it['item_name']) ?></div>
                                        <?php if (!empty($it['description'])): ?>
                                            <div class="item-desc"><?= e($it['description']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($it['item_id_code'])): ?>
                                            <div class="item-id">Item ID: <?= e($it['item_id_code']) ?></div>
                                        <?php endif; ?>

                                        <!-- Tax Split Under Item Description -->
                                        <div class="item-tax-split">
                                            <?php if ($isIntraState): ?>
                                                <div>CGST @ <?= (float)$it['cgst_rate'] ?>%</div>
                                                <div>SGST @ <?= (float)$it['sgst_rate'] ?>%</div>
                                            <?php else: ?>
                                                <div>IGST @ <?= (float)$it['igst_rate'] ?>%</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="col-hsn"><?= e($valOrBlank($it['hsn_sac'] ?? '')) ?></td>
                                    <td class="col-gst"><?= (float)$it['gst_rate'] ?>%</td>
                                    <td class="col-qty">
                                        <div><?= (float)$it['quantity'] ?></div>
                                        <div style="margin-top: 14px; font-weight: normal;"><?= (float)$it['quantity'] ?></div>
                                    </td>
                                    <td class="col-uqc"><?= e(strtoupper($it['unit'] ?: 'NOS')) ?></td>
                                    <td class="col-disc1"><?= (float)($it['discount_rate'] ?? 0) > 0 ? (float)$it['discount_rate'] . '%' : '' ?></td>
                                    <td class="col-price"><?= format_inr_amount($it['unit_price'], false, 0) ?></td>
                                    <td class="col-disc2"><?= (float)($it['discount_amount'] ?? 0) > 0 ? format_inr_amount($it['discount_amount'], false, 0) : '' ?></td>
                                    <td class="col-pwgst"><?= format_inr_amount($it['price_with_gst'], false, 0) ?></td>
                                    <td class="col-taxrate"><?= format_inr_amount($it['taxable_rate'], false, 0) ?></td>
                                    <td class="col-amt">
                                        <div><?= format_inr_amount($it['taxable_value'], false, 0) ?></div>
                                        <div class="amt-subrows">
                                            <div><?= format_inr_amount($it['taxable_value'], false, 0) ?></div>
                                            <?php if ($isIntraState): ?>
                                                <div><?= format_inr_amount($it['cgst_amount'], false, 0) ?></div>
                                                <div><?= format_inr_amount($it['sgst_amount'], false, 0) ?></div>
                                            <?php else: ?>
                                                <div><?= format_inr_amount($it['igst_amount'], false, 0) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Visual Spacer Row ensuring columns stretch downwards -->
                            <tr class="spacer-row">
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
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <!-- 6. Tax Summary & Totals Row -->
                <div class="tax-summary-box">
                    <div class="tax-summary-left">
                        <table class="tax-summary-subtable">
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
                                    <td><?= format_inr_amount($taxSummary['taxable_amt'], false, 0) ?></td>
                                    <?php if ($isIntraState): ?>
                                        <td><?= format_inr_amount($taxSummary['cgst_amt'], false, 0) ?></td>
                                        <td><?= format_inr_amount($taxSummary['sgst_amt'], false, 0) ?></td>
                                    <?php else: ?>
                                        <td colspan="2"><?= format_inr_amount($taxSummary['igst_amt'], false, 0) ?></td>
                                    <?php endif; ?>
                                    <td class="bold"><?= format_inr_amount($taxSummary['total_gst'], false, 0) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="tax-summary-right">
                        <div class="totals-row">
                            <span>Roundup</span>
                            <span><?= e($taxSummary['round_off_display']) ?></span>
                        </div>
                        <div class="totals-row grand-total">
                            <span>Grand Total</span>
                            <span><?= format_inr_amount($taxSummary['grand_total'], false, 0) ?></span>
                        </div>
                    </div>
                </div>

                <!-- 7. Amount Chargeable In Words -->
                <div class="words-section">
                    <div class="words-title">Amount Chargeable (In Words)</div>
                    <div class="words-content"><?= e($amountInWords) ?></div>
                </div>

                <!-- 8. Footer (Terms & Conditions, Signature, Seal) -->
                <div class="invoice-footer">
                    <div class="footer-left">
                        <div>
                            <div class="terms-title">Terms & Condition:</div>
                            <div class="terms-body"><?= e($seller['terms'] ?? "Warranty And Claims: All warranties/Claims of the products will be covered by respective manufacture/service centers as per their terms and conditions.\nRate Difference Pay on Overdue Bill: 18 % on value of invoice.") ?></div>
                        </div>
                        <div class="buyer-signature-line">Buyer Seal And Signature</div>
                    </div>

                    <div class="footer-right">
                        <div class="signatory-company">For <?= e($seller['company_legal_name'] ?: $seller['company_name']) ?></div>
                        <div class="stamp-signature-area">
                            <?php if (!empty($seller['stamp_url'])): ?>
                                <img src="<?= e($seller['stamp_url']) ?>" alt="Authorized Stamp">
                            <?php else: ?>
                                <!-- Built-in authentic WIS TECHNOSAVVY Round Stamp & Signature Vector -->
                                <svg viewBox="0 0 160 90" xmlns="http://www.w3.org/2000/svg">
                                    <g transform="translate(85, 45)">
                                        <!-- Outer double circle -->
                                        <circle cx="0" cy="0" r="38" fill="none" stroke="#253b80" stroke-width="2.5" stroke-dasharray="7,1"/>
                                        <circle cx="0" cy="0" r="34" fill="none" stroke="#253b80" stroke-width="1.2"/>
                                        <circle cx="0" cy="0" r="23" fill="none" stroke="#253b80" stroke-width="1.2"/>
                                        <!-- Text on circle path -->
                                        <path id="stamp-arc-top" d="M -28 0 A 28 28 0 0 1 28 0" fill="none"/>
                                        <path id="stamp-arc-bot" d="M 28 0 A 28 28 0 0 1 -28 0" fill="none"/>
                                        <text font-family="Arial, Helvetica, sans-serif" font-size="6.8" font-weight="bold" fill="#253b80" letter-spacing="1">
                                            <textPath href="#stamp-arc-top" startOffset="50%" text-anchor="middle">WIS TECHNOSAVVY PVT. LTD.</textPath>
                                        </text>
                                        <text font-family="Arial, Helvetica, sans-serif" font-size="7.5" font-weight="bold" fill="#253b80">
                                            <textPath href="#stamp-arc-bot" startOffset="50%" text-anchor="middle">&#9733; PUNE &#9733;</textPath>
                                        </text>
                                        <text x="0" y="3" font-family="Arial, Helvetica, sans-serif" font-size="8.5" font-weight="bold" fill="#253b80" text-anchor="middle">PUNE</text>
                                    </g>
                                    <!-- Signature overlay path across the stamp -->
                                    <path d="M 20 65 Q 40 10 50 45 T 70 30 Q 95 15 120 40 T 150 25" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M 45 40 Q 55 5 65 35 T 85 25" fill="none" stroke="#1d4ed8" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="signatory-title">Authorized Signatory</div>
                    </div>
                </div>

                <!-- 9. Bottom Computer Generated Note -->
                <div class="system-generated-bar">
                    This is a Computer Generated TAX INVOICE
                </div>
            </div>
        </div>
    </div>

</body>
</html>
