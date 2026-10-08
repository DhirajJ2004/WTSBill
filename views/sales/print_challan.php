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

$companyId = get_current_company_id();
$challanId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($challanId > 0) {
    $authData = \App\Services\DocumentPrintService::authorizeAndFetch('delivery_challans', $challanId, 'Delivery Challan', url('/delivery-challans'));
    $challan = $authData['document'];
    $company = $authData['company'];
    $logoDataUri = $authData['logoUri'];

    $challanNumber = $challan->challan_number ?? ('DC-' . $challan->id);
    $challanDate = date('d-m-Y', strtotime($challan->challan_date ?? ($challan->created_at ?? date('Y-m-d'))));
    $customer = DB::table('customers')->where('id', $challan->customer_id)->first();
    $recipient = $customer->name ?? 'Customer';
    $recipientAddress = ($customer->address_line1 ?? '') . (!empty($customer->city) ? ' - ' . $customer->city : '');
    $recipientGstin = $customer->gstin ?? 'URP';
    $vehicleNo = $challan->transport_mode ?? ($challan->vehicle_number ?? 'MH-12-QX-4891');
    $ewayBill = $challan->eway_bill_number ?? '241829019283';
    $challanItems = DB::table('delivery_challan_items')->where('delivery_challan_id', $challan->id)->get();
} else {
    $challanNumber = $_GET['number'] ?? 'DC-2026-009';
    $recipient = $_GET['client'] ?? 'Ramesh Hardware Stores';
    $recipientAddress = 'Plot 44, Market Yard, Pune';
    $recipientGstin = '27AAAAA0000A1Z5';
    $vehicleNo = $_GET['vehicle'] ?? 'MH-12-QX-4891';
    $ewayBill = $_GET['eway'] ?? '241829019283';
    $challanDate = date('d-m-Y');
    $challanItems = [];
}

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

$logoPath = __DIR__ . '/../../public/assets/img/logo.png';
$logoDataUri = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : url('/assets/img/logo.png');

