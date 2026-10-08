<?php
require_once __DIR__ . '/../db_helper.php';

use Illuminate\Database\Capsule\Manager as DB;

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (empty($_SESSION['user'])) {
    \App\Services\DocumentPrintService::renderErrorPage(
        'Authentication Required',
        'You must be signed in to view or print official ERP documents.',
        url('/login'),
        'Sign In to WTSBill',
        401
    );
}

$dnId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$authData = \App\Services\DocumentPrintService::authorizeAndFetch('debit_notes', $dnId, 'Debit Note', url('/debit-notes'));
$debitNote = $authData['document'];
$company = $authData['company'];
$companyDetails = $authData['companyDetails'];
$bankDetails = $authData['bankDetails'];
$logoDataUri = $authData['logoUri'];

$dnNumber = $debitNote->debit_note_number ?? 'DN-2026-012';
$origBill = $debitNote->original_purchase_number ?? ($debitNote->purchase_id ? 'PUR-' . str_pad($debitNote->purchase_id, 6, '0', STR_PAD_LEFT) : 'PUR-2026-089');
$supplier = $debitNote->supplier_id ? DB::table('suppliers')->where('id', $debitNote->supplier_id)->first() : null;
$supplierName = $supplier->name ?? ($supplier->company_name ?? 'National Steel Suppliers');
$amount = floatval($debitNote->amount ?? 4200.00);
$taxAmount = floatval($debitNote->total_tax ?? ($debitNote->tax_amount ?? round($amount * 0.18 / 1.18, 2)));
$subTotal = floatval($debitNote->sub_total ?? ($amount - $taxAmount));
$debitDate = date('d-m-Y', strtotime($debitNote->debit_note_date ?? date('Y-m-d')));
$reason = $debitNote->reason ?? 'Material Return / Rejection';

$companyName = $company->name ?? 'Wis Technosavvy Pvt Ltd';
$companyAddress1 = $company->address_line1 ?? 'Office No-B-7, 2nd floor, Shreya Business Hub,';
$companyAddress2 = $company->address_line2 ?? 'Pari chowk, Opp CNG Pump, Narhe,';
$companyCity = trim(($company->city ?? 'Pune') . ', ' . ($company->state ?? 'Maharashtra') . ' ' . ($company->pincode ?? '411041'));
$companyPhone = $company->phone ?? '020 47252364';
$companyEmail = $company->email ?? 'info@wtsindia.co.in';
$companyWebsite = $company->website ?? 'http://www.wtsindia.co.in';
$companyGstin = $company->gstin ?? '27AADCW7577N1ZE';
$companyMsme = $company->msme_registration_no ?? 'UDYAM-MH-26-0734598';
$companyPan = $company->pan ?? 'AADCW7577N';

