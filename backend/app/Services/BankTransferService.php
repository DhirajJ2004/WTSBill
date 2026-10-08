<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\ChartOfAccount;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class BankTransferService
{
    /**
     * Execute atomic funds transfer between accounts (Bank to Bank, Cash to Bank, Bank to Cash)
     */
    public static function executeTransfer(
        int $companyId,
        string $fromType, // BANK, CASH
        int $fromAccountId, // BankAccount id or ChartOfAccount id
        string $toType, // BANK, CASH
        int $toAccountId, // BankAccount id or ChartOfAccount id
        float $amount,
        ?string $transferDate = null,
        ?string $referenceNo = null,
        ?string $notes = null,
        ?int $branchId = null,
        ?string $userName = 'System'
    ): BankTransfer {
        $amount = round(abs(floatval($amount)), 2);
        if ($amount <= 0) {
            throw new Exception("Transfer amount must be greater than zero.");
        }

        $fromType = strtoupper($fromType);
        $toType = strtoupper($toType);

        if ($fromType === $toType && $fromAccountId === $toAccountId) {
            throw new Exception("Source and destination accounts cannot be identical.");
        }

        $date = $transferDate ?: date('Y-m-d');

        return DB::transaction(function () use (
            $companyId, $fromType, $fromAccountId, $toType, $toAccountId,
            $amount, $date, $referenceNo, $notes, $branchId, $userName
        ) {
            AccountService::ensureDefaultAccounts($companyId);

            // 1. Resolve From Account & Ledger Account
            $fromBank = null;
            $fromLedgerId = null;
            $fromName = '';

            if ($fromType === 'BANK') {
                $fromBank = BankAccount::where('company_id', $companyId)->findOrFail($fromAccountId);
                if (!$fromBank->ledger_account_id) {
                    $coa = ChartOfAccount::firstOrCreate([
                        'company_id' => $companyId,
                        'account_name' => $fromBank->bank_name . ' (' . substr($fromBank->account_number, -4) . ')',
                    ], [
                        'account_code' => 'BANK-' . $fromBank->id,
                        'account_type' => 'ASSET',
                        'sub_type' => 'CURRENT_ASSET',
                        'is_system' => false,
                        'is_active' => true,
                    ]);
                    $fromBank->ledger_account_id = $coa->id;
                    $fromBank->save();
                }
                $fromLedgerId = $fromBank->ledger_account_id;
                $fromName = "Bank ({$fromBank->bank_name})";
            } else {
                $fromCoa = ChartOfAccount::where('company_id', $companyId)->findOrFail($fromAccountId);
                $fromLedgerId = $fromCoa->id;
                $fromName = "Cash ({$fromCoa->account_name})";
            }

            // 2. Resolve To Account & Ledger Account
            $toBank = null;
            $toLedgerId = null;
            $toName = '';

            if ($toType === 'BANK') {
                $toBank = BankAccount::where('company_id', $companyId)->findOrFail($toAccountId);
                if (!$toBank->ledger_account_id) {
                    $coa = ChartOfAccount::firstOrCreate([
                        'company_id' => $companyId,
                        'account_name' => $toBank->bank_name . ' (' . substr($toBank->account_number, -4) . ')',
                    ], [
                        'account_code' => 'BANK-' . $toBank->id,
                        'account_type' => 'ASSET',
                        'sub_type' => 'CURRENT_ASSET',
                        'is_system' => false,
                        'is_active' => true,
                    ]);
                    $toBank->ledger_account_id = $coa->id;
                    $toBank->save();
                }
                $toLedgerId = $toBank->ledger_account_id;
                $toName = "Bank ({$toBank->bank_name})";
            } else {
                $toCoa = ChartOfAccount::where('company_id', $companyId)->findOrFail($toAccountId);
                $toLedgerId = $toCoa->id;
                $toName = "Cash ({$toCoa->account_name})";
            }

            if (!$fromLedgerId || !$toLedgerId) {
                throw new Exception("Unable to resolve ledger accounts for the transfer.");
            }

            // 3. Generate Transfer Number
            $transferNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId ?: 1, '2026-27', 'TRANSFER');
            if (empty($transferNumber)) {
                $count = BankTransfer::where('company_id', $companyId)->count() + 1;
                $transferNumber = 'TRF-' . str_pad((string)$count, 6, '0', STR_PAD_LEFT);
            }

            // 4. Double-Entry Accounting: Debit Destination Account, Credit Source Account
            $journal = AccountingEventService::recordBankTransferAccounting(
                companyId: $companyId,
                transferNumber: $transferNumber,
                fromLedgerId: $fromLedgerId,
                toLedgerId: $toLedgerId,
                amount: $amount,
                date: $date,
                referenceNo: $referenceNo,
                description: "Transfer from {$fromName} to {$toName}: " . ($notes ?: 'Internal Fund Transfer'),
                branchId: $branchId,
                userName: $userName
            );

            // 5. Create Bank Transfer Record
            $transfer = BankTransfer::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'transfer_number' => $transferNumber,
                'from_type' => $fromType,
                'from_account_id' => $fromAccountId,
                'to_type' => $toType,
                'to_account_id' => $toAccountId,
                'amount' => $amount,
                'transfer_date' => $date,
                'reference_number' => $referenceNo,
                'notes' => $notes,
                'journal_entry_id' => $journal ? $journal->id : null,
                'created_by' => $userName,
            ]);

            // 6. Record source side Bank Transaction (if from Bank)
            if ($fromBank) {
                BankTransactionService::recordTransaction(
                    companyId: $companyId,
                    bankAccountId: $fromBank->id,
                    debitCredit: 'DEBIT', // Outflow
                    amount: $amount,
                    transactionType: 'TRANSFER',
                    description: "Transfer to {$toName} (#{$transferNumber})",
                    referenceNo: $referenceNo,
                    transactionDate: $date,
                    source: 'TRANSFER',
                    sourceId: (string)$transfer->id,
                    branchId: $branchId
                );
            }

            // 7. Record destination side Bank Transaction (if to Bank)
            if ($toBank) {
                BankTransactionService::recordTransaction(
                    companyId: $companyId,
                    bankAccountId: $toBank->id,
                    debitCredit: 'CREDIT', // Inflow
                    amount: $amount,
                    transactionType: 'TRANSFER',
                    description: "Transfer received from {$fromName} (#{$transferNumber})",
                    referenceNo: $referenceNo,
                    transactionDate: $date,
                    source: 'TRANSFER',
                    sourceId: (string)$transfer->id,
                    branchId: $branchId
                );
            }

            AuditLogService::log(
                $companyId,
                'BANK_TRANSFER_EXECUTED',
                'BankTransfer',
                $transfer->id,
                "Transferred ₹{$amount} from {$fromName} to {$toName} (#{$transferNumber})",
                null,
                $transfer->toArray(),
                $userName
            );

            return $transfer;
        });
    }

    /**
     * Get list of bank transfers
     */
    public static function getTransfers(int $companyId, array $filters = []): array
    {
        $query = BankTransfer::where('company_id', $companyId)->with('journalEntry');

        if (!empty($filters['start_date'])) {
            $query->where('transfer_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('transfer_date', '<=', $filters['end_date']);
        }

        $items = $query->orderBy('transfer_date', 'desc')->orderBy('id', 'desc')->get();

        return $items->map(function ($trf) use ($companyId) {
            $fromName = '';
            if ($trf->from_type === 'BANK') {
                $b = BankAccount::where('company_id', $companyId)->find($trf->from_account_id);
                $fromName = $b ? $b->bank_name : 'Bank';
            } else {
                $c = ChartOfAccount::where('company_id', $companyId)->find($trf->from_account_id);
                $fromName = $c ? $c->account_name : 'Cash';
            }

            $toName = '';
            if ($trf->to_type === 'BANK') {
                $b = BankAccount::where('company_id', $companyId)->find($trf->to_account_id);
                $toName = $b ? $b->bank_name : 'Bank';
            } else {
                $c = ChartOfAccount::where('company_id', $companyId)->find($trf->to_account_id);
                $toName = $c ? $c->account_name : 'Cash';
            }

            return [
                'id' => $trf->id,
                'company_id' => $trf->company_id,
                'transfer_number' => $trf->transfer_number,
                'from_type' => $trf->from_type,
                'from_account_id' => $trf->from_account_id,
                'from_name' => $fromName,
                'to_type' => $trf->to_type,
                'to_account_id' => $trf->to_account_id,
                'to_name' => $toName,
                'amount' => floatval($trf->amount),
                'transfer_date' => $trf->transfer_date ? $trf->transfer_date->format('Y-m-d') : null,
                'reference_number' => $trf->reference_number,
                'notes' => $trf->notes,
                'journal_entry_id' => $trf->journal_entry_id,
                'created_by' => $trf->created_by,
                'created_at' => $trf->created_at ? $trf->created_at->toDateTimeString() : null,
            ];
        })->toArray();
    }

    /**
     * Create transfer from array payload
     */
    public static function createTransfer(int $companyId, array $data, ?string $userName = 'System'): array
    {
        $fromAccountId = intval($data['from_account_id'] ?? ($data['source_account_id'] ?? 0));
        $toAccountId = intval($data['to_account_id'] ?? ($data['destination_account_id'] ?? 0));
        $amount = floatval($data['amount'] ?? 0);
        $fromType = strtoupper($data['from_type'] ?? 'BANK');
        $toType = strtoupper($data['to_type'] ?? 'BANK');
        $date = $data['transfer_date'] ?? date('Y-m-d');
        $ref = $data['reference_number'] ?? ($data['reference_no'] ?? null);
        $notes = $data['notes'] ?? null;
        $branchId = $data['branch_id'] ?? null;

        $transfer = self::executeTransfer($companyId, $fromType, $fromAccountId, $toType, $toAccountId, $amount, $date, $ref, $notes, $branchId, $userName);

        return [
            'transfer' => $transfer,
        ];
    }
}
