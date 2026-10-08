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
use App\Models\Category;
use App\Models\Unit;
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

    /**
     * Create a new Product and record opening stock if provided.
     */
    public static function createProduct(array $data, int $companyId, string $userName = 'Admin'): array
    {
        $errors = \App\Validators\InventoryValidator::validateProduct($data, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        return DB::transaction(function () use ($data, $companyId, $userName) {
            $warehouseId = !empty($data['default_warehouse_id']) ? (int)$data['default_warehouse_id'] : null;

            if (!$warehouseId) {
                $primaryWh = \App\Repositories\InventoryRepository::getPrimaryWarehouse($companyId);
                if (!$primaryWh) {
                    $primaryWh = Warehouse::create([
                        'company_id' => $companyId,
                        'name' => 'Main Warehouse',
                        'code' => 'WH-MAIN',
                        'is_primary' => true,
                        'is_default' => true,
                        'is_active' => true,
                    ]);
                }
                $warehouseId = $primaryWh->id;
            }

            $openingStock = (float)($data['opening_stock'] ?? 0.0);
            $purchasePrice = (float)($data['purchase_price'] ?? 0.0);
            $sellingPrice = (float)($data['selling_price'] ?? ($data['sales_price'] ?? 0.0));
            $taxRate = (float)($data['tax_rate'] ?? ($data['gst_rate'] ?? 0.0));
            $minStock = (float)($data['min_stock_level'] ?? ($data['min_stock_alert'] ?? 0.0));

            $product = Product::create([
                'company_id' => $companyId,
                'name' => trim($data['name']),
                'sku' => trim($data['sku'] ?? ''),
                'barcode' => trim($data['barcode'] ?? ''),
                'hsn_sac' => trim($data['hsn_sac'] ?? ''),
                'category_id' => !empty($data['category_id']) ? (int)$data['category_id'] : null,
                'unit' => trim($data['unit'] ?? 'PCS'),
                'product_type' => trim($data['product_type'] ?? 'Goods'),
                'purchase_price' => $purchasePrice,
                'sales_price' => $sellingPrice,
                'selling_price' => $sellingPrice,
                'mrp' => (float)($data['mrp'] ?? $sellingPrice),
                'tax_rate' => $taxRate,
                'gst_rate' => $taxRate,
                'opening_stock' => $openingStock,
                'current_stock' => $openingStock,
                'min_stock_level' => $minStock,
                'min_stock_alert' => $minStock,
                'track_inventory' => isset($data['track_inventory']) ? (bool)$data['track_inventory'] : true,
                'allow_negative_stock' => (bool)($data['allow_negative_stock'] ?? false),
                'default_warehouse_id' => $warehouseId,
                'description' => trim($data['description'] ?? ''),
                'is_active' => true,
            ]);

            StockBalance::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $product->id,
                'quantity' => $openingStock,
                'available_quantity' => $openingStock,
                'reserved_quantity' => 0.0,
            ]);

            if ($openingStock > 0) {
                StockMovement::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $product->id,
                    'movement_type' => 'OPENING_STOCK',
                    'type' => 'OPENING_STOCK',
                    'direction' => 'IN',
                    'quantity' => $openingStock,
                    'unit_cost' => $purchasePrice,
                    'total_cost' => round($openingStock * $purchasePrice, 2),
                    'balance_after' => $openingStock,
                    'reference_type' => 'OPENING_STOCK',
                    'reference_number' => 'OP-STOCK',
                    'movement_date' => date('Y-m-d'),
                    'created_by' => $userName,
                    'notes' => 'Initial opening stock setup',
                ]);
            }

            \App\Services\AuditLogService::log(
                $companyId,
                $userName,
                'PRODUCT_CREATE',
                'Product',
                $product->id,
                "Created product '{$product->name}' (SKU: {$product->sku}) with opening stock {$openingStock}"
            );

            return [
                'success' => true,
                'product' => $product,
                'product_id' => $product->id,
                'message' => "Product '{$product->name}' created successfully."
            ];
        });
    }

    /**
     * Update an existing product.
     */
    public static function updateProduct(int $id, array $data, int $companyId, string $userName = 'Admin'): array
    {
        $product = Product::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$product) {
            return ['success' => false, 'message' => "Product #{$id} not found or unauthorized."];
        }

        $errors = \App\Validators\InventoryValidator::validateProduct($data, $companyId, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $purchasePrice = isset($data['purchase_price']) ? (float)$data['purchase_price'] : $product->purchase_price;
        $sellingPrice = isset($data['selling_price']) ? (float)$data['selling_price'] : ($data['sales_price'] ?? $product->selling_price);

        $product->update([
            'name' => trim($data['name'] ?? $product->name),
            'sku' => trim($data['sku'] ?? $product->sku),
            'barcode' => trim($data['barcode'] ?? $product->barcode),
            'hsn_sac' => trim($data['hsn_sac'] ?? $product->hsn_sac),
            'category_id' => isset($data['category_id']) ? (int)$data['category_id'] : $product->category_id,
            'unit' => trim($data['unit'] ?? $product->unit),
            'product_type' => trim($data['product_type'] ?? $product->product_type),
            'purchase_price' => $purchasePrice,
            'sales_price' => $sellingPrice,
            'selling_price' => $sellingPrice,
            'mrp' => isset($data['mrp']) ? (float)$data['mrp'] : $product->mrp,
            'tax_rate' => isset($data['tax_rate']) ? (float)$data['tax_rate'] : $product->tax_rate,
            'min_stock_level' => isset($data['min_stock_level']) ? (float)$data['min_stock_level'] : $product->min_stock_level,
            'min_stock_alert' => isset($data['min_stock_level']) ? (float)$data['min_stock_level'] : $product->min_stock_alert,
            'description' => trim($data['description'] ?? $product->description),
            'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : $product->is_active,
        ]);

        \App\Services\AuditLogService::log(
            $companyId,
            $userName,
            'PRODUCT_UPDATE',
            'Product',
            $product->id,
            "Updated product '{$product->name}' (ID #{$product->id})"
        );

        return [
            'success' => true,
            'product' => $product,
            'product_id' => $product->id,
            'message' => "Product '{$product->name}' updated successfully."
        ];
    }

    /**
     * Delete Product (with reference integrity protection).
     */
    public static function deleteProduct(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $product = Product::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$product) {
            return ['success' => false, 'message' => "Product #{$id} not found or unauthorized."];
        }

        $invoiceItemsCount = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $companyId)
            ->where('invoice_items.product_id', $id)
            ->whereNull('invoices.deleted_at')
            ->count();

        if ($invoiceItemsCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete product: {$invoiceItemsCount} active invoice items reference this product."
            ];
        }

        $purchaseItemsCount = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.company_id', $companyId)
            ->where('purchase_items.product_id', $id)
            ->whereNull('purchases.deleted_at')
            ->count();

        if ($purchaseItemsCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete product: {$purchaseItemsCount} purchase items reference this product."
            ];
        }

        $product->delete();

        \App\Services\AuditLogService::log(
            $companyId,
            $userName,
            'PRODUCT_DELETE',
            'Product',
            $id,
            "Deleted product '{$product->name}' (ID #{$id})"
        );

        return ['success' => true, 'message' => "Product '{$product->name}' deleted successfully."];
    }

    /**
     * Record Stock In (Purchases / GRN / Inward).
     */
    public static function recordStockIn(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        float $unitCost = 0.0,
        string $movementType = 'PURCHASE',
        array $metadata = []
    ): array {
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Stock in quantity must be greater than zero.'];
        }

        return DB::transaction(function () use ($companyId, $warehouseId, $productId, $quantity, $unitCost, $movementType, $metadata) {
            $product = Product::withoutGlobalScopes()
                ->where('id', $productId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$product) {
                return ['success' => false, 'message' => "Product #{$productId} not found or unauthorized."];
            }

            $warehouse = Warehouse::withoutGlobalScopes()
                ->where('id', $warehouseId)
                ->where('company_id', $companyId)
                ->first();

            if (!$warehouse) {
                return ['success' => false, 'message' => "Warehouse #{$warehouseId} not found or unauthorized."];
            }

            $balance = StockBalance::where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$balance) {
                $balance = StockBalance::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'reserved_quantity' => 0.0,
                ]);
            }

            $newWhQty = (float)$balance->quantity + $quantity;
            $balance->update([
                'quantity' => $newWhQty,
                'available_quantity' => $newWhQty - (float)$balance->reserved_quantity,
            ]);

            $newProductStock = (float)$product->current_stock + $quantity;
            $product->update(['current_stock' => $newProductStock]);

            $cost = ($unitCost > 0) ? $unitCost : (float)$product->purchase_price;

            $movement = StockMovement::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'movement_type' => $movementType,
                'type' => $movementType,
                'direction' => 'IN',
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'total_cost' => round($quantity * $cost, 2),
                'balance_after' => $newProductStock,
                'reference_type' => $metadata['reference_type'] ?? 'PURCHASE',
                'reference_id' => $metadata['reference_id'] ?? null,
                'reference_number' => $metadata['reference_number'] ?? null,
                'movement_date' => $metadata['movement_date'] ?? date('Y-m-d'),
                'created_by' => $metadata['created_by'] ?? 'System',
                'notes' => $metadata['notes'] ?? 'Stock Inward movement',
            ]);

            return [
                'success' => true,
                'movement_id' => $movement->id,
                'new_stock' => $newProductStock,
                'warehouse_stock' => $newWhQty,
                'message' => "Added {$quantity} units to '{$product->name}' successfully."
            ];
        });
    }

    /**
     * Record Stock Out (Sales / Delivery Challan / Outward).
     */
    public static function recordStockOut(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        float $unitCost = 0.0,
        string $movementType = 'SALE',
        array $metadata = []
    ): array {
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Stock out quantity must be greater than zero.'];
        }

        return DB::transaction(function () use ($companyId, $warehouseId, $productId, $quantity, $unitCost, $movementType, $metadata) {
            $product = Product::withoutGlobalScopes()
                ->where('id', $productId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$product) {
                return ['success' => false, 'message' => "Product #{$productId} not found or unauthorized."];
            }

            $warehouse = Warehouse::withoutGlobalScopes()
                ->where('id', $warehouseId)
                ->where('company_id', $companyId)
                ->first();

            if (!$warehouse) {
                return ['success' => false, 'message' => "Warehouse #{$warehouseId} not found or unauthorized."];
            }

            $balance = StockBalance::where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $currentWhQty = $balance ? (float)$balance->quantity : 0.0;
            $allowNegative = (bool)($product->allow_negative_stock ?? false);

            if ($currentWhQty < $quantity && !$allowNegative) {
                return [
                    'success' => false,
                    'message' => "Insufficient stock in warehouse '{$warehouse->name}'. Available: {$currentWhQty}, Requested: {$quantity}."
                ];
            }

            if (!$balance) {
                $balance = StockBalance::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'reserved_quantity' => 0.0,
                ]);
            }

            $newWhQty = $currentWhQty - $quantity;
            $balance->update([
                'quantity' => $newWhQty,
                'available_quantity' => $newWhQty - (float)$balance->reserved_quantity,
            ]);

            $newProductStock = (float)$product->current_stock - $quantity;
            $product->update(['current_stock' => $newProductStock]);

            $cost = ($unitCost > 0) ? $unitCost : (float)$product->purchase_price;

            $movement = StockMovement::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'movement_type' => $movementType,
                'type' => $movementType,
                'direction' => 'OUT',
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'total_cost' => round($quantity * $cost, 2),
                'balance_after' => $newProductStock,
                'reference_type' => $metadata['reference_type'] ?? 'INVOICE',
                'reference_id' => $metadata['reference_id'] ?? null,
                'reference_number' => $metadata['reference_number'] ?? null,
                'movement_date' => $metadata['movement_date'] ?? date('Y-m-d'),
                'created_by' => $metadata['created_by'] ?? 'System',
                'notes' => $metadata['notes'] ?? 'Stock Outward movement',
            ]);

            return [
                'success' => true,
                'movement_id' => $movement->id,
                'new_stock' => $newProductStock,
                'warehouse_stock' => $newWhQty,
                'message' => "Deducted {$quantity} units from '{$product->name}' successfully."
            ];
        });
    }

    /**
     * Adjust stock up or down.
     */
    public static function adjustStock(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        string $type,
        string $reason = '',
        string $userName = 'Admin'
    ): array {
        $validation = \App\Validators\InventoryValidator::validateAdjustment([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'adjustment_type' => $type,
        ], $companyId);

        if (!empty($validation)) {
            return ['success' => false, 'errors' => $validation, 'message' => reset($validation)];
        }

        $isPositive = in_array(strtoupper($type), ['ADD', 'IN', 'EXCESS', 'CORRECTION_ADD'], true);

        if ($isPositive) {
            return self::recordStockIn($companyId, $warehouseId, $productId, $quantity, 0.0, 'STOCK_ADJUSTMENT_IN', [
                'reference_type' => 'STOCK_ADJUSTMENT',
                'created_by' => $userName,
                'notes' => "Stock Adjustment (Add): {$reason}",
            ]);
        } else {
            return self::recordStockOut($companyId, $warehouseId, $productId, $quantity, 0.0, 'STOCK_ADJUSTMENT_OUT', [
                'reference_type' => 'STOCK_ADJUSTMENT',
                'created_by' => $userName,
                'notes' => "Stock Adjustment (Reduce): {$reason}",
            ]);
        }
    }

    /**
     * Transfer stock between warehouses atomically.
     */
    public static function transferStock(
        int $companyId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $productId,
        float $quantity,
        string $notes = '',
        string $userName = 'Admin'
    ): array {
        $validation = \App\Validators\InventoryValidator::validateTransfer([
            'source_warehouse_id' => $fromWarehouseId,
            'destination_warehouse_id' => $toWarehouseId,
            'product_id' => $productId,
            'quantity' => $quantity,
        ], $companyId);

        if (!empty($validation)) {
            return ['success' => false, 'errors' => $validation, 'message' => reset($validation)];
        }

        return DB::transaction(function () use ($companyId, $fromWarehouseId, $toWarehouseId, $productId, $quantity, $notes, $userName) {
            $product = Product::withoutGlobalScopes()
                ->where('id', $productId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$product) {
                return ['success' => false, 'message' => "Product #{$productId} not found or unauthorized."];
            }

            $srcBalance = StockBalance::where('company_id', $companyId)
                ->where('warehouse_id', $fromWarehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $srcQty = $srcBalance ? (float)$srcBalance->quantity : 0.0;
            $allowNegative = (bool)($product->allow_negative_stock ?? false);

            if ($srcQty < $quantity && !$allowNegative) {
                return [
                    'success' => false,
                    'message' => "Cannot transfer: Insufficient stock in source warehouse. Available: {$srcQty}, Transfer: {$quantity}."
                ];
            }

            $newSrcQty = $srcQty - $quantity;
            if (!$srcBalance) {
                $srcBalance = StockBalance::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $fromWarehouseId,
                    'product_id' => $productId,
                    'quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'reserved_quantity' => 0.0,
                ]);
            }
            $srcBalance->update([
                'quantity' => $newSrcQty,
                'available_quantity' => $newSrcQty - (float)$srcBalance->reserved_quantity,
            ]);

            $destBalance = StockBalance::where('company_id', $companyId)
                ->where('warehouse_id', $toWarehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$destBalance) {
                $destBalance = StockBalance::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $toWarehouseId,
                    'product_id' => $productId,
                    'quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'reserved_quantity' => 0.0,
                ]);
            }
            $newDestQty = (float)$destBalance->quantity + $quantity;
            $destBalance->update([
                'quantity' => $newDestQty,
                'available_quantity' => $newDestQty - (float)$destBalance->reserved_quantity,
            ]);

            $cost = (float)$product->purchase_price;
            $transferNo = 'TRF-' . strtoupper(substr(uniqid(), -6));

            StockMovement::create([
                'company_id' => $companyId,
                'warehouse_id' => $fromWarehouseId,
                'product_id' => $productId,
                'movement_type' => 'TRANSFER_OUT',
                'type' => 'TRANSFER_OUT',
                'direction' => 'OUT',
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'total_cost' => round($quantity * $cost, 2),
                'balance_after' => (float)$product->current_stock,
                'reference_type' => 'TRANSFER',
                'reference_number' => $transferNo,
                'movement_date' => date('Y-m-d'),
                'created_by' => $userName,
                'notes' => "Transferred to WH #{$toWarehouseId}. {$notes}",
            ]);

            StockMovement::create([
                'company_id' => $companyId,
                'warehouse_id' => $toWarehouseId,
                'product_id' => $productId,
                'movement_type' => 'TRANSFER_IN',
                'type' => 'TRANSFER_IN',
                'direction' => 'IN',
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'total_cost' => round($quantity * $cost, 2),
                'balance_after' => (float)$product->current_stock,
                'reference_type' => 'TRANSFER',
                'reference_number' => $transferNo,
                'movement_date' => date('Y-m-d'),
                'created_by' => $userName,
                'notes' => "Transferred from WH #{$fromWarehouseId}. {$notes}",
            ]);

            \App\Services\AuditLogService::log(
                $companyId,
                $userName,
                'STOCK_TRANSFER',
                'Product',
                $productId,
                "Transferred {$quantity} units of '{$product->name}' from WH #{$fromWarehouseId} to WH #{$toWarehouseId} (Ref: {$transferNo})"
            );

            return [
                'success' => true,
                'transfer_number' => $transferNo,
                'source_quantity' => $newSrcQty,
                'destination_quantity' => $newDestQty,
                'message' => "Transferred {$quantity} units of '{$product->name}' successfully."
            ];
        });
    }

    /**
     * Create a new Warehouse.
     */
    public static function createWarehouse(array $data, int $companyId, string $userName = 'Admin'): array
    {
        $errors = \App\Validators\InventoryValidator::validateWarehouse($data, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $code = trim($data['code'] ?? '');
        if (empty($code)) {
            $code = 'WH-' . strtoupper(substr(uniqid(), -4));
        }

        $warehouse = Warehouse::create([
            'company_id' => $companyId,
            'name' => trim($data['name']),
            'code' => $code,
            'address' => trim($data['address'] ?? ''),
            'city' => trim($data['city'] ?? ''),
            'state' => trim($data['state'] ?? ''),
            'pincode' => trim($data['pincode'] ?? ''),
            'manager_name' => trim($data['manager_name'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'is_primary' => (bool)($data['is_primary'] ?? false),
            'is_default' => (bool)($data['is_default'] ?? false),
            'is_active' => true,
        ]);

        \App\Services\AuditLogService::log(
            $companyId,
            $userName,
            'WAREHOUSE_CREATE',
            'Warehouse',
            $warehouse->id,
            "Created Warehouse '{$warehouse->name}' (Code: {$warehouse->code})"
        );

        return [
            'success' => true,
            'warehouse' => $warehouse,
            'warehouse_id' => $warehouse->id,
            'message' => "Warehouse '{$warehouse->name}' created successfully."
        ];
    }

    /**
     * Create a new Category.
     */
    public static function createCategory(array $data, int $companyId, string $userName = 'Admin'): array
    {
        $errors = \App\Validators\InventoryValidator::validateCategory($data, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $category = Category::create([
            'company_id' => $companyId,
            'name' => trim($data['name']),
            'code' => trim($data['code'] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['name']), 0, 6))),
            'description' => trim($data['description'] ?? ''),
            'status' => 'ACTIVE',
        ]);

        return [
            'success' => true,
            'category' => $category,
            'category_id' => $category->id,
            'message' => "Category '{$category->name}' created successfully."
        ];
    }

    /**
     * Create a new Unit.
     */
    public static function createUnit(array $data, int $companyId, string $userName = 'Admin'): array
    {
        $errors = \App\Validators\InventoryValidator::validateUnit($data, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $unit = Unit::create([
            'company_id' => $companyId,
            'name' => trim($data['name']),
            'short_name' => strtoupper(trim($data['short_name'])),
            'type' => trim($data['type'] ?? 'Unit'),
            'decimal_precision' => (int)($data['decimal_precision'] ?? 0),
            'status' => 'ACTIVE',
        ]);

        return [
            'success' => true,
            'unit' => $unit,
            'unit_id' => $unit->id,
            'message' => "Unit '{$unit->name}' created successfully."
        ];
    }
}
