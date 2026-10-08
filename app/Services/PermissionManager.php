<?php

namespace App\Services;

use App\Models\User;

class PermissionManager
{
    /**
     * Map of default roles to their permission sets.
     */
    protected static array $rolePermissions = [
        'ADMIN' => [
            '*.*', // Super admin
        ],
        'SUPER_ADMIN' => [
            '*.*',
        ],
        'OWNER' => [
            '*.*',
        ],
        'MANAGER' => [
            'dashboard.view',
            'sales.*', 'invoices.*', 'invoice.*', 'quotations.*', 'quotation.*', 'sales_order.*', 'delivery_challan.*',
            'purchases.*', 'purchase.*', 'purchase_order.*', 'goods_receipt.*', 'purchase_return.*', 'debit_note.*',
            'inventory.*', 'customers.*', 'suppliers.*', 'payments.*', 'expenses.*', 'banking.*', 'cheque.*', 'cheques.*', 'payment_link.*',
            'reports.*', 'reports.view', 'settings.view', 'settings.manage'
        ],
        'ACCOUNTANT' => [
            'dashboard.view',
            'sales.view', 'sales.export', 'sales.print', 'sales.record_payment',
            'invoice.view', 'invoice.print', 'invoice.export',
            'invoices.view', 'invoices.print', 'invoices.export',
            'purchases.*', 'purchase.*', 'purchase.view', 'purchase.create', 'purchase.edit',
            'purchase_order.*', 'goods_receipt.*', 'purchase_return.*', 'debit_note.*',
            'inventory.view',
            'customers.view',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'payments.*',
            'payments.view', 'payments.create', 'payments.record_payment', 'payments.export', 'payments.print', 'payments.refund', 'payments.allocate',
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.approve', 'expenses.export',
            'accounting.*', 'accounting.view', 'accounting.post',
            'gst.*',
            'banking.*',
            'cheque.*',
            'cheques.*',
            'payment_link.*',
            'documents.*',
            'reports.*', 'reports.view', 'reports.export', 'reports.print', 'reports.financial', 'reports.gst', 'reports.inventory', 'reports.sales', 'reports.purchase', 'reports.audit',
            'branch.view', 'warehouse.view',
            'audit.view', 'audit_logs.view',
        ],
        'AUDITOR' => [
            'dashboard.view',
            'sales.view', 'sales.export', 'sales.print',
            'invoice.view', 'invoices.view',
            'purchases.view', 'purchase.view', 'purchase_order.view', 'goods_receipt.view', 'purchase_return.view', 'debit_note.view',
            'inventory.view',
            'customers.view',
            'suppliers.view',
            'payments.view',
            'expenses.view',
            'accounting.view', 'accounting.view_financial_data',
            'gst.view',
            'banking.view',
            'cheque.view',
            'documents.view', 'documents.print',
            'reports.*', 'reports.view', 'reports.export', 'reports.print', 'reports.financial', 'reports.gst', 'reports.inventory', 'reports.sales', 'reports.purchase', 'reports.audit',
            'branch.view', 'warehouse.view',
            'audit.view', 'audit_logs.view'
        ],
        'INVENTORY_MANAGER' => [
            'dashboard.view',
            'purchases.view', 'purchase.view', 'purchase_order.*', 'goods_receipt.*', 'purchase_return.*',
            'inventory.*', 'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.create', 'inventory.edit',
            'warehouse.*',
            'suppliers.view',
            'documents.view', 'documents.print',
            'reports.inventory', 'reports.view',
        ],
        'SALES_EXECUTIVE' => [
            'dashboard.view',
            'sales.*', 'invoice.*', 'invoices.*', 'invoice.view', 'invoice.create', 'invoice.edit',
            'quotations.*', 'quotation.*', 'sales_order.*', 'delivery_challan.*',
            'customers.*',
            'inventory.view',
            'documents.view', 'documents.print',
            'reports.sales', 'reports.view',
        ],
        'CASHIER' => [
            'dashboard.view',
            'sales.view', 'sales.create', 'sales.print', 'sales.record_payment',
            'invoice.view', 'invoice.create', 'invoice.print',
            'customers.view', 'customers.create',
            'payments.view', 'payments.record_payment', 'payments.print',
            'documents.view', 'documents.print',
        ],
        'STAFF' => [
            'dashboard.view',
            'sales.view', 'sales.create',
            'invoice.view', 'invoice.create',
            'purchases.view', 'purchase.view',
            'inventory.view',
            'customers.view',
            'documents.view', 'documents.print',
        ],
    ];

