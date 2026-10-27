<?php

declare(strict_types=1);

namespace Database\Seeders;

use WTSBill\Core\Database;

/**
 * Enterprise RBAC Roles & Permissions Seeder.
 */
class RbacPermissionSeeder
{
    public static function run(): array
    {
        // 1. Run DDL migration commands
        $migrationSql = file_get_contents(dirname(__DIR__) . '/migrations/2026_10_27_000003_rbac_permissions_and_audit_logs.sql');
        $statements = array_filter(array_map('trim', explode(';', $migrationSql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    Database::statement($stmt);
                } catch (\Throwable $e) {
                    // Ignore column already exists if MySQL version lacks IF NOT EXISTS for ADD COLUMN
                    if (!str_contains($e->getMessage(), 'Duplicate column')) {
                        error_log('Migration warning: ' . $e->getMessage());
                    }
                }
            }
        }

        // 2. Define standard Roles
        $rolesDef = [
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full administrative access to all modules, security settings, users, and audit logs.',
                'is_system' => 1,
            ],
            [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Access to payments, tax invoices, quotations, and expenses.',
                'is_system' => 1,
            ],
            [
                'name' => 'Inventory Manager',
                'slug' => 'inventory_manager',
                'description' => 'Access to warehouse balances, stock in-out management, adjustments, items, and categories.',
                'is_system' => 1,
            ],
            [
                'name' => 'Sales Executive',
                'slug' => 'sales_executive',
                'description' => 'Access to customer quotes, orders, tax invoices, and delivery challans.',
                'is_system' => 1,
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Lowest-privilege view-only dashboard access.',
                'is_system' => 1,
            ],
        ];

