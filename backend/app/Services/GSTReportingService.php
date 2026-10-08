<?php

namespace App\Services;

use App\Models\GSTDocumentSnapshot;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\CreditNote;
use App\Models\DebitNote;
use Illuminate\Database\Capsule\Manager as DB;

class GSTReportingService
{
    /**
     * Get GST Dashboard Summary (Output Tax, Eligible Input Tax, Net Liability)
     */
    public static function getDashboardSummary(int $companyId, ?string $fromDate = null, ?string $toDate = null, ?int $branchId = null): array
    {
        $fromDate = $fromDate ?: date('Y-01-01');
        $toDate = $toDate ?: date('Y-m-d');

        // Output Tax from Sales Invoices
        $outQuery = GSTDocumentSnapshot::where('company_id', $companyId)
            ->where('document_type', 'INVOICE')
            ->whereBetween('document_date', [$fromDate, $toDate]);

        if ($branchId) $outQuery->where('branch_id', $branchId);

        $outTaxable = floatval($outQuery->sum('taxable_amount'));
        $outCgst = floatval($outQuery->sum('cgst_amount'));
        $outSgst = floatval($outQuery->sum('sgst_amount'));
        $outIgst = floatval($outQuery->sum('igst_amount'));
        $outCess = floatval($outQuery->sum('cess_amount'));
        $totalOutputTax = round($outCgst + $outSgst + $outIgst + $outCess, 2);

        // Sales Returns / Credit Notes Reduction
        $cnQuery = GSTDocumentSnapshot::where('company_id', $companyId)
            ->where('document_type', 'CREDIT_NOTE')
            ->whereBetween('document_date', [$fromDate, $toDate]);

        if ($branchId) $cnQuery->where('branch_id', $branchId);

        $cnTax = floatval($cnQuery->sum('total_tax_amount'));
        $netOutputTax = round(max(0, $totalOutputTax - $cnTax), 2);

        // Input Tax Credit from Purchases
        $inQuery = GSTDocumentSnapshot::where('company_id', $companyId)
            ->where('document_type', 'PURCHASE')
            ->whereBetween('document_date', [$fromDate, $toDate]);

        if ($branchId) $inQuery->where('branch_id', $branchId);

        $inTaxable = floatval($inQuery->sum('taxable_amount'));
        $inCgst = floatval($inQuery->sum('cgst_amount'));
        $inSgst = floatval($inQuery->sum('sgst_amount'));
        $inIgst = floatval($inQuery->sum('igst_amount'));
        $inCess = floatval($inQuery->sum('cess_amount'));
        $totalInputTax = round($inCgst + $inSgst + $inIgst + $inCess, 2);

        // Purchase Returns / Debit Notes ITC Reversals
        $dnQuery = GSTDocumentSnapshot::where('company_id', $companyId)
            ->where('document_type', 'DEBIT_NOTE')
            ->whereBetween('document_date', [$fromDate, $toDate]);

        if ($branchId) $dnQuery->where('branch_id', $branchId);

        $dnTax = floatval($dnQuery->sum('total_tax_amount'));
        $netEligibleItc = round(max(0, $totalInputTax - $dnTax), 2);

        // Net GST Liability = Net Output Tax - Net Eligible Input Tax
        $netLiability = round(max(0, $netOutputTax - $netEligibleItc), 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'output_tax' => [
                'taxable_value' => round($outTaxable, 2),
                'cgst' => round($outCgst, 2),
                'sgst' => round($outSgst, 2),
                'igst' => round($outIgst, 2),
                'cess' => round($outCess, 2),
                'total' => $totalOutputTax,
                'credit_note_reversals' => round($cnTax, 2),
                'net_output_tax' => $netOutputTax,
            ],
            'input_tax_credit' => [
                'taxable_value' => round($inTaxable, 2),
                'cgst' => round($inCgst, 2),
                'sgst' => round($inSgst, 2),
                'igst' => round($inIgst, 2),
                'cess' => round($inCess, 2),
                'total' => $totalInputTax,
                'debit_note_reversals' => round($dnTax, 2),
                'net_eligible_itc' => $netEligibleItc,
            ],
            'net_gst_liability' => $netLiability,
        ];
    }

