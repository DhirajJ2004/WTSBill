<?php
$pageTitle = 'Create Quotation - WTSBill ERP';
$pageHeader = 'New Proposal / Price Estimate';
$currentRoute = 'quotations';

require_once __DIR__ . '/../db_helper.php';
use Illuminate\Database\Capsule\Manager as DB;

$customers = get_customers();
$products = get_products();

// Next Quote Number preview
$companyId = get_current_company_id();
$branchId = get_current_branch_id() ?: 1;
$fy = get_current_financial_year() ?: '2026-27';
$nextQuoteNumber = \App\Services\DocumentNumberService::previewNextNumber($companyId, $branchId, $fy, 'QUOTATION');

$submitError = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['csrf_token']) && !verify_csrf_token()) {
        $submitError = 'Invalid or expired security token. Please refresh the page and try again.';
    } else {
        DB::beginTransaction();
        try {
            $companyId = get_current_company_id();
            $branchId = get_current_branch_id() ?: 1;
            $fy = get_current_financial_year() ?: '2026-27';
            
            $customerId = intval($_POST['customer_id'] ?? 0);
            if ($customerId <= 0) {
                throw new Exception("Please select a valid customer.");
            }
            
            $customer = DB::table('customers')->where('id', $customerId)->where('company_id', $companyId)->first();
            if (!$customer) {
                throw new Exception("Please select a valid customer belonging to the active company.");
            }

            $quoteDate = !empty($_POST['quotation_date']) ? $_POST['quotation_date'] : date('Y-m-d');
            $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : date('Y-m-d', strtotime('+30 days'));

            $itemIds = $_POST['item_id'] ?? [];
            $itemHsns = $_POST['item_hsn'] ?? [];
            $itemQtys = $_POST['item_qty'] ?? [];
            $itemRates = $_POST['item_rate'] ?? [];

            $subTotal = 0;
            $taxTotal = 0;
            $cgstTotal = 0;
            $sgstTotal = 0;
            $igstTotal = 0;
            $itemsData = [];

            for ($i = 0; $i < count($itemIds); $i++) {
                $pid = intval($itemIds[$i] ?? 0);
                if ($pid <= 0) continue;

                $prod = DB::table('products')->where('id', $pid)->where('company_id', $companyId)->first();
                if (!$prod) {
                    throw new Exception("Selected product #{$pid} was not found or is unauthorized.");
                }

                $qty = floatval($itemQtys[$i] ?? 0);
                if ($qty <= 0) {
                    throw new Exception("Quantity for item '{$prod->name}' must be greater than zero.");
                }

                $rate = floatval($itemRates[$i] ?? 0);
                if ($rate < 0) {
                    throw new Exception("Unit price for item '{$prod->name}' cannot be negative.");
                }

                $amt = round($qty * $rate, 2);
                $subTotal += $amt;

                $pTaxRate = floatval($prod->tax_rate ?: ($prod->gst_rate ?: 18));
                $halfRate = round($pTaxRate / 2, 2);
                $lineTax = round($amt * ($pTaxRate / 100.0), 2);
                $lineCgst = round($lineTax / 2, 2);
                $lineSgst = round($lineTax - $lineCgst, 2);

                $taxTotal += $lineTax;
                $cgstTotal += $lineCgst;
                $sgstTotal += $lineSgst;

                $itemsData[] = [
                    'company_id' => $companyId,
                    'product_id' => $pid,
                    'item_name' => $prod->name,
                    'hsn_sac' => !empty($itemHsns[$i]) ? $itemHsns[$i] : ($prod->hsn_sac ?: '998313'),
                    'unit' => $prod->unit ?: 'Pcs',
                    'quantity' => $qty,
                    'unit_price' => $rate,
                    'discount_rate' => 0.00,
                    'discount_amount' => 0.00,
                    'taxable_value' => $amt,
                    'gst_rate' => $pTaxRate,
                    'cgst_rate' => $halfRate,
                    'cgst_amount' => $lineCgst,
                    'sgst_rate' => $halfRate,
                    'sgst_amount' => $lineSgst,
                    'igst_rate' => 0.00,
                    'igst_amount' => 0.00,
                    'total_amount' => round($amt + $lineTax, 2),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }

            if (empty($itemsData)) {
                throw new Exception("Please add at least one line item to the quotation.");
            }

            $subTotal = round($subTotal, 2);
            $taxTotal = round($taxTotal, 2);
            $unroundedGrand = $subTotal + $taxTotal;
            $roundedGrand = round($unroundedGrand);
            $roundOff = round($roundedGrand - $unroundedGrand, 2);
            $grandTotal = $roundedGrand;

            // Atomically generate quotation number
            $quoteNumber = \App\Services\DocumentNumberService::generateNextNumber($companyId, $branchId, $fy, 'QUOTATION');

            $newQuoteId = DB::table('quotations')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'quotation_number' => $quoteNumber,
                'quotation_date' => $quoteDate,
                'valid_until' => $validUntil,
                'reference_no' => $_POST['subject'] ?? null,
                'billing_address' => $customer->address_line1,
                'shipping_address' => $customer->address_line1,
                'sub_total' => $subTotal,
                'discount_amount' => 0.00,
                'taxable_value' => $subTotal,
                'cgst_amount' => $cgstTotal,
                'sgst_amount' => $sgstTotal,
                'igst_amount' => $igstTotal,
                'total_tax' => $taxTotal,
                'round_off' => $roundOff,
                'grand_total' => $grandTotal,
                'status' => 'sent',
                'notes' => $_POST['notes'] ?? '',
                'terms' => $_POST['terms'] ?? '1. Quotation valid for 30 days. 2. Payment terms 50% advance.',
                'converted_to_invoice' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            foreach ($itemsData as &$it) {
                $it['quotation_id'] = $newQuoteId;
                DB::table('quotation_items')->insert($it);
            }

            DB::commit();

            header('Location: ' . url('/quotations?created=' . urlencode($quoteNumber)));
            exit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $submitError = $e->getMessage();
        }
    }
}

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <div class="card" style="max-width: 1000px; margin: 0 auto;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-file-signature"></i> Create Commercial Proposal Quotation</div>
                <a href="<?= url('/quotations') ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to Quotations</a>
            </div>

            <?php if (!empty($submitError)): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <strong>Error:</strong> <?= htmlspecialchars($submitError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('/create-quotation') ?>" id="quoteForm">
                <?= csrf_field() ?>
                <div class="form-row" style="grid-template-columns: 2fr 1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label">Select Customer / Client</label>
                        <select class="form-control" name="customer_id" id="customerSelect" required>
                            <option value="">-- Choose Client --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['gstin'] ?: 'B2C') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quotation Date</label>
                        <input type="date" name="quotation_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valid Until</label>
                        <input type="date" name="valid_until" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                </div>

                <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label">Quotation # (Auto)</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($nextQuoteNumber) ?>" readonly style="background: #f1f5f9;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subject / Purpose</label>
                        <input type="text" name="subject" class="form-control" placeholder="Annual ERP Maintenance / Cloud Hosting">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expected Conversion Probability</label>
                        <select class="form-control" name="probability">
                            <option value="high">High (> 80%)</option>
                            <option value="medium" selected>Medium (50 - 80%)</option>
                            <option value="low">Low (< 50%)</option>
                        </select>
                    </div>
                </div>

                <!-- Scope items table -->
                <div style="margin-top: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h4 style="font-weight: 600;">Proposed Scope of Work & Items</h4>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addQuoteRow()"><i class="fa-solid fa-plus"></i> Add Item Row</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table" id="quoteItemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">Service / Deliverable Description</th>
                                    <th style="width: 15%;">SAC/HSN</th>
                                    <th style="width: 10%;">Qty</th>
                                    <th style="width: 15%;">Unit Rate (₹)</th>
                                    <th style="width: 15%;">Estimated (₹)</th>
                                    <th style="width: 5%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select class="form-control item-select" name="item_id[]" onchange="updateItemDetails(this)" required>
                                            <option value="">-- Select Service --</option>
                                            <?php foreach ($products as $p): ?>
                                                <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?? $p['selling_price'] ?? 0 ?>" data-hsn="<?= htmlspecialchars($p['hsn_sac'] ?? '') ?>"><?= htmlspecialchars($p['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="text" class="form-control item-hsn" name="item_hsn[]" placeholder="998313"></td>
                                    <td><input type="number" class="form-control item-qty" name="item_qty[]" value="1" min="1" oninput="calculateTotals()"></td>
                                    <td><input type="number" class="form-control item-rate" name="item_rate[]" value="0.00" step="0.01" oninput="calculateTotals()"></td>
                                    <td><input type="text" class="form-control item-amount" value="0.00" readonly style="background: #f8fafc;"></td>
                                    <td><button type="button" class="btn btn-danger btn-sm" onclick="removeQuoteRow(this)">&times;</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quote Summary Panel -->
                <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                    <div style="width: 100%; max-width: 380px; background: var(--bg-main); padding: 18px; border-radius: var(--radius-md);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>Subtotal (Estimated):</span>
                            <strong id="quoteSubtotal">₹0.00</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; color: var(--text-muted);">
                            <span>GST (18% Estimate):</span>
                            <strong id="quoteTax">₹0.00</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; border-top: 2px dashed var(--border-color); padding-top: 10px; color: var(--primary);">
                            <span>Total Estimated Value:</span>
                            <span id="quoteGrandTotal">₹0.00</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <a href="<?= url('/quotations') ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Save & Send Quotation</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
