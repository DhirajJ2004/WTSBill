<?php
if (!function_exists('url')) {
    function url($path = '') {
        $path = '/' . ltrim($path, '/');
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $subDir = rtrim(dirname($scriptName), '/\\');
        if (strpos($subDir, '/backend/public') !== false) {
            $base = str_replace('/backend/public', '', $subDir);
        } elseif ($subDir !== '/' && $subDir !== '\\' && $subDir !== '.') {
            $base = $subDir;
        } else {
            $base = '';
        }
        $base = rtrim(str_replace('\\', '/', $base), '/');
        if (empty($base) && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/WTSBill') === 0) {
            $base = '/WTSBill';
        }
        return $base . $path;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'WTS LEDGER PRO - Wis Technosavvy Pvt Ltd') ?></title>
    <link rel="icon" type="image/png" href="<?= url('/assets/img/logo.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>
    <script>
        const savedTheme = localStorage.getItem('wts_theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
        window.baseUrl = '<?= url('') ?>';
    </script>
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>?v=2.1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/ajax/libs/Chart.js/3.9.1/chart.min.js" defer></script>
</head>
<body>
<div class="app-container">
