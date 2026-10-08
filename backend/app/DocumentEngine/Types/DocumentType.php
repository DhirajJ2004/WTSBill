<?php

namespace App\DocumentEngine\Types;

class DocumentType
{
    // Sales Documents
    public const SALES_INVOICE = 'SALES_INVOICE';
    public const QUOTATION = 'QUOTATION';
    public const SALES_ORDER = 'SALES_ORDER';
    public const DELIVERY_CHALLAN = 'DELIVERY_CHALLAN';
    public const CREDIT_NOTE = 'CREDIT_NOTE';

    // Purchase Documents
    public const PURCHASE_INVOICE = 'PURCHASE_INVOICE';
    public const PURCHASE_ORDER = 'PURCHASE_ORDER';
    public const DEBIT_NOTE = 'DEBIT_NOTE';

    // Payment Documents
    public const PAYMENT_RECEIPT = 'PAYMENT_RECEIPT';
    public const SUPPLIER_PAYMENT = 'SUPPLIER_PAYMENT';

    // Statements
    public const CUSTOMER_STATEMENT = 'CUSTOMER_STATEMENT';
    public const SUPPLIER_STATEMENT = 'SUPPLIER_STATEMENT';

    // Banking Documents
    public const CASH_BOOK = 'CASH_BOOK';
    public const BANK_BOOK = 'BANK_BOOK';
    public const CHEQUE_REGISTER = 'CHEQUE_REGISTER';
    public const BANK_RECONCILIATION = 'BANK_RECONCILIATION';

    // GST Documents
    public const GST_REPORT = 'GST_REPORT';
    public const GSTR1_SUMMARY = 'GSTR1_SUMMARY';
    public const GSTR3B_SUMMARY = 'GSTR3B_SUMMARY';

    // Financial Reports
    public const PROFIT_LOSS = 'PROFIT_LOSS';
    public const BALANCE_SHEET = 'BALANCE_SHEET';
    public const TRIAL_BALANCE = 'TRIAL_BALANCE';
    public const CASH_FLOW = 'CASH_FLOW';

    // Operational Reports
    public const SALES_REPORT = 'SALES_REPORT';
    public const PURCHASE_REPORT = 'PURCHASE_REPORT';
    public const INVENTORY_REPORT = 'INVENTORY_REPORT';

    public static function all(): array
    {
        return [
            self::SALES_INVOICE,
            self::QUOTATION,
            self::SALES_ORDER,
            self::DELIVERY_CHALLAN,
            self::CREDIT_NOTE,
            self::PURCHASE_INVOICE,
            self::PURCHASE_ORDER,
            self::DEBIT_NOTE,
            self::PAYMENT_RECEIPT,
            self::SUPPLIER_PAYMENT,
            self::CUSTOMER_STATEMENT,
            self::SUPPLIER_STATEMENT,
            self::CASH_BOOK,
            self::BANK_BOOK,
            self::CHEQUE_REGISTER,
            self::BANK_RECONCILIATION,
            self::GST_REPORT,
            self::GSTR1_SUMMARY,
            self::GSTR3B_SUMMARY,
            self::PROFIT_LOSS,
            self::BALANCE_SHEET,
            self::TRIAL_BALANCE,
            self::CASH_FLOW,
            self::SALES_REPORT,
            self::PURCHASE_REPORT,
            self::INVENTORY_REPORT,
        ];
    }

    public static function getMeta(string $type): array
    {
        $type = strtoupper($type);
        return match ($type) {
            self::SALES_INVOICE => [
                'title' => 'TAX INVOICE',
                'category' => 'SALES',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => true,
                'is_accounting_source' => true,
            ],
            self::QUOTATION => [
                'title' => 'QUOTATION / ESTIMATE',
                'category' => 'SALES',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => true,
                'watermark_text' => 'QUOTATION - NOT A TAX INVOICE',
                'is_accounting_source' => false,
            ],
            self::SALES_ORDER => [
                'title' => 'SALES ORDER',
                'category' => 'SALES',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => true,
                'is_accounting_source' => false,
            ],
            self::DELIVERY_CHALLAN => [
                'title' => 'DELIVERY CHALLAN',
                'category' => 'SALES',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            self::CREDIT_NOTE => [
                'title' => 'CREDIT NOTE',
                'category' => 'SALES',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => true,
                'is_accounting_source' => true,
            ],
            self::PURCHASE_INVOICE => [
                'title' => 'PURCHASE INVOICE',
                'category' => 'PURCHASES',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => true,
                'is_accounting_source' => true,
            ],
            self::PURCHASE_ORDER => [
                'title' => 'PURCHASE ORDER',
                'category' => 'PURCHASES',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => true,
                'is_accounting_source' => false,
            ],
            self::DEBIT_NOTE => [
                'title' => 'DEBIT NOTE',
                'category' => 'PURCHASES',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => true,
                'is_accounting_source' => true,
            ],
            self::PAYMENT_RECEIPT => [
                'title' => 'PAYMENT RECEIPT',
                'category' => 'PAYMENTS',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => false,
                'is_accounting_source' => true,
            ],
            self::CUSTOMER_STATEMENT => [
                'title' => 'CUSTOMER STATEMENT OF ACCOUNTS',
                'category' => 'STATEMENTS',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            self::SUPPLIER_STATEMENT => [
                'title' => 'SUPPLIER STATEMENT OF ACCOUNTS',
                'category' => 'STATEMENTS',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => true,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            self::CASH_BOOK => [
                'title' => 'CASH BOOK',
                'category' => 'BANKING',
                'default_paper_size' => 'A4',
                'default_orientation' => 'LANDSCAPE',
                'requires_buyer_seller' => false,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            self::BANK_BOOK => [
                'title' => 'BANK BOOK',
                'category' => 'BANKING',
                'default_paper_size' => 'A4',
                'default_orientation' => 'LANDSCAPE',
                'requires_buyer_seller' => false,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            self::CHEQUE_REGISTER => [
                'title' => 'CHEQUE REGISTER',
                'category' => 'BANKING',
                'default_paper_size' => 'A4',
                'default_orientation' => 'LANDSCAPE',
                'requires_buyer_seller' => false,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            self::BANK_RECONCILIATION => [
                'title' => 'BANK RECONCILIATION STATEMENT',
                'category' => 'BANKING',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => false,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            self::PROFIT_LOSS => [
                'title' => 'PROFIT & LOSS STATEMENT',
                'category' => 'FINANCIAL_REPORTS',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => false,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            self::BALANCE_SHEET => [
                'title' => 'BALANCE SHEET',
                'category' => 'FINANCIAL_REPORTS',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => false,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            self::TRIAL_BALANCE => [
                'title' => 'TRIAL BALANCE',
                'category' => 'FINANCIAL_REPORTS',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => false,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            self::CASH_FLOW => [
                'title' => 'CASH FLOW STATEMENT',
                'category' => 'FINANCIAL_REPORTS',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => false,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ],
            default => [
                'title' => strtoupper(str_replace('_', ' ', $type)),
                'category' => 'GENERAL',
                'default_paper_size' => 'A4',
                'default_orientation' => 'PORTRAIT',
                'requires_buyer_seller' => false,
                'requires_hsn' => false,
                'is_accounting_source' => false,
            ]
        };
    }
}
