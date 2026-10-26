<?php

declare(strict_types=1);

namespace WTSBill\Core;

/**
 * Native PSR-4 Autoloader for WTSBill ERP.
 * Provides resilient, standalone class loading without external framework dependencies.
 */
class Autoloader
{
    private static array $prefixes = [];
    private static bool $registered = false;

    /**
     * Register the autoloader with SPL.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        $baseDir = dirname(__DIR__); // points to app/

        self::addNamespace('WTSBill\\', $baseDir);
        self::addNamespace('App\\', $baseDir);

        spl_autoload_register([self::class, 'loadClass']);
        self::$registered = true;
    }

    /**
     * Add a base directory for a namespace prefix.
     */
    public static function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim(str_replace('\\', '/', $baseDir), '/') . '/';

        if (!isset(self::$prefixes[$prefix])) {
            self::$prefixes[$prefix] = [];
        }

        if (!in_array($baseDir, self::$prefixes[$prefix], true)) {
            self::$prefixes[$prefix][] = $baseDir;
        }
    }

    /**
     * Load the requested class file.
     */
    public static function loadClass(string $class): bool
    {
        $prefix = $class;

        while (false !== ($pos = strrpos($prefix, '\\'))) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);

            $mapped = self::loadMappedFile($prefix, $relativeClass);
            if ($mapped) {
                return true;
            }

            $prefix = rtrim($prefix, '\\');
        }

        return false;
    }

    /**
     * Resolve and require mapped file.
     */
    private static function loadMappedFile(string $prefix, string $relativeClass): bool
    {
        if (!isset(self::$prefixes[$prefix])) {
            return false;
        }

        foreach (self::$prefixes[$prefix] as $baseDir) {
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require $file;
                return true;
            }
        }

        return false;
    }

    public static function isRegistered(): bool
    {
        return self::$registered;
    }

    public static function getPrefixes(): array
    {
        return self::$prefixes;
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Autoloader', false)) {
    class_alias(Autoloader::class, 'App\Core\Autoloader');
}
