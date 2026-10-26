<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Repositories\UserRepository;
use WTSBill\Services\AuthService;

/**
 * Feature Test for Authentication Workflows (Login, Logout, Session Lifecycle, Password Reset).
 */
class AuthenticationTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        $userRepo = new UserRepository();

        // Ensure session is started for testing
        Session::start();
        Session::clear();

        // 1. Successful Authentication
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $assert('AUTH-01', 'Valid credentials authenticate successfully', 
            $user !== null && $user['email'] === 'anil.d@wtsbill.in');
        $assert('AUTH-02', 'Session user_id is populated', Session::get('user_id') === 1);
        $assert('AUTH-03', 'Session user_role is set to ADMIN', Session::get('user_role') === 'ADMIN');
        $assert('AUTH-04', 'Session tenant company_id is set to 1', Session::get('current_company_id') === 1);
        $assert('AUTH-05', 'authService->check() returns true', $authService->check() === true);
        $assert('AUTH-06', 'authService->id() returns 1', $authService->id() === 1);

        // 2. Failed Authentication with Wrong Password
        Session::clear();
        $failedWrongPass = false;
        try {
            $authService->authenticate('anil.d@wtsbill.in', 'wrong_password_xyz');
        } catch (ValidationException $e) {
            $failedWrongPass = true;
            $errors = $e->getErrors();
            $assert('AUTH-07', 'Invalid password returns standard credential error', 
                str_contains($errors['email'] ?? '', 'credentials do not match'));
        }
        $assert('AUTH-08', 'Invalid password throws ValidationException', $failedWrongPass === true);
        $assert('AUTH-09', 'Failed login does not set session user_id', Session::get('user_id') === null);

        // 3. Failed Authentication with Non-Existent Email
        $failedNonExistent = false;
        try {
            $authService->authenticate('does_not_exist@wtsbill.in', 'password123');
        } catch (ValidationException $e) {
            $failedNonExistent = true;
            $errors = $e->getErrors();
            $assert('AUTH-10', 'Non-existent email uses identical error message to prevent enumeration', 
                str_contains($errors['email'] ?? '', 'credentials do not match'));
        }
        $assert('AUTH-11', 'Non-existent email throws ValidationException', $failedNonExistent === true);

        // 4. Logout Workflow
        $authService->authenticate('priya.s@wtsbill.in', 'password123');
        $assert('AUTH-12', 'Priya Sharma logged in', Session::get('user_id') === 2);
        $authService->logout();
        $assert('AUTH-13', 'Logout clears session user_id', Session::get('user_id') === null);
        $assert('AUTH-14', 'authService->check() returns false after logout', $authService->check() === false);

        // 5. Session Expiry Lifecycle
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        // Artificially age session beyond 7200 seconds
        Session::set('last_activity_at', time() - 8000);
        $assert('AUTH-15', 'Expired session is detected and check() returns false', $authService->check() === false);
        $assert('AUTH-16', 'Expired session clears user_id', Session::get('user_id') === null);

        // 6. Password Reset Token Generation & Verification
        $token = $authService->initiatePasswordReset('anil.d@wtsbill.in');
        $assert('AUTH-17', 'Password reset generates 64-character token', is_string($token) && strlen($token) === 64);

        $isValidToken = $userRepo->verifyPasswordResetToken('anil.d@wtsbill.in', $token);
        $assert('AUTH-18', 'Password reset token verifies successfully', $isValidToken === true);

        $isInvalidToken = $userRepo->verifyPasswordResetToken('anil.d@wtsbill.in', 'fake_token_abc');
        $assert('AUTH-19', 'Invalid reset token is rejected', $isInvalidToken === false);

        // Cleanup test token
        $userRepo->deletePasswordResetToken('anil.d@wtsbill.in');
        $assert('AUTH-20', 'Reset token deleted after cleanup', 
            $userRepo->verifyPasswordResetToken('anil.d@wtsbill.in', $token) === false);

        // 7. Full HTTP Route Authentication (/api/v1/auth/login)
        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/api.php';

        $apiLoginReq = new Request(
            [],
            [],
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/v1/auth/login', 'HTTP_CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode(['email' => 'anil.d@wtsbill.in', 'password' => 'password123'])
        );
        $apiLoginRes = $router->dispatch($apiLoginReq);
        $assert('AUTH-21', 'API login route returns 200 OK', $apiLoginRes->getStatusCode() === 200);
        $payload = json_decode($apiLoginRes->getContent(), true);
        $assert('AUTH-22', 'API login payload contains user details', $payload['data']['user']['email'] === 'anil.d@wtsbill.in');
    }
}
