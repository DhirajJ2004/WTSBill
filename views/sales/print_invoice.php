<?php
require_once __DIR__ . '/../db_helper.php';
use Illuminate\Database\Capsule\Manager as DB;

$invId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$copyType = $_GET['copy'] ?? 'original'; // original, duplicate, triplicate

$authData = \App\Services\DocumentPrintService::authorizeAndFetch('invoices', $invId, 'Invoice', url('/invoices'));
$invoice = $authData['document'];
$company = $authData['company'];
$companyDetails = $authData['companyDetails'];
$bankDetails = $authData['bankDetails'];
$logoDataUri = $authData['logoUri'];

$customer = DB::table('customers')->where('id', $invoice->customer_id)->first();
$items = DB::table('invoice_items')->where('invoice_id', $invoice->id)->get();

// Official Company Details from authorized entity
$companyName = $company->name ?? 'Company';
$companyAddress1 = $company->address_line1 ?? '';
$companyAddress2 = $company->address_line2 ?? '';
$companyCity = trim(($company->city ?? '') . ', ' . ($company->state ?? '') . ' ' . ($company->pincode ?? ''));
$companyPhone = $company->phone ?? '';
$companyEmail = $company->email ?? '';
$companyWebsite = $company->website ?? '';
$companyGstin = $company->gstin ?? '';
$companyMsme = $company->msme_registration_no ?? '';
$companyPan = $company->pan ?? '';

// Bank Details
$bank = DB::table('bank_accounts')->where('company_id', $company->id ?? 0)->first();
$bankName = $bank->bank_name ?? 'Primary Bank';
$bankAccountName = $bank->account_name ?? $companyName;
$bankAccountNo = $bank->account_number ?? '';
$bankIfsc = $bank->ifsc_code ?? '';

// Logo
$logoPath = __DIR__ . '/../../public/assets/img/logo.png';
$logoDataUri = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : url('/assets/img/logo.png');

$invNumber = $invoice->invoice_number ?? 'INV-000000';
$invDate = date('d-m-Y', strtotime($invoice->invoice_date ?? date('Y-m-d')));
$dueDate = date('d-m-Y', strtotime($invoice->due_date ?? date('Y-m-d')));

$customerName = $customer->name ?? 'Customer';
$customerAddress = htmlspecialchars($customer->address_line1 ?? '') . (!empty($customer->address_line2) ? '<br>' . htmlspecialchars($customer->address_line2) : '') . '<br>' . htmlspecialchars($customer->city ?? '') . ' - ' . htmlspecialchars($customer->state ?? '') . '<br>PIN :' . htmlspecialchars($customer->pincode ?? '');
$customerGstin = $customer->gstin ?? 'URP';
$customerGstType = !empty($customer->gstin) ? 'Regular' : 'Unregistered';
$placeOfSupply = ($customer->state ?? 'Maharashtra') . (!empty($customer->state_code) ? ' [State Code : ' . $customer->state_code . ']' : '');
$contactPerson = $customer->contact_person ?? $customerName;

$taxableAmt = floatval($invoice->sub_total ?? 0);
$cgst = floatval($invoice->cgst_amount ?? 0);
$sgst = floatval($invoice->sgst_amount ?? 0);
$igst = floatval($invoice->igst_amount ?? 0);
$totalGst = $cgst + $sgst + $igst;
$roundup = floatval($invoice->round_off_amount ?? 0);
$grandTotal = floatval($invoice->grand_total ?? ($taxableAmt + $totalGst + $roundup));

$currentBalance = floatval($customer->current_balance ?? $customer->outstanding_balance ?? 0);
$oldBalance = max(0, $currentBalance - $grandTotal);
$newBalance = $currentBalance;

