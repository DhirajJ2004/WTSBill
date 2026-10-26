<?php

use WTSBill\Core\Config;
use WTSBill\Core\Session;
use WTSBill\Core\View;

$pageTitle = isset($title) && $title !== '' ? $title . ' — ' . Config::get('app.name', 'WTSBill ERP') : Config::get('app.name', 'WTSBill ERP');
$csrfToken = Session::csrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e($pageTitle) ?></title>

    <!-- Native Design System Stylesheet -->
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
            background: radial-gradient(circle at top, #1e293b 0%, #0b1120 100%);
        }
        .auth-container {
            width: 100%;
            max-width: 440px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <?= $content ?? '' ?>
    </div>

    <!-- Toast Notifications Partial -->
    <?php include View::resolvePath('partials/toast'); ?>

    <!-- Native Client Script -->
    <script src="/assets/js/app.js"></script>
</body>
</html>
