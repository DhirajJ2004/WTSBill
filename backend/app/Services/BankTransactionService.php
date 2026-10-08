<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class BankTransactionService
{
    /**
     * Record a bank transaction in the database
     */
    public static function recordTransaction(
        int $companyId,
        int $bankAccountId,
        string $debitCredit, // DEBIT (Outflow), CREDIT (Inflow)
        float $amount,
        string $transactionType, // DEPOSIT, WITHDRAWAL, TRANSFER, BANK_CHARGE, INTEREST, PAYMENT_RECEIVED, PAYMENT_MADE, CHEQUE_DEPOSIT, CHEQUE_PAYMENT, OTHER
        string $description,
        ?string $referenceNo = null,
        ?string $transactionDate = null,
        ?string $valueDate = null,
        string $source = 'MANUAL',
        ?string $sourceId = null,
        ?int $branchId = null,
        bool $isReconciled = false
    ): BankTransaction {
        $amount = round(abs(floatval($amount)), 2);
        if ($amount <= 0) {
            throw new Exception("Transaction amount must be greater than zero.");
        }

        $date = $transactionDate ?: date('Y-m-d');
        $valDate = $valueDate ?: $date;
        $debitCredit = strtoupper($debitCredit);

        return DB::transaction(function () use (
            $companyId, $bankAccountId, $debitCredit, $amount, $transactionType,
            $description, $referenceNo, $date, $valDate, $source, $sourceId, $branchId, $isReconciled
        ) {
            $bankAccount = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);

            // Compute running balance
            $currentBal = floatval($bankAccount->current_balance);
            $newBal = ($debitCredit === 'CREDIT') ? ($currentBal + $amount) : ($currentBal - $amount);
            $bankAccount->current_balance = $newBal;
            $bankAccount->save();

            $txn = BankTransaction::create([
                'company_id' => $companyId,
                'branch_id' => $branchId ?: $bankAccount->branch_id,
                'bank_account_id' => $bankAccountId,
                'transaction_date' => $date,
                'value_date' => $valDate,
                'type' => $debitCredit,
                'debit_credit' => $debitCredit,
                'transaction_type' => strtoupper($transactionType),
                'reference_no' => $referenceNo,
                'reference_number' => $referenceNo,
                'description' => $description,
                'amount' => $amount,
                'balance_after' => $newBal,
                'source' => strtoupper($source),
                'source_id' => $sourceId ? (string)$sourceId : null,
                'is_reconciled' => $isReconciled,
                'reconciliation_status' => $isReconciled ? 'RECONCILED' : 'UNRECONCILED',
                'reconciled_at' => $isReconciled ? date('Y-m-d H:i:s') : null,
                'reconciled_by' => $isReconciled ? 'System' : null,
            ]);

            return $txn;
        });
    }

    /**
     * Record Bank Charge with automatic double-entry accounting
     */
    public static function recordBankCharge(
        int $companyId,
        int $bankAccountId,
        float $amount,
        string $description = 'Bank Service Charge',
        ?string $referenceNo = null,
        ?string $transactionDate = null,
        ?string $userName = 'System'
    ): BankTransaction {
        return DB::transaction(function () use ($companyId, $bankAccountId, $amount, $description, $referenceNo, $transactionDate, $userName) {
            $date = $transactionDate ?: date('Y-m-d');
            $bankAccount = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);

            // 1. Double-Entry Accounting: Debit Bank Charges Expense, Credit Bank
            $journal = AccountingEventService::recordBankChargeAccounting(
                $companyId,
                $bankAccount,
                $amount,
                $description,
                $referenceNo,
                $date,
                $userName
            );

            // 2. Bank Transaction
            $txn = static::recordTransaction(
                companyId: $companyId,
                bankAccountId: $bankAccountId,
                debitCredit: 'DEBIT',
                amount: $amount,
                transactionType: 'BANK_CHARGE',
                description: $description,
                referenceNo: $referenceNo,
                transactionDate: $date,
                source: 'MANUAL',
                sourceId: $journal ? (string)$journal->id : null,
                branchId: $bankAccount->branch_id
            );

            AuditLogService::log(
                $companyId,
                'BANK_CHARGE_RECORDED',
                'BankTransaction',
                $txn->id,
                "Recorded bank charge ₹{$amount} on {$bankAccount->bank_name}",
                null,
                $txn->toArray(),
                $userName
            );

            return $txn;
        });
    }

    /**
     * Record Bank Interest with automatic double-entry accounting
     */
    public static function recordBankInterest(
        int $companyId,
        int $bankAccountId,
        float $amount,
        string $description = 'Bank Interest Credit',
        ?string $referenceNo = null,
        ?string $transactionDate = null,
        ?string $userName = 'System'
    ): BankTransaction {
        return DB::transaction(function () use ($companyId, $bankAccountId, $amount, $description, $referenceNo, $transactionDate, $userName) {
            $date = $transactionDate ?: date('Y-m-d');
            $bankAccount = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);

            // 1. Double-Entry Accounting: Debit Bank, Credit Interest Income
            $journal = AccountingEventService::recordBankInterestAccounting(
                $companyId,
                $bankAccount,
                $amount,
                $description,
                $referenceNo,
                $date,
                $userName
            );

            // 2. Bank Transaction
            $txn = static::recordTransaction(
                companyId: $companyId,
                bankAccountId: $bankAccountId,
                debitCredit: 'CREDIT',
                amount: $amount,
                transactionType: 'INTEREST',
                description: $description,
                referenceNo: $referenceNo,
                transactionDate: $date,
                source: 'MANUAL',
                sourceId: $journal ? (string)$journal->id : null,
                branchId: $bankAccount->branch_id
            );

            AuditLogService::log(
                $companyId,
                'BANK_INTEREST_RECORDED',
                'BankTransaction',
                $txn->id,
                "Recorded bank interest income ₹{$amount} on {$bankAccount->bank_name}",
                null,
                $txn->toArray(),
                $userName
            );

            return $txn;
        });
    }

    /**
     * Exclude bank transaction from reconciliation with audit trail
     */
    public static function excludeTransaction(int $companyId, int $transactionId, string $reason, ?string $userName = 'System'): BankTransaction
    {
        $txn = BankTransaction::where('company_id', $companyId)->findOrFail($transactionId);
        $old = $txn->toArray();

        $txn->reconciliation_status = 'EXCLUDED';
        $txn->reconciled_by = $userName;
        $txn->reconciled_at = date('Y-m-d H:i:s');
        $txn->save();

        AuditLogService::log(
            $companyId,
            'BANK_TRANSACTION_EXCLUDED',
            'BankTransaction',
            $txn->id,
            "Excluded bank transaction #{$txn->id} from reconciliation: {$reason}",
            $old,
            $txn->toArray(),
            $userName
        );

        return $txn;
    }

    /**
     * Restore excluded bank transaction
     */
    public static function includeTransaction(int $companyId, int $transactionId, ?string $userName = 'System'): BankTransaction
    {
        $txn = BankTransaction::where('company_id', $companyId)->findOrFail($transactionId);
        $old = $txn->toArray();

        $txn->reconciliation_status = 'UNRECONCILED';
        $txn->reconciled_by = null;
        $txn->reconciled_at = null;
        $txn->save();

        AuditLogService::log(
            $companyId,
            'BANK_TRANSACTION_RESTORED',
            'BankTransaction',
            $txn->id,
            "Restored bank transaction #{$txn->id} to active reconciliation",
            $old,
            $txn->toArray(),
            $userName
        );

        return $txn;
    }

    /**
     * Get filtered list of bank transactions
     */
    public static function getTransactions(int $companyId, array $filters = []): array
    {
        $query = BankTransaction::where('company_id', $companyId)->with('bankAccount');

        if (!empty($filters['bank_account_id'])) {
            $query->where('bank_account_id', $filters['bank_account_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date']);
        }
        if (!empty($filters['type'])) {
            $query->where('debit_credit', strtoupper($filters['type']));
        }
        if (!empty($filters['transaction_type'])) {
            $query->where('transaction_type', strtoupper($filters['transaction_type']));
        }
        if (!empty($filters['reconciliation_status'])) {
            $query->where('reconciliation_status', strtoupper($filters['reconciliation_status']));
        }
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                  ->orWhere('reference_number', 'like', $search)
                  ->orWhere('reference_no', 'like', $search);
            });
        }

        $items = $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')->get();

        return $items->map(function ($txn) {
            return [
                'id' => $txn->id,
                'company_id' => $txn->company_id,
                'branch_id' => $txn->branch_id,
                'bank_account_id' => $txn->bank_account_id,
                'bank_name' => $txn->bankAccount ? $txn->bankAccount->bank_name : 'Bank',
                'account_name' => $txn->bankAccount ? $txn->bankAccount->account_name : '',
                'masked_account_number' => $txn->bankAccount ? $txn->bankAccount->masked_account_number : '',
                'transaction_date' => $txn->transaction_date ? $txn->transaction_date->format('Y-m-d') : null,
                'value_date' => $txn->value_date ? $txn->value_date->format('Y-m-d') : null,
                'type' => $txn->debit_credit,
                'debit_credit' => $txn->debit_credit,
                'transaction_type' => $txn->transaction_type,
                'reference_number' => $txn->reference_number ?: $txn->reference_no,
                'description' => $txn->description,
                'amount' => floatval($txn->amount),
                'balance_after' => floatval($txn->balance_after),
                'source' => $txn->source,
                'source_id' => $txn->source_id,
                'reconciliation_status' => $txn->reconciliation_status,
                'reconciled_at' => $txn->reconciled_at,
                'reconciled_by' => $txn->reconciled_by,
                'created_at' => $txn->created_at ? $txn->created_at->toDateTimeString() : null,
            ];
        })->toArray();
    }
}
