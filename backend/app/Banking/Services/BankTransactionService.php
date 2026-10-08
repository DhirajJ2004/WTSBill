<?php

namespace App\Banking\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\AccountService;
use App\Services\JournalService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use RuntimeException;

class BankTransactionService
{
    /**
     * Inter-bank funds transfer (Bank A -> Bank B).
     */
    public static function transferFunds(
        int $companyId,
        int $sourceBankId,
        int $destBankId,
        float $amount,
        ?string $reference = null,
        ?string $date = null,
        ?string $userName = 'System'
    ): array {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException("Transfer amount must be greater than zero.");
        }
        if ($sourceBankId === $destBankId) {
            throw new InvalidArgumentException("Source and destination bank accounts must be different.");
        }

        $date = $date ?: date('Y-m-d');
        $ref = $reference ?: ('TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

        return DB::transaction(function () use ($companyId, $sourceBankId, $destBankId, $amount, $ref, $date, $userName) {
            $sourceBank = BankAccount::where('company_id', $companyId)->lockForUpdate()->findOrFail($sourceBankId);
            $destBank = BankAccount::where('company_id', $companyId)->lockForUpdate()->findOrFail($destBankId);

            // Update balances
            $sourceNewBal = round($sourceBank->current_balance - $amount, 2);
            $destNewBal = round($destBank->current_balance + $amount, 2);

            $sourceBank->update(['current_balance' => $sourceNewBal]);
            $destBank->update(['current_balance' => $destNewBal]);

            // Bank Transactions
            $txSource = BankTransaction::create([
                'company_id' => $companyId,
                'branch_id' => $sourceBank->branch_id,
                'bank_account_id' => $sourceBank->id,
                'transaction_date' => $date,
                'type' => 'DEBIT',
                'transaction_type' => 'TRANSFER',
                'reference_number' => $ref,
                'description' => "Funds transfer to {$destBank->account_name}",
                'amount' => $amount,
                'debit_credit' => 'DEBIT',
                'balance_after' => $sourceNewBal,
                'source' => 'TRANSFER',
                'is_reconciled' => false,
                'reconciliation_status' => 'UNRECONCILED',
            ]);

            $txDest = BankTransaction::create([
                'company_id' => $companyId,
                'branch_id' => $destBank->branch_id,
                'bank_account_id' => $destBank->id,
                'transaction_date' => $date,
                'type' => 'CREDIT',
                'transaction_type' => 'TRANSFER',
                'reference_number' => $ref,
                'description' => "Funds transfer from {$sourceBank->account_name}",
                'amount' => $amount,
                'debit_credit' => 'CREDIT',
                'balance_after' => $destNewBal,
                'source' => 'TRANSFER',
                'is_reconciled' => false,
                'reconciliation_status' => 'UNRECONCILED',
            ]);

            // Double-entry accounting: Dr Bank B, Cr Bank A
            $sourceLedgerId = $sourceBank->ledger_account_id ?: AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT')->id;
            $destLedgerId = $destBank->ledger_account_id ?: AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT')->id;

            $journal = JournalService::createJournalEntry([
                'company_id' => $companyId,
                'financial_year' => '2026-27',
                'entry_date' => $date,
                'entry_type' => 'CONTRA',
                'reference_type' => 'BANK_TRANSFER',
                'reference_id' => $ref,
                'description' => "Inter-bank Transfer: {$sourceBank->account_name} to {$destBank->account_name}",
                'narration' => "Ref #{$ref}",
                'status' => 'POSTED',
                'lines' => [
                    [
                        'account_id' => $destLedgerId,
                        'debit' => $amount,
                        'credit' => 0.00,
                        'narration' => "Debit to {$destBank->account_name}",
                    ],
                    [
                        'account_id' => $sourceLedgerId,
                        'debit' => 0.00,
                        'credit' => $amount,
                        'narration' => "Credit from {$sourceBank->account_name}",
                    ],
                ],
            ], $userName);

            return [
                'reference' => $ref,
                'source_transaction' => $txSource,
                'destination_transaction' => $txDest,
                'journal_entry' => $journal,
            ];
        });
    }

    /**
     * Cash Deposit (Cash -> Bank Account).
     */
    public static function cashDeposit(
        int $companyId,
        int $bankAccountId,
        float $amount,
        ?string $reference = null,
        ?string $date = null,
        ?string $userName = 'System'
    ): BankTransaction {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException("Deposit amount must be greater than zero.");
        }

