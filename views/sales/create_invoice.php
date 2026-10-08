<?php
$pageTitle = 'Create Sales Invoice - WTSBill ERP';
$pageHeader = 'Tax Invoice Billing Studio';
$currentRoute = 'create-invoice';

require_once __DIR__ . '/../db_helper.php';
use Illuminate\Database\Capsule\Manager as DB;

$companyId = get_current_company_id();
$branchId = get_current_branch_id() ?: 1;
$fy = get_current_financial_year() ?: '2026-27';

$customers = get_customers();
$products = get_products();

// Fetch Warehouses for this company
$warehouses = DB::table('warehouses')
    ->where('company_id', $companyId)
    ->whereNull('deleted_at')
    ->orderByDesc('is_primary')
    ->get();

$primaryWarehouseId = $warehouses->where('is_primary', 1)->first()?->id ?? ($warehouses->first()?->id ?? 1);
$selectedWarehouseId = $primaryWarehouseId;

// Query Stock Balances for the company to provide real-time warehouse-specific stock
$stockBalances = DB::table('stock_balances')
    ->where('company_id', $companyId)
    ->get();

$stockByWhProd = [];
foreach ($stockBalances as $sb) {
    $stockByWhProd[$sb->warehouse_id][$sb->product_id] = floatval($sb->available_quantity ?: $sb->quantity);
}

// Company negative stock default
$companyRecord = DB::table('companies')->where('id', $companyId)->first();
$companyAllowNeg = (bool)($companyRecord->allow_negative_stock ?? false);

// Preview next sequential invoice number scoped by Company, Branch, FY
$nextInvNumber = \App\Services\DocumentNumberService::previewNextNumber($companyId, $branchId, $fy, 'INVOICE');
$selectedCustomerId = 0;
$sourceQuote = null;
$invoiceDate = date('Y-m-d');
$dueDate = date('Y-m-d', strtotime('+15 days'));
$selectedInvoiceType = 'tax_invoice';
$selectedTaxMode = 'intra';
$allowNegativeStock = $companyAllowNeg;
$notes = '';
$terms = '';
$savedItems = [];

try {
    if (!empty($_GET['from_quote'])) {
        $quoteQuery = DB::table('quotations')->where('company_id', $companyId);
        if (is_numeric($_GET['from_quote'])) {
            $quoteQuery->where(function($q) {
                $q->where('id', intval($_GET['from_quote']))
                  ->orWhere('quotation_number', $_GET['from_quote']);
            });
        } else {
            $quoteQuery->where('quotation_number', $_GET['from_quote']);
        }
        $sourceQuote = $quoteQuery->first();
        if ($sourceQuote) {
            $selectedCustomerId = $sourceQuote->customer_id;
            $qItems = DB::table('quotation_items')->where('quotation_id', $sourceQuote->id)->get();
            if ($qItems->isNotEmpty() && empty($savedItems)) {
                $savedItems = [];
                foreach ($qItems as $qi) {
                    $savedItems[] = [
                        'product_id' => $qi->product_id,
                        'item_name' => $qi->item_name ?? '',
                        'hsn_sac' => $qi->hsn_sac ?? '',
                        'quantity' => floatval($qi->quantity),
                        'unit_price' => floatval($qi->unit_price),
                        'discount_rate' => floatval($qi->discount_rate ?? 0),
                        'tax_rate' => floatval($qi->gst_rate ?? 18),
                    ];
                }
            }
        }
    }
} catch (\Throwable $e) {}

