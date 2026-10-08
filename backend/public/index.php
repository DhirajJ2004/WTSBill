<?php

// PHP 8.1 Polyfills — required because this server runs PHP 8.0.x
// enum_exists() was added in PHP 8.1; Eloquent 9.x calls it for cast resolution
if (!function_exists('enum_exists')) {
    function enum_exists(string $enum, bool $autoload = true): bool {
        return false; // PHP 8.0 has no enums; always return false
    }
}
// array_is_list() was added in PHP 8.1
if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool {
        if ($array === [] || $array === array_values($array)) {
            return true;
        }
        $nextKey = 0;
        foreach ($array as $k => $_) {
            if ($k !== $nextKey++) return false;
        }
        return true;
    }
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Http/Request.php';

use App\Database\Database;
use App\Database\DatabaseSeeder;
use App\Http\Middleware\AuthorizationException;
use App\Http\Request as AppRequest;
use App\Http\JsonResponse as AppJsonResponse;

// Register Class Aliases for Illuminate Http classes
if (!class_exists('Illuminate\Http\Request')) {
    class_alias('App\Http\Request', 'Illuminate\Http\Request');
}
if (!class_exists('Illuminate\Http\JsonResponse')) {
    class_alias('App\Http\JsonResponse', 'Illuminate\Http\JsonResponse');
}

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value, '"\' ');
        }
    }
}

// Production Error & Debug Configuration
$isAppDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
if (!$isAppDebug) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Polyfill getallheaders if CLI mode
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// CORS Management: Never use unrestricted wildcard '*'
if (!function_exists('apply_cors_headers')) {
    function apply_cors_headers(bool $exitOnPreflight = true): array {
        $allowedOriginsStr = $_ENV['ALLOWED_ORIGINS'] ?? 'http://localhost:8000,http://127.0.0.1:8000,http://localhost:3000,http://localhost:5173';
        $allowedOrigins = array_map('trim', explode(',', $allowedOriginsStr));

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $isAllowed = false;

        if (!empty($origin)) {
            if (in_array($origin, $allowedOrigins, true)) {
                $isAllowed = true;
            } else {
                $parsed = parse_url($origin);
                $host = $parsed['host'] ?? '';
                if (in_array($host, ['localhost', '127.0.0.1'], true)) {
                    $isAllowed = true;
                }
            }
        }

        $emitted = [];

        if ($isAllowed) {
            $emitted['Access-Control-Allow-Origin'] = $origin;
            $emitted['Access-Control-Allow-Credentials'] = 'true';
            $emitted['Vary'] = 'Origin';
            if (!headers_sent()) {
                header("Access-Control-Allow-Origin: {$origin}");
                header('Access-Control-Allow-Credentials: true');
                header('Vary: Origin');
            }
        }

        $emitted['Access-Control-Allow-Methods'] = 'GET, POST, PUT, DELETE, OPTIONS';
        $emitted['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, X-Company-Id, X-Branch-Id, X-Financial-Year, X-Api-Token, X-CSRF-Token';

        if (!headers_sent()) {
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Company-Id, X-Branch-Id, X-Financial-Year, X-Api-Token, X-CSRF-Token');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            if ($isAllowed || empty($origin)) {
                http_response_code(204);
            } else {
                http_response_code(403);
            }
            if ($exitOnPreflight) {
                exit();
            }
        }

        return $emitted;
    }
}

// Global Sanitization Helper: Suppress internal paths, SQLSTATE, and stack traces
if (!function_exists('sanitize_error_payload')) {
    function sanitize_error_payload(&$data): void {
        $isAppDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (is_array($data)) {
            foreach ($data as $key => &$val) {
                if (is_string($val)) {
                    if (preg_match('/(SQLSTATE|QueryException|PDOException|\bselect\b.*\bfrom\b|\.php:\d+|\bvendor\/|\bCapsule)/i', $val)) {
                        error_log("[SECURITY ERROR SANITIZED] Raw error: " . $val);
                        if (!$isAppDebug) {
                            $val = ($key === 'message' || $key === 'error')
                                ? 'An internal database or server error occurred.'
                                : '[REDACTED]';
                        }
                    }
                } elseif (is_array($val)) {
                    sanitize_error_payload($val);
                }
            }
        }
    }
}

