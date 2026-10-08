<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\TenantMiddleware;
use App\Auth\WorkspaceContext;

class AuthController
{
    /**
     * Handle Login submission.
     */
    public function login(): void
    {
        CsrfMiddleware::handle();

        $identifier = $_POST['identifier'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        $result = AuthService::login($identifier, $password, $remember);

        if (!$result['success']) {
            $_SESSION['auth_error'] = $result['message'];
            header('Location: ' . (function_exists('url') ? url('/login') : '/login'));
            exit();
        }

        header('Location: ' . $result['redirect']);
        exit();
    }

    /**
     * Handle Logout.
     */
    public function logout(): void
    {
        AuthService::logout();
        header('Location: ' . (function_exists('url') ? url('/login?logout=1') : '/login?logout=1'));
        exit();
    }

    /**
     * Handle Forgot Password submission.
     */
    public function forgotPassword(): void
    {
        CsrfMiddleware::handle();

        $email = $_POST['email'] ?? '';
        $result = AuthService::forgotPassword($email);

        $_SESSION['auth_flash'] = $result['message'];
        header('Location: ' . (function_exists('url') ? url('/forgot-password') : '/forgot-password'));
        exit();
    }

    /**
     * Handle Reset Password submission.
     */
    public function resetPassword(): void
    {
        CsrfMiddleware::handle();

        $email = $_POST['email'] ?? '';
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmation = $_POST['password_confirmation'] ?? '';

        $result = AuthService::resetPassword($email, $token, $password, $confirmation);

        if (!$result['success']) {
            $_SESSION['auth_error'] = $result['message'];
            header('Location: ' . (function_exists('url') ? url('/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email)) : '/reset-password'));
            exit();
        }

        header('Location: ' . (function_exists('url') ? url('/login?reset=1') : '/login?reset=1'));
        exit();
    }

    /**
     * Handle Workspace Selection (Company, Branch, Financial Year).
     */
    public function selectWorkspace(): void
    {
        CsrfMiddleware::handle();

        $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
        $companyId = (int)($_POST['company_id'] ?? 0);
        $branchId = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
        $financialYear = $_POST['financial_year'] ?? null;

        try {
            $result = AuthService::selectWorkspace($userId, $companyId, $branchId, $financialYear);
            header('Location: ' . $result['redirect']);
            exit();
        } catch (\Throwable $e) {
            $_SESSION['auth_error'] = $e->getMessage();
            header('Location: ' . (function_exists('url') ? url('/select-company') : '/select-company'));
            exit();
        }
    }
}
