<?php
/**
 * WTSBill ERP — Create Sales Order Form
 *
 * @var string $orderNumber
 * @var array $customers
 * @var array $categories
 * @var array $products
 * @var int $selectedCustomerId
 */
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Create Sales Order</h1>
        <p class="page-subtitle">Commercial order confirmation with line item tax calculation</p>
    </div>
    <div>
        <a href="/sales/orders" class="btn btn-secondary" style="text-decoration: none;">&larr; Back to Orders</a>
    </div>
</div>

<form method="POST" action="/sales/orders/create" id="salesOrderForm">
    <?= csrf_field() ?>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Header Info Card -->
        <div class="card" style="padding: 1.25rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Customer *</label>
                    <select name="customer_id" id="customer_select" class="form-control" required style="font-size: 0.85rem;" onchange="updateCustomerDetails(this)">
                        <option value="">Select Customer...</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                    data-gstin="<?= e($c['gstin'] ?? '') ?>"
                                    data-state-code="<?= e($c['state_code'] ?? '27') ?>"
                                    data-city="<?= e($c['city'] ?? '') ?>"
                                    <?= $selectedCustomerId === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?> <?= !empty($c['company_name']) ? ' (' . e($c['company_name']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Order Number</label>
                    <input type="text" name="order_number" class="form-control" value="<?= e($orderNumber) ?>" readonly style="background: var(--bg-surface, #f8fafc); font-weight: 700; font-size: 0.85rem;">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Order Date *</label>
                    <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 0.85rem;">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Expected Delivery Date</label>
                    <input type="date" name="expected_delivery" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" style="font-size: 0.85rem;">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Customer Ref / PO #</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="PO reference (optional)" style="font-size: 0.85rem;">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Initial Status</label>
                    <select name="status" class="form-control" style="font-size: 0.85rem;">
                        <option value="CONFIRMED" selected>CONFIRMED</option>
                        <option value="PENDING">PENDING</option>
                        <option value="PROCESSING">PROCESSING</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Customer Compliance Badge & POS Card -->
        <div class="card" style="padding: 1.25rem;">
            <h4 style="margin: 0 0 0.5rem 0; font-size: 0.85rem; text-transform: uppercase; color: var(--color-muted);">Tax Details</h4>
            <div style="font-size: 0.85rem; line-height: 1.6;">
                <div><strong>GSTIN:</strong> <span id="disp_gstin">—</span></div>
                <div><strong>Place of Supply:</strong> <span id="disp_pos">Maharashtra (27)</span></div>
                <input type="hidden" name="place_of_supply_state_code" id="pos_state_code" value="27">
                <div style="margin-top: 0.5rem; padding: 0.5rem; background: var(--bg-surface, #f8fafc); border-radius: 4px; font-size: 0.75rem; color: var(--color-muted);">
                    Orders represent commercial commitments and do not post accounting entries to the General Ledger until converted to confirmed Tax Invoices.
                </div>
            </div>
        </div>
    </div>

    <!-- Line Items Table Card -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem;">
            <h3 class="card-title" style="margin: 0; font-size: 1rem;">Order Line Items</h3>
            <button type="button" class="btn btn-secondary" onclick="addOrderRow()" style="font-size: 0.8rem;">+ Add Item</button>
        </div>
        <div class="table-container" style="overflow-x: auto;">
            <table class="table" id="itemsTable" style="width: 100%; min-width: 900px;">
                <thead>
                    <tr>
                        <th style="width: 18%;">Category</th>
                        <th style="width: 25%;">Product / Item *</th>
                        <th style="width: 10%;">HSN/SAC</th>
                        <th style="width: 10%; text-align: right;">Quantity *</th>
                        <th style="width: 12%; text-align: right;">Rate (₹) *</th>
                        <th style="width: 8%; text-align: right;">GST %</th>
                        <th style="width: 12%; text-align: right;">Total (₹)</th>
                        <th style="width: 5%; text-align: center;"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <!-- Dynamic rows will be inserted here -->
                </tbody>
            </table>
        </div>
        <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color, #e2e8f0); display: flex; justify-content: flex-end;">
            <div style="width: 320px; font-size: 0.9rem; line-height: 1.8;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Taxable Subtotal:</span>
                    <strong id="lbl_subtotal">₹ 0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Total Tax (GST):</span>
                    <strong id="lbl_tax">₹ 0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 700; color: var(--color-heading); border-top: 1px solid var(--border-color, #e2e8f0); padding-top: 0.5rem; margin-top: 0.25rem;">
                    <span>Grand Total:</span>
                    <span id="lbl_grand_total">₹ 0.00</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes & Submission -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Order Notes / Terms</label>
        <textarea name="notes" class="form-control" rows="2" placeholder="Special delivery instructions, dispatch terms..." style="font-size: 0.85rem;"></textarea>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
        <a href="/sales/orders" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.5rem;">+ Save Sales Order</button>
    </div>
</form>

<!-- Raw product catalogs for dynamic filtering -->
<script>
const categories = <?= json_encode($categories) ?>;
const allProducts = <?= json_encode($products) ?>;
let rowCount = 0;

function updateCustomerDetails(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    const gstin = opt.getAttribute('data-gstin') || '—';
    const stateCode = opt.getAttribute('data-state-code') || '27';
    document.getElementById('disp_gstin').innerText = gstin;
    document.getElementById('pos_state_code').value = stateCode;
    document.getElementById('disp_pos').innerText = (stateCode === '27' ? 'Intra-State: Maharashtra (27)' : `Inter-State: Code (${stateCode})`);
    recalcAll();
}

function addOrderRow() {
    rowCount++;
    const idx = rowCount;
    const tr = document.createElement('tr');
    tr.id = `row_${idx}`;
    tr.innerHTML = `
        <td>
            <select class="form-control row-cat" style="font-size: 0.85rem;" onchange="onCategoryChange(${idx})">
                <option value="">All Categories</option>
                ${categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('')}
            </select>
        </td>
        <td>
            <select name="items[${idx}][product_id]" class="form-control row-prod" required style="font-size: 0.85rem;" onchange="onProductChange(${idx})">
                <option value="">Select Product...</option>
                ${allProducts.map(p => `<option value="${p.id}" data-cat="${p.category_id}" data-hsn="${p.hsn_sac || ''}" data-price="${p.selling_price || 0}" data-tax="${p.tax_rate || 18}" data-name="${p.name}">${p.name} (₹${parseFloat(p.selling_price || 0).toFixed(2)})</option>`).join('')}
            </select>
            <input type="hidden" name="items[${idx}][item_name]" class="row-name" value="">
        </td>
        <td>
            <input type="text" name="items[${idx}][hsn_sac]" class="form-control row-hsn" placeholder="HSN" style="font-size: 0.85rem;">
        </td>
        <td>
            <input type="number" step="0.01" name="items[${idx}][quantity]" class="form-control row-qty" value="1" required style="font-size: 0.85rem; text-align: right;" oninput="recalcRow(${idx})">
        </td>
        <td>
            <input type="number" step="0.01" name="items[${idx}][unit_price]" class="form-control row-rate" value="0.00" required style="font-size: 0.85rem; text-align: right;" oninput="recalcRow(${idx})">
        </td>
        <td>
            <select name="items[${idx}][gst_rate]" class="form-control row-gst" style="font-size: 0.85rem;" onchange="recalcRow(${idx})">
                <option value="0">0%</option>
                <option value="5">5%</option>
                <option value="12">12%</option>
                <option value="18" selected>18%</option>
                <option value="28">28%</option>
            </select>
        </td>
        <td style="text-align: right; font-weight: 600;" class="tabular-nums row-total">
            ₹ 0.00
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-outline-danger" style="padding: 0.2rem 0.4rem; font-size: 0.75rem;" onclick="removeRow(${idx})">&times;</button>
        </td>
    `;
    document.getElementById('itemsBody').appendChild(tr);
    return idx;
}

function removeRow(idx) {
    const r = document.getElementById(`row_${idx}`);
    if (r) r.remove();
    recalcAll();
}

function onCategoryChange(idx) {
    const row = document.getElementById(`row_${idx}`);
    const catId = row.querySelector('.row-cat').value;
    const prodSelect = row.querySelector('.row-prod');
    
    let filtered = allProducts;
    if (catId) {
        filtered = allProducts.filter(p => String(p.category_id) === String(catId));
    }
    
    prodSelect.innerHTML = `<option value="">Select Product...</option>` +
        filtered.map(p => `<option value="${p.id}" data-cat="${p.category_id}" data-hsn="${p.hsn_sac || ''}" data-price="${p.selling_price || 0}" data-tax="${p.tax_rate || 18}" data-name="${p.name}">${p.name} (₹${parseFloat(p.selling_price || 0).toFixed(2)})</option>`).join('');
}

function onProductChange(idx) {
    const row = document.getElementById(`row_${idx}`);
    const sel = row.querySelector('.row-prod');
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;

    row.querySelector('.row-name').value = opt.getAttribute('data-name') || '';
    row.querySelector('.row-hsn').value = opt.getAttribute('data-hsn') || '7318';
    row.querySelector('.row-rate').value = parseFloat(opt.getAttribute('data-price') || 0).toFixed(2);
    row.querySelector('.row-gst').value = opt.getAttribute('data-tax') || '18';
    recalcRow(idx);
}

function recalcRow(idx) {
    const row = document.getElementById(`row_${idx}`);
    if (!row) return;

    const qty = parseFloat(row.querySelector('.row-qty').value) || 0;
    const rate = parseFloat(row.querySelector('.row-rate').value) || 0;
    const gst = parseFloat(row.querySelector('.row-gst').value) || 0;

    const taxable = qty * rate;
    const tax = taxable * (gst / 100);
    const total = taxable + tax;

    row.querySelector('.row-total').innerText = '₹ ' + total.toFixed(2);
    recalcAll();
}

function recalcAll() {
    let subtotal = 0;
    let taxTotal = 0;

    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const qty = parseFloat(tr.querySelector('.row-qty')?.value) || 0;
        const rate = parseFloat(tr.querySelector('.row-rate')?.value) || 0;
        const gst = parseFloat(tr.querySelector('.row-gst')?.value) || 0;

        const taxable = qty * rate;
        const tax = taxable * (gst / 100);
        subtotal += taxable;
        taxTotal += tax;
    });

    const grand = subtotal + taxTotal;

    document.getElementById('lbl_subtotal').innerText = '₹ ' + subtotal.toFixed(2);
    document.getElementById('lbl_tax').innerText = '₹ ' + taxTotal.toFixed(2);
    document.getElementById('lbl_grand_total').innerText = '₹ ' + grand.toFixed(2);
}

// Initialize with one row
document.addEventListener('DOMContentLoaded', () => {
    addOrderRow();
    const custSel = document.getElementById('customer_select');
    if (custSel.value) updateCustomerDetails(custSel);
});
</script>

