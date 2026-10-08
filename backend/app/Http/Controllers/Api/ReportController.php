<?php

namespace App\Http\Controllers\Api;

use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Product;
use App\Models\BankAccount;
use App\Http\Middleware\AuthMiddleware;

class ReportController
{
    public function gstr1()
    {
        $user = AuthMiddleware::authorize('gst', 'view');
        $companyId = AuthMiddleware::getTenantId();
        if (!empty($_GET['company_id']) && (int)$_GET['company_id'] !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: Cannot access report for another company.'], 403);
        }

        $invoices = Invoice::where('company_id', $companyId)->with('customer')->get();

        $b2bInvoices = [];
        $b2cInvoices = [];
        $totalTaxable = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        foreach ($invoices as $inv) {
            $totalTaxable += $inv->sub_total;
            $totalCgst += $inv->cgst_amount;
            $totalSgst += $inv->sgst_amount;
            $totalIgst += $inv->igst_amount;

            if (!empty($inv->customer->gstin)) {
                $b2bInvoices[] = $inv;
            } else {
                $b2cInvoices[] = $inv;
            }
        }

        return response_json([
            'status' => 'success',
            'summary' => [
                'total_invoices' => count($invoices),
                'b2b_count' => count($b2bInvoices),
                'b2c_count' => count($b2cInvoices),
                'total_taxable_value' => $totalTaxable,
                'cgst_total' => $totalCgst,
                'sgst_total' => $totalSgst,
                'igst_total' => $totalIgst,
                'total_gst' => $totalCgst + $totalSgst + $totalIgst,
            ],
            'b2b_invoices' => $b2bInvoices,
            'b2c_invoices' => $b2cInvoices,
        ]);
    }

    public function profitAndLoss()
    {
        $user = AuthMiddleware::authorize('reports', 'view_financial_data');
        $companyId = AuthMiddleware::getTenantId();
        if (!empty($_GET['company_id']) && (int)$_GET['company_id'] !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: Cannot access report for another company.'], 403);
        }

        $salesRevenue = Invoice::where('company_id', $companyId)->sum('sub_total');
        $costOfGoodsSold = Purchase::where('company_id', $companyId)->sum('sub_total');
        $grossProfit = $salesRevenue - $costOfGoodsSold;

        $operatingExpenses = Expense::where('company_id', $companyId)->sum('amount');
        $netProfit = $grossProfit - $operatingExpenses;

        $expensesByCategory = Expense::where('company_id', $companyId)
            ->selectRaw('category, SUM(amount) as total_amount')
            ->groupBy('category')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => [
                'sales_revenue' => $salesRevenue,
                'cost_of_goods_sold' => $costOfGoodsSold,
                'gross_profit' => $grossProfit,
                'operating_expenses' => $operatingExpenses,
                'net_profit' => $netProfit,
                'expenses_breakdown' => $expensesByCategory,
            ],
        ]);
    }

    public function bankLedger()
    {
        $user = AuthMiddleware::authorize('banking', 'view');
        $companyId = AuthMiddleware::getTenantId();
        if (!empty($_GET['company_id']) && (int)$_GET['company_id'] !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: Cannot access report for another company.'], 403);
        }

        $bankAccounts = BankAccount::where('company_id', $companyId)->get();

        return response_json([
            'status' => 'success',
            'data' => $bankAccounts,
        ]);
    }
}
