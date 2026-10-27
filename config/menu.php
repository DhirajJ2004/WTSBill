<?php

declare(strict_types=1);

/**
 * Enterprise Central Navigation Menu Configuration for WTSBill ERP.
 * Each section and menu item is strictly controlled by Gate permissions.
 */
return [
    // Standalone top-level items
    'items' => [
        [
            'id' => 'dashboard',
            'label' => 'Dashboard',
            'route' => '/',
            'permission' => 'dashboard.view',
            // Allow any of the role dashboard permissions to view the Dashboard nav item
            'any_permissions' => ['dashboard.view', 'dashboard.payments.view', 'dashboard.inventory.view'],
            'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
        ],
    ],

    // Grouped section menus
    'sections' => [
        // 1. Payments & Financial Transactions (For Accountant & Admin)
        [
            'id' => 'payments_group',
            'label' => 'Payments & Collections',
            'permission' => 'payments.view',
            'items' => [
                [
                    'id' => 'payments_receipts',
                    'label' => 'Payments & Receipts',
                    'route' => '/payments',
                    'permission' => 'payments.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>',
                ],
            ],
        ],

        // 2. Sales & Billing Group
        [
            'id' => 'sales',
            'label' => 'Sales & Billing',
            'items' => [
                [
                    'id' => 'sales_invoices',
                    'label' => 'Tax Invoices',
                    'route' => '/sales/invoices',
                    'permission' => 'invoices.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
                ],
                [
                    'id' => 'sales_quotations',
                    'label' => 'Quotations (Quotations & Orders)',
                    'route' => '/sales/quotations',
                    'permission' => 'quotations.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
                ],
                [
                    'id' => 'sales_challans',
                    'label' => 'Delivery Challans',
                    'route' => '/sales/challans',
                    'permission' => 'challans.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 19 21 12 17 5 21 12 2"></polygon></svg>',
                ],
            ],
        ],

        // 3. Purchases & Expenses Group
        [
            'id' => 'purchases',
            'label' => 'Purchases & Expenses',
            'items' => [
                [
                    'id' => 'purchases_bills',
                    'label' => 'Purchase Bills',
                    'route' => '/purchases/bills',
                    'permission' => 'bills.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>',
                ],
                [
                    'id' => 'expenses',
                    'label' => 'Expenses',
                    'route' => '/expenses',
                    'permission' => 'expenses.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>',
                ],
            ],
        ],

        // 4. Inventory & Stock Management (For Inventory Manager & Admin)
        [
            'id' => 'inventory',
            'label' => 'Inventory & Stock',
            'items' => [
                [
                    'id' => 'manage_inventory',
                    'label' => 'Manage Inventory',
                    'route' => '/inventory/stock',
                    'permission' => 'inventory.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
                ],
                [
                    'id' => 'stock_management',
                    'label' => 'Stock Management (In-Out)',
                    'route' => '/inventory/stock?action=adjust',
                    'permission' => 'inventory.stock.add',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>',
                ],
                [
                    'id' => 'stock_adjustments',
                    'label' => 'Stock Adjustments',
                    'route' => '/inventory/stock?tab=history',
                    'permission' => 'inventory.adjustment.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>',
                ],
                [
                    'id' => 'items_catalog',
                    'label' => 'Items & Catalog',
                    'route' => '/products',
                    'permission' => 'items.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
                ],
                [
                    'id' => 'categories',
                    'label' => 'Categories',
                    'route' => '/categories',
                    'permission' => 'categories.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>',
                ],
            ],
        ],

        // 5. Parties & CRM Group
        [
            'id' => 'parties',
            'label' => 'Parties & CRM',
            'items' => [
                [
                    'id' => 'customers',
                    'label' => 'Customers',
                    'route' => '/customers',
                    'permission' => 'customers.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
                ],
                [
                    'id' => 'suppliers',
                    'label' => 'Suppliers',
                    'route' => '/suppliers',
                    'permission' => 'suppliers.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
                ],
            ],
        ],

        // 6. Double-Entry Accounting
        [
            'id' => 'accounting',
            'label' => 'Double-Entry Accounting',
            'items' => [
                [
                    'id' => 'chart_of_accounts',
                    'label' => 'Chart of Accounts',
                    'route' => '/accounts',
                    'permission' => 'accounting.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="22" x2="21" y2="22"></line><line x1="6" y1="18" x2="6" y2="11"></line><line x1="10" y1="18" x2="10" y2="11"></line><line x1="14" y1="18" x2="14" y2="11"></line><line x1="18" y1="18" x2="18" y2="11"></line><polygon points="12 2 20 7 4 7"></polygon></svg>',
                ],
                [
                    'id' => 'journals',
                    'label' => 'Journal Entries',
                    'route' => '/accounts/journals',
                    'permission' => 'accounting.create',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>',
                ],
                [
                    'id' => 'ledger',
                    'label' => 'General Ledger',
                    'route' => '/accounts/ledger',
                    'permission' => 'accounting.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>',
                ],
            ],
        ],

        // 7. Compliance & Reports
        [
            'id' => 'reports',
            'label' => 'Compliance & Reports',
            'items' => [
                [
                    'id' => 'report_profit_loss',
                    'label' => 'Profit & Loss',
                    'route' => '/reports/profit-loss',
                    'permission' => 'reports.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
                ],
                [
                    'id' => 'report_balance_sheet',
                    'label' => 'Balance Sheet',
                    'route' => '/reports/balance-sheet',
                    'permission' => 'reports.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>',
                ],
                [
                    'id' => 'report_gst',
                    'label' => 'GSTR-1 Compliance',
                    'route' => '/reports/gst',
                    'permission' => 'reports.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>',
                ],
            ],
        ],

        // 8. Administration (Administrator Only)
        [
            'id' => 'admin',
            'label' => 'Administration',
            'items' => [
                [
                    'id' => 'users',
                    'label' => 'Users & Permissions',
                    'route' => '/users',
                    'permission' => 'users.manage',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>',
                ],
                [
                    'id' => 'roles',
                    'label' => 'Roles & Permissions Matrix',
                    'route' => '/roles',
                    'permission' => 'roles.manage',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
                ],
                [
                    'id' => 'audit_logs',
                    'label' => 'Security Audit Log',
                    'route' => '/settings/audit-logs',
                    'permission' => 'audit.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>',
                ],
                [
                    'id' => 'settings',
                    'label' => 'System Settings',
                    'route' => '/settings',
                    'permission' => 'settings.view',
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
                ],
            ],
        ],
    ],
];
