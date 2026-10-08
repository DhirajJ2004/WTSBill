<?php
/**
 * WTSBill ERP - Reusable Page Header Component
 *
 * Parameters:
 * - $title (string): Main heading text
 * - $subtitle (string|null): Secondary descriptive text
 * - $icon (string|null): FontAwesome icon class e.g. 'fa-solid fa-file-invoice'
 * - $badge (string|null): Status/Category pill
 * - $badgeVariant (string): 'primary'|'success'|'danger'|'warning'|'info'|'secondary'
 * - $breadcrumbs (array|null): Breadcrumb items
 * - $actions (string|null): HTML actions buttons or slot
 */

$title = $title ?? 'Page Title';
$subtitle = $subtitle ?? null;
$icon = $icon ?? null;
$badge = $badge ?? null;
$badgeVariant = $badgeVariant ?? 'primary';
$breadcrumbs = $breadcrumbs ?? null;
$actions = $actions ?? null;
?>
<div class="page-header-container">
    <?php if (!empty($breadcrumbs)): ?>
        <div class="page-header-breadcrumbs">
            <?php 
            $items = $breadcrumbs;
            include __DIR__ . '/breadcrumb.php'; 
            ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <div class="page-header-title-group">
            <?php if (!empty($icon)): ?>
                <div class="page-header-icon-box">
                    <i class="<?= htmlspecialchars($icon) ?>"></i>
                </div>
            <?php endif; ?>
            <div>
                <div class="page-header-title-row">
                    <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
                    <?php if (!empty($badge)): ?>
                        <span class="badge badge-<?= htmlspecialchars($badgeVariant) ?>"><?= htmlspecialchars($badge) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($subtitle)): ?>
                    <p class="page-subtitle"><?= htmlspecialchars($subtitle) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($actions)): ?>
            <div class="page-header-actions">
                <?= $actions ?>
            </div>
        <?php endif; ?>
    </div>
</div>
