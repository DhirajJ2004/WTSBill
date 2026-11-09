<?php
/**
 * WTSBill ERP — Centralized Navigation Menu Configuration
 *
 * Single source of truth for the left sidebar menu items.
 * Each item contains:
 * - label: Display name
 * - icon: SVG icon markup (stroke 2, 18x18, consistent line style)
 * - url: Destination route (or null for parent-only headings)
 * - roles: Allowed role names ('admin', 'accountant', 'inventory_manager', '*')
 * - permission: Internal Gate permission check (if used in enterprise RBAC)
 * - children: Array of sub-items (optional)
 * - collapsed_by_default: Whether the group is collapsed by default (e.g. Accounting)
 * - condition: Optional callback or boolean flag (e.g. multi-warehouse check)
 */

return [
    // 1. Dashboard (top of the menu)
    [
        'id' => 'dashboard',
        'label' => 'Dashboard',
        'url' => '/',
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
        'roles' => ['admin', 'accountant', 'inventory_manager'],
        'permission' => 'dashboard.view',
        'any_permissions' => ['dashboard.view', 'dashboard.payments.view', 'dashboard.inventory.view'],
    ],

    // 2. Sales
    [
        'id' => 'sales',
        'label' => 'Sales',
        'url' => null,
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
        'roles' => ['admin', 'accountant'],
        'permission' => 'invoices.view',
        'children' => [
            [
                'id' => 'sales_invoices',
                'label' => 'Tax Invoices',
                'url' => '/sales/invoices',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'invoices.view',
            ],
            [
                'id' => 'sales_quotations',
                'label' => 'Quotations',
                'url' => '/sales/quotations',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'quotations.view',
            ],
            [
                'id' => 'sales_orders',
                'label' => 'Sales Orders',
                'url' => '/sales/orders',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="m9 14 2 2 4-4"></path></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'quotations.view',
            ],
            [
                'id' => 'sales_challans',
                'label' => 'Delivery Challans',
                'url' => '/sales/challans',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 19 21 12 17 5 21 12 2"></polygon></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'challans.view',
            ],
            [
                'id' => 'sales_credit_notes',
                'label' => 'Credit Notes',
                'url' => '/sales/credit-notes',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 14l-6 6m0 0l-6-6m6 6V4"></path><path d="M3 10h18"></path></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'invoices.view',
            ],
        ],
    ],

    // 3. Purchases
    [
        'id' => 'purchases',
        'label' => 'Purchases',
        'url' => null,
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
        'roles' => ['admin', 'accountant'],
        'permission' => 'bills.view',
        'children' => [
            [
                'id' => 'purchases_bills',
                'label' => 'Purchase Bills',
                'url' => '/purchases/bills',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'bills.view',
            ],
            [
                'id' => 'expenses',
                'label' => 'Expenses',
                'url' => '/expenses',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'expenses.view',
            ],
            [
                'id' => 'purchases_debit_notes',
                'label' => 'Debit Notes',
                'url' => '/purchases/debit-notes',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h10a8 8 0 0 1 8 8v2M3 10l6 6m-6-6l6-6"/></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'bills.view',
            ],
        ],
    ],

    // 4. Items & Stock (Unified tabbed page for Items, Categories, Stock In/Out, Stock Adjustments, Stock Transfers)
    [
        'id' => 'inventory',
        'label' => 'Items',
        'url' => null,
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
        'roles' => ['admin', 'inventory_manager'],
        'permission' => 'inventory.view',
        'children' => [
            [
                'id' => 'items_merged',
                'label' => 'Items',
                'url' => '/items',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
                'roles' => ['admin', 'inventory_manager'],
                'permission' => 'inventory.view',
            ],
        ],
    ],

    // 5. Parties (Unified Customers & Suppliers Hub)
    [
        'id' => 'parties',
        'label' => 'Parties',
        'url' => '/parties',
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'roles' => ['admin', 'sales', 'accountant'],
        'permission' => 'customers.view',
        'any_permissions' => ['customers.view', 'suppliers.view'],
    ],

    // 6. Finance
    [
        'id' => 'finance',
        'label' => 'Finance',
        'url' => null,
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>',
        'roles' => ['admin', 'accountant'],
        'permission' => 'payments.view',
        'children' => [
            [
                'id' => 'payments_receipts',
                'label' => 'Payments & Receipts',
                'url' => '/payments',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'payments.view',
            ],
            [
                'id' => 'cash_bank_merged',
                'label' => 'Cash & Bank',
                'url' => '/accounting/cash-bank',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="21" x2="21" y2="21"></line><line x1="3" y1="10" x2="21" y2="10"></line><polyline points="5 6 12 3 19 6"></polyline><line x1="4" y1="10" x2="4" y2="21"></line><line x1="20" y1="10" x2="20" y2="21"></line><line x1="8" y1="14" x2="8" y2="17"></line><line x1="12" y1="14" x2="12" y2="17"></line><line x1="16" y1="14" x2="16" y2="17"></line></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
            [
                'id' => 'report_receivables_aging',
                'label' => 'Receivables Aging',
                'url' => '/reports/receivables-aging',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
        ],
    ],

    // 7. GST
    [
        'id' => 'gst',
        'label' => 'GST',
        'url' => null,
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>',
        'roles' => ['admin', 'accountant'],
        'permission' => 'reports.view',
        'children' => [
            [
                'id' => 'report_gst',
                'label' => 'GSTR-1 Compliance',
                'url' => '/reports/gst',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
            [
                'id' => 'report_gstr_3b',
                'label' => 'GSTR-3B',
                'url' => '/reports/gstr-3b',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
            [
                'id' => 'report_gst_registers_merged',
                'label' => 'GST Registers',
                'url' => '/reports/gst-registers',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
        ],
    ],

    // 8. Reports
    [
        'id' => 'reports',
        'label' => 'Reports',
        'url' => null,
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
        'roles' => ['admin', 'accountant'],
        'permission' => 'reports.view',
        'children' => [
            [
                'id' => 'report_profit_loss',
                'label' => 'Profit & Loss',
                'url' => '/reports/profit-loss',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
            [
                'id' => 'report_invoice_payment_status',
                'label' => 'Invoice Payment Status',
                'url' => '/reports/invoice-payment-status',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line><path d="m9 11 2 2 4-4"></path></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
            [
                'id' => 'analytics_bi',
                'label' => 'BI Analytics',
                'url' => '/analytics/bi',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
        ],
    ],

    // 9. Accounting (visible only to Admin and Accountant roles; collapsed by default)
    [
        'id' => 'accounting',
        'label' => 'Accounting',
        'url' => null,
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="22" x2="21" y2="22"></line><line x1="6" y1="18" x2="6" y2="11"></line><line x1="10" y1="18" x2="10" y2="11"></line><line x1="14" y1="18" x2="14" y2="11"></line><line x1="18" y1="18" x2="18" y2="11"></line><polygon points="12 2 20 7 4 7"></polygon></svg>',
        'roles' => ['admin', 'accountant'],
        'permission' => 'accounting.view',
        'collapsed_by_default' => true,
        'children' => [
            [
                'id' => 'chart_of_accounts',
                'label' => 'Chart of Accounts',
                'url' => '/accounts',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="22" x2="21" y2="22"></line><line x1="6" y1="18" x2="6" y2="11"></line><line x1="10" y1="18" x2="10" y2="11"></line><line x1="14" y1="18" x2="14" y2="11"></line><line x1="18" y1="18" x2="18" y2="11"></line><polygon points="12 2 20 7 4 7"></polygon></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'accounting.view',
            ],
            [
                'id' => 'journals',
                'label' => 'Journal Entries',
                'url' => '/accounts/journals',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'accounting.create',
            ],
            [
                'id' => 'ledger',
                'label' => 'General Ledger',
                'url' => '/accounts/ledger',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'accounting.view',
            ],
            [
                'id' => 'report_trial_balance',
                'label' => 'Trial Balance',
                'url' => '/reports/trial-balance',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
            [
                'id' => 'report_balance_sheet',
                'label' => 'Balance Sheet',
                'url' => '/reports/balance-sheet',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
            [
                'id' => 'report_cash_flow',
                'label' => 'Cash Flow Statement',
                'url' => '/reports/cash-flow',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
                'roles' => ['admin', 'accountant'],
                'permission' => 'reports.view',
            ],
        ],
    ],

    // 10. Settings (bottom of the menu)
    [
        'id' => 'settings',
        'label' => 'Settings',
        'url' => null,
        'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
        'roles' => ['admin'],
        'permission' => 'settings.view',
        'children' => [
            [
                'id' => 'company_profile',
                'label' => 'Company Profile',
                'url' => '/settings',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
                'roles' => ['admin'],
                'permission' => 'settings.view',
            ],
            [
                'id' => 'company_branding',
                'label' => 'Company Branding',
                'url' => '/settings/branding',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>',
                'roles' => ['admin'],
                'permission' => 'settings.view',
            ],
            [
                'id' => 'users_roles',
                'label' => 'Users & Roles',
                'url' => '/users',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
                'roles' => ['admin'],
                'permission' => 'users.view',
            ],
            [
                'id' => 'invoice_templates',
                'label' => 'Invoice Templates',
                'url' => '/settings/templates',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line></svg>',
                'roles' => ['admin'],
                'permission' => 'settings.view',
            ],
            [
                'id' => 'warehouses_setting',
                'label' => 'Warehouses & Godowns',
                'url' => '/settings/warehouses',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/></svg>',
                'roles' => ['admin', 'inventory_manager'],
                'permission' => 'inventory.view',
                'condition' => function() {
                    // Show only if multi-warehouse is enabled (default true in WTSBill)
                    return \WTSBill\Core\Config::get('app.multi_warehouse', true);
                },
            ],
        ],
    ],
];
