<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Cheque;
use App\Models\ChartOfAccount;
use App\Models\AccountMapping;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class BankAccountService
{
    /**
     * Mask bank account number for UI security (e.g. XXXX XXXX 1234)
     */
    public static function maskAccountNumber(string $num): string
    {
        if (strlen($num) <= 4) {
            return $num;
        }
        $last4 = substr($num, -4);
        return 'XXXX XXXX ' . $last4;
    }

    /**
     * Get list of bank accounts for a company with real-time ledger balance and stats
     */
    public static function getBankAccounts(int $companyId, ?int $branchId = null): array
    {
        $query = BankAccount::where('company_id', $companyId)->where('is_active', true);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        $accounts = $query->orderBy('is_primary', 'desc')->orderBy('bank_name')->get();
        $result = [];

        foreach ($accounts as $acc) {
            $ledgerBal = static::getAccountLedgerBalance($acc);
            $unreconciledCount = BankTransaction::where('company_id', $companyId)
                ->where('bank_account_id', $acc->id)
                ->where('reconciliation_status', 'UNRECONCILED')
                ->count();

            $unreconciledAmount = floatval(BankTransaction::where('company_id', $companyId)
                ->where('bank_account_id', $acc->id)
                ->where('reconciliation_status', 'UNRECONCILED')
                ->sum(DB::raw("CASE WHEN debit_credit = 'CREDIT' THEN amount ELSE -amount END")));

            $reconciledBal = $ledgerBal - $unreconciledAmount;

            $pendingChequesCount = Cheque::where('company_id', $companyId)
                ->where(function ($q) use ($acc) {
                    $q->where('bank_account_id', $acc->id)
                      ->orWhere('deposit_bank_id', $acc->id);
                })
                ->whereIn('status', ['RECEIVED', 'PREPARED', 'ISSUED', 'DEPOSITED', 'PRESENTED'])
                ->count();

            $result[] = [
                'id' => $acc->id,
                'company_id' => $acc->company_id,
                'branch_id' => $acc->branch_id,
                'bank_name' => $acc->bank_name,
                'account_name' => $acc->account_name,
                'account_number' => $acc->account_number,
                'masked_account_number' => $acc->masked_account_number,
                'account_type' => $acc->account_type,
                'ifsc_code' => $acc->ifsc_code ?: $acc->ifsc,
                'branch_name' => $acc->branch_name,
                'currency' => $acc->currency ?: 'INR',
                'is_primary' => (bool)$acc->is_primary,
                'ledger_account_id' => $acc->ledger_account_id,
                'opening_balance' => floatval($acc->opening_balance),
                'opening_balance_date' => $acc->opening_balance_date,
                'current_balance' => $ledgerBal,
                'ledger_balance' => $ledgerBal,
                'reconciled_balance' => round($reconciledBal, 2),
                'unreconciled_amount' => round($unreconciledAmount, 2),
                'unreconciled_count' => $unreconciledCount,
                'pending_cheques_count' => $pendingChequesCount,
                'is_active' => (bool)$acc->is_active,
                'created_at' => $acc->created_at ? $acc->created_at->toDateTimeString() : null,
            ];
        }

        return $result;
    }

    /**
     * Get single bank account details
     */
    public static function getBankAccountById(int $companyId, int $accountId): ?array
    {
        $acc = BankAccount::where('company_id', $companyId)->find($accountId);
        if (!$acc) {
            return null;
        }

        $ledgerBal = static::getAccountLedgerBalance($acc);
        $unreconciledCount = BankTransaction::where('company_id', $companyId)
            ->where('bank_account_id', $acc->id)
            ->where('reconciliation_status', 'UNRECONCILED')
            ->count();

        $unreconciledAmount = floatval(BankTransaction::where('company_id', $companyId)
            ->where('bank_account_id', $acc->id)
            ->where('reconciliation_status', 'UNRECONCILED')
            ->sum(DB::raw("CASE WHEN debit_credit = 'CREDIT' THEN amount ELSE -amount END")));

        $reconciledBal = $ledgerBal - $unreconciledAmount;

        $pendingChequesCount = Cheque::where('company_id', $companyId)
            ->where(function ($q) use ($acc) {
                $q->where('bank_account_id', $acc->id)
                  ->orWhere('deposit_bank_id', $acc->id);
            })
            ->whereIn('status', ['RECEIVED', 'PREPARED', 'ISSUED', 'DEPOSITED', 'PRESENTED'])
            ->count();

        return [
            'id' => $acc->id,
            'company_id' => $acc->company_id,
            'branch_id' => $acc->branch_id,
            'bank_name' => $acc->bank_name,
            'account_name' => $acc->account_name,
            'account_number' => $acc->account_number,
            'masked_account_number' => $acc->masked_account_number,
            'account_type' => $acc->account_type,
            'ifsc_code' => $acc->ifsc_code ?: $acc->ifsc,
            'branch_name' => $acc->branch_name,
            'currency' => $acc->currency ?: 'INR',
            'is_primary' => (bool)$acc->is_primary,
            'ledger_account_id' => $acc->ledger_account_id,
            'opening_balance' => floatval($acc->opening_balance),
            'opening_balance_date' => $acc->opening_balance_date,
            'current_balance' => $ledgerBal,
            'ledger_balance' => $ledgerBal,
            'reconciled_balance' => round($reconciledBal, 2),
            'unreconciled_amount' => round($unreconciledAmount, 2),
            'unreconciled_count' => $unreconciledCount,
            'pending_cheques_count' => $pendingChequesCount,
            'is_active' => (bool)$acc->is_active,
            'created_at' => $acc->created_at ? $acc->created_at->toDateTimeString() : null,
        ];
    }

    /**
     * Create a new Bank Account and provision its Chart of Account mapping
     */
    public static function createBankAccount(int $companyId, array $data, ?string $userName = 'System'): BankAccount
    {
        return DB::transaction(function () use ($companyId, $data, $userName) {
            AccountService::ensureDefaultAccounts($companyId);

            $bankName = trim($data['bank_name'] ?? '');
            $accountName = trim($data['account_name'] ?? $bankName);
            $accountNumber = trim($data['account_number'] ?? '');
            $ifsc = strtoupper(trim($data['ifsc_code'] ?? ($data['ifsc'] ?? '')));
            $accountType = strtoupper(trim($data['account_type'] ?? 'CURRENT'));
            $openingBal = floatval($data['opening_balance'] ?? 0.0);
            $openingDate = $data['opening_balance_date'] ?? date('Y-m-d');
            $isPrimary = !empty($data['is_primary']);

            if (empty($bankName) || empty($accountNumber)) {
                throw new Exception("Bank name and account number are required.");
            }

            // If marked primary, unset other primaries
            if ($isPrimary) {
                BankAccount::where('company_id', $companyId)->update(['is_primary' => false]);
            } else {
                // If this is the first account, make it primary
                $count = BankAccount::where('company_id', $companyId)->count();
                if ($count === 0) {
                    $isPrimary = true;
                }
            }

            // Create Chart of Accounts entry for this bank
            $last4 = substr($accountNumber, -4);
            $coaCode = '11' . str_pad((string)(BankAccount::where('company_id', $companyId)->count() + 10), 2, '0', STR_PAD_LEFT);
            $coaName = "Bank - {$bankName} (..{$last4})";

            $coa = ChartOfAccount::create([
                'company_id' => $companyId,
                'account_code' => $coaCode,
                'account_name' => $coaName,
                'account_type' => 'ASSET',
                'account_subtype' => 'BANK',
                'nature' => 'DEBIT',
                'is_system_account' => false,
                'is_active' => true,
                'description' => "Bank Account Ledger for {$bankName} Account {$accountNumber}",
            ]);

            $bankAccount = BankAccount::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'bank_name' => $bankName,
                'account_name' => $accountName,
                'account_number' => $accountNumber,
                'account_type' => $accountType,
                'ifsc_code' => $ifsc,
                'ifsc' => $ifsc,
                'branch_name' => $data['branch_name'] ?? null,
                'opening_balance' => $openingBal,
                'opening_balance_date' => $openingDate,
                'ledger_account_id' => $coa->id,
                'currency' => $data['currency'] ?? 'INR',
                'current_balance' => $openingBal,
                'is_active' => true,
                'is_primary' => $isPrimary,
                'created_by' => $userName,
            ]);

            // If opening balance > 0, post Opening Balance journal
            if ($openingBal > 0) {
                $capitalAcc = AccountService::getMappedAccount($companyId, 'OWNER_CAPITAL');
                JournalService::createJournalEntry([
                    'company_id' => $companyId,
                    'branch_id' => $bankAccount->branch_id,
                    'financial_year' => '2026-27',
                    'entry_date' => $openingDate,
                    'entry_type' => 'OPENING_BALANCE',
                    'reference_type' => 'BANK_ACCOUNT',
                    'reference_id' => (string)$bankAccount->id,
                    'description' => "Opening balance for Bank Account {$bankName} ({$accountNumber})",
                    'is_system_generated' => true,
                    'status' => 'POSTED',
                    'lines' => [
                        [
                            'account_id' => $coa->id,
                            'debit' => $openingBal,
                            'credit' => 0.00,
                            'description' => "Opening debit balance in {$bankName}",
                        ],
                        [
                            'account_id' => $capitalAcc->id,
                            'debit' => 0.00,
                            'credit' => $openingBal,
                            'description' => "Owner equity / opening balance credit",
                        ]
                    ],
                ], $userName);
            }

            AuditLogService::log(
                $companyId,
                'BANK_ACCOUNT_CREATE',
                'BankAccount',
                $bankAccount->id,
                "Created bank account {$bankName} (masked: {$bankAccount->masked_account_number})",
                null,
                $bankAccount->toArray(),
                $userName
            );

            return $bankAccount;
        });
    }

    /**
     * Update Bank Account
     */
    public static function updateBankAccount(int $companyId, int $accountId, array $data, ?string $userName = 'System'): BankAccount
    {
        $acc = BankAccount::where('company_id', $companyId)->findOrFail($accountId);
        $old = $acc->toArray();

        if (isset($data['is_primary']) && $data['is_primary']) {
            BankAccount::where('company_id', $companyId)->where('id', '!=', $accountId)->update(['is_primary' => false]);
            $acc->is_primary = true;
        }

        if (isset($data['bank_name'])) $acc->bank_name = trim($data['bank_name']);
        if (isset($data['account_name'])) $acc->account_name = trim($data['account_name']);
        if (isset($data['account_type'])) $acc->account_type = strtoupper(trim($data['account_type']));
        if (isset($data['ifsc_code']) || isset($data['ifsc'])) {
            $ifsc = strtoupper(trim($data['ifsc_code'] ?? $data['ifsc']));
            $acc->ifsc_code = $ifsc;
            $acc->ifsc = $ifsc;
        }
        if (isset($data['branch_name'])) $acc->branch_name = trim($data['branch_name']);
        if (isset($data['is_active'])) $acc->is_active = (bool)$data['is_active'];

        $acc->save();

        // Also update mapped Chart of Account name if bank name changed
        if ($acc->ledger_account_id && isset($data['bank_name'])) {
            $coa = ChartOfAccount::find($acc->ledger_account_id);
            if ($coa) {
                $last4 = substr($acc->account_number, -4);
                $coa->account_name = "Bank - {$acc->bank_name} (..{$last4})";
                $coa->save();
            }
        }

        AuditLogService::log(
            $companyId,
            'BANK_ACCOUNT_UPDATE',
            'BankAccount',
            $acc->id,
            "Updated bank account {$acc->bank_name}",
            $old,
            $acc->toArray(),
            $userName
        );

        return $acc;
    }

    /**
     * Delete / Deactivate Bank Account
     */
    public static function deleteBankAccount(int $companyId, int $accountId, ?string $userName = 'System'): bool
    {
        $acc = BankAccount::where('company_id', $companyId)->findOrFail($accountId);
        $old = $acc->toArray();
        $acc->is_active = false;
        $acc->save();

        AuditLogService::log(
            $companyId,
            'BANK_ACCOUNT_DEACTIVATE',
            'BankAccount',
            $acc->id,
            "Deactivated bank account {$acc->bank_name}",
            $old,
            $acc->toArray(),
            $userName
        );

        return true;
    }

    /**
     * Get Cash Accounts for company
     */
    public static function getCashAccounts(int $companyId): array
    {
        AccountService::ensureDefaultAccounts($companyId);
        $cashCoas = ChartOfAccount::where('company_id', $companyId)
            ->where('account_type', 'ASSET')
            ->where('account_subtype', 'CASH')
            ->where('is_active', true)
            ->get();

        $result = [];
        foreach ($cashCoas as $coa) {
            $ledger = LedgerService::getAccountLedger($companyId, $coa->id);
            $result[] = [
                'id' => $coa->id,
                'account_code' => $coa->account_code,
                'account_name' => $coa->account_name,
                'current_balance' => floatval($ledger['closing_balance'] ?? 0.0),
            ];
        }

        return $result;
    }

    /**
     * Get Banking Dashboard Overview
     */
    public static function getBankingOverview(int $companyId, ?int $branchId = null): array
    {
        $accounts = static::getBankAccounts($companyId, $branchId);
        $cashAccounts = static::getCashAccounts($companyId);

        $totalBankBalance = 0.0;
        $totalReconciledBankBalance = 0.0;
        $totalUnreconciledAmount = 0.0;
        $totalUnreconciledCount = 0;
        $totalPendingChequesCount = 0;

        foreach ($accounts as $acc) {
            $totalBankBalance += $acc['ledger_balance'];
            $totalReconciledBankBalance += $acc['reconciled_balance'];
            $totalUnreconciledAmount += $acc['unreconciled_amount'];
            $totalUnreconciledCount += $acc['unreconciled_count'];
            $totalPendingChequesCount += $acc['pending_cheques_count'];
        }

        $totalCashBalance = 0.0;
        foreach ($cashAccounts as $cash) {
            $totalCashBalance += $cash['current_balance'];
        }

        $recentTransactions = BankTransaction::where('company_id', $companyId)
            ->with('bankAccount')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($txn) {
                return [
                    'id' => $txn->id,
                    'bank_account_id' => $txn->bank_account_id,
                    'bank_name' => $txn->bankAccount ? $txn->bankAccount->bank_name : 'Bank',
                    'transaction_date' => $txn->transaction_date ? $txn->transaction_date->format('Y-m-d') : null,
                    'type' => $txn->debit_credit,
                    'transaction_type' => $txn->transaction_type,
                    'description' => $txn->description,
                    'reference_number' => $txn->reference_number ?: $txn->reference_no,
                    'amount' => floatval($txn->amount),
                    'reconciliation_status' => $txn->reconciliation_status,
                ];
            });

        return [
            'total_bank_balance' => round($totalBankBalance, 2),
            'total_reconciled_bank_balance' => round($totalReconciledBankBalance, 2),
            'total_cash_balance' => round($totalCashBalance, 2),
            'total_liquid_funds' => round($totalBankBalance + $totalCashBalance, 2),
            'unreconciled_amount' => round($totalUnreconciledAmount, 2),
            'unreconciled_count' => $totalUnreconciledCount,
            'pending_cheques_count' => $totalPendingChequesCount,
            'bank_accounts' => $accounts,
            'cash_accounts' => $cashAccounts,
            'recent_transactions' => $recentTransactions,
        ];
    }

    /**
     * Helper to compute authoritative ledger balance from double-entry ledger
     */
    public static function getAccountLedgerBalance(BankAccount $account): float
    {
        if (!$account->ledger_account_id) {
            return floatval($account->opening_balance);
        }
        $ledger = LedgerService::getAccountLedger($account->company_id, $account->ledger_account_id);
        return floatval($ledger['closing_balance'] ?? $account->opening_balance);
    }
}
