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
        'OWNER' => [
            '*.*',
        ],
        'MANAGER' => [
            'dashboard.view',
            'sales.*', 'purchases.*', 'purchase.*', 'purchase_order.*', 'goods_receipt.*', 'purchase_return.*', 'debit_note.*',
            'inventory.*', 'customers.*', 'suppliers.*', 'payments.*', 'expenses.*', 'banking.*', 'cheque.*', 'cheques.*', 'payment_link.*', 'reports.*'
        ],
        'ACCOUNTANT' => [
            'dashboard.view',
            'sales.view', 'sales.export', 'sales.print', 'sales.record_payment',
            'purchases.*', 'purchase.*', 'purchase_order.*', 'goods_receipt.*', 'purchase_return.*', 'debit_note.*',
            'inventory.view',
            'customers.view',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'payments.*',
            'payments.view', 'payments.create', 'payments.record_payment', 'payments.export', 'payments.print', 'payments.refund', 'payments.allocate',
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.approve', 'expenses.export',
            'accounting.*',
            'gst.*',
            'banking.*',
            'cheque.*',
            'cheques.*',
            'payment_link.*',
            'payment_links.*',
            'gateway.*',
            'documents.*',
            'document.*',
            'document_template.*',
            'document_numbering.*',
            'reports.*',
            'reports.view', 'reports.export', 'reports.print', 'reports.financial', 'reports.gst', 'reports.inventory', 'reports.sales', 'reports.purchase', 'reports.audit', 'reports.view_financial_data', 'reports.consolidated',
            'branch.*', 'warehouse.*', 'pricelists.*', 'sales.override_price',
            'notifications.*', 'reminders.*', 'recurring.*', 'notification_templates.*',
            'imports.*', 'exports.*', 'backup.create', 'backup.view', 'audit.view',
        ],
        'AUDITOR' => [
            'dashboard.view',
            'sales.view', 'sales.export', 'sales.print',
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
            'cheques.view',
            'payment_link.view',
            'documents.view', 'documents.print', 'documents.pdf',
            'document.view', 'document.print', 'document.pdf',
            'reports.view', 'reports.export', 'reports.print', 'reports.financial', 'reports.gst', 'reports.inventory', 'reports.sales', 'reports.purchase', 'reports.audit', 'reports.view_financial_data', 'reports.consolidated',
            'branch.view', 'warehouse.view', 'pricelists.view',
            'notifications.view', 'reminders.view', 'recurring.view',
            'audit.view'
        ],
        'INVENTORY_MANAGER' => [
            'dashboard.view',
            'purchases.view', 'purchase.view', 'purchase_order.*', 'goods_receipt.*', 'purchase_return.*',
            'inventory.*',
            'warehouse.*',
            'suppliers.view',
            'notifications.view',
            'documents.view', 'documents.print', 'documents.pdf',
        ],
        'SALES_EXECUTIVE' => [
            'dashboard.view',
            'sales.*',
            'customers.*',
            'inventory.view',
            'pricelists.view',
            'reminders.view', 'reminders.send',
            'notifications.view',
            'documents.view', 'documents.print', 'documents.pdf', 'documents.share',
        ],
        'CASHIER' => [
            'dashboard.view',
            'sales.view', 'sales.create', 'sales.print', 'sales.record_payment',
            'customers.view', 'customers.create',
            'payments.view', 'payments.record_payment', 'payments.print',
            'documents.view', 'documents.print',
        ],
        'STAFF' => [
            'dashboard.view',
            'sales.view', 'sales.create',
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

        // Wildcard admin
        if (in_array('*.*', $userPerms, true)) {
            return true;
        }

        $moduleLower = strtolower($module);
        $actionLower = strtolower($action);

        $permissionKey = "{$moduleLower}.{$actionLower}";
        $wildcardModule = "{$moduleLower}.*";

        // Check module aliases
        $aliases = [];
        if ($moduleLower === 'purchases') $aliases[] = 'purchase';
        if ($moduleLower === 'purchase') $aliases[] = 'purchases';
        if ($moduleLower === 'payments') $aliases[] = 'payment';
        if ($moduleLower === 'payment') $aliases[] = 'payments';
        if ($moduleLower === 'receivables') $aliases[] = 'receivable';
        if ($moduleLower === 'receivable') $aliases[] = 'receivables';
        if ($moduleLower === 'payables') $aliases[] = 'payable';
        if ($moduleLower === 'payable') $aliases[] = 'payables';
        if ($moduleLower === 'payment_reminders') $aliases[] = 'payment_reminder';
        if ($moduleLower === 'refunds') $aliases[] = 'refund';
        if ($moduleLower === 'journals' || $moduleLower === 'journal' || $moduleLower === 'ledger' || $moduleLower === 'trial_balance' || $moduleLower === 'accounts' || $moduleLower === 'chart_of_accounts') {
            $aliases[] = 'accounting';
        }
        if ($moduleLower === 'accounting') {
            $aliases[] = 'journals';
            $aliases[] = 'journal';
            $aliases[] = 'ledger';
            $aliases[] = 'trial_balance';
            $aliases[] = 'accounts';
            $aliases[] = 'chart_of_accounts';
        }
        if ($moduleLower === 'gst' || $moduleLower === 'tax' || $moduleLower === 'taxes' || $moduleLower === 'einvoice' || $moduleLower === 'e_invoice' || $moduleLower === 'ewaybill' || $moduleLower === 'e_way_bill' || $moduleLower === 'gstr1' || $moduleLower === 'gstr3b') {
            $aliases[] = 'gst';
            $aliases[] = 'tax';
        }

        $actionAliases = [$actionLower];
        if ($actionLower === 'adjust_stock') $actionAliases[] = 'adjust';
        if ($actionLower === 'adjust') $actionAliases[] = 'adjust_stock';
        if ($actionLower === 'create') $actionAliases[] = 'record_payment';
        if ($actionLower === 'record_payment') $actionAliases[] = 'create';

        foreach ($actionAliases as $act) {
            $perm = "{$moduleLower}.{$act}";
            if (in_array($perm, $userPerms, true) || in_array($wildcardModule, $userPerms, true)) {
                return true;
            }

            foreach ($aliases as $alias) {
                if (in_array("{$alias}.{$act}", $userPerms, true) || in_array("{$alias}.*", $userPerms, true)) {
                    return true;
                }
            }
        }

        return false;
    }

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
}
