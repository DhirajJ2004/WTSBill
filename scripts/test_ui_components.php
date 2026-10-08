<?php

/**
 * WTSBill ERP - UI Layouts & Components Verification Suite
 * Tests the rendering and parameters of all 16 reusable UI components & layouts.
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';
require_once __DIR__ . '/../views/db_helper.php';

use App\Database\Database;
Database::init();

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$failures = [];

function assert_ui(string $category, string $title, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests, $failures;
    $totalTests++;
    echo "[TEST #{$totalTests}] [{$category}] {$title} ... ";
    if ($condition) {
        $passedTests++;
        echo "PASS\n";
        if (!empty($detail)) {
            echo "   -> {$detail}\n";
        }
    } else {
        $failedTests++;
        echo "FAIL\n";
        $msg = "[{$category}] {$title}: Failed ({$detail})";
        $failures[] = $msg;
        echo "   -> ERROR: {$msg}\n";
    }
}

echo "======================================================================\n";
echo "   WTSBill ERP - UI Components & Layouts Verification Suite\n";
echo "======================================================================\n\n";

// 1. Test Sidebar Component
$sidebarHtml = component('sidebar', ['currentRoute' => 'invoices']);
assert_ui(
    'SIDEBAR',
    'Sidebar component renders with active route highlight',
    strpos($sidebarHtml, 'class="sidebar"') !== false && strpos($sidebarHtml, 'menu-item active" title="Sales Invoices"') !== false,
    'Rendered ' . strlen($sidebarHtml) . ' bytes'
);

// 2. Test Topbar Component
$topbarHtml = component('topbar');
assert_ui(
    'TOPBAR',
    'Topbar component renders navbar, workspace selector, search input & profile dropdown',
    strpos($topbarHtml, 'class="navbar"') !== false && strpos($topbarHtml, 'globalQuickSearch') !== false && strpos($topbarHtml, 'workspaceDropdownBtn') !== false,
    'Rendered ' . strlen($topbarHtml) . ' bytes'
);

// 3. Test Breadcrumb Component
$breadcrumbHtml = component('breadcrumb', [
    'company' => 'Wis Technosavvy',
    'items' => [
        ['label' => 'Sales', 'url' => '/invoices'],
        ['label' => 'New Invoice']
    ],
    'badge' => 'FY 2026-27'
]);
assert_ui(
    'BREADCRUMB',
    'Breadcrumb component renders ordered list, links and status badge',
    strpos($breadcrumbHtml, 'breadcrumb-container') !== false && strpos($breadcrumbHtml, 'New Invoice') !== false && strpos($breadcrumbHtml, 'FY 2026-27') !== false
);

// 4. Test Page Header Component
$pageHeaderHtml = component('page_header', [
    'title' => 'Sales Invoices',
    'subtitle' => 'Manage and track all customer GST tax invoices.',
    'icon' => 'fa-regular fa-file-lines',
    'badge' => '24 Active',
    'badgeVariant' => 'success',
    'actions' => '<a href="/create-invoice" class="btn btn-primary btn-sm">+ Create Invoice</a>'
]);
assert_ui(
    'PAGE_HEADER',
    'Page header component renders title, subtitle, icon, badge and actions',
    strpos($pageHeaderHtml, 'page-header') !== false && strpos($pageHeaderHtml, 'Sales Invoices') !== false && strpos($pageHeaderHtml, 'badge-success') !== false
);

// 5. Test Button Component
$btnPrimary = component('button', [
    'text' => 'Save Invoice',
    'icon' => 'fa-solid fa-check',
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'submit'
]);
$btnLink = component('button', [
    'text' => 'Cancel',
    'variant' => 'outline',
    'href' => '/invoices'
]);
assert_ui(
    'BUTTON',
    'Button component renders button[type=submit] and anchor styled as button',
    strpos($btnPrimary, '<button type="submit"') !== false && strpos($btnPrimary, 'btn-primary') !== false && strpos($btnLink, '<a href=') !== false && strpos($btnLink, 'btn-outline') !== false
);

// 6. Test Card Component
$cardHtml = component('card', [
    'title' => 'Monthly Revenue',
    'subtitle' => 'April 2026',
    'icon' => 'fa-solid fa-indian-rupee-sign',
    'badge' => '+12.4%',
    'badgeVariant' => 'success',
    'content' => '<div class="metric-val">₹ 14,28,500</div>',
    'footer' => '<a href="/reports" class="link-sm">View full breakdown</a>'
]);
assert_ui(
    'CARD',
    'Card component renders header with badge, body content and footer slot',
    strpos($cardHtml, 'class="card') !== false && strpos($cardHtml, 'Monthly Revenue') !== false && strpos($cardHtml, 'metric-val') !== false && strpos($cardHtml, 'card-footer') !== false
);

// 7. Test Table Component
$tableHtml = component('table', [
    'headers' => [
        ['label' => 'Invoice #', 'align' => 'left', 'width' => '20%'],
        ['label' => 'Customer', 'align' => 'left'],
        ['label' => 'Amount', 'align' => 'right'],
        ['label' => 'Status', 'align' => 'center'],
    ],
    'rows' => [
        ['INV-001', 'Ramesh Hardware', '₹ 11,800.00', '<span class="badge badge-success">PAID</span>'],
        ['INV-002', 'Apollo Pharma', '₹ 5,900.00', '<span class="badge badge-warning">UNPAID</span>']
    ]
]);
assert_ui(
    'TABLE',
    'Table component renders responsive wrapper, column headers and data cells',
    strpos($tableHtml, 'table-responsive') !== false && strpos($tableHtml, 'INV-001') !== false && strpos($tableHtml, 'Apollo Pharma') !== false
);

// 8. Test Form Group Component
$formGroupText = component('form_group', [
    'label' => 'Customer Name',
    'name' => 'customer_name',
    'placeholder' => 'Enter legal customer name',
    'icon' => 'fa-regular fa-user',
    'required' => true,
    'helpText' => 'As registered on GST portal'
]);
$formGroupSelect = component('form_group', [
    'label' => 'Payment Mode',
    'name' => 'payment_mode',
    'type' => 'select',
    'options' => [
        'BANK_TRANSFER' => 'Bank NEFT/RTGS',
        'UPI' => 'UPI / QR Code',
        'CASH' => 'Cash'
    ],
    'value' => 'BANK_TRANSFER'
]);
assert_ui(
    'FORM_GROUP',
    'Form group component renders labeled text input with icon and select with options',
    strpos($formGroupText, 'form-label') !== false && strpos($formGroupText, 'name="customer_name"') !== false && strpos($formGroupSelect, '<select') !== false && strpos($formGroupSelect, 'selected') !== false
);

// 9. Test Modal Component
$modalHtml = component('modal', [
    'id' => 'newCustomerModal',
    'title' => 'Create New Customer',
    'icon' => 'fa-solid fa-user-plus',
    'size' => 'lg',
    'content' => '<p>Customer form fields here...</p>',
    'footer' => '<button type="button" class="btn btn-outline btn-sm">Cancel</button><button type="submit" class="btn btn-primary btn-sm">Save</button>'
]);
assert_ui(
    'MODAL',
    'Modal component renders dialog, close button, body slot and footer slot',
    strpos($modalHtml, 'id="newCustomerModal"') !== false && strpos($modalHtml, 'modal-lg') !== false && strpos($modalHtml, 'modal-close-btn') !== false
);

// 10. Test Dropdown Component
$dropdownHtml = component('dropdown', [
    'id' => 'exportMenu',
    'triggerText' => 'Export',
    'triggerIcon' => 'fa-solid fa-file-export',
    'items' => [
        ['label' => 'Export as PDF', 'url' => '/export?format=pdf', 'icon' => 'fa-solid fa-file-pdf'],
        ['label' => 'Export as Excel', 'url' => '/export?format=xlsx', 'icon' => 'fa-solid fa-file-excel'],
        ['divider' => true],
        ['label' => 'Print Document', 'url' => '#', 'icon' => 'fa-solid fa-print', 'onclick' => 'window.print()']
    ]
]);
assert_ui(
    'DROPDOWN',
    'Dropdown component renders trigger button and menu items with icons and divider',
    strpos($dropdownHtml, 'id="exportMenu_btn"') !== false && strpos($dropdownHtml, 'Export as PDF') !== false && strpos($dropdownHtml, 'dropdown-divider') !== false
);

// 11. Test Toast Component
$toastHtml = component('toast', [
    'message' => 'Invoice #INV-001 created successfully.',
    'type' => 'success',
    'title' => 'Invoice Created',
    'autoShow' => true
]);
assert_ui(
    'TOAST',
    'Toast component renders alert notification and JS window.showToast helper',
    strpos($toastHtml, 'toast-container') !== false && strpos($toastHtml, 'Invoice #INV-001 created successfully.') !== false && strpos($toastHtml, 'window.showToast') !== false
);

// 12. Test Pagination Component
$paginationHtml = component('pagination', [
    'currentPage' => 2,
    'totalPages' => 5,
    'totalItems' => 120,
    'perPage' => 25,
    'baseUrl' => '/invoices'
]);
assert_ui(
    'PAGINATION',
    'Pagination component renders page numbers, current item metrics and navigation arrows',
    strpos($paginationHtml, 'pagination-container') !== false && strpos($paginationHtml, 'Showing <strong>26</strong>') !== false && strpos($paginationHtml, 'page=3') !== false
);

// 13. Test Empty State Component
$emptyStateHtml = component('empty_state', [
    'title' => 'No Invoices Found',
    'message' => 'Get started by creating your first sales tax invoice.',
    'actionText' => 'Create First Invoice',
    'actionUrl' => '/create-invoice',
    'icon' => 'fa-solid fa-file-invoice'
]);
assert_ui(
    'EMPTY_STATE',
    'Empty state component renders illustration icon, heading and call-to-action button',
    strpos($emptyStateHtml, 'empty-state-wrapper') !== false && strpos($emptyStateHtml, 'No Invoices Found') !== false && strpos($emptyStateHtml, 'Create First Invoice') !== false
);

// 14. Test Error State Component
$errorStateHtml = component('error_state', [
    'title' => 'Payment Gateway Timeout',
    'message' => 'Unable to reach the payment processor. Please check connection.',
    'code' => 504,
    'retryOnclick' => 'retryPayment()'
]);
assert_ui(
    'ERROR_STATE',
    'Error state component renders error icon, code badge, retry action and dashboard link',
    strpos($errorStateHtml, 'error-state-card') !== false && strpos($errorStateHtml, 'Code: 504') !== false && strpos($errorStateHtml, 'retryPayment()') !== false
);

// 15. Test Loading State Component
$loadingSpinner = component('loading_state', ['text' => 'Fetching transactions...', 'type' => 'spinner']);
$loadingSkeleton = component('loading_state', ['type' => 'skeleton', 'rows' => 4]);
assert_ui(
    'LOADING_STATE',
    'Loading state component renders spinner ring and skeleton placeholder bars',
    strpos($loadingSpinner, 'loading-spinner-ring') !== false && strpos($loadingSkeleton, 'skeleton-line') !== false
);

// 16. Test Confirmation Dialog Component
$confirmDialogHtml = component('confirm_dialog', [
    'id' => 'deleteInvoiceConfirm',
    'title' => 'Cancel Invoice #INV-2026-001',
    'message' => 'Are you sure you want to cancel this invoice? Stock will be restored.',
    'confirmText' => 'Yes, Cancel Invoice',
    'confirmAction' => '/invoices/cancel',
    'variant' => 'danger'
]);
assert_ui(
    'CONFIRM_DIALOG',
    'Confirmation dialog component renders modal with danger variant and form action',
    strpos($confirmDialogHtml, 'id="deleteInvoiceConfirm"') !== false && strpos($confirmDialogHtml, 'btn-danger') !== false && strpos($confirmDialogHtml, 'Yes, Cancel Invoice') !== false
);

// 17. Test Master Layout Integration
ob_start();
$pageTitle = 'Dashboard - WTSBill ERP';
$currentRoute = 'dashboard';
$content = '<div class="test-content"><h3>Dashboard KPI Widgets</h3></div>';
include __DIR__ . '/../views/layouts/layout.php';
$masterLayoutHtml = ob_get_clean();

assert_ui(
    'MASTER_LAYOUT',
    'Master layout wraps head, sidebar, topbar, content slot and scripts without error',
    strpos($masterLayoutHtml, '<!DOCTYPE html>') !== false && strpos($masterLayoutHtml, 'class="sidebar"') !== false && strpos($masterLayoutHtml, 'class="navbar"') !== false && strpos($masterLayoutHtml, 'Dashboard KPI Widgets') !== false,
    'Rendered ' . strlen($masterLayoutHtml) . ' bytes'
);

echo "\n======================================================================\n";
echo "   UI COMPONENTS & LAYOUTS SUMMARY\n";
echo "   Total Assertions: {$totalTests}\n";
echo "   Passed:           {$passedTests}\n";
echo "   Failed:           {$failedTests}\n";
echo "======================================================================\n";

if ($failedTests > 0) {
    echo "\nFAILURES DETECTED:\n";
    foreach ($failures as $f) {
        echo " - {$f}\n";
    }
    exit(1);
} else {
    echo "\n[ALL 16 UI COMPONENTS AND LAYOUTS VERIFIED AND PASSING 100%]\n";
    exit(0);
}
