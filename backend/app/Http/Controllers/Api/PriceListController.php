<?php

namespace App\Http\Controllers\Api;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\CustomerPriceOverride;
use App\Services\PriceCalculationService;
use App\Http\Middleware\AuthMiddleware;
use App\Services\PermissionManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class PriceListController
{
    /**
     * List all price lists.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $priceLists = PriceList::where('company_id', $companyId)->withCount('items')->get();

        return response()->json([
            'status' => 'success',
            'price_lists' => $priceLists,
        ]);
    }

    /**
     * Show price list with items.
     */
    public function show(int $id): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $priceList = PriceList::where('company_id', $companyId)->with(['items.product', 'branch'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'price_list' => $priceList,
        ]);
    }

    /**
     * Create price list.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $user = AuthMiddleware::getAuthenticatedUser();

        if ($user && !PermissionManager::can($user, 'pricelists', 'create')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $request->validate(['name' => 'required|string|max:120']);

        $isDefault = (bool)$request->input('is_default', false);
        if ($isDefault) {
            PriceList::where('company_id', $companyId)->update(['is_default' => false]);
        }

        $priceList = PriceList::create([
            'company_id' => $companyId,
            'branch_id' => $request->input('branch_id'),
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'description' => $request->input('description'),
            'price_type' => $request->input('price_type', 'FIXED'),
            'adjustment_value' => floatval($request->input('adjustment_value', 0)),
            'tax_mode' => $request->input('tax_mode', 'TAX_EXCLUSIVE'),
            'is_default' => $isDefault,
            'is_active' => true,
            'effective_from' => $request->input('effective_from'),
            'effective_to' => $request->input('effective_to'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Price list created successfully',
            'price_list' => $priceList,
        ], 201);
    }

    /**
     * Add item to price list.
     */
    public function saveItem(Request $request, int $priceListId): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $priceList = PriceList::where('company_id', $companyId)->findOrFail($priceListId);

        $productId = intval($request->input('product_id'));
        $price = floatval($request->input('price', 0));
        $minQty = floatval($request->input('minimum_quantity', 1));
        $maxQty = $request->input('maximum_quantity') ? floatval($request->input('maximum_quantity')) : null;

        $item = PriceListItem::create([
            'company_id' => $companyId,
            'price_list_id' => $priceList->id,
            'product_id' => $productId,
            'price' => $price,
            'minimum_quantity' => $minQty,
            'maximum_quantity' => $maxQty,
            'discount_rate' => floatval($request->input('discount_rate', 0)),
            'discount_amount' => floatval($request->input('discount_amount', 0)),
            'effective_from' => $request->input('effective_from'),
            'effective_to' => $request->input('effective_to'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Price list item saved',
            'item' => $item->load('product'),
        ]);
    }

    /**
     * Bulk update price list.
     */
    public function bulkUpdate(Request $request, int $priceListId): JsonResponse
    {
        $user = AuthMiddleware::getAuthenticatedUser();
        if ($user && !PermissionManager::can($user, 'pricelists', 'bulk_update')) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $type = $request->input('adjustment_type', 'PERCENTAGE'); // PERCENTAGE, FIXED
        $value = floatval($request->input('value', 0));
        $preview = (bool)$request->input('preview', false);

        try {
            $result = PriceCalculationService::bulkUpdatePriceList(
                $priceListId,
                $type,
                $value,
                $preview,
                $user?->id,
                $user?->name ?: 'System'
            );

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Calculate price for product / customer / qty / branch.
     */
    public function calculatePrice(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $productId = intval($request->input('product_id'));
        $customerId = $request->input('customer_id') ? intval($request->input('customer_id')) : null;
        $quantity = floatval($request->input('quantity', 1.0));
        $branchId = $request->input('branch_id') ? intval($request->input('branch_id')) : null;
        $date = $request->input('date');

        try {
            $priceData = PriceCalculationService::getApplicablePrice(
                $companyId,
                $productId,
                $customerId,
                $quantity,
                $branchId,
                $date
            );

            return response()->json([
                'status' => 'success',
                'price_data' => $priceData,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Set customer price override.
     */
    public function setCustomerOverride(Request $request): JsonResponse
    {
        $companyId = AuthMiddleware::getTenantId();
        $customerId = intval($request->input('customer_id'));
        $productId = intval($request->input('product_id'));
        $price = floatval($request->input('price'));

        try {
            $override = PriceCalculationService::setCustomerPriceOverride(
                $companyId,
                $customerId,
                $productId,
                $price,
                floatval($request->input('discount_rate', 0)),
                $request->input('notes')
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Customer price override saved',
                'override' => $override,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
