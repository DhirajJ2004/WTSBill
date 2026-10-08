<?php
$pageTitle = 'Debit Notes - WTSBill ERP';
$pageHeader = 'Purchase Debit Notes & Supplier Adjustments';
$currentRoute = 'debit-notes';

require_once __DIR__ . '/../db_helper.php';
$suppliers = get_suppliers();

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-file-invoice"></i> Supplier Debit Notes</div>
                <button class="btn btn-primary" onclick="openModal('addDebitNoteModal')"><i class="fa-solid fa-plus"></i> + Issue Debit Note</button>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Debit Note #</th>
                            <th>Supplier / Vendor</th>
                            <th>Original Bill #</th>
                            <th>Debit Date</th>
                            <th>Tax Amount</th>
                            <th>Total Debit Amount</th>
                            <th>Reason</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>DN-2025-26-001</strong></td>
                            <td><strong>CloudHost India Pvt Ltd</strong></td>
                            <td><code>BILL-9021</code></td>
                            <td>2026-09-18</td>
                            <td>₹540.00</td>
                            <td><strong>₹3,540.00</strong></td>
                            <td><span class="badge badge-warning">Downtime Credit Adjustment</span></td>
                            <td class="text-right">
                                <a href="<?= url('/print-debit-note?number=DN-2026-012&bill=BILL-9021&supplier=' . urlencode('CloudHost India Pvt Ltd') . '&amount=3540') ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-print"></i> Print</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Add Debit Note Modal -->
<div class="modal-overlay" id="addDebitNoteModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-file-invoice text-primary"></i> Issue Supplier Debit Note</div>
            <button class="modal-close" onclick="closeModal('addDebitNoteModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="debitNoteErrorMsg" style="display: none; padding: 10px 14px; background: #fee2e2; border: 1px solid #f87171; border-radius: 6px; color: #991b1b; font-size: 13px; margin-bottom: 14px;"></div>

            <div class="form-group">
                <label class="form-label">Supplier / Vendor *</label>
                <select class="form-control" id="dnSupplierId">
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Vendor Bill #</label>
                    <input type="text" class="form-control" id="dnBillNumber" placeholder="BILL-9021">
                </div>
                <div class="form-group">
                    <label class="form-label">Debit Date *</label>
                    <input type="date" class="form-control" id="dnDate" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Debit Amount (excl. tax) *</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="dnAmount" placeholder="3000.00">
                </div>
                <div class="form-group">
                    <label class="form-label">GST Tax Rate</label>
                    <select class="form-control" id="dnTaxRate">
                        <option value="18">18% GST</option>
                        <option value="12">12% GST</option>
                        <option value="5">5% GST</option>
                        <option value="0">0% Exempt</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addDebitNoteModal')">Cancel</button>
            <button class="btn btn-primary" id="btnIssueDebitNote" onclick="submitDebitNoteForm()"><i class="fa-solid fa-check"></i> Issue Debit Note</button>
        </div>
    </div>
</div>

<script>
async function submitDebitNoteForm() {
    let errBox = document.getElementById('debitNoteErrorMsg');
    errBox.style.display = 'none';
    errBox.innerText = '';

    let supplierId = parseInt(document.getElementById('dnSupplierId').value);
    let billNum = (document.getElementById('dnBillNumber').value || '').trim();
    let noteDate = document.getElementById('dnDate').value;
    let amount = parseFloat(document.getElementById('dnAmount').value || 0);
    let taxRate = parseFloat(document.getElementById('dnTaxRate').value || 0);

    if (!supplierId) {
        errBox.innerText = 'Please select a supplier.';
        errBox.style.display = 'block';
        return;
    }
    if (amount <= 0 || isNaN(amount)) {
        errBox.innerText = 'Debit amount must be greater than zero.';
        errBox.style.display = 'block';
        return;
    }

    let btn = document.getElementById('btnIssueDebitNote');
    let originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

    try {
        let payload = {
            supplier_id: supplierId,
            debit_note_date: noteDate,
            reason: 'Purchase return / debit note adjustment',
            subtotal: amount,
            tax_rate: taxRate,
            bill_number: billNum,
            items: [
                {
                    product_id: 1,
                    quantity: 1,
                    unit_price: amount,
                    tax_rate: taxRate,
                    description: 'Debit Note Adjustment'
                }
            ]
        };

        let res = await fetch('<?= url('/api/v1/debit-notes') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });

        let data = await res.json();

        if (res.ok && data.status === 'success') {
            closeModal('addDebitNoteModal');
            let successMsg = 'Debit Note issued successfully! Ref: ' + (data.data?.debit_note_number || 'OK');
            if (typeof showToast === 'function') {
                showToast(successMsg, 'success');
            } else {
                alert(successMsg);
            }
            setTimeout(() => { location.reload(); }, 600);
        } else {
            errBox.innerText = data.message || 'Error issuing debit note.';
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
