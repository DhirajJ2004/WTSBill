<?php

namespace App\Reporting\Services;

use App\Services\BankingReportService;
use App\Services\BankAccountService;
use App\Services\ReceivableService;
use App\Services\PayableService;
use App\Services\AgingService;
use App\Models\Payment;
use App\Models\Cheque;
use App\Reporting\Filters\ReportFilterService;
use App\Reporting\Charts\ReportChartService;

class BankingReportServiceAdapter
{
    /**
     * Cash Book
     */
    public static function getCashBook(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $cb = BankingReportService::getCashBook($companyId, $dates['start_date'], $dates['end_date']);

        $chart = ReportChartService::buildBarChart(
            'Cash Inflow vs Outflow (₹)',
            ['Total Receipts (In)', 'Total Disbursements (Out)'],
            [['name' => 'Cash Flow (₹)', 'data' => [floatval($cb['total_receipts'] ?? 0), floatval($cb['total_payments'] ?? 0)], 'color' => '#059669']]
        );

        return [
            'period' => $dates,
            'summary_kpis' => [
                'opening_balance' => floatval($cb['opening_balance'] ?? 0),
                'total_receipts' => floatval($cb['total_receipts'] ?? 0),
                'total_payments' => floatval($cb['total_payments'] ?? 0),
                'closing_balance' => floatval($cb['closing_balance'] ?? 0),
            ],
            'charts' => ['cash_flow' => $chart],
            'rows' => $cb['entries'] ?? [],
            'columns' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'voucher_no', 'label' => 'Voucher / Ref #', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Particulars', 'type' => 'text'],
                ['key' => 'debit', 'label' => 'Receipt (In) ₹', 'type' => 'currency'],
                ['key' => 'credit', 'label' => 'Payment (Out) ₹', 'type' => 'currency'],
                ['key' => 'running_balance', 'label' => 'Running Balance (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Bank Book
     */
    public static function getBankBook(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);
        $bankAccountId = !empty($filters['bank_account_id']) ? intval($filters['bank_account_id']) : 0;

        if ($bankAccountId <= 0) {
            $firstBank = BankAccountService::getBankAccounts($companyId)->first();
            $bankAccountId = $firstBank ? $firstBank->id : 1;
        }

        $bb = BankingReportService::getBankBook($companyId, $bankAccountId, $dates['start_date'], $dates['end_date']);

        return [
            'period' => $dates,
            'summary_kpis' => [
                'account_name' => $bb['account_name'] ?? 'Bank Account',
                'opening_balance' => floatval($bb['opening_balance'] ?? 0),
                'total_deposits' => floatval($bb['total_deposits'] ?? 0),
                'total_withdrawals' => floatval($bb['total_withdrawals'] ?? 0),
                'closing_balance' => floatval($bb['closing_balance'] ?? 0),
            ],
            'rows' => $bb['entries'] ?? [],
            'columns' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'ref_no', 'label' => 'Ref / Cheque #', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Particulars', 'type' => 'text'],
                ['key' => 'deposit', 'label' => 'Deposit (₹)', 'type' => 'currency'],
                ['key' => 'withdrawal', 'label' => 'Withdrawal (₹)', 'type' => 'currency'],
                ['key' => 'running_balance', 'label' => 'Running Balance (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Cheque Register
     */
    public static function getChequeRegister(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $query = Cheque::where('company_id', $companyId)
            ->whereDate('cheque_date', '>=', $dates['start_date'])
            ->whereDate('cheque_date', '<=', $dates['end_date']);

        if (!empty($filters['cheque_type'])) {
            $query->where('type', $filters['cheque_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $cheques = $query->get();

        $rows = [];
        foreach ($cheques as $c) {
            $rows[] = [
                'cheque_id' => $c->id,
                'type' => $c->type, // RECEIVED, ISSUED
                'cheque_number' => $c->cheque_number,
                'date' => is_object($c->cheque_date) ? $c->cheque_date->format('Y-m-d') : (string)$c->cheque_date,
                'party_name' => $c->party_name ?: 'Party',
                'bank_name' => $c->bank_name ?: '-',
                'amount' => floatval($c->amount),
                'status' => $c->status, // RECEIVED, DEPOSITED, CLEARED, BOUNCED, CANCELLED
            ];
        }

        return [
            'period' => $dates,
            'summary_kpis' => [
                'total_cheques' => count($rows),
                'total_amount' => array_sum(array_column($rows, 'amount')),
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'cheque_number', 'label' => 'Cheque #', 'type' => 'text'],
                ['key' => 'type', 'label' => 'Direction', 'type' => 'badge'],
                ['key' => 'date', 'label' => 'Cheque Date', 'type' => 'date'],
                ['key' => 'party_name', 'label' => 'Party Name', 'type' => 'text'],
                ['key' => 'bank_name', 'label' => 'Bank Name', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Amount (₹)', 'type' => 'currency'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];
    }

    /**
     * Payments Received Register
     */
    public static function getPaymentsReceived(int $companyId, array $filters = []): array
    {
        $dates = ReportFilterService::resolveDateRange($filters);

        $payments = Payment::with('customer')
            ->where('company_id', $companyId)
            ->where('party_type', 'CUSTOMER')
            ->whereDate('payment_date', '>=', $dates['start_date'])
            ->whereDate('payment_date', '<=', $dates['end_date'])
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $rows = [];
        $totalAmt = 0.0;
        $modeCounts = [];

        foreach ($payments as $p) {
            $amt = floatval($p->amount);
            $totalAmt += $amt;
            $mode = $p->payment_mode ?: 'CASH';
            $modeCounts[$mode] = ($modeCounts[$mode] ?? 0) + $amt;

            $rows[] = [
                'payment_number' => $p->payment_number,
                'date' => is_object($p->payment_date) ? $p->payment_date->format('Y-m-d') : (string)$p->payment_date,
                'customer_name' => $p->customer?->name ?: 'Customer',
                'payment_mode' => $mode,
                'reference_number' => $p->reference_number ?: '-',
                'amount' => $amt,
                'allocated_amount' => floatval($p->allocated_amount),
                'unallocated_amount' => floatval($p->unallocated_amount),
            ];
        }

        $donutItems = [];
        foreach ($modeCounts as $m => $val) {
            $donutItems[] = ['name' => $m, 'value' => $val];
        }
        $donutChart = ReportChartService::buildDonutChart('Collections by Payment Mode', $donutItems);

        return [
            'period' => $dates,
            'summary_kpis' => [
                'receipts_count' => count($rows),
                'total_received' => $totalAmt,
            ],
            'charts' => [
                'payment_modes' => $donutChart,
            ],
            'rows' => $rows,
            'columns' => [
                ['key' => 'payment_number', 'label' => 'Receipt #', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'customer_name', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'payment_mode', 'label' => 'Mode', 'type' => 'badge'],
                ['key' => 'reference_number', 'label' => 'Ref / UTR #', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Amount (₹)', 'type' => 'currency'],
                ['key' => 'allocated_amount', 'label' => 'Settled (₹)', 'type' => 'currency'],
                ['key' => 'unallocated_amount', 'label' => 'Advance (₹)', 'type' => 'currency'],
            ],
        ];
    }

    /**
     * Receivables Aging Report
     */
    public static function getReceivablesAging(int $companyId, array $filters = []): array
    {
        $branchId = !empty($filters['branch_id']) ? intval($filters['branch_id']) : null;
        $aging = AgingService::getReceivablesAging($companyId, $branchId);
        $summary = $aging['summary'] ?? ($aging['totals'] ?? []);

        $labels = ['Not Due', '0–30 Days', '31–60 Days', '61–90 Days', '90+ Days'];
        $values = [
            floatval($summary['current'] ?? 0),
            floatval($summary['days_1_30'] ?? ($summary['bucket_1_30'] ?? 0)),
            floatval($summary['days_31_60'] ?? ($summary['bucket_31_60'] ?? 0)),
            floatval($summary['days_61_90'] ?? ($summary['bucket_61_90'] ?? 0)),
            floatval(($summary['days_91_180'] ?? 0) + ($summary['days_180_plus'] ?? ($summary['bucket_91_plus'] ?? 0))),
        ];

        $chart = ReportChartService::buildBarChart(
            'Receivables Aging Distribution (₹)',
            $labels,
            [['name' => 'Outstanding (₹)', 'data' => $values, 'color' => '#ef4444']]
        );

        return [
            'summary_kpis' => [
                'total_receivables' => floatval($summary['total_outstanding'] ?? 0),
            ],
            'charts' => ['receivables_aging' => $chart],
            'rows' => $aging['rows'] ?? [],
            'columns' => [
                ['key' => 'customer_name', 'label' => 'Customer Name', 'type' => 'text'],
                ['key' => 'current', 'label' => 'Not Due (₹)', 'type' => 'currency'],
                ['key' => 'days_1_30', 'label' => '0–30 Days (₹)', 'type' => 'currency'],
                ['key' => 'days_31_60', 'label' => '31–60 Days (₹)', 'type' => 'currency'],
                ['key' => 'days_61_90', 'label' => '61–90 Days (₹)', 'type' => 'currency'],
                ['key' => 'days_91_180', 'label' => '91–180 Days (₹)', 'type' => 'currency'],
                ['key' => 'days_180_plus', 'label' => '180+ Days (₹)', 'type' => 'currency'],
                ['key' => 'total_due', 'label' => 'Total Due (₹)', 'type' => 'currency'],
            ],
        ];
    }
}
