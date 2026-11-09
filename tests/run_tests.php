<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// Bootstrap Autoloader and helpers
require_once ROOT_PATH . '/app/Core/Autoloader.php';
\WTSBill\Core\Autoloader::register();
require_once ROOT_PATH . '/app/Core/helpers.php';

// Load Unit, Integration, Feature, and Security test classes
require_once ROOT_PATH . '/tests/Unit/CoreRouterTest.php';
require_once ROOT_PATH . '/tests/Unit/CoreRequestTest.php';
require_once ROOT_PATH . '/tests/Unit/CoreResponseTest.php';
require_once ROOT_PATH . '/tests/Unit/DatabaseConfigurationTest.php';
require_once ROOT_PATH . '/tests/Integration/DatabaseConnectionTest.php';
require_once ROOT_PATH . '/tests/Integration/DatabaseTransactionTest.php';
require_once ROOT_PATH . '/tests/Integration/ProjectBootstrapTest.php';
require_once ROOT_PATH . '/tests/Feature/AuthenticationTest.php';
require_once ROOT_PATH . '/tests/Feature/AuthorizationTest.php';
require_once ROOT_PATH . '/tests/Security/CsrfProtectionTest.php';
require_once ROOT_PATH . '/tests/Security/TenantIsolationTest.php';
require_once ROOT_PATH . '/tests/Security/AuthenticationSecurityTest.php';
require_once ROOT_PATH . '/tests/Security/SqlInjectionTest.php';
require_once ROOT_PATH . '/tests/Security/XssProtectionTest.php';
require_once ROOT_PATH . '/tests/Security/AccessControlTest.php';
require_once ROOT_PATH . '/tests/Feature/LayoutRenderingTest.php';
require_once ROOT_PATH . '/tests/Feature/FrontendIntegrationTest.php';
require_once ROOT_PATH . '/tests/Integration/PerformanceSmokeTest.php';
require_once ROOT_PATH . '/tests/Integration/DatabaseIntegrityTest.php';
require_once ROOT_PATH . '/tests/Integration/BusinessLogicParityTest.php';
require_once ROOT_PATH . '/tests/Integration/RouteParityTest.php';
require_once ROOT_PATH . '/tests/Integration/PrintOutputParityTest.php';
require_once ROOT_PATH . '/tests/Feature/FullWorkflowRegressionTest.php';
require_once ROOT_PATH . '/tests/Feature/ProductManagementTest.php';
require_once ROOT_PATH . '/tests/Feature/GlobalPopupFixTest.php';
require_once ROOT_PATH . '/tests/Feature/BranchSwitchingTest.php';
require_once ROOT_PATH . '/tests/Feature/BalanceSheetStatementTest.php';
require_once ROOT_PATH . '/tests/Feature/InventoryStockManagementTest.php';
require_once ROOT_PATH . '/tests/Feature/PurchaseBillsPayableTest.php';
require_once ROOT_PATH . '/tests/Feature/TaxInvoicePrintTemplateTest.php';
require_once ROOT_PATH . '/tests/Feature/SettingsEditTest.php';
require_once ROOT_PATH . '/tests/Feature/InvoiceEditDeleteTest.php';
require_once ROOT_PATH . '/tests/Feature/CustomerDeleteTest.php';
require_once ROOT_PATH . '/tests/Feature/AuditLogSettingsTest.php';
require_once ROOT_PATH . '/tests/Feature/DashboardInvoiceViewTest.php';
require_once ROOT_PATH . '/tests/Feature/QuotationPrintTest.php';
require_once ROOT_PATH . '/tests/Security/RbacRoleIsolationTest.php';
require_once ROOT_PATH . '/tests/Feature/InvoiceCategoryFilterTest.php';
require_once ROOT_PATH . '/tests/Feature/CategoryManagementTest.php';
require_once ROOT_PATH . '/tests/Feature/PartialPaymentTest.php';
require_once ROOT_PATH . '/tests/Feature/QuotationManagementTest.php';
require_once ROOT_PATH . '/tests/Feature/SupplierDeleteTest.php';
require_once ROOT_PATH . '/tests/Feature/AccountManagementTest.php';
require_once ROOT_PATH . '/tests/Feature/DynamicUpiQrPaymentTest.php';
require_once ROOT_PATH . '/tests/Feature/CommunicationSharingTest.php';
require_once ROOT_PATH . '/tests/Feature/ThemePersistenceTest.php';
require_once ROOT_PATH . '/tests/Feature/BackupAndCleanupTest.php';
require_once ROOT_PATH . '/tests/Feature/SessionIdleDraftTest.php';
require_once ROOT_PATH . '/tests/Feature/LedgerStatementTest.php';
require_once ROOT_PATH . '/tests/Feature/CustomerProfileTest.php';
require_once ROOT_PATH . '/tests/Feature/DocumentFilterTest.php';
require_once ROOT_PATH . '/tests/Feature/InvoiceNumberReuseTest.php';
require_once ROOT_PATH . '/tests/Feature/CrmExpansionTest.php';
require_once ROOT_PATH . '/tests/Feature/SalesOrderWorkflowTest.php';
require_once ROOT_PATH . '/tests/Feature/WarehouseStockTransferTest.php';
require_once ROOT_PATH . '/tests/Feature/ProcurementLifecycleTest.php';
require_once ROOT_PATH . '/tests/Feature/CustomerAdvancesAgingTest.php';
require_once ROOT_PATH . '/tests/Feature/ExtendedAccountingReportsTest.php';
require_once ROOT_PATH . '/tests/Feature/GstRegistersComplianceTest.php';
require_once ROOT_PATH . '/tests/Feature/BusinessAnalyticsTest.php';
require_once ROOT_PATH . '/tests/Feature/NotificationCenterHubTest.php';
require_once ROOT_PATH . '/tests/Feature/CompanyBrandingTest.php';
require_once ROOT_PATH . '/tests/Feature/UnifiedPartiesTest.php';
require_once ROOT_PATH . '/tests/Feature/InvoicePaymentStatusReportTest.php';



