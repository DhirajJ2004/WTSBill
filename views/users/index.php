<?php
/**
 * WTSBill ERP — Users & Role-Based Access Control View
 *
 * @var array $users
 * @var array $roles
 * @var array $branches
 */
use WTSBill\Auth\Gate;
use WTSBill\Core\Session;

$csrfToken = Session::getCsrfToken();
$currentUserId = (int)(Gate::user()['id'] ?? 1);
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700;">Users & Role-Based Access Control</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.875rem;">
            Manage operators, assign system roles (Administrator, Accountant, Inventory Manager), and authorize operating branch access
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="user-search" class="form-control" placeholder="Search user name or email..." style="width: 250px; padding: 0.4rem 0.75rem; font-size: 0.85rem;">
        <button type="button" class="btn btn-primary" onclick="openCreateUserModal()">+ Add User</button>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="margin: 0; font-size: 1.1rem; font-weight: 600;">System Users (<span id="users-count"><?= count($users ?? []) ?></span>)</h3>
        <span style="font-size: 0.8rem; color: var(--text-muted);">Strict Server-Side Gate Enforced</span>
    </div>
    <div class="table-container">
        <table class="table" id="users-table">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>User & Contact</th>
                    <th>Assigned Role</th>
                    <th>Assigned Branches</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right; width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody id="users-table-body">
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $u): ?>
                        <?php 
                            $isSelf = (int)$u['id'] === $currentUserId;
                            $roleBadge = match(strtolower((string)($u['role_slug'] ?? $u['role']))) {
                                'admin' => 'badge-primary',
                                'accountant' => 'badge-info',
                                'inventory_manager' => 'badge-warning',
                                'sales_executive' => 'badge-success',
                                default => 'badge-secondary'
                            };
                            $isActive = (int)($u['is_active'] ?? 1) === 1 && ($u['status'] ?? 'ACTIVE') === 'ACTIVE';
                        ?>
                        <tr id="user-row-<?= (int)$u['id'] ?>">
                            <td><?= e((string)$u['id']) ?></td>
                            <td>
                                <strong style="color: var(--text-primary);"><?= e($u['name']) ?></strong>
                                <?php if ($isSelf): ?>
                                    <span class="badge badge-info" style="font-size: 0.65rem; margin-left: 0.35rem;">You</span>
                                <?php endif; ?>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem;">
                                    <?= e($u['email']) ?>
                                    <?php if (!empty($u['phone'])): ?>
                                        &bull; <?= e($u['phone']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $roleBadge ?>">
                                    <?= e($u['role_name'] ?? $u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($u['branches'])): ?>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                        <?php foreach ($u['branches'] as $b): ?>
                                            <span class="badge badge-secondary" style="font-size: 0.72rem;"><?= e($b['name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">All Company Branches</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= $isActive ? 'success' : 'danger' ?>">
                                    <?= $isActive ? 'ACTIVE' : 'INACTIVE' ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-sm btn-secondary" onclick='openEditUserModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Details & Role">
                                    Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="openResetPasswordModal(<?= (int)$u['id'] ?>, '<?= e($u['name']) ?>')" title="Reset Password">
                                    Reset Pwd
                                </button>
                                <?php if (!$isSelf): ?>
                                    <button type="button" class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" onclick="toggleUserStatus(<?= (int)$u['id'] ?>, '<?= e($u['name']) ?>', <?= $isActive ? 'true' : 'false' ?>)" title="<?= $isActive ? 'Deactivate' : 'Activate' ?>">
                                        <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-users-row">
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">No user records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create / Invite User -->
<div id="create-user-modal" class="modal-backdrop" role="dialog" aria-modal="true">
    <div class="modal-dialog" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title">Create New System User</h3>
            <button type="button" class="modal-close" onclick="closeCreateUserModal()">&times;</button>
        </div>
        <form id="create-user-form" onsubmit="saveNewUser(event)">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div id="create-user-error" class="alert alert-danger" style="display: none; padding: 0.6rem 0.9rem; font-size: 0.85rem;"></div>

                <div class="form-group">
                    <label for="cu-name">Full Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="cu-name" name="name" class="form-control" placeholder="e.g. Suresh Patel" required autofocus>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="cu-email">Email Address <span style="color: var(--danger);">*</span></label>
                        <input type="email" id="cu-email" name="email" class="form-control" placeholder="e.g. suresh@wtsbill.com" required>
                    </div>
                    <div class="form-group">
                        <label for="cu-phone">Mobile / Phone</label>
                        <input type="tel" id="cu-phone" name="phone" class="form-control" placeholder="+91 9876543210">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="cu-role">Assigned Role <span style="color: var(--danger);">*</span></label>
                        <select id="cu-role" name="role_id" class="form-control" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int)$r['id'] ?>" <?= ($r['slug'] ?? '') === 'accountant' ? 'selected' : '' ?>>
                                    <?= e($r['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="cu-pass">Initial Password <span style="color: var(--danger);">*</span></label>
                        <input type="password" id="cu-pass" name="password" class="form-control" value="Password@123" required>
                    </div>
                </div>

                <div class="form-group">
                    <label style="margin-bottom: 0.35rem; display: block;">Authorized Operating Branches</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; max-height: 140px; overflow-y: auto; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: var(--radius); background: var(--bg-hover, rgba(255,255,255,0.02));">
                        <?php foreach ($branches as $b): ?>
                            <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; cursor: pointer;">
                                <input type="checkbox" name="branch_ids[]" value="<?= (int)$b['id'] ?>" checked>
                                <span><?= e($b['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeCreateUserModal()">Cancel</button>
                <button type="submit" id="create-user-submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit User -->
<div id="edit-user-modal" class="modal-backdrop" role="dialog" aria-modal="true">
    <div class="modal-dialog" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit User & Role Assignment</h3>
            <button type="button" class="modal-close" onclick="closeEditUserModal()">&times;</button>
        </div>
        <form id="edit-user-form" onsubmit="saveEditUser(event)">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" id="eu-id" name="id" value="">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div id="edit-user-error" class="alert alert-danger" style="display: none; padding: 0.6rem 0.9rem; font-size: 0.85rem;"></div>

                <div class="form-group">
                    <label for="eu-name">Full Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="eu-name" name="name" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="eu-email">Email Address</label>
                        <input type="email" id="eu-email" class="form-control" disabled style="opacity: 0.7;">
                    </div>
                    <div class="form-group">
                        <label for="eu-phone">Mobile / Phone</label>
                        <input type="tel" id="eu-phone" name="phone" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="eu-role">Assigned Role <span style="color: var(--danger);">*</span></label>
                    <select id="eu-role" name="role_id" class="form-control" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>">
                                <?= e($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="margin-bottom: 0.35rem; display: block;">Authorized Operating Branches</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; max-height: 140px; overflow-y: auto; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: var(--radius); background: var(--bg-hover, rgba(255,255,255,0.02));">
                        <?php foreach ($branches as $b): ?>
                            <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; cursor: pointer;">
                                <input type="checkbox" name="branch_ids[]" value="<?= (int)$b['id'] ?>" class="eu-branch-cb" id="eu-branch-<?= (int)$b['id'] ?>">
                                <span><?= e($b['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                <button type="submit" id="edit-user-submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset Password -->
<div id="reset-pwd-modal" class="modal-backdrop" role="dialog" aria-modal="true">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title">Reset User Password</h3>
            <button type="button" class="modal-close" onclick="closeResetPasswordModal()">&times;</button>
        </div>
        <form id="reset-pwd-form" onsubmit="saveResetPassword(event)">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" id="rp-id" name="id" value="">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">
                    Reset password for <strong id="rp-user-name" style="color: var(--text-primary);"></strong>:
                </p>
                <div class="form-group">
                    <label for="rp-pass">New Password (Min 6 characters) <span style="color: var(--danger);">*</span></label>
                    <input type="password" id="rp-pass" name="password" class="form-control" placeholder="Enter new strong password" required minlength="6">
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.WTSBill && window.WTSBill.Tables) {
        window.WTSBill.Tables.filter('user-search', 'users-table');
    }
});

function openCreateUserModal() {
    document.getElementById('create-user-form').reset();
    document.getElementById('cu-pass').value = 'Password@123';
    document.getElementById('create-user-error').style.display = 'none';
    if (window.WTSBill && window.WTSBill.Modal) {
        window.WTSBill.Modal.open('create-user-modal');
    } else {
        document.getElementById('create-user-modal').classList.add('active');
    }
}

function closeCreateUserModal() {
    if (window.WTSBill && window.WTSBill.Modal) {
        window.WTSBill.Modal.close('create-user-modal');
    } else {
        document.getElementById('create-user-modal').classList.remove('active');
    }
}

async function saveNewUser(e) {
    e.preventDefault();
    const form = document.getElementById('create-user-form');
    const btn = document.getElementById('create-user-submit');
    const errBox = document.getElementById('create-user-error');
    btn.disabled = true;
    errBox.style.display = 'none';

    try {
        const resp = await fetch('/users', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '<?= e($csrfToken) ?>'
            },
            body: new FormData(form)
        });
        const res = await resp.json();
        if (resp.ok && res.success) {
            closeCreateUserModal();
            if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show(res.message, 'success');
            setTimeout(() => window.location.reload(), 300);
        } else {
            errBox.textContent = res.message || 'Failed to create user.';
            errBox.style.display = 'block';
            btn.disabled = false;
        }
    } catch(err) {
        errBox.textContent = 'Server communication error.';
        errBox.style.display = 'block';
        btn.disabled = false;
    }
}

function openEditUserModal(u) {
    document.getElementById('edit-user-form').reset();
    document.getElementById('edit-user-error').style.display = 'none';
    document.getElementById('eu-id').value = u.id;
    document.getElementById('eu-name').value = u.name;
    document.getElementById('eu-email').value = u.email;
    document.getElementById('eu-phone').value = u.phone || '';
    document.getElementById('eu-role').value = u.role_id || 5;

    // Branches
    const userBranchIds = (u.branch_ids || []).map(Number);
    document.querySelectorAll('.eu-branch-cb').forEach(cb => {
        cb.checked = userBranchIds.includes(Number(cb.value));
    });

    if (window.WTSBill && window.WTSBill.Modal) {
        window.WTSBill.Modal.open('edit-user-modal');
    } else {
        document.getElementById('edit-user-modal').classList.add('active');
    }
}

function closeEditUserModal() {
    if (window.WTSBill && window.WTSBill.Modal) {
        window.WTSBill.Modal.close('edit-user-modal');
    } else {
        document.getElementById('edit-user-modal').classList.remove('active');
    }
}

async function saveEditUser(e) {
    e.preventDefault();
    const form = document.getElementById('edit-user-form');
    const btn = document.getElementById('edit-user-submit');
    const errBox = document.getElementById('edit-user-error');
    btn.disabled = true;
    errBox.style.display = 'none';

    try {
        const resp = await fetch('/users/update', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '<?= e($csrfToken) ?>'
            },
            body: new FormData(form)
        });
        const res = await resp.json();
        if (resp.ok && res.success) {
            closeEditUserModal();
            if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show(res.message, 'success');
            setTimeout(() => window.location.reload(), 300);
        } else {
            errBox.textContent = res.message || 'Failed to update user.';
            errBox.style.display = 'block';
            btn.disabled = false;
        }
    } catch(err) {
        errBox.textContent = 'Server communication error.';
        errBox.style.display = 'block';
        btn.disabled = false;
    }
}

function openResetPasswordModal(id, name) {
    document.getElementById('reset-pwd-form').reset();
    document.getElementById('rp-id').value = id;
    document.getElementById('rp-user-name').textContent = name;
    if (window.WTSBill && window.WTSBill.Modal) {
        window.WTSBill.Modal.open('reset-pwd-modal');
    } else {
        document.getElementById('reset-pwd-modal').classList.add('active');
    }
}

function closeResetPasswordModal() {
    if (window.WTSBill && window.WTSBill.Modal) {
        window.WTSBill.Modal.close('reset-pwd-modal');
    } else {
        document.getElementById('reset-pwd-modal').classList.remove('active');
    }
}

async function saveResetPassword(e) {
    e.preventDefault();
    const form = document.getElementById('reset-pwd-form');

    try {
        const resp = await fetch('/users/reset-password', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '<?= e($csrfToken) ?>'
            },
            body: new FormData(form)
        });
        const res = await resp.json();
        if (resp.ok && res.success) {
            closeResetPasswordModal();
            if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show(res.message, 'success');
        } else {
            if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show(res.message || 'Failed to reset password.', 'error');
        }
    } catch(err) {
        if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show('Server communication error.', 'error');
    }
}

async function toggleUserStatus(id, name, isActive) {
    const action = isActive ? 'deactivate' : 'activate';
    if (!confirm(`Are you sure you want to ${action} user "${name}"?`)) return;

    try {
        const formData = new FormData();
        formData.append('id', id);

        const resp = await fetch('/users/toggle-status', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '<?= e($csrfToken) ?>'
            },
            body: formData
        });
        const res = await resp.json();
        if (resp.ok && res.success) {
            if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show(res.message, 'success');
            setTimeout(() => window.location.reload(), 300);
        } else {
            if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show(res.message || `Failed to ${action} user.`, 'error');
        }
    } catch(err) {
        if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show('Server communication error.', 'error');
    }
}
</script>