$docType = 'DELIVERY CHALLAN';
$docNumber = $challanNumber;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Challan - <?= htmlspecialchars($challanNumber) ?> - WIS TECHNOSAVVY PVT LTD</title>
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
        <div class="tax-invoice-badge">DELIVERY CHALLAN</div>
        <div style="font-style: italic; font-size: 10.5px;" id="copyTypeLabel">Original for Consignee</div>
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
            <div style="font-size: 11px; margin-bottom: 2px;"><strong>Consignee (Ship &amp; Deliver To):</strong></div>
            <div style="font-size: 13px; font-weight: 800; margin-bottom: 2px;"><?= htmlspecialchars($recipient) ?></div>
            <div style="font-size: 11px; line-height: 1.25; margin-bottom: 6px;">Plot 18, Commercial Market Area, Narhe, Pune - 411041<br>State: Maharashtra [27]</div>
            <div><strong>GSTIN :</strong> 27BBBBB1234C1Z5</div>
            <div><strong>Place of Supply :</strong> Maharashtra [27]</div>
        </div>
        <div class="meta-col">
            <div class="meta-sub-row">
                <div>
                    <div><strong>Challan Number:</strong> <?= htmlspecialchars($challanNumber) ?></div>
                    <div><strong>Dispatch Date:</strong> <?= date('d-m-Y') ?></div>
                    <div><strong>E-Way Bill No:</strong> <?= htmlspecialchars($ewayBill) ?></div>
                </div>
                <div>
                    <div><strong>Vehicle No:</strong> <?= htmlspecialchars($vehicleNo) ?></div>
                    <div><strong>Transport Mode:</strong> Road / Surface Express</div>
                    <div><strong>Challan Type:</strong> Supply on Approval / Job Work</div>
                </div>
            </div>
            <div style="border-top: 1px solid #000; padding: 4px 6px; font-size: 10px; background: #f8fafc;">
                <strong>Transporter / Driver Remarks:</strong><br>
                Safely loaded with bubble packaging. Transit seal intact: #TS-99214.
            </div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Description of Goods / Materials</th>
                <th style="width: 12%;">HSN / SAC</th>
                <th style="width: 10%;">Package Qty</th>
                <th style="width: 12%;">Gross Weight</th>
                <th style="width: 16%;">Inspection Remarks</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;">1</td>
                <td>
                    <div style="font-weight: bold;">Standard High-Pressure Industrial Valves 2-inch</div>
                    <div style="font-size: 9.5px; color: #334155;">Model: WTS-VLV-200. Heavy duty brass casting with nitrile seal</div>
                </td>
                <td style="text-align: center;">848180</td>
                <td style="text-align: right; font-weight: bold;">120 Pcs</td>
                <td style="text-align: right;">42.50 Kgs</td>
                <td>Brand new in sealed carton</td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;">2</td>
                <td>
                    <div style="font-weight: bold;">Heavy Duty Copper Conduit Bushings &amp; Clamps</div>
                    <div style="font-size: 9.5px; color: #334155;">Precision threaded connectors for electrical conduit trunking</div>
                </td>
                <td style="text-align: center;">854449</td>
                <td style="text-align: right; font-weight: bold;">50 Sets</td>
                <td style="text-align: right;">18.20 Kgs</td>
                <td>Quality certified batch</td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;">3</td>
                <td>
                    <div style="font-weight: bold;">Multi-Point Industrial Digital Flow Meter Assembly</div>
                    <div style="font-size: 9.5px; color: #334155;">Electronic calibration kit included with test report</div>
                </td>
                <td style="text-align: center;">902610</td>
                <td style="text-align: right; font-weight: bold;">10 Nos</td>
                <td style="text-align: right;">6.80 Kgs</td>
                <td>Demo &amp; calibration transit</td>
            </tr>
            <tr style="height: 60px;">
                <td colspan="6" style="padding: 8px; vertical-align: top; background: #fafafa;">
                    <div style="font-weight: bold; margin-bottom: 2px;">Material Dispatch Declaration:</div>
                    <div style="color: #475569; font-size: 10px; line-height: 1.35;">
                        The goods described herein are being transported for job work / demonstration purposes and not by way of sale. No commercial transaction is concluded by virtue of this delivery challan.
                    </div>
                </td>
            </tr>
            <tr style="border-top: 1.5px solid #000; font-weight: bold; background: #f1f5f9;">
                <td colspan="3" style="text-align: right; padding: 5px 8px;">Total Goods Dispatched:</td>
                <td style="text-align: right; padding: 5px 4px;">180 Units</td>
                <td style="text-align: right; padding: 5px 4px;">67.50 Kgs</td>
                <td>&nbsp;</td>
            </tr>
        </tbody>
    </table>

    <div class="bottom-split-row">
        <div class="bottom-terms-col" id="termsSection">
            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">Challan Conditions &amp; Gate Entry Rules:</div>
            <div>1. Consignee must verify bundle seals and condition before signing the receiving voucher.</div>
            <div>2. Discrepancies or carton damage must be endorsed on both transporter copy and challan.</div>
            <div>3. Returnable materials must be re-dispatched within 30 days under valid return delivery note.</div>
        </div>
        <div class="bottom-stamp-col" id="stampSection">
            <div style="font-weight: bold; font-size: 10.5px; margin-bottom: 2px;">For WIS TECHNOSAVVY PVT LTD</div>
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
        <div style="font-weight: bold; width: 33%;">Receiver's Signature &amp; Stamp</div>
        <div style="text-align: center; font-size: 10px; width: 34%;">Driver / Transporter Signature</div>
        <div style="text-align: right; font-weight: bold; width: 33%;">Authorized Signatory</div>
    </div>
</div>
</div>

</body>
</html>
