<?php
/**
 * WTSBill ERP — Standardized Module Workspace View (for upcoming/pending chunks)
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($moduleTitle ?? 'ERP Module') ?></h1>
        <p class="page-subtitle"><?= e($moduleSubtitle ?? 'Enterprise module ready for data transactions') ?></p>
    </div>
</div>

<div class="card" style="text-align: center; padding: 3rem 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 1rem; color: var(--color-info);">
        <?= e($moduleIcon ?? '📁') ?>
    </div>
    <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">
        <?= e($moduleTitle ?? 'Module') ?> Workspace
    </h2>
    <p style="max-width: 500px; margin: 0 auto 1.5rem; color: var(--text-muted); font-size: 0.95rem; line-height: 1.6;">
        Database schema and tables are active in <code>wtsbill_db</code>. Active context: Company #<?= e((string)(\WTSBill\Core\Session::getCompanyId() ?? 1)) ?>, Branch #<?= e((string)(\WTSBill\Core\Session::getBranchId() ?? 1)) ?>.
    </p>
    <div>
        <a href="/" class="btn btn-primary">&larr; Return to Dashboard</a>
    </div>
</div>
