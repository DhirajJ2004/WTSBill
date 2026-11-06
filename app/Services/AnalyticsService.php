<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;

/**
 * Business Analytics & Executive BI Reporting Service.
 * Computes revenue performance, margin analytics, time-series sales trends,
 * top products/customers rankings, and branch benchmarks.
 */
class AnalyticsService
{
    /**
     * Compute Executive BI Analytics Dashboard Data.
     */
    public function getExecutiveDashboard(int $companyId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? (string)$filters['from_date'] : date('Y-m-01');
        $toDate = !empty($filters['to_date']) ? (string)$filters['to_date'] : date('Y-m-d');
        $branchId = (int)($filters['branch_id'] ?? 0);

        $branchSql = "";
        $params = [$companyId, $fromDate, $toDate];
        if ($branchId > 0) {
            $branchSql = " AND (branch_id = ? OR branch_id IS NULL)";
            $params[] = $branchId;
        }

        // 1. Revenue & Sales Invoicing
        $revRow = Database::selectOne(
            "SELECT 
                COALESCE(COUNT(id), 0) AS total_invoices,
                COALESCE(SUM(sub_total), 0) AS total_taxable,
                COALESCE(SUM(total_tax), 0) AS total_tax,
                COALESCE(SUM(grand_total), 0) AS total_revenue,
                COALESCE(SUM(amount_paid), 0) AS total_collected,
                COALESCE(SUM(amount_due), 0) AS total_outstanding
             FROM invoices 
             WHERE company_id = ? 
               AND invoice_date BETWEEN ? AND ? 
               AND deleted_at IS NULL 
               {$branchSql}",
            $params
        );

        $totalRevenue = (float)($revRow['total_revenue'] ?? 0);
        $taxableRevenue = (float)($revRow['total_taxable'] ?? 0);
        $totalCollected = (float)($revRow['total_collected'] ?? 0);
        $totalOutstanding = (float)($revRow['total_outstanding'] ?? 0);
        $invoiceCount = (int)($revRow['total_invoices'] ?? 0);

        // 2. Direct Procurement / COGS Inward
        $cogsRow = Database::selectOne(
            "SELECT 
                COALESCE(COUNT(id), 0) AS total_bills,
                COALESCE(SUM(sub_total), 0) AS total_purchases
             FROM purchases 
             WHERE company_id = ? 
               AND purchase_date BETWEEN ? AND ? 
               AND deleted_at IS NULL 
               {$branchSql}",
            $params
        );
        $cogsAmount = (float)($cogsRow['total_purchases'] ?? 0);

        // 3. Operating Overhead Expenses
        $expBranchSql = "";
        $expParams = [$companyId, $fromDate, $toDate];
        if ($branchId > 0) {
            $expBranchSql = " AND (branch_id = ? OR branch_id IS NULL)";
            $expParams[] = $branchId;
        }

        $expRow = Database::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total_overhead 
             FROM expenses 
             WHERE company_id = ? 
               AND expense_date BETWEEN ? AND ? 
               {$expBranchSql}",
            $expParams
        );
        $operatingExpenses = (float)($expRow['total_overhead'] ?? 0);

        // 4. Margins & Profitability
        $grossProfit = round($taxableRevenue - $cogsAmount, 2);
        $grossMarginPct = $taxableRevenue > 0 ? round(($grossProfit / $taxableRevenue) * 100, 2) : 0.0;
        $netProfit = round($grossProfit - $operatingExpenses, 2);
        $netMarginPct = $taxableRevenue > 0 ? round(($netProfit / $taxableRevenue) * 100, 2) : 0.0;

        // 5. Top 5 Selling Products by Turnover
        $invItemBranchSql = "";
        $itemParams = [$companyId, $fromDate, $toDate];
        if ($branchId > 0) {
            $invItemBranchSql = " AND (i.branch_id = ? OR i.branch_id IS NULL)";
            $itemParams[] = $branchId;
        }

        $topProducts = Database::select(
            "SELECT 
                ii.product_id,
                ii.item_name,
                COALESCE(p.sku, 'PROD-SKU') AS sku,
                COALESCE(SUM(ii.quantity), 0) AS units_sold,
                COALESCE(SUM(ii.taxable_value), 0) AS total_taxable,
                COALESCE(SUM(ii.total_amount), 0) AS gross_sales
             FROM invoice_items ii
             INNER JOIN invoices i ON ii.invoice_id = i.id
             LEFT JOIN products p ON ii.product_id = p.id
             WHERE i.company_id = ? 
               AND i.invoice_date BETWEEN ? AND ? 
               AND i.deleted_at IS NULL 
               {$invItemBranchSql}
             GROUP BY ii.product_id, ii.item_name, p.sku
             ORDER BY gross_sales DESC 
             LIMIT 5",
            $itemParams
        );

        // 6. Top 5 Customer Accounts by Turnover
        $topCustomers = Database::select(
            "SELECT 
                i.customer_id,
                COALESCE(c.name, i.customer_name, 'Direct Client') AS customer_name,
                COALESCE(c.city, 'Pune') AS city,
                COALESCE(c.gstin, '') AS gstin,
                COUNT(i.id) AS invoice_count,
                COALESCE(SUM(i.grand_total), 0) AS total_billed,
                COALESCE(SUM(i.amount_paid), 0) AS total_paid,
                COALESCE(SUM(i.amount_due), 0) AS total_due
             FROM invoices i
             LEFT JOIN customers c ON i.customer_id = c.id
             WHERE i.company_id = ? 
               AND i.invoice_date BETWEEN ? AND ? 
               AND i.deleted_at IS NULL 
               {$invItemBranchSql}
             GROUP BY i.customer_id, customer_name, c.city, c.gstin
             ORDER BY total_billed DESC 
             LIMIT 5",
            $itemParams
        );

