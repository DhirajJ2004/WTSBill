<?php

namespace App\Repositories;

use App\Services\AccountingService;
use App\Services\TrialBalanceService;
use App\Services\ProfitLossService;
use App\Services\BalanceSheetService;
use App\Services\ReceivableService;
use App\Services\PayableService;
use Illuminate\Database\Capsule\Manager as DB;

class ReportRepository
{
    /**
     * 1. Sales Report: Aggregated sales with pagination, date & customer filters.
     */
    public static function getSalesReport(int $companyId, array $filters = []): array
    {
        $query = DB::table('invoices as i')
            ->leftJoin('customers as c', 'i.customer_id', '=', 'c.id')
            ->where('i.company_id', $companyId)
            ->where('i.status', '!=', 'CANCELLED');

        if (!empty($filters['branch_id'])) {
            $query->where('i.branch_id', intval($filters['branch_id']));
        }
        if (!empty($filters['financial_year'])) {
            $query->where('i.financial_year', trim($filters['financial_year']));
        }
        if (!empty($filters['customer_id'])) {
            $query->where('i.customer_id', intval($filters['customer_id']));
        }
        if (!empty($filters['payment_status'])) {
            $query->where('i.payment_status', strtoupper(trim($filters['payment_status'])));
        }
        if (!empty($filters['from_date'])) {
            $query->where('i.invoice_date', '>=', substr(trim($filters['from_date']), 0, 10));
        }
        if (!empty($filters['to_date'])) {
            $query->where('i.invoice_date', '<=', substr(trim($filters['to_date']), 0, 10));
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('i.invoice_number', 'LIKE', $term)
                  ->orWhere('c.name', 'LIKE', $term)
                  ->orWhere('c.gstin', 'LIKE', $term);
            });
        }

        // Summary totals via SQL aggregate
        $summary = (clone $query)->selectRaw('
            COUNT(i.id) as total_invoices,
            COALESCE(SUM(i.sub_total), 0) as total_taxable,
            COALESCE(SUM(i.cgst_amount), 0) as total_cgst,
            COALESCE(SUM(i.sgst_amount), 0) as total_sgst,
            COALESCE(SUM(i.igst_amount), 0) as total_igst,
            COALESCE(SUM(i.total_tax), 0) as total_tax,
            COALESCE(SUM(i.grand_total), 0) as total_grand,
            COALESCE(SUM(i.amount_paid), 0) as total_paid,
            COALESCE(SUM(i.amount_due), 0) as total_due
        ')->first();

        // Paginated rows
        $page = max(1, intval($filters['page'] ?? 1));
        $perPage = max(1, min(500, intval($filters['per_page'] ?? 25)));
        $totalRecords = (clone $query)->count();

        $rows = $query->select(
            'i.id',
            'i.invoice_number',
            'i.invoice_date',
            'i.due_date',
            'i.customer_id',
            'c.name as customer_name',
            'c.gstin as customer_gstin',
            'i.sub_total',
            'i.cgst_amount',
            'i.sgst_amount',
            'i.igst_amount',
            'i.total_tax',
            'i.grand_total',
            'i.amount_paid',
            'i.amount_due',
            'i.payment_status',
            'i.status'
        )
        ->orderBy('i.invoice_date', 'desc')
        ->orderBy('i.id', 'desc')
        ->forPage($page, $perPage)
        ->get();

        return [
            'summary' => [
                'total_invoices' => intval($summary->total_invoices ?? 0),
                'total_taxable' => round(floatval($summary->total_taxable ?? 0), 2),
                'total_cgst' => round(floatval($summary->total_cgst ?? 0), 2),
                'total_sgst' => round(floatval($summary->total_sgst ?? 0), 2),
                'total_igst' => round(floatval($summary->total_igst ?? 0), 2),
                'total_tax' => round(floatval($summary->total_tax ?? 0), 2),
                'total_grand' => round(floatval($summary->total_grand ?? 0), 2),
                'total_paid' => round(floatval($summary->total_paid ?? 0), 2),
                'total_due' => round(floatval($summary->total_due ?? 0), 2),
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $perPage),
            ],
            'data' => $rows,
        ];
    }

    /**
     * 2. Purchase Report: Aggregated purchases with pagination & vendor breakdown.
     */
    public static function getPurchaseReport(int $companyId, array $filters = []): array
    {
        $query = DB::table('purchases as p')
            ->leftJoin('suppliers as s', 'p.supplier_id', '=', 's.id')
            ->where('p.company_id', $companyId)
            ->where('p.status', '!=', 'CANCELLED');

        if (!empty($filters['branch_id'])) {
            $query->where('p.branch_id', intval($filters['branch_id']));
        }
        if (!empty($filters['financial_year'])) {
            $query->where('p.financial_year', trim($filters['financial_year']));
        }
        if (!empty($filters['supplier_id'])) {
            $query->where('p.supplier_id', intval($filters['supplier_id']));
        }
        if (!empty($filters['payment_status'])) {
            $query->where('p.payment_status', strtoupper(trim($filters['payment_status'])));
        }
        if (!empty($filters['from_date'])) {
            $query->where('p.purchase_date', '>=', substr(trim($filters['from_date']), 0, 10));
        }
        if (!empty($filters['to_date'])) {
            $query->where('p.purchase_date', '<=', substr(trim($filters['to_date']), 0, 10));
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('p.purchase_number', 'LIKE', $term)
                  ->orWhere('p.vendor_invoice_number', 'LIKE', $term)
                  ->orWhere('s.name', 'LIKE', $term)
                  ->orWhere('s.gstin', 'LIKE', $term);
            });
        }

        $summary = (clone $query)->selectRaw('
            COUNT(p.id) as total_bills,
            COALESCE(SUM(p.sub_total), 0) as total_taxable,
            COALESCE(SUM(p.cgst_amount), 0) as total_cgst,
            COALESCE(SUM(p.sgst_amount), 0) as total_sgst,
            COALESCE(SUM(p.igst_amount), 0) as total_igst,
            COALESCE(SUM(p.total_tax), 0) as total_tax,
            COALESCE(SUM(p.grand_total), 0) as total_grand,
            COALESCE(SUM(p.amount_paid), 0) as total_paid,
            COALESCE(SUM(p.amount_due), 0) as total_due
        ')->first();

        $page = max(1, intval($filters['page'] ?? 1));
        $perPage = max(1, min(500, intval($filters['per_page'] ?? 25)));
        $totalRecords = (clone $query)->count();

        $rows = $query->select(
            'p.id',
            'p.purchase_number',
            'p.vendor_invoice_number as supplier_invoice_no',
            'p.purchase_date',
            'p.due_date',
            'p.supplier_id',
            's.name as supplier_name',
            's.gstin as supplier_gstin',
            'p.sub_total',
            'p.cgst_amount',
            'p.sgst_amount',
            'p.igst_amount',
            'p.total_tax',
            'p.grand_total',
            'p.amount_paid',
            'p.amount_due',
            'p.payment_status',
            'p.status'
        )
        ->orderBy('p.purchase_date', 'desc')
        ->orderBy('p.id', 'desc')
        ->forPage($page, $perPage)
        ->get();

        return [
            'summary' => [
                'total_bills' => intval($summary->total_bills ?? 0),
                'total_taxable' => round(floatval($summary->total_taxable ?? 0), 2),
                'total_cgst' => round(floatval($summary->total_cgst ?? 0), 2),
                'total_sgst' => round(floatval($summary->total_sgst ?? 0), 2),
                'total_igst' => round(floatval($summary->total_igst ?? 0), 2),
                'total_tax' => round(floatval($summary->total_tax ?? 0), 2),
                'total_grand' => round(floatval($summary->total_grand ?? 0), 2),
                'total_paid' => round(floatval($summary->total_paid ?? 0), 2),
                'total_due' => round(floatval($summary->total_due ?? 0), 2),
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $perPage),
            ],
            'data' => $rows,
        ];
    }

    /**
     * 3. Inventory Report: Stock on hand, valuation, and low stock metrics.
     */
    public static function getInventoryReport(int $companyId, array $filters = []): array
    {
        $query = DB::table('products as p')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('p.company_id', $companyId)
            ->whereNull('p.deleted_at');

        if (!empty($filters['category_id'])) {
            $query->where('p.category_id', intval($filters['category_id']));
        }
        if (!empty($filters['low_stock'])) {
            $query->whereRaw('p.current_stock <= COALESCE(p.min_stock_level, p.min_stock_alert, 0)');
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('p.name', 'LIKE', $term)
                  ->orWhere('p.sku', 'LIKE', $term)
                  ->orWhere('p.hsn_sac', 'LIKE', $term)
                  ->orWhere('c.name', 'LIKE', $term);
            });
        }

        $summary = (clone $query)->selectRaw('
            COUNT(p.id) as total_products,
            COALESCE(SUM(p.current_stock), 0) as total_units,
            COALESCE(SUM(p.current_stock * p.purchase_price), 0) as total_cost_valuation,
            COALESCE(SUM(p.current_stock * COALESCE(p.selling_price, p.sales_price, 0)), 0) as total_retail_valuation,
            COALESCE(SUM(CASE WHEN p.current_stock <= COALESCE(p.min_stock_level, p.min_stock_alert, 0) THEN 1 ELSE 0 END), 0) as low_stock_count
        ')->first();

        $page = max(1, intval($filters['page'] ?? 1));
        $perPage = max(1, min(500, intval($filters['per_page'] ?? 25)));
        $totalRecords = (clone $query)->count();

        $rows = $query->select(
            'p.id',
            'p.name',
            'p.sku',
            'p.hsn_sac as hsn_code',
            'p.current_stock',
            DB::raw('COALESCE(p.min_stock_level, p.min_stock_alert, 0) as min_stock_level'),
            'p.purchase_price',
            DB::raw('COALESCE(p.selling_price, p.sales_price, 0) as selling_price'),
            DB::raw('(p.current_stock * p.purchase_price) as cost_value'),
            DB::raw('(p.current_stock * COALESCE(p.selling_price, p.sales_price, 0)) as retail_value'),
            'c.name as category_name',
            'p.unit as unit_code'
        )
        ->orderBy('p.name', 'asc')
        ->forPage($page, $perPage)
        ->get();

        return [
            'summary' => [
                'total_products' => intval($summary->total_products ?? 0),
                'total_units' => round(floatval($summary->total_units ?? 0), 2),
                'total_cost_valuation' => round(floatval($summary->total_cost_valuation ?? 0), 2),
                'total_retail_valuation' => round(floatval($summary->total_retail_valuation ?? 0), 2),
                'low_stock_count' => intval($summary->low_stock_count ?? 0),
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $perPage),
            ],
            'data' => $rows,
        ];
    }

    /**
     * 4. Stock Ledger Report: Chronological movements with running balance.
     */
    public static function getStockLedgerReport(int $companyId, array $filters = []): array
    {
        $query = DB::table('stock_movements as sm')
            ->leftJoin('products as p', 'sm.product_id', '=', 'p.id')
            ->leftJoin('warehouses as w', 'sm.warehouse_id', '=', 'w.id')
            ->where('sm.company_id', $companyId);

        if (!empty($filters['product_id'])) {
            $query->where('sm.product_id', intval($filters['product_id']));
        }
        if (!empty($filters['warehouse_id'])) {
            $query->where('sm.warehouse_id', intval($filters['warehouse_id']));
        }
        if (!empty($filters['movement_type'])) {
            $query->where('sm.movement_type', strtoupper(trim($filters['movement_type'])));
        }
        if (!empty($filters['from_date'])) {
            $query->where('sm.created_at', '>=', substr(trim($filters['from_date']), 0, 10) . ' 00:00:00');
        }
        if (!empty($filters['to_date'])) {
            $query->where('sm.created_at', '<=', substr(trim($filters['to_date']), 0, 10) . ' 23:59:59');
        }

        $page = max(1, intval($filters['page'] ?? 1));
        $perPage = max(1, min(500, intval($filters['per_page'] ?? 25)));
        $totalRecords = (clone $query)->count();

        $rows = $query->select(
            'sm.id',
            'sm.created_at as movement_date',
            'sm.movement_type',
            'sm.direction',
            'sm.quantity',
            'sm.balance_after',
            'sm.unit_cost',
            'sm.reference_type',
            'sm.reference_id',
            'sm.notes',
            'p.name as product_name',
            'p.sku as product_sku',
            'w.name as warehouse_name'
        )
        ->orderBy('sm.id', 'desc')
        ->forPage($page, $perPage)
        ->get();

        return [
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $perPage),
            ],
            'data' => $rows,
        ];
    }

    /**
     * 5. Party Outstanding Report: Receivables and Payables aging.
     */
    public static function getPartyOutstandingReport(int $companyId, array $filters = []): array
    {
        $type = strtoupper($filters['party_type'] ?? 'CUSTOMER');
        $branchId = !empty($filters['branch_id']) ? intval($filters['branch_id']) : null;

        if ($type === 'SUPPLIER') {
            $data = PayableService::getSupplierPayables($companyId, $branchId);
            $aging = PayableService::getPayablesAging($companyId, $branchId);
            $totalDue = array_sum(array_column($data, 'total_due'));
            return [
                'party_type' => 'SUPPLIER',
                'total_outstanding' => round($totalDue, 2),
                'aging_summary' => $aging['summary'] ?? [],
                'parties' => $data,
            ];
        }

        $data = ReceivableService::getCustomerReceivables($companyId, $branchId);
        $aging = ReceivableService::getReceivablesAging($companyId, $branchId);
        $totalDue = array_sum(array_column($data, 'total_due'));

        return [
            'party_type' => 'CUSTOMER',
            'total_outstanding' => round($totalDue, 2),
            'aging_summary' => $aging['summary'] ?? [],
            'parties' => $data,
        ];
    }

    /**
     * 6. Payment Report: Inflow receipts and outflow disbursements.
     */
    public static function getPaymentReport(int $companyId, array $filters = []): array
    {
        $query = DB::table('payments as p')
            ->where('p.company_id', $companyId)
            ->where('p.status', '!=', 'CANCELLED');

        if (!empty($filters['payment_type'])) {
            $query->where('p.payment_type', strtoupper(trim($filters['payment_type'])));
        }
        if (!empty($filters['payment_mode'])) {
            $query->where('p.payment_mode', strtoupper(trim($filters['payment_mode'])));
        }
        if (!empty($filters['from_date'])) {
            $query->where('p.payment_date', '>=', substr(trim($filters['from_date']), 0, 10));
        }
        if (!empty($filters['to_date'])) {
            $query->where('p.payment_date', '<=', substr(trim($filters['to_date']), 0, 10));
        }

        $summary = (clone $query)->selectRaw('
            COUNT(p.id) as total_count,
            COALESCE(SUM(CASE WHEN p.payment_type = "RECEIPT" THEN p.amount ELSE 0 END), 0) as total_receipts,
            COALESCE(SUM(CASE WHEN p.payment_type = "PAYMENT" THEN p.amount ELSE 0 END), 0) as total_payments,
            COALESCE(SUM(p.amount), 0) as total_volume
        ')->first();

        $page = max(1, intval($filters['page'] ?? 1));
        $perPage = max(1, min(500, intval($filters['per_page'] ?? 25)));
        $totalRecords = (clone $query)->count();

        $rows = $query->select(
            'p.id',
            'p.payment_number',
            'p.receipt_number',
            'p.payment_type',
            'p.party_type',
            'p.party_id',
            'p.amount',
            'p.payment_mode',
            'p.payment_date',
            'p.transaction_reference',
            'p.utr',
            'p.cheque_number',
            'p.cheque_status',
            'p.status'
        )
        ->orderBy('p.payment_date', 'desc')
        ->orderBy('p.id', 'desc')
        ->forPage($page, $perPage)
        ->get();

        return [
            'summary' => [
                'total_count' => intval($summary->total_count ?? 0),
                'total_receipts' => round(floatval($summary->total_receipts ?? 0), 2),
                'total_payments' => round(floatval($summary->total_payments ?? 0), 2),
                'total_volume' => round(floatval($summary->total_volume ?? 0), 2),
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $perPage),
            ],
            'data' => $rows,
        ];
    }

    /**
     * 7. Expense Report: Categorized operational expenses.
     */
    public static function getExpenseReport(int $companyId, array $filters = []): array
    {
        $query = DB::table('expenses as e')->where('e.company_id', $companyId);

        if (!empty($filters['category'])) {
            $query->where('e.category', trim($filters['category']));
        }
        if (!empty($filters['payment_mode'])) {
            $query->where('e.payment_mode', strtoupper(trim($filters['payment_mode'])));
        }
        if (!empty($filters['from_date'])) {
            $query->where('e.expense_date', '>=', substr(trim($filters['from_date']), 0, 10));
        }
        if (!empty($filters['to_date'])) {
            $query->where('e.expense_date', '<=', substr(trim($filters['to_date']), 0, 10));
        }

        $summary = (clone $query)->selectRaw('
            COUNT(e.id) as total_expenses,
            COALESCE(SUM(e.amount), 0) as total_amount,
            COALESCE(SUM(e.tax_amount), 0) as total_tax,
            COALESCE(SUM(CASE WHEN e.is_itc_eligible = 1 THEN e.tax_amount ELSE 0 END), 0) as total_itc_eligible
        ')->first();

        $page = max(1, intval($filters['page'] ?? 1));
        $perPage = max(1, min(500, intval($filters['per_page'] ?? 25)));
        $totalRecords = (clone $query)->count();

        $rows = $query->select(
            'e.id',
            'e.expense_number',
            'e.expense_date',
            'e.category',
            'e.payee',
            'e.amount',
            'e.tax_amount',
            'e.payment_mode',
            'e.is_itc_eligible',
            'e.description'
        )
        ->orderBy('e.expense_date', 'desc')
        ->orderBy('e.id', 'desc')
        ->forPage($page, $perPage)
        ->get();

        return [
            'summary' => [
                'total_expenses' => intval($summary->total_expenses ?? 0),
                'total_amount' => round(floatval($summary->total_amount ?? 0), 2),
                'total_tax' => round(floatval($summary->total_tax ?? 0), 2),
                'total_itc_eligible' => round(floatval($summary->total_itc_eligible ?? 0), 2),
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $perPage),
            ],
            'data' => $rows,
        ];
    }

    /**
     * 8. GST / GSTR Tax Report: GSTR-1 & GSTR-3B components.
     */
    public static function getGSTReport(int $companyId, array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? date('Y-01-01');
        $toDate = $filters['to_date'] ?? date('Y-m-d');

        // Outward Supplies (GSTR-1 Liability)
        $outward = DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->selectRaw('
                COUNT(id) as invoice_count,
                COALESCE(SUM(sub_total), 0) as taxable_value,
                COALESCE(SUM(cgst_amount), 0) as cgst_liability,
                COALESCE(SUM(sgst_amount), 0) as sgst_liability,
                COALESCE(SUM(igst_amount), 0) as igst_liability,
                COALESCE(SUM(total_tax), 0) as total_tax_liability
            ')
            ->first();

        // Inward Supplies (GSTR-3B Input Tax Credit)
        $inward = DB::table('purchases')
            ->where('company_id', $companyId)
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('purchase_date', [$fromDate, $toDate])
            ->selectRaw('
                COUNT(id) as bill_count,
                COALESCE(SUM(sub_total), 0) as taxable_value,
                COALESCE(SUM(cgst_amount), 0) as input_cgst,
                COALESCE(SUM(sgst_amount), 0) as input_sgst,
                COALESCE(SUM(igst_amount), 0) as input_igst,
                COALESCE(SUM(total_tax), 0) as total_itc
            ')
            ->first();

        $netCgstPayable = max(0, floatval($outward->cgst_liability ?? 0) - floatval($inward->input_cgst ?? 0));
        $netSgstPayable = max(0, floatval($outward->sgst_liability ?? 0) - floatval($inward->input_sgst ?? 0));
        $netIgstPayable = max(0, floatval($outward->igst_liability ?? 0) - floatval($inward->input_igst ?? 0));

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'outward_supplies_gstr1' => [
                'invoice_count' => intval($outward->invoice_count ?? 0),
                'taxable_value' => round(floatval($outward->taxable_value ?? 0), 2),
                'cgst' => round(floatval($outward->cgst_liability ?? 0), 2),
                'sgst' => round(floatval($outward->sgst_liability ?? 0), 2),
                'igst' => round(floatval($outward->igst_liability ?? 0), 2),
                'total_tax' => round(floatval($outward->total_tax_liability ?? 0), 2),
            ],
            'inward_supplies_itc' => [
                'bill_count' => intval($inward->bill_count ?? 0),
                'taxable_value' => round(floatval($inward->taxable_value ?? 0), 2),
                'cgst' => round(floatval($inward->input_cgst ?? 0), 2),
                'sgst' => round(floatval($inward->input_sgst ?? 0), 2),
                'igst' => round(floatval($inward->input_igst ?? 0), 2),
                'total_tax' => round(floatval($inward->total_itc ?? 0), 2),
            ],
            'net_tax_payable' => [
                'cgst' => round($netCgstPayable, 2),
                'sgst' => round($netSgstPayable, 2),
                'igst' => round($netIgstPayable, 2),
                'total' => round($netCgstPayable + $netSgstPayable + $netIgstPayable, 2),
            ],
        ];
    }

    /**
     * 9. Audit Report: Comprehensive system audit log stream.
     */
    public static function getAuditReport(int $companyId, array $filters = []): array
    {
        $query = DB::table('audit_logs')->where('company_id', $companyId);

        if (!empty($filters['action'])) {
            $query->where('action', strtoupper(trim($filters['action'])));
        }
        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', trim($filters['entity_type']));
        }
        if (!empty($filters['user_name'])) {
            $query->where('user_name', trim($filters['user_name']));
        }
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', substr(trim($filters['from_date']), 0, 10) . ' 00:00:00');
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', substr(trim($filters['to_date']), 0, 10) . ' 23:59:59');
        }

        $page = max(1, intval($filters['page'] ?? 1));
        $perPage = max(1, min(500, intval($filters['per_page'] ?? 50)));
        $totalRecords = (clone $query)->count();

        $rows = $query->orderBy('id', 'desc')
            ->forPage($page, $perPage)
            ->get();

        return [
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $perPage),
            ],
            'data' => $rows,
        ];
    }
}
