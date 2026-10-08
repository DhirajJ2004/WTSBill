<?php

namespace App\Reporting\Types;

class ReportRegistry
{
    // Categories
    public const CAT_SALES = 'Sales';
    public const CAT_PURCHASES = 'Purchases';
    public const CAT_INVENTORY = 'Inventory';
    public const CAT_CUSTOMERS = 'Customers';
    public const CAT_SUPPLIERS = 'Suppliers';
    public const CAT_PAYMENTS = 'Payments';
    public const CAT_EXPENSES = 'Expenses';
    public const CAT_ACCOUNTING = 'Accounting';
    public const CAT_GST = 'GST & Tax';
    public const CAT_BANKING = 'Banking';
    public const CAT_ANALYTICS = 'Business Analytics';

    // Report Keys
    public const SALES_SUMMARY = 'SALES_SUMMARY';
    public const SALES_REGISTER = 'SALES_REGISTER';
    public const SALES_DETAIL = 'SALES_DETAIL';
    public const SALES_BY_PRODUCT = 'SALES_BY_PRODUCT';
    public const SALES_BY_CUSTOMER = 'SALES_BY_CUSTOMER';
    public const SALES_BY_CATEGORY = 'SALES_BY_CATEGORY';
    public const SALES_BY_BRANCH = 'SALES_BY_BRANCH';
    public const SALES_BY_PAYMENT_MODE = 'SALES_BY_PAYMENT_MODE';
    public const SALES_RETURN = 'SALES_RETURN';
    public const SALES_GROWTH = 'SALES_GROWTH';

    public const PURCHASE_SUMMARY = 'PURCHASE_SUMMARY';
    public const PURCHASE_REGISTER = 'PURCHASE_REGISTER';
    public const PURCHASE_DETAIL = 'PURCHASE_DETAIL';
    public const PURCHASE_BY_PRODUCT = 'PURCHASE_BY_PRODUCT';
    public const PURCHASE_BY_SUPPLIER = 'PURCHASE_BY_SUPPLIER';

    public const CUSTOMER_OUTSTANDING = 'CUSTOMER_OUTSTANDING';
    public const RECEIVABLES = 'RECEIVABLES';
    public const RECEIVABLE_AGING = 'RECEIVABLE_AGING';
    public const CUSTOMER_PROFITABILITY = 'CUSTOMER_PROFITABILITY';

    public const SUPPLIER_OUTSTANDING = 'SUPPLIER_OUTSTANDING';
    public const PAYABLES = 'PAYABLES';
    public const PAYABLE_AGING = 'PAYABLE_AGING';

    public const PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';
    public const PAYMENT_MADE = 'PAYMENT_MADE';
    public const PAYMENT_SUMMARY = 'PAYMENT_SUMMARY';
    public const PAYMENT_MODE_REPORT = 'PAYMENT_MODE_REPORT';
    public const UNALLOCATED_PAYMENTS = 'UNALLOCATED_PAYMENTS';

    public const INVENTORY_SUMMARY = 'INVENTORY_SUMMARY';
    public const STOCK_MOVEMENT = 'STOCK_MOVEMENT';
    public const STOCK_VALUATION = 'STOCK_VALUATION';
    public const STOCK_LEDGER = 'STOCK_LEDGER';
    public const LOW_STOCK = 'LOW_STOCK';
    public const FAST_MOVING = 'FAST_MOVING';
    public const DEAD_STOCK = 'DEAD_STOCK';
    public const BATCH_EXPIRY = 'BATCH_EXPIRY';
    public const WAREHOUSE_STOCK = 'WAREHOUSE_STOCK';

    public const EXPENSE_SUMMARY = 'EXPENSE_SUMMARY';
    public const EXPENSE_TREND = 'EXPENSE_TREND';
    public const RECURRING_EXPENSE = 'RECURRING_EXPENSE';

    public const TRIAL_BALANCE = 'TRIAL_BALANCE';
    public const PROFIT_LOSS = 'PROFIT_LOSS';
    public const BALANCE_SHEET = 'BALANCE_SHEET';
    public const CASH_FLOW = 'CASH_FLOW';
    public const GENERAL_LEDGER = 'GENERAL_LEDGER';
    public const CUSTOMER_LEDGER = 'CUSTOMER_LEDGER';
    public const SUPPLIER_LEDGER = 'SUPPLIER_LEDGER';
    public const PERIOD_COMPARISON = 'PERIOD_COMPARISON';
    public const DAY_BOOK = 'DAY_BOOK';
    public const JOURNAL_REGISTER = 'JOURNAL_REGISTER';

