<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Capsule\Manager as DB;

class PaymentRepository
{
    /**
     * Get paginated payments / receipts with filtering and search.
     */
    public static function getPayments(int $companyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
        }
        if (isset($filters['per_page'])) {
            $perPage = max(1, min(100, (int)$filters['per_page']));
        }

        $query = Payment::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        // Payment Type (RECEIPT vs PAYMENT)
        if (!empty($filters['payment_type']) && $filters['payment_type'] !== 'all') {
            $query->where('payment_type', strtoupper($filters['payment_type']));
        }

        // Party Type (CUSTOMER vs SUPPLIER)
        if (!empty($filters['party_type']) && $filters['party_type'] !== 'all') {
            $query->where('party_type', strtoupper($filters['party_type']));
        }

        // Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('payment_number', 'like', "%{$s}%")
                  ->orWhere('receipt_number', 'like', "%{$s}%")
                  ->orWhere('reference_number', 'like', "%{$s}%")
                  ->orWhere('transaction_reference', 'like', "%{$s}%")
                  ->orWhere('utr', 'like', "%{$s}%")
                  ->orWhere('cheque_number', 'like', "%{$s}%");
            });
        }

        // Date Range
        if (!empty($filters['from_date'])) {
            $query->where('payment_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('payment_date', '<=', $filters['to_date']);
        }

        $total = $query->count();
        $items = $query->with(['customer', 'supplier'])
            ->orderBy('payment_date', 'desc')
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
     * Find payment by ID scoped to tenant.
     */
    public static function findPayment(int $id, int $companyId): ?Payment
    {
        return Payment::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Get Complete Payment / Receipt with Party Relations for Viewing/Printing.
     */
    public static function getPaymentWithDetails(int $id, int $companyId): ?array
    {
        $payment = self::findPayment($id, $companyId);
        if (!$payment) {
            return null;
        }

        $party = null;
        if ($payment->party_type === 'CUSTOMER') {
            $party = DB::table('customers')
                ->where('id', $payment->party_id)
                ->where('company_id', $companyId)
                ->first();
        } else {
            $party = DB::table('suppliers')
                ->where('id', $payment->party_id)
                ->where('company_id', $companyId)
                ->first();
        }

        $company = DB::table('companies')
            ->where('id', $companyId)
            ->first();

        $branch = DB::table('branches')
            ->where('id', $payment->branch_id)
            ->where('company_id', $companyId)
            ->first();

        return [
            'payment' => $payment,
            'party' => $party,
            'company' => $company,
            'branch' => $branch,
        ];
    }

    /**
     * Generate Next Sequential Payment / Receipt Number with Lock.
     */
    public static function generateNextPaymentNumber(int $companyId, string $type = 'RECEIPT'): string
    {
        $prefix = ($type === 'RECEIPT' || $type === 'CUSTOMER_PAYMENT') ? 'REC' : 'PAY';

        $latest = DB::table('payments')
            ->where('company_id', $companyId)
            ->where('payment_number', 'like', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('payment_number');

        $nextSeq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $nextSeq = intval($matches[1]) + 1;
        } else {
            $count = DB::table('payments')
                ->where('company_id', $companyId)
                ->where('payment_number', 'like', "{$prefix}-%")
                ->count();
            $nextSeq = $count + 1;
        }

        return sprintf("%s-%04d", $prefix, $nextSeq);
    }

    /**
     * Summary KPIs for payments and receipts.
     */
    public static function getPaymentStats(int $companyId): array
    {
        $totalReceipts = (float)DB::table('payments')
            ->where('company_id', $companyId)
            ->where('payment_type', 'RECEIPT')
            ->whereNull('deleted_at')
            ->where('status', 'POSTED')
            ->sum('amount');

        $totalPayments = (float)DB::table('payments')
            ->where('company_id', $companyId)
            ->where('payment_type', 'PAYMENT')
            ->whereNull('deleted_at')
            ->where('status', 'POSTED')
            ->sum('amount');

        $receiptCount = DB::table('payments')
            ->where('company_id', $companyId)
            ->where('payment_type', 'RECEIPT')
            ->whereNull('deleted_at')
            ->count();

        $paymentCount = DB::table('payments')
            ->where('company_id', $companyId)
            ->where('payment_type', 'PAYMENT')
            ->whereNull('deleted_at')
            ->count();

        return [
            'total_receipts' => round($totalReceipts, 2),
            'total_payments' => round($totalPayments, 2),
            'receipt_count' => $receiptCount,
            'payment_count' => $paymentCount,
        ];
    }
}