// Global Helpers
function response_json($data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    apply_cors_headers(false);
    sanitize_error_payload($data);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function get_json_input() {
    static $parsedInput = null;
    if ($parsedInput !== null) return $parsedInput;
    $raw = file_get_contents('php://input');
    if (empty($raw) && php_sapi_name() === 'cli') {
        $raw = @file_get_contents('php://stdin');
    }
    if (empty($raw)) {
        $parsedInput = $_POST;
        return $parsedInput;
    }
    $parsedInput = json_decode($raw, true) ?? [];
    return $parsedInput;
}

if (!function_exists('request')) {
    function request() {
        return AppRequest::capture();
    }
}

if (!function_exists('response')) {
    function response() {
        return new class {
            public function json($data, int $status = 200, array $headers = []) {
                response_json($data, $status);
            }
        };
    }
}

// Handle CORS Preflight early
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    apply_cors_headers(true);
}

// Initialize Database & Migration Schema
$dbInitError = null;
try {
    Database::init();
    DatabaseSeeder::seed();
} catch (\Throwable $e) {
    $dbInitError = $e;
    error_log("[WTSBill DB Bootstrap Error] " . $e->getMessage());
}

// In CLI mode, if index.php is included as a bootstrap file, do not dispatch web routes
if (php_sapi_name() === 'cli' && empty($_SERVER['REQUEST_URI'])) {
    return;
}

// Static Assets & Web View Routing
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Strip project subfolder prefix if hosted under XAMPP subfolder (e.g., /WTSBill/dashboard)
if (strpos($uriPath, '/WTSBill') === 0) {
    $uriPath = substr($uriPath, strlen('/WTSBill'));
}
if (empty($uriPath)) {
    $uriPath = '/';
}

