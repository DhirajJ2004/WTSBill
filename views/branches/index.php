<?php
/**
 * WTSBill ERP — Branch & Location Management View
 * Administrative view for creating, configuring, and assigning branches.
 */
use WTSBill\Core\Session;

$csrfToken = Session::csrfToken();
$currentBranchId = (int)($activeBranchId ?? Session::getBranchId() ?? 1);
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">Branch & Location Management</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.875rem;">Manage operating branches, GSTIN registrations, user assignments, and multi-location scoping</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <button type="button" class="btn btn-primary" onclick="openCreateBranchModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.4rem;">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Register Branch
        </button>
    </div>
</div>

<!-- Branches Grid / Table -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">Registered Operating Branches</h3>
        <span class="badge badge-primary"><?= count($branches) ?> Branches</span>
    </div>
    <div class="table-responsive">
        <table class="table" style="width: 100%;">
            <thead>
                <tr>
                    <th>Branch Name</th>
                    <th>Code</th>
                    <th>Location / State</th>
                    <th>GSTIN</th>
                    <th>Users Assigned</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="branches-table-body">
                <?php if (empty($branches)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            No branches found. Click "Register Branch" to create your first operating location.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($branches as $branch): ?>
                        <?php 
                        $bId = (int)$branch['id'];
                        $isCurrent = ($bId === $currentBranchId);
                        $isActive = ((int)$branch['is_active'] === 1);
                        $isMain = ((int)$branch['is_main_branch'] === 1);
                        ?>
                        <tr id="branch-row-<?= $bId ?>">
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <strong><?= e($branch['name']) ?></strong>
                                    <?php if ($isMain): ?>
                                        <span class="badge badge-primary" style="font-size: 0.65rem;">HQ / MAIN</span>
                                    <?php endif; ?>
                                    <?php if ($isCurrent): ?>
                                        <span class="badge badge-success" style="font-size: 0.65rem;">ACTIVE CONTEXT</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($branch['address'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;"><?= e($branch['address']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code><?= e($branch['branch_code'] ?? $branch['code'] ?? '—') ?></code>
                            </td>
                            <td>
                                <div><?= e($branch['city'] ?? '—') ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($branch['state'] ?? 'Maharashtra') ?> (<?= e($branch['state_code'] ?? '27') ?>)</div>
                            </td>
                            <td>
                                <?php if (!empty($branch['gstin'])): ?>
                                    <code><?= e($branch['gstin']) ?></code>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-secondary"><?= (int)($branch['assigned_users_count'] ?? 0) ?> Users</span>
                            </td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge badge-success">ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">INACTIVE</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                                    <?php if ($isActive && !$isCurrent): ?>
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="WTSBill.Branch.switch(<?= $bId ?>, '<?= e(addslashes($branch['name'])) ?>')" title="Switch to this branch">
                                            Switch
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline" onclick="openEditBranchModal(<?= $bId ?>)" title="Edit branch details">
                                        Edit
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline" onclick="openAssignUsersModal(<?= $bId ?>, '<?= e(addslashes($branch['name'])) ?>')" title="Assign users">
                                        Users
                                    </button>
                                    <?php if (!$isMain): ?>
                                        <button type="button" class="btn btn-sm <?= $isActive ? 'btn-danger' : 'btn-success' ?>" onclick="toggleBranchStatus(<?= $bId ?>, '<?= e(addslashes($branch['name'])) ?>', <?= $isActive ? '0' : '1' ?>)" title="<?= $isActive ? 'Deactivate branch' : 'Activate branch' ?>">
                                            <?= $isActive ? 'Disable' : 'Enable' ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 1. CREATE / EDIT BRANCH MODAL -->
<div id="branch-modal" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 640px;">
        <div class="modal-header">
            <h3 id="branch-modal-title" class="modal-title">Register Operating Branch</h3>
            <button type="button" class="modal-close" onclick="WTSBill.Modal.close('branch-modal')">&times;</button>
        </div>
        <form id="branch-form" onsubmit="handleBranchFormSubmit(event)">
            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" id="branch-id" name="id" value="">

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div id="branch-form-error" class="alert alert-danger" style="display: none;"></div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Branch Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" id="branch-name" name="name" class="form-control" placeholder="e.g. Pune Regional Branch" required>
                    </div>
                    <div>
                        <label class="form-label">Branch Code <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" id="branch-code" name="branch_code" class="form-control" placeholder="e.g. PUN-02, NAS-03" required style="text-transform: uppercase;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">GSTIN (15 Digits)</label>
                        <input type="text" id="branch-gstin" name="gstin" class="form-control" placeholder="27AAAAA0000A1Z5" maxlength="15" style="text-transform: uppercase;">
                    </div>
                    <div>
                        <label class="form-label">PAN (10 Digits)</label>
                        <input type="text" id="branch-pan" name="pan" class="form-control" placeholder="AAAAA0000A" maxlength="10" style="text-transform: uppercase;">
                    </div>
                </div>

                <div>
                    <label class="form-label">Premises / Street Address</label>
                    <textarea id="branch-address" name="address" class="form-control" rows="2" placeholder="Building, plot, street, industrial area"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">City</label>
                        <input type="text" id="branch-city" name="city" class="form-control" placeholder="e.g. Pune">
                    </div>
                    <div>
                        <label class="form-label">State</label>
                        <input type="text" id="branch-state" name="state" class="form-control" value="Maharashtra">
                    </div>
                    <div>
                        <label class="form-label">State Code</label>
                        <input type="text" id="branch-state-code" name="state_code" class="form-control" value="27" maxlength="2">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Pincode</label>
                        <input type="text" id="branch-pincode" name="pincode" class="form-control" placeholder="411057" maxlength="10">
                    </div>
                    <div>
                        <label class="form-label">Contact Phone</label>
                        <input type="text" id="branch-phone" name="phone" class="form-control" placeholder="+91 9876543210">
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" id="branch-email" name="email" class="form-control" placeholder="pune@wtsbill.in">
                    </div>
                </div>

                <div style="display: flex; gap: 1.5rem; align-items: center; margin-top: 0.5rem; padding: 0.75rem; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="branch-is-main" name="is_main_branch" value="1">
                        <span>Is Main Organization HQ Branch</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="branch-is-active" name="is_active" value="1" checked>
                        <span>Active for Operations</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="WTSBill.Modal.close('branch-modal')">Cancel</button>
                <button type="submit" id="branch-submit-btn" class="btn btn-primary">Save Branch</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. ASSIGN USERS MODAL -->
<div id="branch-users-modal" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title">Manage User Branch Access</h3>
            <button type="button" class="modal-close" onclick="WTSBill.Modal.close('branch-users-modal')">&times;</button>
        </div>
        <form id="branch-users-form" onsubmit="handleAssignUsersSubmit(event)">
            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" id="assign-branch-id" name="branch_id" value="">

            <div class="modal-body">
                <p id="assign-branch-subtitle" style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">
                    Select authorized users who can switch and operate in this branch.
                </p>

                <div id="assign-users-list" style="max-height: 280px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.75rem;">
                    <?php foreach ($allUsers as $u): ?>
                        <label style="display: flex; align-items: center; justify-content: space-between; padding: 0.4rem 0.5rem; border-radius: var(--radius-sm); cursor: pointer;" class="user-assign-row">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>" class="user-branch-checkbox" id="user-chk-<?= (int)$u['id'] ?>">
                                <div>
                                    <div style="font-weight: 500; font-size: 0.875rem;"><?= e($u['name']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($u['email']) ?></div>
                                </div>
                            </div>
                            <span class="badge badge-secondary" style="font-size: 0.7rem;"><?= e(strtoupper($u['role'])) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="WTSBill.Modal.close('branch-users-modal')">Cancel</button>
                <button type="submit" id="assign-submit-btn" class="btn btn-primary">Save Assignments</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateBranchModal() {
    document.getElementById('branch-modal-title').textContent = 'Register Operating Branch';
    document.getElementById('branch-form').reset();
    document.getElementById('branch-id').value = '';
    document.getElementById('branch-is-active').checked = true;
    document.getElementById('branch-is-main').checked = false;
    document.getElementById('branch-form-error').style.display = 'none';
    WTSBill.Modal.open('branch-modal');
}

function openEditBranchModal(branchId) {
    document.getElementById('branch-modal-title').textContent = 'Edit Branch Details';
    document.getElementById('branch-form-error').style.display = 'none';

    WTSBill.Http.fetchWithCsrf('/branches/' + branchId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.branch) {
                const b = data.branch;
                document.getElementById('branch-id').value = b.id;
                document.getElementById('branch-name').value = b.name || '';
                document.getElementById('branch-code').value = b.branch_code || b.code || '';
                document.getElementById('branch-gstin').value = b.gstin || '';
                document.getElementById('branch-pan').value = b.pan || '';
                document.getElementById('branch-address').value = b.address || '';
                document.getElementById('branch-city').value = b.city || '';
                document.getElementById('branch-state').value = b.state || 'Maharashtra';
                document.getElementById('branch-state-code').value = b.state_code || '27';
                document.getElementById('branch-pincode').value = b.pincode || '';
                document.getElementById('branch-phone').value = b.phone || '';
                document.getElementById('branch-email').value = b.email || '';
                document.getElementById('branch-is-main').checked = (parseInt(b.is_main_branch) === 1);
                document.getElementById('branch-is-active').checked = (parseInt(b.is_active) === 1);

                WTSBill.Modal.open('branch-modal');
            } else {
                WTSBill.Toast.show(data.message || 'Failed to load branch details', 'danger');
            }
        })
        .catch(err => {
            WTSBill.Toast.show('Error loading branch: ' + err.message, 'danger');
        });
}

function handleBranchFormSubmit(e) {
    e.preventDefault();
    const branchId = document.getElementById('branch-id').value;
    const isEdit = (branchId && parseInt(branchId) > 0);
    const url = isEdit ? '/branches/' + branchId + '/update' : '/branches';
    const btn = document.getElementById('branch-submit-btn');
    const errBox = document.getElementById('branch-form-error');

    btn.disabled = true;
    btn.textContent = 'Saving...';
    errBox.style.display = 'none';

    const formData = new FormData(document.getElementById('branch-form'));
    const payload = {};
    formData.forEach((val, key) => { payload[key] = val; });
    payload['is_main_branch'] = document.getElementById('branch-is-main').checked ? 1 : 0;
    payload['is_active'] = document.getElementById('branch-is-active').checked ? 1 : 0;

    WTSBill.Http.fetchWithCsrf(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json().then(data => ({ ok: res.ok, data })))
    .then(result => {
        btn.disabled = false;
        btn.textContent = 'Save Branch';

        if (result.ok && result.data.success) {
            WTSBill.Modal.close('branch-modal');
            WTSBill.Toast.show(result.data.message || 'Branch saved successfully.', 'success');
            setTimeout(() => { window.location.reload(); }, 400);
        } else {
            errBox.textContent = result.data.message || 'Failed to save branch.';
            errBox.style.display = 'block';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = 'Save Branch';
        errBox.textContent = 'Network error: ' + err.message;
        errBox.style.display = 'block';
    });
}

function openAssignUsersModal(branchId, branchName) {
    document.getElementById('assign-branch-id').value = branchId;
    document.getElementById('assign-branch-subtitle').textContent = 'Assign authorized users to ' + branchName;

    // Reset all checkboxes
    document.querySelectorAll('.user-branch-checkbox').forEach(cb => { cb.checked = false; });

    WTSBill.Http.fetchWithCsrf('/branches/' + branchId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.branch) {
                const assigned = data.branch.assigned_user_ids || [];
                assigned.forEach(uid => {
                    const cb = document.getElementById('user-chk-' + uid);
                    if (cb) cb.checked = true;
                });
                WTSBill.Modal.open('branch-users-modal');
            } else {
                WTSBill.Toast.show('Failed to fetch user assignments.', 'danger');
            }
        })
        .catch(err => {
            WTSBill.Toast.show('Error: ' + err.message, 'danger');
        });
}

function handleAssignUsersSubmit(e) {
    e.preventDefault();
    const branchId = document.getElementById('assign-branch-id').value;
    const btn = document.getElementById('assign-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const selectedUsers = [];
    document.querySelectorAll('.user-branch-checkbox:checked').forEach(cb => {
        selectedUsers.push(parseInt(cb.value));
    });

    WTSBill.Http.fetchWithCsrf('/branches/' + branchId + '/assign-users', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ user_ids: selectedUsers })
    })
    .then(res => res.json().then(data => ({ ok: res.ok, data })))
    .then(result => {
        btn.disabled = false;
        btn.textContent = 'Save Assignments';

        if (result.ok && result.data.success) {
            WTSBill.Modal.close('branch-users-modal');
            WTSBill.Toast.show(result.data.message || 'Assignments updated.', 'success');
            setTimeout(() => { window.location.reload(); }, 400);
        } else {
            WTSBill.Toast.show(result.data.message || 'Failed to update assignments.', 'danger');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = 'Save Assignments';
        WTSBill.Toast.show('Network error: ' + err.message, 'danger');
    });
}

function toggleBranchStatus(branchId, branchName, newStatus) {
    const action = (newStatus === 1) ? 'activate' : 'deactivate';
    if (!confirm('Are you sure you want to ' + action + ' ' + branchName + '?')) {
        return;
    }

    WTSBill.Http.fetchWithCsrf('/branches/' + branchId + '/toggle-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
    })
    .then(res => res.json().then(data => ({ ok: res.ok, data })))
    .then(result => {
        if (result.ok && result.data.success) {
            WTSBill.Toast.show(result.data.message, 'success');
            setTimeout(() => { window.location.reload(); }, 400);
        } else {
            WTSBill.Toast.show(result.data.message || 'Operation failed.', 'danger');
        }
    })
    .catch(err => {
        WTSBill.Toast.show('Network error: ' + err.message, 'danger');
    });
}
</script>
