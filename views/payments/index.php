<?php
/**
 * WTSBill ERP — Payments & Receipts Management View
 *
 * @var array $payments
 * @var array $summary
 * @var string $activeType
 * @var string $activeTab
 * @var string $search
 * @var array $customers
 * @var array $suppliers
 * @var array $bankAccounts
 * @var array $invoicePayments
 * @var array $invoiceKpi
 * @var array $filters
 */

use WTSBill\Auth\Gate;
use WTSBill\Core\Session;

$activeTab = $activeTab ?? 'vouchers';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Payments & Receipts</h1>
        <p class="page-subtitle">Track customer collections, partial invoice installments, supplier remittances, and voucher records</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <?php if (Gate::can('payments.create')): ?>
            <button type="button" class="btn btn-primary" onclick="window.openPaymentModal('RECEIPT')">+ Record Receipt (In)</button>
            <button type="button" class="btn btn-secondary" onclick="window.openPaymentModal('PAYMENT')">+ Record Payment (Out)</button>
        <?php endif; ?>
    </div>
</div>

<!-- Navigation Tabs -->
<div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
    <a href="/payments?tab=vouchers" class="btn <?= $activeTab !== 'invoices' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 20px; padding: 0.45rem 1.25rem; font-size: 0.85rem; font-weight: 600;">
        Vouchers Register
    </a>
    <a href="/payments?tab=invoices" class="btn <?= $activeTab === 'invoices' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 20px; padding: 0.45rem 1.25rem; font-size: 0.85rem; font-weight: 600;">
        Partial Payments & Invoice Balances
    </a>
</div>