$totalTests = 0;
$passed = 0;
$failed = 0;

$assert = function (string $code, string $description, bool $condition, ?string $detail = null) use (&$totalTests, &$passed, &$failed): void {
    $totalTests++;
    if ($condition) {
        $passed++;
        echo "\033[32m[PASS]\033[0m {$code} | {$description}\n";
    } else {
        $failed++;
        echo "\033[31m[FAIL]\033[0m {$code} | {$description}" . ($detail ? " -> {$detail}" : '') . "\n";
    }
};

echo "==================================================================================\n";
echo "              WTSBILL ERP — COMPLETE ARCHITECTURAL TEST SUITE                     \n";
echo "==================================================================================\n\n";

echo "--- SECTION 1: UNIT TESTS — CORE ROUTER ---\n";
\Tests\Unit\CoreRouterTest::run($assert);

echo "\n--- SECTION 2: UNIT TESTS — CORE REQUEST ---\n";
\Tests\Unit\CoreRequestTest::run($assert);

echo "\n--- SECTION 3: UNIT TESTS — CORE RESPONSE ---\n";
\Tests\Unit\CoreResponseTest::run($assert);

echo "\n--- SECTION 4: UNIT TESTS — DATABASE CONFIGURATION ---\n";
\Tests\Unit\DatabaseConfigurationTest::run($assert);

echo "\n--- SECTION 5: INTEGRATION TESTS — DATABASE CONNECTION & BASELINE ---\n";
\Tests\Integration\DatabaseConnectionTest::run($assert);

echo "\n--- SECTION 6: INTEGRATION TESTS — DATABASE TRANSACTIONS & REPOSITORY ---\n";
\Tests\Integration\DatabaseTransactionTest::run($assert);

echo "\n--- SECTION 7: FEATURE TESTS — AUTHENTICATION & SESSION LIFECYCLE ---\n";
\Tests\Feature\AuthenticationTest::run($assert);

echo "\n--- SECTION 8: FEATURE TESTS — RBAC AUTHORIZATION ---\n";
\Tests\Feature\AuthorizationTest::run($assert);

