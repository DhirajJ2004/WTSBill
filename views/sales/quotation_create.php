<?php
/**
 * WTSBill ERP — Create Sales Quotation View
 *
 * @var array $customers
 * @var array $categories
 * @var array $products
 */
$errorMsg = \WTSBill\Core\Session::getFlash('error');
?>
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">Generate Sales Quotation</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.875rem;">Create formal commercial quotation, estimate bid, multi-line pricing, and automated GST tax calculations</p>
    </div>
    <div>
        <a href="/sales/quotations" class="btn btn-secondary" style="font-size: 0.85rem;">&larr; Back to Quotations</a>
    </div>
</div>

<div class="card" style="max-width: 1040px; margin: 0 auto 2rem auto;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="margin: 0;">New Sales Quotation Form</h3>
        <span class="badge badge-info">Auto Numbering</span>
    </div>
    <div class="card-body">
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;">
                <?= e($errorMsg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/sales/quotations/create" id="quotation-create-form" onsubmit="window.submitQuotationForm(event)">
            <input type="hidden" name="_csrf_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">
            <input type="hidden" id="quote-status" name="status" value="SENT">

            <!-- Primary Header Information -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                        <label for="quote-customer" style="margin: 0; font-size: 0.85rem; font-weight: 600;">Customer Name <span style="color: var(--danger);">*</span></label>
                        <button type="button" class="btn btn-sm btn-link" onclick="window.openNewCustomerModal()" style="font-size: 0.8rem; font-weight: 600; text-decoration: none; padding: 0; color: var(--color-primary); cursor: pointer; border: none; background: transparent;">
                            + New Customer
                        </button>
                    </div>
                    <select id="quote-customer" name="customer_id" class="form-control" required autofocus onchange="window.onCustomerChange()">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers ?? [] as $c): ?>
                            <option value="<?= e((string)$c['id']) ?>" 
                                    data-gstin="<?= e($c['gstin'] ?? '') ?>" 
                                    data-state="<?= e($c['state_code'] ?? '27') ?>"
                                    data-address="<?= e($c['address_line1'] ?? '') ?>"
                                    data-city="<?= e($c['city'] ?? '') ?>">
                                <?= e($c['name']) ?> <?= !empty($c['company_name']) ? ('(' . e($c['company_name']) . ')') : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quote-date" style="font-size: 0.85rem; font-weight: 600;">Quotation Date <span style="color: var(--danger);">*</span></label>
                    <input type="date" id="quote-date" name="quotation_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="quote-valid-until" style="font-size: 0.85rem; font-weight: 600;">Valid Until / Expiry <span style="color: var(--danger);">*</span></label>
                    <input type="date" id="quote-valid-until" name="valid_until" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>

                <div class="form-group">
                    <label for="quote-pos" style="font-size: 0.85rem; font-weight: 600;">Place of Supply <span style="color: var(--danger);">*</span></label>
                    <select id="quote-pos" name="place_of_supply" class="form-control" required onchange="window.recalculateTotals()">
                        <option value="27" selected>27 - Maharashtra (Intra-state: CGST + SGST)</option>
                        <option value="24">24 - Gujarat (Inter-state: IGST)</option>
                        <option value="07">07 - Delhi (Inter-state: IGST)</option>
                        <option value="29">29 - Karnataka (Inter-state: IGST)</option>
                        <option value="33">33 - Tamil Nadu (Inter-state: IGST)</option>
                        <option value="09">09 - Uttar Pradesh (Inter-state: IGST)</option>
                    </select>
                </div>
            </div>

            <!-- Commercial Terms & Metadata Section -->
            <div style="background: var(--bg-hover); padding: 1rem 1.25rem; border-radius: var(--radius); margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                <h4 style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.75rem;">Commercial Metadata & Shipping</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem;">
                    <div class="form-group">
                        <label for="quote-ref-no" style="font-size: 0.8rem;">Ref # / Subject</label>
                        <input type="text" id="quote-ref-no" name="reference_no" class="form-control" placeholder="e.g. RFQ-2026/OCT-88" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="quote-kind-attn" style="font-size: 0.8rem;">Kind Attn / Contact Person</label>
                        <input type="text" id="quote-kind-attn" name="kind_attn" class="form-control" placeholder="e.g. Mr. Rajesh Sharma" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="quote-payment-days" style="font-size: 0.8rem;">Credit / Validity Days</label>
                        <input type="number" id="quote-payment-days" name="payment_days" class="form-control" value="30" min="0" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="quote-billing-address" style="font-size: 0.8rem;">Billing Address</label>
                        <input type="text" id="quote-billing-address" name="billing_address" class="form-control" placeholder="Client billing address" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="quote-shipping-address" style="font-size: 0.8rem;">Shipping Destination (if different)</label>
                        <input type="text" id="quote-shipping-address" name="shipping_address" class="form-control" placeholder="Delivery site or depot address" style="font-size: 0.85rem;">
                    </div>
                </div>
            </div>

            <!-- Line Items Table Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                <h4 style="font-size: 0.95rem; font-weight: 600; margin: 0; color: var(--text-primary);">Quotation Line Items</h4>
                <button type="button" id="btn-add-line-item" class="btn btn-sm btn-secondary" onclick="window.addLineItem()" style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; padding: 0.35rem 0.85rem; border: 1px dashed var(--primary); background: var(--primary-soft); color: var(--primary); font-weight: 600;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>+ Add Item</span>
                </button>
            </div>

            <style>
                #items-table th, #items-table td {
                    padding: 0.5rem 0.4rem;
                    vertical-align: middle;
                }
                #items-table .form-control {
                    padding: 0.4rem 0.55rem;
                }
            </style>
            <div class="table-container" style="margin-bottom: 1rem; overflow-x: auto;">
                <table class="table" id="items-table" style="width: 100%; min-width: 860px;">
                    <thead>
                        <tr>
                            <th style="width: 19%;">Category</th>
                            <th style="width: 24%;">Product / Item</th>
                            <th style="width: 12%;">HSN/SAC</th>
                            <th style="width: 9%; text-align: right;">Qty</th>
                            <th style="width: 12%; text-align: right;">Rate (₹)</th>
                            <th style="width: 12%; text-align: right;">GST Rate</th>
                            <th style="width: 10%; text-align: right;">Amount (₹)</th>
                            <th style="width: 2%; text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-table-body">
                        <!-- Populated dynamically via JS -->
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
                        <strong id="summary-items-count">0 Item(s)</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Taxable Subtotal:</span>
                        <strong id="summary-subtotal">₹0.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;" id="summary-tax-row">
                        <span style="color: var(--text-muted);" id="summary-tax-label">CGST + SGST:</span>
                        <strong id="summary-tax-val">₹0.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Round Off:</span>
                        <strong id="summary-round-off">₹0.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 0.5rem; font-size: 1.15rem; color: var(--color-primary);">
                        <strong>Grand Total:</strong>
                        <strong id="summary-grand">₹0.00</strong>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="quote-notes">Terms & Conditions / Special Notes</label>
                <textarea id="quote-notes" name="notes" class="form-control" rows="3" placeholder="Commercial conditions, delivery lead time, warranty, or transport terms">1) Price Basis: Ex-works Pune, packing & freight extra at actuals.
