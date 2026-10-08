<?php

namespace App\Controllers;

use App\Services\UserService;
use App\Models\User;
use App\Http\Request;

class UserController
{
    public function index(Request $request)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $filters = [
            'role'      => $request->get('role'),
            'is_active' => $request->get('is_active'),
            'search'    => $request->get('search'),
        ];

        $users = UserService::getUsers($companyId, $filters);
        return ['success' => true, 'data' => $users];
    }

    public function store(Request $request)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $currentUser = $_SESSION['user']['name'] ?? 'Admin';
        
        $input = [
            'name'     => $request->get('name'),
            'email'    => $request->get('email'),
            'role'     => $request->get('role') ?: 'STAFF',
            'password' => $request->get('password'),
        ];

        return UserService::createUser($input, $companyId, $currentUser);
    }

    public function update(Request $request, int $id)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $currentUser = $_SESSION['user']['name'] ?? 'Admin';

        $input = [
            'name'      => $request->get('name'),
            'role'      => $request->get('role'),
            'is_active' => $request->get('is_active'),
            'password'  => $request->get('password'),
        ];

        return UserService::updateUser($id, $input, $companyId, $currentUser);
    }

    public function checkPermission(Request $request)
    {
        $userId = (int)$request->get('user_id');
        $permission = (string)$request->get('permission');
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));

        $user = User::find($userId);
        if (!$user) {
            return ['success' => false, 'allowed' => false, 'message' => 'User not found.'];
        }

        $allowed = UserService::checkPermission($user, $permission, $companyId);
        return [
            'success'    => true,
            'user_id'    => $userId,
            'permission' => $permission,
            'allowed'    => $allowed,
        ];
    }
}
