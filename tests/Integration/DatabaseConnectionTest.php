<?php

declare(strict_types=1);

namespace Tests\Integration;

use WTSBill\Core\Database;
use WTSBill\Exceptions\DatabaseException;
use PDO;

/**
 * Integration Test for Database Connection, Query Compatibility, and Baseline Integrity.
 */
class DatabaseConnectionTest
{
    public static function run(callable $assert): void
    {
        // 1. Connection established
        $pdo = Database::pdo();
        $assert('DBCONN-01', 'Live PDO connection is active', $pdo instanceof PDO);
        $assert('DBCONN-02', 'Database::isConnected() is true', Database::isConnected());

        // 2. PDO Attributes
        $errMode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
        $assert('DBCONN-03', 'PDO error mode is ERRMODE_EXCEPTION', $errMode === PDO::ERRMODE_EXCEPTION);

        $fetchMode = $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
        $assert('DBCONN-04', 'PDO default fetch mode is FETCH_ASSOC', $fetchMode === PDO::FETCH_ASSOC);

        // 3. Character Set & Collation on connection
        $charsetRow = Database::selectOne("SHOW VARIABLES LIKE 'character_set_connection'");
        $assert('DBCONN-05', 'Connection character set is utf8mb4', ($charsetRow['Value'] ?? '') === 'utf8mb4');

        // 4. Simulated connection failure & credential redaction
        $badConfig = [
            'host' => '127.0.0.1',
            'port' => 33999, // Unused port to trigger connection error
            'database' => 'non_existent_db_xyz',
            'username' => 'fake_user_test',
            'password' => 'secret_password_must_not_leak_123',
            'charset' => 'utf8mb4',
        ];

        $failedCleanly = false;
        $noLeak = false;

        try {
            $badPdo = Database::connect($badConfig);
        } catch (\Throwable $e) {
            $failedCleanly = true;
            $msg = $e->getMessage();
            // Verify password was NOT leaked in exception message
            $noLeak = !str_contains($msg, 'secret_password_must_not_leak_123');
        }

        $assert('DBCONN-06', 'Connection failure throws safe exception', $failedCleanly === true);
        $assert('DBCONN-07', 'Exception message redacts sensitive credentials', $noLeak === true);

        // Reconnect to active DB
        Database::disconnect();
        $pdo = Database::pdo();
        $assert('DBCONN-08', 'Reconnected cleanly after test disconnect', Database::isConnected());

        // 5. Baseline Data Comparison & Verification
        // Users
        $admin = Database::selectOne("SELECT id, name, email, role, status FROM users WHERE id = :id", ['id' => 1]);
        $assert('DBCONN-09', 'Admin user verified in baseline', 
            $admin !== null && $admin['email'] === 'anil.d@wtsbill.in' && $admin['name'] === 'Anil Desai');

        // Company
        $company = Database::selectOne("SELECT id, name, gstin, state_code FROM companies WHERE id = :id", ['id' => 1]);
        $assert('DBCONN-10', 'Tenant company verified in baseline', 
            $company !== null && $company['gstin'] === '27AADCW7577N1ZE' && $company['state_code'] === '27');

        // Branches
        $branches = Database::select("SELECT id, branch_code FROM branches ORDER BY id ASC");
        $assert('DBCONN-11', 'Two operating branches verified', count($branches) === 2);

        // Tax Rates
        $taxRates = Database::select("SELECT id, rate FROM tax_rates ORDER BY rate ASC");
        $rates = array_map(fn($r) => (float) $r['rate'], $taxRates);
        $assert('DBCONN-12', '5 standard GST tax rates verified (0, 5, 12, 18, 28)', 
            count($taxRates) === 5 && in_array(18.0, $rates, true) && in_array(0.0, $rates, true));

        // Products count
        $productCount = (int) Database::selectOne("SELECT COUNT(*) as cnt FROM products")['cnt'];
        $assert('DBCONN-13', '50 baseline products catalog verified', $productCount === 50);

        // Financial Decimal Precision
        $sampleInvoice = Database::selectOne("SELECT invoice_number, grand_total, total_tax FROM invoices WHERE id = 1");
        $assert('DBCONN-14', 'Invoice grand_total preserves 2 decimal format', 
            $sampleInvoice !== null && $sampleInvoice['grand_total'] === '67260.00' && $sampleInvoice['total_tax'] === '10260.00');

        // Safe parameter binding (SQL Injection immunity test)
        $maliciousInput = "anil.d@wtsbill.in' OR '1'='1";
        $injectionResult = Database::selectOne("SELECT id, email FROM users WHERE email = :email", ['email' => $maliciousInput]);
        $assert('DBCONN-15', 'SQL injection string does not match any record', $injectionResult === null);
    }
}
