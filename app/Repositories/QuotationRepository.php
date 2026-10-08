<?php

namespace App\Repositories;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Customer;
use Illuminate\Database\Capsule\Manager as DB;

class QuotationRepository
{
    /**
     * Get paginated quotations with filtering and search.
     */
    public static function getQuotations(int $companyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
        }
        if (isset($filters['per_page'])) {
            $perPage = max(1, min(100, (int)$filters['per_page']));
        }

        $query = Quotation::withoutGlobalScopes()
            ->where('company_id', $companyId);

        // Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('quotation_number', 'like', "%{$s}%")
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

        // Date Range
        if (!empty($filters['from_date'])) {
            $query->where('quotation_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('quotation_date', '<=', $filters['to_date']);
        }

        $total = $query->count();
        $items = $query->with(['customer', 'items'])
            ->orderBy('quotation_date', 'desc')
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
     * Find quotation by ID scoped to tenant.
     */
    public static function findQuotation(int $id, int $companyId): ?Quotation
    {
        return Quotation::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Get Complete Quotation with Line Items and Relations for Viewing/Printing.
     */
    public static function getQuotationWithItems(int $id, int $companyId): ?array
    {
        $quotation = self::findQuotation($id, $companyId);
        if (!$quotation) {
            return null;
        }

        $items = DB::table('quotation_items')
            ->leftJoin('products', 'quotation_items.product_id', '=', 'products.id')
            ->where('quotation_items.quotation_id', $id)
            ->where('quotation_items.company_id', $companyId)
            ->select(
                'quotation_items.*',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.hsn_sac as product_hsn'
            )
            ->get();

        $customer = DB::table('customers')
            ->where('id', $quotation->customer_id)
            ->where('company_id', $companyId)
            ->first();

        $company = DB::table('companies')
            ->where('id', $companyId)
            ->first();

        $branch = DB::table('branches')
            ->where('id', $quotation->branch_id)
            ->where('company_id', $companyId)
            ->first();

        return [
            'quotation' => $quotation,
            'items' => $items,
            'customer' => $customer,
            'company' => $company,
            'branch' => $branch,
        ];
    }

    /**
     * Generate Next Sequential Quotation Number with Lock Protection.
     */
    public static function generateNextQuotationNumber(int $companyId, string $prefix = 'QTN'): string
    {
        $latest = DB::table('quotations')
            ->where('company_id', $companyId)
            ->where('quotation_number', 'like', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('quotation_number');

        $nextSeq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $nextSeq = intval($matches[1]) + 1;
        } else {
            $count = DB::table('quotations')->where('company_id', $companyId)->count();
            $nextSeq = $count + 1;
        }

        return sprintf("%s-%04d", $prefix, $nextSeq);
    }

    /**
     * Summary KPIs for quotations.
     */
    public static function getQuotationStats(int $companyId): array
    {
        $query = DB::table('quotations')
            ->where('company_id', $companyId);

        $totalCount = $query->count();
        $totalAmount = (float)$query->sum('grand_total');
        $convertedCount = DB::table('quotations')
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('status', 'CONVERTED')
                  ->orWhere('converted_to_invoice', 1);
            })
            ->count();
        $openCount = DB::table('quotations')
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['CONVERTED', 'REJECTED', 'EXPIRED'])
            ->where('converted_to_invoice', 0)
            ->count();

        return [
            'total_count' => $totalCount,
            'total_amount' => round($totalAmount, 2),
            'converted_count' => $convertedCount,
            'open_count' => $openCount,
        ];
    }
}
