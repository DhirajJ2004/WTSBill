<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\PaymentRefund;
use App\Models\StockAdjustment;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use Illuminate\Database\Capsule\Manager as DB;

class AccountingEventService
{
    /**
     * Post Double-Entry Accounting for a Sales Invoice.
     */
    public static function recordSaleAccounting(Invoice $invoice, ?string $userName = 'System'): ?JournalEntry
    {
        if ($invoice->status === 'DRAFT' || $invoice->status === 'CANCELLED') {
            return null; // Only POSTED / PAID invoices generate accounting entries
        }

        $companyId = $invoice->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $recAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');
        $salesAcc = AccountService::getMappedAccount($companyId, 'SALES_REVENUE');
        $cgstAcc = AccountService::getMappedAccount($companyId, 'OUTPUT_CGST');
        $sgstAcc = AccountService::getMappedAccount($companyId, 'OUTPUT_SGST');
        $igstAcc = AccountService::getMappedAccount($companyId, 'OUTPUT_IGST');
        $roundGainAcc = AccountService::getMappedAccount($companyId, 'ROUND_OFF_GAIN');
        $roundLossAcc = AccountService::getMappedAccount($companyId, 'ROUND_OFF_LOSS');

        $grandTotal = round(floatval($invoice->grand_total), 2);
        $taxableAmount = round(floatval($invoice->taxable_amount ?: ($grandTotal - floatval($invoice->cgst_amount) - floatval($invoice->sgst_amount) - floatval($invoice->igst_amount))), 2);
        $cgst = round(floatval($invoice->cgst_amount ?? 0), 2);
        $sgst = round(floatval($invoice->sgst_amount ?? 0), 2);
        $igst = round(floatval($invoice->igst_amount ?? 0), 2);

        $lines = [];

        // 1. Debit Accounts Receivable
        $lines[] = [
            'account_id' => $recAcc->id,
            'debit' => $grandTotal,
            'credit' => 0.00,
            'description' => "Invoice #{$invoice->invoice_number} Receivable from Customer #{$invoice->customer_id}",
            'party_type' => 'CUSTOMER',
            'party_id' => $invoice->customer_id,
        ];

        // 2. Credit Sales Revenue
        if ($taxableAmount > 0) {
            $lines[] = [
                'account_id' => $salesAcc->id,
                'debit' => 0.00,
                'credit' => $taxableAmount,
                'description' => "Sales Revenue for Invoice #{$invoice->invoice_number}",
                'party_type' => 'CUSTOMER',
                'party_id' => $invoice->customer_id,
            ];
        }

        // 3. Credit Tax Liabilities
        if ($cgst > 0) {
            $lines[] = [
                'account_id' => $cgstAcc->id,
                'debit' => 0.00,
                'credit' => $cgst,
                'description' => "Output CGST on Invoice #{$invoice->invoice_number}",
                'tax_category' => 'CGST',
            ];
        }
        if ($sgst > 0) {
            $lines[] = [
                'account_id' => $sgstAcc->id,
                'debit' => 0.00,
                'credit' => $sgst,
                'description' => "Output SGST on Invoice #{$invoice->invoice_number}",
                'tax_category' => 'SGST',
            ];
        }
        if ($igst > 0) {
            $lines[] = [
                'account_id' => $igstAcc->id,
                'debit' => 0.00,
                'credit' => $igst,
                'description' => "Output IGST on Invoice #{$invoice->invoice_number}",
                'tax_category' => 'IGST',
            ];
        }

        // 4. Handle Rounding Difference
        $creditsTotal = $taxableAmount + $cgst + $sgst + $igst;
        $roundDiff = round($grandTotal - $creditsTotal, 2);

        if ($roundDiff > 0.001) {
            // Credit Round off gain
            $lines[] = [
                'account_id' => $roundGainAcc->id,
                'debit' => 0.00,
                'credit' => $roundDiff,
                'description' => "Round off adjustment for Invoice #{$invoice->invoice_number}",
            ];
        } elseif ($roundDiff < -0.001) {
            // Debit Round off loss
            $lines[] = [
                'account_id' => $roundLossAcc->id,
                'debit' => abs($roundDiff),
                'credit' => 0.00,
                'description' => "Round off adjustment for Invoice #{$invoice->invoice_number}",
            ];
        }

        $saleJournal = JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $invoice->branch_id,
            'financial_year' => $invoice->financial_year ?? '2026-27',
            'entry_date' => $invoice->invoice_date ?: date('Y-m-d'),
            'entry_type' => 'SALE',
            'reference_type' => 'INVOICE',
            'reference_id' => (string)$invoice->id,
            'description' => "Accounting journal for Tax Invoice #{$invoice->invoice_number}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);

        // Post INVENTORY SALE journal (Dr COGS / Cr Inventory)
        static::recordInventorySaleAccounting($invoice, $userName);

        return $saleJournal;
    }

