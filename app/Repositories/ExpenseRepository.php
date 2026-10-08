<?php

namespace App\Repositories;

use App\Models\Expense;
use Illuminate\Database\Capsule\Manager as DB;

class ExpenseRepository
{
    /**
     * Get paginated operating expenses with filtering and search.
     */
    public static function getExpenses(int $companyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
        }
        if (isset($filters['per_page'])) {
            $perPage = max(1, min(100, (int)$filters['per_page']));
        }

        $query = Expense::withoutGlobalScopes()
            ->where('company_id', $companyId);

        // Category Filter
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        // Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('expense_number', 'like', "%{$s}%")
                  ->orWhere('payee', 'like', "%{$s}%")
                  ->orWhere('category', 'like', "%{$s}%")
                  ->orWhere('reference_no', 'like', "%{$s}%");
            });
        }

        // Date Range
        if (!empty($filters['from_date'])) {
            $query->where('expense_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('expense_date', '<=', $filters['to_date']);
        }

        $total = $query->count();
        $items = $query->orderBy('expense_date', 'desc')
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
     * Find expense by ID scoped to tenant.
     */
    public static function findExpense(int $id, int $companyId): ?Expense
    {
        return Expense::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Generate Next Sequential Expense Number.
     */
    public static function generateNextExpenseNumber(int $companyId, string $prefix = 'EXP'): string
    {
        $latest = DB::table('expenses')
            ->where('company_id', $companyId)
            ->where('expense_number', 'like', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('expense_number');

        $nextSeq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $nextSeq = intval($matches[1]) + 1;
        } else {
            $count = DB::table('expenses')->where('company_id', $companyId)->count();
            $nextSeq = $count + 1;
        }

        return sprintf("%s-%04d", $prefix, $nextSeq);
    }

    /**
     * Summary KPIs for expenses.
     */
    public static function getExpenseStats(int $companyId): array
    {
        $query = DB::table('expenses')
            ->where('company_id', $companyId);

        $totalAmount = (float)$query->sum('amount');
        $totalTax = (float)$query->sum('tax_amount');
        $totalCount = $query->count();

        // Top categories
        $categoryBreakdown = DB::table('expenses')
            ->where('company_id', $companyId)
            ->select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();

        return [
            'total_amount' => round($totalAmount, 2),
            'total_tax' => round($totalTax, 2),
            'total_count' => $totalCount,
            'categories' => $categoryBreakdown,
        ];
    }
}
