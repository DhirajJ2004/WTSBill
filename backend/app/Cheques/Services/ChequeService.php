<?php

namespace App\Cheques\Services;

use App\Models\Cheque;
use App\Models\ChequeEvent;
use App\Models\Payment;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\Customer;
use App\Services\AccountingEventService;
use App\Services\JournalService;
use App\Services\AccountService;
use App\Payments\Allocations\PaymentAllocationService;
use Illuminate\Database\Capsule\Manager as DB;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

class ChequeService
{
    /**
     * Record customer cheque received.
     */
    public static function receiveCheque(int $companyId, array $data, ?string $userName = 'System'): Cheque
    {
        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException("Cheque amount must be greater than zero.");
        }

        $chequeNumber = trim($data['cheque_number'] ?? '');
        if (empty($chequeNumber)) {
            throw new InvalidArgumentException("Cheque number is required.");
        }

        return DB::transaction(function () use ($companyId, $amount, $chequeNumber, $data, $userName) {
            $partyId = intval($data['customer_id'] ?? ($data['party_id'] ?? 0));
            $customer = Customer::where('company_id', $companyId)->find($partyId);

            $cheque = Cheque::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'cheque_type' => 'RECEIVED',
                'cheque_number' => $chequeNumber,
                'cheque_date' => $data['cheque_date'] ?? date('Y-m-d'),
                'amount' => $amount,
                'party_type' => 'CUSTOMER',
                'party_id' => $partyId,
                'party_name' => $customer?->name ?: ($data['party_name'] ?? 'Customer'),
                'payee' => $data['payee'] ?? ($customer?->name ?: ($data['party_name'] ?? 'Company / Self')),
                'bank_name' => $data['bank_name'] ?? 'Bank',
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'received_date' => $data['received_date'] ?? date('Y-m-d'),
                'status' => 'RECEIVED',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userName,
            ]);

            ChequeEvent::create([
                'company_id' => $companyId,
                'cheque_id' => $cheque->id,
                'event_type' => 'RECEIVED',
                'to_status' => 'RECEIVED',
                'event_date' => date('Y-m-d'),
                'performed_by' => $userName,
                'created_by' => $userName,
                'details_json' => ['amount' => $amount, 'bank' => $cheque->bank_name],
            ]);

