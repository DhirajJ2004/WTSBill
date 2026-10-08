<?php

namespace App\SystemAdmin\Services;

use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;

class DataIntegrityService
{
    /**
     * Run comprehensive data consistency checks across the business ledger.
     */
    public static function runIntegrityCheck(int $companyId): array
    {
        $accountingCheck = self::checkAccountingIntegrity($companyId);
        $inventoryCheck = self::checkInventoryIntegrity($companyId);
        $paymentCheck = self::checkPaymentIntegrity($companyId);
        $relationshipCheck = self::checkRelationshipIntegrity($companyId);
        $gstCheck = self::checkGstIntegrity($companyId);

        $isAllPassed = $accountingCheck['passed'] && $inventoryCheck['passed'] && $paymentCheck['passed'] && $relationshipCheck['passed'] && $gstCheck['passed'];

        return [
            'status' => $isAllPassed ? 'PASSED' : 'ISSUES_DETECTED',
            'timestamp' => date('c'),
            'accounting' => $accountingCheck,
            'inventory' => $inventoryCheck,
            'payments' => $paymentCheck,
            'relationships' => $relationshipCheck,
            'gst' => $gstCheck,
        ];
    }

    /**
     * Verify all posted journals are mathematically balanced (Debit = Credit).
     */
    public static function checkAccountingIntegrity(int $companyId): array
    {
        $journals = JournalEntry::where('company_id', $companyId)
            ->where('status', 'POSTED')
            ->get();

        $unbalanced = [];
        foreach ($journals as $j) {
            $diff = abs(round($j->total_debit - $j->total_credit, 2));
            if ($diff > 0.001) {
                $unbalanced[] = [
                    'journal_id' => $j->id,
                    'entry_number' => $j->entry_number,
                    'debit' => $j->total_debit,
                    'credit' => $j->total_credit,
                    'difference' => $diff,
                ];
            }
        }

        return [
            'passed' => empty($unbalanced),
            'total_journals_checked' => $journals->count(),
            'unbalanced_count' => count($unbalanced),
            'unbalanced_entries' => $unbalanced,
        ];
    }

    /**
     * Verify inventory stock equation matches current physical stock.
     */
    public static function checkInventoryIntegrity(int $companyId): array
    {
        $products = Product::where('company_id', $companyId)->get();
        $mismatches = [];

        foreach ($products as $p) {
            $movements = StockMovement::where('company_id', $companyId)
                ->where('product_id', $p->id)
                ->get();

            $calculated = 0.0;
            if ($movements->isNotEmpty()) {
                foreach ($movements as $m) {
                    if ($m->direction === 'IN' || $m->type === 'IN' || in_array($m->movement_type, ['PURCHASE', 'TRANSFER_IN', 'ADJUSTMENT_IN', 'OPENING_STOCK', 'RETURN_IN'])) {
                        $calculated += (float)$m->quantity;
                    } elseif ($m->direction === 'OUT' || $m->type === 'OUT' || in_array($m->movement_type, ['SALE', 'TRANSFER_OUT', 'ADJUSTMENT_OUT', 'RETURN_OUT'])) {
                        $calculated -= (float)$m->quantity;
                    }
                }
            } else {
                $calculated = (float)$p->opening_stock;
            }

            // Compare with current_stock
            if (abs($calculated - (float)$p->current_stock) > 0.001) {
                $mismatches[] = [
                    'product_id' => $p->id,
                    'product_name' => $p->name,
                    'sku' => $p->sku,
                    'current_stock' => $p->current_stock,
                    'calculated_stock' => $calculated,
                ];
            }
        }

        return [
            'passed' => empty($mismatches),
            'total_products_checked' => $products->count(),
            'mismatches_count' => count($mismatches),
            'mismatches' => $mismatches,
        ];
    }

    /**
     * Verify payment allocations and invoice non-negative balance invariants.
     */
    public static function checkPaymentIntegrity(int $companyId): array
    {
        $payments = Payment::with('allocations')->where('company_id', $companyId)->get();
        $invoices = Invoice::where('company_id', $companyId)->get();

        $paymentIssues = [];
        foreach ($payments as $pm) {
            $allocTotal = round($pm->allocations->sum('allocated_amount'), 2);
            if ($allocTotal > (round((float)$pm->amount, 2) + 0.001)) {
                $paymentIssues[] = [
                    'payment_id' => $pm->id,
                    'payment_number' => $pm->payment_number,
                    'payment_amount' => $pm->amount,
                    'allocated_total' => $allocTotal,
                    'issue' => 'Allocations exceed payment amount',
                ];
            }
        }

        $invoiceIssues = [];
        foreach ($invoices as $inv) {
            if ((float)$inv->amount_due < -0.001) {
                $invoiceIssues[] = [
                    'invoice_id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'amount_due' => $inv->amount_due,
                    'issue' => 'Negative balance due on invoice',
                ];
            }
        }

        return [
            'passed' => empty($paymentIssues) && empty($invoiceIssues),
            'payment_issues_count' => count($paymentIssues),
            'invoice_issues_count' => count($invoiceIssues),
            'payment_issues' => $paymentIssues,
            'invoice_issues' => $invoiceIssues,
        ];
    }

    /**
     * Verify referential integrity of foreign key relations.
     */
    public static function checkRelationshipIntegrity(int $companyId): array
    {
        $orphanInvoiceItems = InvoiceItem::whereHas('invoice', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->whereDoesntHave('product')->count();

        $orphanJournalLines = JournalLine::whereHas('journalEntry', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->whereDoesntHave('account')->count();

        $orphanInvoices = Invoice::where('company_id', $companyId)->whereDoesntHave('customer')->count();

        $isPassed = ($orphanInvoiceItems === 0) && ($orphanJournalLines === 0) && ($orphanInvoices === 0);

        return [
            'passed' => $isPassed,
            'orphan_invoice_items' => $orphanInvoiceItems,
            'orphan_journal_lines' => $orphanJournalLines,
            'orphan_invoices' => $orphanInvoices,
        ];
    }

    /**
     * Verify GST calculation consistency.
     */
    public static function checkGstIntegrity(int $companyId): array
    {
        $invoices = Invoice::with('items')->where('company_id', $companyId)->get();
        $discrepancies = [];

        foreach ($invoices as $inv) {
            $calcTax = round($inv->items->sum(function ($item) {
                return ($item->cgst_amount ?? 0) + ($item->sgst_amount ?? 0) + ($item->igst_amount ?? 0);
            }), 2);

            if ($inv->items->isNotEmpty() && abs($calcTax - (float)$inv->tax_total) > 0.05) {
                $discrepancies[] = [
                    'invoice_id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'stored_tax_total' => $inv->tax_total,
                    'calculated_tax_total' => $calcTax,
                ];
            }
        }

        return [
            'passed' => empty($discrepancies),
            'discrepancies_count' => count($discrepancies),
            'discrepancies' => $discrepancies,
        ];
    }
}
