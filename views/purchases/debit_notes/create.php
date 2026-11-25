<?php
$title = $title ?? 'Issue Debit Note';
$suppliers = $suppliers ?? [];
$purchases = $purchases ?? [];
?>

<div class="content-wrapper" style="padding: 24px;">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
            <a href="/purchases/debit-notes" style="color: #64748b; text-decoration: none;">Debit Notes</a>
            <span>/</span>
            <span style="color: #1e293b; font-weight: 500;">Issue Note</span>
        </div>
        <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Issue Debit Note (Purchase Return)</h1>
        <p style="font-size: 14px; color: #64748b; margin: 0;">Issue a debit note to reverse supplier liability and account for defective or returned goods.</p>
    </div>

    <form action="/purchases/debit-notes/create" method="POST" id="dnForm">
        <?= csrf_field() ?>

        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0 0 20px 0;">Debit Note Details</h2>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Supplier <span style="color: #ef4444;">*</span></label>
                    <select name="supplier_id" id="supplier_id" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int)$s['id'] ?>">
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['company_name'] ?: 'Vendor #' . $s['id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Original Inward Bill (Optional)</label>
                    <select name="purchase_id" id="purchase_id" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                        <option value="">-- Link to Pending Purchase Bill (Optional) --</option>
                        <?php foreach ($purchases as $p): ?>
                            <?php
                            $amtDue = (float)($p['amount_due'] ?? 0);
                            $isSettled = ($amtDue <= 0.001) || (strtoupper($p['status'] ?? '') === 'PAID');
                            if ($isSettled) {
                                continue;
                            }
                            $isOverdue = !empty($p['due_date']) && (strtotime($p['due_date']) < strtotime(date('Y-m-d')));

                            if ($isOverdue) {
                                $dueStatusLabel = 'Overdue: ₹' . number_format($amtDue, 2) . (!empty($p['due_date']) ? ' (Due: ' . date('d M Y', strtotime($p['due_date'])) . ')' : '');
                            } else {
                                $dueStatusLabel = 'Outstanding: ₹' . number_format($amtDue, 2) . (!empty($p['due_date']) ? ' (Due: ' . date('d M Y', strtotime($p['due_date'])) . ')' : '');
                            }
                            ?>
                            <option value="<?= (int)$p['id'] ?>" data-supplier-id="<?= (int)($p['supplier_id'] ?? 0) ?>" data-amount-due="<?= $amtDue ?>">
                                <?= htmlspecialchars($p['purchase_number']) ?> (<?= $dueStatusLabel ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="purchase-bill-hint" style="margin-top: 6px; font-size: 12.5px; color: #0284c7; display: none;"></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Debit Note Date <span style="color: #ef4444;">*</span></label>
                    <input type="date" name="debit_note_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>

                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Return Amount (₹) <span style="color: #ef4444;">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Reason for Debit Note <span style="color: #ef4444;">*</span></label>
                <select name="reason" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;">
                    <option value="Goods Damaged / Defective">Goods Damaged / Defective in Transit</option>
                    <option value="Quality Rejection">Quality Rejection upon Inspection</option>
                    <option value="Excess Supply">Excess Supply beyond Purchase Order</option>
                    <option value="Rate Difference / Overbilled">Rate Difference / Vendor Overbilling</option>
                    <option value="Post-Purchase Discount">Post-Purchase Volume Discount / Rebate</option>
                    <option value="Other">Other (Specify in Notes)</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Notes & Observations</label>
                <textarea name="notes" rows="3" placeholder="Provide justification, defect inspection report details, or vendor correspondence..." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;"></textarea>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <a href="/purchases/debit-notes" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #475569; font-weight: 600; text-decoration: none;">Cancel</a>
            <button type="submit" style="padding: 10px 24px; border-radius: 8px; border: none; background: #2563eb; color: #fff; font-weight: 600; cursor: pointer;">Issue Debit Note</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const supplierSelect = document.getElementById('supplier_id');
    const purchaseSelect = document.getElementById('purchase_id');
    const amountInput = document.querySelector('input[name="amount"]');
    const hintDiv = document.getElementById('purchase-bill-hint');

    if (!supplierSelect || !purchaseSelect) return;

    function filterBills() {
        const selectedSupplierId = supplierSelect.value;
        const currentBillOpt = purchaseSelect.selectedOptions[0];

        Array.from(purchaseSelect.options).forEach((opt, idx) => {
            if (idx === 0) return; // Keep default prompt option
            const billSupplierId = opt.getAttribute('data-supplier-id');
            if (!selectedSupplierId || billSupplierId === selectedSupplierId) {
                opt.style.display = '';
                opt.disabled = false;
            } else {
                opt.style.display = 'none';
                opt.disabled = true;
            }
        });

        // If currently selected bill doesn't belong to newly selected supplier, reset bill
        if (currentBillOpt && currentBillOpt.value && selectedSupplierId) {
            if (currentBillOpt.getAttribute('data-supplier-id') !== selectedSupplierId) {
                purchaseSelect.value = '';
                updateHint();
            }
        }
    }

    function updateHint() {
        const opt = purchaseSelect.selectedOptions[0];
        if (opt && opt.value) {
            const due = parseFloat(opt.getAttribute('data-amount-due') || 0);
            const suppId = opt.getAttribute('data-supplier-id');
            if (suppId && (!supplierSelect.value || supplierSelect.value !== suppId)) {
                supplierSelect.value = suppId;
            }
            if (hintDiv) {
                hintDiv.style.display = 'block';
                hintDiv.innerHTML = `<strong>Selected Bill Outstanding:</strong> ₹${due.toLocaleString('en-IN', {minimumFractionDigits: 2})} remaining`;
            }
            if (amountInput && (!amountInput.value || parseFloat(amountInput.value) <= 0)) {
                amountInput.placeholder = `Max ₹${due.toFixed(2)}`;
            }
        } else {
            if (hintDiv) {
                hintDiv.style.display = 'none';
                hintDiv.innerHTML = '';
            }
            if (amountInput) {
                amountInput.placeholder = '0.00';
            }
        }
    }

    supplierSelect.addEventListener('change', filterBills);
    purchaseSelect.addEventListener('change', updateHint);

    // Initial filter pass
    filterBills();
    updateHint();
});
</script>

