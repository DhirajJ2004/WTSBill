<?php
/**
 * WTSBill ERP — Create Tax Invoice View
 *
 * @var array $customers
 * @var array $categories
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

<div class="card" style="max-width: 1000px; margin: 0 auto 2rem auto;">
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
                        <?php 
                            $preselectedCustId = (int)($_GET['customer_id'] ?? 0);
                        ?>
                        <?php foreach ($customers ?? [] as $c): ?>
                            <option value="<?= e((string)$c['id']) ?>" 
                                    <?= ($preselectedCustId === (int)$c['id']) ? 'selected' : '' ?>
                                    data-gstin="<?= e($c['gstin'] ?? '') ?>" 
                                    data-state="<?= e($c['state_code'] ?? '27') ?>">
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

            <!-- Extended GST Invoice Metadata Fields -->
            <div style="background: var(--bg-hover); padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                <h4 style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.75rem;">Order & Shipping Metadata (Optional)</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
                    <div class="form-group">
                        <label for="inv-po-no" style="font-size: 0.8rem;">P.O. Number</label>
                        <input type="text" id="inv-po-no" name="po_no" class="form-control" placeholder="e.g. PO/26-27/001" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="inv-po-date" style="font-size: 0.8rem;">P.O. Date</label>
                        <input type="date" id="inv-po-date" name="po_date" class="form-control" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="inv-challan-no" style="font-size: 0.8rem;">Challan Number</label>
                        <input type="text" id="inv-challan-no" name="challan_no" class="form-control" placeholder="e.g. CH-9901" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="inv-credit-days" style="font-size: 0.8rem;">Bill Credit (Days)</label>
                        <input type="number" id="inv-credit-days" name="bill_credit_days" class="form-control" value="0" min="0" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="inv-delivery-by" style="font-size: 0.8rem;">Delivery By</label>
                        <input type="text" id="inv-delivery-by" name="delivery_by" class="form-control" placeholder="e.g. Courier / By Hand" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group">
                        <label for="inv-receipt-mode" style="font-size: 0.8rem;">Receipt Mode</label>
                        <select id="inv-receipt-mode" name="receipt_mode" class="form-control" style="font-size: 0.85rem;">
                            <option value="Credit" selected>Credit</option>
                            <option value="Cash">Cash</option>
                            <option value="UPI">UPI</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="inv-lr-no" style="font-size: 0.8rem;">LR Number</label>
                        <input type="text" id="inv-lr-no" name="lr_no" class="form-control" placeholder="e.g. LR-4421" style="font-size: 0.85rem;">
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
                        <!-- Dynamically populated via JS -->
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
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 0.5rem; font-size: 1.15rem; color: var(--color-primary);">
                        <strong>Grand Total:</strong>
                        <strong id="summary-grand">₹0.00</strong>
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

    // In-memory products cache keyed by categoryId
    window.categoryProductsCache = {};

    // Pre-populate cache from initial products catalog where category_id is present
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

    window.addLineItem = function(initialData = null) {
        rowCounter++;
        const tbody = document.getElementById('items-table-body');
        const tr = document.createElement('tr');
        tr.id = 'item-row-' + rowCounter;
        tr.className = 'invoice-item-row';
        tr.dataset.rowId = rowCounter;

        // Build Category options
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

        // Preload or fetch products if category is given
        if (initialData && initialData.category_id) {
            window.loadCategoryProducts(rowCounter, parseInt(initialData.category_id), initialData.product_id || null, false);
        } else if (initialData && initialData.product_id) {
            // Find category from catalog products if category_id wasn't specified
            const prod = catalogProducts.find(p => p.id === parseInt(initialData.product_id));
            if (prod && prod.category_id) {
                tr.querySelector('.item-category').value = prod.category_id;
                window.loadCategoryProducts(rowCounter, prod.category_id, initialData.product_id, false);
            }
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

    window.onRowCategoryChange = function(rowId) {
        const row = document.getElementById('item-row-' + rowId);
        if (!row) return;

        const catSelect = row.querySelector('.item-category');
        const categoryId = catSelect.value ? parseInt(catSelect.value) : 0;
        const prodSelect = row.querySelector('.item-product');

        // Clear previous product selection when category changes
        prodSelect.value = '';
        row.querySelector('.item-rate').value = '0.00';
        row.querySelector('.item-hsn').value = '';
        window.recalculateTotals();

        if (!categoryId) {
            prodSelect.innerHTML = '<option value="">-- Select Category First --</option>';
            prodSelect.disabled = false;
            return;
        }

        window.loadCategoryProducts(rowId, categoryId, null, true);
    };

    window.loadCategoryProducts = async function(rowId, categoryId, preselectedProductId = null, isUserChange = false) {
        const row = document.getElementById('item-row-' + rowId);
        if (!row) return;

        const prodSelect = row.querySelector('.item-product');

        // Show loading state while fetching products
        prodSelect.disabled = true;
        prodSelect.innerHTML = '<option value="">Loading products...</option>';

        try {
            let products = null;

            // Fetch products belonging strictly to this category from backend API
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/api/categories/' + encodeURIComponent(categoryId) + '/products', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken
                }
            });

            if (!resp.ok) {
                throw new Error('Server returned HTTP ' + resp.status);
            }

            const result = await resp.json();
            if (result && result.success && Array.isArray(result.products)) {
                products = result.products;
                // Update client-side cache
                window.categoryProductsCache[categoryId] = products;
            } else {
                throw new Error(result.message || 'Failed to load category products.');
            }

            // Populate Product dropdown
            if (products.length === 0) {
                prodSelect.innerHTML = '<option value="">No products available in this category</option>';
                prodSelect.disabled = true;
                if (isUserChange && window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show('No active products found in this category.', 'info');
                }
            } else {
                let optionsHtml = '<option value="">-- Choose Catalog Product --</option>';
                products.forEach(p => {
                    const isSelected = preselectedProductId && parseInt(preselectedProductId) === p.id;
                    optionsHtml += `<option value="${p.id}" data-price="${p.price}" data-hsn="${p.hsn_sac || p.hsn}" data-tax="${p.tax_rate || p.tax}" data-unit="${p.unit || 'Pcs'}" ${isSelected ? 'selected' : ''}>${escapeHtml(p.name)} (₹${parseFloat(p.price).toFixed(2)})</option>`;
                });
                prodSelect.innerHTML = optionsHtml;
                prodSelect.disabled = false;

                if (preselectedProductId) {
                    prodSelect.value = preselectedProductId;
                    window.onRowProductChange(rowId, false);
                }
            }
        } catch (err) {
            console.error('Category products fetch error:', err);

            // Resilient fallback to pre-cached data if network fails
            if (window.categoryProductsCache[categoryId] && window.categoryProductsCache[categoryId].length > 0) {
                const cachedProducts = window.categoryProductsCache[categoryId];
                let optionsHtml = '<option value="">-- Choose Catalog Product --</option>';
                cachedProducts.forEach(p => {
                    const isSelected = preselectedProductId && parseInt(preselectedProductId) === p.id;
                    optionsHtml += `<option value="${p.id}" data-price="${p.price}" data-hsn="${p.hsn_sac || p.hsn}" data-tax="${p.tax_rate || p.tax}" data-unit="${p.unit || 'Pcs'}" ${isSelected ? 'selected' : ''}>${escapeHtml(p.name)} (₹${parseFloat(p.price).toFixed(2)})</option>`;
                });
                prodSelect.innerHTML = optionsHtml;
                prodSelect.disabled = false;
                if (preselectedProductId) {
                    prodSelect.value = preselectedProductId;
                }
            } else {
                prodSelect.innerHTML = '<option value="">Failed to load products</option>';
                prodSelect.disabled = false;
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show('Failed to fetch products for selected category.', 'danger');
                }
            }
        }

        window.recalculateTotals();
    };

    window.onRowProductChange = function(rowId, overwriteRate = true) {
        const row = document.getElementById('item-row-' + rowId);
        if (!row) return;

        const sel = row.querySelector('.item-product');
        const opt = sel.options[sel.selectedIndex];
        if (opt && opt.dataset.price) {
            if (overwriteRate) {
                row.querySelector('.item-rate').value = parseFloat(opt.dataset.price).toFixed(2);
            }
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

    window.submitInvoiceForm = async function(e) {
        e.preventDefault();
        const form = document.getElementById('invoice-create-form');
        const btn = document.getElementById('inv-submit-btn');

        btn.disabled = true;
        btn.textContent = 'Generating Invoice...';

        const formData = new FormData(form);

        // Also populate backward-compatible single item fields from first row
        const firstRow = document.querySelector('.invoice-item-row');
        if (firstRow) {
            const catId = firstRow.querySelector('.item-category').value;
            const pId = firstRow.querySelector('.item-product').value;
            const hsn = firstRow.querySelector('.item-hsn').value;
            const qty = firstRow.querySelector('.item-qty').value;
            const rate = firstRow.querySelector('.item-rate').value;
            const gst = firstRow.querySelector('.item-gst').value;
            formData.set('category_id', catId);
            formData.set('product_id', pId);
            formData.set('hsn_sac', hsn);
            formData.set('quantity', qty);
            formData.set('rate', rate);
            formData.set('gst_rate', gst);
        }

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
            form.submit();
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize default initial line item
        const firstCat = categoriesList[0] || null;
        if (firstCat) {
            window.addLineItem({
                category_id: firstCat.id,
                quantity: 1,
                rate: 0,
                hsn_sac: '',
                gst_rate: 18
            });
        } else {
            window.addLineItem();
        }

        // Auto-populate customer fields if pre-selected
        if (document.getElementById('inv-customer') && document.getElementById('inv-customer').value) {
            window.onCustomerChange();
        }
    });
</script>