// Handle Form Submission
$submitError = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['csrf_token']) && !verify_csrf_token()) {
        $submitError = 'Invalid or expired security token. Please refresh the page and try again.';
    } else {
        try {
            $input = !empty($_POST) ? $_POST : get_json_input();
            if (empty($input) && php_sapi_name() === 'cli') {
                parse_str(@file_get_contents('php://stdin'), $input);
            }

            $selectedCustomerId = intval($input['customer_id'] ?? 0);
            $invoiceDate = !empty($input['invoice_date']) ? $input['invoice_date'] : date('Y-m-d');
            $dueDate = !empty($input['due_date']) ? $input['due_date'] : date('Y-m-d', strtotime('+15 days'));
            $selectedWarehouseId = intval($input['warehouse_id'] ?? $primaryWarehouseId);
            $selectedInvoiceType = $input['invoice_type'] ?? 'tax_invoice';
            $selectedTaxMode = $input['tax_mode'] ?? 'intra';
            $allowNegativeStock = !empty($input['allow_negative_stock']);
            $notes = $input['notes'] ?? '';
            $terms = $input['terms_and_conditions'] ?? '';

            $itemIds = $input['item_id'] ?? [];
            $itemNames = $input['item_name'] ?? [];
            $itemHsns = $input['item_hsn'] ?? [];
            $itemQtys = $input['item_qty'] ?? [];
            $itemRates = $input['item_rate'] ?? [];
            $itemDiscs = $input['item_disc'] ?? [];
            $itemTaxRates = $input['item_tax_rate'] ?? [];

            $items = [];
            $savedItems = [];
            for ($i = 0; $i < count($itemIds); $i++) {
                $pid = intval($itemIds[$i] ?? 0);
                if ($pid <= 0) continue;
                $row = [
                    'product_id' => $pid,
                    'item_name' => $itemNames[$i] ?? '',
                    'hsn_sac' => $itemHsns[$i] ?? '',
                    'quantity' => floatval($itemQtys[$i] ?? 1),
                    'unit_price' => floatval($itemRates[$i] ?? 0),
                    'discount_rate' => floatval($itemDiscs[$i] ?? 0),
                    'tax_rate' => floatval($itemTaxRates[$i] ?? 18),
                ];
                $items[] = $row;
                $savedItems[] = $row;
            }

            $placeOfSupply = ($selectedTaxMode === 'inter') ? '29' : '27';

            $invoicePayload = [
                'customer_id' => $selectedCustomerId,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'warehouse_id' => $selectedWarehouseId,
                'allow_negative_stock' => $allowNegativeStock,
                'place_of_supply' => $placeOfSupply,
                'status' => 'POSTED',
                'items' => $items,
                'notes' => $notes,
                'terms_and_conditions' => $terms,
            ];

            $res = \App\Services\InvoiceService::createInvoice($invoicePayload, $companyId, $branchId);
            if (!$res['success']) {
                throw new Exception($res['message']);
            }

            $createdInvNumber = $res['data']->invoice_number;
            header('Location: ' . url('/invoices?created=' . urlencode($createdInvNumber)));
            exit();
        } catch (\Throwable $e) {
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
        <div class="card" style="max-width: 1050px; margin: 0 auto;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-file-invoice"></i> Create New GST Tax Invoice</div>
                <a href="<?= url('/invoices') ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to Invoices</a>
            </div>

            <?php if (!empty($submitError)): ?>
                <?php
                $matched = preg_match("/Insufficient stock for product '([^']+)' in warehouse #(\d+)\. Available: ([0-9.]+), Requested: ([0-9.]+)/", $submitError, $matches);
                $prodName = $matched ? $matches[1] : '';
                $availQty = $matched ? floatval($matches[3]) : 0;
                $reqQty = $matched ? floatval($matches[4]) : 0;
                ?>
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid var(--danger); color: var(--danger); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px; margin-top: 2px;"></i>
                            <div>
                                <strong style="font-size: 14px;">Stock Limitation:</strong> <?= htmlspecialchars($submitError) ?>
                                <?php if ($matched): ?>
                                    <div style="font-size: 12px; margin-top: 4px; color: #475569;">
                                        Select an action to proceed with this invoice:
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($matched): ?>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-sm btn-outline" style="border-color: var(--danger); color: var(--danger); background: white;" onclick="applyStockAdjustment(<?= $availQty ?>)">
                                    <i class="fa-solid fa-compress"></i> Adjust Qty to <?= $availQty ?>
                                </button>
                                <button type="button" class="btn btn-sm" style="background: #2563eb; color: #ffffff;" onclick="forceAllowNegativeAndSubmit()">
                                    <i class="fa-solid fa-bolt"></i> Enable Oversell & Submit
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('/create-invoice') ?>" id="invoiceForm">
                <?= csrf_field() ?>
                
                <div class="form-row" style="grid-template-columns: 2fr 1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label">Select Client / Customer</label>
                        <select class="form-control" name="customer_id" id="customerSelect" required onchange="handleCustomerChange(this)">
                            <option value="">-- Choose Client --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" 
                                    data-gstin="<?= htmlspecialchars($c['gstin'] ?? '') ?>" 
                                    data-state-code="<?= htmlspecialchars($c['state_code'] ?? '') ?>"
                                    data-customer-type="<?= htmlspecialchars($c['customer_type'] ?? 'REGULAR') ?>"
                                    data-pos="<?= htmlspecialchars($c['place_of_supply'] ?? '') ?>"
                                    <?= $c['id'] == $selectedCustomerId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['gstin'] ?: 'B2C') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invoice Date</label>
                        <input type="date" name="invoice_date" class="form-control" value="<?= htmlspecialchars($invoiceDate) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($dueDate) ?>">
                    </div>
                </div>

                <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label">Invoice Number (FY Scoped)</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($nextInvNumber) ?>" readonly style="background: #f1f5f9;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dispatch Warehouse</label>
                        <select class="form-control" name="warehouse_id" id="warehouseSelect" onchange="onWarehouseChange()">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh->id ?>" <?= $wh->id == $selectedWarehouseId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($wh->name) ?><?= $wh->is_primary ? ' (Primary)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invoice Type</label>
                        <select class="form-control" name="invoice_type">
                            <option value="tax_invoice" <?= $selectedInvoiceType === 'tax_invoice' ? 'selected' : '' ?>>GST Tax Invoice</option>
                            <option value="proforma" <?= $selectedInvoiceType === 'proforma' ? 'selected' : '' ?>>Proforma Invoice</option>
                            <option value="recurring" <?= $selectedInvoiceType === 'recurring' ? 'selected' : '' ?>>Recurring Service Invoice</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tax Split Mode</label>
                        <select class="form-control" id="taxMode" name="tax_mode" onchange="calculateTotals()">
                            <option value="intra" <?= $selectedTaxMode === 'intra' ? 'selected' : '' ?>>Intra-State Supply (CGST + SGST)</option>
                            <option value="inter" <?= $selectedTaxMode === 'inter' ? 'selected' : '' ?>>Inter-State Supply (IGST)</option>
                        </select>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div style="margin-top: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                        <h4 style="font-weight: 600; margin: 0;">Billable Goods, Products & Services</h4>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; user-select: none; background: #f8fafc; padding: 6px 12px; border-radius: var(--radius-md); border: 1px solid #cbd5e1;">
                                <input type="checkbox" name="allow_negative_stock" id="allowNegativeStock" value="1" <?= $allowNegativeStock ? 'checked' : '' ?> onchange="calculateTotals(); validateAllRowStocks();">
                                <span style="font-weight: 500; color: #1e293b;"><i class="fa-solid fa-box-open text-primary"></i> Allow negative stock / overselling</span>
                            </label>
                            <button type="button" class="btn btn-outline btn-sm" onclick="addInvRow()"><i class="fa-solid fa-plus"></i> Add Line Entry</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table" id="invItemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 32%;">Service / Product Description</th>
                                    <th style="width: 11%;">SAC/HSN</th>
                                    <th style="width: 10%;">Qty</th>
                                    <th style="width: 13%;">Unit Rate (₹)</th>
                                    <th style="width: 9%;">Disc (%)</th>
                                    <th style="width: 10%;">GST Rate</th>
                                    <th style="width: 11%;">Amount (₹)</th>
                                    <th style="width: 4%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rowsToRender = !empty($savedItems) ? $savedItems : [
                                    ['product_id' => 0, 'item_name' => '', 'hsn_sac' => '', 'quantity' => 1, 'unit_price' => 0.00, 'discount_rate' => 0, 'tax_rate' => 18]
                                ];
                                foreach ($rowsToRender as $rIdx => $rItem):
                                    $currPid = $rItem['product_id'];
                                ?>
                                    <tr>
                                        <td>
                                            <select class="form-control item-select" name="item_id[]" onchange="updateInvItemDetails(this)" required>
                                                <option value="">-- Select Product / Service --</option>
                                                <?php foreach ($products as $p): 
                                                    $pTax = floatval($p['tax_rate'] ?? ($p['gst_rate'] ?? 18));
                                                    $isService = strtoupper($p['type'] ?? '') === 'service' || strtoupper($p['product_type'] ?? '') === 'SERVICE';
                                                    $pStock = $stockByWhProd[$selectedWarehouseId][$p['id']] ?? floatval($p['current_stock'] ?? 0);
                                                    $whJson = htmlspecialchars(json_encode(array_map(function($wh) use ($stockByWhProd, $p) {
                                                        return $stockByWhProd[$wh->id][$p['id']] ?? floatval($p['current_stock'] ?? 0);
                                                    }, $warehouses->keyBy('id')->all())), ENT_QUOTES, 'UTF-8');
                                                ?>
                                                    <option value="<?= $p['id'] ?>" 
                                                        data-name="<?= htmlspecialchars($p['name']) ?>" 
                                                        data-price="<?= $p['price'] ?? 0 ?>" 
                                                        data-hsn="<?= htmlspecialchars($p['hsn_sac'] ?? '') ?>"
                                                        data-tax-rate="<?= $pTax ?>"
                                                        data-is-service="<?= $isService ? '1' : '0' ?>"
                                                        data-stock="<?= $pStock ?>"
                                                        data-wh-stocks='<?= $whJson ?>'
                                                        <?= $p['id'] == $currPid ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($p['name']) ?> <?= $isService ? '(Service | ' . $pTax . '% GST)' : '(Avail: ' . $pStock . ' | ' . $pTax . '% GST)' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" class="item-name" name="item_name[]" value="<?= htmlspecialchars($rItem['item_name']) ?>">
                                            <div class="item-stock-hint" style="font-size: 11px; margin-top: 4px; display: none;"></div>
                                        </td>
                                        <td><input type="text" class="form-control item-hsn" name="item_hsn[]" value="<?= htmlspecialchars($rItem['hsn_sac']) ?>" placeholder="84818020"></td>
                                        <td><input type="number" class="form-control item-qty" name="item_qty[]" value="<?= htmlspecialchars($rItem['quantity']) ?>" min="1" step="any" oninput="calculateTotals(); validateRowStock(this);"></td>
                                        <td><input type="number" class="form-control item-rate" name="item_rate[]" value="<?= htmlspecialchars(number_format(floatval($rItem['unit_price']), 2, '.', '')) ?>" step="0.01" oninput="calculateTotals()"></td>
                                        <td><input type="number" class="form-control item-disc" name="item_disc[]" value="<?= htmlspecialchars($rItem['discount_rate']) ?>" min="0" max="100" oninput="calculateTotals()"></td>
                                        <td>
                                            <select class="form-control item-tax-rate" name="item_tax_rate[]" onchange="calculateTotals()">
                                                <option value="0" <?= $rItem['tax_rate'] == 0 ? 'selected' : '' ?>>0%</option>
                                                <option value="5" <?= $rItem['tax_rate'] == 5 ? 'selected' : '' ?>>5%</option>
                                                <option value="12" <?= $rItem['tax_rate'] == 12 ? 'selected' : '' ?>>12%</option>
                                                <option value="18" <?= $rItem['tax_rate'] == 18 ? 'selected' : '' ?>>18%</option>
                                                <option value="28" <?= $rItem['tax_rate'] == 28 ? 'selected' : '' ?>>28%</option>
                                            </select>
                                        </td>
                                        <td><input type="text" class="form-control item-amount" value="0.00" readonly style="background: #f8fafc;"></td>
                                        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeInvRow(this)">&times;</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Notes & Terms -->
                <div class="form-row" style="grid-template-columns: 1fr 1fr; margin-top: 20px;">
                    <div class="form-group">
                        <label class="form-label">Invoice Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Thank you for your business."><?= htmlspecialchars($notes) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Terms & Conditions</label>
                        <textarea class="form-control" name="terms_and_conditions" rows="3" placeholder="Payment due within 15 days."><?= htmlspecialchars($terms) ?></textarea>
                    </div>
                </div>

                <!-- Summary Panel -->
                <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                    <div style="width: 100%; max-width: 380px; background: var(--bg-main); padding: 18px; border-radius: var(--radius-md);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>Taxable Subtotal:</span>
                            <strong id="subtotalVal">₹0.00</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; color: var(--text-muted);" id="cgstRow">
                            <span>CGST Output:</span>
                            <strong id="cgstVal">₹0.00</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; color: var(--text-muted);" id="sgstRow">
                            <span>SGST Output:</span>
                            <strong id="sgstVal">₹0.00</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; color: var(--text-muted); display: none;" id="igstRow">
                            <span>IGST Output:</span>
                            <strong id="igstVal">₹0.00</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; border-top: 2px dashed var(--border-color); padding-top: 10px; color: var(--primary);">
                            <span>Invoice Grand Total:</span>
                            <span id="grandTotalVal">₹0.00</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <a href="<?= url('/invoices') ?>" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="btnSubmit"><i class="fa-solid fa-check"></i> Generate & Post Invoice</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    calculateTotals();
    validateAllRowStocks();
});

