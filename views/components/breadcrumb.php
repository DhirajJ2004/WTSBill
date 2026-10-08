<?php
/**
 * WTSBill ERP - Reusable Breadcrumb Component
 *
 * Parameters:
 * - $items (array): List of items e.g. [['label' => 'Sales', 'url' => '/invoices'], ['label' => 'Invoices']]
 * - $company (string|null): Company prefix name
 * - $badge (string|null): Optional right badge
 */

$items = $items ?? [];
$showHome = $showHome ?? true;
?>
<nav class="breadcrumb-container" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <?php if ($showHome): ?>
            <li class="breadcrumb-item">
                <a href="<?= url('/dashboard') ?>" class="breadcrumb-link" title="Dashboard">
                    <i class="fa-solid fa-house breadcrumb-icon"></i>
                </a>
            </li>
        <?php endif; ?>

        <?php if (!empty($company)): ?>
            <li class="breadcrumb-separator"><i class="fa-solid fa-chevron-right"></i></li>
            <li class="breadcrumb-item">
                <span class="breadcrumb-text"><?= htmlspecialchars($company) ?></span>
            </li>
        <?php endif; ?>

        <?php 
        $total = count($items);
        foreach ($items as $idx => $item): 
            $isLast = ($idx === $total - 1);
            $label = is_array($item) ? ($item['label'] ?? '') : (string)$item;
            $linkUrl = is_array($item) ? ($item['url'] ?? null) : null;
        ?>
            <li class="breadcrumb-separator"><i class="fa-solid fa-chevron-right"></i></li>
            <li class="breadcrumb-item <?= $isLast ? 'active' : '' ?>" <?= $isLast ? 'aria-current="page"' : '' ?>>
                <?php if (!$isLast && !empty($linkUrl)): ?>
                    <a href="<?= url($linkUrl) ?>" class="breadcrumb-link"><?= htmlspecialchars($label) ?></a>
                <?php else: ?>
                    <span class="breadcrumb-current-text"><?= htmlspecialchars($label) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>

        <?php if (!empty($badge)): ?>
            <li class="breadcrumb-badge-item">
                <span class="badge badge-primary"><?= htmlspecialchars($badge) ?></span>
            </li>
        <?php endif; ?>
    </ol>
</nav>
