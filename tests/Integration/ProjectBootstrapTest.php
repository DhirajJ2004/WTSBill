<?php

declare(strict_types=1);

namespace Tests\Integration;

use WTSBill\Core\Autoloader;
use WTSBill\Core\Config;
use WTSBill\Core\Database;
use WTSBill\Core\Env;
use WTSBill\Core\Exceptions\HttpException;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Logger;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use PDO;

/**
 * Integration Test for WTSBill Project Architecture Bootstrap.
 */
class ProjectBootstrapTest
{
    public static function run(callable $assert): void
    {
        $rootPath = dirname(__DIR__, 2);

        // 1. Autoloader verification
        Autoloader::register();
        $assert('BOOT-01', 'Standalone PSR-4 Autoloader is registered', Autoloader::isRegistered());
        $assert('BOOT-02', 'WTSBill\\ namespace is mapped in Autoloader', isset(Autoloader::getPrefixes()['WTSBill\\']));

        // 2. Env loading
        Env::load($rootPath . '/.env');
        $assert('BOOT-03', 'Environment file loaded', Env::isLoaded());
        $assert('BOOT-04', 'env() reads APP_NAME', env('APP_NAME') === 'WTSBill ERP');

        // 3. Config bootstrapping
        Config::loadFromDirectory($rootPath . '/config');
        $assert('BOOT-05', 'Config repository bootstrapped', Config::isBootstrapped());
        $assert('BOOT-06', 'config(app.timezone) is Asia/Kolkata', config('app.timezone') === 'Asia/Kolkata');
        $assert('BOOT-07', 'config(session.cookie) is configured', config('session.cookie') === 'wtsbill_session');
        $assert('BOOT-08', 'config(security.csrf.token_name) is set', config('security.csrf.token_name') === '_csrf_token');

        // 4. Database configuration validation & PDO connection
        $dbConfig = config('database.connections.mysql');
        $assert('BOOT-09', 'Database config has host, port, database', 
            isset($dbConfig['host'], $dbConfig['port'], $dbConfig['database']));
        $pdo = Database::pdo();
        $assert('BOOT-10', 'Native PDO Connection verified', $pdo instanceof PDO);
        $assert('BOOT-11', 'PDO error mode is ERRMODE_EXCEPTION', $pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION);

        // 5. View rendering & traversal security
        View::setBasePath($rootPath . '/views');
        $rendered = View::render('components/test_card', [
            'title' => 'Test Card Title',
            'message' => 'Test Card Message <script>alert(1)</script>'
        ]);
        $assert('BOOT-12', 'View renders template correctly', str_contains($rendered, 'Test Card Title'));
        $assert('BOOT-13', 'View template escapes unsafe HTML via e()', str_contains($rendered, '&lt;script&gt;'));

        // Traversal security check
        $traversalBlocked = false;
        try {
            View::render('../../config/app');
        } catch (\Throwable $e) {
            $traversalBlocked = true;
        }
        $assert('BOOT-14', 'View resolution blocks directory traversal attempt', $traversalBlocked === true);

        // 6. Session infrastructure & CSRF
        Session::start(['save_path' => $rootPath . '/storage/sessions']);
        $assert('BOOT-15', 'Secure Session started', Session::isStarted());
        $csrfToken = Session::csrfToken();
        $assert('BOOT-16', 'CSRF token generated (64 hex chars)', strlen($csrfToken) === 64);
        $assert('BOOT-17', 'CSRF token verifies with correct token', Session::verifyCsrfToken($csrfToken) === true);
        $assert('BOOT-18', 'CSRF token fails with invalid token', Session::verifyCsrfToken('fake_token_123') === false);

        Session::setTenantContext(1, 2);
        $assert('BOOT-19', 'Tenant context companyId stored and retrieved', Session::getCompanyId() === 1);
        $assert('BOOT-20', 'Tenant context branchId stored and retrieved', Session::getBranchId() === 2);

        // 7. Logger and credential redaction
        Logger::setLogDirectory($rootPath . '/storage/logs');
        Logger::info('User login successful', ['email' => 'anil.d@wtsbill.in', 'password' => 'secretP@ss123']);
        $logFile = $rootPath . '/storage/logs/wtsbill-' . date('Y-m-d') . '.log';
        $assert('BOOT-21', 'Logger file created', file_exists($logFile));
        $logContent = file_get_contents($logFile);
        $assert('BOOT-22', 'Sensitive password is redacted in log file', str_contains($logContent, '[REDACTED]'));
        $assert('BOOT-23', 'Raw password is never written to log file', !str_contains($logContent, 'secretP@ss123'));

        // 8. Exceptions
        $httpNotFound = HttpException::notFound('Item missing');
        $assert('BOOT-24', 'HttpException::notFound has status 404', $httpNotFound->getStatusCode() === 404);
        $valEx = new ValidationException(['gstin' => 'Invalid GSTIN']);
        $assert('BOOT-25', 'ValidationException has status 422', $valEx->getStatusCode() === 422 && isset($valEx->getErrors()['gstin']));

        // 9. End-to-end Router dispatch from routes/web.php and routes/api.php
        $router = new Router();
        require $rootPath . '/routes/web.php';
        require $rootPath . '/routes/api.php';

        $rootReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
        $rootRes = $router->dispatch($rootReq);
        $assert('BOOT-26', 'Root web route / returns 200 HTML', $rootRes->getStatusCode() === 200 && str_contains($rootRes->getContent(), 'WTSBill ERP'));

        $healthReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/health', 'HTTP_ACCEPT' => 'application/json']);
        $healthRes = $router->dispatch($healthReq);
        $healthData = json_decode($healthRes->getContent(), true);
        $assert('BOOT-27', 'API route /api/health returns 200 OK', $healthRes->getStatusCode() === 200 && $healthData['status'] === 'ok');
        $assert('BOOT-28', 'API health reports database connected', $healthData['database'] === 'connected');
    }
}