$docType = 'DEBIT NOTE';
$docNumber = $dnNumber;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debit Note - <?= htmlspecialchars($dnNumber) ?> - WIS TECHNOSAVVY PVT LTD</title>
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
        .buyer-meta-grid { display: grid; grid-template-columns: 48% 52%; border-bottom: 1.5px solid #000; }
        .buyer-col { border-right: 1.5px solid #000; padding: 6px 8px; }
        .meta-col { display: flex; flex-direction: column; }
        .meta-sub-row { display: grid; grid-template-columns: 50% 50%; padding: 4px 6px; font-size: 11px; line-height: 1.35; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 10.5px; table-layout: fixed; empty-cells: show; }
        .items-table thead th { border-bottom: 1.5px solid #000; border-right: 1px solid #000 !important; padding: 5px 3px; font-weight: bold; text-align: center; box-sizing: border-box; }
        .items-table thead th:last-child { border-right: none !important; }
        .items-table tbody td { border-right: 1px solid #000 !important; padding: 5px 4px; vertical-align: top; box-sizing: border-box; }
        .items-table tbody td:last-child { border-right: none !important; }
        .tax-summary-table { width: 100%; border-collapse: collapse; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; font-size: 10.5px; table-layout: fixed; empty-cells: show; }
        .tax-summary-table th, .tax-summary-table td { border-right: 1px solid #000 !important; padding: 4px 6px; text-align: right; box-sizing: border-box; }
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
            <div><strong>MSME No.: <?= htmlspecialchars($companyMsme) ?></strong></div>
            <div><strong>PAN: <?= htmlspecialchars($companyPan) ?></strong></div>
        </div>
        <div class="tax-invoice-badge">DEBIT NOTE</div>
        <div style="font-style: italic; font-size: 10.5px;" id="copyTypeLabel">Original for Supplier</div>
    </div>

    <div class="company-header-block">
        <div class="company-logo-area">
            <img src="<?= $logoDataUri ?>" alt="Wis Technosavvy Pvt Ltd">
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

    <div class="buyer-meta-grid">
        <div class="buyer-col">
            <div style="font-size: 11px; margin-bottom: 2px;"><strong>Debited To (Supplier / Vendor):</strong></div>
            <div style="font-size: 13px; font-weight: 800; margin-bottom: 2px;"><?= htmlspecialchars($supplierName) ?></div>
            <div style="font-size: 11px; line-height: 1.25; margin-bottom: 6px;">Industrial Estate, MIDC Bhosari, Pune - 411026<br>State: Maharashtra [27]</div>
            <div><strong>GSTIN :</strong> 27AAAAA9999Z1</div>
            <div><strong>Place of Supply :</strong> Maharashtra [27]</div>
        </div>
        <div class="meta-col">
            <div class="meta-sub-row">
                <div>
                    <div><strong>Debit Note #:</strong> <?= htmlspecialchars($dnNumber) ?></div>
                    <div><strong>Issue Date:</strong> <?= date('d-m-Y') ?></div>
                    <div><strong>Vendor Bill #:</strong> <?= htmlspecialchars($origBill) ?></div>
                </div>
                <div>
                    <div><strong>Bill Date:</strong> <?= date('d-m-Y', strtotime('-10 days')) ?></div>
                    <div><strong>Debit Category:</strong> Purchase Rejection / Defect</div>
                    <div><strong>Settlement:</strong> Offset against Payable</div>
                </div>
            </div>
            <div style="border-top: 1px solid #000; padding: 4px 6px; font-size: 10px; background: #fff7ed; color: #c2410c;">
                <strong>Reason for Debit:</strong> Goods received in damaged/leaking condition; rejected during inward QA inspection.
            </div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Particulars / Material Rejected</th>
                <th style="width: 12%;">HSN / SAC</th>
                <th style="width: 8%;">GST %</th>
                <th style="width: 15%;">Taxable Amount (₹)</th>
                <th style="width: 15%;">Debited Total (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;">1</td>
                <td>
                    <div style="font-weight: bold;">Substandard Valve Fitting Kit (Batch #9822)</div>
                    <div style="font-size: 9.5px; color: #334155;">Transit leakage and defective threading against Bill #<?= htmlspecialchars($origBill) ?></div>
                </td>
                <td style="text-align: center;">848180</td>
                <td style="text-align: center;">18%</td>
                <td style="text-align: right;"><?= number_format($subTotal, 2) ?></td>
                <td style="text-align: right; font-weight: bold;"><?= number_format($amount, 2) ?></td>
            </tr>
            <tr style="height: 60px;">
                <td>&nbsp;</td>
                <td style="vertical-align: top; padding-top: 6px;">
                    <div style="text-align: right; font-weight: bold; padding-right: 12px; margin-bottom: 3px;">CGST @ 9%</div>
                    <div style="text-align: right; font-weight: bold; padding-right: 12px;">SGST @ 9%</div>
                </td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td style="vertical-align: top; padding-top: 6px; text-align: right; font-weight: bold;">
                    <div style="margin-bottom: 3px;"><?= number_format($taxAmount / 2, 2) ?></div>
                    <div><?= number_format($taxAmount / 2, 2) ?></div>
                </td>
            </tr>
        </tbody>
    </table>

    <table class="tax-summary-table">
        <thead>
            <tr>
                <th style="width: 25%;">Taxable Value</th>
                <th style="width: 15%;">CGST (9%)</th>
                <th style="width: 15%;">SGST (9%)</th>
                <th style="width: 20%;">Total Tax</th>
                <th style="width: 25%; text-align: right;">Total Debited Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($subTotal, 2) ?></td>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($taxAmount / 2, 2) ?></td>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($taxAmount / 2, 2) ?></td>
                <td style="text-align: center; font-weight: bold;">₹<?= number_format($taxAmount, 2) ?></td>
                <td style="text-align: right; font-size: 14px; font-weight: 900; color: #ea580c;">₹<?= number_format($amount, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <div style="padding: 4px 8px; font-size: 10px; border-bottom: 1.5px solid #000; background: #fafafa;">
        <strong>Total Debit In Words:</strong> <?= htmlspecialchars(getIndianWords($amount)) ?>
    </div>

    <div class="bottom-split-row">
        <div class="bottom-terms-col" id="termsSection">
            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">Terms &amp; Adjustment Conditions:</div>
            <div>1. This debit note is issued under Section 34 of the CGST Act 2017 for goods rejected upon receipt.</div>
            <div>2. The supplier is requested to issue an equivalent credit note in their books to reflect this return.</div>
            <div>3. Input Tax Credit (ITC) has been appropriately adjusted in the current tax period.</div>
        </div>
        <div class="bottom-stamp-col" id="stampSection">
            <div style="font-weight: bold; font-size: 10.5px; margin-bottom: 2px;">For <?= htmlspecialchars($companyName) ?></div>
            <div style="display: flex; justify-content: flex-end; align-items: center; margin: 2px 0;">
                <svg width="120" height="66" viewBox="0 0 120 66">
                    <circle cx="85" cy="33" r="28" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-dasharray="2.5,1.5"/>
                    <circle cx="85" cy="33" r="24" fill="none" stroke="#2563eb" stroke-width="1"/>
                    <text x="85" y="30" font-size="6" font-weight="900" fill="#2563eb" text-anchor="middle">WIS TECHNOSAVVY</text>
                    <text x="85" y="40" font-size="8" font-weight="900" fill="#2563eb" text-anchor="middle">PUNE</text>
                    <path d="M 15,42 Q 30,16 42,34 T 60,20 Q 72,48 90,28 T 115,20" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bottom-footer-bar">
        <div style="font-weight: bold; width: 33%;">Supplier / Vendor Acknowledgement</div>
        <div style="text-align: center; font-size: 10px; width: 34%;">Computer Generated Debit Voucher</div>
        <div style="text-align: right; font-weight: bold; width: 33%;">Authorized Signatory</div>
    </div>
</div>
</div>

</body>
</html>
