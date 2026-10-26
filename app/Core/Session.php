<?php

declare(strict_types=1);

namespace WTSBill\Core;

/**
 * Secure Native Session Manager for WTSBill ERP.
 * Handles HttpOnly cookies, SameSite enforcement, CSRF tokens, flash messages, and multi-tenant scoping.
 */
class Session
{
    private static bool $started = false;

    /**
     * Start the session with secure baseline cookie options.
     */
    public static function start(?array $options = null): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }

        $headersSent = headers_sent();

        if (!$headersSent) {
            $savePath = $options['save_path'] ?? (dirname(__DIR__, 2) . '/storage/sessions');
            if (!is_dir($savePath)) {
                @mkdir($savePath, 0755, true);
            }

            if (is_dir($savePath) && is_writable($savePath)) {
                @session_save_path($savePath);
            }

            $lifetime = (int) ($options['lifetime'] ?? 7200); // 2 hours
            $cookieName = $options['cookie_name'] ?? 'wtsbill_session';
            $secure = (bool) ($options['secure'] ?? false);
            $samesite = $options['samesite'] ?? 'Lax';

            @ini_set('session.name', $cookieName);
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.cookie_httponly', '1');

            @session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => $samesite,
            ]);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        self::$started = true;

        // Age out flash data
        self::ageFlashData();
    }

    public static function isStarted(): bool
    {
        return self::$started || session_status() === PHP_SESSION_ACTIVE || isset($_SESSION);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::ensureStarted();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::ensureStarted();
        return array_key_exists($key, $_SESSION);
    }

    public static function remove(string $key): void
    {
        self::ensureStarted();
        unset($_SESSION[$key]);
    }

    public static function all(): array
    {
        self::ensureStarted();
        return $_SESSION;
    }

    public static function clear(): void
    {
        self::ensureStarted();
        $_SESSION = [];
    }

    public static function destroy(): void
    {
        if (self::isStarted()) {
            $_SESSION = [];
            if (!headers_sent() && ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_destroy();
            }
            self::$started = false;
        }
    }

    private static bool $regenerated = false;

    public static function regenerateId(bool $deleteOld = true): bool
    {
        self::ensureStarted();
        self::$regenerated = true;
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            return session_regenerate_id($deleteOld);
        }
        return true;
    }

    public static function wasRegenerated(): bool
    {
        return self::$regenerated;
    }

    public static function resetRegenerated(): void
    {
        self::$regenerated = false;
    }

    // --- CSRF PROTECTION ---

    public static function csrfToken(): string
    {
        self::ensureStarted();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function getCsrfToken(): string
    {
        return self::csrfToken();
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        self::ensureStarted();
        if (empty($token) || empty($_SESSION['_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    // --- FLASH MESSAGES ---

    public static function flash(string $key, mixed $value): void
    {
        self::ensureStarted();
        $_SESSION['_flash']['new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::ensureStarted();
        return $_SESSION['_flash']['old'][$key] ?? $_SESSION['_flash']['new'][$key] ?? $default;
    }

    public static function hasFlash(string $key): bool
    {
        self::ensureStarted();
        return isset($_SESSION['_flash']['old'][$key]) || isset($_SESSION['_flash']['new'][$key]);
    }

    private static function ageFlashData(): void
    {
        if (isset($_SESSION['_flash']['old'])) {
            unset($_SESSION['_flash']['old']);
        }
        if (isset($_SESSION['_flash']['new'])) {
            $_SESSION['_flash']['old'] = $_SESSION['_flash']['new'];
            unset($_SESSION['_flash']['new']);
        }
    }

    // --- MULTI-TENANT CONTEXT ---

    public static function setTenantContext(int $companyId, ?int $branchId = null): void
    {
        self::set('current_company_id', $companyId);
        self::set('current_branch_id', $branchId);
    }

    public static function getCompanyId(): ?int
    {
        $id = self::get('current_company_id');
        return $id !== null ? (int) $id : null;
    }

    public static function getBranchId(): ?int
    {
        $id = self::get('current_branch_id');
        return $id !== null ? (int) $id : null;
    }

    public static function getBranchName(): ?string
    {
        return self::get('current_branch_name');
    }

    public static function getBranchCode(): ?string
    {
        return self::get('current_branch_code');
    }

    public static function getUserId(): ?int
    {
        $id = self::get('user_id') ?? (self::get('user')['id'] ?? null);
        return $id !== null ? (int) $id : null;
    }

    public static function getUser(): ?array
    {
        $user = self::get('user');
        return is_array($user) ? $user : null;
    }

    private static function ensureStarted(): void
    {
        if (!self::isStarted()) {
            self::start();
        }
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Session', false)) {
    class_alias(Session::class, 'App\Core\Session');
}
