<?php
/**
 * WTSBill ERP — Tax Invoices List View & Invoice Inspection Workflow
 *
 * @var array $invoices
 * @var array $bankAccounts
 * @var array $branches
 * @var array $statusCounts
 * @var string $activeStatus
 * @var string $search
 * @var string $fromDate
 * @var string $toDate
 * @var string $selectedBranch
 * @var mixed $minAmount
 * @var mixed $maxAmount
 * @var array $pagination
 */

$currentStatus = $activeStatus ?? 'ALL';
$searchVal = $search ?? '';
$fromVal = $fromDate ?? '';
$toVal = $toDate ?? '';
$branchVal = $selectedBranch ?? 'ALL';
$minVal = $minAmount ?? '';
$maxVal = $maxAmount ?? '';

$buildQuery = function(array $overrides = []) use ($currentStatus, $searchVal, $fromVal, $toVal, $branchVal, $minVal, $maxVal) {
    $params = [
        'status' => $overrides['status'] ?? $currentStatus,
        'search' => $overrides['search'] ?? $searchVal,
        'from_date' => $overrides['from_date'] ?? $fromVal,
        'to_date' => $overrides['to_date'] ?? $toVal,
        'branch_id' => $overrides['branch_id'] ?? $branchVal,
        'min_amount' => $overrides['min_amount'] ?? $minVal,
        'max_amount' => $overrides['max_amount'] ?? $maxVal,
        'page' => $overrides['page'] ?? 1,
    ];
    $clean = [];
    foreach ($params as $k => $v) {
        if ($v !== null && $v !== '' && !($k === 'status' && $v === 'ALL')) {
            $clean[$k] = $v;
        }
    }
    return '/sales/invoices' . (!empty($clean) ? '?' . http_build_query($clean) : '');
};
?>
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700;">Tax Invoices</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.875rem;">Accounts receivable, GST compliance, invoice generation, settlement tracking, and payment status verification</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <?php if (\WTSBill\Auth\Gate::can('invoices.create')): ?>
            <a href="/sales/invoices/create" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600;">+ Create Invoice</a>
        <?php endif; ?>
    </div>
</div>

<!-- Status Pill Tabs (Feature 4.1) -->
<div class="status-pills-bar" style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; align-items: center;">
    <a href="<?= $buildQuery(['status' => 'ALL', 'page' => 1]) ?>" class="btn <?= $currentStatus === 'ALL' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
        <span>All Invoices</span>
        <span class="badge" style="background: rgba(255,255,255,0.25); font-size: 0.75rem; padding: 0.1rem 0.45rem; border-radius: 9999px;"><?= (int)($statusCounts['total'] ?? 0) ?></span>
    </a>
    <a href="<?= $buildQuery(['status' => 'UNPAID', 'page' => 1]) ?>" class="btn <?= ($currentStatus === 'UNPAID' || $currentStatus === 'PENDING') ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
        <span>Pending / Unpaid</span>
        <span class="badge" style="background: rgba(255,255,255,0.25); font-size: 0.75rem; padding: 0.1rem 0.45rem; border-radius: 9999px;"><?= (int)($statusCounts['unpaid'] ?? 0) ?></span>
    </a>
    <a href="<?= $buildQuery(['status' => 'PARTIAL', 'page' => 1]) ?>" class="btn <?= ($currentStatus === 'PARTIAL' || $currentStatus === 'PARTIALLY_PAID') ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
        <span>Partially Paid</span>
        <span class="badge" style="background: rgba(255,255,255,0.25); font-size: 0.75rem; padding: 0.1rem 0.45rem; border-radius: 9999px;"><?= (int)($statusCounts['partial'] ?? 0) ?></span>
    </a>
    <a href="<?= $buildQuery(['status' => 'PAID', 'page' => 1]) ?>" class="btn <?= $currentStatus === 'PAID' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
        <span>Paid</span>
        <span class="badge" style="background: rgba(255,255,255,0.25); font-size: 0.75rem; padding: 0.1rem 0.45rem; border-radius: 9999px;"><?= (int)($statusCounts['paid'] ?? 0) ?></span>
    </a>
    <a href="<?= $buildQuery(['status' => 'OVERDUE', 'page' => 1]) ?>" class="btn <?= $currentStatus === 'OVERDUE' ? 'btn-danger' : 'btn-outline-danger' ?>" style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; <?= $currentStatus !== 'OVERDUE' ? 'border: 1px solid var(--danger, #ef4444); color: var(--danger, #ef4444);' : '' ?>">
        <span>Overdue</span>
        <span class="badge" style="background: rgba(239,68,68,0.2); color: inherit; font-size: 0.75rem; padding: 0.1rem 0.45rem; border-radius: 9999px;"><?= (int)($statusCounts['overdue'] ?? 0) ?></span>
    </a>
</div>

