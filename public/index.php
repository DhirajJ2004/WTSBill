<?php

declare(strict_types=1);

// WTSBill ERP — Native PHP 8+ Rebuild Public Gateway

// Built-in web server router: return false for existing static assets
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    if ($requestPath !== '/' && file_exists(__DIR__ . $requestPath) && !is_dir(__DIR__ . $requestPath)) {
        return false;
    }
}

define('WTSBILL_START', microtime(true));
define('ROOT_PATH', dirname(__DIR__));

// Autoloading
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// Ensure standalone PSR-4 autoloader is registered as well
require_once ROOT_PATH . '/app/Core/Autoloader.php';
\WTSBill\Core\Autoloader::register();
require_once ROOT_PATH . '/app/Core/helpers.php';

use WTSBill\Core\Config;
use WTSBill\Core\Env;
use WTSBill\Core\Logger;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Core\View;

// 1. Initialize Environment
Env::load(ROOT_PATH . '/.env');

// 2. Initialize Configuration
Config::loadFromDirectory(ROOT_PATH . '/config');

// 3. Initialize Core Paths
Logger::setLogDirectory(ROOT_PATH . '/storage/logs');
View::setBasePath(ROOT_PATH . '/views');

// 4. Capture Request
$request = Request::capture();

// 5. Initialize Router & Load Route Definitions
$router = new Router();

if (file_exists(ROOT_PATH . '/routes/web.php')) {
    require_once ROOT_PATH . '/routes/web.php';
}

if (file_exists(ROOT_PATH . '/routes/api.php')) {
    require_once ROOT_PATH . '/routes/api.php';
}

// 6. Dispatch Request and Send Response
try {
    $response = $router->dispatch($request);
} catch (\WTSBill\Core\Exceptions\HttpException $e) {
    if ($request->expectsJson()) {
        $response = Response::error($e->getMessage(), $e->getStatusCode(), $e->getErrors());
    } else {
        $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $response = Response::html("<h1>Error {$e->getStatusCode()}</h1><p>{$msg}</p>", $e->getStatusCode());
    }
} catch (\Throwable $e) {
    Logger::error('Unhandled Exception: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    if ($request->expectsJson()) {
        $response = Response::error('An unexpected server error occurred.', 500);
    } else {
        $response = Response::html('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>500 Internal Server Error &mdash; WTSBill ERP</title>'
            . '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}'
            . '.card{background:#1e293b;padding:2.5rem;border-radius:1rem;box-shadow:0 10px 25px rgba(0,0,0,0.5);text-align:center;max-width:480px;border:1px solid #334155;}'
            . 'h1{color:#ef4444;margin-bottom:0.5rem;font-size:1.75rem;}p{color:#94a3b8;line-height:1.6;font-size:0.95rem;}'
            . 'a{display:inline-block;margin-top:1.25rem;background:#0284c7;color:#fff;text-decoration:none;font-weight:600;padding:0.6rem 1.25rem;border-radius:0.5rem;}</style></head><body>'
            . '<div class="card"><h1>500 Internal Server Error</h1><p>A system error occurred while processing your request. Please contact your system administrator.</p>'
            . '<a href="/">&larr; Return to Dashboard</a></div></body></html>', 500);
    }
}

$response->send();
