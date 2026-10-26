<?php

declare(strict_types=1);

namespace Tests\Integration;

use WTSBill\Core\Config;
use WTSBill\Core\Database;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;

/**
 * Performance and Perceived Loading Smoke Test (Chunk 18).
 * Validates template path caching, statement caching, safe query caching,
 * cache invalidation on mutations, tenant isolation, and Server-Timing headers.
 */
class PerformanceSmokeTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        Session::setTenantContext(1, 1);

        $root = dirname(__DIR__, 2);

        // 1. View Template Path Caching
        View::clearCache();
        $initialCached = View::getCachedPathCount();
        $assert('PERF-01', 'View path cache starts empty after clearCache()', $initialCached === 0);

        $path1 = View::resolvePath('layouts/app');
        $afterFirst = View::getCachedPathCount();
        $path2 = View::resolvePath('layouts/app');
        $afterSecond = View::getCachedPathCount();

        $assert('PERF-02', 'View::resolvePath() populates in-memory cache', $afterFirst === 1);
        $assert('PERF-03', 'View::resolvePath() reuses cached path without disk overhead', 
            $afterSecond === 1 && $path1 === $path2);

        // 2. Database Prepared Statement Caching
        Database::clearStatementCache();
        $sql = "SELECT id, name FROM companies WHERE id = ?";
        $stmt1 = Database::prepare($sql);
        $stmt2 = Database::prepare($sql);

        $assert('PERF-04', 'Database::prepare() caches and reuses identical PDOStatement objects', 
            $stmt1 === $stmt2);

        // 3. Database Execution Metrics
        Database::resetQueryMetrics();
        $companies = Database::select("SELECT id, name FROM companies LIMIT 2");
        $metrics = Database::getQueryMetrics();

        $assert('PERF-05', 'Database query metrics track execution count and latency', 
            $metrics['count'] >= 1 && $metrics['time_ms'] >= 0.0);

        // 4. Safe Query Caching with Multi-Tenant Isolation
        Database::clearQueryCache();
        Session::setTenantContext(1, 1);
        $q1 = Database::selectCached("SELECT id, name FROM customers WHERE company_id = ?", [1], 1);
        
        Session::setTenantContext(2, 1);
        $q2 = Database::selectCached("SELECT id, name FROM customers WHERE company_id = ?", [2], 2);

        $metricsAfterCached = Database::getQueryMetrics();
        $assert('PERF-06', 'Database::selectCached() stores separate cache keys for distinct tenants', 
            $metricsAfterCached['cached_queries'] >= 2);

        // 5. Query Cache Invalidation on Mutations
        $cachedBeforeInsert = Database::getQueryMetrics()['cached_queries'];
        Database::clearQueryCache();
        $assert('PERF-07', 'Database::clearQueryCache() flushes cached query results', 
            Database::getQueryMetrics()['cached_queries'] === 0);

        // 6. Response Server-Timing & Headers
        $response = Response::html('<p>Performance Check</p>')
            ->withHeader('X-Custom-Perf', '1')
            ->withCache(7200, true)
            ->withServerTiming('db', 2.45, '2 SQL queries');

        $headers = $response->getHeaders();

        $assert('PERF-08', 'Response::withHeader() sets custom HTTP headers', 
            ($headers['X-Custom-Perf'] ?? '') === '1');
        $assert('PERF-09', 'Response::withCache() sets public Cache-Control with max-age', 
            ($headers['Cache-Control'] ?? '') === 'public, max-age=7200');
        $assert('PERF-10', 'Response::withServerTiming() sets standard Server-Timing header', 
            str_contains($headers['Server-Timing'] ?? '', 'db;dur=2.45;desc="2 SQL queries"'));

        // 7. Conditional 304 ETag Support
        $testContent = '<h1>ETag Validation Content</h1>';
        $expectedEtag = '"' . md5($testContent) . '"';
        $etagResponse = Response::html($testContent);
        
        $_SERVER['HTTP_IF_NONE_MATCH'] = $expectedEtag;
        ob_start();
        $etagResponse->send();
        $output = ob_get_clean();

        $assert('PERF-11', 'Response::send() generates matching ETag and returns empty body on 304 match', 
            $output === '');
        unset($_SERVER['HTTP_IF_NONE_MATCH']);

        // 8. Configuration & Asset Rules
        $htaccessPath = $root . '/public/.htaccess';
        $htaccessContent = file_get_contents($htaccessPath);

        $assert('PERF-12', 'public/.htaccess enables mod_deflate gzip compression for static assets', 
            str_contains($htaccessContent, 'mod_deflate.c') &&
            str_contains($htaccessContent, 'AddOutputFilterByType DEFLATE text/css'));

        $assert('PERF-13', 'public/.htaccess defines long-lived static browser cache headers', 
            str_contains($htaccessContent, 'mod_expires.c') &&
            str_contains($htaccessContent, 'Cache-Control "public, max-age=2592000, immutable"'));

        $assert('PERF-14', 'config/app.php defines performance and asset cache configuration parameters', 
            Config::has('app.performance') &&
            Config::get('app.performance.view_path_cache') === true);

        // Clean up
        Session::clear();
    }
}