<!-- Multi-Field Filter Bar (Feature 4.1) -->
<div class="card" style="margin-bottom: 1.25rem;">
    <div class="card-body" style="padding: 1rem 1.25rem;">
        <form method="GET" action="/sales/invoices" id="invoiceFilterForm" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end;">
            <input type="hidden" name="status" value="<?= e($currentStatus) ?>">

            <div style="flex: 2; min-width: 200px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">Search Invoices</label>
                <input type="text" id="invoice-search" name="search" class="form-control" placeholder="Search invoice # or customer..." value="<?= e($searchVal) ?>" style="font-size: 0.85rem; padding: 0.4rem 0.65rem;">
            </div>

            <div style="flex: 1; min-width: 140px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">Branch</label>
                <select name="branch_id" class="form-control" style="font-size: 0.85rem; padding: 0.4rem 0.65rem;">
                    <option value="ALL" <?= $branchVal === 'ALL' ? 'selected' : '' ?>>All Branches</option>
                    <?php if (!empty($branches)): ?>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= ((string)$branchVal === (string)$b['id']) ? 'selected' : '' ?>>
                                <?= e($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div style="width: 135px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= e($fromVal) ?>" style="font-size: 0.85rem; padding: 0.4rem 0.5rem;">
            </div>

            <div style="width: 135px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= e($toVal) ?>" style="font-size: 0.85rem; padding: 0.4rem 0.5rem;">
            </div>

            <div style="width: 105px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">Min Amount</label>
                <input type="number" step="0.01" name="min_amount" class="form-control" placeholder="₹ Min" value="<?= e((string)$minVal) ?>" style="font-size: 0.85rem; padding: 0.4rem 0.5rem;">
            </div>

            <div style="width: 105px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">Max Amount</label>
                <input type="number" step="0.01" name="max_amount" class="form-control" placeholder="₹ Max" value="<?= e((string)$maxVal) ?>" style="font-size: 0.85rem; padding: 0.4rem 0.5rem;">
            </div>

            <div style="display: flex; gap: 0.4rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.42rem 1rem; font-size: 0.85rem; font-weight: 600;">Filter</button>
                <a href="/sales/invoices" class="btn btn-secondary" style="padding: 0.42rem 0.8rem; font-size: 0.85rem; text-decoration: none;">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="margin: 0;">Tax Invoices List (<span id="invoices-count"><?= (int)($pagination['total'] ?? count($invoices ?? [])) ?></span> Invoices Found)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="invoices-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer Name</th>
                    <th>Date</th>
                    <th style="text-align: right;">Total (INR)</th>
                    <th style="text-align: right;">Due Balance</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="invoices-table-body">
                <?php if (!empty($invoices)): ?>
                    <?php foreach ($invoices as $inv): ?>
                        <?php
                            $isPaid = $inv['status'] === 'PAID';
                            $isPartial = $inv['status'] === 'PARTIAL';
                            $isOverdue = !$isPaid && !empty($inv['due_date']) && (strtotime($inv['due_date']) < strtotime(date('Y-m-d')));
                        ?>
                        <tr id="invoice-row-<?= (int)$inv['id'] ?>">
                            <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                            <td>
                                <?php if (!empty($inv['customer_id'])): ?>
                                    <a href="/customers/<?= (int)$inv['customer_id'] ?>" style="color: inherit; text-decoration: underline; font-weight: 500;">
                                        <?= e($inv['customer_name'] ?: 'Customer #' . $inv['customer_id']) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e($inv['customer_name'] ?: 'Direct Customer') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e(format_date($inv['invoice_date'])) ?></td>
                            <td style="text-align: right; font-weight: 600;"><?= format_currency((float)$inv['grand_total']) ?></td>
                            <td style="text-align: right; color: <?= (float)($inv['amount_due'] ?? 0) > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>;">
                                <?= format_currency((float)($inv['amount_due'] ?? 0)) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($isPaid): ?>
                                    <span class="badge badge-success">Paid</span>
                                <?php elseif ($isOverdue): ?>
                                    <span class="badge badge-danger">Overdue</span>
                                    <?php if ($isPartial): ?>
                                        <div style="font-size: 0.7rem; color: var(--color-warning, #f59e0b); font-weight: 600; margin-top: 2px;">Partially Paid</div>
                                    <?php endif; ?>
                                <?php elseif ($isPartial): ?>
                                    <span class="badge badge-warning">Partially Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if (\WTSBill\Auth\Gate::can('payments.create') && (float)($inv['amount_due'] ?? 0) > 0.001): ?>
                                    <button type="button" class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; margin-right: 3px;" onclick="window.openRecordPaymentModal(<?= (int)$inv['id'] ?>, '<?= e(addslashes($inv['invoice_number'])) ?>', '<?= e(addslashes($inv['customer_name'] ?: 'Customer #' . $inv['customer_id'])) ?>', <?= (float)$inv['grand_total'] ?>, <?= (float)($inv['amount_paid'] ?? 0) ?>, <?= (float)($inv['amount_due'] ?? 0) ?>)">+ Pay</button>
                                <?php endif; ?>
                                <?php if (\WTSBill\Auth\Gate::can('invoices.print')): ?>
                                    <a href="/sales/invoices/<?= (int)$inv['id'] ?>/print" target="_blank" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; text-decoration: none; margin-right: 3px;">Print</a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; margin-right: 3px;" onclick="window.openShareModal('INVOICE', <?= (int)$inv['id'] ?>, '<?= e(addslashes($inv['invoice_number'])) ?>', '<?= e(addslashes($inv['customer_name'] ?: 'Customer #' . $inv['customer_id'])) ?>', '<?= e(addslashes($inv['customer_phone'] ?? '')) ?>', '<?= e(addslashes($inv['customer_email'] ?? '')) ?>', <?= (float)$inv['grand_total'] ?>)">Share</button>
                                <?php if (\WTSBill\Auth\Gate::can('invoices.view')): ?>
                                    <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; margin-right: 3px;" onclick="window.viewInvoice(<?= (int)$inv['id'] ?>)">View</button>
                                <?php endif; ?>
                                <?php if (\WTSBill\Auth\Gate::can('invoices.edit')): ?>
                                    <a href="/sales/invoices/<?= (int)$inv['id'] ?>/edit" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; text-decoration: none; margin-right: 3px;">Edit</a>
                                <?php endif; ?>
                                <?php if (\WTSBill\Auth\Gate::can('invoices.delete')): ?>
                                    <button type="button" class="btn btn-outline-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: var(--danger); border-color: var(--danger);" onclick="window.deleteInvoice(<?= (int)$inv['id'] ?>, '<?= e(addslashes($inv['invoice_number'])) ?>')">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-invoices-row">
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No invoice records found matching the filter criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1.25rem; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.5rem;">
        <div style="font-size: 0.85rem; color: var(--text-muted);">
            Showing <strong><?= count($invoices ?? []) ?></strong> of <strong><?= (int)($pagination['total'] ?? 0) ?></strong> invoices (Page <?= (int)($pagination['page'] ?? 1) ?> of <?= (int)($pagination['total_pages'] ?? 1) ?>)
        </div>
        <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
            <div style="display: flex; gap: 0.25rem; align-items: center;">
                <?php if (($pagination['page'] ?? 1) > 1): ?>
                    <a href="<?= $buildQuery(['page' => $pagination['page'] - 1]) ?>" class="btn btn-sm btn-secondary">&laquo; Prev</a>
                <?php endif; ?>
                <span style="font-size: 0.85rem; padding: 0.25rem 0.5rem; color: var(--text-primary); font-weight: 600;">
                    <?= (int)$pagination['page'] ?> / <?= (int)$pagination['total_pages'] ?>
                </span>
                <?php if (($pagination['page'] ?? 1) < ($pagination['total_pages'] ?? 1)): ?>
                    <a href="<?= $buildQuery(['page' => $pagination['page'] + 1]) ?>" class="btn btn-sm btn-secondary">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="invoice-view-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="inv-modal-title">
    <div class="modal-dialog" style="max-width: 760px;">
        <div class="modal-header">
            <h3 id="inv-modal-title" class="modal-title">Tax Invoice Details</h3>
            <button type="button" class="modal-close" onclick="window.closeInvoiceModal()" aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
            <div id="inv-loading" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                Loading invoice details...
            </div>
            <div id="inv-details" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);" id="inv-view-number"></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;" id="inv-view-dates"></div>
                    </div>
                    <div style="text-align: right;">
                        <span id="inv-view-badge" class="badge"></span>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;" id="inv-view-total"></div>
                        <div style="font-size: 0.82rem; color: var(--color-success); margin-top: 0.2rem;">Paid: <span id="inv-view-paid">₹0.00</span></div>
                        <div style="font-size: 0.82rem; color: var(--color-danger); font-weight: 600;">Due: <span id="inv-view-due">₹0.00</span></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <h4 style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.25rem;">Billed To:</h4>
                        <strong id="inv-view-customer"></strong>
                        <div id="inv-view-gstin" style="font-size: 0.8rem; color: var(--text-muted); font-family: monospace;"></div>
                        <div id="inv-view-address" style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem;"></div>
                    </div>
                    <div>
                        <h4 style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.25rem;">Tax & Place of Supply:</h4>
                        <div id="inv-view-pos" style="font-size: 0.85rem;"></div>
                        <div id="inv-view-tax-breakdown" style="font-size: 0.85rem; margin-top: 0.25rem;"></div>
                    </div>
                </div>

                <h4 style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem;">Line Items</h4>
                <div class="table-container" style="margin-bottom: 1rem;">
                    <table class="table" style="font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th>Item Description</th>
                                <th>HSN</th>
                                <th style="text-align: right;">Qty</th>
                                <th style="text-align: right;">Rate</th>
                                <th style="text-align: right;">Taxable</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="inv-items-table-body"></tbody>
                    </table>
                </div>

                <!-- Prominent Payment Summary & Dynamic Remaining Balance QR Code -->
                <div id="inv-payment-summary-card" style="margin-top: 1.25rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg, 8px); padding: 1.25rem; box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.05));">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.6rem;">
                        <div>
                            <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin: 0;">Payment &amp; Reconciliation Summary</h4>
                            <span style="font-size: 0.78rem; color: var(--text-muted);">Real-time settlement status &amp; dynamic remaining balance UPI QR</span>
                        </div>
                        <span id="inv-summary-pay-badge" class="badge"></span>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div style="background: var(--bg-hover); padding: 0.75rem 1rem; border-radius: var(--radius); border-left: 3px solid var(--color-primary);">
                            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Invoice Total</div>
                            <div id="inv-summary-total" style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-top: 0.25rem;">₹0.00</div>
                        </div>
                        <div style="background: var(--bg-hover); padding: 0.75rem 1rem; border-radius: var(--radius); border-left: 3px solid var(--color-success);">
                            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Total Paid</div>
                            <div id="inv-summary-paid" style="font-size: 1.15rem; font-weight: 700; color: var(--color-success); margin-top: 0.25rem;">₹0.00</div>
                        </div>
                        <div style="background: var(--bg-hover); padding: 0.75rem 1rem; border-radius: var(--radius); border-left: 3px solid var(--color-danger);">
                            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Outstanding Balance</div>
                            <div id="inv-summary-due" style="font-size: 1.25rem; font-weight: 800; color: var(--color-danger); margin-top: 0.25rem;">₹0.00</div>
                        </div>
                    </div>

                    <!-- Remaining Balance Dynamic UPI QR Container -->
                    <div id="inv-summary-qr-container" style="display: flex; gap: 1.25rem; align-items: center; background: #0f172a; border: 1px solid #334155; border-radius: var(--radius); padding: 1rem; color: #f8fafc;">
                        <div id="inv-summary-qr-svg" style="background: #ffffff; padding: 6px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; min-width: 110px; min-height: 110px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);">
                            <!-- QR SVG injected here -->
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <span class="badge" style="background: #0284c7; color: #ffffff; font-size: 0.7rem; padding: 2px 8px;">DYNAMIC UPI QR</span>
                                <span id="inv-summary-qr-amount-badge" style="font-size: 1.1rem; font-weight: 700; color: #38bdf8;">₹0.00</span>
                            </div>
                            <p style="margin: 0.25rem 0 0.4rem 0; font-size: 0.85rem; color: #cbd5e1; line-height: 1.4;" id="inv-summary-qr-instruction">
                                Scan this QR code using any supported UPI application to pay the outstanding invoice balance.
                            </p>
                            <div style="font-size: 0.78rem; color: #94a3b8; display: flex; flex-direction: column; gap: 0.2rem;">
                                <div>Merchant UPI ID: <strong id="inv-summary-merchant-vpa" style="color: #e2e8f0; font-family: monospace;">-</strong></div>
                                <div>Invoice Reference: <strong id="inv-summary-invoice-ref" style="color: #e2e8f0; font-family: monospace;">-</strong></div>
                            </div>
                        </div>
                    </div>

                    <!-- Paid in Full Container (Hidden when balance > 0) -->
                    <div id="inv-summary-paid-full" style="display: none; text-align: center; padding: 1.25rem; background: #064e3b; border: 1px solid #059669; border-radius: var(--radius); color: #ecfdf5;">
                        <div style="font-size: 1.5rem; margin-bottom: 0.2rem; color: #34d399;">✓</div>
                        <h4 style="margin: 0 0 0.25rem 0; font-size: 1.05rem; font-weight: 700; color: #34d399;">Invoice Fully Paid &amp; Settled</h4>
                        <p style="margin: 0; font-size: 0.82rem; color: #a7f3d0;">No outstanding balance remains (₹0.00 due). Dynamic UPI QR is deactivated.</p>
                    </div>

                    <!-- Merchant UPI Missing Warning Container -->
                    <div id="inv-summary-upi-missing" style="display: none; padding: 1rem 1.25rem; background: #451a03; border: 1px solid #78350f; border-radius: var(--radius); color: #fef3c7;">
                        <div style="font-weight: 600; font-size: 0.9rem; margin-bottom: 0.25rem;">⚠️ Merchant UPI Not Configured</div>
                        <div style="font-size: 0.82rem; color: #fde68a;">A merchant UPI ID is required to generate dynamic payment QR codes. Please configure your UPI ID in <a href="/settings" style="color: #38bdf8; text-decoration: underline;">Settings &rarr; Company Profile</a>.</div>
                    </div>
                </div>

                <!-- Payment Installments & History Section -->
                <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <h4 style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin: 0;">Payment History & Installments</h4>
                        <span id="inv-view-installments-count" class="badge badge-info" style="font-size: 0.75rem;"></span>
                    </div>
                    <div class="table-container" style="margin-bottom: 1rem;">
                        <table class="table" style="font-size: 0.82rem;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Receipt #</th>
                                    <th>Mode</th>
                                    <th>Reference</th>
                                    <th>Recorded By</th>
                                    <th style="text-align: right;">Amount Paid</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="text-align: right;">Receipt</th>
                                </tr>
                            </thead>
                            <tbody id="inv-payments-table-body">
                                <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 1rem;">No payment installments recorded.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="inv-view-notes" style="font-size: 0.8rem; color: var(--text-muted); padding: 0.75rem; background: var(--bg-hover); border-radius: var(--radius);"></div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <?php if (\WTSBill\Auth\Gate::can('payments.create')): ?>
                    <button type="button" id="inv-modal-record-pay-btn" class="btn btn-success" style="display: none;" onclick="window.openPaymentFromViewModal()">+ Record Payment</button>
                <?php endif; ?>
                <a id="inv-modal-download-pdf-btn" href="#" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none;" download>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Download PDF
                </a>
                <button type="button" id="inv-modal-share-btn" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 4px;" onclick="window.openCurrentModalShare()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                    Share
                </button>
                <?php if (\WTSBill\Auth\Gate::can('invoices.print')): ?>
                    <a id="inv-modal-print-btn" href="#" target="_blank" class="btn btn-secondary" style="text-decoration: none;"><i class="icon">🖨️</i> Print</a>
                <?php endif; ?>
                <?php if (\WTSBill\Auth\Gate::can('invoices.edit')): ?>
                    <a id="inv-modal-edit-btn" href="#" class="btn btn-primary" style="text-decoration: none;">Edit Invoice</a>
                <?php endif; ?>
                <?php if (\WTSBill\Auth\Gate::can('invoices.delete')): ?>
                    <button type="button" id="inv-modal-delete-btn" class="btn btn-outline-danger" style="color: var(--danger); border-color: var(--danger);" onclick="window.deleteCurrentModalInvoice()">Delete Invoice</button>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-secondary" onclick="window.closeInvoiceModal()">Close</button>
        </div>
    </div>
</div>

<!-- Working Record Payment Modal -->
<div id="record-payment-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="rpm-title" style="display: none;">
    <div class="modal-dialog" style="max-width: 540px;">
        <div class="modal-header">
            <h3 id="rpm-title" class="modal-title">Record Invoice Payment</h3>
            <button type="button" class="modal-close" onclick="window.closeRecordPaymentModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="record-payment-form" onsubmit="window.submitRecordPayment(event)">
            <input type="hidden" id="rpm-invoice-id" name="invoice_id" value="">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Read-Only Invoice Summary Bar -->
                <div style="background: var(--bg-hover); padding: 0.85rem 1rem; border-radius: var(--radius); border: 1px solid var(--border-color); display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.85rem;">
                    <div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Invoice #</div>
                        <strong id="rpm-invoice-number" style="color: var(--text-primary); font-size: 0.95rem;">-</strong>
                    </div>
                    <div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Customer</div>
                        <strong id="rpm-customer-name" style="color: var(--text-primary); font-size: 0.95rem;">-</strong>
                    </div>
                    <div style="margin-top: 0.35rem;">
                        <div style="color: var(--text-muted); font-size: 0.75rem;">Invoice Total: <strong id="rpm-invoice-total">₹0.00</strong></div>
                        <div style="color: var(--color-success); font-size: 0.75rem;">Previously Paid: <strong id="rpm-previously-paid">₹0.00</strong></div>
                    </div>
                    <div style="margin-top: 0.35rem; text-align: right;">
                        <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Outstanding Due</div>
                        <div id="rpm-outstanding-balance" style="font-size: 1.15rem; font-weight: 700; color: var(--color-danger);">₹0.00</div>
                    </div>
                </div>

                <div id="rpm-error-alert" class="alert alert-danger" style="display: none; padding: 0.6rem 0.85rem; font-size: 0.85rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="rpm-amount" style="font-size: 0.85rem; font-weight: 600;">Payment Amount (₹) <span style="color: var(--danger);">*</span></label>
                        <input type="number" step="0.01" min="0.01" id="rpm-amount" name="amount" class="form-control" placeholder="0.00" required autofocus style="font-weight: 700; font-size: 1rem;">
                        <small style="color: var(--text-muted); font-size: 0.72rem;">Max payable: <span id="rpm-max-hint">₹0.00</span></small>
                    </div>
                    <div class="form-group">
                        <label for="rpm-payment-date" style="font-size: 0.85rem; font-weight: 600;">Payment Date <span style="color: var(--danger);">*</span></label>
                        <input type="date" id="rpm-payment-date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="rpm-payment-mode" style="font-size: 0.85rem; font-weight: 600;">Payment Mode <span style="color: var(--danger);">*</span></label>
                        <select id="rpm-payment-mode" name="payment_mode" class="form-control" required>
                            <option value="UPI">UPI / QR Code</option>
                            <option value="Bank Transfer">Bank Transfer / NEFT / RTGS</option>
                            <option value="Cash">Cash</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Card">Credit / Debit Card</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="rpm-reference-no" style="font-size: 0.85rem; font-weight: 600;">Transaction Ref / UTR / Cheque #</label>
                        <input type="text" id="rpm-reference-no" name="reference_no" class="form-control" placeholder="e.g. UTR-982182 or CHQ-0012">
                    </div>
                </div>

                <div class="form-group">
                    <label for="rpm-bank-account" style="font-size: 0.85rem; font-weight: 600;">Bank / Cash Deposit Account</label>
                    <select id="rpm-bank-account" name="bank_account_id" class="form-control">
                        <option value="">-- Primary Operating Account --</option>
                        <?php foreach ($bankAccounts ?? [] as $ba): ?>
                            <option value="<?= (int)$ba['id'] ?>">
                                <?= e($ba['bank_name']) ?> - <?= e($ba['account_name'] ?: $ba['account_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rpm-notes" style="font-size: 0.85rem; font-weight: 600;">Notes / Remarks</label>
                    <textarea id="rpm-notes" name="notes" class="form-control" rows="2" placeholder="e.g. Cleared installment via Bank Transfer"></textarea>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeRecordPaymentModal()">Cancel</button>
                <button type="submit" id="rpm-submit-btn" class="btn btn-success" style="padding: 0.5rem 1.25rem; font-weight: 600;">Record Payment &rarr;</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentModalInvoiceId = 0;
    let currentModalInvoiceNumber = '';

    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('invoice-search', 'invoices-table');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('invoice-view-modal');
                if (modal && modal.classList.contains('active')) {
                    window.closeInvoiceModal();
                }
            }
        });
    });

    window.viewInvoice = async function(id) {
        currentModalInvoiceId = id;
        document.getElementById('inv-loading').style.display = 'block';
        document.getElementById('inv-details').style.display = 'none';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('invoice-view-modal');
        } else {
            document.getElementById('invoice-view-modal').classList.add('active');
        }

        try {
            const resp = await fetch('/sales/invoices/' + id, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();

            if (data.success && data.invoice) {
                const inv = data.invoice;
                const items = data.items || [];
                currentModalInvoiceNumber = inv.invoice_number;

                const printBtn = document.getElementById('inv-modal-print-btn');
                if (printBtn) printBtn.href = '/sales/invoices/' + id + '/print';

                const dlBtn = document.getElementById('inv-modal-download-pdf-btn');
                if (dlBtn) dlBtn.href = '/sales/invoices/' + id + '/pdf?download=1';

                window.currentModalInvoiceFull = inv;

                const editBtn = document.getElementById('inv-modal-edit-btn');
                if (editBtn) editBtn.href = '/sales/invoices/' + id + '/edit';

                document.getElementById('inv-view-number').textContent = inv.invoice_number;
                document.getElementById('inv-view-dates').textContent = 'Date: ' + inv.invoice_date + ' | Due: ' + (inv.due_date || 'Immediate');
                document.getElementById('inv-view-total').textContent = '₹' + parseFloat(inv.grand_total).toLocaleString('en-IN', {minimumFractionDigits: 2});
                
                const grandTotal = parseFloat(inv.grand_total || 0);
                const amountPaid = parseFloat(inv.amount_paid || 0);
                const amountDue = parseFloat(inv.amount_due !== undefined ? inv.amount_due : (grandTotal - amountPaid));
                
                document.getElementById('inv-view-paid').textContent = '₹' + amountPaid.toLocaleString('en-IN', {minimumFractionDigits: 2});
                document.getElementById('inv-view-due').textContent = '₹' + (amountDue > 0 ? amountDue.toLocaleString('en-IN', {minimumFractionDigits: 2}) : '0.00');

                const payBtn = document.getElementById('inv-modal-record-pay-btn');
                if (payBtn) {
                    if (amountDue > 0.001) {
                        payBtn.style.display = 'inline-block';
                        window.currentModalInvoiceData = {
                            id: inv.id,
                            invoice_number: inv.invoice_number,
                            customer_name: inv.customer_name || 'Direct Customer',
                            grand_total: grandTotal,
                            amount_paid: amountPaid,
                            amount_due: amountDue
                        };
                    } else {
                        payBtn.style.display = 'none';
                        window.currentModalInvoiceData = null;
                    }
                }

                // Dynamic Payment Summary & Remaining Balance QR Code
                const sumTotal = document.getElementById('inv-summary-total');
                if (sumTotal) sumTotal.textContent = '₹' + grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2});
                const sumPaid = document.getElementById('inv-summary-paid');
                if (sumPaid) sumPaid.textContent = '₹' + amountPaid.toLocaleString('en-IN', {minimumFractionDigits: 2});
                const sumDue = document.getElementById('inv-summary-due');
                if (sumDue) sumDue.textContent = '₹' + (amountDue > 0 ? amountDue.toLocaleString('en-IN', {minimumFractionDigits: 2}) : '0.00');

                const sumBadge = document.getElementById('inv-summary-pay-badge');
                if (sumBadge) {
                    sumBadge.className = 'badge badge-' + (amountDue <= 0.001 ? 'success' : (amountPaid > 0.001 ? 'warning' : 'danger'));
                    sumBadge.textContent = amountDue <= 0.001 ? 'PAID' : (amountPaid > 0.001 ? 'PARTIALLY PAID' : 'UNPAID');
                }

                const qrContainer = document.getElementById('inv-summary-qr-container');
                const paidContainer = document.getElementById('inv-summary-paid-full');
                const upiMissing = document.getElementById('inv-summary-upi-missing');

                if (amountDue <= 0.001) {
                    if (qrContainer) qrContainer.style.display = 'none';
                    if (upiMissing) upiMissing.style.display = 'none';
                    if (paidContainer) paidContainer.style.display = 'block';
                } else if (!data.has_merchant_upi) {
                    if (qrContainer) qrContainer.style.display = 'none';
                    if (paidContainer) paidContainer.style.display = 'none';
                    if (upiMissing) upiMissing.style.display = 'block';
                } else {
                    if (paidContainer) paidContainer.style.display = 'none';
                    if (upiMissing) upiMissing.style.display = 'none';
                    if (qrContainer) {
                        qrContainer.style.display = 'flex';
                        const qrSvgBox = document.getElementById('inv-summary-qr-svg');
                        if (qrSvgBox && data.qr_svg) qrSvgBox.innerHTML = data.qr_svg;
                        const qrAmtBadge = document.getElementById('inv-summary-qr-amount-badge');
                        if (qrAmtBadge) qrAmtBadge.textContent = '₹' + amountDue.toLocaleString('en-IN', {minimumFractionDigits: 2});
                        const vpaElem = document.getElementById('inv-summary-merchant-vpa');
                        if (vpaElem) vpaElem.textContent = data.merchant_upi || '-';
                        const refElem = document.getElementById('inv-summary-invoice-ref');
                        if (refElem) refElem.textContent = inv.invoice_number;
                        const instrElem = document.getElementById('inv-summary-qr-instruction');
                        if (instrElem && data.payment_instructions) instrElem.textContent = data.payment_instructions;
                    }
                }

                const badge = document.getElementById('inv-view-badge');
                badge.className = 'badge badge-' + (inv.status === 'PAID' ? 'success' : (inv.status === 'PARTIAL' ? 'warning' : 'danger'));
                badge.textContent = inv.status;

                document.getElementById('inv-view-customer').textContent = inv.customer_name || 'Direct Customer';
                document.getElementById('inv-view-gstin').textContent = inv.customer_gstin ? ('GSTIN: ' + inv.customer_gstin) : 'Unregistered';
                document.getElementById('inv-view-address').textContent = inv.billing_address || '';

                const isIgst = parseInt(inv.is_igst) === 1;
                document.getElementById('inv-view-pos').textContent = 'Place of Supply: ' + (inv.place_of_supply || '27') + (isIgst ? ' (Inter-state IGST)' : ' (Intra-state CGST + SGST)');
                document.getElementById('inv-view-tax-breakdown').textContent = isIgst ? ('IGST: ₹' + parseFloat(inv.igst_amount || 0).toFixed(2)) : ('CGST: ₹' + parseFloat(inv.cgst_amount || 0).toFixed(2) + ' | SGST: ₹' + parseFloat(inv.sgst_amount || 0).toFixed(2));

                const tbody = document.getElementById('inv-items-table-body');
                tbody.innerHTML = '';
                items.forEach(it => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><strong>${it.item_name}</strong></td>
                        <td><code>${it.hsn_sac || '-'}</code></td>
                        <td style="text-align: right;">${parseFloat(it.quantity).toFixed(0)} ${it.unit || ''}</td>
                        <td style="text-align: right;">₹${parseFloat(it.unit_price).toFixed(2)}</td>
                        <td style="text-align: right;">₹${parseFloat(it.taxable_value).toFixed(2)}</td>
                        <td style="text-align: right; font-weight: 600;">₹${parseFloat(it.total_amount).toFixed(2)}</td>
                    `;
                    tbody.appendChild(row);
                });

                // Render payment history installments
                const payments = data.payments || [];
                const ptbody = document.getElementById('inv-payments-table-body');
                ptbody.innerHTML = '';
                const histBadge = document.getElementById('inv-view-installments-count');
                if (histBadge) {
                    histBadge.textContent = payments.length + (payments.length === 1 ? ' Installment' : ' Installments');
                }

                if (payments.length > 0) {
                    payments.forEach(p => {
                        const pr = document.createElement('tr');
                        pr.innerHTML = `
                            <td>${p.payment_date || '-'}</td>
                            <td><strong>${p.receipt_number || p.payment_number || '-'}</strong></td>
                            <td>${p.payment_mode || 'Cash'}</td>
                            <td><code>${p.reference_no || '-'}</code></td>
                            <td style="color: var(--text-muted);">${p.recorded_by_name || 'System'}</td>
                            <td style="text-align: right; font-weight: 600; color: var(--color-success);">₹${parseFloat(p.allocated_amount || p.amount || 0).toFixed(2)}</td>
                            <td style="text-align: center;"><span class="badge badge-success">${p.status || 'COMPLETED'}</span></td>
                            <td style="text-align: right;">
                                <a href="/payments/${p.id}/print" target="_blank" class="btn btn-sm btn-secondary" style="padding: 0.15rem 0.45rem; font-size: 0.75rem; text-decoration: none;">Print</a>
                            </td>
                        `;
                        ptbody.appendChild(pr);
                    });
                } else {
                    ptbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 1rem;">No payment installments recorded yet.</td></tr>';
                }

                document.getElementById('inv-view-notes').textContent = inv.notes || 'Standard terms & conditions apply.';

                document.getElementById('inv-loading').style.display = 'none';
                document.getElementById('inv-details').style.display = 'block';
            } else {
                document.getElementById('inv-loading').textContent = data.message || 'Failed to load invoice details.';
            }
        } catch (err) {
            console.error('Invoice view error:', err);
            document.getElementById('inv-loading').textContent = 'Network or server error while loading invoice.';
        }
    };

    window.sendCurrentModalWhatsAppPdf = function() {
        if (!window.currentModalInvoiceFull) return;
        const inv = window.currentModalInvoiceFull;
        window.sendDirectWhatsAppPdf('INVOICE', inv.id, inv.invoice_number, inv.customer_name || 'Customer', inv.customer_phone || '');
    };

    window.openCurrentModalShare = function() {
        if (!window.currentModalInvoiceFull) return;
        const inv = window.currentModalInvoiceFull;
        window.openShareModal('INVOICE', inv.id, inv.invoice_number, inv.customer_name || 'Customer', inv.customer_phone || '', inv.customer_email || '', inv.grand_total);
    };

    window.closeInvoiceModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('invoice-view-modal');
        } else {
            document.getElementById('invoice-view-modal').classList.remove('active');
        }
    };

    window.deleteCurrentModalInvoice = function() {
        if (currentModalInvoiceId > 0) {
            window.closeInvoiceModal();
            window.deleteInvoice(currentModalInvoiceId, currentModalInvoiceNumber);
        }
    };

    window.deleteInvoice = function(id, invoiceNumber) {
        if (!confirm('Are you sure you want to delete invoice ' + (invoiceNumber || '#' + id) + '? This will cancel the invoice and roll back the customer ledger balance.')) {
            return;
        }

        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        fetch('/sales/invoices/' + id + '/delete', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': metaToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf_token: metaToken })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Invoice deleted successfully.', 'success');
                }
                const row = document.getElementById('invoice-row-' + id);
                if (row) row.remove();
                setTimeout(() => window.location.reload(), 400);
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Failed to delete invoice.', 'danger');
                }
            }
        })
        .catch(err => {
            console.error('Delete invoice error:', err);
            // Fallback to standard form submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/sales/invoices/' + id + '/delete';
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_csrf_token';
            csrfInput.value = metaToken;
            form.appendChild(csrfInput);
            document.body.appendChild(form);
            form.submit();
        });
    };

    window.openPaymentFromViewModal = function() {
        if (window.currentModalInvoiceData) {
            window.closeInvoiceModal();
            const d = window.currentModalInvoiceData;
            window.openRecordPaymentModal(d.id, d.invoice_number, d.customer_name, d.grand_total, d.amount_paid, d.amount_due);
        }
    };

    window.openRecordPaymentModal = function(id, invoiceNumber, customerName, total, paid, due) {
        document.getElementById('rpm-invoice-id').value = id;
        document.getElementById('rpm-invoice-number').textContent = invoiceNumber;
        document.getElementById('rpm-customer-name').textContent = customerName;
        document.getElementById('rpm-invoice-total').textContent = '₹' + parseFloat(total).toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('rpm-previously-paid').textContent = '₹' + parseFloat(paid).toLocaleString('en-IN', {minimumFractionDigits: 2});
        
        const balanceDue = Math.max(0, parseFloat(due));
        document.getElementById('rpm-outstanding-balance').textContent = '₹' + balanceDue.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('rpm-max-hint').textContent = '₹' + balanceDue.toLocaleString('en-IN', {minimumFractionDigits: 2});
        
        const amountInput = document.getElementById('rpm-amount');
        amountInput.max = balanceDue.toFixed(2);
        amountInput.value = balanceDue.toFixed(2);
        
        document.getElementById('rpm-error-alert').style.display = 'none';
        document.getElementById('rpm-error-alert').textContent = '';

        const modal = document.getElementById('record-payment-modal');
        if (modal) {
            modal.style.display = 'flex';
            amountInput.focus();
        }
    };

    window.closeRecordPaymentModal = function() {
        const modal = document.getElementById('record-payment-modal');
        if (modal) modal.style.display = 'none';
    };

    window.submitRecordPayment = async function(event) {
        event.preventDefault();
        const form = event.target;
        const btn = document.getElementById('rpm-submit-btn');
        const errAlert = document.getElementById('rpm-error-alert');
        errAlert.style.display = 'none';

        const invId = document.getElementById('rpm-invoice-id').value;
        const amount = parseFloat(document.getElementById('rpm-amount').value || 0);
        const maxPayable = parseFloat(document.getElementById('rpm-amount').max || 0);

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
                window.closeRecordPaymentModal();
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
</script>

<?php include \WTSBill\Core\View::resolvePath('partials/share_modal'); ?>
