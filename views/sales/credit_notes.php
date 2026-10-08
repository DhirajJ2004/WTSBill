<?php
$pageTitle = 'Credit Notes - WTSBill ERP';
$pageHeader = 'Sales Credit Notes & Refund Adjustments';
$currentRoute = 'credit-notes';

require_once __DIR__ . '/../db_helper.php';
$customers = get_customers();

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-receipt"></i> Sales Credit Notes</div>
                <button class="btn btn-primary" onclick="openModal('addCreditNoteModal')"><i class="fa-solid fa-plus"></i> + Issue Credit Note</button>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Credit Note #</th>
                            <th>Original Invoice #</th>
                            <th>Customer Name</th>
                            <th>Credit Date</th>
                            <th>Tax Amount</th>
                            <th>Total Credit Amount</th>
                            <th>Reason</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>CN-2025-26-001</strong></td>
                            <td><code>INV-2025-26-0001</code></td>
                            <td><strong>TechCorp Solutions Pvt Ltd</strong></td>
                            <td>2026-09-21</td>
                            <td>₹900.00</td>
                            <td><strong>₹5,900.00</strong></td>
                            <td><span class="badge badge-info">Service Hours Adjustment</span></td>
                            <td class="text-right">
                                <a href="<?= url('/print-credit-note?number=CN-2025-26-001&invoice=INV-2025-26-0001&customer=' . urlencode('TechCorp Solutions Pvt Ltd') . '&amount=5900') ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-print"></i> Print</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Add Credit Note Modal -->
<div class="modal-overlay" id="addCreditNoteModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-file-invoice text-primary"></i> Issue Sales Credit Note</div>
            <button class="modal-close" onclick="closeModal('addCreditNoteModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="creditNoteErrorMsg" style="display: none; padding: 10px 14px; background: #fee2e2; border: 1px solid #f87171; border-radius: 6px; color: #991b1b; font-size: 13px; margin-bottom: 14px;"></div>

            <div class="form-group">
                <label class="form-label">Customer Name *</label>
                <select class="form-control" id="cnCustomerId">
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Original Sales Invoice #</label>
                    <input type="text" class="form-control" id="cnInvoiceNumber" placeholder="INV-2025-26-0001">
                </div>
                <div class="form-group">
                    <label class="form-label">Credit Date *</label>
                    <input type="date" class="form-control" id="cnDate" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Credit Amount (excl. tax) *</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="cnAmount" placeholder="5000.00">
                </div>
                <div class="form-group">
                    <label class="form-label">GST Tax Rate</label>
                    <select class="form-control" id="cnTaxRate">
                        <option value="18">18% GST</option>
                        <option value="12">12% GST</option>
                        <option value="5">5% GST</option>
                        <option value="0">0% Exempt</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Reason for Credit Note *</label>
                <textarea class="form-control" id="cnReason" rows="2" placeholder="Describe correction, SLA penalty, or refund reason..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addCreditNoteModal')">Cancel</button>
            <button class="btn btn-primary" id="btnIssueCreditNote" onclick="submitCreditNoteForm()"><i class="fa-solid fa-check"></i> Issue Credit Note</button>
        </div>
    </div>
</div>

<script>
async function submitCreditNoteForm() {
    let errBox = document.getElementById('creditNoteErrorMsg');
    errBox.style.display = 'none';
    errBox.innerText = '';

    let customerId = parseInt(document.getElementById('cnCustomerId').value);
    let invNum = (document.getElementById('cnInvoiceNumber').value || '').trim();
    let noteDate = document.getElementById('cnDate').value;
    let amount = parseFloat(document.getElementById('cnAmount').value || 0);
    let taxRate = parseFloat(document.getElementById('cnTaxRate').value || 0);
    let reason = (document.getElementById('cnReason').value || '').trim();

    if (!customerId) {
        errBox.innerText = 'Please select a customer.';
        errBox.style.display = 'block';
        return;
    }
    if (amount <= 0 || isNaN(amount)) {
        errBox.innerText = 'Credit amount must be greater than zero.';
        errBox.style.display = 'block';
        return;
    }

    let btn = document.getElementById('btnIssueCreditNote');
    let originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

    try {
        let payload = {
            customer_id: customerId,
            credit_note_date: noteDate,
            reason: reason,
            subtotal: amount,
            tax_rate: taxRate,
            invoice_number: invNum,
            items: [
                {
                    product_id: 1,
                    quantity: 1,
                    unit_price: amount,
                    tax_rate: taxRate,
                    description: reason || 'Credit Note Adjustment'
                }
            ]
        };

        let res = await fetch('<?= url('/api/v1/credit-notes') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });

        let data = await res.json();

        if (res.ok && data.status === 'success') {
            closeModal('addCreditNoteModal');
            let successMsg = 'Credit Note issued successfully! Ref: ' + (data.data?.credit_note_number || 'OK');
            if (typeof showToast === 'function') {
                showToast(successMsg, 'success');
            } else {
                alert(successMsg);
            }
            setTimeout(() => { location.reload(); }, 600);
        } else {
            errBox.innerText = data.message || 'Error issuing credit note.';
            errBox.style.display = 'block';
        }
    } catch (e) {
        errBox.innerText = 'Network or server error: ' + e.message;
        errBox.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