function handleCustomerChange(select) {
    let option = select.options[select.selectedIndex];
    if (!option.value) return;

    let customerStateCode = option.getAttribute('data-state-code');
    let taxMode = document.getElementById('taxMode');
    
    let companyStateCode = '27'; // Default Maharashtra
    let isInter = false;
    
    if (customerStateCode && customerStateCode !== companyStateCode) {
        isInter = true;
    }
    
    taxMode.value = isInter ? 'inter' : 'intra';
    calculateTotals();
}

function updateInvItemDetails(select) {
    let row = select.closest('tr');
    let option = select.options[select.selectedIndex];
    if (!option.value) {
        let hint = row.querySelector('.item-stock-hint');
        if (hint) hint.style.display = 'none';
        return;
    }

    let price = option.getAttribute('data-price') || 0;
    let hsn = option.getAttribute('data-hsn') || '';
    let name = option.getAttribute('data-name') || option.text || '';
    let taxRate = option.getAttribute('data-tax-rate');
    if (taxRate === null || taxRate === undefined) {
        taxRate = 18;
    }
    
    row.querySelector('.item-rate').value = parseFloat(price).toFixed(2);
    row.querySelector('.item-hsn').value = hsn;
    row.querySelector('.item-name').value = name;
    if (row.querySelector('.item-tax-rate')) {
        row.querySelector('.item-tax-rate').value = parseInt(taxRate, 10);
    }
    
    calculateTotals();
    validateRowStock(row.querySelector('.item-qty'));
}