<?php if ($activeTab === 'invoices'): ?>
    <!-- TAB 2: PARTIAL PAYMENTS & INVOICE BALANCES -->

    <!-- Invoice Receivables KPI Summary -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="margin-bottom: 0;">
            <div class="card-body">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Invoiced</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-top: 0.25rem;">
                    <?= format_currency((float)($invoiceKpi['total_invoiced'] ?? 0)) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Lifetime tax invoices billed</div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div class="card-body">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Collections Received</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-success); margin-top: 0.25rem;">
                    <?= format_currency((float)($invoiceKpi['total_collected'] ?? 0)) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Realized installments & receipts</div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div class="card-body">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Outstanding Balance Due</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-danger); margin-top: 0.25rem;">
                    <?= format_currency((float)($invoiceKpi['total_outstanding'] ?? 0)) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Pending customer receivables</div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div class="card-body">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Overdue Invoices</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: <?= (int)($invoiceKpi['overdue_count'] ?? 0) > 0 ? 'var(--color-warning)' : 'var(--color-success)' ?>; margin-top: 0.25rem;">
                    <?= (int)($invoiceKpi['overdue_count'] ?? 0) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Past due date with pending balance</div>
            </div>
        </div>
    </div>

    <!-- Filters & Search for Invoices -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-body" style="padding: 1rem 1.25rem;">
            <form method="GET" action="/payments" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end;">
                <input type="hidden" name="tab" value="invoices">

                <div style="flex: 1; min-width: 180px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Invoice # or customer..." value="<?= e($filters['search'] ?? '') ?>" style="font-size: 0.85rem; padding: 0.4rem 0.65rem;">
                </div>

                <div style="width: 200px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">Customer</label>
                    <select name="customer_id" class="form-control" style="font-size: 0.85rem; padding: 0.4rem 0.65rem;">
                        <option value="">All Customers</option>
                        <?php foreach ($customers ?? [] as $cust): ?>
                            <option value="<?= (int)$cust['id'] ?>" <?= ((string)($filters['customer_id'] ?? '') === (string)$cust['id']) ? 'selected' : '' ?>>
                                <?= e($cust['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="width: 160px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">Payment Status</label>
                    <select name="payment_status" class="form-control" style="font-size: 0.85rem; padding: 0.4rem 0.65rem;">
                        <option value="ALL" <?= ($filters['status'] ?? 'ALL') === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="UNPAID" <?= ($filters['status'] ?? '') === 'UNPAID' ? 'selected' : '' ?>>Unpaid</option>
                        <option value="PARTIAL" <?= ($filters['status'] ?? '') === 'PARTIAL' ? 'selected' : '' ?>>Partially Paid</option>
                        <option value="PAID" <?= ($filters['status'] ?? '') === 'PAID' ? 'selected' : '' ?>>Paid</option>
                        <option value="OVERDUE" <?= ($filters['status'] ?? '') === 'OVERDUE' ? 'selected' : '' ?>>Overdue</option>
                    </select>
                </div>

                <div style="width: 140px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= e($filters['from_date'] ?? '') ?>" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                </div>

                <div style="width: 140px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= e($filters['to_date'] ?? '') ?>" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.85rem;">Apply</button>
                    <a href="/payments?tab=invoices" class="btn btn-secondary" style="padding: 0.45rem 0.75rem; font-size: 0.85rem;">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Invoices Receivables Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Customer Invoice Receivables (<?= count($invoicePayments ?? []) ?> Invoices)</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Invoice Date</th>
                        <th>Due Date</th>
                        <th style="text-align: right;">Total Amount</th>
                        <th style="text-align: right;">Total Paid</th>
                        <th style="text-align: right;">Outstanding Balance</th>
                        <th style="text-align: center;">Status</th>
                        <th>Last Payment</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($invoicePayments)): ?>
                        <?php foreach ($invoicePayments as $inv): ?>
                            <?php
                                $statusBadge = match($inv['status']) {
                                    'PAID' => 'success',
                                    'PARTIAL' => 'warning',
                                    'OVERDUE' => 'danger',
                                    default => 'danger'
                                };
                            ?>
                            <tr>
                                <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                                <td><?= e($inv['customer_name']) ?></td>
                                <td style="font-size: 0.85rem;"><?= e(format_date($inv['invoice_date'])) ?></td>
                                <td style="font-size: 0.85rem; color: <?= ($inv['is_overdue'] ?? false) ? 'var(--color-danger)' : 'var(--text-muted)' ?>;">
                                    <?= e($inv['due_date'] ? format_date($inv['due_date']) : 'Immediate') ?>
                                </td>
                                <td style="text-align: right; font-weight: 600;"><?= format_currency((float)$inv['grand_total']) ?></td>
                                <td style="text-align: right; color: var(--color-success); font-weight: 600;"><?= format_currency((float)$inv['amount_paid']) ?></td>
                                <td style="text-align: right; color: <?= (float)$inv['amount_due'] > 0.001 ? 'var(--color-danger)' : 'var(--color-success)' ?>; font-weight: 700;">
                                    <?= format_currency((float)$inv['amount_due']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= $statusBadge ?>">
                                        <?= e($inv['computed_status_label'] ?? $inv['status']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= $inv['last_payment_date'] ? e(format_date($inv['last_payment_date'])) : '—' ?>
                                    <?php if ((int)($inv['installments_count'] ?? 0) > 0): ?>
                                        <div style="font-size: 0.72rem; color: var(--color-primary);"><?= (int)$inv['installments_count'] ?> installment(s)</div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: flex; gap: 0.25rem; justify-content: flex-end;">
                                        <?php if (Gate::can('payments.create') && (float)$inv['amount_due'] > 0.001): ?>
                                            <button type="button" class="btn btn-sm btn-success" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" onclick="window.openRecordInvoicePaymentModal(<?= (int)$inv['id'] ?>, '<?= e(addslashes($inv['invoice_number'])) ?>', '<?= e(addslashes($inv['customer_name'])) ?>', <?= (float)$inv['grand_total'] ?>, <?= (float)$inv['amount_paid'] ?>, <?= (float)$inv['amount_due'] ?>)">
                                                + Pay
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-secondary" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" onclick="window.viewInvoicePayments(<?= (int)$inv['id'] ?>, '<?= e(addslashes($inv['invoice_number'])) ?>')">
                                            History
                                        </button>
                                        <?php if (Gate::can('invoices.view')): ?>
                                            <a href="/sales/invoices/<?= (int)$inv['id'] ?>/print" target="_blank" class="btn btn-sm btn-secondary" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" title="Print Invoice">
                                                Invoice
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                                No invoices matching the filter criteria found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- TAB 1: VOUCHERS REGISTER (DEFAULT) -->

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
                <a href="/payments?tab=vouchers&type=ALL" class="btn btn-sm <?= $activeType === 'ALL' ? 'btn-primary' : 'btn-secondary' ?>">All Transactions</a>
                <a href="/payments?tab=vouchers&type=RECEIPT" class="btn btn-sm <?= $activeType === 'RECEIPT' ? 'btn-primary' : 'btn-secondary' ?>">Receipts (In)</a>
                <a href="/payments?tab=vouchers&type=PAYMENT" class="btn btn-sm <?= $activeType === 'PAYMENT' ? 'btn-primary' : 'btn-secondary' ?>">Payments (Out)</a>
            </div>
            <form method="GET" action="/payments" style="display: flex; gap: 0.5rem;">
                <input type="hidden" name="tab" value="vouchers">
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
<?php endif; ?>

<!-- Modal: Record Payment / Receipt Voucher -->
<div id="payment-modal" class="modal-backdrop" role="dialog" aria-modal="true" style="display: none;">
    <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-header">
            <h3 id="payment-modal-title" class="modal-title">Record Payment / Receipt</h3>
            <button type="button" class="modal-close" onclick="window.closePaymentModal()">&times;</button>
        </div>
        <form method="POST" action="/payments">
            <input type="hidden" name="_csrf_token" value="<?= e(Session::csrfToken()) ?>">
            <input type="hidden" id="p-payment-type" name="payment_type" value="RECEIPT">

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="p-amount">Amount (INR) <span style="color: var(--danger);">*</span></label>
                        <input type="number" step="0.01" min="0.01" id="p-amount" name="amount" class="form-control" placeholder="0.00" required autofocus>
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
                            <option value="UPI">UPI / QR Payment</option>
                            <option value="Bank Transfer">Bank Transfer / NEFT</option>
                            <option value="Cash">Cash</option>
                            <option value="Cheque">Cheque</option>
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
                        <optgroup label="Suppliers" id="opt-suppliers" style="display: none;">
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group">
                    <label for="p-bank-account">Deposit / Withdrawal Bank Account</label>
                    <select id="p-bank-account" name="bank_account_id" class="form-control">
                        <option value="">-- Primary Operating Account --</option>
                        <?php foreach ($bankAccounts as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= e($b['bank_name']) ?> - <?= e($b['account_name'] ?: $b['account_number']) ?></option>
                        <?php endforeach; ?>
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

<!-- Modal: Record Invoice Payment Installment (Dedicated) -->
<div id="record-invoice-payment-modal" class="modal-backdrop" role="dialog" aria-modal="true" style="display: none;">
    <div class="modal-dialog" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title">Record Payment Installment</h3>
            <button type="button" class="modal-close" onclick="window.closeRecordInvoicePaymentModal()">&times;</button>
        </div>
        <form id="record-invoice-payment-form" onsubmit="window.submitInvoicePayment(event)">
            <input type="hidden" id="rip-invoice-id" name="invoice_id" value="">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Summary Card -->
                <div style="background: var(--bg-hover); padding: 0.85rem 1rem; border-radius: var(--radius); border: 1px solid var(--border-color); display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.85rem;">
                    <div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Invoice #</div>
                        <strong id="rip-invoice-number" style="color: var(--text-primary); font-size: 0.95rem;">-</strong>
                    </div>
                    <div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Customer</div>
                        <strong id="rip-customer-name" style="color: var(--text-primary); font-size: 0.95rem;">-</strong>
                    </div>
                    <div style="margin-top: 0.35rem;">
                        <div style="color: var(--text-muted); font-size: 0.75rem;">Invoice Total: <strong id="rip-invoice-total">₹0.00</strong></div>
                        <div style="color: var(--color-success); font-size: 0.75rem;">Previously Paid: <strong id="rip-previously-paid">₹0.00</strong></div>
                    </div>
                    <div style="margin-top: 0.35rem; text-align: right;">
                        <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Outstanding Due</div>
                        <div id="rip-outstanding-balance" style="font-size: 1.15rem; font-weight: 700; color: var(--color-danger);">₹0.00</div>
                    </div>
                </div>

                <div id="rip-error-alert" class="alert alert-danger" style="display: none; padding: 0.6rem 0.85rem; font-size: 0.85rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="rip-amount" style="font-size: 0.85rem; font-weight: 600;">Payment Amount (₹) <span style="color: var(--danger);">*</span></label>
                        <input type="number" step="0.01" min="0.01" id="rip-amount" name="amount" class="form-control" placeholder="0.00" required autofocus style="font-weight: 700; font-size: 1rem;">
                        <small style="color: var(--text-muted); font-size: 0.72rem;">Max payable: <span id="rip-max-hint">₹0.00</span></small>
                    </div>
                    <div class="form-group">
                        <label for="rip-payment-date" style="font-size: 0.85rem; font-weight: 600;">Payment Date <span style="color: var(--danger);">*</span></label>
                        <input type="date" id="rip-payment-date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="rip-payment-mode" style="font-size: 0.85rem; font-weight: 600;">Payment Mode <span style="color: var(--danger);">*</span></label>
                        <select id="rip-payment-mode" name="payment_mode" class="form-control" required>
                            <option value="UPI">UPI / QR Code</option>
                            <option value="Bank Transfer">Bank Transfer / NEFT / RTGS</option>
                            <option value="Cash">Cash</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Card">Credit / Debit Card</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="rip-reference-no" style="font-size: 0.85rem; font-weight: 600;">Reference # / UTR / Cheque</label>
                        <input type="text" id="rip-reference-no" name="reference_no" class="form-control" placeholder="e.g. UTR-982182">
                    </div>
                </div>

                <div class="form-group">
                    <label for="rip-bank-account" style="font-size: 0.85rem; font-weight: 600;">Bank / Cash Deposit Account</label>
                    <select id="rip-bank-account" name="bank_account_id" class="form-control">
                        <option value="">-- Primary Operating Account --</option>
                        <?php foreach ($bankAccounts as $ba): ?>
                            <option value="<?= (int)$ba['id'] ?>">
                                <?= e($ba['bank_name']) ?> - <?= e($ba['account_name'] ?: $ba['account_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rip-notes" style="font-size: 0.85rem; font-weight: 600;">Notes / Remarks</label>
                    <textarea id="rip-notes" name="notes" class="form-control" rows="2" placeholder="e.g. Cleared invoice installment"></textarea>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeRecordInvoicePaymentModal()">Cancel</button>
                <button type="submit" id="rip-submit-btn" class="btn btn-success" style="padding: 0.5rem 1.25rem; font-weight: 600;">Record Payment &rarr;</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: View Invoice Payment History -->
<div id="invoice-history-modal" class="modal-backdrop" role="dialog" aria-modal="true" style="display: none;">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title">Payment History: <span id="ih-invoice-number"></span></h3>
            <button type="button" class="modal-close" onclick="window.closeInvoiceHistoryModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="ih-loading" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                Loading payment history...
            </div>
            <div id="ih-content" style="display: none;">
                <div class="table-container">
                    <table class="table" style="font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Receipt #</th>
                                <th>Mode</th>
                                <th>Reference</th>
                                <th>Recorded By</th>
                                <th style="text-align: right;">Amount Paid</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ih-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="window.closeInvoiceHistoryModal()">Close</button>
        </div>
    </div>
</div>

<script>
window.openPaymentModal = function(type) {
    document.getElementById('p-payment-type').value = type;
    var modal = document.getElementById('payment-modal');
    var title = document.getElementById('payment-modal-title');
    var btn = document.getElementById('p-submit-btn');
    var optCust = document.getElementById('opt-customers');
    var optSupp = document.getElementById('opt-suppliers');
    var partyLabel = document.getElementById('p-party-label');

    if (type === 'RECEIPT') {
        title.textContent = 'Record Inward Receipt (Money In)';
        btn.textContent = 'Save Money Receipt';
        btn.className = 'btn btn-primary';
        partyLabel.textContent = 'Received From (Customer)';
        optCust.style.display = 'block';
        optSupp.style.display = 'none';
    } else {
        title.textContent = 'Record Outward Payment (Money Out)';
        btn.textContent = 'Save Payment Voucher';
        btn.className = 'btn btn-danger';
        partyLabel.textContent = 'Paid To (Supplier)';
        optCust.style.display = 'none';
        optSupp.style.display = 'block';
    }

    modal.style.display = 'flex';
};

window.closePaymentModal = function() {
    document.getElementById('payment-modal').style.display = 'none';
};

window.openRecordInvoicePaymentModal = function(id, invoiceNumber, customerName, total, paid, due) {
    document.getElementById('rip-invoice-id').value = id;
    document.getElementById('rip-invoice-number').textContent = invoiceNumber;
    document.getElementById('rip-customer-name').textContent = customerName;
    document.getElementById('rip-invoice-total').textContent = '₹' + parseFloat(total).toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('rip-previously-paid').textContent = '₹' + parseFloat(paid).toLocaleString('en-IN', {minimumFractionDigits: 2});
    
    const balanceDue = Math.max(0, parseFloat(due));
    document.getElementById('rip-outstanding-balance').textContent = '₹' + balanceDue.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('rip-max-hint').textContent = '₹' + balanceDue.toLocaleString('en-IN', {minimumFractionDigits: 2});
    
    const amountInput = document.getElementById('rip-amount');
    amountInput.max = balanceDue.toFixed(2);
    amountInput.value = balanceDue.toFixed(2);
    
    document.getElementById('rip-error-alert').style.display = 'none';
    document.getElementById('rip-error-alert').textContent = '';

    const modal = document.getElementById('record-invoice-payment-modal');
    modal.style.display = 'flex';
    amountInput.focus();
};

window.closeRecordInvoicePaymentModal = function() {
    document.getElementById('record-invoice-payment-modal').style.display = 'none';
};

window.submitInvoicePayment = async function(event) {
    event.preventDefault();
    const form = event.target;
    const btn = document.getElementById('rip-submit-btn');
    const errAlert = document.getElementById('rip-error-alert');
    errAlert.style.display = 'none';

    const invId = document.getElementById('rip-invoice-id').value;
    const amount = parseFloat(document.getElementById('rip-amount').value || 0);
    const maxPayable = parseFloat(document.getElementById('rip-amount').max || 0);

    if (amount <= 0) {
        errAlert.textContent = 'Please enter a valid payment amount greater than zero.';
        errAlert.style.display = 'block';
        return;
    }

    if (maxPayable > 0 && amount > maxPayable + 0.01) {
        errAlert.textContent = 'Payment amount cannot exceed the outstanding balance of ₹' + maxPayable.toFixed(2);
        errAlert.style.display = 'block';
        return;
    }

    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    payload._csrf_token = metaToken;

    btn.disabled = true;
    btn.textContent = 'Recording Payment...';

    try {
        const resp = await fetch('/sales/invoices/' + invId + '/payments', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': metaToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await resp.json();
        if (data.success) {
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show(data.message || 'Payment recorded successfully!', 'success');
            }
            window.closeRecordInvoicePaymentModal();
            setTimeout(() => window.location.reload(), 400);
        } else {
            errAlert.textContent = data.message || 'Failed to record payment.';
            errAlert.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Record Payment →';
        }
    } catch (err) {
        console.error('Payment submission error:', err);
        errAlert.textContent = 'Server connection error. Please try again.';
        errAlert.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Record Payment →';
    }
};

window.viewInvoicePayments = async function(invId, invNumber) {
    document.getElementById('ih-invoice-number').textContent = invNumber;
    document.getElementById('ih-loading').style.display = 'block';
    document.getElementById('ih-content').style.display = 'none';
    document.getElementById('invoice-history-modal').style.display = 'flex';

    try {
        const resp = await fetch('/sales/invoices/' + invId + '/payments', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (data.success) {
            const tbody = document.getElementById('ih-table-body');
            tbody.innerHTML = '';
            const list = data.payments || [];

            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No payment transactions recorded for this invoice.</td></tr>';
            } else {
                list.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${p.payment_date || '-'}</td>
                        <td><strong>${p.receipt_number || p.payment_number || '-'}</strong></td>
                        <td>${p.payment_mode || 'Cash'}</td>
                        <td><code>${p.reference_no || '-'}</code></td>
                        <td style="color: var(--text-muted);">${p.recorded_by_name || 'System'}</td>
                        <td style="text-align: right; font-weight: 700; color: var(--color-success);">₹${parseFloat(p.allocated_amount || p.amount || 0).toFixed(2)}</td>
                        <td style="text-align: center;"><span class="badge badge-success">${p.status || 'COMPLETED'}</span></td>
                        <td style="text-align: right;">
                            <a href="/payments/${p.id}/print" target="_blank" class="btn btn-sm btn-secondary" style="padding: 0.15rem 0.5rem; font-size: 0.75rem;">Print</a>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            document.getElementById('ih-loading').style.display = 'none';
            document.getElementById('ih-content').style.display = 'block';
        }
    } catch (err) {
        console.error('Fetch payments error:', err);
        document.getElementById('ih-loading').textContent = 'Failed to load payment history.';
    }
};

window.closeInvoiceHistoryModal = function() {
    document.getElementById('invoice-history-modal').style.display = 'none';
};
</script>
