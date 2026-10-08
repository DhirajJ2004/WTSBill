<?php

namespace App\Reporting\Services;

use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\DeliveryChallan;
use App\Models\RecurringInvoice;
use App\Reporting\Filters\ReportFilterService;

class OperationalReportService
{
    /**
     * Quotation Conversion Funnel
     */
    public static function getQuotationConversion(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $quotations = Quotation::where('company_id', $companyId)
            ->whereDate('quotation_date', '>=', $dates['start_date'])
            ->whereDate('quotation_date', '<=', $dates['end_date'])
            ->get();

        $totalQuotes = $quotations->count();
        $totalQuoteValue = floatval($quotations->sum('grand_total'));

        $accepted = $quotations->whereIn('status', ['ACCEPTED', 'CONVERTED']);
        $acceptedCount = $accepted->count();
        $acceptedValue = floatval($accepted->sum('grand_total'));

        $conversionRate = $totalQuotes > 0 ? round(($acceptedCount / $totalQuotes) * 100, 2) : 0.0;

        return [
            'period' => $dates,
            'summary_kpis' => [
                'total_quotations' => $totalQuotes,
                'total_quotation_value' => $totalQuoteValue,
                'converted_quotations' => $acceptedCount,
                'converted_value' => $acceptedValue,
                'conversion_rate_pct' => $conversionRate,
            ],
            'rows' => $quotations->map(fn($q) => [
                'quotation_number' => $q->quotation_number,
                'date' => is_object($q->quotation_date) ? $q->quotation_date->format('Y-m-d') : (string)$q->quotation_date,
                'customer_name' => $q->customer?->name ?: ($q->customer_name ?: 'Customer'),
                'grand_total' => floatval($q->grand_total),
                'status' => $q->status,
                'valid_until' => $q->expiry_date ?: '-',
            ])->toArray(),
            'columns' => [
                ['key' => 'quotation_number', 'label' => 'Quotation #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'customer_name', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'grand_total', 'label' => 'Amount (₹)', 'type' => 'currency'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
                ['key' => 'valid_until', 'label' => 'Valid Until', 'type' => 'date'],
            ],
        ];
    }

    /**
     * Sales Order Summary Report
     */
    public static function getOrderSummary(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $orders = SalesOrder::where('company_id', $companyId)
            ->whereDate('order_date', '>=', $dates['start_date'])
            ->whereDate('order_date', '<=', $dates['end_date'])
            ->get();

        return [
            'period' => $dates,
            'summary_kpis' => [
                'order_count' => $orders->count(),
                'total_order_value' => floatval($orders->sum('grand_total')),
            ],
            'rows' => $orders->map(fn($o) => [
                'order_number' => $o->order_number,
                'date' => is_object($o->order_date) ? $o->order_date->format('Y-m-d') : (string)$o->order_date,
                'customer_name' => $o->customer?->name ?: ($o->customer_name ?: 'Customer'),
                'grand_total' => floatval($o->grand_total),
                'status' => $o->status,
            ])->toArray(),
            'columns' => [
                ['key' => 'order_number', 'label' => 'Order #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'customer_name', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'grand_total', 'label' => 'Order Value (₹)', 'type' => 'currency'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];
    }
}
