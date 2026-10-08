<?php
/**
 * WTSBill ERP - Reusable Loading State Component
 *
 * Parameters:
 * - $text (string): Status text (default 'Loading...')
 * - $type (string): 'spinner' | 'skeleton' | 'inline'
 * - $rows (int): Number of skeleton placeholder lines
 */

$text = $text ?? 'Loading...';
$type = $type ?? 'spinner';
$rows = max(1, (int)($rows ?? 3));
?>
<?php if ($type === 'skeleton'): ?>
    <div class="skeleton-loader-container" aria-busy="true" aria-label="Content loading">
        <?php for ($i = 0; $i < $rows; $i++): ?>
            <div class="skeleton-line" style="width: <?= 100 - ($i * 12) ?>%;"></div>
        <?php endfor; ?>
    </div>
<?php elseif ($type === 'inline'): ?>
    <span class="inline-loader" aria-busy="true">
        <i class="fa-solid fa-spinner fa-spin text-primary"></i>
        <span class="inline-loader-text"><?= htmlspecialchars($text) ?></span>
    </span>
<?php else: ?>
    <div class="loading-state-wrapper" aria-busy="true" aria-label="Loading">
        <div class="loading-spinner-ring"></div>
        <p class="loading-state-text"><?= htmlspecialchars($text) ?></p>
    </div>
<?php endif; ?>
