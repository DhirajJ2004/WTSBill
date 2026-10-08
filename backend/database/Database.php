<?php

namespace App\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

class Database
{
    private static ?Capsule $capsule = null;
    private static ?\Throwable $initError = null;

    /**
     * Required PHP extensions for WTSBill ERP runtime
     */
    public const REQUIRED_EXTENSIONS = [
        'pdo'       => 'PDO (PHP Data Objects)',
        'pdo_mysql' => 'PDO MySQL Driver (pdo_mysql)',
        'mbstring'  => 'Multibyte String (mbstring)',
        'json'      => 'JSON Parser & Encoder (json)',
        'openssl'   => 'OpenSSL Cryptography (openssl)',
        'curl'      => 'cURL Client (curl)',
        'fileinfo'  => 'File Information (fileinfo)',
    ];

    /**
     * Detect the active PHP executable path dynamically
     */
    public static function getPhpExecutable(): string
    {
        // 1. Check PHP_BINARY constant
        if (defined('PHP_BINARY') && !empty(PHP_BINARY) && @is_file(PHP_BINARY)) {
            return PHP_BINARY;
        }

        // 2. Check environment variables
        $envBinary = getenv('PHP_BINARY') ?: getenv('PHP_PATH') ?: ($_ENV['PHP_BINARY'] ?? null);
        if ($envBinary && @is_file($envBinary)) {
            return $envBinary;
        }

        // 3. Search system PATH via where (Windows) or which (Unix)
        $isWin = stripos(PHP_OS, 'WIN') === 0;
        $cmd = $isWin ? 'where.exe php 2>nul' : 'which php 2>/dev/null';
        $output = [];
        $returnVar = 0;
        @exec($cmd, $output, $returnVar);
        if ($returnVar === 0 && !empty($output[0]) && @is_file(trim($output[0]))) {
            return trim($output[0]);
        }

        // 4. Check standard installation directories
        $candidates = [
            'C:\\xampp\\php\\php.exe',
            'C:\\php\\php.exe',
            'C:\\Program Files\\PHP\\php.exe',
            'C:\\tools\\php\\php.exe',
            'D:\\xampp\\php\\php.exe',
            'E:\\xampp\\php\\php.exe',
            '/usr/bin/php',
            '/usr/local/bin/php',
            '/opt/homebrew/bin/php',
        ];
        foreach ($candidates as $candidate) {
            if (@is_file($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }

    /**
     * Verify all required PHP extensions and PDO MySQL driver availability
     *
     * @return array{ok: bool, missing: array<string, string>, errors: string[]}
     */
    public static function checkRequirements(): array
    {
        $missing = [];
        $errors = [];

        foreach (self::REQUIRED_EXTENSIONS as $ext => $name) {
            if (!extension_loaded($ext)) {
                $missing[$ext] = $name;
            }
        }

        // Check specifically if PDO has mysql driver registered
        if (extension_loaded('pdo')) {
            $drivers = \PDO::getAvailableDrivers();
            if (!in_array('mysql', $drivers, true)) {
                $missing['pdo_mysql'] = self::REQUIRED_EXTENSIONS['pdo_mysql'];
                $errors[] = "PDO driver 'mysql' is not available. Loaded PDO drivers: " . (empty($drivers) ? 'none' : implode(', ', $drivers));
            }
        }

        return [
            'ok' => empty($missing) && empty($errors),
            'missing' => $missing,
            'errors' => $errors,
        ];
    }

    /**
     * Load environment variables from .env if not already loaded
     */
    public static function loadEnvironment(): void
    {
        $possiblePaths = [
            __DIR__ . '/../.env',
            __DIR__ . '/../../.env',
            dirname(__DIR__, 2) . '/.env',
        ];

        foreach ($possiblePaths as $envPath) {
            if (file_exists($envPath)) {
                $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if (empty($trimmed) || strpos($trimmed, '#') === 0) {
                            continue;
                        }
                        if (strpos($line, '=') !== false) {
                            [$name, $value] = explode('=', $line, 2);
                            $name = trim($name);
                            $value = trim($value, "\"'\r\n ");
                            if (!isset($_ENV[$name]) || $_ENV[$name] === '') {
                                $_ENV[$name] = $value;
                            }
                            if (getenv($name) === false) {
                                putenv("{$name}={$value}");
                            }
                        }
                    }
                }
                break;
            }
        }
    }

    /**
     * Initialize Capsule database connection with comprehensive validation and error handling
     *
     * @throws \RuntimeException
     */
    public static function init(?array $customConfig = null): Capsule
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }

        // 1. Ensure environment variables are loaded
        self::loadEnvironment();

        // 2. Proactively verify PHP runtime extensions
        $reqCheck = self::checkRequirements();
        if (!$reqCheck['ok']) {
            $iniFile = php_ini_loaded_file() ?: 'php.ini';
            $isAppDebug = filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);

