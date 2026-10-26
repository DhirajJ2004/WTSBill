<?php

declare(strict_types=1);

namespace WTSBill\Repositories;

use WTSBill\Core\Database;
use WTSBill\Core\Session;

/**
 * Product Repository for WTSBill ERP.
 * Handles entity persistence, SKU uniqueness checks, search, and catalog queries.
 */
class ProductRepository extends BaseRepository
{
    protected string $table = 'products';
    protected string $primaryKey = 'id';
    protected bool $tenantScoped = true;

    /**
     * Find a product by SKU within the active company scope.
     */
    public function findBySku(string $sku, ?int $excludeId = null): ?array
    {
        $companyId = Session::getCompanyId() ?? 1;
        $sql = "SELECT * FROM `{$this->table}` WHERE `company_id` = ? AND `sku` = ?";
        $params = [$companyId, $sku];

        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
        }

        $sql .= " LIMIT 1";

        return Database::selectOne($sql, $params);
    }

    /**
     * Retrieve products for catalog listing with category details.
     */
    public function getCatalog(string $search = ''): array
    {
        $companyId = Session::getCompanyId() ?? 1;
        $sql = "SELECT p.*, 
                       COALESCE(p.selling_price, p.sales_price, 0) as selling_price,
                       c.name as category_name
                FROM `{$this->table}` p
                LEFT JOIN `categories` c ON p.category_id = c.id
                WHERE p.company_id = ?";
        $params = [$companyId];

        if (!empty(trim($search))) {
            $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.hsn_sac LIKE ? OR p.barcode LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY p.id ASC";

        return Database::select($sql, $params);
    }

    /**
     * Update stock balance for a product.
     */
    public function updateStock(int $productId, float $quantityDelta): bool
    {
        $companyId = Session::getCompanyId() ?? 1;
        $affected = Database::update(
            $this->table,
            ['current_stock' => Database::raw("current_stock + {$quantityDelta}")],
            "id = ? AND company_id = ?",
            [$productId, $companyId]
        );

        return $affected > 0;
    }
}