// Serve static assets dynamically
if (strpos($uriPath, '/assets/') !== false) {
    $assetSubPath = substr($uriPath, strpos($uriPath, '/assets/'));
    $filePath = __DIR__ . '/../../public' . $assetSubPath;
    if (file_exists($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        if ($ext === 'css') header('Content-Type: text/css');
        elseif ($ext === 'js') header('Content-Type: application/javascript');
        elseif ($ext === 'svg') header('Content-Type: image/svg+xml');
        elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico'])) header('Content-Type: image/' . $ext);
        readfile($filePath);
        exit();
    }
}

// Logout route handler
if ($uriPath === '/logout') {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    $_SESSION = [];
    @session_destroy();
    setcookie('wts_company_id', '', time() - 3600, '/');
    setcookie('wts_branch_id', '', time() - 3600, '/');
    setcookie('wts_financial_year', '', time() - 3600, '/');
    setcookie('wts_remember', '', time() - 3600, '/');
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    $subPrefix = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/WTSBill') === 0) ? '/WTSBill' : '';
    header('Location: ' . $subPrefix . '/login?logout=1', true, 302);
    exit();
}

// Switch Company handler
if ($uriPath === '/switch-company') {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    require_once __DIR__ . '/../../views/db_helper.php';
    $cid = (int)($_GET['id'] ?? $_GET['company_id'] ?? 0);
    $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
    $fy = $_GET['fy'] ?? get_current_financial_year();
    try {
        set_active_workspace($cid, $fy, null, $branchId);
        $subPrefix = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/WTSBill') === 0) ? '/WTSBill' : '';
        $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ($subPrefix . '/dashboard');
        header('Location: ' . $redirect);
        exit();
    } catch (\App\Http\Middleware\AuthorizationException $e) {
        http_response_code(403);
        echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style='font-family: sans-serif; padding: 40px; text-align: center;'><h2 style='color:#dc2626;'>403 Forbidden</h2><p>" . htmlspecialchars($e->getMessage()) . "</p><p><a href='" . url('/select-company') . "'>Return to Company Selection</a></p></body></html>";
        exit();
    }
}

$webRoutes = [
    '/' => __DIR__ . '/../../views/dashboard.php',
    '/login' => __DIR__ . '/../../views/auth/login.php',
    '/forgot-password' => __DIR__ . '/../../views/auth/forgot_password.php',
    '/reset-password' => __DIR__ . '/../../views/auth/reset_password.php',
    '/select-company' => __DIR__ . '/../../views/auth/select_company.php',
    '/dashboard' => __DIR__ . '/../../views/dashboard.php',
    '/invoices' => __DIR__ . '/../../views/sales/invoices.php',
    '/create-invoice' => __DIR__ . '/../../views/sales/create_invoice.php',
    '/quotations' => __DIR__ . '/../../views/sales/quotations.php',
    '/create-quotation' => __DIR__ . '/../../views/sales/create_quotation.php',
    '/recurring-invoices' => __DIR__ . '/../../views/sales/recurring_invoices.php',
    '/credit-notes' => __DIR__ . '/../../views/sales/credit_notes.php',
    '/debit-notes' => __DIR__ . '/../../views/purchases/debit_notes.php',
    '/purchases' => __DIR__ . '/../../views/purchases/purchases.php',
    '/inventory' => __DIR__ . '/../../views/inventory/index.php',
    '/parties' => __DIR__ . '/../../views/parties/index.php',
    '/payments' => __DIR__ . '/../../views/payments/index.php',
    '/expenses' => __DIR__ . '/../../views/expenses/index.php',
    '/accounting' => __DIR__ . '/../../views/accounting/index.php',
    '/reports' => __DIR__ . '/../../views/reports/index.php',
    '/users' => __DIR__ . '/../../views/settings/users.php',
    '/audit-logs' => __DIR__ . '/../../views/settings/audit_logs.php',
    '/settings' => __DIR__ . '/../../views/settings/index.php',
    '/print-invoice' => __DIR__ . '/../../views/sales/print_invoice.php',
    '/invoices/print' => __DIR__ . '/../../views/sales/print_invoice.php',
    '/print-quotation' => __DIR__ . '/../../views/sales/print_quotation.php',
    '/quotations/print' => __DIR__ . '/../../views/sales/print_quotation.php',
    '/print-receipt' => __DIR__ . '/../../views/payments/print_receipt.php',
    '/payments/print' => __DIR__ . '/../../views/payments/print_receipt.php',
    '/print-order' => __DIR__ . '/../../views/sales/print_order.php',
    '/sales-orders/print' => __DIR__ . '/../../views/sales/print_order.php',
    '/print-challan' => __DIR__ . '/../../views/sales/print_challan.php',
    '/challans/print' => __DIR__ . '/../../views/sales/print_challan.php',
    '/print-credit-note' => __DIR__ . '/../../views/sales/print_credit_note.php',
    '/credit-notes/print' => __DIR__ . '/../../views/sales/print_credit_note.php',
    '/print-debit-note' => __DIR__ . '/../../views/purchases/print_debit_note.php',
    '/debit-notes/print' => __DIR__ . '/../../views/purchases/print_debit_note.php',
    '/print-purchase' => __DIR__ . '/../../views/purchases/print_purchase.php',
    '/purchases/print' => __DIR__ . '/../../views/purchases/print_purchase.php',

    // Fallback mappings for direct script paths (/views/...)
    '/views/auth/login.php' => __DIR__ . '/../../views/auth/login.php',
    '/views/auth/forgot_password.php' => __DIR__ . '/../../views/auth/forgot_password.php',
    '/views/auth/reset_password.php' => __DIR__ . '/../../views/auth/reset_password.php',
    '/views/auth/select_company.php' => __DIR__ . '/../../views/auth/select_company.php',
    '/views/dashboard.php' => __DIR__ . '/../../views/dashboard.php',
    '/views/sales/invoices.php' => __DIR__ . '/../../views/sales/invoices.php',
    '/views/sales/create_invoice.php' => __DIR__ . '/../../views/sales/create_invoice.php',
    '/views/sales/quotations.php' => __DIR__ . '/../../views/sales/quotations.php',
    '/views/sales/create_quotation.php' => __DIR__ . '/../../views/sales/create_quotation.php',
    '/views/sales/recurring_invoices.php' => __DIR__ . '/../../views/sales/recurring_invoices.php',
    '/views/sales/credit_notes.php' => __DIR__ . '/../../views/sales/credit_notes.php',
    '/views/purchases/purchases.php' => __DIR__ . '/../../views/purchases/purchases.php',
    '/views/purchases/debit_notes.php' => __DIR__ . '/../../views/purchases/debit_notes.php',
    '/views/inventory/index.php' => __DIR__ . '/../../views/inventory/index.php',
    '/views/parties/index.php' => __DIR__ . '/../../views/parties/index.php',
    '/views/payments/index.php' => __DIR__ . '/../../views/payments/index.php',
    '/views/expenses/index.php' => __DIR__ . '/../../views/expenses/index.php',
    '/views/accounting/index.php' => __DIR__ . '/../../views/accounting/index.php',
    '/views/reports/index.php' => __DIR__ . '/../../views/reports/index.php',
    '/views/settings/users.php' => __DIR__ . '/../../views/settings/users.php',
    '/views/settings/audit_logs.php' => __DIR__ . '/../../views/settings/audit_logs.php',
    '/views/settings/index.php' => __DIR__ . '/../../views/settings/index.php',
    '/views/sales/print_invoice.php' => __DIR__ . '/../../views/sales/print_invoice.php',
    '/views/sales/print_quotation.php' => __DIR__ . '/../../views/sales/print_quotation.php',
    '/views/payments/print_receipt.php' => __DIR__ . '/../../views/payments/print_receipt.php',
];

if ($dbInitError !== null) {
    $isAppDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if (strpos($uriPath, '/api/') === 0) {
        response_json([
            'status' => 'error',
            'message' => $isAppDebug ? $dbInitError->getMessage() : 'Database service is currently unavailable. Please contact the administrator.',
            'error_type' => 'DATABASE_INITIALIZATION_ERROR',
        ], 500);
    }

    if ($isAppDebug) {
        http_response_code(500);
        echo "<!DOCTYPE html><html><head><title>Database Configuration Error</title>";
        echo "<style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#f8fafc;padding:40px;line-height:1.6;}";
        echo ".card{max-width:800px;margin:0 auto;background:#1e293b;border:1px solid #ef4444;border-radius:12px;padding:32px;box-shadow:0 10px 25px rgba(0,0,0,0.5);}";
        echo "h1{color:#ef4444;margin-top:0;font-size:24px;display:flex;align-items:center;gap:10px;}pre{background:#0b0f19;padding:16px;border-radius:8px;overflow-x:auto;color:#cbd5e1;font-size:14px;}";
        echo ".tag{display:inline-block;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;background:rgba(239,68,68,0.2);color:#fca5a5;margin-bottom:16px;}";
        echo "</style></head><body><div class='card'>";
        echo "<div class='tag'>WTSBill Runtime Diagnostic Mode</div>";
        echo "<h1>Database Connection / Extension Error</h1>";
        echo "<p>The application could not initialize its database connection. Diagnostic details:</p>";
        echo "<pre>" . htmlspecialchars($dbInitError->getMessage()) . "</pre>";
        echo "<h3>Common Fixes:</h3>";
        echo "<ul>";
        echo "<li><strong>Missing Driver:</strong> Ensure <code>extension=pdo_mysql</code> is enabled in your active <code>php.ini</code> (" . htmlspecialchars(php_ini_loaded_file() ?: 'php.ini') . ").</li>";
        echo "<li><strong>MySQL Server:</strong> Verify MySQL / MariaDB is started on the configured port.</li>";
        echo "<li><strong>Credentials:</strong> Verify database credentials in <code>backend/.env</code>.</li>";
        echo "</ul></div></body></html>";
        exit();
    } else {
        http_response_code(500);
        echo "<!DOCTYPE html><html><head><title>500 - Service Unavailable</title></head><body style='font-family:sans-serif;text-align:center;padding:60px;background:#f8fafc;'><h2 style='color:#dc2626;'>500 - Service Temporarily Unavailable</h2><p>An internal database error occurred. Please contact your system administrator.</p></body></html>";
        exit();
    }
}

if (isset($webRoutes[$uriPath])) {
    $publicWebRoutes = [
        '/login',
        '/forgot-password',
        '/reset-password',
        '/views/auth/login.php',
        '/views/auth/forgot_password.php',
        '/views/auth/reset_password.php',
    ];

    if (!in_array($uriPath, $publicWebRoutes, true)) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Check session expiration (2-hour / 7200s inactivity timeout)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 7200) {
            $_SESSION = [];
            @session_destroy();
            $subPrefix = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/WTSBill') === 0) ? '/WTSBill' : '';
            header('Location: ' . $subPrefix . '/login?expired=1', true, 302);
            exit();
        }

        // Check user session
        if (empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
            $subPrefix = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/WTSBill') === 0) ? '/WTSBill' : '';
            header('Location: ' . $subPrefix . '/login', true, 302);
            exit();
        }

        $_SESSION['last_activity'] = time();
    }

    require_once $webRoutes[$uriPath];
    exit();
}

// Dispatch API Route
require_once __DIR__ . '/../routes/api.php';
try {
    dispatch_route($uriPath, $_SERVER['REQUEST_METHOD'] ?? 'GET');
} catch (AuthorizationException $e) {
    response_json($e->response, $e->statusCode);
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    response_json([
        'status' => 'error',
        'message' => 'Resource not found or unauthorized.',
    ], 404);
} catch (\Throwable $e) {
    error_log("[UNCAUGHT EXCEPTION] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
    $isAppDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if (!$isAppDebug) {
        response_json([
            'status' => 'error',
            'message' => 'An internal server error occurred. Please contact the administrator.',
        ], 500);
    } else {
        response_json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
}