        $date = $date ?: date('Y-m-d');
        $ref = $reference ?: ('DEP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

        return DB::transaction(function () use ($companyId, $bankAccountId, $amount, $ref, $date, $userName) {
            $bank = BankAccount::where('company_id', $companyId)->lockForUpdate()->findOrFail($bankAccountId);
            $newBal = round($bank->current_balance + $amount, 2);
            $bank->update(['current_balance' => $newBal]);

            $tx = BankTransaction::create([
                'company_id' => $companyId,
                'branch_id' => $bank->branch_id,
                'bank_account_id' => $bank->id,
                'transaction_date' => $date,
                'type' => 'CREDIT',
                'transaction_type' => 'DEPOSIT',
                'reference_number' => $ref,
                'description' => "Cash Deposit to {$bank->account_name}",
                'amount' => $amount,
                'debit_credit' => 'CREDIT',
                'balance_after' => $newBal,
                'source' => 'MANUAL',
                'is_reconciled' => false,
                'reconciliation_status' => 'UNRECONCILED',
            ]);

            // Dr Bank Account, Cr Cash Account
            $bankLedgerId = $bank->ledger_account_id ?: AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT')->id;
            $cashLedgerId = AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT')->id;

            JournalService::createJournalEntry([
                'company_id' => $companyId,
                'financial_year' => '2026-27',
                'entry_date' => $date,
                'entry_type' => 'CONTRA',
                'reference_type' => 'CASH_DEPOSIT',
                'reference_id' => $ref,
                'description' => "Cash Deposit into {$bank->account_name}",
                'narration' => "Ref #{$ref}",
                'status' => 'POSTED',
                'lines' => [
                    ['account_id' => $bankLedgerId, 'debit' => $amount, 'credit' => 0.00, 'narration' => 'Cash Deposit'],
                    ['account_id' => $cashLedgerId, 'debit' => 0.00, 'credit' => $amount, 'narration' => 'Cash with Business'],
                ],
            ], $userName);

            return $tx;
        });
    }

    /**
     * Cash Withdrawal (Bank Account -> Cash).
     */
    public static function cashWithdrawal(
        int $companyId,
        int $bankAccountId,
        float $amount,
        ?string $reference = null,
        ?string $date = null,
        ?string $userName = 'System'
    ): BankTransaction {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException("Withdrawal amount must be greater than zero.");
        }

        $date = $date ?: date('Y-m-d');
        $ref = $reference ?: ('WDL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

        return DB::transaction(function () use ($companyId, $bankAccountId, $amount, $ref, $date, $userName) {
            $bank = BankAccount::where('company_id', $companyId)->lockForUpdate()->findOrFail($bankAccountId);
            $newBal = round($bank->current_balance - $amount, 2);
            $bank->update(['current_balance' => $newBal]);

            $tx = BankTransaction::create([
                'company_id' => $companyId,
                'branch_id' => $bank->branch_id,
                'bank_account_id' => $bank->id,
                'transaction_date' => $date,
                'type' => 'DEBIT',
                'transaction_type' => 'WITHDRAWAL',
                'reference_number' => $ref,
                'description' => "Cash Withdrawal from {$bank->account_name}",
                'amount' => $amount,
                'debit_credit' => 'DEBIT',
                'balance_after' => $newBal,
                'source' => 'MANUAL',
                'is_reconciled' => false,
                'reconciliation_status' => 'UNRECONCILED',
            ]);

            // Dr Cash Account, Cr Bank Account
            $bankLedgerId = $bank->ledger_account_id ?: AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT')->id;
            $cashLedgerId = AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT')->id;

            JournalService::createJournalEntry([
                'company_id' => $companyId,
                'financial_year' => '2026-27',
                'entry_date' => $date,
                'entry_type' => 'CONTRA',
                'reference_type' => 'CASH_WITHDRAWAL',
                'reference_id' => $ref,
                'description' => "Cash Withdrawal from {$bank->account_name}",
                'narration' => "Ref #{$ref}",
                'status' => 'POSTED',
                'lines' => [
                    ['account_id' => $cashLedgerId, 'debit' => $amount, 'credit' => 0.00, 'narration' => 'Cash with Business'],
                    ['account_id' => $bankLedgerId, 'debit' => 0.00, 'credit' => $amount, 'narration' => 'Bank Withdrawal'],
                ],
            ], $userName);

            return $tx;
        });
    }
}
