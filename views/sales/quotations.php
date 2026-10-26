<?php
/**
 * WTSBill ERP — Sales Quotations & Orders View
 *
 * @var array $quotations
 * @var array $customers
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Quotations & Proforma Orders</h1>
        <p class="page-subtitle">Price estimates, proforma bids, validity periods, customer quotations, and sales order conversion</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="quote-search" class="form-control" placeholder="Search quotation #..." style="width: 240px; padding: 0.4rem 0.75rem; font-size: 0.85rem;">
        <button type="button" class="btn btn-primary" onclick="window.openQuotationModal()">+ Create Quotation</button>
    </div>
</div>

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
                </tr>
            </thead>
            <tbody id="quote-table-body">
                <?php if (!empty($quotations)): ?>
                    <?php foreach ($quotations as $q): ?>
                        <tr>
                            <td><strong><?= e($q['quotation_number']) ?></strong></td>
                            <td><?= e($q['customer_name'] ?: 'Customer #' . $q['customer_id']) ?></td>
                            <td><?= e(format_date($q['quotation_date'])) ?></td>
                            <td><?= e(format_date($q['valid_until'] ?? date('Y-m-d', strtotime('+30 days')))) ?></td>
                            <td style="text-align: right;"><?= format_currency((float)($q['sub_total'] ?? 0)) ?></td>
                            <td style="text-align: right; color: var(--color-info);"><?= format_currency((float)($q['total_tax'] ?? 0)) ?></td>
                            <td style="text-align: right; font-weight: 700;"><?= format_currency((float)($q['grand_total'] ?? 0)) ?></td>
                            <td style="text-align: center;">
                                <span class="badge badge-warning"><?= e($q['status'] ?? 'SENT') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-quote-row">
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">No quotation records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Quotation Creation Modal -->
<div id="quotation-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="quote-modal-title">
    <div class="modal-dialog" style="max-width: 560px;">
        <div class="modal-header">
            <h3 id="quote-modal-title" class="modal-title">Create Sales Quotation</h3>
            <button type="button" class="modal-close" onclick="window.closeQuotationModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="quotation-form" onsubmit="window.saveQuotation(event)">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div class="modal-body">
                <div id="quote-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group">
                        <label for="quote-customer">Customer <span style="color: var(--danger);">*</span></label>
                        <select id="quote-customer" name="customer_id" class="form-control" required autofocus>
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers ?? [] as $cust): ?>
                                <option value="<?= e((string)$cust['id']) ?>">
                                    <?= e($cust['name']) ?> <?= !empty($cust['company_name']) ? ('(' . e($cust['company_name']) . ')') : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="quote-amount">Estimated Total Amount (₹) <span style="color: var(--danger);">*</span></label>
                            <input type="number" id="quote-amount" name="amount" class="form-control" placeholder="10000.00" step="0.01" min="1" required>
                        </div>

                        <div class="form-group">
                            <label for="quote-valid">Valid Until Date</label>
                            <input type="date" id="quote-valid" name="valid_until" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="quote-notes">Scope of Work & Notes</label>
                        <textarea id="quote-notes" name="notes" class="form-control" rows="3" placeholder="Pricing terms, delivery timeframe, and validity conditions"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeQuotationModal()">Cancel</button>
                <button type="submit" id="quote-submit-btn" class="btn btn-primary">Save Quotation</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('quote-search', 'quote-table');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('quotation-modal');
                if (modal && modal.classList.contains('active')) {
                    window.closeQuotationModal();
                }
            }
        });
    });

    window.openQuotationModal = function() {
        const form = document.getElementById('quotation-form');
        form.reset();
        document.getElementById('quote-modal-error').style.display = 'none';
        document.getElementById('quote-modal-error').textContent = '';
        document.getElementById('quote-submit-btn').disabled = false;
        document.getElementById('quote-submit-btn').textContent = 'Save Quotation';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('quotation-modal');
        } else {
            document.getElementById('quotation-modal').classList.add('active');
        }
    };

    window.closeQuotationModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('quotation-modal');
        } else {
            document.getElementById('quotation-modal').classList.remove('active');
        }
    };

    window.saveQuotation = async function(e) {
        e.preventDefault();
        const form = document.getElementById('quotation-form');
        const errBox = document.getElementById('quote-modal-error');
        const submitBtn = document.getElementById('quote-submit-btn');

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const formData = new FormData(form);

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/sales/quotations', {
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
                window.closeQuotationModal();
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Quotation created successfully!', 'success');
                }
                setTimeout(() => window.location.reload(), 300);
            } else {
                errBox.style.display = 'block';
                errBox.textContent = result.message || 'Failed to save quotation.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Quotation';
            }
        } catch (err) {
            console.error('Save quotation error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Quotation';
        }
    };
</script>
