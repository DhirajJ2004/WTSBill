<?php
/**
 * Accessible Reusable Modal Dialog Partial
 *
 * @var string $modalId
 * @var string $modalTitle
 * @var string $modalBody
 * @var string|null $modalFooter
 */
$modalId = $modalId ?? 'app-modal';
$modalTitle = $modalTitle ?? 'Confirm Action';
$modalBody = $modalBody ?? '';
$modalFooter = $modalFooter ?? null;
?>
<div id="<?= e($modalId) ?>" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="<?= e($modalId) ?>-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="<?= e($modalId) ?>-title" class="modal-title"><?= e($modalTitle) ?></h3>
            <button type="button" class="modal-close" data-modal-close="<?= e($modalId) ?>" aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body">
            <?= $modalBody ?>
        </div>
        <div class="modal-footer">
            <?php if ($modalFooter): ?>
                <?= $modalFooter ?>
            <?php else: ?>
                <button type="button" class="btn btn-secondary" data-modal-close="<?= e($modalId) ?>">Cancel</button>
                <button type="button" class="btn btn-primary" id="<?= e($modalId) ?>-confirm-btn">Confirm</button>
            <?php endif; ?>
        </div>
    </div>
</div>
