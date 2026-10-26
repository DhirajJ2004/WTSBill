<?php

use WTSBill\Core\Config;

$appName = Config::get('app.name', 'WTSBill ERP');
$year = date('Y');
?>
<footer class="erp-footer">
    <div>
        &copy; <?= e($year) ?> <strong><?= e($appName) ?></strong> &bull; All Rights Reserved.
    </div>
    <div>
        <span>Environment: <strong><?= e(Config::get('app.env', 'production')) ?></strong></span>
        <span style="margin: 0 0.5rem;">&bull;</span>
        <span>Native PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></span>
    </div>
</footer>