    public const GST_SUMMARY = 'GST_SUMMARY';
    public const GST_TAXABLE_SUMMARY = 'GST_TAXABLE_SUMMARY';
    public const GST_OUTPUT = 'GST_OUTPUT';
    public const GST_INPUT = 'GST_INPUT';
    public const HSN_SUMMARY = 'HSN_SUMMARY';
    public const GSTR1_SUMMARY = 'GSTR1_SUMMARY';
    public const GSTR3B_SUMMARY = 'GSTR3B_SUMMARY';
    public const GST_RECONCILIATION = 'GST_RECONCILIATION';

    public const BANK_SUMMARY = 'BANK_SUMMARY';
    public const BANK_BOOK = 'BANK_BOOK';
    public const CASH_BOOK = 'CASH_BOOK';
    public const BANK_RECONCILIATION = 'BANK_RECONCILIATION';
    public const CHEQUE_REGISTER = 'CHEQUE_REGISTER';

    public const MANAGEMENT_DASHBOARD = 'MANAGEMENT_DASHBOARD';
    public const BUSINESS_PERFORMANCE = 'BUSINESS_PERFORMANCE';
    public const MONTHLY_COMPARISON = 'MONTHLY_COMPARISON';
    public const PROFITABILITY = 'PROFITABILITY';
    public const TOP_PRODUCTS = 'TOP_PRODUCTS';
    public const TOP_CUSTOMERS = 'TOP_CUSTOMERS';
    public const SALES_VS_PURCHASE = 'SALES_VS_PURCHASE';
    public const REVENUE_VS_EXPENSE = 'REVENUE_VS_EXPENSE';
    public const QUOTATION_CONVERSION = 'QUOTATION_CONVERSION';
    public const ORDER_SUMMARY = 'ORDER_SUMMARY';

