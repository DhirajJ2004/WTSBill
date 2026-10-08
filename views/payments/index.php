<?php
$pageTitle = 'Payments & Receipts - WTSBill ERP';
$pageHeader = 'Payment Collections & Bank Reconciliation';
$currentRoute = 'payments';

require_once __DIR__ . '/../db_helper.php';

use App\Models\Payment;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Banking\Services\PaymentEngine;
use Illuminate\Database\Capsule\Manager as DB;

$companyId = get_current_company_id();
$branchId = get_current_branch_id() ?: 1;

$submitError = null;
$submitSuccess = null;

if (isset($_GET['created'])) {
    $submitSuccess = "Payment #" . htmlspecialchars($_GET['created']) . " recorded and posted successfully!";
}

// -------------------------------------------------------------
// Handle Web Form POST /payments
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $isJson = (strpos($_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
    $input = $isJson ? get_json_input() : $_POST;

    if (empty($input) && php_sapi_name() === 'cli') {
        parse_str(@file_get_contents('php://stdin'), $input);
    }

    if (!$isJson && isset($input['csrf_token']) && !verify_csrf_token()) {
        $submitError = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            if (($input['action_type'] ?? '') === 'save_gateway') {
                \App\Models\SystemSetting::updateOrCreate(
                    ['company_id' => $companyId, 'setting_key' => 'razorpay_key'],
                    ['setting_value' => trim($input['razorpay_key'] ?? '')]
                );
                \App\Models\SystemSetting::updateOrCreate(
                    ['company_id' => $companyId, 'setting_key' => 'razorpay_secret'],
                    ['setting_value' => trim($input['razorpay_secret'] ?? '')]
                );
                \App\Models\SystemSetting::updateOrCreate(
                    ['company_id' => $companyId, 'setting_key' => 'stripe_key'],
                    ['setting_value' => trim($input['stripe_key'] ?? '')]
                );
                $submitSuccess = 'Payment Gateway API keys configured and saved successfully!';
            } else {
                $input['company_id'] = $companyId;
                $input['branch_id'] = $branchId;
                $paymentType = strtoupper($input['payment_type'] ?? 'RECEIPT');
                $partyType = strtoupper($input['party_type'] ?? ($paymentType === 'RECEIPT' ? 'CUSTOMER' : 'SUPPLIER'));

                if ($paymentType === 'RECEIPT' || $partyType === 'CUSTOMER') {
                    $payment = PaymentEngine::processCustomerReceipt($companyId, $input, 'Admin');
                } else {
                    $payment = PaymentEngine::processSupplierPayment($companyId, $input, 'Admin');
                }

                if ($isJson) {
                    response_json([
                        'status' => 'success',
                        'message' => 'Payment recorded and posted successfully',
                        'data' => $payment
                    ], 201);
                } else {
                    $targetUrl = url('/payments?created=' . urlencode($payment->payment_number));
                    if (!headers_sent()) {
                        header('Location: ' . $targetUrl);
                        exit();
                    } else {
                        echo "<script>window.location.href='" . $targetUrl . "';</script>";
                    }
                }
            }
        } catch (\Throwable $e) {
            if ($isJson) {
                response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
            } else {
                $submitError = $e->getMessage();
            }
        }
    }
}

// Query real payments, invoices, purchases, customers, and suppliers
$payments = [];
$openInvoices = [];
$openPurchases = [];
$customers = get_customers();
$suppliers = get_suppliers();

