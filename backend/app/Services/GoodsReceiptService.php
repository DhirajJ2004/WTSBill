<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Warehouse;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

class GoodsReceiptService
{
    /**
     * Create a Goods Receipt Note (GRN).
     *
     * Every GRN automatically:
     *  - Records quantity-received against PO items
     *  - Updates PO status (PARTIALLY_RECEIVED / RECEIVED)
     *  - Calls InventoryService to record PURCHASE (IN) stock movements
     *  - Everything in one atomic transaction
     */
    public static function createGRN(array $input): GoodsReceipt
    {
        return DB::transaction(function () use ($input) {
            $user      = AuthMiddleware::getUser();
            $companyId = $user ? (int)$user->current_company_id : intval($input['company_id'] ?? 0);
            $branchId  = AuthMiddleware::getBranchId() ?: (intval($input['branch_id'] ?? 0) ?: null);
            $fy        = AuthMiddleware::getFinancialYear() ?: '2026-27';

            if (!$companyId) {
                throw new \InvalidArgumentException('Forbidden: Active company workspace context is required.');
            }

            // Resolve PO (optional — GRN can be direct without a PO)
            $poId = intval($input['purchase_order_id'] ?? 0);
            $po   = null;
            if ($poId) {
                $po = PurchaseOrder::withoutGlobalScopes()->with('items')->find($poId);
                if (!$po) {
                    throw new \InvalidArgumentException("Purchase Order #{$poId} not found.");
                }
                if ((int)$po->company_id !== $companyId) {
                    throw new \InvalidArgumentException("Forbidden: Purchase Order #{$poId} belongs to another company.");
                }
                if ($po->status === 'CANCELLED') {
                    throw new \InvalidArgumentException("Cannot receive against a cancelled Purchase Order #{$po->po_number}.");
                }
            }

            // Resolve supplier
            $supplierId = $po ? $po->supplier_id : intval($input['supplier_id'] ?? 0);
            if (!$supplierId) {
                throw new \InvalidArgumentException('Supplier ID is required when creating a GRN without a Purchase Order.');
            }

            // Resolve and validate warehouse (company-scoped, no hardcoded fallback)
            $warehouseId = intval($input['warehouse_id'] ?? 0);
            if (!$warehouseId) {
                $defaultWh = Warehouse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('is_active', 1)
                    ->orderBy('is_primary', 'desc')
                    ->orderBy('id')
                    ->first();
                if (!$defaultWh) {
                    throw new \InvalidArgumentException("No active warehouse found for company #{$companyId}. Please specify warehouse_id.");
                }
                $warehouseId = $defaultWh->id;
            } else {
                $wh = Warehouse::withoutGlobalScopes()->find($warehouseId);
                if (!$wh || (int)$wh->company_id !== $companyId) {
                    throw new \InvalidArgumentException("Warehouse #{$warehouseId} is invalid or belongs to another company.");
                }
                if (!$wh->is_active) {
                    throw new \InvalidArgumentException("Warehouse '{$wh->name}' is inactive.");
                }
            }

            // Validate items
            $items = $input['items'] ?? [];
            if (empty($items)) {
                throw new \InvalidArgumentException('Please add at least one item to the Goods Receipt.');
            }

            $grnNumber = DocumentNumberService::generateNextNumber($companyId, $branchId, $fy, 'GOODS_RECEIPT');

            $grn = GoodsReceipt::create([
                'company_id'        => $companyId,
                'branch_id'         => $branchId,
                'supplier_id'       => $supplierId,
                'purchase_order_id' => $poId ?: null,
                'grn_number'        => $grnNumber,
                'grn_date'          => $input['grn_date'] ?? date('Y-m-d'),
                'warehouse_id'      => $warehouseId,
                'status'            => 'RECEIVED',
                'notes'             => $input['notes'] ?? '',
            ]);

            $createdByName = $user ? $user->name : 'System';

            foreach ($items as $it) {
                $productId   = intval($it['product_id'] ?? 0);
                $qtyReceived = floatval($it['quantity_received'] ?? $it['received_quantity'] ?? $it['quantity'] ?? 0);
                $unitPrice   = floatval($it['unit_price'] ?? $it['unit_cost'] ?? $it['price'] ?? 0);

                if ($productId <= 0) {
                    throw new \InvalidArgumentException('Each GRN item must have a valid product_id.');
                }
                if ($qtyReceived <= 0) {
                    throw new \InvalidArgumentException("Received quantity for product #{$productId} must be greater than zero.");
                }

                // Validate product belongs to this company
                $product = \App\Models\Product::withoutGlobalScopes()->find($productId);
                if (!$product || (int)$product->company_id !== $companyId) {
                    throw new \InvalidArgumentException("Product #{$productId} is invalid or belongs to another company.");
                }

                GoodsReceiptItem::create([
                    'company_id'        => $companyId,
                    'goods_receipt_id'  => $grn->id,
                    'product_id'        => $productId,
                    'item_name'         => $it['item_name'] ?? $product->name ?? 'Item',
                    'quantity_ordered'  => floatval($it['quantity_ordered'] ?? 0),
                    'quantity_received' => $qtyReceived,
                    'unit'              => $it['unit'] ?? $product->unit ?? 'Pcs',
                    'unit_price'        => $unitPrice,
                ]);

                // Update matching PO line quantity received
                if ($po) {
                    $poItem = $po->items->firstWhere('product_id', $productId);
                    if ($poItem) {
                        $newRecQty = min(floatval($poItem->quantity), floatval($poItem->received_quantity) + $qtyReceived);
                        $poItem->update(['received_quantity' => $newRecQty]);
                    }
                }

                // ─── CRITICAL: Record stock movement for each received item ───
                InventoryService::recordStockMovement(
                    companyId:    $companyId,
                    warehouseId:  $warehouseId,
                    productId:    $productId,
                    movementType: 'PURCHASE',
                    quantity:     $qtyReceived,
                    direction:    'IN',
                    unitCost:     $unitPrice ?: floatval($product->purchase_price),
                    branchId:     $branchId,
                    batchId:      $it['batch_id'] ?? null,
                    refType:      'GOODS_RECEIPT',
                    refId:        $grn->id,
                    refNumber:    $grnNumber,
                    movementDate: $grn->grn_date,
                    createdBy:    $createdByName,
                    notes:        "Stock received via GRN #{$grnNumber}" . ($po ? " against PO #{$po->po_number}" : '')
                );
            }

            // Auto-update PO status based on received quantities
            if ($po) {
                $po->refresh();
                $allCompleted = true;
                $anyReceived  = false;

                foreach ($po->items as $item) {
                    if (floatval($item->received_quantity) < floatval($item->quantity)) {
                        $allCompleted = false;
                    }
                    if (floatval($item->received_quantity) > 0) {
                        $anyReceived = true;
                    }
                }

                if ($allCompleted) {
                    $po->update(['status' => 'RECEIVED']);
                } elseif ($anyReceived) {
                    $po->update(['status' => 'PARTIALLY_RECEIVED']);
                }
            }

            return $grn;
        });
    }
}
