<?php

/**
 * WTSBill ERP - Role-Based Access Control (RBAC) Matrix
 */

return [
    'roles' => [
        'SUPER_ADMIN' => [
            'label' => 'Super Administrator',
            'permissions' => ['*']
        ],
        'ADMIN' => [
            'label' => 'Company Administrator',
            'permissions' => [
                'dashboard.*', 'sales.*', 'purchases.*', 'inventory.*',
                'accounting.*', 'reports.*', 'settings.*', 'users.*', 'parties.*', 'payments.*', 'expenses.*'
            ]
        ],
        'ACCOUNTANT' => [
            'label' => 'Accountant',
            'permissions' => [
                'dashboard.view', 'sales.view', 'sales.create', 'sales.edit',
                'purchases.view', 'purchases.create', 'purchases.edit',
                'payments.*', 'expenses.*', 'accounting.*', 'reports.*', 'parties.*'
            ]
        ],
        'AUDITOR' => [
            'label' => 'Auditor',
            'permissions' => [
                'dashboard.view', 'sales.view', 'purchases.view',
                'accounting.view', 'reports.view', 'audit_logs.view'
            ]
        ],
        'INVENTORY_MANAGER' => [
            'label' => 'Inventory Manager',
            'permissions' => [
                'dashboard.view', 'inventory.*', 'purchases.view', 'purchases.create'
            ]
        ],
        'SALES_EXECUTIVE' => [
            'label' => 'Sales Executive',
            'permissions' => [
                'dashboard.view', 'sales.view', 'sales.create', 'quotations.*', 'parties.view', 'parties.create'
            ]
        ]
    ]
];
