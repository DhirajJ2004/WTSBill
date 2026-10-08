<?php

namespace App\Banking\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Services\AccountService;
use InvalidArgumentException;

class BankAccountService
{
    /**
     * Create bank account with automatic chart of account linkage.
     */
    public static function createBankAccount(int $companyId, array $data, ?int $branchId = null): BankAccount
    {
        $accNumber = trim($data['account_number'] ?? '');
        if (empty($accNumber)) {
            throw new InvalidArgumentException("Bank account number is required.");
        }

        $bankName = trim($data['bank_name'] ?? '');
        $accountName = trim($data['account_name'] ?? "{$bankName} - {$accNumber}");
        $openingBalance = floatval($data['opening_balance'] ?? 0.0);

        // Ensure linked ledger account in Chart of Accounts
        $bankGroup = AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT');
        $ledgerAcc = ChartOfAccount::create([
            'company_id' => $companyId,
            'account_code' => 'BANK-' . substr(md5($accNumber . time()), 0, 6),
            'account_name' => "Bank: {$accountName}",
            'account_type' => 'ASSET',
            'parent_id' => $bankGroup?->parent_id ?: $bankGroup?->id,
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'bank_name' => $bankName,
            'account_name' => $accountName,
            'account_number' => $accNumber,
            'account_type' => $data['account_type'] ?? 'CURRENT',
            'ifsc_code' => $data['ifsc'] ?? ($data['ifsc_code'] ?? null),
            'ifsc' => $data['ifsc'] ?? ($data['ifsc_code'] ?? null),
            'branch_name' => $data['branch_name'] ?? null,
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'ledger_account_id' => $ledgerAcc->id,
            'is_active' => true,
            'is_primary' => (bool)($data['is_primary'] ?? false),
        ]);

        return $bankAccount;
    }

    /**
     * Get masked bank account display.
     */
    public static function getMaskedNumber(string $fullNumber): string
    {
        if (strlen($fullNumber) <= 4) return $fullNumber;
        return 'XXXX XXXX ' . substr($fullNumber, -4);
    }
}