    public static function getAllReports(): array
    {
        return [
            // Sales
            self::SALES_SUMMARY => [
                'key' => self::SALES_SUMMARY,
                'title' => 'Sales Summary',
                'category' => self::CAT_SALES,
                'description' => 'Comprehensive summary of gross sales, net revenue, GST collected, discounts, returns, and invoice counts.',
                'filters' => ['date_range', 'branch', 'customer', 'status'],
                'default_charts' => ['sales_trend', 'sales_by_status'],
                'permission' => 'reports.sales',
            ],
            self::SALES_DETAIL => [
                'key' => self::SALES_DETAIL,
                'title' => 'Sales Detail Report',
                'category' => self::CAT_SALES,
                'description' => 'Line-item level sales register with rates, quantities, taxes, and customer traceability.',
                'filters' => ['date_range', 'branch', 'customer', 'product', 'category', 'status'],
                'default_charts' => ['daily_sales_trend'],
                'permission' => 'reports.sales',
            ],
            self::SALES_BY_PRODUCT => [
                'key' => self::SALES_BY_PRODUCT,
                'title' => 'Sales by Product',
                'category' => self::CAT_SALES,
                'description' => 'Product sales performance, quantities sold, average selling prices, and revenue contributions.',
                'filters' => ['date_range', 'branch', 'category', 'product'],
                'default_charts' => ['top_products_revenue', 'top_products_qty'],
                'permission' => 'reports.sales',
            ],
            self::SALES_BY_CUSTOMER => [
                'key' => self::SALES_BY_CUSTOMER,
                'title' => 'Sales by Customer',
                'category' => self::CAT_SALES,
                'description' => 'Customer-wise sales aggregates, return counts, net billing, payments received, and outstanding balances.',
                'filters' => ['date_range', 'branch', 'customer'],
                'default_charts' => ['top_customers_sales'],
                'permission' => 'reports.sales',
            ],
            self::SALES_BY_CATEGORY => [
                'key' => self::SALES_BY_CATEGORY,
                'title' => 'Sales by Category',
                'category' => self::CAT_SALES,
                'description' => 'Product category revenue breakdown, discount impact, and tax contributions.',
                'filters' => ['date_range', 'branch'],
                'default_charts' => ['category_share_donut'],
                'permission' => 'reports.sales',
            ],
            self::SALES_RETURN => [
                'key' => self::SALES_RETURN,
                'title' => 'Sales Return (Credit Notes)',
                'category' => self::CAT_SALES,
                'description' => 'Summary of returned products, credit notes issued, GST reversals, and return reasons.',
                'filters' => ['date_range', 'branch', 'customer'],
                'default_charts' => ['return_reasons_donut'],
                'permission' => 'reports.sales',
            ],

            // Purchases
            self::PURCHASE_SUMMARY => [
                'key' => self::PURCHASE_SUMMARY,
                'title' => 'Purchase Summary',
                'category' => self::CAT_PURCHASES,
                'description' => 'Total gross purchases, returns, input tax credit, and supplier invoice counts.',
                'filters' => ['date_range', 'branch', 'supplier', 'status'],
                'default_charts' => ['purchase_trend'],
                'permission' => 'reports.purchase',
            ],
            self::PURCHASE_DETAIL => [
                'key' => self::PURCHASE_DETAIL,
                'title' => 'Purchase Detail Report',
                'category' => self::CAT_PURCHASES,
                'description' => 'Detailed vendor bills with line items, purchase rates, taxes, and vendor invoice numbers.',
                'filters' => ['date_range', 'branch', 'supplier', 'product'],
                'default_charts' => ['daily_purchase_trend'],
                'permission' => 'reports.purchase',
            ],
            self::PURCHASE_BY_PRODUCT => [
                'key' => self::PURCHASE_BY_PRODUCT,
                'title' => 'Purchase by Product',
                'category' => self::CAT_PURCHASES,
                'description' => 'Product-wise procurement quantities, total spend, average purchase prices, and input GST.',
                'filters' => ['date_range', 'branch', 'product'],
                'default_charts' => ['top_purchased_products'],
                'permission' => 'reports.purchase',
            ],
            self::PURCHASE_BY_SUPPLIER => [
                'key' => self::PURCHASE_BY_SUPPLIER,
                'title' => 'Purchase by Supplier',
                'category' => self::CAT_PURCHASES,
                'description' => 'Vendor-wise purchase aggregates, return adjustments, payments made, and outstanding payables.',
                'filters' => ['date_range', 'branch', 'supplier'],
                'default_charts' => ['top_suppliers_spend'],
                'permission' => 'reports.purchase',
            ],

            // Payments & Receivables
            self::PAYMENT_RECEIVED => [
                'key' => self::PAYMENT_RECEIVED,
                'title' => 'Payment Received Register',
                'category' => self::CAT_PAYMENTS,
                'description' => 'Inward customer payments broken down by mode (UPI, Cash, Bank, Cheque, Gateway).',
                'filters' => ['date_range', 'branch', 'customer', 'payment_mode'],
                'default_charts' => ['payment_modes_received'],
                'permission' => 'reports.view',
            ],
            self::PAYMENT_MADE => [
                'key' => self::PAYMENT_MADE,
                'title' => 'Payment Made Register',
                'category' => self::CAT_PAYMENTS,
                'description' => 'Outward vendor payments and expense disbursements with bank/cash account mapping.',
                'filters' => ['date_range', 'branch', 'supplier', 'payment_mode'],
                'default_charts' => ['payment_modes_made'],
                'permission' => 'reports.view',
            ],
            self::RECEIVABLES => [
                'key' => self::RECEIVABLES,
                'title' => 'Accounts Receivable Aging',
                'category' => self::CAT_PAYMENTS,
                'description' => 'Outstanding customer balances bucketed by age (Not Due, 0-30, 31-60, 61-90, 90+ days).',
                'filters' => ['branch', 'customer', 'as_of_date'],
                'default_charts' => ['receivables_aging_bar'],
                'permission' => 'reports.view',
            ],
            self::PAYABLES => [
                'key' => self::PAYABLES,
                'title' => 'Accounts Payable Aging',
                'category' => self::CAT_PAYMENTS,
                'description' => 'Outstanding supplier obligations bucketed by age with due date tracking.',
                'filters' => ['branch', 'supplier', 'as_of_date'],
                'default_charts' => ['payables_aging_bar'],
                'permission' => 'reports.view',
            ],

            // Inventory
            self::INVENTORY_SUMMARY => [
                'key' => self::INVENTORY_SUMMARY,
                'title' => 'Inventory Overview & Summary',
                'category' => self::CAT_INVENTORY,
                'description' => 'Total catalog items, stock units, valuation, low stock items, out of stock, and expiring batches.',
                'filters' => ['branch', 'warehouse', 'category'],
                'default_charts' => ['stock_by_category_donut'],
                'permission' => 'reports.inventory',
            ],
            self::STOCK_MOVEMENT => [
                'key' => self::STOCK_MOVEMENT,
                'title' => 'Stock Movement (Item Register)',
                'category' => self::CAT_INVENTORY,
                'description' => 'Authoritative inventory movements (Purchase, Sale, Transfer, Adjustment, Return) with running balance.',
                'filters' => ['date_range', 'warehouse', 'product', 'movement_type'],
                'default_charts' => ['stock_in_out_bar'],
                'permission' => 'reports.inventory',
            ],
            self::STOCK_VALUATION => [
                'key' => self::STOCK_VALUATION,
                'title' => 'Stock Valuation Report',
                'category' => self::CAT_INVENTORY,
                'description' => 'Inventory valuation based on weighted average purchase costs and current stock on hand.',
                'filters' => ['warehouse', 'category'],
                'default_charts' => ['valuation_by_category'],
                'permission' => 'reports.inventory',
            ],
            self::LOW_STOCK => [
                'key' => self::LOW_STOCK,
                'title' => 'Low Stock & Reorder Alert',
                'category' => self::CAT_INVENTORY,
                'description' => 'Products with quantity at or below configured minimum reorder levels.',
                'filters' => ['warehouse', 'category'],
                'default_charts' => ['shortage_bar'],
                'permission' => 'reports.inventory',
            ],
            self::DEAD_STOCK => [
                'key' => self::DEAD_STOCK,
                'title' => 'Dead Stock / Non-Moving Items',
                'category' => self::CAT_INVENTORY,
                'description' => 'Inventory items with zero sales or movements over 30, 60, 90, or 180 days.',
                'filters' => ['warehouse', 'days_threshold'],
                'default_charts' => ['dead_stock_value_bar'],
                'permission' => 'reports.inventory',
            ],
            self::BATCH_EXPIRY => [
                'key' => self::BATCH_EXPIRY,
                'title' => 'Batch Expiry Schedule',
                'category' => self::CAT_INVENTORY,
                'description' => 'Track batch numbers, manufacturing dates, and expiring goods categorized into Expired, Expiring Soon, and Safe.',
                'filters' => ['warehouse', 'expiry_window_days'],
                'default_charts' => ['expiry_status_donut'],
                'permission' => 'reports.inventory',
            ],
            self::WAREHOUSE_STOCK => [
                'key' => self::WAREHOUSE_STOCK,
                'title' => 'Warehouse Stock Comparison',
                'category' => self::CAT_INVENTORY,
                'description' => 'Comparative breakdown of stock quantities and valuations across multiple warehouses.',
                'filters' => ['warehouse'],
                'default_charts' => ['warehouse_stock_bar'],
                'permission' => 'reports.inventory',
            ],

            // Expenses
            self::EXPENSE_SUMMARY => [
                'key' => self::EXPENSE_SUMMARY,
                'title' => 'Expense Summary',
                'category' => self::CAT_EXPENSES,
                'description' => 'Category-wise operational expenditures, vendor disbursements, and monthly expense trends.',
                'filters' => ['date_range', 'branch', 'category'],
                'default_charts' => ['expense_by_category_donut', 'expense_trend_line'],
                'permission' => 'reports.financial',
            ],
            self::RECURRING_EXPENSE => [
                'key' => self::RECURRING_EXPENSE,
                'title' => 'Recurring Expenses',
                'category' => self::CAT_EXPENSES,
                'description' => 'Fixed and recurring expenses (Rent, Utilities, Subscriptions) with frequency schedules.',
                'filters' => ['branch', 'status'],
                'default_charts' => ['recurring_by_category'],
                'permission' => 'reports.financial',
            ],

            // Accounting
            self::TRIAL_BALANCE => [
                'key' => self::TRIAL_BALANCE,
                'title' => 'Trial Balance',
                'category' => self::CAT_ACCOUNTING,
                'description' => 'Authoritative mathematical invariant verify: Total Debits must equal Total Credits across all ledgers.',
                'filters' => ['date_range', 'branch'],
                'default_charts' => ['debits_vs_credits_bar'],
                'permission' => 'reports.financial',
            ],
            self::PROFIT_LOSS => [
                'key' => self::PROFIT_LOSS,
                'title' => 'Profit & Loss Statement',
                'category' => self::CAT_ACCOUNTING,
                'description' => 'Revenue, Cost of Goods Sold, Gross Profit, Operating Expenses, and Net Profit from double-entry ledgers.',
                'filters' => ['date_range', 'branch', 'comparison_period'],
                'default_charts' => ['pnl_waterfall_bar', 'net_profit_trend'],
                'permission' => 'reports.financial',
            ],
            self::BALANCE_SHEET => [
                'key' => self::BALANCE_SHEET,
                'title' => 'Balance Sheet',
                'category' => self::CAT_ACCOUNTING,
                'description' => 'Assets, Liabilities, and Owner Equity satisfying: Assets = Liabilities + Equity.',
                'filters' => ['as_of_date', 'branch'],
                'default_charts' => ['assets_vs_liabilities_donut'],
                'permission' => 'reports.financial',
            ],
            self::CASH_FLOW => [
                'key' => self::CASH_FLOW,
                'title' => 'Cash Flow Statement',
                'category' => self::CAT_ACCOUNTING,
                'description' => 'Inflows and outflows categorized into Operating, Investing, and Financing activities.',
                'filters' => ['date_range', 'branch'],
                'default_charts' => ['cash_flow_activities_bar'],
                'permission' => 'reports.financial',
            ],
            self::GENERAL_LEDGER => [
                'key' => self::GENERAL_LEDGER,
                'title' => 'General Ledger',
                'category' => self::CAT_ACCOUNTING,
                'description' => 'Detailed journal postings per account with running balance and transaction traceability.',
                'filters' => ['date_range', 'account_id', 'branch'],
                'default_charts' => ['ledger_running_balance_line'],
                'permission' => 'reports.financial',
            ],
            self::CUSTOMER_LEDGER => [
                'key' => self::CUSTOMER_LEDGER,
                'title' => 'Customer Ledger Statement',
                'category' => self::CAT_ACCOUNTING,
                'description' => 'Individual customer account statement with invoices, credit notes, receipts, and running balance.',
                'filters' => ['date_range', 'customer_id'],
                'default_charts' => ['customer_running_balance_line'],
                'permission' => 'reports.financial',
            ],
            self::SUPPLIER_LEDGER => [
                'key' => self::SUPPLIER_LEDGER,
                'title' => 'Supplier Ledger Statement',
                'category' => self::CAT_ACCOUNTING,
                'description' => 'Individual supplier account statement with purchase bills, debit notes, payments, and running balance.',
                'filters' => ['date_range', 'supplier_id'],
                'default_charts' => ['supplier_running_balance_line'],
                'permission' => 'reports.financial',
            ],
            self::PERIOD_COMPARISON => [
                'key' => self::PERIOD_COMPARISON,
                'title' => 'Accounting Period Comparison',
                'category' => self::CAT_ACCOUNTING,
                'description' => 'Comparative financial performance across months, quarters, or financial years.',
                'filters' => ['period_type', 'branch'],
                'default_charts' => ['period_comparison_bar'],
                'permission' => 'reports.financial',
            ],

            // GST
            self::GST_SUMMARY => [
                'key' => self::GST_SUMMARY,
                'title' => 'GST Summary & Net Tax Liability',
                'category' => self::CAT_GST,
                'description' => 'Output GST on sales, Input GST on purchases, and Net GST payable split by CGST, SGST, IGST, and Cess.',
                'filters' => ['date_range', 'branch'],
                'default_charts' => ['gst_output_vs_input_bar'],
                'permission' => 'reports.gst',
            ],
            self::GST_OUTPUT => [
                'key' => self::GST_OUTPUT,
                'title' => 'GST Output (Sales) Register',
                'category' => self::CAT_GST,
                'description' => 'Outward supply tax liability register categorized by B2B, B2C Large, B2C Small, and SEZ.',
                'filters' => ['date_range', 'branch', 'tax_rate'],
                'default_charts' => ['output_tax_by_rate_donut'],
                'permission' => 'reports.gst',
            ],
            self::GST_INPUT => [
                'key' => self::GST_INPUT,
                'title' => 'GST Input (Purchases) Register',
                'category' => self::CAT_GST,
                'description' => 'Inward supply input tax credit (ITC) register with vendor GSTIN and tax breakdowns.',
                'filters' => ['date_range', 'branch', 'tax_rate'],
                'default_charts' => ['input_tax_by_rate_donut'],
                'permission' => 'reports.gst',
            ],
            self::HSN_SUMMARY => [
                'key' => self::HSN_SUMMARY,
                'title' => 'HSN/SAC Tax Summary',
                'category' => self::CAT_GST,
                'description' => 'HSN and SAC code summaries with total taxable values and tax split for GSTR-1 Table 12.',
                'filters' => ['date_range', 'branch'],
                'default_charts' => ['top_hsn_taxable_bar'],
                'permission' => 'reports.gst',
            ],
            self::GSTR1_SUMMARY => [
                'key' => self::GSTR1_SUMMARY,
                'title' => 'GSTR-1 Preparation Summary',
                'category' => self::CAT_GST,
                'description' => 'Prepared GSTR-1 tables (4-B2B, 5-B2CL, 7-B2CS, 8-Nil/Exempt, 9-CDNR, 12-HSN, 13-Docs).',
                'filters' => ['date_range', 'branch'],
                'default_charts' => ['gstr1_sections_bar'],
                'permission' => 'reports.gst',
            ],
            self::GSTR3B_SUMMARY => [
                'key' => self::GSTR3B_SUMMARY,
                'title' => 'GSTR-3B Preparation Summary',
                'category' => self::CAT_GST,
                'description' => 'Prepared GSTR-3B tables (3.1 Outward supplies, 4 Eligible ITC, 5.1 Late fee/Interest).',
                'filters' => ['date_range', 'branch'],
                'default_charts' => ['gstr3b_tax_composition_bar'],
                'permission' => 'reports.gst',
            ],
            self::GST_RECONCILIATION => [
                'key' => self::GST_RECONCILIATION,
                'title' => 'GST Reconciliation (Books vs GSTR-2B)',
                'category' => self::CAT_GST,
                'description' => 'Compare purchase books against portal GSTR-2B records: Matched, Mismatched, Missing in Books, Missing in Portal.',
                'filters' => ['date_range', 'match_status'],
                'default_charts' => ['reconciliation_match_status_donut'],
                'permission' => 'reports.gst',
            ],

            // Banking
            self::BANK_SUMMARY => [
                'key' => self::BANK_SUMMARY,
                'title' => 'Banking & Cash Overview',
                'category' => self::CAT_BANKING,
                'description' => 'Live balances across all bank accounts and cash drawers with masked account numbers.',
                'filters' => ['branch'],
                'default_charts' => ['bank_balances_bar'],
                'permission' => 'reports.view',
            ],
            self::BANK_BOOK => [
                'key' => self::BANK_BOOK,
                'title' => 'Bank Book',
                'category' => self::CAT_BANKING,
                'description' => 'Detailed bank account ledger with deposits, withdrawals, cheques, and running balances.',
                'filters' => ['date_range', 'bank_account_id'],
                'default_charts' => ['bank_running_balance_line'],
                'permission' => 'reports.view',
            ],
            self::CASH_BOOK => [
                'key' => self::CASH_BOOK,
                'title' => 'Cash Book',
                'category' => self::CAT_BANKING,
                'description' => 'Cash register with daily cash collections, direct payments, petty cash expenses, and drawer closing balances.',
                'filters' => ['date_range', 'branch'],
                'default_charts' => ['cash_flow_daily_bar'],
                'permission' => 'reports.view',
            ],
            self::BANK_RECONCILIATION => [
                'key' => self::BANK_RECONCILIATION,
                'title' => 'Bank Reconciliation Statement',
                'category' => self::CAT_BANKING,
                'description' => 'Book balance reconciled against statement balance with uncleared deposits and unpresented cheques.',
                'filters' => ['bank_account_id', 'as_of_date'],
                'default_charts' => ['reconciliation_bridge_bar'],
                'permission' => 'reports.view',
            ],
            self::CHEQUE_REGISTER => [
                'key' => self::CHEQUE_REGISTER,
                'title' => 'Cheque Register (Received & Issued)',
                'category' => self::CAT_BANKING,
                'description' => 'Complete cheque lifecycle tracking: Received, Deposited, Cleared, Bounced, and Cancelled.',
                'filters' => ['date_range', 'cheque_type', 'status'],
                'default_charts' => ['cheque_status_donut'],
                'permission' => 'reports.view',
            ],

            // Business Analytics
            self::BUSINESS_PERFORMANCE => [
                'key' => self::BUSINESS_PERFORMANCE,
                'title' => 'Executive Business Performance',
                'category' => self::CAT_ANALYTICS,
                'description' => 'Executive KPI dashboard: Sales, Purchases, Gross Profit, Operating Expenses, Net Margin, and Working Capital.',
                'filters' => ['date_range', 'branch', 'comparison_period'],
                'default_charts' => ['revenue_vs_expenses_bar', 'working_capital_trend'],
                'permission' => 'reports.financial',
            ],
            self::MONTHLY_COMPARISON => [
                'key' => self::MONTHLY_COMPARISON,
                'title' => 'Month-on-Month 12 Month Trend',
                'category' => self::CAT_ANALYTICS,
                'description' => '12-month rolling comparative analysis of revenue, costs, net profit, receivables, and payables.',
                'filters' => ['branch', 'financial_year'],
                'default_charts' => ['mom_sales_vs_purchases_line', 'mom_profit_trend'],
                'permission' => 'reports.financial',
            ],
            self::PROFITABILITY => [
                'key' => self::PROFITABILITY,
                'title' => 'Profitability Analysis (Product / Customer / Category)',
                'category' => self::CAT_ANALYTICS,
                'description' => 'Gross margins and profitability computed from reliable transaction selling prices and purchase costs.',
                'filters' => ['date_range', 'branch', 'dimension'],
                'default_charts' => ['margin_percentage_bar'],
                'permission' => 'reports.financial',
            ],
            self::TOP_PRODUCTS => [
                'key' => self::TOP_PRODUCTS,
                'title' => 'Top Performing Products',
                'category' => self::CAT_ANALYTICS,
                'description' => 'Rank best-selling items by revenue, unit quantity, or gross profit contribution.',
                'filters' => ['date_range', 'branch', 'limit', 'rank_by'],
                'default_charts' => ['top_products_bar'],
                'permission' => 'reports.sales',
            ],
            self::TOP_CUSTOMERS => [
                'key' => self::TOP_CUSTOMERS,
                'title' => 'Top Customers by Value',
                'category' => self::CAT_ANALYTICS,
                'description' => 'Rank customers by total revenue, order frequency, and payment turnaround.',
                'filters' => ['date_range', 'branch', 'limit', 'rank_by'],
                'default_charts' => ['top_customers_bar'],
                'permission' => 'reports.sales',
            ],
            self::SALES_VS_PURCHASE => [
                'key' => self::SALES_VS_PURCHASE,
                'title' => 'Sales vs Purchase Comparison',
                'category' => self::CAT_ANALYTICS,
                'description' => 'Track business gross trading margins by comparing sales revenues against procurement spend.',
                'filters' => ['date_range', 'branch'],
                'default_charts' => ['sales_vs_purchase_trend_line'],
                'permission' => 'reports.financial',
            ],
            self::REVENUE_VS_EXPENSE => [
                'key' => self::REVENUE_VS_EXPENSE,
                'title' => 'Revenue vs Total Expenses',
                'category' => self::CAT_ANALYTICS,
                'description' => 'Track operating margin health by comparing top-line revenue against cost of sales and overheads.',
                'filters' => ['date_range', 'branch'],
                'default_charts' => ['revenue_vs_expense_area'],
                'permission' => 'reports.financial',
            ],
        ];
    }

    public static function getReportMeta(string $reportKey): ?array
    {
        $all = self::getAllReports();
        if (isset($all[$reportKey])) {
            return $all[$reportKey];
        }

        // Dynamic fallback metadata
        $title = ucwords(strtolower(str_replace('_', ' ', $reportKey)));
        return [
            'key' => $reportKey,
            'title' => $title,
            'category' => self::CAT_ANALYTICS,
            'description' => "Detailed report view for {$title}.",
            'filters' => ['date_range', 'branch'],
            'default_charts' => [],
            'permission' => 'reports.view',
        ];
    }

    public static function getCategories(): array
    {
        return [
            self::CAT_SALES => 'Sales & Invoices',
            self::CAT_PURCHASES => 'Procurement & Bills',
            self::CAT_PAYMENTS => 'Payments & Receivables',
            self::CAT_INVENTORY => 'Inventory & Stock',
            self::CAT_EXPENSES => 'Expenses & Overheads',
            self::CAT_ACCOUNTING => 'Financial Statements & Ledgers',
            self::CAT_GST => 'GST & Tax Compliance',
            self::CAT_BANKING => 'Cash & Bank Management',
            self::CAT_ANALYTICS => 'Business Analytics & Insights',
        ];
    }
}
