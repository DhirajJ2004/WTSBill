<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\StockBalance;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\PaymentAllocation;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;

class BranchAccountingAndReportingService
{
    /**
     * Get branch-specific or consolidated Profit & Loss statement from accounting engine.
     */
    public static function getBranchProfitLoss(int $companyId, ?int $branchId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        return ProfitLossService::getProfitAndLoss($companyId, $fromDate, $toDate, $branchId);
    }

    /**
     * Get branch-specific or consolidated Balance Sheet from accounting engine.
     */
    public static function getBranchBalanceSheet(int $companyId, ?int $branchId = null, ?string $asOfDate = null): array
    {
        return BalanceSheetService::getBalanceSheet($companyId, $asOfDate, $branchId);
    }

    /**
     * Compare performance metrics across branches.
     */
    public static function getBranchPerformanceComparison(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $fromDate = $fromDate ?: Carbon::now()->startOfMonth()->toDateString();
        $toDate = $toDate ?: Carbon::now()->endOfMonth()->toDateString();

        $branches = Branch::where('company_id', $companyId)->where('is_active', true)->get();

        $branchNames = [];
        $salesSeries = [];
        $purchaseSeries = [];
        $profitSeries = [];
        $rows = [];

        $totalSales = 0.0;
        $totalPurchases = 0.0;
        $totalNetProfit = 0.0;

        foreach ($branches as $branch) {
            $bId = $branch->id;

            $sales = floatval(Invoice::where('company_id', $companyId)
                ->where('branch_id', $bId)
                ->whereBetween('invoice_date', [$fromDate, $toDate])
                ->where('status', '!=', 'CANCELLED')
                ->sum('grand_total'));

            $purchases = floatval(Purchase::where('company_id', $companyId)
                ->where('branch_id', $bId)
                ->whereBetween('purchase_date', [$fromDate, $toDate])
                ->where('status', '!=', 'CANCELLED')
                ->sum('grand_total'));

            $pnl = ProfitLossService::getProfitAndLoss($companyId, $fromDate, $toDate, $bId);
            $netProfit = floatval($pnl['net_profit'] ?? 0);

            $receivables = floatval(Invoice::where('company_id', $companyId)
                ->where('branch_id', $bId)
                ->where('status', '!=', 'CANCELLED')
                ->sum('amount_due'));

            $stockValue = floatval(StockBalance::where('stock_balances.company_id', $companyId)
                ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
                ->join('products', 'products.id', '=', 'stock_balances.product_id')
                ->where('warehouses.branch_id', $bId)
                ->sum(StockBalance::raw('stock_balances.quantity * products.purchase_price')));

            $totalSales += $sales;
            $totalPurchases += $purchases;
            $totalNetProfit += $netProfit;

            $branchNames[] = $branch->name;
            $salesSeries[] = $sales;
            $purchaseSeries[] = $purchases;
            $profitSeries[] = $netProfit;

            $rows[] = [
                'branch_id' => $bId,
                'branch_name' => $branch->name,
                'branch_code' => $branch->code,
                'is_main' => (bool)$branch->is_main_branch,
                'sales' => $sales,
                'purchases' => $purchases,
                'net_profit' => $netProfit,
                'receivables' => $receivables,
                'stock_valuation' => $stockValue,
            ];
        }

        $chart = ReportChartService::buildBarChart(
            'Branch Performance Comparison (₹)',
            $branchNames,
            [
                ['name' => 'Sales Revenue', 'data' => $salesSeries, 'color' => '#2563eb'],
                ['name' => 'Procurement', 'data' => $purchaseSeries, 'color' => '#f59e0b'],
                ['name' => 'Net Profit', 'data' => $profitSeries, 'color' => '#10b981'],
            ]
        );

        return [
            'period' => ['start_date' => $fromDate, 'end_date' => $toDate],
            'summary_kpis' => [
                'total_branches' => count($branches),
                'consolidated_sales' => $totalSales,
                'consolidated_purchases' => $totalPurchases,
                'consolidated_profit' => $totalNetProfit,
            ],
            'chart' => $chart,
            'branches' => $rows,
        ];
    }

    /**
     * Get Customer Global Balance + Branch-wise breakdown.
     */
    public static function getCustomerBranchBalances(int $companyId, int $customerId): array
    {
        $customer = Customer::where('company_id', $companyId)->findOrFail($customerId);
        $branches = Branch::where('company_id', $companyId)->get();

        $branchBreakdown = [];
        $totalOutstanding = 0.0;

        foreach ($branches as $b) {
            $invoices = Invoice::where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->where('branch_id', $b->id)
                ->where('status', '!=', 'CANCELLED')
                ->get();

            $branchTotalDue = 0.0;
            foreach ($invoices as $inv) {
                $paid = floatval(PaymentAllocation::where('company_id', $companyId)->where('document_type', 'INVOICE')->where('document_id', $inv->id)->sum('allocated_amount')) ?: floatval($inv->amount_paid ?? 0);
                $due = isset($inv->amount_due) && $inv->amount_due !== null ? floatval($inv->amount_due) : max(0, round(floatval($inv->grand_total) - $paid, 2));
                $branchTotalDue += $due;
            }

            if ($branchTotalDue > 0 || $invoices->count() > 0) {
                $branchBreakdown[] = [
                    'branch_id' => $b->id,
                    'branch_name' => $b->name,
                    'branch_code' => $b->code,
                    'invoice_count' => $invoices->count(),
                    'total_due' => $branchTotalDue,
                ];
                $totalOutstanding += $branchTotalDue;
            }
        }

        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'global_outstanding_balance' => $totalOutstanding,
            'branches' => $branchBreakdown,
        ];
    }

    /**
     * Get Supplier Global Balance + Branch-wise breakdown.
     */
    public static function getSupplierBranchBalances(int $companyId, int $supplierId): array
    {
        $supplier = Supplier::where('company_id', $companyId)->findOrFail($supplierId);
        $branches = Branch::where('company_id', $companyId)->get();

        $branchBreakdown = [];
        $totalPayable = 0.0;

        foreach ($branches as $b) {
            $purchases = Purchase::where('company_id', $companyId)
                ->where('supplier_id', $supplierId)
                ->where('branch_id', $b->id)
                ->where('status', '!=', 'CANCELLED')
                ->get();

            $branchTotalDue = 0.0;
            foreach ($purchases as $p) {
                $paid = floatval(PaymentAllocation::where('company_id', $companyId)->where('document_type', 'PURCHASE')->where('document_id', $p->id)->sum('allocated_amount')) ?: floatval($p->amount_paid ?? 0);
                $due = isset($p->amount_due) && $p->amount_due !== null ? floatval($p->amount_due) : max(0, round(floatval($p->grand_total) - $paid, 2));
                $branchTotalDue += $due;
            }

            if ($branchTotalDue > 0 || $purchases->count() > 0) {
                $branchBreakdown[] = [
                    'branch_id' => $b->id,
                    'branch_name' => $b->name,
                    'branch_code' => $b->code,
                    'bill_count' => $purchases->count(),
                    'total_payable' => $branchTotalDue,
                ];
                $totalPayable += $branchTotalDue;
            }
        }

        return [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'global_payable_balance' => $totalPayable,
            'branches' => $branchBreakdown,
        ];
    }
}
