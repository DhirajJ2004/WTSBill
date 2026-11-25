<?php
/**
 * WTSBill ERP — Delivery Challans View
 * Supports creating delivery challans directly or by retrieving an existing Tax Invoice.
 *
 * @var array $challans
 * @var array $customers
 */
?>
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary, #051F20);">Delivery Challans &amp; Dispatch</h1>
        <p class="page-subtitle" style="margin: 0.25rem 0 0 0; color: var(--text-muted, #4B5563); font-size: 0.875rem;">
            Goods delivery notes, vehicle numbers, dispatch tracking, and invoice-to-challan conversion
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <input type="text" id="challan-search" class="form-control" placeholder="Search challan or customer..." style="width: 240px; padding: 0.45rem 0.75rem; font-size: 0.85rem; border: 1px solid #8EB69B; border-radius: 6px;">
        <button type="button" class="btn btn-primary" onclick="window.openChallanModal('invoice')" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.9rem; font-size: 0.85rem; background: #235347; color: #FFFFFF; border: none; border-radius: 6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
            <span>+ Create from Invoice</span>
        </button>
        <button type="button" class="btn btn-secondary" onclick="window.openChallanModal('direct')" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.9rem; font-size: 0.85rem; border: 1px solid #8EB69B; background: #FFFFFF; color: #051F20; border-radius: 6px;">
            <span>+ Direct Challan</span>
        </button>
    </div>
</div>

