<?php
/**
 * WTSBill ERP - Master Application Layout
 *
 * Variables:
 * - $pageTitle (string): Browser title
 * - $currentRoute (string): Active route key for sidebar navigation
 * - $content (string): Inner view content HTML
 * - $breadcrumbs (array|null): Page breadcrumb items
 * - $headerActions (string|null): Action buttons for page header
 */

if (!function_exists('url')) {
    require_once __DIR__ . '/../db_helper.php';
}

$pageTitle = $pageTitle ?? 'WTS LEDGER PRO - Wis Technosavvy Pvt Ltd';
$currentRoute = $currentRoute ?? 'dashboard';
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
        <!-- 1. SIDEBAR -->
        <?php include __DIR__ . '/../components/sidebar.php'; ?>

        <!-- 2. MAIN CONTENT WRAPPER -->
        <div class="main-wrapper">
            <!-- 3. TOPBAR / NAVBAR -->
            <?php include __DIR__ . '/../components/topbar.php'; ?>

            <!-- 4. PAGE MAIN CONTENT -->
            <main class="content-area" id="mainContent">
                <?php
                // Flash message banner handling
                $flashSuccess = $_SESSION['flash_success'] ?? $_SESSION['success'] ?? null;
                $flashError = $_SESSION['flash_error'] ?? $_SESSION['error'] ?? $_SESSION['auth_error'] ?? null;
                unset($_SESSION['flash_success'], $_SESSION['success'], $_SESSION['flash_error'], $_SESSION['error'], $_SESSION['auth_error']);
                ?>
                <?php if (!empty($flashSuccess)): ?>
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <i class="fa-solid fa-circle-check"></i>
                        <div class="alert-content"><?= htmlspecialchars($flashSuccess) ?></div>
                        <button type="button" class="alert-close-btn" onclick="this.closest('.alert').remove()" aria-label="Close">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($flashError)): ?>
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <div class="alert-content"><?= htmlspecialchars($flashError) ?></div>
                        <button type="button" class="alert-close-btn" onclick="this.closest('.alert').remove()" aria-label="Close">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <?= $content ?? '' ?>
            </main>
        </div>
    </div>

    <!-- GLOBAL TOAST CONTAINER -->
    <?php include __DIR__ . '/../components/toast.php'; ?>

    <!-- JAVASCRIPT BUNDLE -->
    <script src="<?= url('/assets/js/app.js') ?>?v=2.1.0"></script>
</body>
</html>
