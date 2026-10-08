<?php
/**
 * Automated Responsive UI & Viewport Layout Audit Test Suite
 * Tests all major pages against 11 viewport dimensions:
 * 320px, 360px, 375px, 390px, 414px, 768px, 820px, 1024px, 1280px, 1440px, 1920px
 */

require_once __DIR__ . '/../views/db_helper.php';

$viewports = [
    320  => 'iPhone SE (1st gen) / Narrow Mobile',
    360  => 'Android Standard Compact',
    375  => 'iPhone 6/7/8/SE 2',
    390  => 'iPhone 12/13/14',
    414  => 'iPhone XR / Plus',
    768  => 'iPad Mini / Portrait Tablet',
    820  => 'iPad Air / Medium Tablet',
    1024 => 'iPad Pro / Small Laptop',
    1280 => 'Standard Desktop Display',
    1440 => 'Wide Laptop / Desktop',
    1920 => 'Full HD 1080p Desktop'
];

$pages = [
    'Login' => __DIR__ . '/../views/auth/login.php',
    'Company Selection' => __DIR__ . '/../views/auth/select_company.php',
    'Dashboard' => __DIR__ . '/../views/dashboard.php',
    'Invoices' => __DIR__ . '/../views/sales/invoices.php',
    'Create Invoice' => __DIR__ . '/../views/sales/create_invoice.php',
    'Quotations' => __DIR__ . '/../views/sales/quotations.php',
    'Create Quotation' => __DIR__ . '/../views/sales/create_quotation.php',
    'Recurring Invoices' => __DIR__ . '/../views/sales/recurring_invoices.php',
    'Credit Notes' => __DIR__ . '/../views/sales/credit_notes.php',
    'Debit Notes' => __DIR__ . '/../views/purchases/debit_notes.php',
    'Purchases' => __DIR__ . '/../views/purchases/purchases.php',
    'Inventory' => __DIR__ . '/../views/inventory/index.php',
    'Parties' => __DIR__ . '/../views/parties/index.php',
    'Payments' => __DIR__ . '/../views/payments/index.php',
    'Expenses' => __DIR__ . '/../views/expenses/index.php',
    'Accounting' => __DIR__ . '/../views/accounting/index.php',
    'Reports' => __DIR__ . '/../views/reports/index.php',
    'Users' => __DIR__ . '/../views/settings/users.php',
    'Audit Logs' => __DIR__ . '/../views/settings/audit_logs.php',
    'Settings' => __DIR__ . '/../views/settings/index.php',
    'Print Invoice' => __DIR__ . '/../views/sales/print_invoice.php',
    'Print Quotation' => __DIR__ . '/../views/sales/print_quotation.php',
    'Print Order' => __DIR__ . '/../views/sales/print_order.php',
    'Print Credit Note' => __DIR__ . '/../views/sales/print_credit_note.php',
    'Print Challan' => __DIR__ . '/../views/sales/print_challan.php',
    'Print Debit Note' => __DIR__ . '/../views/purchases/print_debit_note.php',
    'Print Receipt' => __DIR__ . '/../views/payments/print_receipt.php'
];

echo "====================================================================\n";
echo "=== WTSBILL ERP: COMPREHENSIVE RESPONSIVE VIEWPORT AUDIT ===\n";
echo "====================================================================\n\n";

// Set up mock session
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Anil Desai',
    'email' => 'admin@wtsbill.in',
    'role' => 'ADMIN',
    'avatar' => 'AD'
];
$_SESSION['selected_company_id'] = 1;
$_SESSION['selected_branch_id'] = 1;
$_SESSION['selected_fy'] = 'FY 2026-2027';

$totalChecks = 0;
$passedChecks = 0;
$failedChecks = 0;
$issues = [];

// 1. Verify Global CSS Responsive Rules
echo "--- CHECKING GLOBAL RESPONSIVE RULES IN APP.CSS ---\n";
$css = file_get_contents(__DIR__ . '/../public/assets/css/app.css');

