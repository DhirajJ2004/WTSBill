<?php

namespace App\Services;

use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Company;
use App\Models\Branch;
use App\Repositories\DeliveryChallanRepository;
use App\Validators\DeliveryChallanValidator;
use App\Services\InventoryService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class DeliveryChallanService
{
    /**
     * Create a new Delivery Challan.
     */
    public static function createDeliveryChallan(
        array $input,
        int $companyId,
        ?int $branchId = null,
        string $userName = 'Admin'
    ): array {
        $errors = DeliveryChallanValidator::validate($input, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $company = Company::withoutGlobalScopes()->where('id', $companyId)->first();
        if (!$company) {
            return ['success' => false, 'message' => 'Active company context is invalid.'];
        }

        if (!$branchId) {
            $branch = Branch::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $branchId = $branch ? $branch->id : 1;
        }

        $customerId = (int)$input['customer_id'];
        $customer = Customer::withoutGlobalScopes()
            ->where('id', $customerId)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$customer) {
            return ['success' => false, 'message' => 'Selected customer is invalid or unauthorized.'];
        }

        $warehouseId = !empty($input['warehouse_id']) ? (int)$input['warehouse_id'] : 1;
        $challanDate = !empty($input['challan_date']) ? substr(trim($input['challan_date']), 0, 10) : date('Y-m-d');
        $dispatchStock = !empty($input['dispatch_stock']);
        $status = strtoupper($input['status'] ?? ($dispatchStock ? 'DISPATCHED' : 'DRAFT'));

        $validatedItems = [];
        foreach ($input['items'] as $item) {
            $prodId = (int)$item['product_id'];
            $product = Product::withoutGlobalScopes()
                ->where('id', $prodId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->first();

            if (!$product) {
                return ['success' => false, 'message' => "Product #{$prodId} is invalid or unauthorized."];
            }

            $qty = (float)$item['quantity'];
            $unitPrice = (float)($item['unit_price'] ?? ($item['rate'] ?? $product->sales_price));
            $totalAmount = round($qty * $unitPrice, 2);

            $validatedItems[] = [
                'product' => $product,
                'product_id' => $product->id,
                'item_name' => $item['item_name'] ?? $product->name,
                'quantity' => $qty,
                'unit' => $item['unit'] ?? ($product->unit ?: 'PCS'),
                'unit_price' => $unitPrice,
                'total_amount' => $totalAmount,
                'batch_no' => $item['batch_no'] ?? null,
                'serial_no' => $item['serial_no'] ?? null,
            ];
        }

        return DB::transaction(function () use (
            $companyId, $branchId, $warehouseId, $customer, $challanDate,
            $status, $dispatchStock, $validatedItems, $input, $userName
        ) {
            $dcNumber = DeliveryChallanRepository::generateNextChallanNumber($companyId, 'DC');

            $challan = DeliveryChallan::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'challan_number' => $dcNumber,
                'challan_date' => $challanDate,
                'sales_order_id' => !empty($input['sales_order_id']) ? (int)$input['sales_order_id'] : null,
                'reference_so' => $input['reference_so'] ?? null,
                'delivery_address' => $input['delivery_address'] ?? $customer->billing_address,
                'transport_details' => $input['transport_details'] ?? '',
                'status' => $status,
                'notes' => $input['notes'] ?? '',
            ]);

            foreach ($validatedItems as $vi) {
                DeliveryChallanItem::create([
                    'company_id' => $companyId,
                    'delivery_challan_id' => $challan->id,
                    'product_id' => $vi['product_id'],
                    'item_name' => $vi['item_name'],
                    'quantity' => $vi['quantity'],
                    'unit' => $vi['unit'],
                    'unit_price' => $vi['unit_price'],
                    'total_amount' => $vi['total_amount'],
                    'batch_no' => $vi['batch_no'],
                    'serial_no' => $vi['serial_no'],
                ]);
            }

            // Deduct stock if dispatch is requested
            if ($dispatchStock || $status === 'DISPATCHED') {
                foreach ($validatedItems as $vi) {
                    $prod = $vi['product'];
                    if (strtoupper($prod->product_type ?? '') !== 'SERVICES' && $prod->track_inventory) {
                        $stockRes = InventoryService::recordStockOut(
                            $companyId,
                            $warehouseId,
                            $prod->id,
                            $vi['quantity'],
                            (float)$prod->purchase_price,
                            'DISPATCH',
                            [
                                'reference_type' => 'DELIVERY_CHALLAN',
                                'reference_id' => $challan->id,
                                'reference_number' => $dcNumber,
                                'movement_date' => $challanDate,
                                'created_by' => $userName,
                                'notes' => "Stock dispatched under Delivery Challan #{$dcNumber}",
                            ]
                        );

                        if (!$stockRes['success']) {
                            throw new \Exception("Stock dispatch failed: " . $stockRes['message']);
                        }
                    }
                }
            }

            AuditLogService::log(
                $companyId,
                $userName,
                'DELIVERY_CHALLAN_CREATE',
                'DeliveryChallan',
                $challan->id,
                "Created Delivery Challan #{$dcNumber} for Customer: {$customer->name}"
            );

            return [
                'success' => true,
                'delivery_challan_id' => $challan->id,
                'challan_number' => $dcNumber,
                'challan' => $challan->load(['customer', 'items']),
                'message' => "Delivery Challan #{$dcNumber} created successfully."
            ];
        });
    }

    /**
     * Dispatch an existing Delivery Challan (Deducting stock).
     */
    public static function dispatchChallan(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $challan = DeliveryChallan::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$challan) {
            return ['success' => false, 'message' => "Delivery Challan #{$id} not found or unauthorized."];
        }

        if ($challan->status === 'DISPATCHED' || $challan->status === 'DELIVERED' || $challan->status === 'INVOICED') {
            return ['success' => false, 'message' => "Delivery Challan #{$challan->challan_number} is already {$challan->status}."];
        }

        return DB::transaction(function () use ($challan, $companyId, $userName) {
            $items = DeliveryChallanItem::where('delivery_challan_id', $challan->id)->where('company_id', $companyId)->get();
            $warehouseId = 1;

            foreach ($items as $item) {
                $prod = Product::withoutGlobalScopes()->where('id', $item->product_id)->where('company_id', $companyId)->first();
                if ($prod && strtoupper($prod->product_type ?? '') !== 'SERVICES' && $prod->track_inventory) {
                    $stockRes = InventoryService::recordStockOut(
                        $companyId,
                        $warehouseId,
                        $prod->id,
                        (float)$item->quantity,
                        (float)$prod->purchase_price,
                        'DISPATCH',
                        [
                            'reference_type' => 'DELIVERY_CHALLAN',
                            'reference_id' => $challan->id,
                            'reference_number' => $challan->challan_number,
                            'movement_date' => date('Y-m-d'),
                            'created_by' => $userName,
                            'notes' => "Stock dispatched for Delivery Challan #{$challan->challan_number}",
                        ]
                    );

                    if (!$stockRes['success']) {
                        throw new \Exception("Stock dispatch failed: " . $stockRes['message']);
                    }
                }
            }

            $challan->update(['status' => 'DISPATCHED']);

            AuditLogService::log(
                $companyId,
                $userName,
                'DELIVERY_CHALLAN_DISPATCH',
                'DeliveryChallan',
                $challan->id,
                "Dispatched Delivery Challan #{$challan->challan_number}"
            );

            return [
                'success' => true,
                'message' => "Delivery Challan #{$challan->challan_number} dispatched successfully."
            ];
        });
    }

    /**
     * Delete an un-invoiced Delivery Challan.
     */
    public static function deleteDeliveryChallan(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $challan = DeliveryChallan::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$challan) {
            return ['success' => false, 'message' => "Delivery Challan #{$id} not found or unauthorized."];
        }

        if ($challan->status === 'INVOICED') {
            return ['success' => false, 'message' => "Invoiced delivery challans cannot be deleted."];
        }

        return DB::transaction(function () use ($challan, $companyId, $id, $userName) {
            // If stock was dispatched, restore it
            if ($challan->status === 'DISPATCHED' || $challan->status === 'DELIVERED') {
                $items = DeliveryChallanItem::where('delivery_challan_id', $challan->id)->where('company_id', $companyId)->get();
                foreach ($items as $item) {
                    $prod = Product::withoutGlobalScopes()->where('id', $item->product_id)->where('company_id', $companyId)->first();
                    if ($prod && strtoupper($prod->product_type ?? '') !== 'SERVICES' && $prod->track_inventory) {
                        InventoryService::recordStockIn(
                            $companyId,
                            1,
                            $prod->id,
                            (float)$item->quantity,
                            (float)$prod->purchase_price,
                            'SALES_RETURN',
                            [
                                'reference_type' => 'DELIVERY_CHALLAN_CANCEL',
                                'reference_id' => $challan->id,
                                'reference_number' => $challan->challan_number,
                                'movement_date' => date('Y-m-d'),
                                'created_by' => $userName,
                                'notes' => "Restored stock upon deleting Delivery Challan #{$challan->challan_number}",
                            ]
                        );
                    }
                }
            }

            DeliveryChallanItem::where('delivery_challan_id', $id)->where('company_id', $companyId)->delete();
            $challan->delete();

            AuditLogService::log(
                $companyId,
                $userName,
                'DELIVERY_CHALLAN_DELETE',
                'DeliveryChallan',
                $id,
                "Deleted Delivery Challan #{$challan->challan_number}"
            );

            return ['success' => true, 'message' => "Delivery Challan #{$challan->challan_number} deleted successfully."];
        });
    }
}
