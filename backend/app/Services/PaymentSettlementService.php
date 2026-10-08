<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\PaymentSettlement;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class PaymentSettlementService
{
    /**
     * Record gateway payout settlement to company bank account with fees accounting
     */
    public static function recordSettlement(
        int $companyId,
        string $settlementId,
        string $provider,
        string $settlementDate,
        float $grossAmount,
        float $feeAmount,
        float $taxAmount,
        int $bankAccountId,
        ?string $userName = 'System'
    ): PaymentSettlement {
        $grossAmount = round(abs(floatval($grossAmount)), 2);
        $feeAmount = round(abs(floatval($feeAmount)), 2);
        $taxAmount = round(abs(floatval($taxAmount)), 2);
        $netAmount = round($grossAmount - $feeAmount - $taxAmount, 2);

        if ($grossAmount <= 0 || $netAmount <= 0) {
            throw new Exception("Gross and Net settlement amounts must be greater than zero.");
        }

        return DB::transaction(function () use (
            $companyId, $settlementId, $provider, $settlementDate,
            $grossAmount, $feeAmount, $taxAmount, $netAmount, $bankAccountId, $userName
        ) {
            $bankAccount = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);

            // Double-entry accounting:
            // Debit Bank Account: Net Amount
            // Debit Payment Gateway Charges: Fee + Tax
            // Credit Payment Gateway Clearing: Gross Amount
            $journal = AccountingEventService::recordPaymentGatewaySettlementAccounting(
                companyId: $companyId,
                settlementId: $settlementId,
                provider: $provider,
                bankAccount: $bankAccount,
                grossAmount: $grossAmount,
                feeAmount: $feeAmount + $taxAmount,
                netAmount: $netAmount,
                date: $settlementDate,
                userName: $userName
            );

            // Record Bank Transaction for Net deposit
            $bankTxn = BankTransactionService::recordTransaction(
                companyId: $companyId,
                bankAccountId: $bankAccountId,
                debitCredit: 'CREDIT',
                amount: $netAmount,
                transactionType: 'OTHER',
                description: "Payment Gateway Settlement #{$settlementId} ({$provider})",
                referenceNo: $settlementId,
                transactionDate: $settlementDate,
                source: 'GATEWAY_SETTLEMENT',
                sourceId: $settlementId,
                branchId: $bankAccount->branch_id
            );

            $settlement = PaymentSettlement::create([
                'company_id' => $companyId,
                'settlement_id' => $settlementId,
                'provider' => strtoupper($provider),
                'settlement_date' => $settlementDate,
                'gross_amount' => $grossAmount,
                'fee_amount' => $feeAmount,
                'tax_amount' => $taxAmount,
                'net_amount' => $netAmount,
                'bank_account_id' => $bankAccountId,
                'bank_transaction_id' => $bankTxn->id,
                'status' => 'SETTLED',
            ]);

            AuditLogService::log(
                $companyId,
                'GATEWAY_SETTLEMENT_RECORDED',
                'PaymentSettlement',
                $settlement->id,
                "Settled ₹{$netAmount} from {$provider} into {$bankAccount->bank_name} (Gross: ₹{$grossAmount}, Fee: ₹" . ($feeAmount + $taxAmount) . ")",
                null,
                $settlement->toArray(),
                $userName
            );

            return $settlement;
        });
    }

    /**
     * List settlements
     */
    public static function listSettlements(int $companyId, array $filters = []): array
    {
        $query = PaymentSettlement::where('company_id', $companyId)->with('bankAccount');

        if (!empty($filters['provider'])) {
            $query->where('provider', strtoupper($filters['provider']));
        }
        if (!empty($filters['start_date'])) {
            $query->where('settlement_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('settlement_date', '<=', $filters['end_date']);
        }

        $items = $query->orderBy('settlement_date', 'desc')->get();

        return $items->map(function ($s) {
            return [
                'id' => $s->id,
                'settlement_id' => $s->settlement_id,
                'provider' => $s->provider,
                'settlement_date' => $s->settlement_date ? $s->settlement_date->format('Y-m-d') : null,
                'gross_amount' => floatval($s->gross_amount),
                'fee_amount' => floatval($s->fee_amount),
                'tax_amount' => floatval($s->tax_amount),
                'net_amount' => floatval($s->net_amount),
                'bank_account_id' => $s->bank_account_id,
                'bank_name' => $s->bankAccount ? $s->bankAccount->bank_name : 'Bank',
                'status' => $s->status,
                'created_at' => $s->created_at ? $s->created_at->toDateTimeString() : null,
            ];
        })->toArray();
    }
}
