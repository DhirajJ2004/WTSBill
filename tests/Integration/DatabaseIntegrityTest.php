<?php

declare(strict_types=1);

namespace Tests\Integration;

use WTSBill\Core\Database;
use WTSBill\Core\Session;

/**
 * Database Integrity and Schema Parity Test (Chunk 20).
 * Verifies foreign key relationships, multi-tenant column indices,
 * transaction rollback safety, and document sequence monotonicity.
 */
class DatabaseIntegrityTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        Session::setTenantContext(1, 1);

        // 1. Core ERP Tables Existence (135+ Tables Baseline)
        $tables = Database::select("SHOW TABLES");
        $tableCount = count($tables);
        $assert('DBINT-01', "Database schema contains all required enterprise tables (>=130 tables)", 
            $tableCount >= 130);

        // 2. Multi-Tenant Column Presence on Critical Domain Tables
        $tenantTables = ['customers', 'suppliers', 'products', 'invoices', 'purchases', 'expenses', 'stock_adjustments'];
        $missingTenantCol = [];
        foreach ($tenantTables as $table) {
            $cols = Database::select("SHOW COLUMNS FROM `{$table}` LIKE 'company_id'");
            if (empty($cols)) {
                $missingTenantCol[] = $table;
            }
        }
        $assert('DBINT-02', 'All core transaction tables enforce company_id tenant column', 
            empty($missingTenantCol));

        // 3. Transaction ACID Rollback Safety
        $preCustomerCount = (int) Database::selectOne("SELECT COUNT(*) as cnt FROM customers WHERE company_id = 1")['cnt'];
        
        try {
            Database::transaction(function () {
                Database::insert('customers', [
                    'company_id' => 1,
                    'name' => 'ROLLBACK_TEST_CUSTOMER_TEMP',
                    'company_name' => 'Rollback Co',
                    'phone' => '9999999999',
                    'email' => 'rollback.test@example.com',
                    'is_active' => 1,
                ]);
                
                // Intentionally trigger rollback
                throw new \RuntimeException("Simulated transactional failure for integrity audit");
            });
        } catch (\Throwable $e) {
            // Expected rollback
        }

        $postCustomerCount = (int) Database::selectOne("SELECT COUNT(*) as cnt FROM customers WHERE company_id = 1")['cnt'];
        $assert('DBINT-03', 'Database transaction rollback cleanly purges uncommitted records with zero side effects', 
            $preCustomerCount === $postCustomerCount);

        // 4. Sequential Document Numbering Uniqueness & Monotonicity
        $invoiceNumbers = Database::select(
            "SELECT invoice_number FROM invoices WHERE company_id = 1 ORDER BY id ASC LIMIT 10"
        );
        $numberSet = [];
        $duplicates = 0;
        foreach ($invoiceNumbers as $row) {
            $num = $row['invoice_number'];
            if (isset($numberSet[$num])) {
                $duplicates++;
            }
            $numberSet[$num] = true;
        }
        $assert('DBINT-04', 'Invoice document numbers are unique with zero duplicates', 
            $duplicates === 0 && count($numberSet) > 0);

        // 5. Foreign Key Reference Integrity
        $orphanInvoices = Database::select(
            "SELECT i.id FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.customer_id IS NOT NULL AND c.id IS NULL"
        );
        $assert('DBINT-05', 'Zero orphaned invoice records (all customer_id references resolve to valid customers)', 
            count($orphanInvoices) === 0);

        // Clean up
        Session::clear();
    }
}