    /**
     * Generate structured GSTR-1 preparation data
     */
    public static function getGSTR1Data(int $companyId, ?string $financialYear = null, ?string $period = null): array
    {
        $query = GSTDocumentSnapshot::where('company_id', $companyId)
            ->whereIn('document_type', ['INVOICE', 'CREDIT_NOTE']);

        $snapshots = $query->orderBy('document_date', 'asc')->get();

        $b2b = [];
        $b2cl = [];
        $b2cs = [];
        $cdnr = [];
        $hsnSummary = [];

        foreach ($snapshots as $snap) {
            $cat = strtoupper($snap->gst_category);
            $lines = $snap->lines;

            if ($snap->document_type === 'INVOICE') {
                if ($cat === 'B2B') {
                    $b2b[] = [
                        'gstin' => $snap->buyer_gstin,
                        'invoice_number' => $snap->document_number,
                        'invoice_date' => $snap->document_date,
                        'invoice_value' => $snap->total_document_value,
                        'place_of_supply' => $snap->place_of_supply,
                        'reverse_charge' => $snap->is_reverse_charge ? 'Y' : 'N',
                        'taxable_value' => $snap->taxable_amount,
                        'cgst' => $snap->cgst_amount,
                        'sgst' => $snap->sgst_amount,
                        'igst' => $snap->igst_amount,
                        'cess' => $snap->cess_amount,
                    ];
                } elseif ($cat === 'B2CL') {
                    $b2cl[] = [
                        'invoice_number' => $snap->document_number,
                        'invoice_date' => $snap->document_date,
                        'invoice_value' => $snap->total_document_value,
                        'place_of_supply' => $snap->place_of_supply,
                        'taxable_value' => $snap->taxable_amount,
                        'igst' => $snap->igst_amount,
                        'cess' => $snap->cess_amount,
                    ];
                } else {
                    $b2cs[] = [
                        'place_of_supply' => $snap->place_of_supply,
                        'taxable_value' => $snap->taxable_amount,
                        'cgst' => $snap->cgst_amount,
                        'sgst' => $snap->sgst_amount,
                        'igst' => $snap->igst_amount,
                        'cess' => $snap->cess_amount,
                    ];
                }

                // HSN summary
                foreach ($lines as $ln) {
                    $hsn = $ln['hsn_code'] ?? ($ln['hsn_sac'] ?? 'OTHER');
                    if (!isset($hsnSummary[$hsn])) {
                        $hsnSummary[$hsn] = [
                            'hsn_code' => $hsn,
                            'description' => $ln['product_name'] ?? $hsn,
                            'uqc' => 'NOS',
                            'total_quantity' => 0,
                            'total_value' => 0.0,
                            'taxable_value' => 0.0,
                            'cgst' => 0.0,
                            'sgst' => 0.0,
                            'igst' => 0.0,
                            'cess' => 0.0,
                        ];
                    }
                    $hsnSummary[$hsn]['total_quantity'] += floatval($ln['quantity'] ?? 1);
                    $hsnSummary[$hsn]['total_value'] += floatval($ln['total_amount'] ?? 0);
                    $hsnSummary[$hsn]['taxable_value'] += floatval($ln['taxable_amount'] ?? 0);
                    $hsnSummary[$hsn]['cgst'] += floatval($ln['cgst_amount'] ?? 0);
                    $hsnSummary[$hsn]['sgst'] += floatval($ln['sgst_amount'] ?? 0);
                    $hsnSummary[$hsn]['igst'] += floatval($ln['igst_amount'] ?? 0);
                    $hsnSummary[$hsn]['cess'] += floatval($ln['cess_amount'] ?? 0);
                }
            } elseif ($snap->document_type === 'CREDIT_NOTE') {
                $cdnr[] = [
                    'gstin' => $snap->buyer_gstin,
                    'credit_note_number' => $snap->document_number,
                    'note_date' => $snap->document_date,
                    'note_value' => $snap->total_document_value,
                    'taxable_value' => $snap->taxable_amount,
                    'cgst' => $snap->cgst_amount,
                    'sgst' => $snap->sgst_amount,
                    'igst' => $snap->igst_amount,
                    'cess' => $snap->cess_amount,
                ];
            }
        }

        return [
            'financial_year' => $financialYear ?: '2026-27',
            'period' => $period ?: 'Current',
            'b2b' => [
                'count' => count($b2b),
                'taxable_value' => round(array_sum(array_column($b2b, 'taxable_value')), 2),
                'total_tax' => round(array_sum(array_column($b2b, 'cgst')) + array_sum(array_column($b2b, 'sgst')) + array_sum(array_column($b2b, 'igst')), 2),
                'records' => $b2b,
            ],
            'b2cl' => [
                'count' => count($b2cl),
                'taxable_value' => round(array_sum(array_column($b2cl, 'taxable_value')), 2),
                'total_tax' => round(array_sum(array_column($b2cl, 'igst')), 2),
                'records' => $b2cl,
            ],
            'b2cs' => [
                'count' => count($b2cs),
                'taxable_value' => round(array_sum(array_column($b2cs, 'taxable_value')), 2),
                'total_tax' => round(array_sum(array_column($b2cs, 'cgst')) + array_sum(array_column($b2cs, 'sgst')) + array_sum(array_column($b2cs, 'igst')), 2),
                'records' => $b2cs,
            ],
            'cdnr' => [
                'count' => count($cdnr),
                'taxable_value' => round(array_sum(array_column($cdnr, 'taxable_value')), 2),
                'total_tax' => round(array_sum(array_column($cdnr, 'cgst')) + array_sum(array_column($cdnr, 'sgst')) + array_sum(array_column($cdnr, 'igst')), 2),
                'records' => $cdnr,
            ],
            'hsn_summary' => array_values($hsnSummary),
        ];
    }

