<?php

declare(strict_types=1);

namespace WTSBill\Core;

/**
 * Thread-safe configuration repository with dot-notation lookup.
 */
class Config
{
    private static array $items = [];
    private static bool $bootstrapped = false;

    /**
     * Bootstrap all configuration files from the config directory.
     */
    public static function loadFromDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.php');
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $values = require $file;
            if (is_array($values)) {
                self::$items[$key] = $values;
            }
        }

        self::$bootstrapped = true;
    }

    /**
     * Get a configuration item using dot notation (e.g., 'database.mysql.host').
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (empty($key)) {
            return self::$items;
        }

        $segments = explode('.', $key);
        $current = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Check if a configuration key exists.
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Set a configuration item dynamically.
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * Get all configuration items.
     */
    public static function all(): array
    {
        return self::$items;
    }

    public static function isBootstrapped(): bool
    {
        return self::$bootstrapped;
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Config', false)) {
    class_alias(Config::class, 'App\Core\Config');
}
