<?php
/**
 * WTSBill ERP — Users & Roles Management View & User Onboarding Workflow
 *
 * @var array $users
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Users & Role-Based Access Control</h1>
        <p class="page-subtitle">Manage company operators, role assignments (ADMIN, ACCOUNTANT, AUDITOR, INVENTORY, SALES), and security access</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="user-search" class="form-control" placeholder="Search user name or email..." style="width: 250px; padding: 0.4rem 0.75rem; font-size: 0.85rem;">
        <button type="button" class="btn btn-primary" onclick="window.openUserModal()">+ Add User</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Active System Users (<span id="users-count"><?= count($users ?? []) ?></span> Users)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="users-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User Name</th>
                    <th>Email Address</th>
                    <th>Assigned Role</th>
                    <th>Tenant Company</th>
                    <th style="text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody id="users-table-body">
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= e((string)$u['id']) ?></td>
                            <td><strong><?= e($u['name']) ?></strong></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge badge-info"><?= e($u['role']) ?></span></td>
                            <td>Company #<?= e((string)$u['company_id']) ?></td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= ($u['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'success' : 'danger' ?>">
                                    <?= e($u['status'] ?? 'ACTIVE') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-users-row">
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No user records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working User Onboarding & Invitation Modal -->
<div id="user-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
    <div class="modal-dialog" style="max-width: 560px;">
        <div class="modal-header">
            <h3 id="user-modal-title" class="modal-title">Create / Invite System User</h3>
            <button type="button" class="modal-close" onclick="window.closeUserModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="user-form" onsubmit="window.saveUser(event)">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div class="modal-body">
                <div id="user-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group">
                        <label for="u-name">Full Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="u-name" name="name" class="form-control" placeholder="e.g. Anand Sharma" required autofocus>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="u-email">Email Address <span style="color: var(--danger);">*</span></label>
                            <input type="email" id="u-email" name="email" class="form-control" placeholder="e.g. anand@wtsbill.com" required>
                        </div>

                        <div class="form-group">
                            <label for="u-phone">Phone / Mobile</label>
                            <input type="tel" id="u-phone" name="phone" class="form-control" placeholder="+91 9876543210">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="u-role">Role Assignment <span style="color: var(--danger);">*</span></label>
                            <select id="u-role" name="role" class="form-control" required>
                                <option value="ACCOUNTANT">Accountant</option>
                                <option value="OPERATOR">Data Operator</option>
                                <option value="SALES">Sales Executive</option>
                                <option value="MANAGER">Branch Manager</option>
                                <option value="AUDITOR">Auditor</option>
                                <option value="ADMIN">System Administrator</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="u-pass">Initial Password <span style="color: var(--danger);">*</span></label>
                            <input type="password" id="u-pass" name="password" class="form-control" value="Password@123" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeUserModal()">Cancel</button>
                <button type="submit" id="user-submit-btn" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('user-search', 'users-table');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('user-modal');
                if (modal && modal.classList.contains('active')) {
                    window.closeUserModal();
                }
            }
        });
    });

    window.openUserModal = function() {
        const form = document.getElementById('user-form');
        form.reset();
        document.getElementById('u-pass').value = 'Password@123';
        document.getElementById('user-modal-error').style.display = 'none';
        document.getElementById('user-modal-error').textContent = '';
        document.getElementById('user-submit-btn').disabled = false;
        document.getElementById('user-submit-btn').textContent = 'Create User';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('user-modal');
        } else {
            document.getElementById('user-modal').classList.add('active');
        }
    };

    window.closeUserModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('user-modal');
        } else {
            document.getElementById('user-modal').classList.remove('active');
        }
    };

    window.saveUser = async function(e) {
        e.preventDefault();
        const form = document.getElementById('user-form');
        const errBox = document.getElementById('user-modal-error');
        const submitBtn = document.getElementById('user-submit-btn');

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const formData = new FormData(form);

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/users', {
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
                window.closeUserModal();
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'User created successfully!', 'success');
                }
                setTimeout(() => window.location.reload(), 300);
            } else {
                errBox.style.display = 'block';
                errBox.textContent = result.message || 'Failed to create user.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create User';
            }
        } catch (err) {
            console.error('Save user error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create User';
        }
    };
</script>
