<?php

declare(strict_types=1);

namespace WTSBill\Repositories;

use WTSBill\Core\Database;
use WTSBill\Core\Session;
use WTSBill\Exceptions\DatabaseException;

/**
 * Base Repository for WTSBill ERP Data Access Layer.
 * Provides parameterized CRUD, pagination, and transparent multi-tenant isolation.
 */
abstract class BaseRepository
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool $tenantScoped = true;

    /**
     * Find a record by primary key.
     */
    public function find(int|string $id, array $columns = ['*']): ?array
    {
        $cols = implode(', ', array_map(fn($c) => $c === '*' ? '*' : "`{$c}`", $columns));
        $criteria = [$this->primaryKey => $id];
        $this->applyTenantScope($criteria);

        $whereClauses = [];
        $bindings = [];
        foreach ($criteria as $col => $val) {
            $param = 'w_' . str_replace('.', '_', $col);
            $whereClauses[] = "`{$col}` = :{$param}";
            $bindings[$param] = $val;
        }

        $sql = sprintf(
            'SELECT %s FROM `%s` WHERE %s LIMIT 1',
            $cols,
            $this->table,
            implode(' AND ', $whereClauses)
        );

        return Database::selectOne($sql, $bindings);
    }

    /**
     * Retrieve all matching records with tenant scoping.
     */
    public function all(array $columns = ['*'], string $orderBy = 'id ASC'): array
    {
        return $this->where([], $columns, $orderBy);
    }

    /**
     * Retrieve records matching arbitrary criteria with prepared parameter binding.
     */
    public function where(array $criteria = [], array $columns = ['*'], string $orderBy = 'id ASC'): array
    {
        $this->applyTenantScope($criteria);
        $cols = implode(', ', array_map(fn($c) => $c === '*' ? '*' : "`{$c}`", $columns));

        $whereClauses = [];
        $bindings = [];
        foreach ($criteria as $col => $val) {
            if ($val === null) {
                $whereClauses[] = "`{$col}` IS NULL";
            } else {
                $param = 'w_' . str_replace('.', '_', (string) $col);
                $whereClauses[] = "`{$col}` = :{$param}";
                $bindings[$param] = $val;
            }
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
        $sql = sprintf(
            'SELECT %s FROM `%s` %s ORDER BY %s',
            $cols,
            $this->table,
            $whereSql,
            $orderBy
        );

        return Database::select($sql, $bindings);
    }

    /**
     * Retrieve the first record matching criteria.
     */
    public function whereOne(array $criteria = [], array $columns = ['*']): ?array
    {
        $results = $this->where($criteria, $columns, "{$this->primaryKey} ASC LIMIT 1");
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Insert a record and return the generated ID.
     */
    public function create(array $data): int|string
    {
        if ($this->tenantScoped && !isset($data['company_id'])) {
            $companyId = Session::getCompanyId() ?? 1;
            $data['company_id'] = $companyId;
        }

        return Database::insert($this->table, $data);
    }

    /**
     * Update a record by primary key.
     */
    public function update(int|string $id, array $data): int
    {
        $whereCriteria = [$this->primaryKey => $id];
        $this->applyTenantScope($whereCriteria);

        $whereClauses = [];
        $whereBindings = [];
        foreach ($whereCriteria as $col => $val) {
            $param = 'w_' . str_replace('.', '_', (string) $col);
            $whereClauses[] = "`{$col}` = :{$param}";
            $whereBindings[$param] = $val;
        }

        return Database::update(
            $this->table,
            $data,
            implode(' AND ', $whereClauses),
            $whereBindings
        );
    }

    /**
     * Delete a record by primary key.
     */
    public function delete(int|string $id): int
    {
        $whereCriteria = [$this->primaryKey => $id];
        $this->applyTenantScope($whereCriteria);

        $whereClauses = [];
        $whereBindings = [];
        foreach ($whereCriteria as $col => $val) {
            $param = 'w_' . str_replace('.', '_', (string) $col);
            $whereClauses[] = "`{$col}` = :{$param}";
            $whereBindings[$param] = $val;
        }

        return Database::delete(
            $this->table,
            implode(' AND ', $whereClauses),
            $whereBindings
        );
    }

    /**
     * Count matching records.
     */
    public function count(array $criteria = []): int
    {
        $this->applyTenantScope($criteria);

        $whereClauses = [];
        $bindings = [];
        foreach ($criteria as $col => $val) {
            $param = 'w_' . str_replace('.', '_', (string) $col);
            $whereClauses[] = "`{$col}` = :{$param}";
            $bindings[$param] = $val;
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
        $sql = sprintf('SELECT COUNT(*) as aggregate_count FROM `%s` %s', $this->table, $whereSql);

        $result = Database::selectOne($sql, $bindings);
        return (int) ($result['aggregate_count'] ?? 0);
    }

    /**
     * Paginate records.
     */
    public function paginate(int $page = 1, int $perPage = 25, array $criteria = [], string $orderBy = 'id DESC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = $this->count($criteria);
        $totalPages = (int) ceil($total / $perPage);

        $this->applyTenantScope($criteria);

        $whereClauses = [];
        $bindings = [];
        foreach ($criteria as $col => $val) {
            $param = 'w_' . str_replace('.', '_', (string) $col);
            $whereClauses[] = "`{$col}` = :{$param}";
            $bindings[$param] = $val;
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
        $sql = sprintf(
            'SELECT * FROM `%s` %s ORDER BY %s LIMIT %d OFFSET %d',
            $this->table,
            $whereSql,
            $orderBy,
            $perPage,
            $offset
        );

        $items = Database::select($sql, $bindings);

        return [
            'data' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_more' => $page < $totalPages,
        ];
    }

    /**
     * Enforce tenant isolation if tenantScoped is enabled.
     */
    protected function applyTenantScope(array &$criteria): void
    {
        if ($this->tenantScoped && !isset($criteria['company_id'])) {
            $companyId = Session::getCompanyId() ?? 1;
            $criteria['company_id'] = $companyId;
        }
    }

    public function getTable(): string
    {
        return $this->table;
    }
}

// Backward compatibility alias
if (!class_exists('App\Repositories\BaseRepository', false)) {
    class_alias(BaseRepository::class, 'App\Repositories\BaseRepository');
}
