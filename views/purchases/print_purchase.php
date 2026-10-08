<?php
require_once __DIR__ . '/../db_helper.php';
use Illuminate\Database\Capsule\Manager as DB;

$purchaseId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$authData = \App\Services\DocumentPrintService::authorizeAndFetch('purchases', $purchaseId, 'Purchase Document', url('/purchases'));
$purchase = $authData['document'];
$company = $authData['company'];
$companyDetails = $authData['companyDetails'];
$bankDetails = $authData['bankDetails'];
$logoDataUri = $authData['logoUri'];

$supplier = DB::table('suppliers')->where('id', $purchase->supplier_id)->first();
$items = DB::table('purchase_items')->where('purchase_id', $purchase->id)->get();

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

$purchaseNumber = $purchase->purchase_number ?? ('PUR-' . $purchase->id);
$vendorBillNo = $purchase->vendor_invoice_number ?? ($purchase->reference_no ?? '-');
$purchaseDate = date('d-m-Y', strtotime($purchase->purchase_date ?? ($purchase->created_at ?? date('Y-m-d'))));
$dueDate = date('d-m-Y', strtotime($purchase->due_date ?? date('Y-m-d', strtotime('+30 days'))));

$taxableAmt = floatval($purchase->sub_total ?? 0);
$cgst = floatval($purchase->cgst_amount ?? 0);
$sgst = floatval($purchase->sgst_amount ?? 0);
$igst = floatval($purchase->igst_amount ?? 0);
$totalTax = floatval($purchase->total_tax ?? ($cgst + $sgst + $igst));
$grandTotal = floatval($purchase->grand_total ?? ($taxableAmt + $totalTax));

$supplierName = $supplier->name ?? ($purchase->supplier_name ?? 'Vendor');
$supplierAddress = ($supplier->address_line1 ?? ($purchase->billing_address ?? '')) . (!empty($supplier->city) ? ' - ' . $supplier->city : '');
$supplierGstin = $supplier->gstin ?? ($purchase->supplier_gstin ?? 'URP');

