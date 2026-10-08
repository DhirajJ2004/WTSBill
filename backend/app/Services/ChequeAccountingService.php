<?php

namespace App\Services;

use App\Models\Cheque;
use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Exception;

class ChequeAccountingService
{
    /**
     * Post double-entry accounting when a customer cheque is received
     * Debit: Undeposited Funds / Cheques in Hand
     * Credit: Accounts Receivable
     */
    public static function recordChequeReceivedAccounting(Cheque $cheque, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $cheque->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $undepositedAcc = AccountService::getMappedAccount($companyId, 'UNDEPOSITED_FUNDS');
        $recAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');
        $amount = round(floatval($cheque->amount), 2);

        $lines = [
            [
                'account_id' => $undepositedAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Cheque received #{$cheque->cheque_number} from {$cheque->party_name}",
                'party_type' => 'CUSTOMER',
                'party_id' => $cheque->party_id,
            ],
            [
                'account_id' => $recAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Credit to Accounts Receivable for Cheque #{$cheque->cheque_number}",
                'party_type' => 'CUSTOMER',
                'party_id' => $cheque->party_id,
            ]
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $cheque->branch_id,
            'financial_year' => '2026-27',
            'entry_date' => $cheque->received_date ? $cheque->received_date->format('Y-m-d') : date('Y-m-d'),
            'entry_type' => 'RECEIPT',
            'reference_type' => 'CHEQUE',
            'reference_id' => (string)$cheque->id,
            'description' => "Cheque Receipt #{$cheque->cheque_number} from {$cheque->party_name}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post double-entry accounting when a received cheque is deposited into a bank account
     * Debit: Bank Account
     * Credit: Undeposited Funds / Cheques in Hand
     */
    public static function recordChequeDepositAccounting(Cheque $cheque, BankAccount $depositBank, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $cheque->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $undepositedAcc = AccountService::getMappedAccount($companyId, 'UNDEPOSITED_FUNDS');
        $bankLedgerId = $depositBank->ledger_account_id;
        $amount = round(floatval($cheque->amount), 2);

        $lines = [
            [
                'account_id' => $bankLedgerId,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Cheque deposit #{$cheque->cheque_number} into {$depositBank->bank_name}",
            ],
            [
                'account_id' => $undepositedAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Clearing undeposited funds for deposited cheque #{$cheque->cheque_number}",
            ]
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $cheque->branch_id,
            'financial_year' => '2026-27',
            'entry_date' => $cheque->deposit_date ? $cheque->deposit_date->format('Y-m-d') : date('Y-m-d'),
            'entry_type' => 'TRANSFER',
            'reference_type' => 'CHEQUE_DEPOSIT',
            'reference_id' => (string)$cheque->id,
            'description' => "Cheque Deposit #{$cheque->cheque_number} to {$depositBank->bank_name}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post double-entry accounting when a supplier cheque is issued
     * Debit: Accounts Payable
     * Credit: Cheques Issued Clearing (or Bank Account)
     */
    public static function recordChequeIssuedAccounting(Cheque $cheque, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $cheque->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $payAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_PAYABLE');
        $issuedClearingAcc = AccountService::getMappedAccount($companyId, 'CHEQUES_ISSUED_CLEARING');
        $amount = round(floatval($cheque->amount), 2);

        $lines = [
            [
                'account_id' => $payAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Debit Accounts Payable for Issued Cheque #{$cheque->cheque_number} to {$cheque->party_name}",
                'party_type' => 'SUPPLIER',
                'party_id' => $cheque->party_id,
            ],
            [
                'account_id' => $issuedClearingAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Cheque issued clearing liability for #{$cheque->cheque_number}",
                'party_type' => 'SUPPLIER',
                'party_id' => $cheque->party_id,
            ]
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $cheque->branch_id,
            'financial_year' => '2026-27',
            'entry_date' => $cheque->issue_date ? $cheque->issue_date->format('Y-m-d') : date('Y-m-d'),
            'entry_type' => 'PAYMENT',
            'reference_type' => 'CHEQUE_ISSUED',
            'reference_id' => (string)$cheque->id,
            'description' => "Cheque Issued #{$cheque->cheque_number} to {$cheque->party_name}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post double-entry accounting when an issued cheque clears the bank
     * Debit: Cheques Issued Clearing
     * Credit: Bank Account
     */
    public static function recordIssuedChequeClearanceAccounting(Cheque $cheque, BankAccount $bankAccount, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $cheque->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $issuedClearingAcc = AccountService::getMappedAccount($companyId, 'CHEQUES_ISSUED_CLEARING');
        $bankLedgerId = $bankAccount->ledger_account_id;
        $amount = round(floatval($cheque->amount), 2);

        $lines = [
            [
                'account_id' => $issuedClearingAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Settlement of Cheque Issued Clearing for #{$cheque->cheque_number}",
            ],
            [
                'account_id' => $bankLedgerId,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Bank clearance for Cheque #{$cheque->cheque_number}",
            ]
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $cheque->branch_id,
            'financial_year' => '2026-27',
            'entry_date' => $cheque->clearance_date ? $cheque->clearance_date->format('Y-m-d') : date('Y-m-d'),
            'entry_type' => 'PAYMENT',
            'reference_type' => 'CHEQUE_CLEARANCE',
            'reference_id' => (string)$cheque->id,
            'description' => "Bank Clearance of Issued Cheque #{$cheque->cheque_number}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post double-entry accounting reversal when a received cheque bounces
     * Debit: Accounts Receivable (Customer)
     * Credit: Bank Account (if deposited) OR Undeposited Funds
     * Plus optional bank charges: Debit Bank Charges, Credit Bank Account
     */
    public static function recordReceivedChequeBounceAccounting(Cheque $cheque, float $bounceCharges = 0.0, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $cheque->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $recAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');
        $bankChargesAcc = AccountService::getMappedAccount($companyId, 'BANK_CHARGES');
        $amount = round(floatval($cheque->amount), 2);

        // Determine credit account: Bank Account if deposited, else Undeposited Funds
        $creditAccountId = null;
        if ($cheque->deposit_bank_id) {
            $depositBank = BankAccount::find($cheque->deposit_bank_id);
            $creditAccountId = $depositBank ? $depositBank->ledger_account_id : AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT')->id;
        } else {
            $creditAccountId = AccountService::getMappedAccount($companyId, 'UNDEPOSITED_FUNDS')->id;
        }

        $lines = [
            [
                'account_id' => $recAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Cheque Bounce Reversal: Restoring Receivable from {$cheque->party_name} for Cheque #{$cheque->cheque_number}",
                'party_type' => 'CUSTOMER',
                'party_id' => $cheque->party_id,
            ],
            [
                'account_id' => $creditAccountId,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Reversal of Cheque Receipt #{$cheque->cheque_number} due to bounce",
            ]
        ];

        // If bounce charges occurred
        if ($bounceCharges > 0) {
            $lines[] = [
                'account_id' => $bankChargesAcc->id,
                'debit' => $bounceCharges,
                'credit' => 0.00,
                'description' => "Cheque Bounce Penalty/Charges on #{$cheque->cheque_number}",
            ];
            $lines[] = [
                'account_id' => $creditAccountId,
                'debit' => 0.00,
                'credit' => $bounceCharges,
                'description' => "Bank debit for Cheque Bounce charges on #{$cheque->cheque_number}",
            ];
        }

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $cheque->branch_id,
            'financial_year' => '2026-27',
            'entry_date' => $cheque->bounce_date ? $cheque->bounce_date->format('Y-m-d') : date('Y-m-d'),
            'entry_type' => 'REVERSAL',
            'reference_type' => 'CHEQUE_BOUNCE',
            'reference_id' => (string)$cheque->id,
            'description' => "Cheque Bounce Reversal #{$cheque->cheque_number}: {$cheque->bounce_reason}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }
}