function onWarehouseChange() {
    let whSelect = document.getElementById('warehouseSelect');
    let whId = whSelect.value;
    
    // Update all option labels and data-stock attributes based on the new warehouse
    document.querySelectorAll('.item-select').forEach(sel => {
        for (let i = 0; i < sel.options.length; i++) {
            let opt = sel.options[i];
            if (!opt.value) continue;
            let whStocksRaw = opt.getAttribute('data-wh-stocks');
            if (whStocksRaw) {
                try {
                    let whStocks = JSON.parse(whStocksRaw);
                    if (whStocks[whId] !== undefined) {
                        let st = parseFloat(whStocks[whId]);
                        opt.setAttribute('data-stock', st);
                        let isService = opt.getAttribute('data-is-service') === '1';
                        let tax = opt.getAttribute('data-tax-rate') || 18;
                        let baseName = opt.getAttribute('data-name') || opt.text;
                        opt.text = isService ? `${baseName} (Service | ${tax}% GST)` : `${baseName} (Avail: ${st} | ${tax}% GST)`;
                    }
                } catch(e){}
            }
        }
    });

    validateAllRowStocks();
}

function validateRowStock(qtyInput) {
    let row = qtyInput.closest('tr');
    let sel = row.querySelector('.item-select');
    let hint = row.querySelector('.item-stock-hint');
    if (!sel || !sel.value || !hint) return;

    let opt = sel.options[sel.selectedIndex];
    let isService = opt.getAttribute('data-is-service') === '1';
    if (isService) {
        hint.style.display = 'block';
        hint.innerHTML = '<span style="color: #64748b;"><i class="fa-solid fa-bolt"></i> Service (No inventory deduction)</span>';
        return;
    }

    let stock = parseFloat(opt.getAttribute('data-stock')) || 0;
    let qty = parseFloat(qtyInput.value) || 0;
    let allowNeg = document.getElementById('allowNegativeStock').checked;

    hint.style.display = 'block';
    if (qty > stock) {
        if (allowNeg) {
            hint.innerHTML = `<span style="color: #d97706;"><i class="fa-solid fa-box-open"></i> Overselling (Stock: ${stock}, Shortage: ${(qty - stock).toFixed(2)})</span>`;
        } else {
            hint.innerHTML = `<span style="color: #dc2626; font-weight: 500;"><i class="fa-solid fa-triangle-exclamation"></i> Only <strong>${stock}</strong> available</span> <a href="javascript:void(0)" onclick="setRowQty(this, ${stock})" style="color: #2563eb; text-decoration: underline; margin-left: 6px; font-weight: 600;">Use ${stock}</a>`;
        }
    } else {
        hint.innerHTML = `<span style="color: #16a34a;"><i class="fa-solid fa-check"></i> In Stock: <strong>${stock}</strong> available</span>`;
    }
}

