<?php

declare(strict_types=1);

namespace WTSBill\Repositories;

use WTSBill\Core\Database;
use WTSBill\Core\Session;

/**
 * Unified Party Repository for WTSBill ERP.
 * Handles unified persistence, multi-role querying, server-side filtering, and pagination.
 */
class PartyRepository extends BaseRepository
{
    protected string $table = 'parties';
    protected string $primaryKey = 'id';
    protected bool $tenantScoped = true;

    /**
     * Find party by ID and company.
     */
    public function findParty(int $id, ?int $companyId = null): ?array
    {
        $cid = $companyId ?? (Session::getCompanyId() ?? 1);
        return Database::selectOne(
            "SELECT * FROM `{$this->table}` WHERE `id` = ? AND `company_id` = ? AND `deleted_at` IS NULL LIMIT 1",
            [$id, $cid]
        );
    }

    /**
     * Find party by party code.
     */
    public function findByCode(string $code, ?int $companyId = null): ?array
    {
        $cid = $companyId ?? (Session::getCompanyId() ?? 1);
        return Database::selectOne(
            "SELECT * FROM `{$this->table}` WHERE `party_code` = ? AND `company_id` = ? AND `deleted_at` IS NULL LIMIT 1",
            [$code, $cid]
        );
    }

    /**
     * Find party by GSTIN.
     */
    public function findByGstin(string $gstin, ?int $companyId = null, ?int $excludeId = null): ?array
    {
        $gstin = strtoupper(trim($gstin));
        if (empty($gstin)) {
            return null;
        }

        $cid = $companyId ?? (Session::getCompanyId() ?? 1);
        $sql = "SELECT * FROM `{$this->table}` WHERE `company_id` = ? AND `gstin` = ? AND `deleted_at` IS NULL";
        $params = [$cid, $gstin];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
        }

