<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\AuthService;
use WTSBill\Validators\LoginValidator;

/**
 * Authentication Controller handling Login, Logout, and Password Reset workflows.
 */
class AuthController
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * Display the login form.
     */
    public function loginForm(Request $request): Response
    {
        if ($this->authService->check()) {
            return Response::redirect('/');
        }

        $html = View::render('auth/login', [
            'csrf_token' => Session::csrfToken(),
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ]);

        return Response::html($html);
    }

    /**
     * Process authentication credentials.
     */
    public function login(Request $request): Response
    {
        try {
            $credentials = LoginValidator::validate($request->input());
            $user = $this->authService->authenticate($credentials['email'], $credentials['password']);

            if ($request->expectsJson()) {
                return Response::success([
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                    ]
                ], 'Authentication successful.');
            }

            // Redirect to intended URL or default to dashboard
            $intended = Session::get('url.intended', '/');
            Session::remove('url.intended');

            return Response::redirect($intended);

        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return Response::error($e->getMessage(), 422, $e->getErrors());
            }

            $errorMessage = reset($e->getErrors()) ?: $e->getMessage();
            Session::flash('error', $errorMessage);
            return Response::redirect('/login');
        }
    }

    /**
     * Process logout request.
     */
    public function logout(Request $request): Response
    {
        $this->authService->logout();

        if ($request->expectsJson()) {
            return Response::success(null, 'Logged out successfully.');
        }

        return Response::redirect('/login');
    }

    /**
     * Display the forgot password form.
     */
    public function forgotPasswordForm(Request $request): Response
    {
        $html = View::render('auth/forgot-password', [
            'csrf_token' => Session::csrfToken(),
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ]);

        return Response::html($html);
    }

    /**
     * Generate password reset token.
     */
    public function sendResetLink(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($request->expectsJson()) {
                return Response::error('Valid email is required.', 422, ['email' => 'Valid email is required.']);
            }
            Session::flash('error', 'Please provide a valid email address.');
            return Response::redirect('/forgot-password');
        }

        $token = $this->authService->initiatePasswordReset($email);

        $msg = 'If your email is registered in our system, a password reset token has been registered.';

        if ($request->expectsJson()) {
            return Response::success(['token' => $token], $msg);
        }

        Session::flash('success', $msg);
        return Response::redirect('/forgot-password');
    }

    /**
     * Display the password reset form.
     */
    public function resetPasswordForm(Request $request): Response
    {
        $email = (string) $request->query('email', '');
        $token = (string) $request->query('token', '');

        $html = View::render('auth/reset-password', [
            'csrf_token' => Session::csrfToken(),
            'email' => $email,
            'token' => $token,
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ]);

        return Response::html($html);
    }

    /**
     * Process password reset update.
     */
    public function resetPassword(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $token = trim((string) $request->input('token', ''));
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');

        if (strlen($password) < 6) {
            $msg = 'Password must be at least 6 characters.';
            if ($request->expectsJson()) {
                return Response::error($msg, 422, ['password' => $msg]);
            }
            Session::flash('error', $msg);
            return Response::redirect("/reset-password?email=" . urlencode($email) . "&token=" . urlencode($token));
        }

        if ($password !== $passwordConfirmation) {
            $msg = 'Password confirmation does not match.';
            if ($request->expectsJson()) {
                return Response::error($msg, 422, ['password_confirmation' => $msg]);
            }
            Session::flash('error', $msg);
            return Response::redirect("/reset-password?email=" . urlencode($email) . "&token=" . urlencode($token));
        }

        try {
            $this->authService->resetPassword($email, $token, $password);

            $msg = 'Your password has been successfully reset. Please log in with your new password.';
            if ($request->expectsJson()) {
                return Response::success(null, $msg);
            }

            Session::flash('success', $msg);
            return Response::redirect('/login');

        } catch (ValidationException $e) {
            $msg = reset($e->getErrors()) ?: $e->getMessage();
            if ($request->expectsJson()) {
                return Response::error($msg, 422, $e->getErrors());
            }

            Session::flash('error', $msg);
            return Response::redirect("/reset-password?email=" . urlencode($email) . "&token=" . urlencode($token));
        }
    }
}

// Backward compatibility alias
if (!class_exists('App\Controllers\AuthController', false)) {
    class_alias(AuthController::class, 'App\Controllers\AuthController');
}
