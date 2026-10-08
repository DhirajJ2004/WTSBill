<?php

/**
 * WTSBill ERP - Consolidated Database Configuration
 * Supports environment variables via $_ENV, $_SERVER, or getenv() with safe defaults.
 */

if (!function_exists('wts_db_env')) {
    function wts_db_env(string $key, $default = null)
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        return $default;
    }
}

return [
    'default' => wts_db_env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => wts_db_env('DB_HOST', '127.0.0.1'),
            'port'      => (string) wts_db_env('DB_PORT', '3306'),
            'database'  => wts_db_env('DB_DATABASE', 'wtsbill_db'),
            'username'  => wts_db_env('DB_USERNAME', 'root'),
            'password'  => wts_db_env('DB_PASSWORD', ''),
            'charset'   => wts_db_env('DB_CHARSET', 'utf8mb4'),
            'collation' => wts_db_env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix'    => wts_db_env('DB_PREFIX', ''),
            'strict'    => true,
            'engine'    => null,
            'options'   => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        ],
    ],
];
