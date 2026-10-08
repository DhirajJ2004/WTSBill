<?php
require_once __DIR__ . '/../db_helper.php';
use Illuminate\Database\Capsule\Manager as DB;

$quoteId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$authData = \App\Services\DocumentPrintService::authorizeAndFetch('quotations', $quoteId, 'Quotation', url('/quotations'));
$quote = $authData['document'];
$company = $authData['company'];
$companyDetails = $authData['companyDetails'];
$bankDetails = $authData['bankDetails'];
$logoDataUri = $authData['logoUri'];

$customer = DB::table('customers')->where('id', $quote->customer_id)->first();
$items = DB::table('quotation_items')->where('quotation_id', $quote->id)->get();

$companyName = $companyDetails['name'];
$companyAddress1 = $companyDetails['address1'];
$companyAddress2 = $companyDetails['address2'];
$companyCity = $companyDetails['city'];
$companyPhone = $companyDetails['phone'];
$companyEmail = $companyDetails['email'];
$companyWebsite = $companyDetails['website'];
$companyGstin = $companyDetails['gstin'];
$companyMsme = $companyDetails['msme'];
$companyPan = $companyDetails['pan'];

$bankName = $bankDetails['bank_name'];
$bankAccountName = $bankDetails['account_name'];
$bankAccountNo = $bankDetails['account_number'];
$bankIfsc = $bankDetails['ifsc_code'];

// Logo
$logoPath = __DIR__ . '/../../public/assets/img/logo.png';
$logoDataUri = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : url('/assets/img/logo.png');

$quoteNumber = $quote->quotation_number ?? ('QTN-' . $quote->id);
$quoteDate = date('d-m-Y', strtotime($quote->quotation_date ?? date('Y-m-d')));
$validUntil = date('d-m-Y', strtotime($quote->valid_until ?? date('Y-m-d', strtotime('+30 days'))));
$taxableAmt = floatval($quote->sub_total ?? 0);
$taxTotal = floatval($quote->total_tax ?? 0);
$grandTotal = floatval($quote->grand_total ?? ($taxableAmt + $taxTotal));
$cgst = floatval($quote->cgst_amount ?? round($taxTotal / 2, 2));
$sgst = floatval($quote->sgst_amount ?? round($taxTotal / 2, 2));
$igst = floatval($quote->igst_amount ?? 0);

$customerName = $customer->name ?? 'Customer';
$customerAddress = htmlspecialchars($customer->address_line1 ?? '') . (!empty($customer->address_line2) ? '<br>' . htmlspecialchars($customer->address_line2) : '') . '<br>' . htmlspecialchars($customer->city ?? '') . ' - ' . htmlspecialchars($customer->state ?? '') . '<br>PIN :' . htmlspecialchars($customer->pincode ?? '');
$customerGstin = $customer->gstin ?? 'URP';
$placeOfSupply = ($customer->state ?? 'Maharashtra') . (!empty($customer->state_code) ? ' [' . $customer->state_code . ']' : ' [27]');

