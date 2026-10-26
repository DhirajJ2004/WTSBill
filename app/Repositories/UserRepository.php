<?php

declare(strict_types=1);

namespace WTSBill\Repositories;

use WTSBill\Core\Database;
use WTSBill\Repositories\BaseRepository;

/**
 * User Repository for Authentication and Access Control.
 */
class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected bool $tenantScoped = false; // User lookup by email is globally scoped

    /**
     * Find active user by email address.
     */
    public function findByEmail(string $email): ?array
    {
        $normalized = strtolower(trim($email));
        return Database::selectOne(
            "SELECT * FROM `users` WHERE LOWER(`email`) = :email AND `deleted_at` IS NULL LIMIT 1",
            ['email' => $normalized]
        );
    }

    /**
     * Find user with assigned roles and branches.
     */
    public function findWithRolesAndBranches(int $userId): ?array
    {
        $user = $this->find($userId);
        if (!$user) {
            return null;
        }

        // Fetch user roles
        $roles = Database::select(
            "SELECT r.id, r.name, r.slug 
             FROM `user_roles` ur 
             JOIN `roles` r ON ur.role_id = r.id 
             WHERE ur.user_id = :user_id",
            ['user_id' => $userId]
        );

        // Fetch user branches
        $branches = Database::select(
            "SELECT b.id, b.name, b.branch_code, b.is_main_branch 
             FROM `user_branches` ub 
             JOIN `branches` b ON ub.branch_id = b.id 
             WHERE ub.user_id = :user_id",
            ['user_id' => $userId]
        );

        $user['roles'] = $roles;
        $user['branches'] = $branches;

        return $user;
    }

    /**
     * Update user password hash.
     */
    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $updated = Database::update(
            'users',
            [
                'password' => $hashedPassword,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id = :id',
            ['id' => $userId]
        );
        return $updated > 0;
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin(int $userId): bool
    {
        $updated = Database::update(
            'users',
            [
                'last_login_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id = :id',
            ['id' => $userId]
        );
        return $updated > 0;
    }

    /**
     * Store password reset token.
     */
    public function storePasswordResetToken(string $email, string $token): bool
    {
        $normalized = strtolower(trim($email));

        // Delete existing reset tokens for this email
        Database::delete('password_resets', 'email = :email', ['email' => $normalized]);

        // Insert new reset token
        Database::insert('password_resets', [
            'email' => $normalized,
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Verify password reset token.
     */
    public function verifyPasswordResetToken(string $email, string $token): bool
    {
        $normalized = strtolower(trim($email));
        $record = Database::selectOne(
            "SELECT * FROM `password_resets` WHERE `email` = :email AND `token` = :token LIMIT 1",
            ['email' => $normalized, 'token' => $token]
        );

        if (!$record) {
            return false;
        }

        // Token expiry check (valid for 1 hour = 3600 seconds)
        $createdAt = strtotime($record['created_at']);
        if ($createdAt === false || (time() - $createdAt) > 3600) {
            return false;
        }

        return true;
    }

    /**
     * Delete password reset token after use.
     */
    public function deletePasswordResetToken(string $email): bool
    {
        $normalized = strtolower(trim($email));
        return Database::delete('password_resets', 'email = :email', ['email' => $normalized]) > 0;
    }
}

// Backward compatibility alias
if (!class_exists('App\Repositories\UserRepository', false)) {
    class_alias(UserRepository::class, 'App\Repositories\UserRepository');
}
