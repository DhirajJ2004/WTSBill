<?php

namespace Database\Seeders;

use Illuminate\Database\Capsule\Manager as DB;

class SystemSeeder
{
    /**
     * Seeds essential master system data required for ERP operations.
     * Contains NO mock/demo financial transactions or test company data.
     */
    public static function run(): void
    {
        $jsonPath = __DIR__ . '/system_seed_data.json';
        if (!file_exists($jsonPath)) return;

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) return;

        // 1. Indian States Master
        if (!empty($data['indian_states'])) {
            foreach ($data['indian_states'] as $st) {
                DB::table('indian_states')->updateOrInsert(
                    ['code' => $st['code']],
                    [
                        'name' => $st['name'],
                        'state_code' => $st['state_code'] ?? $st['code'],
                        'type' => $st['type'] ?? 'STATE',
                        'is_active' => $st['is_active'] ?? 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                );
            }
        }

        // 2. GST Rates Master
        if (!empty($data['gst_rates'])) {
            foreach ($data['gst_rates'] as $rate) {
                DB::table('gst_rates')->updateOrInsert(
                    ['rate' => $rate['rate']],
                    [
                        'name' => $rate['name'] ?? ($rate['rate'] . '% GST'),
                        'cgst_rate' => $rate['cgst_rate'] ?? ($rate['rate'] / 2),
                        'sgst_rate' => $rate['sgst_rate'] ?? ($rate['rate'] / 2),
                        'igst_rate' => $rate['igst_rate'] ?? $rate['rate'],
                        'cess_rate' => $rate['cess_rate'] ?? 0.00,
                        'is_active' => $rate['is_active'] ?? 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                );
            }
        }

        // 3. Standard Roles
        $standardRoles = [
            ['name' => 'SUPER_ADMIN', 'label' => 'Super Administrator', 'description' => 'Full unconstrained system access'],
            ['name' => 'ADMIN', 'label' => 'Company Administrator', 'description' => 'Full administrative access within tenant'],
            ['name' => 'ACCOUNTANT', 'label' => 'Accountant', 'description' => 'Full accounting, invoicing, journal and financial reporting'],
            ['name' => 'AUDITOR', 'label' => 'Auditor', 'description' => 'Read-only access to financial logs, books and statements'],
            ['name' => 'INVENTORY_MANAGER', 'label' => 'Inventory Manager', 'description' => 'Warehouse, products, batches and stock transfers'],
            ['name' => 'SALES_EXECUTIVE', 'label' => 'Sales Executive', 'description' => 'Customer creation, sales orders, quotations, invoices']
        ];

        foreach ($standardRoles as $r) {
            DB::table('roles')->updateOrInsert(
                ['name' => $r['name']],
                [
                    'label' => $r['label'],
                    'description' => $r['description'],
                    'is_system' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );
        }
    }
}
