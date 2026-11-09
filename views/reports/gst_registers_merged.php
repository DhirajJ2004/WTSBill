<?php
require_once dirname(__DIR__, 2) . '/includes/tabs.php';

$title = 'GST Registers (Sales & Purchase)';
$activeTab = $activeTab ?? (string)($_GET['tab'] ?? 'sales');

$tabs = [
    [
        'id' => 'sales',
        'label' => 'GST Sales Register',
        'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>',
    ],
    [
        'id' => 'purchases',
        'label' => 'GST Purchase Register',
        'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/></svg>',
    ],
];
?>

<div class="content-wrapper" style="padding: 1.5rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #163832; margin-bottom: 0.25rem;">
                <span style="color: #235347; font-weight: 500;">GST Compliance</span>
                <span>/</span>
                <span style="color: #051F20; font-weight: 600;">GST Registers</span>
            </div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #051F20; margin: 0;">GST Registers</h1>
            <p style="font-size: 0.85rem; color: #163832; margin: 0.25rem 0 0 0;">Outward tax supplies and inward purchase registers for GSTR-1, GSTR-3B, and ITC reconciliation.</p>
        </div>
        <div style="display: flex; gap: 0.65rem;">
            <?php if ($activeTab === 'sales'): ?>
                <a href="/reports/gst-sales-register/export" class="btn btn-secondary" style="background: #DAF1DE; border: 1.5px solid #8EB69B; color: #235347; padding: 0.55rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none; font-size: 0.85rem;">
                    Download Sales CSV
                </a>
            <?php else: ?>
                <a href="/reports/gst-purchase-register/export" class="btn btn-secondary" style="background: #DAF1DE; border: 1.5px solid #8EB69B; color: #235347; padding: 0.55rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none; font-size: 0.85rem;">
                    Download Purchase CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reusable Tabs Component -->
    <?= render_tabs($tabs, $activeTab, '/reports/gst-registers') ?>

    <!-- TAB CONTENT -->
    <?php if ($activeTab === 'sales'): ?>
        <?php
            if (file_exists(__DIR__ . '/gst_sales_register.php')) {
                include __DIR__ . '/gst_sales_register.php';
            }
        ?>
    <?php else: ?>
        <?php
            if (file_exists(__DIR__ . '/gst_purchase_register.php')) {
                include __DIR__ . '/gst_purchase_register.php';
            }
        ?>
    <?php endif; ?>
</div>

