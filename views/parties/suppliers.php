<?php
/**
 * WTSBill ERP — Suppliers Master List View & Registration Workflow
 *
 * @var array $suppliers
 * @var string $search
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Supplier Directory</h1>
        <p class="page-subtitle">Vendor master records, GSTIN, accounts payable, and procurement contacts</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="supplier-search" class="form-control" placeholder="Search suppliers..." style="width: 240px; padding: 0.4rem 0.75rem; font-size: 0.85rem;" value="<?= e($search ?? '') ?>">
        <button type="button" class="btn btn-primary" onclick="window.openSupplierModal()">+ New Supplier</button>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title">Registered Suppliers (<span id="suppliers-count"><?= count($suppliers ?? []) ?></span> Active)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="suppliers-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 23%;">Supplier Name</th>
                    <th style="width: 14%;">GSTIN</th>
                    <th style="width: 16%;">City / State</th>
                    <th style="width: 13%;">Phone</th>
                    <th style="width: 13%; text-align: right;">Payable Balance</th>
                    <th style="width: 6%; text-align: center;">Status</th>
                    <th style="width: 10%; text-align: center; white-space: nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody id="suppliers-table-body">
                <?php if (!empty($suppliers)): ?>
                    <?php foreach ($suppliers as $s): ?>
                        <tr id="supplier-row-<?= (int)$s['id'] ?>">
                            <td><?= e((string)$s['id']) ?></td>
                            <td>
                                <strong><?= e($s['name']) ?></strong>
                                <?php if (!empty($s['company_name'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($s['company_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($s['gstin'] ?: 'UNREGISTERED') ?></code></td>
                            <td><?= e(trim(($s['city'] ?? '') . ', ' . ($s['state'] ?? ''), ', ')) ?></td>
                            <td><?= e($s['phone'] ?? '-') ?></td>
                            <td style="text-align: right; font-weight: 600; color: <?= (float)($s['current_balance'] ?? 0) > 0 ? 'var(--color-danger)' : 'var(--text-primary)' ?>;">
                                <?= format_currency((float)($s['current_balance'] ?? 0)) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= ($s['is_active'] ?? 1) ? 'success' : 'danger' ?>" id="badge-sup-<?= $s['id'] ?>">
                                    <?= ($s['is_active'] ?? 1) ? 'ACTIVE' : 'INACTIVE' ?>
                                </span>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: flex; gap: 0.35rem; justify-content: center; align-items: center;">
                                    <?php if (\WTSBill\Auth\Gate::can('suppliers.edit')): ?>
                                        <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" onclick="window.editSupplier(<?= (int)$s['id'] ?>)">Edit</button>
                                    <?php endif; ?>
                                    <?php if (\WTSBill\Auth\Gate::can('suppliers.delete')): ?>
                                        <button type="button" class="btn btn-outline-danger" style="padding: 0.25rem 0.55rem; font-size: 0.78rem; color: var(--danger); border-color: var(--danger);" onclick="window.deleteSupplier(<?= (int)$s['id'] ?>, '<?= e(addslashes($s['name'])) ?>')">Delete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-suppliers-row">
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">No supplier records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Supplier Registration & Edit Modal -->
<div id="supplier-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="supplier-modal-title">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <h3 id="supplier-modal-title" class="modal-title">Register New Supplier</h3>
            <button type="button" class="modal-close" onclick="window.closeSupplierModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="supplier-form" onsubmit="window.saveSupplier(event)">
            <input type="hidden" id="sup-id" name="id" value="">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div id="supplier-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="sup-name">Supplier / Vendor Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="sup-name" name="name" class="form-control" placeholder="e.g. National Steel Works" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="sup-company">Company / Legal Name</label>
                        <input type="text" id="sup-company" name="company_name" class="form-control" placeholder="e.g. National Steel Works Pvt Ltd">
                    </div>

                    <div class="form-group">
                        <label for="sup-gstin">GSTIN (15 Digits)</label>
                        <input type="text" id="sup-gstin" name="gstin" class="form-control" placeholder="e.g. 27AABCN1234F1Z8" maxlength="15" data-uppercase>
                    </div>

                    <div class="form-group">
                        <label for="sup-pan">PAN Number</label>
                        <input type="text" id="sup-pan" name="pan" class="form-control" placeholder="e.g. AABCN1234F" maxlength="10" data-uppercase>
                    </div>

                    <div class="form-group">
                        <label for="sup-phone">Phone / Mobile</label>
                        <input type="tel" id="sup-phone" name="phone" class="form-control" placeholder="e.g. +91 9876543210">
                    </div>

                    <div class="form-group">
                        <label for="sup-email">Email Address</label>
                        <input type="email" id="sup-email" name="email" class="form-control" placeholder="e.g. accounts@nationalsteel.com">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="sup-address">Office / Warehouse Address</label>
                        <textarea id="sup-address" name="address_line1" class="form-control" rows="2" placeholder="Street, Industrial Area"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="sup-city">City</label>
                        <input type="text" id="sup-city" name="city" class="form-control" placeholder="e.g. Ahmedabad">
                    </div>

                    <div class="form-group">
                        <label for="sup-state">State</label>
                        <input type="text" id="sup-state" name="state" class="form-control" placeholder="e.g. Gujarat">
                    </div>

                    <div class="form-group">
                        <label for="sup-pincode">PIN Code</label>
                        <input type="text" id="sup-pincode" name="pincode" class="form-control" placeholder="e.g. 380001" maxlength="10">
                    </div>

                    <div class="form-group" id="sup-opening-group">
                        <label for="sup-opening-balance">Opening Balance (₹)</label>
                        <input type="number" id="sup-opening-balance" name="opening_balance" class="form-control" placeholder="0.00" step="0.01">
                    </div>

                    <div class="form-group">
                        <label for="sup-payment-terms">Payment Terms (Days)</label>
                        <input type="number" id="sup-payment-terms" name="payment_terms_days" class="form-control" placeholder="30" value="30">
                    </div>

                    <div class="form-group">
                        <label for="sup-status">Status</label>
                        <select id="sup-status" name="is_active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <?php if (\WTSBill\Auth\Gate::can('suppliers.delete')): ?>
                        <button type="button" id="sup-modal-delete-btn" class="btn btn-outline-danger" style="display: none; padding: 0.4rem 0.75rem; font-size: 0.85rem; color: var(--danger); border-color: var(--danger);" onclick="window.deleteCurrentModalSupplier()">Delete Supplier</button>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="window.closeSupplierModal()">Cancel</button>
                    <button type="submit" id="sup-submit-btn" class="btn btn-primary">Save Supplier</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('supplier-search', 'suppliers-table');
        }

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('supplier-modal');
                if (modal && modal.classList.contains('active')) {
                    window.closeSupplierModal();
                }
            }
        });
    });

    window.openSupplierModal = function() {
        const form = document.getElementById('supplier-form');
        form.reset();
        document.getElementById('sup-id').value = '';
        document.getElementById('supplier-modal-title').textContent = 'Register New Supplier';
        document.getElementById('sup-opening-group').style.display = 'block';
        document.getElementById('supplier-modal-error').style.display = 'none';
        document.getElementById('supplier-modal-error').textContent = '';
        document.getElementById('sup-submit-btn').disabled = false;
        document.getElementById('sup-submit-btn').textContent = 'Save Supplier';
        const modalDeleteBtn = document.getElementById('sup-modal-delete-btn');
        if (modalDeleteBtn) modalDeleteBtn.style.display = 'none';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('supplier-modal');
        } else {
            document.getElementById('supplier-modal').classList.add('active');
        }
        setTimeout(() => document.getElementById('sup-name').focus(), 100);
    };

    window.closeSupplierModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('supplier-modal');
        } else {
            document.getElementById('supplier-modal').classList.remove('active');
        }
    };

    window.editSupplier = async function(id) {
        window.openSupplierModal();
        document.getElementById('supplier-modal-title').textContent = 'Edit Supplier Details';
        document.getElementById('sup-opening-group').style.display = 'none';
        document.getElementById('sup-submit-btn').textContent = 'Update Supplier';
        const modalDeleteBtn = document.getElementById('sup-modal-delete-btn');
        if (modalDeleteBtn) modalDeleteBtn.style.display = 'inline-block';

        try {
            const resp = await fetch('/suppliers/' + id, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            if (data.success && data.supplier) {
                const s = data.supplier;
                document.getElementById('sup-id').value = s.id;
                document.getElementById('sup-name').value = s.name || '';
                document.getElementById('sup-company').value = s.company_name || '';
                document.getElementById('sup-gstin').value = s.gstin || '';
                document.getElementById('sup-pan').value = s.pan || '';
                document.getElementById('sup-phone').value = s.phone || '';
                document.getElementById('sup-email').value = s.email || '';
                document.getElementById('sup-address').value = s.address_line1 || '';
                document.getElementById('sup-city').value = s.city || '';
                document.getElementById('sup-state').value = s.state || '';
                document.getElementById('sup-pincode').value = s.pincode || '';
                document.getElementById('sup-payment-terms').value = s.payment_terms_days || '30';
                document.getElementById('sup-status').value = s.is_active ? '1' : '0';
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Failed to load supplier details.', 'danger');
                }
            }
        } catch (err) {
            console.error('Supplier fetch error:', err);
        }
    };

    window.saveSupplier = async function(e) {
        e.preventDefault();
        const form = document.getElementById('supplier-form');
        const errBox = document.getElementById('supplier-modal-error');
        const submitBtn = document.getElementById('sup-submit-btn');
        const id = document.getElementById('sup-id').value;

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const formData = new FormData(form);
        const url = id ? ('/suppliers/' + id + '/update') : '/suppliers';

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch(url, {
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
                window.closeSupplierModal();
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Supplier saved successfully!', 'success');
                }
                setTimeout(() => window.location.reload(), 300);
            } else {
                errBox.style.display = 'block';
                if (result.errors) {
                    errBox.innerHTML = Object.values(result.errors).join('<br>');
                } else {
                    errBox.textContent = result.message || 'An error occurred while saving.';
                }
                submitBtn.disabled = false;
                submitBtn.textContent = id ? 'Update Supplier' : 'Save Supplier';
            }
        } catch (err) {
            console.error('Save supplier error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = id ? 'Update Supplier' : 'Save Supplier';
        }
    };

    window.deleteCurrentModalSupplier = function() {
        const id = document.getElementById('sup-id').value;
        const name = document.getElementById('sup-name').value;
        if (id) {
            window.closeSupplierModal();
            window.deleteSupplier(id, name);
        }
    };

    window.deleteSupplier = async function(id, name) {
        if (!confirm('Are you sure you want to delete supplier "' + (name || '#' + id) + '"? This will remove the supplier record.')) {
            return;
        }

        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        try {
            const resp = await fetch('/suppliers/' + id + '/delete', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken
                },
                body: new URLSearchParams({
                    '_csrf_token': metaToken,
                    'id': id
                })
            });

            const data = await resp.json();

            if (resp.ok && data.success) {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Supplier deleted successfully.', 'success');
                }
                const row = document.getElementById('supplier-row-' + id);
                if (row) {
                    row.remove();
                }

                // Update active counter
                const countSpan = document.getElementById('suppliers-count');
                if (countSpan) {
                    const currentCount = parseInt(countSpan.textContent) || 0;
                    countSpan.textContent = Math.max(0, currentCount - 1);
                }

                // If no rows remain, show empty row
                const tbody = document.getElementById('suppliers-table-body');
                if (tbody && tbody.querySelectorAll('tr').length === 0) {
                    tbody.innerHTML = '<tr id="no-suppliers-row"><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">No supplier records found.</td></tr>';
                }
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Failed to delete supplier.', 'danger');
                }
            }
        } catch (err) {
            console.error('Delete supplier error:', err);
            // Fallback to standard form submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/suppliers/' + id + '/delete';
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_csrf_token';
            csrfInput.value = metaToken;
            form.appendChild(csrfInput);
            document.body.appendChild(form);
            form.submit();
        }
    };
</script>
