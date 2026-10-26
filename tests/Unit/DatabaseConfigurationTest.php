<?php

declare(strict_types=1);

namespace Tests\Unit;

use RuntimeException;
use WTSBill\Core\Config;
use WTSBill\Core\Database;
use PDO;

/**
 * Unit Test for Database Configuration and Parameter Validation.
 */
class DatabaseConfigurationTest
{
    public static function run(callable $assert): void
    {
        $rootPath = dirname(__DIR__, 2);
        Config::loadFromDirectory($rootPath . '/config');

        // 1. Config loading
        $defaultConn = Config::get('database.default');
        $assert('DBCFG-01', 'Default database connection is mysql', $defaultConn === 'mysql');

        $mysqlConfig = Config::get('database.connections.mysql');
        $assert('DBCFG-02', 'MySQL config array exists', is_array($mysqlConfig));
        $assert('DBCFG-03', 'Database name matches wtsbill_db', ($mysqlConfig['database'] ?? '') === 'wtsbill_db');
        $assert('DBCFG-04', 'Database host is configured', !empty($mysqlConfig['host']));
        $assert('DBCFG-05', 'Database charset is utf8mb4', ($mysqlConfig['charset'] ?? '') === 'utf8mb4');

        // 2. PDO Options verification
        $options = $mysqlConfig['options'] ?? [];
        $assert('DBCFG-06', 'PDO error mode is ERRMODE_EXCEPTION', ($options[PDO::ATTR_ERRMODE] ?? null) === PDO::ERRMODE_EXCEPTION);
        $assert('DBCFG-07', 'PDO fetch mode is FETCH_ASSOC', ($options[PDO::ATTR_DEFAULT_FETCH_MODE] ?? null) === PDO::FETCH_ASSOC);
        $assert('DBCFG-08', 'Emulate prepares is false', ($options[PDO::ATTR_EMULATE_PREPARES] ?? null) === false);

        // 3. Database::validateConfig() valid case
        $validConfig = [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass',
        ];
        $passed = true;
        try {
            Database::validateConfig($validConfig);
        } catch (\Throwable $e) {
            $passed = false;
        }
        $assert('DBCFG-09', 'validateConfig passes valid parameters', $passed === true);

        // 4. Missing host
        $missingHost = false;
        try {
            Database::validateConfig(['database' => 'db', 'username' => 'u']);
        } catch (RuntimeException $e) {
            $missingHost = str_contains($e->getMessage(), 'host');
        }
        $assert('DBCFG-10', 'validateConfig catches missing host', $missingHost === true);

        // 5. Missing database name
        $missingDb = false;
        try {
            Database::validateConfig(['host' => 'localhost', 'username' => 'u']);
        } catch (RuntimeException $e) {
            $missingDb = str_contains($e->getMessage(), 'database');
        }
        $assert('DBCFG-11', 'validateConfig catches missing database name', $missingDb === true);

        // 6. Missing username
        $missingUser = false;
        try {
            Database::validateConfig(['host' => 'localhost', 'database' => 'db']);
        } catch (RuntimeException $e) {
            $missingUser = str_contains($e->getMessage(), 'username');
        }
        $assert('DBCFG-12', 'validateConfig catches missing username', $missingUser === true);

        // 7. Invalid port
        $invalidPort = false;
        try {
            Database::validateConfig(['host' => 'localhost', 'database' => 'db', 'username' => 'u', 'port' => 999999]);
        } catch (RuntimeException $e) {
            $invalidPort = str_contains($e->getMessage(), 'invalid port');
        }
        $assert('DBCFG-13', 'validateConfig catches invalid port number', $invalidPort === true);
    }
}
