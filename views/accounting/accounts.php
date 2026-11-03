<?php
/**
 * WTSBill ERP — Chart of Accounts View & Ledger Creation Workflow
 *
 * @var array $accounts
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Chart of Accounts</h1>
        <p class="page-subtitle">Standard double-entry accounting ledger hierarchy: Assets, Liabilities, Equity, Revenue, and Expense groups</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="account-search" class="form-control" placeholder="Search account name or code..." style="width: 260px; padding: 0.4rem 0.75rem; font-size: 0.85rem;">
        <button type="button" class="btn btn-primary" onclick="window.openAccountModal()">+ New Account</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">General Ledger Accounts (<span id="accounts-count"><?= count($accounts ?? []) ?></span> Accounts)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="accounts-table">
            <thead>
                <tr>
                    <th style="width: 14%;">Account Code</th>
                    <th style="width: 28%;">Account Name</th>
                    <th style="width: 18%;">Classification Type</th>
                    <th style="width: 16%; text-align: right;">Current Balance</th>
                    <th style="width: 10%; text-align: center;">Nature</th>
                    <th style="width: 14%; text-align: center; white-space: nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody id="accounts-table-body">
                <?php if (!empty($accounts)): ?>
                    <?php foreach ($accounts as $acc): ?>
                        <tr id="account-row-<?= (int)$acc['id'] ?>">
                            <td><code><?= e($acc['account_code']) ?></code></td>
                            <td>
                                <strong><?= e($acc['account_name']) ?></strong>
                                <?php if (!empty($acc['description'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($acc['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-info"><?= e($acc['account_type']) ?></span></td>
                            <td style="text-align: right; font-weight: 700; color: <?= in_array($acc['account_type'], ['ASSET', 'EXPENSE']) ? 'var(--color-primary)' : 'var(--color-success)' ?>;">
                                <?= format_currency((float)$acc['current_balance']) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= in_array($acc['account_type'], ['ASSET', 'EXPENSE']) ? 'primary' : 'warning' ?>">
                                    <?= in_array($acc['account_type'], ['ASSET', 'EXPENSE']) ? 'DEBIT' : 'CREDIT' ?>
                                </span>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: flex; gap: 0.35rem; justify-content: center; align-items: center;">
                                    <?php if (\WTSBill\Auth\Gate::can('accounting.edit')): ?>
                                        <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" onclick="window.editAccount(<?= (int)$acc['id'] ?>)">Edit</button>
                                    <?php endif; ?>
                                    <?php if (\WTSBill\Auth\Gate::can('accounting.delete')): ?>
                                        <button type="button" class="btn btn-outline-danger" style="padding: 0.25rem 0.55rem; font-size: 0.78rem; color: var(--danger); border-color: var(--danger);" onclick="window.deleteAccount(<?= (int)$acc['id'] ?>, '<?= e(addslashes($acc['account_name'])) ?>', <?= (int)($acc['is_system_account'] ?? 0) ?>)">Delete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-accounts-row">
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No general ledger accounts found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Account Creation Modal -->
<div id="account-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="account-modal-title">
    <div class="modal-dialog" style="max-width: 560px;">
        <div class="modal-header">
            <h3 id="account-modal-title" class="modal-title">Create General Ledger Account</h3>
            <button type="button" class="modal-close" onclick="window.closeAccountModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="account-form" onsubmit="window.saveAccount(event)">
            <input type="hidden" id="acc-id" name="id" value="">
            <input type="hidden" id="acc-is-system" name="is_system" value="0">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div class="modal-body">
                <div id="account-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group">
                        <label for="acc-name">Account Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="acc-name" name="account_name" class="form-control" placeholder="e.g. State Bank of India - Current A/c" required autofocus>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="acc-type">Account Classification <span style="color: var(--danger);">*</span></label>
                            <select id="acc-type" name="account_type" class="form-control" required>
                                <option value="ASSET">Asset (Dr)</option>
                                <option value="LIABILITY">Liability (Cr)</option>
                                <option value="EQUITY">Equity / Capital (Cr)</option>
                                <option value="INCOME">Income / Revenue (Cr)</option>
                                <option value="EXPENSE">Expense (Dr)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="acc-code">Account Code (GL Code)</label>
                            <input type="text" id="acc-code" name="account_code" class="form-control" placeholder="Auto-generated if empty">
                        </div>
                    </div>

                    <div class="form-group" id="acc-opening-group">
                        <label for="acc-opening">Opening Balance (₹)</label>
                        <input type="number" id="acc-opening" name="opening_balance" class="form-control" placeholder="0.00" step="0.01" value="0.00">
                    </div>

                    <div class="form-group" id="acc-status-group" style="display: none;">
                        <label for="acc-status">Account Status</label>
                        <select id="acc-status" name="is_active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="acc-desc">Description</label>
                        <textarea id="acc-desc" name="description" class="form-control" rows="2" placeholder="Account purpose, banking details, or ledger sub-classification"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <?php if (\WTSBill\Auth\Gate::can('accounting.delete')): ?>
                        <button type="button" id="acc-modal-delete-btn" class="btn btn-outline-danger" style="display: none; padding: 0.4rem 0.75rem; font-size: 0.85rem; color: var(--danger); border-color: var(--danger);" onclick="window.deleteCurrentModalAccount()">Delete Account</button>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="window.closeAccountModal()">Cancel</button>
                    <button type="submit" id="acc-submit-btn" class="btn btn-primary">Create Account</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('account-search', 'accounts-table');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('account-modal');
                if (modal && modal.classList.contains('active')) {
                    window.closeAccountModal();
                }
            }
        });
    });

    window.openAccountModal = function() {
        const form = document.getElementById('account-form');
        form.reset();
        document.getElementById('acc-id').value = '';
        document.getElementById('acc-is-system').value = '0';
        document.getElementById('account-modal-title').textContent = 'Create General Ledger Account';
        document.getElementById('account-modal-error').style.display = 'none';
        document.getElementById('account-modal-error').textContent = '';
        document.getElementById('acc-submit-btn').disabled = false;
        document.getElementById('acc-submit-btn').textContent = 'Create Account';
        document.getElementById('acc-type').disabled = false;
        document.getElementById('acc-code').readOnly = false;
        document.getElementById('acc-opening').disabled = false;
        document.getElementById('acc-opening-group').style.display = 'block';
        document.getElementById('acc-status-group').style.display = 'none';
        const modalDeleteBtn = document.getElementById('acc-modal-delete-btn');
        if (modalDeleteBtn) modalDeleteBtn.style.display = 'none';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('account-modal');
        } else {
            document.getElementById('account-modal').classList.add('active');
        }
        setTimeout(() => document.getElementById('acc-name').focus(), 100);
    };

    window.closeAccountModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('account-modal');
        } else {
            document.getElementById('account-modal').classList.remove('active');
        }
    };

    window.editAccount = async function(id) {
        window.openAccountModal();
        document.getElementById('account-modal-title').textContent = 'Edit General Ledger Account';
        document.getElementById('acc-submit-btn').textContent = 'Update Account';
        document.getElementById('acc-opening').disabled = true;
        document.getElementById('acc-opening-group').style.display = 'none';
        document.getElementById('acc-status-group').style.display = 'block';

        const modalDeleteBtn = document.getElementById('acc-modal-delete-btn');
        if (modalDeleteBtn) modalDeleteBtn.style.display = 'inline-block';

        try {
            const resp = await fetch('/accounts/' + id, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            if (data.success && data.account) {
                const acc = data.account;
                document.getElementById('acc-id').value = acc.id;
                document.getElementById('acc-name').value = acc.account_name || '';
                document.getElementById('acc-type').value = acc.account_type || 'ASSET';
                document.getElementById('acc-code').value = acc.account_code || '';
                document.getElementById('acc-opening').value = parseFloat(acc.opening_balance || 0).toFixed(2);
                document.getElementById('acc-desc').value = acc.description || '';
                document.getElementById('acc-status').value = acc.is_active ? '1' : '0';
                document.getElementById('acc-is-system').value = acc.is_system_account ? '1' : '0';

                if (parseInt(acc.is_system_account) === 1) {
                    document.getElementById('acc-type').disabled = true;
                    document.getElementById('acc-code').readOnly = true;
                } else {
                    document.getElementById('acc-type').disabled = false;
                    document.getElementById('acc-code').readOnly = false;
                }
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Failed to load account details.', 'danger');
                }
            }
        } catch (err) {
            console.error('Account fetch error:', err);
        }
    };

    window.saveAccount = async function(e) {
        e.preventDefault();
        const form = document.getElementById('account-form');
        const errBox = document.getElementById('account-modal-error');
        const submitBtn = document.getElementById('acc-submit-btn');
        const id = document.getElementById('acc-id').value;

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = id ? 'Updating...' : 'Creating...';

        const formData = new FormData(form);
        const url = id ? ('/accounts/' + id + '/update') : '/accounts';

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
                window.closeAccountModal();
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Ledger account saved successfully!', 'success');
                }
                setTimeout(() => window.location.reload(), 300);
            } else {
                errBox.style.display = 'block';
                errBox.textContent = result.message || 'An error occurred while saving account.';
                submitBtn.disabled = false;
                submitBtn.textContent = id ? 'Update Account' : 'Create Account';
            }
        } catch (err) {
            console.error('Save account error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = id ? 'Update Account' : 'Create Account';
        }
    };

    window.deleteCurrentModalAccount = function() {
        const id = document.getElementById('acc-id').value;
        const name = document.getElementById('acc-name').value;
        const isSystem = parseInt(document.getElementById('acc-is-system').value) === 1;
        if (id) {
            window.closeAccountModal();
            window.deleteAccount(id, name, isSystem);
        }
    };

    window.deleteAccount = async function(id, name, isSystem = false) {
        if (isSystem) {
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('System account "' + (name || '#' + id) + '" is a core system ledger account and cannot be deleted.', 'warning');
            }
            return;
        }

        if (!confirm('Are you sure you want to delete ledger account "' + (name || '#' + id) + '"? This will remove the account from the Chart of Accounts.')) {
            return;
        }

        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        try {
            const resp = await fetch('/accounts/' + id + '/delete', {
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
                    window.WTSBill.Toast.show(data.message || 'Ledger account deleted successfully.', 'success');
                }
                const row = document.getElementById('account-row-' + id);
                if (row) {
                    row.remove();
                }

                // Update accounts count
                const countSpan = document.getElementById('accounts-count');
                if (countSpan) {
                    const currentCount = parseInt(countSpan.textContent) || 0;
                    countSpan.textContent = Math.max(0, currentCount - 1);
                }

                // If no rows remain, show empty row
                const tbody = document.getElementById('accounts-table-body');
                if (tbody && tbody.querySelectorAll('tr').length === 0) {
                    tbody.innerHTML = '<tr id="no-accounts-row"><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No general ledger accounts found.</td></tr>';
                }
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Failed to delete account.', 'danger');
                }
            }
        } catch (err) {
            console.error('Delete account error:', err);
            // Fallback to standard form submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/accounts/' + id + '/delete';
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
