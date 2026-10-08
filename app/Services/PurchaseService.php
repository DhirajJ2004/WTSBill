<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Repositories\PurchaseRepository;
use App\Validators\PurchaseValidator;
use App\Services\InventoryService;
use App\Services\AccountingEventService;
use App\Services\AccountService;
use App\Services\AuditLogService;
use App\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

class PurchaseService
{
    /**
     * Atomically create and post a purchase bill with complete transactional integrity.
     */
    public static function createPurchase(
        array $input,
        int $companyId,
        ?int $branchId = null,
        mixed $authUser = 'Admin',
        ?int $userId = null
    ): array {
        $userName = is_string($authUser) ? $authUser : ($authUser->name ?? 'Admin');
        $userId = $userId ?: (is_object($authUser) && isset($authUser->id) ? (int)$authUser->id : null);

        // 1. Validation
        $errors = PurchaseValidator::validate($input, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $company = Company::withoutGlobalScopes()->where('id', $companyId)->first();
        if (!$company) {
            return ['success' => false, 'message' => 'Active company context is invalid.'];
        }

        // Branch
        if (!$branchId) {
            $branch = Branch::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $branchId = $branch ? $branch->id : 1;
        }

        // Supplier
        $supplierId = (int)$input['supplier_id'];
        $supplier = Supplier::withoutGlobalScopes()
            ->where('id', $supplierId)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$supplier) {
            return ['success' => false, 'message' => 'Selected supplier is invalid or unauthorized.'];
        }

        // Warehouse
        $warehouseId = !empty($input['warehouse_id']) ? (int)$input['warehouse_id'] : 0;
        if ($warehouseId > 0) {
            $warehouse = Warehouse::withoutGlobalScopes()
                ->where('id', $warehouseId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->first();
            if (!$warehouse) {
                return ['success' => false, 'message' => "Warehouse #{$warehouseId} is invalid or unauthorized."];
            }
        } else {
            $primaryWh = Warehouse::withoutGlobalScopes()->where('company_id', $companyId)->where('is_primary', 1)->first()
                ?: Warehouse::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $warehouseId = $primaryWh ? $primaryWh->id : 1;
        }

        $purchaseDate = !empty($input['purchase_date']) ? substr(trim($input['purchase_date']), 0, 10) : date('Y-m-d');
        $dueDate = !empty($input['due_date']) ? substr(trim($input['due_date']), 0, 10) : date('Y-m-d', strtotime('+30 days'));

        // GST Determination
        $companyState = trim($company->state ?: 'Maharashtra');
        $companyStateCode = trim($company->state_code ?: '27');
        $pos = trim($input['place_of_supply'] ?? ($supplier->state ?: $companyState));
        $posCode = trim($input['place_of_supply_code'] ?? ($supplier->state_code ?: ''));

        $isIgst = false;
        if (!empty($posCode) && !empty($companyStateCode)) {
            $isIgst = ($posCode !== $companyStateCode);
        } elseif (!empty($pos) && !empty($companyState)) {
            $isIgst = (strcasecmp($pos, $companyState) !== 0);
        }

        // Validate line items and calculate line amounts
        $validatedItems = [];
        $grossSubtotal = 0.0;
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;

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
            $unitPrice = (float)($item['unit_price'] ?? ($item['rate'] ?? $product->purchase_price));

            $discRate = (float)($item['discount_rate'] ?? 0.0);
            $discAmount = (float)($item['discount_amount'] ?? 0.0);
            $baseLine = $qty * $unitPrice;

            if ($discRate > 0) {
                $discAmount = round($baseLine * ($discRate / 100.0), 2);
            }
            $taxableValue = round($baseLine - $discAmount, 2);

            $gstRate = isset($item['gst_rate']) ? (float)$item['gst_rate'] : (isset($item['tax_rate']) ? (float)$item['tax_rate'] : (float)$product->tax_rate);
            $cgstRate = 0.0;
            $sgstRate = 0.0;
            $igstRate = 0.0;
            $cgstAmount = 0.0;
            $sgstAmount = 0.0;
            $igstAmount = 0.0;

            if ($isIgst) {
                $igstRate = $gstRate;
                $igstAmount = round($taxableValue * ($igstRate / 100.0), 2);
            } else {
                $cgstRate = $gstRate / 2.0;
                $sgstRate = $gstRate / 2.0;
                $cgstAmount = round($taxableValue * ($cgstRate / 100.0), 2);
                $sgstAmount = round($taxableValue * ($sgstRate / 100.0), 2);
            }

            $lineTotal = round($taxableValue + $cgstAmount + $sgstAmount + $igstAmount, 2);

            $validatedItems[] = [
                'product' => $product,
                'product_id' => $product->id,
                'item_name' => $item['item_name'] ?? $product->name,
                'hsn_sac' => $item['hsn_sac'] ?? ($product->hsn_sac ?: '84818030'),
                'quantity' => $qty,
                'unit' => $item['unit'] ?? ($product->unit ?: 'PCS'),
                'unit_price' => $unitPrice,
                'discount_rate' => $discRate,
                'discount_amount' => $discAmount,
                'taxable_value' => $taxableValue,
                'gst_rate' => $gstRate,
                'cgst_rate' => $cgstRate,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => $sgstRate,
                'sgst_amount' => $sgstAmount,
                'igst_rate' => $igstRate,
                'igst_amount' => $igstAmount,
                'total_amount' => $lineTotal,
            ];

            $grossSubtotal += $taxableValue;
            $totalCgst += $cgstAmount;
            $totalSgst += $sgstAmount;
            $totalIgst += $igstAmount;
        }

        // Bill level discount
        $docDiscount = (float)($input['discount_amount'] ?? 0.0);
        $docDiscountRate = (float)($input['discount_rate'] ?? 0.0);
        if ($docDiscountRate > 0) {
            $docDiscount = round($grossSubtotal * ($docDiscountRate / 100.0), 2);
        }

        $totalLineDiscount = 0.0;
        foreach ($validatedItems as $vi) {
            $totalLineDiscount += (float)$vi['discount_amount'];
        }
        $totalDiscount = round($totalLineDiscount + $docDiscount, 2);

        $subTotal = round($grossSubtotal - $docDiscount, 2);
        $totalTax = round($totalCgst + $totalSgst + $totalIgst, 2);
        $unroundedGrandTotal = $subTotal + $totalTax;
        $roundedGrandTotal = round($unroundedGrandTotal);
        $roundOff = round($roundedGrandTotal - $unroundedGrandTotal, 2);
        $grandTotal = $roundedGrandTotal;

        $status = strtoupper($input['status'] ?? 'POSTED');
        if (!in_array($status, ['DRAFT', 'POSTED', 'PAID'], true)) {
            $status = 'POSTED';
        }

        $paymentStatus = strtoupper($input['payment_status'] ?? ($status === 'PAID' ? 'PAID' : 'UNPAID'));
        $amountPaid = ($paymentStatus === 'PAID') ? $grandTotal : (float)($input['amount_paid'] ?? 0.0);
        $amountDue = max(0.0, round($grandTotal - $amountPaid, 2));

        // -------------------------------------------------------------
        // TRANSACTIONAL ATOMIC EXECUTION (ALL OR NOTHING)
        // -------------------------------------------------------------
        return DB::transaction(function () use (
            $companyId, $branchId, $warehouseId, $supplier, $purchaseDate, $dueDate,
            $pos, $subTotal, $docDiscountRate, $totalDiscount, $totalCgst, $totalSgst,
            $totalIgst, $totalTax, $roundOff, $grandTotal, $amountPaid, $amountDue,
            $status, $paymentStatus, $validatedItems, $input, $userName, $userId
        ) {
            // A. Acquire sequential purchase number under lock
            $purNumber = PurchaseRepository::generateNextPurchaseNumber($companyId, 'PUR');

            // B. Create Purchase Header
            $purchase = Purchase::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'supplier_id' => $supplier->id,
                'purchase_number' => $purNumber,
                'vendor_invoice_number' => $input['vendor_invoice_number'] ?? null,
                'purchase_date' => $purchaseDate,
                'due_date' => $dueDate,
                'reference_no' => $input['reference_no'] ?? null,
                'place_of_supply' => $pos,
                'sub_total' => $subTotal,
                'discount_rate' => $docDiscountRate,
                'discount_amount' => $totalDiscount,
                'cgst_amount' => $totalCgst,
                'sgst_amount' => $totalSgst,
                'igst_amount' => $totalIgst,
                'total_tax' => $totalTax,
                'round_off' => $roundOff,
                'grand_total' => $grandTotal,
                'amount_paid' => $amountPaid,
                'amount_due' => $amountDue,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'notes' => $input['notes'] ?? '',
                'internal_remarks' => $input['internal_remarks'] ?? '',
                'supplier_name' => $supplier->name,
                'supplier_gstin' => $supplier->gstin,
                'billing_address' => $input['billing_address'] ?? $supplier->billing_address,
                'shipping_address' => $input['shipping_address'] ?? ($input['billing_address'] ?? $supplier->billing_address),
                'created_by' => $userId,
            ]);

            // C. Create Purchase Items
            foreach ($validatedItems as $vi) {
                PurchaseItem::create([
                    'company_id' => $companyId,
                    'purchase_id' => $purchase->id,
                    'product_id' => $vi['product_id'],
                    'item_name' => $vi['item_name'],
                    'hsn_sac' => $vi['hsn_sac'],
                    'quantity' => $vi['quantity'],
                    'unit' => $vi['unit'],
                    'unit_price' => $vi['unit_price'],
                    'discount_rate' => $vi['discount_rate'],
                    'discount_amount' => $vi['discount_amount'],
                    'taxable_value' => $vi['taxable_value'],
                    'taxable_amount' => $vi['taxable_value'],
                    'gst_rate' => $vi['gst_rate'],
                    'cgst_rate' => $vi['cgst_rate'],
                    'cgst_amount' => $vi['cgst_amount'],
                    'sgst_rate' => $vi['sgst_rate'],
                    'sgst_amount' => $vi['sgst_amount'],
                    'igst_rate' => $vi['igst_rate'],
                    'igst_amount' => $vi['igst_amount'],
                    'total_amount' => $vi['total_amount'],
                ]);
            }

            // D. Increase Stock Movements & Post Accounting Journal if POSTED
            if ($status !== 'DRAFT') {
                foreach ($validatedItems as $vi) {
                    $prod = $vi['product'];
                    if (strtoupper($prod->product_type ?? '') !== 'SERVICES' && $prod->track_inventory) {
                        $stockRes = InventoryService::recordStockIn(
                            $companyId,
                            $warehouseId,
                            $prod->id,
                            $vi['quantity'],
                            $vi['unit_price'],
                            'PURCHASE',
                            [
                                'reference_type' => 'PURCHASE',
                                'reference_id' => $purchase->id,
                                'reference_number' => $purNumber,
                                'movement_date' => $purchaseDate,
                                'created_by' => $userName,
                                'notes' => "Stock inward from Purchase Bill #{$purNumber}",
                            ]
                        );

                        if (!$stockRes['success']) {
                            throw new \Exception("Stock inward failed: " . $stockRes['message']);
                        }
                    }
                }

                // Double-Entry Accounting Journal
                AccountService::ensureDefaultAccounts($companyId);
                AccountingEventService::recordPurchaseAccounting($purchase, $userName);

                // Supplier Accounts Payable Balance Adjustment
                if ($amountDue > 0) {
                    $supplier->update([
                        'current_balance' => round((float)$supplier->current_balance + $amountDue, 2)
                    ]);
                }
            }

            AuditLogService::log(
                $companyId,
                $userName,
                'PURCHASE_CREATE',
                'Purchase',
                $purchase->id,
                "Created Purchase Bill #{$purNumber} for Supplier: {$supplier->name}, Total: ₹{$grandTotal}"
            );

            return [
                'success' => true,
                'purchase' => $purchase,
                'purchase_id' => $purchase->id,
                'purchase_number' => $purNumber,
                'grand_total' => $grandTotal,
                'message' => "Purchase Bill #{$purNumber} created successfully."
            ];
        });
    }

    /**
     * Cancel / Void a Purchase Bill (Reversing inventory and accounting).
     */
    public static function voidPurchase(int $id, int $companyId, string $userName = 'Admin', string $reason = 'Cancelled'): array
    {
        return DB::transaction(function () use ($id, $companyId, $userName, $reason) {
            $purchase = Purchase::withoutGlobalScopes()
                ->where('id', $id)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$purchase) {
                return ['success' => false, 'message' => "Purchase Bill #{$id} not found or unauthorized."];
            }

            if ($purchase->status === 'CANCELLED') {
                return ['success' => false, 'message' => "Purchase Bill #{$purchase->purchase_number} is already cancelled."];
            }

            // Deduct / reverse the inward stock
            $items = PurchaseItem::where('purchase_id', $id)->where('company_id', $companyId)->get();
            foreach ($items as $item) {
                $product = Product::withoutGlobalScopes()->where('id', $item->product_id)->where('company_id', $companyId)->first();
                if ($product && $product->track_inventory) {
                    InventoryService::recordStockOut(
                        $companyId,
                        $purchase->warehouse_id ?: 1,
                        $product->id,
                        $item->quantity,
                        (float)$item->unit_price,
                        'PURCHASE_RETURN',
                        [
                            'reference_type' => 'PURCHASE_CANCEL',
                            'reference_id' => $purchase->id,
                            'reference_number' => $purchase->purchase_number,
                            'movement_date' => date('Y-m-d'),
                            'created_by' => $userName,
                            'notes' => "Reversed stock upon voiding Purchase Bill #{$purchase->purchase_number}. Reason: {$reason}",
                        ]
                    );
                }
            }

            // Adjust Supplier Payable Balance
            if ($purchase->amount_due > 0) {
                $supplier = Supplier::withoutGlobalScopes()->where('id', $purchase->supplier_id)->where('company_id', $companyId)->first();
                if ($supplier) {
                    $newBalance = max(0.0, round((float)$supplier->current_balance - (float)$purchase->amount_due, 2));
                    $supplier->update(['current_balance' => $newBalance]);
                }
            }

            // Cancel Accounting Journal Entry
            try {
                $origJournal = \App\Models\JournalEntry::where('company_id', $companyId)
                    ->where('reference_type', 'PURCHASE')
                    ->where('reference_id', (string)$purchase->id)
                    ->where('status', 'POSTED')
                    ->first();
                if ($origJournal) {
                    $origJournal->update(['status' => 'CANCELLED']);
                }
            } catch (\Throwable $e) {}

            $purchase->update([
                'status' => 'CANCELLED',
                'amount_due' => 0.0,
                'notes' => trim($purchase->notes . " [VOIDED: {$reason}]"),
            ]);

            AuditLogService::log(
                $companyId,
                $userName,
                'PURCHASE_VOID',
                'Purchase',
                $id,
                "Voided Purchase Bill #{$purchase->purchase_number}. Reason: {$reason}"
            );

            return ['success' => true, 'message' => "Purchase Bill #{$purchase->purchase_number} voided successfully."];
        });
    }

    /**
     * Delete DRAFT purchase.
     */
    public static function deletePurchase(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $purchase = Purchase::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$purchase) {
            return ['success' => false, 'message' => "Purchase Bill #{$id} not found or unauthorized."];
        }

        if ($purchase->status === 'POSTED' || $purchase->status === 'PAID') {
            return ['success' => false, 'message' => "Posted purchase bills cannot be deleted directly. Please void or return the bill."];
        }

        $purchase->delete();

        AuditLogService::log(
            $companyId,
            $userName,
            'PURCHASE_DELETE',
            'Purchase',
            $id,
            "Deleted draft Purchase Bill #{$purchase->purchase_number}"
        );

        return ['success' => true, 'message' => "Purchase Bill #{$purchase->purchase_number} deleted successfully."];
    }
}