$docType = 'PROPOSAL / QUOTATION';
$docNumber = $quoteNumber;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QUOTATION - <?= htmlspecialchars($quoteNumber) ?> - <?= htmlspecialchars($companyName) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { background-color: #f1f5f9; color: #000; font-size: 11px; line-height: 1.3; padding: 20px; }
        .invoice-sheet { width: 100%; max-width: 210mm; min-height: 260mm; margin: 0 auto; background: #fff; border: 1.5px solid #000; padding: 0; position: relative; box-shadow: 0 4px 12px rgba(0,0,0,0.12); box-sizing: border-box; }
        .top-tax-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1.5px solid #000; padding: 4px 8px; font-size: 11px; }
        .tax-invoice-badge { font-size: 14px; font-weight: 900; letter-spacing: 0.5px; text-decoration: underline; text-align: center; }
        .company-header-block { display: flex; align-items: center; padding: 8px 12px; border-bottom: 1.5px solid #000; position: relative; }
        .company-logo-area { width: 90px; height: 90px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; margin-right: 12px; }
        .company-logo-area img { width: 82px; height: 82px; object-fit: contain; border-radius: 50%; }
        .company-info-center { flex: 1; text-align: center; padding-right: 90px; }
        .company-name-title { font-size: 19px; font-weight: 900; letter-spacing: 0.5px; margin-bottom: 2px; }
        .buyer-meta-grid { display: grid; grid-template-columns: 45% 55%; border-bottom: 1.5px solid #000; }
        .buyer-col { border-right: 1.5px solid #000; padding: 6px 8px; }
        .meta-col { display: flex; flex-direction: column; }
        .meta-sub-row { display: grid; grid-template-columns: 50% 50%; padding: 4px 6px; font-size: 11px; line-height: 1.35; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; empty-cells: show; }
        .items-table thead th { border-bottom: 1.5px solid #000; border-right: 1px solid #000 !important; padding: 4px 2px; font-weight: bold; text-align: center; box-sizing: border-box; }
        .items-table thead th:last-child { border-right: none !important; }
        .items-table tbody td { border-right: 1px solid #000 !important; padding: 4px 4px; vertical-align: top; font-size: 10.5px; box-sizing: border-box; }
        .items-table tbody td:last-child { border-right: none !important; }
        .tax-summary-table { width: 100%; border-collapse: collapse; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; font-size: 10.5px; table-layout: fixed; empty-cells: show; }
        .tax-summary-table th, .tax-summary-table td { border-right: 1px solid #000 !important; padding: 3px 6px; text-align: right; box-sizing: border-box; }
        .tax-summary-table th { text-align: center; font-weight: bold; }
        .tax-summary-table th:last-child, .tax-summary-table td:last-child { border-right: none !important; }
        
        /* Bottom Split Section */
        .bottom-split-row { display: grid; grid-template-columns: 60% 40%; border-bottom: 1px solid #000000; min-height: 75px; box-sizing: border-box; }
        .bottom-terms-col { padding: 4px 6px; font-size: 8.5px; line-height: 1.35; }
        .bottom-stamp-col { padding: 4px 8px; text-align: right; display: flex; flex-direction: column; justify-content: flex-start; align-items: flex-end; box-sizing: border-box; }
        .bottom-footer-bar { display: flex; justify-content: space-between; align-items: center; padding: 4px 8px; font-size: 10.5px; box-sizing: border-box; }

        @page { size: A4 portrait; margin: 6mm; }
        @media print {
            html, body { background: #ffffff !important; padding: 0 !important; margin: 0 !important; width: 100% !important; }
            .print-controls-bar, .no-print { display: none !important; }
            .pdf-sheet-wrapper { zoom: var(--pdf-zoom, 0.94) !important; width: 100% !important; max-width: 100% !important; margin: 0 auto !important; padding: 0 !important; }
            .invoice-sheet { box-shadow: none !important; border: 1.5px solid #000000 !important; width: 100% !important; max-width: 100% !important; min-height: auto !important; height: auto !important; margin: 0 auto !important; box-sizing: border-box !important; page-break-inside: avoid !important; break-inside: avoid !important; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../layout/print_controls.php'; ?>

<div class="pdf-sheet-wrapper">
<div class="invoice-sheet">
    <div class="top-tax-header">
        <div>
            <div><strong>GSTIN: <?= htmlspecialchars($companyGstin) ?></strong></div>
            <?php if (!empty($companyMsme)): ?><div><strong>MSME No.: <?= htmlspecialchars($companyMsme) ?></strong></div><?php endif; ?>
            <?php if (!empty($companyPan)): ?><div><strong>PAN: <?= htmlspecialchars($companyPan) ?></strong></div><?php endif; ?>
        </div>
        <div class="tax-invoice-badge">COMMERCIAL PROPOSAL / QUOTATION</div>
        <div style="font-style: italic; font-size: 10.5px;" id="copyTypeLabel">Original (Page 1/1)</div>
    </div>

    <div class="company-header-block">
        <div class="company-logo-area">
            <img src="<?= $logoDataUri ?>" alt="<?= htmlspecialchars($companyName) ?>">
        </div>
        <div class="company-info-center">
            <div class="company-name-title"><?= htmlspecialchars($companyName) ?></div>
            <?php if (!empty($companyAddress1)): ?><p><?= htmlspecialchars($companyAddress1) ?></p><?php endif; ?>
            <?php if (!empty($companyAddress2)): ?><p><?= htmlspecialchars($companyAddress2) ?></p><?php endif; ?>
            <?php if (!empty($companyCity)): ?><p><?= htmlspecialchars($companyCity) ?></p><?php endif; ?>
            <?php if (!empty($companyPhone)): ?><p><strong><?= htmlspecialchars($companyPhone) ?></strong></p><?php endif; ?>
            <?php if (!empty($companyEmail)): ?><p><?= htmlspecialchars($companyEmail) ?></p><?php endif; ?>
            <?php if (!empty($companyWebsite)): ?><p><?= htmlspecialchars($companyWebsite) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="buyer-meta-grid">
        <div class="buyer-col">
            <div style="font-size: 11px; margin-bottom: 2px;">Prepared For:</div>
            <div style="font-size: 13px; font-weight: 800; margin-bottom: 2px;"><?= htmlspecialchars($customerName) ?></div>
            <div style="font-size: 11px; line-height: 1.25; margin-bottom: 6px;"><?= $customerAddress ?></div>
            <div><strong>GSTIN :</strong> <?= htmlspecialchars($customerGstin) ?></div>
            <div><strong>Place of Supply :</strong> <?= htmlspecialchars($placeOfSupply) ?></div>
        </div>
        <div class="meta-col">
            <div class="meta-sub-row">
                <div>
                    <div><strong>Quotation #:</strong> <?= htmlspecialchars($quoteNumber) ?></div>
                    <div><strong>Quote Date:</strong> <?= htmlspecialchars($quoteDate) ?></div>
                    <div><strong>Valid Until:</strong> <?= htmlspecialchars($validUntil) ?></div>
                    <div><strong>Status:</strong> <?= strtoupper(htmlspecialchars($quote->status ?? 'SENT')) ?></div>
                </div>
                <div>
                    <div><strong>Prepared By:</strong> Sales Dept</div>
                    <div><strong>Currency:</strong> INR (₹)</div>
                    <div><strong>Delivery Lead:</strong> 1-2 Weeks</div>
                </div>
            </div>
            <?php if (!empty($bankAccountNo)): ?>
            <div style="border-top: 1px solid #000; padding: 4px 6px; font-size: 10px; display: flex; justify-content: space-between; align-items: center;" id="bankQrSection">
                <div>
                    <strong>Bank Details:</strong><br>
                    <?= htmlspecialchars($bankName) ?> | A/C: <?= htmlspecialchars($bankAccountNo) ?><br>
                    IFSC: <?= htmlspecialchars($bankIfsc) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Item / Scope of Work</th>
                <th style="width: 12%;">HSN/SAC</th>
                <th style="width: 8%;">GST</th>
                <th style="width: 8%;">Qty</th>
                <th style="width: 11%;">Rate (₹)</th>
                <th style="width: 11%;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $rowNum = 1;
            if ($items && count($items) > 0):
                foreach ($items as $it): 
                    $pName = $it->item_name ?? 'Product / Service';
                    if (empty($pName) && !empty($it->product_id)) {
                        $pObj = DB::table('products')->where('id', $it->product_id)->first();
                        $pName = $pObj->name ?? 'Product';
                    }
                    $hsn = $it->hsn_sac ?? '';
                    $gst = floatval($it->gst_rate ?? 18);
                    $qty = floatval($it->quantity ?? 1);
                    $rate = floatval($it->unit_price ?? 0);
                    $amt = floatval($it->total_amount ?? ($qty * $rate));
            ?>
                <tr>
                    <td style="text-align: center; font-weight: bold;"><?= $rowNum++ ?></td>
                    <td>
                        <div style="font-weight: bold;"><?= htmlspecialchars($pName) ?></div>
                    </td>
                    <td style="text-align: center;"><?= htmlspecialchars($hsn ?: 'N/A') ?></td>
                    <td style="text-align: center;"><?= $gst ?>%</td>
                    <td style="text-align: right; font-weight: bold;"><?= $qty ?> <?= htmlspecialchars($it->unit ?? 'Pcs') ?></td>
                    <td style="text-align: right;"><?= number_format($rate, 2) ?></td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format($amt, 2) ?></td>
                </tr>
            <?php 
                endforeach; 
            else:
            ?>
                <tr>
                    <td style="text-align: center; font-weight: bold;">1</td>
                    <td><div style="font-weight: bold;">Commercial Proposal Items</div></td>
                    <td style="text-align: center;">998313</td>
                    <td style="text-align: center;">18%</td>
                    <td style="text-align: right; font-weight: bold;">1 NOS</td>
                    <td style="text-align: right;"><?= number_format($taxableAmt, 2) ?></td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format($taxableAmt, 2) ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table class="tax-summary-table">
        <thead>
            <tr>
                <th style="width: 25%;">Taxable Amount</th>
                <th style="width: 15%;">CGST</th>
                <th style="width: 15%;">SGST</th>
                <th style="width: 20%;">Total Tax</th>
                <th style="width: 25%; text-align: right;">Total Quotation</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($taxableAmt, 2) ?></td>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($cgst, 2) ?></td>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($sgst, 2) ?></td>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($taxTotal, 2) ?></td>
                <td style="text-align: right; font-size: 14px; font-weight: 900;">₹<?= number_format($grandTotal, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <div style="padding: 6px 10px; background: #f8fafc; border-bottom: 1.5px solid #000; font-size: 11px;">
        <strong>Amount Chargeable in Words:</strong> <?= htmlspecialchars(getIndianWords($grandTotal)) ?>
    </div>

    <div class="bottom-split-row">
        <div class="bottom-terms-col" id="termsSection">
            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">Commercial Terms &amp; Scope Validity:</div>
            <div>1. Quotation valid for 30 calendar days from issue date.</div>
            <div>2. Taxes: GST applicable as per statutory notifications.</div>
            <?php if (!empty($quote->terms)): ?>
                <div>3. <?= htmlspecialchars($quote->terms) ?></div>
            <?php endif; ?>
        </div>
        <div class="bottom-stamp-col" id="stampSection">
            <div style="font-weight: bold; font-size: 10.5px; margin-bottom: 2px;">For <?= htmlspecialchars($companyName) ?></div>
            <div style="display: flex; justify-content: flex-end; align-items: center; margin: 2px 0;">
                <svg width="120" height="66" viewBox="0 0 120 66">
                    <circle cx="85" cy="33" r="28" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-dasharray="2.5,1.5"/>
                    <circle cx="85" cy="33" r="24" fill="none" stroke="#2563eb" stroke-width="1"/>
                    <text x="85" y="30" font-size="6" font-weight="900" fill="#2563eb" text-anchor="middle"><?= strtoupper(substr(htmlspecialchars($companyName), 0, 14)) ?></text>
                    <text x="85" y="40" font-size="8" font-weight="900" fill="#2563eb" text-anchor="middle">APPROVED</text>
                    <path d="M 15,42 Q 30,16 42,34 T 60,20 Q 72,48 90,28 T 115,20" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bottom-footer-bar">
        <div style="font-weight: bold; width: 33%;">Proposal Acceptance Signature</div>
        <div style="text-align: center; font-size: 10px; width: 34%;">Computer Generated Quotation</div>
        <div style="text-align: right; font-weight: bold; width: 33%;">Authorized Signatory</div>
    </div>
</div>
</div>
</body>
</html>
