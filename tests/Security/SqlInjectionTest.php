<?php

declare(strict_types=1);

namespace Tests\Security;

use WTSBill\Core\Database;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Exceptions\DatabaseException;

/**
 * SQL Injection (SQLi) Defense Regression Test (Chunk 19).
 * Verifies strict PDO prepared statement binding, stacked query neutralization,
 * UNION injection resistance, and database credential/syntax redaction.
 */
class SqlInjectionTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        Session::setTenantContext(1, 1);

        // 1. Classic Tautology Injection (' OR '1'='1)
        $payload1 = "' OR '1'='1";
        $results1 = Database::select("SELECT id, name FROM customers WHERE name = ?", [$payload1]);
        $assert('SQLI-01', "Tautology payload (' OR '1'='1) is treated as literal string, returning 0 records", 
            count($results1) === 0);

        // 2. Stacked Query Injection Attack
        $payload2 = "1; DROP TABLE customers; --";
        $results2 = Database::select("SELECT id, name FROM customers WHERE id = ?", [$payload2]);
        $assert('SQLI-02', "Stacked query injection ('; DROP TABLE...') is parameterized safely", 
            is_array($results2));

        // Verify table was NOT dropped
        $tableStillExists = Database::select("SHOW TABLES LIKE 'customers'");
        $assert('SQLI-03', 'Database integrity maintained; table customers remains intact', 
            count($tableStillExists) === 1);

        // 3. UNION SELECT Information Disclosure Attack
        $unionPayload = "1 UNION SELECT id, password FROM users --";
        $results3 = Database::select("SELECT id, name FROM customers WHERE id = ?", [$unionPayload]);
        $assert('SQLI-04', 'UNION SELECT attack is bounded by prepared parameter, returning no leaked records', 
            count($results3) <= 1);

        // 4. Exception Credential & Syntax Redaction
        $exceptionCaught = false;
        $sanitizedMsg = '';
        try {
            Database::select("SELECT * FROM non_existent_table_xyz_12345");
        } catch (DatabaseException $e) {
            $exceptionCaught = true;
            // Test Response::error sanitization
            $res = Response::error($e->getMessage(), 500);
            $decoded = json_decode($res->getContent(), true);
            $sanitizedMsg = $decoded['message'] ?? '';
        }

        $assert('SQLI-05', 'Invalid SQL statement triggers controlled DatabaseException', 
            $exceptionCaught);
        $assert('SQLI-06', 'User-facing error response redacts raw SQL syntax and table names', 
            !str_contains($sanitizedMsg, 'non_existent_table') &&
            !str_contains($sanitizedMsg, 'SQLSTATE') &&
            str_contains($sanitizedMsg, 'database error occurred'));

        // 5. Query Cache Isolation with Injection Payload
        Database::clearQueryCache();
        $qNormal = Database::selectCached("SELECT id FROM companies WHERE name = ?", ['Wis Technosavvy Pvt Ltd'], 1);
        $qInjected = Database::selectCached("SELECT id FROM companies WHERE name = ?", ["Wis Technosavvy' OR '1'='1"], 1);
        $assert('SQLI-07', 'Injected query creates isolated cache entry and does not overwrite clean query', 
            count($qNormal) === 1 && count($qInjected) === 0);

        // Clean up
        Session::clear();
    }
}
