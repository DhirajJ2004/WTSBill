<?php
$title = $title ?? 'Create Purchase Order';
$suppliers = $suppliers ?? [];
$products = $products ?? [];
$branches = $branches ?? [];
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
            <a href="/purchases/orders" style="color: #64748b; text-decoration: none;">Purchase Orders</a>
            <span>/</span>
            <span style="color: #1e293b; font-weight: 500;">New PO</span>
        </div>
        <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Create Purchase Order (PO)</h1>
        <p style="font-size: 14px; color: #64748b; margin: 0;">Raise an official purchase commitment with vendor, tax rates, and delivery schedule.</p>
    </div>

    <form action="/purchases/orders/create" method="POST" id="poForm">
        <?= csrf_field() ?>

        <!-- Order Header Card -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0 0 20px 0;">Supplier & Order Details</h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Supplier / Vendor <span style="color: #ef4444;">*</span></label>
                    <select name="supplier_id" id="supplier_id" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" data-state="<?= htmlspecialchars($s['state'] ?? '') ?>" data-gstin="<?= htmlspecialchars($s['gstin'] ?? '') ?>">
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['company_name'] ?: 'GST: ' . ($s['gstin'] ?? 'Unregistered')) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Receiving Branch</label>
                    <select name="branch_id" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">PO Date <span style="color: #ef4444;">*</span></label>
                    <input type="date" name="po_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>

                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Expected Delivery Date</label>
                    <input type="date" name="expected_delivery" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Vendor Quotation / Ref #</label>
                    <input type="text" name="reference_no" placeholder="e.g. VQ-2026-88" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Initial Order Status</label>
                    <select name="status" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                        <option value="APPROVED" selected>Approved & Issued</option>
                        <option value="PENDING">Draft / Pending Approval</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Ordered Products & Materials</h2>
                <button type="button" onclick="addPoRow()" style="padding: 6px 14px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: 600; color: #2563eb; cursor: pointer;">
                    + Add Product Row
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;" id="poItemsTable">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 13px;">
                            <th style="padding: 10px 12px; width: 40%;">Product / Description</th>
                            <th style="padding: 10px 12px; width: 15%;">Quantity</th>
                            <th style="padding: 10px 12px; width: 15%;">Unit Cost (₹)</th>
                            <th style="padding: 10px 12px; width: 12%;">GST Rate (%)</th>
                            <th style="padding: 10px 12px; width: 14%; text-align: right;">Total Amount (₹)</th>
                            <th style="padding: 10px 12px; width: 4%; text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody id="poItemsBody">
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 12px;">
                                <select name="items[0][product_id]" required onchange="handleProductSelect(this, 0)" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background: #fff;">
                                    <option value="">-- Choose Product --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>" data-price="<?= (float)$p['cost_price'] ?>" data-unit="<?= htmlspecialchars($p['unit'] ?? 'PCS') ?>" data-hsn="<?= htmlspecialchars($p['hsn_sac'] ?? '') ?>">
                                            <?= htmlspecialchars($p['name']) ?> (SKU: <?= htmlspecialchars($p['sku'] ?? '—') ?> | Cost: ₹<?= number_format((float)$p['cost_price'], 2) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="items[0][item_name]" id="itemName_0">
                                <input type="hidden" name="items[0][unit]" id="unit_0" value="PCS">
                                <input type="hidden" name="items[0][hsn_sac]" id="hsn_0">
                            </td>
                            <td style="padding: 10px 12px;">
                                <input type="number" name="items[0][quantity]" id="qty_0" step="0.01" min="0.01" required placeholder="0.00" oninput="calcRow(0)" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            </td>
                            <td style="padding: 10px 12px;">
                                <input type="number" name="items[0][unit_price]" id="price_0" step="0.01" min="0" required placeholder="0.00" oninput="calcRow(0)" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            </td>
                            <td style="padding: 10px 12px;">
                                <select name="items[0][gst_rate]" id="gst_0" onchange="calcRow(0)" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background: #fff;">
                                    <option value="18">18%</option>
                                    <option value="12">12%</option>
                                    <option value="5">5%</option>
                                    <option value="28">28%</option>
                                    <option value="0">0% (Nil)</option>
                                </select>
                            </td>
                            <td style="padding: 10px 12px; text-align: right; font-weight: 600; color: #1e293b;" id="total_0">
                                ₹0.00
                            </td>
                            <td style="padding: 10px 12px; text-align: center;">
                                <button type="button" onclick="removePoRow(this)" style="background: none; border: none; color: #ef4444; font-size: 18px; cursor: pointer;">&times;</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals Summary Grid -->
            <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                <div style="width: 320px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #475569;">
                        <span>Subtotal (Taxable):</span>
                        <span id="summarySubtotal" style="font-weight: 600;">₹0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #475569;">
                        <span>Estimated GST:</span>
                        <span id="summaryTax" style="font-weight: 600;">₹0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 10px; border-top: 1px solid #cbd5e1; font-size: 16px; font-weight: 700; color: #0f172a;">
                        <span>Grand Total:</span>
                        <span id="summaryGrand" style="color: #2563eb;">₹0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes & Terms -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Notes / Instructions to Vendor</label>
                    <textarea name="notes" rows="3" placeholder="Inspection requirements, packaging, delivery hours..." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;"></textarea>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Commercial Terms & Conditions</label>
                    <textarea name="terms" rows="3" placeholder="Payment within 30 days of GRN, warranty terms..." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">1. Payment terms: 30 days from date of bill.
2. Subject to material inspection upon delivery.
3. Defective goods will be returned under Debit Note.</textarea>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <a href="/purchases/orders" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #475569; font-weight: 600; text-decoration: none;">Cancel</a>
            <button type="submit" style="padding: 10px 24px; border-radius: 8px; border: none; background: #2563eb; color: #fff; font-weight: 600; cursor: pointer;">Save & Issue Purchase Order</button>
        </div>
    </form>
</div>

<script>
let poRowCount = 1;
const productSelectOptions = `<?php foreach ($products as $p): ?>
    <option value="<?= (int)$p['id'] ?>" data-price="<?= (float)$p['cost_price'] ?>" data-unit="<?= htmlspecialchars($p['unit'] ?? 'PCS') ?>" data-hsn="<?= htmlspecialchars($p['hsn_sac'] ?? '') ?>">
        <?= htmlspecialchars($p['name']) ?> (SKU: <?= htmlspecialchars($p['sku'] ?? '—') ?> | Cost: ₹<?= number_format((float)$p['cost_price'], 2) ?>)
    </option>
<?php endforeach; ?>`;

function addPoRow() {
    const tbody = document.getElementById('poItemsBody');
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid #f1f5f9';
    tr.innerHTML = `
        <td style="padding: 10px 12px;">
            <select name="items[${poRowCount}][product_id]" required onchange="handleProductSelect(this, ${poRowCount})" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background: #fff;">
                <option value="">-- Choose Product --</option>
                ${productSelectOptions}
            </select>
            <input type="hidden" name="items[${poRowCount}][item_name]" id="itemName_${poRowCount}">
            <input type="hidden" name="items[${poRowCount}][unit]" id="unit_${poRowCount}" value="PCS">
            <input type="hidden" name="items[${poRowCount}][hsn_sac]" id="hsn_${poRowCount}">
        </td>
        <td style="padding: 10px 12px;">
            <input type="number" name="items[${poRowCount}][quantity]" id="qty_${poRowCount}" step="0.01" min="0.01" required placeholder="0.00" oninput="calcRow(${poRowCount})" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
        </td>
        <td style="padding: 10px 12px;">
            <input type="number" name="items[${poRowCount}][unit_price]" id="price_${poRowCount}" step="0.01" min="0" required placeholder="0.00" oninput="calcRow(${poRowCount})" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
        </td>
        <td style="padding: 10px 12px;">
            <select name="items[${poRowCount}][gst_rate]" id="gst_${poRowCount}" onchange="calcRow(${poRowCount})" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background: #fff;">
                <option value="18">18%</option>
                <option value="12">12%</option>
                <option value="5">5%</option>
                <option value="28">28%</option>
                <option value="0">0% (Nil)</option>
            </select>
        </td>
        <td style="padding: 10px 12px; text-align: right; font-weight: 600; color: #1e293b;" id="total_${poRowCount}">
            ₹0.00
        </td>
        <td style="padding: 10px 12px; text-align: center;">
            <button type="button" onclick="removePoRow(this)" style="background: none; border: none; color: #ef4444; font-size: 18px; cursor: pointer;">&times;</button>
        </td>
    `;
    tbody.appendChild(tr);
    poRowCount++;
}

function removePoRow(btn) {
    const tbody = document.getElementById('poItemsBody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        calcAll();
    }
}

function handleProductSelect(select, idx) {
    const opt = select.options[select.selectedIndex];
    if (opt.value) {
        document.getElementById(`price_${idx}`).value = opt.dataset.price || '0.00';
        document.getElementById(`unit_${idx}`).value = opt.dataset.unit || 'PCS';
        document.getElementById(`hsn_${idx}`).value = opt.dataset.hsn || '';
        document.getElementById(`itemName_${idx}`).value = opt.text.split(' (')[0];
        calcRow(idx);
    }
}

function calcRow(idx) {
    const qty = parseFloat(document.getElementById(`qty_${idx}`)?.value || '0');
    const price = parseFloat(document.getElementById(`price_${idx}`)?.value || '0');
    const gstRate = parseFloat(document.getElementById(`gst_${idx}`)?.value || '0');

    const taxable = qty * price;
    const tax = (taxable * gstRate) / 100;
    const total = taxable + tax;

    const totalEl = document.getElementById(`total_${idx}`);
    if (totalEl) {
        totalEl.textContent = '₹' + total.toFixed(2);
    }
    calcAll();
}

function calcAll() {
    let subtotal = 0;
    let taxTotal = 0;

    const tbody = document.getElementById('poItemsBody');
    for (let i = 0; i < tbody.children.length; i++) {
        const row = tbody.children[i];
        const qtyInp = row.querySelector('input[name*="[quantity]"]');
        const priceInp = row.querySelector('input[name*="[unit_price]"]');
        const gstSel = row.querySelector('select[name*="[gst_rate]"]');

        const qty = parseFloat(qtyInp?.value || '0');
        const price = parseFloat(priceInp?.value || '0');
        const gst = parseFloat(gstSel?.value || '0');

        const taxable = qty * price;
        const tax = (taxable * gst) / 100;

        subtotal += taxable;
        taxTotal += tax;
    }

    const grand = subtotal + taxTotal;
    document.getElementById('summarySubtotal').textContent = '₹' + subtotal.toFixed(2);
    document.getElementById('summaryTax').textContent = '₹' + taxTotal.toFixed(2);
    document.getElementById('summaryGrand').textContent = '₹' + grand.toFixed(2);
}
</script>