$docType = 'PURCHASE BILL VOUCHER';
$docNumber = $purchaseNumber;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PURCHASE BILL - <?= htmlspecialchars($purchaseNumber) ?> - <?= htmlspecialchars($companyName) ?></title>
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
        .buyer-meta-grid { display: grid; grid-template-columns: 50% 50%; border-bottom: 1.5px solid #000; }
        .buyer-col { border-right: 1.5px solid #000; padding: 6px 8px; }
        .meta-col { display: flex; flex-direction: column; }
        .meta-sub-row { display: grid; grid-template-columns: 50% 50%; padding: 4px 6px; font-size: 11px; line-height: 1.35; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; empty-cells: show; }
        .items-table thead th { border-bottom: 1.5px solid #000; border-right: 1px solid #000 !important; padding: 4px 2px; font-weight: bold; text-align: center; box-sizing: border-box; }
        .items-table thead th:last-child { border-right: none !important; }
        .items-table tbody td { border-right: 1px solid #000 !important; padding: 5px 4px; vertical-align: top; font-size: 10.5px; box-sizing: border-box; }
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
        <div><strong>GSTIN:</strong> <?= htmlspecialchars($companyGstin) ?></div>
        <div class="tax-invoice-badge"><?= htmlspecialchars($docType) ?></div>
        <div style="text-align: right;"><strong>Internal Voucher</strong></div>
    </div>

    <div class="company-header-block">
        <div class="company-logo-area">
            <img src="<?= $logoDataUri ?>" alt="Logo">
        </div>
        <div class="company-info-center">
            <div class="company-name-title"><?= htmlspecialchars($companyName) ?></div>
            <p><?= htmlspecialchars($companyAddress1) ?> <?= htmlspecialchars($companyAddress2) ?></p>
            <p><?= htmlspecialchars($companyCity) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($companyPhone) ?> | <strong>Email:</strong> <?= htmlspecialchars($companyEmail) ?></p>
        </div>
    </div>

    <div class="buyer-meta-grid">
        <div class="buyer-col">
            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">Vendor / Supplier Details:</div>
            <div style="font-size: 13px; font-weight: 800;"><?= htmlspecialchars($supplierName) ?></div>
            <div><?= htmlspecialchars($supplierAddress) ?></div>
            <div><strong>GSTIN:</strong> <?= htmlspecialchars($supplierGstin) ?></div>
        </div>
        <div class="meta-col">
            <div class="meta-sub-row" style="border-bottom: 1px solid #000;">
                <div><strong>Purchase Voucher No:</strong></div>
                <div style="font-weight: 900;"><?= htmlspecialchars($purchaseNumber) ?></div>
            </div>
            <div class="meta-sub-row" style="border-bottom: 1px solid #000;">
                <div><strong>Vendor Invoice / Ref:</strong></div>
                <div><?= htmlspecialchars($vendorBillNo) ?></div>
            </div>
            <div class="meta-sub-row">
                <div><strong>Purchase Date:</strong> <?= htmlspecialchars($purchaseDate) ?></div>
                <div><strong>Due Date:</strong> <?= htmlspecialchars($dueDate) ?></div>
            </div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">Sr.</th>
                <th style="width: 45%;">Item Description</th>
                <th style="width: 12%;">HSN/SAC</th>
                <th style="width: 8%;">Qty</th>
                <th style="width: 15%;">Rate (₹)</th>
                <th style="width: 15%;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items) && count($items) > 0): ?>
                <?php foreach ($items as $idx => $it): 
                    $rate = floatval($it->unit_price ?? 0);
                    $qty = floatval($it->quantity ?? 1);
                    $amt = floatval($it->total_amount ?? ($rate * $qty));
                ?>
                    <tr>
                        <td style="text-align: center;"><?= $idx + 1 ?></td>
                        <td><strong><?= htmlspecialchars($it->product_name ?? 'Inward Goods') ?></strong></td>
                        <td style="text-align: center;"><?= htmlspecialchars($it->hsn_sac ?? '84818020') ?></td>
                        <td style="text-align: center; font-weight: bold;"><?= $qty ?></td>
                        <td style="text-align: right;"><?= number_format($rate, 2) ?></td>
                        <td style="text-align: right; font-weight: bold;"><?= number_format($amt, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td style="text-align: center;">1</td>
                    <td><strong>Inward Goods &amp; Inventory Supplies</strong></td>
                    <td style="text-align: center;">84818020</td>
                    <td style="text-align: center; font-weight: bold;">1</td>
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
                <th style="width: 20%;">Total Inward Tax</th>
                <th style="width: 25%; text-align: right;">Total Bill Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($taxableAmt, 2) ?></td>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($cgst, 2) ?></td>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($sgst, 2) ?></td>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($totalTax, 2) ?></td>
                <td style="text-align: right; font-size: 14px; font-weight: 900; color: #166534;">₹<?= number_format($grandTotal, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <div style="padding: 6px 10px; background: #f8fafc; border-bottom: 1.5px solid #000; font-size: 11px;">
        <strong>Total Bill Amount in Words:</strong> <?= htmlspecialchars(getIndianWords($grandTotal)) ?>
    </div>

    <div class="bottom-split-row">
        <div class="bottom-terms-col" id="termsSection">
            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">Inward Verification Note:</div>
            <div>1. Goods received in good condition and stock updated in registry.</div>
            <div>2. Eligible for Input Tax Credit (ITC) as per GSTR-2B compliance.</div>
        </div>
        <div class="bottom-stamp-col" id="stampSection">
            <div style="font-weight: bold; font-size: 10.5px; margin-bottom: 2px;">For <?= htmlspecialchars($companyName) ?></div>
            <div style="display: flex; justify-content: flex-end; align-items: center; margin: 2px 0;">
                <svg width="120" height="66" viewBox="0 0 120 66">
                    <circle cx="85" cy="33" r="28" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-dasharray="2.5,1.5"/>
                    <circle cx="85" cy="33" r="24" fill="none" stroke="#2563eb" stroke-width="1"/>
                    <text x="85" y="30" font-size="6" font-weight="900" fill="#2563eb" text-anchor="middle"><?= strtoupper(substr(htmlspecialchars($companyName), 0, 14)) ?></text>
                    <text x="85" y="40" font-size="8" font-weight="900" fill="#2563eb" text-anchor="middle">VERIFIED</text>
                    <path d="M 15,42 Q 30,16 42,34 T 60,20 Q 72,48 90,28 T 115,20" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bottom-footer-bar">
        <div style="font-weight: bold; width: 33%;">Goods Inward Receiver</div>
        <div style="text-align: center; font-size: 10px; width: 34%;">Computer Generated Purchase Document</div>
        <div style="text-align: right; font-weight: bold; width: 33%;">Store Manager Signatory</div>
    </div>
</div>
</div>
</body>
</html>
