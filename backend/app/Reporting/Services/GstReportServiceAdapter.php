<?php

namespace App\Reporting\Services;

use App\Services\GSTReportingService;
use App\Services\GSTReconciliationService;
use App\Models\GSTDocumentSnapshot;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Purchase;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;

class GstReportServiceAdapter
{
    /**
     * GST Summary Report
     */
    public static function getGstSummary(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $branchId = !empty($filters['branch_id']) ? intval($filters['branch_id']) : null;

        $gst = GSTReportingService::getDashboardSummary($companyId, $dates['start_date'], $dates['end_date'], $branchId);

        $outTax = floatval($gst['output_tax']['net_output_tax'] ?? ($gst['output_tax']['total_output_tax'] ?? 0));
        $inTax = floatval($gst['input_tax_credit']['net_eligible_itc'] ?? ($gst['input_tax_credit']['total_input_tax'] ?? 0));

        if ($outTax == 0) {
            $invs = Invoice::where('company_id', $companyId)
                ->whereDate('invoice_date', '>=', $dates['start_date'])
                ->whereDate('invoice_date', '<=', $dates['end_date'])
                ->where('status', '!=', 'CANCELLED')->get();
            $outTax = floatval($invs->sum('total_tax') ?: ($invs->sum('cgst_amount') + $invs->sum('sgst_amount') + $invs->sum('igst_amount')));
            $cgstOut = floatval($invs->sum('cgst_amount'));
            $sgstOut = floatval($invs->sum('sgst_amount'));
            $igstOut = floatval($invs->sum('igst_amount'));
        } else {
            $cgstOut = floatval($gst['output_tax']['cgst'] ?? 0);
            $sgstOut = floatval($gst['output_tax']['sgst'] ?? 0);
            $igstOut = floatval($gst['output_tax']['igst'] ?? 0);
        }

        if ($inTax == 0) {
            $purchases = Purchase::where('company_id', $companyId)
                ->whereDate('purchase_date', '>=', $dates['start_date'])
                ->whereDate('purchase_date', '<=', $dates['end_date'])
                ->where('status', '!=', 'CANCELLED')->get();
            $inTax = floatval($purchases->sum('total_tax') ?: ($purchases->sum('cgst_amount') + $purchases->sum('sgst_amount') + $purchases->sum('igst_amount')));
            $cgstIn = floatval($purchases->sum('cgst_amount'));
            $sgstIn = floatval($purchases->sum('sgst_amount'));
            $igstIn = floatval($purchases->sum('igst_amount'));
        } else {
            $cgstIn = floatval($gst['input_tax_credit']['cgst'] ?? 0);
            $sgstIn = floatval($gst['input_tax_credit']['sgst'] ?? 0);
            $igstIn = floatval($gst['input_tax_credit']['igst'] ?? 0);
        }

        $netLiability = max(0, $outTax - $inTax);

        $chart = ReportChartService::buildBarChart(
            'GST Output vs Input Credit (₹)',
            ['Output GST (Sales)', 'Input Tax Credit (Purchases)', 'Net GST Payable'],
            [['name' => 'Amount (₹)', 'data' => [$outTax, $inTax, $netLiability], 'color' => '#8b5cf6']]
        );

        $rows = [
            ['component' => 'CGST', 'output_tax' => $cgstOut, 'input_tax' => $cgstIn, 'net_payable' => max(0, $cgstOut - $cgstIn)],
            ['component' => 'SGST', 'output_tax' => $sgstOut, 'input_tax' => $sgstIn, 'net_payable' => max(0, $sgstOut - $sgstIn)],
            ['component' => 'IGST', 'output_tax' => $igstOut, 'input_tax' => $igstIn, 'net_payable' => max(0, $igstOut - $igstIn)],
            ['component' => 'CESS', 'output_tax' => floatval($gst['output_tax']['cess'] ?? 0), 'input_tax' => floatval($gst['input_tax_credit']['cess'] ?? 0), 'net_payable' => 0.0],
        ];

        return [
            'period' => $dates,
            'summary_kpis' => [
                'total_output_tax' => $outTax,
                'total_input_tax_credit' => $inTax,
                'net_gst_payable' => $netLiability,
            ],
            'charts' => [
                'gst_output_vs_input' => $chart,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'component', 'label' => 'Tax Component', 'type' => 'text'],
                ['key' => 'output_tax', 'label' => 'Output Liability (₹)', 'type' => 'currency'],
                ['key' => 'input_tax', 'label' => 'Input Credit (₹)', 'type' => 'currency'],
                ['key' => 'net_payable', 'label' => 'Net Payable (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * GST Taxable Summary Grouped by Rate (0%, 5%, 12%, 18%, 28%)
     */
    public static function getGstTaxableSummary(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $items = InvoiceItem::with('invoice')
            ->whereHas('invoice', function ($q) use ($companyId, $dates) {
                $q->where('company_id', $companyId)
                    ->whereDate('invoice_date', '>=', $dates['start_date'])
                    ->whereDate('invoice_date', '<=', $dates['end_date'])
                    ->where('status', '!=', 'CANCELLED');
            })->get();

        $rates = [0.0 => 0.0, 5.0 => 0.0, 12.0 => 0.0, 18.0 => 0.0, 28.0 => 0.0];
        $taxMap = [0.0 => 0.0, 5.0 => 0.0, 12.0 => 0.0, 18.0 => 0.0, 28.0 => 0.0];

        foreach ($items as $it) {
            $r = floatval($it->gst_rate);
            $taxable = floatval($it->taxable_value);
            $tax = floatval($it->cgst_amount + $it->sgst_amount + $it->igst_amount);

            if (!isset($rates[$r])) {
                $rates[$r] = 0.0;
                $taxMap[$r] = 0.0;
            }
            $rates[$r] += $taxable;
            $taxMap[$r] += $tax;
        }

        $rows = [];
        foreach ($rates as $r => $taxableVal) {
            $rows[] = [
                'rate' => $r . '%',
                'taxable_amount' => $taxableVal,
                'cgst' => round($taxMap[$r] / 2, 2),
                'sgst' => round($taxMap[$r] / 2, 2),
                'igst' => 0.0,
                'total_tax' => $taxMap[$r],
                'total_value' => $taxableVal + $taxMap[$r],
            ];
        }

        return [
            'period' => $dates,
            'rows' => $rows,
            'columns' => [
                ['key' => 'rate', 'label' => 'GST Slab', 'type' => 'text'],
                ['key' => 'taxable_amount', 'label' => 'Taxable Value (₹)', 'type' => 'currency'],
                ['key' => 'total_tax', 'label' => 'Total GST (₹)', 'type' => 'currency'],
                ['key' => 'total_value', 'label' => 'Total Invoiced (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * GST Output Register (Sales)
     */
    public static function getGstOutputRegister(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $invoices = Invoice::with('customer')
            ->where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $dates['start_date'])
            ->whereDate('invoice_date', '<=', $dates['end_date'])
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $rows = $invoices->map(fn($inv) => [
            'invoice_number' => $inv->invoice_number,
            'date' => is_object($inv->invoice_date) ? $inv->invoice_date->format('Y-m-d') : (string)$inv->invoice_date,
            'customer_name' => $inv->customer?->name ?: ($inv->customer_name ?: 'Walk-in'),
            'gstin' => $inv->customer?->gstin ?: ($inv->customer_gstin ?: 'UNREGISTERED'),
            'taxable_amount' => floatval($inv->taxable_value ?: $inv->subtotal),
            'cgst' => floatval($inv->cgst_amount),
            'sgst' => floatval($inv->sgst_amount),
            'igst' => floatval($inv->igst_amount),
            'total_tax' => floatval($inv->total_tax ?: $inv->tax_total),
            'grand_total' => floatval($inv->grand_total),
        ])->toArray();

        return [
            'period' => $dates,
            'rows' => $rows,
            'columns' => [
                ['key' => 'invoice_number', 'label' => 'Invoice #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'customer_name', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'gstin', 'label' => 'GSTIN', 'type' => 'text'],
                ['key' => 'taxable_amount', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'cgst', 'label' => 'CGST (₹)', 'type' => 'currency'],
                ['key' => 'sgst', 'label' => 'SGST (₹)', 'type' => 'currency'],
                ['key' => 'igst', 'label' => 'IGST (₹)', 'type' => 'currency'],
                ['key' => 'total_tax', 'label' => 'Total GST (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * GST Input Register (Purchases & Inward ITC)
     */
    public static function getGstInputRegister(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $purchases = Purchase::with('supplier')
            ->where('company_id', $companyId)
            ->whereDate('purchase_date', '>=', $dates['start_date'])
            ->whereDate('purchase_date', '<=', $dates['end_date'])
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $rows = $purchases->map(fn($p) => [
            'purchase_number' => $p->purchase_number,
            'vendor_inv' => $p->vendor_invoice_number ?: '-',
            'date' => is_object($p->purchase_date) ? $p->purchase_date->format('Y-m-d') : (string)$p->purchase_date,
            'supplier_name' => $p->supplier?->name ?: ($p->supplier_name ?: 'Supplier'),
            'gstin' => $p->supplier?->gstin ?: ($p->supplier_gstin ?: 'UNREGISTERED'),
            'taxable_amount' => floatval($p->taxable_value ?: $p->subtotal),
            'cgst' => floatval($p->cgst_amount),
            'sgst' => floatval($p->sgst_amount),
            'igst' => floatval($p->igst_amount),
            'total_tax' => floatval($p->total_tax ?: ($p->cgst_amount + $p->sgst_amount + $p->igst_amount)),
            'itc_eligibility' => 'ELIGIBLE',
        ])->toArray();

        return [
            'period' => $dates,
            'rows' => $rows,
            'columns' => [
                ['key' => 'purchase_number', 'label' => 'Purchase #', 'type' => 'text'],
                ['key' => 'vendor_inv', 'label' => 'Vendor Inv #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'supplier_name', 'label' => 'Supplier', 'type' => 'text'],
                ['key' => 'gstin', 'label' => 'GSTIN', 'type' => 'text'],
                ['key' => 'taxable_amount', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'total_tax', 'label' => 'ITC Amount (₹)', 'type' => 'currency'],
                ['key' => 'itc_eligibility', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];
    }

    /**
     * HSN/SAC Summary
     */
    public static function getHsnSummary(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $hsnData = GSTReportingService::getHSNSummary($companyId, $dates['start_date'], $dates['end_date']);

        $rows = [];
        foreach ($hsnData as $h) {
            $rows[] = [
                'hsn_sac' => $h['hsn_sac'] ?: 'General',
                'description' => $h['description'] ?? 'Products / Services',
                'quantity' => floatval($h['total_quantity'] ?? 0),
                'taxable_value' => floatval($h['taxable_value'] ?? 0),
                'cgst' => floatval($h['cgst_amount'] ?? 0),
                'sgst' => floatval($h['sgst_amount'] ?? 0),
                'igst' => floatval($h['igst_amount'] ?? 0),
                'total_tax' => floatval($h['total_tax'] ?? 0),
            ];
        }

        return [
            'period' => $dates,
            'rows' => $rows,
            'columns' => [
                ['key' => 'hsn_sac', 'label' => 'HSN/SAC', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'text'],
                ['key' => 'quantity', 'label' => 'Total Qty', 'type' => 'number'],
                ['key' => 'taxable_value', 'label' => 'Taxable (₹)', 'type' => 'currency'],
                ['key' => 'cgst', 'label' => 'CGST (₹)', 'type' => 'currency'],
                ['key' => 'sgst', 'label' => 'SGST (₹)', 'type' => 'currency'],
                ['key' => 'igst', 'label' => 'IGST (₹)', 'type' => 'currency'],
                ['key' => 'total_tax', 'label' => 'Total Tax (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * GST Reconciliation (Books vs Portal/Imported 2B)
     */
    public static function getGstReconciliation(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $reco = GSTReconciliationService::getReconciliationReport($companyId, $dates['start_date'], $dates['end_date']);

        return [
            'period' => $dates,
            'summary_kpis' => [
                'matched_count' => $reco['matched_count'] ?? 0,
                'mismatch_count' => $reco['mismatch_count'] ?? 0,
                'missing_in_books' => $reco['missing_in_books_count'] ?? 0,
                'missing_in_gstr2b' => $reco['missing_in_2b_count'] ?? 0,
            ],
            'rows' => $reco['items'] ?? [],
            'columns' => [
                ['key' => 'invoice_number', 'label' => 'Invoice #', 'type' => 'text'],
                ['key' => 'supplier_gstin', 'label' => 'GSTIN', 'type' => 'text'],
                ['key' => 'books_taxable', 'label' => 'Books Taxable (₹)', 'type' => 'currency'],
                ['key' => 'portal_taxable', 'label' => 'Portal Taxable (₹)', 'type' => 'currency'],
                ['key' => 'difference', 'label' => 'Difference (₹)', 'type' => 'currency'],
                ['key' => 'match_status', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];
    }
}
