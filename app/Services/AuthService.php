<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Config;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Logger;
use WTSBill\Core\Session;
use WTSBill\Repositories\UserRepository;

/**
 * Authentication and Multi-Tenant Context Service.
 */
class AuthService
{
    private UserRepository $userRepository;

    public function __construct(?UserRepository $userRepository = null)
    {
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    /**
     * Authenticate user credentials and establish secure session.
     *
     * @param string $email User email address
     * @param string $password Plaintext password
     * @return array Authenticated user record
     * @throws ValidationException On invalid credentials or inactive account
     */
    public function authenticate(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            Logger::warning("Failed login attempt for non-existent email: {$email}");
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        // Account status check
        if ((int) $user['is_active'] !== 1 || strtoupper((string) ($user['status'] ?? 'ACTIVE')) !== 'ACTIVE') {
            Logger::warning("Login attempt on inactive account: {$email} (ID: {$user['id']})");
            throw ValidationException::withMessages([
                'email' => 'Your account is inactive. Please contact your system administrator.'
            ]);
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            Logger::warning("Invalid password login attempt for email: {$email}");
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        // Check if password hash needs updating to current cost
        $bcryptCost = (int) Config::get('security.bcrypt_cost', 10);
        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => $bcryptCost])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $bcryptCost]);
            $this->userRepository->updatePassword((int) $user['id'], $newHash);
        }

        // Establish session and protect against session fixation
        Session::start();
        Session::regenerateId(true);

        // Determine tenant and branch context
        $companyId = (int) ($user['current_company_id'] ?? 1);
        
        // Find default branch for this user or fallback to main branch
        $branchRepo = new \WTSBill\Repositories\BranchRepository();
        $defaultBranch = $branchRepo->getUserDefaultBranch((int) $user['id'], $companyId);
        $branchId = $defaultBranch ? (int)$defaultBranch['id'] : 1;
        $branchName = $defaultBranch ? (string)$defaultBranch['name'] : 'Main Branch';
        $branchCode = $defaultBranch ? (string)($defaultBranch['branch_code'] ?? $defaultBranch['code'] ?? '') : '';

        Session::set('user_id', (int) $user['id']);
        Session::set('user_name', $user['name']);
        Session::set('user_email', $user['email']);
        Session::set('user_role', strtoupper($user['role']));
        Session::set('current_company_id', $companyId);
        Session::set('current_branch_id', $branchId);
        Session::set('current_branch_name', $branchName);
        Session::set('current_branch_code', $branchCode);
        Session::set('authenticated_at', time());
        Session::set('last_activity_at', time());

        // Update last login timestamp in DB
        $this->userRepository->updateLastLogin((int) $user['id']);

        // Invalidate any gate cache
        \WTSBill\Auth\Gate::flushCache();

        Logger::info("User successfully logged in: {$user['email']} (ID: {$user['id']}, Role: {$user['role']})");

        return $user;
    }

    /**
     * Terminate active user session.
     */
    public function logout(): void
    {
        $userId = Session::get('user_id');
        $email = Session::get('user_email');

        if ($userId) {
            Logger::info("User logged out: {$email} (ID: {$userId})");
        }

        \WTSBill\Auth\Gate::flushCache();
        Session::clear();
        Session::destroy();
    }

    /**
     * Check if user is currently authenticated and session is active.
     */
    public function check(): bool
    {
        if (!Session::isStarted()) {
            Session::start();
        }

        $userId = Session::get('user_id');
        if (!$userId) {
            return false;
        }

        // Check session expiration
        $lastActivity = (int) Session::get('last_activity_at', 0);
        $lifetime = (int) Config::get('session.lifetime', 7200);

        if ($lastActivity > 0 && (time() - $lastActivity) > $lifetime) {
            $this->logout();
            return false;
        }

        // Refresh activity timestamp
        Session::set('last_activity_at', time());

        return true;
    }

    /**
     * Get authenticated user ID.
     */
    public function id(): ?int
    {
        return $this->check() ? (int) Session::get('user_id') : null;
    }

    /**
     * Get authenticated user record.
     */
    public function user(): ?array
    {
        $id = $this->id();
        return $id ? $this->userRepository->findWithRolesAndBranches($id) : null;
    }

    /**
     * Check if authenticated user has a specific role.
     */
    public function hasRole(string|array $roles): bool
    {
        if (!$this->check()) {
            return false;
        }

        $userRole = strtoupper((string) Session::get('user_role', ''));
        $allowed = array_map('strtoupper', (array) $roles);

        // ADMIN has universal permission
        if ($userRole === 'ADMIN') {
            return true;
        }

        return in_array($userRole, $allowed, true);
    }

    /**
     * Initiate password reset workflow.
     */
    public function initiatePasswordReset(string $email): ?string
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            // Silently return to prevent user enumeration attacks
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $this->userRepository->storePasswordResetToken($email, $token);

        Logger::info("Password reset token generated for email: {$email}");

        return $token;
    }

    /**
     * Reset password using verification token.
     */
    public function resetPassword(string $email, string $token, string $newPassword): bool
    {
        $valid = $this->userRepository->verifyPasswordResetToken($email, $token);
        if (!$valid) {
            throw ValidationException::withMessages([
                'token' => 'The password reset token is invalid or has expired.'
            ]);
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'User not found.'
            ]);
        }

        $bcryptCost = (int) Config::get('security.bcrypt_cost', 10);
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => $bcryptCost]);

        $this->userRepository->updatePassword((int) $user['id'], $hashed);
        $this->userRepository->deletePasswordResetToken($email);

        Logger::info("Password reset successfully completed for user: {$email}");

        return true;
    }
}

// Backward compatibility alias
if (!class_exists('App\Services\AuthService', false)) {
    class_alias(AuthService::class, 'App\Services\AuthService');
}
