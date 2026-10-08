<?php
require_once __DIR__ . '/../db_helper.php';
use Illuminate\Database\Capsule\Manager as DB;

$paymentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$authData = \App\Services\DocumentPrintService::authorizeAndFetch('payments', $paymentId, 'Payment Receipt', url('/payments'));
$payment = $authData['document'];
$company = $authData['company'];
$companyDetails = $authData['companyDetails'];
$bankDetails = $authData['bankDetails'];
$logoDataUri = $authData['logoUri'];

$customer = null;
$supplier = null;
if (!empty($payment->customer_id)) {
    $customer = DB::table('customers')->where('id', $payment->customer_id)->first();
}
if (!empty($payment->supplier_id)) {
    $supplier = DB::table('suppliers')->where('id', $payment->supplier_id)->first();
}

$invoice = null;
if (!empty($payment->invoice_id)) {
    $invoice = DB::table('invoices')->where('id', $payment->invoice_id)->first();
}

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

$receiptNumber = $payment->payment_number ?? ('REC-' . $payment->id);
$receiptDate = date('d-m-Y', strtotime($payment->payment_date ?? ($payment->created_at ?? date('Y-m-d'))));
$amount = floatval($payment->amount ?? 0);
$paymentMode = $payment->payment_mode ?? 'Bank Transfer';
$referenceNo = $payment->reference_number ?? ($payment->reference_no ?? '-');
$matchedInvoice = $invoice->invoice_number ?? ($payment->invoice_number ?? 'Direct Settlement');

$partyName = $customer->name ?? ($supplier->name ?? 'Direct Counterparty');
$partyAddress = ($customer->address_line1 ?? ($supplier->address_line1 ?? '')) . (!empty($customer->city ?? $supplier->city) ? ' - ' . ($customer->city ?? $supplier->city) : '');
$partyGstin = $customer->gstin ?? ($supplier->gstin ?? 'URP');

$docType = ($payment->payment_type ?? 'RECEIPT') === 'PAYMENT' ? 'SUPPLIER PAYMENT VOUCHER' : 'PAYMENT RECEIPT';
$docNumber = $receiptNumber;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RECEIPT - <?= htmlspecialchars($receiptNumber) ?> - WIS TECHNOSAVVY PVT LTD</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { background-color: #f1f5f9; color: #000; font-size: 11px; line-height: 1.3; padding: 20px; }
        .invoice-sheet { width: 100%; max-width: 210mm; min-height: 240mm; margin: 0 auto; background: #fff; border: 1.5px solid #000; padding: 0; position: relative; box-shadow: 0 4px 12px rgba(0,0,0,0.12); box-sizing: border-box; }
        .top-tax-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1.5px solid #000; padding: 4px 8px; font-size: 11px; }
        .tax-invoice-badge { font-size: 14px; font-weight: 900; letter-spacing: 0.5px; text-decoration: underline; text-align: center; }
        .company-header-block { display: flex; align-items: center; padding: 8px 12px; border-bottom: 1.5px solid #000; position: relative; }
        .company-logo-area { width: 90px; height: 90px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; margin-right: 12px; }
        .company-logo-area img { width: 82px; height: 82px; object-fit: contain; border-radius: 50%; }
        .company-info-center { flex: 1; text-align: center; padding-right: 90px; }
        .company-name-title { font-size: 19px; font-weight: 900; letter-spacing: 0.5px; margin-bottom: 2px; }
        .buyer-meta-grid { display: grid; grid-template-columns: 50% 50%; border-bottom: 1.5px solid #000; }
        .buyer-col { border-right: 1.5px solid #000; padding: 12px 16px; }
        .amount-highlight-box { padding: 14px; background: #f0fdf4; border-bottom: 1.5px solid #000; text-align: center; }
        .signatures-grid { display: grid; grid-template-columns: 35% 30% 35%; padding: 10px 16px; min-height: 80px; align-items: flex-end; font-size: 10.5px; }
        .stamp-box-area { text-align: right; }

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
        <div class="tax-invoice-badge">OFFICIAL PAYMENT RECEIPT</div>
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

    <div class="amount-highlight-box">
        <div style="font-size: 12px; font-weight: 700; color: #166534; text-transform: uppercase; margin-bottom: 4px;">Net Amount Received</div>
        <div style="font-size: 32px; font-weight: 900; color: #15803d;">₹<?= number_format($amount, 2) ?></div>
    </div>

    <div class="buyer-meta-grid">
        <div class="buyer-col">
            <div style="font-size: 11px; margin-bottom: 4px; text-decoration: underline; font-weight: bold;">Received With Thanks From:</div>
            <div style="font-size: 14px; font-weight: 800; margin-bottom: 4px;"><?= htmlspecialchars($partyName) ?></div>
            <div style="font-size: 11.5px; line-height: 1.35; margin-bottom: 8px;"><?= $partyAddress ?></div>
            <div><strong>GSTIN / Tax ID:</strong> <?= htmlspecialchars($partyGstin) ?></div>
        </div>
        <div style="padding: 12px 16px; font-size: 11.5px; line-height: 1.6;">
            <div><strong>Receipt No:</strong> <?= htmlspecialchars($receiptNumber) ?></div>
            <div><strong>Receipt Date:</strong> <?= htmlspecialchars($receiptDate) ?></div>
            <div><strong>Payment Mode:</strong> <?= htmlspecialchars($paymentMode) ?></div>
            <div><strong>Reference / UTR #:</strong> <?= htmlspecialchars($referenceNo) ?></div>
            <div><strong>Settled Against Invoice:</strong> <code><?= htmlspecialchars($matchedInvoice) ?></code></div>
        </div>
    </div>

    <div style="padding: 12px 16px; border-bottom: 1.5px solid #000; font-size: 11px;">
        <strong>Amount In Words:</strong> <?= ucwords(getIndianWords($amount)) ?>
    </div>

    <div class="signatures-grid">
        <div><strong>Payer Signature / Seal</strong></div>
        <div style="text-align: center; font-size: 10px; color: #1e293b;">Computer Generated Payment Voucher</div>
        <div class="stamp-box-area" id="stampSection">
            <div style="font-weight: bold;">For <?= htmlspecialchars($companyName) ?></div>
            <div style="display: flex; justify-content: flex-end; align-items: center; margin: 4px 0;">
                <svg width="120" height="70" viewBox="0 0 120 70">
                    <circle cx="85" cy="35" r="28" fill="none" stroke="#2563eb" stroke-width="2" stroke-dasharray="2.5,1.5"/>
                    <circle cx="85" cy="35" r="24" fill="none" stroke="#2563eb" stroke-width="1"/>
                    <text x="85" y="32" font-size="6" font-weight="900" fill="#2563eb" text-anchor="middle">WIS TECHNOSAVVY</text>
                    <text x="85" y="42" font-size="8" font-weight="900" fill="#2563eb" text-anchor="middle">PUNE</text>
                    <path d="M 15,44 Q 30,16 42,34 T 60,20 Q 72,50 90,30 T 115,22" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <div style="font-weight: bold;">Authorized Cashier / Accountant</div>
        </div>
    </div>
</div>
</div>
</body>
</html>
