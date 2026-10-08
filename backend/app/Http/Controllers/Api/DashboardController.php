<?php

namespace App\Http\Controllers\Api;

use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\BankAccount;
use App\Http\Middleware\AuthMiddleware;

class DashboardController
{
    public function getMetrics()
    {
        $user = AuthMiddleware::authorize('dashboard', 'view');

        // 1. Total Sales / Revenue
        $totalSales = Invoice::where('status', '!=', 'CANCELLED')->sum('grand_total');

        // 2. Total Receivables
        $totalReceivable = Invoice::where('status', '!=', 'PAID')->sum('amount_due');

        // 3. Total Purchases & Payables
        $totalPurchases = Purchase::sum('grand_total');
        $totalPayable = Purchase::sum('amount_due');

        // 4. Total Expenses
        $totalExpenses = Expense::sum('amount');

        // 5. Net GST Liability
        $totalSalesTax = Invoice::sum('total_tax');
        $totalPurchaseTax = Purchase::sum('total_tax');
        $totalExpenseTax = Expense::where('is_itc_eligible', true)->sum('tax_amount');
        $gstLiability = max(0, $totalSalesTax - ($totalPurchaseTax + $totalExpenseTax));

        // 6. Cash & Bank Balance
        $bankBalance = BankAccount::sum('current_balance');

        // 7. Low Stock Count
        $lowStockProducts = Product::whereRaw('current_stock <= min_stock_alert')->get();

        // 8. Recent Transactions
        $recentInvoices = Invoice::with('customer')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return response_json([
            'status' => 'success',
            'summary' => [
                'total_sales' => $totalSales,
                'total_receivable' => $totalReceivable,
                'total_purchases' => $totalPurchases,
                'total_payable' => $totalPayable,
                'total_expenses' => $totalExpenses,
                'gst_liability' => $gstLiability,
                'bank_balance' => $bankBalance,
                'low_stock_count' => count($lowStockProducts),
            ],
            'low_stock_items' => $lowStockProducts,
            'recent_invoices' => $recentInvoices,
        ]);
    }
}