    /**
     * Generate structured GSTR-3B summary
     */
    public static function getGSTR3BData(int $companyId, ?string $financialYear = null, ?string $period = null): array
    {
        $dash = static::getDashboardSummary($companyId);

        return [
            'financial_year' => $financialYear ?: '2026-27',
            'period' => $period ?: 'Current',
            'table_3_1_outward_supplies' => [
                'taxable_value' => $dash['output_tax']['taxable_value'],
                'igst' => $dash['output_tax']['igst'],
                'cgst' => $dash['output_tax']['cgst'],
                'sgst' => $dash['output_tax']['sgst'],
                'cess' => $dash['output_tax']['cess'],
            ],
            'table_4_eligible_itc' => [
                'taxable_value' => $dash['input_tax_credit']['taxable_value'],
                'igst' => $dash['input_tax_credit']['igst'],
                'cgst' => $dash['input_tax_credit']['cgst'],
                'sgst' => $dash['input_tax_credit']['sgst'],
                'cess' => $dash['input_tax_credit']['cess'],
            ],
            'table_6_1_payment_of_tax' => [
                'total_tax_payable' => $dash['output_tax']['net_output_tax'],
                'itc_adjusted' => min($dash['output_tax']['net_output_tax'], $dash['input_tax_credit']['net_eligible_itc']),
                'net_tax_payable_in_cash' => $dash['net_gst_liability'],
            ],
        ];
    }

    /**
     * Get Rate-wise GST analysis
     */
    public static function getTaxRateAnalysis(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $rates = [0.0, 5.0, 12.0, 18.0, 28.0];
        $analysis = [];

        $snapshots = GSTDocumentSnapshot::where('company_id', $companyId)
            ->where('document_type', 'INVOICE')
            ->get();

        foreach ($rates as $r) {
            $analysis[$r] = [
                'rate' => $r,
                'transaction_count' => 0,
                'taxable_amount' => 0.0,
                'cgst' => 0.0,
                'sgst' => 0.0,
                'igst' => 0.0,
                'total_tax' => 0.0,
            ];
        }

        foreach ($snapshots as $snap) {
            $lines = $snap->lines;
            foreach ($lines as $ln) {
                $r = floatval($ln['gst_rate'] ?? 18.0);
                if (!isset($analysis[$r])) {
                    $analysis[$r] = [
                        'rate' => $r,
                        'transaction_count' => 0,
                        'taxable_amount' => 0.0,
                        'cgst' => 0.0,
                        'sgst' => 0.0,
                        'igst' => 0.0,
                        'total_tax' => 0.0,
                    ];
                }
                $analysis[$r]['transaction_count']++;
                $analysis[$r]['taxable_amount'] += floatval($ln['taxable_amount'] ?? 0);
                $analysis[$r]['cgst'] += floatval($ln['cgst_amount'] ?? 0);
                $analysis[$r]['sgst'] += floatval($ln['sgst_amount'] ?? 0);
                $analysis[$r]['igst'] += floatval($ln['igst_amount'] ?? 0);
                $analysis[$r]['total_tax'] += floatval($ln['total_tax'] ?? 0);
            }
        }

        return array_values($analysis);
    }
}
