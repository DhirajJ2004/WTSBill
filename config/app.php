<?php

/**
 * WTSBill ERP - Application Configuration
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
    'name' => env_val('APP_NAME', 'WTSBill ERP'),
    'env' => env_val('APP_ENV', 'production'),
    'debug' => filter_var(env_val('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'url' => env_val('APP_URL', 'http://127.0.0.1:8000'),
    'timezone' => env_val('APP_TIMEZONE', 'Asia/Kolkata'),
    'locale' => env_val('APP_LOCALE', 'en'),
    'key' => env_val('APP_KEY', 'base64:wtsbill_secret_key_default_32_bytes_len='),
];
