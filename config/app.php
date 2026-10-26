<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'WTSBill ERP'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => 'Asia/Kolkata',
    'locale' => 'en',
    'key' => env('APP_KEY', 'base64:c29tZXJhbmRvbXNlY3JldGtleWZvcmJoYXJhdGxlZGdlcg=='),
    'allowed_origins' => explode(',', env('ALLOWED_ORIGINS', 'http://localhost:8000,http://127.0.0.1:8000')),
    'jwt_secret' => env('JWT_SECRET', 'wtsbill_db_pro_jwt_secret_key_2026_indian_saas'),
    'performance' => [
        'view_path_cache' => (bool) env('APP_VIEW_CACHE', true),
        'statement_cache' => (bool) env('APP_STATEMENT_CACHE', true),
        'query_cache' => (bool) env('APP_QUERY_CACHE', true),
        'server_timing' => (bool) env('APP_SERVER_TIMING', true),
    ],
    'assets' => [
        'cache_control' => 'public, max-age=2592000, immutable',
    ],
];
