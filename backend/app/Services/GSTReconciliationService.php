<?php

namespace App\Services;

use App\Models\GSTReconciliation;
use App\Models\GSTReconciliationItem;
use App\Models\Purchase;
use App\Models\GSTDocumentSnapshot;

class GSTReconciliationService
{
    /**
     * Run systemic GSTR-2B reconciliation comparing Books Purchase snapshots vs Portal data.
     */
    public static function createReconciliation(int $companyId, string $financialYear, string $periodMonth, array $portalRecords, string $returnType = 'GSTR2B', ?string $userName = 'Admin'): GSTReconciliation
    {
        $recon = GSTReconciliation::create([
            'company_id' => $companyId,
            'financial_year' => $financialYear,
            'period_month' => $periodMonth,
            'return_type' => $returnType,
            'status' => 'IN_PROGRESS',
            'total_portal_records' => count($portalRecords),
            'imported_by' => $userName,
        ]);

        // 1. Fetch Books Purchase Snapshots
        $booksSnapshots = GSTDocumentSnapshot::where('company_id', $companyId)
            ->where('document_type', 'PURCHASE')
            ->get();

        $recon->update(['total_books_records' => $booksSnapshots->count()]);

        $matchedCount = 0;
        $partialCount = 0;
        $mismatchCount = 0;
        $booksOnlyCount = 0;
        $portalOnlyCount = 0;

        $processedBooksIds = [];

        // 2. Iterate Portal Records
        foreach ($portalRecords as $pRec) {
            $pGstin = strtoupper(trim($pRec['gstin'] ?? ''));
            $pInvNo = strtoupper(trim($pRec['invoice_number'] ?? ($pRec['inum'] ?? '')));
            $pDate = $pRec['invoice_date'] ?? ($pRec['idt'] ?? date('Y-m-d'));
            $pTaxable = floatval($pRec['taxable_value'] ?? ($pRec['txval'] ?? 0));
            $pTax = floatval($pRec['total_tax'] ?? ($pRec['tax'] ?? 0));
            $pParty = $pRec['party_name'] ?? ($pRec['trade_name'] ?? 'Vendor');

            // Find match in Books
            $matchingBook = $booksSnapshots->first(function ($b) use ($pGstin, $pInvNo, &$processedBooksIds) {
                if (in_array($b->id, $processedBooksIds)) return false;
                $bGstin = strtoupper(trim($b->seller_gstin ?? ''));
                $bDocNo = strtoupper(trim($b->document_number ?? ''));
                return ($bGstin === $pGstin && (str_contains($bDocNo, $pInvNo) || str_contains($pInvNo, $bDocNo)));
            });

            if ($matchingBook) {
                $processedBooksIds[] = $matchingBook->id;
                $bTaxable = floatval($matchingBook->taxable_amount);
                $bTax = floatval($matchingBook->total_tax_amount);

                $taxableDiff = round(abs($bTaxable - $pTaxable), 2);
                $taxDiff = round(abs($bTax - $pTax), 2);

                $status = 'MISMATCH';
                if ($taxableDiff <= 1.00 && $taxDiff <= 1.00) {
                    $status = 'MATCHED';
                    $matchedCount++;
                } elseif ($taxableDiff <= 5.00 || $taxDiff <= 5.00) {
                    $status = 'PARTIAL_MATCH';
                    $partialCount++;
                } else {
                    $mismatchCount++;
                }

                GSTReconciliationItem::create([
                    'company_id' => $companyId,
                    'reconciliation_id' => $recon->id,
                    'gstin' => $pGstin,
                    'party_name' => $pParty,
                    'invoice_number' => $matchingBook->document_number,
                    'invoice_date' => $matchingBook->document_date,
                    'books_taxable' => $bTaxable,
                    'portal_taxable' => $pTaxable,
                    'books_tax' => $bTax,
                    'portal_tax' => $pTax,
                    'taxable_diff' => $taxableDiff,
                    'tax_diff' => $taxDiff,
                    'cgst_diff' => round(abs(floatval($matchingBook->cgst_amount) - floatval($pRec['cgst'] ?? 0)), 2),
                    'sgst_diff' => round(abs(floatval($matchingBook->sgst_amount) - floatval($pRec['sgst'] ?? 0)), 2),
                    'igst_diff' => round(abs(floatval($matchingBook->igst_amount) - floatval($pRec['igst'] ?? 0)), 2),
                    'match_status' => $status,
                    'action_taken' => ($status === 'MATCHED') ? 'ACCEPT' : 'NONE',
                ]);
            } else {
                // Portal only (Missing in Books)
                $portalOnlyCount++;
                GSTReconciliationItem::create([
                    'company_id' => $companyId,
                    'reconciliation_id' => $recon->id,
                    'gstin' => $pGstin,
                    'party_name' => $pParty,
                    'invoice_number' => $pInvNo,
                    'invoice_date' => $pDate,
                    'books_taxable' => 0.00,
                    'portal_taxable' => $pTaxable,
                    'books_tax' => 0.00,
                    'portal_tax' => $pTax,
                    'taxable_diff' => $pTaxable,
                    'tax_diff' => $pTax,
                    'match_status' => 'PORTAL_ONLY',
                    'action_taken' => 'NONE',
                ]);
            }
        }

        // 3. Any leftover books records missing in portal
        foreach ($booksSnapshots as $b) {
            if (!in_array($b->id, $processedBooksIds)) {
                $booksOnlyCount++;
                GSTReconciliationItem::create([
                    'company_id' => $companyId,
                    'reconciliation_id' => $recon->id,
                    'gstin' => $b->seller_gstin ?: 'UNREGISTERED',
                    'party_name' => 'Books Purchase',
                    'invoice_number' => $b->document_number,
                    'invoice_date' => $b->document_date,
                    'books_taxable' => floatval($b->taxable_amount),
                    'portal_taxable' => 0.00,
                    'books_tax' => floatval($b->total_tax_amount),
                    'portal_tax' => 0.00,
                    'taxable_diff' => floatval($b->taxable_amount),
                    'tax_diff' => floatval($b->total_tax_amount),
                    'match_status' => 'BOOKS_ONLY',
                    'action_taken' => 'NONE',
                ]);
            }
        }

        $recon->update([
            'status' => 'COMPLETED',
            'matched_count' => $matchedCount,
            'partial_match_count' => $partialCount,
            'mismatch_count' => $mismatchCount,
            'books_only_count' => $booksOnlyCount,
            'portal_only_count' => $portalOnlyCount,
        ]);

        AuditLogService::log(
            $companyId,
            $userName,
            'GST_RECONCILIATION',
            'GSTReconciliation',
            $recon->id,
            "Completed {$returnType} Reconciliation for FY {$financialYear}-{$periodMonth}: Matched: {$matchedCount}, Mismatch: {$mismatchCount}, Books Only: {$booksOnlyCount}, Portal Only: {$portalOnlyCount}"
        );

        return $recon->fresh('items');
    }

    /**
     * Record auditor action on a reconciliation item
     */
    public static function recordItemAction(int $itemId, string $action, ?string $notes = null, ?string $userName = 'Admin'): GSTReconciliationItem
    {
        $item = GSTReconciliationItem::findOrFail($itemId);

        $action = strtoupper($action); // ACCEPT, REJECT, REVIEWED, EXCLUDE
        $item->update([
            'action_taken' => $action,
            'action_notes' => $notes,
            'reviewed_by' => $userName,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogService::log(
            $item->company_id,
            $userName,
            'RECON_ACTION',
            'GSTReconciliationItem',
            $item->id,
            "Reconciliation Item #{$item->invoice_number} marked as {$action}. Notes: {$notes}"
        );

        return $item;
    }
}
