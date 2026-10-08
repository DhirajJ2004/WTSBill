<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class FinancialYearService
{
    /**
     * Get all financial years / accounting periods for a company.
     */
    public static function getFinancialYears(int $companyId): array
    {
        return AccountingPeriod::where('company_id', $companyId)
            ->orderBy('start_date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get active financial year for a company or auto-detect from date.
     */
    public static function getCurrentFinancialYear(int $companyId, ?string $date = null): ?AccountingPeriod
    {
        $targetDate = $date ?: date('Y-m-d');
        
        $period = AccountingPeriod::where('company_id', $companyId)
            ->where('start_date', '<=', $targetDate)
            ->where('end_date', '>=', $targetDate)
            ->first();

        if (!$period) {
            $period = AccountingPeriod::where('company_id', $companyId)
                ->orderBy('end_date', 'desc')
                ->first();
        }

        return $period;
    }

    /**
     * Create a new Financial Year / Accounting Period.
     */
    public static function createFinancialYear(int $companyId, array $data, string $userName = 'Admin'): array
    {
        $financialYear = trim($data['financial_year'] ?? '');
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $periodName = trim($data['period_name'] ?? "FY {$financialYear}");

        if (empty($financialYear) || empty($startDate) || empty($endDate)) {
            return ['success' => false, 'message' => 'Financial year code, start date, and end date are required.'];
        }

        // Validate date order
        if (strtotime($startDate) >= strtotime($endDate)) {
            return ['success' => false, 'message' => 'Start date must be strictly before end date.'];
        }

        // Check uniqueness for company
        $exists = AccountingPeriod::where('company_id', $companyId)
            ->where('financial_year', $financialYear)
            ->exists();

        if ($exists) {
            return ['success' => false, 'message' => "Financial year '{$financialYear}' already exists."];
        }

        $period = AccountingPeriod::create([
            'company_id'     => $companyId,
            'period_name'    => $periodName,
            'financial_year' => $financialYear,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'is_locked'      => !empty($data['is_locked']),
            'lock_reason'    => $data['lock_reason'] ?? null,
            'locked_by'      => !empty($data['is_locked']) ? $userName : null,
            'locked_at'      => !empty($data['is_locked']) ? date('Y-m-d H:i:s') : null,
        ]);

        AuditLogService::record([
            'company_id'  => $companyId,
            'user_name'   => $userName,
            'action'      => 'CREATE_FINANCIAL_YEAR',
            'entity'      => 'AccountingPeriod',
            'entity_id'   => $period->id,
            'description' => "Created financial year '{$periodName}' ({$startDate} to {$endDate})",
            'new_values'  => $period->toArray(),
        ]);

        return [
            'success' => true,
            'period'  => $period,
            'message' => "Financial year '{$periodName}' created successfully."
        ];
    }

    /**
     * Lock a financial year / period to prevent backdated entries.
     */
    public static function lockPeriod(int $companyId, int $periodId, string $reason, string $userName = 'Admin'): array
    {
        $period = AccountingPeriod::where('company_id', $companyId)->find($periodId);
        if (!$period) {
            return ['success' => false, 'message' => 'Accounting period not found.'];
        }

        $oldValues = $period->toArray();

        $period->update([
            'is_locked'   => true,
            'lock_reason' => $reason,
            'locked_by'   => $userName,
            'locked_at'   => date('Y-m-d H:i:s'),
        ]);

        AuditLogService::record([
            'company_id'  => $companyId,
            'user_name'   => $userName,
            'action'      => 'LOCK_FINANCIAL_YEAR',
            'entity'      => 'AccountingPeriod',
            'entity_id'   => $period->id,
            'description' => "Locked financial year '{$period->period_name}': {$reason}",
            'old_values'  => $oldValues,
            'new_values'  => $period->toArray(),
        ]);

        return [
            'success' => true,
            'period'  => $period,
            'message' => "Financial year '{$period->period_name}' locked successfully."
        ];
    }

    /**
     * Unlock a financial year / period.
     */
    public static function unlockPeriod(int $companyId, int $periodId, string $userName = 'Admin'): array
    {
        $period = AccountingPeriod::where('company_id', $companyId)->find($periodId);
        if (!$period) {
            return ['success' => false, 'message' => 'Accounting period not found.'];
        }

        $oldValues = $period->toArray();

        $period->update([
            'is_locked'   => false,
            'lock_reason' => null,
            'locked_by'   => null,
            'locked_at'   => null,
        ]);

        AuditLogService::record([
            'company_id'  => $companyId,
            'user_name'   => $userName,
            'action'      => 'UNLOCK_FINANCIAL_YEAR',
            'entity'      => 'AccountingPeriod',
            'entity_id'   => $period->id,
            'description' => "Unlocked financial year '{$period->period_name}'",
            'old_values'  => $oldValues,
            'new_values'  => $period->toArray(),
        ]);

        return [
            'success' => true,
            'period'  => $period,
            'message' => "Financial year '{$period->period_name}' unlocked successfully."
        ];
    }

    /**
     * Check if a specific transaction date is locked for the company.
     */
    public static function isDateLocked(int $companyId, string $date): bool
    {
        return AccountingPeriod::where('company_id', $companyId)
            ->where('is_locked', true)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }
}
