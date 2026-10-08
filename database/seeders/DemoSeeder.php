<?php

namespace Database\Seeders;

use Illuminate\Database\Capsule\Manager as DB;

class DemoSeeder
{
    /**
     * Seeds isolated DEMO / SAMPLE company data for local test & development environments.
     * MUST NOT be invoked in production.
     */
    public static function run(): void
    {
        // Check if demo company already exists
        $existing = DB::table('companies')->where('id', 1)->first();
        if ($existing) return;

        // Create Demo Company
        $companyId = DB::table('companies')->insertGetId([
            'id' => 1,
            'name' => 'Wis Technosavvy Demo Corp',
            'legal_name' => 'Wis Technosavvy Pvt Ltd',
            'gstin' => '27AAACW1234F1Z5',
            'pan' => 'AAACW1234F',
            'email' => 'admin@wtsbill.in',
            'phone' => '+91 98201 11111',
            'address_line1' => 'Tech Park, Baner',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'pincode' => '411045',
            'currency' => 'INR',
            'financial_year_start' => '04-01',
            'status' => 'ACTIVE',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Create Main Branch
        $branchId = DB::table('branches')->insertGetId([
            'id' => 1,
            'company_id' => $companyId,
            'name' => 'Pune Headquarters',
            'code' => 'PUN01',
            'branch_code' => 'PUN01',
            'is_main_branch' => 1,
            'is_active' => 1,
            'state_code' => '27',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Create Demo Admin User
        DB::table('users')->updateOrInsert(
            ['email' => 'anil.d@wtsbill.in'],
            [
                'id' => 1,
                'name' => 'Anil Desai',
                'password' => password_hash('password123', PASSWORD_BCRYPT),
                'phone' => '+91 98201 11111',
                'role' => 'ADMIN',
                'current_company_id' => $companyId,
                'is_active' => 1,
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );
    }
}
