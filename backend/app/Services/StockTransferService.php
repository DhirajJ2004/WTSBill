<?php

namespace App\Services;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use RuntimeException;

class StockTransferService
{
    /**
     * Create a new Stock Transfer Draft.
     */
    public static function createTransfer(int $companyId, array $data, ?string $userName = 'System'): StockTransfer
    {
        $fromWhId = intval($data['from_warehouse_id'] ?? 0);
        $toWhId = intval($data['to_warehouse_id'] ?? 0);

        if ($fromWhId <= 0 || $toWhId <= 0) {
            throw new InvalidArgumentException("Both source and destination warehouses are required.");
        }

        if ($fromWhId === $toWhId) {
            throw new InvalidArgumentException("Source and Destination warehouses cannot be the same.");
        }

        $fromWh = Warehouse::where('company_id', $companyId)->findOrFail($fromWhId);
        $toWh = Warehouse::where('company_id', $companyId)->findOrFail($toWhId);

        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new InvalidArgumentException("Stock transfer must contain at least one item.");
        }

        $company = Company::find($companyId);
        $allowNegative = $company ? (bool)$company->allow_negative_stock : false;

        $transferNumber = $data['transfer_number'] ?? ('TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

        return DB::transaction(function () use ($companyId, $fromWh, $toWh, $items, $transferNumber, $data, $userName, $allowNegative) {
            $transfer = StockTransfer::create([
                'company_id' => $companyId,
                'transfer_number' => $transferNumber,
                'from_branch_id' => $fromWh->branch_id,
                'from_warehouse_id' => $fromWh->id,
                'to_branch_id' => $toWh->branch_id,
                'to_warehouse_id' => $toWh->id,
                'transfer_date' => $data['transfer_date'] ?? date('Y-m-d'),
                'status' => 'DRAFT',
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userName,
            ]);

            foreach ($items as $item) {
                $productId = intval($item['product_id']);
                $qty = floatval($item['quantity'] ?? ($item['quantity_sent'] ?? 0));

                if ($qty <= 0) {
                    throw new InvalidArgumentException("Transfer quantity must be greater than 0.");
                }

                // Check stock availability in source warehouse
                $balance = StockBalance::where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $fromWh->id)
                    ->first();

                $available = $balance ? (floatval($balance->quantity) - floatval($balance->reserved_quantity ?? 0)) : 0.0;

                if (!$allowNegative && $qty > $available) {
                    $prod = Product::find($productId);
                    $prodName = $prod ? $prod->name : "Product #{$productId}";
                    throw new RuntimeException("Insufficient available stock for '{$prodName}' in warehouse '{$fromWh->name}'. Available: {$available}, Requested: {$qty}");
                }

                StockTransferItem::create([
                    'company_id' => $companyId,
                    'transfer_id' => $transfer->id,
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'quantity_sent' => $qty,
                    'quantity_received' => 0.000,
                    'unit' => $item['unit'] ?? 'Pcs',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $transfer->load(['items.product', 'fromWarehouse', 'toWarehouse', 'fromBranch', 'toBranch']);
        });
    }

    /**
     * Approve Transfer.
     */
    public static function approveTransfer(int $transferId, ?string $userName = 'System'): StockTransfer
    {
        $transfer = StockTransfer::findOrFail($transferId);
        if ($transfer->status !== 'DRAFT') {
            throw new RuntimeException("Only DRAFT transfers can be approved.");
        }

        $transfer->update([
            'status' => 'APPROVED',
            'approved_by' => $userName,
        ]);

        return $transfer;
    }

    /**
     * Dispatch Transfer (Stock leaves source warehouse, enters In-Transit).
     */
    public static function dispatchTransfer(int $transferId, ?string $userName = 'System'): StockTransfer
    {
        $transfer = StockTransfer::with('items')->findOrFail($transferId);
        if (!in_array($transfer->status, ['DRAFT', 'APPROVED'])) {
            throw new RuntimeException("Cannot dispatch transfer in status '{$transfer->status}'.");
        }

        $companyId = $transfer->company_id;

        return DB::transaction(function () use ($transfer, $companyId, $userName) {
            foreach ($transfer->items as $item) {
                $qty = floatval($item->quantity_sent ?: $item->quantity);

                // 1. Deduct physical stock from source warehouse
                $sourceBalance = StockBalance::firstOrCreate(
                    ['company_id' => $companyId, 'product_id' => $item->product_id, 'warehouse_id' => $transfer->from_warehouse_id],
                    ['quantity' => 0.000, 'reserved_quantity' => 0.000, 'in_transit_quantity' => 0.000]
                );
                $sourceBalance->quantity = floatval($sourceBalance->quantity) - $qty;
                $sourceBalance->save();

                // 2. Add to in-transit tracking on destination warehouse balance record
                $destBalance = StockBalance::firstOrCreate(
                    ['company_id' => $companyId, 'product_id' => $item->product_id, 'warehouse_id' => $transfer->to_warehouse_id],
                    ['quantity' => 0.000, 'reserved_quantity' => 0.000, 'in_transit_quantity' => 0.000]
                );
                $destBalance->in_transit_quantity = floatval($destBalance->in_transit_quantity) + $qty;
                $destBalance->save();

                // 3. Record stock movement audit
                StockMovement::create([
                    'company_id' => $companyId,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'type' => 'TRANSFER_OUT',
                    'movement_type' => 'TRANSFER_OUT',
                    'direction' => 'OUT',
                    'quantity' => -$qty,
                    'balance_after' => floatval($sourceBalance->quantity),
                    'reference_type' => 'STOCK_TRANSFER',
                    'reference_id' => $transfer->id,
                    'movement_date' => date('Y-m-d'),
                    'notes' => "Dispatched to Warehouse #{$transfer->to_warehouse_id}",
                ]);
            }

            $transfer->update([
                'status' => 'IN_TRANSIT',
                'dispatched_by' => $userName,
            ]);

            return $transfer;
        });
    }

    /**
     * Receive Transfer (Stock received at destination warehouse).
     */
    public static function receiveTransfer(int $transferId, array $receivedQuantities = [], ?string $userName = 'System'): StockTransfer
    {
        $transfer = StockTransfer::with('items')->findOrFail($transferId);
        if (!in_array($transfer->status, ['IN_TRANSIT', 'PARTIALLY_RECEIVED'])) {
            throw new RuntimeException("Transfer is not in transit for receiving (Current status: '{$transfer->status}').");
        }

        $companyId = $transfer->company_id;

        return DB::transaction(function () use ($transfer, $companyId, $receivedQuantities, $userName) {
            $allFullyReceived = true;

            foreach ($transfer->items as $item) {
                $qtySent = floatval($item->quantity_sent ?: $item->quantity);
                $previouslyReceived = floatval($item->quantity_received ?? 0);
                $incomingQty = isset($receivedQuantities[$item->id])
                    ? floatval($receivedQuantities[$item->id])
                    : ($qtySent - $previouslyReceived);

                if ($incomingQty < 0) {
                    throw new InvalidArgumentException("Received quantity cannot be negative.");
                }

                $newTotalReceived = $previouslyReceived + $incomingQty;
                if ($newTotalReceived > $qtySent) {
                    throw new RuntimeException("Total received ({$newTotalReceived}) exceeds sent quantity ({$qtySent}) for item #{$item->id}.");
                }

                $item->quantity_received = $newTotalReceived;
                $item->save();

                if ($incomingQty > 0) {
                    // 1. Decrease In-Transit quantity and increase Physical stock on destination warehouse
                    $destBalance = StockBalance::firstOrCreate(
                        ['company_id' => $companyId, 'product_id' => $item->product_id, 'warehouse_id' => $transfer->to_warehouse_id],
                        ['quantity' => 0.000, 'reserved_quantity' => 0.000, 'in_transit_quantity' => 0.000]
                    );

                    $destBalance->in_transit_quantity = max(0, floatval($destBalance->in_transit_quantity) - $incomingQty);
                    $destBalance->quantity = floatval($destBalance->quantity) + $incomingQty;
                    $destBalance->save();

                    // 2. Record Stock Movement audit
                    StockMovement::create([
                        'company_id' => $companyId,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $transfer->to_warehouse_id,
                        'type' => 'TRANSFER_IN',
                        'movement_type' => 'TRANSFER_IN',
                        'direction' => 'IN',
                        'quantity' => $incomingQty,
                        'balance_after' => floatval($destBalance->quantity),
                        'reference_type' => 'STOCK_TRANSFER',
                        'reference_id' => $transfer->id,
                        'movement_date' => date('Y-m-d'),
                        'notes' => "Received from Warehouse #{$transfer->from_warehouse_id}",
                    ]);
                }

                if ($item->quantity_received < $qtySent) {
                    $allFullyReceived = false;
                }
            }

            $newStatus = $allFullyReceived ? 'RECEIVED' : 'PARTIALLY_RECEIVED';

            $transfer->update([
                'status' => $newStatus,
                'received_by' => $userName,
            ]);

            return $transfer;
        });
    }

    /**
     * Cancel Transfer with inventory reversal.
     */
    public static function cancelTransfer(int $transferId, string $reason = 'Cancelled by user', ?string $userName = 'System'): StockTransfer
    {
        $transfer = StockTransfer::with('items')->findOrFail($transferId);
        if ($transfer->status === 'RECEIVED' || $transfer->status === 'CANCELLED') {
            throw new RuntimeException("Cannot cancel transfer in status '{$transfer->status}'.");
        }

        $companyId = $transfer->company_id;

        return DB::transaction(function () use ($transfer, $companyId, $reason, $userName) {
            if ($transfer->status === 'IN_TRANSIT' || $transfer->status === 'PARTIALLY_RECEIVED') {
                // Revert unreceived stock from in-transit back to source warehouse physical stock
                foreach ($transfer->items as $item) {
                    $qtySent = floatval($item->quantity_sent ?: $item->quantity);
                    $qtyReceived = floatval($item->quantity_received ?? 0);
                    $unreceived = $qtySent - $qtyReceived;

                    if ($unreceived > 0) {
                        // 1. Remove from destination in-transit
                        $destBalance = StockBalance::where('company_id', $companyId)
                            ->where('product_id', $item->product_id)
                            ->where('warehouse_id', $transfer->to_warehouse_id)
                            ->first();
                        if ($destBalance) {
                            $destBalance->in_transit_quantity = max(0, floatval($destBalance->in_transit_quantity) - $unreceived);
                            $destBalance->save();
                        }

                        // 2. Return to source warehouse physical stock
                        $sourceBalance = StockBalance::firstOrCreate(
                            ['company_id' => $companyId, 'product_id' => $item->product_id, 'warehouse_id' => $transfer->from_warehouse_id],
                            ['quantity' => 0.000, 'reserved_quantity' => 0.000, 'in_transit_quantity' => 0.000]
                        );
                        $sourceBalance->quantity = floatval($sourceBalance->quantity) + $unreceived;
                        $sourceBalance->save();

                        // 3. Movement reversal log
                        StockMovement::create([
                            'company_id' => $companyId,
                            'product_id' => $item->product_id,
                            'warehouse_id' => $transfer->from_warehouse_id,
                            'type' => 'TRANSFER_REVERSAL',
                            'movement_type' => 'TRANSFER_REVERSAL',
                            'direction' => 'IN',
                            'quantity' => $unreceived,
                            'balance_after' => floatval($sourceBalance->quantity),
                            'reference_type' => 'STOCK_TRANSFER',
                            'reference_id' => $transfer->id,
                            'movement_date' => date('Y-m-d'),
                            'notes' => "Reversed cancelled transfer: {$reason}",
                        ]);
                    }
                }
            }

            $transfer->update([
                'status' => 'CANCELLED',
                'cancelled_by' => $userName,
                'notes' => ($transfer->notes ? $transfer->notes . ' | ' : '') . "Cancellation Reason: {$reason}",
            ]);

            return $transfer;
        });
    }

    /**
     * Generate Stock Transfer Note Document Payload.
     */
    public static function generateTransferNote(int $transferId): array
    {
        $transfer = StockTransfer::with(['items.product', 'fromWarehouse', 'toWarehouse', 'fromBranch', 'toBranch'])->findOrFail($transferId);

        $lines = [];
        $totalQty = 0.0;
        foreach ($transfer->items as $idx => $it) {
            $qty = floatval($it->quantity_sent ?: $it->quantity);
            $totalQty += $qty;
            $lines[] = [
                'sr_no' => $idx + 1,
                'product_name' => $it->product?->name ?: 'Item',
                'sku' => $it->product?->sku ?: '-',
                'quantity_sent' => $qty,
                'quantity_received' => floatval($it->quantity_received ?? 0),
                'unit' => $it->unit ?: ($it->product?->unit ?: 'Pcs'),
                'notes' => $it->notes ?: '-',
            ];
        }

        return [
            'document_title' => 'STOCK TRANSFER NOTE',
            'transfer_number' => $transfer->transfer_number,
            'transfer_date' => $transfer->transfer_date,
            'status' => $transfer->status,
            'reference_no' => $transfer->reference_no ?: '-',
            'source_branch' => $transfer->fromBranch?->name ?: 'Main Branch',
            'source_warehouse' => $transfer->fromWarehouse?->name ?: 'Main Warehouse',
            'destination_branch' => $transfer->toBranch?->name ?: 'Main Branch',
            'destination_warehouse' => $transfer->toWarehouse?->name ?: 'Main Warehouse',
            'created_by' => $transfer->created_by ?: 'System',
            'approved_by' => $transfer->approved_by ?: '-',
            'dispatched_by' => $transfer->dispatched_by ?: '-',
            'received_by' => $transfer->received_by ?: '-',
            'total_quantity' => $totalQty,
            'items' => $lines,
            'notes' => $transfer->notes,
        ];
    }
}