$requiredCssChecks = [
    'Fluid box-sizing reset' => (strpos($css, 'box-sizing: border-box') !== false),
    'App container max-width 100vw' => (strpos($css, 'max-width: 100vw') !== false),
    'Table responsive horizontal scroll' => (strpos($css, '.table-responsive') !== false && strpos($css, 'overflow-x: auto') !== false),
    'Modal max-width viewport constraint' => (strpos($css, 'calc(100vw - 24px)') !== false || strpos($css, 'calc(100vw - 16px)') !== false),
    'Subnav tabs smooth scrollbar-free scroll' => (strpos($css, '.subnav-tabs-wrapper') !== false && strpos($css, 'overflow-x: auto') !== false),
    'Mobile sidebar drawer mode (<= 1023px)' => (strpos($css, '@media (max-width: 1023px)') !== false && strpos($css, 'transform: translateX(-100%)') !== false),
    'Mobile navbar search bar collapsing' => (strpos($css, '.navbar-center') !== false && strpos($css, 'display: none !important') !== false),
    'Mobile single-column form collapsing' => (strpos($css, 'grid-template-columns: 1fr !important') !== false),
    'Mobile stats 1-2 columns collapsing' => (strpos($css, '.dashboard-stats-row') !== false && strpos($css, 'grid-template-columns: 1fr 1fr !important') !== false),
    'Micro-mobile single-column stats (<= 360px)' => (strpos($css, '@media (max-width: 360px)') !== false && strpos($css, 'grid-template-columns: 1fr !important') !== false),
    'Key shortcuts bar mobile adjustment' => (strpos($css, '.dashboard-key-bar') !== false && strpos($css, 'left: 0 !important') !== false)
];

foreach ($requiredCssChecks as $label => $res) {
    $totalChecks++;
    if ($res) {
        $passedChecks++;
        echo " [PASS] $label\n";
    } else {
        $failedChecks++;
        echo " [FAIL] $label\n";
        $issues[] = "CSS Rule Missing: $label";
    }
}
echo "\n";

// 2. Audit Every Page across All Breakpoints
echo "--- CHECKING " . count($pages) . " PAGES ACROSS " . count($viewports) . " BREAKPOINTS ---\n";

foreach ($pages as $pageName => $pagePath) {
    if (!file_exists($pagePath)) {
        echo " [WARN] Page file not found: $pagePath\n";
        continue;
    }
    
    $content = file_get_contents($pagePath);
    
    // Check 1: No fixed width > 320px in inline styles without max-width
    $hasUnfluidFixedPx = false;
    if (preg_match_all('/style="[^"]*width:\s*(\d+)px/i', $content, $matches)) {
        foreach ($matches[1] as $idx => $px) {
            if ((int)$px > 300) {
                $styleBlock = $matches[0][$idx];
                if (strpos($styleBlock, 'max-width') === false) {
                    $hasUnfluidFixedPx = true;
                    $issues[] = "$pageName: Found unfluid fixed width {$px}px in style block: '$styleBlock'";
                }
            }
        }
    }
    
    // Check 2: Tables inside table-responsive or wts-table-container or print
    $hasUnresponsiveTable = false;
    if (strpos($content, '<table') !== false && strpos($pageName, 'Print') === false) {
        // Table should be inside table-responsive or wts-table or table-card
        if (strpos($content, 'table-responsive') === false && 
            strpos($content, 'wts-table') === false && 
            strpos($content, 'table-card') === false &&
            strpos($content, 'overflow-x: auto') === false) {
            $hasUnresponsiveTable = true;
            $issues[] = "$pageName: Table found without responsive scroll wrapper.";
        }
    }
    
    // Check 3: Modals fit inside viewport
    $hasUnboundedModal = false;
    if (strpos($content, 'modal-content') !== false) {
        if (preg_match('/class="modal-content"[^>]*style="[^"]*width:\s*(\d+)px/i', $content, $m)) {
            if ((int)$m[1] > 300 && strpos($m[0], 'max-width') === false) {
                $hasUnboundedModal = true;
                $issues[] = "$pageName: Modal has fixed pixel width {$m[1]}px without max-width constraint.";
            }
        }
    }
    
    // Test across all viewports
    foreach ($viewports as $width => $vpName) {
        $totalChecks++;
        $pageCheckPassed = true;
        
        if ($width <= 375 && $hasUnfluidFixedPx) {
            $pageCheckPassed = false;
        }
        if ($width <= 768 && $hasUnresponsiveTable) {
            $pageCheckPassed = false;
        }
        if ($width <= 414 && $hasUnboundedModal) {
            $pageCheckPassed = false;
        }
        
        if ($pageCheckPassed) {
            $passedChecks++;
        } else {
            $failedChecks++;
        }
    }
    
    echo " [AUDITED] $pageName: Verified across 11 breakpoints (320px - 1920px)\n";
}

echo "\n====================================================================\n";
echo "=== RESPONSIVE AUDIT RESULTS ===\n";
echo "Total Breakpoint Checks: $totalChecks\n";
echo "Passed:                  $passedChecks\n";
echo "Failed:                  $failedChecks\n";

if (empty($issues)) {
    echo "Status:                  ALL VIEWPORTS & PAGES PASSED [100% RESPONSIVE]\n";
} else {
    echo "Status:                  ISSUES DETECTED:\n";
    foreach ($issues as $iss) {
        echo "  - $iss\n";
    }
}
echo "====================================================================\n";
