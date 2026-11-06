<?php
$title = $title ?? 'Advance Voucher Details';
$advance = $advance ?? [];
$unpaidInvoices = $unpaidInvoices ?? [];
$allocations = $advance['allocations'] ?? [];
$status = $advance['status'] ?? 'ACTIVE';
$remBalance = (float)($advance['remaining_amount'] ?? 0);
?>

<div class="content-wrapper" style="padding: 24px; max-width: 1000px; margin: 0 auto;">
    <!-- Top Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; margin-bottom: 4px;">
                <a href="/payments/advances" style="color: #64748b; text-decoration: none;">Customer Advances</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 500;"><?= htmlspecialchars($advance['payment_number'] ?? '#' . $advance['id']) ?></span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 12px;">
                <span><?= htmlspecialchars($advance['payment_number'] ?? 'ADV-' . $advance['id']) ?></span>
                <span style="font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; <?= $status === 'ACTIVE' ? 'background: #dcfce7; color: #15803d;' : 'background: #f1f5f9; color: #64748b;' ?>">
                    <?= htmlspecialchars($status) ?>
                </span>
            </h1>
        </div>

        <div style="display: flex; gap: 12px;">
            <a href="/customers/<?= (int)$advance['customer_id'] ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 500; background: #fff; color: #334155; border: 1px solid #cbd5e1; text-decoration: none;">
                View Customer 360°
            </a>
            <a href="/payments/advances" style="padding: 10px 18px; border-radius: 8px; font-weight: 500; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; text-decoration: none;">
                Back to Advances
            </a>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Advance</div>
            <div style="font-size: 24px; font-weight: 700; color: #1e293b; margin-top: 6px;">₹<?= number_format((float)$advance['total_amount'], 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Recv Date: <?= date('d M Y', strtotime($advance['advance_date'])) ?></div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Allocated to Invoices</div>
            <div style="font-size: 24px; font-weight: 700; color: #2563eb; margin-top: 6px;">₹<?= number_format((float)$advance['allocated_amount'], 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;"><?= count($allocations) ?> drawdown allocation(s)</div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #16a34a; text-transform: uppercase;">Available for Drawdown</div>
            <div style="font-size: 24px; font-weight: 700; color: #16a34a; margin-top: 6px;">₹<?= number_format($remBalance, 2) ?></div>
            <div style="font-size: 12px; color: #16a34a; margin-top: 4px;"><?= $remBalance > 0 ? 'Ready for application' : 'Exhausted' ?></div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Payment Channel</div>
            <div style="font-size: 16px; font-weight: 700; color: #1e293b; margin-top: 8px;"><?= htmlspecialchars($advance['payment_mode'] ?: 'Bank') ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Ref: <?= htmlspecialchars($advance['reference_no'] ?: '—') ?></div>
        </div>
    </div>

    <!-- Customer Information Details -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
        <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px;">Party Information</div>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <a href="/customers/<?= (int)$advance['customer_id'] ?>" style="font-size: 18px; font-weight: 700; color: #2563eb; text-decoration: none;">
                    <?= htmlspecialchars($advance['customer_name']) ?>
                </a>
                <div style="font-size: 13px; color: #64748b; margin-top: 4px;">
                    Phone: <?= htmlspecialchars($advance['customer_phone'] ?: '—') ?> | 
                    Email: <?= htmlspecialchars($advance['customer_email'] ?: '—') ?> | 
                    GSTIN: <?= htmlspecialchars($advance['customer_gstin'] ?: 'Unregistered') ?>
                </div>
            </div>
            <div>
                <div style="font-size: 12px; color: #64748b;">Customer Current Balance</div>
                <div style="font-size: 18px; font-weight: 700; color: #1e293b;">₹<?= number_format((float)($advance['customer_current_balance'] ?? 0), 2) ?></div>
            </div>
        </div>
        <?php if (!empty($advance['notes'])): ?>
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9; font-size: 13px; color: #475569;">
                <strong>Voucher Notes:</strong> <?= nl2br(htmlspecialchars($advance['notes'])) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Drawdown Section: Allocate Available Advance to Open Invoices -->
    <?php if ($status === 'ACTIVE' && $remBalance > 0.001): ?>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Drawdown Against Open Invoices</h2>
                    <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">Allocate unapplied advance funds to open customer invoices.</p>
                </div>
                <div style="font-size: 13px; font-weight: 600; color: #16a34a;">
                    Available to Apply: ₹<span id="availBalance"><?= number_format($remBalance, 2, '.', '') ?></span>
                </div>
            </div>

            <!-- Inline error banner (Zero browser alerts) -->
            <div id="drawdownErrorBanner" style="display: none; margin: 16px 20px 0 20px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px;">
                <span id="drawdownErrorMessage"></span>
            </div>

            <?php if (empty($unpaidInvoices)): ?>
                <div style="padding: 32px; text-align: center; color: #94a3b8; font-size: 14px;">
                    This customer currently has no unpaid invoices with an outstanding balance.
                </div>
            <?php else: ?>
                <form id="drawdownForm" action="/payments/advances/<?= (int)$advance['id'] ?>/allocate" method="POST" style="padding: 20px;">
                    <?= csrf_field() ?>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">
                                <th style="padding: 10px 12px;">Invoice No</th>
                                <th style="padding: 10px 12px;">Date</th>
                                <th style="padding: 10px 12px; text-align: right;">Grand Total (₹)</th>
                                <th style="padding: 10px 12px; text-align: right;">Amount Due (₹)</th>
                                <th style="padding: 10px 12px; text-align: right; width: 180px;">Allocate Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unpaidInvoices as $inv): 
                                $due = (float)$inv['amount_due'];
                            ?>
                                <tr style="border-bottom: 1px solid #f8fafc;">
                                    <td style="padding: 12px; font-weight: 600; color: #2563eb;">
                                        <a href="/sales/invoices/<?= (int)$inv['id'] ?>/print" target="_blank" style="color: #2563eb; text-decoration: none;">
                                            <?= htmlspecialchars($inv['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px; color: #64748b;">
                                        <?= date('d M Y', strtotime($inv['invoice_date'])) ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right; color: #475569;">
                                        ₹<?= number_format((float)$inv['grand_total'], 2) ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right; font-weight: 600; color: #dc2626;">
                                        ₹<?= number_format($due, 2) ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right;">
                                        <input type="number" step="0.01" min="0" max="<?= $due ?>" 
                                               name="allocations[<?= (int)$inv['id'] ?>]" 
                                               data-due="<?= $due ?>" 
                                               class="drawdown-input" 
                                               placeholder="0.00" 
                                               style="width: 140px; text-align: right; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: 600;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="border-top: 2px solid #e2e8f0; font-weight: 600;">
                                <td colspan="4" style="padding: 14px 12px; text-align: right; font-size: 14px; color: #1e293b;">
                                    Total Selected to Allocate:
                                </td>
                                <td style="padding: 14px 12px; text-align: right; font-size: 15px; color: #2563eb;" id="totalAllocatedDisplay">
                                    ₹0.00
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
                        <button type="submit" id="submitDrawdownBtn" style="padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; background: #16a34a; color: #fff; border: none; cursor: pointer;">
                            Apply Drawdown to Invoices
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Past Drawdown Allocations Table -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">Drawdown History &amp; Applied Documents</h2>
        </div>

        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">
                    <th style="padding: 12px 20px;">Allocation Date</th>
                    <th style="padding: 12px 20px;">Invoice Number</th>
                    <th style="padding: 12px 20px; text-align: right;">Allocated Amount (₹)</th>
                    <th style="padding: 12px 20px; text-align: right;">Invoice Remaining Due (₹)</th>
                    <th style="padding: 12px 20px;">Applied By</th>
                    <th style="padding: 12px 20px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allocations)): ?>
                    <tr>
                        <td colspan="6" style="padding: 36px; text-align: center; color: #94a3b8;">
                            No drawdown allocations have been applied yet for this advance.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allocations as $al): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">
                                <?= date('d M Y', strtotime($al['allocation_date'])) ?>
                            </td>
                            <td style="padding: 12px 20px; font-weight: 600; color: #2563eb;">
                                <?= htmlspecialchars($al['invoice_number'] ?? 'Invoice #' . $al['document_id']) ?>
                            </td>
                            <td style="padding: 12px 20px; text-align: right; font-weight: 700; color: #16a34a;">
                                ₹<?= number_format((float)$al['allocated_amount'], 2) ?>
                            </td>
                            <td style="padding: 12px 20px; text-align: right; color: #64748b;">
                                ₹<?= number_format((float)($al['amount_due'] ?? 0), 2) ?>
                            </td>
                            <td style="padding: 12px 20px; color: #64748b;">
                                <?= htmlspecialchars($al['created_by'] ?? 'System') ?>
                            </td>
                            <td style="padding: 12px 20px; text-align: right;">
                                <a href="/sales/invoices/<?= (int)$al['document_id'] ?>/print" target="_blank" style="color: #2563eb; text-decoration: none; font-size: 12px; font-weight: 500;">
                                    View Invoice
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
var maxAvail = <?= json_encode($remBalance) ?>;
var inputs = document.querySelectorAll('.drawdown-input');

function recalculateAllocations() {
    var total = 0;
    inputs.forEach(function(inp) {
        var v = parseFloat(inp.value) || 0;
        total += v;
    });

    var disp = document.getElementById('totalAllocatedDisplay');
    if (disp) {
        disp.textContent = '₹' + total.toFixed(2);
    }
    return total;
}

inputs.forEach(function(inp) {
    inp.addEventListener('input', function() {
        var errBanner = document.getElementById('drawdownErrorBanner');
        var errMsg = document.getElementById('drawdownErrorMessage');
        var total = recalculateAllocations();

        if (total > maxAvail + 0.001) {
            errMsg.textContent = 'Total allocations (₹' + total.toFixed(2) + ') exceed available advance balance of ₹' + maxAvail.toFixed(2) + '.';
            errBanner.style.display = 'block';
        } else {
            errBanner.style.display = 'none';
        }
    });
});

var form = document.getElementById('drawdownForm');
if (form) {
    form.addEventListener('submit', function(e) {
        var errBanner = document.getElementById('drawdownErrorBanner');
        var errMsg = document.getElementById('drawdownErrorMessage');
        var total = recalculateAllocations();

        if (total <= 0) {
            e.preventDefault();
            errMsg.textContent = 'Please allocate an amount greater than zero to at least one invoice.';
            errBanner.style.display = 'block';
            return;
        }

        if (total > maxAvail + 0.001) {
            e.preventDefault();
            errMsg.textContent = 'Total allocations (₹' + total.toFixed(2) + ') exceed available advance balance of ₹' + maxAvail.toFixed(2) + '.';
            errBanner.style.display = 'block';
            return;
        }

        errBanner.style.display = 'none';
    });
}
</script>

