<?php

namespace App\Repositories;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Customer;
use Illuminate\Database\Capsule\Manager as DB;

class SalesOrderRepository
{
    /**
     * Get paginated sales orders with filtering and search.
     */
    public static function getSalesOrders(int $companyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
        }
        if (isset($filters['per_page'])) {
            $perPage = max(1, min(100, (int)$filters['per_page']));
        }

        $query = SalesOrder::withoutGlobalScopes()
            ->where('company_id', $companyId);

        // Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%{$s}%")
                  ->orWhere('reference_no', 'like', "%{$s}%")
                  ->orWhereHas('customer', function ($cq) use ($s) {
                      $cq->where('name', 'like', "%{$s}%");
                  });
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

        // Fulfillment Status Filter
        if (!empty($filters['fulfillment_status']) && $filters['fulfillment_status'] !== 'all') {
            $query->where('fulfillment_status', $filters['fulfillment_status']);
        }

        // Date Range
        if (!empty($filters['from_date'])) {
            $query->where('order_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('order_date', '<=', $filters['to_date']);
        }

        $total = $query->count();
        $items = $query->with(['customer', 'items'])
            ->orderBy('order_date', 'desc')
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
     * Find sales order by ID scoped to tenant.
     */
    public static function findSalesOrder(int $id, int $companyId): ?SalesOrder
    {
        return SalesOrder::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Get Complete Sales Order with Line Items and Relations for Viewing/Printing.
     */
    public static function getSalesOrderWithItems(int $id, int $companyId): ?array
    {
        $salesOrder = self::findSalesOrder($id, $companyId);
        if (!$salesOrder) {
            return null;
        }

        $items = DB::table('sales_order_items')
            ->leftJoin('products', 'sales_order_items.product_id', '=', 'products.id')
            ->where('sales_order_items.sales_order_id', $id)
            ->where('sales_order_items.company_id', $companyId)
            ->select(
                'sales_order_items.*',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.hsn_sac as product_hsn'
            )
            ->get();

        $customer = DB::table('customers')
            ->where('id', $salesOrder->customer_id)
            ->where('company_id', $companyId)
            ->first();

        $company = DB::table('companies')
            ->where('id', $companyId)
            ->first();

        $branch = DB::table('branches')
            ->where('id', $salesOrder->branch_id)
            ->where('company_id', $companyId)
            ->first();

        return [
            'sales_order' => $salesOrder,
            'items' => $items,
            'customer' => $customer,
            'company' => $company,
            'branch' => $branch,
        ];
    }

    /**
     * Generate Next Sequential Sales Order Number with Lock Protection.
     */
    public static function generateNextSalesOrderNumber(int $companyId, string $prefix = 'SO'): string
    {
        $latest = DB::table('sales_orders')
            ->where('company_id', $companyId)
            ->where('order_number', 'like', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('order_number');

        $nextSeq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $nextSeq = intval($matches[1]) + 1;
        } else {
            $count = DB::table('sales_orders')->where('company_id', $companyId)->count();
            $nextSeq = $count + 1;
        }

        return sprintf("%s-%04d", $prefix, $nextSeq);
    }

    /**
     * Summary KPIs for sales orders.
     */
    public static function getSalesOrderStats(int $companyId): array
    {
        $query = DB::table('sales_orders')
            ->where('company_id', $companyId);

        $totalCount = $query->count();
        $totalAmount = (float)$query->sum('grand_total');
        $fulfilledCount = DB::table('sales_orders')
            ->where('company_id', $companyId)
            ->whereIn('fulfillment_status', ['Fulfilled', 'FULFILLED'])
            ->count();
        $pendingCount = DB::table('sales_orders')
            ->where('company_id', $companyId)
            ->whereNotIn('fulfillment_status', ['Fulfilled', 'FULFILLED', 'Cancelled', 'CANCELLED'])
            ->count();

        return [
            'total_count' => $totalCount,
            'total_amount' => round($totalAmount, 2),
            'fulfilled_count' => $fulfilledCount,
            'pending_count' => $pendingCount,
        ];
    }
}
