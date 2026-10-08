<?php

namespace App\Reporting\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentGatewayTransaction;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;
use Carbon\Carbon;

class PaymentReportService
{
    /**
     * Payment Summary Report (Received vs Paid)
     */
    public static function getPaymentSummary(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $payments = Payment::where('company_id', $companyId)
            ->whereDate('payment_date', '>=', $dates['start_date'])
            ->whereDate('payment_date', '<=', $dates['end_date'])
            ->get();

        $received = $payments->where('party_type', 'CUSTOMER');
        $made = $payments->where('party_type', 'SUPPLIER');

        $totalReceived = floatval($received->sum('amount'));
        $totalMade = floatval($made->sum('amount'));
        $netCashMovement = $totalReceived - $totalMade;

        return [
            'period' => $dates,
            'summary_kpis' => [
                'total_received' => $totalReceived,
                'total_received_count' => $received->count(),
                'total_made' => $totalMade,
                'total_made_count' => $made->count(),
                'net_cash_movement' => $netCashMovement,
            ],
            'rows' => $payments->map(fn($p) => [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'date' => is_object($p->payment_date) ? $p->payment_date->format('Y-m-d') : (string)$p->payment_date,
                'party_type' => $p->party_type,
                'party_name' => $p->customer?->name ?: ($p->supplier?->name ?: ($p->party_name ?: 'Party #' . $p->party_id)),
                'payment_mode' => $p->payment_mode,
                'reference_number' => $p->reference_number ?: '-',
                'amount' => floatval($p->amount),
                'allocated_amount' => floatval($p->allocated_amount),
                'unallocated_amount' => floatval($p->unallocated_amount),
                'status' => $p->status,
            ])->toArray(),
            'columns' => [
                ['key' => 'payment_number', 'label' => 'Payment #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'party_type', 'label' => 'Type', 'type' => 'badge'],
                ['key' => 'party_name', 'label' => 'Party Name', 'type' => 'text'],
                ['key' => 'payment_mode', 'label' => 'Mode', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Amount (₹)', 'type' => 'currency'],
                ['key' => 'allocated_amount', 'label' => 'Allocated (₹)', 'type' => 'currency'],
                ['key' => 'unallocated_amount', 'label' => 'Unallocated (₹)', 'type' => 'currency'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];
    }

    /**
     * Payment Mode Breakdown Report
     */
    public static function getPaymentModeReport(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $payments = Payment::where('company_id', $companyId)
            ->whereDate('payment_date', '>=', $dates['start_date'])
            ->whereDate('payment_date', '<=', $dates['end_date'])
            ->get();

        $modes = ['CASH' => 0.0, 'UPI' => 0.0, 'CARD' => 0.0, 'BANK_TRANSFER' => 0.0, 'CHEQUE' => 0.0, 'GATEWAY' => 0.0, 'OTHER' => 0.0];
        $counts = ['CASH' => 0, 'UPI' => 0, 'CARD' => 0, 'BANK_TRANSFER' => 0, 'CHEQUE' => 0, 'GATEWAY' => 0, 'OTHER' => 0];

        $totalAmt = 0.0;
        foreach ($payments as $p) {
            $m = strtoupper($p->payment_mode ?: 'CASH');
            if (!isset($modes[$m])) $m = 'OTHER';
            $modes[$m] += floatval($p->amount);
            $counts[$m]++;
            $totalAmt += floatval($p->amount);
        }

        $rows = [];
        foreach ($modes as $mKey => $amt) {
            if ($counts[$mKey] > 0 || $amt > 0) {
                $rows[] = [
                    'mode' => $mKey,
                    'count' => $counts[$mKey],
                    'amount' => $amt,
                    'percentage' => $totalAmt > 0 ? round(($amt / $totalAmt) * 100, 2) : 0.0,
                ];
            }
        }

        $chartItems = array_map(fn($r) => ['name' => $r['mode'], 'value' => $r['amount']], $rows);
        $chart = ReportChartService::buildDonutChart('Payment Modes Distribution', $chartItems);

        return [
            'period' => $dates,
            'summary_kpis' => ['total_payments' => $totalAmt, 'transaction_count' => $payments->count()],
            'charts' => ['mode_distribution' => $chart],
            'rows' => $rows,
            'columns' => [
                ['key' => 'mode', 'label' => 'Payment Mode', 'type' => 'text'],
                ['key' => 'count', 'label' => 'Transactions', 'type' => 'number'],
                ['key' => 'amount', 'label' => 'Total Amount (₹)', 'type' => 'currency'],
                ['key' => 'percentage', 'label' => 'Share %', 'type' => 'percentage'],
            ],
        ];
    }

    /**
     * Unallocated Payments Report
     */
    public static function getUnallocatedPayments(int $companyId, array $filters = []): array
    {
        $payments = Payment::where('company_id', $companyId)
            ->where('unallocated_amount', '>', 0)
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $rows = $payments->map(fn($p) => [
            'id' => $p->id,
            'payment_number' => $p->payment_number,
            'date' => is_object($p->payment_date) ? $p->payment_date->format('Y-m-d') : (string)$p->payment_date,
            'party_type' => $p->party_type,
            'party_name' => $p->customer?->name ?: ($p->supplier?->name ?: 'Party #' . $p->party_id),
            'total_amount' => floatval($p->amount),
            'unallocated_amount' => floatval($p->unallocated_amount),
            'payment_mode' => $p->payment_mode,
        ])->toArray();

        return [
            'summary_kpis' => [
                'unallocated_count' => count($rows),
                'total_unallocated_amount' => floatval($payments->sum('unallocated_amount')),
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'payment_number', 'label' => 'Payment #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'party_type', 'label' => 'Party Type', 'type' => 'badge'],
                ['key' => 'party_name', 'label' => 'Party Name', 'type' => 'text'],
                ['key' => 'total_amount', 'label' => 'Total Amount (₹)', 'type' => 'currency'],
                ['key' => 'unallocated_amount', 'label' => 'Unallocated Balance (₹)', 'type' => 'currency'],
                ['key' => 'payment_mode', 'label' => 'Mode', 'type' => 'text'],
            ],
        ];
    }
}
