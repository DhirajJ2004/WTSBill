<?php

namespace App\Repositories;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Database\Capsule\Manager as DB;

class PurchaseRepository
{
    /**
     * Get paginated purchases with filtering and search.
     */
    public static function getPurchases(int $companyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
        }
        if (isset($filters['per_page'])) {
            $perPage = max(1, min(100, (int)$filters['per_page']));
        }

        $query = Purchase::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        // Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('purchase_number', 'like', "%{$s}%")
                  ->orWhere('vendor_invoice_number', 'like', "%{$s}%")
                  ->orWhere('supplier_name', 'like', "%{$s}%")
                  ->orWhere('reference_no', 'like', "%{$s}%");
            });
        }

        // Supplier Filter
        if (!empty($filters['supplier_id']) && $filters['supplier_id'] !== 'all') {
            $query->where('supplier_id', (int)$filters['supplier_id']);
        }

        // Status Filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Payment Status Filter
        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $query->where('payment_status', $filters['payment_status']);
        }

        // Date Range
        if (!empty($filters['from_date'])) {
            $query->where('purchase_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('purchase_date', '<=', $filters['to_date']);
        }

        $total = $query->count();
        $items = $query->with(['supplier', 'items.product'])
            ->orderBy('purchase_date', 'desc')
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
     * Find purchase by ID scoped to tenant.
     */
    public static function findPurchase(int $id, int $companyId): ?Purchase
    {
        return Purchase::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Get Complete Purchase with Line Items and Relations for Viewing/Printing.
     */
    public static function getPurchaseWithItems(int $id, int $companyId): ?array
    {
        $purchase = self::findPurchase($id, $companyId);
        if (!$purchase) {
            return null;
        }

        $items = DB::table('purchase_items')
            ->leftJoin('products', 'purchase_items.product_id', '=', 'products.id')
            ->where('purchase_items.purchase_id', $id)
            ->where('purchase_items.company_id', $companyId)
            ->select(
                'purchase_items.*',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.hsn_sac as product_hsn'
            )
            ->get();

        $supplier = DB::table('suppliers')
            ->where('id', $purchase->supplier_id)
            ->where('company_id', $companyId)
            ->first();

        $company = DB::table('companies')
            ->where('id', $companyId)
            ->first();

        $branch = DB::table('branches')
            ->where('id', $purchase->branch_id)
            ->where('company_id', $companyId)
            ->first();

        return [
            'purchase' => $purchase,
            'items' => $items,
            'supplier' => $supplier,
            'company' => $company,
            'branch' => $branch,
        ];
    }

    /**
     * Generate Next Sequential Purchase Number with Exclusive Lock Protection.
     */
    public static function generateNextPurchaseNumber(int $companyId, string $prefix = 'PUR'): string
    {
        $latest = DB::table('purchases')
            ->where('company_id', $companyId)
            ->where('purchase_number', 'like', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('purchase_number');

        $nextSeq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $nextSeq = intval($matches[1]) + 1;
        } else {
            $count = DB::table('purchases')->where('company_id', $companyId)->count();
            $nextSeq = $count + 1;
        }

        return sprintf("%s-%04d", $prefix, $nextSeq);
    }

    /**
     * Summary KPIs for purchases.
     */
    public static function getPurchaseStats(int $companyId): array
    {
        $purchases = DB::table('purchases')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'CANCELLED');

        $totalPurchases = (float)$purchases->sum('grand_total');
        $totalPaid = (float)$purchases->sum('amount_paid');
        $totalDue = (float)$purchases->sum('amount_due');
        $totalCount = $purchases->count();

        $unpaidCount = DB::table('purchases')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', 'UNPAID')
            ->count();

        $overdueCount = DB::table('purchases')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', '!=', 'PAID')
            ->where('due_date', '<', date('Y-m-d'))
            ->count();

        return [
            'total_purchases' => round($totalPurchases, 2),
            'total_paid' => round($totalPaid, 2),
            'total_due' => round($totalDue, 2),
            'total_count' => $totalCount,
            'unpaid_count' => $unpaidCount,
            'overdue_count' => $overdueCount,
        ];
    }
}
