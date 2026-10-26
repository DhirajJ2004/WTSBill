<?php

declare(strict_types=1);

namespace WTSBill\Core;

/**
 * Zero-dependency Environment file loader and variable accessor for WTSBill ERP.
 */
class Env
{
    private static bool $loaded = false;
    private static array $repository = [];

    /**
     * Load environment variables from a .env file.
     */
    public static function load(string $path): void
    {
        if (!file_exists($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip enclosing quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            self::$repository[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        self::$loaded = true;
    }

    /**
     * Retrieve an environment variable value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$repository)) {
            $val = self::$repository[$key];
        } elseif (array_key_exists($key, $_ENV)) {
            $val = $_ENV[$key];
        } elseif (($envVal = getenv($key)) !== false) {
            $val = $envVal;
        } else {
            return $default;
        }

        // Cast boolean / null values
        $lower = strtolower((string) $val);
        if ($lower === 'true' || $lower === '(true)') {
            return true;
        }
        if ($lower === 'false' || $lower === '(false)') {
            return false;
        }
        if ($lower === 'null' || $lower === '(null)') {
            return null;
        }

        return $val;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Env', false)) {
    class_alias(Env::class, 'App\Core\Env');
}
