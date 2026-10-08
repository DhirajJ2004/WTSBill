<?php

namespace App\Services;

use App\Models\Cheque;
use App\Models\ChequeEvent;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class ChequeService
{
    /**
     * Record a received customer cheque
     */
    public static function createReceivedCheque(int $companyId, array $data, ?string $userName = 'System'): Cheque
    {
        return DB::transaction(function () use ($companyId, $data, $userName) {
            $chequeNo = trim($data['cheque_number'] ?? '');
            $amount = round(abs(floatval($data['amount'] ?? 0)), 2);
            $customerId = intval($data['customer_id'] ?? ($data['party_id'] ?? 0));
            $customerName = trim($data['customer_name'] ?? ($data['party_name'] ?? ''));

            if (empty($chequeNo) || $amount <= 0 || (!$customerId && empty($customerName))) {
                throw new Exception("Cheque number, amount and customer are required.");
            }

            if ($customerId && empty($customerName)) {
                $cust = Customer::where('company_id', $companyId)->find($customerId);
                if ($cust) $customerName = $cust->name;
            }

            $date = $data['cheque_date'] ?? date('Y-m-d');
            $recDate = $data['received_date'] ?? date('Y-m-d');

            $cheque = Cheque::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'cheque_type' => 'RECEIVED',
                'cheque_number' => $chequeNo,
                'cheque_date' => $date,
                'received_date' => $recDate,
                'amount' => $amount,
                'party_type' => 'CUSTOMER',
                'party_id' => $customerId ?: null,
                'party_name' => $customerName,
                'payee' => $customerName,
                'bank_name' => $data['bank_name'] ?? 'Customer Bank',
                'status' => 'RECEIVED',
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userName,
            ]);

            // Post accounting entry (Dr Undeposited Funds, Cr Accounts Receivable)
            $journal = ChequeAccountingService::recordChequeReceivedAccounting($cheque, $userName);
            if ($journal) {
                $cheque->journal_entry_id = $journal->id;
                $cheque->save();
            }

            // Record Cheque Event
            ChequeEvent::create([
                'company_id' => $companyId,
                'cheque_id' => $cheque->id,
                'from_status' => null,
                'to_status' => 'RECEIVED',
                'event_date' => $recDate,
                'notes' => "Cheque received from {$customerName}",
                'created_by' => $userName,
            ]);

            AuditLogService::log(
                $companyId,
                'CHEQUE_RECEIVED',
                'Cheque',
                $cheque->id,
                "Received customer cheque #{$chequeNo} for ₹{$amount} from {$customerName}",
                null,
                $cheque->toArray(),
                $userName
            );

            return $cheque;
        });
    }

    /**
     * Record an issued supplier cheque
     */
    public static function createIssuedCheque(int $companyId, array $data, ?string $userName = 'System'): Cheque
    {
        return DB::transaction(function () use ($companyId, $data, $userName) {
            $chequeNo = trim($data['cheque_number'] ?? '');
            $amount = round(abs(floatval($data['amount'] ?? 0)), 2);
            $supplierId = intval($data['supplier_id'] ?? ($data['party_id'] ?? 0));
            $supplierName = trim($data['supplier_name'] ?? ($data['party_name'] ?? ($data['payee'] ?? '')));
            $bankAccountId = intval($data['bank_account_id'] ?? 0);

            if (empty($chequeNo) || $amount <= 0 || (!$supplierId && empty($supplierName)) || !$bankAccountId) {
                throw new Exception("Cheque number, amount, payee and bank account are required for issued cheque.");
            }

            if ($supplierId && empty($supplierName)) {
                $sup = Supplier::where('company_id', $companyId)->find($supplierId);
                if ($sup) $supplierName = $sup->name;
            }

            $bankAccount = BankAccount::where('company_id', $companyId)->findOrFail($bankAccountId);

            $date = $data['cheque_date'] ?? date('Y-m-d');
            $issueDate = $data['issue_date'] ?? date('Y-m-d');

            $cheque = Cheque::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'cheque_type' => 'ISSUED',
                'cheque_number' => $chequeNo,
                'cheque_date' => $date,
                'issue_date' => $issueDate,
                'amount' => $amount,
                'party_type' => 'SUPPLIER',
                'party_id' => $supplierId ?: null,
                'party_name' => $supplierName,
                'payee' => $supplierName,
                'bank_name' => $bankAccount->bank_name,
                'bank_account_id' => $bankAccountId,
                'status' => 'ISSUED',
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userName,
            ]);

            // Post accounting entry (Dr Accounts Payable, Cr Cheques Issued Clearing)
            $journal = ChequeAccountingService::recordChequeIssuedAccounting($cheque, $userName);
            if ($journal) {
                $cheque->journal_entry_id = $journal->id;
                $cheque->save();
            }

            // Record Cheque Event
            ChequeEvent::create([
                'company_id' => $companyId,
                'cheque_id' => $cheque->id,
                'from_status' => null,
                'to_status' => 'ISSUED',
                'event_date' => $issueDate,
                'notes' => "Cheque issued to {$supplierName}",
                'created_by' => $userName,
            ]);

            AuditLogService::log(
                $companyId,
                'CHEQUE_ISSUED',
                'Cheque',
                $cheque->id,
                "Issued cheque #{$chequeNo} for ₹{$amount} to {$supplierName}",
                null,
                $cheque->toArray(),
                $userName
            );

            return $cheque;
        });
    }

    /**
     * Deposit received cheque into company bank account
     */
    public static function depositCheque(int $companyId, int $chequeId, int $depositBankId, ?string $depositDate = null, ?string $userName = 'System'): Cheque
    {
        return DB::transaction(function () use ($companyId, $chequeId, $depositBankId, $depositDate, $userName) {
            $cheque = Cheque::where('company_id', $companyId)->findOrFail($chequeId);
            if ($cheque->cheque_type !== 'RECEIVED') {
                throw new Exception("Only received cheques can be deposited.");
            }
            if ($cheque->status === 'CLEARED' || $cheque->status === 'CANCELLED') {
                throw new Exception("Cannot deposit cheque in {$cheque->status} status.");
            }

            $depositBank = BankAccount::where('company_id', $companyId)->findOrFail($depositBankId);
            $date = $depositDate ?: date('Y-m-d');
            $oldStatus = $cheque->status;

            $cheque->deposit_bank_id = $depositBankId;
            $cheque->deposit_date = $date;
            $cheque->status = 'DEPOSITED';
            $cheque->save();

            // Post double-entry accounting (Dr Bank Account, Cr Undeposited Funds)
            ChequeAccountingService::recordChequeDepositAccounting($cheque, $depositBank, $userName);

            // Record Bank Transaction
            BankTransactionService::recordTransaction(
                companyId: $companyId,
                bankAccountId: $depositBankId,
                debitCredit: 'CREDIT',
                amount: floatval($cheque->amount),
                transactionType: 'CHEQUE_DEPOSIT',
                description: "Cheque Deposit #{$cheque->cheque_number} from {$cheque->party_name}",
                referenceNo: $cheque->cheque_number,
                transactionDate: $date,
                source: 'CHEQUE',
                sourceId: (string)$cheque->id,
                branchId: $cheque->branch_id
            );

            ChequeEvent::create([
                'company_id' => $companyId,
                'cheque_id' => $cheque->id,
                'from_status' => $oldStatus,
                'to_status' => 'DEPOSITED',
                'event_date' => $date,
                'notes' => "Deposited in {$depositBank->bank_name}",
                'created_by' => $userName,
            ]);

            AuditLogService::log(
                $companyId,
                'CHEQUE_DEPOSITED',
                'Cheque',
                $cheque->id,
                "Deposited cheque #{$cheque->cheque_number} in {$depositBank->bank_name}",
                ['old_status' => $oldStatus],
                $cheque->toArray(),
                $userName
            );

            return $cheque;
        });
    }

    /**
     * Mark cheque as Cleared
     */
    public static function clearCheque(int $companyId, int $chequeId, ?string $clearanceDate = null, ?string $userName = 'System'): Cheque
    {
        return DB::transaction(function () use ($companyId, $chequeId, $clearanceDate, $userName) {
            $cheque = Cheque::where('company_id', $companyId)->findOrFail($chequeId);
            if ($cheque->status === 'CLEARED' || $cheque->status === 'BOUNCED' || $cheque->status === 'CANCELLED') {
                throw new Exception("Cannot clear cheque in {$cheque->status} status.");
            }

            $date = $clearanceDate ?: date('Y-m-d');
            $oldStatus = $cheque->status;

            if ($cheque->cheque_type === 'ISSUED') {
                $bankAccount = BankAccount::where('company_id', $companyId)->findOrFail($cheque->bank_account_id);
                // Post double-entry accounting (Dr Cheques Issued Clearing, Cr Bank Account)
                ChequeAccountingService::recordIssuedChequeClearanceAccounting($cheque, $bankAccount, $userName);

                // Record Bank Transaction
                BankTransactionService::recordTransaction(
                    companyId: $companyId,
                    bankAccountId: $bankAccount->id,
                    debitCredit: 'DEBIT',
                    amount: floatval($cheque->amount),
                    transactionType: 'CHEQUE_PAYMENT',
                    description: "Cheque Clearance #{$cheque->cheque_number} to {$cheque->party_name}",
                    referenceNo: $cheque->cheque_number,
                    transactionDate: $date,
                    source: 'CHEQUE',
                    sourceId: (string)$cheque->id,
                    branchId: $cheque->branch_id
                );
            }

            $cheque->status = 'CLEARED';
            $cheque->clearance_date = $date;
            $cheque->save();

            ChequeEvent::create([
                'company_id' => $companyId,
                'cheque_id' => $cheque->id,
                'from_status' => $oldStatus,
                'to_status' => 'CLEARED',
                'event_date' => $date,
                'notes' => "Cheque cleared successfully",
                'created_by' => $userName,
            ]);

            AuditLogService::log(
                $companyId,
                'CHEQUE_CLEARED',
                'Cheque',
                $cheque->id,
                "Cleared cheque #{$cheque->cheque_number}",
                ['old_status' => $oldStatus],
                $cheque->toArray(),
                $userName
            );

            return $cheque;
        });
    }

    /**
     * Mark cheque as Bounced
     */
    public static function bounceCheque(
        int $companyId,
        int $chequeId,
        string $bounceReason = 'Insufficient Funds',
        float $bounceCharges = 0.0,
        ?string $bounceDate = null,
        ?string $userName = 'System'
    ): Cheque {
        return DB::transaction(function () use (
            $companyId, $chequeId, $bounceReason, $bounceCharges, $bounceDate, $userName
        ) {
            $cheque = Cheque::where('company_id', $companyId)->findOrFail($chequeId);
            if ($cheque->status === 'CANCELLED') {
                throw new Exception("Cannot bounce a cancelled cheque.");
            }

            $date = $bounceDate ?: date('Y-m-d');
            $oldStatus = $cheque->status;

            $cheque->status = 'BOUNCED';
            $cheque->bounce_reason = $bounceReason;
            $cheque->bounce_charges = $bounceCharges;
            $cheque->bounce_date = $date;
            $cheque->save();

            // Post accounting reversal
            if ($cheque->cheque_type === 'RECEIVED') {
                ChequeAccountingService::recordReceivedChequeBounceAccounting($cheque, $bounceCharges, $userName);
            }

            ChequeEvent::create([
                'company_id' => $companyId,
                'cheque_id' => $cheque->id,
                'from_status' => $oldStatus,
                'to_status' => 'BOUNCED',
                'event_date' => $date,
                'notes' => "Cheque bounced: {$bounceReason} (Charges: ₹{$bounceCharges})",
                'created_by' => $userName,
            ]);

            AuditLogService::log(
                $companyId,
                'CHEQUE_BOUNCED',
                'Cheque',
                $cheque->id,
                "Bounced cheque #{$cheque->cheque_number}: {$bounceReason}",
                ['old_status' => $oldStatus],
                $cheque->toArray(),
                $userName
            );

            return $cheque;
        });
    }

    /**
     * Cancel cheque
     */
    public static function cancelCheque(int $companyId, int $chequeId, string $reason = 'Cancelled by user', ?string $userName = 'System'): Cheque
    {
        return DB::transaction(function () use ($companyId, $chequeId, $reason, $userName) {
            $cheque = Cheque::where('company_id', $companyId)->findOrFail($chequeId);
            if ($cheque->status === 'CLEARED') {
                throw new Exception("Cannot cancel an already cleared cheque.");
            }

            $oldStatus = $cheque->status;
            $cheque->status = 'CANCELLED';
            $cheque->notes = trim(($cheque->notes ?: '') . " [Cancelled: {$reason}]");
            $cheque->save();

            ChequeEvent::create([
                'company_id' => $companyId,
                'cheque_id' => $cheque->id,
                'from_status' => $oldStatus,
                'to_status' => 'CANCELLED',
                'event_date' => date('Y-m-d'),
                'notes' => "Cheque cancelled: {$reason}",
                'created_by' => $userName,
            ]);

            AuditLogService::log(
                $companyId,
                'CHEQUE_CANCELLED',
                'Cheque',
                $cheque->id,
                "Cancelled cheque #{$cheque->cheque_number}: {$reason}",
                ['old_status' => $oldStatus],
                $cheque->toArray(),
                $userName
            );

            return $cheque;
        });
    }

    /**
     * Get list of cheques with filter
     */
    public static function getCheques(int $companyId, array $filters = []): array
    {
        $query = Cheque::where('company_id', $companyId)->with(['bankAccount', 'depositBank', 'events']);

        if (!empty($filters['cheque_type'])) {
            $query->where('cheque_type', strtoupper($filters['cheque_type']));
        }
        if (!empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }
        if (!empty($filters['start_date'])) {
            $query->where('cheque_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('cheque_date', '<=', $filters['end_date']);
        }
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('cheque_number', 'like', $search)
                  ->orWhere('party_name', 'like', $search)
                  ->orWhere('payee', 'like', $search);
            });
        }

        $items = $query->orderBy('cheque_date', 'desc')->orderBy('id', 'desc')->get();

        return $items->map(function ($chq) {
            return [
                'id' => $chq->id,
                'company_id' => $chq->company_id,
                'branch_id' => $chq->branch_id,
                'cheque_type' => $chq->cheque_type,
                'cheque_number' => $chq->cheque_number,
                'cheque_date' => $chq->cheque_date ? $chq->cheque_date->format('Y-m-d') : null,
                'received_date' => $chq->received_date ? $chq->received_date->format('Y-m-d') : null,
                'issue_date' => $chq->issue_date ? $chq->issue_date->format('Y-m-d') : null,
                'deposit_date' => $chq->deposit_date ? $chq->deposit_date->format('Y-m-d') : null,
                'clearance_date' => $chq->clearance_date ? $chq->clearance_date->format('Y-m-d') : null,
                'bounce_date' => $chq->bounce_date ? $chq->bounce_date->format('Y-m-d') : null,
                'amount' => floatval($chq->amount),
                'party_type' => $chq->party_type,
                'party_id' => $chq->party_id,
                'party_name' => $chq->party_name ?: $chq->payee,
                'payee' => $chq->payee ?: $chq->party_name,
                'bank_name' => $chq->bank_name,
                'bank_account_id' => $chq->bank_account_id,
                'deposit_bank_id' => $chq->deposit_bank_id,
                'status' => $chq->status,
                'bounce_reason' => $chq->bounce_reason,
                'bounce_charges' => floatval($chq->bounce_charges),
                'reference_number' => $chq->reference_number,
                'notes' => $chq->notes,
                'created_by' => $chq->created_by,
                'events' => $chq->events ? $chq->events->toArray() : [],
                'created_at' => $chq->created_at ? $chq->created_at->toDateTimeString() : null,
            ];
        })->toArray();
    }

    /**
     * Alias for createReceivedCheque
     */
    public static function receiveCheque(int $companyId, array $data, ?string $userName = 'System'): Cheque
    {
        return self::createReceivedCheque($companyId, $data, $userName);
    }
}