2) Validity: 30 days from quotation date.
3) Taxes: GST applicable as indicated above.</textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; align-items: center;">
                <a href="/sales/quotations" class="btn btn-secondary">Cancel</a>
                <button type="button" class="btn btn-secondary" onclick="window.saveQuoteAsDraft()" style="padding: 0.65rem 1.25rem; font-size: 0.9rem;">Save as Draft</button>
                <button type="submit" id="quote-submit-btn" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-size: 0.95rem;">Save & Generate Quotation &rarr;</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Quick Create New Customer (AJAX, No Reload) -->
<div id="new-customer-modal" class="modal-backdrop" role="dialog" aria-modal="true" style="display: none;">
    <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title">Create New Customer</h3>
            <button type="button" class="modal-close" onclick="window.closeNewCustomerModal()">&times;</button>
        </div>
        <form id="new-customer-form" onsubmit="window.submitNewCustomer(event)">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div id="ncc-error-alert" class="alert alert-danger" style="display: none; padding: 0.6rem 0.85rem; font-size: 0.85rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div class="form-group">
                    <label for="ncc-name" style="font-size: 0.85rem; font-weight: 600;">Customer / Business Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="ncc-name" name="name" class="form-control" placeholder="e.g. Acme Engineering Corp" required autofocus>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="ncc-phone" style="font-size: 0.85rem; font-weight: 600;">Phone / Mobile</label>
                        <input type="text" id="ncc-phone" name="phone" class="form-control" placeholder="e.g. 9881032297">
                    </div>
                    <div class="form-group">
                        <label for="ncc-email" style="font-size: 0.85rem; font-weight: 600;">Email Address</label>
                        <input type="email" id="ncc-email" name="email" class="form-control" placeholder="client@domain.com">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="ncc-gstin" style="font-size: 0.85rem; font-weight: 600;">GSTIN (15 Digits)</label>
                        <input type="text" id="ncc-gstin" name="gstin" class="form-control" placeholder="27AAAAA0000A1Z5" maxlength="15" oninput="window.onQuickGstinInput(this.value)">
                    </div>
                    <div class="form-group">
                        <label for="ncc-state-code" style="font-size: 0.85rem; font-weight: 600;">State / Code <span style="color: var(--danger);">*</span></label>
                        <select id="ncc-state-code" name="state_code" class="form-control" required>
                            <option value="27" selected>27 - Maharashtra</option>
                            <option value="24">24 - Gujarat</option>
                            <option value="07">07 - Delhi</option>
                            <option value="29">29 - Karnataka</option>
                            <option value="33">33 - Tamil Nadu</option>
                            <option value="09">09 - Uttar Pradesh</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="ncc-address" style="font-size: 0.85rem; font-weight: 600;">Billing Address</label>
                    <textarea id="ncc-address" name="address_line1" class="form-control" rows="2" placeholder="Street, Industrial Area, City"></textarea>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeNewCustomerModal()">Cancel</button>
                <button type="submit" id="ncc-submit-btn" class="btn btn-primary">Save & Select Customer &rarr;</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden categories JSON for client row generation -->
