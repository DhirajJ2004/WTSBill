<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Session;

/**
 * Enterprise Audit Logger for WTSBill ERP.
 * Implements immutable audit trail tracking with sensitive field masking,
 * session hashing, and suspicious security event monitoring.
 */
class AuditLogger
{
    private static array $sensitiveKeys = [
        'password', 'password_confirmation', 'token', 'csrf_token', '_token',
        'secret', 'jwt_secret', 'card_number', 'bank_account_number', 'cvv',
        'pin', 'remember_token', 'app_key'
    ];

    /**
     * Record an audit event into audit_logs.
     */
    public static function log(
        string $action,
        string $module,
        string $entityType,
        int|string|null $entityId = null,
        ?string $entityLabel = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $status = 'success',
        ?string $notes = null,
        ?int $userId = null,
        ?string $userName = null,
        ?string $userRole = null
    ): int {
        try {
            $companyId = (int)(Session::getCompanyId() ?? 1);
            $branchId = Session::getBranchId();
            $branchId = $branchId !== null ? (int)$branchId : null;
            $resolvedEntityId = $entityId !== null ? (int)$entityId : null;

            // Resolve acting user context
            $resolvedUserId = $userId ?? Session::getUserId();
            $resolvedUserName = $userName ?? (string)Session::get('user_name', 'Guest / System');
            $resolvedUserRole = $userRole ?? (string)Session::get('user_role', 'GUEST');

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown'), 0, 250);
            $requestUrl = substr((string)($_SERVER['REQUEST_URI'] ?? '/'), 0, 500);
            $httpMethod = substr((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 10);

            // Hash session ID to prevent session hijacking via audit logs
            $sessionId = session_id();
            $sessionIdHash = !empty($sessionId) ? hash('sha256', $sessionId) : null;

            // Filter old and new values: mask secrets and keep only changed fields if both supplied
            [$filteredOld, $filteredNew] = self::prepareDiffs($oldValues, $newValues);

            $oldJson = $filteredOld !== null ? json_encode($filteredOld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
            $newJson = $filteredNew !== null ? json_encode($filteredNew, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

            $actionUpper = strtoupper(trim($action));
            $statusUpper = strtoupper(trim($status));
            $severity = 'INFO';
            if ($statusUpper === 'DENIED' || $actionUpper === 'PERMISSION_DENIED') {
                $severity = 'WARNING';
            } elseif ($statusUpper === 'FAILED' || $actionUpper === 'LOGIN_FAILED') {
                $severity = 'WARNING';
            }

            // Suspicious event check
            if ($actionUpper === 'LOGIN_FAILED') {
                $recentFails = self::countRecentFailedLogins($ip, $resolvedUserName);
                if ($recentFails >= 4) { // This makes the 5th attempt trigger ALERT
                    $severity = 'ALERT';
                    $notes = trim(($notes ? $notes . ' | ' : '') . "SECURITY ALERT: 5+ failed login attempts detected in 10 minutes.");
                }
            } elseif ($actionUpper === 'ROLE_CHANGED') {
                $severity = 'WARNING';
            }

            $description = $notes ?? "Action '{$actionUpper}' on {$entityType}" . ($entityLabel ? " [{$entityLabel}]" : '');

            return (int)Database::insert('audit_logs', [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => $resolvedUserId,
                'user_name' => $resolvedUserName,
                'user_role' => $resolvedUserRole,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'session_id_hash' => $sessionIdHash,
                'action' => $actionUpper,
                'module' => strtolower(trim($module)),
                'entity_type' => strtoupper(trim($entityType)),
                'entity_id' => $resolvedEntityId,
                'entity_label' => $entityLabel,
                'description' => $description,
                'notes' => $notes,
                'old_values' => $oldJson,
                'new_values' => $newJson,
                'before_data_json' => $oldJson,
                'after_data_json' => $newJson,
                'request_url' => $requestUrl,
                'http_method' => $httpMethod,
                'status' => $statusUpper,
                'severity' => $severity,
                'correlation_id' => bin2hex(random_bytes(16)),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('AuditLogger error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mask sensitive values and filter for diffs.
     */
    private static function prepareDiffs(?array $old, ?array $new): array
    {
        $maskedOld = $old !== null ? self::maskSensitive($old) : null;
        $maskedNew = $new !== null ? self::maskSensitive($new) : null;

        if ($maskedOld !== null && $maskedNew !== null) {
            // Keep only keys that differ
            $diffOld = [];
            $diffNew = [];
            $allKeys = array_unique(array_merge(array_keys($maskedOld), array_keys($maskedNew)));

            foreach ($allKeys as $k) {
                $valOld = $maskedOld[$k] ?? null;
                $valNew = $maskedNew[$k] ?? null;
                if ($valOld !== $valNew) {
                    $diffOld[$k] = $valOld;
                    $diffNew[$k] = $valNew;
                }
            }
            return [$diffOld, $diffNew];
        }

        return [$maskedOld, $maskedNew];
    }

    /**
     * Recursively mask sensitive fields.
     */
    private static function maskSensitive(array $data): array
    {
        $result = [];
        foreach ($data as $key => $val) {
            $lowerKey = strtolower((string)$key);
            $isSensitive = false;
            foreach (self::$sensitiveKeys as $secret) {
                if (str_contains($lowerKey, $secret)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $result[$key] = '********';
            } elseif (is_array($val)) {
                $result[$key] = self::maskSensitive($val);
            } else {
                $result[$key] = $val;
            }
        }
        return $result;
    }

    /**
     * Count failed logins for this IP or username in last 10 minutes.
     */
    private static function countRecentFailedLogins(string $ip, string $userName): int
    {
        try {
            $since = date('Y-m-d H:i:s', time() - 600);
            $row = Database::selectOne(
                "SELECT COUNT(*) AS total FROM `audit_logs` 
                 WHERE `action` = 'LOGIN_FAILED' 
                   AND (`ip_address` = :ip OR `user_name` = :username)
                   AND `created_at` >= :since",
                ['ip' => $ip, 'username' => $userName, 'since' => $since]
            );
            return (int)($row['total'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}

// Global and App namespace backward compatibility aliases
if (!class_exists('App\Services\AuditLogger', false)) {
    class_alias(AuditLogger::class, 'App\Services\AuditLogger');
}
if (!class_exists('AuditLogger', false)) {
    class_alias(AuditLogger::class, 'AuditLogger');
}
