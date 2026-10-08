<?php

namespace App\Services;

use App\Repositories\ReportRepository;
use App\Services\AccountingService;

class ReportService
{
    /**
     * Get report data by type.
     */
    public static function generateReport(string $reportType, int $companyId, array $filters = []): array
    {
        $type = strtolower(trim($reportType));

        switch ($type) {
            case 'sales':
                return ReportRepository::getSalesReport($companyId, $filters);
            case 'purchases':
            case 'purchase':
                return ReportRepository::getPurchaseReport($companyId, $filters);
            case 'inventory':
            case 'stock':
                return ReportRepository::getInventoryReport($companyId, $filters);
            case 'stock-ledger':
            case 'stock_ledger':
                return ReportRepository::getStockLedgerReport($companyId, $filters);
            case 'party-outstanding':
            case 'party_outstanding':
            case 'outstanding':
                return ReportRepository::getPartyOutstandingReport($companyId, $filters);
            case 'payments':
            case 'payment':
                return ReportRepository::getPaymentReport($companyId, $filters);
            case 'expenses':
            case 'expense':
                return ReportRepository::getExpenseReport($companyId, $filters);
            case 'trial-balance':
            case 'trial_balance':
                return AccountingService::getTrialBalance($companyId, $filters['as_of_date'] ?? null, !empty($filters['branch_id']) ? intval($filters['branch_id']) : null, $filters['financial_year'] ?? null);
            case 'profit-loss':
            case 'profit_loss':
            case 'pnl':
                return AccountingService::getProfitAndLoss($companyId, $filters['from_date'] ?? null, $filters['to_date'] ?? null, !empty($filters['branch_id']) ? intval($filters['branch_id']) : null, $filters['financial_year'] ?? null);
            case 'balance-sheet':
            case 'balance_sheet':
                return AccountingService::getBalanceSheet($companyId, $filters['as_of_date'] ?? null, !empty($filters['branch_id']) ? intval($filters['branch_id']) : null, $filters['financial_year'] ?? null);
            case 'gst':
            case 'gstr':
                return ReportRepository::getGSTReport($companyId, $filters);
            case 'audit':
            case 'audit-log':
            case 'audit_log':
                return ReportRepository::getAuditReport($companyId, $filters);
            default:
                throw new \InvalidArgumentException("Unsupported report type: '{$reportType}'");
        }
    }

    /**
     * Stream or generate CSV export for a report.
     */
    public static function exportCSV(string $reportType, int $companyId, array $filters = []): string
    {
        $filters['per_page'] = 10000; // Fetch complete dataset for export without pagination clipping
        $report = static::generateReport($reportType, $companyId, $filters);

        $output = fopen('php://temp', 'r+');
        // UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        switch (strtolower($reportType)) {
            case 'sales':
                fputcsv($output, ['Invoice Number', 'Date', 'Due Date', 'Customer Name', 'Customer GSTIN', 'Taxable Amount', 'CGST', 'SGST', 'IGST', 'Total Tax', 'Grand Total', 'Amount Paid', 'Amount Due', 'Payment Status']);
                foreach ($report['data'] as $row) {
                    fputcsv($output, [
                        $row->invoice_number,
                        $row->invoice_date,
                        $row->due_date,
                        $row->customer_name,
                        $row->customer_gstin,
                        $row->sub_total,
                        $row->cgst_amount,
                        $row->sgst_amount,
                        $row->igst_amount,
                        $row->total_tax,
                        $row->grand_total,
                        $row->amount_paid,
                        $row->amount_due,
                        $row->payment_status,
                    ]);
                }
                break;

            case 'purchases':
            case 'purchase':
                fputcsv($output, ['Bill Number', 'Supplier Inv No', 'Date', 'Supplier Name', 'Supplier GSTIN', 'Taxable Amount', 'CGST', 'SGST', 'IGST', 'Total Tax', 'Grand Total', 'Amount Paid', 'Amount Due', 'Payment Status']);
                foreach ($report['data'] as $row) {
                    fputcsv($output, [
                        $row->purchase_number,
                        $row->supplier_invoice_no,
                        $row->purchase_date,
                        $row->supplier_name,
                        $row->supplier_gstin,
                        $row->sub_total,
                        $row->cgst_amount,
                        $row->sgst_amount,
                        $row->igst_amount,
                        $row->total_tax,
                        $row->grand_total,
                        $row->amount_paid,
                        $row->amount_due,
                        $row->payment_status,
                    ]);
                }
                break;

            case 'inventory':
            case 'stock':
                fputcsv($output, ['Product Name', 'SKU', 'HSN Code', 'Category', 'Current Stock', 'Unit', 'Purchase Price', 'Selling Price', 'Cost Valuation', 'Retail Valuation']);
                foreach ($report['data'] as $row) {
                    fputcsv($output, [
                        $row->name,
                        $row->sku,
                        $row->hsn_code,
                        $row->category_name,
                        $row->current_stock,
                        $row->unit_code,
                        $row->purchase_price,
                        $row->selling_price,
                        $row->cost_value,
                        $row->retail_value,
                    ]);
                }
                break;

            case 'expenses':
            case 'expense':
                fputcsv($output, ['Expense No', 'Date', 'Category', 'Payee', 'Amount', 'Tax Amount', 'Mode', 'ITC Eligible', 'Description']);
                foreach ($report['data'] as $row) {
                    fputcsv($output, [
                        $row->expense_number,
                        $row->expense_date,
                        $row->category,
                        $row->payee,
                        $row->amount,
                        $row->tax_amount,
                        $row->payment_mode,
                        $row->is_itc_eligible ? 'YES' : 'NO',
                        $row->description,
                    ]);
                }
                break;

            case 'trial-balance':
            case 'trial_balance':
                fputcsv($output, ['Account Code', 'Account Name', 'Account Type', 'Nature', 'Debit (INR)', 'Credit (INR)']);
                foreach ($report['accounts'] as $acc) {
                    fputcsv($output, [
                        $acc['account_code'],
                        $acc['account_name'],
                        $acc['account_type'],
                        $acc['nature'],
                        $acc['debit'],
                        $acc['credit'],
                    ]);
                }
                fputcsv($output, ['TOTAL', '', '', '', $report['total_debit'], $report['total_credit']]);
                break;

            default:
                fputcsv($output, ['Record ID', 'Data Payload']);
                if (isset($report['data'])) {
                    foreach ($report['data'] as $row) {
                        fputcsv($output, (array)$row);
                    }
                }
                break;
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
