<?php
/**
 * WTSBill ERP - Reusable Error State Component
 *
 * Parameters:
 * - $title (string): Error title
 * - $message (string|null): Error details
 * - $code (int|string|null): Error HTTP or exception code
 * - $retryUrl (string|null): Refresh / retry URL
 * - $retryOnclick (string|null): JS retry action
 * - $icon (string|null): Error icon
 */

$title = $title ?? 'An Error Occurred';
$message = $message ?? 'We were unable to complete your request. Please try again.';
$code = $code ?? null;
$retryUrl = $retryUrl ?? null;
$retryOnclick = $retryOnclick ?? 'window.location.reload()';
$icon = $icon ?? 'fa-solid fa-triangle-exclamation';
?>
<div class="error-state-card">
    <div class="error-state-icon-box">
        <i class="<?= htmlspecialchars($icon) ?>"></i>
    </div>
    
    <div class="error-state-content">
        <div class="error-state-header">
            <h3 class="error-state-title"><?= htmlspecialchars($title) ?></h3>
            <?php if (!empty($code)): ?>
                <span class="badge badge-danger">Code: <?= htmlspecialchars((string)$code) ?></span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($message)): ?>
            <p class="error-state-message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <div class="error-state-actions">
            <?php if (!empty($retryUrl)): ?>
                <a href="<?= htmlspecialchars(url($retryUrl)) ?>" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-rotate-right"></i>
                    <span>Try Again</span>
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-outline btn-sm" onclick="<?= htmlspecialchars($retryOnclick) ?>">
                    <i class="fa-solid fa-rotate-right"></i>
                    <span>Try Again</span>
                </button>
            <?php endif; ?>
            <a href="<?= url('/dashboard') ?>" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-house"></i>
                <span>Return to Dashboard</span>
            </a>
        </div>
    </div>
</div>
