<?php

namespace App\Repositories;

use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\Customer;
use Illuminate\Database\Capsule\Manager as DB;

class DeliveryChallanRepository
{
    /**
     * Get paginated delivery challans with filtering and search.
     */
    public static function getDeliveryChallans(int $companyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
        }
        if (isset($filters['per_page'])) {
            $perPage = max(1, min(100, (int)$filters['per_page']));
        }

        $query = DeliveryChallan::withoutGlobalScopes()
            ->where('company_id', $companyId);

        // Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('challan_number', 'like', "%{$s}%")
                  ->orWhere('reference_so', 'like', "%{$s}%")
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

        // Date Range
        if (!empty($filters['from_date'])) {
            $query->where('challan_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('challan_date', '<=', $filters['to_date']);
        }

        $total = $query->count();
        $items = $query->with(['customer', 'items'])
            ->orderBy('challan_date', 'desc')
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
     * Find delivery challan by ID scoped to tenant.
     */
    public static function findDeliveryChallan(int $id, int $companyId): ?DeliveryChallan
    {
        return DeliveryChallan::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Get Complete Delivery Challan with Line Items and Relations for Viewing/Printing.
     */
    public static function getDeliveryChallanWithItems(int $id, int $companyId): ?array
    {
        $challan = self::findDeliveryChallan($id, $companyId);
        if (!$challan) {
            return null;
        }

        $items = DB::table('delivery_challan_items')
            ->leftJoin('products', 'delivery_challan_items.product_id', '=', 'products.id')
            ->where('delivery_challan_items.delivery_challan_id', $id)
            ->where('delivery_challan_items.company_id', $companyId)
            ->select(
                'delivery_challan_items.*',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.hsn_sac as product_hsn'
            )
            ->get();

        $customer = DB::table('customers')
            ->where('id', $challan->customer_id)
            ->where('company_id', $companyId)
            ->first();

        $company = DB::table('companies')
            ->where('id', $companyId)
            ->first();

        $branch = DB::table('branches')
            ->where('id', $challan->branch_id)
            ->where('company_id', $companyId)
            ->first();

        return [
            'challan' => $challan,
            'items' => $items,
            'customer' => $customer,
            'company' => $company,
            'branch' => $branch,
        ];
    }

    /**
     * Generate Next Sequential Delivery Challan Number with Lock Protection.
     */
    public static function generateNextChallanNumber(int $companyId, string $prefix = 'DC'): string
    {
        $latest = DB::table('delivery_challans')
            ->where('company_id', $companyId)
            ->where('challan_number', 'like', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('challan_number');

        $nextSeq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $nextSeq = intval($matches[1]) + 1;
        } else {
            $count = DB::table('delivery_challans')->where('company_id', $companyId)->count();
            $nextSeq = $count + 1;
        }

        return sprintf("%s-%04d", $prefix, $nextSeq);
    }

    /**
     * Summary KPIs for delivery challans.
     */
    public static function getDeliveryChallanStats(int $companyId): array
    {
        $query = DB::table('delivery_challans')
            ->where('company_id', $companyId);

        $totalCount = $query->count();
        $deliveredCount = DB::table('delivery_challans')
            ->where('company_id', $companyId)
            ->whereIn('status', ['Delivered', 'DELIVERED', 'INVOICED'])
            ->count();
        $pendingCount = DB::table('delivery_challans')
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['Delivered', 'DELIVERED', 'INVOICED', 'Cancelled', 'CANCELLED'])
            ->count();

        return [
            'total_count' => $totalCount,
            'delivered_count' => $deliveredCount,
            'pending_count' => $pendingCount,
        ];
    }
}