            if (isset($reqCheck['missing']['pdo_mysql'])) {
                $drivers = extension_loaded('pdo') ? \PDO::getAvailableDrivers() : [];
                $driverList = empty($drivers) ? 'none' : implode(', ', $drivers);

                $msg = "Database Runtime Error: The PHP MySQL PDO driver ('pdo_mysql') is missing or disabled.\n";
                if ($isAppDebug || php_sapi_name() === 'cli') {
                    $msg .= "Required Extension: pdo_mysql\n";
                    $msg .= "Available PDO Drivers: {$driverList}\n";
                    $msg .= "Loaded php.ini: {$iniFile}\n\n";
                    $msg .= "To resolve this in PHP / XAMPP:\n";
                    $msg .= "1. Open your php.ini file: {$iniFile}\n";
                    $msg .= "2. Locate and uncomment (remove leading ';'): extension=pdo_mysql\n";
                    $msg .= "3. Ensure extension_dir = \"ext\" points to valid extension binaries.\n";
                    $msg .= "4. Restart your Apache/Web server and MySQL service.\n";
                } else {
                    $msg .= "Please contact your system administrator to enable the required database driver.";
                }

                $ex = new \RuntimeException($msg);
                self::$initError = $ex;
                error_log("[WTSBill DB Error] " . $msg);
                throw $ex;
            }

            $missingList = implode(', ', array_values($reqCheck['missing']));
            $msg = "Required PHP extensions missing: {$missingList}.\nLoaded php.ini: {$iniFile}\nPlease enable these extensions in php.ini and restart your web server.";
            $ex = new \RuntimeException($msg);
            self::$initError = $ex;
            error_log("[WTSBill DB Error] " . $msg);
            throw $ex;
        }

        // 3. Load database configuration
        $config = $customConfig ?? (require __DIR__ . '/../config/database.php');
        $mysqlConfig = $config['connections'][$config['default'] ?? 'mysql'] ?? ($config['connections']['mysql'] ?? []);

        // 4. Create and configure Illuminate Capsule
        $capsule = new Capsule();
        $capsule->addConnection($mysqlConfig);

        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // 5. Test connection eagerly
        try {
            $pdo = $capsule->getConnection()->getPdo();
            if (!$pdo instanceof \PDO) {
                throw new \RuntimeException("Failed to obtain a valid PDO instance from database connection.");
            }
        } catch (\Throwable $e) {
            $isAppDebug = filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
            $host = $mysqlConfig['host'] ?? '127.0.0.1';
            $port = $mysqlConfig['port'] ?? '3306';
            $database = $mysqlConfig['database'] ?? 'wtsbill_db';
            $username = $mysqlConfig['username'] ?? 'root';

            error_log("[WTSBill DB Connection Error] " . $e->getMessage() . " on {$host}:{$port}/{$database}");

            if ($isAppDebug || php_sapi_name() === 'cli') {
                $detailedMsg = "Database Connection Failed: Unable to connect to MySQL database '{$database}' on {$host}:{$port} with user '{$username}'.\n";
                $detailedMsg .= "Driver: mysql + PDO\n";
                $detailedMsg .= "Underlying Error: " . $e->getMessage() . "\n\n";
                $detailedMsg .= "Troubleshooting Checklist:\n";
                $detailedMsg .= "1. Verify MySQL service is running on {$host}:{$port} (e.g. check XAMPP Control Panel).\n";
                $detailedMsg .= "2. Verify database '{$database}' exists in MySQL.\n";
                $detailedMsg .= "3. Check database credentials in backend/.env (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD).\n";
                $detailedMsg .= "4. Verify that port {$port} is not blocked or conflicting.";
                $ex = new \RuntimeException($detailedMsg, (int)$e->getCode(), $e);
            } else {
                $ex = new \RuntimeException("Database service is currently unavailable. Please contact the administrator.", 500, $e);
            }

            self::$initError = $ex;
            throw $ex;
        }

        self::$capsule = $capsule;
        self::$initError = null;
        return $capsule;
    }

    /**
     * Get active Capsule instance or null if not yet initialized
     */
    public static function getCapsule(): ?Capsule
    {
        return self::$capsule;
    }

    /**
     * Get active connection
     */
    public static function getConnection(?string $name = null): \Illuminate\Database\Connection
    {
        $capsule = self::init();
        return $capsule->getConnection($name);
    }

    /**
     * Get underlying PDO instance
     */
    public static function getPdo(?string $name = null): \PDO
    {
        return self::getConnection($name)->getPdo();
    }

    /**
     * Check if database is currently connected and responsive
     */
    public static function isConnected(): bool
    {
        try {
            if (self::$capsule === null) {
                self::init();
            }
            $pdo = self::getPdo();
            return $pdo instanceof \PDO;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get last initialization error if any
     */
    public static function getLastError(): ?\Throwable
    {
        return self::$initError;
    }

    /**
     * Reset capsule instance (for tests / reinitialization)
     */
    public static function reset(): void
    {
        self::$capsule = null;
        self::$initError = null;
    }
}
