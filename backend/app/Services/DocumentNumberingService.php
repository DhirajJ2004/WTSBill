<?php

namespace App\Services;

class DocumentNumberingService
{
    /**
     * Generate the next document number for the given tenant context.
     * Forward to authoritative DocumentNumberService.
     */
    public static function generateNextNumber(int $companyId, ?int $branchId, string $financialYear, string $documentType): string
    {
        return DocumentNumberService::generateNextNumber($companyId, $branchId, $financialYear, $documentType);
    }

    /**
     * Preview next document number without advancing counter.
     */
    public static function previewNextNumber(int $companyId, ?int $branchId, string $financialYear, string $documentType): string
    {
        return DocumentNumberService::previewNextNumber($companyId, $branchId, $financialYear, $documentType);
    }
}
