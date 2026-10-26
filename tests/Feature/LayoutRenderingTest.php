<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Config;
use WTSBill\Core\Session;
use WTSBill\Core\View;

/**
 * Feature Test for Master Layouts, Partials, and Frontend Assets (Chunk 5).
 */
class LayoutRenderingTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        Session::set('user_name', 'Anil Desai');
        Session::set('user_email', 'anil.d@wtsbill.in');
        Session::set('user_role', 'ADMIN');
        Session::setTenantContext(1, 2);

        // 1. Asset Files Integrity
        $cssFile = dirname(__DIR__, 2) . '/public/assets/css/app.css';
        $jsFile = dirname(__DIR__, 2) . '/public/assets/js/app.js';

        $assert('LAYOUT-01', 'public/assets/css/app.css exists and is non-empty', 
            file_exists($cssFile) && filesize($cssFile) > 1000);
        $assert('LAYOUT-02', 'public/assets/js/app.js exists and is non-empty', 
            file_exists($jsFile) && filesize($jsFile) > 1000);

        $cssContent = file_get_contents($cssFile);
        $assert('LAYOUT-03', 'CSS contains custom properties and dark theme palette', 
            str_contains($cssContent, '--color-primary') && str_contains($cssContent, '--bg-sidebar'));
        $assert('LAYOUT-04', 'CSS contains responsive drawer and print media queries', 
            str_contains($cssContent, '@media (max-width: 768px)') && str_contains($cssContent, '@media print'));

        $jsContent = file_get_contents($jsFile);
        $assert('LAYOUT-05', 'JavaScript exports Toast and Modal managers', 
            str_contains($jsContent, 'window.Toast = Toast') && str_contains($jsContent, 'window.Modal = Modal'));
        $assert('LAYOUT-06', 'JavaScript provides CSRF-protected fetch helper', 
            str_contains($jsContent, 'window.fetchWithCsrf'));

        // 2. Main Application Layout (views/layouts/app.php)
        $renderedApp = View::renderWithLayout('dashboard/index', [
            'title' => 'Overview Dashboard',
            'currentUri' => '/',
        ], 'layouts/app');

        $assert('LAYOUT-07', 'Layout renders HTML5 doctype and html element', 
            str_contains($renderedApp, '<!DOCTYPE html>') && str_contains($renderedApp, '<html lang="en"'));
        $assert('LAYOUT-08', 'Layout renders formatted page title', 
            str_contains($renderedApp, '<title>Overview Dashboard — WTSBill ERP</title>'));
        $assert('LAYOUT-09', 'Layout includes CSRF meta tag matching session', 
            str_contains($renderedApp, '<meta name="csrf-token" content="' . Session::csrfToken() . '">'));
        $assert('LAYOUT-10', 'Layout links to app.css and app.js', 
            str_contains($renderedApp, '/assets/css/app.css') && str_contains($renderedApp, '/assets/js/app.js'));

        // 3. Sidebar Partial Rendering
        $assert('LAYOUT-11', 'Sidebar contains brand title and version', 
            str_contains($renderedApp, 'WTSBill ERP') && str_contains($renderedApp, 'v2.0'));
        $assert('LAYOUT-12', 'Sidebar contains Core ERP modules', 
            str_contains($renderedApp, 'Customers') && 
            str_contains($renderedApp, 'Tax Invoices') && 
            str_contains($renderedApp, 'Chart of Accounts') && 
            str_contains($renderedApp, 'GSTR-1 Compliance'));
        $assert('LAYOUT-13', 'Sidebar marks active navigation link', 
            str_contains($renderedApp, 'href="/" class="nav-item active"'));

        // 4. Header Partial Rendering
        $assert('LAYOUT-14', 'Header renders active tenant and branch indicators', 
            str_contains($renderedApp, 'Company #1') && str_contains($renderedApp, 'Branch #2'));
        $assert('LAYOUT-15', 'Header renders authenticated user details and role', 
            str_contains($renderedApp, 'Anil Desai') && str_contains($renderedApp, 'ADMIN'));
        $assert('LAYOUT-16', 'Header renders CSRF-protected logout action', 
            str_contains($renderedApp, 'action="/logout"') && str_contains($renderedApp, 'name="_csrf_token"'));

        // 5. Footer Partial Rendering
        $assert('LAYOUT-17', 'Footer renders copyright and current year', 
            str_contains($renderedApp, '&copy; ' . date('Y')) && str_contains($renderedApp, 'All Rights Reserved'));
        $assert('LAYOUT-18', 'Footer renders PHP runtime version', 
            str_contains($renderedApp, 'Native PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION));

        // 6. Toast Notifications Partial & HTML Escaping
        Session::flash('success', 'Invoice #INV-2026-0001 saved successfully!');
        Session::flash('error', '<script>alert("xss")</script>Payment failed');

        $renderedToast = View::render('partials/toast');
        $assert('LAYOUT-19', 'Toast renders flash success message', 
            str_contains($renderedToast, 'Invoice #INV-2026-0001 saved successfully!'));
        $assert('LAYOUT-20', 'Toast escapes unsafe XSS in error message', 
            str_contains($renderedToast, '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;') && 
            !str_contains($renderedToast, '<script>alert("xss")</script>'));

        // 7. Modal Partial Rendering
        $renderedModal = View::render('partials/modal', [
            'modalId' => 'delete-confirm-dialog',
            'modalTitle' => 'Delete Customer Confirmation',
            'modalBody' => '<p>Are you sure you want to permanently delete this customer?</p>',
        ]);
        $assert('LAYOUT-21', 'Modal renders with assigned ID and ARIA attributes', 
            str_contains($renderedModal, 'id="delete-confirm-dialog"') && 
            str_contains($renderedModal, 'role="dialog"') && 
            str_contains($renderedModal, 'aria-modal="true"'));
        $assert('LAYOUT-22', 'Modal renders custom title and body content', 
            str_contains($renderedModal, 'Delete Customer Confirmation') && 
            str_contains($renderedModal, 'permanently delete this customer'));

        // 8. Main Layout Alias Compatibility (layouts/main.php)
        $renderedMainAlias = View::renderWithLayout('dashboard/index', ['title' => 'Main Alias Test']);
        $assert('LAYOUT-23', 'layouts/main alias resolves and renders app layout structure', 
            str_contains($renderedMainAlias, '<!DOCTYPE html>') && 
            str_contains($renderedMainAlias, 'Main Alias Test — WTSBill ERP'));

        // 9. Dedicated Auth Layout (views/layouts/auth.php)
        $renderedAuth = View::renderWithLayout('components/test_card', [
            'title' => 'Sign In',
            'title_text' => 'Login Card'
        ], 'layouts/auth');
        $assert('LAYOUT-24', 'layouts/auth renders centered auth container and csrf-token', 
            str_contains($renderedAuth, 'class="auth-container"') && 
            str_contains($renderedAuth, '<meta name="csrf-token"'));

        // 10. Header Interactive Action Buttons (Sidebar Toggle & Theme Switcher)
        $assert('LAYOUT-25', 'Header renders interactive sidebar toggle button with onclick trigger', 
            str_contains($renderedApp, 'id="sidebar-toggle"') && 
            str_contains($renderedApp, 'onclick="window.toggleSidebar(event)"'));

        $assert('LAYOUT-26', 'Header renders theme switcher button with onclick trigger and icons', 
            str_contains($renderedApp, 'id="theme-toggle-btn"') && 
            str_contains($renderedApp, 'onclick="window.toggleTheme(event)"') &&
            str_contains($renderedApp, 'id="theme-icon-sun"') &&
            str_contains($renderedApp, 'id="theme-icon-moon"'));

        $assert('LAYOUT-27', 'Header embeds synchronous, self-contained toggle controllers on window', 
            str_contains($renderedApp, 'window.toggleSidebar = function') && 
            str_contains($renderedApp, 'window.toggleTheme = function') &&
            str_contains($renderedApp, 'window.updateThemeUI = function'));

        $themeCssFile = dirname(__DIR__, 2) . '/public/assets/css/theme.css';
        $themeCss = file_exists($themeCssFile) ? file_get_contents($themeCssFile) : '';
        $assert('LAYOUT-28', 'Theme stylesheet defines sidebar-collapsed rules and action button styling', 
            str_contains($themeCss, 'body.sidebar-collapsed .erp-sidebar') && 
            str_contains($themeCss, '.sidebar-toggle-btn') &&
            str_contains($themeCss, '.theme-toggle-btn'));

        // 11. Collapsible Sidebar Category Dropdowns
        $assert('LAYOUT-29', 'Sidebar renders collapsible category dropdown groups with right-pointing carets', 
            str_contains($renderedApp, 'data-nav-group="parties"') && 
            str_contains($renderedApp, 'data-nav-group="sales"') && 
            str_contains($renderedApp, 'data-nav-group="inventory"') && 
            str_contains($renderedApp, 'nav-group-caret'));

        $salesPageHtml = View::renderWithLayout('sales/invoices', [
            'title' => 'Tax Invoices',
            'currentUri' => '/sales/invoices',
            'invoices' => [],
            'totals' => ['total_invoices' => 0, 'gross_sales' => 0, 'tax_collected' => 0, 'outstanding' => 0],
            'customers' => [],
            'pagination' => ['total' => 0, 'page' => 1, 'per_page' => 25, 'total_pages' => 1],
        ], 'layouts/app');

        $assert('LAYOUT-30', 'Sidebar on /sales/invoices marks Sales & Billing group as has-active', 
            str_contains($salesPageHtml, 'data-nav-group="sales"') && 
            str_contains($salesPageHtml, 'has-active'));

        // Clean up session
        Session::clear();
    }
}
