<?php

namespace App\Services;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AccountingEventService;
use Illuminate\Database\Capsule\Manager as DB;

class PurchaseReturnService
{
    public static function createReturn(array $input): PurchaseReturn
    {
        return DB::transaction(function () use ($input) {
            $user      = AuthMiddleware::getUser();
            $companyId = $user ? (int)$user->current_company_id : 0;
            $branchId  = AuthMiddleware::getBranchId();
            $fy        = AuthMiddleware::getFinancialYear() ?: '2026-27';

            if (!$companyId) {
                throw new \InvalidArgumentException('Forbidden: Active company workspace context is required.');
            }

            // Resolve and validate original purchase (company-scoped)
            $purchaseId = intval($input['purchase_id'] ?? 0);
            if (!$purchaseId) {
                throw new \InvalidArgumentException('purchase_id is required.');
            }

            $purchase = Purchase::withoutGlobalScopes()->with('items')->find($purchaseId);
            if (!$purchase) {
                throw new \InvalidArgumentException("Purchase Bill #{$purchaseId} not found.");
            }
            if ((int)$purchase->company_id !== $companyId) {
                throw new \InvalidArgumentException("Forbidden: Purchase Bill #{$purchaseId} belongs to another company.");
            }
            if ($purchase->status !== 'POSTED') {
                throw new \InvalidArgumentException("Can only return items against a POSTED purchase bill. Current status: {$purchase->status}.");
            }

            $items = $input['items'] ?? [];
            if (empty($items)) {
                throw new \InvalidArgumentException('Please add at least one product to return.');
            }

            // Validate each return item against the original purchase
            $enrichedItems = [];
            foreach ($items as $idx => $it) {
                $productId = intval($it['product_id'] ?? 0);
                $returnQty = floatval($it['quantity'] ?? 0);

                if ($productId <= 0) {
                    throw new \InvalidArgumentException("Item #{$idx}: product_id is required.");
                }
                if ($returnQty <= 0) {
                    throw new \InvalidArgumentException("Item #{$idx}: Return quantity must be greater than zero.");
                }

                $origItem = $purchase->items->firstWhere('product_id', $productId);
                if (!$origItem) {
                    throw new \InvalidArgumentException("Product #{$productId} was not part of original Purchase Bill #{$purchase->purchase_number}.");
                }

                // Calculate previously returned quantity
                $prevReturnQty = PurchaseReturnItem::whereHas('purchaseReturn', function ($q) use ($purchaseId) {
                    $q->where('purchase_id', $purchaseId)->where('status', '!=', 'CANCELLED');
                })->where('product_id', $productId)->sum('quantity');

                $maxEligible = floatval($origItem->quantity) - floatval($prevReturnQty);
                if ($returnQty > ($maxEligible + 0.001)) {
                    throw new \InvalidArgumentException(
                        "Return quantity ({$returnQty}) for '{$origItem->item_name}' exceeds maximum eligible ({$maxEligible})."
                    );
                }

                // Use GST rates from the original purchase item — never hardcode
                $enrichedItems[] = [
                    'product_id'  => $productId,
                    'item_name'   => $origItem->item_name,
                    'hsn_sac'     => $origItem->hsn_sac ?? '',
                    'quantity'    => $returnQty,
                    'unit'        => $origItem->unit,
                    'unit_price'  => floatval($it['unit_price'] ?? $origItem->unit_price),
                    'gst_rate'    => floatval($origItem->gst_rate),   // from original — not hardcoded
                    'cess_rate'   => floatval($origItem->cess_rate ?? 0),
                    'discount_rate'  => 0,
                    'discount_amount'=> 0,
                ];
            }

            // Resolve place of supply from original purchase
            $isIgst = (substr($purchase->place_of_supply ?? '27', 0, 2) !== '27');
            $calc   = PurchaseInvoiceService::calculateTotals($enrichedItems, $isIgst);

            // Resolve warehouse (no hardcoded fallback to #1)
            $warehouseId = intval($input['warehouse_id'] ?? $purchase->warehouse_id ?? 0);
            if (!$warehouseId) {
                $defaultWh = Warehouse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('is_active', 1)
                    ->orderBy('is_primary', 'desc')
                    ->orderBy('id')
                    ->first();
                if (!$defaultWh) {
                    throw new \InvalidArgumentException("No active warehouse found. Please specify warehouse_id.");
                }
                $warehouseId = $defaultWh->id;
            } else {
                $wh = Warehouse::withoutGlobalScopes()->find($warehouseId);
                if (!$wh || (int)$wh->company_id !== $companyId) {
                    throw new \InvalidArgumentException("Warehouse #{$warehouseId} is invalid or belongs to another company.");
                }
            }

            $prNumber = DocumentNumberService::generateNextNumber($companyId, $branchId, $fy, 'PURCHASE_RETURN');

            $ret = PurchaseReturn::create([
                'company_id'          => $companyId,
                'branch_id'           => $branchId,
                'supplier_id'         => $purchase->supplier_id,
                'purchase_id'         => $purchase->id,
                'return_number'       => $prNumber,
                'return_date'         => $input['return_date'] ?? date('Y-m-d'),
                'reason'              => $input['reason'] ?? 'Returned',
                'warehouse_id'        => $warehouseId,
                'sub_total'           => $calc['sub_total'],
                'total_tax'           => $calc['total_tax'],
                'grand_total'         => $calc['grand_total'],
                'refund_amount'       => $calc['grand_total'],
                'tax_reversal_amount' => $calc['total_tax'],
                'status'              => 'APPROVED',
                'notes'               => $input['notes'] ?? '',
            ]);

            $createdByName = $user ? $user->name : 'System';

            foreach ($calc['items'] as $it) {
                PurchaseReturnItem::create([
                    'company_id'        => $companyId,
                    'purchase_return_id'=> $ret->id,
                    'product_id'        => $it['product_id'],
                    'item_name'         => $it['item_name'] ?? 'Item',
                    'hsn_sac'           => $it['hsn_sac'] ?? '',
                    'quantity'          => $it['quantity'],
                    'unit'              => $it['unit'] ?? 'Pcs',
                    'unit_price'        => $it['unit_price'],
                    'taxable_value'     => $it['taxable_value'],
                    'gst_rate'          => $it['gst_rate'],
                    'cgst_amount'       => $it['cgst_amount'],
                    'sgst_amount'       => $it['sgst_amount'],
                    'igst_amount'       => $it['igst_amount'],
                    'amount'            => $it['total_amount'],
                    'total_amount'      => $it['total_amount'],
                ]);

                // Record PURCHASE_RETURN (OUT) movement — stock leaves to supplier
                InventoryService::recordStockMovement(
                    companyId:    $companyId,
                    warehouseId:  $warehouseId,
                    productId:    (int)$it['product_id'],
                    movementType: 'PURCHASE_RETURN',
                    quantity:     floatval($it['quantity']),
                    direction:    'OUT',
                    unitCost:     floatval($it['unit_price']),
                    branchId:     $branchId,
                    refType:      'PURCHASE_RETURN',
                    refId:        $ret->id,
                    refNumber:    $prNumber,
                    movementDate: $ret->return_date,
                    createdBy:    $createdByName,
                    notes:        "Purchase Return #{$prNumber} against Bill #{$purchase->purchase_number}"
                );
            }

            // Reduce supplier AP balance — we are returning goods so we owe less
            $returnTotal = floatval($ret->grand_total);
            if ($returnTotal > 0) {
                $supplier = Supplier::withoutGlobalScopes()->find($purchase->supplier_id);
                if ($supplier) {
                    $supplier->update([
                        'current_balance' => max(0.0, round(floatval($supplier->current_balance) - $returnTotal, 2))
                    ]);
                }
            }

            // Post accounting journal for the purchase return
            // recordPurchaseReturnAccounting expects a DebitNote model — create a lightweight proxy
            // Since we use PurchaseReturn directly, we call the journal service with raw amounts
            AccountingEventService::recordPurchaseReturnJournal($ret, $createdByName);

            return $ret;
        });
    }
}
