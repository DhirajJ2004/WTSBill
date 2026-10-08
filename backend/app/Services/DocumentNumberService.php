<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentNumberSetting;
use Illuminate\Database\Capsule\Manager as DB;

class DocumentNumberService
{
    /**
     * Map document types to standard 3-4 letter prefix codes.
     */
    public const TYPE_PREFIXES = [
        'INVOICE' => 'INV',
        'TAX_INVOICE' => 'INV',
        'SALES_INVOICE' => 'INV',
        'QUOTATION' => 'QTN',
        'PROFORMA_INVOICE' => 'PI',
        'ESTIMATE' => 'EST',
        'SALES_ORDER' => 'SO',
        'DELIVERY_CHALLAN' => 'DC',
        'CREDIT_NOTE' => 'CN',
        'DEBIT_NOTE' => 'DN',
        'SALES_RETURN' => 'SR',
        'PURCHASE' => 'PUR',
        'PURCHASE_INVOICE' => 'PUR',
        'BILL' => 'PUR',
        'PURCHASE_ORDER' => 'PO',
        'GOODS_RECEIPT' => 'GRN',
        'PURCHASE_RETURN' => 'PR',
        'PAYMENT' => 'PAY',
        'SUPPLIER_PAYMENT' => 'PAY',
        'RECEIPT' => 'REC',
        'PAYMENT_RECEIPT' => 'REC',
        'REFUND' => 'REF',
        'JOURNAL' => 'JV',
        'JOURNAL_ENTRY' => 'JV',
        'MANUAL_JOURNAL' => 'JV',
        'RECURRING_INVOICE' => 'RINV',
        'STOCK_TRANSFER' => 'ST',
        'EXPENSE' => 'EXP',
    ];

    /**
     * Format Financial Year into short code: e.g. "2026-27" -> "FY26-27".
     */
    public static function formatFinancialYearCode(?string $financialYear): string
    {
        $fy = trim((string)$financialYear);
        if (empty($fy)) {
            $y = (int)date('y');
            $m = (int)date('n');
            if ($m < 4) {
                return 'FY' . ($y - 1) . '-' . $y;
            }
            return 'FY' . $y . '-' . ($y + 1);
        }

        // If already formatted like "FY26-27" or "FY2026-27"
        if (preg_match('/^FY(\d{2,4})-(\d{2})$/i', $fy, $matches)) {
            $start = substr($matches[1], -2);
            return 'FY' . $start . '-' . $matches[2];
        }

        // Standard "2026-27" -> "FY26-27"
        if (preg_match('/^(\d{4})-(\d{2,4})$/', $fy, $matches)) {
            $start = substr($matches[1], -2);
            $end = substr($matches[2], -2);
            return 'FY' . $start . '-' . $end;
        }

        return 'FY' . preg_replace('/[^A-Za-z0-9_-]/', '', $fy);
    }

    /**
     * Resolve short branch identifier code: e.g. Branch with code "HO-01" -> "HO01" or "PUN".
     */
    public static function resolveBranchCode(?int $branchId, int $companyId): string
    {
        if ($branchId && $branchId > 0) {
            $branch = Branch::withoutGlobalScopes()->where('id', $branchId)->where('company_id', $companyId)->first();
            if ($branch) {
                $code = trim((string)($branch->code ?: $branch->branch_code));
                if (!empty($code)) {
                    $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
                    if (!empty($clean)) {
                        return substr($clean, 0, 6);
                    }
                }
                // Try from city
                if (!empty($branch->city)) {
                    $cleanCity = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $branch->city));
                    return substr($cleanCity, 0, 3);
                }
            }
        }

        return 'HO';
    }

    /**
     * Build the standard prefix structure: e.g. "INV/FY26-27/PUN/"
     */
    public static function buildDocumentPrefix(string $docType, string $fyCode, string $branchCode): string
    {
        $typePrefix = self::TYPE_PREFIXES[strtoupper(trim($docType))] ?? 'DOC';
        return "{$typePrefix}/{$fyCode}/{$branchCode}/";
    }

    /**
     * Atomically generate the next sequential document number with row locking.
     * Prevents duplicate numbers under concurrent requests.
     * 
     * Output format example: INV/FY26-27/PUN/000001
     */
    public static function generateNextNumber(int $companyId, ?int $branchId, string $financialYear, string $documentType): string
    {
        $docType = strtoupper(trim($documentType));
        $fyCode = self::formatFinancialYearCode($financialYear);
        $resolvedBranchId = ($branchId && $branchId > 0) ? $branchId : 1;
        $branchCode = self::resolveBranchCode($resolvedBranchId, $companyId);
        $targetPrefix = self::buildDocumentPrefix($docType, $fyCode, $branchCode);

        // Execute in database transaction to acquire exclusive row lock
        return DB::transaction(function () use ($companyId, $resolvedBranchId, $financialYear, $docType, $targetPrefix) {
            // Find existing sequence with exclusive FOR UPDATE lock
            $setting = DocumentNumberSetting::where('company_id', $companyId)
                ->where('branch_id', $resolvedBranchId)
                ->where('financial_year', $financialYear)
                ->where('document_type', $docType)
                ->lockForUpdate()
                ->first();

            // If sequence doesn't exist yet, insert with default starting value
            if (!$setting) {
                try {
                    DocumentNumberSetting::create([
                        'company_id' => $companyId,
                        'branch_id' => $resolvedBranchId,
                        'financial_year' => $financialYear,
                        'document_type' => $docType,
                        'prefix' => $targetPrefix,
                        'starting_number' => 1,
                        'current_number' => 0,
                    ]);
                } catch (\Throwable $e) {
                    // Handled if a simultaneous worker created the unique row first
                }

                $setting = DocumentNumberSetting::where('company_id', $companyId)
                    ->where('branch_id', $resolvedBranchId)
                    ->where('financial_year', $financialYear)
                    ->where('document_type', $docType)
                    ->lockForUpdate()
                    ->first();
            }

            // Calculate next sequence counter
            $startNum = max(1, (int)$setting->starting_number);
            $currNum = (int)$setting->current_number;
            $nextNum = ($currNum >= $startNum) ? ($currNum + 1) : $startNum;

            // Atomically update sequence counter and prefix
            $setting->current_number = $nextNum;
            $setting->prefix = $targetPrefix;
            $setting->save();

            // Return formatted structured document number: INV/FY26-27/PUN/000001
            return $targetPrefix . sprintf('%06d', $nextNum);
        });
    }

    /**
     * Preview the next document number without advancing the sequence counter.
     */
    public static function previewNextNumber(int $companyId, ?int $branchId, string $financialYear, string $documentType): string
    {
        $docType = strtoupper(trim($documentType));
        $fyCode = self::formatFinancialYearCode($financialYear);
        $resolvedBranchId = ($branchId && $branchId > 0) ? $branchId : 1;
        $branchCode = self::resolveBranchCode($resolvedBranchId, $companyId);
        $targetPrefix = self::buildDocumentPrefix($docType, $fyCode, $branchCode);

        $setting = DocumentNumberSetting::where('company_id', $companyId)
            ->where('branch_id', $resolvedBranchId)
            ->where('financial_year', $financialYear)
            ->where('document_type', $docType)
            ->first();

        if (!$setting) {
            return $targetPrefix . sprintf('%06d', 1);
        }

        $startNum = max(1, (int)$setting->starting_number);
        $currNum = (int)$setting->current_number;
        $nextNum = ($currNum >= $startNum) ? ($currNum + 1) : $startNum;

        return $targetPrefix . sprintf('%06d', $nextNum);
    }
}
