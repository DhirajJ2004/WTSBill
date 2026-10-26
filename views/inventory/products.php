<?php
/**
 * WTSBill ERP — Product Catalog List View & Registration Workflow
 *
 * @var array $products
 * @var array $categories
 * @var array $units
 * @var string $search
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Product & Item Catalog</h1>
        <p class="page-subtitle">Inventory items, SKU definitions, HSN/SAC codes, GST tax rates, and stock valuation</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="product-search" class="form-control" placeholder="Search products..." style="width: 240px; padding: 0.4rem 0.75rem; font-size: 0.85rem;" value="<?= e($search ?? '') ?>">
        <button type="button" class="btn btn-primary" onclick="window.openProductModal()">+ New Product</button>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title">Catalog Inventory (<span id="products-count"><?= count($products ?? []) ?></span> Items)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="products-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product Name & SKU</th>
                    <th>Category</th>
                    <th>HSN / SAC</th>
                    <th style="text-align: right;">Selling Price</th>
                    <th style="text-align: center;">GST %</th>
                    <th style="text-align: right;">Current Stock</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody id="products-table-body">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $p): ?>
                        <tr id="product-row-<?= e((string)$p['id']) ?>">
                            <td><?= e((string)$p['id']) ?></td>
                            <td>
                                <strong><?= e($p['name']) ?></strong>
                                <?php if (!empty($p['sku'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">SKU: <code><?= e($p['sku']) ?></code></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($p['category_name'] ?? 'General') ?>
                            </td>
                            <td><code><?= e($p['hsn_sac'] ?? ($p['hsn_code'] ?? '8471')) ?></code></td>
                            <td style="text-align: right; font-weight: 600;">
                                <?= format_currency((float)($p['selling_price'] ?? ($p['sales_price'] ?? 0))) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-info"><?= (float)($p['tax_rate'] ?? 18) ?>%</span>
                            </td>
                            <td style="text-align: right; font-weight: 600;">
                                <?= e((string)($p['current_stock'] ?? 0)) ?> <?= e($p['unit'] ?? 'Pcs') ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= ($p['is_active'] ?? 1) ? 'success' : 'danger' ?>" id="badge-status-<?= $p['id'] ?>">
                                    <?= ($p['is_active'] ?? 1) ? 'ACTIVE' : 'INACTIVE' ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 0.25rem; justify-content: center;">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="window.editProduct(<?= (int)$p['id'] ?>)">Edit</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-products-row">
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 2rem;">No inventory products found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Product Registration & Edit Modal -->
<div id="product-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <h3 id="product-modal-title" class="modal-title">Register New Product</h3>
            <button type="button" class="modal-close" onclick="window.closeProductModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="product-form" onsubmit="window.saveProduct(event)">
            <input type="hidden" id="prod-id" name="id" value="">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div id="product-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="prod-name">Product / Item Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="prod-name" name="name" class="form-control" placeholder="e.g. Industrial Brass Valve 2inch" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="prod-sku">SKU / Product Code <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="prod-sku" name="sku" class="form-control" placeholder="e.g. HW-BRV-020" data-uppercase required>
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Must be unique across your organization.</small>
                    </div>

                    <div class="form-group">
                        <label for="prod-category">Category</label>
                        <select id="prod-category" name="category_id" class="form-control">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories ?? [] as $cat): ?>
                                <option value="<?= e((string)$cat['id']) ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="prod-unit">Primary Unit</label>
                        <select id="prod-unit" name="unit" class="form-control">
                            <?php if (!empty($units)): ?>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= e($u['short_name']) ?>"><?= e($u['name']) ?> (<?= e($u['short_name']) ?>)</option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="Pcs">Pieces (Pcs)</option>
                                <option value="KG">Kilograms (KG)</option>
                                <option value="Box">Boxes (Box)</option>
                                <option value="Mtrs">Meters (Mtrs)</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="prod-hsn">HSN / SAC Code</label>
                        <input type="text" id="prod-hsn" name="hsn_sac" class="form-control" placeholder="e.g. 84818020" maxlength="10">
                    </div>

                    <div class="form-group">
                        <label for="prod-sales-price">Selling / Sales Price (₹) <span style="color: var(--danger);">*</span></label>
                        <input type="number" id="prod-sales-price" name="sales_price" class="form-control" placeholder="0.00" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="prod-purchase-price">Purchase Price (₹)</label>
                        <input type="number" id="prod-purchase-price" name="purchase_price" class="form-control" placeholder="0.00" step="0.01" min="0">
                    </div>

                    <div class="form-group">
                        <label for="prod-tax-rate">GST Tax Rate <span style="color: var(--danger);">*</span></label>
                        <select id="prod-tax-rate" name="tax_rate" class="form-control" required>
                            <option value="0">0% (Nil / Exempt)</option>
                            <option value="5">5% GST</option>
                            <option value="12">12% GST</option>
                            <option value="18" selected>18% GST (Standard)</option>
                            <option value="28">28% GST</option>
                        </select>
                    </div>

                    <div class="form-group" id="opening-stock-group">
                        <label for="prod-opening-stock">Opening Stock Quantity</label>
                        <input type="number" id="prod-opening-stock" name="opening_stock" class="form-control" placeholder="0" step="0.001" min="0">
                    </div>

                    <div class="form-group">
                        <label for="prod-min-stock">Min Stock Alert Level</label>
                        <input type="number" id="prod-min-stock" name="min_stock_alert" class="form-control" placeholder="10" step="0.001" min="0" value="10">
                    </div>

                    <div class="form-group">
                        <label for="prod-barcode">Barcode / EAN</label>
                        <input type="text" id="prod-barcode" name="barcode" class="form-control" placeholder="e.g. 8901234567890">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="prod-description">Item Description / Technical Notes</label>
                        <textarea id="prod-description" name="description" class="form-control" rows="2" placeholder="Optional product specifications, dimensions, or details..."></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="window.closeProductModal()">Cancel</button>
                <button type="submit" id="product-submit-btn" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
    window.openProductModal = function() {
        const form = document.getElementById('product-form');
        form.reset();
        document.getElementById('prod-id').value = '';
        document.getElementById('product-modal-title').innerText = 'Register New Product';
        document.getElementById('opening-stock-group').style.display = 'block';
        document.getElementById('product-modal-error').style.display = 'none';
        document.getElementById('product-modal-error').innerHTML = '';
        document.getElementById('product-submit-btn').innerText = 'Save Product';
        
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('product-modal');
        } else {
            document.getElementById('product-modal').classList.add('open');
        }
    };

    window.closeProductModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('product-modal');
        } else {
            document.getElementById('product-modal').classList.remove('open');
        }
    };

    window.editProduct = function(id) {
        window.openProductModal();
        document.getElementById('product-modal-title').innerText = 'Edit Product #' + id;
        document.getElementById('prod-id').value = id;
        document.getElementById('opening-stock-group').style.display = 'none'; // Opening stock only on create
        document.getElementById('product-submit-btn').innerText = 'Update Product';

        fetch('/products/' + id, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.product) {
                const p = data.product;
                document.getElementById('prod-name').value = p.name || '';
                document.getElementById('prod-sku').value = p.sku || '';
                document.getElementById('prod-category').value = p.category_id || '';
                document.getElementById('prod-unit').value = p.unit || 'Pcs';
                document.getElementById('prod-hsn').value = p.hsn_sac || '';
                document.getElementById('prod-sales-price').value = p.sales_price || p.selling_price || '0.00';
                document.getElementById('prod-purchase-price').value = p.purchase_price || '0.00';
                document.getElementById('prod-tax-rate').value = parseInt(p.tax_rate) || 18;
                document.getElementById('prod-min-stock').value = p.min_stock_alert || 10;
                document.getElementById('prod-barcode').value = p.barcode || '';
                document.getElementById('prod-description').value = p.description || '';
            }
        })
        .catch(err => {
            console.error('Failed to load product:', err);
        });
    };

    window.saveProduct = function(e) {
        e.preventDefault();
        const form = document.getElementById('product-form');
        const submitBtn = document.getElementById('product-submit-btn');
        const errBox = document.getElementById('product-modal-error');
        const prodId = document.getElementById('prod-id').value;

        errBox.style.display = 'none';
        errBox.innerHTML = '';

        const formData = new FormData(form);
        const payload = {};
        formData.forEach((val, key) => { payload[key] = val; });

        // Client validation
        if (!payload.name || payload.name.trim().length < 2) {
            errBox.innerText = 'Product name is required (minimum 2 characters).';
            errBox.style.display = 'block';
            document.getElementById('prod-name').focus();
            return;
        }

        if (!payload.sku || payload.sku.trim().length < 2) {
            errBox.innerText = 'SKU / Product Code is required (minimum 2 characters).';
            errBox.style.display = 'block';
            document.getElementById('prod-sku').focus();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerText = 'Saving...';

        const endpoint = prodId ? ('/products/' + prodId + '/update') : '/products';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || payload._token;

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok || !data.success) {
                let msg = data.message || 'Validation failed.';
                if (data.errors) {
                    msg += '<br>' + Object.values(data.errors).join('<br>');
                }
                throw new Error(msg);
            }
            return data;
        })
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerText = 'Save Product';
            window.closeProductModal();

            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show(data.message, 'success');
            }

            // Dynamically update UI table
            const p = data.product;
            if (p) {
                const tbody = document.getElementById('products-table-body');
                const noRow = document.getElementById('no-products-row');
                if (noRow) noRow.remove();

                const existingRow = document.getElementById('product-row-' + p.id);
                const rowHtml = `
                    <td>${p.id}</td>
                    <td>
                        <strong>${escapeHtml(p.name)}</strong>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">SKU: <code>${escapeHtml(p.sku)}</code></div>
                    </td>
                    <td>General</td>
                    <td><code>${escapeHtml(p.hsn_sac || '8471')}</code></td>
                    <td style="text-align: right; font-weight: 600;">₹${parseFloat(p.sales_price || 0).toFixed(2)}</td>
                    <td style="text-align: center;"><span class="badge badge-info">${parseFloat(p.tax_rate || 18)}%</span></td>
                    <td style="text-align: right; font-weight: 600;">${parseFloat(p.current_stock || 0).toFixed(3)} ${escapeHtml(p.unit || 'Pcs')}</td>
                    <td style="text-align: center;"><span class="badge badge-success">ACTIVE</span></td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="window.editProduct(${p.id})">Edit</button>
                    </td>
                `;

                if (existingRow) {
                    existingRow.innerHTML = rowHtml;
                } else {
                    const newTr = document.createElement('tr');
                    newTr.id = 'product-row-' + p.id;
                    newTr.innerHTML = rowHtml;
                    newTr.style.animation = 'fadeIn 0.3s ease';
                    tbody.prepend(newTr);

                    const countElem = document.getElementById('products-count');
                    if (countElem) {
                        countElem.innerText = parseInt(countElem.innerText || '0') + 1;
                    }
                }
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerText = prodId ? 'Update Product' : 'Save Product';
            errBox.innerHTML = err.message;
            errBox.style.display = 'block';
        });
    };

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('product-search', 'products-table');
        }
    });
</script>
