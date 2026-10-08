<?php

/**
 * WTSBill ERP - Database Configuration
 * Centralized PDO & Capsule settings with strict error handling and prepared statement enforcement.
 */

if (!function_exists('env_val')) {
    function env_val(string $key, $default = null) {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
        $val = getenv($key);
        if ($val !== false && $val !== '') return $val;
        return $default;
    }
}

return [
    'default' => env_val('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env_val('DB_HOST', '127.0.0.1'),
            'port'      => (string) env_val('DB_PORT', '3306'),
            'database'  => env_val('DB_DATABASE', 'wtsbill_db'),
            'username'  => env_val('DB_USERNAME', 'root'),
            'password'  => env_val('DB_PASSWORD', ''),
            'charset'   => env_val('DB_CHARSET', 'utf8mb4'),
            'collation' => env_val('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix'    => env_val('DB_PREFIX', ''),
            'strict'    => true,
            'engine'    => 'InnoDB',
            'options'   => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        ],
    ],
];
