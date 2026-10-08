<?php

namespace App\Services;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\CustomerPriceOverride;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

class PriceCalculationService
{
    /**
     * Get authoritative applicable selling price for a product.
     *
     * Priority Hierarchy:
     * 1. Customer-Specific Product Price (CustomerPriceOverride)
     * 2. Customer's Assigned Price List (PriceList matching Product & Quantity tier)
     * 3. Customer Group's Default Price List
     * 4. Branch-Specific Price List
     * 5. Business Default Price List
     * 6. Product Default Selling Price (fallback)
     */
    public static function getApplicablePrice(
        int $companyId,
        int $productId,
        ?int $customerId = null,
        float $quantity = 1.0,
        ?int $branchId = null,
        ?string $date = null
    ): array {
        $product = Product::where('company_id', $companyId)->findOrFail($productId);
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $basePrice = floatval($product->sales_price ?: ($product->selling_price ?? 0.0));

        // 1. Check Customer-Specific Product Price Override
        if ($customerId) {
            $override = CustomerPriceOverride::where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->where('product_id', $productId)
                ->first();

            if ($override) {
                $overridePrice = floatval($override->price);
                $discRate = floatval($override->discount_rate ?? 0);
                $finalPrice = $discRate > 0 ? ($overridePrice * (1 - ($discRate / 100))) : $overridePrice;

                return [
                    'price' => round($finalPrice, 2),
                    'unit_price' => round($overridePrice, 2),
                    'source' => 'CUSTOMER_SPECIFIC',
                    'price_list_id' => null,
                    'price_list_name' => 'Customer Special Rate',
                    'discount_rate' => $discRate,
                    'tax_mode' => 'TAX_EXCLUSIVE',
                    'is_custom' => true,
                ];
            }
        }

        // 2. Check Customer's Assigned Price List
        $customer = $customerId ? Customer::where('company_id', $companyId)->find($customerId) : null;
        if ($customer && $customer->price_list_id) {
            $priceList = PriceList::where('company_id', $companyId)
                ->where('id', $customer->price_list_id)
                ->where('is_active', true)
                ->first();

            if ($priceList && self::isPriceListValid($priceList, $date)) {
                $result = self::resolvePriceFromPriceList($priceList, $product, $quantity, $date);
                if ($result) {
                    $result['source'] = 'CUSTOMER_PRICE_LIST';
                    return $result;
                }
            }
        }

        // 3. Check Customer Group Default Price List
        if ($customer && $customer->customer_group_id) {
            $group = CustomerGroup::where('company_id', $companyId)->find($customer->customer_group_id);
            if ($group && $group->default_price_list_id) {
                $priceList = PriceList::where('company_id', $companyId)
                    ->where('id', $group->default_price_list_id)
                    ->where('is_active', true)
                    ->first();

                if ($priceList && self::isPriceListValid($priceList, $date)) {
                    $result = self::resolvePriceFromPriceList($priceList, $product, $quantity, $date);
                    if ($result) {
                        $result['source'] = 'CUSTOMER_GROUP_PRICE_LIST';
                        return $result;
                    }
                }
            }
        }

        // 4. Check Branch-Specific Price List
        if ($branchId) {
            $branchPriceList = PriceList::where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->first();

            if ($branchPriceList && self::isPriceListValid($branchPriceList, $date)) {
                $result = self::resolvePriceFromPriceList($branchPriceList, $product, $quantity, $date);
                if ($result) {
                    $result['source'] = 'BRANCH_PRICE_LIST';
                    return $result;
                }
            }
        }

        // 5. Check Business Default Price List
        $defaultPriceList = PriceList::where('company_id', $companyId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($defaultPriceList && self::isPriceListValid($defaultPriceList, $date)) {
            $result = self::resolvePriceFromPriceList($defaultPriceList, $product, $quantity, $date);
            if ($result) {
                $result['source'] = 'DEFAULT_PRICE_LIST';
                return $result;
            }
        }

        // 6. Fallback: Product Default Selling Price
        return [
            'price' => round($basePrice, 2),
            'unit_price' => round($basePrice, 2),
            'source' => 'PRODUCT_BASE_PRICE',
            'price_list_id' => null,
            'price_list_name' => 'Standard Rate',
            'discount_rate' => 0.00,
            'tax_mode' => 'TAX_EXCLUSIVE',
            'is_custom' => false,
        ];
    }

    /**
     * Resolve price from a specific Price List.
     */
    public static function resolvePriceFromPriceList(PriceList $priceList, Product $product, float $quantity, Carbon $date): ?array
    {
        $basePrice = floatval($product->sales_price ?: ($product->selling_price ?? 0.0));

        // 1. Check direct Price List Item match for this product
        $item = PriceListItem::where('price_list_id', $priceList->id)
            ->where('product_id', $product->id)
            ->where(function ($q) use ($quantity) {
                $q->where('minimum_quantity', '<=', $quantity)
                  ->where(function ($q2) use ($quantity) {
                      $q2->whereNull('maximum_quantity')->orWhere('maximum_quantity', '>=', $quantity);
                  });
            })
            ->orderBy('minimum_quantity', 'desc')
            ->first();

        if ($item && self::isItemValid($item, $date)) {
            $itemPrice = floatval($item->price);
            $discRate = floatval($item->discount_rate ?? 0);
            $discAmount = floatval($item->discount_amount ?? 0);

            $finalPrice = $itemPrice;
            if ($discRate > 0) {
                $finalPrice = $finalPrice * (1 - ($discRate / 100));
            } elseif ($discAmount > 0) {
                $finalPrice = max(0, $finalPrice - $discAmount);
            }

            return [
                'price' => round($finalPrice, 2),
                'unit_price' => round($itemPrice, 2),
                'price_list_id' => $priceList->id,
                'price_list_name' => $priceList->name,
                'discount_rate' => $discRate,
                'discount_amount' => $discAmount,
                'tax_mode' => $priceList->tax_mode ?: 'TAX_EXCLUSIVE',
                'is_custom' => false,
            ];
        }

        // 2. If Price List has Percentage Adjustment type (e.g. +5% or -10% across all products)
        if ($priceList->price_type === 'PERCENTAGE_ADJUSTMENT') {
            $adjPct = floatval($priceList->adjustment_value);
            $adjustedPrice = max(0, $basePrice * (1 + ($adjPct / 100)));

            return [
                'price' => round($adjustedPrice, 2),
                'unit_price' => round($adjustedPrice, 2),
                'price_list_id' => $priceList->id,
                'price_list_name' => $priceList->name,
                'discount_rate' => 0.00,
                'discount_amount' => 0.00,
                'tax_mode' => $priceList->tax_mode ?: 'TAX_EXCLUSIVE',
                'is_custom' => false,
            ];
        }

        return null;
    }

    /**
     * Check Price List validity date.
     */
    private static function isPriceListValid(PriceList $pl, Carbon $date): bool
    {
        if ($pl->effective_from && $date->lt(Carbon::parse($pl->effective_from))) {
            return false;
        }
        if ($pl->effective_to && $date->gt(Carbon::parse($pl->effective_to))) {
            return false;
        }
        return true;
    }

    /**
     * Check Price List Item validity date.
     */
    private static function isItemValid(PriceListItem $item, Carbon $date): bool
    {
        if ($item->effective_from && $date->lt(Carbon::parse($item->effective_from))) {
            return false;
        }
        if ($item->effective_to && $date->gt(Carbon::parse($item->effective_to))) {
            return false;
        }
        return true;
    }

    /**
     * Bulk update prices in a Price List (Percentage or Fixed increase/decrease).
     */
    public static function bulkUpdatePriceList(
        int $priceListId,
        string $adjustmentType, // 'PERCENTAGE' or 'FIXED'
        float $value, // e.g. 5.0 for +5% or -10.0 for -10%
        bool $previewOnly = false,
        ?int $userId = null,
        string $userName = 'System'
    ): array {
        $priceList = PriceList::with('items.product')->findOrFail($priceListId);
        $companyId = $priceList->company_id;

        $previewRows = [];

        foreach ($priceList->items as $item) {
            $oldPrice = floatval($item->price);
            $newPrice = $oldPrice;

            if ($adjustmentType === 'PERCENTAGE') {
                $newPrice = max(0, round($oldPrice * (1 + ($value / 100)), 2));
            } else {
                $newPrice = max(0, round($oldPrice + $value, 2));
            }

            $previewRows[] = [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?: 'Item',
                'sku' => $item->product?->sku ?: '-',
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'variance' => round($newPrice - $oldPrice, 2),
            ];

            if (!$previewOnly) {
                $item->price = $newPrice;
                $item->save();

                // Record price history audit
                self::recordPriceHistory(
                    $companyId,
                    $item->product_id,
                    $priceList->id,
                    $oldPrice,
                    $newPrice,
                    "Bulk adjustment ({$adjustmentType} {$value})",
                    $userId,
                    $userName
                );
            }
        }

        return [
            'price_list_id' => $priceList->id,
            'price_list_name' => $priceList->name,
            'adjustment_type' => $adjustmentType,
            'adjustment_value' => $value,
            'is_preview' => $previewOnly,
            'items_count' => count($previewRows),
            'items' => $previewRows,
        ];
    }

    /**
     * Record price change in audit history.
     */
    public static function recordPriceHistory(
        int $companyId,
        int $productId,
        ?int $priceListId,
        float $oldPrice,
        float $newPrice,
        string $reason = 'Updated by user',
        ?int $userId = null,
        string $userName = 'System'
    ): PriceHistory {
        return PriceHistory::create([
            'company_id' => $companyId,
            'product_id' => $productId,
            'price_list_id' => $priceListId,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'changed_by_user_id' => $userId,
            'changed_by_name' => $userName,
            'reason' => $reason,
        ]);
    }

    /**
     * Set or update customer-specific price override.
     */
    public static function setCustomerPriceOverride(
        int $companyId,
        int $customerId,
        int $productId,
        float $price,
        float $discountRate = 0.00,
        ?string $notes = null
    ): CustomerPriceOverride {
        $override = CustomerPriceOverride::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->first();

        if ($override) {
            $override->update([
                'price' => $price,
                'discount_rate' => $discountRate,
                'notes' => $notes,
            ]);
            return $override;
        }

        return CustomerPriceOverride::create([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'product_id' => $productId,
            'price' => $price,
            'discount_rate' => $discountRate,
            'notes' => $notes,
        ]);
    }
}
