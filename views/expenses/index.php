<?php
/**
 * WTSBill ERP — Direct & Indirect Expenses View & Recording Workflow
 *
 * @var array $expenses
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Direct & Indirect Expenses</h1>
        <p class="page-subtitle">Operational expenditures, rent, utilities, office supplies, travel vouchers, and petty cash disbursements</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="expense-search" class="form-control" placeholder="Search expense voucher..." style="width: 240px; padding: 0.4rem 0.75rem; font-size: 0.85rem;">
        <button type="button" class="btn btn-primary" onclick="window.openExpenseModal()">+ Record Expense</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Expense Vouchers Log (<span id="expenses-count"><?= count($expenses ?? []) ?></span> Vouchers)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="expenses-table">
            <thead>
                <tr>
                    <th>Voucher #</th>
                    <th>Category</th>
                    <th>Payee / Vendor</th>
                    <th>Date</th>
                    <th>Payment Mode</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody id="expenses-table-body">
                <?php if (!empty($expenses)): ?>
                    <?php foreach ($expenses as $exp): ?>
                        <tr>
                            <td><strong><?= e($exp['expense_number'] ?? 'EXP-2026-0001') ?></strong></td>
                            <td><span class="badge badge-info"><?= e($exp['category'] ?? 'Operations') ?></span></td>
                            <td><?= e($exp['payee'] ?? 'Office Supplies') ?></td>
                            <td><?= e(format_date($exp['expense_date'] ?? date('Y-m-d'))) ?></td>
                            <td><?= e($exp['payment_mode'] ?? 'Bank Transfer') ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--color-danger);"><?= format_currency((float)($exp['amount'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-expense-row">
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No expense vouchers recorded.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Expense Recording Modal -->
<div id="expense-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="expense-modal-title">
    <div class="modal-dialog" style="max-width: 560px;">
        <div class="modal-header">
            <h3 id="expense-modal-title" class="modal-title">Record Expense Voucher</h3>
            <button type="button" class="modal-close" onclick="window.closeExpenseModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="expense-form" onsubmit="window.saveExpense(event)">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div class="modal-body">
                <div id="expense-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group">
                        <label for="exp-category">Expense Category <span style="color: var(--danger);">*</span></label>
                        <select id="exp-category" name="category" class="form-control" required autofocus>
                            <option value="Rent & Premises">Rent & Premises</option>
                            <option value="Electricity & Power">Electricity & Power</option>
                            <option value="Office Supplies & Stationery">Office Supplies & Stationery</option>
                            <option value="Logistics & Freight Charges">Logistics & Freight Charges</option>
                            <option value="IT, Software & Subscriptions">IT, Software & Subscriptions</option>
                            <option value="Travel, Conveyance & Fuel">Travel, Conveyance & Fuel</option>
                            <option value="Legal & Professional Fees">Legal & Professional Fees</option>
                            <option value="Miscellaneous & Petty Cash">Miscellaneous & Petty Cash</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="exp-payee">Payee / Beneficiary <span style="color: var(--danger);">*</span></label>
                            <input type="text" id="exp-payee" name="payee" class="form-control" placeholder="e.g. Tata Power Ltd" required>
                        </div>

                        <div class="form-group">
                            <label for="exp-amount">Amount (₹) <span style="color: var(--danger);">*</span></label>
                            <input type="number" id="exp-amount" name="amount" class="form-control" placeholder="0.00" step="0.01" min="1" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="exp-date">Expense Date <span style="color: var(--danger);">*</span></label>
                            <input type="date" id="exp-date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="exp-mode">Payment Mode <span style="color: var(--danger);">*</span></label>
                            <select id="exp-mode" name="payment_mode" class="form-control" required>
                                <option value="Bank Transfer / NEFT">Bank Transfer / NEFT</option>
                                <option value="UPI / Online">UPI / Online</option>
                                <option value="Corporate Credit Card">Corporate Credit Card</option>
                                <option value="Cheque Payment">Cheque Payment</option>
                                <option value="Petty Cash">Petty Cash</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exp-desc">Voucher Description / Notes</label>
                        <textarea id="exp-desc" name="description" class="form-control" rows="2" placeholder="Purpose of expenditure and receipt reference"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeExpenseModal()">Cancel</button>
                <button type="submit" id="expense-submit-btn" class="btn btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('expense-search', 'expenses-table');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('expense-modal');
                if (modal && modal.classList.contains('active')) {
                    window.closeExpenseModal();
                }
            }
        });
    });

    window.openExpenseModal = function() {
        const form = document.getElementById('expense-form');
        form.reset();
        document.getElementById('expense-modal-error').style.display = 'none';
        document.getElementById('expense-modal-error').textContent = '';
        document.getElementById('expense-submit-btn').disabled = false;
        document.getElementById('expense-submit-btn').textContent = 'Save Expense';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('expense-modal');
        } else {
            document.getElementById('expense-modal').classList.add('active');
        }
    };

    window.closeExpenseModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('expense-modal');
        } else {
            document.getElementById('expense-modal').classList.remove('active');
        }
    };

    window.saveExpense = async function(e) {
        e.preventDefault();
        const form = document.getElementById('expense-form');
        const errBox = document.getElementById('expense-modal-error');
        const submitBtn = document.getElementById('expense-submit-btn');

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const formData = new FormData(form);

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/expenses', {
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
                window.closeExpenseModal();
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Expense recorded successfully!', 'success');
                }
                setTimeout(() => window.location.reload(), 300);
            } else {
                errBox.style.display = 'block';
                errBox.textContent = result.message || 'Failed to record expense.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Expense';
            }
        } catch (err) {
            console.error('Save expense error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Expense';
        }
    };
</script>