function validateAllRowStocks() {
    document.querySelectorAll('#invItemsTable tbody tr .item-qty').forEach(q => {
        validateRowStock(q);
    });
}

function setRowQty(element, targetQty) {
    let row = element.closest('tr');
    let qtyInput = row.querySelector('.item-qty');
    qtyInput.value = targetQty;
    calculateTotals();
    validateRowStock(qtyInput);
}

function applyStockAdjustment(targetQty) {
    let qtyInputs = document.querySelectorAll('#invItemsTable tbody tr .item-qty');
    if (qtyInputs.length > 0) {
        qtyInputs[0].value = targetQty;
        calculateTotals();
        validateRowStock(qtyInputs[0]);
    }
}

function forceAllowNegativeAndSubmit() {
    let chk = document.getElementById('allowNegativeStock');
    chk.checked = true;
    document.getElementById('invoiceForm').submit();
}

function addInvRow() {
    let tbody = document.querySelector('#invItemsTable tbody');
    let firstRow = tbody.querySelector('tr');
    let newRow = firstRow.cloneNode(true);
    newRow.querySelector('.item-select').selectedIndex = 0;
    newRow.querySelector('.item-name').value = '';
    newRow.querySelector('.item-hsn').value = '';
    newRow.querySelector('.item-qty').value = 1;
    newRow.querySelector('.item-rate').value = '0.00';
    newRow.querySelector('.item-disc').value = '0';
    if (newRow.querySelector('.item-tax-rate')) {
        newRow.querySelector('.item-tax-rate').value = '18';
    }
    newRow.querySelector('.item-amount').value = '0.00';
    let hint = newRow.querySelector('.item-stock-hint');
    if (hint) {
        hint.innerHTML = '';
        hint.style.display = 'none';
    }
    tbody.appendChild(newRow);
}

