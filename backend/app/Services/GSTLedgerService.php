<?php

namespace App\Services;

use App\Models\GSTDocumentSnapshot;
use App\Models\GSTFilingPeriod;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Capsule\Manager as DB;

class GSTLedgerService
{
    /**
     * Get unified GST Transaction Register / Ledger
     */
    public static function getRegister(int $companyId, array $filters = []): array
    {
        $query = GSTDocumentSnapshot::where('company_id', $companyId);

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', intval($filters['branch_id']));
        }

        if (!empty($filters['from_date'])) {
            $query->where('document_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('document_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['document_type']) && $filters['document_type'] !== 'ALL') {
            $query->where('document_type', strtoupper($filters['document_type']));
        }

        if (!empty($filters['gst_category']) && $filters['gst_category'] !== 'ALL') {
            $query->where('gst_category', strtoupper($filters['gst_category']));
        }

        if (!empty($filters['supply_type']) && $filters['supply_type'] !== 'ALL') {
            $query->where('supply_type', strtoupper($filters['supply_type']));
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'LIKE', $search)
                  ->orWhere('buyer_gstin', 'LIKE', $search)
                  ->orWhere('seller_gstin', 'LIKE', $search)
                  ->orWhere('place_of_supply', 'LIKE', $search);
            });
        }

        $records = $query->orderBy('document_date', 'desc')
                         ->orderBy('id', 'desc')
                         ->get();

        $rows = [];
        $totalTaxable = 0.0;
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;
        $totalCess = 0.0;
        $totalTax = 0.0;
        $totalGrand = 0.0;

        // Cache customer / supplier names
        $customerCache = [];
        $supplierCache = [];

        foreach ($records as $rec) {
            $partyName = '-';
            $partyGstin = '-';

            if ($rec->document_type === 'INVOICE') {
                $partyGstin = $rec->buyer_gstin ?: 'Unregistered';
                $inv = Invoice::find($rec->document_id);
                if ($inv && $inv->customer_id) {
                    if (!isset($customerCache[$inv->customer_id])) {
                        $c = Customer::find($inv->customer_id);
                        $customerCache[$inv->customer_id] = $c ? $c->name : 'Customer';
                    }
                    $partyName = $customerCache[$inv->customer_id];
                }
            } elseif ($rec->document_type === 'PURCHASE') {
                $partyGstin = $rec->seller_gstin ?: 'Unregistered';
                $pur = Purchase::find($rec->document_id);
                if ($pur && $pur->supplier_id) {
                    if (!isset($supplierCache[$pur->supplier_id])) {
                        $s = Supplier::find($pur->supplier_id);
                        $supplierCache[$pur->supplier_id] = $s ? $s->name : 'Supplier';
                    }
                    $partyName = $supplierCache[$pur->supplier_id];
                }
            } elseif ($rec->document_type === 'CREDIT_NOTE') {
                $partyGstin = $rec->buyer_gstin ?: 'Unregistered';
                $cn = CreditNote::find($rec->document_id);
                if ($cn && $cn->customer_id) {
                    if (!isset($customerCache[$cn->customer_id])) {
                        $c = Customer::find($cn->customer_id);
                        $customerCache[$cn->customer_id] = $c ? $c->name : 'Customer';
                    }
                    $partyName = $customerCache[$cn->customer_id];
                }
            } elseif ($rec->document_type === 'DEBIT_NOTE') {
                $partyGstin = $rec->seller_gstin ?: 'Unregistered';
                $dn = DebitNote::find($rec->document_id);
                if ($dn && $dn->supplier_id) {
                    if (!isset($supplierCache[$dn->supplier_id])) {
                        $s = Supplier::find($dn->supplier_id);
                        $supplierCache[$dn->supplier_id] = $s ? $s->name : 'Supplier';
                    }
                    $partyName = $supplierCache[$dn->supplier_id];
                }
            }

            $taxable = floatval($rec->taxable_amount);
            $cgst = floatval($rec->cgst_amount);
            $sgst = floatval($rec->sgst_amount);
            $igst = floatval($rec->igst_amount);
            $cess = floatval($rec->cess_amount);
            $tax = floatval($rec->total_tax_amount);
            $grand = floatval($rec->total_document_value);

            // Is locked check
            $isPeriodLocked = static::isDateInLockedPeriod($companyId, $rec->document_date);

            $rows[] = [
                'id' => $rec->id,
                'document_type' => $rec->document_type,
                'document_id' => $rec->document_id,
                'document_number' => $rec->document_number,
                'document_date' => $rec->document_date,
                'party_name' => $partyName,
                'party_gstin' => $partyGstin,
                'place_of_supply' => $rec->place_of_supply,
                'place_of_supply_name' => PlaceOfSupplyService::getStateName($rec->place_of_supply),
                'supply_type' => $rec->supply_type,
                'gst_category' => $rec->gst_category,
                'is_reverse_charge' => (bool)$rec->is_reverse_charge,
                'taxable_amount' => $taxable,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'cess_amount' => $cess,
                'total_tax_amount' => $tax,
                'total_document_value' => $grand,
                'is_period_locked' => $isPeriodLocked,
                'created_at' => $rec->created_at ? $rec->created_at->format('Y-m-d H:i:s') : null,
            ];

            $totalTaxable += $taxable;
            $totalCgst += $cgst;
            $totalSgst += $sgst;
            $totalIgst += $igst;
            $totalCess += $cess;
            $totalTax += $tax;
            $totalGrand += $grand;
        }

        return [
            'records' => $rows,
            'summary' => [
                'count' => count($rows),
                'total_taxable' => round($totalTaxable, 2),
                'total_cgst' => round($totalCgst, 2),
                'total_sgst' => round($totalSgst, 2),
                'total_igst' => round($totalIgst, 2),
                'total_cess' => round($totalCess, 2),
                'total_tax' => round($totalTax, 2),
                'total_grand_total' => round($totalGrand, 2),
            ]
        ];
    }

    /**
     * Check if a specific transaction date is within a locked GST filing period
     */
    public static function isDateInLockedPeriod(int $companyId, string $dateStr): bool
    {
        $timestamp = strtotime($dateStr);
        if (!$timestamp) return false;

        $monthNum = date('n', $timestamp);
        $year = date('Y', $timestamp);

        // Indian financial year calculation (April to March)
        $fyStart = ($monthNum >= 4) ? $year : ($year - 1);
        $fyEnd = $fyStart + 1;
        $fyStr = "{$fyStart}-" . substr((string)$fyEnd, 2, 2);

        $periodMonthCode = 'M' . str_pad((string)$monthNum, 2, '0', STR_PAD_LEFT);

        $locked = GSTFilingPeriod::where('company_id', $companyId)
            ->where('financial_year', $fyStr)
            ->where('period_name', $periodMonthCode)
            ->where('status', 'LOCKED')
            ->exists();

        return $locked;
    }

    /**
     * Enforce period lock before altering or posting GST documents
     */
    public static function assertPeriodNotLocked(int $companyId, string $documentDate): void
    {
        if (static::isDateInLockedPeriod($companyId, $documentDate)) {
            throw new \Exception("GST period is locked for date {$documentDate}. Modifications to GST-sensitive transactions are prohibited.");
        }
    }

    /**
     * Lock a GST Filing Period
     */
    public static function lockPeriod(int $companyId, string $financialYear, string $periodName, string $returnType = 'GSTR1', ?string $userName = 'Admin'): GSTFilingPeriod
    {
        $period = GSTFilingPeriod::firstOrNew([
            'company_id' => $companyId,
            'financial_year' => $financialYear,
            'period_name' => $periodName,
            'return_type' => $returnType,
        ]);

        $period->status = 'LOCKED';
        $period->filed_by = $userName;
        $period->filing_date = date('Y-m-d');
        $period->save();

        AuditLogService::log(
            $companyId,
            $userName,
            'GST_PERIOD_LOCKED',
            'GSTFilingPeriod',
            $period->id,
            "GST Period {$periodName} ({$financialYear}) locked by {$userName}"
        );

        return $period;
    }

    /**
     * Reopen a locked GST Filing Period
     */
    public static function reopenPeriod(int $companyId, int $periodId, string $reason, string $userName): GSTFilingPeriod
    {
        $period = GSTFilingPeriod::where('company_id', $companyId)->findOrFail($periodId);

        if (empty(trim($reason))) {
            throw new \Exception("A mandatory justification reason is required to reopen a locked GST period.");
        }

        $period->status = 'REOPENED';
        $period->save();

        AuditLogService::log(
            $companyId,
            $userName,
            'GST_PERIOD_REOPENED',
            'GSTFilingPeriod',
            $period->id,
            "GST Period #{$periodId} ({$period->period_name} - {$period->financial_year}) reopened by {$userName}. Reason: {$reason}"
        );

        return $period;
    }
}
