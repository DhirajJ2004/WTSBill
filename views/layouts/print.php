<?php
/**
 * WTSBill ERP - Print Document Layout
 *
 * Variables:
 * - $documentTitle (string): Document title for print / tab
 * - $content (string): Inner printable document HTML
 */

if (!function_exists('url')) {
    require_once __DIR__ . '/../db_helper.php';
}

$documentTitle = $documentTitle ?? 'Print Document &bull; WTS LEDGER PRO';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($documentTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= url('/assets/img/logo.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>?v=2.1.0">
    <style>
        @media print {
            .no-print, .print-controls-bar, .navbar, .sidebar {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-page-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body class="print-preview-body">
    <div class="print-page-wrapper">
        <?= $content ?? '' ?>
    </div>
</body>
</html>
