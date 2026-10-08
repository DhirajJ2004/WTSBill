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
$company = DB::table('companies')->where('id', $companyId)->first();
$bank = DB::table('bank_accounts')->where('company_id', $companyId)->first();

$type = $_GET['type'] ?? 'so'; // 'so' = Sales Order, 'po' = Purchase Order
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId > 0) {
    $table = ($type === 'po') ? 'purchase_orders' : 'sales_orders';
    $docName = ($type === 'po') ? 'Purchase Order' : 'Sales Order';
    $authData = \App\Services\DocumentPrintService::authorizeAndFetch($table, $orderId, $docName, url($type === 'po' ? '/purchases' : '/sales-orders'));
    $order = $authData['document'];
    $company = $authData['company'];
    $bank = $authData['bank'];
    $logoDataUri = $authData['logoUri'];

    $orderNumber = ($type === 'po') ? ($order->po_number ?? ('PO-' . $order->id)) : ($order->order_number ?? ('SO-' . $order->id));
    $orderDate = date('d-m-Y', strtotime($order->order_date ?? ($order->po_date ?? ($order->created_at ?? date('Y-m-d')))));
    
    if ($type === 'po') {
        $supplier = DB::table('suppliers')->where('id', $order->supplier_id)->first();
        $clientName = $supplier->name ?? 'Vendor';
        $clientAddress = ($supplier->address_line1 ?? '') . (!empty($supplier->city) ? ' - ' . $supplier->city : '');
        $clientGstin = $supplier->gstin ?? 'URP';
        $orderItems = DB::table('purchase_order_items')->where('purchase_order_id', $order->id)->get();
    } else {
        $customer = DB::table('customers')->where('id', $order->customer_id)->first();
        $clientName = $customer->name ?? 'Customer';
        $clientAddress = ($customer->address_line1 ?? '') . (!empty($customer->city) ? ' - ' . $customer->city : '');
        $clientGstin = $customer->gstin ?? 'URP';
        $orderItems = DB::table('sales_order_items')->where('sales_order_id', $order->id)->get();
    }
    
    $grandTotal = floatval($order->grand_total ?? ($order->total_amount ?? 0));
    $taxableAmt = floatval($order->sub_total ?? 0);
    $totalTax = floatval($order->total_tax ?? 0);
    $cgst = floatval($order->cgst_amount ?? round($totalTax / 2, 2));
    $sgst = floatval($order->sgst_amount ?? round($totalTax / 2, 2));
    $igst = floatval($order->igst_amount ?? 0);
} else {
    $orderNumber = $_GET['number'] ?? ($type === 'po' ? 'PO-2026-0082' : 'SO-2026-081');
    $clientName = $_GET['party'] ?? ($type === 'po' ? 'Apex Metals & Alloys' : 'Ramesh Hardware Stores');
    $clientAddress = '123 Industrial Area, Pune';
    $clientGstin = '27AAAAA0000A1Z5';
    $orderDate = date('d-m-Y');
    $taxableAmt = 10000.00;
    $cgst = 900.00;
    $sgst = 900.00;
    $igst = 0.00;
    $grandTotal = 11800.00;
    $orderItems = [];
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

$docTitle = ($type === 'po') ? 'PURCHASE ORDER (PO)' : 'CONFIRMED SALES ORDER (SO)';
$docType = $docTitle;
$docNumber = $orderNumber;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($docTitle) ?> - <?= htmlspecialchars($orderNumber) ?> - WIS TECHNOSAVVY PVT LTD</title>
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
        <div>
            <div><strong>GSTIN: <?= htmlspecialchars($companyGstin) ?></strong></div>
            <div><strong>MSME No.: <?= htmlspecialchars($companyMsme) ?></strong></div>
            <div><strong>PAN: <?= htmlspecialchars($companyPan) ?></strong></div>
        </div>
        <div class="tax-invoice-badge"><?= htmlspecialchars($docTitle) ?></div>
        <div style="font-style: italic; font-size: 10.5px;" id="copyTypeLabel">Original (Page 1/1)</div>
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
            <div style="font-size: 11px; margin-bottom: 2px;"><?= ($type === 'po') ? 'Vendor / Supplier:' : 'Ordered By (Customer):' ?></div>
            <div style="font-size: 13px; font-weight: 800; margin-bottom: 2px;"><?= htmlspecialchars($clientName) ?></div>
            <div style="font-size: 11px; line-height: 1.25; margin-bottom: 6px;">MIDC Industrial Area, Pune - Maharashtra<br>PIN :411038</div>
            <div><strong>GSTIN :</strong> 27AAAAA1234Z1</div>
            <div><strong>Place of Supply :</strong> Maharashtra [27]</div>
        </div>
        <div class="meta-col">
            <div class="meta-sub-row">
                <div>
                    <div><strong>Order Number:</strong> <?= htmlspecialchars($orderNumber) ?></div>
                    <div><strong>Order Date:</strong> <?= date('d-m-Y') ?></div>
                    <div><strong>Target Dispatch:</strong> <?= date('d-m-Y', strtotime('+14 days')) ?></div>
                </div>
                <div>
                    <div><strong>Status:</strong> CONFIRMED</div>
                    <div><strong>Payment Terms:</strong> Net 30 Days</div>
                    <div><strong>Delivery Route:</strong> Central Hub</div>
                </div>
            </div>
            <div style="border-top: 1px solid #000; padding: 4px 6px; font-size: 10px; background: #f8fafc;">
                <strong>Shipping / Destination Address:</strong><br>
                Central Warehouse, Andheri East, Mumbai, Maharashtra - 400093
            </div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 46%;">Item Name &amp; Technical Specifications</th>
                <th style="width: 10%;">HSN Code</th>
                <th style="width: 8%;">GST</th>
                <th style="width: 8%;">Qty</th>
                <th style="width: 12%;">Unit Rate (₹)</th>
                <th style="width: 12%;">Total Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;">1</td>
                <td>
                    <div style="font-weight: bold;">Industrial Brass Valve 2-inch Standard</div>
                    <div style="font-size: 9.5px; color: #334155;">Heavy Duty A36 Grade Flow Control Valve Assembly</div>
                </td>
                <td style="text-align: center;">848180</td>
                <td style="text-align: center;">18%</td>
                <td style="text-align: right; font-weight: bold;">145 Pcs</td>
                <td style="text-align: right;">1,250.00</td>
                <td style="text-align: right; font-weight: bold;">1,81,250.00</td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;">2</td>
                <td>
                    <div style="font-weight: bold;">High-Purity Copper Wire 1.5 sq mm</div>
                    <div style="font-size: 9.5px; color: #334155;">Insulated 90-meter Rolls (Red / Black)</div>
                </td>
                <td style="text-align: center;">854449</td>
                <td style="text-align: center;">18%</td>
                <td style="text-align: right; font-weight: bold;">40 Coils</td>
                <td style="text-align: right;">1,850.00</td>
                <td style="text-align: right; font-weight: bold;">74,000.00</td>
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
                <td>&nbsp;</td>
                <td style="vertical-align: top; padding-top: 6px; text-align: right; font-weight: bold;">
                    <div style="margin-bottom: 3px;">22,972.50</div>
                    <div>22,972.50</div>
                </td>
            </tr>
        </tbody>
    </table>

    <table class="tax-summary-table">
        <thead>
            <tr>
                <th style="width: 25%;">Taxable Amount</th>
                <th style="width: 15%;">CGST (9%)</th>
                <th style="width: 15%;">SGST (9%)</th>
                <th style="width: 20%;">Total GST (18%)</th>
                <th style="width: 25%; text-align: right;">Total Order Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;">₹2,55,250.00</td>
                <td style="text-align: center; font-weight: bold;">₹22,972.50</td>
                <td style="text-align: center; font-weight: bold;">₹22,972.50</td>
                <td style="text-align: center; font-weight: bold;">₹45,945.00</td>
                <td style="text-align: right; font-size: 14px; font-weight: 900;">₹3,01,195.00</td>
            </tr>
        </tbody>
    </table>

    <div style="border-bottom: 1.5px solid #000; padding: 5px 8px; font-size: 11px; background-color: #fafafa;">
        <strong>Amount Chargeable in Words:</strong> <?= htmlspecialchars(getIndianWords(301195.00)) ?>
    </div>

    <div class="bottom-split-row">
        <div class="bottom-terms-col" id="termsSection">
            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">Commercial Terms &amp; Conditions:</div>
            <div>1. Goods must be dispatched strictly as per the specified technical grades and manufacturer warranties.</div>
            <div>2. Invoice and E-way bill copies must accompany all consignments at warehouse gate entry.</div>
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
        <div style="font-weight: bold; width: 33%;">Consignee / Party Signature</div>
        <div style="text-align: center; font-size: 10px; width: 34%;">Computer Generated Order Voucher</div>
        <div style="text-align: right; font-weight: bold; width: 33%;">Authorized Signatory</div>
    </div>
</div>
</div>
</body>
</html>
