<?php

namespace App\Imports\Templates;

class TemplateGeneratorService
{
    private static array $templates = [
        'CUSTOMERS' => [
            'headers' => [
                'customer_name', 'phone', 'email', 'gstin', 'pan', 'tax_type', 'state',
                'billing_address', 'shipping_address', 'opening_balance', 'credit_limit', 'credit_period_days'
            ],
            'sample' => [
                'Apex Enterprises', '9876543210', 'apex@example.com', '27ABCDE1234F1Z5', 'ABCDE1234F', 'B2B', 'Maharashtra',
                'Shop 12, Industrial Estate, Pune', 'Shop 12, Industrial Estate, Pune', '15000.00', '100000.00', '30'
            ],
            'required' => ['customer_name'],
            'descriptions' => [
                'customer_name' => 'Full legal name or business name (Required)',
                'phone' => '10-digit mobile number (Unique identifier)',
                'gstin' => '15-character valid GSTIN for registered businesses',
                'state' => 'Place of supply state',
                'opening_balance' => 'Initial receivable balance due from customer',
            ],
        ],
        'SUPPLIERS' => [
            'headers' => [
                'supplier_name', 'phone', 'email', 'gstin', 'pan', 'state',
                'address', 'opening_balance', 'bank_name', 'bank_account_number', 'ifsc_code'
            ],
            'sample' => [
                'National Logistics Ltd', '9822001122', 'accounts@nationallogistics.com', '27AABCN1234P1ZV', 'AABCN1234P', 'Maharashtra',
                'Plot 40, MIDC, Mumbai', '25000.00', 'HDFC Bank', '50200012345678', 'HDFC0000123'
            ],
            'required' => ['supplier_name'],
            'descriptions' => [
                'supplier_name' => 'Supplier / Vendor business name (Required)',
                'phone' => '10-digit phone number',
                'gstin' => '15-character valid GSTIN',
                'opening_balance' => 'Initial payable balance due to supplier',
            ],
        ],
        'PRODUCTS' => [
            'headers' => [
                'name', 'sku', 'barcode', 'category', 'unit', 'purchase_price',
                'selling_price', 'mrp', 'gst_rate', 'hsn_code', 'min_stock_level', 'opening_stock'
            ],
            'sample' => [
                'Wireless Ergonomic Mouse', 'SKU-MOU-01', '8901234567890', 'Electronics', 'PCS', '450.00',
                '850.00', '999.00', '18.00', '84716060', '10', '50'
            ],
            'required' => ['name', 'selling_price'],
            'descriptions' => [
                'name' => 'Product name (Required)',
                'sku' => 'Stock Keeping Unit (Unique identifier)',
                'selling_price' => 'Standard selling rate before tax (Required)',
                'gst_rate' => 'Applicable GST percentage (e.g. 0, 5, 12, 18, 28)',
                'opening_stock' => 'Initial available quantity',
            ],
        ],
        'CATEGORIES' => [
            'headers' => ['name', 'parent_category', 'description'],
            'sample' => ['Electronics', '', 'Electronic accessories and components'],
            'required' => ['name'],
            'descriptions' => [
                'name' => 'Category name (Required, Unique)',
                'parent_category' => 'Optional parent category for hierarchical grouping',
            ],
        ],
        'OPENING_BALANCES' => [
            'headers' => ['account_code', 'account_name', 'debit_amount', 'credit_amount', 'opening_date', 'reference_notes'],
            'sample' => ['1200', 'Accounts Receivable', '50000.00', '0.00', '2026-04-01', 'FY26-27 Opening Balance'],
            'required' => ['account_code', 'opening_date'],
            'descriptions' => [
                'account_code' => 'Chart of Accounts code (e.g. 1000, 1100, 1200, 2000, 3000)',
                'debit_amount' => 'Debit opening balance (or 0)',
                'credit_amount' => 'Credit opening balance (or 0)',
                'opening_date' => 'Opening date (YYYY-MM-DD)',
            ],
        ],
        'OPENING_STOCK' => [
            'headers' => ['sku', 'product_name', 'warehouse_code', 'quantity', 'unit_cost', 'as_of_date'],
            'sample' => ['SKU-MOU-01', 'Wireless Ergonomic Mouse', 'MAIN', '100', '450.00', '2026-04-01'],
            'required' => ['sku', 'warehouse_code', 'quantity', 'unit_cost'],
            'descriptions' => [
                'sku' => 'Product SKU code (Required)',
                'warehouse_code' => 'Branch warehouse code (Required, e.g. MAIN)',
                'quantity' => 'Physical opening stock count (Required)',
                'unit_cost' => 'Valuation purchase cost per unit (Required)',
            ],
        ],
        'PRICE_LISTS' => [
            'headers' => ['price_list_name', 'sku', 'product_name', 'custom_price', 'min_quantity', 'max_quantity'],
            'sample' => ['Wholesale Price List', 'SKU-MOU-01', 'Wireless Ergonomic Mouse', '750.00', '10', '99'],
            'required' => ['price_list_name', 'sku', 'custom_price'],
            'descriptions' => [
                'price_list_name' => 'Name of the price list (Required)',
                'sku' => 'Product SKU code (Required)',
                'custom_price' => 'Special tier / list price',
            ],
        ],
    ];

    /**
     * Get template structure definition.
     */
    public static function getTemplate(string $dataType): array
    {
        $type = strtoupper($dataType);
        if (!isset(self::$templates[$type])) {
            throw new \InvalidArgumentException("Unsupported import data type: {$dataType}");
        }
        return self::$templates[$type];
    }

    /**
     * Generate downloadable CSV template string.
     */
    public static function generateCsvTemplate(string $dataType): string
    {
        $tpl = self::getTemplate($dataType);
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $tpl['headers']);
        fputcsv($fp, $tpl['sample']);
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }
}