        $roleIdMap = [];
        foreach ($rolesDef as $r) {
            $existing = Database::selectOne("SELECT id FROM `roles` WHERE `slug` = :slug LIMIT 1", ['slug' => $r['slug']]);
            if ($existing) {
                $id = (int)$existing['id'];
                Database::update('roles', [
                    'name' => $r['name'],
                    'description' => $r['description'],
                    'is_system' => $r['is_system'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $id]);
            } else {
                $id = Database::insert('roles', [
                    'company_id' => 1,
                    'name' => $r['name'],
                    'slug' => $r['slug'],
                    'description' => $r['description'],
                    'is_system' => $r['is_system'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $roleIdMap[$r['slug']] = $id;
        }

        // 3. Define Permissions Matrix
        $permissions = [
            // Dashboard
            ['module' => 'dashboard', 'action' => 'view', 'slug' => 'dashboard.view', 'name' => 'View General Dashboard', 'description' => 'View the default system dashboard.'],
            ['module' => 'dashboard', 'action' => 'view', 'slug' => 'dashboard.payments.view', 'name' => 'View Payments Dashboard', 'description' => 'View receivables, payables, and cash flow overview.'],
            ['module' => 'dashboard', 'action' => 'view', 'slug' => 'dashboard.inventory.view', 'name' => 'View Inventory Dashboard', 'description' => 'View stock quantities, low stock, and warehouse valuation.'],

            // Sales & Billing — Invoices
            ['module' => 'invoices', 'action' => 'view', 'slug' => 'invoices.view', 'name' => 'View Tax Invoices', 'description' => 'View invoices directory and details.'],
            ['module' => 'invoices', 'action' => 'create', 'slug' => 'invoices.create', 'name' => 'Create Invoices', 'description' => 'Create new sales tax invoices.'],
            ['module' => 'invoices', 'action' => 'edit', 'slug' => 'invoices.edit', 'name' => 'Edit Invoices', 'description' => 'Modify existing draft/unpaid invoices.'],
            ['module' => 'invoices', 'action' => 'delete', 'slug' => 'invoices.delete', 'name' => 'Delete Invoices', 'description' => 'Delete or cancel tax invoices.'],
            ['module' => 'invoices', 'action' => 'print', 'slug' => 'invoices.print', 'name' => 'Print Invoices', 'description' => 'Print and export PDF tax invoices.'],

            // Sales & Billing — Quotations
            ['module' => 'quotations', 'action' => 'view', 'slug' => 'quotations.view', 'name' => 'View Quotations', 'description' => 'View quotes and sales orders.'],
            ['module' => 'quotations', 'action' => 'create', 'slug' => 'quotations.create', 'name' => 'Create Quotations', 'description' => 'Draft new quotations and pro-formas.'],
            ['module' => 'quotations', 'action' => 'edit', 'slug' => 'quotations.edit', 'name' => 'Edit Quotations', 'description' => 'Edit quotation details.'],
            ['module' => 'quotations', 'action' => 'delete', 'slug' => 'quotations.delete', 'name' => 'Delete Quotations', 'description' => 'Delete quotations.'],
            ['module' => 'quotations', 'action' => 'print', 'slug' => 'quotations.print', 'name' => 'Print Quotations', 'description' => 'Print official quotation documents.'],

            // Sales & Billing — Delivery Challans
            ['module' => 'challans', 'action' => 'view', 'slug' => 'challans.view', 'name' => 'View Challans', 'description' => 'View delivery challans.'],
            ['module' => 'challans', 'action' => 'create', 'slug' => 'challans.create', 'name' => 'Create Challans', 'description' => 'Create delivery challans.'],
            ['module' => 'challans', 'action' => 'edit', 'slug' => 'challans.edit', 'name' => 'Edit Challans', 'description' => 'Modify delivery challans.'],
            ['module' => 'challans', 'action' => 'delete', 'slug' => 'challans.delete', 'name' => 'Delete Challans', 'description' => 'Cancel delivery challans.'],
            ['module' => 'challans', 'action' => 'print', 'slug' => 'challans.print', 'name' => 'Print Challans', 'description' => 'Print delivery challans.'],

            // Expenses
            ['module' => 'expenses', 'action' => 'view', 'slug' => 'expenses.view', 'name' => 'View Expenses', 'description' => 'View business expense entries.'],
            ['module' => 'expenses', 'action' => 'create', 'slug' => 'expenses.create', 'name' => 'Record Expenses', 'description' => 'Record company expenses.'],
            ['module' => 'expenses', 'action' => 'edit', 'slug' => 'expenses.edit', 'name' => 'Edit Expenses', 'description' => 'Modify recorded expenses.'],
            ['module' => 'expenses', 'action' => 'delete', 'slug' => 'expenses.delete', 'name' => 'Delete Expenses', 'description' => 'Delete expense entries.'],

            // Payments & Receipts
            ['module' => 'payments', 'action' => 'view', 'slug' => 'payments.view', 'name' => 'View Payments & Receipts', 'description' => 'View payments received and disbursed.'],
            ['module' => 'payments', 'action' => 'create', 'slug' => 'payments.create', 'name' => 'Record Payments & Receipts', 'description' => 'Record customer receipts and supplier payments.'],
            ['module' => 'payments', 'action' => 'edit', 'slug' => 'payments.edit', 'name' => 'Edit Payments', 'description' => 'Modify payment allocations.'],
            ['module' => 'payments', 'action' => 'delete', 'slug' => 'payments.delete', 'name' => 'Delete Payments', 'description' => 'Delete or void payment records.'],
            ['module' => 'payments', 'action' => 'print', 'slug' => 'payments.print', 'name' => 'Print Payment Vouchers', 'description' => 'Print money receipts and vouchers.'],

            // Inventory & Stock Balances
            ['module' => 'inventory', 'action' => 'view', 'slug' => 'inventory.view', 'name' => 'View Warehouse Stock', 'description' => 'View multi-warehouse stock balances and ledger.'],
            ['module' => 'inventory', 'action' => 'create', 'slug' => 'inventory.stock.add', 'name' => 'Add Inward Stock', 'description' => 'Add inbound physical stock directly.'],
            ['module' => 'inventory', 'action' => 'delete', 'slug' => 'inventory.stock.remove', 'name' => 'Remove Outward Stock', 'description' => 'Remove or write-off stock.'],
            ['module' => 'inventory', 'action' => 'view', 'slug' => 'inventory.adjustment.view', 'name' => 'View Stock Adjustments', 'description' => 'View stock adjustment history.'],
            ['module' => 'inventory', 'action' => 'create', 'slug' => 'inventory.adjustment.create', 'name' => 'Record Stock Adjustments', 'description' => 'Create stock reconciliation adjustments.'],
            ['module' => 'inventory', 'action' => 'edit', 'slug' => 'inventory.adjustment.edit', 'name' => 'Edit Stock Adjustments', 'description' => 'Edit reconciliation adjustments.'],
            ['module' => 'inventory', 'action' => 'delete', 'slug' => 'inventory.adjustment.delete', 'name' => 'Delete Stock Adjustments', 'description' => 'Revert and delete stock adjustments.'],

            // Products & Catalog
            ['module' => 'items', 'action' => 'view', 'slug' => 'items.view', 'name' => 'View Items & Catalog', 'description' => 'View item directory and SKUs.'],
            ['module' => 'items', 'action' => 'create', 'slug' => 'items.create', 'name' => 'Create Items', 'description' => 'Create new product or service.'],
            ['module' => 'items', 'action' => 'edit', 'slug' => 'items.edit', 'name' => 'Edit Items', 'description' => 'Update item prices, tax, and details.'],
            ['module' => 'items', 'action' => 'delete', 'slug' => 'items.delete', 'name' => 'Delete Items', 'description' => 'Delete items from catalog.'],

            // Categories
            ['module' => 'categories', 'action' => 'view', 'slug' => 'categories.view', 'name' => 'View Categories', 'description' => 'View item categories.'],
            ['module' => 'categories', 'action' => 'create', 'slug' => 'categories.create', 'name' => 'Create Categories', 'description' => 'Create item categories.'],
            ['module' => 'categories', 'action' => 'edit', 'slug' => 'categories.edit', 'name' => 'Edit Categories', 'description' => 'Update category details.'],
            ['module' => 'categories', 'action' => 'delete', 'slug' => 'categories.delete', 'name' => 'Delete Categories', 'description' => 'Delete categories.'],

            // Customers
            ['module' => 'customers', 'action' => 'view', 'slug' => 'customers.view', 'name' => 'View Customers', 'description' => 'View customer master directory.'],
            ['module' => 'customers', 'action' => 'create', 'slug' => 'customers.create', 'name' => 'Create Customers', 'description' => 'Add new customer.'],
            ['module' => 'customers', 'action' => 'edit', 'slug' => 'customers.edit', 'name' => 'Edit Customers', 'description' => 'Update customer records.'],
            ['module' => 'customers', 'action' => 'delete', 'slug' => 'customers.delete', 'name' => 'Delete Customers', 'description' => 'Delete customer records.'],

            // Suppliers
            ['module' => 'suppliers', 'action' => 'view', 'slug' => 'suppliers.view', 'name' => 'View Suppliers', 'description' => 'View supplier vendor directory.'],
            ['module' => 'suppliers', 'action' => 'create', 'slug' => 'suppliers.create', 'name' => 'Create Suppliers', 'description' => 'Add new supplier.'],
            ['module' => 'suppliers', 'action' => 'edit', 'slug' => 'suppliers.edit', 'name' => 'Edit Suppliers', 'description' => 'Update supplier information.'],
            ['module' => 'suppliers', 'action' => 'delete', 'slug' => 'suppliers.delete', 'name' => 'Delete Suppliers', 'description' => 'Delete suppliers.'],

            // Purchase Bills
            ['module' => 'bills', 'action' => 'view', 'slug' => 'bills.view', 'name' => 'View Purchase Bills', 'description' => 'View purchase bills and payables.'],
            ['module' => 'bills', 'action' => 'create', 'slug' => 'bills.create', 'name' => 'Create Purchase Bills', 'description' => 'Enter purchase bills.'],
            ['module' => 'bills', 'action' => 'edit', 'slug' => 'bills.edit', 'name' => 'Edit Purchase Bills', 'description' => 'Modify purchase bills.'],
            ['module' => 'bills', 'action' => 'delete', 'slug' => 'bills.delete', 'name' => 'Delete Purchase Bills', 'description' => 'Delete purchase bills.'],
            ['module' => 'bills', 'action' => 'print', 'slug' => 'bills.print', 'name' => 'Print Purchase Bills', 'description' => 'Export/print purchase bills.'],

            // Accounting
            ['module' => 'accounting', 'action' => 'view', 'slug' => 'accounting.view', 'name' => 'View Chart of Accounts & Journals', 'description' => 'View ledger, journal entries, and COA.'],
            ['module' => 'accounting', 'action' => 'create', 'slug' => 'accounting.create', 'name' => 'Create Journal Entries', 'description' => 'Post manual double-entry journals.'],
            ['module' => 'accounting', 'action' => 'edit', 'slug' => 'accounting.edit', 'name' => 'Edit Accounts', 'description' => 'Edit account heads and groupings.'],
            ['module' => 'accounting', 'action' => 'delete', 'slug' => 'accounting.delete', 'name' => 'Delete Accounts/Journals', 'description' => 'Void journal entries.'],

            // Reports
            ['module' => 'reports', 'action' => 'view', 'slug' => 'reports.view', 'name' => 'View Financial Reports', 'description' => 'View Profit & Loss, Balance Sheet, GST reports.'],
            ['module' => 'reports', 'action' => 'export', 'slug' => 'reports.export', 'name' => 'Export Reports', 'description' => 'Export financial statements to CSV/Excel.'],

            // Users & Security
            ['module' => 'users', 'action' => 'view', 'slug' => 'users.view', 'name' => 'View Users', 'description' => 'View user list.'],
            ['module' => 'users', 'action' => 'create', 'slug' => 'users.create', 'name' => 'Create Users', 'description' => 'Invite new users.'],
            ['module' => 'users', 'action' => 'edit', 'slug' => 'users.edit', 'name' => 'Edit Users', 'description' => 'Edit user status, password reset, branch.'],
            ['module' => 'users', 'action' => 'delete', 'slug' => 'users.delete', 'name' => 'Deactivate Users', 'description' => 'Deactivate or delete users.'],
            ['module' => 'users', 'action' => 'manage', 'slug' => 'users.manage', 'name' => 'Manage Users & Permissions', 'description' => 'Full user and role assignment management.'],

            // Roles & Permissions
            ['module' => 'roles', 'action' => 'view', 'slug' => 'roles.view', 'name' => 'View Roles', 'description' => 'View role matrix.'],
            ['module' => 'roles', 'action' => 'manage', 'slug' => 'roles.manage', 'name' => 'Manage Roles & Permissions', 'description' => 'Create, edit, duplicate roles and assign permissions.'],

            // System Settings
            ['module' => 'settings', 'action' => 'view', 'slug' => 'settings.view', 'name' => 'View System Settings', 'description' => 'View company profile and configurations.'],
            ['module' => 'settings', 'action' => 'edit', 'slug' => 'settings.edit', 'name' => 'Edit System Settings', 'description' => 'Update organization profile and branch options.'],

            // Audit Trail
            ['module' => 'audit', 'action' => 'view', 'slug' => 'audit.view', 'name' => 'View Audit Logs', 'description' => 'View full system audit trail.'],
            ['module' => 'audit', 'action' => 'export', 'slug' => 'audit.export', 'name' => 'Export Audit Logs', 'description' => 'Export audit logs to CSV.'],
        ];

        $permIdMap = [];
        foreach ($permissions as $p) {
            $existing = Database::selectOne("SELECT id FROM `permissions` WHERE `slug` = :slug LIMIT 1", ['slug' => $p['slug']]);
            if ($existing) {
                $id = (int)$existing['id'];
                Database::update('permissions', [
                    'name' => $p['name'],
                    'module' => $p['module'],
                    'action' => $p['action'],
                    'description' => $p['description'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $id]);
            } else {
                $id = Database::insert('permissions', [
                    'name' => $p['name'],
                    'slug' => $p['slug'],
                    'module' => $p['module'],
                    'action' => $p['action'],
                    'description' => $p['description'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $permIdMap[$p['slug']] = $id;
        }

        // 4. Map Role to Permissions
        // Administrator: ALL permissions
        $adminPerms = array_values($permIdMap);

        // Accountant: exactly what was requested
        $accountantPerms = [
            $permIdMap['dashboard.payments.view'],
            $permIdMap['invoices.view'],
            $permIdMap['invoices.create'],
            $permIdMap['invoices.edit'],
            $permIdMap['invoices.print'],
            $permIdMap['invoices.delete'],
            $permIdMap['quotations.view'],
            $permIdMap['quotations.create'],
            $permIdMap['quotations.edit'],
            $permIdMap['quotations.print'],
            $permIdMap['expenses.view'],
            $permIdMap['expenses.create'],
            $permIdMap['expenses.edit'],
            $permIdMap['expenses.delete'],
            $permIdMap['payments.view'],
            $permIdMap['payments.create'],
            $permIdMap['payments.print'],
            $permIdMap['payments.delete'],
        ];

        // Inventory Manager: exactly what was requested
        $inventoryPerms = [
            $permIdMap['dashboard.inventory.view'],
            $permIdMap['inventory.view'],
            $permIdMap['inventory.stock.add'],
            $permIdMap['inventory.stock.remove'],
            $permIdMap['inventory.adjustment.view'],
            $permIdMap['inventory.adjustment.create'],
            $permIdMap['inventory.adjustment.edit'],
            $permIdMap['inventory.adjustment.delete'],
            $permIdMap['items.view'],
            $permIdMap['items.create'],
            $permIdMap['items.edit'],
            $permIdMap['items.delete'],
            $permIdMap['categories.view'],
            $permIdMap['categories.create'],
            $permIdMap['categories.edit'],
            $permIdMap['categories.delete'],
        ];

        // Sales Executive
        $salesPerms = [
            $permIdMap['dashboard.view'],
            $permIdMap['invoices.view'],
            $permIdMap['invoices.create'],
            $permIdMap['invoices.print'],
            $permIdMap['quotations.view'],
            $permIdMap['quotations.create'],
            $permIdMap['quotations.print'],
            $permIdMap['challans.view'],
        ];

        // Viewer
        $viewerPerms = [
            $permIdMap['dashboard.view'],
        ];

        $rolePermAssignments = [
            $roleIdMap['admin'] => $adminPerms,
            $roleIdMap['accountant'] => $accountantPerms,
            $roleIdMap['inventory_manager'] => $inventoryPerms,
            $roleIdMap['sales_executive'] => $salesPerms,
            $roleIdMap['viewer'] => $viewerPerms,
        ];

        Database::statement("TRUNCATE TABLE `role_permissions`");
        foreach ($rolePermAssignments as $roleId => $permIds) {
            foreach (array_unique($permIds) as $pid) {
                Database::insert('role_permissions', [
                    'role_id' => $roleId,
                    'permission_id' => $pid,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // 5. Update Existing Users to Correct Roles
        // ID 1: Anil Desai -> admin
        Database::update('users', [
            'role_id' => $roleIdMap['admin'],
            'role' => 'ADMIN',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = 1');

        // ID 2: Priya Sharma -> accountant
        Database::update('users', [
            'role_id' => $roleIdMap['accountant'],
            'role' => 'ACCOUNTANT',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = 2');

        // ID 3: Rohan Mehta -> viewer
        Database::update('users', [
            'role_id' => $roleIdMap['viewer'],
            'role' => 'VIEWER',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = 3');

        // ID 4: Suresh Patel -> inventory_manager
        Database::update('users', [
            'role_id' => $roleIdMap['inventory_manager'],
            'role' => 'INVENTORY_MANAGER',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = 4');

        // ID 5: Neha Gupta -> sales_executive
        Database::update('users', [
            'role_id' => $roleIdMap['sales_executive'],
            'role' => 'SALES_EXECUTIVE',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = 5');

        // Sync user_roles junction table as well for backward compatibility
        Database::statement("DELETE FROM `user_roles` WHERE user_id IN (1, 2, 3, 4, 5)");
        Database::insert('user_roles', ['user_id' => 1, 'role_id' => $roleIdMap['admin'], 'created_at' => date('Y-m-d H:i:s')]);
        Database::insert('user_roles', ['user_id' => 2, 'role_id' => $roleIdMap['accountant'], 'created_at' => date('Y-m-d H:i:s')]);
        Database::insert('user_roles', ['user_id' => 3, 'role_id' => $roleIdMap['viewer'], 'created_at' => date('Y-m-d H:i:s')]);
        Database::insert('user_roles', ['user_id' => 4, 'role_id' => $roleIdMap['inventory_manager'], 'created_at' => date('Y-m-d H:i:s')]);
        Database::insert('user_roles', ['user_id' => 5, 'role_id' => $roleIdMap['sales_executive'], 'created_at' => date('Y-m-d H:i:s')]);

        // Ensure user_branches assignments for core staff (Inventory Manager manages both branches)
        $hasBranch1 = Database::selectOne("SELECT id FROM user_branches WHERE user_id = 4 AND branch_id = 1 AND company_id = 1");
        if (!$hasBranch1) {
            Database::insert('user_branches', [
                'company_id' => 1,
                'user_id' => 4,
                'branch_id' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'roles' => count($rolesDef),
            'permissions' => count($permissions),
            'role_permissions' => count(Database::select("SELECT id FROM `role_permissions`")),
            'role_ids' => $roleIdMap,
        ];
    }
}
