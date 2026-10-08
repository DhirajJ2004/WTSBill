<?php

namespace App\Notifications\Events;

class BusinessEvent
{
    public int $companyId;
    public ?int $branchId;
    public string $eventType;
    public string $entityType;
    public int $entityId;
    public string $occurredAt;
    public array $payload;

    public function __construct(
        int $companyId,
        string $eventType,
        string $entityType,
        int $entityId,
        ?int $branchId = null,
        array $payload = [],
        ?string $occurredAt = null
    ) {
        $this->companyId = $companyId;
        $this->eventType = $eventType;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->branchId = $branchId;
        $this->payload = $payload;
        $this->occurredAt = $occurredAt ?: date('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'event_type' => $this->eventType,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'occurred_at' => $this->occurredAt,
            'payload' => $this->payload,
        ];
    }
}
