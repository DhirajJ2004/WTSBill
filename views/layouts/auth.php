<?php
/**
 * WTSBill ERP - Authentication Page Layout
 *
 * Variables:
 * - $pageTitle (string): Browser title
 * - $content (string): Inner view content HTML
 */

if (!function_exists('url')) {
    require_once __DIR__ . '/../db_helper.php';
}

$pageTitle = $pageTitle ?? 'Authentication &bull; WTS LEDGER PRO';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= url('/assets/img/logo.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>?v=2.1.0">
    <style>
        body {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 10% 20%, rgba(37, 99, 235, 0.06) 0px, transparent 50%),
                radial-gradient(at 90% 80%, rgba(16, 185, 129, 0.05) 0px, transparent 50%),
                linear-gradient(to right, #f1f5f9 1px, transparent 1px),
                linear-gradient(to bottom, #f1f5f9 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 32px 32px, 32px 32px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <?= $content ?? '' ?>
        
        <div class="footer-note" style="text-align: center; margin-top: 20px; font-size: 12px; color: #64748b;">
            &copy; <?= date('Y') ?> Wis Technosavvy Pvt Ltd &bull; Next-Gen ERP Billing System
        </div>
    </div>

    <!-- GLOBAL TOAST CONTAINER -->
    <?php include __DIR__ . '/../components/toast.php'; ?>
</body>
</html>