$displayItems = [];
$totalBilledQty = 0;
$idx = 1;
foreach ($items as $it) {
    $rate = floatval($it->unit_price ?? 0);
    $qty = floatval($it->quantity ?? 0);
    $totalBilledQty += $qty;
    $disc = floatval($it->discount_amount ?? 0);
    $lineTaxable = floatval($it->taxable_value ?? ($rate * $qty - $disc));
    $taxRate = floatval($it->tax_rate ?? 0);
    $lineGst = floatval($it->cgst_amount ?? 0) + floatval($it->sgst_amount ?? 0) + floatval($it->igst_amount ?? 0);
    $lineTotal = $lineTaxable + $lineGst;
    $priceWithGst = $qty > 0 ? ($lineTotal / $qty) : $rate;

    $displayItems[] = [
        'sn' => $idx++ . ')',
        'category' => 'GOODS / SERVICES',
        'name' => $it->item_name ?? 'Product / Service Item',
        'desc' => $it->description ?? '',
        'hsn' => $it->hsn_sac ?? '',
        'gst_rate' => $taxRate > 0 ? $taxRate . '%' : '0%',
        'qty' => $qty,
        'uqc' => $it->unit ?? 'NOS',
        'disc1' => $disc > 0 ? number_format($disc, 2) : '',
        'price' => number_format($rate, 2),
        'disc2' => '',
        'price_with_gst' => number_format($priceWithGst, 2),
        'taxable_rate' => number_format($lineTaxable, 2),
        'amount' => number_format($lineTaxable, 2)
    ];
}

$amountInWords = getIndianWords($grandTotal);

