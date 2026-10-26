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
    <link rel="stylesheet" href="/assets/css/theme.css">
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('wtsbill_theme') || 'light';
                document.documentElement.setAttribute('data-theme', savedTheme);
                if (localStorage.getItem('wtsbill_sidebar_collapsed') === '1' && window.innerWidth > 768) {
                    if (document.body) {
                        document.body.classList.add('sidebar-collapsed');
                    } else {
                        document.addEventListener('DOMContentLoaded', function() {
                            document.body.classList.add('sidebar-collapsed');
                        });
                    }
                }
            } catch(e) {}
        })();
    </script>
</head>
<body>
    <div class="erp-container">
        <!-- Sidebar Navigation Partial -->
        <?php include View::resolvePath('partials/sidebar'); ?>

        <!-- Main ERP Workspace -->
        <div class="erp-main">
            <!-- Header Partial -->
            <?php include View::resolvePath('partials/header'); ?>

            <!-- Page Content Slot -->
            <main class="erp-content">
                <?= $content ?? '' ?>
            </main>

            <!-- Footer Partial -->
            <?php include View::resolvePath('partials/footer'); ?>
        </div>
    </div>

    <!-- Toast Notifications Partial -->
    <?php include View::resolvePath('partials/toast'); ?>

    <!-- Native Client Script -->
    <script src="/assets/js/app.js"></script>
</body>
</html>