try {
    $payments = Payment::where('company_id', $companyId)
        ->with(['customer', 'supplier', 'allocations.invoice', 'allocations.purchase'])
        ->orderBy('payment_date', 'desc')
        ->orderBy('id', 'desc')
        ->get();

    $openInvoices = Invoice::where('company_id', $companyId)
        ->where('status', 'POSTED')
        ->where('amount_due', '>', 0.01)
        ->with('customer')
        ->orderByDesc('invoice_date')
        ->get();

    $openPurchases = Purchase::where('company_id', $companyId)
        ->where('status', 'POSTED')
        ->where('amount_due', '>', 0.01)
        ->with('supplier')
        ->orderByDesc('purchase_date')
        ->get();
} catch (\Throwable $e) {}

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-money-bill-transfer"></i> Payment Transactions &amp; Receipts</div>
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-outline" onclick="openModal('gatewayModal')"><i class="fa-solid fa-credit-card"></i> Payment Gateway APIs</button>
                    <button class="btn btn-primary" onclick="openModal('recordPaymentModal')"><i class="fa-solid fa-plus"></i> + Record Payment</button>
                </div>
            </div>

            <?php if (!empty($submitSuccess)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px; padding: 12px 18px; background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; color: #065f46; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check" style="font-size: 18px;"></i>
                    <span><?= htmlspecialchars($submitSuccess) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($submitError)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px; padding: 12px 18px; background: #fef2f2; border: 1px solid #ef4444; border-radius: 8px; color: #991b1b; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px;"></i>
                    <span><?= htmlspecialchars($submitError) ?></span>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div style="display: flex; gap: 16px; margin-bottom: 20px;">
                <input type="text" id="paySearch" class="form-control" placeholder="Search payment #, client, ref no..." style="max-width: 320px;" onkeyup="filterPayments()">
                <select id="modeFilter" class="form-control" style="max-width: 200px;" onchange="filterPayments()">
                    <option value="">All Payment Modes</option>
                    <option value="bank">Bank Transfer (NEFT/RTGS)</option>
                    <option value="upi">UPI / QR Code</option>
                    <option value="card">Credit/Debit Card</option>
                    <option value="cheque">Cheque</option>
                    <option value="cash">Cash</option>
                </select>
                <select id="typeFilter" class="form-control" style="max-width: 180px;" onchange="filterPayments()">
                    <option value="">All Types</option>
                    <option value="receipt">Customer Receipts</option>
                    <option value="payment">Supplier Payments</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table" id="payTable">
                    <thead>
                        <tr>
                            <th>Receipt / Payment #</th>
                            <th>Party Name</th>
                            <th>Type</th>
                            <th>Invoice / Bill Ref</th>
                            <th>Payment Date</th>
                            <th>Mode</th>
                            <th>Reference / UTR #</th>
                            <th>Amount</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $pay): 
                            $partyName = ($pay->party_type === 'CUSTOMER') 
                                ? ($pay->customer?->name ?? 'Customer')
                                : ($pay->supplier?->name ?? 'Supplier');
                            $docRef = '-';
                            if ($pay->allocations && $pay->allocations->isNotEmpty()) {
                                $firstAlloc = $pay->allocations->first();
                                $docRef = $firstAlloc->invoice?->invoice_number ?? ($firstAlloc->purchase?->purchase_number ?? '-');
                            }
                        ?>
                            <tr data-mode="<?= strtolower($pay->payment_mode ?? 'upi') ?>" data-type="<?= strtolower($pay->payment_type ?? 'receipt') ?>">
                                <td><strong><?= htmlspecialchars($pay->payment_number) ?></strong></td>
                                <td><strong><?= htmlspecialchars($partyName) ?></strong></td>
                                <td><span class="health-badge <?= $pay->payment_type === 'RECEIPT' ? 'badge-blue' : 'badge-neutral' ?>"><?= htmlspecialchars($pay->payment_type) ?></span></td>
                                <td><code><?= htmlspecialchars($docRef) ?></code></td>
                                <td><?= htmlspecialchars($pay->payment_date ?? date('Y-m-d')) ?></td>
                                <td><span class="badge badge-info"><?= strtoupper(htmlspecialchars($pay->payment_mode ?? 'UPI')) ?></span></td>
                                <td><code><?= htmlspecialchars($pay->reference_number ?? $pay->utr ?? 'N/A') ?></code></td>
                                <td><strong style="color: <?= $pay->payment_type === 'RECEIPT' ? 'var(--success)' : 'var(--danger)' ?>;">₹<?= number_format((float)($pay->amount ?? 0), 2) ?></strong></td>
                                <td class="text-right">
                                    <a href="<?= url('/print-receipt?id=' . $pay->id) ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-print"></i> Receipt PDF</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payments) || (is_object($payments) && $payments->isEmpty())): ?>
                            <tr>
                                <td colspan="9" class="text-center" style="padding: 30px; color: #64748b;">No payment records found. Click "+ Record Payment" to create one.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Record Payment -->
