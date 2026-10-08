<?php

namespace App\Validators;

use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Purchase;

class PurchaseValidator
{
    /**
     * Validate purchase bill payload.
     *
     * @param array $data
     * @param int $companyId
     * @param int|null $currentPurchaseId Optional ID when updating to exclude self
     * @return array Validation errors map (empty if valid)
     */
    public static function validate(array $data, int $companyId, ?int $currentPurchaseId = null): array
    {
        $errors = [];

        // 1. Supplier Validation (Strict Tenant Scoping)
        $supplierId = isset($data['supplier_id']) ? (int)$data['supplier_id'] : 0;
        if ($supplierId <= 0) {
            $errors['supplier_id'] = 'Supplier is required. Please select a valid supplier.';
        } else {
            $supplier = Supplier::withoutGlobalScopes()
                ->where('id', $supplierId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->first();

            if (!$supplier) {
                $otherCompSupp = Supplier::withoutGlobalScopes()->where('id', $supplierId)->first();
                if ($otherCompSupp && (int)$otherCompSupp->company_id !== $companyId) {
                    $errors['supplier_id'] = 'Forbidden: Selected supplier belongs to another company context.';
                } else {
                    $errors['supplier_id'] = 'Selected supplier does not exist or is inactive.';
                }
            }
        }

        // 2. Date Validations
        if (!empty($data['purchase_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['purchase_date'])) {
            $errors['purchase_date'] = 'Purchase date must be in YYYY-MM-DD format.';
        }

        if (!empty($data['due_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['due_date'])) {
            $errors['due_date'] = 'Due date must be in YYYY-MM-DD format.';
        }

        if (!empty($data['purchase_date']) && !empty($data['due_date']) && $data['due_date'] < $data['purchase_date']) {
            $errors['due_date'] = 'Purchase due date cannot be earlier than the purchase bill date.';
        }

        // 3. Warehouse Validation
        $warehouseId = isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : 0;
        if ($warehouseId > 0) {
            $warehouse = Warehouse::withoutGlobalScopes()
                ->where('id', $warehouseId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->first();

            if (!$warehouse) {
                $errors['warehouse_id'] = 'Selected warehouse does not exist or is unauthorized.';
            }
        }

        // 4. Duplicate Vendor Bill Number Check
        if (!empty($data['vendor_invoice_number']) && $supplierId > 0) {
            $vendorInv = trim($data['vendor_invoice_number']);
            $dupQuery = Purchase::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('supplier_id', $supplierId)
                ->where('vendor_invoice_number', $vendorInv)
                ->whereNull('deleted_at');

            if ($currentPurchaseId) {
                $dupQuery->where('id', '!=', $currentPurchaseId);
            }

            if ($dupQuery->exists()) {
                $errors['vendor_invoice_number'] = "Duplicate vendor bill: Bill #{$vendorInv} has already been recorded for this supplier.";
            }
        }

        // 5. Line Items Validation
        if (empty($data['items']) || !is_array($data['items'])) {
            $errors['items'] = 'Purchase bill must contain at least one line item.';
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
                    $otherCompProd = Product::withoutGlobalScopes()->where('id', $productId)->first();
                    if ($otherCompProd && (int)$otherCompProd->company_id !== $companyId) {
                        $errors["item_{$lineNum}_product"] = "Line item #{$lineNum}: Forbidden: Product #{$productId} belongs to another company context.";
                    } else {
                        $errors["item_{$lineNum}_product"] = "Line item #{$lineNum}: Product does not exist or is invalid.";
                    }
                    continue;
                }

                $qty = isset($item['quantity']) ? (float)$item['quantity'] : 0.0;
                if ($qty <= 0) {
                    $errors["item_{$lineNum}_quantity"] = "Line item #{$lineNum} ('{$product->name}'): Quantity must be greater than zero. Provided: {$qty}.";
                }

                $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : (isset($item['rate']) ? (float)$item['rate'] : 0.0);
                if ($unitPrice < 0) {
                    $errors["item_{$lineNum}_price"] = "Line item #{$lineNum} ('{$product->name}'): Unit purchase price cannot be negative.";
                }

                $discRate = isset($item['discount_rate']) ? (float)$item['discount_rate'] : 0.0;
                if ($discRate < 0 || $discRate > 100) {
                    $errors["item_{$lineNum}_discount"] = "Line item #{$lineNum}: Discount rate must be between 0 and 100%.";
                }

                $taxRate = isset($item['gst_rate']) ? (float)$item['gst_rate'] : (isset($item['tax_rate']) ? (float)$item['tax_rate'] : 0.0);
                if ($taxRate < 0 || $taxRate > 100) {
                    $errors["item_{$lineNum}_tax"] = "Line item #{$lineNum}: GST rate must be between 0 and 100%.";
                }

                $validItemCount++;
            }

            if ($validItemCount === 0 && !isset($errors['items'])) {
                $errors['items'] = 'At least one valid purchase line item is required.';
            }
        }

        return $errors;
    }
}
