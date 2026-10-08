<?php

namespace App\Audit\Formatters;

class AuditLogFormatter
{
    /**
     * Format an audit entry into a human-readable activity story.
     */
    public static function formatHumanReadable(array $log): string
    {
        $user = $log['user_name'] ?? 'System';
        $action = strtolower($log['action'] ?? 'modified');
        $entity = strtolower($log['entity_type'] ?? 'record');
        $entityId = $log['entity_id'] ? "#{$log['entity_id']}" : '';
        $desc = $log['description'] ?? '';

        if (!empty($log['before_data_json']) && !empty($log['after_data_json'])) {
            $changes = [];
            foreach ($log['after_data_json'] as $k => $newVal) {
                $oldVal = $log['before_data_json'][$k] ?? null;
                if ($oldVal !== $newVal) {
                    $oldStr = is_scalar($oldVal) ? (string)$oldVal : json_encode($oldVal);
                    $newStr = is_scalar($newVal) ? (string)$newVal : json_encode($newVal);
                    $changes[] = "{$k}: '{$oldStr}' → '{$newStr}'";
                }
            }
            if (!empty($changes)) {
                return "{$user} {$action} {$entity} {$entityId} [Changes: " . implode(', ', $changes) . "]";
            }
        }

        return "{$user} {$action} {$entity} {$entityId}: {$desc}";
    }
}
