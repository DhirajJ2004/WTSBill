<?php

declare(strict_types=1);

namespace WTSBill\Core;

use WTSBill\Core\Exceptions\HttpException;
use RuntimeException;

/**
 * Safe Template View Renderer for WTSBill ERP.
 * Prevents path traversal vulnerabilities and provides layout rendering support.
 */
class View
{
    private static ?string $basePath = null;
    private static array $sharedData = [];
    private static array $resolvedPaths = [];

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim(str_replace('\\', '/', $path), '/');
        self::$resolvedPaths = [];
    }

    public static function clearCache(): void
    {
        self::$resolvedPaths = [];
    }

    public static function getCachedPathCount(): int
    {
        return count(self::$resolvedPaths);
    }

    public static function getBasePath(): string
    {
        if (self::$basePath === null) {
            self::$basePath = dirname(__DIR__, 2) . '/views';
        }
        return self::$basePath;
    }

    public static function share(string $key, mixed $value): void
    {
        self::$sharedData[$key] = $value;
    }

    public static function exists(string $view): bool
    {
        try {
            self::resolvePath($view);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Render a view file with optional data.
     */
    public static function render(string $view, array $data = []): string
    {
        $filePath = self::resolvePath($view);
        $mergedData = array_merge(self::$sharedData, $data);

        return self::renderFile($filePath, $mergedData);
    }

    /**
     * Render a view wrapped inside a layout.
     */
    public static function renderWithLayout(string $view, array $data = [], string $layout = 'layouts/main'): string
    {
        $content = self::render($view, $data);
        $layoutData = array_merge($data, ['content' => $content]);

        return self::render($layout, $layoutData);
    }

    /**
     * Resolve and validate that the template file exists within the allowed views directory.
     */
    public static function resolvePath(string $view): string
    {
        if (isset(self::$resolvedPaths[$view])) {
            return self::$resolvedPaths[$view];
        }

        $base = self::getBasePath();
        
        // Normalize view path (e.g. 'dashboard.index' -> 'dashboard/index')
        $normalized = str_replace('.', '/', $view);
        $normalized = ltrim($normalized, '/');
        
        $candidate = "{$base}/{$normalized}.php";

        // Check file existence
        if (!file_exists($candidate)) {
            throw HttpException::notFound("View [{$view}] not found at {$candidate}.");
        }

        $realBase = realpath($base);
        $realFile = realpath($candidate);

        // Security: Prevent directory traversal attack (e.g., ../../)
        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase)) {
            throw new RuntimeException("Directory traversal attempt detected in view path [{$view}].");
        }

        return self::$resolvedPaths[$view] = $realFile;
    }

    private static function renderFile(string $filePath, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            include $filePath;
            return ob_get_clean() ?: '';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\View', false)) {
    class_alias(View::class, 'App\Core\View');
}
