<?php

declare(strict_types=1);

namespace Tests\Security;

use WTSBill\Core\Request;
use WTSBill\Core\Session;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Middleware\AuthenticationMiddleware;
use WTSBill\Services\AuthService;

/**
 * Authentication and Session Security Regression Test (Chunk 19).
 * Verifies session fixation defenses, password hash integrity,
 * generic timing-safe enumeration prevention, and open-redirect sanitization.
 */
class AuthenticationSecurityTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        $authService = new AuthService();

        // 1. Session Fixation Defense
        Session::resetRegenerated();
        $user = $authService->authenticate('anil.d@wtsbill.in', 'password123');

        $assert('AUTHSEC-01', 'Session ID regenerates upon successful login to prevent fixation', 
            Session::wasRegenerated() === true);

        // 2. Password Hash Algorithm & Cost Verification
        $hashInfo = password_get_info($user['password']);
        $assert('AUTHSEC-02', 'Password hash uses strong Bcrypt algorithm with cost >= 10', 
            $hashInfo['algoName'] === 'bcrypt' && ($hashInfo['options']['cost'] ?? 0) >= 10);

        // 3. Inactive User Login Rejection
        $inactiveCaught = false;
        try {
            $authService->authenticate('priya.s@wtsbill.in', 'wrong_pass');
        } catch (ValidationException $e) {
            $inactiveCaught = true;
        }
        $assert('AUTHSEC-03', 'Invalid credentials on valid user account throw ValidationException', 
            $inactiveCaught);

        // 4. Timing-Safe Generic Error (User Enumeration Prevention)
        $nonExistentMsg = '';
        try {
            $authService->authenticate('ghost.user.999@wtsbill.in', 'any_pass');
        } catch (ValidationException $e) {
            $nonExistentMsg = $e->getErrors()['email'] ?? '';
        }

        $wrongPassMsg = '';
        try {
            $authService->authenticate('anil.d@wtsbill.in', 'incorrect_password_xyz');
        } catch (ValidationException $e) {
            $wrongPassMsg = $e->getErrors()['email'] ?? '';
        }

        $assert('AUTHSEC-04', 'Non-existent user email returns generic error message', 
            $nonExistentMsg === 'These credentials do not match our records.');
        $assert('AUTHSEC-05', 'Wrong password returns identical error message to prevent enumeration', 
            $nonExistentMsg === $wrongPassMsg);

        // 5. Open Redirect Protection in AuthenticationMiddleware
        Session::clear();
        $authMiddleware = new AuthenticationMiddleware($authService);

        // A. External absolute URL attack
        $evilReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'https://evil-phishing.com/steal']);
        $authMiddleware->handle($evilReq);
        $assert('AUTHSEC-06', 'External absolute target URL is sanitized to / to prevent open redirect', 
            Session::get('url.intended') === '/');

        // B. Protocol-relative URL attack (//evil.com)
        $protocolRelativeReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '//evil.com/harvest']);
        $authMiddleware->handle($protocolRelativeReq);
        $assert('AUTHSEC-07', 'Protocol-relative URL (//evil.com) is sanitized to / to prevent open redirect', 
            Session::get('url.intended') === '/');

        // C. Legitimate internal route preserved
        $legitReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sales/invoices']);
        $authMiddleware->handle($legitReq);
        $assert('AUTHSEC-08', 'Legitimate internal relative path (/sales/invoices) is preserved as intended URL', 
            Session::get('url.intended') === '/sales/invoices');

        // 6. Logout Cleanup
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $authService->logout();
        $assert('AUTHSEC-09', 'Logout completely clears user_id and invalidates authentication session', 
            !$authService->check() && Session::get('user_id') === null);

        // 7. Secure Password Reset Token Entropy
        $resetToken = $authService->initiatePasswordReset('anil.d@wtsbill.in');
        $assert('AUTHSEC-10', 'Password reset generates 64-hex-char cryptographically secure token', 
            $resetToken !== null && strlen($resetToken) === 64 && ctype_xdigit($resetToken));

        // Clean up
        Session::clear();
    }
}
