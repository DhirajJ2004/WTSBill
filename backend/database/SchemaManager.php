<?php

namespace App\Database;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

class SchemaManager
{
    public static function migrate(): void
    {
        Database::init();
        $schema = DB::schema();

        // ----------------------------------------------------
        // 1. COMPANY / TENANT & ACCESS CONTROL
        // ----------------------------------------------------
        if (!$schema->hasTable('companies')) {
            $schema->create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('legal_name')->nullable();
                $table->string('gstin', 15)->nullable()->index();
                $table->string('pan', 10)->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address_line1')->nullable();
                $table->text('address_line2')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('state_code', 2)->default('27');
                $table->string('pincode', 10)->nullable();
                $table->string('currency', 3)->default('INR');
                $table->string('financial_year_start')->default('04-01');
                $table->string('logo_url')->nullable();
                $table->string('business_type')->nullable();
                $table->string('status')->default('ACTIVE');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!$schema->hasTable('branches')) {
            $schema->create('branches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name');
                $table->string('code', 30)->nullable();
                $table->string('branch_code', 30)->nullable();
                $table->string('legal_name')->nullable();
                $table->string('gstin', 15)->nullable();
                $table->string('pan', 10)->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('state_code', 2)->nullable();
                $table->string('pincode', 10)->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_main_branch')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            $branchCols = ['code', 'branch_code', 'legal_name', 'gstin', 'pan', 'address', 'city', 'state', 'state_code', 'pincode', 'phone', 'email', 'is_main_branch', 'is_active'];
            foreach ($branchCols as $col) {
                if (!$schema->hasColumn('branches', $col)) {
                    $schema->table('branches', function (Blueprint $table) use ($col) {
                        if ($col === 'is_active') {
                            $table->boolean('is_active')->default(true);
                        } elseif ($col === 'is_main_branch') {
                            $table->boolean('is_main_branch')->default(false);
                        } elseif ($col === 'address') {
                            $table->text('address')->nullable();
                        } else {
                            $table->string($col)->nullable();
                        }
                    });
                }
            }
        }

        if (!$schema->hasTable('users')) {
            $schema->create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('phone')->nullable();
                $table->string('role')->default('Admin');
                $table->unsignedBigInteger('current_company_id')->nullable()->index();
                $table->boolean('is_active')->default(true);
                $table->string('status')->default('ACTIVE');
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!$schema->hasTable('roles')) {
            $schema->create('roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name'); // Admin, Accountant, Sales Manager, Inventory Manager, Auditor
                $table->string('slug');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'slug']);
            });
        }

        if (!$schema->hasTable('permissions')) {
            $schema->create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('module'); // Sales, Purchases, Inventory, Accounting, Reports
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('user_roles')) {
            $schema->create('user_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('role_id')->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('role_permissions')) {
            $schema->create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id')->index();
                $table->unsignedBigInteger('permission_id')->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('personal_access_tokens')) {
            $schema->create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('token', 64)->unique();
                $table->string('name')->default('default');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('password_resets')) {
            $schema->create('password_resets', function (Blueprint $table) {
                $table->string('email')->index();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!$schema->hasTable('branches')) {
            $schema->create('branches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name');
                $table->string('branch_code')->nullable();
                $table->string('gstin', 15)->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('state_code', 2)->default('27');
                $table->boolean('is_main_branch')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!$schema->hasTable('user_branches')) {
            $schema->create('user_branches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('branch_id')->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('warehouses')) {
            $schema->create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('city')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ----------------------------------------------------
        // 2. CUSTOMERS
        // ----------------------------------------------------
        if (!$schema->hasTable('customer_groups')) {
            $schema->create('customer_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name');
                $table->decimal('discount_percentage', 5, 2)->default(0.00);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('customers')) {
            $schema->create('customers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_group_id')->nullable()->index();
                $table->string('name');
                $table->string('company_name')->nullable();
                $table->string('gstin', 15)->nullable()->index();
                $table->string('pan', 10)->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address_line1')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('state_code', 2)->default('27');
                $table->string('pincode', 10)->nullable();
                $table->decimal('opening_balance', 15, 2)->default(0.00);
                $table->decimal('current_balance', 15, 2)->default(0.00);
                $table->decimal('credit_limit', 15, 2)->default(0.00);
                $table->integer('payment_terms_days')->default(30);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!$schema->hasTable('customer_addresses')) {
            $schema->create('customer_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('type')->default('BILLING'); // BILLING, SHIPPING
                $table->text('address_line1');
                $table->string('city');
                $table->string('state');
                $table->string('state_code', 2)->default('27');
                $table->string('pincode', 10);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('customer_contacts')) {
            $schema->create('customer_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('name');
                $table->string('designation')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('customer_tags')) {
            $schema->create('customer_tags', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('tag_name');
                $table->timestamps();
            });
        }

        // ----------------------------------------------------
        // 3. SUPPLIERS
        // ----------------------------------------------------
        if (!$schema->hasTable('supplier_groups')) {
            $schema->create('supplier_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('suppliers')) {
            $schema->create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('supplier_group_id')->nullable()->index();
                $table->string('name');
                $table->string('company_name')->nullable();
                $table->string('gstin', 15)->nullable()->index();
                $table->string('pan', 10)->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address_line1')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('state_code', 2)->default('27');
                $table->string('pincode', 10)->nullable();
                $table->decimal('opening_balance', 15, 2)->default(0.00);
                $table->decimal('current_balance', 15, 2)->default(0.00);
                $table->integer('payment_terms_days')->default(30);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!$schema->hasTable('supplier_addresses')) {
            $schema->create('supplier_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->string('type')->default('OFFICE');
                $table->text('address_line1');
                $table->string('city');
                $table->string('state');
                $table->string('state_code', 2)->default('27');
                $table->string('pincode', 10);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('supplier_contacts')) {
            $schema->create('supplier_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('supplier_bank_accounts')) {
            $schema->create('supplier_bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->string('bank_name');
                $table->string('account_number');
                $table->string('ifsc_code', 11);
                $table->string('branch_name')->nullable();
                $table->timestamps();
            });
        }

        // ----------------------------------------------------
        // 4. PRODUCTS & CATALOG
        // ----------------------------------------------------
        if (!$schema->hasTable('categories')) {
            $schema->create('categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('subcategories')) {
            $schema->create('subcategories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('category_id')->index();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('units')) {
            $schema->create('units', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name'); // Pieces, Kilograms, Boxes, Meters, Liters
                $table->string('short_name');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('products')) {
            $schema->create('products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->unsignedBigInteger('subcategory_id')->nullable()->index();
                $table->string('name');
                $table->string('sku')->index();
                $table->string('hsn_sac', 10)->nullable()->index();
                $table->string('unit')->default('Pcs');
                $table->decimal('sales_price', 15, 2)->default(0.00);
                $table->decimal('purchase_price', 15, 2)->default(0.00);
                $table->decimal('tax_rate', 5, 2)->default(18.00);
                $table->decimal('current_stock', 15, 3)->default(0.000);
                $table->decimal('min_stock_alert', 15, 3)->default(10.000);
                $table->string('barcode')->nullable()->index();
                $table->text('description')->nullable();
                $table->boolean('has_batch')->default(false);
                $table->boolean('has_serial')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'sku']);
            });
        }

        if (!$schema->hasTable('product_units')) {
            $schema->create('product_units', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('unit_name');
                $table->decimal('conversion_factor', 15, 4)->default(1.0000);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('product_prices')) {
            $schema->create('product_prices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('price_type')->default('RETAIL'); // RETAIL, WHOLESALE, DISTRIBUTOR
                $table->decimal('price', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('product_barcodes')) {
            $schema->create('product_barcodes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('barcode')->index();
                $table->timestamps();
                $table->unique(['company_id', 'barcode']);
            });
        }

        // ----------------------------------------------------
        // 5. INVENTORY & STOCK BALANCES
        // ----------------------------------------------------
        if (!$schema->hasTable('stock_balances')) {
            $schema->create('stock_balances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity', 15, 3)->default(0.000);
                $table->decimal('reserved_quantity', 15, 3)->default(0.000);
                $table->timestamps();
                $table->unique(['company_id', 'warehouse_id', 'product_id']);
            });
        }

        if (!$schema->hasTable('stock_movements')) {
            $schema->create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('type'); // SALE, PURCHASE, ADJUSTMENT_ADD, ADJUSTMENT_SUB, TRANSFER
                $table->decimal('quantity', 15, 3);
                $table->decimal('balance_after', 15, 3);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('stock_adjustments')) {
            $schema->create('stock_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->string('adjustment_number')->index();
                $table->date('adjustment_date');
                $table->string('reason');
                $table->text('notes')->nullable();
                $table->string('created_by')->default('Admin');
                $table->timestamps();
            });
        } elseif (!$schema->hasColumn('stock_adjustments', 'created_by')) {
            $schema->table('stock_adjustments', function (Blueprint $table) {
                $table->string('created_by')->default('Admin');
            });
        }

        if (!$schema->hasTable('stock_adjustment_items')) {
            $schema->create('stock_adjustment_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('adjustment_id')->index();
                $table->unsignedBigInteger('stock_adjustment_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('type')->default('INCREASE'); // INCREASE, DECREASE
                $table->string('adjustment_type')->nullable();
                $table->decimal('quantity', 15, 3);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->timestamps();
            });
        } elseif (!$schema->hasColumn('stock_adjustment_items', 'adjustment_id')) {
            $schema->table('stock_adjustment_items', function (Blueprint $table) {
                $table->unsignedBigInteger('adjustment_id')->nullable()->index();
                $table->string('type')->default('INCREASE');
                $table->decimal('unit_cost', 15, 2)->default(0);
            });
        }

        if (!$schema->hasTable('stock_transfers')) {
            $schema->create('stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('transfer_number')->index();
                $table->unsignedBigInteger('from_warehouse_id')->index();
                $table->unsignedBigInteger('to_warehouse_id')->index();
                $table->date('transfer_date');
                $table->string('status')->default('IN_TRANSIT');
                $table->string('reference_no')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } elseif (!$schema->hasColumn('stock_transfers', 'reference_no')) {
            $schema->table('stock_transfers', function (Blueprint $table) {
                $table->string('reference_no')->nullable();
                $table->text('notes')->nullable();
            });
        }

        if (!$schema->hasTable('stock_transfer_items')) {
            $schema->create('stock_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('transfer_id')->index();
                $table->unsignedBigInteger('stock_transfer_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity', 15, 3);
                $table->timestamps();
            });
        } elseif (!$schema->hasColumn('stock_transfer_items', 'transfer_id')) {
            $schema->table('stock_transfer_items', function (Blueprint $table) {
                $table->unsignedBigInteger('transfer_id')->nullable()->index();
            });
        }

        if (!$schema->hasTable('batches')) {
            $schema->create('batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->string('batch_number');
                $table->date('mfg_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->decimal('quantity', 15, 3)->default(0.000);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('serial_numbers')) {
            $schema->create('serial_numbers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('serial_number')->index();
                $table->string('status')->default('IN_STOCK'); // IN_STOCK, SOLD, RETURNED
                $table->timestamps();
                $table->unique(['company_id', 'product_id', 'serial_number']);
            });
        } elseif (!$schema->hasColumn('serial_numbers', 'warehouse_id')) {
            $schema->table('serial_numbers', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            });
        }

        // ----------------------------------------------------
        // 6. SALES & BILLING
        // ----------------------------------------------------
        if (!$schema->hasTable('quotations')) {
            $schema->create('quotations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('quotation_number')->index();
                $table->date('quotation_date');
                $table->date('valid_until')->nullable();
                $table->decimal('sub_total', 15, 2);
                $table->decimal('total_tax', 15, 2);
                $table->decimal('grand_total', 15, 2);
                $table->string('status')->default('DRAFT');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('quotation_items')) {
            $schema->create('quotation_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('quotation_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity', 15, 3);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('total_amount', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('sales_orders')) {
            $schema->create('sales_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('order_number')->index();
                $table->date('order_date');
                $table->decimal('grand_total', 15, 2);
                $table->string('status')->default('CONFIRMED');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('sales_order_items')) {
            $schema->create('sales_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('sales_order_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity', 15, 3);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('total_amount', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('invoices')) {
            $schema->create('invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('invoice_number')->index();
                $table->string('reference_po_number')->nullable();
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->string('place_of_supply', 2)->default('27');
                $table->boolean('is_igst')->default(false);
                $table->decimal('sub_total', 15, 2)->default(0.00);
                $table->decimal('discount_rate', 5, 2)->default(0.00);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->decimal('cgst_amount', 15, 2)->default(0.00);
                $table->decimal('sgst_amount', 15, 2)->default(0.00);
                $table->decimal('igst_amount', 15, 2)->default(0.00);
                $table->decimal('total_tax', 15, 2)->default(0.00);
                $table->decimal('round_off', 5, 2)->default(0.00);
                $table->decimal('grand_total', 15, 2)->default(0.00);
                $table->decimal('amount_paid', 15, 2)->default(0.00);
                $table->decimal('amount_due', 15, 2)->default(0.00);
                $table->string('status')->default('PAID');
                $table->string('payment_mode')->default('Bank Transfer');
                $table->text('notes')->nullable();
                $table->text('terms_and_conditions')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!$schema->hasTable('invoice_items')) {
            $schema->create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('item_name');
                $table->string('hsn_sac', 10)->nullable();
                $table->decimal('quantity', 15, 3);
                $table->string('unit')->default('Pcs');
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount_rate', 5, 2)->default(0.00);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->decimal('taxable_value', 15, 2);
                $table->decimal('gst_rate', 5, 2)->default(18.00);
                $table->decimal('cgst_rate', 5, 2)->default(9.00);
                $table->decimal('cgst_amount', 15, 2)->default(0.00);
                $table->decimal('sgst_rate', 5, 2)->default(9.00);
                $table->decimal('sgst_amount', 15, 2)->default(0.00);
                $table->decimal('igst_rate', 5, 2)->default(0.00);
                $table->decimal('igst_amount', 15, 2)->default(0.00);
                $table->decimal('total_amount', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('delivery_challans')) {
            $schema->create('delivery_challans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('challan_number')->index();
                $table->date('challan_date');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('delivery_challan_items')) {
            $schema->create('delivery_challan_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('delivery_challan_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity', 15, 3);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('credit_notes')) {
            $schema->create('credit_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                $table->string('credit_note_number')->index();
                $table->date('credit_note_date');
                $table->decimal('amount', 15, 2);
                $table->text('reason')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('credit_note_items')) {
            $schema->create('credit_note_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('credit_note_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity', 15, 3);
                $table->decimal('amount', 15, 2);
                $table->timestamps();
            });
        }

        // ----------------------------------------------------
        // 7. PURCHASES & VENDOR BILLS
        // ----------------------------------------------------
        if (!$schema->hasTable('purchase_orders')) {
            $schema->create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->string('po_number')->index();
                $table->date('po_date');
                $table->decimal('grand_total', 15, 2);
                $table->string('status')->default('ISSUED');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('purchase_order_items')) {
            $schema->create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity', 15, 3);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('total_amount', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('purchases')) {
            $schema->create('purchases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->string('purchase_number')->index();
                $table->string('vendor_invoice_number')->nullable();
                $table->date('purchase_date');
                $table->date('due_date')->nullable();
                $table->decimal('sub_total', 15, 2)->default(0.00);
                $table->decimal('cgst_amount', 15, 2)->default(0.00);
                $table->decimal('sgst_amount', 15, 2)->default(0.00);
                $table->decimal('igst_amount', 15, 2)->default(0.00);
                $table->decimal('total_tax', 15, 2)->default(0.00);
                $table->decimal('round_off', 5, 2)->default(0.00);
                $table->decimal('grand_total', 15, 2)->default(0.00);
                $table->decimal('amount_paid', 15, 2)->default(0.00);
                $table->decimal('amount_due', 15, 2)->default(0.00);
                $table->string('status')->default('PAID');
                $table->text('notes')->nullable();
                $table->text('internal_remarks')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } elseif (!$schema->hasColumn('purchases', 'branch_id')) {
            $schema->table('purchases', function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->index();
            });
        }

        if (!$schema->hasTable('purchase_items')) {
            $schema->create('purchase_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('purchase_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('item_name');
                $table->string('hsn_sac', 10)->nullable();
                $table->decimal('quantity', 15, 3);
                $table->string('unit')->default('Pcs');
                $table->decimal('unit_price', 15, 2);
                $table->decimal('taxable_value', 15, 2);
                $table->decimal('gst_rate', 5, 2)->default(18.00);
                $table->decimal('tax_amount', 15, 2)->default(0.00);
                $table->decimal('total_amount', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('purchase_returns')) {
            $schema->create('purchase_returns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->string('return_number')->index();
                $table->date('return_date');
                $table->decimal('grand_total', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('purchase_return_items')) {
            $schema->create('purchase_return_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('purchase_return_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity', 15, 3);
                $table->decimal('amount', 15, 2);
                $table->timestamps();
            });
        }

        // ----------------------------------------------------
        // 8. PAYMENTS & EXPENSES
        // ----------------------------------------------------
        if (!$schema->hasTable('payments')) {
            $schema->create('payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('payment_number')->index();
                $table->string('party_type'); // CUSTOMER, SUPPLIER
                $table->unsignedBigInteger('party_id')->index();
                $table->date('payment_date');
                $table->decimal('amount', 15, 2);
                $table->string('payment_mode')->default('NEFT/RTGS');
                $table->string('reference_no')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('payment_allocations')) {
            $schema->create('payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('payment_id')->index();
                $table->string('document_type'); // INVOICE, PURCHASE
                $table->unsignedBigInteger('document_id')->index();
                $table->decimal('allocated_amount', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('expense_categories')) {
            $schema->create('expense_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('expenses')) {
            $schema->create('expenses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('expense_number')->index();
                $table->string('category');
                $table->string('payee')->nullable();
                $table->date('expense_date');
                $table->decimal('amount', 15, 2);
                $table->decimal('tax_amount', 15, 2)->default(0.00);
                $table->string('payment_mode')->default('Bank Transfer');
                $table->string('gstin', 15)->nullable();
                $table->boolean('is_itc_eligible')->default(true);
                $table->string('reference_no')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        } elseif (!$schema->hasColumn('expenses', 'branch_id')) {
            $schema->table('expenses', function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->index();
            });
        }

        if (!$schema->hasTable('recurring_expenses')) {
            $schema->create('recurring_expenses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('category');
                $table->decimal('amount', 15, 2);
                $table->string('frequency')->default('MONTHLY');
                $table->date('next_date');
                $table->timestamps();
            });
        }

        // ----------------------------------------------------
        // 9. DOUBLE ENTRY ACCOUNTING & GST
        // ----------------------------------------------------
        if (!$schema->hasTable('chart_of_accounts')) {
            $schema->create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('account_code')->index();
                $table->string('account_name');
                $table->string('account_type'); // ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE
                $table->decimal('current_balance', 15, 2)->default(0.00);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('journal_entries')) {
            $schema->create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('entry_number')->index();
                $table->date('entry_date');
                $table->text('narration');
                $table->decimal('total_debit', 15, 2);
                $table->decimal('total_credit', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('journal_entry_lines')) {
            $schema->create('journal_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('journal_entry_id')->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->decimal('debit_amount', 15, 2)->default(0.00);
                $table->decimal('credit_amount', 15, 2)->default(0.00);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('ledger_entries')) {
            $schema->create('ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->date('entry_date');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->decimal('debit', 15, 2)->default(0.00);
                $table->decimal('credit', 15, 2)->default(0.00);
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('accounting_periods')) {
            $schema->create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('period_name'); // FY 2026-27 Q1
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_closed')->default(false);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('tax_rates')) {
            $schema->create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name');
                $table->decimal('rate', 5, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('gst_configurations')) {
            $schema->create('gst_configurations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('gstin', 15);
                $table->string('composition_scheme')->default('NO');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('gst_transactions')) {
            $schema->create('gst_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('type'); // OUTWARD_B2B, OUTWARD_B2C, INWARD_ITC
                $table->string('reference_number');
                $table->date('transaction_date');
                $table->decimal('taxable_value', 15, 2);
                $table->decimal('cgst', 15, 2)->default(0.00);
                $table->decimal('sgst', 15, 2)->default(0.00);
                $table->decimal('igst', 15, 2)->default(0.00);
                $table->timestamps();
            });
        }

        // ----------------------------------------------------
        // 10. BANKING & SYSTEM
        // ----------------------------------------------------
        if (!$schema->hasTable('bank_accounts')) {
            $schema->create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('bank_name');
                $table->string('account_name');
                $table->string('account_number');
                $table->string('ifsc_code', 11);
                $table->string('branch_name')->nullable();
                $table->decimal('current_balance', 15, 2)->default(0.00);
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('bank_transactions')) {
            $schema->create('bank_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->date('transaction_date');
                $table->string('type'); // CREDIT, DEBIT
                $table->decimal('amount', 15, 2);
                $table->decimal('balance_after', 15, 2);
                $table->string('reference_no')->nullable();
                $table->string('description');
                $table->boolean('is_reconciled')->default(true);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('bank_reconciliations')) {
            $schema->create('bank_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->date('statement_date');
                $table->decimal('statement_balance', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('cheques')) {
            $schema->create('cheques', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('cheque_number');
                $table->date('cheque_date');
                $table->decimal('amount', 15, 2);
                $table->string('payee');
                $table->string('status')->default('CLEARED');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('notifications')) {
            $schema->create('notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('title');
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('attachments')) {
            $schema->create('attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('attachable_type');
                $table->unsignedBigInteger('attachable_id');
                $table->string('file_name');
                $table->string('file_path');
                $table->string('mime_type')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('audit_logs')) {
            $schema->create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name')->nullable();
                $table->string('action');
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->text('description');
                $table->string('ip_address')->nullable();
                $table->timestamps();
            });
        }

        // Alter tables checks
        if ($schema->hasTable('companies')) {
            if (!$schema->hasColumn('companies', 'business_type')) {
                $schema->table('companies', function (Blueprint $table) {
                    $table->string('business_type')->nullable();
                });
            }
            if (!$schema->hasColumn('companies', 'status')) {
                $schema->table('companies', function (Blueprint $table) {
                    $table->string('status')->default('ACTIVE');
                });
            }
        }

        if ($schema->hasTable('users')) {
            if (!$schema->hasColumn('users', 'status')) {
                $schema->table('users', function (Blueprint $table) {
                    $table->string('status')->default('ACTIVE');
                });
            }
        }

        // ============================================================
        // CHUNK 2: MASTER DATA FOUNDATION — NEW TABLES
        // ============================================================

        if (!$schema->hasTable('tax_rates')) {
            $schema->create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name'); // GST 18%, GST 5%, Cess 1%
                $table->decimal('rate', 5, 2);
                $table->string('tax_type')->default('GST'); // GST, CESS
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('status')->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('chart_of_accounts')) {
            $schema->create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('account_name');
                $table->string('account_code')->nullable()->index();
                $table->string('account_type'); // ASSET, LIABILITY, EQUITY, INCOME, EXPENSE
                $table->unsignedBigInteger('parent_account_id')->nullable()->index();
                $table->text('description')->nullable();
                $table->boolean('is_system_account')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['company_id', 'account_code']);
            });
        }

        if (!$schema->hasTable('tags')) {
            $schema->create('tags', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name');
                $table->string('color')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'name']);
            });
        }

        if (!$schema->hasTable('taggables')) {
            $schema->create('taggables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tag_id')->index();
                $table->string('taggable_type'); // Customer, Supplier, Product
                $table->unsignedBigInteger('taggable_id')->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('indian_states')) {
            $schema->create('indian_states', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 2)->unique();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('unit_conversions')) {
            $schema->create('unit_conversions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('from_unit_id')->index();
                $table->unsignedBigInteger('to_unit_id')->index();
                $table->decimal('conversion_factor', 15, 4);
                $table->timestamps();
            });
        }

        // ============================================================
        // CHUNK 2: MASTER DATA FOUNDATION — ALTER EXISTING TABLES
        // ============================================================

        // --- Customers ---
        if ($schema->hasTable('customers')) {
            $addCols = [
                'customer_type' => "string('customer_type')->default('Business')",
                'alt_phone' => "string('alt_phone')->nullable()",
                'tax_type' => "string('tax_type')->default('Unregistered')",
                'place_of_supply' => "string('place_of_supply')->nullable()",
                'status' => "string('status')->default('ACTIVE')",
                'notes' => "text('notes')->nullable()",
                'credit_period' => "integer('credit_period')->default(30)",
                'opening_balance_type' => "string('opening_balance_type')->default('Debit')",
                'default_price_list' => "string('default_price_list')->nullable()",
                'created_by' => "unsignedBigInteger('created_by')->nullable()",
                'updated_by' => "unsignedBigInteger('updated_by')->nullable()",
            ];
            foreach ($addCols as $col => $def) {
                if (!$schema->hasColumn('customers', $col)) {
                    $schema->table('customers', function (Blueprint $table) use ($col) {
                        match ($col) {
                            'customer_type' => $table->string('customer_type')->default('Business'),
                            'alt_phone' => $table->string('alt_phone')->nullable(),
                            'tax_type' => $table->string('tax_type')->default('Unregistered'),
                            'place_of_supply' => $table->string('place_of_supply')->nullable(),
                            'status' => $table->string('status')->default('ACTIVE'),
                            'notes' => $table->text('notes')->nullable(),
                            'credit_period' => $table->integer('credit_period')->default(30),
                            'opening_balance_type' => $table->string('opening_balance_type')->default('Debit'),
                            'default_price_list' => $table->string('default_price_list')->nullable(),
                            'created_by' => $table->unsignedBigInteger('created_by')->nullable(),
                            'updated_by' => $table->unsignedBigInteger('updated_by')->nullable(),
                        };
                    });
                }
            }
        }

        // --- Customer Groups ---
        if ($schema->hasTable('customer_groups')) {
            if (!$schema->hasColumn('customer_groups', 'description')) {
                $schema->table('customer_groups', function (Blueprint $table) {
                    $table->text('description')->nullable();
                });
            }
            if (!$schema->hasColumn('customer_groups', 'status')) {
                $schema->table('customer_groups', function (Blueprint $table) {
                    $table->string('status')->default('ACTIVE');
                });
            }
        }

        // --- Customer Addresses ---
        if ($schema->hasTable('customer_addresses')) {
            if (!$schema->hasColumn('customer_addresses', 'address_line2')) {
                $schema->table('customer_addresses', function (Blueprint $table) {
                    $table->text('address_line2')->nullable();
                });
            }
            if (!$schema->hasColumn('customer_addresses', 'country')) {
                $schema->table('customer_addresses', function (Blueprint $table) {
                    $table->string('country')->default('India');
                });
            }
            if (!$schema->hasColumn('customer_addresses', 'landmark')) {
                $schema->table('customer_addresses', function (Blueprint $table) {
                    $table->string('landmark')->nullable();
                });
            }
            if (!$schema->hasColumn('customer_addresses', 'is_default')) {
                $schema->table('customer_addresses', function (Blueprint $table) {
                    $table->boolean('is_default')->default(false);
                });
            }
        }

        // --- Customer Contacts ---
        if ($schema->hasTable('customer_contacts')) {
            if (!$schema->hasColumn('customer_contacts', 'is_primary')) {
                $schema->table('customer_contacts', function (Blueprint $table) {
                    $table->boolean('is_primary')->default(false);
                });
            }
        }

        // --- Suppliers ---
        if ($schema->hasTable('suppliers')) {
            $supplierCols = ['alt_phone', 'tax_type', 'place_of_supply', 'contact_person', 'notes', 'status', 'credit_period', 'default_payment_mode', 'created_by', 'updated_by'];
            foreach ($supplierCols as $col) {
                if (!$schema->hasColumn('suppliers', $col)) {
                    $schema->table('suppliers', function (Blueprint $table) use ($col) {
                        match ($col) {
                            'alt_phone' => $table->string('alt_phone')->nullable(),
                            'tax_type' => $table->string('tax_type')->default('Unregistered'),
                            'place_of_supply' => $table->string('place_of_supply')->nullable(),
                            'contact_person' => $table->string('contact_person')->nullable(),
                            'notes' => $table->text('notes')->nullable(),
                            'status' => $table->string('status')->default('ACTIVE'),
                            'credit_period' => $table->integer('credit_period')->default(30),
                            'default_payment_mode' => $table->string('default_payment_mode')->nullable(),
                            'created_by' => $table->unsignedBigInteger('created_by')->nullable(),
                            'updated_by' => $table->unsignedBigInteger('updated_by')->nullable(),
                        };
                    });
                }
            }
        }

        // --- Supplier Groups ---
        if ($schema->hasTable('supplier_groups')) {
            if (!$schema->hasColumn('supplier_groups', 'description')) {
                $schema->table('supplier_groups', function (Blueprint $table) {
                    $table->text('description')->nullable();
                });
            }
            if (!$schema->hasColumn('supplier_groups', 'status')) {
                $schema->table('supplier_groups', function (Blueprint $table) {
                    $table->string('status')->default('ACTIVE');
                });
            }
        }

        // --- Supplier Addresses ---
        if ($schema->hasTable('supplier_addresses')) {
            if (!$schema->hasColumn('supplier_addresses', 'address_line2')) {
                $schema->table('supplier_addresses', function (Blueprint $table) {
                    $table->text('address_line2')->nullable();
                });
            }
        }

        // --- Supplier Bank Accounts ---
        if ($schema->hasTable('supplier_bank_accounts')) {
            if (!$schema->hasColumn('supplier_bank_accounts', 'account_name')) {
                $schema->table('supplier_bank_accounts', function (Blueprint $table) {
                    $table->string('account_name')->nullable();
                });
            }
        }

        // --- Categories ---
        if ($schema->hasTable('categories')) {
            if (!$schema->hasColumn('categories', 'parent_category_id')) {
                $schema->table('categories', function (Blueprint $table) {
                    $table->unsignedBigInteger('parent_category_id')->nullable()->index();
                });
            }
            if (!$schema->hasColumn('categories', 'description')) {
                $schema->table('categories', function (Blueprint $table) {
                    $table->text('description')->nullable();
                });
            }
            if (!$schema->hasColumn('categories', 'status')) {
                $schema->table('categories', function (Blueprint $table) {
                    $table->string('status')->default('ACTIVE');
                });
            }
        }

        // --- Units ---
        if ($schema->hasTable('units')) {
            if (!$schema->hasColumn('units', 'type')) {
                $schema->table('units', function (Blueprint $table) {
                    $table->string('type')->default('QUANTITY'); // QUANTITY, WEIGHT, VOLUME, LENGTH, AREA
                });
            }
            if (!$schema->hasColumn('units', 'decimal_precision')) {
                $schema->table('units', function (Blueprint $table) {
                    $table->integer('decimal_precision')->default(0);
                });
            }
            if (!$schema->hasColumn('units', 'status')) {
                $schema->table('units', function (Blueprint $table) {
                    $table->string('status')->default('ACTIVE');
                });
            }
        }

        // --- Products (extend with missing columns) ---
        if ($schema->hasTable('products')) {
            $prodCols = ['product_type', 'mrp', 'wholesale_price', 'is_tax_inclusive', 'cess_rate', 'secondary_unit', 'conversion_ratio', 'opening_stock_value', 'reorder_level', 'is_expiry_tracked', 'image_url', 'created_by', 'updated_by'];
            foreach ($prodCols as $col) {
                if (!$schema->hasColumn('products', $col)) {
                    $schema->table('products', function (Blueprint $table) use ($col) {
                        match ($col) {
                            'product_type' => $table->string('product_type')->default('Goods'),
                            'mrp' => $table->decimal('mrp', 15, 2)->default(0.00),
                            'wholesale_price' => $table->decimal('wholesale_price', 15, 2)->default(0.00),
                            'is_tax_inclusive' => $table->boolean('is_tax_inclusive')->default(false),
                            'cess_rate' => $table->decimal('cess_rate', 5, 2)->default(0.00),
                            'secondary_unit' => $table->string('secondary_unit')->nullable(),
                            'conversion_ratio' => $table->decimal('conversion_ratio', 15, 4)->default(1.0000),
                            'opening_stock_value' => $table->decimal('opening_stock_value', 15, 2)->default(0.00),
                            'reorder_level' => $table->decimal('reorder_level', 15, 3)->default(0.000),
                            'is_expiry_tracked' => $table->boolean('is_expiry_tracked')->default(false),
                            'image_url' => $table->string('image_url')->nullable(),
                            'created_by' => $table->unsignedBigInteger('created_by')->nullable(),
                            'updated_by' => $table->unsignedBigInteger('updated_by')->nullable(),
                        };
                    });
                }
            }
        }
        // --- Chart of Accounts ---
        if ($schema->hasTable('chart_of_accounts')) {
            if (!$schema->hasColumn('chart_of_accounts', 'parent_account_id')) {
                $schema->table('chart_of_accounts', function (Blueprint $table) {
                    $table->unsignedBigInteger('parent_account_id')->nullable()->index();
                });
            }
            if (!$schema->hasColumn('chart_of_accounts', 'description')) {
                $schema->table('chart_of_accounts', function (Blueprint $table) {
                    $table->text('description')->nullable();
                });
            }
            if (!$schema->hasColumn('chart_of_accounts', 'is_system_account')) {
                $schema->table('chart_of_accounts', function (Blueprint $table) {
                    $table->boolean('is_system_account')->default(false);
                });
            }
            if (!$schema->hasColumn('chart_of_accounts', 'is_active')) {
                $schema->table('chart_of_accounts', function (Blueprint $table) {
                    $table->boolean('is_active')->default(true);
                });
            }
        }
        // --- Tax Rates ---
        if ($schema->hasTable('tax_rates')) {
            if (!$schema->hasColumn('tax_rates', 'tax_type')) {
                $schema->table('tax_rates', function (Blueprint $table) {
                    $table->string('tax_type')->default('GST');
                });
            }
            if (!$schema->hasColumn('tax_rates', 'effective_from')) {
                $schema->table('tax_rates', function (Blueprint $table) {
                    $table->date('effective_from')->nullable();
                    $table->date('effective_to')->nullable();
                    $table->string('status')->default('ACTIVE');
                });
            }
        }

        // ============================================================
        // CHUNK 3: SALES TRANSACTION ENGINE — NEW TABLES
        // ============================================================

        if (!$schema->hasTable('document_number_settings')) {
            $schema->create('document_number_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('financial_year'); // e.g. "2026-27"
                $table->string('document_type'); // QUOTATION, SALES_ORDER, DELIVERY_CHALLAN, INVOICE, CREDIT_NOTE, SALES_RETURN
                $table->string('prefix');
                $table->integer('starting_number')->default(1);
                $table->integer('current_number')->default(0);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('sales_returns')) {
            $schema->create('sales_returns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                $table->string('return_number')->index();
                $table->date('return_date');
                $table->string('reason')->nullable();
                $table->boolean('stock_impact')->default(true);
                $table->decimal('sub_total', 15, 2)->default(0.00);
                $table->decimal('total_tax', 15, 2)->default(0.00);
                $table->decimal('grand_total', 15, 2)->default(0.00);
                $table->decimal('refund_amount', 15, 2)->default(0.00);
                $table->decimal('tax_reversal_amount', 15, 2)->default(0.00);
                $table->string('status')->default('COMPLETED');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('sales_return_items')) {
            $schema->create('sales_return_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('sales_return_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('item_name');
                $table->string('hsn_sac', 10)->nullable();
                $table->decimal('quantity', 15, 3);
                $table->string('unit')->default('Pcs');
                $table->decimal('unit_price', 15, 2);
                $table->decimal('taxable_value', 15, 2)->default(0.00);
                $table->decimal('gst_rate', 5, 2)->default(0.00);
                $table->decimal('cgst_amount', 15, 2)->default(0.00);
                $table->decimal('sgst_amount', 15, 2)->default(0.00);
                $table->decimal('igst_amount', 15, 2)->default(0.00);
                $table->decimal('total_amount', 15, 2);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('recurring_invoices')) {
            $schema->create('recurring_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('template_name');
                $table->string('frequency')->default('MONTHLY'); // WEEKLY, MONTHLY, QUARTERLY, YEARLY, CUSTOM
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->date('next_invoice_date');
                $table->date('last_generated_date')->nullable();
                $table->string('status')->default('ACTIVE'); // ACTIVE, PAUSED, COMPLETED, CANCELLED
                $table->string('payment_terms')->nullable();
                $table->decimal('sub_total', 15, 2)->default(0.00);
                $table->decimal('total_tax', 15, 2)->default(0.00);
                $table->decimal('grand_total', 15, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('recurring_invoice_items')) {
            $schema->create('recurring_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('recurring_invoice_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('item_name');
                $table->string('hsn_sac', 10)->nullable();
                $table->decimal('quantity', 15, 3);
                $table->string('unit')->default('Pcs');
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount_rate', 5, 2)->default(0.00);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->decimal('taxable_value', 15, 2)->default(0.00);
                $table->decimal('gst_rate', 5, 2)->default(18.00);
                $table->decimal('cgst_rate', 5, 2)->default(9.00);
                $table->decimal('cgst_amount', 15, 2)->default(0.00);
                $table->decimal('sgst_rate', 5, 2)->default(9.00);
                $table->decimal('sgst_amount', 15, 2)->default(0.00);
                $table->decimal('igst_rate', 5, 2)->default(0.00);
                $table->decimal('igst_amount', 15, 2)->default(0.00);
                $table->decimal('total_amount', 15, 2);
                $table->timestamps();
            });
        }

        // ============================================================
        // CHUNK 3: SALES TRANSACTION ENGINE — ALTERATIONS
        // ============================================================

        // --- Invoices ---
        if ($schema->hasTable('invoices')) {
            if (!$schema->hasColumn('invoices', 'warehouse_id')) {
                $schema->table('invoices', function (Blueprint $table) {
                    $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                });
            }
            if (!$schema->hasColumn('invoices', 'payment_status')) {
                $schema->table('invoices', function (Blueprint $table) {
                    $table->string('payment_status')->default('UNPAID');
                    $table->string('customer_name')->nullable();
                    $table->string('customer_gstin')->nullable();
                    $table->text('billing_address')->nullable();
                    $table->text('shipping_address')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->unsignedBigInteger('updated_by')->nullable();
                    $table->unsignedBigInteger('source_quotation_id')->nullable()->index();
                    $table->unsignedBigInteger('source_sales_order_id')->nullable()->index();
                    $table->unsignedBigInteger('source_delivery_challan_id')->nullable()->index();
                });
            }
        }

        // --- Quotations ---
        if ($schema->hasTable('quotations')) {
            if (!$schema->hasColumn('quotations', 'branch_id')) {
                $schema->table('quotations', function (Blueprint $table) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                    $table->string('reference_no')->nullable();
                    $table->text('billing_address')->nullable();
                    $table->text('shipping_address')->nullable();
                    $table->decimal('discount_amount', 15, 2)->default(0.00);
                    $table->decimal('taxable_value', 15, 2)->default(0.00);
                    $table->decimal('cgst_amount', 15, 2)->default(0.00);
                    $table->decimal('sgst_amount', 15, 2)->default(0.00);
                    $table->decimal('igst_amount', 15, 2)->default(0.00);
                    $table->decimal('round_off', 5, 2)->default(0.00);
                    $table->text('notes')->nullable();
                    $table->text('terms')->nullable();
                    $table->boolean('converted_to_invoice')->default(false);
                    $table->unsignedBigInteger('converted_invoice_id')->nullable()->index();
                });
            }
        }

        // --- Quotation Items ---
        if ($schema->hasTable('quotation_items')) {
            if (!$schema->hasColumn('quotation_items', 'item_name')) {
                $schema->table('quotation_items', function (Blueprint $table) {
                    $table->string('item_name')->nullable();
                    $table->string('hsn_sac', 10)->nullable();
                    $table->string('unit')->default('Pcs');
                    $table->decimal('discount_rate', 5, 2)->default(0.00);
                    $table->decimal('discount_amount', 15, 2)->default(0.00);
                    $table->decimal('taxable_value', 15, 2)->default(0.00);
                    $table->decimal('gst_rate', 5, 2)->default(18.00);
                    $table->decimal('cgst_rate', 5, 2)->default(9.00);
                    $table->decimal('cgst_amount', 15, 2)->default(0.00);
                    $table->decimal('sgst_rate', 5, 2)->default(9.00);
                    $table->decimal('sgst_amount', 15, 2)->default(0.00);
                    $table->decimal('igst_rate', 5, 2)->default(0.00);
                    $table->decimal('igst_amount', 15, 2)->default(0.00);
                });
            }
        }

        // --- Sales Orders ---
        if ($schema->hasTable('sales_orders')) {
            if (!$schema->hasColumn('sales_orders', 'branch_id')) {
                $schema->table('sales_orders', function (Blueprint $table) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                    $table->date('expected_delivery')->nullable();
                    $table->string('reference_no')->nullable();
                    $table->decimal('sub_total', 15, 2)->default(0.00);
                    $table->decimal('discount_amount', 15, 2)->default(0.00);
                    $table->decimal('total_tax', 15, 2)->default(0.00);
                    $table->text('notes')->nullable();
                    $table->string('fulfillment_status')->default('CONFIRMED');
                    $table->string('payment_status')->default('UNPAID');
                    $table->unsignedBigInteger('source_quotation_id')->nullable()->index();
                });
            }
        }

        // --- Sales Order Items ---
        if ($schema->hasTable('sales_order_items')) {
            if (!$schema->hasColumn('sales_order_items', 'item_name')) {
                $schema->table('sales_order_items', function (Blueprint $table) {
                    $table->string('item_name')->nullable();
                    $table->string('hsn_sac', 10)->nullable();
                    $table->string('unit')->default('Pcs');
                    $table->decimal('discount_rate', 5, 2)->default(0.00);
                    $table->decimal('discount_amount', 15, 2)->default(0.00);
                    $table->decimal('taxable_value', 15, 2)->default(0.00);
                    $table->decimal('gst_rate', 5, 2)->default(18.00);
                    $table->decimal('cgst_rate', 5, 2)->default(9.00);
                    $table->decimal('cgst_amount', 15, 2)->default(0.00);
                    $table->decimal('sgst_rate', 5, 2)->default(9.00);
                    $table->decimal('sgst_amount', 15, 2)->default(0.00);
                    $table->decimal('igst_rate', 5, 2)->default(0.00);
                    $table->decimal('igst_amount', 15, 2)->default(0.00);
                });
            }
        }

        // --- Delivery Challans ---
        if ($schema->hasTable('delivery_challans')) {
            if (!$schema->hasColumn('delivery_challans', 'branch_id')) {
                $schema->table('delivery_challans', function (Blueprint $table) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                    $table->unsignedBigInteger('sales_order_id')->nullable()->index();
                    $table->string('reference_so')->nullable();
                    $table->text('delivery_address')->nullable();
                    $table->text('transport_details')->nullable();
                    $table->string('status')->default('DRAFT');
                    $table->text('notes')->nullable();
                });
            }
        }

        // --- Delivery Challan Items ---
        if ($schema->hasTable('delivery_challan_items')) {
            if (!$schema->hasColumn('delivery_challan_items', 'item_name')) {
                $schema->table('delivery_challan_items', function (Blueprint $table) {
                    $table->string('item_name')->nullable();
                    $table->string('unit')->default('Pcs');
                    $table->decimal('unit_price', 15, 2)->default(0.00);
                    $table->decimal('total_amount', 15, 2)->default(0.00);
                    $table->string('batch_no')->nullable();
                    $table->string('serial_no')->nullable();
                });
            }
        }

        // --- Credit Notes ---
        if ($schema->hasTable('credit_notes')) {
            if (!$schema->hasColumn('credit_notes', 'branch_id')) {
                $schema->table('credit_notes', function (Blueprint $table) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                    $table->string('original_invoice_number')->nullable();
                    $table->decimal('sub_total', 15, 2)->default(0.00);
                    $table->decimal('total_tax', 15, 2)->default(0.00);
                    $table->decimal('cgst_reversal', 15, 2)->default(0.00);
                    $table->decimal('sgst_reversal', 15, 2)->default(0.00);
                    $table->decimal('igst_reversal', 15, 2)->default(0.00);
                    $table->string('status')->default('APPROVED');
                    $table->text('notes')->nullable();
                });
            }
        }

        // --- Credit Note Items ---
        if ($schema->hasTable('credit_note_items')) {
            if (!$schema->hasColumn('credit_note_items', 'item_name')) {
                $schema->table('credit_note_items', function (Blueprint $table) {
                    $table->string('item_name')->nullable();
                    $table->string('hsn_sac', 10)->nullable();
                    $table->string('unit')->default('Pcs');
                    $table->decimal('unit_price', 15, 2)->default(0.00);
                    $table->decimal('discount_rate', 5, 2)->default(0.00);
                    $table->decimal('discount_amount', 15, 2)->default(0.00);
                    $table->decimal('taxable_value', 15, 2)->default(0.00);
                    $table->decimal('gst_rate', 5, 2)->default(18.00);
                    $table->decimal('cgst_rate', 5, 2)->default(9.00);
                    $table->decimal('cgst_amount', 15, 2)->default(0.00);
                    $table->decimal('sgst_rate', 5, 2)->default(9.00);
                    $table->decimal('sgst_amount', 15, 2)->default(0.00);
                    $table->decimal('igst_rate', 5, 2)->default(0.00);
                    $table->decimal('igst_amount', 15, 2)->default(0.00);
                });
            }
        }

        // ====================================================
        // CHUNK 4 - PURCHASE module schemas & alterations
        // ====================================================

        // 1. Purchase Orders
        if (!$schema->hasTable('purchase_orders')) {
            $schema->create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->string('po_number')->index();
                $table->date('po_date');
                $table->date('expected_delivery')->nullable();
                $table->string('reference_no')->nullable();
                $table->decimal('sub_total', 15, 2)->default(0.00);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->decimal('total_tax', 15, 2)->default(0.00);
                $table->decimal('grand_total', 15, 2)->default(0.00);
                $table->string('status')->default('DRAFT'); // DRAFT, SENT, PARTIALLY_RECEIVED, RECEIVED, CANCELLED, CLOSED
                $table->text('notes')->nullable();
                $table->text('terms')->nullable();
                $table->timestamps();
            });
        }

        // 2. Purchase Order Items
        if (!$schema->hasTable('purchase_order_items')) {
            $schema->create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('item_name');
                $table->string('hsn_sac', 10)->nullable();
                $table->decimal('quantity', 15, 3)->default(0.000);
                $table->decimal('received_quantity', 15, 3)->default(0.000);
                $table->string('unit')->default('Pcs');
                $table->decimal('unit_price', 15, 2)->default(0.00);
                $table->decimal('discount_rate', 5, 2)->default(0.00);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->decimal('taxable_value', 15, 2)->default(0.00);
                $table->decimal('gst_rate', 5, 2)->default(18.00);
                $table->decimal('cgst_rate', 5, 2)->default(9.00);
                $table->decimal('cgst_amount', 15, 2)->default(0.00);
                $table->decimal('sgst_rate', 5, 2)->default(9.00);
                $table->decimal('sgst_amount', 15, 2)->default(0.00);
                $table->decimal('igst_rate', 5, 2)->default(0.00);
                $table->decimal('igst_amount', 15, 2)->default(0.00);
                $table->decimal('total_amount', 15, 2)->default(0.00);
                $table->timestamps();
            });
        }

        // 3. Goods Receipts (GRN)
        if (!$schema->hasTable('goods_receipts')) {
            $schema->create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
                $table->string('grn_number')->index();
                $table->date('grn_date');
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->string('status')->default('DRAFT'); // DRAFT, RECEIVED, CANCELLED
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 4. Goods Receipt Items
        if (!$schema->hasTable('goods_receipt_items')) {
            $schema->create('goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('goods_receipt_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('item_name');
                $table->decimal('quantity_ordered', 15, 3)->default(0.000);
                $table->decimal('quantity_received', 15, 3)->default(0.000);
                $table->string('unit')->default('Pcs');
                $table->decimal('unit_price', 15, 2)->default(0.00);
                $table->timestamps();
            });
        }

        // 5. Debit Notes
        if (!$schema->hasTable('debit_notes')) {
            $schema->create('debit_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->unsignedBigInteger('purchase_id')->nullable()->index();
                $table->string('debit_note_number')->index();
                $table->date('debit_note_date');
                $table->string('original_purchase_number')->nullable();
                $table->decimal('sub_total', 15, 2)->default(0.00);
                $table->decimal('total_tax', 15, 2)->default(0.00);
                $table->decimal('cgst_reversal', 15, 2)->default(0.00);
                $table->decimal('sgst_reversal', 15, 2)->default(0.00);
                $table->decimal('igst_reversal', 15, 2)->default(0.00);
                $table->decimal('amount', 15, 2)->default(0.00);
                $table->string('reason')->default('Other');
                $table->string('status')->default('APPROVED'); // APPROVED, CANCELLED
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 6. Debit Note Items
        if (!$schema->hasTable('debit_note_items')) {
            $schema->create('debit_note_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('debit_note_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('item_name');
                $table->string('hsn_sac', 10)->nullable();
                $table->decimal('quantity', 15, 3)->default(0.000);
                $table->string('unit')->default('Pcs');
                $table->decimal('unit_price', 15, 2)->default(0.00);
                $table->decimal('discount_rate', 5, 2)->default(0.00);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->decimal('taxable_value', 15, 2)->default(0.00);
                $table->decimal('gst_rate', 5, 2)->default(18.00);
                $table->decimal('cgst_rate', 5, 2)->default(9.00);
                $table->decimal('cgst_amount', 15, 2)->default(0.00);
                $table->decimal('sgst_rate', 5, 2)->default(9.00);
                $table->decimal('sgst_amount', 15, 2)->default(0.00);
                $table->decimal('igst_rate', 5, 2)->default(0.00);
                $table->decimal('igst_amount', 15, 2)->default(0.00);
                $table->decimal('amount', 15, 2)->default(0.00);
                $table->timestamps();
            });
        }

        // 7. Supplier Bill Attachments
        if (!$schema->hasTable('supplier_bill_attachments')) {
            $schema->create('supplier_bill_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('purchase_id')->nullable()->index();
                $table->string('file_name');
                $table->string('file_type');
                $table->string('file_path');
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }

        // 8. Alter purchases table to add missing Chunk 4 columns
        if ($schema->hasTable('purchases')) {
            $schema->table('purchases', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('purchases', 'warehouse_id')) {
                    $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                }
                if (!$schema->hasColumn('purchases', 'purchase_order_id')) {
                    $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
                }
                if (!$schema->hasColumn('purchases', 'reference_no')) {
                    $table->string('reference_no')->nullable();
                }
                if (!$schema->hasColumn('purchases', 'place_of_supply')) {
                    $table->string('place_of_supply')->nullable();
                }
                if (!$schema->hasColumn('purchases', 'payment_status')) {
                    $table->string('payment_status')->default('UNPAID');
                }
                if (!$schema->hasColumn('purchases', 'supplier_name')) {
                    $table->string('supplier_name')->nullable();
                }
                if (!$schema->hasColumn('purchases', 'supplier_gstin')) {
                    $table->string('supplier_gstin')->nullable();
                }
                if (!$schema->hasColumn('purchases', 'billing_address')) {
                    $table->text('billing_address')->nullable();
                }
                if (!$schema->hasColumn('purchases', 'shipping_address')) {
                    $table->text('shipping_address')->nullable();
                }
                if (!$schema->hasColumn('purchases', 'freight_amount')) {
                    $table->decimal('freight_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchases', 'freight_taxable')) {
                    $table->boolean('freight_taxable')->default(false);
                }
                if (!$schema->hasColumn('purchases', 'additional_charges')) {
                    $table->decimal('additional_charges', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchases', 'additional_charges_taxable')) {
                    $table->boolean('additional_charges_taxable')->default(false);
                }
                if (!$schema->hasColumn('purchases', 'discount_rate')) {
                    $table->decimal('discount_rate', 5, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchases', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchases', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!$schema->hasColumn('purchases', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable();
                }
            });
        }

        // 9. Alter purchase_items table to add missing Chunk 4 columns
        if ($schema->hasTable('purchase_items')) {
            $schema->table('purchase_items', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('purchase_items', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!$schema->hasColumn('purchase_items', 'discount_rate')) {
                    $table->decimal('discount_rate', 5, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_items', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_items', 'cgst_rate')) {
                    $table->decimal('cgst_rate', 5, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_items', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_items', 'sgst_rate')) {
                    $table->decimal('sgst_rate', 5, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_items', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_items', 'igst_rate')) {
                    $table->decimal('igst_rate', 5, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_items', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_items', 'cess_rate')) {
                    $table->decimal('cess_rate', 5, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_items', 'cess_amount')) {
                    $table->decimal('cess_amount', 15, 2)->default(0.00);
                }
            });
        }

        // 10. Alter purchase_returns table to add missing Chunk 4 columns
        if ($schema->hasTable('purchase_returns')) {
            $schema->table('purchase_returns', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('purchase_returns', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('purchase_returns', 'purchase_id')) {
                    $table->unsignedBigInteger('purchase_id')->nullable()->index();
                }
                if (!$schema->hasColumn('purchase_returns', 'reason')) {
                    $table->string('reason')->nullable();
                }
                if (!$schema->hasColumn('purchase_returns', 'warehouse_id')) {
                    $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                }
                if (!$schema->hasColumn('purchase_returns', 'sub_total')) {
                    $table->decimal('sub_total', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_returns', 'total_tax')) {
                    $table->decimal('total_tax', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_returns', 'refund_amount')) {
                    $table->decimal('refund_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_returns', 'tax_reversal_amount')) {
                    $table->decimal('tax_reversal_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_returns', 'status')) {
                    $table->string('status')->default('COMPLETED');
                }
                if (!$schema->hasColumn('purchase_returns', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }

        // 11. Alter purchase_return_items table to add missing Chunk 4 columns
        if ($schema->hasTable('purchase_return_items')) {
            $schema->table('purchase_return_items', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('purchase_return_items', 'item_name')) {
                    $table->string('item_name')->nullable();
                }
                if (!$schema->hasColumn('purchase_return_items', 'hsn_sac')) {
                    $table->string('hsn_sac', 10)->nullable();
                }
                if (!$schema->hasColumn('purchase_return_items', 'unit')) {
                    $table->string('unit')->default('Pcs');
                }
                if (!$schema->hasColumn('purchase_return_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_return_items', 'taxable_value')) {
                    $table->decimal('taxable_value', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_return_items', 'gst_rate')) {
                    $table->decimal('gst_rate', 5, 2)->default(18.00);
                }
                if (!$schema->hasColumn('purchase_return_items', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_return_items', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_return_items', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_return_items', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->default(0.00);
                }
            });
        }

        // 12. Alter purchase_orders table to ensure all Chunk 4 columns exist
        if ($schema->hasTable('purchase_orders')) {
            $schema->table('purchase_orders', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('purchase_orders', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('purchase_orders', 'expected_delivery')) {
                    $table->date('expected_delivery')->nullable();
                }
                if (!$schema->hasColumn('purchase_orders', 'reference_no')) {
                    $table->string('reference_no')->nullable();
                }
                if (!$schema->hasColumn('purchase_orders', 'sub_total')) {
                    $table->decimal('sub_total', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_orders', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_orders', 'total_tax')) {
                    $table->decimal('total_tax', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_orders', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!$schema->hasColumn('purchase_orders', 'terms')) {
                    $table->text('terms')->nullable();
                }
            });
        }

        // 13. Alter purchase_order_items table to ensure all Chunk 4 columns exist
        if ($schema->hasTable('purchase_order_items')) {
            $schema->table('purchase_order_items', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('purchase_order_items', 'item_name')) {
                    $table->string('item_name')->default('Item');
                }
                if (!$schema->hasColumn('purchase_order_items', 'hsn_sac')) {
                    $table->string('hsn_sac', 10)->nullable();
                }
                if (!$schema->hasColumn('purchase_order_items', 'received_quantity')) {
                    $table->decimal('received_quantity', 15, 3)->default(0.000);
                }
                if (!$schema->hasColumn('purchase_order_items', 'unit')) {
                    $table->string('unit')->default('Pcs');
                }
                if (!$schema->hasColumn('purchase_order_items', 'discount_rate')) {
                    $table->decimal('discount_rate', 5, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_order_items', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_order_items', 'taxable_value')) {
                    $table->decimal('taxable_value', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_order_items', 'gst_rate')) {
                    $table->decimal('gst_rate', 5, 2)->default(18.00);
                }
                if (!$schema->hasColumn('purchase_order_items', 'cgst_rate')) {
                    $table->decimal('cgst_rate', 5, 2)->default(9.00);
                }
                if (!$schema->hasColumn('purchase_order_items', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_order_items', 'sgst_rate')) {
                    $table->decimal('sgst_rate', 5, 2)->default(9.00);
                }
                if (!$schema->hasColumn('purchase_order_items', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_order_items', 'igst_rate')) {
                    $table->decimal('igst_rate', 5, 2)->default(0.00);
                }
                if (!$schema->hasColumn('purchase_order_items', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 2)->default(0.00);
                }
            });
        }

        // ====================================================
        // CHUNK 5 - INVENTORY & STOCK ENGINE schemas & alterations
        // ====================================================

        // 1. Stock Counts & Physical Verification
        if (!$schema->hasTable('stock_counts')) {
            $schema->create('stock_counts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->string('count_number')->index();
                $table->date('count_date');
                $table->string('status')->default('DRAFT'); // DRAFT, IN_PROGRESS, COMPLETED, CANCELLED
                $table->string('counted_by')->default('Admin');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('stock_count_items')) {
            $schema->create('stock_count_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('stock_count_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('system_quantity', 15, 3)->default(0.000);
                $table->decimal('physical_quantity', 15, 3)->default(0.000);
                $table->decimal('difference', 15, 3)->default(0.000);
                $table->decimal('unit_cost', 15, 2)->default(0.00);
                $table->boolean('adjustment_created')->default(false);
                $table->unsignedBigInteger('adjustment_id')->nullable();
                $table->timestamps();
            });
        }

        // 2. Alter stock_movements for Chunk 5
        if ($schema->hasTable('stock_movements')) {
            $schema->table('stock_movements', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('stock_movements', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('stock_movements', 'batch_id')) {
                    $table->unsignedBigInteger('batch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('stock_movements', 'serial_number_id')) {
                    $table->unsignedBigInteger('serial_number_id')->nullable()->index();
                }
                if (!$schema->hasColumn('stock_movements', 'movement_type')) {
                    $table->string('movement_type')->default('OPENING_STOCK')->index();
                }
                if (!$schema->hasColumn('stock_movements', 'direction')) {
                    $table->string('direction')->default('IN')->index(); // IN, OUT
                }
                if (!$schema->hasColumn('stock_movements', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('stock_movements', 'total_cost')) {
                    $table->decimal('total_cost', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('stock_movements', 'reference_number')) {
                    $table->string('reference_number')->nullable()->index();
                }
                if (!$schema->hasColumn('stock_movements', 'movement_date')) {
                    $table->date('movement_date')->default(date('Y-m-d'))->index();
                }
                if (!$schema->hasColumn('stock_movements', 'created_by')) {
                    $table->string('created_by')->default('System');
                }
            });
        }

        // 3. Alter stock_balances for Chunk 5
        if ($schema->hasTable('stock_balances')) {
            $schema->table('stock_balances', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('stock_balances', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('stock_balances', 'available_quantity')) {
                    $table->decimal('available_quantity', 15, 3)->default(0.000);
                }
                if (!$schema->hasColumn('stock_balances', 'last_movement_id')) {
                    $table->unsignedBigInteger('last_movement_id')->nullable();
                }
            });
        }

        // 4. Alter batches for Chunk 5
        if ($schema->hasTable('batches')) {
            $schema->table('batches', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('batches', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('batches', 'purchase_rate')) {
                    $table->decimal('purchase_rate', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('batches', 'status')) {
                    $table->string('status')->default('ACTIVE'); // ACTIVE, EXPIRED, DEPLETED
                }
            });
        }

        // 5. Alter serial_numbers for Chunk 5
        if ($schema->hasTable('serial_numbers')) {
            $schema->table('serial_numbers', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('serial_numbers', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('serial_numbers', 'purchase_id')) {
                    $table->unsignedBigInteger('purchase_id')->nullable()->index();
                }
                if (!$schema->hasColumn('serial_numbers', 'invoice_id')) {
                    $table->unsignedBigInteger('invoice_id')->nullable()->index();
                }
            });
        }

        // 6. Alter warehouses for Chunk 5
        if ($schema->hasTable('warehouses')) {
            $schema->table('warehouses', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('warehouses', 'address')) {
                    $table->text('address')->nullable();
                }
                if (!$schema->hasColumn('warehouses', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });
        }

        // 7. Alter products for Chunk 5 inventory configurations
        if ($schema->hasTable('products')) {
            $schema->table('products', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('products', 'track_inventory')) {
                    $table->boolean('track_inventory')->default(true);
                }
                if (!$schema->hasColumn('products', 'track_batch')) {
                    $table->boolean('track_batch')->default(false);
                }
                if (!$schema->hasColumn('products', 'track_serial')) {
                    $table->boolean('track_serial')->default(false);
                }
                if (!$schema->hasColumn('products', 'track_expiry')) {
                    $table->boolean('track_expiry')->default(false);
                }
                if (!$schema->hasColumn('products', 'allow_negative_stock')) {
                    $table->boolean('allow_negative_stock')->default(false);
                }
                if (!$schema->hasColumn('products', 'reorder_level')) {
                    $table->decimal('reorder_level', 15, 3)->default(10.000);
                }
                if (!$schema->hasColumn('products', 'reorder_quantity')) {
                    $table->decimal('reorder_quantity', 15, 3)->default(50.000);
                }
                if (!$schema->hasColumn('products', 'valuation_method')) {
                    $table->string('valuation_method')->default('WEIGHTED_AVERAGE'); // WEIGHTED_AVERAGE, FIFO
                }
                if (!$schema->hasColumn('products', 'default_warehouse_id')) {
                    $table->unsignedBigInteger('default_warehouse_id')->nullable();
                }
            });
        }

        // ----------------------------------------------------
        // 8. CHUNK 6: PAYMENTS, RECEIVABLES & PAYABLES ENGINE
        // ----------------------------------------------------
        if (!$schema->hasTable('payments')) {
            $schema->create('payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('financial_year', 20)->default('2026-27')->index();
                $table->string('payment_number')->index();
                $table->string('payment_type', 30)->index(); // RECEIPT, PAYMENT
                $table->date('payment_date')->index();
                $table->string('party_type', 30)->index(); // CUSTOMER, SUPPLIER
                $table->unsignedBigInteger('party_id')->index();
                $table->decimal('amount', 15, 2);
                $table->decimal('allocated_amount', 15, 2)->default(0.00);
                $table->decimal('unallocated_amount', 15, 2)->default(0.00);
                $table->string('currency', 3)->default('INR');
                $table->string('payment_mode', 30)->default('CASH')->index(); // CASH, UPI, CARD, BANK_TRANSFER, CHEQUE, OTHER
                $table->unsignedBigInteger('account_id')->nullable()->index(); // ChartOfAccount or BankAccount
                $table->string('reference_number')->nullable()->index();
                $table->string('transaction_reference')->nullable();
                $table->string('bank_reference')->nullable();
                $table->string('utr')->nullable();
                $table->string('cheque_number')->nullable();
                $table->date('cheque_date')->nullable();
                $table->string('cheque_bank')->nullable();
                $table->string('cheque_status', 30)->default('RECEIVED')->index(); // RECEIVED, DEPOSITED, CLEARED, BOUNCED, CANCELLED
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('DRAFT')->index(); // DRAFT, POSTED, CANCELLED
                $table->string('created_by')->default('System');
                $table->string('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'payment_number']);
            });
        } else {
            $schema->table('payments', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('payments', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('payments', 'financial_year')) {
                    $table->string('financial_year', 20)->default('2026-27')->index();
                }
                if (!$schema->hasColumn('payments', 'payment_type')) {
                    $table->string('payment_type', 30)->default('RECEIPT')->index();
                }
                if (!$schema->hasColumn('payments', 'allocated_amount')) {
                    $table->decimal('allocated_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('payments', 'unallocated_amount')) {
                    $table->decimal('unallocated_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('payments', 'currency')) {
                    $table->string('currency', 3)->default('INR');
                }
                if (!$schema->hasColumn('payments', 'account_id')) {
                    $table->unsignedBigInteger('account_id')->nullable()->index();
                }
                if (!$schema->hasColumn('payments', 'reference_number')) {
                    $table->string('reference_number')->nullable()->index();
                }
                if (!$schema->hasColumn('payments', 'transaction_reference')) {
                    $table->string('transaction_reference')->nullable();
                }
                if (!$schema->hasColumn('payments', 'bank_reference')) {
                    $table->string('bank_reference')->nullable();
                }
                if (!$schema->hasColumn('payments', 'utr')) {
                    $table->string('utr')->nullable();
                }
                if (!$schema->hasColumn('payments', 'cheque_number')) {
                    $table->string('cheque_number')->nullable();
                }
                if (!$schema->hasColumn('payments', 'cheque_date')) {
                    $table->date('cheque_date')->nullable();
                }
                if (!$schema->hasColumn('payments', 'cheque_bank')) {
                    $table->string('cheque_bank')->nullable();
                }
                if (!$schema->hasColumn('payments', 'cheque_status')) {
                    $table->string('cheque_status', 30)->default('RECEIVED')->index();
                }
                if (!$schema->hasColumn('payments', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!$schema->hasColumn('payments', 'status')) {
                    $table->string('status', 30)->default('DRAFT')->index();
                }
                if (!$schema->hasColumn('payments', 'created_by')) {
                    $table->string('created_by')->default('System');
                }
                if (!$schema->hasColumn('payments', 'updated_by')) {
                    $table->string('updated_by')->nullable();
                }
                if (!$schema->hasColumn('payments', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (!$schema->hasTable('payment_allocations')) {
            $schema->create('payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('payment_id')->index();
                $table->string('document_type', 40)->index(); // INVOICE, PURCHASE_INVOICE, CREDIT_NOTE, DEBIT_NOTE, ADVANCE, REFUND
                $table->unsignedBigInteger('document_id')->index();
                $table->decimal('allocated_amount', 15, 2);
                $table->date('allocation_date')->index();
                $table->text('notes')->nullable();
                $table->string('created_by')->default('System');
                $table->timestamps();

                $table->index(['company_id', 'payment_id', 'document_id']);
                $table->index(['company_id', 'document_type', 'document_id']);
            });
        } else {
            $schema->table('payment_allocations', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('payment_allocations', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('payment_allocations', 'allocation_date')) {
                    $table->date('allocation_date')->default(date('Y-m-d'))->index();
                }
                if (!$schema->hasColumn('payment_allocations', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!$schema->hasColumn('payment_allocations', 'created_by')) {
                    $table->string('created_by')->default('System');
                }
            });
        }

        if (!$schema->hasTable('payment_refunds')) {
            $schema->create('payment_refunds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('financial_year', 20)->default('2026-27')->index();
                $table->string('refund_number')->index();
                $table->unsignedBigInteger('payment_id')->nullable()->index();
                $table->string('party_type', 30)->index(); // CUSTOMER, SUPPLIER
                $table->unsignedBigInteger('party_id')->index();
                $table->decimal('amount', 15, 2);
                $table->date('refund_date')->index();
                $table->string('refund_mode', 30)->default('CASH');
                $table->string('reference_no')->nullable();
                $table->text('reason')->nullable();
                $table->string('status', 30)->default('POSTED')->index(); // POSTED, CANCELLED
                $table->string('created_by')->default('System');
                $table->timestamps();

                $table->unique(['company_id', 'refund_number']);
            });
        }

        if (!$schema->hasTable('payment_reminders')) {
            $schema->create('payment_reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->string('channel', 20)->default('WHATSAPP')->index(); // EMAIL, WHATSAPP, SMS
                $table->date('sent_date')->index();
                $table->integer('days_overdue')->default(0);
                $table->decimal('outstanding_amount', 15, 2)->default(0.00);
                $table->string('status', 30)->default('SENT')->index(); // PENDING, SENT, FAILED, DISMISSED
                $table->string('message_template_id')->nullable();
                $table->text('notes')->nullable();
                $table->string('created_by')->default('System');
                $table->timestamps();
            });
        }

        // ----------------------------------------------------
        // 9. CHUNK 7: COMPLETE DOUBLE-ENTRY ACCOUNTING ENGINE
        // ----------------------------------------------------
        if (!$schema->hasTable('chart_of_accounts')) {
            $schema->create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('parent_account_id')->nullable()->index();
                $table->string('account_code', 30)->index();
                $table->string('account_name');
                $table->string('account_type', 30)->index(); // ASSET, LIABILITY, EQUITY, INCOME, EXPENSE
                $table->string('account_subtype', 50)->nullable()->index();
                $table->string('nature', 10)->default('DEBIT')->index(); // DEBIT or CREDIT
                $table->decimal('opening_balance', 15, 2)->default(0.00);
                $table->string('opening_balance_type', 10)->default('DEBIT');
                $table->boolean('is_system_account')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'account_code']);
            });
        } else {
            $schema->table('chart_of_accounts', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('chart_of_accounts', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('chart_of_accounts', 'account_subtype')) {
                    $table->string('account_subtype', 50)->nullable()->index();
                }
                if (!$schema->hasColumn('chart_of_accounts', 'nature')) {
                    $table->string('nature', 10)->default('DEBIT')->index();
                }
                if (!$schema->hasColumn('chart_of_accounts', 'opening_balance')) {
                    $table->decimal('opening_balance', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('chart_of_accounts', 'opening_balance_type')) {
                    $table->string('opening_balance_type', 10)->default('DEBIT');
                }
                if (!$schema->hasColumn('chart_of_accounts', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (!$schema->hasTable('journal_entries')) {
            $schema->create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('financial_year', 20)->default('2026-27')->index();
                $table->string('journal_number', 50)->index();
                $table->date('entry_date')->index();
                $table->string('entry_type', 40)->default('MANUAL')->index(); // SALE, PURCHASE, RECEIPT, PAYMENT, EXPENSE, SALES_RETURN, PURCHASE_RETURN, REFUND, STOCK_ADJUSTMENT, OPENING_BALANCE, MANUAL
                $table->string('reference_type', 50)->nullable()->index();
                $table->string('reference_id', 100)->nullable()->index();
                $table->text('description')->nullable();
                $table->string('status', 30)->default('POSTED')->index(); // DRAFT, POSTED, REVERSED
                $table->decimal('total_debit', 15, 2)->default(0.00);
                $table->decimal('total_credit', 15, 2)->default(0.00);
                $table->boolean('is_system_generated')->default(false)->index();
                $table->unsignedBigInteger('original_journal_id')->nullable()->index();
                $table->unsignedBigInteger('reversal_journal_id')->nullable()->index();
                $table->text('reversal_reason')->nullable();
                $table->string('created_by')->default('System');
                $table->string('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->string('reversed_by')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'journal_number']);
            });
        } else {
            $schema->table('journal_entries', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('journal_entries', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('journal_entries', 'financial_year')) {
                    $table->string('financial_year', 20)->default('2026-27')->index();
                }
                if (!$schema->hasColumn('journal_entries', 'journal_number')) {
                    $table->string('journal_number', 50)->nullable()->index();
                }
                if (!$schema->hasColumn('journal_entries', 'entry_type')) {
                    $table->string('entry_type', 40)->default('MANUAL')->index();
                }
                if (!$schema->hasColumn('journal_entries', 'reference_type')) {
                    $table->string('reference_type', 50)->nullable()->index();
                }
                if (!$schema->hasColumn('journal_entries', 'reference_id')) {
                    $table->string('reference_id', 100)->nullable()->index();
                }
                if (!$schema->hasColumn('journal_entries', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!$schema->hasColumn('journal_entries', 'status')) {
                    $table->string('status', 30)->default('POSTED')->index();
                }
                if (!$schema->hasColumn('journal_entries', 'is_system_generated')) {
                    $table->boolean('is_system_generated')->default(false)->index();
                }
                if (!$schema->hasColumn('journal_entries', 'original_journal_id')) {
                    $table->unsignedBigInteger('original_journal_id')->nullable()->index();
                }
                if (!$schema->hasColumn('journal_entries', 'reversal_journal_id')) {
                    $table->unsignedBigInteger('reversal_journal_id')->nullable()->index();
                }
                if (!$schema->hasColumn('journal_entries', 'reversal_reason')) {
                    $table->text('reversal_reason')->nullable();
                }
                if (!$schema->hasColumn('journal_entries', 'created_by')) {
                    $table->string('created_by')->default('System');
                }
                if (!$schema->hasColumn('journal_entries', 'posted_by')) {
                    $table->string('posted_by')->nullable();
                }
                if (!$schema->hasColumn('journal_entries', 'posted_at')) {
                    $table->timestamp('posted_at')->nullable();
                }
                if (!$schema->hasColumn('journal_entries', 'reversed_by')) {
                    $table->string('reversed_by')->nullable();
                }
                if (!$schema->hasColumn('journal_entries', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable();
                }
                if (!$schema->hasColumn('journal_entries', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (!$schema->hasTable('journal_lines')) {
            $schema->create('journal_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('journal_entry_id')->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->decimal('debit', 15, 2)->default(0.00);
                $table->decimal('credit', 15, 2)->default(0.00);
                $table->text('description')->nullable();
                $table->string('party_type', 30)->nullable()->index(); // CUSTOMER, SUPPLIER
                $table->unsignedBigInteger('party_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('cost_center', 50)->nullable()->index();
                $table->string('tax_category', 30)->nullable()->index();
                $table->timestamps();

                $table->index(['company_id', 'account_id']);
                $table->index(['company_id', 'journal_entry_id', 'account_id']);
            });
        }

        if (!$schema->hasTable('accounting_periods')) {
            $schema->create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('financial_year', 20)->default('2026-27')->index();
                $table->string('period_name', 50)->index(); // Q1, Q2, Q3, Q4, ANNUAL, or MONTHLY
                $table->date('start_date')->index();
                $table->date('end_date')->index();
                $table->boolean('is_locked')->default(false)->index();
                $table->string('locked_by')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->text('lock_reason')->nullable();
                $table->unique(['company_id', 'financial_year', 'period_name']);
            });
        } else {
            $schema->table('accounting_periods', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('accounting_periods', 'financial_year')) {
                    $table->string('financial_year', 20)->default('2026-27')->index();
                }
                if (!$schema->hasColumn('accounting_periods', 'is_locked')) {
                    $table->boolean('is_locked')->default(false)->index();
                }
                if (!$schema->hasColumn('accounting_periods', 'locked_by')) {
                    $table->string('locked_by')->nullable();
                }
                if (!$schema->hasColumn('accounting_periods', 'locked_at')) {
                    $table->timestamp('locked_at')->nullable();
                }
                if (!$schema->hasColumn('accounting_periods', 'lock_reason')) {
                    $table->text('lock_reason')->nullable();
                }
            });
        }

        if (!$schema->hasTable('account_mappings')) {
            $schema->create('account_mappings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('mapping_key', 50)->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'mapping_key']);
            });
        }

        // ----------------------------------------------------
        // 10. CHUNK 8: GST, TAX, E-INVOICE & E-WAY BILL ENGINE
        // ----------------------------------------------------
        if (!$schema->hasTable('gst_configurations')) {
            $schema->create('gst_configurations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->boolean('gst_registered')->default(true);
                $table->string('gstin', 15)->nullable()->index();
                $table->string('legal_business_name')->nullable();
                $table->string('trade_name')->nullable();
                $table->string('pan', 10)->nullable()->index();
                $table->string('registered_state', 100)->default('Maharashtra');
                $table->string('state_code', 2)->default('27')->index();
                $table->string('tax_registration_type', 30)->default('REGISTERED_REGULAR')->index(); // REGISTERED_REGULAR, REGISTERED_COMPOSITION, UNREGISTERED, CONSUMER
                $table->boolean('is_composition')->default(false);
                $table->string('default_place_of_supply', 100)->default('Maharashtra');
                $table->boolean('einvoice_applicable')->default(false);
                $table->decimal('einvoice_threshold', 15, 2)->default(50000000.00); // 5 Cr standard threshold
                $table->boolean('eway_bill_applicable')->default(true);
                $table->decimal('eway_threshold', 15, 2)->default(50000.00); // ₹50,000 standard
                $table->string('filing_frequency', 20)->default('MONTHLY'); // MONTHLY, QUARTERLY
                $table->string('api_environment', 20)->default('SANDBOX'); // SANDBOX, PRODUCTION
                $table->unique(['company_id', 'branch_id']);
            });
        } else {
            $schema->table('gst_configurations', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('gst_configurations', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('gst_configurations', 'gst_registered')) {
                    $table->boolean('gst_registered')->default(true);
                }
                if (!$schema->hasColumn('gst_configurations', 'legal_business_name')) {
                    $table->string('legal_business_name')->nullable();
                }
                if (!$schema->hasColumn('gst_configurations', 'trade_name')) {
                    $table->string('trade_name')->nullable();
                }
                if (!$schema->hasColumn('gst_configurations', 'pan')) {
                    $table->string('pan', 10)->nullable()->index();
                }
                if (!$schema->hasColumn('gst_configurations', 'registered_state')) {
                    $table->string('registered_state', 100)->default('Maharashtra');
                }
                if (!$schema->hasColumn('gst_configurations', 'state_code')) {
                    $table->string('state_code', 2)->default('27')->index();
                }
                if (!$schema->hasColumn('gst_configurations', 'tax_registration_type')) {
                    $table->string('tax_registration_type', 30)->default('REGISTERED_REGULAR')->index();
                }
                if (!$schema->hasColumn('gst_configurations', 'is_composition')) {
                    $table->boolean('is_composition')->default(false);
                }
                if (!$schema->hasColumn('gst_configurations', 'default_place_of_supply')) {
                    $table->string('default_place_of_supply', 100)->default('Maharashtra');
                }
                if (!$schema->hasColumn('gst_configurations', 'einvoice_applicable')) {
                    $table->boolean('einvoice_applicable')->default(false);
                }
                if (!$schema->hasColumn('gst_configurations', 'einvoice_threshold')) {
                    $table->decimal('einvoice_threshold', 15, 2)->default(50000000.00);
                }
                if (!$schema->hasColumn('gst_configurations', 'eway_bill_applicable')) {
                    $table->boolean('eway_bill_applicable')->default(true);
                }
                if (!$schema->hasColumn('gst_configurations', 'eway_threshold')) {
                    $table->decimal('eway_threshold', 15, 2)->default(50000.00);
                }
                if (!$schema->hasColumn('gst_configurations', 'filing_frequency')) {
                    $table->string('filing_frequency', 20)->default('MONTHLY');
                }
                if (!$schema->hasColumn('gst_configurations', 'api_environment')) {
                    $table->string('api_environment', 20)->default('SANDBOX');
                }
            });
        }

        if (!$schema->hasTable('gst_rates')) {
            $schema->create('gst_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index(); // null for system defaults
                $table->decimal('rate', 5, 2)->index(); // 0, 0.25, 3, 5, 12, 18, 28
                $table->decimal('cess_rate', 5, 2)->default(0.00);
                $table->string('description')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_system_default')->default(false);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('hsn_sac_master')) {
            $schema->create('hsn_sac_master', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 20)->index();
                $table->string('type', 10)->default('HSN')->index(); // HSN or SAC
                $table->string('description');
                $table->decimal('default_gst_rate', 5, 2)->default(18.00);
                $table->decimal('default_cess_rate', 5, 2)->default(0.00);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->index(['code', 'type']);
            });
        }

        if (!$schema->hasTable('gstin_validations')) {
            $schema->create('gstin_validations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('gstin', 15)->index();
                $table->string('legal_name')->nullable();
                $table->string('trade_name')->nullable();
                $table->string('state_code', 2)->nullable();
                $table->string('state_name', 100)->nullable();
                $table->string('taxpayer_type', 40)->nullable();
                $table->string('registration_status', 30)->nullable();
                $table->boolean('is_format_valid')->default(false);
                $table->boolean('is_portal_verified')->default(false);
                $table->string('source', 40)->default('LOCAL_CHECKSUM'); // LOCAL_CHECKSUM, SANDBOX_SIMULATION, PORTAL_API
                $table->string('response_reference')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamps();

                $table->index(['gstin', 'is_portal_verified']);
            });
        }

        if (!$schema->hasTable('gst_document_snapshots')) {
            $schema->create('gst_document_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('document_type', 30)->index(); // INVOICE, PURCHASE, CREDIT_NOTE, DEBIT_NOTE
                $table->unsignedBigInteger('document_id')->index();
                $table->string('document_number', 50)->index();
                $table->date('document_date')->index();
                $table->string('seller_gstin', 15)->nullable()->index();
                $table->string('seller_state_code', 2)->nullable();
                $table->string('buyer_gstin', 15)->nullable()->index();
                $table->string('buyer_state_code', 2)->nullable();
                $table->string('place_of_supply', 100)->index();
                $table->string('supply_type', 20)->default('INTRA_STATE')->index(); // INTRA_STATE, INTER_STATE
                $table->string('gst_category', 30)->default('B2B')->index(); // B2B, B2C, B2CL, B2CS, EXP, SEZ, DEEMED_EXP, CDNR, CDNUR
                $table->boolean('is_reverse_charge')->default(false)->index();
                $table->decimal('taxable_amount', 15, 2)->default(0.00);
                $table->decimal('cgst_amount', 15, 2)->default(0.00);
                $table->decimal('sgst_amount', 15, 2)->default(0.00);
                $table->decimal('igst_amount', 15, 2)->default(0.00);
                $table->decimal('cess_amount', 15, 2)->default(0.00);
                $table->decimal('total_tax_amount', 15, 2)->default(0.00);
                $table->decimal('total_document_value', 15, 2)->default(0.00);
                $table->longText('lines_snapshot_json')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'document_type', 'document_id'], 'gst_doc_snaps_unique');
                $table->index(['company_id', 'document_date', 'gst_category'], 'gst_doc_snaps_date_cat_idx');
            });
        }

        if (!$schema->hasTable('e_invoices')) {
            $schema->create('e_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->string('irn', 64)->nullable()->index();
                $table->string('ack_no', 50)->nullable()->index();
                $table->string('ack_date', 30)->nullable();
                $table->longText('signed_invoice')->nullable();
                $table->longText('signed_qr_data')->nullable();
                $table->text('qr_code_url')->nullable();
                $table->string('status', 30)->default('NOT_REQUIRED')->index(); // NOT_REQUIRED, ELIGIBLE, VALIDATION_FAILED, READY, SUBMITTED, GENERATED, FAILED, CANCELLED
                $table->boolean('is_sandbox')->default(true)->index();
                $table->text('error_message')->nullable();
                $table->string('cancel_reason', 10)->nullable(); // 1-Duplicate, 2-Data entry mistake, 3-Order cancelled, 4-Others
                $table->text('cancel_remarks')->nullable();
                $table->timestamp('cancel_date')->nullable();
                $table->string('generated_by')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'invoice_id']);
            });
        }

        if (!$schema->hasTable('e_invoice_status_history')) {
            $schema->create('e_invoice_status_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('e_invoice_id')->index();
                $table->string('status', 30)->index();
                $table->text('remarks')->nullable();
                $table->string('created_by')->default('System');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('e_way_bills')) {
            $schema->create('e_way_bills', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->string('ewb_number', 30)->nullable()->index();
                $table->timestamp('ewb_date')->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->string('status', 30)->default('NOT_REQUIRED')->index(); // NOT_REQUIRED, ELIGIBLE, READY, SUBMITTED, GENERATED, FAILED, CANCELLED, EXPIRED
                $table->string('transport_mode', 20)->default('ROAD'); // ROAD, RAIL, AIR, SHIP
                $table->string('transporter_id', 20)->nullable()->index();
                $table->string('transporter_name')->nullable();
                $table->string('transport_doc_no', 50)->nullable();
                $table->date('transport_doc_date')->nullable();
                $table->string('vehicle_no', 30)->nullable();
                $table->string('vehicle_type', 20)->default('REGULAR'); // REGULAR, OVER_DIMENSIONAL
                $table->string('from_pincode', 10)->nullable();
                $table->string('to_pincode', 10)->nullable();
                $table->integer('distance_km')->default(0);
                $table->boolean('is_sandbox')->default(true)->index();
                $table->text('error_message')->nullable();
                $table->string('cancel_reason', 10)->nullable();
                $table->text('cancel_remarks')->nullable();
                $table->timestamp('cancel_date')->nullable();
                $table->string('generated_by')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'invoice_id']);
                $table->index(['company_id', 'ewb_number']);
            });
        }

        if (!$schema->hasTable('e_way_bill_status_history')) {
            $schema->create('e_way_bill_status_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('e_way_bill_id')->index();
                $table->string('status', 30)->index();
                $table->text('remarks')->nullable();
                $table->string('created_by')->default('System');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('gst_reconciliation')) {
            $schema->create('gst_reconciliation', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('financial_year', 20)->default('2026-27')->index();
                $table->string('period_month', 10)->index(); // 04, 05, etc.
                $table->string('return_type', 20)->default('GSTR2B')->index(); // GSTR2B, GSTR2A, GSTR1
                $table->string('status', 30)->default('IN_PROGRESS')->index();
                $table->integer('total_portal_records')->default(0);
                $table->integer('total_books_records')->default(0);
                $table->integer('matched_count')->default(0);
                $table->integer('partial_match_count')->default(0);
                $table->integer('mismatch_count')->default(0);
                $table->integer('books_only_count')->default(0);
                $table->integer('portal_only_count')->default(0);
                $table->string('imported_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'financial_year', 'period_month']);
            });
        }

        if (!$schema->hasTable('gst_reconciliation_items')) {
            $schema->create('gst_reconciliation_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('reconciliation_id')->index();
                $table->string('gstin', 15)->index();
                $table->string('party_name')->nullable();
                $table->string('invoice_number', 50)->index();
                $table->date('invoice_date')->index();
                $table->decimal('books_taxable', 15, 2)->default(0.00);
                $table->decimal('portal_taxable', 15, 2)->default(0.00);
                $table->decimal('books_tax', 15, 2)->default(0.00);
                $table->decimal('portal_tax', 15, 2)->default(0.00);
                $table->decimal('taxable_diff', 15, 2)->default(0.00);
                $table->decimal('tax_diff', 15, 2)->default(0.00);
                $table->decimal('cgst_diff', 15, 2)->default(0.00);
                $table->decimal('sgst_diff', 15, 2)->default(0.00);
                $table->decimal('igst_diff', 15, 2)->default(0.00);
                $table->decimal('cess_diff', 15, 2)->default(0.00);
                $table->string('match_status', 30)->default('MISMATCH')->index(); // MATCHED, PARTIAL_MATCH, MISMATCH, BOOKS_ONLY, PORTAL_ONLY, EXCLUDED
                $table->string('action_taken', 30)->default('NONE')->index(); // ACCEPT, REJECT, REVIEWED, EXCLUDE, NONE
                $table->text('action_notes')->nullable();
                $table->string('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'match_status']);
                $table->index(['reconciliation_id', 'gstin']);
            });
        }

        if (!$schema->hasTable('gst_filing_periods')) {
            $schema->create('gst_filing_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('financial_year', 20)->default('2026-27')->index();
                $table->string('period_name', 20)->index(); // M01..M12, Q1..Q4
                $table->string('return_type', 20)->default('GSTR1')->index(); // GSTR1, GSTR3B
                $table->string('status', 30)->default('DRAFT')->index(); // DRAFT, VALIDATED, READY_TO_FILE, FILED
                $table->longText('summary_data_json')->nullable();
                $table->date('filing_date')->nullable();
                $table->string('arn_number', 50)->nullable()->index();
                $table->string('filed_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'financial_year', 'period_name', 'return_type'], 'gst_filing_periods_unique');
            });
        }

        // ----------------------------------------------------
        // 17. CHUNK 9: BANKING, RECONCILIATION, CHEQUES & PAYMENT GATEWAY
        // ----------------------------------------------------
        if ($schema->hasTable('bank_accounts')) {
            $schema->table('bank_accounts', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('bank_accounts', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('bank_accounts', 'account_type')) {
                    $table->string('account_type', 30)->default('CURRENT')->index(); // CURRENT, SAVINGS, CASH_CREDIT, OD, OTHER
                }
                if (!$schema->hasColumn('bank_accounts', 'ifsc')) {
                    $table->string('ifsc', 11)->nullable();
                }
                if (!$schema->hasColumn('bank_accounts', 'opening_balance')) {
                    $table->decimal('opening_balance', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('bank_accounts', 'opening_balance_date')) {
                    $table->date('opening_balance_date')->nullable();
                }
                if (!$schema->hasColumn('bank_accounts', 'ledger_account_id')) {
                    $table->unsignedBigInteger('ledger_account_id')->nullable()->index();
                }
                if (!$schema->hasColumn('bank_accounts', 'currency')) {
                    $table->string('currency', 3)->default('INR');
                }
                if (!$schema->hasColumn('bank_accounts', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index();
                }
                if (!$schema->hasColumn('bank_accounts', 'created_by')) {
                    $table->string('created_by')->default('System');
                }
            });
        }

        if ($schema->hasTable('bank_transactions')) {
            $schema->table('bank_transactions', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('bank_transactions', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('bank_transactions', 'value_date')) {
                    $table->date('value_date')->nullable();
                }
                if (!$schema->hasColumn('bank_transactions', 'transaction_type')) {
                    $table->string('transaction_type', 40)->default('OTHER')->index(); // DEPOSIT, WITHDRAWAL, TRANSFER, BANK_CHARGE, INTEREST, PAYMENT_RECEIVED, PAYMENT_MADE, CHEQUE_DEPOSIT, CHEQUE_PAYMENT, OTHER
                }
                if (!$schema->hasColumn('bank_transactions', 'reference_number')) {
                    $table->string('reference_number', 100)->nullable()->index();
                }
                if (!$schema->hasColumn('bank_transactions', 'debit_credit')) {
                    $table->string('debit_credit', 10)->default('CREDIT')->index(); // DEBIT, CREDIT
                }
                if (!$schema->hasColumn('bank_transactions', 'source')) {
                    $table->string('source', 40)->default('MANUAL')->index(); // CUSTOMER_PAYMENT, SUPPLIER_PAYMENT, EXPENSE, SALE, PURCHASE, BANK_IMPORT, MANUAL, TRANSFER, CHEQUE, GATEWAY_SETTLEMENT
                }
                if (!$schema->hasColumn('bank_transactions', 'source_id')) {
                    $table->string('source_id', 50)->nullable()->index();
                }
                if (!$schema->hasColumn('bank_transactions', 'reconciliation_status')) {
                    $table->string('reconciliation_status', 30)->default('UNRECONCILED')->index(); // UNRECONCILED, MATCHED, RECONCILED, EXCLUDED
                }
                if (!$schema->hasColumn('bank_transactions', 'reconciled_at')) {
                    $table->timestamp('reconciled_at')->nullable();
                }
                if (!$schema->hasColumn('bank_transactions', 'reconciled_by')) {
                    $table->string('reconciled_by')->nullable();
                }
            });
        }

        if (!$schema->hasTable('bank_transfers')) {
            $schema->create('bank_transfers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('transfer_number', 50)->index();
                $table->string('from_type', 20)->default('BANK'); // BANK, CASH
                $table->unsignedBigInteger('from_account_id')->index();
                $table->string('to_type', 20)->default('BANK'); // BANK, CASH
                $table->unsignedBigInteger('to_account_id')->index();
                $table->decimal('amount', 15, 2);
                $table->date('transfer_date')->index();
                $table->string('reference_number', 100)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->string('created_by')->default('System');
                $table->timestamps();

                $table->unique(['company_id', 'transfer_number']);
            });
        }

        if (!$schema->hasTable('bank_statement_imports')) {
            $schema->create('bank_statement_imports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->string('file_name');
                $table->string('file_type', 20)->default('CSV'); // CSV, EXCEL
                $table->integer('total_rows')->default(0);
                $table->integer('valid_rows')->default(0);
                $table->integer('invalid_rows')->default(0);
                $table->integer('duplicate_rows')->default(0);
                $table->integer('imported_rows')->default(0);
                $table->string('status', 30)->default('PREVIEWED')->index(); // PREVIEWED, IMPORTED, FAILED
                $table->longText('column_mapping_json')->nullable();
                $table->string('imported_by')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('bank_statement_rows')) {
            $schema->create('bank_statement_rows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('import_id')->index();
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->integer('row_index')->default(0);
                $table->date('transaction_date')->index();
                $table->date('value_date')->nullable();
                $table->string('description');
                $table->string('reference_number', 100)->nullable()->index();
                $table->decimal('debit_amount', 15, 2)->default(0.00);
                $table->decimal('credit_amount', 15, 2)->default(0.00);
                $table->decimal('amount', 15, 2)->default(0.00);
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->boolean('is_duplicate')->default(false)->index();
                $table->boolean('is_valid')->default(true);
                $table->text('error_message')->nullable();
                $table->unsignedBigInteger('bank_transaction_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if ($schema->hasTable('bank_reconciliations')) {
            $schema->table('bank_reconciliations', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('bank_reconciliations', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('bank_reconciliations', 'statement_start_date')) {
                    $table->date('statement_start_date')->nullable();
                }
                if (!$schema->hasColumn('bank_reconciliations', 'statement_end_date')) {
                    $table->date('statement_end_date')->nullable();
                }
                if (!$schema->hasColumn('bank_reconciliations', 'statement_opening_balance')) {
                    $table->decimal('statement_opening_balance', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('bank_reconciliations', 'statement_closing_balance')) {
                    $table->decimal('statement_closing_balance', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('bank_reconciliations', 'book_opening_balance')) {
                    $table->decimal('book_opening_balance', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('bank_reconciliations', 'book_closing_balance')) {
                    $table->decimal('book_closing_balance', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('bank_reconciliations', 'reconciled_balance')) {
                    $table->decimal('reconciled_balance', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('bank_reconciliations', 'difference_amount')) {
                    $table->decimal('difference_amount', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('bank_reconciliations', 'uncleared_deposits')) {
                    $table->decimal('uncleared_deposits', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('bank_reconciliations', 'unpresented_cheques')) {
                    $table->decimal('unpresented_cheques', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('bank_reconciliations', 'status')) {
                    $table->string('status', 30)->default('OPEN')->index(); // OPEN, IN_PROGRESS, COMPLETED
                }
                if (!$schema->hasColumn('bank_reconciliations', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
                if (!$schema->hasColumn('bank_reconciliations', 'completed_by')) {
                    $table->string('completed_by')->nullable();
                }
                if (!$schema->hasColumn('bank_reconciliations', 'reopened_at')) {
                    $table->timestamp('reopened_at')->nullable();
                }
                if (!$schema->hasColumn('bank_reconciliations', 'reopened_by')) {
                    $table->string('reopened_by')->nullable();
                }
                if (!$schema->hasColumn('bank_reconciliations', 'reopen_reason')) {
                    $table->text('reopen_reason')->nullable();
                }
            });
        }

        if (!$schema->hasTable('bank_reconciliation_matches')) {
            $schema->create('bank_reconciliation_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('reconciliation_id')->index();
                $table->unsignedBigInteger('bank_transaction_id')->nullable()->index();
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->unsignedBigInteger('journal_line_id')->nullable()->index();
                $table->unsignedBigInteger('payment_id')->nullable()->index();
                $table->string('match_type', 30)->default('ONE_TO_ONE')->index(); // ONE_TO_ONE, ONE_TO_MANY, MANY_TO_ONE, PARTIAL
                $table->string('confidence_score', 20)->default('HIGH')->index(); // HIGH, MEDIUM, LOW
                $table->decimal('matched_amount', 15, 2)->default(0.00);
                $table->decimal('difference_amount', 15, 2)->default(0.00);
                $table->string('status', 30)->default('MATCHED')->index(); // MATCHED, CONFIRMED, UNMATCHED, EXCLUDED
                $table->text('notes')->nullable();
                $table->string('matched_by')->nullable();
                $table->timestamps();
            });
        }

        if ($schema->hasTable('cheques')) {
            $schema->table('cheques', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('cheques', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (!$schema->hasColumn('cheques', 'cheque_type')) {
                    $table->string('cheque_type', 20)->default('RECEIVED')->index(); // RECEIVED, ISSUED
                }
                if (!$schema->hasColumn('cheques', 'party_type')) {
                    $table->string('party_type', 20)->default('CUSTOMER')->index(); // CUSTOMER, SUPPLIER, OTHER
                }
                if (!$schema->hasColumn('cheques', 'party_id')) {
                    $table->unsignedBigInteger('party_id')->nullable()->index();
                }
                if (!$schema->hasColumn('cheques', 'party_name')) {
                    $table->string('party_name')->nullable();
                }
                if (!$schema->hasColumn('cheques', 'bank_name')) {
                    $table->string('bank_name')->nullable();
                }
                if (!$schema->hasColumn('cheques', 'bank_account_id')) {
                    $table->unsignedBigInteger('bank_account_id')->nullable()->index();
                }
                if (!$schema->hasColumn('cheques', 'deposit_bank_id')) {
                    $table->unsignedBigInteger('deposit_bank_id')->nullable()->index();
                }
                if (!$schema->hasColumn('cheques', 'received_date')) {
                    $table->date('received_date')->nullable();
                }
                if (!$schema->hasColumn('cheques', 'issue_date')) {
                    $table->date('issue_date')->nullable();
                }
                if (!$schema->hasColumn('cheques', 'deposit_date')) {
                    $table->date('deposit_date')->nullable();
                }
                if (!$schema->hasColumn('cheques', 'clearance_date')) {
                    $table->date('clearance_date')->nullable();
                }
                if (!$schema->hasColumn('cheques', 'bounce_date')) {
                    $table->date('bounce_date')->nullable();
                }
                if (!$schema->hasColumn('cheques', 'bounce_reason')) {
                    $table->string('bounce_reason')->nullable();
                }
                if (!$schema->hasColumn('cheques', 'bounce_charges')) {
                    $table->decimal('bounce_charges', 15, 2)->default(0.00);
                }
                if (!$schema->hasColumn('cheques', 'reference_number')) {
                    $table->string('reference_number', 100)->nullable();
                }
                if (!$schema->hasColumn('cheques', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!$schema->hasColumn('cheques', 'payment_id')) {
                    $table->unsignedBigInteger('payment_id')->nullable()->index();
                }
                if (!$schema->hasColumn('cheques', 'journal_entry_id')) {
                    $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                }
                if (!$schema->hasColumn('cheques', 'created_by')) {
                    $table->string('created_by')->default('System');
                }
            });
        }

        if (!$schema->hasTable('cheque_events')) {
            $schema->create('cheque_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('cheque_id')->index();
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30)->index();
                $table->date('event_date')->index();
                $table->text('notes')->nullable();
                $table->string('created_by')->default('System');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('payment_links')) {
            $schema->create('payment_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('link_id', 60)->index(); // plink_xxxx
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->decimal('amount', 15, 2);
                $table->decimal('amount_paid', 15, 2)->default(0.00);
                $table->string('currency', 3)->default('INR');
                $table->string('provider', 30)->default('SIMULATION')->index(); // RAZORPAY, CASHFREE, STRIPE, SIMULATION
                $table->string('external_reference', 100)->nullable()->index();
                $table->string('url')->nullable();
                $table->string('status', 30)->default('CREATED')->index(); // CREATED, SENT, OPENED, PAID, FAILED, EXPIRED, CANCELLED
                $table->boolean('allow_partial')->default(false);
                $table->timestamp('expiry_date')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'link_id']);
            });
        }

        if (!$schema->hasTable('payment_gateway_transactions')) {
            $schema->create('payment_gateway_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('payment_link_id')->nullable()->index();
                $table->string('provider', 30)->default('SIMULATION')->index();
                $table->string('gateway_order_id', 100)->nullable()->index();
                $table->string('gateway_payment_id', 100)->nullable()->index();
                $table->string('gateway_signature', 255)->nullable();
                $table->decimal('amount', 15, 2);
                $table->decimal('fee_amount', 15, 2)->default(0.00);
                $table->decimal('tax_amount', 15, 2)->default(0.00);
                $table->decimal('net_amount', 15, 2)->default(0.00);
                $table->string('currency', 3)->default('INR');
                $table->string('status', 30)->default('SUCCESS')->index(); // PENDING, PROCESSING, SUCCESS, FAILED, REFUNDED
                $table->boolean('is_sandbox')->default(true)->index();
                $table->string('webhook_event_id', 100)->nullable()->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('payment_gateway_events')) {
            $schema->create('payment_gateway_events', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 30)->index();
                $table->string('event_id', 100)->unique();
                $table->string('event_type', 50)->index();
                $table->longText('payload_json')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->string('status', 30)->default('PROCESSED')->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('payment_settlements')) {
            $schema->create('payment_settlements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('settlement_id', 100)->index();
                $table->string('provider', 30)->default('SIMULATION')->index();
                $table->date('settlement_date')->index();
                $table->decimal('gross_amount', 15, 2);
                $table->decimal('fee_amount', 15, 2)->default(0.00);
                $table->decimal('tax_amount', 15, 2)->default(0.00);
                $table->decimal('net_amount', 15, 2);
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->unsignedBigInteger('bank_transaction_id')->nullable()->index();
                $table->string('status', 30)->default('SETTLED')->index(); // PENDING, SETTLED, FAILED
                $table->timestamps();

                $table->unique(['company_id', 'settlement_id']);
            });
        }

        // ----------------------------------------------------
        // 18. CHUNK 10: DOCUMENT ENGINE, TEMPLATES & SHARING
        // ----------------------------------------------------
        if (!$schema->hasTable('document_number_settings')) {
            $schema->create('document_number_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('financial_year', 15)->default('2026-27')->index();
                $table->string('document_type', 50)->index();
                $table->string('prefix', 30)->default('DOC-');
                $table->string('suffix', 30)->nullable();
                $table->unsignedBigInteger('starting_number')->default(1);
                $table->unsignedBigInteger('current_number')->default(0);
                $table->integer('padding_length')->default(6);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'branch_id', 'financial_year', 'document_type'], 'doc_num_lookup_idx');
            });
        }

        if (!$schema->hasTable('document_templates')) {
            $schema->create('document_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('document_type', 50)->index(); // SALES_INVOICE, QUOTATION, PURCHASE_INVOICE, PAYMENT_RECEIPT, CUSTOMER_STATEMENT, etc.
                $table->string('template_key', 50)->index(); // classic_gst, modern_gst, minimal_gst, thermal_80mm, etc.
                $table->string('template_name', 100);
                $table->string('paper_size', 20)->default('A4'); // A4, A5, LETTER, THERMAL_80MM
                $table->string('orientation', 20)->default('PORTRAIT'); // PORTRAIT, LANDSCAPE
                $table->string('brand_color', 20)->default('#1e40af');
                $table->string('accent_color', 20)->default('#3b82f6');
                $table->string('font_family', 50)->default('Inter');
                $table->string('logo_position', 20)->default('LEFT'); // LEFT, CENTER, RIGHT, HIDDEN
                $table->boolean('watermark_enabled')->default(false);
                $table->string('watermark_text')->nullable();
                $table->decimal('watermark_opacity', 3, 2)->default(0.08);
                $table->boolean('show_bank_details')->default(true);
                $table->boolean('show_upi_qr')->default(true);
                $table->boolean('show_signature')->default(true);
                $table->boolean('show_stamp')->default(false);
                $table->boolean('show_hsn_summary')->default(true);
                $table->boolean('show_tax_breakdown')->default(true);
                $table->boolean('show_amount_in_words')->default(true);
                $table->boolean('show_terms')->default(true);
                $table->text('default_terms')->nullable();
                $table->text('default_notes')->nullable();
                $table->text('custom_css')->nullable();
                $table->longText('config_json')->nullable();
                $table->boolean('is_default')->default(false)->index();
                $table->boolean('is_system')->default(false);
                $table->integer('version')->default(1);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!$schema->hasTable('document_template_versions')) {
            $schema->create('document_template_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('template_id')->index();
                $table->integer('version_number');
                $table->longText('config_json')->nullable();
                $table->string('created_by')->default('System');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('generated_documents')) {
            $schema->create('generated_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('document_type', 50)->index();
                $table->string('source_type', 50)->index();
                $table->string('source_id', 100)->index();
                $table->string('document_number', 100)->index();
                $table->date('document_date')->index();
                $table->unsignedBigInteger('template_id')->nullable()->index();
                $table->integer('version')->default(1);
                $table->string('file_name');
                $table->string('file_path')->nullable();
                $table->string('mime_type', 50)->default('application/pdf');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('checksum_hash', 64)->nullable(); // SHA-256
                $table->longText('snapshot_json')->nullable(); // Immutable DocumentModel Snapshot
                $table->boolean('is_frozen')->default(false);
                $table->string('generated_by')->default('System');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('document_shares')) {
            $schema->create('document_shares', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('generated_document_id')->index();
                $table->string('share_token', 64)->unique();
                $table->string('channel', 30)->default('LINK')->index(); // LINK, EMAIL, WHATSAPP, DOWNLOAD
                $table->string('recipient_name')->nullable();
                $table->string('recipient_contact')->nullable();
                $table->boolean('allow_download')->default(true);
                $table->boolean('allow_print')->default(true);
                $table->string('password_hash')->nullable();
                $table->unsignedInteger('access_count')->default(0);
                $table->unsignedInteger('max_access_count')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->boolean('is_revoked')->default(false)->index();
                $table->timestamp('revoked_at')->nullable();
                $table->string('created_by')->default('System');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('document_audit_logs')) {
            $schema->create('document_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('generated_document_id')->index();
                $table->string('action', 50)->index(); // GENERATED, VIEWED, DOWNLOADED, PRINTED, SHARED, EMAILED, WHATSAPP_SENT, LINK_OPENED, REVOKED
                $table->string('user_name')->default('System');
                $table->string('ip_address', 45)->default('127.0.0.1');
                $table->text('user_agent')->nullable();
                $table->longText('metadata_json')->nullable();
                $table->timestamps();
            });
        }

        // ==========================================
        // 19. CHUNK 11: REPORTING ENGINE & ANALYTICS
        // ==========================================

        if (!$schema->hasTable('report_saved_views')) {
            $schema->create('report_saved_views', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('report_key', 60)->index();
                $table->string('name', 150);
                $table->longText('filters_json')->nullable();
                $table->longText('columns_json')->nullable();
                $table->longText('sort_json')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_favorite')->default(false);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('report_audit_logs')) {
            $schema->create('report_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 100)->default('System');
                $table->string('report_key', 60)->index();
                $table->string('action', 50)->index(); // GENERATED, EXPORTED_CSV, EXPORTED_EXCEL, EXPORTED_PDF, PRINTED, VIEW_SAVED
                $table->longText('filters_json')->nullable();
                $table->string('ip_address', 45)->default('127.0.0.1');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('report_snapshots')) {
            $schema->create('report_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('report_key', 60)->index();
                $table->string('period_type', 30)->default('FINANCIAL_YEAR'); // MONTH, QUARTER, FINANCIAL_YEAR, CUSTOM
                $table->string('period_label', 100);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->longText('dataset_json');
                $table->boolean('is_locked')->default(false);
                $table->string('locked_by', 100)->nullable();
                $table->timestamps();
            });
        }

        // ==========================================
        // 20. CHUNK 12: PRICE LISTS, MULTI-WAREHOUSE & BRANCH MANAGEMENT
        // ==========================================

        // 20.1 Company Feature Toggles
        $companyCols = [
            'branch_management_enabled' => 'boolean',
            'multi_warehouse_enabled' => 'boolean',
            'allow_negative_stock' => 'boolean',
        ];
        foreach ($companyCols as $col => $type) {
            if (!$schema->hasColumn('companies', $col)) {
                $schema->table('companies', function (Blueprint $table) use ($col) {
                    $table->boolean($col)->default(false);
                });
            }
        }

        // 20.2 Branch Entity Enhancements
        $branchExtraCols = [
            'legal_name' => 'string',
            'pan' => 'string',
            'is_main_branch' => 'boolean',
        ];
        foreach ($branchExtraCols as $col => $type) {
            if (!$schema->hasColumn('branches', $col)) {
                $schema->table('branches', function (Blueprint $table) use ($col, $type) {
                    if ($type === 'boolean') {
                        $table->boolean($col)->default(false);
                    } else {
                        $table->string($col, 100)->nullable();
                    }
                });
            }
        }

        // 20.3 Branch Users (User to Branch mapping)
        if (!$schema->hasTable('branch_users')) {
            $schema->create('branch_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->boolean('is_default')->default(false);
                $table->boolean('can_switch')->default(true);
                $table->timestamps();
                $table->unique(['company_id', 'branch_id', 'user_id']);
            });
        }

        // 20.4 Branch Settings & Numbering Series
        if (!$schema->hasTable('branch_settings')) {
            $schema->create('branch_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->unique()->index();
                $table->string('invoice_prefix', 30)->nullable();
                $table->string('quotation_prefix', 30)->nullable();
                $table->string('purchase_prefix', 30)->nullable();
                $table->string('receipt_prefix', 30)->nullable();
                $table->unsignedBigInteger('default_warehouse_id')->nullable();
                $table->string('logo_url')->nullable();
                $table->text('terms_conditions')->nullable();
                $table->unsignedBigInteger('bank_account_id')->nullable();
                $table->longText('settings_json')->nullable();
                $table->timestamps();
            });
        }

        // 20.5 Warehouse Enhancements
        $warehouseExtraCols = [
            'manager_name' => 'string',
            'phone' => 'string',
            'state' => 'string',
            'pincode' => 'string',
            'is_default' => 'boolean',
        ];
        foreach ($warehouseExtraCols as $col => $type) {
            if (!$schema->hasColumn('warehouses', $col)) {
                $schema->table('warehouses', function (Blueprint $table) use ($col, $type) {
                    if ($type === 'boolean') {
                        $table->boolean($col)->default(false);
                    } else {
                        $table->string($col, 100)->nullable();
                    }
                });
            }
        }

        // 20.6 Stock Balances Enhancements (Reserved & In-Transit)
        $stockExtraCols = [
            'reserved_quantity' => 'decimal',
            'in_transit_quantity' => 'decimal',
        ];
        foreach ($stockExtraCols as $col => $type) {
            if (!$schema->hasColumn('stock_balances', $col)) {
                $schema->table('stock_balances', function (Blueprint $table) use ($col) {
                    $table->decimal($col, 15, 3)->default(0.000);
                });
            }
        }

        // 20.7 Stock Transfers Enhancements
        $transferExtraCols = [
            'from_branch_id' => 'unsignedBigInteger',
            'to_branch_id' => 'unsignedBigInteger',
            'created_by' => 'string',
            'approved_by' => 'string',
            'dispatched_by' => 'string',
            'received_by' => 'string',
            'cancelled_by' => 'string',
        ];
        foreach ($transferExtraCols as $col => $type) {
            if (!$schema->hasColumn('stock_transfers', $col)) {
                $schema->table('stock_transfers', function (Blueprint $table) use ($col, $type) {
                    if ($type === 'unsignedBigInteger') {
                        $table->unsignedBigInteger($col)->nullable()->index();
                    } else {
                        $table->string($col, 100)->nullable();
                    }
                });
            }
        }

        // 20.8 Stock Transfer Items Enhancements
        $transferItemExtraCols = [
            'quantity_sent' => 'decimal',
            'quantity_received' => 'decimal',
            'unit' => 'string',
            'notes' => 'text',
        ];
        foreach ($transferItemExtraCols as $col => $type) {
            if (!$schema->hasColumn('stock_transfer_items', $col)) {
                $schema->table('stock_transfer_items', function (Blueprint $table) use ($col, $type) {
                    if ($type === 'decimal') {
                        $table->decimal($col, 15, 3)->default(0.000);
                    } elseif ($type === 'text') {
                        $table->text($col)->nullable();
                    } else {
                        $table->string($col, 30)->nullable();
                    }
                });
            }
        }

        // 20.9 Price Lists (Retail, Wholesale, Distributor, Branch/Customer-specific)
        if (!$schema->hasTable('price_lists')) {
            $schema->create('price_lists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name', 120);
                $table->string('code', 50)->nullable()->index();
                $table->text('description')->nullable();
                $table->string('currency', 3)->default('INR');
                $table->string('price_type', 30)->default('FIXED'); // FIXED, PERCENTAGE_ADJUSTMENT
                $table->decimal('adjustment_value', 8, 2)->default(0.00); // e.g. +5% or -10%
                $table->string('tax_mode', 30)->default('TAX_EXCLUSIVE'); // TAX_EXCLUSIVE, TAX_INCLUSIVE
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 20.10 Price List Items (Product Specific & Quantity Tiers)
        if (!$schema->hasTable('price_list_items')) {
            $schema->create('price_list_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('price_list_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('price', 15, 2)->default(0.00);
                $table->decimal('minimum_quantity', 15, 3)->default(1.000);
                $table->decimal('maximum_quantity', 15, 3)->nullable();
                $table->decimal('discount_rate', 5, 2)->default(0.00);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->timestamps();
                $table->index(['price_list_id', 'product_id']);
            });
        }

        // 20.11 Customer-Specific Price Overrides (Customer + Product -> Price)
        if (!$schema->hasTable('customer_price_overrides')) {
            $schema->create('customer_price_overrides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('price', 15, 2);
                $table->decimal('discount_rate', 5, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'customer_id', 'product_id'], 'cust_price_overrides_uniq');
            });
        }

        // 20.12 Price Histories (Audit Trail for Price Changes)
        if (!$schema->hasTable('price_histories')) {
            $schema->create('price_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('price_list_id')->nullable()->index();
                $table->decimal('old_price', 15, 2);
                $table->decimal('new_price', 15, 2);
                $table->unsignedBigInteger('changed_by_user_id')->nullable()->index();
                $table->string('changed_by_name', 100)->default('System');
                $table->string('reason')->nullable();
                $table->timestamps();
            });
        }

        // 20.13 Customer & Customer Group Price List Bindings
        if (!$schema->hasColumn('customers', 'price_list_id')) {
            $schema->table('customers', function (Blueprint $table) {
                $table->unsignedBigInteger('price_list_id')->nullable()->index();
            });
        }
        if (!$schema->hasColumn('customer_groups', 'default_price_list_id')) {
            $schema->table('customer_groups', function (Blueprint $table) {
                $table->unsignedBigInteger('default_price_list_id')->nullable()->index();
            });
        }

        // ==========================================
        // 21. CHUNK 13: NOTIFICATIONS, REMINDERS & RECURRING TRANSACTIONS
        // ==========================================

        // 21.1 Notification Preferences
        if (!$schema->hasTable('notification_preferences')) {
            $schema->create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('category', 60)->index(); // PAYMENTS, SALES, PURCHASES, INVENTORY, ACCOUNTING, GST, BANKING, TRANSFERS, SYSTEM
                $table->boolean('in_app_enabled')->default(true);
                $table->boolean('email_enabled')->default(false);
                $table->boolean('whatsapp_enabled')->default(false);
                $table->boolean('sms_enabled')->default(false);
                $table->timestamps();
                $table->unique(['company_id', 'user_id', 'category']);
            });
        }

        // 21.2 Notification Templates
        if (!$schema->hasTable('notification_templates')) {
            $schema->create('notification_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('template_key', 80)->index();
                $table->string('name', 120);
                $table->string('channel', 30)->default('EMAIL'); // IN_APP, EMAIL, WHATSAPP, SMS
                $table->string('category', 60)->default('PAYMENTS');
                $table->string('subject', 200)->nullable();
                $table->longText('body_template');
                $table->longText('variables_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 21.3 Notification Rules (Configurable Reminder & Alert Triggers)
        if (!$schema->hasTable('notification_rules')) {
            $schema->create('notification_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('event_type', 80)->index(); // INVOICE_DUE_SOON, INVOICE_DUE_TODAY, INVOICE_OVERDUE, LOW_STOCK, BATCH_EXPIRY
                $table->string('rule_name', 120);
                $table->integer('days_offset')->default(0); // -3 (3 days before), 0 (on day), 7 (7 days overdue)
                $table->string('channel_priority', 100)->default('WHATSAPP,EMAIL'); // CSV priority list
                $table->boolean('is_active')->default(true);
                $table->longText('config_json')->nullable();
                $table->timestamps();
            });
        }

        // 21.4 Notification Logs & Delivery Tracking
        if (!$schema->hasTable('notification_logs')) {
            $schema->create('notification_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('event_type', 80)->index();
                $table->string('entity_type', 60)->index(); // INVOICE, PAYMENT, STOCK, TRANSFER, RECURRING
                $table->unsignedBigInteger('entity_id')->index();
                $table->string('channel', 30)->index(); // IN_APP, EMAIL, WHATSAPP, SMS
                $table->string('recipient', 191)->nullable()->index();
                $table->unsignedBigInteger('template_id')->nullable()->index();
                $table->string('status', 30)->default('PENDING')->index(); // PENDING, PROCESSING, SENT, DELIVERED, FAILED, CANCELLED
                $table->string('provider_message_id', 100)->nullable()->index();
                $table->integer('attempt_count')->default(0);
                $table->text('last_error')->nullable();
                $table->string('idempotency_key', 191)->nullable()->unique();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'entity_type', 'entity_id']);
            });
        }

        // 21.5 Business Events Ledger
        if (!$schema->hasTable('notification_events')) {
            $schema->create('notification_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('event_type', 80)->index();
                $table->string('entity_type', 60)->index();
                $table->unsignedBigInteger('entity_id')->index();
                $table->timestamp('occurred_at');
                $table->longText('payload_json')->nullable();
                $table->string('status', 30)->default('PENDING'); // PENDING, PROCESSED, FAILED
                $table->timestamps();
            });
        }

        // 21.6 In-App Notifications (Notification Center)
        if (!$schema->hasTable('in_app_notifications')) {
            $schema->create('in_app_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index(); // Nullable means all users of company/branch
                $table->string('category', 60)->default('SYSTEM')->index();
                $table->string('priority', 20)->default('NORMAL')->index(); // LOW, NORMAL, HIGH, CRITICAL
                $table->string('title', 150);
                $table->text('message');
                $table->string('entity_type', 60)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->boolean('is_read')->default(false)->index();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'is_read']);
            });
        }

        // 21.7 Recurring Transactions (Template Root)
        if (!$schema->hasTable('recurring_transactions')) {
            $schema->create('recurring_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('type', 40)->index(); // SALES_INVOICE, PURCHASE_EXPENSE, PAYMENT, JOURNAL_ENTRY
                $table->string('name', 150);
                $table->string('frequency', 30)->default('MONTHLY'); // DAILY, WEEKLY, MONTHLY, QUARTERLY, HALF_YEARLY, YEARLY, CUSTOM
                $table->integer('interval_count')->default(1);
                $table->string('month_end_policy', 30)->default('LAST_VALID_DAY'); // LAST_VALID_DAY, LAST_DAY_OF_MONTH
                $table->string('missed_schedule_policy', 30)->default('GENERATE_MISSED'); // GENERATE_MISSED, GENERATE_NEXT_ONLY
                $table->string('pricing_policy', 30)->default('PRESERVE_TEMPLATE_PRICE'); // PRESERVE_TEMPLATE_PRICE, USE_CURRENT_PRICE
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->date('next_run_at')->index();
                $table->date('last_run_at')->nullable();
                $table->integer('total_occurrences')->nullable();
                $table->integer('remaining_occurrences')->nullable();
                $table->string('status', 30)->default('ACTIVE')->index(); // ACTIVE, PAUSED, COMPLETED, CANCELLED, NEEDS_ATTENTION
                $table->longText('template_payload_json');
                $table->boolean('auto_post')->default(true);
                $table->boolean('auto_send')->default(false);
                $table->string('created_by', 100)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'status', 'next_run_at']);
            });
        }

        // 21.8 Recurring Transaction Execution Runs
        if (!$schema->hasTable('recurring_transaction_runs')) {
            $schema->create('recurring_transaction_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('recurring_transaction_id')->index();
                $table->date('occurrence_date');
                $table->timestamp('scheduled_at');
                $table->timestamp('executed_at')->nullable();
                $table->string('status', 30)->default('SUCCESS'); // SUCCESS, FAILED, SKIPPED
                $table->string('generated_entity_type', 60)->nullable();
                $table->unsignedBigInteger('generated_entity_id')->nullable();
                $table->text('error_message')->nullable();
                $table->integer('attempt_count')->default(1);
                $table->timestamps();
                $table->unique(['recurring_transaction_id', 'occurrence_date'], 'rec_occ_uniq');
            });
        }

        // 21.9 Recurring Transaction Audit Trail
        if (!$schema->hasTable('recurring_transaction_histories')) {
            $schema->create('recurring_transaction_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('recurring_transaction_id')->index();
                $table->string('action', 50); // CREATED, PAUSED, RESUMED, EDITED, CANCELLED, GENERATED, FAILED, RETRIED
                $table->unsignedBigInteger('performed_by_user_id')->nullable();
                $table->string('performed_by_name', 100)->default('System');
                $table->longText('details_json')->nullable();
                $table->timestamps();
            });
        }

        // 21.10 Centralized Scheduler Jobs & Distributed Locks
        if (!$schema->hasTable('scheduler_jobs')) {
            $schema->create('scheduler_jobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('job_key', 100)->index();
                $table->string('job_type', 60)->index(); // DAILY_REMINDERS, RECURRING_TRANSACTIONS, STOCK_ALERTS, BATCH_EXPIRY
                $table->string('status', 30)->default('PENDING')->index(); // PENDING, PROCESSING, COMPLETED, FAILED
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->integer('attempt_count')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('locked_until')->nullable()->index();
                $table->string('locked_by', 100)->nullable();
                $table->timestamps();
            });
        }

        // 21.11 Business Communication Settings
        if (!$schema->hasTable('business_communication_settings')) {
            $schema->create('business_communication_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->unique()->index();
                $table->string('timezone', 50)->default('Asia/Kolkata');
                $table->string('preferred_send_time', 10)->default('10:00');
                $table->string('quiet_hours_start', 10)->default('21:00');
                $table->string('quiet_hours_end', 10)->default('09:00');
                $table->boolean('email_configured')->default(false);
                $table->boolean('whatsapp_configured')->default(false);
                $table->boolean('sms_configured')->default(false);
                $table->longText('settings_json')->nullable();
                $table->timestamps();
            });
        }

        // 21.12 Customer Communication Additions
        $customerCommCols = [
            'reminder_enabled' => 'boolean',
            'preferred_channel' => 'string',
            'whatsapp_number' => 'string',
        ];
        foreach ($customerCommCols as $col => $type) {
            if (!$schema->hasColumn('customers', $col)) {
                $schema->table('customers', function (Blueprint $table) use ($col, $type) {
                    if ($type === 'boolean') {
                        $table->boolean($col)->default(true);
                    } else {
                        $table->string($col, 50)->nullable();
                    }
                });
            }
        }

        // ==========================================
        // 22. CHUNK 14: ADVANCED PAYMENTS, BANKING, CHEQUES & PAYMENT GATEWAYS
        // ==========================================

        // 22.1 Payments Enhancements (Unallocated Amount, Receipt Number, Bank Account)
        if ($schema->hasTable('payments')) {
            if (!$schema->hasColumn('payments', 'unallocated_amount')) {
                $schema->table('payments', function (Blueprint $table) {
                    $table->decimal('unallocated_amount', 15, 2)->default(0.00)->after('amount');
                });
            }
            if (!$schema->hasColumn('payments', 'receipt_number')) {
                $schema->table('payments', function (Blueprint $table) {
                    $table->string('receipt_number', 100)->nullable()->index()->after('payment_number');
                });
            }
            if (!$schema->hasColumn('payments', 'bank_account_id')) {
                $schema->table('payments', function (Blueprint $table) {
                    $table->unsignedBigInteger('bank_account_id')->nullable()->index();
                });
            }
        }

        // 22.2 Payment Refunds
        if (!$schema->hasTable('payment_refunds')) {
            $schema->create('payment_refunds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('payment_id')->index();
                $table->string('refund_number', 100)->unique();
                $table->date('refund_date');
                $table->decimal('amount', 15, 2);
                $table->string('reason', 255)->nullable();
                $table->string('payment_mode', 40)->default('BANK_TRANSFER');
                $table->unsignedBigInteger('bank_account_id')->nullable()->index();
                $table->string('reference_no', 100)->nullable();
                $table->string('status', 30)->default('COMPLETED')->index(); // REQUESTED, PROCESSING, COMPLETED, FAILED, CANCELLED
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        } else {
            if (!$schema->hasColumn('payment_refunds', 'payment_mode')) {
                $schema->table('payment_refunds', function (Blueprint $table) {
                    $table->string('payment_mode', 40)->default('BANK_TRANSFER');
                });
            }
            if (!$schema->hasColumn('payment_refunds', 'bank_account_id')) {
                $schema->table('payment_refunds', function (Blueprint $table) {
                    $table->unsignedBigInteger('bank_account_id')->nullable()->index();
                });
            }
        }

        // 22.3 Customer Advances
        if (!$schema->hasTable('customer_advances')) {
            $schema->create('customer_advances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('payment_id')->index();
                $table->date('advance_date');
                $table->decimal('total_amount', 15, 2);
                $table->decimal('allocated_amount', 15, 2)->default(0.00);
                $table->decimal('remaining_amount', 15, 2);
                $table->string('status', 30)->default('ACTIVE')->index(); // ACTIVE, FULLY_UTILIZED, REFUNDED
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 22.4 Customer Credits
        if (!$schema->hasTable('customer_credits')) {
            $schema->create('customer_credits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('credit_type', 40)->index(); // OVERPAYMENT, ADVANCE, CREDIT_NOTE, REFUND_ADJUSTMENT
                $table->unsignedBigInteger('source_id')->nullable()->index(); // payment_id or credit_note_id
                $table->decimal('amount', 15, 2);
                $table->decimal('applied_amount', 15, 2)->default(0.00);
                $table->decimal('remaining_amount', 15, 2);
                $table->string('status', 30)->default('ACTIVE')->index(); // ACTIVE, FULLY_APPLIED, CANCELLED
                $table->timestamps();
            });
        }

        // 22.5 Payment Links
        if (!$schema->hasTable('payment_links')) {
            $schema->create('payment_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->string('token', 80)->unique();
                $table->decimal('amount', 15, 2);
                $table->decimal('original_invoice_amount', 15, 2);
                $table->boolean('is_partial_allowed')->default(false);
                $table->decimal('min_amount', 15, 2)->nullable();
                $table->string('status', 30)->default('ACTIVE')->index(); // CREATED, ACTIVE, OPENED, PAYMENT_PENDING, PAID, EXPIRED, CANCELLED
                $table->timestamp('expires_at')->nullable()->index();
                $table->string('description', 255)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('payment_id')->nullable()->index();
                $table->timestamps();
            });
        }

        // 22.6 Payment Link Events (Audit Trail)
        if (!$schema->hasTable('payment_link_events')) {
            $schema->create('payment_link_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_link_id')->index();
                $table->string('event_type', 50)->index(); // CREATED, OPENED, ATTEMPTED, PAID, EXPIRED, CANCELLED
                $table->string('ip_address', 50)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->longText('payload_json')->nullable();
                $table->timestamps();
            });
        }

        // 22.7 Payment Gateway Transactions
        if (!$schema->hasTable('payment_gateway_transactions')) {
            $schema->create('payment_gateway_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('payment_id')->nullable()->index();
                $table->unsignedBigInteger('payment_link_id')->nullable()->index();
                $table->string('provider', 40)->index(); // RAZORPAY, PAYU, CASHFREE, STRIPE, SANDBOX
                $table->string('gateway_order_id', 100)->nullable()->index();
                $table->string('gateway_payment_id', 100)->nullable()->unique();
                $table->decimal('amount', 15, 2);
                $table->decimal('fee_amount', 15, 2)->default(0.00);
                $table->decimal('tax_amount', 15, 2)->default(0.00);
                $table->decimal('net_amount', 15, 2);
                $table->string('status', 30)->default('SUCCESS')->index(); // PENDING, SUCCESS, FAILED, REFUNDED
                $table->longText('raw_payload_json')->nullable();
                $table->timestamps();
            });
        }

        // 22.8 Payment Gateway Webhooks (Idempotent Event Log)
        if (!$schema->hasTable('payment_gateway_webhooks')) {
            $schema->create('payment_gateway_webhooks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('provider', 40)->index();
                $table->string('event_id', 120)->index();
                $table->string('event_type', 80)->index();
                $table->string('signature', 255)->nullable();
                $table->longText('payload_json')->nullable();
                $table->string('status', 30)->default('RECEIVED')->index(); // RECEIVED, PROCESSED, DUPLICATE, FAILED
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'event_id'], 'gw_webhook_uniq');
            });
        }

        // 22.9 Payment Gateway Settlements
        if (!$schema->hasTable('payment_gateway_settlements')) {
            $schema->create('payment_gateway_settlements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('provider', 40)->index();
                $table->string('settlement_id', 100)->unique();
                $table->date('settlement_date');
                $table->decimal('gross_amount', 15, 2);
                $table->decimal('fee_amount', 15, 2)->default(0.00);
                $table->decimal('tax_amount', 15, 2)->default(0.00);
                $table->decimal('net_amount', 15, 2);
                $table->unsignedBigInteger('bank_account_id')->nullable()->index();
                $table->string('status', 30)->default('MATCHED')->index(); // MATCHED, PARTIALLY_MATCHED, UNMATCHED
                $table->timestamps();
            });
        }

        // 22.10 Bank Statement Imports
        if (!$schema->hasTable('bank_statement_imports')) {
            $schema->create('bank_statement_imports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->string('file_name', 191);
                $table->string('import_format', 20)->default('CSV'); // CSV, EXCEL
                $table->longText('mapping_config_json')->nullable();
                $table->integer('total_rows')->default(0);
                $table->integer('imported_rows')->default(0);
                $table->integer('duplicate_rows')->default(0);
                $table->string('status', 30)->default('COMPLETED')->index();
                $table->timestamps();
            });
        }

        // 22.11 Bank Statement Rows (Raw Rows for Reconciliation)
        if (!$schema->hasTable('bank_statement_rows')) {
            $schema->create('bank_statement_rows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('import_id')->index();
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->date('row_date');
                $table->string('description', 255);
                $table->string('reference_number', 100)->nullable()->index();
                $table->decimal('debit', 15, 2)->default(0.00);
                $table->decimal('credit', 15, 2)->default(0.00);
                $table->decimal('balance', 15, 2)->nullable();
                $table->string('match_status', 30)->default('UNMATCHED')->index(); // UNMATCHED, MATCHED, PARTIALLY_MATCHED, IGNORED
                $table->boolean('duplicate_flag')->default(false)->index();
                $table->timestamps();
            });
        }

        // 22.12 Bank Reconciliations
        if (!$schema->hasTable('bank_reconciliations')) {
            $schema->create('bank_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->date('statement_start_date');
                $table->date('statement_end_date');
                $table->decimal('opening_balance', 15, 2);
                $table->decimal('closing_balance', 15, 2);
                $table->decimal('system_balance', 15, 2)->default(0.00);
                $table->decimal('unreconciled_difference', 15, 2)->default(0.00);
                $table->string('status', 30)->default('OPEN')->index(); // OPEN, IN_PROGRESS, COMPLETED
                $table->timestamp('reconciled_at')->nullable();
                $table->string('reconciled_by', 100)->nullable();
                $table->timestamps();
            });
        }

        // 22.13 Bank Reconciliation Matches
        if (!$schema->hasTable('bank_reconciliation_matches')) {
            $schema->create('bank_reconciliation_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('bank_reconciliation_id')->index();
                $table->unsignedBigInteger('bank_statement_row_id')->index();
                $table->unsignedBigInteger('bank_transaction_id')->index();
                $table->decimal('matched_amount', 15, 2);
                $table->string('match_confidence', 30)->default('EXACT'); // EXACT, HIGH_CONFIDENCE, MANUAL
                $table->string('matched_by', 100)->nullable();
                $table->timestamps();
            });
        }

        // 22.14 Cheques Master Register
        if (!$schema->hasTable('cheques')) {
            $schema->create('cheques', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('direction', 20)->default('RECEIVED')->index(); // RECEIVED, ISSUED
                $table->string('party_type', 30)->default('CUSTOMER')->index(); // CUSTOMER, SUPPLIER
                $table->unsignedBigInteger('party_id')->index();
                $table->string('cheque_number', 50)->index();
                $table->string('bank_name', 120);
                $table->date('cheque_date');
                $table->date('received_issued_date');
                $table->date('deposit_date')->nullable();
                $table->date('cleared_date')->nullable();
                $table->date('bounced_date')->nullable();
                $table->decimal('amount', 15, 2);
                $table->unsignedBigInteger('bank_account_id')->nullable()->index();
                $table->string('status', 30)->default('RECEIVED')->index(); // RECEIVED, DEPOSITED, PREPARED, ISSUED, PRESENTED, CLEARED, BOUNCED, CANCELLED
                $table->string('bounce_reason', 255)->nullable();
                $table->decimal('bounce_charges', 15, 2)->default(0.00);
                $table->unsignedBigInteger('payment_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 22.15 Cheque Events (Audit)
        if (!$schema->hasTable('cheque_events')) {
            $schema->create('cheque_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cheque_id')->index();
                $table->string('event_type', 40)->index(); // RECEIVED, DEPOSITED, CLEARED, BOUNCED, CANCELLED
                $table->string('performed_by', 100)->default('System');
                $table->longText('details_json')->nullable();
                $table->timestamps();
            });
        }

        // 22.16 Table Additions & Fallbacks for Existing Chunk Tables
        if ($schema->hasTable('payment_links')) {
            if (!$schema->hasColumn('payment_links', 'token')) {
                $schema->table('payment_links', function (Blueprint $table) {
                    $table->string('token', 80)->nullable()->unique();
                });
            }
            if (!$schema->hasColumn('payment_links', 'original_invoice_amount')) {
                $schema->table('payment_links', function (Blueprint $table) {
                    $table->decimal('original_invoice_amount', 15, 2)->default(0.00);
                });
            }
            if (!$schema->hasColumn('payment_links', 'is_partial_allowed')) {
                $schema->table('payment_links', function (Blueprint $table) {
                    $table->boolean('is_partial_allowed')->default(false);
                });
            }
            if (!$schema->hasColumn('payment_links', 'min_amount')) {
                $schema->table('payment_links', function (Blueprint $table) {
                    $table->decimal('min_amount', 15, 2)->nullable();
                });
            }
            if (!$schema->hasColumn('payment_links', 'expires_at')) {
                $schema->table('payment_links', function (Blueprint $table) {
                    $table->timestamp('expires_at')->nullable()->index();
                });
            }
            if (!$schema->hasColumn('payment_links', 'description')) {
                $schema->table('payment_links', function (Blueprint $table) {
                    $table->string('description', 255)->nullable();
                });
            }
            if (!$schema->hasColumn('payment_links', 'payment_id')) {
                $schema->table('payment_links', function (Blueprint $table) {
                    $table->unsignedBigInteger('payment_id')->nullable()->index();
                });
            }
        }

        if ($schema->hasTable('payment_gateway_transactions')) {
            if (!$schema->hasColumn('payment_gateway_transactions', 'payment_id')) {
                $schema->table('payment_gateway_transactions', function (Blueprint $table) {
                    $table->unsignedBigInteger('payment_id')->nullable()->index();
                });
            }
            if (!$schema->hasColumn('payment_gateway_transactions', 'net_amount')) {
                $schema->table('payment_gateway_transactions', function (Blueprint $table) {
                    $table->decimal('net_amount', 15, 2)->default(0.00);
                });
            }
            if (!$schema->hasColumn('payment_gateway_transactions', 'raw_payload_json')) {
                $schema->table('payment_gateway_transactions', function (Blueprint $table) {
                    $table->longText('raw_payload_json')->nullable();
                });
            }
        }

        if ($schema->hasTable('bank_statement_imports')) {
            if (!$schema->hasColumn('bank_statement_imports', 'import_format')) {
                $schema->table('bank_statement_imports', function (Blueprint $table) {
                    $table->string('import_format', 20)->default('CSV');
                });
            }
            if (!$schema->hasColumn('bank_statement_imports', 'mapping_config_json')) {
                $schema->table('bank_statement_imports', function (Blueprint $table) {
                    $table->longText('mapping_config_json')->nullable();
                });
            }
        }

        if ($schema->hasTable('bank_statement_rows')) {
            if (!$schema->hasColumn('bank_statement_rows', 'row_date')) {
                $schema->table('bank_statement_rows', function (Blueprint $table) {
                    $table->date('row_date')->nullable();
                });
            }
            if (!$schema->hasColumn('bank_statement_rows', 'debit')) {
                $schema->table('bank_statement_rows', function (Blueprint $table) {
                    $table->decimal('debit', 15, 2)->default(0.00);
                });
            }
            if (!$schema->hasColumn('bank_statement_rows', 'credit')) {
                $schema->table('bank_statement_rows', function (Blueprint $table) {
                    $table->decimal('credit', 15, 2)->default(0.00);
                });
            }
            if (!$schema->hasColumn('bank_statement_rows', 'match_status')) {
                $schema->table('bank_statement_rows', function (Blueprint $table) {
                    $table->string('match_status', 30)->default('UNMATCHED')->index();
                });
            }
            if (!$schema->hasColumn('bank_statement_rows', 'duplicate_flag')) {
                $schema->table('bank_statement_rows', function (Blueprint $table) {
                    $table->boolean('duplicate_flag')->default(false)->index();
                });
            }
        }

        if ($schema->hasTable('bank_reconciliations')) {
            if (!$schema->hasColumn('bank_reconciliations', 'opening_balance')) {
                $schema->table('bank_reconciliations', function (Blueprint $table) {
                    $table->decimal('opening_balance', 15, 2)->default(0.00);
                });
            }
            if (!$schema->hasColumn('bank_reconciliations', 'closing_balance')) {
                $schema->table('bank_reconciliations', function (Blueprint $table) {
                    $table->decimal('closing_balance', 15, 2)->default(0.00);
                });
            }
            if (!$schema->hasColumn('bank_reconciliations', 'system_balance')) {
                $schema->table('bank_reconciliations', function (Blueprint $table) {
                    $table->decimal('system_balance', 15, 2)->default(0.00);
                });
            }
            if (!$schema->hasColumn('bank_reconciliations', 'unreconciled_difference')) {
                $schema->table('bank_reconciliations', function (Blueprint $table) {
                    $table->decimal('unreconciled_difference', 15, 2)->default(0.00);
                });
            }
            if (!$schema->hasColumn('bank_reconciliations', 'reconciled_at')) {
                $schema->table('bank_reconciliations', function (Blueprint $table) {
                    $table->timestamp('reconciled_at')->nullable();
                });
            }
            if (!$schema->hasColumn('bank_reconciliations', 'reconciled_by')) {
                $schema->table('bank_reconciliations', function (Blueprint $table) {
                    $table->string('reconciled_by', 100)->nullable();
                });
            }
        }

        if ($schema->hasTable('bank_reconciliation_matches')) {
            if (!$schema->hasColumn('bank_reconciliation_matches', 'bank_reconciliation_id')) {
                $schema->table('bank_reconciliation_matches', function (Blueprint $table) {
                    $table->unsignedBigInteger('bank_reconciliation_id')->nullable()->index();
                });
            }
            if (!$schema->hasColumn('bank_reconciliation_matches', 'bank_statement_row_id')) {
                $schema->table('bank_reconciliation_matches', function (Blueprint $table) {
                    $table->unsignedBigInteger('bank_statement_row_id')->nullable()->index();
                });
            }
            if (!$schema->hasColumn('bank_reconciliation_matches', 'match_confidence')) {
                $schema->table('bank_reconciliation_matches', function (Blueprint $table) {
                    $table->string('match_confidence', 30)->default('EXACT');
                });
            }
        }

        if ($schema->hasTable('cheque_events')) {
            if (!$schema->hasColumn('cheque_events', 'event_type')) {
                $schema->table('cheque_events', function (Blueprint $table) {
                    $table->string('event_type', 40)->nullable()->index();
                });
            }
            if (!$schema->hasColumn('cheque_events', 'performed_by')) {
                $schema->table('cheque_events', function (Blueprint $table) {
                    $table->string('performed_by', 100)->nullable();
                });
            }
            if (!$schema->hasColumn('cheque_events', 'details_json')) {
                $schema->table('cheque_events', function (Blueprint $table) {
                    $table->longText('details_json')->nullable();
                });
            }
        }

        // =========================================================================
        // SECTION 23: CHUNK 15 - IMPORT/EXPORT, BACKUP/RESTORE, DATA MIGRATION,
        // COMPREHENSIVE AUDIT TRAIL & SYSTEM ADMINISTRATION
        // =========================================================================

        // 23.1 Import Jobs Table
        if (!$schema->hasTable('import_jobs')) {
            $schema->create('import_jobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('data_type', 50)->index(); // CUSTOMERS, SUPPLIERS, PRODUCTS, CATEGORIES, OPENING_BALANCES, OPENING_STOCK, PRICE_LISTS
                $table->string('file_name', 255);
                $table->string('file_type', 20)->default('CSV'); // CSV, XLSX
                $table->string('file_path', 500)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->integer('total_rows')->default(0);
                $table->integer('valid_rows')->default(0);
                $table->integer('warning_rows')->default(0);
                $table->integer('error_rows')->default(0);
                $table->integer('duplicate_rows')->default(0);
                $table->integer('processed_rows')->default(0);
                $table->string('duplicate_action', 30)->default('SKIP'); // SKIP, UPDATE, CREATE_NEW
                $table->longText('mapping_config_json')->nullable();
                $table->longText('validation_summary_json')->nullable();
                $table->string('status', 30)->default('UPLOADED')->index(); // UPLOADED, MAPPED, VALIDATED, CONFIRMED, PROCESSING, COMPLETED, FAILED, CANCELLED
                $table->text('error_message')->nullable();
                $table->string('error_report_path', 500)->nullable();
                $table->string('correlation_id', 60)->nullable()->index();
                $table->string('created_by', 100)->default('System');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // 23.2 Import Row Logs Table
        if (!$schema->hasTable('import_row_logs')) {
            $schema->create('import_row_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('import_job_id')->index();
                $table->integer('row_index')->index();
                $table->string('status', 30)->default('VALID')->index(); // VALID, WARNING, ERROR, DUPLICATE
                $table->longText('raw_data_json')->nullable();
                $table->longText('parsed_data_json')->nullable();
                $table->longText('issues_json')->nullable();
                $table->timestamps();
            });
        }

        // 23.3 Export Jobs Table
        if (!$schema->hasTable('export_jobs')) {
            $schema->create('export_jobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('data_type', 50)->index(); // CUSTOMERS, SUPPLIERS, PRODUCTS, INVENTORY, SALES, PURCHASES, PAYMENTS, EXPENSES, INVOICES, LEDGER, BANK_TRANSACTIONS, AUDIT_LOGS
                $table->string('export_format', 20)->default('CSV'); // CSV, XLSX, PDF
                $table->longText('columns_json')->nullable();
                $table->longText('filters_json')->nullable();
                $table->string('file_name', 255)->nullable();
                $table->string('file_path', 500)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('download_token', 80)->nullable()->unique();
                $table->timestamp('download_token_expires_at')->nullable()->index();
                $table->string('status', 30)->default('QUEUED')->index(); // QUEUED, PROCESSING, READY, FAILED, EXPIRED
                $table->text('error_message')->nullable();
                $table->integer('total_records')->default(0);
                $table->string('correlation_id', 60)->nullable()->index();
                $table->string('created_by', 100)->default('System');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // 23.4 Backup Records Table
        if (!$schema->hasTable('backup_records')) {
            $schema->create('backup_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index(); // Nullable for full-system instance backup
                $table->string('backup_type', 30)->default('MANUAL')->index(); // MANUAL, SCHEDULED, FULL, SYSTEM
                $table->string('file_name', 255);
                $table->string('file_path', 500);
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('checksum', 100)->index(); // SHA-256
                $table->string('storage_location', 100)->default('LOCAL');
                $table->string('app_version', 30)->default('1.0.0');
                $table->string('schema_version', 30)->default('23.0');
                $table->string('status', 30)->default('COMPLETED')->index(); // QUEUED, RUNNING, COMPLETED, FAILED, EXPIRED, DELETED
                $table->text('error_message')->nullable();
                $table->longText('metadata_json')->nullable();
                $table->boolean('is_encrypted')->default(false);
                $table->integer('retention_days')->default(30);
                $table->timestamp('expires_at')->nullable()->index();
                $table->string('created_by', 100)->default('System');
                $table->timestamps();
            });
        }

        // 23.5 Restore Logs Table (Danger Zone Audit)
        if (!$schema->hasTable('restore_logs')) {
            $schema->create('restore_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('backup_record_id')->index();
                $table->unsignedBigInteger('safety_backup_id')->nullable()->index();
                $table->string('restore_mode', 30)->default('BUSINESS'); // FULL, BUSINESS, VALIDATION
                $table->string('status', 30)->default('STARTED')->index(); // STARTED, IN_PROGRESS, COMPLETED, FAILED, ROLLED_BACK
                $table->string('initiated_by', 100)->default('Admin');
                $table->string('ip_address', 60)->nullable();
                $table->boolean('checksum_verified')->default(false);
                $table->boolean('schema_compatible')->default(false);
                $table->longText('tables_restored_json')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // 23.6 Schema Migrations Tracker Table
        if (!$schema->hasTable('schema_migrations')) {
            $schema->create('schema_migrations', function (Blueprint $table) {
                $table->id();
                $table->string('version', 50)->unique();
                $table->string('name', 255);
                $table->integer('batch')->default(1);
                $table->string('status', 30)->default('APPLIED')->index(); // APPLIED, FAILED, ROLLED_BACK
                $table->integer('execution_time_ms')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('applied_at')->useCurrent();
            });
        }

        // 23.7 Migration Profiles Table (Source Software Column Mappings)
        if (!$schema->hasTable('migration_profiles')) {
            $schema->create('migration_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name', 100);
                $table->string('data_type', 50)->index();
                $table->string('source_software', 50)->default('GENERIC'); // MYBILLBOOK, VYAPAR, TALLY, ZOHO, GENERIC
                $table->longText('mapping_json');
                $table->string('created_by', 100)->default('System');
                $table->timestamps();
            });
        }

        // 23.8 Centralized Document Numbering Configurations Table
        if (!$schema->hasTable('document_numbering_configs')) {
            $schema->create('document_numbering_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('document_type', 50)->index(); // INVOICE, PURCHASE_INVOICE, QUOTATION, SALES_ORDER, CREDIT_NOTE, DEBIT_NOTE, PAYMENT_RECEIPT, PURCHASE_ORDER, DELIVERY_CHALLAN, JOURNAL, EXPENSE, PAYMENT
                $table->string('prefix', 30)->default('INV-');
                $table->string('suffix', 30)->nullable();
                $table->bigInteger('starting_number')->default(1);
                $table->bigInteger('current_number')->default(0);
                $table->integer('number_padding')->default(4);
                $table->string('reset_frequency', 20)->default('YEARLY'); // YEARLY, MONTHLY, NEVER
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 23.9 User Sessions Table (Active Session Management & Revocation)
        if (!$schema->hasTable('user_sessions')) {
            $schema->create('user_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('session_token', 100)->unique();
                $table->string('ip_address', 60)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('device_info', 100)->nullable();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->boolean('is_revoked')->default(false)->index();
                $table->timestamps();
            });
        }

        // 23.10 Upgrade Existing audit_logs Table with Rich Context Columns
        if ($schema->hasTable('audit_logs')) {
            if (!$schema->hasColumn('audit_logs', 'branch_id')) {
                $schema->table('audit_logs', function (Blueprint $table) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                });
            }
            if (!$schema->hasColumn('audit_logs', 'correlation_id')) {
                $schema->table('audit_logs', function (Blueprint $table) {
                    $table->string('correlation_id', 60)->nullable()->index();
                });
            }
            if (!$schema->hasColumn('audit_logs', 'before_data_json')) {
                $schema->table('audit_logs', function (Blueprint $table) {
                    $table->longText('before_data_json')->nullable();
                });
            }
            if (!$schema->hasColumn('audit_logs', 'after_data_json')) {
                $schema->table('audit_logs', function (Blueprint $table) {
                    $table->longText('after_data_json')->nullable();
                });
            }
            if (!$schema->hasColumn('audit_logs', 'metadata_json')) {
                $schema->table('audit_logs', function (Blueprint $table) {
                    $table->longText('metadata_json')->nullable();
                });
            }
            if (!$schema->hasColumn('audit_logs', 'user_agent')) {
                $schema->table('audit_logs', function (Blueprint $table) {
                    $table->string('user_agent', 255)->nullable();
                });
            }
            if (!$schema->hasColumn('audit_logs', 'status')) {
                $schema->table('audit_logs', function (Blueprint $table) {
                    $table->string('status', 30)->default('SUCCESS')->index();
                });
            }
            if (!$schema->hasColumn('audit_logs', 'severity')) {
                $schema->table('audit_logs', function (Blueprint $table) {
                    $table->string('severity', 20)->default('INFO')->index(); // INFO, WARNING, CRITICAL
                });
            }
        }

        // 23.11 Products Column Compatibility Check
        if ($schema->hasTable('products')) {
            if (!$schema->hasColumn('products', 'selling_price')) {
                $schema->table('products', function (Blueprint $table) {
                    $table->decimal('selling_price', 15, 2)->nullable();
                });
            }
            if (!$schema->hasColumn('products', 'gst_rate')) {
                $schema->table('products', function (Blueprint $table) {
                    $table->decimal('gst_rate', 5, 2)->default(0.00);
                });
            }
            if (!$schema->hasColumn('products', 'opening_stock')) {
                $schema->table('products', function (Blueprint $table) {
                    $table->decimal('opening_stock', 15, 3)->default(0.000);
                });
            }
            if (!$schema->hasColumn('products', 'min_stock_level')) {
                $schema->table('products', function (Blueprint $table) {
                    $table->decimal('min_stock_level', 15, 3)->default(0.000);
                });
            }
        }

        // =====================================================================
        // SECTION 24: CHUNK 16 - REPORTING & ANALYTICS
        // =====================================================================

        // 24.1 Report Saved Views & Favorites
        if (!$schema->hasTable('report_saved_views')) {
            $schema->create('report_saved_views', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('report_key', 80)->index();
                $table->string('name', 150);
                $table->text('filters_json')->nullable();
                $table->text('columns_json')->nullable();
                $table->text('sort_json')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_favorite')->default(false);
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 24.2 Report Audit Logs
        if (!$schema->hasTable('report_audit_logs')) {
            $schema->create('report_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 120)->nullable();
                $table->string('report_key', 80)->index();
                $table->string('action', 50)->default('GENERATED'); // GENERATED, EXPORTED_CSV, EXPORTED_EXCEL, EXPORTED_PDF, PRINTED, VIEW_SAVED
                $table->text('filters_json')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 24.3 Report Snapshots
        if (!$schema->hasTable('report_snapshots')) {
            $schema->create('report_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('report_key', 80)->index();
                $table->string('snapshot_name', 150);
                $table->string('correlation_id', 60)->nullable()->index();
                $table->text('filters_json')->nullable();
                $table->longText('dataset_json');
                $table->string('generated_by', 120)->default('System');
                $table->timestamp('generated_at')->useCurrent();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 24.4 Report Schedules
        if (!$schema->hasTable('report_schedules')) {
            $schema->create('report_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('report_key', 80)->index();
                $table->string('title', 150);
                $table->string('frequency', 30)->default('WEEKLY'); // DAILY, WEEKLY, MONTHLY
                $table->string('delivery_channel', 30)->default('EMAIL'); // EMAIL, WHATSAPP, IN_APP
                $table->string('recipient', 255);
                $table->string('export_format', 20)->default('PDF'); // PDF, EXCEL, CSV
                $table->text('filters_json')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 120)->default('System');
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // ----------------------------------------------------
        // 25. AUTOMATION, COMMUNICATION, REMINDERS & RECURRING
        // ----------------------------------------------------

        // 25.1 Automation Events
        if (!$schema->hasTable('automation_events')) {
            $schema->create('automation_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 64)->unique();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('event_type', 80)->index();
                $table->string('entity_type', 60)->index();
                $table->unsignedBigInteger('entity_id')->index();
                $table->timestamp('occurred_at')->useCurrent();
                $table->string('triggered_by', 120)->default('System');
                $table->text('metadata_json')->nullable();
                $table->string('status', 30)->default('CREATED'); // CREATED, PROCESSING, PROCESSED, FAILED, SKIPPED
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 25.2 Automation Rules
        if (!$schema->hasTable('automation_rules')) {
            $schema->create('automation_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->string('event_type', 80)->index();
                $table->text('conditions_json')->nullable();
                $table->text('actions_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('priority')->default(10);
                $table->string('created_by', 120)->default('System');
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 25.3 Automation Execution Logs
        if (!$schema->hasTable('automation_execution_logs')) {
            $schema->create('automation_execution_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('rule_id')->nullable()->index();
                $table->string('event_id', 64)->nullable()->index();
                $table->string('action_type', 60);
                $table->string('entity_type', 60)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('status', 30)->default('SUCCESS'); // SUCCESS, FAILED, SKIPPED
                $table->text('result_data_json')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('executed_at')->useCurrent();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 25.4 Reminders
        if (!$schema->hasTable('reminders')) {
            $schema->create('reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('reminder_type', 60)->index(); // INVOICE_DUE, INVOICE_OVERDUE, SUPPLIER_BILL_DUE, CHEQUE_DUE, CHEQUE_CLEARANCE, GST_TASK, INTERNAL_TASK, CUSTOM
                $table->string('entity_type', 60)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('recipient', 255)->nullable();
                $table->date('due_date')->nullable();
                $table->date('reminder_date')->index();
                $table->string('schedule_type', 30)->default('BEFORE_DUE'); // SPECIFIC_DATE, BEFORE_DUE, ON_DUE, AFTER_DUE
                $table->integer('offset_days')->default(0);
                $table->string('frequency', 30)->default('ONCE'); // ONCE, DAILY, EVERY_X_DAYS, WEEKLY
                $table->integer('max_count')->default(3);
                $table->integer('sent_count')->default(0);
                $table->string('stop_condition', 50)->default('ON_PAID');
                $table->string('status', 30)->default('SCHEDULED'); // PENDING, SCHEDULED, SENT, COMPLETED, DISMISSED, CANCELLED, FAILED
                $table->string('priority', 20)->default('NORMAL'); // LOW, NORMAL, HIGH, CRITICAL
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 25.5 In-App Notifications
        if (!$schema->hasTable('in_app_notifications')) {
            $schema->create('in_app_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('category', 40)->default('system')->index(); // payments, sales, purchases, inventory, gst, system, tasks
                $table->string('type', 60)->default('INFO');
                $table->string('title', 200);
                $table->text('message');
                $table->string('entity_type', 60)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('action_url', 255)->nullable();
                $table->string('read_status', 20)->default('UNREAD')->index(); // UNREAD, READ, ARCHIVED
                $table->string('priority', 20)->default('NORMAL'); // LOW, NORMAL, HIGH, CRITICAL
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        } else {
            $notifCols = ['type', 'category', 'read_status', 'action_url', 'priority', 'entity_type', 'entity_id'];
            foreach ($notifCols as $col) {
                if (!$schema->hasColumn('in_app_notifications', $col)) {
                    $schema->table('in_app_notifications', function (Blueprint $table) use ($col) {
                        $table->string($col, 100)->nullable();
                    });
                }
            }
        }

        // 25.6 Communication Messages (Email & WhatsApp Queue & History)
        if (!$schema->hasTable('communication_messages')) {
            $schema->create('communication_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('channel', 30)->index(); // EMAIL, WHATSAPP, IN_APP, SMS
                $table->string('entity_type', 60)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('recipient', 255);
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('template_code', 60)->nullable();
                $table->string('subject', 255)->nullable();
                $table->text('body');
                $table->text('variables_json')->nullable();
                $table->string('attachment_path', 255)->nullable();
                $table->string('provider_name', 50)->nullable();
                $table->string('provider_reference', 120)->nullable();
                $table->string('status', 30)->default('QUEUED')->index(); // QUEUED, SENDING, SENT, DELIVERED, READ, FAILED, BOUNCED
                $table->integer('attempts')->default(0);
                $table->integer('max_attempts')->default(3);
                $table->timestamp('next_retry_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 25.7 Communication Preferences
        if (!$schema->hasTable('communication_preferences')) {
            $schema->create('communication_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('party_type', 30); // CUSTOMER, SUPPLIER, USER
                $table->unsignedBigInteger('party_id')->index();
                $table->boolean('email_opt_in')->default(true);
                $table->boolean('whatsapp_opt_in')->default(true);
                $table->boolean('sms_opt_in')->default(true);
                $table->boolean('promotional_opt_in')->default(false);
                $table->string('preferred_channel', 30)->default('WHATSAPP');
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 25.8 Communication Templates
        if (!$schema->hasTable('communication_templates')) {
            $schema->create('communication_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('channel', 30)->default('EMAIL'); // EMAIL, WHATSAPP, SMS, IN_APP
                $table->string('template_code', 60)->index();
                $table->string('name', 150);
                $table->string('category', 50)->default('Sales');
                $table->string('subject', 255)->nullable();
                $table->text('body');
                $table->text('variables_json')->nullable();
                $table->string('language', 10)->default('en');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 25.9 Recurring Templates
        if (!$schema->hasTable('recurring_templates')) {
            $schema->create('recurring_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('template_number', 50)->index();
                $table->string('transaction_type', 40)->default('SALES_INVOICE'); // SALES_INVOICE, PURCHASE_BILL, EXPENSE, JOURNAL, PAYMENT, REMINDER
                $table->string('party_type', 30)->nullable();
                $table->unsignedBigInteger('party_id')->nullable();
                $table->string('title', 150);
                $table->longText('payload_json');
                $table->decimal('amount', 15, 2)->default(0.00);
                $table->string('frequency', 30)->default('MONTHLY'); // DAILY, WEEKLY, MONTHLY, QUARTERLY, YEARLY, CUSTOM
                $table->string('month_end_policy', 30)->default('LAST_DAY'); // LAST_DAY, SKIP, NEXT_VALID
                $table->string('custom_cron', 50)->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->date('next_run_date')->index();
                $table->date('last_run_date')->nullable();
                $table->string('generation_mode', 30)->default('AUTO_DRAFT'); // AUTO_DRAFT, AUTO_POST
                $table->string('status', 30)->default('ACTIVE'); // DRAFT, ACTIVE, PAUSED, COMPLETED, CANCELLED, FAILED
                $table->integer('version')->default(1);
                $table->string('created_by', 120)->default('System');
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 25.10 Recurring Run Logs
        if (!$schema->hasTable('recurring_run_logs')) {
            $schema->create('recurring_run_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('template_id')->index();
                $table->string('run_id', 64)->unique();
                $table->date('run_date');
                $table->string('generated_entity_type', 60)->nullable();
                $table->unsignedBigInteger('generated_entity_id')->nullable();
                $table->string('generation_mode', 30)->default('AUTO_DRAFT');
                $table->string('status', 30)->default('SUCCESS'); // SUCCESS, FAILED, SKIPPED
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }
    }
}

