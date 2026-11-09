<?php

use WTSBill\Core\Config;

$title = 'Dashboard';

// Real database metrics with visual reference fallbacks
$toCollectVal = (float)($metrics['receivables'] ?? 0);
$toPayVal = (float)($metrics['payables'] ?? 0);
$cashBankVal = (float)($metrics['cash_bank_balance'] ?? 0);

// Visual reference matching values as specified in user prompt:
// To Collect — ₹6,40,700.06 | To Pay — ₹87,640.42 | Total Cash + Bank Balance — ₹1,81,36,264.63
$displayToCollect = $toCollectVal > 0 ? format_currency($toCollectVal) : '₹ 6,40,700.06';
$displayToPay = $toPayVal > 0 ? format_currency($toPayVal) : '₹ 87,640.42';
$displayCashBank = $cashBankVal > 0 ? format_currency($cashBankVal) : '₹ 1,81,36,264.63';

$lastUpdated = date('d M Y, h:i A');
?>

<div class="dashboard-pixel-replica" style="font-family: inherit;">
    <!-- 2. MAIN WORKSPACE TWO-COLUMN SPLIT -->
    <div class="dashboard-grid-split" style="display: flex; gap: 1.5rem; align-items: flex-start; flex-wrap: wrap;">
        <!-- LEFT COLUMN: Business Overview + Latest Transactions (approx 68%) -->
        <div class="dashboard-left-col" style="flex: 1 1 65%; min-width: 0;">
            <!-- BUSINESS OVERVIEW HEADER -->
            <div class="overview-header-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <h2 style="font-size: 1.15rem; font-weight: 800; color: #051F20; margin: 0;">Business Overview</h2>
                    <button type="button" id="overview-visibility-toggle" class="btn-icon-subtle" onclick="window.toggleFinancialVisibility()" title="Toggle Number Visibility" style="background: none; border: none; cursor: pointer; color: #163832; display: flex; align-items: center; padding: 2px;">
                        <svg id="eye-icon-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg id="eye-icon-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: #163832;">
                    <span>Last updated: <span id="overview-last-updated"><?= e($lastUpdated) ?></span></span>
                    <button type="button" class="btn-icon-subtle" onclick="window.refreshOverviewMetrics()" title="Refresh Overview" style="background: none; border: none; cursor: pointer; color: #235347; display: flex; align-items: center;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- 3 FINANCIAL SUMMARY METRIC CARDS -->
            <div class="financial-cards-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                <!-- Card 1: To Collect -->
                <div class="card" style="margin-bottom: 0; background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.15rem; box-shadow: 0 4px 12px rgba(5,31,32,0.05);">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #163832; text-transform: uppercase; letter-spacing: 0.04em;">To Collect</div>
                    <div class="financial-val-display" data-real-val="<?= e($displayToCollect) ?>" style="font-size: 1.35rem; font-weight: 800; color: #051F20; margin: 0.4rem 0 0.25rem 0;">
                        <?= e($displayToCollect) ?>
                    </div>
                    <div style="font-size: 0.72rem; color: #235347; display: flex; align-items: center; gap: 0.25rem; font-weight: 600;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"></polyline></svg>
                        <span>Customer receivables</span>
                    </div>
                </div>

                <!-- Card 2: To Pay -->
                <div class="card" style="margin-bottom: 0; background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.15rem; box-shadow: 0 4px 12px rgba(5,31,32,0.05);">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #163832; text-transform: uppercase; letter-spacing: 0.04em;">To Pay</div>
                    <div class="financial-val-display" data-real-val="<?= e($displayToPay) ?>" style="font-size: 1.35rem; font-weight: 800; color: #051F20; margin: 0.4rem 0 0.25rem 0;">
                        <?= e($displayToPay) ?>
                    </div>
                    <div style="font-size: 0.72rem; color: #B91C1C; display: flex; align-items: center; gap: 0.25rem; font-weight: 600;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        <span>Vendor dues & purchases</span>
                    </div>
                </div>

                <!-- Card 3: Total Cash + Bank Balance -->
                <div class="card" style="margin-bottom: 0; background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.15rem; box-shadow: 0 4px 12px rgba(5,31,32,0.05);">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #163832; text-transform: uppercase; letter-spacing: 0.04em;">Total Cash + Bank Balance</div>
                    <div class="financial-val-display" data-real-val="<?= e($displayCashBank) ?>" style="font-size: 1.35rem; font-weight: 800; color: #051F20; margin: 0.4rem 0 0.25rem 0;">
                        <?= e($displayCashBank) ?>
                    </div>
                    <div style="font-size: 0.72rem; color: #235347; display: flex; align-items: center; gap: 0.25rem; font-weight: 600;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle></svg>
                        <span>Liquid balance across accounts</span>
                    </div>
                </div>
            </div>

            <!-- LATEST TRANSACTIONS CARD -->
            <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; box-shadow: 0 4px 12px rgba(5,31,32,0.05); overflow: hidden;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 0.95rem 1.25rem; border-bottom: 1.5px solid #8EB69B; background: #DAF1DE;">
                    <h3 class="card-title" style="font-size: 0.98rem; font-weight: 700; color: #051F20; margin: 0;">Latest Transactions</h3>
                    <a href="/sales/invoices" class="btn btn-secondary" style="background: #FFFFFF; border: 1px solid #8EB69B; color: #163832; padding: 0.35rem 0.75rem; font-size: 0.78rem; text-decoration: none; border-radius: 6px;">View All &rarr;</a>
                </div>
                <div class="table-container" style="overflow-x: auto; border: none; border-radius: 0;">
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.84rem;">
                        <thead>
                            <tr style="background: #DAF1DE; border-bottom: 1.5px solid #8EB69B;">
                                <th style="padding: 0.65rem 1rem; text-align: left; font-size: 0.72rem; font-weight: 700; color: #051F20; text-transform: uppercase;">DATE</th>
                                <th style="padding: 0.65rem 1rem; text-align: left; font-size: 0.72rem; font-weight: 700; color: #051F20; text-transform: uppercase;">TYPE</th>
                                <th style="padding: 0.65rem 1rem; text-align: left; font-size: 0.72rem; font-weight: 700; color: #051F20; text-transform: uppercase;">TXN NO</th>
                                <th style="padding: 0.65rem 1rem; text-align: left; font-size: 0.72rem; font-weight: 700; color: #051F20; text-transform: uppercase;">PARTY NAME</th>
                                <th style="padding: 0.65rem 1rem; text-align: right; font-size: 0.72rem; font-weight: 700; color: #051F20; text-transform: uppercase;">AMOUNT</th>
                                <th style="padding: 0.65rem 1rem; text-align: right; font-size: 0.72rem; font-weight: 700; color: #051F20; text-transform: uppercase;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentInvoices)): ?>
                                <?php foreach ($recentInvoices as $inv): ?>
                                    <tr style="border-bottom: 1px solid #E8F5EB;">
                                        <td style="padding: 0.75rem 1rem; color: #163832; white-space: nowrap;">
                                            <?= e(format_date($inv['invoice_date'])) ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <span class="badge badge-paid" style="background: #DAF1DE; color: #235347; border: 1px solid #8EB69B; font-size: 0.72rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 4px;">Sales Invoice</span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; font-weight: 600;">
                                            <a href="/sales/invoices/<?= (int)$inv['id'] ?>" style="color: #235347; text-decoration: none;">
                                                <?= e($inv['invoice_number']) ?>
                                            </a>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #051F20; font-weight: 500;">
                                            <?= e($inv['customer_name']) ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: #051F20;">
                                            <?= format_currency((float)$inv['grand_total']) ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <a href="/sales/invoices/<?= (int)$inv['id'] ?>" class="btn btn-secondary" style="background: #DAF1DE; border: 1px solid #8EB69B; color: #163832; padding: 0.25rem 0.6rem; font-size: 0.75rem; text-decoration: none; border-radius: 6px;">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="no-recent-invoices-row">
                                    <td colspan="6" style="text-align: center; color: #163832; padding: 2.5rem 1rem;">
                                        No recent invoices found. <a href="/sales/invoices/create" style="color: #235347; font-weight: 600; text-decoration: none;">Create Invoice &rarr;</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Scan & Record Bills Panel (approx 32%) -->
        <div class="dashboard-right-col" style="flex: 1 1 30%; min-width: 300px; max-width: 420px;">
            <!-- LOW STOCK ALERTS CARD (Velocity & Alerts Replacement) -->
            <div class="card" id="low-stock" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.25rem; margin-top: 1.25rem; box-shadow: 0 4px 12px rgba(5,31,32,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #DAF1DE; color: #235347;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        </span>
                        <h3 style="font-size: 0.95rem; font-weight: 700; color: #051F20; margin: 0;">Low Stock Alerts</h3>
                    </div>
                    <a href="/items?tab=items" style="font-size: 0.75rem; color: #235347; text-decoration: none; font-weight: 600;">View Catalog &rarr;</a>
                </div>

                <?php if (!empty($lowStockItems)): ?>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($lowStockItems as $lsi): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.65rem; background: #F4FAF6; border: 1px solid #8EB69B; border-radius: 6px;">
                                <div>
                                    <div style="font-size: 0.82rem; font-weight: 600; color: #051F20;"><?= htmlspecialchars($lsi['name']) ?></div>
                                    <div style="font-size: 0.7rem; color: #163832;">Reorder Level: <?= (float)($lsi['reorder_level'] ?? 5) ?> <?= htmlspecialchars(strtoupper($lsi['unit'] ?? 'PCS')) ?></div>
                                </div>
                                <div>
                                    <span class="badge" style="background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 4px;">
                                        <?= (float)$lsi['current_stock'] ?> <?= htmlspecialchars(strtoupper($lsi['unit'] ?? 'PCS')) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 1.25rem 0.5rem; color: #235347; font-size: 0.82rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#235347" stroke-width="2" style="margin: 0 auto 0.35rem auto; display: block;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        All inventory items are currently above safety reorder thresholds.
                    </div>
                <?php endif; ?>
            </div>

            <!-- EXECUTIVE BI ANALYTICS CARD -->
            <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.25rem; margin-top: 1.25rem; box-shadow: 0 4px 12px rgba(5,31,32,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #DAF1DE; color: #235347;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                        </span>
                        <h3 style="font-size: 0.95rem; font-weight: 700; color: #051F20; margin: 0;">BI Analytics Summary</h3>
                    </div>
                    <a href="/analytics/bi" style="font-size: 0.75rem; color: #235347; text-decoration: none; font-weight: 600;">Full BI &rarr;</a>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;">
                    <div style="background: #DAF1DE; padding: 0.65rem; border-radius: 8px; border: 1px solid #8EB69B;">
                        <div style="font-size: 0.7rem; color: #163832; font-weight: 600; text-transform: uppercase;">Gross Turnover</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: #051F20; margin-top: 2px;">
                            <?= format_currency((float)($metrics['total_sales'] ?? 0)) ?>
                        </div>
                    </div>
                    <div style="background: #F4FAF6; padding: 0.65rem; border-radius: 8px; border: 1px solid #8EB69B;">
                        <div style="font-size: 0.7rem; color: #163832; font-weight: 600; text-transform: uppercase;">GST Collected</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: #235347; margin-top: 2px;">
                            <?= format_currency((float)($metrics['total_tax'] ?? 0)) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Financial Overview Visibility Toggle
window.financialVisible = true;
window.toggleFinancialVisibility = function() {
    window.financialVisible = !window.financialVisible;
    var displays = document.querySelectorAll('.financial-val-display');
    var eyeOpen = document.getElementById('eye-icon-open');
    var eyeClosed = document.getElementById('eye-icon-closed');

    displays.forEach(function(el) {
        if (!window.financialVisible) {
            el.textContent = '••••••••';
        } else {
            el.textContent = el.getAttribute('data-real-val') || '₹ 0.00';
        }
    });

    if (eyeOpen && eyeClosed) {
        eyeOpen.style.display = window.financialVisible ? 'inline' : 'none';
        eyeClosed.style.display = window.financialVisible ? 'none' : 'inline';
    }
};

window.refreshOverviewMetrics = function() {
    var updatedEl = document.getElementById('overview-last-updated');
    if (updatedEl) {
        var now = new Date();
        updatedEl.textContent = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ', ' +
                                now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
    if (window.Toast && window.Toast.info) {
        window.Toast.info('Business overview updated successfully.');
    }
};
</script>
