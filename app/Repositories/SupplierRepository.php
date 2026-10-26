<?php

declare(strict_types=1);

namespace WTSBill\Repositories;

use WTSBill\Core\Database;
use WTSBill\Core\Session;

/**
 * Supplier Repository for WTSBill ERP.
 * Handles supplier persistence, search, GSTIN uniqueness checks, and balances.
 */
class SupplierRepository extends BaseRepository
{
    protected string $table = 'suppliers';
    protected string $primaryKey = 'id';
    protected bool $tenantScoped = true;

    /**
     * Find supplier by GSTIN within current tenant.
     */
    public function findByGstin(string $gstin, ?int $excludeId = null): ?array
    {
        $gstin = strtoupper(trim($gstin));
        if (empty($gstin)) {
            return null;
        }

        $companyId = Session::getCompanyId() ?? 1;
        $sql = "SELECT * FROM `{$this->table}` WHERE `company_id` = ? AND `gstin` = ?";
        $params = [$companyId, $gstin];

        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
        }

        $sql .= " LIMIT 1";

        return Database::selectOne($sql, $params);
    }

    /**
     * Search suppliers for listing or autocomplete.
     */
    public function search(string $term = ''): array
    {
        $companyId = Session::getCompanyId() ?? 1;
        $sql = "SELECT id, name, company_name, gstin, phone, email, city, state, opening_balance, current_balance, is_active 
                FROM `{$this->table}` 
                WHERE company_id = ?";
        $params = [$companyId];

        if (!empty(trim($term))) {
            $sql .= " AND (name LIKE ? OR company_name LIKE ? OR gstin LIKE ? OR phone LIKE ? OR city LIKE ?)";
            $q = '%' . trim($term) . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql .= " ORDER BY id ASC";

        return Database::select($sql, $params);
    }
}