echo "\n--- SECTION 9: SECURITY TESTS — CSRF PROTECTION ---\n";
\Tests\Security\CsrfProtectionTest::run($assert);

echo "\n--- SECTION 10: SECURITY TESTS — MULTI-TENANT ISOLATION ---\n";
\Tests\Security\TenantIsolationTest::run($assert);

echo "\n--- SECTION 11: SECURITY TESTS — AUTHENTICATION & SESSION DEFENSE ---\n";
\Tests\Security\AuthenticationSecurityTest::run($assert);

echo "\n--- SECTION 12: SECURITY TESTS — SQL INJECTION DEFENSE ---\n";
\Tests\Security\SqlInjectionTest::run($assert);

echo "\n--- SECTION 13: SECURITY TESTS — XSS & CONTENT INJECTION DEFENSE ---\n";
\Tests\Security\XssProtectionTest::run($assert);

echo "\n--- SECTION 14: SECURITY TESTS — ACCESS CONTROL & IDOR DEFENSE ---\n";
\Tests\Security\AccessControlTest::run($assert);

echo "\n--- SECTION 15: INTEGRATION TESTS — PROJECT BOOTSTRAP ---\n";
\Tests\Integration\ProjectBootstrapTest::run($assert);

echo "\n--- SECTION 16: FEATURE TESTS — MASTER LAYOUTS & ASSETS ---\n";
\Tests\Feature\LayoutRenderingTest::run($assert);

echo "\n--- SECTION 17: FEATURE TESTS — FRONTEND INTEGRATION & ASSETS ---\n";
\Tests\Feature\FrontendIntegrationTest::run($assert);

echo "\n--- SECTION 18: INTEGRATION TESTS — PERFORMANCE & PERCEIVED LOADING ---\n";
\Tests\Integration\PerformanceSmokeTest::run($assert);

echo "\n--- SECTION 19: INTEGRATION TESTS — DATABASE INTEGRITY & SCHEMA ---\n";
\Tests\Integration\DatabaseIntegrityTest::run($assert);

echo "\n--- SECTION 20: INTEGRATION TESTS — BUSINESS LOGIC & CALCULATION PARITY ---\n";
\Tests\Integration\BusinessLogicParityTest::run($assert);

echo "\n--- SECTION 21: INTEGRATION TESTS — ROUTE & METHOD PARITY ---\n";
\Tests\Integration\RouteParityTest::run($assert);

echo "\n--- SECTION 22: INTEGRATION TESTS — PRINT & DOCUMENT OUTPUT PARITY ---\n";
\Tests\Integration\PrintOutputParityTest::run($assert);

echo "\n--- SECTION 23: FEATURE TESTS — FULL ENTERPRISE WORKFLOW REGRESSION ---\n";
\Tests\Feature\FullWorkflowRegressionTest::run($assert);

echo "\n--- SECTION 24: FEATURE TESTS — PRODUCT & INVENTORY MANAGEMENT ---\n";
\Tests\Feature\ProductManagementTest::run($assert);

echo "\n--- SECTION 25: FEATURE TESTS — GLOBAL PLACEHOLDER POPUP FIX ---\n";
\Tests\Feature\GlobalPopupFixTest::run($assert);

echo "\n--- SECTION 26: FEATURE TESTS — BRANCH MANAGEMENT & SWITCHING SYSTEM ---\n";
\Tests\Feature\BranchSwitchingTest::run($assert);

echo "\n--- SECTION 27: FEATURE TESTS — ACCOUNTANT-GRADE BALANCE SHEET STATEMENT ---\n";
\Tests\Feature\BalanceSheetStatementTest::run($assert);

echo "\n--- SECTION 28: FEATURE TESTS — INVENTORY STOCK & WAREHOUSE BALANCES UPGRADE ---\n";
\Tests\Feature\InventoryStockManagementTest::run($assert);

echo "\n--- SECTION 29: FEATURE TESTS — PURCHASE BILLS & ACCOUNTS PAYABLE UPGRADE ---\n";
\Tests\Feature\PurchaseBillsPayableTest::run($assert);

