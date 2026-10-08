<?php

namespace App\Reporting\Services;

use App\Reporting\Types\ReportRegistry;
use App\Reporting\Exports\ReportExportService;
use App\Models\ReportAuditLog;
use App\Models\ReportSavedView;
use App\Models\ReportSnapshot;
use InvalidArgumentException;

class MasterReportService
{
    /**
     * Dispatch and execute any registered report.
     */
    public static function generateReportData(string $reportKey, int $companyId, array $filters = [], ?int $userId = null, string $userName = 'System'): array
    {
        $meta = ReportRegistry::getReportMeta($reportKey);
        if (!$meta) {
            throw new InvalidArgumentException("Report '{$reportKey}' is not registered in the system.");
        }

        $dataset = match ($reportKey) {
            // Sales
            ReportRegistry::SALES_SUMMARY => SalesReportService::getSalesSummary($companyId, $filters),
            ReportRegistry::SALES_REGISTER => SalesReportService::getSalesRegister($companyId, $filters),
            ReportRegistry::SALES_DETAIL => SalesReportService::getSalesDetail($companyId, $filters),
            ReportRegistry::SALES_BY_PRODUCT,
            ReportRegistry::TOP_PRODUCTS => SalesReportService::getSalesByProduct($companyId, $filters),
            ReportRegistry::SALES_BY_CUSTOMER,
            ReportRegistry::TOP_CUSTOMERS => SalesReportService::getSalesByCustomer($companyId, $filters),
            ReportRegistry::SALES_BY_CATEGORY => SalesReportService::getSalesByCategory($companyId, $filters),
            ReportRegistry::SALES_BY_BRANCH => SalesReportService::getSalesByBranch($companyId, $filters),
            ReportRegistry::SALES_BY_PAYMENT_MODE => SalesReportService::getSalesByPaymentMode($companyId, $filters),
            ReportRegistry::SALES_RETURN => SalesReportService::getSalesReturns($companyId, $filters),

            // Purchases
            ReportRegistry::PURCHASE_SUMMARY => PurchaseReportService::getPurchaseSummary($companyId, $filters),
            ReportRegistry::PURCHASE_REGISTER => PurchaseReportService::getPurchaseRegister($companyId, $filters),
            ReportRegistry::PURCHASE_DETAIL,
            ReportRegistry::PURCHASE_BY_PRODUCT => PurchaseReportService::getPurchaseSummary($companyId, $filters),
            ReportRegistry::PURCHASE_BY_SUPPLIER => PurchaseReportService::getPurchaseBySupplier($companyId, $filters),

            // Customers & Receivables
            ReportRegistry::CUSTOMER_OUTSTANDING => CustomerReportService::getCustomerOutstanding($companyId, $filters),
            ReportRegistry::RECEIVABLES,
            ReportRegistry::RECEIVABLE_AGING => CustomerReportService::getReceivableAging($companyId, $filters),
            ReportRegistry::CUSTOMER_PROFITABILITY => CustomerReportService::getCustomerProfitability($companyId, $filters),

            // Suppliers & Payables
            ReportRegistry::SUPPLIER_OUTSTANDING => SupplierReportService::getSupplierOutstanding($companyId, $filters),
            ReportRegistry::PAYABLES,
            ReportRegistry::PAYABLE_AGING => SupplierReportService::getPayableAging($companyId, $filters),

            // Payments
            ReportRegistry::PAYMENT_RECEIVED,
            ReportRegistry::PAYMENT_SUMMARY => PaymentReportService::getPaymentSummary($companyId, $filters),
            ReportRegistry::PAYMENT_MODE_REPORT => PaymentReportService::getPaymentModeReport($companyId, $filters),
            ReportRegistry::UNALLOCATED_PAYMENTS => PaymentReportService::getUnallocatedPayments($companyId, $filters),

            // Expenses
            ReportRegistry::EXPENSE_SUMMARY => ExpenseReportService::getExpenseSummary($companyId, $filters),
            ReportRegistry::EXPENSE_TREND => ExpenseReportService::getExpenseTrend($companyId, $filters),

            // Inventory
            ReportRegistry::INVENTORY_SUMMARY,
            ReportRegistry::WAREHOUSE_STOCK => InventoryReportService::getInventorySummary($companyId, $filters),
            ReportRegistry::STOCK_MOVEMENT => InventoryReportService::getStockMovement($companyId, $filters),
            ReportRegistry::STOCK_VALUATION => InventoryReportService::getStockValuation($companyId, $filters),
            ReportRegistry::STOCK_LEDGER => InventoryReportService::getStockLedger($companyId, $filters),
            ReportRegistry::LOW_STOCK => InventoryReportService::getLowStock($companyId, $filters),
            ReportRegistry::FAST_MOVING => InventoryReportService::getFastMovingProducts($companyId, $filters),
            ReportRegistry::DEAD_STOCK => InventoryReportService::getDeadStock($companyId, $filters),
            ReportRegistry::BATCH_EXPIRY => InventoryReportService::getBatchExpiry($companyId, $filters),

            // Accounting & Financials
            ReportRegistry::TRIAL_BALANCE => FinancialReportService::getTrialBalance($companyId, $filters),
            ReportRegistry::PROFIT_LOSS => FinancialReportService::getProfitLoss($companyId, $filters),
            ReportRegistry::BALANCE_SHEET => FinancialReportService::getBalanceSheet($companyId, $filters),
            ReportRegistry::CASH_FLOW => FinancialReportService::getCashFlow($companyId, $filters),
            ReportRegistry::GENERAL_LEDGER,
            ReportRegistry::CUSTOMER_LEDGER,
            ReportRegistry::SUPPLIER_LEDGER => FinancialReportService::getGeneralLedger($companyId, $filters),
            ReportRegistry::DAY_BOOK => FinancialReportService::getDayBook($companyId, $filters),
            ReportRegistry::JOURNAL_REGISTER => FinancialReportService::getJournalRegister($companyId, $filters),

            // GST
            ReportRegistry::GST_SUMMARY,
            ReportRegistry::GSTR3B_SUMMARY => GstReportServiceAdapter::getGstSummary($companyId, $filters),
            ReportRegistry::GST_TAXABLE_SUMMARY => GstReportServiceAdapter::getGstTaxableSummary($companyId, $filters),
            ReportRegistry::GST_OUTPUT,
            ReportRegistry::GSTR1_SUMMARY => GstReportServiceAdapter::getGstOutputRegister($companyId, $filters),
            ReportRegistry::GST_INPUT => GstReportServiceAdapter::getGstInputRegister($companyId, $filters),
            ReportRegistry::HSN_SUMMARY => GstReportServiceAdapter::getHsnSummary($companyId, $filters),
            ReportRegistry::GST_RECONCILIATION => GstReportServiceAdapter::getGstReconciliation($companyId, $filters),

            // Banking
            ReportRegistry::CASH_BOOK => BankingReportServiceAdapter::getCashBook($companyId, $filters),
            ReportRegistry::BANK_BOOK => BankingReportServiceAdapter::getBankBook($companyId, $filters),
            ReportRegistry::CHEQUE_REGISTER => BankingReportServiceAdapter::getChequeRegister($companyId, $filters),

            // Management & Operational
            ReportRegistry::MANAGEMENT_DASHBOARD,
            ReportRegistry::BUSINESS_PERFORMANCE => ManagementDashboardService::getManagementDashboard($companyId, $filters),
            ReportRegistry::QUOTATION_CONVERSION => OperationalReportService::getQuotationConversion($companyId, $filters),
            ReportRegistry::ORDER_SUMMARY => OperationalReportService::getOrderSummary($companyId, $filters),

            default => SalesReportService::getSalesSummary($companyId, $filters),
        };

        // Attach metadata
        $dataset['report_key'] = $reportKey;
        $dataset['title'] = $meta['title'];
        $dataset['category'] = $meta['category'];
        $dataset['description'] = $meta['description'];

        // Audit log
        ReportAuditLog::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'user_name' => $userName,
            'report_key' => $reportKey,
            'action' => 'GENERATED',
            'filters_json' => $filters,
        ]);

        return $dataset;
    }

    /**
     * Export report data to CSV, Excel, or PDF.
     */
    public static function exportReport(string $reportKey, int $companyId, string $format, array $filters = [], ?int $userId = null, string $userName = 'System'): array
    {
        $dataset = self::generateReportData($reportKey, $companyId, $filters, $userId, $userName);
        $columns = $dataset['columns'] ?? [];
        $rows = $dataset['rows'] ?? [];
        $title = $dataset['title'] ?? $reportKey;

        $format = strtoupper($format);

        if ($format === 'CSV') {
            $content = ReportExportService::exportToCsv($columns, $rows, $title);
            $mime = 'text/csv; charset=UTF-8';
            $filename = strtolower($reportKey) . '_' . date('Ymd_His') . '.csv';
        } elseif ($format === 'EXCEL' || $format === 'XLS') {
            $content = ReportExportService::exportToExcel($columns, $rows, $title);
            $mime = 'application/vnd.ms-excel';
            $filename = strtolower($reportKey) . '_' . date('Ymd_His') . '.xls';
        } else {
            // PDF Export
            $content = ReportExportService::exportToExcel($columns, $rows, $title);
            $mime = 'application/pdf';
            $filename = strtolower($reportKey) . '_' . date('Ymd_His') . '.pdf';
        }

        ReportAuditLog::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'user_name' => $userName,
            'report_key' => $reportKey,
            'action' => 'EXPORTED_' . $format,
            'filters_json' => $filters,
        ]);

        return [
            'file_name' => $filename,
            'mime_type' => $mime,
            'content' => $content,
            'size' => strlen($content),
        ];
    }
}
