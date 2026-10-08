<?php

namespace App\Reporting\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\CreditNote;
use App\Models\Branch;
use App\Models\Payment;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    /**
     * Sales Summary Report
     */
    public static function getSalesSummary(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $startDate = $dates['start_date'];
        $endDate = $dates['end_date'];

        $invQuery = Invoice::where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate);

        if (!empty($filters['branch_id'])) {
            $invQuery->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['customer_id'])) {
            $invQuery->where('customer_id', $filters['customer_id']);
        }
        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $invQuery->where('status', $filters['status']);
        } else {
            $invQuery->where('status', '!=', 'CANCELLED');
        }

        $invoices = $invQuery->get();

        $invoiceCount = $invoices->count();
        $grossSales = floatval($invoices->sum('sub_total'));
        $totalDiscount = floatval($invoices->sum('discount_amount'));
        $taxableSales = floatval($invoices->sum(fn($i) => $i->taxable_value ?: ($i->sub_total - ($i->discount_amount ?? 0))));
        $totalCgst = floatval($invoices->sum('cgst_amount'));
        $totalSgst = floatval($invoices->sum('sgst_amount'));
        $totalIgst = floatval($invoices->sum('igst_amount'));
        $totalTax = floatval($invoices->sum('total_tax') ?: ($totalCgst + $totalSgst + $totalIgst));
        $grandTotal = floatval($invoices->sum('grand_total'));
        $totalPaid = floatval($invoices->sum('amount_paid'));
        $totalDue = floatval($invoices->sum('amount_due'));

        // Credit notes (returns) in period
        $cnQuery = CreditNote::where('company_id', $companyId)
            ->whereDate('credit_note_date', '>=', $startDate)
            ->whereDate('credit_note_date', '<=', $endDate);
        if (!empty($filters['branch_id'])) {
            $cnQuery->where('branch_id', $filters['branch_id']);
        }
        $creditNotes = $cnQuery->get();
        $totalReturns = floatval($creditNotes->sum('grand_total'));
        $returnsCount = $creditNotes->count();

        $netSales = $grandTotal - $totalReturns;
        $avgInvoiceValue = $invoiceCount > 0 ? round($grandTotal / $invoiceCount, 2) : 0.0;

        // Daily trend data for chart
        $dailyMap = [];
        $period = Carbon::parse($startDate)->daysUntil(Carbon::parse($endDate));
        foreach ($period as $d) {
            $dailyMap[$d->format('Y-m-d')] = 0.0;
        }
        foreach ($invoices as $inv) {
            $dStr = is_object($inv->invoice_date) ? $inv->invoice_date->format('Y-m-d') : substr((string)$inv->invoice_date, 0, 10);
            if (isset($dailyMap[$dStr])) {
                $dailyMap[$dStr] += floatval($inv->grand_total);
            }
        }

        $chartLabels = array_map(fn($k) => Carbon::parse($k)->format('d M'), array_keys($dailyMap));
        $chartValues = array_values($dailyMap);

        $salesTrendChart = ReportChartService::buildLineChart(
            'Sales Trend (' . $dates['label'] . ')',
            $chartLabels,
            [['name' => 'Sales (₹)', 'data' => $chartValues, 'color' => '#2563eb']]
        );

        // Status breakdown
        $statusCounts = [];
        foreach ($invoices as $inv) {
            $st = $inv->status ?: 'DRAFT';
            $statusCounts[$st] = ($statusCounts[$st] ?? 0) + floatval($inv->grand_total);
        }
        $statusItems = [];
        foreach ($statusCounts as $st => $val) {
            $statusItems[] = ['name' => $st, 'value' => $val];
        }
        $statusChart = ReportChartService::buildDonutChart('Sales by Status', $statusItems);

        return [
            'period' => $dates,
            'summary_kpis' => [
                'gross_sales' => $grossSales,
                'discount' => $totalDiscount,
                'taxable_sales' => $taxableSales,
                'total_tax' => $totalTax,
                'cgst' => $totalCgst,
                'sgst' => $totalSgst,
                'igst' => $totalIgst,
                'total_sales' => $grandTotal,
                'returns' => $totalReturns,
                'net_sales' => $netSales,
                'total_paid' => $totalPaid,
                'total_due' => $totalDue,
                'invoice_count' => $invoiceCount,
                'returns_count' => $returnsCount,
                'average_invoice_value' => $avgInvoiceValue,
            ],
            'charts' => [
                'sales_trend' => $salesTrendChart,
                'sales_by_status' => $statusChart,
            ],
            'rows' => $invoices->map(fn($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'date' => is_object($inv->invoice_date) ? $inv->invoice_date->format('Y-m-d') : (string)$inv->invoice_date,
                'customer_name' => $inv->customer?->name ?: ($inv->customer_name ?: 'Walk-in Customer'),
                'taxable_value' => floatval($inv->taxable_value ?: $inv->subtotal),
                'total_tax' => floatval($inv->total_tax ?: $inv->tax_total),
                'grand_total' => floatval($inv->grand_total),
                'amount_paid' => floatval($inv->amount_paid),
                'amount_due' => floatval($inv->amount_due),
                'status' => $inv->status,
            ])->toArray(),
            'columns' => [
                ['key' => 'invoice_number', 'label' => 'Invoice #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'customer_name', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'taxable_value', 'label' => 'Taxable Value (₹)', 'type' => 'currency'],
                ['key' => 'total_tax', 'label' => 'GST (₹)', 'type' => 'currency'],
                ['key' => 'grand_total', 'label' => 'Grand Total (₹)', 'type' => 'currency'],
                ['key' => 'amount_paid', 'label' => 'Paid (₹)', 'type' => 'currency'],
                ['key' => 'amount_due', 'label' => 'Due (₹)', 'type' => 'currency'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];
    }

    /**
     * Sales Register (Auditor & Accountant Grade)
     */
    public static function getSalesRegister(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $startDate = $dates['start_date'];
        $endDate = $dates['end_date'];

        $q = Invoice::with(['customer', 'items'])
            ->where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate);

        if (!empty($filters['branch_id'])) {
            $q->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['customer_id'])) {
            $q->where('customer_id', $filters['customer_id']);
        }

        $invoices = $q->orderBy('invoice_date', 'asc')->get();

        $rows = [];
        $totTaxable = 0.0;
        $totCgst = 0.0;
        $totSgst = 0.0;
        $totIgst = 0.0;
        $totDisc = 0.0;
        $totGrand = 0.0;
        $totPaid = 0.0;
        $totBal = 0.0;

        foreach ($invoices as $inv) {
            $taxable = floatval($inv->taxable_value ?: $inv->subtotal);
            $cgst = floatval($inv->cgst_amount);
            $sgst = floatval($inv->sgst_amount);
            $igst = floatval($inv->igst_amount);
            $disc = floatval($inv->discount_amount);
            $grand = floatval($inv->grand_total);
            $paid = floatval($inv->amount_paid);
            $bal = floatval($inv->amount_due);

            $totTaxable += $taxable;
            $totCgst += $cgst;
            $totSgst += $sgst;
            $totIgst += $igst;
            $totDisc += $disc;
            $totGrand += $grand;
            $totPaid += $paid;
            $totBal += $bal;

            $rows[] = [
                'id' => $inv->id,
                'date' => is_object($inv->invoice_date) ? $inv->invoice_date->format('Y-m-d') : (string)$inv->invoice_date,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer?->name ?: ($inv->customer_name ?: 'Customer'),
                'gstin' => $inv->customer?->gstin ?: ($inv->customer_gstin ?: '-'),
                'taxable_amount' => $taxable,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'discount' => $disc,
                'total' => $grand,
                'paid' => $paid,
                'balance' => $bal,
                'status' => $inv->status,
            ];
        }

        return [
            'period' => $dates,
            'summary_kpis' => [
                'invoice_count' => count($rows),
                'total_taxable' => $totTaxable,
                'total_cgst' => $totCgst,
                'total_sgst' => $totSgst,
                'total_igst' => $totIgst,
                'total_discount' => $totDisc,
                'total_grand' => $totGrand,
                'total_paid' => $totPaid,
                'total_balance' => $totBal,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'invoice_number', 'label' => 'Invoice #', 'type' => 'text'],
                ['key' => 'customer_name', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'gstin', 'label' => 'GSTIN', 'type' => 'text'],
                ['key' => 'taxable_amount', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'cgst', 'label' => 'CGST (₹)', 'type' => 'currency'],
                ['key' => 'sgst', 'label' => 'SGST (₹)', 'type' => 'currency'],
                ['key' => 'igst', 'label' => 'IGST (₹)', 'type' => 'currency'],
                ['key' => 'discount', 'label' => 'Disc (₹)', 'type' => 'currency'],
                ['key' => 'total', 'label' => 'Total (₹)', 'type' => 'currency'],
                ['key' => 'paid', 'label' => 'Paid (₹)', 'type' => 'currency'],
                ['key' => 'balance', 'label' => 'Balance (₹)', 'type' => 'currency'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];
    }

    /**
     * Sales Detail Report (Line-Item Level)
     */
    public static function getSalesDetail(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $query = InvoiceItem::with(['invoice.customer', 'product'])
            ->whereHas('invoice', function ($q) use ($companyId, $dates, $filters) {
                $q->where('company_id', $companyId)
                    ->whereDate('invoice_date', '>=', $dates['start_date'])
                    ->whereDate('invoice_date', '<=', $dates['end_date'])
                    ->where('status', '!=', 'CANCELLED');
                if (!empty($filters['branch_id'])) {
                    $q->where('branch_id', $filters['branch_id']);
                }
                if (!empty($filters['customer_id'])) {
                    $q->where('customer_id', $filters['customer_id']);
                }
            });

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        $items = $query->get();

        $rows = [];
        $totalQty = 0.0;
        $totalTaxable = 0.0;
        $totalGst = 0.0;
        $totalAmt = 0.0;

        foreach ($items as $it) {
            $inv = $it->invoice;
            $qty = floatval($it->quantity);
            $taxable = floatval($it->taxable_value);
            $gst = floatval($it->cgst_amount + $it->sgst_amount + $it->igst_amount);
            $total = floatval($it->total_amount ?: ($taxable + $gst));

            $totalQty += $qty;
            $totalTaxable += $taxable;
            $totalGst += $gst;
            $totalAmt += $total;

            $rows[] = [
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'date' => is_object($inv->invoice_date) ? $inv->invoice_date->format('Y-m-d') : (string)$inv->invoice_date,
                'customer_name' => $inv->customer?->name ?: ($inv->customer_name ?: 'Customer'),
                'product_name' => $it->item_name ?: ($it->product?->name ?: 'Item'),
                'hsn_sac' => $it->hsn_sac ?: ($it->product?->hsn_sac ?: ''),
                'quantity' => $qty,
                'unit' => $it->unit ?: 'Pcs',
                'unit_price' => floatval($it->unit_price),
                'discount_amount' => floatval($it->discount_amount),
                'taxable_value' => $taxable,
                'gst_rate' => floatval($it->gst_rate) . '%',
                'gst_amount' => $gst,
                'total_amount' => $total,
            ];
        }

        return [
            'period' => $dates,
            'summary_kpis' => [
                'total_items_count' => count($rows),
                'total_quantity' => $totalQty,
                'total_taxable_value' => $totalTaxable,
                'total_gst_amount' => $totalGst,
                'total_amount' => $totalAmt,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'invoice_number', 'label' => 'Invoice #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'customer_name', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'product_name', 'label' => 'Product', 'type' => 'text'],
                ['key' => 'hsn_sac', 'label' => 'HSN/SAC', 'type' => 'text'],
                ['key' => 'quantity', 'label' => 'Qty', 'type' => 'number'],
                ['key' => 'unit_price', 'label' => 'Rate (₹)', 'type' => 'currency'],
                ['key' => 'discount_amount', 'label' => 'Disc (₹)', 'type' => 'currency'],
                ['key' => 'taxable_value', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'gst_rate', 'label' => 'GST %', 'type' => 'text'],
                ['key' => 'gst_amount', 'label' => 'GST (₹)', 'type' => 'currency'],
                ['key' => 'total_amount', 'label' => 'Total (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Sales by Product
     */
    public static function getSalesByProduct(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $items = InvoiceItem::with(['invoice', 'product'])
            ->whereHas('invoice', function ($q) use ($companyId, $dates, $filters) {
                $q->where('company_id', $companyId)
                    ->whereDate('invoice_date', '>=', $dates['start_date'])
                    ->whereDate('invoice_date', '<=', $dates['end_date'])
                    ->where('status', '!=', 'CANCELLED');
                if (!empty($filters['branch_id'])) {
                    $q->where('branch_id', $filters['branch_id']);
                }
            })->get();

        $prodMap = [];
        foreach ($items as $it) {
            $pId = $it->product_id ?: 0;
            $pName = $it->item_name ?: ($it->product?->name ?: 'Item #' . $pId);
            $sku = $it->product?->sku ?: '';

            if (!isset($prodMap[$pId])) {
                $prodMap[$pId] = [
                    'product_id' => $pId,
                    'product_name' => $pName,
                    'sku' => $sku,
                    'quantity_sold' => 0.0,
                    'gross_sales' => 0.0,
                    'discount' => 0.0,
                    'taxable_sales' => 0.0,
                    'gst_amount' => 0.0,
                    'net_sales' => 0.0,
                    'invoice_count' => 0,
                ];
            }

            $prodMap[$pId]['quantity_sold'] += floatval($it->quantity);
            $prodMap[$pId]['gross_sales'] += floatval($it->unit_price * $it->quantity);
            $prodMap[$pId]['discount'] += floatval($it->discount_amount);
            $prodMap[$pId]['taxable_sales'] += floatval($it->taxable_value);
            $prodMap[$pId]['gst_amount'] += floatval($it->cgst_amount + $it->sgst_amount + $it->igst_amount);
            $prodMap[$pId]['net_sales'] += floatval($it->total_amount ?: ($it->taxable_value + $it->cgst_amount + $it->sgst_amount + $it->igst_amount));
            $prodMap[$pId]['invoice_count']++;
        }

        $rows = array_values($prodMap);
        foreach ($rows as &$r) {
            $r['avg_selling_price'] = $r['quantity_sold'] > 0 ? round($r['taxable_sales'] / $r['quantity_sold'], 2) : 0.0;
        }

        // Sort by Net Sales DESC
        usort($rows, fn($a, $b) => $b['net_sales'] <=> $a['net_sales']);

        $top10 = array_slice($rows, 0, 10);
        $labels = array_column($top10, 'product_name');
        $revs = array_column($top10, 'net_sales');

        $chart = ReportChartService::buildBarChart(
            'Top Products by Revenue (₹)',
            $labels,
            [['name' => 'Revenue (₹)', 'data' => $revs, 'color' => '#10b981']]
        );

        return [
            'period' => $dates,
            'charts' => ['top_products_revenue' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'product_name', 'label' => 'Product Name', 'type' => 'text'],
                ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
                ['key' => 'quantity_sold', 'label' => 'Qty Sold', 'type' => 'number'],
                ['key' => 'avg_selling_price', 'label' => 'Avg Rate (₹)', 'type' => 'currency'],
                ['key' => 'discount', 'label' => 'Discount (₹)', 'type' => 'currency'],
                ['key' => 'taxable_sales', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'gst_amount', 'label' => 'GST (₹)', 'type' => 'currency'],
                ['key' => 'net_sales', 'label' => 'Net Revenue (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Sales by Customer
     */
    public static function getSalesByCustomer(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $invs = Invoice::with('customer')
            ->where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $dates['start_date'])
            ->whereDate('invoice_date', '<=', $dates['end_date'])
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $custMap = [];
        foreach ($invs as $inv) {
            $cId = $inv->customer_id ?: 0;
            $cName = $inv->customer?->name ?: ($inv->customer_name ?: 'Customer #' . $cId);
            $phone = $inv->customer?->phone ?: '';
            $gstin = $inv->customer?->gstin ?: ($inv->customer_gstin ?: '');

            if (!isset($custMap[$cId])) {
                $custMap[$cId] = [
                    'customer_id' => $cId,
                    'customer_name' => $cName,
                    'phone' => $phone,
                    'gstin' => $gstin,
                    'invoice_count' => 0,
                    'total_sales' => 0.0,
                    'amount_paid' => 0.0,
                    'amount_due' => 0.0,
                ];
            }

            $custMap[$cId]['invoice_count']++;
            $custMap[$cId]['total_sales'] += floatval($inv->grand_total);
            $custMap[$cId]['amount_paid'] += floatval($inv->amount_paid);
            $custMap[$cId]['amount_due'] += floatval($inv->amount_due);
        }

        $rows = array_values($custMap);
        usort($rows, fn($a, $b) => $b['total_sales'] <=> $a['total_sales']);

        $top10 = array_slice($rows, 0, 10);
        $labels = array_column($top10, 'customer_name');
        $revs = array_column($top10, 'total_sales');

        $chart = ReportChartService::buildBarChart(
            'Top Customers by Sales (₹)',
            $labels,
            [['name' => 'Sales (₹)', 'data' => $revs, 'color' => '#6366f1']]
        );

        return [
            'period' => $dates,
            'charts' => ['top_customers_sales' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'customer_name', 'label' => 'Customer Name', 'type' => 'text'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['key' => 'gstin', 'label' => 'GSTIN', 'type' => 'text'],
                ['key' => 'invoice_count', 'label' => 'Invoices', 'type' => 'number'],
                ['key' => 'total_sales', 'label' => 'Total Billed (₹)', 'type' => 'currency'],
                ['key' => 'amount_paid', 'label' => 'Paid (₹)', 'type' => 'currency'],
                ['key' => 'amount_due', 'label' => 'Outstanding Due (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Sales by Category
     */
    public static function getSalesByCategory(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $items = InvoiceItem::with(['invoice', 'product'])
            ->whereHas('invoice', function ($q) use ($companyId, $dates, $filters) {
                $q->where('company_id', $companyId)
                    ->whereDate('invoice_date', '>=', $dates['start_date'])
                    ->whereDate('invoice_date', '<=', $dates['end_date'])
                    ->where('status', '!=', 'CANCELLED');
                if (!empty($filters['branch_id'])) {
                    $q->where('branch_id', $filters['branch_id']);
                }
            })->get();

        $catMap = [];
        foreach ($items as $it) {
            $cat = is_string($it->product?->category) ? $it->product?->category : ($it->product?->category?->name ?: 'General');
            if (!isset($catMap[$cat])) {
                $catMap[$cat] = [
                    'category' => $cat,
                    'quantity_sold' => 0.0,
                    'taxable_amount' => 0.0,
                    'total_sales' => 0.0,
                ];
            }
            $catMap[$cat]['quantity_sold'] += floatval($it->quantity);
            $catMap[$cat]['taxable_amount'] += floatval($it->taxable_value);
            $catMap[$cat]['total_sales'] += floatval($it->total_amount ?: ($it->taxable_value + $it->cgst_amount + $it->sgst_amount + $it->igst_amount));
        }

        $rows = array_values($catMap);
        usort($rows, fn($a, $b) => $b['total_sales'] <=> $a['total_sales']);

        $chartItems = array_map(fn($r) => ['name' => $r['category'], 'value' => $r['total_sales']], $rows);
        $chart = ReportChartService::buildDonutChart('Sales by Category', $chartItems);

        return [
            'period' => $dates,
            'charts' => ['sales_by_category' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'category', 'label' => 'Category', 'type' => 'text'],
                ['key' => 'quantity_sold', 'label' => 'Qty Sold', 'type' => 'number'],
                ['key' => 'taxable_amount', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'total_sales', 'label' => 'Total Sales (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Sales by Branch
     */
    public static function getSalesByBranch(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $branches = Branch::where('company_id', $companyId)->get();

        $rows = [];
        foreach ($branches as $br) {
            $invs = Invoice::where('company_id', $companyId)
                ->where('branch_id', $br->id)
                ->whereDate('invoice_date', '>=', $dates['start_date'])
                ->whereDate('invoice_date', '<=', $dates['end_date'])
                ->where('status', '!=', 'CANCELLED')
                ->get();

            $rows[] = [
                'branch_id' => $br->id,
                'branch_name' => $br->name,
                'branch_code' => $br->code,
                'invoice_count' => $invs->count(),
                'taxable_amount' => floatval($invs->sum(fn($i) => $i->taxable_value ?: $i->subtotal)),
                'tax_amount' => floatval($invs->sum('total_tax')),
                'grand_total' => floatval($invs->sum('grand_total')),
                'amount_paid' => floatval($invs->sum('amount_paid')),
                'amount_due' => floatval($invs->sum('amount_due')),
            ];
        }

        $labels = array_column($rows, 'branch_name');
        $sales = array_column($rows, 'grand_total');
        $chart = ReportChartService::buildBarChart('Sales by Branch', $labels, [['name' => 'Sales (₹)', 'data' => $sales, 'color' => '#0ea5e9']]);

        return [
            'period' => $dates,
            'charts' => ['sales_by_branch' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'branch_name', 'label' => 'Branch Name', 'type' => 'text'],
                ['key' => 'branch_code', 'label' => 'Code', 'type' => 'text'],
                ['key' => 'invoice_count', 'label' => 'Invoices', 'type' => 'number'],
                ['key' => 'taxable_amount', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'tax_amount', 'label' => 'GST (₹)', 'type' => 'currency'],
                ['key' => 'grand_total', 'label' => 'Total Sales (₹)', 'type' => 'currency'],
                ['key' => 'amount_paid', 'label' => 'Paid (₹)', 'type' => 'currency'],
                ['key' => 'amount_due', 'label' => 'Outstanding (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Sales by Payment Mode
     */
    public static function getSalesByPaymentMode(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $payments = Payment::where('company_id', $companyId)
            ->where('party_type', 'CUSTOMER')
            ->whereDate('payment_date', '>=', $dates['start_date'])
            ->whereDate('payment_date', '<=', $dates['end_date'])
            ->get();

        $modes = [];
        $totalAmount = 0.0;
        foreach ($payments as $pm) {
            $mode = strtoupper($pm->payment_mode ?: 'CASH');
            if (!isset($modes[$mode])) {
                $modes[$mode] = ['mode' => $mode, 'count' => 0, 'amount' => 0.0];
            }
            $modes[$mode]['count']++;
            $modes[$mode]['amount'] += floatval($pm->amount);
            $totalAmount += floatval($pm->amount);
        }

        $rows = [];
        foreach ($modes as $m) {
            $rows[] = [
                'payment_mode' => $m['mode'],
                'count' => $m['count'],
                'amount' => $m['amount'],
                'percentage' => $totalAmount > 0 ? round(($m['amount'] / $totalAmount) * 100, 2) : 0.0,
            ];
        }

        $chartItems = array_map(fn($r) => ['name' => $r['payment_mode'], 'value' => $r['amount']], $rows);
        $chart = ReportChartService::buildDonutChart('Collections by Payment Mode', $chartItems);

        return [
            'period' => $dates,
            'summary_kpis' => ['total_collections' => $totalAmount, 'total_transactions' => $payments->count()],
            'charts' => ['collections_by_mode' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'payment_mode', 'label' => 'Payment Mode', 'type' => 'text'],
                ['key' => 'count', 'label' => 'Transaction Count', 'type' => 'number'],
                ['key' => 'amount', 'label' => 'Collected Amount (₹)', 'type' => 'currency'],
                ['key' => 'percentage', 'label' => 'Share %', 'type' => 'percentage'],
            ],
        ];
    }

    /**
     * Sales Returns (Credit Notes)
     */
    public static function getSalesReturns(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $notes = CreditNote::with(['customer', 'items'])
            ->where('company_id', $companyId)
            ->whereDate('credit_note_date', '>=', $dates['start_date'])
            ->whereDate('credit_note_date', '<=', $dates['end_date'])
            ->get();

        $rows = [];
        $totalTaxable = 0.0;
        $totalGst = 0.0;
        $totalGrand = 0.0;

        foreach ($notes as $cn) {
            $taxable = floatval($cn->taxable_value);
            $gst = floatval($cn->cgst_amount + $cn->sgst_amount + $cn->igst_amount);
            $grand = floatval($cn->grand_total);

            $totalTaxable += $taxable;
            $totalGst += $gst;
            $totalGrand += $grand;

            $rows[] = [
                'credit_note_id' => $cn->id,
                'credit_note_number' => $cn->credit_note_number,
                'date' => is_object($cn->credit_note_date) ? $cn->credit_note_date->format('Y-m-d') : (string)$cn->credit_note_date,
                'original_invoice' => $cn->invoice_number ?: '-',
                'customer_name' => $cn->customer?->name ?: 'Customer',
                'reason' => $cn->reason ?: 'Goods Return',
                'taxable_value' => $taxable,
                'gst_reversed' => $gst,
                'grand_total' => $grand,
            ];
        }

        return [
            'period' => $dates,
            'summary_kpis' => [
                'returns_count' => count($rows),
                'total_taxable_reversed' => $totalTaxable,
                'total_gst_reversed' => $totalGst,
                'total_return_value' => $totalGrand,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'credit_note_number', 'label' => 'Credit Note #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'original_invoice', 'label' => 'Original Invoice', 'type' => 'text'],
                ['key' => 'customer_name', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'reason', 'label' => 'Reason', 'type' => 'text'],
                ['key' => 'taxable_value', 'label' => 'Taxable Reversed (₹)', 'type' => 'currency'],
                ['key' => 'gst_reversed', 'label' => 'GST Reversed (₹)', 'type' => 'currency'],
                ['key' => 'grand_total', 'label' => 'Credit Amount (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Sales Growth & Trend with Period Comparison
     */
    public static function getSalesGrowth(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $currStart = Carbon::parse($dates['start_date']);
        $currEnd = Carbon::parse($dates['end_date']);
        $durationDays = $currStart->diffInDays($currEnd) + 1;

        $prevStart = $currStart->copy()->subDays($durationDays);
        $prevEnd = $currStart->copy()->subDay();

        $currSales = floatval(Invoice::where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $currStart->toDateString())
            ->whereDate('invoice_date', '<=', $currEnd->toDateString())
            ->where('status', '!=', 'CANCELLED')
            ->sum('grand_total'));

        $prevSales = floatval(Invoice::where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $prevStart->toDateString())
            ->whereDate('invoice_date', '<=', $prevEnd->toDateString())
            ->where('status', '!=', 'CANCELLED')
            ->sum('grand_total'));

        $growthRate = 0.0;
        if ($prevSales > 0) {
            $growthRate = round((($currSales - $prevSales) / $prevSales) * 100, 2);
        } elseif ($currSales > 0) {
            $growthRate = 100.0;
        }

        return [
            'current_period' => ['label' => $dates['label'] ?? 'Current Period', 'sales' => $currSales],
            'previous_period' => ['label' => 'Previous ' . $durationDays . ' Days', 'sales' => $prevSales],
            'growth_rate_pct' => $growthRate,
            'growth_amount' => round($currSales - $prevSales, 2),
        ];
    }
}
