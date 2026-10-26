<?php
/**
 * WTSBill ERP — Customers Master List View & Registration Workflow
 *
 * @var array $customers
 * @var string $search
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Customer Directory</h1>
        <p class="page-subtitle">Manage customer master records, GSTIN, credit limits, and outstanding balances</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="customer-search" class="form-control" placeholder="Search customers..." style="width: 240px; padding: 0.4rem 0.75rem; font-size: 0.85rem;" value="<?= e($search ?? '') ?>">
        <button type="button" class="btn btn-primary" onclick="window.openCustomerModal()">+ New Customer</button>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title">Registered Customers (<span id="customers-count"><?= count($customers ?? []) ?></span> Active)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="customers-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer Name</th>
                    <th>GSTIN</th>
                    <th>City / State</th>
                    <th>Phone</th>
                    <th style="text-align: right;">Current Balance</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody id="customers-table-body">
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $c): ?>
                        <tr id="customer-row-<?= (int)$c['id'] ?>">
                            <td><?= e((string)$c['id']) ?></td>
                            <td>
                                <strong><?= e($c['name']) ?></strong>
                                <?php if (!empty($c['company_name'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($c['company_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($c['gstin'] ?: 'UNREGISTERED') ?></code></td>
                            <td><?= e(trim(($c['city'] ?? '') . ', ' . ($c['state'] ?? ''), ', ')) ?></td>
                            <td><?= e($c['phone'] ?? '-') ?></td>
                            <td style="text-align: right; font-weight: 600; color: <?= (float)($c['current_balance'] ?? 0) > 0 ? 'var(--color-warning)' : 'var(--text-primary)' ?>;">
                                <?= format_currency((float)($c['current_balance'] ?? 0)) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= ($c['is_active'] ?? 1) ? 'success' : 'danger' ?>" id="badge-cust-<?= $c['id'] ?>">
                                    <?= ($c['is_active'] ?? 1) ? 'ACTIVE' : 'INACTIVE' ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 0.25rem; justify-content: center;">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="window.editCustomer(<?= (int)$c['id'] ?>)">Edit</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-customers-row">
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">No customer records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Customer Registration & Edit Modal -->
<div id="customer-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="customer-modal-title">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <h3 id="customer-modal-title" class="modal-title">Register New Customer</h3>
            <button type="button" class="modal-close" onclick="window.closeCustomerModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="customer-form" onsubmit="window.saveCustomer(event)">
            <input type="hidden" id="cust-id" name="id" value="">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div id="customer-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="cust-name">Customer / Contact Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="cust-name" name="name" class="form-control" placeholder="e.g. Rajesh Kumar" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="cust-company">Company / Trade Name</label>
                        <input type="text" id="cust-company" name="company_name" class="form-control" placeholder="e.g. Apex Engineering Ltd">
                    </div>

                    <div class="form-group">
                        <label for="cust-gstin">GSTIN (15 Digits)</label>
                        <input type="text" id="cust-gstin" name="gstin" class="form-control" placeholder="e.g. 27AABCU9603R1ZM" maxlength="15" data-uppercase>
                    </div>

                    <div class="form-group">
                        <label for="cust-pan">PAN Number</label>
                        <input type="text" id="cust-pan" name="pan" class="form-control" placeholder="e.g. AABCU9603R" maxlength="10" data-uppercase>
                    </div>

                    <div class="form-group">
                        <label for="cust-phone">Phone / Mobile</label>
                        <input type="tel" id="cust-phone" name="phone" class="form-control" placeholder="e.g. +91 9876543210">
                    </div>

                    <div class="form-group">
                        <label for="cust-email">Email Address</label>
                        <input type="email" id="cust-email" name="email" class="form-control" placeholder="e.g. billing@apex.com">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="cust-address">Billing Address</label>
                        <textarea id="cust-address" name="address_line1" class="form-control" rows="2" placeholder="Street, Building, Area"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="cust-city">City</label>
                        <input type="text" id="cust-city" name="city" class="form-control" placeholder="e.g. Mumbai">
                    </div>

                    <div class="form-group">
                        <label for="cust-state">State</label>
                        <input type="text" id="cust-state" name="state" class="form-control" placeholder="e.g. Maharashtra">
                    </div>

                    <div class="form-group">
                        <label for="cust-pincode">PIN Code</label>
                        <input type="text" id="cust-pincode" name="pincode" class="form-control" placeholder="e.g. 400001" maxlength="10">
                    </div>

                    <div class="form-group" id="cust-opening-group">
                        <label for="cust-opening-balance">Opening Balance (₹)</label>
                        <input type="number" id="cust-opening-balance" name="opening_balance" class="form-control" placeholder="0.00" step="0.01">
                    </div>

                    <div class="form-group">
                        <label for="cust-credit-limit">Credit Limit (₹)</label>
                        <input type="number" id="cust-credit-limit" name="credit_limit" class="form-control" placeholder="0.00" step="0.01">
                    </div>

                    <div class="form-group">
                        <label for="cust-status">Status</label>
                        <select id="cust-status" name="is_active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeCustomerModal()">Cancel</button>
                <button type="submit" id="cust-submit-btn" class="btn btn-primary">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('customer-search', 'customers-table');
        }

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('customer-modal');
                if (modal && modal.classList.contains('active')) {
                    window.closeCustomerModal();
                }
            }
        });
    });

    window.openCustomerModal = function() {
        const form = document.getElementById('customer-form');
        form.reset();
        document.getElementById('cust-id').value = '';
        document.getElementById('customer-modal-title').textContent = 'Register New Customer';
        document.getElementById('cust-opening-group').style.display = 'block';
        document.getElementById('customer-modal-error').style.display = 'none';
        document.getElementById('customer-modal-error').textContent = '';
        document.getElementById('cust-submit-btn').disabled = false;
        document.getElementById('cust-submit-btn').textContent = 'Save Customer';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('customer-modal');
        } else {
            document.getElementById('customer-modal').classList.add('active');
        }
        setTimeout(() => document.getElementById('cust-name').focus(), 100);
    };

    window.closeCustomerModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('customer-modal');
        } else {
            document.getElementById('customer-modal').classList.remove('active');
        }
    };

    window.editCustomer = async function(id) {
        window.openCustomerModal();
        document.getElementById('customer-modal-title').textContent = 'Edit Customer Details';
        document.getElementById('cust-opening-group').style.display = 'none';
        document.getElementById('cust-submit-btn').textContent = 'Update Customer';

        try {
            const resp = await fetch('/customers/' + id, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            if (data.success && data.customer) {
                const c = data.customer;
                document.getElementById('cust-id').value = c.id;
                document.getElementById('cust-name').value = c.name || '';
                document.getElementById('cust-company').value = c.company_name || '';
                document.getElementById('cust-gstin').value = c.gstin || '';
                document.getElementById('cust-pan').value = c.pan || '';
                document.getElementById('cust-phone').value = c.phone || '';
                document.getElementById('cust-email').value = c.email || '';
                document.getElementById('cust-address').value = c.address_line1 || '';
                document.getElementById('cust-city').value = c.city || '';
                document.getElementById('cust-state').value = c.state || '';
                document.getElementById('cust-pincode').value = c.pincode || '';
                document.getElementById('cust-credit-limit').value = c.credit_limit || '';
                document.getElementById('cust-status').value = c.is_active ? '1' : '0';
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Failed to load customer details.', 'danger');
                }
            }
        } catch (err) {
            console.error('Customer fetch error:', err);
        }
    };

    window.saveCustomer = async function(e) {
        e.preventDefault();
        const form = document.getElementById('customer-form');
        const errBox = document.getElementById('customer-modal-error');
        const submitBtn = document.getElementById('cust-submit-btn');
        const id = document.getElementById('cust-id').value;

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const formData = new FormData(form);
        const url = id ? ('/customers/' + id + '/update') : '/customers';

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
                window.closeCustomerModal();
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Customer saved successfully!', 'success');
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
                submitBtn.textContent = id ? 'Update Customer' : 'Save Customer';
            }
        } catch (err) {
            console.error('Save customer error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = id ? 'Update Customer' : 'Save Customer';
        }
    };
</script>
