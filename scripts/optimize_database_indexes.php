<?php
require_once __DIR__ . '/../views/db_helper.php';
use Illuminate\Database\Capsule\Manager as DB;

$indexesToAdd = [
    // table => [ [columns], index_name ]
    'invoices' => [
        [['company_id', 'status'], 'idx_invoices_comp_status'],
        [['company_id', 'branch_id'], 'idx_invoices_comp_branch'],
        [['company_id', 'customer_id'], 'idx_invoices_comp_cust'],
        [['company_id', 'invoice_date'], 'idx_invoices_comp_date'],
        [['company_id', 'created_at'], 'idx_invoices_comp_created']
    ],
    'purchases' => [
        [['company_id', 'status'], 'idx_purchases_comp_status'],
        [['company_id', 'supplier_id'], 'idx_purchases_comp_supp'],
        [['company_id', 'purchase_date'], 'idx_purchases_comp_date']
    ],
    'quotations' => [
        [['company_id', 'status'], 'idx_quotations_comp_status'],
        [['company_id', 'customer_id'], 'idx_quotations_comp_cust']
    ],
    'recurring_invoices' => [
        [['company_id', 'status'], 'idx_recur_comp_status']
    ],
    'products' => [
        [['company_id', 'category_id'], 'idx_products_comp_cat'],
        [['company_id', 'is_active'], 'idx_products_comp_active'],
        [['company_id', 'name'], 'idx_products_comp_name']
    ],
    'customers' => [
        [['company_id', 'is_active'], 'idx_cust_comp_active'],
        [['company_id', 'name'], 'idx_cust_comp_name']
    ],
    'suppliers' => [
        [['company_id', 'is_active'], 'idx_supp_comp_active'],
        [['company_id', 'name'], 'idx_supp_comp_name']
    ],
    'payments' => [
        [['company_id', 'party_id', 'party_type'], 'idx_paym_comp_party'],
        [['company_id', 'payment_date'], 'idx_paym_comp_date']
    ],
    'expenses' => [
        [['company_id', 'expense_date'], 'idx_exp_comp_date'],
        [['company_id', 'category_id'], 'idx_exp_comp_cat']
    ],
    'journal_entries' => [
        [['company_id', 'status', 'entry_date'], 'idx_je_comp_status_date'],
        [['company_id', 'financial_year'], 'idx_je_comp_fy']
    ],
    'journal_lines' => [
        [['company_id', 'account_id'], 'idx_jl_comp_acc'],
        [['journal_entry_id', 'account_id'], 'idx_jl_entry_acc']
    ],
    'stock_balances' => [
        [['company_id', 'product_id', 'warehouse_id'], 'idx_sb_comp_prod_wh']
    ],
    'stock_movements' => [
        [['company_id', 'product_id'], 'idx_sm_comp_prod'],
        [['company_id', 'movement_date'], 'idx_sm_comp_date']
    ],
    'audit_logs' => [
        [['company_id', 'created_at'], 'idx_audit_comp_created']
    ],
    'user_roles' => [
        [['company_id', 'user_id'], 'idx_ur_comp_user']
    ]
];

echo "Analyzing and applying performance indexes...\n";

foreach ($indexesToAdd as $table => $idxList) {
    // Check if table exists
    try {
        $tableExists = DB::select("SHOW TABLES LIKE '{$table}'");
        if (empty($tableExists)) {
            echo " [SKIP] Table '{$table}' does not exist\n";
            continue;
        }

        $existing = DB::select("SHOW INDEX FROM `{$table}`");
        $existingNames = array_unique(array_column($existing, 'Key_name'));

        foreach ($idxList as $def) {
            $cols = $def[0];
            $idxName = $def[1];

            if (in_array($idxName, $existingNames, true)) {
                echo " [EXISTS] {$table}.{$idxName}\n";
                continue;
            }

            // Verify columns exist in table
            $tableCols = DB::select("SHOW COLUMNS FROM `{$table}`");
            $colNames = array_column($tableCols, 'Field');
            $allColsExist = true;
            foreach ($cols as $c) {
                if (!in_array($c, $colNames, true)) {
                    $allColsExist = false;
                    break;
                }
            }

            if (!$allColsExist) {
                echo " [SKIP] Some columns in (" . implode(',', $cols) . ") missing from {$table}\n";
                continue;
            }

            $colListSql = implode('`, `', $cols);
            $sql = "ALTER TABLE `{$table}` ADD INDEX `{$idxName}` (`{$colListSql}`)";
            DB::statement($sql);
            echo " [ADDED] {$table}.{$idxName} (`" . implode('`, `', $cols) . "`)\n";
        }
    } catch (\Throwable $e) {
        echo " [ERROR] {$table}: " . $e->getMessage() . "\n";
    }
}

echo "Database Index Optimization Complete.\n";
