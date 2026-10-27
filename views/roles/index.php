<?php
/**
 * WTSBill ERP — Enterprise Roles & Permissions Matrix View
 *
 * @var array $roles
 * @var array $permissions
 * @var array $modules
 * @var array $rolePermissionsMap
 * @var array $selectedRole
 * @var int $selectedRoleId
 */
use WTSBill\Core\Session;

$csrfToken = Session::getCsrfToken();
$isSystemRole = (int)($selectedRole['is_system'] ?? 0) === 1;
$isAdminRole = ($selectedRole['slug'] ?? '') === 'admin';
$currentPerms = $rolePermissionsMap[$selectedRoleId] ?? [];
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700;">Roles & Permissions Matrix</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.875rem;">
            Configure granular access control, module visibility, and action privileges for each role
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <button type="button" class="btn btn-secondary" onclick="openCreateRoleModal()">+ Create Custom Role</button>
        <?php if ($selectedRole): ?>
            <button type="button" class="btn btn-secondary" onclick="duplicateCurrentRole()">Duplicate Role</button>
        <?php endif; ?>
    </div>
</div>

<!-- Role Selector Pills -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-right: 0.5rem;">Select Role:</span>
            <?php foreach ($roles as $r): ?>
                <?php $isActive = (int)$r['id'] === $selectedRoleId; ?>
                <a href="/roles?role_id=<?= (int)$r['id'] ?>" 
                   class="btn <?= $isActive ? 'btn-primary' : 'btn-secondary' ?>" 
                   style="font-size: 0.85rem; padding: 0.4rem 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem;">
                    <span><?= e($r['name']) ?></span>
                    <?php if ((int)$r['is_system'] === 1): ?>
                        <span class="badge badge-info" style="font-size: 0.65rem; padding: 0.1rem 0.35rem;">SYSTEM</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if ($selectedRole && !$isSystemRole): ?>
            <button type="button" class="btn btn-danger btn-sm" onclick="deleteCurrentRole()">Delete Role</button>
        <?php endif; ?>
    </div>
</div>

