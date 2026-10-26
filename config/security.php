<?php

declare(strict_types=1);

return [
    'csrf' => [
        'token_name' => '_csrf_token',
        'header_name' => 'X-CSRF-TOKEN',
        'cookie_name' => 'XSRF-TOKEN',
    ],

    'cors' => [
        'allowed_origins' => explode(',', env('ALLOWED_ORIGINS', 'http://localhost:8000,http://127.0.0.1:8000')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
        'max_age' => 86400,
    ],

    'app_key' => env('APP_KEY', ''),
    'jwt_secret' => env('JWT_SECRET', 'wtsbill_db_pro_jwt_secret_key_2026_indian_saas'),
    'bcrypt_cost' => (int) env('BCRYPT_COST', 10),

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'self'",
    ],

    'cookies' => [
        'httponly' => true,
        'secure' => env('APP_ENV') === 'production',
        'samesite' => 'Lax',
    ],

    'rate_limiting' => [
        'login' => [
            'max_attempts' => 5,
            'decay_seconds' => 60,
        ],
    ],

    'uploads' => [
        'max_size_kb' => 5120, // 5MB
        'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
        'disallowed_extensions' => ['php', 'phtml', 'exe', 'bat', 'sh', 'js', 'html'],
    ],
];
