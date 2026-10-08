<?php

namespace App\Reporting\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\CreditNote;
use App\Models\JournalLine;
use App\Models\ChartOfAccount;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;

class CustomerReportService
{
    /**
     * Customer Outstanding & Receivables Summary
     */
    public static function getCustomerOutstanding(int $companyId, array $filters = []): array
    {
        $customers = Customer::where('company_id', $companyId)->get();
        $now = Carbon::now();

        $rows = [];
        $totalInvoiced = 0.0;
        $totalCollected = 0.0;
        $totalOutstanding = 0.0;
        $totalOverdue = 0.0;

        foreach ($customers as $c) {
            $invoices = Invoice::where('company_id', $companyId)
                ->where('customer_id', $c->id)
                ->where('status', '!=', 'CANCELLED')
                ->get();

            $invCount = $invoices->count();
            $invVal = floatval($invoices->sum('grand_total'));
            $paid = floatval($invoices->sum('amount_paid'));
            $due = floatval($invoices->sum('amount_due'));

            $overdue = floatval($invoices->filter(function ($i) use ($now) {
                if ($i->amount_due <= 0) return false;
                $dueDt = $i->due_date ? Carbon::parse($i->due_date) : Carbon::parse($i->invoice_date);
                return $dueDt->lt($now);
            })->sum('amount_due'));

            if ($due > 0 || $invCount > 0) {
                $totalInvoiced += $invVal;
                $totalCollected += $paid;
                $totalOutstanding += $due;
                $totalOverdue += $overdue;

                $rows[] = [
                    'customer_id' => $c->id,
                    'customer_name' => $c->name,
                    'phone' => $c->phone ?: '-',
                    'gstin' => $c->gstin ?: '-',
                    'invoice_count' => $invCount,
                    'total_invoice_value' => $invVal,
                    'amount_paid' => $paid,
                    'outstanding_balance' => $due,
                    'overdue_amount' => $overdue,
                    'credit_limit' => floatval($c->credit_limit),
                ];
            }
        }

        usort($rows, fn($a, $b) => $b['outstanding_balance'] <=> $a['outstanding_balance']);

        return [
            'summary_kpis' => [
                'total_customers_with_due' => count(array_filter($rows, fn($r) => $r['outstanding_balance'] > 0)),
                'total_invoiced' => $totalInvoiced,
                'total_collected' => $totalCollected,
                'total_outstanding' => $totalOutstanding,
                'total_overdue' => $totalOverdue,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'customer_name', 'label' => 'Customer Name', 'type' => 'text'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['key' => 'invoice_count', 'label' => 'Invoices', 'type' => 'number'],
                ['key' => 'total_invoice_value', 'label' => 'Total Billed (₹)', 'type' => 'currency'],
                ['key' => 'amount_paid', 'label' => 'Paid (₹)', 'type' => 'currency'],
                ['key' => 'outstanding_balance', 'label' => 'Outstanding (₹)', 'type' => 'currency'],
                ['key' => 'overdue_amount', 'label' => 'Overdue (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Receivable Aging Analysis (5 Aging Buckets)
     */
    public static function getReceivableAging(int $companyId, array $filters = []): array
    {
        $now = Carbon::now();
        $invoices = Invoice::with('customer')
            ->where('company_id', $companyId)
            ->where('amount_due', '>', 0)
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $buckets = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_90_plus' => 0.0,
        ];

        $customerMap = [];

        foreach ($invoices as $inv) {
            $cId = $inv->customer_id ?: 0;
            $cName = $inv->customer?->name ?: ($inv->customer_name ?: 'Customer #' . $cId);
            $dueDt = $inv->due_date ? Carbon::parse($inv->due_date) : Carbon::parse($inv->invoice_date);
            $daysPast = $now->diffInDays($dueDt, false); // negative if overdue

            $amount = floatval($inv->amount_due);

            if (!isset($customerMap[$cId])) {
                $customerMap[$cId] = [
                    'customer_id' => $cId,
                    'customer_name' => $cName,
                    'phone' => $inv->customer?->phone ?: '',
                    'total_due' => 0.0,
                    'current' => 0.0,
                    'days_1_30' => 0.0,
                    'days_31_60' => 0.0,
                    'days_61_90' => 0.0,
                    'days_90_plus' => 0.0,
                ];
            }

            $customerMap[$cId]['total_due'] += $amount;

            if ($daysPast >= 0) {
                $buckets['current'] += $amount;
                $customerMap[$cId]['current'] += $amount;
            } else {
                $overdueDays = abs($daysPast);
                if ($overdueDays <= 30) {
                    $buckets['days_1_30'] += $amount;
                    $customerMap[$cId]['days_1_30'] += $amount;
                } elseif ($overdueDays <= 60) {
                    $buckets['days_31_60'] += $amount;
                    $customerMap[$cId]['days_31_60'] += $amount;
                } elseif ($overdueDays <= 90) {
                    $buckets['days_61_90'] += $amount;
                    $customerMap[$cId]['days_61_90'] += $amount;
                } else {
                    $buckets['days_90_plus'] += $amount;
                    $customerMap[$cId]['days_90_plus'] += $amount;
                }
            }
        }

        $totalAging = array_sum($buckets);

        $chartLabels = ['Not Due (Current)', '1-30 Days Overdue', '31-60 Days Overdue', '61-90 Days Overdue', '90+ Days Overdue'];
        $chartValues = [
            $buckets['current'],
            $buckets['days_1_30'],
            $buckets['days_31_60'],
            $buckets['days_61_90'],
            $buckets['days_90_plus'],
        ];

        $chart = ReportChartService::buildBarChart(
            'Receivable Aging Breakdown (₹)',
            $chartLabels,
            [['name' => 'Outstanding (₹)', 'data' => $chartValues, 'color' => '#ef4444']]
        );

        $rows = array_values($customerMap);
        usort($rows, fn($a, $b) => $b['total_due'] <=> $a['total_due']);

        return [
            'summary_kpis' => [
                'total_receivable' => $totalAging,
                'current_not_due' => $buckets['current'],
                'overdue_1_30' => $buckets['days_1_30'],
                'overdue_31_60' => $buckets['days_31_60'],
                'overdue_61_90' => $buckets['days_61_90'],
                'overdue_90_plus' => $buckets['days_90_plus'],
            ],
            'charts' => ['aging_breakdown' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'customer_name', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'total_due', 'label' => 'Total Due (₹)', 'type' => 'currency'],
                ['key' => 'current', 'label' => 'Current (₹)', 'type' => 'currency'],
                ['key' => 'days_1_30', 'label' => '1-30 Days (₹)', 'type' => 'currency'],
                ['key' => 'days_31_60', 'label' => '31-60 Days (₹)', 'type' => 'currency'],
                ['key' => 'days_61_90', 'label' => '61-90 Days (₹)', 'type' => 'currency'],
                ['key' => 'days_90_plus', 'label' => '90+ Days (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Customer Profitability Analysis
     */
    public static function getCustomerProfitability(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $items = InvoiceItem::with(['invoice.customer', 'product'])
            ->whereHas('invoice', function ($q) use ($companyId, $dates, $filters) {
                $q->where('company_id', $companyId)
                    ->whereDate('invoice_date', '>=', $dates['start_date'])
                    ->whereDate('invoice_date', '<=', $dates['end_date'])
                    ->where('status', '!=', 'CANCELLED');
            })->get();

        $custProfit = [];
        foreach ($items as $it) {
            $cId = $it->invoice?->customer_id ?: 0;
            $cName = $it->invoice?->customer?->name ?: 'Customer #' . $cId;

            if (!isset($custProfit[$cId])) {
                $custProfit[$cId] = [
                    'customer_id' => $cId,
                    'customer_name' => $cName,
                    'revenue' => 0.0,
                    'cost_of_goods' => 0.0,
                    'gross_profit' => 0.0,
                    'margin_percentage' => 0.0,
                ];
            }

            $revenue = floatval($it->taxable_value ?: ($it->quantity * $it->unit_price));
            $purchasePrice = floatval($it->product?->purchase_price ?: ($it->product?->cost_price ?: ($it->unit_price * 0.7)));
            $cost = $it->quantity * $purchasePrice;

            $custProfit[$cId]['revenue'] += $revenue;
            $custProfit[$cId]['cost_of_goods'] += $cost;
            $custProfit[$cId]['gross_profit'] += ($revenue - $cost);
        }

        $rows = array_values($custProfit);
        foreach ($rows as &$r) {
            $r['margin_percentage'] = $r['revenue'] > 0 ? round(($r['gross_profit'] / $r['revenue']) * 100, 2) : 0.0;
        }

        usort($rows, fn($a, $b) => $b['gross_profit'] <=> $a['gross_profit']);

        return [
            'period' => $dates,
            'rows' => $rows,
            'columns' => [
                ['key' => 'customer_name', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'revenue', 'label' => 'Revenue (₹)', 'type' => 'currency'],
                ['key' => 'cost_of_goods', 'label' => 'COGS (₹)', 'type' => 'currency'],
                ['key' => 'gross_profit', 'label' => 'Gross Profit (₹)', 'type' => 'currency'],
                ['key' => 'margin_percentage', 'label' => 'Margin %', 'type' => 'percentage'],
            ],
        ];
    }
}
