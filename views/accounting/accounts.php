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
                    <th>Account Code</th>
                    <th>Account Name</th>
                    <th>Classification Type</th>
                    <th style="text-align: right;">Current Balance</th>
                    <th style="text-align: center;">Nature</th>
                </tr>
            </thead>
            <tbody id="accounts-table-body">
                <?php if (!empty($accounts)): ?>
                    <?php foreach ($accounts as $acc): ?>
                        <tr>
                            <td><code><?= e($acc['account_code']) ?></code></td>
                            <td><strong><?= e($acc['account_name']) ?></strong></td>
                            <td><span class="badge badge-info"><?= e($acc['account_type']) ?></span></td>
                            <td style="text-align: right; font-weight: 700; color: <?= in_array($acc['account_type'], ['ASSET', 'EXPENSE']) ? 'var(--color-primary)' : 'var(--color-success)' ?>;">
                                <?= format_currency((float)$acc['current_balance']) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= in_array($acc['account_type'], ['ASSET', 'EXPENSE']) ? 'primary' : 'warning' ?>">
                                    <?= in_array($acc['account_type'], ['ASSET', 'EXPENSE']) ? 'DEBIT' : 'CREDIT' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-accounts-row">
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No general ledger accounts found.</td>
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

                    <div class="form-group">
                        <label for="acc-opening">Opening Balance (₹)</label>
                        <input type="number" id="acc-opening" name="opening_balance" class="form-control" placeholder="0.00" step="0.01" value="0.00">
                    </div>

                    <div class="form-group">
                        <label for="acc-desc">Description</label>
                        <textarea id="acc-desc" name="description" class="form-control" rows="2" placeholder="Account purpose, banking details, or ledger sub-classification"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeAccountModal()">Cancel</button>
                <button type="submit" id="acc-submit-btn" class="btn btn-primary">Create Account</button>
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
        document.getElementById('account-modal-error').style.display = 'none';
        document.getElementById('account-modal-error').textContent = '';
        document.getElementById('acc-submit-btn').disabled = false;
        document.getElementById('acc-submit-btn').textContent = 'Create Account';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('account-modal');
        } else {
            document.getElementById('account-modal').classList.add('active');
        }
    };

    window.closeAccountModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('account-modal');
        } else {
            document.getElementById('account-modal').classList.remove('active');
        }
    };

    window.saveAccount = async function(e) {
        e.preventDefault();
        const form = document.getElementById('account-form');
        const errBox = document.getElementById('account-modal-error');
        const submitBtn = document.getElementById('acc-submit-btn');

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating...';

        const formData = new FormData(form);

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/accounts', {
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
                    window.WTSBill.Toast.show(result.message || 'Ledger account created successfully!', 'success');
                }
                setTimeout(() => window.location.reload(), 300);
            } else {
                errBox.style.display = 'block';
                errBox.textContent = result.message || 'Failed to create account.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
            }
        } catch (err) {
            console.error('Save account error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Account';
        }
    };
</script>
