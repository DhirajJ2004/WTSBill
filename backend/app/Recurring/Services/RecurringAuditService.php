<?php

namespace App\Recurring\Services;

use App\Models\RecurringTransactionHistory;

class RecurringAuditService
{
    public static function log(
        int $companyId,
        int $recurringTransactionId,
        string $action,
        array $details = [],
        ?int $userId = null,
        string $userName = 'System'
    ): RecurringTransactionHistory {
        return RecurringTransactionHistory::create([
            'company_id' => $companyId,
            'recurring_transaction_id' => $recurringTransactionId,
            'action' => strtoupper($action),
            'performed_by_user_id' => $userId,
            'performed_by_name' => $userName,
            'details_json' => $details,
        ]);
    }
}
