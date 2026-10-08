<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Company;
use App\Models\Branch;
use Illuminate\Database\Capsule\Manager as DB;

class InvoiceRepository
{
    /**
     * Get paginated list of invoices for the tenant.
     */
    public static function getInvoices(int $companyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
        }
        if (isset($filters['per_page'])) {
            $perPage = max(1, min(100, (int)$filters['per_page']));
        }

        $query = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        // Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%")
                  ->orWhere('reference_po_number', 'like', "%{$s}%");
            });
        }

        // Customer Filter
        if (!empty($filters['customer_id']) && $filters['customer_id'] !== 'all') {
            $query->where('customer_id', (int)$filters['customer_id']);
        }

        // Status Filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Payment Status Filter
        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $query->where('payment_status', $filters['payment_status']);
        }

        // Date Range Filter
        if (!empty($filters['from_date'])) {
            $query->where('invoice_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('invoice_date', '<=', $filters['to_date']);
        }

        $total = $query->count();
        $items = $query->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $items,
            'total' => $total,
            'page' => $page,
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * Find invoice by ID scoped to tenant.
     */
    public static function findInvoice(int $id, int $companyId): ?Invoice
    {
        return Invoice::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Get Complete Invoice with Line Items and Relations for Viewing/Printing.
     */
    public static function getInvoiceWithItems(int $id, int $companyId): ?array
    {
        $invoice = self::findInvoice($id, $companyId);
        if (!$invoice) {
            return null;
        }

        $items = DB::table('invoice_items')
            ->leftJoin('products', 'invoice_items.product_id', '=', 'products.id')
            ->where('invoice_items.invoice_id', $id)
            ->where('invoice_items.company_id', $companyId)
            ->select(
                'invoice_items.*',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.hsn_sac as product_hsn'
            )
            ->get();

        $customer = DB::table('customers')
            ->where('id', $invoice->customer_id)
            ->where('company_id', $companyId)
            ->first();

        $company = DB::table('companies')
            ->where('id', $companyId)
            ->first();

        $branch = DB::table('branches')
            ->where('id', $invoice->branch_id)
            ->where('company_id', $companyId)
            ->first();

        return [
            'invoice' => $invoice,
            'items' => $items,
            'customer' => $customer,
            'company' => $company,
            'branch' => $branch,
        ];
    }

    /**
     * Generate Next Sequential Invoice Number with Lock Protection.
     */
    public static function generateNextInvoiceNumber(int $companyId, string $prefix = 'INV', string $financialYear = '2026-27'): string
    {
        // Atomically query highest sequence or count with lock
        $latest = DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('invoice_number', 'like', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('invoice_number');

        $nextSeq = 1;
        if ($latest) {
            if (preg_match('/(\d+)$/', $latest, $matches)) {
                $nextSeq = intval($matches[1]) + 1;
            } else {
                $count = DB::table('invoices')->where('company_id', $companyId)->count();
                $nextSeq = $count + 1;
            }
        }

        return sprintf("%s-%04d", $prefix, $nextSeq);
    }

    /**
     * Summary KPIs for sales invoices.
     */
    public static function getInvoiceStats(int $companyId): array
    {
        $invoices = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'CANCELLED');

        $totalSales = (float)$invoices->sum('grand_total');
        $totalPaid = (float)$invoices->sum('amount_paid');
        $totalDue = (float)$invoices->sum('amount_due');
        $totalCount = $invoices->count();

        $unpaidCount = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', 'UNPAID')
            ->count();

        $overdueCount = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', '!=', 'PAID')
            ->where('due_date', '<', date('Y-m-d'))
            ->count();

        return [
            'total_sales' => round($totalSales, 2),
            'total_paid' => round($totalPaid, 2),
            'total_due' => round($totalDue, 2),
            'total_count' => $totalCount,
            'unpaid_count' => $unpaidCount,
            'overdue_count' => $overdueCount,
        ];
    }
}
