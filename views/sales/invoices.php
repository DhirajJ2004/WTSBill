<?php
/**
 * WTSBill ERP — Tax Invoices List View & Invoice Inspection Workflow
 *
 * @var array $invoices
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Tax Invoices</h1>
        <p class="page-subtitle">Accounts receivable, GST compliance, invoice generation, and settlement tracking</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="invoice-search" class="form-control" placeholder="Search invoice number or client..." style="width: 250px; padding: 0.4rem 0.75rem; font-size: 0.85rem;">
        <a href="/sales/invoices/create" class="btn btn-primary">+ Create Invoice</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Generated Sales Invoices (<span id="invoices-count"><?= count($invoices ?? []) ?></span> Invoices)</h3>
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
                        <tr id="invoice-row-<?= (int)$inv['id'] ?>">
                            <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                            <td><?= e($inv['customer_name'] ?: 'Customer #' . $inv['customer_id']) ?></td>
                            <td><?= e(format_date($inv['invoice_date'])) ?></td>
                            <td style="text-align: right; font-weight: 600;"><?= format_currency((float)$inv['grand_total']) ?></td>
                            <td style="text-align: right; color: <?= (float)($inv['amount_due'] ?? 0) > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>;">
                                <?= format_currency((float)($inv['amount_due'] ?? 0)) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= $inv['status'] === 'PAID' ? 'success' : ($inv['status'] === 'PARTIAL' ? 'warning' : 'danger') ?>">
                                    <?= e($inv['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="window.viewInvoice(<?= (int)$inv['id'] ?>)">View</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-invoices-row">
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No invoice records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Invoice Details View Modal -->
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

                <div id="inv-view-notes" style="font-size: 0.8rem; color: var(--text-muted); padding: 0.75rem; background: var(--bg-hover); border-radius: var(--radius);"></div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="icon">🖨️</i> Print Invoice</button>
            <button type="button" class="btn btn-primary" onclick="window.closeInvoiceModal()">Close</button>
        </div>
    </div>
</div>

<script>
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

                document.getElementById('inv-view-number').textContent = inv.invoice_number;
                document.getElementById('inv-view-dates').textContent = 'Date: ' + inv.invoice_date + ' | Due: ' + (inv.due_date || 'Immediate');
                document.getElementById('inv-view-total').textContent = '₹' + parseFloat(inv.grand_total).toLocaleString('en-IN', {minimumFractionDigits: 2});
                
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

    window.closeInvoiceModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('invoice-view-modal');
        } else {
            document.getElementById('invoice-view-modal').classList.remove('active');
        }
    };
</script>
