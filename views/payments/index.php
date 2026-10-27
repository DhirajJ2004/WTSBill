<?php
/**
 * WTSBill ERP — Payments & Receipts Management View
 *
 * @var array $payments
 * @var array $summary
 * @var string $activeType
 * @var string $search
 * @var array $customers
 * @var array $suppliers
 */

use WTSBill\Auth\Gate;
use WTSBill\Core\Session;
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Payments & Receipts</h1>
        <p class="page-subtitle">Track customer collections, inward receipts, supplier remittances, and voucher records</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <?php if (Gate::can('payments.create')): ?>
            <button type="button" class="btn btn-primary" onclick="window.openPaymentModal('RECEIPT')">+ Record Receipt (In)</button>
            <button type="button" class="btn btn-secondary" onclick="window.openPaymentModal('PAYMENT')">+ Record Payment (Out)</button>
        <?php endif; ?>
    </div>
</div>

<!-- Financial Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Collections Received</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-success); margin-top: 0.25rem;">
                <?= format_currency((float)($summary['total_received'] ?? 0)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Customer receipts & cash-in</div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Disbursements Paid</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-danger); margin-top: 0.25rem;">
                <?= format_currency((float)($summary['total_paid'] ?? 0)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Supplier payments & expenses</div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Net Cash Flow</div>
            <?php 
                $net = (float)($summary['total_received'] ?? 0) - (float)($summary['total_paid'] ?? 0);
            ?>
            <div style="font-size: 1.5rem; font-weight: 700; color: <?= $net >= 0 ? 'var(--color-primary)' : 'var(--color-warning)' ?>; margin-top: 0.25rem;">
                <?= ($net >= 0 ? '+' : '') . format_currency($net) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Net recorded movements</div>
        </div>
    </div>
</div>

<!-- Filter Tabs & Search -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="/payments?type=ALL" class="btn btn-sm <?= $activeType === 'ALL' ? 'btn-primary' : 'btn-secondary' ?>">All Transactions</a>
            <a href="/payments?type=RECEIPT" class="btn btn-sm <?= $activeType === 'RECEIPT' ? 'btn-primary' : 'btn-secondary' ?>">Receipts (In)</a>
            <a href="/payments?type=PAYMENT" class="btn btn-sm <?= $activeType === 'PAYMENT' ? 'btn-primary' : 'btn-secondary' ?>">Payments (Out)</a>
        </div>
        <form method="GET" action="/payments" style="display: flex; gap: 0.5rem;">
            <input type="hidden" name="type" value="<?= e($activeType) ?>">
            <input type="text" name="search" class="form-control" placeholder="Search voucher # or ref..." value="<?= e($search) ?>" style="width: 220px; font-size: 0.85rem; padding: 0.35rem 0.65rem;">
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        </form>
    </div>

    <div class="table-container">
        <table class="table" id="payments-table">
            <thead>
                <tr>
                    <th>Voucher #</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Party</th>
                    <th>Mode</th>
                    <th>Reference</th>
                    <th style="text-align: right;">Amount</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $p): ?>
                        <?php $isRcp = ($p['payment_type'] ?? '') === 'RECEIPT'; ?>
                        <tr>
                            <td><strong><?= e($p['payment_number'] ?? $p['receipt_number']) ?></strong></td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);"><?= e(format_date($p['payment_date'])) ?></td>
                            <td>
                                <span class="badge badge-<?= $isRcp ? 'success' : 'info' ?>">
                                    <?= $isRcp ? 'RECEIPT' : 'PAYMENT' ?>
                                </span>
                            </td>
                            <td><?= e($p['party_type'] . ($p['party_id'] ? " #{$p['party_id']}" : '')) ?></td>
                            <td style="font-size: 0.85rem;"><?= e($p['payment_mode']) ?></td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);"><?= e($p['reference_no'] ?: '—') ?></td>
                            <td style="text-align: right; font-weight: 700; color: <?= $isRcp ? 'var(--color-success)' : 'var(--text-primary)' ?>;">
                                <?= ($isRcp ? '+' : '-') . format_currency((float)$p['amount']) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= $p['status'] === 'COMPLETED' ? 'success' : 'secondary' ?>">
                                    <?= e($p['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 0.25rem; justify-content: flex-end;">
                                    <?php if (Gate::can('payments.print')): ?>
                                        <a href="/payments/<?= (int)$p['id'] ?>/print" target="_blank" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" title="Print Voucher">
                                            Print
                                        </a>
                                    <?php endif; ?>
                                    <?php if (Gate::can('payments.delete')): ?>
                                        <form method="POST" action="/payments/<?= (int)$p['id'] ?>/delete" onsubmit="return confirm('Cancel and void this payment voucher?');" style="margin: 0; display: inline;">
                                            <input type="hidden" name="_csrf_token" value="<?= e(Session::csrfToken()) ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                            No payment or receipt vouchers found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Record Payment / Receipt -->
<div id="payment-modal" class="modal-backdrop" role="dialog" aria-modal="true" style="display: none;">
    <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-header">
            <h3 id="payment-modal-title" class="modal-title">Record Payment / Receipt</h3>
            <button type="button" class="modal-close" onclick="window.closePaymentModal()">&times;</button>
        </div>
        <form method="POST" action="/payments/create">
            <input type="hidden" name="_csrf_token" value="<?= e(Session::csrfToken()) ?>">
            <input type="hidden" id="p-payment-type" name="payment_type" value="RECEIPT">

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="p-amount">Amount (INR) <span style="color: var(--danger);">*</span></label>
                        <input type="number" step="0.01" id="p-amount" name="amount" class="form-control" placeholder="0.00" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="p-date">Payment Date <span style="color: var(--danger);">*</span></label>
                        <input type="date" id="p-date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="p-mode">Payment Mode</label>
                        <select id="p-mode" name="payment_mode" class="form-control">
                            <option value="Bank Transfer">Bank Transfer / NEFT</option>
                            <option value="UPI">UPI / QR Payment</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Cash">Cash</option>
                            <option value="Card">Credit / Debit Card</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="p-ref">Reference # / UTR</label>
                        <input type="text" id="p-ref" name="reference_no" class="form-control" placeholder="e.g. UTR-982182">
                    </div>
                </div>

                <div class="form-group" id="party-select-container">
                    <label id="p-party-label" for="p-party">Party</label>
                    <select id="p-party" name="party_id" class="form-control">
                        <option value="">-- Direct Party / General --</option>
                        <optgroup label="Customers" id="opt-customers">
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group">
                    <label for="p-notes">Notes / Remarks</label>
                    <textarea id="p-notes" name="notes" class="form-control" rows="2" placeholder="e.g. Cleared invoice balance or advance"></textarea>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closePaymentModal()">Cancel</button>
                <button type="submit" id="p-submit-btn" class="btn btn-primary">Save Voucher</button>
            </div>
        </form>
    </div>
</div>

<script>
window.openPaymentModal = function(type) {
    document.getElementById('p-payment-type').value = type;
    var modal = document.getElementById('payment-modal');
    var title = document.getElementById('payment-modal-title');
    var btn = document.getElementById('p-submit-btn');

    if (type === 'RECEIPT') {
        title.textContent = 'Record Inward Receipt (Money In)';
        btn.textContent = 'Save Money Receipt';
        btn.className = 'btn btn-primary';
    } else {
        title.textContent = 'Record Outward Payment (Money Out)';
        btn.textContent = 'Save Payment Voucher';
        btn.className = 'btn btn-danger';
    }

    modal.style.display = 'flex';
};

window.closePaymentModal = function() {
    document.getElementById('payment-modal').style.display = 'none';
};
</script>
