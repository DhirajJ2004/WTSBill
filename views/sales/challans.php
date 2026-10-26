<?php
/**
 * WTSBill ERP — Delivery Challans View
 *
 * @var array $challans
 * @var array $customers
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Delivery Challans & Dispatch</h1>
        <p class="page-subtitle">Goods delivery notes, vehicle numbers, dispatch tracking, and e-Way Bill logistics verification</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="challan-search" class="form-control" placeholder="Search challan #..." style="width: 240px; padding: 0.4rem 0.75rem; font-size: 0.85rem;">
        <button type="button" class="btn btn-primary" onclick="window.openChallanModal()">+ Create Delivery Challan</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Dispatch Challan Registry (<span id="challans-count"><?= count($challans ?? []) ?></span> Challans)</h3>
    </div>
    <div class="table-container">
        <table class="table" id="challans-table">
            <thead>
                <tr>
                    <th>Challan #</th>
                    <th>Customer Name</th>
                    <th>Dispatch Date</th>
                    <th>Transport / Vehicle</th>
                    <th style="text-align: center;">Delivery Status</th>
                </tr>
            </thead>
            <tbody id="challans-table-body">
                <?php if (!empty($challans)): ?>
                    <?php foreach ($challans as $ch): ?>
                        <tr>
                            <td><strong><?= e($ch['challan_number']) ?></strong></td>
                            <td><?= e($ch['customer_name'] ?: 'Direct Customer') ?></td>
                            <td><?= e(format_date($ch['challan_date'])) ?></td>
                            <td><?= e($ch['transport_details'] ?? 'Road Logistics') ?></td>
                            <td style="text-align: center;">
                                <span class="badge badge-success"><?= e($ch['status'] ?? 'DISPATCHED') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-challan-row">
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No delivery challan records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Delivery Challan Creation Modal -->
<div id="challan-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="challan-modal-title">
    <div class="modal-dialog" style="max-width: 560px;">
        <div class="modal-header">
            <h3 id="challan-modal-title" class="modal-title">Generate Delivery Challan</h3>
            <button type="button" class="modal-close" onclick="window.closeChallanModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="challan-form" onsubmit="window.saveChallan(event)">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div class="modal-body">
                <div id="challan-modal-error" class="alert alert-danger" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;"></div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group">
                        <label for="challan-customer">Customer <span style="color: var(--danger);">*</span></label>
                        <select id="challan-customer" name="customer_id" class="form-control" required autofocus>
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers ?? [] as $cust): ?>
                                <option value="<?= e((string)$cust['id']) ?>"><?= e($cust['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="challan-transport">Transport & Vehicle Details</label>
                        <input type="text" id="challan-transport" name="transport_details" class="form-control" placeholder="e.g. MH-04-AB-9812 (Tata 407)">
                    </div>

                    <div class="form-group">
                        <label for="challan-address">Site / Delivery Address</label>
                        <textarea id="challan-address" name="delivery_address" class="form-control" rows="2" placeholder="Destination site or warehouse address"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeChallanModal()">Cancel</button>
                <button type="submit" id="challan-submit-btn" class="btn btn-primary">Create Challan</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.WTSBill && window.WTSBill.Tables) {
            window.WTSBill.Tables.filter('challan-search', 'challans-table');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('challan-modal');
                if (modal && modal.classList.contains('active')) {
                    window.closeChallanModal();
                }
            }
        });
    });

    window.openChallanModal = function() {
        const form = document.getElementById('challan-form');
        form.reset();
        document.getElementById('challan-modal-error').style.display = 'none';
        document.getElementById('challan-modal-error').textContent = '';
        document.getElementById('challan-submit-btn').disabled = false;
        document.getElementById('challan-submit-btn').textContent = 'Create Challan';

        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.open('challan-modal');
        } else {
            document.getElementById('challan-modal').classList.add('active');
        }
    };

    window.closeChallanModal = function() {
        if (window.WTSBill && window.WTSBill.Modal) {
            window.WTSBill.Modal.close('challan-modal');
        } else {
            document.getElementById('challan-modal').classList.remove('active');
        }
    };

    window.saveChallan = async function(e) {
        e.preventDefault();
        const form = document.getElementById('challan-form');
        const errBox = document.getElementById('challan-modal-error');
        const submitBtn = document.getElementById('challan-submit-btn');

        errBox.style.display = 'none';
        errBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const formData = new FormData(form);

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/sales/challans', {
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
                window.closeChallanModal();
                if (window.WTSBill && window.WTSBill.Toast) {
                    window.WTSBill.Toast.show(result.message || 'Delivery challan generated successfully!', 'success');
                }
                setTimeout(() => window.location.reload(), 300);
            } else {
                errBox.style.display = 'block';
                errBox.textContent = result.message || 'Failed to create delivery challan.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Challan';
            }
        } catch (err) {
            console.error('Save challan error:', err);
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error. Please try again.';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Challan';
        }
    };
</script>
