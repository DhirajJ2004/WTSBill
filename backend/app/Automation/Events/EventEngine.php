<?php

namespace App\Automation\Events;

use App\Models\AutomationEvent;
use App\Automation\Rules\AutomationRuleEngine;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class EventEngine
{
    /**
     * Publish a centralized business event.
     *
     * @param int $companyId
     * @param string $eventType e.g. InvoiceCreated, InvoiceOverdue, PaymentReceived, PaymentFailed, ChequeDue, LowStockDetected
     * @param string $entityType e.g. INVOICE, PAYMENT, CHEQUE, PRODUCT, GST
     * @param int $entityId
     * @param array $metadata
     * @param string|null $triggeredBy
     * @param string|null $customEventId
     * @param int|null $branchId
     * @return AutomationEvent
     */
    public static function publish(
        int $companyId,
        string $eventType,
        string $entityType,
        int $entityId,
        array $metadata = [],
        ?string $triggeredBy = 'System',
        ?string $customEventId = null,
        ?int $branchId = null
    ): AutomationEvent {
        $eventId = $customEventId ?: ('EVT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8)));

        // Idempotency: If same event ID already exists, do not re-process
        $existing = AutomationEvent::where('event_id', $eventId)->first();
        if ($existing) {
            return $existing;
        }

        $event = AutomationEvent::create([
            'event_id' => $eventId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'event_type' => $eventType,
            'entity_type' => strtoupper($entityType),
            'entity_id' => $entityId,
            'occurred_at' => date('Y-m-d H:i:s'),
            'triggered_by' => $triggeredBy ?: 'System',
            'metadata_json' => $metadata,
            'status' => 'PROCESSING',
        ]);

        try {
            // Dispatches to AutomationRuleEngine
            $results = AutomationRuleEngine::evaluateEvent($event);
            $event->status = 'PROCESSED';
            $event->save();
        } catch (Exception $e) {
            $event->status = 'FAILED';
            $event->error_message = $e->getMessage();
            $event->save();
        }

        return $event;
    }
}
