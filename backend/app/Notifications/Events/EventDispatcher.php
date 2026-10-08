<?php

namespace App\Notifications\Events;

use App\Models\NotificationEvent;
use App\Notifications\Services\NotificationService;

class EventDispatcher
{
    /**
     * Dispatch business event and trigger notification processing.
     */
    public static function dispatch(BusinessEvent $event): NotificationEvent
    {
        // 1. Record event in immutable ledger
        $dbEvent = NotificationEvent::create([
            'company_id' => $event->companyId,
            'branch_id' => $event->branchId,
            'event_type' => $event->eventType,
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'occurred_at' => $event->occurredAt,
            'payload_json' => $event->payload,
            'status' => 'PENDING',
        ]);

        // 2. Pass to Notification Service for rule matching & channel dispatch
        NotificationService::processEvent($event);

        $dbEvent->update(['status' => 'PROCESSED']);

        return $dbEvent;
    }
}
