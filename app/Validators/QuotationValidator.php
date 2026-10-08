<?php

namespace App\Validators;

use App\Models\Customer;
use App\Models\Product;

class QuotationValidator
{
    /**
     * Validate quotation input data scoped to company.
     *
     * @param array $data
     * @param int $companyId
     * @return array Validation errors map (empty if valid)
     */
    public static function validate(array $data, int $companyId): array
    {
        $errors = [];

        // 1. Customer validation
        $customerId = isset($data['customer_id']) ? (int)$data['customer_id'] : 0;
        if ($customerId <= 0) {
            $errors['customer_id'] = 'Customer is required.';
        } else {
            $customer = Customer::withoutGlobalScopes()
                ->where('id', $customerId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->first();

            if (!$customer) {
                $errors['customer_id'] = 'Selected customer does not exist or is unauthorized.';
            }
        }

        // 2. Date validations
        if (!empty($data['quotation_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['quotation_date'])) {
            $errors['quotation_date'] = 'Quotation date must be in YYYY-MM-DD format.';
        }

        if (!empty($data['valid_until']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['valid_until'])) {
            $errors['valid_until'] = 'Valid until date must be in YYYY-MM-DD format.';
        }

        if (!empty($data['quotation_date']) && !empty($data['valid_until']) && $data['valid_until'] < $data['quotation_date']) {
            $errors['valid_until'] = 'Valid until date cannot be earlier than quotation date.';
        }

        // 3. Line items validation
        if (empty($data['items']) || !is_array($data['items'])) {
            $errors['items'] = 'At least one line item is required.';
        } else {
            $validItemCount = 0;
            foreach ($data['items'] as $index => $item) {
                $lineNum = $index + 1;
                $productId = isset($item['product_id']) ? (int)$item['product_id'] : 0;

                if ($productId <= 0) {
                    $errors["item_{$lineNum}_product"] = "Line item #{$lineNum}: Product is required.";
                    continue;
                }

                $product = Product::withoutGlobalScopes()
                    ->where('id', $productId)
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$product) {
                    $errors["item_{$lineNum}_product"] = "Line item #{$lineNum}: Product does not exist or belongs to another company.";
                    continue;
                }

                $qty = isset($item['quantity']) ? (float)$item['quantity'] : 0.0;
                if ($qty <= 0) {
                    $errors["item_{$lineNum}_quantity"] = "Line item #{$lineNum}: Quantity must be greater than zero.";
                }

                $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : (isset($item['rate']) ? (float)$item['rate'] : 0.0);
                if ($unitPrice < 0) {
                    $errors["item_{$lineNum}_price"] = "Line item #{$lineNum}: Unit price cannot be negative.";
                }

                $discRate = isset($item['discount_rate']) ? (float)$item['discount_rate'] : 0.0;
                if ($discRate < 0 || $discRate > 100) {
                    $errors["item_{$lineNum}_discount"] = "Line item #{$lineNum}: Discount percentage must be between 0 and 100.";
                }

                $taxRate = isset($item['tax_rate']) ? (float)$item['tax_rate'] : (isset($item['gst_rate']) ? (float)$item['gst_rate'] : 0.0);
                if ($taxRate < 0 || $taxRate > 100) {
                    $errors["item_{$lineNum}_tax"] = "Line item #{$lineNum}: Tax rate must be between 0 and 100.";
                }

                $validItemCount++;
            }

            if ($validItemCount === 0 && !isset($errors['items'])) {
                $errors['items'] = 'At least one valid product line item is required.';
            }
        }

        return $errors;
    }
}