function updateItemDetails(select) {
    let row = select.closest('tr');
    let option = select.options[select.selectedIndex];
    let price = option.getAttribute('data-price') || 0;
    let hsn = option.getAttribute('data-hsn') || '';
    
    row.querySelector('.item-rate').value = parseFloat(price).toFixed(2);
    row.querySelector('.item-hsn').value = hsn;
    calculateTotals();
}

function addQuoteRow() {
    let tbody = document.querySelector('#quoteItemsTable tbody');
    let firstRow = tbody.querySelector('tr');
    let newRow = firstRow.cloneNode(true);
    newRow.querySelector('.item-select').selectedIndex = 0;
    newRow.querySelector('.item-hsn').value = '';
    newRow.querySelector('.item-qty').value = 1;
    newRow.querySelector('.item-rate').value = '0.00';
    newRow.querySelector('.item-amount').value = '0.00';
    tbody.appendChild(newRow);
}

function removeQuoteRow(btn) {
    let tbody = document.querySelector('#quoteItemsTable tbody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        calculateTotals();
    }
}

function calculateTotals() {
    let rows = document.querySelectorAll('#quoteItemsTable tbody tr');
    let subtotal = 0;
    
    rows.forEach(r => {
        let qty = parseFloat(r.querySelector('.item-qty').value) || 0;
        let rate = parseFloat(r.querySelector('.item-rate').value) || 0;
        let amt = qty * rate;
        r.querySelector('.item-amount').value = amt.toFixed(2);
        subtotal += amt;
    });
    
    let tax = subtotal * 0.18;
    let grandTotal = subtotal + tax;
    
    document.getElementById('quoteSubtotal').innerText = '₹' + subtotal.toFixed(2);
    document.getElementById('quoteTax').innerText = '₹' + tax.toFixed(2);
    document.getElementById('quoteGrandTotal').innerText = '₹' + grandTotal.toFixed(2);
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
