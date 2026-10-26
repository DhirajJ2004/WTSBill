<?php
/**
 * WTSBill ERP — Product Categories View & Category Registration Workflow
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
        <button type="button" class="btn btn-primary" onclick="window.openCategoryModal()">+ New Category</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Category Taxonomy (<span id="categories-count"><?= count($categories ?? []) ?></span> Groups)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="categories-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Category Name</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th style="text-align: right;">Linked Products</th>
                    <th style="text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody id="categories-table-body">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= e((string)$cat['id']) ?></td>
                            <td><strong><?= e($cat['name']) ?></strong></td>
                            <td><code><?= e($cat['code'] ?? 'CAT-01') ?></code></td>
                            <td><?= e($cat['description'] ?? 'General hardware and industrial materials') ?></td>
                            <td style="text-align: right; font-weight: 600;"><?= e((string)($cat['product_count'] ?? 0)) ?> Products</td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= ($cat['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'success' : 'danger' ?>">
                                    <?= e($cat['status'] ?? 'ACTIVE') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-categories-row">
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No product categories found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Category Registration Modal -->
<div id="category-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
    <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-header">
            <h3 id="category-modal-title" class="modal-title">Add Product Category</h3>
            <button type="button" class="modal-close" onclick="window.closeCategoryModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="category-form" onsubmit="window.saveCategory(event)">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

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
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeCategoryModal()">Cancel</button>
                <button type="submit" id="cat-submit-btn" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('category-search', 'categories-table');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('category-modal');
                if (modal && modal.classList.contains('active')) {
                    window.closeCategoryModal();
                }
            }
        });
    });

    window.openCategoryModal = function() {
        const form = document.getElementById('category-form');
        form.reset();
        document.getElementById('category-modal-error').style.display = 'none';
        document.getElementById('category-modal-error').textContent = '';
        document.getElementById('cat-submit-btn').disabled = false;
        document.getElementById('cat-submit-btn').textContent = 'Save Category';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('category-modal');
        } else {
            document.getElementById('category-modal').classList.add('active');
        }
        setTimeout(() => document.getElementById('cat-name').focus(), 100);
    };

    window.closeCategoryModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('category-modal');
        } else {
            document.getElementById('category-modal').classList.remove('active');
        }
    };

    window.saveCategory = async function(e) {
        e.preventDefault();
        const form = document.getElementById('category-form');
        const errBox = document.getElementById('category-modal-error');
        const submitBtn = document.getElementById('cat-submit-btn');

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const formData = new FormData(form);

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/categories', {
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
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Category created successfully!', 'success');
                }
                setTimeout(() => window.location.reload(), 300);
            } else {
                errBox.style.display = 'block';
                if (result.errors) {
                    errBox.innerHTML = Object.values(result.errors).join('<br>');
                } else {
                    errBox.textContent = result.message || 'An error occurred while saving category.';
                }
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Category';
            }
        } catch (err) {
            console.error('Save category error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Category';
        }
    };
</script>
