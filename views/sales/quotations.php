<?php
/**
 * WTSBill ERP — Sales Quotations & Commercial Estimates View
 *
 * @var array $quotations
 * @var array $summary
 * @var array $customers
 * @var string $activeStatus
 * @var string $search
 */

use WTSBill\Auth\Gate;
use WTSBill\Core\Session;

$csrfToken = Session::getCsrfToken();
$activeStatus = $activeStatus ?? 'ALL';
$search = $search ?? '';
?>
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">Commercial Quotations & Estimates</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.875rem;">Manage client price estimates, proforma bids, validity expirations, and direct one-click conversion to Tax Invoices</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <?php if (Gate::can('quotations.create')): ?>
            <a href="/sales/quotations/create" class="btn btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.45rem 1rem; font-size: 0.85rem; font-weight: 600;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>+ Create Quotation</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Quotations Pipeline Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Active Pipeline</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;">
                <?= format_currency((float)($summary['pipeline_value'] ?? 0)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;"><?= (int)($summary['total_count'] ?? 0) ?> recorded quotation(s)</div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Accepted Quotations</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-success); margin-top: 0.25rem;">
                <?= format_currency((float)($summary['accepted_value'] ?? 0)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;"><?= (int)($summary['accepted_count'] ?? 0) ?> client-approved deal(s)</div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Converted To Invoice</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6; margin-top: 0.25rem;">
                <?= format_currency((float)($summary['converted_value'] ?? 0)) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;"><?= (int)($summary['converted_count'] ?? 0) ?> converted sales order(s)</div>
        </div>
    </div>
</div>

<!-- Search & Status Filter -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1rem 1.25rem;">
        <form method="GET" action="/sales/quotations" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end;">
            <div style="flex: 1; min-width: 220px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search quote #, customer, or ref..." value="<?= e($search) ?>" style="font-size: 0.85rem; padding: 0.4rem 0.65rem;">
            </div>

            <div style="width: 180px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; display: block;">Filter by Status</label>
                <select name="status" class="form-control" style="font-size: 0.85rem; padding: 0.4rem 0.65rem;">
                    <option value="ALL" <?= $activeStatus === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="DRAFT" <?= $activeStatus === 'DRAFT' ? 'selected' : '' ?>>Draft</option>
                    <option value="SENT" <?= $activeStatus === 'SENT' ? 'selected' : '' ?>>Sent</option>
                    <option value="ACCEPTED" <?= $activeStatus === 'ACCEPTED' ? 'selected' : '' ?>>Accepted</option>
                    <option value="CONVERTED" <?= $activeStatus === 'CONVERTED' ? 'selected' : '' ?>>Converted</option>
                    <option value="REJECTED" <?= $activeStatus === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                    <option value="EXPIRED" <?= $activeStatus === 'EXPIRED' ? 'selected' : '' ?>>Expired</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.85rem;">Filter</button>
                <a href="/sales/quotations" class="btn btn-secondary" style="padding: 0.45rem 0.75rem; font-size: 0.85rem;">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Quotations Master Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Quotations Register (<span id="quotations-count"><?= count($quotations ?? []) ?></span> Quotes)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="quote-table">
            <thead>
                <tr>
                    <th>Quote #</th>
                    <th>Customer Name</th>
                    <th>Quote Date</th>
                    <th>Valid Until</th>
                    <th style="text-align: right;">Subtotal</th>
                    <th style="text-align: right;">Total Tax</th>
                    <th style="text-align: right;">Grand Total</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="quote-table-body">
                <?php if (!empty($quotations)): ?>
                    <?php foreach ($quotations as $q): ?>
                        <?php
                            $isConverted = (int)($q['converted_to_invoice'] ?? 0) === 1;
                            $status = strtoupper((string)($q['status'] ?? 'SENT'));
                            $statusBadge = match($status) {
                                'CONVERTED' => 'primary',
                                'ACCEPTED' => 'success',
                                'SENT' => 'info',
                                'DRAFT' => 'secondary',
                                'REJECTED' => 'danger',
                                'EXPIRED' => 'warning',
                                default => 'info'
                            };
                        ?>
                        <tr id="quote-row-<?= (int)$q['id'] ?>">
                            <td>
                                <strong><?= e($q['quotation_number']) ?></strong>
                                <?php if (!empty($q['reference_no'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--text-muted);"><?= e($q['reference_no']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($q['customer_name'] ?: 'Customer #' . $q['customer_id']) ?></td>
                            <td style="font-size: 0.85rem;"><?= e(format_date($q['quotation_date'])) ?></td>
                            <td style="font-size: 0.85rem; color: <?= strtotime($q['valid_until'] ?? '') < time() ? 'var(--color-danger)' : 'var(--text-muted)' ?>;">
                                <?= e(format_date($q['valid_until'] ?? date('Y-m-d', strtotime('+30 days')))) ?>
                            </td>
                            <td style="text-align: right;"><?= format_currency((float)($q['sub_total'] ?? 0)) ?></td>
                            <td style="text-align: right; color: var(--color-info);"><?= format_currency((float)($q['total_tax'] ?? 0)) ?></td>
                            <td style="text-align: right; font-weight: 700;"><?= format_currency((float)($q['grand_total'] ?? 0)) ?></td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= $statusBadge ?>"><?= e($status) ?></span>
                                <?php if ($isConverted && !empty($q['converted_invoice_number'])): ?>
                                    <div style="font-size: 0.72rem; margin-top: 0.2rem; color: #8b5cf6;">
                                        Inv: <strong><?= e($q['converted_invoice_number']) ?></strong>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: flex; gap: 0.25rem; justify-content: flex-end; align-items: center;">
                                    <?php if (Gate::can('quotations.print')): ?>
                                        <a href="/sales/quotations/<?= (int)$q['id'] ?>/print" target="_blank" class="btn btn-sm btn-secondary" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" title="Print Quotation">
                                            Print
                                        </a>
                                    <?php endif; ?>
                                    <?php if (Gate::can('quotations.view')): ?>
                                        <button type="button" class="btn btn-sm btn-secondary" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" onclick="window.viewQuotation(<?= (int)$q['id'] ?>)">
                                            View
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!$isConverted && Gate::can('quotations.edit')): ?>
                                        <a href="/sales/quotations/<?= (int)$q['id'] ?>/edit" class="btn btn-sm btn-primary" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; text-decoration: none;">
                                            Edit
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$isConverted && Gate::can('invoices.create')): ?>
                                        <button type="button" class="btn btn-sm btn-success" style="padding: 0.2rem 0.55rem; font-size: 0.75rem;" onclick="window.convertQuotationToInvoice(<?= (int)$q['id'] ?>, '<?= e(addslashes($q['quotation_number'])) ?>')">
                                            Convert
                                        </button>
                                    <?php endif; ?>
                                    <?php if (Gate::can('quotations.create')): ?>
                                        <button type="button" class="btn btn-sm btn-light" style="padding: 0.2rem 0.45rem; font-size: 0.75rem; border: 1px solid var(--border-color);" title="Duplicate Quotation" onclick="window.duplicateQuotation(<?= (int)$q['id'] ?>, '<?= e(addslashes($q['quotation_number'])) ?>')">
                                            Copy
                                        </button>
                                    <?php endif; ?>
                                    <?php if (Gate::can('quotations.delete')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" style="padding: 0.2rem 0.45rem; font-size: 0.75rem; color: var(--danger); border-color: var(--danger);" title="Delete" onclick="window.deleteQuotation(<?= (int)$q['id'] ?>, '<?= e(addslashes($q['quotation_number'])) ?>')">
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-quote-row">
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                            No commercial quotation records found matching the filter criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Quotation Details View Modal -->
<div id="quotation-view-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="qvm-title" style="display: none;">
    <div class="modal-dialog" style="max-width: 760px;">
        <div class="modal-header">
            <h3 id="qvm-title" class="modal-title">Commercial Quotation Details</h3>
            <button type="button" class="modal-close" onclick="window.closeQuotationViewModal()" aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
            <div id="qvm-loading" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                Loading quotation details...
            </div>
            <div id="qvm-details" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);" id="qvm-number"></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;" id="qvm-dates"></div>
                    </div>
                    <div style="text-align: right;">
                        <span id="qvm-badge" class="badge"></span>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;" id="qvm-total"></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <h4 style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.25rem;">Quoted Customer:</h4>
                        <strong id="qvm-customer"></strong>
                        <div id="qvm-gstin" style="font-size: 0.8rem; color: var(--text-muted); font-family: monospace;"></div>
                        <div id="qvm-address" style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem;"></div>
                    </div>
                    <div>
                        <h4 style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.25rem;">Place of Supply & Ref:</h4>
                        <div id="qvm-pos" style="font-size: 0.85rem;"></div>
                        <div id="qvm-ref" style="font-size: 0.85rem; margin-top: 0.25rem; color: var(--text-muted);"></div>
                    </div>
                </div>

                <h4 style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem;">Line Items</h4>
                <div class="table-container" style="margin-bottom: 1rem;">
                    <table class="table" style="font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th>Item Description</th>
                                <th>Category</th>
                                <th>HSN</th>
                                <th style="text-align: right;">Qty</th>
                                <th style="text-align: right;">Rate</th>
                                <th style="text-align: right;">GST</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="qvm-items-body"></tbody>
                    </table>
                </div>

                <div id="qvm-notes" style="font-size: 0.8rem; color: var(--text-muted); padding: 0.75rem; background: var(--bg-hover); border-radius: var(--radius);"></div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <a id="qvm-print-btn" href="#" target="_blank" class="btn btn-secondary" style="text-decoration: none;">Print Quotation</a>
                <a id="qvm-edit-btn" href="#" class="btn btn-primary" style="text-decoration: none;">Edit Quotation</a>
                <button type="button" id="qvm-convert-btn" class="btn btn-success" onclick="window.convertCurrentModalQuotation()">Convert to Invoice &rarr;</button>
            </div>
            <button type="button" class="btn btn-secondary" onclick="window.closeQuotationViewModal()">Close</button>
        </div>
    </div>
</div>

<script>
    let currentModalQuoteId = 0;
    let currentModalQuoteNumber = '';

    window.viewQuotation = async function(id) {
        currentModalQuoteId = id;
        document.getElementById('qvm-loading').style.display = 'block';
        document.getElementById('qvm-details').style.display = 'none';
        document.getElementById('quotation-view-modal').style.display = 'flex';

        try {
            const resp = await fetch('/sales/quotations/' + id, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();

            if (data.success && data.quotation) {
                const q = data.quotation;
                const items = data.items || [];
                currentModalQuoteNumber = q.quotation_number;

                document.getElementById('qvm-print-btn').href = '/sales/quotations/' + id + '/print';
                const editBtn = document.getElementById('qvm-edit-btn');
                const convertBtn = document.getElementById('qvm-convert-btn');

                const isConverted = parseInt(q.converted_to_invoice) === 1;
                if (isConverted) {
                    editBtn.style.display = 'none';
                    convertBtn.style.display = 'none';
                } else {
                    editBtn.style.display = 'inline-block';
                    editBtn.href = '/sales/quotations/' + id + '/edit';
                    convertBtn.style.display = 'inline-block';
                }

                document.getElementById('qvm-number').textContent = q.quotation_number;
                document.getElementById('qvm-dates').textContent = 'Date: ' + q.quotation_date + ' | Valid Until: ' + (q.valid_until || '-');
                document.getElementById('qvm-total').textContent = '₹' + parseFloat(q.grand_total).toLocaleString('en-IN', {minimumFractionDigits: 2});

                const badge = document.getElementById('qvm-badge');
                badge.className = 'badge badge-' + (isConverted ? 'primary' : (q.status === 'ACCEPTED' ? 'success' : (q.status === 'DRAFT' ? 'secondary' : 'info')));
                badge.textContent = q.status;

                document.getElementById('qvm-customer').textContent = q.customer_name || 'Direct Client';
                document.getElementById('qvm-gstin').textContent = q.customer_gstin ? ('GSTIN: ' + q.customer_gstin) : 'Unregistered';
                document.getElementById('qvm-address').textContent = q.billing_address || '';

                document.getElementById('qvm-pos').textContent = 'Place of Supply: ' + (q.place_of_supply_state_code || '27');
                document.getElementById('qvm-ref').textContent = q.reference_no ? ('Ref: ' + q.reference_no) : '';

                const tbody = document.getElementById('qvm-items-body');
                tbody.innerHTML = '';
                items.forEach(it => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><strong>${it.item_name}</strong></td>
                        <td style="color: var(--text-muted);">${it.category_name || '-'}</td>
                        <td><code>${it.hsn_sac || '-'}</code></td>
                        <td style="text-align: right;">${parseFloat(it.quantity).toFixed(0)}</td>
                        <td style="text-align: right;">₹${parseFloat(it.unit_price).toFixed(2)}</td>
                        <td style="text-align: right;">${parseFloat(it.gst_rate).toFixed(0)}%</td>
                        <td style="text-align: right; font-weight: 600;">₹${parseFloat(it.total_amount).toFixed(2)}</td>
                    `;
                    tbody.appendChild(row);
                });

                document.getElementById('qvm-notes').textContent = q.notes || 'Quotation terms apply.';

                document.getElementById('qvm-loading').style.display = 'none';
                document.getElementById('qvm-details').style.display = 'block';
            } else {
                document.getElementById('qvm-loading').textContent = data.message || 'Failed to load quotation details.';
            }
        } catch (err) {
            console.error('Quotation view error:', err);
            document.getElementById('qvm-loading').textContent = 'Network error while loading quotation.';
        }
    };

    window.closeQuotationViewModal = function() {
        document.getElementById('quotation-view-modal').style.display = 'none';
    };

    window.convertCurrentModalQuotation = function() {
        if (currentModalQuoteId > 0) {
            window.closeQuotationViewModal();
            window.convertQuotationToInvoice(currentModalQuoteId, currentModalQuoteNumber);
        }
    };

    window.convertQuotationToInvoice = async function(id, quoteNumber) {
        if (!confirm(`Convert Quotation ${quoteNumber} to a Tax Invoice? This will generate a sequential Tax Invoice, copy all line items, and mark this quotation as CONVERTED.`)) {
            return;
        }

        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?= e($csrfToken) ?>';

        try {
            const resp = await fetch('/sales/quotations/' + id + '/convert', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ _csrf_token: metaToken })
            });

            const data = await resp.json();
            if (data.success) {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Quotation converted successfully!', 'success');
                }
                setTimeout(() => {
                    if (data.invoice_id) {
                        window.location.href = '/sales/invoices/' + data.invoice_id;
                    } else {
                        window.location.href = '/sales/invoices';
                    }
                }, 500);
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Failed to convert quotation.', 'danger');
                }
            }
        } catch (err) {
            console.error('Convert quotation error:', err);
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('Server error while converting quotation.', 'danger');
            }
        }
    };

    window.duplicateQuotation = async function(id, quoteNumber) {
        if (!confirm(`Create a clone/copy of quotation ${quoteNumber}?`)) {
            return;
        }

        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?= e($csrfToken) ?>';

        try {
            const resp = await fetch('/sales/quotations/' + id + '/duplicate', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ _csrf_token: metaToken })
            });

            const data = await resp.json();
            if (data.success) {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Quotation duplicated successfully!', 'success');
                }
                setTimeout(() => window.location.reload(), 400);
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Failed to duplicate quotation.', 'danger');
                }
            }
        } catch (err) {
            console.error('Duplicate quotation error:', err);
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('Server error while duplicating quotation.', 'danger');
            }
        }
    };

    window.deleteQuotation = async function(id, quoteNumber) {
        if (!confirm(`Are you sure you want to delete quotation ${quoteNumber}?`)) {
            return;
        }

        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?= e($csrfToken) ?>';

        try {
            const resp = await fetch('/sales/quotations/' + id + '/delete', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ _csrf_token: metaToken })
            });

            const data = await resp.json();
            if (data.success) {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Quotation deleted successfully.', 'success');
                }
                const row = document.getElementById('quote-row-' + id);
                if (row) row.remove();
                setTimeout(() => window.location.reload(), 400);
            } else {
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(data.message || 'Failed to delete quotation.', 'danger');
                }
            }
        } catch (err) {
            console.error('Delete quotation error:', err);
            if (window.WTSBill && window.WTSBill.Toast) {
                window.WTSBill.Toast.show('Server error while deleting quotation.', 'danger');
            }
        }
    };
</script>