function removeInvRow(btn) {
    let tbody = document.querySelector('#invItemsTable tbody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        calculateTotals();
    }
}

function calculateTotals() {
    let rows = document.querySelectorAll('#invItemsTable tbody tr');
    let subtotal = 0;
    let totalTax = 0;
    
    rows.forEach(r => {
        let qty = parseFloat(r.querySelector('.item-qty').value) || 0;
        let rate = parseFloat(r.querySelector('.item-rate').value) || 0;
        let disc = parseFloat(r.querySelector('.item-disc').value) || 0;
        let taxSelect = r.querySelector('.item-tax-rate');
        let taxRate = taxSelect ? (parseFloat(taxSelect.value) || 0) : 0;
        
        let gross = qty * rate;
        let amt = gross - (gross * (disc / 100));
        r.querySelector('.item-amount').value = amt.toFixed(2);
        subtotal += amt;

        let lineTax = amt * (taxRate / 100.0);
        totalTax += lineTax;
    });
    
    let taxMode = document.getElementById('taxMode').value;
    let cgstRow = document.getElementById('cgstRow');
    let sgstRow = document.getElementById('sgstRow');
    let igstRow = document.getElementById('igstRow');
    
    if (taxMode === 'intra') {
        cgstRow.style.display = 'flex';
        sgstRow.style.display = 'flex';
        igstRow.style.display = 'none';
        document.getElementById('cgstVal').innerText = '₹' + (totalTax / 2).toFixed(2);
        document.getElementById('sgstVal').innerText = '₹' + (totalTax / 2).toFixed(2);
    } else {
        cgstRow.style.display = 'none';
        sgstRow.style.display = 'none';
        igstRow.style.display = 'flex';
        document.getElementById('igstVal').innerText = '₹' + totalTax.toFixed(2);
    }
    
    let grandTotal = subtotal + totalTax;
    
    document.getElementById('subtotalVal').innerText = '₹' + subtotal.toFixed(2);
    document.getElementById('grandTotalVal').innerText = '₹' + grandTotal.toFixed(2);
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