    /**
     * Check if a given user has a permission for a module and action.
     */
    public static function can(User $user, string $module, string $action): bool
    {
        if (!$user->is_active) {
            return false;
        }

        $normalizedRole = strtoupper(str_replace(' ', '_', $user->role ?: 'STAFF'));
        $userPerms = static::$rolePermissions[$normalizedRole] ?? static::$rolePermissions['STAFF'];

        // Super admin wildcard
        if (in_array('*.*', $userPerms, true)) {
            return true;
        }

        $moduleLower = strtolower($module);
        $actionLower = strtolower($action);

        // Check module aliases
        $moduleAliases = [$moduleLower];
        if (in_array($moduleLower, ['invoice', 'invoices', 'sales', 'sale'], true)) {
            $moduleAliases = array_unique(array_merge($moduleAliases, ['invoice', 'invoices', 'sales', 'sale']));
        }
        if (in_array($moduleLower, ['purchase', 'purchases'], true)) {
            $moduleAliases = array_unique(array_merge($moduleAliases, ['purchase', 'purchases']));
        }
        if (in_array($moduleLower, ['payment', 'payments'], true)) {
            $moduleAliases = array_unique(array_merge($moduleAliases, ['payment', 'payments']));
        }
        if (in_array($moduleLower, ['customer', 'customers'], true)) {
            $moduleAliases = array_unique(array_merge($moduleAliases, ['customer', 'customers']));
        }
        if (in_array($moduleLower, ['supplier', 'suppliers'], true)) {
            $moduleAliases = array_unique(array_merge($moduleAliases, ['supplier', 'suppliers']));
        }
        if (in_array($moduleLower, ['setting', 'settings', 'branch_settings', 'company_settings'], true)) {
            $moduleAliases = array_unique(array_merge($moduleAliases, ['setting', 'settings', 'branch_settings', 'company_settings']));
        }
        if (in_array($moduleLower, ['accounting', 'journals', 'journal', 'ledger', 'trial_balance', 'accounts', 'chart_of_accounts'], true)) {
            $moduleAliases = array_unique(array_merge($moduleAliases, ['accounting', 'journals', 'journal', 'ledger', 'trial_balance', 'accounts', 'chart_of_accounts']));
        }
        if (in_array($moduleLower, ['audit', 'audit_log', 'audit_logs'], true)) {
            $moduleAliases = array_unique(array_merge($moduleAliases, ['audit', 'audit_log', 'audit_logs']));
        }
        if (in_array($moduleLower, ['report', 'reports'], true)) {
            $moduleAliases = array_unique(array_merge($moduleAliases, ['report', 'reports']));
        }

        // Check action aliases
        $actionAliases = [$actionLower];
        if ($actionLower === 'adjust_stock' || $actionLower === 'adjust') {
            $actionAliases = ['adjust', 'adjust_stock'];
        }
        if ($actionLower === 'post' || $actionLower === 'create' || $actionLower === 'store') {
            $actionAliases = array_unique(array_merge($actionAliases, ['post', 'create', 'store']));
        }
        if ($actionLower === 'manage' || $actionLower === 'edit' || $actionLower === 'update') {
            $actionAliases = array_unique(array_merge($actionAliases, ['manage', 'edit', 'update']));
        }
        if ($actionLower === 'read' || $actionLower === 'view' || $actionLower === 'list') {
            $actionAliases = array_unique(array_merge($actionAliases, ['view', 'read', 'list']));
        }

        foreach ($moduleAliases as $mod) {
            if (in_array("{$mod}.*", $userPerms, true)) {
                return true;
            }
            foreach ($actionAliases as $act) {
                if (in_array("{$mod}.{$act}", $userPerms, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check single dot-notated permission string (e.g., 'invoice.create', 'accounting.post').
     */
    public static function check(User $user, string $permission): bool
    {
        $parts = explode('.', $permission, 2);
        $module = $parts[0];
        $action = $parts[1] ?? 'view';
        return static::can($user, $module, $action);
    }

    /**
     * Get all permissions assigned to a given role.
     */
    public static function getPermissionsForRole(string $role): array
    {
        $normalizedRole = strtoupper(str_replace(' ', '_', $role));
        return static::$rolePermissions[$normalizedRole] ?? static::$rolePermissions['STAFF'];
    }

    /**
     * List all system roles and their permission catalogs.
     */
    public static function getAllRolesWithPermissions(): array
    {
        return static::$rolePermissions;
    }
}
