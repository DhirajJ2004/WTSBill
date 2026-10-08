<?php

namespace App\Validators;

use Illuminate\Database\Capsule\Manager as DB;

class InvoiceValidator
{
    /**
     * Validate Invoice Creation / Update Payload.
     *
     * @param array $data Input payload
     * @param int $companyId Tenant company ID
     * @param int|null $excludeId Invoice ID if updating
     * @return array Array of validation error messages
     */
    public static function validate(array $data, int $companyId, ?int $excludeId = null): array
    {
        $errors = [];

        // 1. Customer Validation
        $customerId = (int)($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            $errors['customer_id'] = 'Customer is required.';
        } else {
            $custExists = DB::table('customers')
                ->where('id', $customerId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->exists();

            if (!$custExists) {
                // Check if it belongs to another company for clearer security rejection
                $otherComp = DB::table('customers')->where('id', $customerId)->exists();
                if ($otherComp) {
                    $errors['customer_id'] = 'Forbidden: Selected customer belongs to another company.';
                } else {
                    $errors['customer_id'] = 'Selected customer is invalid or not found.';
                }
            }
        }

        // 2. Date Validation
        $invoiceDate = !empty($data['invoice_date']) ? substr(trim($data['invoice_date']), 0, 10) : date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDate)) {
            $errors['invoice_date'] = 'Invalid invoice date format (YYYY-MM-DD expected).';
        }

        $dueDate = !empty($data['due_date']) ? substr(trim($data['due_date']), 0, 10) : $invoiceDate;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $errors['due_date'] = 'Invalid due date format (YYYY-MM-DD expected).';
        } elseif ($dueDate < $invoiceDate) {
            $errors['due_date'] = 'Due date cannot be earlier than invoice date.';
        }

        // 3. Line Items Validation
        $items = $data['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            $errors['items'] = 'Invoice must contain at least one line item.';
        } else {
            foreach ($items as $idx => $item) {
                $pos = $idx + 1;
                $prodId = (int)($item['product_id'] ?? 0);
                $qty = $item['quantity'] ?? null;
                $rate = $item['unit_price'] ?? ($item['rate'] ?? ($item['price'] ?? null));

                if ($prodId <= 0) {
                    $errors["items.{$idx}.product_id"] = "Item #{$pos}: Valid product is required.";
                } else {
                    $prodExists = DB::table('products')
                        ->where('id', $prodId)
                        ->where('company_id', $companyId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$prodExists) {
                        $otherCompProd = DB::table('products')->where('id', $prodId)->exists();
                        if ($otherCompProd) {
                            $errors["items.{$idx}.product_id"] = "Item #{$pos}: Forbidden: Product belongs to another company.";
                        } else {
                            $errors["items.{$idx}.product_id"] = "Item #{$pos}: Selected product is invalid or not found.";
                        }
                    }
                }

                if ($qty === null || !is_numeric($qty) || (float)$qty <= 0) {
                    $errors["items.{$idx}.quantity"] = "Item #{$pos}: Quantity must be a positive number greater than 0.";
                }

                if ($rate === null || !is_numeric($rate) || (float)$rate < 0) {
                    $errors["items.{$idx}.unit_price"] = "Item #{$pos}: Rate / Unit Price must be a non-negative number.";
                }

                if (isset($item['discount_rate']) && $item['discount_rate'] !== '') {
                    if (!is_numeric($item['discount_rate']) || (float)$item['discount_rate'] < 0 || (float)$item['discount_rate'] > 100) {
                        $errors["items.{$idx}.discount_rate"] = "Item #{$pos}: Discount percentage must be between 0 and 100.";
                    }
                }

                if (isset($item['discount_amount']) && $item['discount_amount'] !== '') {
                    if (!is_numeric($item['discount_amount']) || (float)$item['discount_amount'] < 0) {
                        $errors["items.{$idx}.discount_amount"] = "Item #{$pos}: Discount amount must be non-negative.";
                    }
                }
            }
        }

        // 4. Warehouse validation if provided
        if (!empty($data['warehouse_id'])) {
            $whExists = DB::table('warehouses')
                ->where('id', (int)$data['warehouse_id'])
                ->where('company_id', $companyId)
                ->exists();
            if (!$whExists) {
                $errors['warehouse_id'] = 'Selected warehouse is invalid or unauthorized.';
            }
        }

        return $errors;
    }
}