    /**
     * Post Double-Entry Accounting for Inventory Sale (COGS & Inventory Asset Deduction).
     *
     * Double Entry:
     *   Dr Cost of Goods Sold (COGS)
     *   Cr Inventory Asset
     */
    public static function recordInventorySaleAccounting(Invoice $invoice, ?string $userName = 'System'): ?JournalEntry
    {
        if ($invoice->status === 'DRAFT' || $invoice->status === 'CANCELLED') {
            return null;
        }

        $companyId = $invoice->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $cogsTotal = 0.0;
        $stockMoves = StockMovement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('reference_type', ['INVOICE', 'SALES_INVOICE'])
            ->where('reference_id', $invoice->id)
            ->where('movement_type', 'SALE')
            ->where('direction', 'OUT')
            ->get();

        if ($stockMoves->isNotEmpty()) {
            foreach ($stockMoves as $sm) {
                $cost = floatval($sm->unit_cost ?: ($sm->product ? $sm->product->purchase_price : 0));
                $qty = abs(floatval($sm->quantity));
                $cogsTotal += ($cost * $qty);
            }
        } else {
            $invoice->loadMissing('items.product');
            foreach ($invoice->items as $item) {
                $prod = $item->product;
                if ($prod && strtoupper($prod->product_type ?? '') !== 'SERVICE') {
                    $cost = floatval($prod->purchase_price ?: 0);
                    $qty = floatval($item->quantity ?: 0);
                    $cogsTotal += ($cost * $qty);
                }
            }
        }
        $cogsTotal = round($cogsTotal, 2);

        if ($cogsTotal <= 0) {
            return null;
        }

        $cogsAcc = AccountService::getMappedAccount($companyId, 'COGS');
        $invAssetAcc = AccountService::getMappedAccount($companyId, 'INVENTORY_ASSET');

        $lines = [
            [
                'account_id' => $cogsAcc->id,
                'debit' => $cogsTotal,
                'credit' => 0.00,
                'description' => "Cost of Goods Sold for Tax Invoice #{$invoice->invoice_number}",
            ],
            [
                'account_id' => $invAssetAcc->id,
                'debit' => 0.00,
                'credit' => $cogsTotal,
                'description' => "Inventory Stock deduction for Tax Invoice #{$invoice->invoice_number}",
            ],
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $invoice->branch_id,
            'financial_year' => $invoice->financial_year ?? '2026-27',
            'entry_date' => $invoice->invoice_date ?: date('Y-m-d'),
            'entry_type' => 'INVENTORY_SALE',
            'reference_type' => 'INVOICE',
            'reference_id' => (string)$invoice->id,
            'description' => "COGS & Inventory deduction for Tax Invoice #{$invoice->invoice_number}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for a Purchase Bill.
     */
    public static function recordPurchaseAccounting(Purchase $purchase, ?string $userName = 'System'): ?JournalEntry
    {
        if ($purchase->status === 'DRAFT' || $purchase->status === 'CANCELLED') {
            return null;
        }

        $companyId = $purchase->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $payAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_PAYABLE');
        $invAssetAcc = AccountService::getMappedAccount($companyId, 'INVENTORY_ASSET');
        $inputCgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_CGST');
        $inputSgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_SGST');
        $inputIgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_IGST');

        $grandTotal = round(floatval($purchase->grand_total), 2);
        $taxableAmount = round(floatval($purchase->taxable_amount ?: ($grandTotal - floatval($purchase->cgst_amount) - floatval($purchase->sgst_amount) - floatval($purchase->igst_amount))), 2);
        $cgst = round(floatval($purchase->cgst_amount ?? 0), 2);
        $sgst = round(floatval($purchase->sgst_amount ?? 0), 2);
        $igst = round(floatval($purchase->igst_amount ?? 0), 2);

        $lines = [];

        // 1. Debit Inventory Asset
        if ($taxableAmount > 0) {
            $lines[] = [
                'account_id' => $invAssetAcc->id,
                'debit' => $taxableAmount,
                'credit' => 0.00,
                'description' => "Inventory Stock purchase on Bill #{$purchase->purchase_number}",
                'party_type' => 'SUPPLIER',
                'party_id' => $purchase->supplier_id,
            ];
        }

        // 2. Debit Input Tax
        if ($cgst > 0) {
            $lines[] = [
                'account_id' => $inputCgstAcc->id,
                'debit' => $cgst,
                'credit' => 0.00,
                'description' => "Input CGST credit on Bill #{$purchase->purchase_number}",
                'tax_category' => 'CGST',
            ];
        }
        if ($sgst > 0) {
            $lines[] = [
                'account_id' => $inputSgstAcc->id,
                'debit' => $sgst,
                'credit' => 0.00,
                'description' => "Input SGST credit on Bill #{$purchase->purchase_number}",
                'tax_category' => 'SGST',
            ];
        }
        if ($igst > 0) {
            $lines[] = [
                'account_id' => $inputIgstAcc->id,
                'debit' => $igst,
                'credit' => 0.00,
                'description' => "Input IGST credit on Bill #{$purchase->purchase_number}",
                'tax_category' => 'IGST',
            ];
        }

        // 3. Credit Accounts Payable
        $lines[] = [
            'account_id' => $payAcc->id,
            'debit' => 0.00,
            'credit' => $grandTotal,
            'description' => "Payable to Supplier #{$purchase->supplier_id} for Purchase Bill #{$purchase->purchase_number}",
            'party_type' => 'SUPPLIER',
            'party_id' => $purchase->supplier_id,
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $purchase->branch_id,
            'financial_year' => $purchase->financial_year ?? '2026-27',
            'entry_date' => $purchase->purchase_date ?: date('Y-m-d'),
            'entry_type' => 'PURCHASE',
            'reference_type' => 'PURCHASE_INVOICE',
            'reference_id' => (string)$purchase->id,
            'description' => "Accounting journal for Purchase Bill #{$purchase->purchase_number}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Payments & Receipts.
     */
    public static function recordPaymentAccounting(Payment $payment, ?string $userName = 'System'): ?JournalEntry
    {
        if ($payment->status !== 'POSTED') {
            return null;
        }

        $companyId = $payment->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        // Determine payment account (Cash or Bank)
        $moneyAcc = null;
        if ($payment->account_id) {
            $moneyAcc = ChartOfAccount::where('company_id', $companyId)->find($payment->account_id);
        }
        if (!$moneyAcc) {
            $mode = strtoupper($payment->payment_mode ?? 'CASH');
            $moneyAcc = in_array($mode, ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE'])
                ? AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT')
                : AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT');
        }

        $amount = round(floatval($payment->amount), 2);
        $lines = [];

        if ($payment->payment_type === 'RECEIPT') {
            // Customer Receipt: Debit Cash/Bank, Credit Accounts Receivable
            $recAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');

            $lines[] = [
                'account_id' => $moneyAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Payment received ({$payment->payment_mode}) - Ref: " . ($payment->transaction_reference ?: $payment->payment_number),
                'party_type' => 'CUSTOMER',
                'party_id' => $payment->party_id,
            ];
            $lines[] = [
                'account_id' => $recAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Credit to Accounts Receivable for Receipt #{$payment->payment_number}",
                'party_type' => 'CUSTOMER',
                'party_id' => $payment->party_id,
            ];
        } else {
            // Supplier Payment: Debit Accounts Payable, Credit Cash/Bank
            $payAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_PAYABLE');

            $lines[] = [
                'account_id' => $payAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Debit to Accounts Payable for Payment #{$payment->payment_number}",
                'party_type' => 'SUPPLIER',
                'party_id' => $payment->party_id,
            ];
            $lines[] = [
                'account_id' => $moneyAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Payment disbursed ({$payment->payment_mode}) - Ref: " . ($payment->transaction_reference ?: $payment->payment_number),
                'party_type' => 'SUPPLIER',
                'party_id' => $payment->party_id,
            ];
        }

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $payment->branch_id,
            'financial_year' => $payment->financial_year ?? '2026-27',
            'entry_date' => $payment->payment_date ?: date('Y-m-d'),
            'entry_type' => $payment->payment_type,
            'reference_type' => 'PAYMENT',
            'reference_id' => (string)$payment->id,
            'description' => "Accounting entry for {$payment->payment_type} #{$payment->payment_number}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Operating Expenses.
     */
    public static function recordExpenseAccounting(Expense $expense, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $expense->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $expenseAcc = null;
        if (!empty($expense->account_id)) {
            $expenseAcc = ChartOfAccount::where('company_id', $companyId)->find($expense->account_id);
        }
        if (!$expenseAcc) {
            $expenseAcc = AccountService::getMappedAccount($companyId, 'OPERATING_EXPENSES');
        }

        $paymentMode = strtoupper($expense->payment_mode ?? 'CASH');
        $isPayable = in_array($paymentMode, ['CREDIT', 'PAYABLE']) || (isset($expense->is_paid) && !$expense->is_paid);

        if ($isPayable) {
            $moneyAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_PAYABLE');
            $creditDesc = "Payable for Expense #{$expense->expense_number}";
        } elseif (in_array($paymentMode, ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE', 'NET_BANKING'])) {
            $moneyAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT');
            $creditDesc = "Paid via {$expense->payment_mode}";
        } else {
            $moneyAcc = AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT');
            $creditDesc = "Paid via Cash";
        }

        $amount = round(floatval($expense->amount), 2);
        $taxAmount = round(floatval($expense->tax_amount ?? 0), 2);
        $isItcEligible = !empty($expense->is_itc_eligible);

        $lines = [];
        if ($taxAmount > 0.001 && $isItcEligible && $amount > $taxAmount) {
            $netExpense = round($amount - $taxAmount, 2);
            $lines[] = [
                'account_id' => $expenseAcc->id,
                'debit' => $netExpense,
                'credit' => 0.00,
                'description' => "Expense: " . ($expense->title ?: $expense->category ?: 'Operating Expense'),
            ];

            // Determine if intra-state or inter-state GST
            $company = \App\Models\Company::find($companyId);
            $companyState = $company ? substr($company->gstin ?? '', 0, 2) : '27';
            $vendorState = !empty($expense->gstin) ? substr($expense->gstin, 0, 2) : $companyState;

            if ($companyState === $vendorState) {
                $halfTax = round($taxAmount / 2, 2);
                $sgstTax = round($taxAmount - $halfTax, 2);
                $cgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_CGST');
                $sgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_SGST');
                $lines[] = [
                    'account_id' => $cgstAcc->id,
                    'debit' => $halfTax,
                    'credit' => 0.00,
                    'description' => "Input CGST on Expense #{$expense->expense_number}",
                ];
                $lines[] = [
                    'account_id' => $sgstAcc->id,
                    'debit' => $sgstTax,
                    'credit' => 0.00,
                    'description' => "Input SGST on Expense #{$expense->expense_number}",
                ];
            } else {
                $igstAcc = AccountService::getMappedAccount($companyId, 'INPUT_IGST');
                $lines[] = [
                    'account_id' => $igstAcc->id,
                    'debit' => $taxAmount,
                    'credit' => 0.00,
                    'description' => "Input IGST on Expense #{$expense->expense_number}",
                ];
            }
        } else {
            $lines[] = [
                'account_id' => $expenseAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Expense: " . ($expense->title ?: $expense->category ?: 'Operating Expense'),
            ];
        }

        $lines[] = [
            'account_id' => $moneyAcc->id,
            'debit' => 0.00,
            'credit' => $amount,
            'description' => $creditDesc,
            'party_type' => $isPayable ? 'SUPPLIER' : null,
            'party_id' => ($isPayable && !empty($expense->supplier_id)) ? intval($expense->supplier_id) : null,
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $expense->branch_id,
            'financial_year' => $expense->financial_year ?? '2026-27',
            'entry_date' => $expense->expense_date ?: date('Y-m-d'),
            'entry_type' => 'EXPENSE',
            'reference_type' => 'EXPENSE',
            'reference_id' => (string)$expense->id,
            'description' => "Operating Expense: {$expense->title}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Customer Payment Refunds.
     */
    public static function recordPaymentRefundAccounting(\App\Models\PaymentRefund $refund, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $refund->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $payment = $refund->payment;
        $moneyAcc = in_array(strtoupper($refund->payment_mode ?? 'BANK_TRANSFER'), ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE'])
            ? AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT')
            : AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT');

        $recAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');
        $amount = round(floatval($refund->amount), 2);

        $lines = [
            [
                'account_id' => $recAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Customer Refund for Payment #" . ($payment?->payment_number ?: $refund->payment_id),
                'party_type' => 'CUSTOMER',
                'party_id' => $payment?->party_id,
            ],
            [
                'account_id' => $moneyAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Disbursement for Refund #{$refund->refund_number}",
            ],
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $refund->branch_id,
            'financial_year' => '2026-27',
            'entry_date' => $refund->refund_date ? \Carbon\Carbon::parse($refund->refund_date)->toDateString() : date('Y-m-d'),
            'entry_type' => 'REFUND',
            'reference_type' => 'PAYMENT_REFUND',
            'reference_id' => (string)$refund->id,
            'description' => "Accounting for Refund #{$refund->refund_number}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Sales Returns / Credit Notes.
     */
    public static function recordSalesReturnAccounting(CreditNote $creditNote, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $creditNote->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $salesReturnAcc = AccountService::getMappedAccount($companyId, 'SALES_RETURN');
        $recAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_RECEIVABLE');
        $cgstAcc = AccountService::getMappedAccount($companyId, 'OUTPUT_CGST');
        $sgstAcc = AccountService::getMappedAccount($companyId, 'OUTPUT_SGST');
        $igstAcc = AccountService::getMappedAccount($companyId, 'OUTPUT_IGST');

        $total = round(floatval($creditNote->amount), 2);
        $taxable = round(floatval($creditNote->taxable_amount ?: $total), 2);
        $cgst = round(floatval($creditNote->cgst_amount ?? 0), 2);
        $sgst = round(floatval($creditNote->sgst_amount ?? 0), 2);
        $igst = round(floatval($creditNote->igst_amount ?? 0), 2);

        $lines = [];

        // Debit Sales Return
        $lines[] = [
            'account_id' => $salesReturnAcc->id,
            'debit' => $taxable,
            'credit' => 0.00,
            'description' => "Sales Return on Credit Note #{$creditNote->credit_note_number}",
            'party_type' => 'CUSTOMER',
            'party_id' => $creditNote->customer_id,
        ];

        // Debit Output GST Reversals
        if ($cgst > 0) {
            $lines[] = ['account_id' => $cgstAcc->id, 'debit' => $cgst, 'credit' => 0.00, 'description' => "CGST Reversal on CN #{$creditNote->credit_note_number}", 'tax_category' => 'CGST'];
        }
        if ($sgst > 0) {
            $lines[] = ['account_id' => $sgstAcc->id, 'debit' => $sgst, 'credit' => 0.00, 'description' => "SGST Reversal on CN #{$creditNote->credit_note_number}", 'tax_category' => 'SGST'];
        }
        if ($igst > 0) {
            $lines[] = ['account_id' => $igstAcc->id, 'debit' => $igst, 'credit' => 0.00, 'description' => "IGST Reversal on CN #{$creditNote->credit_note_number}", 'tax_category' => 'IGST'];
        }

        // Credit Accounts Receivable
        $lines[] = [
            'account_id' => $recAcc->id,
            'debit' => 0.00,
            'credit' => $total,
            'description' => "Receivable credit for Customer #{$creditNote->customer_id} on CN #{$creditNote->credit_note_number}",
            'party_type' => 'CUSTOMER',
            'party_id' => $creditNote->customer_id,
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $creditNote->branch_id,
            'financial_year' => $creditNote->financial_year ?? '2026-27',
            'entry_date' => $creditNote->credit_note_date ?: date('Y-m-d'),
            'entry_type' => 'SALES_RETURN',
            'reference_type' => 'CREDIT_NOTE',
            'reference_id' => (string)$creditNote->id,
            'description' => "Credit Note #{$creditNote->credit_note_number}: {$creditNote->reason}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Purchase Returns / Debit Notes.
     */
    public static function recordPurchaseReturnAccounting(DebitNote $debitNote, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $debitNote->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $payAcc = AccountService::getMappedAccount($companyId, 'ACCOUNTS_PAYABLE');
        $invAssetAcc = AccountService::getMappedAccount($companyId, 'INVENTORY_ASSET');
        $inputCgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_CGST');
        $inputSgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_SGST');
        $inputIgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_IGST');

        $total = round(floatval($debitNote->amount), 2);
        $taxable = round(floatval($debitNote->taxable_amount ?: $total), 2);
        $cgst = round(floatval($debitNote->cgst_amount ?? 0), 2);
        $sgst = round(floatval($debitNote->sgst_amount ?? 0), 2);
        $igst = round(floatval($debitNote->igst_amount ?? 0), 2);

        $lines = [];

        // Debit Accounts Payable
        $lines[] = [
            'account_id' => $payAcc->id,
            'debit' => $total,
            'credit' => 0.00,
            'description' => "Debit to Supplier #{$debitNote->supplier_id} for Debit Note #{$debitNote->debit_note_number}",
            'party_type' => 'SUPPLIER',
            'party_id' => $debitNote->supplier_id,
        ];

        // Credit Inventory Stock
        $lines[] = [
            'account_id' => $invAssetAcc->id,
            'debit' => 0.00,
            'credit' => $taxable,
            'description' => "Inventory Stock reversal for DN #{$debitNote->debit_note_number}",
            'party_type' => 'SUPPLIER',
            'party_id' => $debitNote->supplier_id,
        ];

        // Credit Input Tax Reversals
        if ($cgst > 0) {
            $lines[] = ['account_id' => $inputCgstAcc->id, 'debit' => 0.00, 'credit' => $cgst, 'description' => "Input CGST Reversal for DN #{$debitNote->debit_note_number}", 'tax_category' => 'CGST'];
        }
        if ($sgst > 0) {
            $lines[] = ['account_id' => $inputSgstAcc->id, 'debit' => 0.00, 'credit' => $sgst, 'description' => "Input SGST Reversal for DN #{$debitNote->debit_note_number}", 'tax_category' => 'SGST'];
        }
        if ($igst > 0) {
            $lines[] = ['account_id' => $inputIgstAcc->id, 'debit' => 0.00, 'credit' => $igst, 'description' => "Input IGST Reversal for DN #{$debitNote->debit_note_number}", 'tax_category' => 'IGST'];
        }

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $debitNote->branch_id,
            'financial_year' => $debitNote->financial_year ?? '2026-27',
            'entry_date' => $debitNote->debit_note_date ?: date('Y-m-d'),
            'entry_type' => 'PURCHASE_RETURN',
            'reference_type' => 'DEBIT_NOTE',
            'reference_id' => (string)$debitNote->id,
            'description' => "Debit Note #{$debitNote->debit_note_number}: {$debitNote->reason}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Payment Refunds.
     */
    public static function recordRefundAccounting(PaymentRefund $refund, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $refund->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $moneyAcc = in_array(strtoupper($refund->refund_mode ?? 'CASH'), ['BANK_TRANSFER', 'UPI', 'CARD', 'CHEQUE'])
            ? AccountService::getMappedAccount($companyId, 'DEFAULT_BANK_ACCOUNT')
            : AccountService::getMappedAccount($companyId, 'DEFAULT_CASH_ACCOUNT');

        $amount = round(floatval($refund->amount), 2);
        $lines = [];

        if ($refund->party_type === 'CUSTOMER') {
            // Customer Refund: Debit Customer Advance / Accounts Receivable, Credit Cash/Bank
            $custAdvanceAcc = AccountService::getMappedAccount($companyId, 'CUSTOMER_ADVANCE');

            $lines[] = [
                'account_id' => $custAdvanceAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Customer Refund issued to #{$refund->party_id} - Ref: {$refund->refund_number}",
                'party_type' => 'CUSTOMER',
                'party_id' => $refund->party_id,
            ];
            $lines[] = [
                'account_id' => $moneyAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Refund disbursed via {$refund->refund_mode}",
            ];
        } else {
            // Supplier Refund: Debit Cash/Bank, Credit Supplier Advance / Accounts Payable
            $supAdvanceAcc = AccountService::getMappedAccount($companyId, 'SUPPLIER_ADVANCE');

            $lines[] = [
                'account_id' => $moneyAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Supplier Refund received from #{$refund->party_id} via {$refund->refund_mode}",
            ];
            $lines[] = [
                'account_id' => $supAdvanceAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Credit for Supplier Refund #{$refund->refund_number}",
                'party_type' => 'SUPPLIER',
                'party_id' => $refund->party_id,
            ];
        }

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $refund->branch_id,
            'financial_year' => $refund->financial_year ?? '2026-27',
            'entry_date' => $refund->refund_date ?: date('Y-m-d'),
            'entry_type' => 'REFUND',
            'reference_type' => 'PAYMENT_REFUND',
            'reference_id' => (string)$refund->id,
            'description' => "Payment Refund #{$refund->refund_number}: {$refund->reason}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Stock Adjustments.
     */
    public static function recordStockAdjustmentAccounting(StockAdjustment $adj, ?string $userName = 'System'): ?JournalEntry
    {
        $companyId = $adj->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $invAssetAcc = AccountService::getMappedAccount($companyId, 'INVENTORY_ASSET');
        $invLossAcc = AccountService::getMappedAccount($companyId, 'INVENTORY_LOSS');
        $otherIncomeAcc = AccountService::getMappedAccount($companyId, 'OTHER_INCOME');

        $cost = round(floatval($adj->unit_cost ?: ($adj->product ? $adj->product->purchase_price : 0)) * abs(floatval($adj->quantity)), 2);
        if ($cost <= 0) return null;

        $lines = [];
        $type = strtoupper($adj->adjustment_type ?? 'DAMAGE');

        if (in_array($type, ['DAMAGE', 'LOSS', 'EXPIRED', 'THEFT', 'REDUCTION'])) {
            // Debit Inventory Loss, Credit Inventory Asset
            $lines[] = [
                'account_id' => $invLossAcc->id,
                'debit' => $cost,
                'credit' => 0.00,
                'description' => "Stock loss/damage ({$type}) for Product #{$adj->product_id}: {$adj->reason}",
                'product_id' => $adj->product_id,
            ];
            $lines[] = [
                'account_id' => $invAssetAcc->id,
                'debit' => 0.00,
                'credit' => $cost,
                'description' => "Inventory Stock reduction on Adjustment #{$adj->id}",
                'product_id' => $adj->product_id,
            ];
        } else {
            // Positive addition / stock correction
            $lines[] = [
                'account_id' => $invAssetAcc->id,
                'debit' => $cost,
                'credit' => 0.00,
                'description' => "Inventory stock addition on Adjustment #{$adj->id}",
                'product_id' => $adj->product_id,
            ];
            $lines[] = [
                'account_id' => $otherIncomeAcc->id,
                'debit' => 0.00,
                'credit' => $cost,
                'description' => "Stock correction gain ({$type}) on Adjustment #{$adj->id}",
                'product_id' => $adj->product_id,
            ];
        }

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $adj->branch_id,
            'financial_year' => '2026-27',
            'entry_date' => $adj->adjustment_date ?: date('Y-m-d'),
            'entry_type' => 'STOCK_ADJUSTMENT',
            'reference_type' => 'STOCK_ADJUSTMENT',
            'reference_id' => (string)$adj->id,
            'description' => "Stock Adjustment ({$type}): {$adj->reason}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Bank Transfers
     */
    public static function recordBankTransferAccounting(
        int $companyId,
        string $transferNumber,
        int $fromLedgerId,
        int $toLedgerId,
        float $amount,
        string $date,
        ?string $referenceNo = null,
        ?string $description = null,
        ?int $branchId = null,
        ?string $userName = 'System'
    ): ?JournalEntry {
        $amount = round(floatval($amount), 2);
        $lines = [
            [
                'account_id' => $toLedgerId,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Transfer Receipt: " . ($description ?: $transferNumber),
            ],
            [
                'account_id' => $fromLedgerId,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Transfer Outflow: " . ($description ?: $transferNumber),
            ]
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'financial_year' => '2026-27',
            'entry_date' => $date,
            'entry_type' => 'TRANSFER',
            'reference_type' => 'BANK_TRANSFER',
            'reference_id' => $transferNumber,
            'description' => $description ?: "Bank Transfer #{$transferNumber}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Bank Charges
     */
    public static function recordBankChargeAccounting(
        int $companyId,
        \App\Models\BankAccount $bankAccount,
        float $amount,
        string $description = 'Bank Charges',
        ?string $referenceNo = null,
        ?string $date = null,
        ?string $userName = 'System'
    ): ?JournalEntry {
        AccountService::ensureDefaultAccounts($companyId);
        $chargeAcc = AccountService::getMappedAccount($companyId, 'BANK_CHARGES');
        $bankLedgerId = $bankAccount->ledger_account_id;
        $amount = round(floatval($amount), 2);

        $lines = [
            [
                'account_id' => $chargeAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => $description,
            ],
            [
                'account_id' => $bankLedgerId,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Bank charge on {$bankAccount->bank_name}",
            ]
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $bankAccount->branch_id,
            'financial_year' => '2026-27',
            'entry_date' => $date ?: date('Y-m-d'),
            'entry_type' => 'EXPENSE',
            'reference_type' => 'BANK_CHARGE',
            'reference_id' => $referenceNo ?: (string)$bankAccount->id,
            'description' => "Bank Charge on {$bankAccount->bank_name}: {$description}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Bank Interest Income
     */
    public static function recordBankInterestAccounting(
        int $companyId,
        \App\Models\BankAccount $bankAccount,
        float $amount,
        string $description = 'Bank Interest Income',
        ?string $referenceNo = null,
        ?string $date = null,
        ?string $userName = 'System'
    ): ?JournalEntry {
        AccountService::ensureDefaultAccounts($companyId);
        $interestAcc = AccountService::getMappedAccount($companyId, 'INTEREST_INCOME');
        $bankLedgerId = $bankAccount->ledger_account_id;
        $amount = round(floatval($amount), 2);

        $lines = [
            [
                'account_id' => $bankLedgerId,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Bank interest credited to {$bankAccount->bank_name}",
            ],
            [
                'account_id' => $interestAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => $description,
            ]
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $bankAccount->branch_id,
            'financial_year' => '2026-27',
            'entry_date' => $date ?: date('Y-m-d'),
            'entry_type' => 'INCOME',
            'reference_type' => 'BANK_INTEREST',
            'reference_id' => $referenceNo ?: (string)$bankAccount->id,
            'description' => "Bank Interest on {$bankAccount->bank_name}: {$description}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for Payment Gateway Settlements
     */
    public static function recordPaymentGatewaySettlementAccounting(
        int $companyId,
        string $settlementId,
        string $provider,
        \App\Models\BankAccount $bankAccount,
        float $grossAmount,
        float $feeAmount,
        float $netAmount,
        string $date,
        ?string $userName = 'System'
    ): ?JournalEntry {
        AccountService::ensureDefaultAccounts($companyId);
        $clearingAcc = AccountService::getMappedAccount($companyId, 'PAYMENT_GATEWAY_CLEARING');
        $feeAcc = AccountService::getMappedAccount($companyId, 'PAYMENT_GATEWAY_CHARGES');
        $bankLedgerId = $bankAccount->ledger_account_id;

        $lines = [];

        // 1. Debit Bank Account for Net Settlement
        $lines[] = [
            'account_id' => $bankLedgerId,
            'debit' => $netAmount,
            'credit' => 0.00,
            'description' => "Net settlement payout from {$provider} (#{$settlementId})",
        ];

        // 2. Debit Payment Gateway Charges (if fee > 0)
        if ($feeAmount > 0) {
            $lines[] = [
                'account_id' => $feeAcc->id,
                'debit' => $feeAmount,
                'credit' => 0.00,
                'description' => "Payment gateway processing fee & GST on Settlement #{$settlementId}",
            ];
        }

        // 3. Credit Payment Gateway Clearing for Gross Amount
        $lines[] = [
            'account_id' => $clearingAcc->id,
            'debit' => 0.00,
            'credit' => $grossAmount,
            'description' => "Clearing gross settlement for {$provider} (#{$settlementId})",
        ];

        return JournalService::createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $bankAccount->branch_id,
            'financial_year' => '2026-27',
            'entry_date' => $date,
            'entry_type' => 'SETTLEMENT',
            'reference_type' => 'PAYMENT_SETTLEMENT',
            'reference_id' => $settlementId,
            'description' => "Gateway Settlement #{$settlementId} ({$provider}) to {$bankAccount->bank_name}",
            'is_system_generated' => true,
            'status' => 'POSTED',
            'lines' => $lines,
        ], $userName);
    }

    /**
     * Post Double-Entry Accounting for a Purchase Return (Debit Note to Supplier).
     *
     * Journal:
     *   Dr  Accounts Payable        (we reduce what we owe the supplier)
     *   Cr  Inventory Asset         (goods returned, stock reduced)
     *   Cr  Input CGST/SGST/IGST   (reverse the ITC we claimed)
     */
    public static function recordPurchaseReturnJournal(PurchaseReturn $ret, ?string $userName = 'System'): ?JournalEntry
    {
        if ($ret->status === 'CANCELLED') {
            return null;
        }

        $companyId = (int)$ret->company_id;
        AccountService::ensureDefaultAccounts($companyId);

        $payAcc      = AccountService::getMappedAccount($companyId, 'ACCOUNTS_PAYABLE');
        $invAssetAcc = AccountService::getMappedAccount($companyId, 'INVENTORY_ASSET');
        $inputCgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_CGST');
        $inputSgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_SGST');
        $inputIgstAcc = AccountService::getMappedAccount($companyId, 'INPUT_IGST');

        $grandTotal   = round(floatval($ret->grand_total), 2);
        $totalTax     = round(floatval($ret->total_tax ?? 0), 2);
        $taxableValue = round($grandTotal - $totalTax, 2);

        // Derive CGST / SGST / IGST from items
        $items = $ret->items ?? \App\Models\PurchaseReturnItem::where('purchase_return_id', $ret->id)->get();
        $cgst  = round($items->sum('cgst_amount'), 2);
        $sgst  = round($items->sum('sgst_amount'), 2);
        $igst  = round($items->sum('igst_amount'), 2);

        $lines = [];

        // 1. Debit Accounts Payable (reduce liability — we owe less)
        $lines[] = [
            'account_id'  => $payAcc->id,
            'debit'       => $grandTotal,
            'credit'      => 0.00,
            'description' => "Accounts Payable reduction for Purchase Return #{$ret->return_number}",
            'party_type'  => 'SUPPLIER',
            'party_id'    => $ret->supplier_id,
        ];

        // 2. Credit Inventory Asset (goods leave our warehouse)
        if ($taxableValue > 0) {
            $lines[] = [
                'account_id'  => $invAssetAcc->id,
                'debit'       => 0.00,
                'credit'      => $taxableValue,
                'description' => "Inventory reduction for Purchase Return #{$ret->return_number}",
                'party_type'  => 'SUPPLIER',
                'party_id'    => $ret->supplier_id,
            ];
        }

        // 3. Credit Input Tax (reverse ITC previously claimed)
        if ($cgst > 0) {
            $lines[] = [
                'account_id'   => $inputCgstAcc->id,
                'debit'        => 0.00,
                'credit'       => $cgst,
                'description'  => "Input CGST reversal for Purchase Return #{$ret->return_number}",
                'tax_category' => 'CGST',
            ];
        }
        if ($sgst > 0) {
            $lines[] = [
                'account_id'   => $inputSgstAcc->id,
                'debit'        => 0.00,
                'credit'       => $sgst,
                'description'  => "Input SGST reversal for Purchase Return #{$ret->return_number}",
                'tax_category' => 'SGST',
            ];
        }
        if ($igst > 0) {
            $lines[] = [
                'account_id'   => $inputIgstAcc->id,
                'debit'        => 0.00,
                'credit'       => $igst,
                'description'  => "Input IGST reversal for Purchase Return #{$ret->return_number}",
                'tax_category' => 'IGST',
            ];
        }

        return JournalService::createJournalEntry([
            'company_id'         => $companyId,
            'branch_id'          => $ret->branch_id,
            'financial_year'     => '2026-27',
            'entry_date'         => $ret->return_date ?: date('Y-m-d'),
            'entry_type'         => 'PURCHASE_RETURN',
            'reference_type'     => 'PURCHASE_RETURN',
            'reference_id'       => (string)$ret->id,
            'description'        => "Accounting journal for Purchase Return #{$ret->return_number}",
            'is_system_generated'=> true,
            'status'             => 'POSTED',
            'lines'              => $lines,
        ], $userName);
    }
}
