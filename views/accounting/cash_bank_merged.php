<?php
require_once dirname(__DIR__, 2) . '/includes/tabs.php';

$title = 'Cash & Bank Books';
$activeTab = $activeTab ?? (string)($_GET['tab'] ?? 'cash');

$tabs = [
    [
        'id' => 'cash',
        'label' => 'Cash Book',
        'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>',
    ],
    [
        'id' => 'bank',
        'label' => 'Bank Book',
        'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="21" x2="21" y2="21"></line><line x1="3" y1="10" x2="21" y2="10"></line><polyline points="5 6 12 3 19 6"></polyline><line x1="4" y1="10" x2="4" y2="21"></line><line x1="20" y1="10" x2="20" y2="21"></line><line x1="8" y1="14" x2="8" y2="17"></line><line x1="12" y1="14" x2="12" y2="17"></line><line x1="16" y1="14" x2="16" y2="17"></line></svg>',
    ],
];
?>

<div class="content-wrapper" style="padding: 1.5rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #163832; margin-bottom: 0.25rem;">
                <span style="color: #235347; font-weight: 500;">Money & Liquidity</span>
                <span>/</span>
                <span style="color: #051F20; font-weight: 600;">Cash & Bank</span>
            </div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #051F20; margin: 0;">Cash & Bank Books</h1>
            <p style="font-size: 0.85rem; color: #163832; margin: 0.25rem 0 0 0;">Unified treasury registers for counter cash collections, petty cash, bank ledger accounts, and deposits.</p>
        </div>
        <div style="display: flex; gap: 0.65rem;">
            <button type="button" onclick="window.print()" class="btn btn-secondary" style="background: #DAF1DE; border: 1.5px solid #8EB69B; color: #235347; padding: 0.55rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">
                Print Register
            </button>
            <a href="/payments" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.15rem; border-radius: 8px; font-weight: 600; background: #235347; color: #FFFFFF; text-decoration: none; font-size: 0.85rem;">
                + Record Payment / Receipt
            </a>
        </div>
    </div>

    <!-- Reusable Tabs Component -->
    <?= render_tabs($tabs, $activeTab, '/accounting/cash-bank') ?>

    <!-- TAB CONTENT -->
    <?php if ($activeTab === 'cash'): ?>
        <?php
            // Include or render Cash Book register
            if (file_exists(__DIR__ . '/cash_book.php')) {
                include __DIR__ . '/cash_book.php';
            }
        ?>
    <?php else: ?>
        <?php
            // Include or render Bank Book register
            if (file_exists(__DIR__ . '/bank_book.php')) {
                include __DIR__ . '/bank_book.php';
            }
        ?>
    <?php endif; ?>
</div>

