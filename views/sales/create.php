<?php
/**
 * WTSBill ERP — Create Tax Invoice View
 *
 * @var array $customers
 * @var array $products
 */
$errorMsg = \WTSBill\Core\Session::getFlash('error');
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Generate Tax Invoice</h1>
        <p class="page-subtitle">Create GST-compliant sales invoice, automatic CGST/SGST/IGST tax calculation, and ledger posting</p>
    </div>
    <div>
        <a href="/sales/invoices" class="btn btn-secondary">&larr; Back to Invoices</a>
    </div>
</div>

<div class="card" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">New Tax Invoice Form</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;">
                <?= e($errorMsg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/sales/invoices/create" id="invoice-create-form" onsubmit="window.submitInvoiceForm(event)">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label for="inv-customer">Customer Name <span style="color: var(--danger);">*</span></label>
                    <select id="inv-customer" name="customer_id" class="form-control" required autofocus onchange="window.onCustomerChange()">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers ?? [] as $c): ?>
                            <option value="<?= e((string)$c['id']) ?>" data-gstin="<?= e($c['gstin'] ?? '') ?>" data-state="<?= e($c['state_code'] ?? '27') ?>">
                                <?= e($c['name']) ?> (<?= e($c['gstin'] ?: 'Unregistered') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="inv-date">Invoice Date <span style="color: var(--danger);">*</span></label>
                    <input type="date" id="inv-date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="inv-due-date">Due Date <span style="color: var(--danger);">*</span></label>
                    <input type="date" id="inv-due-date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>

                <div class="form-group">
                    <label for="inv-pos">Place of Supply <span style="color: var(--danger);">*</span></label>
                    <select id="inv-pos" name="place_of_supply" class="form-control" required onchange="window.recalculateTotals()">
                        <option value="27" selected>27 - Maharashtra (Intra-state: CGST + SGST)</option>
                        <option value="24">24 - Gujarat (Inter-state: IGST)</option>
                        <option value="07">07 - Delhi (Inter-state: IGST)</option>
                        <option value="29">29 - Karnataka (Inter-state: IGST)</option>
                        <option value="33">33 - Tamil Nadu (Inter-state: IGST)</option>
                    </select>
                </div>
            </div>

            <!-- Line Items Table -->
            <h4 style="font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary);">Invoice Line Items</h4>
            <div class="table-container" style="margin-bottom: 1.5rem;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Product / Item</th>
                            <th style="width: 15%;">HSN/SAC</th>
                            <th style="width: 15%; text-align: right;">Qty</th>
                            <th style="width: 15%; text-align: right;">Rate (₹)</th>
                            <th style="width: 20%; text-align: right;">GST Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select id="line-product" name="product_id" class="form-control" style="font-size: 0.85rem;" onchange="window.onProductChange()">
                                    <option value="">-- Choose Catalog Product --</option>
                                    <?php foreach ($products ?? [] as $p): ?>
                                        <option value="<?= e((string)$p['id']) ?>" 
                                                data-price="<?= (float)$p['selling_price'] ?>"
                                                data-hsn="<?= e($p['hsn_sac'] ?? '7318') ?>"
                                                data-tax="<?= (float)$p['tax_rate'] ?>">
                                            <?= e($p['name']) ?> (<?= format_currency((float)$p['selling_price']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" id="line-hsn" name="hsn_sac" class="form-control" value="7318" style="font-size: 0.85rem;">
                            </td>
                            <td>
                                <input type="number" id="line-qty" name="quantity" class="form-control" value="10" min="1" step="0.01" style="text-align: right; font-size: 0.85rem;" oninput="window.recalculateTotals()">
                            </td>
                            <td>
                                <input type="number" id="line-rate" name="rate" class="form-control" value="45.00" min="0" step="0.01" style="text-align: right; font-size: 0.85rem;" oninput="window.recalculateTotals()">
                            </td>
                            <td>
                                <select id="line-gst" name="gst_rate" class="form-control" style="font-size: 0.85rem;" onchange="window.recalculateTotals()">
                                    <option value="18" selected>18% (Standard)</option>
                                    <option value="12">12%</option>
                                    <option value="5">5%</option>
                                    <option value="0">0% (Exempt)</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals Summary Card -->
            <div style="background: var(--bg-hover); padding: 1rem 1.5rem; border-radius: var(--radius); margin-bottom: 1.5rem; display: flex; justify-content: flex-end;">
                <div style="width: 280px; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.9rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Taxable Subtotal:</span>
                        <strong id="summary-subtotal">₹450.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;" id="summary-tax-row">
                        <span style="color: var(--text-muted);" id="summary-tax-label">CGST 9% + SGST 9%:</span>
                        <strong id="summary-tax-val">₹81.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 0.5rem; font-size: 1.1rem; color: var(--color-primary);">
                        <strong>Grand Total:</strong>
                        <strong id="summary-grand">₹531.00</strong>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="inv-notes">Terms & Notes</label>
                <textarea id="inv-notes" name="notes" class="form-control" rows="2" placeholder="Payment terms, bank details, or delivery instructions">Payment due within 30 days of invoice date.</textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <a href="/sales/invoices" class="btn btn-secondary">Cancel</a>
                <button type="submit" id="inv-submit-btn" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-size: 0.95rem;">Save & Generate Invoice &rarr;</button>
            </div>
        </form>
    </div>
</div>

<script>
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

    window.onProductChange = function() {
        const select = document.getElementById('line-product');
        const opt = select.options[select.selectedIndex];
        if (opt && opt.dataset.price) {
            document.getElementById('line-rate').value = parseFloat(opt.dataset.price).toFixed(2);
            document.getElementById('line-hsn').value = opt.dataset.hsn || '7318';
            document.getElementById('line-gst').value = opt.dataset.tax || '18';
        }
        window.recalculateTotals();
    };

    window.recalculateTotals = function() {
        const qty = parseFloat(document.getElementById('line-qty').value) || 0;
        const rate = parseFloat(document.getElementById('line-rate').value) || 0;
        const gstRate = parseFloat(document.getElementById('line-gst').value) || 0;
        const pos = document.getElementById('inv-pos').value;

        const taxable = Math.round(qty * rate * 100) / 100;
        const taxVal = Math.round(taxable * (gstRate / 100) * 100) / 100;
        const grand = Math.round(taxable + taxVal);

        document.getElementById('summary-subtotal').textContent = '₹' + taxable.toFixed(2);

        const isIntraState = (pos === '27');
        if (isIntraState) {
            const split = (gstRate / 2).toFixed(1);
            document.getElementById('summary-tax-label').textContent = `CGST ${split}% + SGST ${split}%:`;
        } else {
            document.getElementById('summary-tax-label').textContent = `IGST ${gstRate}%:`;
        }
        document.getElementById('summary-tax-val').textContent = '₹' + taxVal.toFixed(2);
        document.getElementById('summary-grand').textContent = '₹' + grand.toFixed(2);
    };

    window.submitInvoiceForm = async function(e) {
        e.preventDefault();
        const form = document.getElementById('invoice-create-form');
        const btn = document.getElementById('inv-submit-btn');

        btn.disabled = true;
        btn.textContent = 'Generating Invoice...';

        const formData = new FormData(form);

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/sales/invoices/create', {
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
                    window.WTSBill.Toast.show(result.message || 'Invoice generated successfully!', 'success');
                }
                setTimeout(() => window.location.href = '/sales/invoices', 400);
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Failed to generate invoice. Please check the form.', 'danger');
                }
                btn.disabled = false;
                btn.textContent = 'Save & Generate Invoice →';
            }
        } catch (err) {
            console.error('Invoice create error:', err);
            form.submit(); // fallback standard submit
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        window.recalculateTotals();
    });
</script>
