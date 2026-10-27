<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Session;

/**
 * Enterprise Audit Logging Service.
 * Records immutable audit trails for sensitive record mutations, administrative actions,
 * authentication events, and profile configuration changes.
 */
class AuditService
{
    /**
     * Record an audit log entry.
     */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $beforeData = null,
        ?array $afterData = null,
        string $severity = 'INFO',
        string $status = 'SUCCESS'
    ): int {
        try {
            $companyId = (int)(Session::getCompanyId() ?? 1);
            $branchId = (int)(Session::getBranchId() ?? 1);
            $user = Session::getUser() ?? Session::get('auth_user') ?? [];
            $userId = (int)($user['id'] ?? (Session::get('user_id') ?? 1));
            $userName = (string)($user['name'] ?? (Session::get('user_name') ?? 'Super Administrator'));

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'WTSBill-Client/1.0'), 0, 250);
            $correlationId = bin2hex(random_bytes(16));

            return (int)Database::insert('audit_logs', [
                'company_id' => $companyId,
                'branch_id' => $branchId ?: null,
                'user_id' => $userId ?: null,
                'user_name' => $userName,
                'action' => strtoupper(trim($action)),
                'entity_type' => strtoupper(trim($entityType)),
                'entity_id' => $entityId,
                'description' => $description,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'correlation_id' => $correlationId,
                'before_data_json' => $beforeData !== null ? json_encode($beforeData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'after_data_json' => $afterData !== null ? json_encode($afterData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'metadata_json' => json_encode([
                    'session_id' => session_id(),
                    'uri' => $_SERVER['REQUEST_URI'] ?? '/settings',
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
                ]),
                'status' => strtoupper($status),
                'severity' => strtoupper($severity),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('Audit log recording failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Query audit logs with pagination and filters.
     */
    public static function getLogs(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $where = ["company_id = ?"];
        $params = [$companyId];

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(description LIKE ? OR user_name LIKE ? OR action LIKE ? OR entity_type LIKE ? OR ip_address LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['action'])) {
            $where[] = "action = ?";
            $params[] = strtoupper(trim((string)$filters['action']));
        }

        if (!empty($filters['entity_type'])) {
            $where[] = "entity_type = ?";
            $params[] = strtoupper(trim((string)$filters['entity_type']));
        }

        if (!empty($filters['severity'])) {
            $where[] = "severity = ?";
            $params[] = strtoupper(trim((string)$filters['severity']));
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = trim((string)$filters['date_to']);
        }

        $whereSql = implode(' AND ', $where);

        $countRow = Database::selectOne("SELECT COUNT(*) as total FROM audit_logs WHERE {$whereSql}", $params);
        $total = (int)($countRow['total'] ?? 0);

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT id, company_id, branch_id, user_id, user_name, action, entity_type, entity_id, description, ip_address, status, severity, created_at, before_data_json, after_data_json, metadata_json 
                FROM audit_logs 
                WHERE {$whereSql} 
                ORDER BY id DESC 
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = Database::select($sql, $params);

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int)ceil($total / max(1, $perPage)),
        ];
    }
}