<div class="modal-overlay" id="recordPaymentModal">
    <div class="modal-content" style="max-width: 620px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-receipt text-primary"></i> Record Payment &amp; Settle Invoices</div>
            <button class="modal-close" onclick="closeModal('recordPaymentModal')">&times;</button>
        </div>
        <form method="POST" action="<?= url('/payments') ?>" id="recordPaymentForm">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 12px;">
                <div id="paymentErrorMsg" style="display: none; padding: 10px 14px; background: #fee2e2; border: 1px solid #f87171; border-radius: 6px; color: #991b1b; font-size: 13px;"></div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Payment Type *</label>
                        <select class="form-control" name="payment_type" id="payTypeSelect" onchange="onPaymentTypeChange()">
                            <option value="RECEIPT">Customer Receipt (Inward Collection)</option>
                            <option value="PAYMENT">Supplier Payment (Outward Disbursement)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" name="payment_date" class="form-control" id="paymentDate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <!-- Customer Mode Invoices -->
                <div class="form-group" id="customerInvoiceGroup">
                    <label class="form-label">Match Open Sales Invoice (Optional)</label>
                    <select class="form-control" name="invoice_id" id="invoiceMatch" onchange="autoFillInvoiceAmount(this)">
                        <option value="">-- Direct Customer Payment (No Specific Invoice) --</option>
                        <?php foreach ($openInvoices as $inv): 
                            $due = floatval($inv->amount_due ?: ($inv->grand_total - $inv->amount_paid));
                        ?>
                            <option value="<?= $inv->id ?>" data-amount="<?= $due ?>" data-customer-id="<?= $inv->customer_id ?>"><?= htmlspecialchars($inv->invoice_number) ?> - <?= htmlspecialchars($inv->customer?->name ?? 'Client') ?> (Due: ₹<?= number_format($due, 2) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="customerSelectGroup">
                    <label class="form-label">Customer Account *</label>
                    <select class="form-control" name="customer_id" id="payCustomerId">
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (Bal: ₹<?= number_format((float)($c['current_balance'] ?? 0), 2) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Supplier Mode Purchases -->
                <div class="form-group" id="supplierSelectGroup" style="display: none;">
                    <label class="form-label">Supplier / Vendor *</label>
                    <select class="form-control" name="supplier_id" id="paySupplierId">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (Payable: ₹<?= number_format((float)($s['current_balance'] ?? 0), 2) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Amount (₹) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" id="payAmount" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Mode *</label>
                        <select class="form-control" name="payment_mode" id="paymentMode" required>
                            <option value="BANK_TRANSFER">Bank Transfer (NEFT/RTGS/IMPS)</option>
                            <option value="UPI">UPI / QR Code</option>
                            <option value="CARD">Credit / Debit Card</option>
                            <option value="CHEQUE">Cheque</option>
                            <option value="CASH">Cash</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">UTR / Reference No.</label>
                        <input type="text" name="reference_number" class="form-control" id="payReference" placeholder="e.g. UTR629100234">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Transaction Notes</label>
                        <input type="text" name="notes" class="form-control" id="payNotes" placeholder="Payment remarks...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('recordPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnRecordPayment"><i class="fa-solid fa-check"></i> Record &amp; Post Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Gateway Settings -->
<div class="modal-overlay" id="gatewayModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <div class="modal-title">Payment Gateway API Settings</div>
            <button class="modal-close" onclick="closeModal('gatewayModal')">&times;</button>
        </div>
<!-- Modal: Payment Gateway Settings -->
<div class="modal-overlay" id="gatewayModal">
    <div class="modal-content" style="max-width: 520px;">
        <form method="POST" action="<?= url('/payments') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="save_gateway">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-credit-card text-primary"></i> Payment Gateway API Configuration</div>
                <button type="button" class="modal-close" onclick="closeModal('gatewayModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 16px;">
                    <h4 style="font-weight: 600; margin-bottom: 8px;"><i class="fa-solid fa-bolt text-warning"></i> Razorpay Integration</h4>
                    <div class="form-group">
                        <label class="form-label">Razorpay Key ID</label>
                        <input type="text" name="razorpay_key" class="form-control" value="<?= htmlspecialchars(\App\Models\SystemSetting::where('company_id', $companyId)->where('setting_key', 'razorpay_key')->value('setting_value') ?? 'rzp_live_9831920381023') ?>" placeholder="rzp_live_...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Razorpay Key Secret</label>
                        <input type="password" name="razorpay_secret" class="form-control" value="<?= htmlspecialchars(\App\Models\SystemSetting::where('company_id', $companyId)->where('setting_key', 'razorpay_secret')->value('setting_value') ?? '••••••••••••••••') ?>" placeholder="••••••••••••••••">
                    </div>
                </div>
                <div>
                    <h4 style="font-weight: 600; margin-bottom: 8px;"><i class="fa-brands fa-stripe text-primary"></i> Stripe API Integration</h4>
                    <div class="form-group">
                        <label class="form-label">Publishable Key</label>
                        <input type="text" name="stripe_key" class="form-control" value="<?= htmlspecialchars(\App\Models\SystemSetting::where('company_id', $companyId)->where('setting_key', 'stripe_key')->value('setting_value') ?? 'pk_live_51M000000000000000') ?>" placeholder="pk_live_...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('gatewayModal')">Close</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Keys</button>
            </div>
        </form>
    </div>
</div>

<script>
function onPaymentTypeChange() {
    let type = document.getElementById('payTypeSelect').value;
    let custInvGroup = document.getElementById('customerInvoiceGroup');
    let custSelGroup = document.getElementById('customerSelectGroup');
    let suppSelGroup = document.getElementById('supplierSelectGroup');

    if (type === 'RECEIPT') {
        custInvGroup.style.display = 'block';
        custSelGroup.style.display = 'block';
        suppSelGroup.style.display = 'none';
        document.getElementById('payCustomerId').required = true;
        document.getElementById('paySupplierId').required = false;
    } else {
        custInvGroup.style.display = 'none';
        custSelGroup.style.display = 'none';
        suppSelGroup.style.display = 'block';
        document.getElementById('payCustomerId').required = false;
        document.getElementById('paySupplierId').required = true;
    }
}

function autoFillInvoiceAmount(select) {
    let opt = select.options[select.selectedIndex];
    if (opt && opt.value) {
        let amt = opt.getAttribute('data-amount') || '';
        let custId = opt.getAttribute('data-customer-id') || '';
        document.getElementById('payAmount').value = amt;
        if (custId) {
            document.getElementById('payCustomerId').value = custId;
        }
    }
}

function filterPayments() {
    let q = document.getElementById('paySearch').value.toLowerCase();
    let mode = document.getElementById('modeFilter').value.toLowerCase();
    let type = document.getElementById('typeFilter').value.toLowerCase();
    let rows = document.querySelectorAll('#payTable tbody tr');
    
    rows.forEach(r => {
        let text = r.innerText.toLowerCase();
        let rMode = r.getAttribute('data-mode') || '';
        let rType = r.getAttribute('data-type') || '';
        
        let matchText = !q || text.includes(q);
        let matchMode = !mode || rMode.includes(mode);
        let matchType = !type || rType === type;
        
        if (matchText && matchMode && matchType) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
