<?php
$title = $title ?? 'Create Credit Note';
$customers = $customers ?? [];
$invoices = $invoices ?? [];
?>

<div class="content-wrapper" style="padding: 1.5rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #163832; margin-bottom: 0.25rem;">
                <a href="/sales/credit-notes" style="color: #235347; text-decoration: none;">Credit Notes</a>
                <span>/</span>
                <span style="color: #051F20; font-weight: 600;">Create</span>
            </div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #051F20; margin: 0;">Create Credit Note</h1>
            <p style="font-size: 0.85rem; color: #163832; margin: 0.25rem 0 0 0;">Issue a sales return credit note to reduce customer receivables and adjust GST liabilities.</p>
        </div>
        <div>
            <a href="/sales/credit-notes" class="btn btn-secondary" style="background: #DAF1DE; border: 1.5px solid #8EB69B; color: #163832; padding: 0.55rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none; font-size: 0.85rem;">
                &larr; Back to Credit Notes
            </a>
        </div>
    </div>

    <!-- Form Card -->
    <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.5rem; max-width: 900px;">
        <form method="POST" action="/sales/credit-notes">
            <input type="hidden" name="_csrf_token" value="<?= \WTSBill\Core\Session::csrfToken() ?>">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #051F20; margin-bottom: 0.35rem;">Credit Note Number *</label>
                    <input type="text" name="credit_note_number" required value="CN-<?= date('Y') ?>-<?= str_pad((string)rand(100, 999), 4, '0', STR_PAD_LEFT) ?>" style="width: 100%; padding: 0.55rem 0.75rem; border: 1.5px solid #8EB69B; border-radius: 6px; font-size: 0.85rem; background: #DAF1DE; color: #051F20; font-weight: 600;">
                </div>

                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #051F20; margin-bottom: 0.35rem;">Credit Note Date *</label>
                    <input type="date" name="credit_note_date" required value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.55rem 0.75rem; border: 1.5px solid #8EB69B; border-radius: 6px; font-size: 0.85rem; background: #FFFFFF; color: #051F20;">
                </div>

                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #051F20; margin-bottom: 0.35rem;">Customer *</label>
                    <select name="customer_id" required style="width: 100%; padding: 0.55rem 0.75rem; border: 1.5px solid #8EB69B; border-radius: 6px; font-size: 0.85rem; background: #FFFFFF; color: #051F20;">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['phone'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #051F20; margin-bottom: 0.35rem;">Against Invoice (Optional)</label>
                    <input type="text" name="invoice_number" placeholder="e.g. INV-2026-0001" style="width: 100%; padding: 0.55rem 0.75rem; border: 1.5px solid #8EB69B; border-radius: 6px; font-size: 0.85rem; background: #FFFFFF; color: #051F20;">
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #051F20; margin-bottom: 0.35rem;">Reason for Return / Credit Note *</label>
                <select name="reason" required style="width: 100%; padding: 0.55rem 0.75rem; border: 1.5px solid #8EB69B; border-radius: 6px; font-size: 0.85rem; background: #FFFFFF; color: #051F20; margin-bottom: 0.75rem;">
                    <option value="Goods Return / Defective Material">Goods Return / Defective Material</option>
                    <option value="Correction in Invoice / Pricing Error">Correction in Invoice / Pricing Error</option>
                    <option value="Post-Sale Discount / Volume Rebate">Post-Sale Discount / Volume Rebate</option>
                    <option value="Order Cancellation / Partial Refund">Order Cancellation / Partial Refund</option>
                </select>
                <textarea name="notes" rows="2" placeholder="Additional details or justification..." style="width: 100%; padding: 0.55rem 0.75rem; border: 1.5px solid #8EB69B; border-radius: 6px; font-size: 0.85rem; background: #FFFFFF; color: #051F20;"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; background: #F4FAF6; padding: 1.25rem; border-radius: 8px; border: 1px solid #8EB69B;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #051F20; margin-bottom: 0.35rem;">Taxable Return Amount (₹) *</label>
                    <input type="number" step="0.01" name="taxable_amount" id="taxable_amount" required value="0.00" oninput="calculateTotal()" style="width: 100%; padding: 0.55rem 0.75rem; border: 1.5px solid #8EB69B; border-radius: 6px; font-size: 0.95rem; font-weight: 700; background: #FFFFFF; color: #051F20;">
                </div>

                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #051F20; margin-bottom: 0.35rem;">GST Rate (%)</label>
                    <select name="gst_rate" id="gst_rate" onchange="calculateTotal()" style="width: 100%; padding: 0.55rem 0.75rem; border: 1.5px solid #8EB69B; border-radius: 6px; font-size: 0.85rem; background: #FFFFFF; color: #051F20;">
                        <option value="0">0% (Nil)</option>
                        <option value="5">5%</option>
                        <option value="12">12%</option>
                        <option value="18" selected>18%</option>
                        <option value="28">28%</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #051F20; margin-bottom: 0.35rem;">Total Credit Value (₹)</label>
                    <input type="number" step="0.01" name="total_amount" id="total_amount" readonly value="0.00" style="width: 100%; padding: 0.55rem 0.75rem; border: 1.5px solid #8EB69B; border-radius: 6px; font-size: 1rem; font-weight: 800; background: #DAF1DE; color: #235347;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <a href="/sales/credit-notes" class="btn btn-secondary" style="padding: 0.6rem 1.25rem; border-radius: 6px; background: #FFFFFF; border: 1px solid #8EB69B; color: #163832; text-decoration: none; font-size: 0.85rem;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.75rem; border-radius: 6px; background: #235347; color: #FFFFFF; border: none; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Issue Credit Note &rarr;</button>
            </div>
        </form>
    </div>
</div>

<script>
function calculateTotal() {
    var taxable = parseFloat(document.getElementById('taxable_amount').value) || 0;
    var rate = parseFloat(document.getElementById('gst_rate').value) || 0;
    var gst = (taxable * rate) / 100;
    var total = taxable + gst;
    document.getElementById('total_amount').value = total.toFixed(2);
}
</script>

