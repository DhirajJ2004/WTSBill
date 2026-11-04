<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\AuthService;

/**
 * Feature Test Suite for Light and Dark Theme Switcher & Persistence.
 */
class ThemePersistenceTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        // 1. Authenticate user
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $userId = (int)$user['id'];
        $csrfToken = Session::csrfToken();

        $assert('THEME-01', 'User authenticates and establishes session context for theme management',
            $user !== null && $userId > 0);

        // 2. Switch Theme to DARK via API
        $darkReq = new Request([], ['theme' => 'dark', '_csrf_token' => $csrfToken], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/user/theme',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $darkRes = $router->dispatch($darkReq);
        $darkJson = json_decode($darkRes->getContent(), true);

        $dbUserDark = Database::selectOne("SELECT theme FROM users WHERE id = ?", [$userId]);

        $assert('THEME-02', 'POST /api/user/theme persists dark theme preference to users table & session',
            $darkRes->getStatusCode() === 200 &&
            isset($darkJson['success']) && $darkJson['success'] === true &&
            $darkJson['theme'] === 'dark' &&
            Session::get('theme') === 'dark' &&
            ($dbUserDark['theme'] ?? '') === 'dark'
        );

        // 3. Layout Rendering in Dark Mode
        $darkLayoutHtml = View::renderWithLayout('dashboard/index', [
            'title' => 'Dashboard',
            'currentUri' => '/',
        ], 'layouts/app');

        $assert('THEME-03', 'app.php layout renders data-theme="dark" when session theme is dark',
            str_contains($darkLayoutHtml, 'data-theme="dark"') &&
            str_contains($darkLayoutHtml, 'localStorage.getItem(\'wtsbill_theme\')')
        );

        // 4. Switch Theme to LIGHT
        $lightReq = new Request([], ['theme' => 'light', '_csrf_token' => $csrfToken], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/user/theme',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $lightRes = $router->dispatch($lightReq);
        $lightJson = json_decode($lightRes->getContent(), true);

        $dbUserLight = Database::selectOne("SELECT theme FROM users WHERE id = ?", [$userId]);

        $assert('THEME-04', 'POST /user/theme persists light theme preference to users table & session',
            $lightRes->getStatusCode() === 200 &&
            isset($lightJson['success']) && $lightJson['success'] === true &&
            $lightJson['theme'] === 'light' &&
            Session::get('theme') === 'light' &&
            ($dbUserLight['theme'] ?? '') === 'light'
        );

        // 5. Layout Rendering in Light Mode
        $lightLayoutHtml = View::renderWithLayout('dashboard/index', [
            'title' => 'Dashboard',
            'currentUri' => '/',
        ], 'layouts/app');

        $assert('THEME-05', 'app.php layout renders data-theme="light" when session theme is light',
            str_contains($lightLayoutHtml, 'data-theme="light"')
        );

        // 6. Print Stylesheet Isolation Safeguard
        $printCssExists = file_exists(dirname(__DIR__, 2) . '/views/sales/print.php');
        $printContent = $printCssExists ? file_get_contents(dirname(__DIR__, 2) . '/views/sales/print.php') : '';

        $assert('THEME-06', 'Print templates strictly enforce pure white backgrounds and dark text regardless of user theme',
            $printCssExists &&
            str_contains($printContent, '@media print') &&
            str_contains($printContent, 'background: #ffffff !important;') &&
            str_contains($printContent, 'color: #000000 !important;')
        );

        // 7. Reset back to original
        Session::set('theme', 'dark');
        Database::update('users', ['theme' => 'dark'], 'id = ?', [$userId]);
    }
}

