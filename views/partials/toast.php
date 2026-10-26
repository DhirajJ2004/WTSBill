<?php

use WTSBill\Core\Session;

$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');
$flashWarning = Session::getFlash('warning');
$flashInfo = Session::getFlash('info');
?>
<div id="toast-container" class="toast-container" aria-live="polite">
    <?php if ($flashSuccess): ?>
        <div class="toast toast-success" role="alert">
            <div class="toast-message"><?= e($flashSuccess) ?></div>
            <button type="button" class="toast-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="toast toast-error" role="alert">
            <div class="toast-message"><?= e($flashError) ?></div>
            <button type="button" class="toast-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($flashWarning): ?>
        <div class="toast toast-warning" role="alert">
            <div class="toast-message"><?= e($flashWarning) ?></div>
            <button type="button" class="toast-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($flashInfo): ?>
        <div class="toast toast-info" role="alert">
            <div class="toast-message"><?= e($flashInfo) ?></div>
            <button type="button" class="toast-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
        </div>
    <?php endif; ?>
</div>
