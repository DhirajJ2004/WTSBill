<?php
/**
 * WTSBill ERP — Edit Tax Invoice View
 *
 * @var array $invoice
 * @var array $items
 * @var array $customers
 * @var array $products
 */
$errorMsg = \WTSBill\Core\Session::getFlash('error');
$csrfToken = \WTSBill\Core\Session::getCsrfToken();
?>
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">Edit Invoice <?= e($invoice['invoice_number']) ?></h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.875rem;">Modify customer, line items, billing metadata, and recalculate GST tax splits</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <a href="/sales/invoices/<?= (int)$invoice['id'] ?>/print" target="_blank" class="btn btn-secondary" style="font-size: 0.85rem;">Print Invoice</a>
        <a href="/sales/invoices" class="btn btn-secondary" style="font-size: 0.85rem;">&larr; Back to Invoices</a>
    </div>
</div>

<div class="card" style="max-width: 960px; margin: 0 auto 2rem auto;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="margin: 0;">Edit Invoice Form</h3>
        <span class="badge badge-<?= $invoice['status'] === 'PAID' ? 'success' : ($invoice['status'] === 'PARTIAL' ? 'warning' : 'danger') ?>">
            <?= e($invoice['status']) ?>
        </span>
    </div>
    <div class="card-body">
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;">
                <?= e($errorMsg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/sales/invoices/<?= (int)$invoice['id'] ?>/update" id="invoice-edit-form" onsubmit="window.submitInvoiceEditForm(event)">
            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label for="inv-customer">Customer Name <span style="color: var(--danger);">*</span></label>
                    <select id="inv-customer" name="customer_id" class="form-control" required onchange="window.onCustomerChange()">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers ?? [] as $c): ?>
                            <?php $selected = ((int)$c['id'] === (int)$invoice['customer_id']) ? 'selected' : ''; ?>
                            <option value="<?= e((string)$c['id']) ?>" data-gstin="<?= e($c['gstin'] ?? '') ?>" data-state="<?= e($c['state_code'] ?? '27') ?>" <?= $selected ?>>
                                <?= e($c['name']) ?> (<?= e($c['gstin'] ?: 'Unregistered') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="inv-date">Invoice Date <span style="color: var(--danger);">*</span></label>
                    <input type="date" id="inv-date" name="invoice_date" class="form-control" value="<?= e($invoice['invoice_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="inv-due-date">Due Date <span style="color: var(--danger);">*</span></label>
                    <input type="date" id="inv-due-date" name="due_date" class="form-control" value="<?= e($invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>" required>
                </div>

                <div class="form-group">
                    <label for="inv-pos">Place of Supply <span style="color: var(--danger);">*</span></label>
                    <?php $pos = $invoice['place_of_supply_state_code'] ?? $invoice['place_of_supply'] ?? '27'; ?>
                    <select id="inv-pos" name="place_of_supply" class="form-control" required onchange="window.recalculateTotals()">
                        <option value="27" <?= $pos === '27' ? 'selected' : '' ?>>27 - Maharashtra (Intra-state: CGST + SGST)</option>
                        <option value="24" <?= $pos === '24' ? 'selected' : '' ?>>24 - Gujarat (Inter-state: IGST)</option>
                        <option value="07" <?= $pos === '07' ? 'selected' : '' ?>>07 - Delhi (Inter-state: IGST)</option>
                        <option value="29" <?= $pos === '29' ? 'selected' : '' ?>>29 - Karnataka (Inter-state: IGST)</option>
                        <option value="33" <?= $pos === '33' ? 'selected' : '' ?>>33 - Tamil Nadu (Inter-state: IGST)</option>
                    </select>
                </div>
            </div>

            <!-- Extended GST Invoice Metadata Fields -->
            <div style="background: var(--bg-hover); padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                <h4 style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.75rem;">Order & Shipping Metadata (Optional)</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
                    <div class="form-group">
                        <label for="inv-po-no" style="font-size: 0.8rem;">P.O. Number</label>
                        <input type="text" id="inv-po-no" name="po_no" class="form-control" value="<?= e($invoice['po_no'] ?? $invoice['reference_po_number'] ?? '') ?>" placeholder="e.g. PO/26-27/001" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="inv-po-date" style="font-size: 0.8rem;">P.O. Date</label>
                        <input type="date" id="inv-po-date" name="po_date" class="form-control" value="<?= e($invoice['po_date'] ?? '') ?>" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="inv-challan-no" style="font-size: 0.8rem;">Challan Number</label>
                        <input type="text" id="inv-challan-no" name="challan_no" class="form-control" value="<?= e($invoice['challan_no'] ?? '') ?>" placeholder="e.g. CH-9901" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="inv-credit-days" style="font-size: 0.8rem;">Bill Credit (Days)</label>
                        <input type="number" id="inv-credit-days" name="bill_credit_days" class="form-control" value="<?= (int)($invoice['bill_credit_days'] ?? 0) ?>" min="0" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="inv-delivery-by" style="font-size: 0.8rem;">Delivery By</label>
                        <input type="text" id="inv-delivery-by" name="delivery_by" class="form-control" value="<?= e($invoice['delivery_by'] ?? '') ?>" placeholder="e.g. Courier / By Hand" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="inv-receipt-mode" style="font-size: 0.8rem;">Receipt Mode</label>
                        <?php $mode = $invoice['receipt_mode'] ?? 'Credit'; ?>
                        <select id="inv-receipt-mode" name="receipt_mode" class="form-control" style="font-size: 0.85rem;">
                            <option value="Credit" <?= $mode === 'Credit' ? 'selected' : '' ?>>Credit</option>
                            <option value="Cash" <?= $mode === 'Cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="UPI" <?= $mode === 'UPI' ? 'selected' : '' ?>>UPI</option>
                            <option value="Cheque" <?= $mode === 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                            <option value="Bank Transfer" <?= $mode === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="inv-lr-no" style="font-size: 0.8rem;">LR Number</label>
                        <input type="text" id="inv-lr-no" name="lr_no" class="form-control" value="<?= e($invoice['lr_no'] ?? '') ?>" placeholder="e.g. LR-4421" style="font-size: 0.85rem;">
                    </div>
                </div>
            </div>

            <!-- Line Items Table -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                <h4 style="font-size: 0.95rem; font-weight: 600; margin: 0; color: var(--text-primary);">Invoice Line Items</h4>
                <button type="button" id="btn-add-line-item" class="btn btn-sm btn-secondary" onclick="window.addLineItem()" style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; padding: 0.35rem 0.85rem; border: 1px dashed var(--primary); background: var(--primary-soft); color: var(--primary); font-weight: 600;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>+ Add Item</span>
                </button>
            </div>

            <div class="table-container" style="margin-bottom: 1rem; overflow-x: auto;">
                <table class="table" id="items-table" style="width: 100%; min-width: 780px;">
                    <thead>
                        <tr>
                            <th style="width: 32%;">Product / Item</th>
                            <th style="width: 14%;">HSN/SAC</th>
                            <th style="width: 11%; text-align: right;">Qty</th>
                            <th style="width: 14%; text-align: right;">Rate (₹)</th>
                            <th style="width: 13%; text-align: right;">GST Rate</th>
                            <th style="width: 12%; text-align: right;">Amount (₹)</th>
                            <th style="width: 4%; text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-table-body">
                        <!-- Dynamically populated via JS from existing items -->
                    </tbody>
                </table>
            </div>

            <!-- Add Row Action Bar Under Table -->
            <div style="margin-bottom: 1.5rem;">
                <button type="button" class="btn btn-light" onclick="window.addLineItem()" style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.85rem; padding: 0.45rem 1rem; border-radius: 6px; border: 1px dashed var(--border-color); color: var(--primary); background: var(--bg-surface); cursor: pointer;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add Another Line Item</span>
                </button>
            </div>

            <!-- Totals Summary Card -->
            <div style="background: var(--bg-hover); padding: 1.25rem 1.5rem; border-radius: var(--radius); margin-bottom: 1.5rem; display: flex; justify-content: flex-end;">
                <div style="width: 320px; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.9rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Total Items:</span>
                        <strong id="summary-items-count"><?= count($items ?? []) ?> Item(s)</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Taxable Subtotal:</span>
                        <strong id="summary-subtotal">₹<?= number_format((float)$invoice['sub_total'], 2) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;" id="summary-tax-row">
                        <span style="color: var(--text-muted);" id="summary-tax-label">CGST + SGST:</span>
                        <strong id="summary-tax-val">₹<?= number_format((float)$invoice['total_tax'], 2) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 0.5rem; font-size: 1.15rem; color: var(--color-primary);">
                        <strong>Grand Total:</strong>
                        <strong id="summary-grand">₹<?= number_format((float)$invoice['grand_total'], 2) ?></strong>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="inv-notes">Terms & Notes</label>
                <textarea id="inv-notes" name="notes" class="form-control" rows="2" placeholder="Payment terms, bank details, or delivery instructions"><?= e($invoice['notes'] ?? 'Payment due within 30 days of invoice date.') ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <?php if (\WTSBill\Auth\Gate::can('invoices.delete')): ?>
                    <button type="button" class="btn btn-outline-danger" onclick="window.confirmDeleteInvoice()" style="color: var(--danger); border-color: var(--danger);">
                        Delete Invoice
                    </button>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
                <div style="display: flex; gap: 0.75rem;">
                    <a href="/sales/invoices" class="btn btn-secondary">Cancel</a>
                    <button type="submit" id="inv-submit-btn" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-size: 0.95rem;">Save Changes &rarr;</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hidden catalog products JSON for client row generation -->
<script id="catalog-products-data" type="application/json">
<?= json_encode(array_map(function($p) {
    return [
        'id' => (int)$p['id'],
        'name' => (string)$p['name'],
        'price' => (float)$p['selling_price'],
        'hsn' => (string)($p['hsn_sac'] ?? '7318'),
        'tax' => (float)$p['tax_rate'],
    ];
}, $products ?? [])) ?>
</script>

<!-- Existing Invoice Items JSON -->
<script id="existing-items-data" type="application/json">
<?= json_encode(array_map(function($it) {
    return [
        'product_id' => (int)($it['product_id'] ?? 0),
        'quantity' => (float)($it['quantity'] ?? 1),
        'rate' => (float)($it['unit_price'] ?? 0),
        'hsn_sac' => (string)($it['hsn_sac'] ?? '7318'),
        'gst_rate' => (float)($it['gst_rate'] ?? 18),
        'discount_rate' => (float)($it['discount_rate'] ?? 0),
    ];
}, $items ?? [])) ?>
</script>

<script>
    const catalogProducts = JSON.parse(document.getElementById('catalog-products-data').textContent || '[]');
    const existingItems = JSON.parse(document.getElementById('existing-items-data').textContent || '[]');
    let rowCounter = 0;

    window.addLineItem = function(initialData = null) {
        rowCounter++;
        const tbody = document.getElementById('items-table-body');
        const tr = document.createElement('tr');
        tr.id = 'item-row-' + rowCounter;
        tr.className = 'invoice-item-row';
        tr.dataset.rowId = rowCounter;

        let optionsHtml = '<option value="">-- Choose Catalog Product --</option>';
        catalogProducts.forEach(p => {
            const isSelected = initialData && parseInt(initialData.product_id) === p.id;
            optionsHtml += `<option value="${p.id}" data-price="${p.price}" data-hsn="${p.hsn}" data-tax="${p.tax}" ${isSelected ? 'selected' : ''}>${p.name} (₹${p.price.toFixed(2)})</option>`;
        });

        const defaultQty = initialData && initialData.quantity !== undefined ? initialData.quantity : 1;
        const defaultRate = initialData && initialData.rate !== undefined ? initialData.rate : (catalogProducts[0] ? catalogProducts[0].price : 100.00);
        const defaultHsn = initialData && initialData.hsn_sac ? initialData.hsn_sac : (catalogProducts[0] ? catalogProducts[0].hsn : '7318');
        const defaultGst = initialData && initialData.gst_rate !== undefined ? initialData.gst_rate : 18;

        tr.innerHTML = `
            <td>
                <select name="items[${rowCounter}][product_id]" class="form-control item-product" style="font-size: 0.85rem;" onchange="window.onRowProductChange(${rowCounter})">
                    ${optionsHtml}
                </select>
            </td>
            <td>
                <input type="text" name="items[${rowCounter}][hsn_sac]" class="form-control item-hsn" value="${defaultHsn}" style="font-size: 0.85rem;">
            </td>
            <td>
                <input type="number" name="items[${rowCounter}][quantity]" class="form-control item-qty" value="${defaultQty}" min="0.01" step="0.01" style="text-align: right; font-size: 0.85rem;" oninput="window.recalculateTotals()">
            </td>
            <td>
                <input type="number" name="items[${rowCounter}][rate]" class="form-control item-rate" value="${parseFloat(defaultRate).toFixed(2)}" min="0" step="0.01" style="text-align: right; font-size: 0.85rem;" oninput="window.recalculateTotals()">
            </td>
            <td>
                <select name="items[${rowCounter}][gst_rate]" class="form-control item-gst" style="font-size: 0.85rem;" onchange="window.recalculateTotals()">
                    <option value="18" ${defaultGst == 18 ? 'selected' : ''}>18% (Standard)</option>
                    <option value="12" ${defaultGst == 12 ? 'selected' : ''}>12%</option>
                    <option value="5" ${defaultGst == 5 ? 'selected' : ''}>5%</option>
                    <option value="0" ${defaultGst == 0 ? 'selected' : ''}>0% (Exempt)</option>
                </select>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <strong class="item-line-total" style="font-size: 0.9rem; color: var(--text-primary);">₹0.00</strong>
            </td>
            <td style="text-align: center; vertical-align: middle;">
                <button type="button" class="btn btn-sm" onclick="window.removeLineItem(${rowCounter})" title="Remove item" style="padding: 0.2rem 0.45rem; background: transparent; border: none; color: var(--color-danger); cursor: pointer; border-radius: 4px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </td>
        `;

        tbody.appendChild(tr);

        if (initialData && initialData.product_id) {
            const sel = tr.querySelector('.item-product');
            sel.value = initialData.product_id;
        }

        window.recalculateTotals();
    };

    window.removeLineItem = function(rowId) {
        const rows = document.querySelectorAll('.invoice-item-row');
        if (rows.length <= 1) {
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('An invoice must have at least one line item.', 'warning');
            }
            return;
        }
        const row = document.getElementById('item-row-' + rowId);
        if (row) {
            row.remove();
            window.recalculateTotals();
        }
    };

    window.onRowProductChange = function(rowId) {
        const row = document.getElementById('item-row-' + rowId);
        if (!row) return;

        const sel = row.querySelector('.item-product');
        const opt = sel.options[sel.selectedIndex];
        if (opt && opt.dataset.price) {
            row.querySelector('.item-rate').value = parseFloat(opt.dataset.price).toFixed(2);
            row.querySelector('.item-hsn').value = opt.dataset.hsn || '7318';
            row.querySelector('.item-gst').value = opt.dataset.tax || '18';
        }
        window.recalculateTotals();
    };

    window.onCustomerChange = function() {
        const select = document.getElementById('inv-customer');
        const opt = select.options[select.selectedIndex];
        if (opt && opt.dataset.state) {
            const posSelect = document.getElementById('inv-pos');
            for (let i = 0; i < posSelect.options.length; i++) {
                if (posSelect.options[i].value === opt.dataset.state) {
                    posSelect.selectedIndex = i;
                    break;
                }
            }
        }
        window.recalculateTotals();
    };

    window.recalculateTotals = function() {
        const rows = document.querySelectorAll('.invoice-item-row');
        const pos = document.getElementById('inv-pos').value;
        const isIntraState = (pos === '27');

        let totalTaxable = 0.0;
        let totalTax = 0.0;
        let itemCount = 0;

        rows.forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            const gst = parseFloat(row.querySelector('.item-gst').value) || 0;

            const lineTaxable = Math.round(qty * rate * 100) / 100;
            const lineTax = Math.round(lineTaxable * (gst / 100) * 100) / 100;
            const lineTotal = lineTaxable + lineTax;

            row.querySelector('.item-line-total').textContent = '₹' + lineTotal.toFixed(2);

            totalTaxable += lineTaxable;
            totalTax += lineTax;
            itemCount++;
        });

        const grand = Math.round(totalTaxable + totalTax);

        document.getElementById('summary-items-count').textContent = itemCount + ' Item(s)';
        document.getElementById('summary-subtotal').textContent = '₹' + totalTaxable.toFixed(2);

        if (isIntraState) {
            document.getElementById('summary-tax-label').textContent = 'CGST + SGST:';
        } else {
            document.getElementById('summary-tax-label').textContent = 'IGST:';
        }
        document.getElementById('summary-tax-val').textContent = '₹' + totalTax.toFixed(2);
        document.getElementById('summary-grand').textContent = '₹' + grand.toFixed(2);
    };

    window.submitInvoiceEditForm = async function(e) {
        e.preventDefault();
        const form = document.getElementById('invoice-edit-form');
        const btn = document.getElementById('inv-submit-btn');

        btn.disabled = true;
        btn.textContent = 'Saving Changes...';

        const formData = new FormData(form);

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken
                },
                body: formData
            });

            const result = await resp.json();

            if (resp.ok && result.success) {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Invoice updated successfully!', 'success');
                }
                setTimeout(() => window.location.href = '/sales/invoices', 400);
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Failed to update invoice.', 'danger');
                }
                btn.disabled = false;
                btn.textContent = 'Save Changes →';
            }
        } catch (err) {
            console.error('Invoice edit error:', err);
            form.submit();
        }
    };

    window.confirmDeleteInvoice = function() {
        if (!confirm('Are you sure you want to delete invoice <?= e($invoice['invoice_number']) ?>? This action will cancel the invoice and roll back the customer balance.')) {
            return;
        }

        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/sales/invoices/<?= (int)$invoice['id'] ?>/delete';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf_token';
        csrfInput.value = metaToken;
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
    };

    document.addEventListener('DOMContentLoaded', function() {
        if (existingItems && existingItems.length > 0) {
            existingItems.forEach(it => window.addLineItem(it));
        } else {
            const firstProduct = catalogProducts[0] || null;
            window.addLineItem({
                product_id: firstProduct ? firstProduct.id : '',
                quantity: 1,
                rate: firstProduct ? firstProduct.price : 100.00,
                hsn_sac: firstProduct ? firstProduct.hsn : '7318',
                gst_rate: 18
            });
        }
    });
</script>