<div class="card" style="background: #FFFFFF; border: 1px solid #8EB69B; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
    <div class="card-header" style="padding: 1rem 1.25rem; background: #DAF1DE; border-bottom: 1px solid rgba(142,182,155,0.3); display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #051F20;">
            Dispatch Challan Registry (<span id="challans-count"><?= count($challans ?? []) ?></span> Records)
        </h3>
    </div>
    <div class="table-container" style="overflow-x: auto;">
        <table class="table" id="challans-table" style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
            <thead>
                <tr style="background: #0B2B26; color: #FFFFFF;">
                    <th style="padding: 0.75rem 1rem; text-align: left;">Challan #</th>
                    <th style="padding: 0.75rem 1rem; text-align: left;">Linked Invoice</th>
                    <th style="padding: 0.75rem 1rem; text-align: left;">Customer Name</th>
                    <th style="padding: 0.75rem 1rem; text-align: left;">Dispatch Date</th>
                    <th style="padding: 0.75rem 1rem; text-align: left;">Transport / Vehicle</th>
                    <th style="padding: 0.75rem 1rem; text-align: center;">Delivery Status</th>
                    <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="challans-table-body">
                <?php if (!empty($challans)): ?>
                    <?php foreach ($challans as $ch): ?>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem 1rem;">
                                <strong style="color: #235347;"><?= e($ch['challan_number']) ?></strong>
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                <?php if (!empty($ch['invoice_number'])): ?>
                                    <span style="background: #DAF1DE; color: #0B2B26; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.8rem;">
                                        <?= e($ch['invoice_number']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94A3B8;">— (Direct)</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 1rem;"><?= e($ch['customer_name'] ?: 'Direct Customer') ?></td>
                            <td style="padding: 0.75rem 1rem;"><?= date('d M Y', strtotime($ch['challan_date'])) ?></td>
                            <td style="padding: 0.75rem 1rem; color: #4B5563;"><?= e($ch['transport_details'] ?? 'Road Logistics') ?></td>
                            <td style="padding: 0.75rem 1rem; text-align: center;">
                                <span style="background: #D1FAE5; color: #065F46; padding: 3px 8px; border-radius: 4px; font-size: 0.76rem; font-weight: 600;">
                                    <?= e($ch['status'] ?? 'DELIVERED') ?>
                                </span>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right;">
                                <a href="/sales/challans/<?= (int)$ch['id'] ?>/print" target="_blank" class="btn btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.65rem; font-size: 0.78rem; border: 1px solid #8EB69B; background: #FFFFFF; color: #235347; border-radius: 4px; text-decoration: none; font-weight: 600;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    <span>Print</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-challan-row">
                        <td colspan="7" style="text-align: center; color: #94A3B8; padding: 2.5rem;">No delivery challan records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Working Delivery Challan Creation Modal with Invoice Lookup -->
<div id="challan-modal" class="modal-backdrop" role="dialog" aria-modal="true" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; padding:1rem;">
    <div class="modal-dialog" style="background:#FFFFFF; border-radius:10px; width:100%; max-width:680px; overflow:hidden; box-shadow:0 15px 35px rgba(0,0,0,0.25); display:flex; flex-direction:column; max-height:90vh;">
        <div class="modal-header" style="padding:1rem 1.25rem; background:#DAF1DE; border-bottom:1px solid rgba(142,182,155,0.3); display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#235347" stroke-width="2"><polygon points="12 2 19 21 12 17 5 21 12 2"></polygon></svg>
                <h3 id="challan-modal-title" style="margin:0; font-size:1.1rem; font-weight:700; color:#051F20;">Generate Delivery Challan</h3>
            </div>
            <button type="button" class="modal-close" onclick="window.closeChallanModal()" style="background:none; border:none; font-size:1.5rem; line-height:1; cursor:pointer; color:#051F20;">&times;</button>
        </div>

        <form id="challan-form" onsubmit="window.saveChallan(event)" style="display:flex; flex-direction:column; overflow:hidden;">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">
            <input type="hidden" id="challan-invoice-id" name="invoice_id" value="">
            <input type="hidden" id="challan-invoice-number" name="invoice_number" value="">
            <input type="hidden" id="challan-items-json" name="items" value="">

            <div class="modal-body" style="padding:1.25rem; overflow-y:auto; display:flex; flex-direction:column; gap:1.1rem;">
                <div id="challan-modal-error" class="alert alert-danger" style="display:none; padding:0.65rem 0.9rem; border-radius:6px; background:#FEF2F2; border:1px solid #FCA5A5; color:#991B1B; font-size:0.85rem;"></div>

                <!-- Mode Selector Pills -->
                <div style="display:flex; gap:0.5rem; border-bottom:1px solid #E2E8F0; padding-bottom:0.75rem;">
                    <button type="button" id="btn-mode-invoice" onclick="window.switchChallanMode('invoice')" style="padding:0.45rem 0.9rem; font-size:0.84rem; font-weight:600; border-radius:6px; border:none; background:#235347; color:#FFFFFF; cursor:pointer; display:flex; align-items:center; gap:0.4rem;">
                        <span>📄 Create from Existing Invoice</span>
                    </button>
                    <button type="button" id="btn-mode-direct" onclick="window.switchChallanMode('direct')" style="padding:0.45rem 0.9rem; font-size:0.84rem; font-weight:600; border-radius:6px; border:1px solid #CBD5E1; background:#FFFFFF; color:#4B5563; cursor:pointer;">
                        <span>Direct Challan</span>
                    </button>
                </div>

                <!-- Invoice Search Block (Mode: Invoice) -->
                <div id="sec-invoice-lookup" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:1rem;">
                    <label style="display:block; font-size:0.8rem; font-weight:700; color:#051F20; margin-bottom:0.4rem;">
                        Enter or Search Tax Invoice Number:
                    </label>
                    <div style="display:flex; gap:0.5rem;">
                        <input type="text" id="invoice-search-input" class="form-control" placeholder="e.g. INV-2026-0001" style="flex:1; padding:0.45rem 0.75rem; border:1px solid #8EB69B; border-radius:6px; font-size:0.88rem;">
                        <button type="button" class="btn btn-secondary" data-qr-scan="#invoice-search-input" data-qr-type="invoice" style="border:1px solid #8EB69B; background:#FFFFFF; color:#235347; padding:0.45rem 0.75rem; border-radius:6px; font-size:0.82rem; cursor:pointer;" title="Scan Invoice QR Code">
                            📷 Scan QR
                        </button>
                        <button type="button" onclick="window.lookupInvoice()" id="btn-lookup-invoice" style="background:#235347; color:#FFFFFF; border:none; padding:0.45rem 1rem; border-radius:6px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                            Retrieve Invoice
                        </button>
                    </div>

                    <!-- Retrieved Invoice Summary Card -->
                    <div id="invoice-retrieved-card" style="display:none; margin-top:0.75rem; padding:0.75rem; background:#DAF1DE; border:1px solid #8EB69B; border-radius:6px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="font-size:0.75rem; color:#4B5563; text-transform:uppercase; font-weight:600;">Verified Invoice</span>
                                <div style="font-weight:700; color:#051F20; font-size:0.95rem;" id="retrieved-inv-number"></div>
                            </div>
                            <div style="text-align:right;">
                                <span style="font-size:0.75rem; color:#4B5563;">Date</span>
                                <div style="font-weight:600; font-size:0.85rem;" id="retrieved-inv-date"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Details -->
                <div class="form-group">
                    <label for="challan-customer" style="display:block; font-size:0.8rem; font-weight:600; color:#051F20; margin-bottom:0.3rem;">
                        Customer / Consignee <span style="color:#EF4444;">*</span>
                    </label>
                    <select id="challan-customer" name="customer_id" class="form-control" required style="width:100%; padding:0.45rem 0.75rem; border:1px solid #CBD5E1; border-radius:6px; font-size:0.85rem;">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers ?? [] as $cust): ?>
                            <option value="<?= e((string)$cust['id']) ?>"><?= e($cust['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Products Table Preview (Populated from Invoice) -->
                <div id="sec-challan-items" style="display:none;">
                    <label style="display:block; font-size:0.8rem; font-weight:700; color:#051F20; margin-bottom:0.3rem;">
                        Dispatched Products &amp; Quantities:
                    </label>
                    <div style="border:1px solid #E2E8F0; border-radius:6px; overflow:hidden;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                            <thead style="background:#F1F5F9; color:#4B5563;">
                                <tr>
                                    <th style="padding:0.4rem 0.6rem; text-align:left;">Item</th>
                                    <th style="padding:0.4rem 0.6rem; text-align:right; width:90px;">Quantity</th>
                                    <th style="padding:0.4rem 0.6rem; text-align:center; width:60px;">Unit</th>
                                </tr>
                            </thead>
                            <tbody id="challan-items-tbody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Transport & Delivery Info -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                    <div>
                        <label for="challan-transport" style="display:block; font-size:0.8rem; font-weight:600; color:#051F20; margin-bottom:0.3rem;">
                            Transport &amp; Vehicle Details
                        </label>
                        <input type="text" id="challan-transport" name="transport_details" class="form-control" placeholder="e.g. MH-12-AB-9812 (Tata 407)" style="width:100%; padding:0.45rem 0.75rem; border:1px solid #CBD5E1; border-radius:6px; font-size:0.85rem;">
                    </div>
                    <div>
                        <label for="challan-notes" style="display:block; font-size:0.8rem; font-weight:600; color:#051F20; margin-bottom:0.3rem;">
                            Dispatch Note / Remarks
                        </label>
                        <input type="text" id="challan-notes" name="notes" class="form-control" value="Materials dispatched in good condition" style="width:100%; padding:0.45rem 0.75rem; border:1px solid #CBD5E1; border-radius:6px; font-size:0.85rem;">
                    </div>
                </div>

                <div>
                    <label for="challan-address" style="display:block; font-size:0.8rem; font-weight:600; color:#051F20; margin-bottom:0.3rem;">
                        Site / Delivery Address
                    </label>
                    <textarea id="challan-address" name="delivery_address" class="form-control" rows="2" placeholder="Destination site or warehouse address" style="width:100%; padding:0.45rem 0.75rem; border:1px solid #CBD5E1; border-radius:6px; font-size:0.85rem;"></textarea>
                </div>
            </div>

            <div class="modal-footer" style="padding:0.85rem 1.25rem; background:#F8FAFC; border-top:1px solid #E2E8F0; display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="window.closeChallanModal()" style="padding:0.45rem 1rem; border:1px solid #CBD5E1; background:#FFFFFF; color:#4B5563; border-radius:6px; font-size:0.85rem; cursor:pointer;">Cancel</button>
                <button type="submit" id="challan-submit-btn" class="btn btn-primary" style="padding:0.45rem 1.25rem; background:#235347; color:#FFFFFF; border:none; border-radius:6px; font-size:0.85rem; font-weight:600; cursor:pointer;">Create Delivery Challan</button>
            </div>
        </form>
    </div>
</div>

<script>
    let activeChallanMode = 'invoice';
    let currentRetrievedItems = [];

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('challan-search');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase().trim();
                const rows = document.querySelectorAll('#challans-table-body tr');
                rows.forEach(function(row) {
                    if (row.id === 'no-challan-row') return;
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }
    });

    window.openChallanModal = function(mode) {
        mode = mode || 'invoice';
        const form = document.getElementById('challan-form');
        form.reset();
        document.getElementById('challan-invoice-id').value = '';
        document.getElementById('challan-invoice-number').value = '';
        document.getElementById('challan-items-json').value = '';
        document.getElementById('invoice-retrieved-card').style.display = 'none';
        document.getElementById('sec-challan-items').style.display = 'none';
        document.getElementById('challan-items-tbody').innerHTML = '';
        document.getElementById('challan-modal-error').style.display = 'none';
        document.getElementById('challan-submit-btn').disabled = false;
        document.getElementById('challan-submit-btn').textContent = 'Create Delivery Challan';
        currentRetrievedItems = [];

        window.switchChallanMode(mode);

        const modal = document.getElementById('challan-modal');
        modal.style.display = 'flex';
    };

    window.closeChallanModal = function() {
        const modal = document.getElementById('challan-modal');
        if (modal) modal.style.display = 'none';
    };

    window.switchChallanMode = function(mode) {
        activeChallanMode = mode;
        const btnInv = document.getElementById('btn-mode-invoice');
        const btnDir = document.getElementById('btn-mode-direct');
        const secLookup = document.getElementById('sec-invoice-lookup');

        if (mode === 'invoice') {
            btnInv.style.background = '#235347';
            btnInv.style.color = '#FFFFFF';
            btnDir.style.background = '#FFFFFF';
            btnDir.style.color = '#4B5563';
            secLookup.style.display = 'block';
        } else {
            btnDir.style.background = '#235347';
            btnDir.style.color = '#FFFFFF';
            btnInv.style.background = '#FFFFFF';
            btnInv.style.color = '#4B5563';
            secLookup.style.display = 'none';
            document.getElementById('challan-invoice-id').value = '';
            document.getElementById('challan-invoice-number').value = '';
        }
    };

    window.lookupInvoice = function() {
        const query = document.getElementById('invoice-search-input').value.trim();
        const errBox = document.getElementById('challan-modal-error');
        const btn = document.getElementById('btn-lookup-invoice');

        if (!query) {
            errBox.textContent = 'Please enter an invoice number to search.';
            errBox.style.display = 'block';
            return;
        }

        errBox.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Searching...';

        fetch('/api/sales/invoices/lookup?query=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(result => {
            btn.disabled = false;
            btn.textContent = 'Retrieve Invoice';

            if (!result.ok || !result.data.success) {
                errBox.textContent = result.data.message || 'Invoice not found.';
                errBox.style.display = 'block';
                return;
            }

            const inv = result.data.invoice;
            const items = result.data.items || [];

            // Populate form fields
            document.getElementById('challan-invoice-id').value = inv.id;
            document.getElementById('challan-invoice-number').value = inv.invoice_number;
            document.getElementById('retrieved-inv-number').textContent = inv.invoice_number + ' (' + inv.customer_name + ')';
            document.getElementById('retrieved-inv-date').textContent = inv.invoice_date;
            document.getElementById('invoice-retrieved-card').style.display = 'block';

            // Select customer
            const custSelect = document.getElementById('challan-customer');
            if (custSelect) {
                custSelect.value = inv.customer_id;
            }

            // Fill address
            if (inv.delivery_address) {
                document.getElementById('challan-address').value = inv.delivery_address;
            }

            // Populate items
            currentRetrievedItems = items;
            document.getElementById('challan-items-json').value = JSON.stringify(items);

            const tbody = document.getElementById('challan-items-tbody');
            tbody.innerHTML = '';
            if (items.length > 0) {
                items.forEach((item, idx) => {
                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid #F1F5F9';
                    tr.innerHTML = `
                        <td style="padding:0.4rem 0.6rem;">
                            <strong>${item.item_name}</strong>
                            ${item.sku ? `<span style="font-size:0.75rem; color:#64748B;"> (${item.sku})</span>` : ''}
                        </td>
                        <td style="padding:0.4rem 0.6rem; text-align:right;">
                            <input type="number" step="0.001" min="0" value="${item.quantity}" onchange="window.updateItemQty(${idx}, this.value)" style="width:75px; text-align:right; padding:2px 6px; border:1px solid #CBD5E1; border-radius:4px; font-size:0.82rem;">
                        </td>
                        <td style="padding:0.4rem 0.6rem; text-align:center; color:#64748B;">${item.unit || 'Pcs'}</td>
                    `;
                    tbody.appendChild(tr);
                });
                document.getElementById('sec-challan-items').style.display = 'block';
            } else {
                document.getElementById('sec-challan-items').style.display = 'none';
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = 'Retrieve Invoice';
            errBox.textContent = 'Failed to fetch invoice. Please verify connection.';
            errBox.style.display = 'block';
        });
    };

    window.updateItemQty = function(index, newQty) {
        if (currentRetrievedItems[index]) {
            currentRetrievedItems[index].quantity = parseFloat(newQty) || 0;
            document.getElementById('challan-items-json').value = JSON.stringify(currentRetrievedItems);
        }
    };

    window.saveChallan = async function(e) {
        e.preventDefault();
        const form = document.getElementById('challan-form');
        const errBox = document.getElementById('challan-modal-error');
        const submitBtn = document.getElementById('challan-submit-btn');

        errBox.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating Challan...';

        const formData = new FormData(form);
        const payload = {};
        formData.forEach((val, key) => { payload[key] = val; });
        if (currentRetrievedItems.length > 0) {
            payload['items'] = currentRetrievedItems;
        }

        try {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const resp = await fetch('/sales/challans', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': metaToken
                },
                body: JSON.stringify(payload)
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
                submitBtn.textContent = 'Create Delivery Challan';
            }
        } catch (err) {
            errBox.style.display = 'block';
            errBox.textContent = 'Network or server error occurred.';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Delivery Challan';
        }
    };
</script>
