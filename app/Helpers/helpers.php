<?php

/**
 * WTSBill ERP - Centralized Core Helper Functions
 */

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token = null): bool {
        $expected = $_SESSION['csrf_token'] ?? '';
        if (empty($expected)) return false;
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        if (empty($token)) return false;
        return hash_equals($expected, $token);
    }
}

if (!function_exists('auth')) {
    function auth() {
        return new class {
            public function user() {
                return current_user();
            }
            public function id(): ?int {
                return current_user()['id'] ?? null;
            }
            public function check(): bool {
                return !empty(current_user()['id']);
            }
        };
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('current_company')) {
    function current_company() {
        try {
            return WorkspaceContext::getCompany();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('current_company_id')) {
    function current_company_id(): ?int {
        try {
            return WorkspaceContext::getCompanyId();
        } catch (\Throwable $e) {
            return $_SESSION['company_id'] ?? null;
        }
    }
}

if (!function_exists('current_branch')) {
    function current_branch() {
        try {
            return WorkspaceContext::getBranch();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('current_branch_id')) {
    function current_branch_id(): ?int {
        try {
            return WorkspaceContext::getBranchId();
        } catch (\Throwable $e) {
            return $_SESSION['branch_id'] ?? null;
        }
    }
}

if (!function_exists('current_financial_year')) {
    function current_financial_year(): string {
        if (!empty($_SESSION['financial_year'])) {
            return $_SESSION['financial_year'];
        }
        $month = (int)date('m');
        $year = (int)date('Y');
        $fyStart = $month >= 4 ? $year : $year - 1;
        $fyEnd = ($fyStart + 1) % 100;
        return sprintf('%04d-%02d', $fyStart, $fyEnd);
    }
}

if (!function_exists('e')) {
    function e($value): string {
        if ($value === null) return '';
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8', false);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): void {
        $target = url($path);
        header("Location: {$target}", true, $status);
        exit();
    }
}

if (!function_exists('json_response')) {
    function json_response($data, int $status = 200, array $headers = []): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $k => $v) {
            header("{$k}: {$v}");
        }
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = null) {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
}

if (!function_exists('validation_error')) {
    function validation_error(string $key): ?string {
        return $_SESSION['validation_errors'][$key] ?? null;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string {
        $path = '/' . ltrim($path, '/');
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $subDir = rtrim(dirname($scriptName), '/\\');
        if (strpos($subDir, '/backend/public') !== false) {
            $base = str_replace('/backend/public', '', $subDir);
        } elseif ($subDir !== '/' && $subDir !== '\\' && $subDir !== '.') {
            $base = $subDir;
        } else {
            $base = '';
        }
        $base = rtrim(str_replace('\\', '/', $base), '/');
        if (empty($base) && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/WTSBill') === 0) {
            $base = '/WTSBill';
        }
        return $base . $path;
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount, string $symbol = '₹'): string {
        $val = (float)($amount ?? 0);
        return $symbol . number_format($val, 2, '.', ',');
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'd M Y'): string {
        if (empty($date)) return '-';
        $time = strtotime($date);
        return $time ? date($format, $time) : '-';
    }
}

/**
 * UI Component Helpers
 */
if (!function_exists('component')) {
    function component(string $__component_name, array $__component_data = []): string {
        $__component_file = __DIR__ . '/../../views/components/' . str_replace('.', '/', $__component_name) . '.php';
        if (!file_exists($__component_file)) {
            $__component_file = __DIR__ . '/../../views/components/' . $__component_name . '.php';
        }
        if (file_exists($__component_file)) {
            extract($__component_data);
            ob_start();
            include $__component_file;
            return ob_get_clean();
        }
        return "<!-- Component [{$__component_name}] not found -->";
    }
}

if (!function_exists('render_component')) {
    function render_component(string $name, array $data = []): void {
        echo component($name, $data);
    }
}

if (!function_exists('ui_button')) {
    function ui_button(string $text, array $options = []): string {
        $options['text'] = $text;
        return component('button', $options);
    }
}

if (!function_exists('ui_card')) {
    function ui_card(string $title, string $content, array $options = []): string {
        $options['title'] = $title;
        $options['content'] = $content;
        return component('card', $options);
    }
}

if (!function_exists('ui_empty_state')) {
    function ui_empty_state(string $title, ?string $message = null, array $options = []): string {
        $options['title'] = $title;
        $options['message'] = $message;
        return component('empty_state', $options);
    }
}

if (!function_exists('ui_error_state')) {
    function ui_error_state(string $title, ?string $message = null, array $options = []): string {
        $options['title'] = $title;
        $options['message'] = $message;
        return component('error_state', $options);
    }
}

if (!function_exists('ui_badge')) {
    function ui_badge(string $text, string $variant = 'primary'): string {
        return '<span class="badge badge-' . htmlspecialchars($variant) . '">' . htmlspecialchars($text) . '</span>';
    }
}

