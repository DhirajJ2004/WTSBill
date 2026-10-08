<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Capsule\Manager as DB;

class AuditLogService
{
    /**
     * Recursively sanitize data to guarantee passwords, tokens, secrets, session IDs, and DB credentials are NEVER logged.
     */
    public static function sanitizeData($data)
    {
        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                $data = $data->toArray();
            } else {
                $data = (array)$data;
            }
        }
        if (!is_array($data)) {
            return $data;
        }

        $sanitized = [];
        foreach ($data as $key => $val) {
            $keyLower = strtolower((string)$key);
            if (preg_match('/(password|token|secret|jwt|hash|remember_token|auth_key|api_key|credit_card|cvv|pin|db_pass|database_password|session_id|sess_id|session)/i', $keyLower)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($val) || is_object($val)) {
                $sanitized[$key] = static::sanitizeData($val);
            } else {
                $sanitized[$key] = $val;
            }
        }
        return $sanitized;
    }

    /**
     * Record an audit log entry with full server-side context and strict sanitization.
     */
    public static function record(array $params): ?AuditLog
    {
        try {
            $companyId = !empty($params['company_id']) ? (int)$params['company_id'] : 1;
            $branchId = !empty($params['branch_id']) ? (int)$params['branch_id'] : null;
            $userId = !empty($params['user_id']) ? (int)$params['user_id'] : null;
            $userName = $params['user_name'] ?? 'System';
            $action = strtoupper(trim($params['action'] ?? 'ACTION'));
            $entityType = $params['entity'] ?? ($params['entity_type'] ?? 'General');
            $entityId = isset($params['entity_id']) ? (int)$params['entity_id'] : 0;
            $description = $params['description'] ?? '';

            // Redact description if sensitive patterns present
            if (is_string($description)) {
                $description = preg_replace('/(password|token|secret|jwt|api_key|session_id)\s*[:=]\s*(\S+)/i', '$1: [REDACTED]', $description);
            }

            $oldValues = !empty($params['old_values']) ? static::sanitizeData($params['old_values']) : null;
            $newValues = !empty($params['new_values']) ? static::sanitizeData($params['new_values']) : null;

            $ipAddress = $params['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $userAgent = $params['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'WTSBill-App');

            return AuditLog::create([
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'user_id'          => $userId,
                'user_name'        => $userName,
                'action'           => $action,
                'entity_type'      => $entityType,
                'entity_id'        => $entityId,
                'description'      => $description ?: "{$action} on {$entityType} #{$entityId}",
                'before_data_json' => $oldValues ? json_encode($oldValues) : null,
                'after_data_json'  => $newValues ? json_encode($newValues) : null,
                'ip_address'       => $ipAddress,
                'user_agent'       => substr((string)$userAgent, 0, 255),
                'status'           => $params['status'] ?? 'SUCCESS',
                'severity'         => $params['severity'] ?? 'INFO',
            ]);
        } catch (\Throwable $e) {
            error_log("[AuditLogService] Failed to record audit log: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Backward-compatible log interface.
     */
    public static function log(
        int $companyId,
        $param2 = null,
        $param3 = null,
        $param4 = null,
        $param5 = null,
        $param6 = null,
        $param7 = null,
        $param8 = null
    ): void {
        $userName = 'System';
        $action = 'ACTION';
        $entityType = 'General';
        $entityId = 0;
        $description = '';
        $oldVal = null;
        $newVal = null;

        if (is_string($param2) && is_string($param3) && (is_int($param4) || is_null($param4)) && is_string($param5)) {
            $action = strtoupper($param2);
            $entityType = $param3;
            $entityId = $param4 ?: 0;
            $description = $param5;
            $oldVal = $param6;
            $newVal = $param7;
            $userName = is_string($param8) ? $param8 : 'System';
        } else {
            $userName = $param2 ?: 'System';
            $action = strtoupper((string)$param3);
            $entityType = (string)$param4;
            $entityId = is_numeric($param5) ? (int)$param5 : 0;
            $description = (string)$param6;
            $newVal = is_array($param8) ? $param8 : null;
        }

        static::record([
            'company_id'  => $companyId,
            'user_name'   => $userName,
            'action'      => $action,
            'entity'      => $entityType,
            'entity_id'   => $entityId,
            'description' => $description,
            'old_values'  => $oldVal,
            'new_values'  => $newVal,
        ]);
    }

    /**
     * Search and paginate audit logs for a company.
     */
    public static function getLogs(int $companyId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $query = AuditLog::where('company_id', $companyId);

        if (!empty($filters['action'])) {
            $query->where('action', strtoupper(trim($filters['action'])));
        }
        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', trim($filters['entity_type']));
        }
        if (!empty($filters['user_name'])) {
            $query->where('user_name', 'LIKE', '%' . trim($filters['user_name']) . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('status', strtoupper(trim($filters['status'])));
        }
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date'] . ' 00:00:00');
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date'] . ' 23:59:59');
        }
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', $search)
                  ->orWhere('action', 'LIKE', $search)
                  ->orWhere('user_name', 'LIKE', $search)
                  ->orWhere('ip_address', 'LIKE', $search);
            });
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $items = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $perPage,
            'total_pages'  => ceil($total / $perPage),
            'items'        => $items,
        ];
    }
}
