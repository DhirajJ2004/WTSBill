<?php

/**
 * WTSBill ERP - Web View Route Registry
 * Maps clean URL paths to server-rendered view files.
 */

return [
    '/' => dirname(__DIR__) . '/views/dashboard.php',
    '/login' => dirname(__DIR__) . '/views/auth/login.php',
    '/forgot-password' => dirname(__DIR__) . '/views/auth/forgot_password.php',
    '/reset-password' => dirname(__DIR__) . '/views/auth/reset_password.php',
    '/select-company' => dirname(__DIR__) . '/views/auth/select_company.php',
    '/dashboard' => dirname(__DIR__) . '/views/dashboard.php',
    '/invoices' => dirname(__DIR__) . '/views/sales/invoices.php',
    '/create-invoice' => dirname(__DIR__) . '/views/sales/create_invoice.php',
    '/quotations' => dirname(__DIR__) . '/views/sales/quotations.php',
    '/create-quotation' => dirname(__DIR__) . '/views/sales/create_quotation.php',
    '/recurring-invoices' => dirname(__DIR__) . '/views/sales/recurring_invoices.php',
    '/credit-notes' => dirname(__DIR__) . '/views/sales/credit_notes.php',
    '/debit-notes' => dirname(__DIR__) . '/views/purchases/debit_notes.php',
    '/purchases' => dirname(__DIR__) . '/views/purchases/purchases.php',
    '/inventory' => dirname(__DIR__) . '/views/inventory/index.php',
    '/parties' => dirname(__DIR__) . '/views/parties/index.php',
    '/payments' => dirname(__DIR__) . '/views/payments/index.php',
    '/expenses' => dirname(__DIR__) . '/views/expenses/index.php',
    '/accounting' => dirname(__DIR__) . '/views/accounting/index.php',
    '/reports' => dirname(__DIR__) . '/views/reports/index.php',
    '/users' => dirname(__DIR__) . '/views/settings/users.php',
    '/audit-logs' => dirname(__DIR__) . '/views/settings/audit_logs.php',
    '/settings' => dirname(__DIR__) . '/views/settings/index.php',
    '/print-invoice' => dirname(__DIR__) . '/views/sales/print_invoice.php',
    '/invoices/print' => dirname(__DIR__) . '/views/sales/print_invoice.php',
    '/print-quotation' => dirname(__DIR__) . '/views/sales/print_quotation.php',
    '/quotations/print' => dirname(__DIR__) . '/views/sales/print_quotation.php',
    '/print-receipt' => dirname(__DIR__) . '/views/payments/print_receipt.php',
    '/payments/print' => dirname(__DIR__) . '/views/payments/print_receipt.php',
    '/print-order' => dirname(__DIR__) . '/views/sales/print_order.php',
    '/sales-orders/print' => dirname(__DIR__) . '/views/sales/print_order.php',
    '/print-challan' => dirname(__DIR__) . '/views/sales/print_challan.php',
    '/challans/print' => dirname(__DIR__) . '/views/sales/print_challan.php',
    '/print-credit-note' => dirname(__DIR__) . '/views/sales/print_credit_note.php',
    '/credit-notes/print' => dirname(__DIR__) . '/views/sales/print_credit_note.php',
    '/print-debit-note' => dirname(__DIR__) . '/views/purchases/print_debit_note.php',
    '/debit-notes/print' => dirname(__DIR__) . '/views/purchases/print_debit_note.php',
    '/print-purchase' => dirname(__DIR__) . '/views/purchases/print_purchase.php',
    '/purchases/print' => dirname(__DIR__) . '/views/purchases/print_purchase.php',
];
