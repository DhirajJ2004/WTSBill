<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\Batch;
use App\Models\SerialNumber;
use App\Models\Company;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

class InventoryService
{
    /**
     * Determine direction from movement type.
     */
    public static function getDirectionForMovementType(string $movementType): string
    {
        $inTypes = [
            'OPENING_STOCK',
            'PURCHASE',
            'SALES_RETURN',
            'STOCK_ADJUSTMENT_IN',
            'TRANSFER_IN',
            'REVERSAL_IN',
        ];

        return in_array(strtoupper($movementType), $inTypes, true) ? 'IN' : 'OUT';
    }

    /**
     * Atomically record a stock movement in the ledger and synchronize cached balances.
     * The STOCK MOVEMENT LEDGER is the single source of truth.
     */
    public static function recordStockMovement(
        int $companyId,
        int $warehouseId,
        int $productId,
        string $movementType,
        float $quantity,
        ?string $direction = null,
        ?float $unitCost = null,
        ?int $branchId = null,
        ?int $batchId = null,
        ?int $serialNumberId = null,
        ?string $refType = null,
        ?int $refId = null,
        ?string $refNumber = null,
        ?string $movementDate = null,
        ?string $createdBy = null,
        ?string $notes = null,
        ?bool $allowNegativeStock = null
    ): StockMovement {
        return DB::transaction(function () use (
            $companyId, $warehouseId, $productId, $movementType, $quantity,
            $direction, $unitCost, $branchId, $batchId, $serialNumberId,
            $refType, $refId, $refNumber, $movementDate, $createdBy, $notes,
            $allowNegativeStock
        ) {
            // Cross-company isolation: validate warehouse belongs to this company
            $warehouse = Warehouse::withoutGlobalScopes()->find($warehouseId);
            if (!$warehouse) {
                throw new \InvalidArgumentException("Warehouse #{$warehouseId} not found.");
            }
            if ((int)$warehouse->company_id !== (int)$companyId) {
                throw new \InvalidArgumentException("Forbidden: Warehouse #{$warehouseId} belongs to another company.");
            }

            // Cross-company isolation: validate product belongs to this company
            $product = Product::withoutGlobalScopes()->find($productId);
            if (!$product) {
                throw new \InvalidArgumentException("Product #{$productId} not found.");
            }
            if ((int)$product->company_id !== (int)$companyId) {
                throw new \InvalidArgumentException("Forbidden: Product #{$productId} belongs to another company.");
            }

            if ($quantity <= 0) {
                throw new \InvalidArgumentException("Movement quantity must be greater than zero. Provided: {$quantity}");
            }

            // Derive or validate direction
            $dir = strtoupper($direction ?: static::getDirectionForMovementType($movementType));
            if (!in_array($dir, ['IN', 'OUT'], true)) {
                throw new \InvalidArgumentException("Invalid movement direction '{$dir}'. Must be IN or OUT.");
            }

            $signedDelta = ($dir === 'IN') ? $quantity : -$quantity;
            $effUnitCost = ($unitCost !== null) ? floatval($unitCost) : floatval($product->purchase_price ?: 0);
            $totalCost = round($quantity * $effUnitCost, 2);

            // Fetch or create warehouse stock balance
            $balance = StockBalance::where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->first();

            if (!$balance) {
                $existingTotal = StockBalance::where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->sum('quantity');

                $initialSeed = ($existingTotal == 0 && floatval($product->current_stock) > 0)
                    ? floatval($product->current_stock)
                    : 0.0;

                $balance = StockBalance::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                    'quantity' => $initialSeed,
                    'reserved_quantity' => 0.0,
                    'available_quantity' => $initialSeed,
                ]);
            }

            $currentWhQty = floatval($balance->quantity);
            $newWhQty = $currentWhQty + $signedDelta;

            // Strict Negative Stock Protection
            $companyAllowNeg = (bool)Company::withoutGlobalScopes()->where('id', $companyId)->value('allow_negative_stock');
            $allowNeg = $allowNegativeStock ?? (bool)($product->allow_negative_stock || $companyAllowNeg);

            if ($newWhQty < 0 && !$allowNeg) {
                throw new \InvalidArgumentException(
                    "Insufficient stock for product '{$product->name}' in warehouse #{$warehouseId}. Available: {$currentWhQty}, Requested OUT: {$quantity}."
                );
            }

            // Handle Batch tracking update if applicable
            if ($batchId) {
                $batch = Batch::where('company_id', $companyId)->find($batchId);
                if ($batch) {
                    $newBatchQty = floatval($batch->quantity) + $signedDelta;
                    if ($newBatchQty < 0 && !$allowNeg) {
                        throw new \InvalidArgumentException("Insufficient batch stock for Batch '{$batch->batch_number}'. Available: {$batch->quantity}, Requested: {$quantity}.");
                    }
                    $batch->update([
                        'quantity' => $newBatchQty,
                        'status' => ($newBatchQty <= 0) ? 'DEPLETED' : 'ACTIVE',
                    ]);
                }
            }

            // Handle Serial Number lifecycle update if applicable
            if ($serialNumberId) {
                $serial = SerialNumber::where('company_id', $companyId)->find($serialNumberId);
                if ($serial) {
                    if ($dir === 'OUT') {
                        $serial->update([
                            'status' => ($movementType === 'SALE') ? 'SOLD' : 'LOST',
                            'invoice_id' => ($movementType === 'SALE') ? $refId : $serial->invoice_id,
                        ]);
                    } elseif ($dir === 'IN') {
                        $serial->update([
                            'status' => 'AVAILABLE',
                            'warehouse_id' => $warehouseId,
                        ]);
                    }
                }
            }

            // Update Weighted Average Cost on IN movements
            if ($dir === 'IN' && $effUnitCost > 0 && $product->valuation_method === 'WEIGHTED_AVERAGE') {
                $prevTotalQty = floatval($product->current_stock);
                $prevCost = floatval($product->purchase_price);
                if ($prevTotalQty > 0) {
                    $newWac = (($prevTotalQty * $prevCost) + ($quantity * $effUnitCost)) / ($prevTotalQty + $quantity);
                    $product->update(['purchase_price' => round($newWac, 2)]);
                } else {
                    $product->update(['purchase_price' => round($effUnitCost, 2)]);
                }
            }

            // Create authoritative Stock Movement row
            $movement = StockMovement::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'batch_id' => $batchId,
                'serial_number_id' => $serialNumberId,
                'type' => $movementType,
                'movement_type' => $movementType,
                'direction' => $dir,
                'quantity' => $quantity,
                'unit_cost' => $effUnitCost,
                'total_cost' => $totalCost,
                'balance_after' => $newWhQty,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'reference_number' => $refNumber,
                'movement_date' => $movementDate ?: date('Y-m-d'),
                'created_by' => $createdBy ?: 'System',
                'notes' => $notes ?: "Movement {$movementType} ({$dir}) for {$quantity} units",
            ]);

            // Synchronize warehouse StockBalance
            $balance->update([
                'quantity' => $newWhQty,
                'available_quantity' => max(0, $newWhQty - floatval($balance->reserved_quantity)),
                'last_movement_id' => $movement->id,
            ]);

            // Synchronize Product aggregate current_stock
            $aggregateStock = StockBalance::where('company_id', $companyId)
                ->where('product_id', $productId)
                ->sum('quantity');

            $product->update(['current_stock' => $aggregateStock]);

            return $movement;
        });
    }

    /**
     * Process Opening Stock registration.
     */
    public static function processOpeningStock(array $input): StockMovement
    {
        $user = AuthMiddleware::getUser();
        $companyId = $user ? (int)$user->current_company_id : AuthMiddleware::getTenantId();
        if (isset($input['company_id'])) {
            AuthMiddleware::validateTenantAccess((int)$input['company_id']);
        }
        $branchId = isset($input['branch_id']) ? AuthMiddleware::validateBranchAccess((int)$input['branch_id']) : AuthMiddleware::getBranchId();
        $warehouseId = \App\Auth\WorkspaceContext::getAuthorizedWarehouseId($companyId, !empty($input['warehouse_id']) ? intval($input['warehouse_id']) : null);
        if ($warehouseId <= 0) {
            throw new \Exception("Cannot record opening stock: No valid warehouse found for Company #{$companyId}.");
        }
        $productId = intval($input['product_id']);
        $quantity = floatval($input['quantity']);
        $unitCost = floatval($input['unit_cost'] ?? 0);

        return static::recordStockMovement(
            companyId: $companyId,
            warehouseId: $warehouseId,
            productId: $productId,
            movementType: 'OPENING_STOCK',
            quantity: $quantity,
            direction: 'IN',
            unitCost: $unitCost,
            branchId: $branchId,
            batchId: $input['batch_id'] ?? null,
            serialNumberId: $input['serial_number_id'] ?? null,
            refType: 'OPENING_BALANCE',
            refNumber: 'OPENING-' . date('Ymd'),
            movementDate: $input['opening_date'] ?? date('Y-m-d'),
            createdBy: $user ? $user->name : 'Admin',
            notes: $input['notes'] ?? 'Initial Opening Stock entry'
        );
    }

    /**
     * Create and commit a transaction-safe Stock Adjustment.
     */
    public static function createAdjustment(
        int $companyId,
        int $warehouseId,
        array $items,
        string $reason,
        ?string $notes = null,
        string $createdByName = 'Admin',
        ?int $branchId = null
    ): StockAdjustment {
        return DB::transaction(function () use ($companyId, $warehouseId, $items, $reason, $notes, $createdByName, $branchId) {
            $count = StockAdjustment::where('company_id', $companyId)->count() + 1;
            $adjNo = 'ADJ-' . date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

            $adjustment = StockAdjustment::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'adjustment_number' => $adjNo,
                'adjustment_date' => date('Y-m-d'),
                'reason' => $reason,
                'notes' => $notes ?: '',
                'created_by' => $createdByName,
            ]);

            foreach ($items as $item) {
                $productId = intval($item['product_id']);
                $qty = floatval($item['quantity']);
                $type = strtoupper($item['type'] ?? 'INCREASE'); // INCREASE / DECREASE
                $unitCost = floatval($item['unit_cost'] ?? 0);

                $adjItem = StockAdjustmentItem::create([
                    'company_id' => $companyId,
                    'adjustment_id' => $adjustment->id,
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $productId,
                    'type' => $type,
                    'adjustment_type' => $type,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                ]);

                $movementType = ($type === 'INCREASE') ? 'STOCK_ADJUSTMENT_IN' : 'STOCK_ADJUSTMENT_OUT';
                $direction = ($type === 'INCREASE') ? 'IN' : 'OUT';

                // Categorize specific damage/expiry movement types if specified in reason
                $reasonUpper = strtoupper($reason);
                if ($type === 'DECREASE') {
                    if (str_contains($reasonUpper, 'DAMAGE')) $movementType = 'DAMAGE';
                    elseif (str_contains($reasonUpper, 'LOSS')) $movementType = 'LOSS';
                    elseif (str_contains($reasonUpper, 'EXPIR')) $movementType = 'EXPIRY';
                }

                static::recordStockMovement(
                    companyId: $companyId,
                    warehouseId: $warehouseId,
                    productId: $productId,
                    movementType: $movementType,
                    quantity: $qty,
                    direction: $direction,
                    unitCost: $unitCost,
                    branchId: $branchId,
                    batchId: $item['batch_id'] ?? null,
                    refType: 'STOCK_ADJUSTMENT',
                    refId: $adjustment->id,
                    refNumber: $adjNo,
                    movementDate: date('Y-m-d'),
                    createdBy: $createdByName,
                    notes: "Stock Adjustment #{$adjNo}: Reason - {$reason}"
                );
            }

            return $adjustment;
        });
    }

    /**
     * Create Stock Transfer (Dispatches TRANSFER_OUT from source warehouse).
     */
    public static function createTransfer(
        int $companyId,
        int $fromWarehouseId,
        int $toWarehouseId,
        array $items,
        ?string $refNo = null,
        ?string $notes = null,
        ?int $branchId = null
    ): StockTransfer {
        return DB::transaction(function () use ($companyId, $fromWarehouseId, $toWarehouseId, $items, $refNo, $notes, $branchId) {
            if ($fromWarehouseId === $toWarehouseId) {
                throw new \InvalidArgumentException('Source and destination warehouses cannot be the same.');
            }

            $count = StockTransfer::where('company_id', $companyId)->count() + 1;
            $trfNo = 'TRF-' . date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
            $user = AuthMiddleware::getUser();
            $createdByName = $user ? $user->name : 'Admin';

            $transfer = StockTransfer::create([
                'company_id' => $companyId,
                'transfer_number' => $trfNo,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'transfer_date' => date('Y-m-d'),
                'status' => 'IN_TRANSIT',
                'reference_no' => $refNo,
                'notes' => $notes ?: '',
            ]);

            foreach ($items as $it) {
                $productId = intval($it['product_id']);
                $qty = floatval($it['quantity']);

                StockTransferItem::create([
                    'company_id' => $companyId,
                    'transfer_id' => $transfer->id,
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $productId,
                    'quantity' => $qty,
                ]);

                $sourceBal = StockBalance::where('company_id', $companyId)
                    ->where('warehouse_id', $fromWarehouseId)
                    ->where('product_id', $productId)
                    ->first();
                $avail = $sourceBal ? floatval($sourceBal->available_quantity ?: $sourceBal->quantity) : 0.0;
                if ($avail < $qty) {
                    $prodObj = Product::find($productId);
                    $prodName = $prodObj ? $prodObj->name : "#{$productId}";
                    throw new \InvalidArgumentException("Insufficient stock for product '{$prodName}' in source warehouse #{$fromWarehouseId}. Available: {$avail}, Requested: {$qty}.");
                }

                // Record TRANSFER_OUT immediately upon dispatch
                static::recordStockMovement(
                    companyId: $companyId,
                    warehouseId: $fromWarehouseId,
                    productId: $productId,
                    movementType: 'TRANSFER_OUT',
                    quantity: $qty,
                    direction: 'OUT',
                    branchId: $branchId,
                    batchId: $it['batch_id'] ?? null,
                    serialNumberId: $it['serial_number_id'] ?? null,
                    refType: 'STOCK_TRANSFER',
                    refId: $transfer->id,
                    refNumber: $trfNo,
                    movementDate: date('Y-m-d'),
                    createdBy: $createdByName,
                    notes: "Stock Transfer #{$trfNo} to Warehouse #{$toWarehouseId}",
                    allowNegativeStock: false
                );
            }

            return $transfer;
        });
    }

    /**
     * Mark Stock Transfer as RECEIVED at destination warehouse.
     */
    public static function receiveTransfer(int $transferId): StockTransfer
    {
        return DB::transaction(function () use ($transferId) {
            $transfer = StockTransfer::with('items')->findOrFail($transferId);

            if ($transfer->status === 'RECEIVED') {
                throw new \InvalidArgumentException("Transfer #{$transfer->transfer_number} has already been received.");
            }

            if ($transfer->status === 'CANCELLED') {
                throw new \InvalidArgumentException("Cannot receive a cancelled transfer #{$transfer->transfer_number}.");
            }

            $user = AuthMiddleware::getUser();
            $createdByName = $user ? $user->name : 'Admin';

            foreach ($transfer->items as $it) {
                static::recordStockMovement(
                    companyId: $transfer->company_id,
                    warehouseId: $transfer->to_warehouse_id,
                    productId: $it->product_id,
                    movementType: 'TRANSFER_IN',
                    quantity: floatval($it->quantity),
                    direction: 'IN',
                    refType: 'STOCK_TRANSFER',
                    refId: $transfer->id,
                    refNumber: $transfer->transfer_number,
                    movementDate: date('Y-m-d'),
                    createdBy: $createdByName,
                    notes: "Received Transfer #{$transfer->transfer_number} from Warehouse #{$transfer->from_warehouse_id}"
                );
            }

            $transfer->update(['status' => 'RECEIVED']);
            return $transfer;
        });
    }

    /**
     * Cancel an In-Transit Stock Transfer (Reverses TRANSFER_OUT back to source warehouse).
     */
    public static function cancelTransfer(int $transferId, string $reason = 'Cancelled by user'): StockTransfer
    {
        return DB::transaction(function () use ($transferId, $reason) {
            $transfer = StockTransfer::with('items')->findOrFail($transferId);

            if ($transfer->status !== 'IN_TRANSIT') {
                throw new \InvalidArgumentException("Only IN_TRANSIT transfers can be cancelled.");
            }

            $user = AuthMiddleware::getUser();
            $createdByName = $user ? $user->name : 'Admin';

            foreach ($transfer->items as $it) {
                static::recordStockMovement(
                    companyId: $transfer->company_id,
                    warehouseId: $transfer->from_warehouse_id,
                    productId: $it->product_id,
                    movementType: 'MANUAL_CORRECTION',
                    quantity: floatval($it->quantity),
                    direction: 'IN',
                    refType: 'STOCK_TRANSFER',
                    refId: $transfer->id,
                    refNumber: $transfer->transfer_number,
                    movementDate: date('Y-m-d'),
                    createdBy: $createdByName,
                    notes: "Cancelled Transfer #{$transfer->transfer_number} - Returned stock to source: {$reason}"
                );
            }

            $transfer->update([
                'status' => 'CANCELLED',
                'notes' => trim($transfer->notes . " | Cancelled: {$reason}"),
            ]);

            return $transfer;
        });
    }

    /**
     * Create a Physical Stock Count sheet.
     */
    public static function createStockCount(
        int $companyId,
        int $warehouseId,
        array $items,
        ?string $notes = null,
        string $countedBy = 'Admin',
        ?int $branchId = null
    ): StockCount {
        return DB::transaction(function () use ($companyId, $warehouseId, $items, $notes, $countedBy, $branchId) {
            $countNo = 'SC-' . date('Y') . '-' . str_pad(StockCount::where('company_id', $companyId)->count() + 1, 4, '0', STR_PAD_LEFT);

            $stockCount = StockCount::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'count_number' => $countNo,
                'count_date' => date('Y-m-d'),
                'status' => 'DRAFT',
                'counted_by' => $countedBy,
                'notes' => $notes ?: '',
            ]);

            foreach ($items as $it) {
                $productId = intval($it['product_id']);
                $physicalQty = floatval($it['physical_quantity']);

                $balance = StockBalance::where('company_id', $companyId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->first();

                $systemQty = $balance ? floatval($balance->quantity) : 0.0;
                $difference = $physicalQty - $systemQty;

                $product = Product::find($productId);
                $unitCost = $product ? floatval($product->purchase_price) : 0.0;

                StockCountItem::create([
                    'company_id' => $companyId,
                    'stock_count_id' => $stockCount->id,
                    'product_id' => $productId,
                    'system_quantity' => $systemQty,
                    'physical_quantity' => $physicalQty,
                    'difference' => $difference,
                    'unit_cost' => $unitCost,
                    'adjustment_created' => false,
                ]);
            }

            return $stockCount;
        });
    }

    /**
     * Reconcile/Approve a Stock Count sheet (Automatically creates Adjustments for variances).
     */
    public static function reconcileStockCount(int $stockCountId): StockCount
    {
        return DB::transaction(function () use ($stockCountId) {
            $stockCount = StockCount::with('items')->findOrFail($stockCountId);

            if ($stockCount->status === 'COMPLETED') {
                throw new \InvalidArgumentException('Stock count has already been reconciled.');
            }

            $adjustItems = [];
            foreach ($stockCount->items as $item) {
                if ($item->difference != 0) {
                    $adjustItems[] = [
                        'product_id' => $item->product_id,
                        'quantity' => abs($item->difference),
                        'type' => ($item->difference > 0) ? 'INCREASE' : 'DECREASE',
                        'unit_cost' => $item->unit_cost,
                    ];
                }
            }

            if (!empty($adjustItems)) {
                $adjustment = static::createAdjustment(
                    companyId: $stockCount->company_id,
                    warehouseId: $stockCount->warehouse_id,
                    items: $adjustItems,
                    reason: "Physical Count Difference from #{$stockCount->count_number}",
                    notes: "Automated adjustment for Physical Stock Count #{$stockCount->count_number}",
                    createdByName: $stockCount->counted_by,
                    branchId: $stockCount->branch_id
                );

                foreach ($stockCount->items as $item) {
                    $item->update([
                        'adjustment_created' => true,
                        'adjustment_id' => $adjustment->id,
                    ]);
                }
            }

            $stockCount->update(['status' => 'COMPLETED']);
            return $stockCount;
        });
    }

    /**
     * Admin Reconciliation & Discrepancy Repair Utility.
     * Verifies Stock Ledger movements vs cached StockBalance and Product stock totals.
     */
    public static function recalculateInventory(int $companyId, ?int $warehouseId = null, bool $repair = false): array
    {
        return DB::transaction(function () use ($companyId, $warehouseId, $repair) {
            $query = Product::where('company_id', $companyId);
            $products = $query->get();
            $discrepancies = [];

            $whQuery = Warehouse::where('company_id', $companyId);
            if ($warehouseId) {
                $whQuery->where('id', $warehouseId);
            }
            $warehouses = $whQuery->get();

            foreach ($products as $prod) {
                $ledgerTotal = 0.0;

                foreach ($warehouses as $wh) {
                    // Calculate true balance from movements ledger
                    $inQty = StockMovement::where('company_id', $companyId)
                        ->where('warehouse_id', $wh->id)
                        ->where('product_id', $prod->id)
                        ->where('direction', 'IN')
                        ->sum('quantity');

                    $outQty = StockMovement::where('company_id', $companyId)
                        ->where('warehouse_id', $wh->id)
                        ->where('product_id', $prod->id)
                        ->where('direction', 'OUT')
                        ->sum('quantity');

                    $trueWhBalance = round($inQty - $outQty, 3);
                    $ledgerTotal += $trueWhBalance;

                    $cachedBalance = StockBalance::where('company_id', $companyId)
                        ->where('warehouse_id', $wh->id)
                        ->where('product_id', $prod->id)
                        ->first();

                    $cachedQty = $cachedBalance ? round(floatval($cachedBalance->quantity), 3) : 0.0;

                    if ($trueWhBalance !== $cachedQty) {
                        $discrepancies[] = [
                            'product_id' => $prod->id,
                            'product_name' => $prod->name,
                            'warehouse_id' => $wh->id,
                            'warehouse_name' => $wh->name,
                            'ledger_quantity' => $trueWhBalance,
                            'cached_quantity' => $cachedQty,
                            'difference' => round($trueWhBalance - $cachedQty, 3),
                            'repaired' => $repair,
                        ];

                        if ($repair) {
                            if (!$cachedBalance) {
                                StockBalance::create([
                                    'company_id' => $companyId,
                                    'warehouse_id' => $wh->id,
                                    'product_id' => $prod->id,
                                    'quantity' => $trueWhBalance,
                                    'available_quantity' => $trueWhBalance,
                                ]);
                            } else {
                                $cachedBalance->update([
                                    'quantity' => $trueWhBalance,
                                    'available_quantity' => max(0, $trueWhBalance - floatval($cachedBalance->reserved_quantity)),
                                ]);
                            }
                        }
                    }
                }

                // Check aggregate product current_stock
                $prodCurrentStock = round(floatval($prod->current_stock), 3);
                if ($ledgerTotal !== $prodCurrentStock) {
                    if ($repair) {
                        $prod->update(['current_stock' => $ledgerTotal]);
                    }
                }
            }

            return [
                'total_products_checked' => count($products),
                'discrepancies_found' => count($discrepancies),
                'discrepancies' => $discrepancies,
                'repaired' => $repair,
            ];
        });
    }

    /**
     * Get FEFO recommended batches (Earliest Expiry First).
     */
    public static function getFEFOBatches(int $companyId, int $productId, ?int $warehouseId = null): array
    {
        $query = Batch::where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->where('status', 'ACTIVE');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->orderBy('expiry_date', 'asc')->get()->toArray();
    }

    /**
     * Get Comprehensive Valuation Summary.
     */
    public static function getValuationSummary(int $companyId): array
    {
        $products = Product::with('category')->where('company_id', $companyId)->get();

        $totalValuation = 0.0;
        $items = [];
        $lowStockCount = 0;
        $outOfStockCount = 0;

        foreach ($products as $p) {
            $stock = floatval($p->current_stock);
            $cost = floatval($p->purchase_price);
            $val = round($stock * $cost, 2);
            $totalValuation += $val;

            $status = 'NORMAL';
            if ($stock <= 0) {
                $status = 'OUT_OF_STOCK';
                $outOfStockCount++;
            } elseif ($stock <= floatval($p->min_stock_alert ?: 10)) {
                $status = 'LOW_STOCK';
                $lowStockCount++;
            }

            $items[] = [
                'product_id' => $p->id,
                'product_name' => $p->name,
                'sku' => $p->sku,
                'category' => $p->category ? $p->category->name : 'General',
                'current_stock' => $stock,
                'unit' => $p->unit ?: 'Pcs',
                'unit_cost' => $cost,
                'sales_price' => floatval($p->sales_price),
                'total_valuation' => $val,
                'reorder_level' => floatval($p->reorder_level ?: 10),
                'reorder_quantity' => floatval($p->reorder_quantity ?: 50),
                'status' => $status,
            ];
        }

        return [
            'total_products' => count($products),
            'total_valuation' => round($totalValuation, 2),
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'items' => $items,
        ];
    }
}
