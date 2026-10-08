<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Http\Middleware\AuthMiddleware;
use App\Auth\WorkspaceContext;

class AuditLogService
{
    // Standard Canonical Actions Required by WTSBill Compliance
    public const LOGIN            = 'LOGIN';
    public const LOGOUT           = 'LOGOUT';
    public const CREATE_COMPANY   = 'CREATE_COMPANY';
    public const CREATE_CUSTOMER  = 'CREATE_CUSTOMER';
    public const CREATE_SUPPLIER  = 'CREATE_SUPPLIER';
    public const CREATE_PRODUCT   = 'CREATE_PRODUCT';
    public const CREATE_INVOICE   = 'CREATE_INVOICE';
    public const UPDATE_INVOICE   = 'UPDATE_INVOICE';
    public const CANCEL_INVOICE   = 'CANCEL_INVOICE';
    public const CREATE_PAYMENT   = 'CREATE_PAYMENT';
    public const CREATE_PURCHASE  = 'CREATE_PURCHASE';
    public const CREATE_EXPENSE   = 'CREATE_EXPENSE';
    public const STOCK_ADJUSTMENT = 'STOCK_ADJUSTMENT';
    public const STOCK_TRANSFER   = 'STOCK_TRANSFER';
    public const CREATE_USER      = 'CREATE_USER';
    public const UPDATE_USER      = 'UPDATE_USER';
    public const CHANGE_ROLE      = 'CHANGE_ROLE';

    /**
     * Map legacy/alternate action names to canonical standard action names.
     */
    protected static array $actionMap = [
        'CUSTOMER_CREATE'        => self::CREATE_CUSTOMER,
        'SUPPLIER_CREATE'        => self::CREATE_SUPPLIER,
        'PRODUCT_CREATE'         => self::CREATE_PRODUCT,
        'INVOICE_CREATE'         => self::CREATE_INVOICE,
        'INVOICE_UPDATE'         => self::UPDATE_INVOICE,
        'INVOICE_CANCEL'         => self::CANCEL_INVOICE,
        'PAYMENT_CREATE'         => self::CREATE_PAYMENT,
        'PURCHASE_CREATE'        => self::CREATE_PURCHASE,
        'EXPENSE_CREATE'         => self::CREATE_EXPENSE,
        'COMPANY_PROVISIONED'    => self::CREATE_COMPANY,
        'USER_CREATE'            => self::CREATE_USER,
        'USER_UPDATE'            => self::UPDATE_USER,
        'USER_ROLE_CHANGE'       => self::CHANGE_ROLE,
        'USER_PERMISSION_CHANGE' => self::CREATE_USER,
        'ROLE_CHANGE'            => self::CHANGE_ROLE,
    ];

