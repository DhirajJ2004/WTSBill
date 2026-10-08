<?php

namespace App\Imports\Mappers;

use App\Imports\Templates\TemplateGeneratorService;

class ColumnAutoMapper
{
    private static array $aliases = [
        'customer_name' => ['customer name', 'client name', 'party name', 'customer', 'client', 'buyer', 'business name', 'company name'],
        'supplier_name' => ['supplier name', 'vendor name', 'vendor', 'supplier', 'seller', 'party name'],
        'phone' => ['phone', 'mobile', 'mobile number', 'phone number', 'contact', 'contact number', 'telephone', 'cell'],
        'email' => ['email', 'email address', 'e-mail', 'mail'],
        'gstin' => ['gstin', 'gst number', 'gst no', 'gst', 'tax number', 'vat no'],
        'pan' => ['pan', 'pan number', 'pan no'],
        'tax_type' => ['tax type', 'gst type', 'registration type', 'customer type', 'party type'],
        'state' => ['state', 'place of supply', 'pos', 'state name'],
        'billing_address' => ['billing address', 'address', 'address line 1', 'street address', 'bill address'],
        'shipping_address' => ['shipping address', 'delivery address', 'ship to address', 'ship address'],
        'opening_balance' => ['opening balance', 'initial balance', 'open bal', 'op bal', 'balance'],
        'credit_limit' => ['credit limit', 'max credit'],
        'credit_period_days' => ['credit period', 'credit period days', 'payment terms', 'due days'],
        'name' => ['name', 'product name', 'item name', 'item', 'title', 'description', 'product'],
        'sku' => ['sku', 'item code', 'product code', 'sku code', 'item no', 'code'],
        'barcode' => ['barcode', 'upc', 'ean', 'bar code'],
        'category' => ['category', 'category name', 'item group', 'group'],
        'unit' => ['unit', 'uom', 'measurement unit', 'unit of measure'],
        'purchase_price' => ['purchase price', 'cost price', 'buying rate', 'cost', 'buy rate', 'purchase rate'],
        'selling_price' => ['selling price', 'sale price', 'rate', 'price', 'unit price', 'sales rate'],
        'mrp' => ['mrp', 'maximum retail price', 'list price'],
        'gst_rate' => ['gst rate', 'tax rate', 'gst %', 'tax %', 'gst rate %', 'vat %'],
        'hsn_code' => ['hsn', 'hsn code', 'sac', 'sac code', 'hsn/sac'],
        'min_stock_level' => ['min stock', 'minimum stock', 'reorder level', 'min stock level', 'low stock threshold'],
        'opening_stock' => ['opening stock', 'initial stock', 'opening qty', 'initial quantity', 'op stock'],
        'warehouse_code' => ['warehouse code', 'warehouse', 'location', 'branch warehouse', 'store'],
        'quantity' => ['quantity', 'qty', 'units', 'count'],
        'unit_cost' => ['unit cost', 'cost per unit', 'rate', 'valuation rate'],
        'as_of_date' => ['date', 'as of date', 'opening date', 'entry date'],
        'account_code' => ['account code', 'code', 'ledger code', 'account no', 'gl code'],
        'account_name' => ['account name', 'ledger name', 'account', 'ledger'],
        'debit_amount' => ['debit', 'debit amount', 'dr', 'dr amount'],
        'credit_amount' => ['credit', 'credit amount', 'cr', 'cr amount'],
        'opening_date' => ['opening date', 'date', 'as on date'],
        'reference_notes' => ['reference notes', 'narration', 'notes', 'remarks', 'reference'],
        'price_list_name' => ['price list name', 'price list', 'tier name', 'tier'],
        'custom_price' => ['custom price', 'special price', 'tier price', 'rate'],
        'min_quantity' => ['min quantity', 'min qty', 'minimum quantity'],
        'max_quantity' => ['max quantity', 'max qty', 'maximum quantity'],
    ];

    /**
     * Auto-suggest column mappings given a list of uploaded headers.
     */
    public static function suggestMappings(string $dataType, array $uploadedHeaders): array
    {
        $template = TemplateGeneratorService::getTemplate($dataType);
        $expectedFields = $template['headers'];
        $mappings = [];

        foreach ($uploadedHeaders as $rawHeader) {
            $cleaned = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', ' ', (string)$rawHeader)));
            $matchedField = null;

            // 1. Direct exact or snake_case match
            $snake = strtolower(trim(str_replace(' ', '_', $cleaned)));
            if (in_array($snake, $expectedFields, true)) {
                $matchedField = $snake;
            }

            // 2. Alias lookup
            if (!$matchedField) {
                foreach ($expectedFields as $field) {
                    $fieldAliases = self::$aliases[$field] ?? [];
                    if (in_array($cleaned, $fieldAliases, true) || in_array($snake, $fieldAliases, true)) {
                        $matchedField = $field;
                        break;
                    }
                }
            }

            // 3. Substring matching
            if (!$matchedField) {
                foreach ($expectedFields as $field) {
                    $fieldAliases = self::$aliases[$field] ?? [];
                    foreach ($fieldAliases as $alias) {
                        if (str_contains($cleaned, $alias) || str_contains($alias, $cleaned)) {
                            $matchedField = $field;
                            break 2;
                        }
                    }
                }
            }

            $mappings[$rawHeader] = $matchedField;
        }

        return $mappings;
    }
}
