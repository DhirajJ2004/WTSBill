<?php
$title = $title ?? 'Record Customer Advance';
$customers = $customers ?? [];
$branches = $branches ?? [];
$preSelectedCustomer = $preSelectedCustomer ?? 0;
?>

<div class="content-wrapper" style="padding: 24px; max-width: 900px; margin: 0 auto;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/payments/advances" style="color: #64748b; text-decoration: none;">Customer Advances</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;">Record Deposit</span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0;">Record Customer Advance</h1>
        </div>
        <a href="/payments/advances" style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; background: #fff; color: #475569; border: 1px solid #cbd5e1; text-decoration: none;">
            Cancel
        </a>
    </div>

    <!-- Inline Error Notification Container (Zero browser alerts) -->
    <div id="advanceErrorBanner" style="display: none; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px;">
        <span id="advanceErrorMessage"></span>
    </div>

    <form id="advanceCreateForm" action="/payments/advances/create" method="POST" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <?= csrf_field() ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <!-- Customer -->
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Customer *</label>
                <select name="customer_id" id="customerId" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $preSelectedCustomer === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?> (Current Due: ₹<?= number_format((float)$c['current_balance'], 2) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Advance Date -->
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Advance Receipt Date *</label>
                <input type="date" name="advance_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <!-- Amount -->
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Advance Amount (₹) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="advanceAmount" required placeholder="0.00" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 600;">
            </div>

            <!-- Payment Mode -->
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Payment Mode *</label>
                <select name="payment_mode" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                    <option value="Bank Transfer">Bank Transfer (NEFT/RTGS/IMPS)</option>
                    <option value="UPI">UPI / QR Code</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Cash">Cash</option>
                    <option value="Credit Card">Credit Card</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <!-- Branch -->
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Receiving Branch</label>
                <select name="branch_id" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Reference / UTR -->
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Reference / UTR / Cheque Number</label>
                <input type="text" name="reference_no" placeholder="e.g. UTR-982348274 or CHQ-00123" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
            </div>
        </div>

        <div style="margin-bottom: 24px;">
            <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Notes / Remarks</label>
            <textarea name="notes" rows="3" placeholder="Additional details or terms regarding this customer deposit..." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <a href="/payments/advances" style="padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; background: #fff; color: #64748b; border: 1px solid #cbd5e1; text-decoration: none;">
                Cancel
            </a>
            <button type="submit" id="saveAdvanceBtn" style="padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; background: #2563eb; color: #fff; border: none; cursor: pointer;">
                Save Customer Advance
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('advanceCreateForm').addEventListener('submit', function(e) {
    var amt = parseFloat(document.getElementById('advanceAmount').value) || 0;
    var cust = document.getElementById('customerId').value;
    var errorBanner = document.getElementById('advanceErrorBanner');
    var errorMsg = document.getElementById('advanceErrorMessage');

    if (!cust) {
        e.preventDefault();
        errorMsg.textContent = 'Please select a customer for this advance receipt.';
        errorBanner.style.display = 'block';
        return;
    }
    if (amt <= 0) {
        e.preventDefault();
        errorMsg.textContent = 'Advance amount must be greater than zero.';
        errorBanner.style.display = 'block';
        return;
    }
    errorBanner.style.display = 'none';
});
</script>