<!-- Role Details & Matrix Form -->
<form id="role-matrix-form" onsubmit="saveRolePermissions(event)">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="id" value="<?= (int)$selectedRoleId ?>">

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 class="card-title" style="margin: 0; font-size: 1.15rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <span><?= e($selectedRole['name'] ?? 'Role') ?></span>
                    <span class="badge badge-secondary" style="font-size: 0.75rem; font-family: monospace;"><?= e($selectedRole['slug'] ?? '') ?></span>
                    <?php if ($isSystemRole): ?>
                        <span class="badge badge-info" style="font-size: 0.75rem;">System Defined</span>
                    <?php endif; ?>
                </h3>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.825rem; color: var(--text-muted);">
                    <?= e($selectedRole['description'] ?? 'No description provided') ?>
                </p>
            </div>
            
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <?php if (!$isAdminRole): ?>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAllCheckboxes(true)">Select All</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAllCheckboxes(false)">Deselect All</button>
                <?php endif; ?>
                <button type="submit" id="save-matrix-btn" class="btn btn-primary" <?= $isAdminRole ? 'disabled title="Administrator has all permissions by system design"' : '' ?>>
                    Save Permissions
                </button>
            </div>
        </div>

        <?php if ($isAdminRole): ?>
            <div style="padding: 1rem 1.25rem; background: rgba(59, 130, 246, 0.1); border-bottom: 1px solid var(--border-color); color: #93c5fd; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <span>The <strong>Administrator</strong> role has unrestricted full access to every module, setting, and transaction in WTSBill ERP.</span>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table class="table" id="matrix-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Module / Resource</th>
                        <th style="width: 75%;">Allowed Actions & Privileges</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $modKey => $modData): ?>
                        <tr>
                            <td style="vertical-align: top; padding-top: 1rem;">
                                <strong style="font-size: 0.95rem; color: var(--text-primary);"><?= e($modData['label']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;"><code><?= e($modKey) ?></code></div>
                                <?php if (!$isAdminRole): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size: 0.7rem; padding: 0.15rem 0.4rem; margin-top: 0.4rem;" onclick="toggleModuleCheckboxes('<?= e($modKey) ?>')">
                                        Toggle All
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td style="vertical-align: top; padding-top: 1rem;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 0.75rem;">
                                    <?php foreach ($modData['permissions'] as $p): ?>
                                        <?php 
                                            $isChecked = $isAdminRole || isset($currentPerms[(int)$p['id']]);
                                        ?>
                                        <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.85rem; cursor: <?= $isAdminRole ? 'default' : 'pointer' ?>; background: var(--bg-hover, rgba(255,255,255,0.03)); padding: 0.5rem 0.65rem; border-radius: var(--radius); border: 1px solid var(--border-color);">
                                            <input type="checkbox" 
                                                   name="permissions[]" 
                                                   value="<?= (int)$p['id'] ?>" 
                                                   class="perm-checkbox perm-mod-<?= e($modKey) ?>"
                                                   <?= $isChecked ? 'checked' : '' ?>
                                                   <?= $isAdminRole ? 'disabled' : '' ?>
                                                   style="margin-top: 0.2rem;">
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-primary);">
                                                    <?= e($p['name'] ?: $p['slug']) ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                                    <?= e($p['slug']) ?>
                                                </div>
                                                <?php if (!empty($p['description'])): ?>
                                                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.15rem;">
                                                        <?= e($p['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<!-- Modal: Create Custom Role -->
<div id="create-role-modal" class="modal-backdrop" role="dialog" aria-modal="true">
    <div class="modal-dialog" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Create Custom Role</h3>
            <button type="button" class="modal-close" onclick="closeCreateRoleModal()">&times;</button>
        </div>
        <form id="create-role-form" onsubmit="submitCreateRole(event)">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label for="cr-name">Role Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="cr-name" name="name" class="form-control" placeholder="e.g. Warehouse Supervisor" required>
                </div>
                <div class="form-group">
                    <label for="cr-desc">Description</label>
                    <textarea id="cr-desc" name="description" class="form-control" rows="3" placeholder="Brief summary of duties and access permissions"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeCreateRoleModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create & Configure Matrix</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAllCheckboxes(checked) {
    document.querySelectorAll('.perm-checkbox:not(:disabled)').forEach(cb => {
        cb.checked = checked;
    });
}

function toggleModuleCheckboxes(modKey) {
    const cbs = document.querySelectorAll('.perm-mod-' + modKey + ':not(:disabled)');
    const anyUnchecked = Array.from(cbs).some(cb => !cb.checked);
    cbs.forEach(cb => cb.checked = anyUnchecked);
}

function openCreateRoleModal() {
    document.getElementById('create-role-form').reset();
    if (window.WTSBill && window.WTSBill.Modal) {
        window.WTSBill.Modal.open('create-role-modal');
    } else {
        document.getElementById('create-role-modal').classList.add('active');
    }
}

function closeCreateRoleModal() {
    if (window.WTSBill && window.WTSBill.Modal) {
        window.WTSBill.Modal.close('create-role-modal');
    } else {
        document.getElementById('create-role-modal').classList.remove('active');
    }
}

async function submitCreateRole(e) {
    e.preventDefault();
    const form = document.getElementById('create-role-form');
    const formData = new FormData(form);

    try {
        const resp = await fetch('/roles', {
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
            closeCreateRoleModal();
            window.location.href = '/roles?role_id=' + res.role_id;
        } else {
            if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show(res.message || 'Failed to create role', 'error');
        }
    } catch (err) {
        console.error(err);
        if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show('Server error creating role', 'error');
    }
}

async function saveRolePermissions(e) {
    e.preventDefault();
    const form = document.getElementById('role-matrix-form');
    const btn = document.getElementById('save-matrix-btn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const formData = new FormData(form);

    try {
        const resp = await fetch('/roles/<?= (int)$selectedRoleId ?>/update', {
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
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show(res.message || 'Permissions updated successfully!', 'success');
            }
            setTimeout(() => window.location.reload(), 300);
        } else {
            if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show(res.message || 'Failed to update permissions', 'error');
            btn.disabled = false;
            btn.textContent = 'Save Permissions';
        }
    } catch (err) {
        console.error(err);
        if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show('Server error saving permissions', 'error');
        btn.disabled = false;
        btn.textContent = 'Save Permissions';
    }
}

async function duplicateCurrentRole() {
    if (!confirm('Duplicate role "<?= e($selectedRole['name'] ?? '') ?>"?')) return;

    try {
        const resp = await fetch('/roles/<?= (int)$selectedRoleId ?>/duplicate', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '<?= e($csrfToken) ?>'
            }
        });
        const res = await resp.json();
        if (resp.ok && res.success) {
            window.location.href = '/roles?role_id=' + res.role_id;
        } else {
            if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show(res.message || 'Failed to duplicate role', 'error');
        }
    } catch (err) {
        console.error(err);
        if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show('Server error duplicating role', 'error');
    }
}

async function deleteCurrentRole() {
    if (!confirm('Are you sure you want to delete role "<?= e($selectedRole['name'] ?? '') ?>"? This cannot be undone.')) return;

    try {
        const resp = await fetch('/roles/<?= (int)$selectedRoleId ?>/delete', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '<?= e($csrfToken) ?>'
            }
        });
        const res = await resp.json();
        if (resp.ok && res.success) {
            window.location.href = '/roles';
        } else {
            if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show(res.message || 'Failed to delete role', 'error');
        }
    } catch (err) {
        console.error(err);
        if (window.WTSBill && window.WTSBill.Toast) window.WTSBill.Toast.show('Server error deleting role', 'error');
    }
}
</script>
