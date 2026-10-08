<?php

namespace App\Middleware;

class GuestMiddleware
{
    public static function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!empty($_SESSION['user_id']) || !empty($_SESSION['user']['id'])) {
            header('Location: ' . (function_exists('url') ? url('/dashboard') : '/dashboard'));
            exit();
        }
    }
}
