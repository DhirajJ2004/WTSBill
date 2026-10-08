<?php

namespace App\Support;

use Throwable;

class ExceptionHandler
{
    public static function handle(Throwable $e, bool $isApi = false): void
    {
        $appConfig = require __DIR__ . '/../../config/app.php';
        $isDebug = $appConfig['debug'] ?? false;

        error_log(sprintf(
            "[CRITICAL ERROR] %s in %s:%d\nStack trace:\n%s",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        if ($isApi || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => $isDebug ? $e->getMessage() : 'An internal server error occurred. Please contact system support.',
                'error_type' => 'SERVER_EXCEPTION',
                'file' => $isDebug ? $e->getFile() : null,
                'line' => $isDebug ? $e->getLine() : null,
            ], JSON_PRETTY_PRINT);
            exit();
        }

        http_response_code(500);
        if ($isDebug) {
            echo "<!DOCTYPE html><html><head><title>500 Internal Error</title>";
            echo "<style>body{font-family:sans-serif;background:#0f172a;color:#f8fafc;padding:40px;}";
            echo ".box{max-width:800px;margin:0 auto;background:#1e293b;padding:24px;border-radius:12px;border:1px solid #ef4444;}";
            echo "pre{background:#0b0f19;padding:16px;border-radius:8px;overflow:auto;color:#cbd5e1;}</style></head>";
            echo "<body><div class='box'><h2 style='color:#ef4444;'>Application Exception</h2>";
            echo "<p><strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>";
            echo "<p>In " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></div></body></html>";
            exit();
        }

        echo "<!DOCTYPE html><html><head><title>500 Service Unavailable</title></head>";
        echo "<body style='font-family:sans-serif;text-align:center;padding:60px;background:#f8fafc;'>";
        echo "<h2 style='color:#dc2626;'>500 - Service Temporarily Unavailable</h2>";
        echo "<p>An unexpected error occurred. Our engineers have been notified.</p>";
        echo "<p><a href='" . (function_exists('url') ? url('/dashboard') : '/') . "'>Return to Dashboard</a></p></body></html>";
        exit();
    }
}
