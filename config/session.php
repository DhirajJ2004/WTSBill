<?php

declare(strict_types=1);

return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_LIFETIME', 7200), // 2 hours in seconds
    'expire_on_close' => (bool) env('SESSION_EXPIRE_ON_CLOSE', false),
    'cookie' => env('SESSION_COOKIE', 'wtsbill_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN', null),
    'secure' => (bool) env('SESSION_SECURE_COOKIE', false),
    'http_only' => true,
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
    'save_path' => env('SESSION_SAVE_PATH', dirname(__DIR__) . '/storage/sessions'),
];
