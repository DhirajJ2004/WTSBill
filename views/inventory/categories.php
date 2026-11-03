<?php
/**
 * WTSBill ERP — Product Categories View, Management & Taxonomy Workflow
 *
 * @var array $categories
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Product Categories</h1>
        <p class="page-subtitle">Item classifications, category hierarchy, taxonomy codes, and catalog segmentation</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="category-search" class="form-control" placeholder="Search categories..." style="width: 240px; padding: 0.4rem 0.75rem; font-size: 0.85rem;">
        <?php if (\WTSBill\Auth\Gate::can('categories.create')): ?>
            <button type="button" class="btn btn-primary" onclick="window.openCategoryModal()">+ New Category</button>
        <?php endif; ?>
    </div>
</div>

<style>
    #categories-table {
        table-layout: fixed;
        width: 100%;
    }
    #categories-table th, #categories-table td {
        vertical-align: middle;
    }
</style>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Category Taxonomy (<span id="categories-count"><?= count($categories ?? []) ?></span> Groups)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="categories-table">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">#</th>
                    <th style="width: 180px;">Category Name</th>
                    <th style="width: 110px; text-align: center;">Code</th>
                    <th style="width: auto;">Description</th>
                    <th style="width: 130px; text-align: center;">Linked Products</th>
                    <th style="width: 95px; text-align: center;">Status</th>
                    <th style="width: 200px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody id="categories-table-body">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr id="category-row-<?= (int)$cat['id'] ?>">
                            <td style="text-align: center;"><?= e((string)$cat['id']) ?></td>
                            <td><strong class="cat-name-display"><?= e($cat['name']) ?></strong></td>
                            <td style="text-align: center;"><code><?= e($cat['code'] ?? 'CAT-01') ?></code></td>
                            <td><?= e($cat['description'] ?? 'General hardware and industrial materials') ?></td>
                            <td style="text-align: center; font-weight: 600;"><?= e((string)($cat['product_count'] ?? 0)) ?> Products</td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= ($cat['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'success' : 'danger' ?>">
                                    <?= e($cat['status'] ?? 'ACTIVE') ?>
                                </span>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: flex; gap: 0.35rem; justify-content: center; align-items: center;">
                                    <?php if (\WTSBill\Auth\Gate::can('categories.view')): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" onclick="window.openManageCategoryModal(<?= (int)$cat['id'] ?>)">Manage</button>
                                    <?php endif; ?>
                                    <?php if (\WTSBill\Auth\Gate::can('categories.edit')): ?>
                                        <button type="button" class="btn btn-primary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" onclick="window.openEditCategoryModal(<?= (int)$cat['id'] ?>)">Edit</button>
                                    <?php endif; ?>
                                    <?php if (\WTSBill\Auth\Gate::can('categories.delete')): ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem; color: var(--danger); border-color: var(--danger);" onclick="window.deleteCategory(<?= (int)$cat['id'] ?>, '<?= e(addslashes($cat['name'])) ?>', <?= (int)($cat['product_count'] ?? 0) ?>)">Delete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-categories-row">
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No product categories found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Category Registration & Edit Modal -->
<div id="category-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
    <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-header">
            <h3 id="category-modal-title" class="modal-title">Add Product Category</h3>
            <button type="button" class="modal-close" onclick="window.closeCategoryModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="category-form" onsubmit="window.saveCategory(event)">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">
            <input type="hidden" id="cat-id" name="id" value="">

            <div class="modal-body">
                <div id="category-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group">
                        <label for="cat-name">Category Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="cat-name" name="name" class="form-control" placeholder="e.g. Electrical Components" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="cat-code">Category Code</label>
                        <input type="text" id="cat-code" name="code" class="form-control" placeholder="e.g. ELEC-01" data-uppercase>
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Leave blank to auto-generate taxonomy code.</small>
                    </div>

                    <div class="form-group">
                        <label for="cat-desc">Description</label>
                        <textarea id="cat-desc" name="description" class="form-control" rows="3" placeholder="Category purpose, sub-types, and classification notes"></textarea>
                    </div>

                    <div class="form-group" id="cat-status-group" style="display: none;">
                        <label for="cat-status">Status</label>
                        <select id="cat-status" name="status" class="form-control">
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeCategoryModal()">Cancel</button>
                <button type="submit" id="cat-submit-btn" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Category Modal -->
<div id="manage-category-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="manage-modal-title">
    <div class="modal-dialog" style="max-width: 760px;">
        <div class="modal-header">
            <div>
                <h3 id="manage-modal-title" class="modal-title">Manage Category</h3>
                <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 3px;">
                    Code: <code id="manage-code">-</code> &bull;
                    Status: <span id="manage-status-badge" class="badge badge-success">ACTIVE</span>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="window.closeManageCategoryModal()" aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 72vh; overflow-y: auto;">
            <div id="manage-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

            <!-- Overview Card -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem; margin-bottom: 1.25rem; background: var(--bg-hover, rgba(0,0,0,0.03)); padding: 1rem; border-radius: var(--radius); border: 1px solid var(--border-color, #e2e8f0);">
                <div>
                    <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Category Name</span>
                    <div id="manage-name-val" style="font-weight: 600; font-size: 1.05rem; margin-top: 2px;">-</div>
                </div>
                <div>
                    <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Taxonomy Code</span>
                    <div style="margin-top: 2px;"><code id="manage-code-val" style="font-size: 0.95rem;">-</code></div>
                </div>
                <div>
                    <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Linked Products</span>
                    <div id="manage-count-val" style="font-weight: 600; font-size: 1.05rem; margin-top: 2px;">-</div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Description</span>
                    <div id="manage-desc-val" style="font-size: 0.88rem; color: var(--text-color); margin-top: 2px;">-</div>
                </div>
            </div>

            <!-- Management Action Toolbar -->
            <div style="display: flex; gap: 0.5rem; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; flex-wrap: wrap;">
                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 600;">Products Linked to this Category</h4>
                <div style="display: flex; gap: 0.5rem;">
                    <?php if (\WTSBill\Auth\Gate::can('categories.edit')): ?>
                        <button type="button" id="manage-toggle-status-btn" class="btn btn-secondary btn-sm" onclick="window.toggleCurrentCategoryStatus()">Toggle Status</button>
                        <button type="button" id="manage-edit-btn" class="btn btn-primary btn-sm" onclick="window.openEditFromManage()">Edit Category</button>
                    <?php endif; ?>
                    <a href="/products" class="btn btn-secondary btn-sm">Product Catalog &rarr;</a>
                </div>
            </div>

            <!-- Products List Table -->
            <div class="table-container" style="border: 1px solid var(--border-color, #e2e8f0); border-radius: var(--radius); max-height: 240px; overflow-y: auto;">
                <table class="table" style="margin-bottom: 0; font-size: 0.85rem;">
                    <thead style="position: sticky; top: 0; background: var(--bg-surface, #fff); z-index: 1;">
                        <tr>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>HSN/SAC</th>
                            <th style="text-align: right;">Selling Price</th>
                            <th style="text-align: right;">Stock</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="manage-products-tbody">
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Loading linked products...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <?php if (\WTSBill\Auth\Gate::can('categories.delete')): ?>
                    <button type="button" id="manage-delete-btn" class="btn btn-outline-danger btn-sm" style="color: var(--danger); border-color: var(--danger);" onclick="window.deleteCurrentCategory()">Delete Category</button>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-secondary" onclick="window.closeManageCategoryModal()">Close</button>
        </div>
    </div>
</div>

<script>
    let currentCategoryData = null;

    function notify(message, type) {
        if (window.WTSBill && window.WTSBill.Toast) {
            window.WTSBill.Toast.show(message, type || 'info');
        }
    }

    function modalOpen(modalId) {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open(modalId);
        } else {
            const el = document.getElementById(modalId);
            if (el) {
                el.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        }
    }

    function modalClose(modalId) {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close(modalId);
        } else {
            const el = document.getElementById(modalId);
            if (el) {
                el.classList.remove('open');
                document.body.style.overflow = '';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('category-search', 'categories-table');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                ['category-modal', 'manage-category-modal'].forEach(function(id) {
                    const el = document.getElementById(id);
                    if (el && el.classList.contains('open')) {
                        modalClose(id);
                    }
                });
            }
        });
    });

    window.openCategoryModal = function() {
        const form = document.getElementById('category-form');
        form.reset();
        document.getElementById('cat-id').value = '';
        document.getElementById('category-modal-title').textContent = 'Add Product Category';
        document.getElementById('cat-status-group').style.display = 'none';
        document.getElementById('cat-status').value = 'ACTIVE';
        document.getElementById('category-modal-error').style.display = 'none';
        document.getElementById('category-modal-error').textContent = '';
        document.getElementById('cat-submit-btn').disabled = false;
        document.getElementById('cat-submit-btn').textContent = 'Save Category';

        modalOpen('category-modal');
        setTimeout(() => document.getElementById('cat-name').focus(), 100);
    };

    window.closeCategoryModal = function() {
        modalClose('category-modal');
    };

    window.openEditCategoryModal = async function(id) {
        window.openCategoryModal();
        document.getElementById('category-modal-title').textContent = 'Loading Category #' + id + '...';
        document.getElementById('cat-submit-btn').disabled = true;

        try {
            const resp = await fetch('/categories/' + id, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await resp.json();

            if (resp.ok && data.success && data.category) {
                const cat = data.category;
                document.getElementById('cat-id').value = cat.id;
                document.getElementById('cat-name').value = cat.name || '';
                document.getElementById('cat-code').value = cat.code || '';
                document.getElementById('cat-desc').value = cat.description || '';
                document.getElementById('cat-status').value = cat.status || 'ACTIVE';
                document.getElementById('cat-status-group').style.display = 'block';

                document.getElementById('category-modal-title').textContent = 'Edit Product Category: ' + cat.name;
                document.getElementById('cat-submit-btn').disabled = false;
                document.getElementById('cat-submit-btn').textContent = 'Update Category';
            } else {
                notify(data.message || 'Failed to load category details.', 'danger');
                window.closeCategoryModal();
            }
        } catch (err) {
            console.error('Failed to load category:', err);
            notify('Failed to load category details.', 'danger');
            window.closeCategoryModal();
        }
    };

    window.openEditFromManage = function() {
        if (!currentCategoryData) return;
        const catId = currentCategoryData.id;
        window.closeManageCategoryModal();
        window.openEditCategoryModal(catId);
    };

    window.openManageCategoryModal = async function(id) {
        currentCategoryData = null;
        document.getElementById('manage-modal-title').textContent = 'Manage Category #' + id;
        document.getElementById('manage-code').textContent = '...';
        document.getElementById('manage-name-val').textContent = 'Loading...';
        document.getElementById('manage-code-val').textContent = '...';
        document.getElementById('manage-count-val').textContent = '...';
        document.getElementById('manage-desc-val').textContent = '...';
        document.getElementById('manage-status-badge').textContent = '...';
        document.getElementById('manage-status-badge').className = 'badge';
        document.getElementById('manage-products-tbody').innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Loading linked products...</td></tr>';
        document.getElementById('manage-modal-error').style.display = 'none';

        modalOpen('manage-category-modal');

        try {
            const resp = await fetch('/categories/' + id, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await resp.json();

            if (resp.ok && data.success && data.category) {
                const cat = data.category;
                const products = data.products || [];
                currentCategoryData = cat;
                currentCategoryData.products = products;

                document.getElementById('manage-modal-title').textContent = 'Manage Category: ' + cat.name;
                document.getElementById('manage-code').textContent = cat.code || 'CAT-' + cat.id;
                document.getElementById('manage-name-val').textContent = cat.name;
                document.getElementById('manage-code-val').textContent = cat.code || 'CAT-' + cat.id;
                document.getElementById('manage-count-val').textContent = (cat.product_count || products.length || 0) + ' Products';
                document.getElementById('manage-desc-val').textContent = cat.description || 'No description provided.';

                const isAct = (cat.status || 'ACTIVE') === 'ACTIVE';
                const statusBadge = document.getElementById('manage-status-badge');
                statusBadge.textContent = cat.status || 'ACTIVE';
                statusBadge.className = 'badge ' + (isAct ? 'badge-success' : 'badge-danger');

                const tbody = document.getElementById('manage-products-tbody');
                if (products.length > 0) {
                    tbody.innerHTML = products.map(function(p) {
                        const price = parseFloat(p.selling_price || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const isProdActive = (p.status || 'ACTIVE') === 'ACTIVE';
                        return '<tr>' +
                            '<td><code>' + escapeHtml(p.sku || '-') + '</code></td>' +
                            '<td><strong>' + escapeHtml(p.name || '') + '</strong></td>' +
                            '<td><code>' + escapeHtml(p.hsn_sac || '-') + '</code></td>' +
                            '<td style="text-align: right; font-weight: 600;">₹' + price + '</td>' +
                            '<td style="text-align: right;">' + escapeHtml(String(p.stock_quantity ?? 0)) + ' ' + escapeHtml(p.unit || '') + '</td>' +
                            '<td style="text-align: center;"><span class="badge ' + (isProdActive ? 'badge-success' : 'badge-danger') + '">' + escapeHtml(p.status || 'ACTIVE') + '</span></td>' +
                        '</tr>';
                    }).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No products currently assigned to this category.</td></tr>';
                }
            } else {
                notify(data.message || 'Failed to load category information.', 'danger');
                window.closeManageCategoryModal();
            }
        } catch (err) {
            console.error('Error fetching manage category data:', err);
            notify('Failed to load category information.', 'danger');
            window.closeManageCategoryModal();
        }
    };

    window.closeManageCategoryModal = function() {
        modalClose('manage-category-modal');
    };

    window.toggleCurrentCategoryStatus = async function() {
        if (!currentCategoryData) return;
        const catId = currentCategoryData.id;
        const btn = document.getElementById('manage-toggle-status-btn');
        btn.disabled = true;

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('_token', metaToken);

            const resp = await fetch('/categories/' + catId + '/toggle-status', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken
                },
                body: formData
            });

            const data = await resp.json();
            btn.disabled = false;

            if (resp.ok && data.success && data.category) {
                currentCategoryData.status = data.category.status;
                const isAct = data.category.status === 'ACTIVE';
                const statusBadge = document.getElementById('manage-status-badge');
                statusBadge.textContent = data.category.status;
                statusBadge.className = 'badge ' + (isAct ? 'badge-success' : 'badge-danger');

                notify(data.message, 'success');

                // Update table row badge as well
                const row = document.getElementById('category-row-' + catId);
                if (row) {
                    const badge = row.querySelector('.badge');
                    if (badge) {
                        badge.textContent = data.category.status;
                        badge.className = 'badge ' + (isAct ? 'badge-success' : 'badge-danger');
                    }
                }
            } else {
                notify(data.message || 'Failed to toggle status.', 'danger');
            }
        } catch (err) {
            btn.disabled = false;
            console.error('Toggle status error:', err);
            notify('Network or server error while toggling status.', 'danger');
        }
    };

    window.saveCategory = async function(e) {
        e.preventDefault();
        const form = document.getElementById('category-form');
        const errBox = document.getElementById('category-modal-error');
        const submitBtn = document.getElementById('cat-submit-btn');
        const catId = document.getElementById('cat-id').value;

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';

        const formData = new FormData(form);
        const targetUrl = catId ? ('/categories/' + catId + '/update') : '/categories';

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch(targetUrl, {
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
                window.closeCategoryModal();
                notify(result.message || 'Category saved successfully!', 'success');
                setTimeout(() => window.location.reload(), 300);
            } else {
                errBox.style.display = 'block';
                if (result.errors) {
                    errBox.innerHTML = Object.values(result.errors).join('<br>');
                } else {
                    errBox.textContent = result.message || 'An error occurred while saving category.';
                }
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        } catch (err) {
            console.error('Save category error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    };

    window.deleteCategory = async function(id, name, productCount) {
        if (productCount > 0) {
            notify('Cannot delete "' + name + '" (' + productCount + ' linked products). Reassign or delete products first, or mark category as Inactive.', 'warning');
            return;
        }

        if (!confirm('Are you sure you want to permanently delete category "' + name + '"? This action cannot be undone.')) {
            return;
        }

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('_token', metaToken);

            const resp = await fetch('/categories/' + id + '/delete', {
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
                notify(result.message || 'Category deleted successfully!', 'success');
                const row = document.getElementById('category-row-' + id);
                if (row) row.remove();
                setTimeout(() => window.location.reload(), 300);
            } else {
                notify(result.message || 'Failed to delete category.', 'danger');
            }
        } catch (err) {
            console.error('Delete category error:', err);
            notify('Network or server error while deleting category.', 'danger');
        }
    };

    window.deleteCurrentCategory = function() {
        if (!currentCategoryData) return;
        const count = currentCategoryData.product_count || (currentCategoryData.products ? currentCategoryData.products.length : 0);
        window.deleteCategory(currentCategoryData.id, currentCategoryData.name, count);
    };

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
</script>