        $sql .= " LIMIT 1";
        return Database::selectOne($sql, $params);
    }

    /**
     * Generate next sequential party code.
     */
    public function generatePartyCode(?int $companyId = null, string $type = 'Party'): string
    {
        $cid = $companyId ?? (Session::getCompanyId() ?? 1);
        $maxRow = Database::selectOne(
            "SELECT MAX(id) as max_id FROM `{$this->table}` WHERE `company_id` = ?",
            [$cid]
        );
        $nextId = (int)($maxRow['max_id'] ?? 0) + 1;
        
        $prefix = match (strtolower($type)) {
            'customer' => 'PRT-C',
            'supplier' => 'PRT-S',
            default => 'PRT-',
        };

        return sprintf('%s%04d', $prefix, $nextId);
    }

    /**
     * Execute server-side filtered, searched, and paginated query.
     */
    public function searchParties(array $filters = [], ?int $companyId = null, int $page = 1, int $perPage = 25): array
    {
        $cid = $companyId ?? (Session::getCompanyId() ?? 1);
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["company_id = ?", "deleted_at IS NULL"];
        $params = [$cid];

        // 1. Party Type Filter
        $type = strtolower(trim((string)($filters['type'] ?? 'all')));
        if ($type === 'customer' || $type === 'customers') {
            $where[] = "(party_type = 'Customer' OR party_type = 'Both')";
        } elseif ($type === 'supplier' || $type === 'suppliers') {
            $where[] = "(party_type = 'Supplier' OR party_type = 'Both')";
        } elseif ($type === 'both') {
            $where[] = "party_type = 'Both'";
        }

        // 2. Active / Inactive Status Filter
        $status = strtolower(trim((string)($filters['status'] ?? 'all')));
        if ($status === 'active') {
            $where[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "is_active = 0";
        }

        // 3. City Filter
        $city = trim((string)($filters['city'] ?? ''));
        if (!empty($city) && $city !== 'all') {
            $where[] = "city = ?";
            $params[] = $city;
        }

        // 4. State Filter
        $state = trim((string)($filters['state'] ?? ''));
        if (!empty($state) && $state !== 'all') {
            $where[] = "state = ?";
            $params[] = $state;
        }

        // 5. Outstanding Balance Filter
        $balance = strtolower(trim((string)($filters['balance'] ?? 'all')));
        if ($balance === 'has_outstanding') {
            $where[] = "ABS(current_balance) > 0.01";
        } elseif ($balance === 'zero_balance') {
            $where[] = "ABS(current_balance) <= 0.01";
        } elseif ($balance === 'debit') {
            $where[] = "current_balance > 0.01";
        } elseif ($balance === 'credit') {
            $where[] = "current_balance < -0.01";
        }

        // 6. Search Bar & Specific Criteria
        $query = trim((string)($filters['q'] ?? ($filters['search'] ?? '')));
        $searchBy = strtolower(trim((string)($filters['search_by'] ?? 'all')));

        if (!empty($query)) {
            $wild = '%' . $query . '%';
            if ($searchBy === 'name') {
                $where[] = "(name LIKE ? OR company_name LIKE ?)";
                $params[] = $wild;
                $params[] = $wild;
            } elseif ($searchBy === 'phone') {
                $where[] = "(phone LIKE ? OR alt_phone LIKE ?)";
                $params[] = $wild;
                $params[] = $wild;
            } elseif ($searchBy === 'email') {
                $where[] = "email LIKE ?";
                $params[] = $wild;
            } elseif ($searchBy === 'gstin') {
                $where[] = "(gstin LIKE ? OR pan LIKE ?)";
                $params[] = $wild;
                $params[] = $wild;
            } elseif ($searchBy === 'party_code' || $searchBy === 'code') {
                $where[] = "party_code LIKE ?";
                $params[] = $wild;
            } else {
                // All Fields search
                $where[] = "(name LIKE ? OR company_name LIKE ? OR party_code LIKE ? OR phone LIKE ? OR alt_phone LIKE ? OR email LIKE ? OR gstin LIKE ? OR pan LIKE ? OR city LIKE ? OR contact_person LIKE ?)";
                for ($i = 0; $i < 10; $i++) {
                    $params[] = $wild;
                }
            }
        }

        $whereClause = implode(" AND ", $where);

        // Compute overall counts for quick filter pills
        $countsRow = Database::selectOne(
            "SELECT 
                COUNT(*) as count_all,
                SUM(CASE WHEN party_type = 'Customer' OR party_type = 'Both' THEN 1 ELSE 0 END) as count_customers,
                SUM(CASE WHEN party_type = 'Supplier' OR party_type = 'Both' THEN 1 ELSE 0 END) as count_suppliers,
                SUM(CASE WHEN party_type = 'Both' THEN 1 ELSE 0 END) as count_both
             FROM `{$this->table}`
             WHERE company_id = ? AND deleted_at IS NULL",
            [$cid]
        );

        // Total matching current query
        $totalRow = Database::selectOne(
            "SELECT COUNT(*) as count FROM `{$this->table}` WHERE {$whereClause}",
            $params
        );
        $total = (int)($totalRow['count'] ?? 0);
        $totalPages = (int)ceil($total / $perPage);

        // Fetch paginated dataset
        $sql = "SELECT * FROM `{$this->table}` WHERE {$whereClause} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";
        $data = Database::select($sql, $params);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'counts' => [
                'all' => (int)($countsRow['count_all'] ?? 0),
                'customers' => (int)($countsRow['count_customers'] ?? 0),
                'suppliers' => (int)($countsRow['count_suppliers'] ?? 0),
                'both' => (int)($countsRow['count_both'] ?? 0),
            ],
            'filters' => [
                'type' => $type,
                'q' => $query,
                'search_by' => $searchBy,
                'city' => $city,
                'state' => $state,
                'status' => $status,
                'balance' => $balance,
            ]
        ];
    }

    /**
     * Get distinct cities for dropdown filtering.
     */
    public function getDistinctCities(?int $companyId = null): array
    {
        $cid = $companyId ?? (Session::getCompanyId() ?? 1);
        $rows = Database::select(
            "SELECT DISTINCT city FROM `{$this->table}` WHERE company_id = ? AND city IS NOT NULL AND city != '' AND deleted_at IS NULL ORDER BY city ASC",
            [$cid]
        );
        return array_filter(array_column($rows, 'city'));
    }
}