            return $cheque;
        });
    }

    /**
     * Deposit received cheque into business bank account.
     */
    public static function depositCheque(int $chequeId, int $bankAccountId, ?string $userName = 'System'): Cheque
    {
        return DB::transaction(function () use ($chequeId, $bankAccountId, $userName) {
            $cheque = Cheque::lockForUpdate()->findOrFail($chequeId);
            if ($cheque->status !== 'RECEIVED') {
                throw new RuntimeException("Cannot deposit cheque in status '{$cheque->status}'.");
            }

            $bank = BankAccount::findOrFail($bankAccountId);

            $cheque->update([
                'status' => 'DEPOSITED',
                'deposit_bank_id' => $bank->id,
                'bank_account_id' => $bank->id,
                'deposit_date' => date('Y-m-d'),
            ]);

            ChequeEvent::create([
                'company_id' => $cheque->company_id,
                'cheque_id' => $cheque->id,
                'event_type' => 'DEPOSITED',
                'to_status' => 'DEPOSITED',
                'event_date' => date('Y-m-d'),
                'performed_by' => $userName,
                'created_by' => $userName,
                'details_json' => ['bank_account' => $bank->account_name],
            ]);

            return $cheque;
        });
    }

    /**
     * Clear cheque (funds realized in bank account).
     */
    public static function clearCheque(int $chequeId, ?string $clearanceDate = null, ?string $userName = 'System'): Cheque
    {
        return DB::transaction(function () use ($chequeId, $clearanceDate, $userName) {
            $cheque = Cheque::lockForUpdate()->findOrFail($chequeId);
            if (!in_array($cheque->status, ['RECEIVED', 'DEPOSITED', 'PRESENTED'])) {
                throw new RuntimeException("Cannot clear cheque in status '{$cheque->status}'.");
            }

            $date = $clearanceDate ?: date('Y-m-d');
            $companyId = $cheque->company_id;

            $bankAccId = $cheque->bank_account_id ?: BankAccount::where('company_id', $companyId)->first()?->id;
            $bank = $bankAccId ? BankAccount::find($bankAccId) : null;

            // 1. Create Verified Payment record
            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $cheque->branch_id,
                'financial_year' => '2026-27',
                'payment_number' => 'REC-CHQ-' . date('Ymd') . '-' . $cheque->cheque_number,
                'receipt_number' => 'RCP-CHQ-' . date('Ymd') . '-' . $cheque->cheque_number,
                'payment_type' => 'RECEIPT',
                'payment_date' => $date,
                'party_type' => $cheque->party_type,
                'party_id' => $cheque->party_id,
                'amount' => $cheque->amount,
                'allocated_amount' => 0.00,
                'unallocated_amount' => $cheque->amount,
                'currency' => 'INR',
                'payment_mode' => 'CHEQUE',
                'cheque_number' => $cheque->cheque_number,
                'cheque_date' => $cheque->cheque_date,
                'cheque_bank' => $cheque->bank_name,
                'cheque_status' => 'CLEARED',
                'bank_account_id' => $bank?->id,
                'status' => 'POSTED',
                'created_by' => \App\Http\Middleware\AuthMiddleware::getUser()?->id ?? $cheque->created_by,
            ]);

            // Auto-allocate to unpaid customer invoices
            PaymentAllocationService::autoAllocate($payment, $userName);

            // 2. Post Accounting: Dr Bank Account, Cr Accounts Receivable
            AccountingEventService::recordPaymentAccounting($payment, $userName);

            // 3. Update Bank balance & transaction
            if ($bank) {
                $newBal = round($bank->current_balance + $cheque->amount, 2);
                $bank->update(['current_balance' => $newBal]);

                BankTransaction::create([
                    'company_id' => $companyId,
                    'branch_id' => $cheque->branch_id,
                    'bank_account_id' => $bank->id,
                    'transaction_date' => $date,
                    'type' => 'CREDIT',
                    'transaction_type' => 'CHEQUE_DEPOSIT',
                    'reference_number' => $cheque->cheque_number,
                    'description' => "Cheque #{$cheque->cheque_number} Cleared ({$cheque->party_name})",
                    'amount' => $cheque->amount,
                    'debit_credit' => 'CREDIT',
                    'balance_after' => $newBal,
                    'source' => 'CHEQUE',
                    'source_id' => $cheque->id,
                    'is_reconciled' => true,
                    'reconciliation_status' => 'RECONCILED',
                ]);
            }

            $cheque->update([
                'status' => 'CLEARED',
                'clearance_date' => $date,
                'payment_id' => $payment->id,
            ]);

            ChequeEvent::create([
                'company_id' => $companyId,
                'cheque_id' => $cheque->id,
                'event_type' => 'CLEARED',
                'to_status' => 'CLEARED',
                'event_date' => $date,
                'performed_by' => $userName,
                'created_by' => $userName,
                'details_json' => ['payment_id' => $payment->id, 'clearance_date' => $date],
            ]);

            return $cheque;
        });
    }

    /**
     * Bounce cheque (Reversal accounting, non-destructive audit trail).
     */
    public static function bounceCheque(int $chequeId, string $reason = 'Insufficient Funds', float $charges = 0.0, ?string $userName = 'System'): Cheque
    {
        return DB::transaction(function () use ($chequeId, $reason, $charges, $userName) {
            $cheque = Cheque::lockForUpdate()->findOrFail($chequeId);
            $companyId = $cheque->company_id;

            $cheque->update([
                'status' => 'BOUNCED',
                'bounce_date' => date('Y-m-d'),
                'bounce_reason' => $reason,
                'bounce_charges' => $charges,
            ]);

            // If it had a payment allocated, reverse the invoices and payment
            if ($cheque->payment_id) {
                $payment = Payment::with('allocations.invoice')->find($cheque->payment_id);
                if ($payment) {
                    foreach ($payment->allocations as $alloc) {
                        $inv = $alloc->invoice;
                        if ($inv) {
                            $newPaid = max(0.00, round(($inv->amount_paid ?? 0) - $alloc->allocated_amount, 2));
                            $newDue = round($inv->grand_total - $newPaid, 2);
                            $inv->update([
                                'amount_paid' => $newPaid,
                                'amount_due' => $newDue,
                                'status' => ($newPaid > 0) ? 'PARTIALLY_PAID' : 'UNPAID',
                            ]);
                        }
                    }
                    $payment->update(['status' => 'CANCELLED']);
                }
            }

            // Post Reversal Journal: Dr Accounts Receivable (Amount + Charges), Cr Bank Account (Amount), Cr Bank Charges (Charges)
            $recAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');
            $bankAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT');

            $lines = [
                [
                    'account_id' => $recAcc->id,
                    'debit' => round($cheque->amount + $charges, 2),
                    'credit' => 0.00,
                    'narration' => "Reversal of Bounced Cheque #{$cheque->cheque_number} + Charges",
                    'party_type' => 'CUSTOMER',
                    'party_id' => $cheque->party_id,
                ],
                [
                    'account_id' => $bankAcc->id,
                    'debit' => 0.00,
                    'credit' => $cheque->amount,
                    'narration' => "Reversal of Cheque Receipt #{$cheque->cheque_number}",
                ],
            ];

            if ($charges > 0) {
                $chargeIncomeAcc = AccountService::getMappedAccount($companyId, 'ROUND_OFF_GAIN'); // Bank penalty / recovery
                $lines[] = [
                    'account_id' => $chargeIncomeAcc->id,
                    'debit' => 0.00,
                    'credit' => $charges,
                    'narration' => 'Cheque Bounce Penalty Fee',
                ];
            }

            JournalService::createJournalEntry([
                'company_id' => $companyId,
                'financial_year' => '2026-27',
                'entry_date' => date('Y-m-d'),
                'entry_type' => 'MANUAL',
                'status' => 'POSTED',
                'description' => "Reversal for Bounced Cheque #{$cheque->cheque_number}: {$reason}",
                'narration' => "Cheque Bounce Audit",
                'lines' => $lines,
            ], $userName);

            ChequeEvent::create([
                'company_id' => $companyId,
                'cheque_id' => $cheque->id,
                'event_type' => 'BOUNCED',
                'to_status' => 'BOUNCED',
                'event_date' => date('Y-m-d'),
                'performed_by' => $userName,
                'created_by' => $userName,
                'details_json' => ['reason' => $reason, 'charges' => $charges],
            ]);

            return $cheque;
        });
    }

    /**
     * Issue supplier cheque.
     */
    public static function issueCheque(int $companyId, array $data, ?string $userName = 'System'): Cheque
    {
        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException("Cheque amount must be greater than zero.");
        }

        $chequeNumber = trim($data['cheque_number'] ?? '');
        if (empty($chequeNumber)) {
            throw new InvalidArgumentException("Cheque number is required.");
        }

        return Cheque::create([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'] ?? null,
            'cheque_type' => 'ISSUED',
            'cheque_number' => $chequeNumber,
            'cheque_date' => $data['cheque_date'] ?? date('Y-m-d'),
            'amount' => $amount,
            'party_type' => 'SUPPLIER',
            'party_id' => intval($data['supplier_id'] ?? ($data['party_id'] ?? 0)),
            'party_name' => $data['party_name'] ?? 'Supplier',
            'payee' => $data['payee'] ?? ($data['party_name'] ?? 'Supplier Payee'),
            'bank_name' => $data['bank_name'] ?? 'Bank',
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'issue_date' => $data['issue_date'] ?? date('Y-m-d'),
            'status' => 'ISSUED',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userName,
        ]);
    }
}
