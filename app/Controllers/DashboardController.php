<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Config;
use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\AuthService;

/**
 * Enterprise Multi-Role Dashboard Controller.
 * Strictly segments dashboard widgets and queries by role permissions.
 * Never executes queries for widgets forbidden to the active user.
 */
class DashboardController
{
    /**
     * Role-specific dashboard dispatcher.
     */
    public function index(Request $req): Response
    {
        $authService = new AuthService();
        if (!$authService->check()) {
            return $this->renderPublicLanding();
        }

        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 1);
        $userRole = Gate::role();

        // 1. ACCOUNTANT ROLE: Payments & Financial Flow Dashboard
        if (Gate::can('dashboard.payments.view') && !Gate::can('dashboard.view')) {
            return $this->renderAccountantDashboard($companyId, $branchId);
        }

        // 2. INVENTORY MANAGER ROLE: Warehouse & Stock Dashboard
        if (Gate::can('dashboard.inventory.view') && !Gate::can('dashboard.view')) {
            return $this->renderInventoryDashboard($companyId, $branchId);
        }

        // 3. ADMINISTRATOR & FULL USERS: Core Executive Dashboard
        Gate::authorize('dashboard.view', $req);
        return $this->renderAdminDashboard($companyId, $branchId);
    }

    /**
     * Accountant: Exclusively queries financial flow, receivables, payables, and recent payments.
     */
    protected function renderAccountantDashboard(int $companyId, int $branchId): Response
    {
        // Receivables
        $recRow = Database::selectOne(
            "SELECT COALESCE(SUM(amount_due), 0) AS receivables 
             FROM `invoices` 
             WHERE company_id = ? AND deleted_at IS NULL AND status != 'CANCELLED'",
            [$companyId]
        );

        // Payables
        $payRow = Database::selectOne(
            "SELECT COALESCE(SUM(amount_due), 0) AS payables 
             FROM `purchases` 
             WHERE company_id = ?",
            [$companyId]
        );

        // Collections & Disbursements from payments table
        $paymentTotals = Database::selectOne(
            "SELECT 
                COALESCE(SUM(CASE WHEN payment_type = 'RECEIPT' THEN amount ELSE 0 END), 0) AS total_received,
                COALESCE(SUM(CASE WHEN payment_type = 'PAYMENT' THEN amount ELSE 0 END), 0) AS total_paid
             FROM `payments` 
             WHERE company_id = ? AND deleted_at IS NULL",
            [$companyId]
        );

        // Recent Payments / Receipts (5 latest)
        $recentPayments = Database::select(
            "SELECT id, payment_number, receipt_number, payment_date, amount, payment_mode, payment_type, status, notes
             FROM `payments` 
             WHERE company_id = ? AND deleted_at IS NULL 
             ORDER BY payment_date DESC, id DESC LIMIT 5",
            [$companyId]
        );

        // Overdue Invoices
        $overdueInvoices = Database::select(
            "SELECT i.id, i.invoice_number, i.invoice_date, i.due_date, i.grand_total, i.amount_due, i.status,
                    COALESCE(c.name, i.customer_name, 'Direct Client') AS customer_name
             FROM `invoices` i
             LEFT JOIN `customers` c ON i.customer_id = c.id
             WHERE i.company_id = ? AND i.deleted_at IS NULL AND i.status != 'CANCELLED' 
                   AND i.amount_due > 0 AND i.due_date < CURDATE()
             ORDER BY i.due_date ASC LIMIT 5",
            [$companyId]
        );

        $html = View::renderWithLayout('dashboard/payments', [
            'title' => 'Payments Dashboard',
            'currentUri' => '/',
            'metrics' => [
                'receivables' => (float)($recRow['receivables'] ?? 0),
                'payables' => (float)($payRow['payables'] ?? 0),
                'total_received' => (float)($paymentTotals['total_received'] ?? 0),
                'total_paid' => (float)($paymentTotals['total_paid'] ?? 0),
            ],
            'recentPayments' => $recentPayments,
            'overdueInvoices' => $overdueInvoices,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Inventory Manager: Exclusively queries warehouse stock levels, low stock alerts, and physical adjustments.
     * ZERO financial revenue, P&L, balance sheet, or administrator widgets.
     */
    protected function renderInventoryDashboard(int $companyId, int $branchId): Response
    {
        // Stock KPI totals
        $stockKpi = Database::selectOne(
            "SELECT 
                COUNT(*) AS total_items,
                COALESCE(SUM(current_stock), 0) AS total_units,
                COALESCE(SUM(current_stock * purchase_price), 0) AS total_valuation,
                SUM(CASE WHEN current_stock <= COALESCE(reorder_level, min_stock_alert, 5) AND current_stock > 0 THEN 1 ELSE 0 END) AS low_stock_count,
                SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock_count
             FROM `products` 
             WHERE company_id = ? AND deleted_at IS NULL",
            [$companyId]
        );

        // Adjustments this month
        $adjMonth = Database::selectOne(
            "SELECT COUNT(*) AS count 
             FROM `stock_adjustments` 
             WHERE company_id = ? AND adjustment_date >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            [$companyId]
        );

        // Critical Attention Items (Out of stock or Low stock)
        $criticalItems = Database::select(
            "SELECT id, name, sku, current_stock, COALESCE(reorder_level, min_stock_alert, 5) AS reorder_level 
             FROM `products` 
             WHERE company_id = ? AND deleted_at IS NULL 
                   AND (current_stock <= COALESCE(reorder_level, min_stock_alert, 5) OR current_stock <= 0)
             ORDER BY current_stock ASC LIMIT 6",
            [$companyId]
        );

        // Recent Adjustments
        $recentAdjustments = Database::select(
            "SELECT sa.id, sa.adjustment_number, sa.adjustment_date, sa.reason,
                    COALESCE(p.name, 'Stock Adjustment') AS product_name,
                    COALESCE(sai.adjustment_type, 'ADJUSTMENT') AS type,
                    COALESCE(sai.quantity, 1) AS quantity,
                    COALESCE(u.name, 'Inventory Manager') AS operator
             FROM `stock_adjustments` sa
             LEFT JOIN `stock_adjustment_items` sai ON sa.id = sai.adjustment_id
             LEFT JOIN `products` p ON sai.product_id = p.id
             LEFT JOIN `users` u ON sa.created_by = u.id
             WHERE sa.company_id = ?
             ORDER BY sa.id DESC LIMIT 5",
            [$companyId]
        );

        $html = View::renderWithLayout('dashboard/inventory', [
            'title' => 'Inventory Dashboard',
            'currentUri' => '/',
            'metrics' => [
                'total_units' => (float)($stockKpi['total_units'] ?? 0),
                'total_valuation' => (float)($stockKpi['total_valuation'] ?? 0),
                'low_stock_count' => (int)($stockKpi['low_stock_count'] ?? 0),
                'out_of_stock_count' => (int)($stockKpi['out_of_stock_count'] ?? 0),
                'adjustments_this_month' => (int)($adjMonth['count'] ?? 0),
            ],
            'criticalItems' => $criticalItems,
            'recentAdjustments' => $recentAdjustments,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Administrator: Complete executive dashboard with sales, taxes, receivables, and payables.
     */
    protected function renderAdminDashboard(int $companyId, int $branchId): Response
    {
        $salesMetrics = Database::selectOne(
            "SELECT COUNT(*) AS total_invoices, 
                    COALESCE(SUM(grand_total), 0) AS total_sales,
                    COALESCE(SUM(total_tax), 0) AS total_tax,
                    COALESCE(SUM(amount_due), 0) AS receivables
             FROM `invoices` 
             WHERE company_id = ? AND branch_id = ? AND deleted_at IS NULL AND status != 'CANCELLED'",
            [$companyId, $branchId]
        );

        $purchaseMetrics = Database::selectOne(
            "SELECT COALESCE(SUM(amount_due), 0) AS payables
             FROM `purchases` 
             WHERE company_id = ? AND branch_id = ?",
            [$companyId, $branchId]
        );

        $recentInvoices = Database::select(
            "SELECT i.id, i.invoice_number, COALESCE(c.name, i.customer_name, 'Direct Customer') AS customer_name, i.invoice_date, i.grand_total, i.status 
             FROM `invoices` i
             LEFT JOIN `customers` c ON i.customer_id = c.id
             WHERE i.company_id = ? AND (i.branch_id = ? OR i.branch_id IS NULL) AND i.deleted_at IS NULL AND i.status != 'CANCELLED'
             ORDER BY i.id DESC LIMIT 5",
            [$companyId, $branchId]
        );

        $html = View::renderWithLayout('dashboard/index', [
            'title' => 'Dashboard',
            'currentUri' => '/',
            'metrics' => [
                'total_sales' => (float)($salesMetrics['total_sales'] ?? 0),
                'total_tax' => (float)($salesMetrics['total_tax'] ?? 0),
                'receivables' => (float)($salesMetrics['receivables'] ?? 0),
                'payables' => (float)($purchaseMetrics['payables'] ?? 0),
            ],
            'recentInvoices' => $recentInvoices,
        ], 'layouts/app');

        return Response::html($html);
    }

    protected function renderPublicLanding(): Response
    {
        return Response::html(
            '<!DOCTYPE html><html><head><title>' . e(Config::get('app.name', 'WTSBill ERP')) . '</title>' .
            '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}' .
            '.card{background:#1e293b;padding:2.5rem;border-radius:1rem;box-shadow:0 10px 25px rgba(0,0,0,0.5);text-align:center;max-width:480px;border:1px solid #334155;}' .
            'h1{color:#38bdf8;margin-bottom:0.5rem;font-size:1.75rem;}p{color:#94a3b8;line-height:1.6;font-size:0.95rem;}' .
            '.badge{display:inline-block;padding:0.25rem 0.75rem;background:#0369a1;color:#e0f2fe;border-radius:9999px;font-size:0.75rem;font-weight:600;margin-bottom:1rem;}' .
            'a{color:#38bdf8;text-decoration:none;font-weight:500;margin:0 0.5rem;}</style></head><body>' .
            '<div class="card"><span class="badge">NATIVE PHP 8+ ENGINE</span>' .
            '<h1>' . e(Config::get('app.name', 'WTSBill ERP')) . '</h1>' .
            '<p>High-performance Enterprise Billing, GST Accounting & Multi-Tenant Inventory Rebuild.</p>' .
            '<p style="margin-top:1.25rem;"><a href="/login">Login Portal &rarr;</a> | <a href="/api/health">Check /api/health &rarr;</a></p>' .
            '</div></body></html>'
        );
    }
}
