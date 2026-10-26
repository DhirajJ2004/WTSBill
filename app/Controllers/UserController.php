<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;

/**
 * User & Roles Controller.
 * Manages operator access, invitation, and role assignments.
 */
class UserController
{
    /**
     * User directory and roles view.
     */
    public function index(Request $req): Response
    {
        $users = Database::select(
            "SELECT id, name, email, phone, role, COALESCE(current_company_id, 1) as company_id, status, is_active, created_at 
             FROM users 
             ORDER BY id ASC"
        );

        $html = View::renderWithLayout('users/index', [
            'title' => 'Users & Roles',
            'currentUri' => '/users',
            'users' => $users,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Create/invite new user.
     */
    public function store(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $name = trim((string)($payload['name'] ?? ''));
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $phone = trim((string)($payload['phone'] ?? ''));
        $role = strtoupper(trim((string)($payload['role'] ?? 'OPERATOR')));
        $password = (string)($payload['password'] ?? 'password123');

        if (empty($name)) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Full name is required.'], 422);
            }
            Session::flash('error', 'Full name is required.');
            return Response::redirect('/users');
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Valid email address is required.'], 422);
            }
            Session::flash('error', 'Valid email address is required.');
            return Response::redirect('/users');
        }

        // Check duplicate email
        $existing = Database::selectOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => "User with email '{$email}' already exists."], 422);
            }
            Session::flash('error', "User with email '{$email}' already exists.");
            return Response::redirect('/users');
        }

        $validRoles = ['ADMIN', 'ACCOUNTANT', 'MANAGER', 'OPERATOR', 'AUDITOR', 'SALES'];
        if (!in_array($role, $validRoles)) {
            $role = 'OPERATOR';
        }

        try {
            $id = Database::insert('users', [
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $phone ?: null,
                'role' => $role,
                'current_company_id' => $companyId,
                'status' => 'ACTIVE',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "User '{$name}' created and assigned '{$role}' role successfully.",
                ], 201);
            }

            Session::flash('success', "User '{$name}' created successfully.");
            return Response::redirect('/users');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create user: ' . $e->getMessage());
            return Response::redirect('/users');
        }
    }

    protected function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $contentType = (string)($req->header('content-type') ?? '');
        $xRequestedWith = (string)($req->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}
