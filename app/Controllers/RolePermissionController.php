<?php

namespace App\Controllers;

use App\Services\PermissionManager;
use App\Http\Request;

class RolePermissionController
{
    public function index()
    {
        $roles = PermissionManager::getAllRolesWithPermissions();
        return [
            'success' => true,
            'data'    => $roles,
        ];
    }

    public function show(string $role)
    {
        $perms = PermissionManager::getPermissionsForRole($role);
        return [
            'success'     => true,
            'role'        => $role,
            'permissions' => $perms,
        ];
    }
}
