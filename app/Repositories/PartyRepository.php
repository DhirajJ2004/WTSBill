<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\CreditNote;
use App\Models\DebitNote;
use Illuminate\Database\Capsule\Manager as DB;

class PartyRepository
{
    /**
     * Get paginated customer list for tenant.
     */
    public static function getCustomers(int $companyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
        }
        if (isset($filters['per_page'])) {
            $perPage = max(1, min(100, (int)$filters['per_page']));
        }

        $query = Customer::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        // Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('company_name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('gstin', 'like', "%{$s}%")
                  ->orWhere('city', 'like', "%{$s}%");
            });
        }

        // Status Filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Tax Type Filter
        if (!empty($filters['tax_type']) && $filters['tax_type'] !== 'all') {
            $query->where('tax_type', $filters['tax_type']);
        }

        // Customer Type Filter
        if (!empty($filters['customer_type']) && $filters['customer_type'] !== 'all') {
            $query->where('customer_type', $filters['customer_type']);
        }

        $total = $query->count();
        $items = $query->orderBy('name', 'asc')
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
     * Get paginated supplier list for tenant.
     */
    public static function getSuppliers(int $companyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
        }
        if (isset($filters['per_page'])) {
            $perPage = max(1, min(100, (int)$filters['per_page']));
        }

        $query = Supplier::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        // Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('company_name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('gstin', 'like', "%{$s}%")
                  ->orWhere('city', 'like', "%{$s}%");
            });
        }

        // Status Filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Tax Type Filter
        if (!empty($filters['tax_type']) && $filters['tax_type'] !== 'all') {
            $query->where('tax_type', $filters['tax_type']);
        }

        $total = $query->count();
        $items = $query->orderBy('name', 'asc')
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
     * Find customer by ID scoped to tenant.
     */
    public static function findCustomer(int $id, int $companyId): ?Customer
    {
        return Customer::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Find supplier by ID scoped to tenant.
     */
    public static function findSupplier(int $id, int $companyId): ?Supplier
    {
        return Supplier::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Get Customer Transaction History (Invoices, Receipts, Credit Notes).
     */
    public static function getCustomerTransactions(int $customerId, int $companyId): array
    {
        $transactions = [];

        // Invoices
        $invoices = DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->select('id', 'invoice_number as doc_number', 'invoice_date as doc_date', 'grand_total as debit', DB::raw('0 as credit'), 'status')
            ->get();

        foreach ($invoices as $inv) {
            $transactions[] = [
                'type' => 'INVOICE',
                'doc_number' => $inv->doc_number,
                'date' => $inv->doc_date,
                'debit' => (float)$inv->debit,
                'credit' => 0.0,
                'status' => $inv->status,
                'description' => "Tax Invoice #{$inv->doc_number}",
            ];
        }

        // Payments / Receipts
        // Payments / Receipts
        $payments = DB::table('payments')
            ->where('company_id', $companyId)
            ->where('party_id', $customerId)
            ->where('party_type', 'CUSTOMER')
            ->where('payment_type', 'RECEIPT')
            ->whereNull('deleted_at')
            ->select('id', 'payment_number as doc_number', 'payment_date as doc_date', 'amount as credit', 'status', 'payment_mode')
            ->get();

        foreach ($payments as $pay) {
            $transactions[] = [
                'type' => 'RECEIPT',
                'doc_number' => $pay->doc_number,
                'date' => $pay->doc_date,
                'debit' => 0.0,
                'credit' => (float)$pay->credit,
                'status' => $pay->status,
                'description' => "Payment Receipt ({$pay->payment_mode})",
            ];
        }

        // Credit Notes
        $creditNotes = DB::table('credit_notes')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->select('id', 'credit_note_number as doc_number', 'credit_note_date as doc_date', 'amount as credit', 'status')
            ->get();

        foreach ($creditNotes as $cn) {
            $transactions[] = [
                'type' => 'CREDIT_NOTE',
                'doc_number' => $cn->doc_number,
                'date' => $cn->doc_date,
                'debit' => 0.0,
                'credit' => (float)$cn->credit,
                'status' => $cn->status,
                'description' => "Credit Note #{$cn->doc_number}",
            ];
        }

        // Sort by date descending
        usort($transactions, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $customer = self::findCustomer($customerId, $companyId);
        if ($customer && (float)$customer->opening_balance != 0) {
            array_unshift($transactions, [
                'type' => 'OPENING_BALANCE',
                'doc_number' => 'OP-BAL',
                'date' => $customer->created_at ? substr((string)$customer->created_at, 0, 10) : date('Y-m-d'),
                'debit' => (float)$customer->opening_balance,
                'credit' => 0.0,
                'status' => 'POSTED',
                'description' => 'Opening Balance',
            ]);
        }

        return $transactions;
    }

    /**
     * Get Supplier Transaction History (Purchases, Payments, Debit Notes).
     */
    public static function getSupplierTransactions(int $supplierId, int $companyId): array
    {
        $transactions = [];

        // Purchases
        $purchases = DB::table('purchases')
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->whereNull('deleted_at')
            ->select('id', 'purchase_number as doc_number', 'purchase_date as doc_date', 'grand_total as credit', DB::raw('0 as debit'), 'status')
            ->get();

        foreach ($purchases as $pur) {
            $transactions[] = [
                'type' => 'PURCHASE',
                'doc_number' => $pur->doc_number,
                'date' => $pur->doc_date,
                'debit' => 0.0,
                'credit' => (float)$pur->credit,
                'status' => $pur->status,
                'description' => "Purchase Bill #{$pur->doc_number}",
            ];
        }

        // Payments
        $payments = DB::table('payments')
            ->where('company_id', $companyId)
            ->where('party_id', $supplierId)
            ->where('party_type', 'SUPPLIER')
            ->where('payment_type', 'PAYMENT')
            ->whereNull('deleted_at')
            ->select('id', 'payment_number as doc_number', 'payment_date as doc_date', 'amount as debit', 'status', 'payment_mode')
            ->get();

        foreach ($payments as $pay) {
            $transactions[] = [
                'type' => 'PAYMENT',
                'doc_number' => $pay->doc_number,
                'date' => $pay->doc_date,
                'debit' => (float)$pay->debit,
                'credit' => 0.0,
                'status' => $pay->status,
                'description' => "Supplier Payment ({$pay->payment_mode})",
            ];
        }

        // Debit Notes
        $debitNotes = DB::table('debit_notes')
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->select('id', 'debit_note_number as doc_number', 'debit_note_date as doc_date', 'amount as debit', 'status')
            ->get();

        foreach ($debitNotes as $dn) {
            $transactions[] = [
                'type' => 'DEBIT_NOTE',
                'doc_number' => $dn->doc_number,
                'date' => $dn->doc_date,
                'debit' => (float)$dn->debit,
                'credit' => 0.0,
                'status' => $dn->status,
                'description' => "Debit Note #{$dn->doc_number}",
            ];
        }

        usort($transactions, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $supplier = self::findSupplier($supplierId, $companyId);
        if ($supplier && (float)$supplier->opening_balance != 0) {
            array_unshift($transactions, [
                'type' => 'OPENING_BALANCE',
                'doc_number' => 'OP-BAL',
                'date' => $supplier->created_at ? substr((string)$supplier->created_at, 0, 10) : date('Y-m-d'),
                'debit' => 0.0,
                'credit' => (float)$supplier->opening_balance,
                'status' => 'POSTED',
                'description' => 'Opening Balance',
            ]);
        }

        return $transactions;
    }

    /**
     * Compute current outstanding balances.
     */
    public static function computeCustomerBalance(int $customerId, int $companyId): float
    {
        $customer = self::findCustomer($customerId, $companyId);
        if (!$customer) return 0.0;

        $opening = (float)($customer->opening_balance ?? 0.0);
        $totalSales = (float)DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'CANCELLED')
            ->whereNull('deleted_at')
            ->sum('grand_total');

        $totalReceipts = (float)DB::table('payments')
            ->where('company_id', $companyId)
            ->where('party_id', $customerId)
            ->where('party_type', 'CUSTOMER')
            ->where('payment_type', 'RECEIPT')
            ->where('status', '!=', 'CANCELLED')
            ->whereNull('deleted_at')
            ->sum('amount');

        $totalCreditNotes = (float)DB::table('credit_notes')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->sum('amount');

        return ($opening + $totalSales) - ($totalReceipts + $totalCreditNotes);
    }
}