        // 7. Monthly Revenue Trends (Last 6 Months)
        $trends = [];
        for ($m = 5; $m >= 0; $m--) {
            $startM = date('Y-m-01', strtotime("-{$m} months"));
            $endM = date('Y-m-t', strtotime("-{$m} months"));
            $monthLabel = date('M Y', strtotime($startM));

            $mRev = Database::selectOne(
                "SELECT COALESCE(SUM(grand_total), 0) AS revenue 
                 FROM invoices 
                 WHERE company_id = ? AND invoice_date BETWEEN ? AND ? AND deleted_at IS NULL {$branchSql}",
                [$companyId, $startM, $endM]
            );

            $mExp = Database::selectOne(
                "SELECT COALESCE(SUM(amount), 0) AS expense 
                 FROM expenses 
                 WHERE company_id = ? AND expense_date BETWEEN ? AND ? {$expBranchSql}",
                [$companyId, $startM, $endM]
            );

            $trends[] = [
                'month' => $monthLabel,
                'revenue' => (float)($mRev['revenue'] ?? 0),
                'expense' => (float)($mExp['expense'] ?? 0),
            ];
        }

        // 8. Branch Performance Breakdown
        $branchPerformance = Database::select(
            "SELECT 
                b.id,
                b.name AS branch_name,
                COALESCE(COUNT(i.id), 0) AS invoice_count,
                COALESCE(SUM(i.grand_total), 0) AS total_sales,
                COALESCE(SUM(i.amount_due), 0) AS outstanding
             FROM branches b
             LEFT JOIN invoices i ON b.id = i.branch_id AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL
             WHERE b.company_id = ? AND b.is_active = 1
             GROUP BY b.id, b.name
             ORDER BY total_sales DESC",
            [$fromDate, $toDate, $companyId]
        );

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'metrics' => [
                'total_revenue' => $totalRevenue,
                'taxable_revenue' => $taxableRevenue,
                'total_collected' => $totalCollected,
                'total_outstanding' => $totalOutstanding,
                'invoice_count' => $invoiceCount,
                'cogs_amount' => $cogsAmount,
                'operating_expenses' => $operatingExpenses,
                'gross_profit' => $grossProfit,
                'gross_margin_pct' => $grossMarginPct,
                'net_profit' => $netProfit,
                'net_margin_pct' => $netMarginPct,
            ],
            'top_products' => $topProducts,
            'top_customers' => $topCustomers,
            'trends' => $trends,
            'branches' => $branchPerformance,
        ];
    }

    /**
     * Export Executive Analytics Summary as CSV.
     */
    public function exportExecutiveReportCsv(int $companyId, array $filters = []): string
    {
        $dash = $this->getExecutiveDashboard($companyId, $filters);
        $m = $dash['metrics'];
        $lines = [];

        $lines[] = '"EXECUTIVE BI ANALYTICS SUMMARY","Period: ' . $dash['from_date'] . ' to ' . $dash['to_date'] . '"';
        $lines[] = '"Metric Description","Value (INR / %)"';
        $lines[] = sprintf('"Total Invoiced Revenue",%.2f', $m['total_revenue']);
        $lines[] = sprintf('"Taxable Sales Base",%.2f', $m['taxable_revenue']);
        $lines[] = sprintf('"Cost of Goods Sold (Inward Purchases)",%.2f', $m['cogs_amount']);
        $lines[] = sprintf('"Gross Profit",%.2f', $m['gross_profit']);
        $lines[] = sprintf('"Gross Profit Margin",%.2f%%', $m['gross_margin_pct']);
        $lines[] = sprintf('"Operating Overhead Expenses",%.2f', $m['operating_expenses']);
        $lines[] = sprintf('"Net Profit / Loss",%.2f', $m['net_profit']);
        $lines[] = sprintf('"Net Profit Margin",%.2f%%', $m['net_margin_pct']);
        $lines[] = sprintf('"Total Cash Collections",%.2f', $m['total_collected']);
        $lines[] = sprintf('"Outstanding Accounts Receivable",%.2f', $m['total_outstanding']);

        $lines[] = '';
        $lines[] = '"TOP 5 SELLING PRODUCTS"';
        $lines[] = '"Product Name","SKU","Units Sold","Taxable (INR)","Gross Sales (INR)"';
        foreach ($dash['top_products'] as $p) {
            $lines[] = sprintf(
                '"%s","%s",%.2f,%.2f,%.2f',
                str_replace('"', '""', (string)$p['item_name']),
                $p['sku'],
                (float)$p['units_sold'],
                (float)$p['total_taxable'],
                (float)$p['gross_sales']
            );
        }

        $lines[] = '';
        $lines[] = '"TOP 5 CUSTOMER ACCOUNTS"';
        $lines[] = '"Customer Name","City","GSTIN","Invoices","Total Billed (INR)","Total Paid (INR)","Due (INR)"';
        foreach ($dash['top_customers'] as $c) {
            $lines[] = sprintf(
                '"%s","%s","%s",%d,%.2f,%.2f,%.2f',
                str_replace('"', '""', (string)$c['customer_name']),
                $c['city'],
                $c['gstin'],
                (int)$c['invoice_count'],
                (float)$c['total_billed'],
                (float)$c['total_paid'],
                (float)$c['total_due']
            );
        }

        return implode("\r\n", $lines);
    }
}

