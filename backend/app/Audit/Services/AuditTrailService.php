<?php

namespace App\Audit\Services;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AuditTrailService
{
    private static array $sensitiveKeys = [
        'password',
        'password_hash',
        'remember_token',
        'secret',
        'api_key',
        'token',
        'card_number',
        'cvv',
        'auth_token',
        'access_token',
        'private_key',
    ];

    /**
     * Generate unique correlation ID for multi-step operations.
     */
    public static function generateCorrelationId(string $prefix = 'OP'): string
    {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(Str::random(8));
    }

    /**
     * Record an immutable audit log entry.
     */
    public static function log(
        int $companyId,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?array $metadata = null,
        ?int $userId = 1,
        ?string $userName = 'System',
        ?int $branchId = null,
        ?string $correlationId = null,
        string $status = 'SUCCESS',
        string $severity = 'INFO'
    ): AuditLog {
        $sanitizedBefore = $beforeData ? self::redactSensitiveData($beforeData) : null;
        $sanitizedAfter = $afterData ? self::redactSensitiveData($afterData) : null;
        $sanitizedMeta = $metadata ? self::redactSensitiveData($metadata) : null;

        return AuditLog::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'user_name' => $userName,
            'action' => strtoupper($action),
            'entity_type' => strtoupper($entityType),
            'entity_id' => $entityId,
            'description' => $description,
            'correlation_id' => $correlationId ?: self::generateCorrelationId(),
            'before_data_json' => $sanitizedBefore,
            'after_data_json' => $sanitizedAfter,
            'metadata_json' => $sanitizedMeta,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System',
            'status' => $status,
            'severity' => $severity,
        ]);
    }

    /**
     * Query audit logs with search, filtering and pagination.
     */
    public static function queryLogs(int $companyId, array $filters = []): array
    {
        $query = AuditLog::where('company_id', $companyId)->orderBy('created_at', 'desc');

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', strtoupper($filters['action']));
        }
        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', strtoupper($filters['entity_type']));
        }
        if (!empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }
        if (!empty($filters['correlation_id'])) {
            $query->where('correlation_id', $filters['correlation_id']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', $term)
                  ->orWhere('user_name', 'like', $term)
                  ->orWhere('action', 'like', $term)
                  ->orWhere('entity_type', 'like', $term);
            });
        }

        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $total = $query->count();
        $logs = $query->skip($offset)->take($limit)->get();

        return [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'data' => $logs->toArray(),
        ];
    }

    /**
     * Recursively redact sensitive keys from array.
     */
    public static function redactSensitiveData(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string)$key);
            $isSensitive = false;
            foreach (self::$sensitiveKeys as $sk) {
                if (str_contains($lowerKey, $sk)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $result[$key] = '******** (REDACTED)';
            } elseif (is_array($value)) {
                $result[$key] = self::redactSensitiveData($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