echo "\n--- SECTION 30: FEATURE TESTS — TAX INVOICE PRINT & PDF TEMPLATE REBUILD ---\n";
\Tests\Feature\TaxInvoicePrintTemplateTest::run($assert);

echo "\n--- SECTION 31: FEATURE TESTS — ENTERPRISE SYSTEM SETTINGS & PROFILE EDIT ---\n";
\Tests\Feature\SettingsEditTest::run($assert);

echo "\n--- SECTION 32: FEATURE TESTS — TAX INVOICE EDIT & DELETE ACTIONS ---\n";
\Tests\Feature\InvoiceEditDeleteTest::run($assert);
 
echo "\n--- SECTION 33: FEATURE TESTS — CUSTOMER DELETION & DIRECTORY MANAGEMENT ---\n";
\Tests\Feature\CustomerDeleteTest::run($assert);

echo "\n--- SECTION 34: FEATURE TESTS — ENTERPRISE AUDIT LOGS IN SETTINGS (ADMIN ONLY) ---\n";
\Tests\Feature\AuditLogSettingsTest::run($assert);

echo "\n--- SECTION 35: FEATURE TESTS — DASHBOARD INVOICE VIEW & ROUTE PARITY ---\n";
\Tests\Feature\DashboardInvoiceViewTest::run($assert);

echo "\n--- SECTION 36: FEATURE TESTS — QUOTATIONS PRINT OPTION & DOCUMENT TEMPLATE ---\n";
\Tests\Feature\QuotationPrintTest::run($assert);

echo "\n--- SECTION 37: SECURITY TESTS — RBAC ROLE ISOLATION & SERVER-SIDE GATING ---\n";
\Tests\Security\RbacRoleIsolationTest::run($assert);

echo "\n--- SECTION 38: FEATURE TESTS — DYNAMIC PRODUCT CATEGORY FILTER IN INVOICE ITEMS ---\n";
\Tests\Feature\InvoiceCategoryFilterTest::run($assert);

echo "\n--- SECTION 39: FEATURE TESTS — CATEGORY MANAGEMENT (EDIT, DELETE, MANAGE) ---\n";
\Tests\Feature\CategoryManagementTest::run($assert);

echo "\n--- SECTION 40: FEATURE TESTS — PARTIAL PAYMENT MANAGEMENT & INSTALLMENTS ---\n";
\Tests\Feature\PartialPaymentTest::run($assert);

echo "\n--- SECTION 41: FEATURE TESTS — DEDICATED QUOTATION MANAGEMENT & CONVERSION ---\n";
\Tests\Feature\QuotationManagementTest::run($assert);

echo "\n--- SECTION 42: FEATURE TESTS — SUPPLIER DELETION & VENDOR DIRECTORY MANAGEMENT ---\n";
\Tests\Feature\SupplierDeleteTest::run($assert);

echo "\n--- SECTION 43: FEATURE TESTS — CHART OF ACCOUNTS (EDIT & DELETE OPTIONS) ---\n";
\Tests\Feature\AccountManagementTest::run($assert);

echo "\n--- SECTION 44: FEATURE TESTS — DYNAMIC REMAINING BALANCE UPI QR CODE ---\n";
\Tests\Feature\DynamicUpiQrPaymentTest::run($assert);

echo "\n--- SECTION 45: FEATURE TESTS — WHATSAPP & EMAIL DOCUMENT SHARING ---\n";
\Tests\Feature\CommunicationSharingTest::run($assert);

echo "\n--- SECTION 46: FEATURE TESTS — LIGHT & DARK THEME SWITCHER & PERSISTENCE ---\n";
\Tests\Feature\ThemePersistenceTest::run($assert);

echo "\n--- SECTION 47: FEATURE TESTS — AUTOMATIC WEEKLY BACKUP & DISASTER RECOVERY ---\n";
\Tests\Feature\BackupAndCleanupTest::run($assert);

echo "\n--- SECTION 48: FEATURE TESTS — 15-MINUTE INACTIVITY TIMEOUT & DRAFT SAVING ---\n";
\Tests\Feature\SessionIdleDraftTest::run($assert);

