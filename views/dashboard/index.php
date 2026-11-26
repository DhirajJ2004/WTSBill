<?php

use WTSBill\Core\Config;

$title = 'Dashboard';

// Real database metrics with fallbacks
$totalSales = (float)($metrics['total_sales'] ?? 0);
$totalSalesCount = (int)($metrics['total_sales_count'] ?? 0);
$totalPurchases = (float)($metrics['total_purchases'] ?? 0);
$totalPurchasesCount = (int)($metrics['total_purchases_count'] ?? 0);
$totalReceivables = (float)($metrics['receivables'] ?? 0);
$totalPayables = (float)($metrics['payables'] ?? 0);
$totalCustomers = (int)($metrics['total_customers'] ?? 0);
$totalProducts = (int)($metrics['total_products'] ?? 0);

$lastUpdated = date('d M Y, h:i A');
?>

<div class="dashboard-root" style="padding: 1rem 0 2rem 0; font-family: inherit;">
    
    <!-- 1. TOP HEADER & QUICK ACTION BAR -->
    <div class="dashboard-header-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #2A485E; margin-bottom: 0.25rem;">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #355872;"></span>
                <span style="font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Enterprise Intelligence Hub</span>
                <span>•</span>
                <span id="dashboard-clock"><?= e($lastUpdated) ?></span>
            </div>
            <h1 style="font-size: 1.65rem; font-weight: 800; color: #1E293B; margin: 0; letter-spacing: -0.02em;">
                Executive Dashboard
            </h1>
        </div>

        <!-- Quick Action Shortcuts -->
        <div class="quick-actions-group" style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <a href="/sales/invoices/create" class="btn btn-primary" style="background: #355872; color: #FFFFFF; font-weight: 600; font-size: 0.82rem; padding: 0.55rem 0.95rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 4px 10px rgba(53, 88, 114, 0.25); border: 1px solid #2A485E;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>New Invoice</span>
            </a>
            <a href="/purchases/bills/create" class="btn btn-secondary" style="background: #FFFFFF; color: #2A485E; font-weight: 600; font-size: 0.82rem; padding: 0.55rem 0.85rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem; border: 1.5px solid #7AAACE;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                <span>Record Bill</span>
            </a>
            <a href="/parties?action=create" class="btn btn-secondary" style="background: #FFFFFF; color: #2A485E; font-weight: 600; font-size: 0.82rem; padding: 0.55rem 0.85rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem; border: 1.5px solid #7AAACE;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path></svg>
                <span>Add Party</span>
            </a>
            <a href="/items?tab=items" class="btn btn-secondary" style="background: #FFFFFF; color: #2A485E; font-weight: 600; font-size: 0.82rem; padding: 0.55rem 0.85rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem; border: 1.5px solid #7AAACE;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                <span>Catalog</span>
            </a>
            <button type="button" onclick="window.location.reload()" title="Refresh Dashboard Data" style="background: #EBF5FC; color: #2A485E; border: 1.5px solid #7AAACE; border-radius: 8px; padding: 0.55rem; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
            </button>
        </div>
    </div>

    <!-- 2. TOP 6 CORE SUMMARY METRIC CARDS -->
    <div class="summary-cards-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(185px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        
        <!-- CARD 1: Total Sales -->
        <div class="kpi-card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 14px; padding: 1.15rem; box-shadow: 0 4px 14px rgba(30, 41, 59, 0.04); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem;">
                <span style="font-size: 0.74rem; font-weight: 700; color: #2A485E; text-transform: uppercase; letter-spacing: 0.05em;">Total Sales</span>
                <span style="width: 32px; height: 32px; border-radius: 8px; background: #EBF5FC; color: #355872; display: flex; align-items: center; justify-content: center;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                </span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #1E293B; letter-spacing: -0.02em; line-height: 1.2;">
                <?= format_currency($totalSales) ?>
            </div>
            <div style="font-size: 0.72rem; color: #355872; font-weight: 600; margin-top: 0.45rem; display: flex; align-items: center; gap: 0.25rem;">
                <span style="background: #EBF5FC; padding: 0.1rem 0.35rem; border-radius: 4px; border: 1px solid #7AAACE;"><?= $totalSalesCount ?> invoices</span>
                <span>Active records</span>
            </div>
        </div>

        <!-- CARD 2: Total Purchases -->
        <div class="kpi-card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 14px; padding: 1.15rem; box-shadow: 0 4px 14px rgba(30, 41, 59, 0.04); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem;">
                <span style="font-size: 0.74rem; font-weight: 700; color: #2A485E; text-transform: uppercase; letter-spacing: 0.05em;">Total Purchases</span>
                <span style="width: 32px; height: 32px; border-radius: 8px; background: #EEF4F8; color: #355872; display: flex; align-items: center; justify-content: center;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                </span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #1E293B; letter-spacing: -0.02em; line-height: 1.2;">
                <?= format_currency($totalPurchases) ?>
            </div>
            <div style="font-size: 0.72rem; color: #2A485E; font-weight: 600; margin-top: 0.45rem; display: flex; align-items: center; gap: 0.25rem;">
                <span style="background: #EBF5FC; padding: 0.1rem 0.35rem; border-radius: 4px; border: 1px solid #7AAACE;"><?= $totalPurchasesCount ?> bills</span>
                <span>Vendor procurement</span>
            </div>
        </div>

        <!-- CARD 3: Total Receivables -->
        <div class="kpi-card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 14px; padding: 1.15rem; box-shadow: 0 4px 14px rgba(30, 41, 59, 0.04); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem;">
                <span style="font-size: 0.74rem; font-weight: 700; color: #2A485E; text-transform: uppercase; letter-spacing: 0.05em;">Receivables</span>
                <span style="width: 32px; height: 32px; border-radius: 8px; background: #EBF5FC; color: #355872; display: flex; align-items: center; justify-content: center;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                </span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #355872; letter-spacing: -0.02em; line-height: 1.2;">
                <?= format_currency($totalReceivables) ?>
            </div>
            <div style="font-size: 0.72rem; color: #355872; font-weight: 600; margin-top: 0.45rem; display: flex; align-items: center; gap: 0.25rem;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"></polyline></svg>
                <span>Customer balance due</span>
            </div>
        </div>

        <!-- CARD 4: Total Payables -->
        <div class="kpi-card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 14px; padding: 1.15rem; box-shadow: 0 4px 14px rgba(30, 41, 59, 0.04); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem;">
                <span style="font-size: 0.74rem; font-weight: 700; color: #2A485E; text-transform: uppercase; letter-spacing: 0.05em;">Payables</span>
                <span style="width: 32px; height: 32px; border-radius: 8px; background: #FFF5F5; color: #B91C1C; display: flex; align-items: center; justify-content: center;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #B91C1C; letter-spacing: -0.02em; line-height: 1.2;">
                <?= format_currency($totalPayables) ?>
            </div>
            <div style="font-size: 0.72rem; color: #B91C1C; font-weight: 600; margin-top: 0.45rem; display: flex; align-items: center; gap: 0.25rem;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                <span>Supplier dues pending</span>
            </div>
        </div>

        <!-- CARD 5: Total Customers -->
        <div class="kpi-card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 14px; padding: 1.15rem; box-shadow: 0 4px 14px rgba(30, 41, 59, 0.04); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem;">
                <span style="font-size: 0.74rem; font-weight: 700; color: #2A485E; text-transform: uppercase; letter-spacing: 0.05em;">Customers</span>
                <span style="width: 32px; height: 32px; border-radius: 8px; background: #EBF5FC; color: #355872; display: flex; align-items: center; justify-content: center;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path></svg>
                </span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #1E293B; letter-spacing: -0.02em; line-height: 1.2;">
                <?= $totalCustomers ?>
            </div>
            <div style="font-size: 0.72rem; color: #355872; font-weight: 600; margin-top: 0.45rem;">
                <span>Registered CRM accounts</span>
            </div>
        </div>

        <!-- CARD 6: Total Products -->
        <div class="kpi-card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 14px; padding: 1.15rem; box-shadow: 0 4px 14px rgba(30, 41, 59, 0.04); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem;">
                <span style="font-size: 0.74rem; font-weight: 700; color: #2A485E; text-transform: uppercase; letter-spacing: 0.05em;">Catalog Items</span>
                <span style="width: 32px; height: 32px; border-radius: 8px; background: #EBF5FC; color: #355872; display: flex; align-items: center; justify-content: center;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                </span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #1E293B; letter-spacing: -0.02em; line-height: 1.2;">
                <?= $totalProducts ?>
            </div>
            <div style="font-size: 0.72rem; color: #2A485E; font-weight: 600; margin-top: 0.45rem;">
                <span>Active SKU stock items</span>
            </div>
        </div>
    </div>

    <!-- 3. INTERACTIVE 4-DONUT CHARTS SECTION (100% REAL DATABASE VALUES) -->
    <div style="margin-bottom: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.95rem;">
            <div>
                <h2 style="font-size: 1.15rem; font-weight: 800; color: #1E293B; margin: 0;">Financial Distribution &amp; Status Analytics</h2>
                <p style="font-size: 0.78rem; color: #2A485E; margin: 0.15rem 0 0 0;">Real-time database analytics for payments, payables, expenses, and receivables aging.</p>
            </div>
            <span style="font-size: 0.74rem; font-weight: 700; color: #355872; background: #EBF5FC; padding: 0.25rem 0.65rem; border-radius: 6px; border: 1px solid #7AAACE;">
                Live DB Sync
            </span>
        </div>

        <div class="donut-charts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
            
            <!-- DONUT 1: Sales Payment Status -->
            <?php
                $sPaid = (float)($chartSalesStatus['PAID']['amount'] ?? 0);
                $sPartial = (float)($chartSalesStatus['PARTIAL']['amount'] ?? 0);
                $sPending = (float)($chartSalesStatus['PENDING']['amount'] ?? 0);
                $sTotal = $sPaid + $sPartial + $sPending;
                $sPaidPct = $sTotal > 0 ? round(($sPaid / $sTotal) * 100) : 0;
                $sPartPct = $sTotal > 0 ? round(($sPartial / $sTotal) * 100) : 0;
                $sPendPct = $sTotal > 0 ? round(($sPending / $sTotal) * 100) : 0;
            ?>
            <div class="chart-card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 15px rgba(30, 41, 59, 0.05);">
                <div style="font-size: 0.88rem; font-weight: 700; color: #1E293B; margin-bottom: 0.2rem;">Sales Payment Status</div>
                <div style="font-size: 0.72rem; color: #2A485E; margin-bottom: 0.85rem;">Invoice realization ratio</div>

                <div class="donut-wrap" style="position: relative; width: 160px; height: 160px; margin: 0 auto 1rem auto;">
                    <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg); border-radius: 50%;">
                        <!-- Background ring -->
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#EEF4F8" stroke-width="4.2"></circle>
                        <!-- Paid Segment (#355872) -->
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#355872" stroke-width="4.2" stroke-dasharray="<?= $sPaidPct ?> <?= 100 - $sPaidPct ?>" stroke-dashoffset="0"></circle>
                        <!-- Partial Segment (#7AAACE) -->
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#7AAACE" stroke-width="4.2" stroke-dasharray="<?= $sPartPct ?> <?= 100 - $sPartPct ?>" stroke-dashoffset="-<?= $sPaidPct ?>"></circle>
                        <!-- Pending Segment (#F87171) -->
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#F87171" stroke-width="4.2" stroke-dasharray="<?= $sPendPct ?> <?= 100 - $sPendPct ?>" stroke-dashoffset="-<?= $sPaidPct + $sPartPct ?>"></circle>
                    </svg>
                    <!-- Center Metric -->
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                        <div style="font-size: 0.7rem; font-weight: 600; color: #2A485E; text-transform: uppercase;">Total</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: #1E293B;"><?= format_currency($sTotal) ?></div>
                    </div>
                </div>

                <!-- Legend Pills -->
                <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 0.4rem; color: #2A485E;">
                            <span style="width: 10px; height: 10px; border-radius: 3px; background: #355872;"></span> Paid
                        </span>
                        <span style="font-weight: 700; color: #1E293B;"><?= format_currency($sPaid) ?> (<?= $sPaidPct ?>%)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 0.4rem; color: #2A485E;">
                            <span style="width: 10px; height: 10px; border-radius: 3px; background: #7AAACE;"></span> Partial
                        </span>
                        <span style="font-weight: 700; color: #1E293B;"><?= format_currency($sPartial) ?> (<?= $sPartPct ?>%)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 0.4rem; color: #2A485E;">
                            <span style="width: 10px; height: 10px; border-radius: 3px; background: #F87171;"></span> Pending
                        </span>
                        <span style="font-weight: 700; color: #1E293B;"><?= format_currency($sPending) ?> (<?= $sPendPct ?>%)</span>
                    </div>
                </div>
            </div>

            <!-- DONUT 2: Purchase Payment Status -->
            <?php
                $pPaid = (float)($chartPurchaseStatus['PAID']['amount'] ?? 0);
                $pPartial = (float)($chartPurchaseStatus['PARTIAL']['amount'] ?? 0);
                $pPending = (float)($chartPurchaseStatus['PENDING']['amount'] ?? 0);
                $pTotal = $pPaid + $pPartial + $pPending;
                $pPaidPct = $pTotal > 0 ? round(($pPaid / $pTotal) * 100) : 0;
                $pPartPct = $pTotal > 0 ? round(($pPartial / $pTotal) * 100) : 0;
                $pPendPct = $pTotal > 0 ? round(($pPending / $pTotal) * 100) : 0;
            ?>
            <div class="chart-card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 15px rgba(30, 41, 59, 0.05);">
                <div style="font-size: 0.88rem; font-weight: 700; color: #1E293B; margin-bottom: 0.2rem;">Purchase Bill Status</div>
                <div style="font-size: 0.72rem; color: #2A485E; margin-bottom: 0.85rem;">Accounts payable settlement</div>

                <div class="donut-wrap" style="position: relative; width: 160px; height: 160px; margin: 0 auto 1rem auto;">
                    <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg); border-radius: 50%;">
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#EEF4F8" stroke-width="4.2"></circle>
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#2A485E" stroke-width="4.2" stroke-dasharray="<?= $pPaidPct ?> <?= 100 - $pPaidPct ?>" stroke-dashoffset="0"></circle>
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#7AAACE" stroke-width="4.2" stroke-dasharray="<?= $pPartPct ?> <?= 100 - $pPartPct ?>" stroke-dashoffset="-<?= $pPaidPct ?>"></circle>
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#FCA5A5" stroke-width="4.2" stroke-dasharray="<?= $pPendPct ?> <?= 100 - $pPendPct ?>" stroke-dashoffset="-<?= $pPaidPct + $pPartPct ?>"></circle>
                    </svg>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                        <div style="font-size: 0.7rem; font-weight: 600; color: #2A485E; text-transform: uppercase;">Total</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: #1E293B;"><?= format_currency($pTotal) ?></div>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 0.4rem; color: #2A485E;">
                            <span style="width: 10px; height: 10px; border-radius: 3px; background: #2A485E;"></span> Settled
                        </span>
                        <span style="font-weight: 700; color: #1E293B;"><?= format_currency($pPaid) ?> (<?= $pPaidPct ?>%)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 0.4rem; color: #2A485E;">
                            <span style="width: 10px; height: 10px; border-radius: 3px; background: #7AAACE;"></span> Partial
                        </span>
                        <span style="font-weight: 700; color: #1E293B;"><?= format_currency($pPartial) ?> (<?= $pPartPct ?>%)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 0.4rem; color: #2A485E;">
                            <span style="width: 10px; height: 10px; border-radius: 3px; background: #FCA5A5;"></span> Dues
                        </span>
                        <span style="font-weight: 700; color: #1E293B;"><?= format_currency($pPending) ?> (<?= $pPendPct ?>%)</span>
                    </div>
                </div>
            </div>

            <!-- DONUT 3: Expense Breakdown -->
            <?php
                $expTotal = array_sum(array_column($chartExpenseBreakdown ?? [], 'amount'));
                $expColors = ['#355872', '#7AAACE', '#2A485E', '#253E52', '#EBF5FC'];
            ?>
            <div class="chart-card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 15px rgba(30, 41, 59, 0.05);">
                <div style="font-size: 0.88rem; font-weight: 700; color: #1E293B; margin-bottom: 0.2rem;">Expense Distribution</div>
                <div style="font-size: 0.72rem; color: #2A485E; margin-bottom: 0.85rem;">Top categories by expenditure</div>

                <div class="donut-wrap" style="position: relative; width: 160px; height: 160px; margin: 0 auto 1rem auto;">
                    <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg); border-radius: 50%;">
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#EEF4F8" stroke-width="4.2"></circle>
                        <?php
                            $accumOffset = 0;
                            if (!empty($chartExpenseBreakdown)):
                                foreach ($chartExpenseBreakdown as $idx => $exp):
                                    $ePct = (float)$exp['percent'];
                                    $col = $expColors[$idx % count($expColors)];
                        ?>
                            <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="<?= $col ?>" stroke-width="4.2" stroke-dasharray="<?= $ePct ?> <?= 100 - $ePct ?>" stroke-dashoffset="-<?= $accumOffset ?>"></circle>
                        <?php
                                    $accumOffset += $ePct;
                                endforeach;
                            endif;
                        ?>
                    </svg>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                        <div style="font-size: 0.7rem; font-weight: 600; color: #2A485E; text-transform: uppercase;">Total</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: #1E293B;"><?= format_currency($expTotal) ?></div>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.75rem;">
                    <?php if (!empty($chartExpenseBreakdown)): ?>
                        <?php foreach (array_slice($chartExpenseBreakdown, 0, 3) as $idx => $exp): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="display: flex; align-items: center; gap: 0.4rem; color: #2A485E;">
                                    <span style="width: 10px; height: 10px; border-radius: 3px; background: <?= $expColors[$idx % count($expColors)] ?>;"></span>
                                    <span style="max-width: 110px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($exp['category']) ?></span>
                                </span>
                                <span style="font-weight: 700; color: #1E293B;"><?= format_currency((float)$exp['amount']) ?> (<?= $exp['percent'] ?>%)</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="color: #7AAACE; text-align: center; padding: 0.5rem 0;">No expense records logged yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DONUT 4: Receivables Status -->
            <?php
                $rPaid = (float)($chartReceivablesStatus['paid'] ?? 0);
                $rCurrent = (float)($chartReceivablesStatus['outstanding'] ?? 0);
                $rOverdue = (float)($chartReceivablesStatus['overdue'] ?? 0);
                $rTotal = $rPaid + $rCurrent + $rOverdue;
                $rPaidPct = $rTotal > 0 ? round(($rPaid / $rTotal) * 100) : 0;
                $rCurrPct = $rTotal > 0 ? round(($rCurrent / $rTotal) * 100) : 0;
                $rOverPct = $rTotal > 0 ? round(($rOverdue / $rTotal) * 100) : 0;
            ?>
            <div class="chart-card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 15px rgba(30, 41, 59, 0.05);">
                <div style="font-size: 0.88rem; font-weight: 700; color: #1E293B; margin-bottom: 0.2rem;">Receivables Aging Status</div>
                <div style="font-size: 0.72rem; color: #2A485E; margin-bottom: 0.85rem;">Cash collection risk profile</div>

                <div class="donut-wrap" style="position: relative; width: 160px; height: 160px; margin: 0 auto 1rem auto;">
                    <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg); border-radius: 50%;">
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#EEF4F8" stroke-width="4.2"></circle>
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#355872" stroke-width="4.2" stroke-dasharray="<?= $rPaidPct ?> <?= 100 - $rPaidPct ?>" stroke-dashoffset="0"></circle>
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#7AAACE" stroke-width="4.2" stroke-dasharray="<?= $rCurrPct ?> <?= 100 - $rCurrPct ?>" stroke-dashoffset="-<?= $rPaidPct ?>"></circle>
                        <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#EF4444" stroke-width="4.2" stroke-dasharray="<?= $rOverPct ?> <?= 100 - $rOverPct ?>" stroke-dashoffset="-<?= $rPaidPct + $rCurrPct ?>"></circle>
                    </svg>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                        <div style="font-size: 0.7rem; font-weight: 600; color: #2A485E; text-transform: uppercase;">Outstanding</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: #1E293B;"><?= format_currency($rCurrent + $rOverdue) ?></div>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 0.4rem; color: #2A485E;">
                            <span style="width: 10px; height: 10px; border-radius: 3px; background: #355872;"></span> Collected
                        </span>
                        <span style="font-weight: 700; color: #1E293B;"><?= format_currency($rPaid) ?> (<?= $rPaidPct ?>%)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 0.4rem; color: #2A485E;">
                            <span style="width: 10px; height: 10px; border-radius: 3px; background: #7AAACE;"></span> Current
                        </span>
                        <span style="font-weight: 700; color: #1E293B;"><?= format_currency($rCurrent) ?> (<?= $rCurrPct ?>%)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 0.4rem; color: #2A485E;">
                            <span style="width: 10px; height: 10px; border-radius: 3px; background: #EF4444;"></span> Overdue
                        </span>
                        <span style="font-weight: 700; color: #B91C1C;"><?= format_currency($rOverdue) ?> (<?= $rOverPct ?>%)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. MIDDLE TWO-COLUMN CONTENT GRID (68% / 32%) -->
    <div class="dashboard-main-split" style="display: flex; gap: 1.5rem; align-items: flex-start; flex-wrap: wrap;">
        
        <!-- LEFT COLUMN: Sales Trend + Recent Invoices -->
        <div class="split-left" style="flex: 1 1 65%; min-width: 0;">
            
            <!-- MONTHLY SALES REVENUE TREND -->
            <div class="card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 16px; padding: 1.35rem; margin-bottom: 1.5rem; box-shadow: 0 4px 15px rgba(30, 41, 59, 0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <div>
                        <h3 style="font-size: 1rem; font-weight: 800; color: #1E293B; margin: 0;">Monthly Sales Trend</h3>
                        <p style="font-size: 0.74rem; color: #2A485E; margin: 0.15rem 0 0 0;">Gross invoice turnover performance across billing cycles</p>
                    </div>
                    <a href="/reports/gst-sales-register" style="font-size: 0.78rem; font-weight: 600; color: #355872; text-decoration: none;">Sales Register &rarr;</a>
                </div>

                <?php
                    $maxMonthVal = 1000.0;
                    if (!empty($salesTrends)) {
                        foreach ($salesTrends as $st) {
                            if ((float)$st['total_revenue'] > $maxMonthVal) $maxMonthVal = (float)$st['total_revenue'];
                        }
                    }
                ?>
                <div style="display: flex; align-items: flex-end; justify-content: space-between; height: 140px; border-bottom: 1.5px solid #EEF4F8; padding-bottom: 0.5rem; gap: 0.85rem;">
                    <?php if (!empty($salesTrends)): ?>
                        <?php foreach ($salesTrends as $st): 
                            $barHeight = $maxMonthVal > 0 ? max(12, round(((float)$st['total_revenue'] / $maxMonthVal) * 110)) : 12;
                        ?>
                            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px;">
                                <div title="<?= e($st['month_label']) ?>: <?= format_currency((float)$st['total_revenue']) ?>" 
                                     style="width: 100%; max-width: 38px; height: <?= (int)$barHeight ?>px; background: linear-gradient(180deg, #355872 0%, #2A485E 100%); border-radius: 6px 6px 0 0; transition: height 0.3s ease, background 0.2s ease; cursor: pointer;">
                                </div>
                                <span style="font-size: 0.7rem; color: #2A485E; font-weight: 600; white-space: nowrap;"><?= e($st['month_label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; width: 100%; color: #7AAACE; font-size: 0.82rem; padding: 2rem 0;">No sales history available yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RECENT TAX INVOICES TABLE -->
            <div class="card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(30, 41, 59, 0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1.5px solid #7AAACE; background: #EBF5FC;">
                    <div>
                        <h3 style="font-size: 0.96rem; font-weight: 800; color: #1E293B; margin: 0;">Recent Commercial Invoices</h3>
                    </div>
                    <a href="/sales/invoices" style="background: #FFFFFF; border: 1px solid #7AAACE; color: #2A485E; padding: 0.35rem 0.75rem; font-size: 0.78rem; text-decoration: none; border-radius: 6px; font-weight: 600;">View All &rarr;</a>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.82rem;">
                        <thead>
                            <tr style="background: #F7F8F0; border-bottom: 1.5px solid #EEF4F8;">
                                <th style="padding: 0.65rem 1rem; text-align: left; font-size: 0.7rem; font-weight: 700; color: #2A485E; text-transform: uppercase;">DATE</th>
                                <th style="padding: 0.65rem 1rem; text-align: left; font-size: 0.7rem; font-weight: 700; color: #2A485E; text-transform: uppercase;">INVOICE #</th>
                                <th style="padding: 0.65rem 1rem; text-align: left; font-size: 0.7rem; font-weight: 700; color: #2A485E; text-transform: uppercase;">CUSTOMER</th>
                                <th style="padding: 0.65rem 1rem; text-align: right; font-size: 0.7rem; font-weight: 700; color: #2A485E; text-transform: uppercase;">AMOUNT</th>
                                <th style="padding: 0.65rem 1rem; text-align: center; font-size: 0.7rem; font-weight: 700; color: #2A485E; text-transform: uppercase;">STATUS</th>
                                <th style="padding: 0.65rem 1rem; text-align: right; font-size: 0.7rem; font-weight: 700; color: #2A485E; text-transform: uppercase;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentInvoices)): ?>
                                <?php foreach ($recentInvoices as $inv): 
                                    $st = strtoupper((string)($inv['status'] ?? ''));
                                    $bgBadge = '#EBF5FC'; $colBadge = '#355872';
                                    if ($st === 'PAID') { $bgBadge = '#EBF5FC'; $colBadge = '#355872'; }
                                    elseif ($st === 'PARTIAL' || $st === 'PARTIALLY_PAID') { $bgBadge = '#FEF3C7'; $colBadge = '#92400E'; }
                                    elseif ($st === 'CANCELLED') { $bgBadge = '#FEE2E2'; $colBadge = '#991B1B'; }
                                    else { $bgBadge = '#E0F2FE'; $colBadge = '#0369A1'; }
                                ?>
                                    <tr style="border-bottom: 1px solid #EEF4F8;">
                                        <td style="padding: 0.75rem 1rem; color: #2A485E; white-space: nowrap;">
                                            <?= e(format_date($inv['invoice_date'])) ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; font-weight: 700;">
                                            <a href="/sales/invoices/<?= (int)$inv['id'] ?>" style="color: #355872; text-decoration: none;">
                                                <?= e($inv['invoice_number']) ?>
                                            </a>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #1E293B; font-weight: 600;">
                                            <?= e($inv['customer_name']) ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: #1E293B;">
                                            <?= format_currency((float)$inv['grand_total']) ?>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <span style="background: <?= $bgBadge ?>; color: <?= $colBadge ?>; font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.55rem; border-radius: 9999px;">
                                                <?= e($st) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <a href="/sales/invoices/<?= (int)$inv['id'] ?>" style="background: #EBF5FC; border: 1px solid #7AAACE; color: #2A485E; padding: 0.25rem 0.6rem; font-size: 0.75rem; text-decoration: none; border-radius: 6px; font-weight: 600;">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #2A485E; padding: 2.5rem 1rem;">
                                        No recent invoices recorded.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Quotations, Payments, Low-Stock Alerts -->
        <div class="split-right" style="flex: 1 1 30%; min-width: 290px; max-width: 390px; display: flex; flex-direction: column; gap: 1.25rem;">
            
            <!-- RECENT QUOTATIONS -->
            <div class="card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 16px; padding: 1.15rem; box-shadow: 0 4px 15px rgba(30, 41, 59, 0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.45rem;">
                        <span style="width: 26px; height: 26px; border-radius: 6px; background: #EBF5FC; color: #355872; display: flex; align-items: center; justify-content: center;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path></svg>
                        </span>
                        <h4 style="font-size: 0.88rem; font-weight: 800; color: #1E293B; margin: 0;">Recent Quotations</h4>
                    </div>
                    <a href="/sales/quotations" style="font-size: 0.74rem; font-weight: 600; color: #355872; text-decoration: none;">View &rarr;</a>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php if (!empty($recentQuotations)): ?>
                        <?php foreach ($recentQuotations as $q): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.45rem 0.6rem; background: #F7F8F0; border: 1px solid #EEF4F8; border-radius: 8px;">
                                <div>
                                    <div style="font-size: 0.8rem; font-weight: 700; color: #1E293B;"><?= e($q['quotation_number']) ?></div>
                                    <div style="font-size: 0.68rem; color: #2A485E;"><?= e($q['customer_name']) ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.82rem; font-weight: 800; color: #355872;"><?= format_currency((float)$q['grand_total']) ?></div>
                                    <span style="font-size: 0.65rem; font-weight: 700; color: #2A485E;"><?= e($q['status']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="font-size: 0.78rem; color: #7AAACE; text-align: center; padding: 0.8rem 0;">No pending quotations.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RECENT PAYMENTS -->
            <div class="card" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 16px; padding: 1.15rem; box-shadow: 0 4px 15px rgba(30, 41, 59, 0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.45rem;">
                        <span style="width: 26px; height: 26px; border-radius: 6px; background: #EBF5FC; color: #355872; display: flex; align-items: center; justify-content: center;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                        </span>
                        <h4 style="font-size: 0.88rem; font-weight: 800; color: #1E293B; margin: 0;">Recent Receipts</h4>
                    </div>
                    <a href="/payments" style="font-size: 0.74rem; font-weight: 600; color: #355872; text-decoration: none;">View &rarr;</a>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php if (!empty($recentPayments)): ?>
                        <?php foreach ($recentPayments as $pay): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.45rem 0.6rem; background: #F7F8F0; border: 1px solid #EEF4F8; border-radius: 8px;">
                                <div>
                                    <div style="font-size: 0.8rem; font-weight: 700; color: #1E293B;"><?= e($pay['payment_number'] ?? $pay['receipt_number']) ?></div>
                                    <div style="font-size: 0.68rem; color: #2A485E;"><?= e($pay['payment_mode']) ?> • <?= e(format_date($pay['payment_date'])) ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.82rem; font-weight: 800; color: #355872;"><?= format_currency((float)$pay['amount']) ?></div>
                                    <span style="font-size: 0.65rem; font-weight: 700; color: #355872;">SETTLED</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="font-size: 0.78rem; color: #7AAACE; text-align: center; padding: 0.8rem 0;">No recent receipts logged.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LOW STOCK ALERTS -->
            <div class="card" id="low-stock" style="background: #FFFFFF; border: 1.5px solid #7AAACE; border-radius: 16px; padding: 1.15rem; box-shadow: 0 4px 15px rgba(30, 41, 59, 0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.45rem;">
                        <span style="width: 26px; height: 26px; border-radius: 6px; background: #FFF5F5; color: #B91C1C; display: flex; align-items: center; justify-content: center;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>
                        </span>
                        <h4 style="font-size: 0.88rem; font-weight: 800; color: #1E293B; margin: 0;">Stock Alerts</h4>
                    </div>
                    <a href="/items?tab=items" style="font-size: 0.74rem; font-weight: 600; color: #355872; text-decoration: none;">Stock &rarr;</a>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php if (!empty($lowStockItems)): ?>
                        <?php foreach ($lowStockItems as $lsi): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.45rem 0.6rem; background: #FFF5F5; border: 1px solid #FCA5A5; border-radius: 8px;">
                                <div>
                                    <div style="font-size: 0.8rem; font-weight: 700; color: #1E293B; max-width: 170px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($lsi['name']) ?></div>
                                    <div style="font-size: 0.68rem; color: #2A485E;">Reorder: <?= (float)($lsi['reorder_level'] ?? 5) ?> <?= htmlspecialchars(strtoupper($lsi['unit'] ?? 'PCS')) ?></div>
                                </div>
                                <div>
                                    <span style="background: #FEE2E2; color: #991B1B; font-size: 0.72rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 4px;">
                                        <?= (float)$lsi['current_stock'] ?> <?= htmlspecialchars(strtoupper($lsi['unit'] ?? 'PCS')) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; color: #355872; font-size: 0.78rem; padding: 0.8rem 0;">
                            All catalog stock levels are healthy.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