$copyTitles = [
    'original' => 'Original for Receipient (Page 1/1)',
    'duplicate' => 'Duplicate for Supplier/Transporter (Page 1/1)',
    'triplicate' => 'Triplicate for Consignee (Page 1/1)'
];
$activeCopyTitle = $copyTitles[$copyType] ?? $copyTitles['original'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TAX INVOICE - <?= htmlspecialchars($invNumber) ?> - <?= htmlspecialchars($companyName) ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            background-color: #f1f5f9;
            color: #000000;
            font-size: 11px;
            line-height: 1.3;
            padding: 20px;
        }

        /* Printable Sheet A4 Container */
        .invoice-sheet {
            width: 100%;
            max-width: 210mm;
            min-height: 260mm;
            margin: 0 auto;
            background: #ffffff;
            border: 1.5px solid #000000;
            padding: 0;
            position: relative;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .btn-print {
            background: #0284c7;
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover {
            background: #0369a1;
        }
        .copy-select {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            font-size: 12px;
            background: white;
            font-weight: 600;
        }

        /* Top Header Triple Block */
        .top-tax-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1.5px solid #000000;
            padding: 4px 8px;
            font-size: 11px;
        }
        .tax-invoice-badge {
            font-size: 14px;
            font-weight: 900;
            letter-spacing: 0.5px;
            text-decoration: underline;
            text-align: center;
        }

        /* Company Details & Logo */
        .company-header-block {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1.5px solid #000000;
            position: relative;
        }
        .company-logo-area {
            width: 90px;
            height: 90px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
        .company-logo-area img {
            width: 82px;
            height: 82px;
            object-fit: contain;
            border-radius: 50%;
        }
        .company-info-center {
            flex: 1;
            text-align: center;
            padding-right: 90px; /* balanced center offset for logo */
        }
        .company-name-title {
            font-size: 19px;
            font-weight: 900;
            color: #000000;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .company-info-center p {
            font-size: 11.5px;
            line-height: 1.25;
            color: #000000;
        }

        /* Buyer & Meta Section Grid */
        .buyer-meta-grid {
            display: grid;
            grid-template-columns: 43% 57%;
            border-bottom: 1.5px solid #000000;
        }
        .buyer-col {
            border-right: 1.5px solid #000000;
            padding: 6px 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .buyer-title {
            font-size: 11px;
            margin-bottom: 2px;
        }
        .buyer-name {
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 2px;
        }
        .buyer-address {
            font-size: 11px;
            line-height: 1.25;
            margin-bottom: 6px;
        }
        .meta-col {
            display: flex;
            flex-direction: column;
        }
        .meta-sub-row {
            display: grid;
            grid-template-columns: 50% 50%;
            padding: 4px 6px;
            font-size: 11px;
            line-height: 1.35;
        }
        .meta-line {
            display: flex;
            justify-content: space-between;
            padding-right: 6px;
        }
        .meta-line span:first-child {
            font-weight: normal;
        }
        .meta-line span:last-child {
            font-weight: bold;
        }

        /* Last Transaction Box */
        .transaction-box {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            padding: 4px 6px;
            font-size: 10.5px;
            line-height: 1.35;
        }
        .trans-calc-line {
            display: flex;
            justify-content: space-between;
        }

        /* Bank Details & QR Grid */
        .bank-qr-row {
            display: flex;
            padding: 4px 6px;
            justify-content: space-between;
            align-items: center;
        }
        .bank-details-block {
            font-size: 10px;
            line-height: 1.35;
        }
        .qr-code-block {
            text-align: center;
            padding-right: 4px;
        }
        .qr-code-title {
            font-size: 9.5px;
            font-weight: bold;
            margin-bottom: 2px;
            text-decoration: underline;
        }
        .qr-code-img {
            width: 78px;
            height: 78px;
            object-fit: contain;
            border: 1px solid #000000;
            display: block;
            margin: 0 auto;
        }

        /* Items Main Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            empty-cells: show;
            font-size: 10px;
        }
        .items-table thead th {
            border-bottom: 1.5px solid #000000;
            border-right: 1px solid #000000 !important;
            padding: 4px 2px;
            font-weight: bold;
            text-align: center;
            background: #ffffff;
            line-height: 1.15;
            box-sizing: border-box;
        }
        .items-table thead th:last-child {
            border-right: none !important;
        }
        .items-table tbody td {
            border-right: 1px solid #000000 !important;
            padding: 3px 4px;
            vertical-align: top;
            font-size: 10px;
            box-sizing: border-box;
        }
        .items-table tbody td:last-child {
            border-right: none !important;
        }

        /* Heights for empty space matching classical ERP printouts without page overflow */
        .item-data-row {
            min-height: 38px;
        }
        .item-subtotal-row td {
            padding: 2px 4px;
        }
        .tax-breakdown-row {
            height: 60px;
        }

        /* Summary Footers Table */
        .tax-summary-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1.5px solid #000000;
            border-bottom: 1.5px solid #000000;
            font-size: 10.5px;
            table-layout: fixed;
            empty-cells: show;
        }
        .tax-summary-table th, .tax-summary-table td {
            border-right: 1px solid #000000 !important;
            padding: 3px 6px;
            text-align: right;
            box-sizing: border-box;
        }
        .tax-summary-table th {
            text-align: center;
            font-weight: bold;
        }
        .tax-summary-table th:last-child, .tax-summary-table td:last-child {
            border-right: none !important;
        }

        .grand-total-cell {
            font-size: 13px;
            font-weight: 900;
        }

        /* Amount in words line */
        .words-line {
            border-bottom: 1px solid #000000;
            padding: 4px 6px;
            font-size: 10.5px;
        }
        .words-line strong {
            font-size: 11px;
        }

        /* Bottom Section: Terms on Left, Company Stamp on Right (Exact Attachment Layout) */
        .bottom-split-row {
            display: grid;
            grid-template-columns: 60% 40%;
            border-bottom: 1px solid #000000;
            min-height: 80px;
            box-sizing: border-box;
        }
        .bottom-terms-col {
            padding: 4px 6px;
            font-size: 8.5px;
            line-height: 1.35;
        }
        .terms-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 2px;
        }
        .terms-line {
            margin-bottom: 2px;
        }
        .bottom-stamp-col {
            padding: 4px 8px;
            text-align: right;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: flex-end;
            box-sizing: border-box;
        }
        .company-sign-title {
            font-weight: bold;
            font-size: 10.5px;
            margin-bottom: 2px;
        }
        .stamp-signature-art {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        /* Sub-Footer Bar */
        .bottom-footer-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 8px;
            font-size: 10.5px;
            box-sizing: border-box;
        }

        /* Print Media Styles */
        @page {
            size: A4 portrait;
            margin: 6mm;
        }
        @media print {
            html, body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .no-print-toolbar, .print-controls-bar, .no-print {
                display: none !important;
            }
            .pdf-sheet-wrapper {
                zoom: var(--pdf-zoom, 0.94) !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 auto !important;
                padding: 0 !important;
            }
            .invoice-sheet {
                box-shadow: none !important;
                border: 1.5px solid #000000 !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto !important;
                height: auto !important;
                margin: 0 auto !important;
                box-sizing: border-box !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

<?php
$docType = 'TAX INVOICE';
$docNumber = $invNumber;
include __DIR__ . '/../layout/print_controls.php';
?>

<!-- ======================================================================
     EXACT A4 PRINT SHEET (Matching Attached Invoice Format)
     ====================================================================== -->
<div class="pdf-sheet-wrapper">
<div class="invoice-sheet">

    <!-- 1. Top Header Row: GSTIN, PAN, TAX INVOICE & Copy Type -->
    <div class="top-tax-header">
        <div>
            <div><strong>GSTIN: <?= htmlspecialchars($companyGstin) ?></strong></div>
            <div><strong>MSME No.: <?= htmlspecialchars($companyMsme) ?></strong></div>
            <div><strong>PAN: <?= htmlspecialchars($companyPan) ?></strong></div>
        </div>
        <div class="tax-invoice-badge">
            TAX INVOICE
        </div>
        <div style="font-style: italic; font-size: 10.5px;" id="copyTypeLabel">
            <?= htmlspecialchars($activeCopyTitle) ?>
        </div>
    </div>

    <!-- 2. Company Brand & Official Registered Office Details with Logo -->
    <div class="company-header-block">
        <div class="company-logo-area">
            <img src="<?= $logoDataUri ?>" alt="<?= htmlspecialchars($companyName) ?>">
        </div>
        <div class="company-info-center">
            <div class="company-name-title"><?= htmlspecialchars($companyName) ?></div>
            <p><?= htmlspecialchars($companyAddress1) ?></p>
            <p><?= htmlspecialchars($companyAddress2) ?></p>
            <p><?= htmlspecialchars($companyCity) ?></p>
            <p><strong><?= htmlspecialchars($companyPhone) ?></strong></p>
            <p><?= htmlspecialchars($companyEmail) ?></p>
            <p><?= htmlspecialchars($companyWebsite) ?></p>
        </div>
    </div>

    <!-- 3. Buyer & Invoice Meta Section -->
    <div class="buyer-meta-grid">
        <!-- Left: Buyer Details -->
        <div class="buyer-col">
            <div>
                <div class="buyer-title">Buyer</div>
                <div class="buyer-name"><?= htmlspecialchars($customerName) ?></div>
                <div class="buyer-address"><?= $customerAddress ?></div>
                <div><strong>GSTIN :</strong><?= htmlspecialchars($customerGstin) ?></div>
                <div><strong>GST Type :</strong><?= htmlspecialchars($customerGstType) ?></div>
                <div><strong>POS :</strong> <?= htmlspecialchars($placeOfSupply) ?></div>
            </div>
            <div style="margin-top: 6px;">
                <strong>Contact Person :</strong> <?= htmlspecialchars($contactPerson) ?>
            </div>
        </div>

        <!-- Right: Invoice Info, Last Transaction, Bank & UPI QR -->
        <div class="meta-col">
            <!-- Sub-row 1: Numbers & Dates -->
            <div class="meta-sub-row">
                <div>
                    <div><strong>Invoice No. :</strong> <?= htmlspecialchars($invNumber) ?></div>
                    <div>P.O. No. :</div>
                    <div>Challan No. :</div>
                    <div><strong>Bill Pay Status :</strong> Due <?= number_format($grandTotal, 0) ?></div>
                    <div>Due Date : <?= htmlspecialchars($dueDate) ?></div>
                    <div>Delivery By :</div>
                </div>
                <div>
                    <div><strong>Invoice Date :</strong> <?= htmlspecialchars($invDate) ?></div>
                    <div>P.O. Date :</div>
                    <div>Pay. Mode : Credit</div>
                    <div>Bill Credit : 0 Days</div>
                    <div>&nbsp;</div>
                    <div>LR No. :</div>
                </div>
            </div>

            <!-- Sub-row 2: Last Transaction Calculation -->
            <div class="transaction-box" id="transSection">
                <div style="font-weight: bold; margin-bottom: 2px;">Last Transaction:</div>
                <div class="trans-calc-line">
                    <span>Old Balance</span>
                    <span>=</span>
                    <span>(Debit) <?= number_format($oldBalance, 0) ?></span>
                </div>
                <div class="trans-calc-line">
                    <span>Adding this Invoice Amount</span>
                    <span></span>
                    <span>+<?= number_format($grandTotal, 0) ?></span>
                </div>
                <div class="trans-calc-line" style="border-top: 1px solid #000; padding-top: 1px; font-weight: bold;">
                    <span>New Balance after this Invoice</span>
                    <span>=</span>
                    <span>(Debit) <?= number_format($newBalance, 0) ?></span>
                </div>
            </div>

            <!-- Sub-row 3: Bank Details & UPI QR Code -->
            <div class="bank-qr-row" id="bankQrSection">
                <div class="bank-details-block">
                    <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">Bank Details:</div>
                    <div>BANK NAME : <?= htmlspecialchars($bankName) ?></div>
                    <div>A/C NAME : <?= htmlspecialchars($bankAccountName) ?></div>
                    <div>A/C No. : <?= htmlspecialchars($bankAccountNo) ?></div>
                    <div>IFSC CODE : <?= htmlspecialchars($bankIfsc) ?></div>
                </div>
                <div class="qr-code-block">
                    <div class="qr-code-title">UPI Payment QR Code</div>
                    <!-- Dynamic UPI QR Code for Kotak Account -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&margin=2&data=<?= urlencode("upi://pay?pa={$bankAccountNo}@kotak&pn=" . urlencode($bankAccountName) . "&am={$grandTotal}&cu=INR") ?>" 
                         alt="UPI QR Code" 
                         class="qr-code-img">
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Description of Goods & Services Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 3.5%;">S/N</th>
                <th style="width: 33%;">Description Of Goods / Service</th>
                <th style="width: 9%;">HSN/SAC</th>
                <th style="width: 5%;">GST</th>
                <th style="width: 6.5%;">Billed<br>Quantity</th>
                <th style="width: 5%;">UQC</th>
                <th style="width: 4%;">Disc</th>
                <th style="width: 7%;">Price</th>
                <th style="width: 4%;">Disc</th>
                <th style="width: 7.5%;">Price<br>With GST</th>
                <th style="width: 7.5%;">Taxable<br>Rate</th>
                <th style="width: 8%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($displayItems as $item): ?>
            <tr class="item-data-row">
                <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($item['sn']) ?></td>
                <td>
                    <div style="font-weight: bold;"><?= htmlspecialchars($item['category']) ?></div>
                    <div style="padding-left: 6px;"><?= htmlspecialchars($item['name']) ?></div>
                    <div style="padding-left: 12px; color: #334155;"><?= htmlspecialchars($item['desc']) ?></div>
                </td>
                <td style="text-align: center;"><?= htmlspecialchars($item['hsn']) ?></td>
                <td style="text-align: center;"><?= htmlspecialchars($item['gst_rate']) ?></td>
                <td style="text-align: right; font-weight: bold;"><?= htmlspecialchars($item['qty']) ?></td>
                <td style="text-align: center;"><?= htmlspecialchars($item['uqc']) ?></td>
                <td style="text-align: center;"></td>
                <td style="text-align: right;"><?= htmlspecialchars($item['price']) ?></td>
                <td style="text-align: center;"></td>
                <td style="text-align: right;"><?= htmlspecialchars($item['price_with_gst']) ?></td>
                <td style="text-align: right;"><?= htmlspecialchars($item['taxable_rate']) ?></td>
                <td style="text-align: right; font-weight: bold;"><?= htmlspecialchars($item['amount']) ?></td>
            </tr>
            <?php endforeach; ?>

            <!-- Table Sub-Total Row (Borders on quantity and taxable amounts, vertical lines preserved across all columns) -->
            <tr class="item-subtotal-row">
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td style="text-align: right; font-weight: bold; border-top: 1px solid #000; border-bottom: 1px solid #000;"><?= number_format($totalBilledQty, 0) ?></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td style="text-align: right; font-weight: bold; border-top: 1px solid #000; border-bottom: 1px solid #000;"><?= number_format($taxableAmt, 2) ?></td>
                <td style="text-align: right; font-weight: bold; border-top: 1px solid #000; border-bottom: 1px solid #000;"><?= number_format($taxableAmt, 2) ?></td>
            </tr>

            <!-- Tax Rate Breakdown Inside Description Column with vertical lines preserved across all 12 columns -->
            <tr class="tax-breakdown-row">
                <td>&nbsp;</td>
                <td style="vertical-align: top; padding-top: 6px;">
                    <?php if ($igst > 0): ?>
                    <div style="display: flex; justify-content: flex-end; padding-right: 8px;">
                        <strong>IGST Output</strong>
                    </div>
                    <?php else: ?>
                    <div style="display: flex; justify-content: flex-end; padding-right: 8px; margin-bottom: 3px;">
                        <strong>CGST Output</strong>
                    </div>
                    <div style="display: flex; justify-content: flex-end; padding-right: 8px;">
                        <strong>SGST Output</strong>
                    </div>
                    <?php endif; ?>
                </td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td style="vertical-align: top; padding-top: 6px; text-align: right; font-weight: bold;">
                    <?php if ($igst > 0): ?>
                    <div><?= number_format($igst, 2) ?></div>
                    <?php else: ?>
                    <div style="margin-bottom: 3px;"><?= number_format($cgst, 2) ?></div>
                    <div><?= number_format($sgst, 2) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- 5. Tax Summary & Grand Total Table -->
    <table class="tax-summary-table">
        <thead>
            <tr>
                <th style="width: 18%;">Taxable Amt</th>
                <th style="width: 12%;"><?= $igst > 0 ? 'IGST' : 'CGST' ?></th>
                <th style="width: 12%;"><?= $igst > 0 ? '-' : 'SGST' ?></th>
                <th style="width: 14%;">Total GST</th>
                <th style="width: 24%; text-align: right;">Roundup</th>
                <th style="width: 20%; text-align: right;"><?= number_format($roundup, 2) ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;"><?= number_format($taxableAmt, 2) ?></td>
                <td style="text-align: center; font-weight: bold;"><?= number_format($igst > 0 ? $igst : $cgst, 2) ?></td>
                <td style="text-align: center; font-weight: bold;"><?= $igst > 0 ? '-' : number_format($sgst, 2) ?></td>
                <td style="text-align: center; font-weight: bold;"><?= number_format($totalGst, 2) ?></td>
                <td style="text-align: right; font-size: 13px; font-weight: 900;">Grand Total</td>
                <td style="text-align: right; font-size: 14px; font-weight: 900;">₹<?= number_format($grandTotal, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- 6. Amount in Words -->
    <div class="words-line">
        <div>Amount Chargeable (In Words)</div>
        <div style="font-weight: bold; font-size: 11px;"><?= htmlspecialchars($amountInWords) ?></div>
    </div>

    <!-- 7. Bottom Section: Terms & Conditions on Left, Company Stamp & Signature on Right (Exact Attachment Layout) -->
    <div class="bottom-split-row">
        <!-- Left: Terms & Conditions -->
        <div class="bottom-terms-col" id="termsSection">
            <div class="terms-title">Terms &amp; Condition:</div>
            <div class="terms-line">Warranty And Claims: All warranties/Claims of the products will be covered by respective manufacturer/service centers as per their terms and conditions.</div>
            <div class="terms-line">Rate Difference Pay on Overdue Bill: 18 % on value of Invoice.</div>
        </div>

        <!-- Right: Authorized Company Seal & Signature -->
        <div class="bottom-stamp-col" id="stampSection">
            <div class="company-sign-title">For <?= htmlspecialchars($companyName) ?></div>
            
            <div class="stamp-signature-art">
                <svg width="135" height="66" viewBox="0 0 135 66">
                    <!-- Circular Stamp Border -->
                    <circle cx="95" cy="33" r="29" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-dasharray="2.5,1.5"/>
                    <circle cx="95" cy="33" r="25" fill="none" stroke="#2563eb" stroke-width="1.2"/>
                    
                    <!-- Circular text -->
                    <path id="stampUpperArc" d="M 70,33 A 25,25 0 0,1 120,33" fill="none"/>
                    <text font-size="6.5" font-weight="900" fill="#2563eb" letter-spacing="0.8">
                        <textPath href="#stampUpperArc" startOffset="50%" text-anchor="middle">
                            WIS TECHNOSAVVY PVT. LTD.
                        </textPath>
                    </text>
                    
                    <!-- Center City -->
                    <text x="95" y="37" font-size="9" font-weight="900" fill="#2563eb" text-anchor="middle" letter-spacing="1">PUNE</text>
                    
                    <!-- Lower Star/Dots -->
                    <text x="95" y="50" font-size="8" font-weight="900" fill="#2563eb" text-anchor="middle">★</text>
                    
                    <!-- Stylized Blue Cursive Pen Signature Flowing Across Seal -->
                    <path d="M 12,42 Q 28,16 38,32 T 56,20 Q 68,48 85,26 T 110,20" fill="none" stroke="#1d4ed8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M 30,36 Q 50,56 76,42 T 115,34" fill="none" stroke="#1d4ed8" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M 24,26 Q 36,6 44,22" fill="none" stroke="#1d4ed8" stroke-width="2.0" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- 8. Sub-Footer Bar: Buyer Seal And Signature | Computer Generated | Authorized Signatory -->
    <div class="bottom-footer-bar">
        <div style="font-weight: bold; width: 33%;">Buyer Seal And Signature</div>
        <div style="text-align: center; font-size: 10px; width: 34%;">This is a Computer Generated TAX INVOICE</div>
        <div style="text-align: right; font-weight: bold; width: 33%;">Authorized Signatory</div>
    </div>

</div> <!-- End invoice-sheet -->
</div> <!-- End pdf-sheet-wrapper -->

</body>
</html>