<script id="categories-data" type="application/json">
<?= json_encode(array_map(function($c) {
    return [
        'id' => (int)$c['id'],
        'name' => (string)$c['name'],
        'code' => (string)($c['code'] ?? ''),
    ];
}, $categories ?? [])) ?>
</script>

<!-- Hidden catalog products JSON for client caching / fallback -->
<script id="catalog-products-data" type="application/json">
<?= json_encode(array_map(function($p) {
    return [
        'id' => (int)$p['id'],
        'category_id' => (int)($p['category_id'] ?? 0),
        'name' => (string)$p['name'],
        'sku' => (string)($p['sku'] ?? ''),
        'price' => (float)$p['selling_price'],
        'hsn' => (string)($p['hsn_sac'] ?? '7318'),
        'tax' => (float)($p['tax_rate'] ?? 18),
        'unit' => (string)($p['unit'] ?? 'Pcs'),
    ];
}, $products ?? [])) ?>
</script>

<script>
    const categoriesList = JSON.parse(document.getElementById('categories-data').textContent || '[]');
    const catalogProducts = JSON.parse(document.getElementById('catalog-products-data').textContent || '[]');

    window.categoryProductsCache = {};

    catalogProducts.forEach(p => {
        if (p.category_id) {
            if (!window.categoryProductsCache[p.category_id]) {
                window.categoryProductsCache[p.category_id] = [];
            }
            window.categoryProductsCache[p.category_id].push(p);
        }
    });

    let rowCounter = 0;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    window.onCustomerChange = function() {
        const select = document.getElementById('quote-customer');
        const selected = select.options[select.selectedIndex];
        if (!selected || !selected.value) return;

        const state = selected.dataset.state;
        const gstin = selected.dataset.gstin;
        const addr = selected.dataset.address;

        if (state) {
            const posSelect = document.getElementById('quote-pos');
            for (let i = 0; i < posSelect.options.length; i++) {
                if (posSelect.options[i].value === state) {
                    posSelect.selectedIndex = i;
                    break;
                }
            }
        }

        if (addr) {
            const addrInput = document.getElementById('quote-billing-address');
            if (addrInput && !addrInput.value) {
                addrInput.value = addr;
            }
        }

        window.recalculateTotals();
    };

    window.addLineItem = function(initialData = null) {
        rowCounter++;
        const tbody = document.getElementById('items-table-body');
        const tr = document.createElement('tr');
        tr.id = 'item-row-' + rowCounter;
        tr.className = 'quote-item-row';
        tr.dataset.rowId = rowCounter;

        let catOptionsHtml = '<option value="">-- Select Category --</option>';
        categoriesList.forEach(c => {
            const isCatSelected = initialData && parseInt(initialData.category_id) === c.id;
            catOptionsHtml += `<option value="${c.id}" ${isCatSelected ? 'selected' : ''}>${escapeHtml(c.name)}</option>`;
        });

        const defaultQty = initialData && initialData.quantity !== undefined ? initialData.quantity : 1;
        const defaultRate = initialData && initialData.rate !== undefined ? initialData.rate : 0.00;
        const defaultHsn = initialData && initialData.hsn_sac ? initialData.hsn_sac : '';
        const defaultGst = initialData && initialData.gst_rate !== undefined ? initialData.gst_rate : 18;

        tr.innerHTML = `
            <td>
                <select name="items[${rowCounter}][category_id]" class="form-control item-category" style="font-size: 0.85rem;" onchange="window.onRowCategoryChange(${rowCounter})">
                    ${catOptionsHtml}
                </select>
            </td>
            <td>
                <select name="items[${rowCounter}][product_id]" class="form-control item-product" style="font-size: 0.85rem;" onchange="window.onRowProductChange(${rowCounter})">
                    <option value="">-- Select Category First --</option>
                </select>
            </td>
            <td>
                <input type="text" name="items[${rowCounter}][hsn_sac]" class="form-control item-hsn" value="${escapeHtml(defaultHsn)}" placeholder="HSN/SAC" style="font-size: 0.85rem;">
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

        if (initialData && initialData.category_id) {
            window.loadCategoryProducts(rowCounter, parseInt(initialData.category_id), initialData.product_id || null, false);
        } else if (initialData && initialData.product_id) {
            const prod = catalogProducts.find(p => p.id === parseInt(initialData.product_id));
            if (prod && prod.category_id) {
                tr.querySelector('.item-category').value = prod.category_id;
                window.loadCategoryProducts(rowCounter, prod.category_id, initialData.product_id, false);
            }
        }

        window.recalculateTotals();
    };

    window.removeLineItem = function(rowId) {
        const rows = document.querySelectorAll('.quote-item-row');
        if (rows.length <= 1) {
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('A quotation must have at least one line item.', 'warning');
            }
            return;
        }
        const row = document.getElementById('item-row-' + rowId);
        if (row) {
            row.remove();
            window.recalculateTotals();
        }
    };

    window.onRowCategoryChange = function(rowId) {
        const row = document.getElementById('item-row-' + rowId);
        if (!row) return;

        const catSelect = row.querySelector('.item-category');
        const categoryId = catSelect.value ? parseInt(catSelect.value) : 0;
        const prodSelect = row.querySelector('.item-product');

        prodSelect.innerHTML = '<option value="">Loading products...</option>';
        prodSelect.disabled = true;

        row.querySelector('.item-rate').value = '0.00';
        row.querySelector('.item-hsn').value = '';
        row.querySelector('.item-line-total').textContent = '₹0.00';
        window.recalculateTotals();

        if (!categoryId) {
            prodSelect.innerHTML = '<option value="">-- Select Category First --</option>';
            prodSelect.disabled = false;
            return;
        }

        window.loadCategoryProducts(rowId, categoryId, null, true);
    };

    window.loadCategoryProducts = function(rowId, categoryId, preselectProductId = null, triggerChange = true) {
        const row = document.getElementById('item-row-' + rowId);
        if (!row) return;

        const prodSelect = row.querySelector('.item-product');

        function renderProducts(productsList) {
            prodSelect.disabled = false;
            if (!productsList || productsList.length === 0) {
                prodSelect.innerHTML = '<option value="">No products found in category</option>';
                return;
            }

            let html = '<option value="">-- Select Product --</option>';
            productsList.forEach(p => {
                const isSelected = preselectProductId && parseInt(preselectProductId) === p.id;
                html += `<option value="${p.id}" 
                            data-price="${p.price}" 
                            data-hsn="${escapeHtml(p.hsn)}" 
                            data-tax="${p.tax}" 
                            data-unit="${escapeHtml(p.unit || '')}" 
                            ${isSelected ? 'selected' : ''}>
                            ${escapeHtml(p.name)} (₹${parseFloat(p.price).toFixed(2)})
                         </option>`;
            });
            prodSelect.innerHTML = html;

            if (preselectProductId) {
                prodSelect.value = preselectProductId;
                if (triggerChange) {
                    window.onRowProductChange(rowId);
                }
            }
        }

        if (window.categoryProductsCache[categoryId]) {
            renderProducts(window.categoryProductsCache[categoryId]);
            return;
        }

        fetch('/api/categories/' + categoryId + '/products', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => {
            if (!res.ok) throw new Error('Network error loading products');
            return res.json();
        })
        .then(data => {
            if (data.success && Array.isArray(data.products)) {
                window.categoryProductsCache[categoryId] = data.products.map(p => ({
                    id: parseInt(p.id),
                    category_id: categoryId,
                    name: p.name,
                    sku: p.sku || '',
                    price: parseFloat(p.price || p.selling_price || 0),
                    hsn: p.hsn || p.hsn_sac || '',
                    tax: parseFloat(p.tax || p.tax_rate || 18),
                    unit: p.unit || 'Pcs'
                }));
                renderProducts(window.categoryProductsCache[categoryId]);
            } else {
                prodSelect.innerHTML = '<option value="">No products found</option>';
                prodSelect.disabled = false;
            }
        })
        .catch(err => {
            console.error('Error fetching category products:', err);
            const fallback = catalogProducts.filter(p => p.category_id === categoryId);
            renderProducts(fallback);
        });
    };

    window.onRowProductChange = function(rowId) {
        const row = document.getElementById('item-row-' + rowId);
        if (!row) return;

        const prodSelect = row.querySelector('.item-product');
        const selected = prodSelect.options[prodSelect.selectedIndex];

        if (!selected || !selected.value) {
            row.querySelector('.item-rate').value = '0.00';
            row.querySelector('.item-hsn').value = '';
            window.recalculateTotals();
            return;
        }

        const price = selected.dataset.price ? parseFloat(selected.dataset.price) : 0;
        const hsn = selected.dataset.hsn || '';
        const tax = selected.dataset.tax ? parseFloat(selected.dataset.tax) : 18;

        row.querySelector('.item-rate').value = price.toFixed(2);
        row.querySelector('.item-hsn').value = hsn;

        const gstSelect = row.querySelector('.item-gst');
        if (gstSelect) {
            for (let i = 0; i < gstSelect.options.length; i++) {
                if (parseFloat(gstSelect.options[i].value) === tax) {
                    gstSelect.selectedIndex = i;
                    break;
                }
            }
        }

        window.recalculateTotals();
    };

    window.recalculateTotals = function() {
        const rows = document.querySelectorAll('.quote-item-row');
        let totalItems = 0;
        let taxableSubtotal = 0.0;
        let totalCgst = 0.0;
        let totalSgst = 0.0;
        let totalIgst = 0.0;

        const posSelect = document.getElementById('quote-pos');
        const posCode = posSelect ? posSelect.value : '27';
        const isIntraState = (posCode === '27');

        rows.forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value || 0);
            const rate = parseFloat(row.querySelector('.item-rate').value || 0);
            const gstRate = parseFloat(row.querySelector('.item-gst').value || 0);

            if (qty > 0 && rate >= 0) {
                totalItems++;
                const lineTaxable = Math.round((qty * rate) * 100) / 100;
                taxableSubtotal += lineTaxable;

                let lineTax = 0.0;
                if (isIntraState) {
                    const halfRate = gstRate / 2;
                    const cgst = Math.round((lineTaxable * (halfRate / 100)) * 100) / 100;
                    const sgst = Math.round((lineTaxable * (halfRate / 100)) * 100) / 100;
                    totalCgst += cgst;
                    totalSgst += sgst;
                    lineTax = cgst + sgst;
                } else {
                    const igst = Math.round((lineTaxable * (gstRate / 100)) * 100) / 100;
                    totalIgst += igst;
                    lineTax = igst;
                }

                const lineTotal = lineTaxable + lineTax;
                row.querySelector('.item-line-total').textContent = '₹' + lineTotal.toFixed(2);
            } else {
                row.querySelector('.item-line-total').textContent = '₹0.00';
            }
        });

        const totalTax = isIntraState ? (totalCgst + totalSgst) : totalIgst;
        const rawGrandTotal = taxableSubtotal + totalTax;
        const roundedGrand = Math.round(rawGrandTotal);
        const roundOff = Math.round((roundedGrand - rawGrandTotal) * 100) / 100;

        document.getElementById('summary-items-count').textContent = totalItems + ' Item(s)';
        document.getElementById('summary-subtotal').textContent = '₹' + taxableSubtotal.toFixed(2);

        const taxLabel = document.getElementById('summary-tax-label');
        const taxVal = document.getElementById('summary-tax-val');
        if (isIntraState) {
            taxLabel.textContent = 'CGST + SGST:';
            taxVal.textContent = '₹' + (totalCgst + totalSgst).toFixed(2);
        } else {
            taxLabel.textContent = 'IGST (Inter-state):';
            taxVal.textContent = '₹' + totalIgst.toFixed(2);
        }

        document.getElementById('summary-round-off').textContent = (roundOff >= 0 ? '+' : '') + '₹' + roundOff.toFixed(2);
        document.getElementById('summary-grand').textContent = '₹' + roundedGrand.toFixed(2);
    };

    window.saveQuoteAsDraft = function() {
        document.getElementById('quote-status').value = 'DRAFT';
        const form = document.getElementById('quotation-create-form');
        form.requestSubmit ? form.requestSubmit() : form.submit();
    };

    window.submitQuotationForm = function(event) {
        const custSelect = document.getElementById('quote-customer');
        if (!custSelect.value) {
            event.preventDefault();
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('Please select a customer.', 'warning');
            }
            custSelect.focus();
            return false;
        }

        const rows = document.querySelectorAll('.quote-item-row');
        if (rows.length === 0) {
            event.preventDefault();
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('Please add at least one line item to the quotation.', 'warning');
            }
            return false;
        }

        const btn = document.getElementById('quote-submit-btn');
        btn.disabled = true;
        btn.textContent = 'Generating Quotation...';
        return true;
    };

    // Quick New Customer Modal logic (AJAX, Zero Data Loss)
    window.openNewCustomerModal = function() {
        document.getElementById('ncc-error-alert').style.display = 'none';
        document.getElementById('new-customer-form').reset();
        document.getElementById('new-customer-modal').style.display = 'flex';
        document.getElementById('ncc-name').focus();
    };

    window.closeNewCustomerModal = function() {
        document.getElementById('new-customer-modal').style.display = 'none';
    };

    window.onQuickGstinInput = function(val) {
        val = val.trim().toUpperCase();
        if (val.length >= 2) {
            const stateCode = val.substring(0, 2);
            const stateSelect = document.getElementById('ncc-state-code');
            for (let i = 0; i < stateSelect.options.length; i++) {
                if (stateSelect.options[i].value === stateCode) {
                    stateSelect.selectedIndex = i;
                    break;
                }
            }
        }
    };

    window.submitNewCustomer = async function(event) {
        event.preventDefault();
        const form = event.target;
        const btn = document.getElementById('ncc-submit-btn');
        const errAlert = document.getElementById('ncc-error-alert');
        errAlert.style.display = 'none';

        const name = document.getElementById('ncc-name').value.trim();
        if (!name) {
            errAlert.textContent = 'Customer name is required.';
            errAlert.style.display = 'block';
            return;
        }

        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        payload._csrf_token = metaToken;

        btn.disabled = true;
        btn.textContent = 'Saving Customer...';

        try {
            const resp = await fetch('/customers', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await resp.json();
            if (data.success && data.customer) {
                const c = data.customer;
                const custSelect = document.getElementById('quote-customer');
                
                // Add new option and select it
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.dataset.gstin = c.gstin || '';
                opt.dataset.state = c.state_code || '27';
                opt.dataset.address = c.address_line1 || '';
                opt.dataset.city = c.city || '';
                opt.textContent = `${c.name} (${c.gstin || 'Unregistered'})`;
                custSelect.appendChild(opt);
                custSelect.value = c.id;

                window.onCustomerChange();
                window.closeNewCustomerModal();

                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(`Customer '${c.name}' created and selected successfully!`, 'success');
                }
            } else {
                errAlert.textContent = data.message || 'Failed to create customer.';
                errAlert.style.display = 'block';
            }
        } catch (err) {
            console.error('Customer create error:', err);
            errAlert.textContent = 'Server connection error while creating customer.';
            errAlert.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save & Select Customer →';
        }
    };

    // Initialize first row
    document.addEventListener('DOMContentLoaded', function() {
        window.addLineItem();
    });
</script>
