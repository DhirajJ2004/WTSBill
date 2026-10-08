<?php

namespace App\Scheduler\Jobs;

use App\Models\Batch;
use App\Notifications\Events\BusinessEvent;
use App\Notifications\Events\EventDispatcher;
use Carbon\Carbon;

class BatchExpiryJob
{
    public static function run(int $companyId, int $warningDays = 30): array
    {
        $thresholdDate = Carbon::today()->addDays($warningDays)->toDateString();

        $expiringBatches = Batch::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('current_quantity', '>', 0)
            ->where('expiry_date', '<=', $thresholdDate)
            ->with('product')
            ->get();

        $alertsSent = 0;
        foreach ($expiringBatches as $batch) {
            EventDispatcher::dispatch(new BusinessEvent(
                $companyId,
                'BATCH_EXPIRING',
                'BATCH',
                $batch->id,
                null,
                [
                    'batch_number' => $batch->batch_number,
                    'product_name' => $batch->product?->name ?: 'Item',
                    'expiry_date' => $batch->expiry_date,
                    'quantity' => $batch->current_quantity,
                ]
            ));
            $alertsSent++;
        }

        return [
            'batches_checked' => count($expiringBatches),
            'alerts_sent' => $alertsSent,
        ];
    }
}