echo "\n--- SECTION 49: FEATURE TESTS — GENERAL LEDGER & TRANSACTION VISIBILITY ---\n";
\Tests\Feature\LedgerStatementTest::run($assert);

echo "\n--- SECTION 50: FEATURE TESTS — COMPLETE 360-DEGREE CUSTOMER PROFILE & HISTORY ---\n";
\Tests\Feature\CustomerProfileTest::run($assert);

echo "\n--- SECTION 51: FEATURE TESTS — COMPREHENSIVE INVOICE & QUOTATION LIST FILTERS ---\n";
\Tests\Feature\DocumentFilterTest::run($assert);

echo "\n--- SECTION 52: FEATURE TESTS — INVOICE NUMBER REUSE & STATUTORY PRESERVATION ---\n";
\Tests\Feature\InvoiceNumberReuseTest::run($assert);

echo "\n--- SECTION 53: FEATURE TESTS — CRM & PARTY 360-DEGREE PROFILE UPGRADES ---\n";
\Tests\Feature\CrmExpansionTest::run($assert);

echo "\n--- SECTION 54: FEATURE TESTS — SALES ORDER WORKFLOW & FULFILLMENT LIFECYCLE ---\n";
\Tests\Feature\SalesOrderWorkflowTest::run($assert);

echo "\n--- SECTION 55: FEATURE TESTS — WAREHOUSES, LOGISTICS & STOCK TRANSFERS ---\n";
\Tests\Feature\WarehouseStockTransferTest::run($assert);

echo "\n--- SECTION 56: FEATURE TESTS — PROCUREMENT LIFECYCLE (PO, GRN, DEBIT NOTES) ---\n";
\Tests\Feature\ProcurementLifecycleTest::run($assert);

echo "\n--- SECTION 57: FEATURE TESTS — CUSTOMER ADVANCES & RECEIVABLES AGING ---\n";
\Tests\Feature\CustomerAdvancesAgingTest::run($assert);

echo "\n--- SECTION 58: FEATURE TESTS — COMPLETE DOUBLE-ENTRY ACCOUNTING REPORTS ---\n";
\Tests\Feature\ExtendedAccountingReportsTest::run($assert);

echo "\n--- SECTION 59: FEATURE TESTS — GST REGISTERS & GSTR-3B COMPLIANCE ---\n";
\Tests\Feature\GstRegistersComplianceTest::run($assert);

echo "\n--- SECTION 60: FEATURE TESTS — BUSINESS ANALYTICS & EXECUTIVE BI DASHBOARD ---\n";
\Tests\Feature\BusinessAnalyticsTest::run($assert);

echo "\n--- SECTION 61: FEATURE TESTS — CENTRAL NOTIFICATION HUB & AI REMOVAL VERIFICATION ---\n";
\Tests\Feature\NotificationCenterHubTest::run($assert);

echo "\n--- SECTION 62: FEATURE TESTS — CENTRALIZED COMPANY BRANDING MANAGEMENT ---\n";
\Tests\Feature\CompanyBrandingTest::run($assert);

echo "\n--- SECTION 63: FEATURE TESTS — UNIFIED PARTIES MANAGEMENT HUB ---\n";
\Tests\Feature\UnifiedPartiesTest::run($assert);

echo "\n--- SECTION 64: FEATURE TESTS — INVOICE PAYMENT STATUS REPORT & EXCEL EXPORT ---\n";
\Tests\Feature\InvoicePaymentStatusReportTest::run($assert);




echo "\n==================================================================================\n";
echo "                         TEST VERIFICATION SUMMARY                                \n";
echo "==================================================================================\n";
echo "Total Assertions: {$totalTests}\n";
echo "Passed:           {$passed}\n";
echo "Failed:           {$failed}\n";
$pct = $totalTests > 0 ? round(($passed / $totalTests) * 100, 2) : 0;
echo "Pass Percentage:  {$pct}%\n\n";

if ($failed === 0) {
    echo "\033[32mSTATUS: ALL TEST SUITES PASSED (100%)\033[0m\n";
    exit(0);
} else {
    echo "\033[31mSTATUS: TEST SUITE HAS FAILING ASSERTIONS\033[0m\n";
    exit(1);
}
