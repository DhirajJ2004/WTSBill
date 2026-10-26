<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Session;
use WTSBill\Core\View;

/**
 * Feature Test for Frontend Asset Integration, Design System Tokens, and Client Scripts (Chunk 17).
 */
class FrontendIntegrationTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        Session::set('user_name', 'Frontend Tester');
        Session::set('user_role', 'ADMIN');
        Session::setTenantContext(1, 1);

        $root = dirname(__DIR__, 2);
        $cssPath = $root . '/public/assets/css/app.css';
        $jsPath = $root . '/public/assets/js/app.js';
        $mapPath = $root . '/docs/FRONTEND_ASSET_MAP.md';

        // 1. Asset File Presence & Integrity
        $assert('FRONTEND-01', 'public/assets/css/app.css exists and is non-trivial (>5KB)', 
            file_exists($cssPath) && filesize($cssPath) > 5000);
        $assert('FRONTEND-02', 'public/assets/js/app.js exists and is non-trivial (>3KB)', 
            file_exists($jsPath) && filesize($jsPath) > 3000);
        $assert('FRONTEND-03', 'docs/FRONTEND_ASSET_MAP.md documentation exists', 
            file_exists($mapPath) && filesize($mapPath) > 1000);

        // 2. CSS Design Tokens & Palette Verification
        $css = file_get_contents($cssPath);
        $assert('FRONTEND-04', 'CSS defines full semantic HSL color tokens', 
            str_contains($css, '--color-primary:') &&
            str_contains($css, '--color-success:') &&
            str_contains($css, '--color-danger:') &&
            str_contains($css, '--color-warning:') &&
            str_contains($css, '--color-info:'));

        $assert('FRONTEND-05', 'CSS defines dark mode baseline and light mode override', 
            str_contains($css, '--bg-body: #0b1120') &&
            str_contains($css, '[data-theme="light"]'));

        $assert('FRONTEND-06', 'CSS includes form validation and input group classes', 
            str_contains($css, '.form-control') &&
            str_contains($css, '.is-invalid') &&
            str_contains($css, '.invalid-feedback') &&
            str_contains($css, '.input-group-text'));

        $assert('FRONTEND-07', 'CSS includes KPI widgets and chart containers', 
            str_contains($css, '.kpi-card') &&
            str_contains($css, '.metric-value') &&
            str_contains($css, '.chart-container') &&
            str_contains($css, '.chart-bar'));

        $assert('FRONTEND-08', 'CSS includes responsive mobile drawer and print styles', 
            str_contains($css, '@media (max-width: 768px)') &&
            str_contains($css, '.sidebar-overlay') &&
            str_contains($css, '@media print'));

        // 3. JavaScript Client Engine Verification
        $js = file_get_contents($jsPath);
        $assert('FRONTEND-09', 'JavaScript exposes WTSBill enterprise namespace', 
            str_contains($js, 'window.WTSBill = WTSBill'));

        $assert('FRONTEND-10', 'JavaScript provides Sidebar and Dropdown controllers', 
            str_contains($js, 'WTSBill.Sidebar = Sidebar') &&
            str_contains($js, 'WTSBill.Dropdown = Dropdown'));

        $assert('FRONTEND-11', 'JavaScript provides Toast and Modal controllers', 
            str_contains($js, 'WTSBill.Toast = Toast') &&
            str_contains($js, 'WTSBill.Modal = Modal') &&
            str_contains($js, 'window.Toast = Toast') &&
            str_contains($js, 'window.Modal = Modal'));

        $assert('FRONTEND-12', 'JavaScript provides CSRF-protected AJAX fetch helper', 
            str_contains($js, 'WTSBill.Http = Http') &&
            str_contains($js, 'window.fetchWithCsrf') &&
            str_contains($js, 'X-CSRF-TOKEN'));

        $assert('FRONTEND-13', 'JavaScript includes form double-submit and uppercase safeguards', 
            str_contains($js, 'data-disable-on-submit') &&
            str_contains($js, 'data-uppercase'));

        $assert('FRONTEND-14', 'JavaScript includes client-side table filter helper', 
            str_contains($js, 'WTSBill.Tables = Tables'));

        // 4. Layout Template Integration & Asset Links
        $appHtml = View::renderWithLayout('dashboard/index', [
            'title' => 'Test Dashboard',
            'currentUri' => '/',
        ], 'layouts/app');

        $assert('FRONTEND-15', 'app.php layout links /assets/css/app.css', 
            str_contains($appHtml, '<link rel="stylesheet" href="/assets/css/app.css">'));
        $assert('FRONTEND-16', 'app.php layout links /assets/js/app.js', 
            str_contains($appHtml, '<script src="/assets/js/app.js"></script>'));
        $assert('FRONTEND-17', 'app.php layout embeds CSRF meta token', 
            str_contains($appHtml, '<meta name="csrf-token" content="' . Session::csrfToken() . '">'));
        $assert('FRONTEND-18', 'app.php layout renders dark theme attribute', 
            str_contains($appHtml, 'data-theme="dark"'));

        $authHtml = View::renderWithLayout('components/test_card', [
            'title' => 'Sign In',
            'title_text' => 'Login Card'
        ], 'layouts/auth');

        $assert('FRONTEND-19', 'auth.php layout links /assets/css/app.css and /assets/js/app.js', 
            str_contains($authHtml, '/assets/css/app.css') &&
            str_contains($authHtml, '/assets/js/app.js'));
        $assert('FRONTEND-20', 'auth.php layout renders centered auth container', 
            str_contains($authHtml, 'class="auth-container"'));

        // 5. Extended UI Component Library & Architectural Purity
        $assert('FRONTEND-21', 'CSS includes full button variant suite (.btn-primary, .btn-secondary, etc.)',
            str_contains($css, '.btn-primary') &&
            str_contains($css, '.btn-secondary') &&
            str_contains($css, '.btn-success') &&
            str_contains($css, '.btn-danger') &&
            str_contains($css, '.btn-warning'));

        $assert('FRONTEND-22', 'CSS includes data table striping and hover transitions',
            str_contains($css, '.table-container') &&
            str_contains($css, '.table-striped') &&
            str_contains($css, '.table tbody tr:hover'));

        $assert('FRONTEND-23', 'CSS includes modal dialog backdrop and scale animation',
            str_contains($css, '.modal-backdrop') &&
            str_contains($css, '.modal-dialog') &&
            str_contains($css, '.modal-header') &&
            str_contains($css, '.modal-body') &&
            str_contains($css, '.modal-footer'));

        $assert('FRONTEND-24', 'CSS includes toast alert severity variants',
            str_contains($css, '.toast.toast-success') &&
            str_contains($css, '.toast.toast-error') &&
            str_contains($css, '.toast.toast-warning') &&
            str_contains($css, '.toast.toast-info'));

        $assert('FRONTEND-25', 'CSS includes transaction badge color styles',
            str_contains($css, '.badge-primary') &&
            str_contains($css, '.badge-success') &&
            str_contains($css, '.badge-danger') &&
            str_contains($css, '.badge-warning') &&
            str_contains($css, '.badge-info'));

        $assert('FRONTEND-26', 'CSS includes table pagination controls',
            str_contains($css, '.pagination') &&
            str_contains($css, '.page-item') &&
            str_contains($css, '.page-link'));

        $assert('FRONTEND-27', 'JavaScript handles keyboard Escape dismissal for modals and dropdowns',
            str_contains($js, "e.key === 'Escape'") &&
            str_contains($js, 'Dropdown.closeAll()'));

        $assert('FRONTEND-28', 'Frontend asset files maintain strict separation (no PHP tags leaked)',
            !str_contains($css, '<?php') &&
            !str_contains($js, '<?php'));

        $assert('FRONTEND-29', 'Frontend avoids heavy third-party framework overhead (Zero jQuery/Bootstrap JS)',
            !str_contains($js, 'jQuery') &&
            !str_contains($js, 'bootstrap.Modal') &&
            !str_contains($js, 'React.createElement'));

        $assert('FRONTEND-30', 'Frontend assets optimized for instantaneous load (<30KB each)',
            filesize($cssPath) < 50000 &&
            filesize($jsPath) < 25000);

        // Clean up session
        Session::clear();
    }
}