    /**
     * Recursively sanitize data to guarantee passwords, tokens, and secrets are NEVER logged.
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
     * Standardized record() method recording full rich context.
     *
     * @param array $params [
     *     'action' => string (required),
     *     'entity' => string (entity type, e.g. 'Invoice', 'User'),
     *     'entity_id' => int|string,
     *     'company_id' => int (optional, auto-resolved),
     *     'branch_id' => int (optional, auto-resolved),
     *     'user_id' => int (optional, auto-resolved),
     *     'user_name' => string (optional, auto-resolved),
     *     'old_values' => array|object|null,
     *     'new_values' => array|object|null,
     *     'description' => string,
     *     'ip_address' => string (optional, auto-resolved),
     *     'user_agent' => string (optional, auto-resolved),
     *     'status' => string (default: 'SUCCESS'),
     *     'severity' => string (default: 'INFO'),
     * ]
     * @return AuditLog|null
     */
    public static function record(array $params): ?AuditLog
    {
        try {
            $rawAction = strtoupper(trim($params['action'] ?? 'ACTION'));
            $action = static::$actionMap[$rawAction] ?? $rawAction;

            $entityType = $params['entity'] ?? ($params['entity_type'] ?? 'General');
            $entityId = isset($params['entity_id']) ? intval($params['entity_id']) : 0;
            $description = $params['description'] ?? '';

            // Resolve Company Context
            $companyId = !empty($params['company_id']) ? intval($params['company_id']) : 0;
            if ($companyId <= 0) {
                try {
                    $companyId = AuthMiddleware::getTenantId();
                } catch (\Throwable $e) {
                    $companyId = intval($_SESSION['company_id'] ?? 0);
                }
            }

            // Resolve Branch Context
            $branchId = isset($params['branch_id']) ? intval($params['branch_id']) : null;
            if ($branchId === null) {
                try {
                    $branchId = AuthMiddleware::getBranchId();
                } catch (\Throwable $e) {
                    $branchId = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 1;
                }
            }
            if (!$branchId) {
                $branchId = 1;
            }

            // Resolve User Context
            $userId = isset($params['user_id']) ? intval($params['user_id']) : null;
            $userName = $params['user_name'] ?? null;

            if ($userId === null || empty($userName)) {
                try {
                    $u = AuthMiddleware::getUser();
                    if ($u) {
                        if ($userId === null) $userId = $u->id;
                        if (empty($userName)) $userName = $u->name;
                    }
                } catch (\Throwable $e) {}

                if (empty($userName) && !empty($_SESSION['user']['name'])) {
                    $userName = $_SESSION['user']['name'];
                    if ($userId === null && !empty($_SESSION['user']['id'])) {
                        $userId = (int)$_SESSION['user']['id'];
                    }
                }
            }

            $userName = $userName ?: 'System';

            // Resolve IP & User Agent
            $ipAddress = $params['ip_address'] ?? ($params['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ipAddress = trim($parts[0]);
            }
            $userAgent = $params['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'WTSBill-Client');

            // Sanitize values
            $oldValues = !empty($params['old_values']) ? static::sanitizeData($params['old_values']) : (!empty($params['before_data']) ? static::sanitizeData($params['before_data']) : null);
            $newValues = !empty($params['new_values']) ? static::sanitizeData($params['new_values']) : (!empty($params['after_data']) ? static::sanitizeData($params['after_data']) : null);

            // Redact description if sensitive strings present
            if (is_string($description)) {
                $description = preg_replace('/(password|token|secret|jwt)\s*[:=]\s*(\S+)/i', '$1: [REDACTED]', $description);
            }

            return AuditLog::create([
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'user_id'          => $userId,
                'user_name'        => $userName,
                'action'           => $action,
                'entity_type'      => $entityType,
                'entity_id'        => $entityId,
                'description'      => $description ?: "{$action} on {$entityType} #{$entityId}",
                'before_data_json' => $oldValues,
                'after_data_json'  => $newValues,
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
     * Backward-compatible log() interface for existing call sites.
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
        try {
            $userName = 'System';
            $action = 'ACTION';
            $entityType = 'General';
            $entityId = 0;
            $description = '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $oldVal = null;
            $newVal = null;

            // Check signature variants:
            // Signature A: ($companyId, $action, $entityType, $entityId, $description, $oldVal, $newVal, $userName)
            if (is_string($param2) && is_string($param3) && (is_int($param4) || is_null($param4)) && is_string($param5)) {
                $action = strtoupper($param2);
                $entityType = $param3;
                $entityId = $param4 ?: 0;
                $description = $param5;
                $oldVal = $param6;
                $newVal = $param7;
                $userName = is_string($param8) ? $param8 : 'System';
            } else {
                // Signature B: ($companyId, $userName, $action, $entityType, $entityId, $description, $ipAddress)
                $userName = $param2 ?: 'System';
                $action = strtoupper((string)$param3);
                $entityType = (string)$param4;
                $entityId = is_numeric($param5) ? intval($param5) : 0;
                $description = (string)$param6;
                $ipAddress = $param7 ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
                $newVal = is_array($param8) ? $param8 : null;
            }

            static::record([
                'company_id'   => $companyId,
                'user_name'    => $userName,
                'action'       => $action,
                'entity'       => $entityType,
                'entity_id'    => $entityId,
                'description'  => $description,
                'old_values'   => $oldVal,
                'new_values'   => $newVal,
                'ip_address'   => $ipAddress,
            ]);
        } catch (\Throwable $e) {
            error_log("[AuditLogService::log] " . $e->getMessage());
        }
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
            'total_pages'  => ceil($total / max(1, $perPage)),
            'items'        => $items,
        ];
    }
}
