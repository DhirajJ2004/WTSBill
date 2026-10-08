<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\TaxRate;
use App\Services\InventoryService;
use App\Services\AccountingEventService;
use App\Services\AuditLogService;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

class PurchaseInvoiceService
{
    /**
     * Resolve GST rate for a product from the database.
     * Never hardcoded — reads from products.gst_rate or TaxRate table.
     */
    public static function resolveProductGstRate(int $productId, int $companyId): float
    {
        $product = Product::withoutGlobalScopes()->where('company_id', $companyId)->find($productId);
        if (!$product) {
            return 0.0;
        }

        // If product has a direct GST rate configured, use it
        if (!empty($product->gst_rate) && floatval($product->gst_rate) > 0) {
            return floatval($product->gst_rate);
        }

        // Look up from TaxRate table
        if (!empty($product->tax_rate_id)) {
            $taxRate = TaxRate::where('company_id', $companyId)->find($product->tax_rate_id);
            if ($taxRate) {
                return floatval($taxRate->rate);
            }
        }

        return 0.0; // Default to 0% if unconfigured — never hardcode 18%
    }

    /**
     * Calculate and format totals for a purchase document.
     * Freight GST rate comes from input, not hardcoded.
     */
    public static function calculateTotals(
        array $items,
        bool $isIgst,
        float $docDiscountPercent = 0.0,
        float $docDiscountFixed = 0.0,
        float $freight = 0.0,
        bool $freightTaxable = false,
        float $freightGstRate = 0.0,     // ← explicit rate, NOT hardcoded 18%
        float $additionalCharges = 0.0,
        bool $additionalTaxable = false,
        float $additionalChargesGstRate = 0.0  // ← explicit rate, NOT hardcoded 18%
    ): array {
        $subTotal       = 0.0;
        $totalDiscount  = 0.0;
        $taxableValue   = 0.0;
        $totalCgst      = 0.0;
        $totalSgst      = 0.0;
        $totalIgst      = 0.0;
        $totalCess      = 0.0;
        $processedItems = [];

        foreach ($items as $item) {
            $qty      = floatval($item['quantity'] ?? 0);
            $price    = floatval($item['unit_price'] ?? 0);
            $gstRate  = floatval($item['gst_rate'] ?? $item['tax_rate'] ?? 0.0); // do NOT default to 18
            $cessRate = floatval($item['cess_rate'] ?? 0.0);

            $gross       = $qty * $price;
            $discRate    = floatval($item['discount_rate'] ?? 0.0);
            $discAmount  = floatval($item['discount_amount'] ?? 0.0);

            if ($discRate > 0) {
                $discAmount = round($gross * ($discRate / 100), 2);
            }

            $itemTaxable = max(0.00, $gross - $discAmount);

            $cgstRate = 0; $sgstRate = 0; $igstRate = 0;
            $cgstAmt  = 0; $sgstAmt  = 0; $igstAmt  = 0; $cessAmt = 0;

            if ($isIgst) {
                $igstRate = $gstRate;
                $igstAmt  = round(($itemTaxable * $igstRate) / 100, 2);
            } else {
                $cgstRate = $gstRate / 2;
                $sgstRate = $gstRate / 2;
                $cgstAmt  = round(($itemTaxable * $cgstRate) / 100, 2);
                $sgstAmt  = round(($itemTaxable * $sgstRate) / 100, 2);
            }

            if ($cessRate > 0) {
                $cessAmt = round(($itemTaxable * $cessRate) / 100, 2);
            }

            $lineTotal = $itemTaxable + $cgstAmt + $sgstAmt + $igstAmt + $cessAmt;

            $processedItems[] = array_merge($item, [
                'quantity'       => $qty,
                'unit_price'     => $price,
                'discount_rate'  => $discRate,
                'discount_amount'=> $discAmount,
                'taxable_value'  => $itemTaxable,
                'gst_rate'       => $gstRate,
                'cgst_rate'      => $cgstRate,
                'cgst_amount'    => $cgstAmt,
                'sgst_rate'      => $sgstRate,
                'sgst_amount'    => $sgstAmt,
                'igst_rate'      => $igstRate,
                'igst_amount'    => $igstAmt,
                'cess_rate'      => $cessRate,
                'cess_amount'    => $cessAmt,
                'total_amount'   => $lineTotal,
            ]);

            $subTotal      += $gross;
            $totalDiscount += $discAmount;
            $taxableValue  += $itemTaxable;
            $totalCgst     += $cgstAmt;
            $totalSgst     += $sgstAmt;
            $totalIgst     += $igstAmt;
            $totalCess     += $cessAmt;
        }

        // Apply document-level discount
        if ($docDiscountPercent > 0) {
            $docDiscAmount = round($taxableValue * ($docDiscountPercent / 100), 2);
            $taxableValue  = max(0.00, $taxableValue - $docDiscAmount);
            $totalDiscount += $docDiscAmount;
        } elseif ($docDiscountFixed > 0) {
            $taxableValue   = max(0.00, $taxableValue - $docDiscountFixed);
            $totalDiscount += $docDiscountFixed;
        }

        // Freight charges — use the caller-supplied GST rate (never hardcode 18%)
        if ($freight > 0 && $freightTaxable && $freightGstRate > 0) {
            $freightTax = round(($freight * $freightGstRate) / 100, 2);
            if ($isIgst) {
                $totalIgst += $freightTax;
            } else {
                $totalCgst += round($freightTax / 2, 2);
                $totalSgst += round($freightTax - round($freightTax / 2, 2), 2);
            }
        }

        // Additional charges — use the caller-supplied GST rate (never hardcode 18%)
        if ($additionalCharges > 0 && $additionalTaxable && $additionalChargesGstRate > 0) {
            $addTax = round(($additionalCharges * $additionalChargesGstRate) / 100, 2);
            if ($isIgst) {
                $totalIgst += $addTax;
            } else {
                $totalCgst += round($addTax / 2, 2);
                $totalSgst += round($addTax - round($addTax / 2, 2), 2);
            }
        }

        $totalTax    = round($totalCgst + $totalSgst + $totalIgst + $totalCess, 2);
        $rawGrandTotal = $taxableValue + $totalTax + $freight + $additionalCharges;
        $grandTotal  = round($rawGrandTotal);
        $roundOff    = round($grandTotal - $rawGrandTotal, 2);

        return [
            'items'          => $processedItems,
            'sub_total'      => round($subTotal, 2),
            'discount_amount'=> round($totalDiscount, 2),
            'taxable_value'  => round($taxableValue, 2),
            'cgst_amount'    => round($totalCgst, 2),
            'sgst_amount'    => round($totalSgst, 2),
            'igst_amount'    => round($totalIgst, 2),
            'cess_amount'    => round($totalCess, 2),
            'total_tax'      => $totalTax,
            'round_off'      => $roundOff,
            'grand_total'    => $grandTotal,
        ];
    }

    /**
     * Create a new purchase document (Draft or Posted).
     * Every financial and inventory operation is transactional.
     * Supplier and product ownership is strictly validated.
     */
    public static function createPurchase(array $input): Purchase
    {
        return DB::transaction(function () use ($input) {
            $user      = AuthMiddleware::getUser();
            $companyId = $user ? (int)$user->current_company_id : (int)($input['company_id'] ?? 0);
            $branchId  = AuthMiddleware::getBranchId() ?: (intval($input['branch_id'] ?? 0) ?: null);
            $fy        = AuthMiddleware::getFinancialYear() ?: '2026-27';

            if (!$companyId) {
                throw new \InvalidArgumentException('Forbidden: Active company workspace context is required.');
            }

            // --- Supplier Validation (Company-scoped) ---
            $supplierId = intval($input['supplier_id'] ?? 0);
            if (!$supplierId) {
                throw new \InvalidArgumentException('Supplier is required.');
            }
            $supplier = Supplier::withoutGlobalScopes()->find($supplierId);
            if (!$supplier) {
                throw new \InvalidArgumentException("Supplier #{$supplierId} not found.");
            }
            if ((int)$supplier->company_id !== $companyId) {
                throw new \InvalidArgumentException("Forbidden: Supplier #{$supplierId} belongs to another company.");
            }

            // --- Duplicate Supplier Invoice / Bill Number Check ---
            $vendorInvNumber = trim((string)($input['vendor_invoice_number'] ?? $input['bill_no'] ?? $input['bill_number'] ?? ''));
            if ($vendorInvNumber !== '') {
                $existing = Purchase::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('supplier_id', $supplier->id)
                    ->where('vendor_invoice_number', $vendorInvNumber)
                    ->where('status', '!=', 'CANCELLED')
                    ->first();
                if ($existing) {
                    throw new \InvalidArgumentException("Duplicate supplier invoice number '{$vendorInvNumber}' already exists for supplier '{$supplier->name}'.");
                }
            }

            // --- Warehouse Validation (Company-scoped) ---
            $warehouseId = intval($input['warehouse_id'] ?? 0);
            if ($warehouseId > 0) {
                $wh = Warehouse::withoutGlobalScopes()->find($warehouseId);
                if (!$wh || (int)$wh->company_id !== $companyId) {
                    throw new \InvalidArgumentException("Warehouse #{$warehouseId} is invalid or belongs to another company.");
                }
                if (!$wh->is_active) {
                    throw new \InvalidArgumentException("Warehouse '{$wh->name}' is inactive.");
                }
            } else {
                $defaultWh = Warehouse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('is_active', 1)
                    ->orderBy('is_primary', 'desc')
                    ->orderBy('id')
                    ->first();
                if (!$defaultWh) {
                    throw new \InvalidArgumentException("No active warehouse found for company #{$companyId}. Cannot create purchase.");
                }
                $warehouseId = $defaultWh->id;
            }

            // --- Items Validation ---
            $items = $input['items'] ?? [];
            if (empty($items)) {
                throw new \InvalidArgumentException('Please add at least one product to the purchase.');
            }

            // Validate each product belongs to this company
            foreach ($items as $idx => $item) {
                $pId = intval($item['product_id'] ?? 0);
                if (!$pId) {
                    throw new \InvalidArgumentException("Line item #{$idx}: product_id is required.");
                }
                $product = Product::withoutGlobalScopes()->find($pId);
                if (!$product) {
                    throw new \InvalidArgumentException("Line item #{$idx}: Product #{$pId} not found.");
                }
                if ((int)$product->company_id !== $companyId) {
                    throw new \InvalidArgumentException("Forbidden: Product #{$pId} belongs to another company.");
                }
                $qty = floatval($item['quantity'] ?? $item['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new \InvalidArgumentException("Line item #{$idx} ('{$product->name}'): Quantity must be greater than zero.");
                }
                $price = floatval($item['unit_price'] ?? $item['price'] ?? $item['rate'] ?? 0);
                if ($price < 0) {
                    throw new \InvalidArgumentException("Line item #{$idx} ('{$product->name}'): Price cannot be negative.");
                }

                // If gst_rate not supplied, look it up from product/tax configuration — never default to 18%
                if (!isset($item['gst_rate']) || $item['gst_rate'] === '' || $item['gst_rate'] === null) {
                    $items[$idx]['gst_rate'] = self::resolveProductGstRate($pId, $companyId);
                }
                $items[$idx]['quantity'] = $qty;
                $items[$idx]['unit_price'] = $price;
            }

            // --- GST Direction ---
            $placeOfSupply = $input['place_of_supply'] ?? $supplier->state_code ?? '27';
            $companyStateCode = '27'; // Will be resolved from company GSTIN below
            // Resolve company state from GSTIN
            $company = \App\Models\Company::withoutGlobalScopes()->find($companyId);
            if ($company && !empty($company->gstin) && strlen($company->gstin) >= 2) {
                $companyStateCode = substr($company->gstin, 0, 2);
            }
            $isIgst = (substr($placeOfSupply, 0, 2) !== $companyStateCode);

            $calc = self::calculateTotals(
                $items,
                $isIgst,
                floatval($input['discount_rate'] ?? 0.00),
                floatval($input['discount_amount'] ?? 0.00),
                floatval($input['freight_amount'] ?? 0.00),
                filter_var($input['freight_taxable'] ?? false, FILTER_VALIDATE_BOOLEAN),
                floatval($input['freight_gst_rate'] ?? 0.00),      // caller supplies rate
                floatval($input['additional_charges'] ?? 0.00),
                filter_var($input['additional_charges_taxable'] ?? false, FILTER_VALIDATE_BOOLEAN),
                floatval($input['additional_charges_gst_rate'] ?? 0.00)  // caller supplies rate
            );

            $status = strtoupper($input['status'] ?? 'POSTED');
            if (!in_array($status, ['DRAFT', 'POSTED'], true)) {
                $status = 'POSTED';
            }

            $pNumber = DocumentNumberService::generateNextNumber($companyId, $branchId, $fy, 'PURCHASE');
            $purchaseDate = $input['purchase_date'] ?? $input['bill_date'] ?? date('Y-m-d');
            $dueDate = $input['due_date'] ?? date('Y-m-d', strtotime('+30 days'));

            $purchase = Purchase::create([
                'company_id'                 => $companyId,
                'branch_id'                  => $branchId,
                'warehouse_id'               => $warehouseId,
                'supplier_id'                => $supplier->id,
                'purchase_order_id'          => intval($input['purchase_order_id'] ?? 0) ?: null,
                'purchase_number'            => $pNumber,
                'vendor_invoice_number'      => $vendorInvNumber ?: null,
                'purchase_date'              => $purchaseDate,
                'due_date'                   => $dueDate,
                'reference_no'               => $input['reference_no'] ?? null,
                'place_of_supply'            => $placeOfSupply,
                'sub_total'                  => $calc['sub_total'],
                'cgst_amount'               => $calc['cgst_amount'],
                'sgst_amount'               => $calc['sgst_amount'],
                'igst_amount'               => $calc['igst_amount'],
                'total_tax'                  => $calc['total_tax'],
                'freight_amount'             => floatval($input['freight_amount'] ?? 0.00),
                'freight_taxable'            => filter_var($input['freight_taxable'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'additional_charges'         => floatval($input['additional_charges'] ?? 0.00),
                'additional_charges_taxable' => filter_var($input['additional_charges_taxable'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'discount_rate'              => floatval($input['discount_rate'] ?? 0.00),
                'discount_amount'            => $calc['discount_amount'],
                'round_off'                  => $calc['round_off'],
                'grand_total'                => $calc['grand_total'],
                'amount_paid'                => 0.00,
                'amount_due'                 => $calc['grand_total'],
                'status'                     => $status,
                'payment_status'             => 'UNPAID',
                'notes'                      => $input['notes'] ?? '',
                'internal_remarks'           => $input['internal_remarks'] ?? '',
                'supplier_name'              => $supplier->name,
                'supplier_gstin'             => $supplier->gstin,
                'billing_address'            => $supplier->address_line1 ?? '',
                'shipping_address'           => $supplier->address_line1 ?? '',
                'created_by'                 => $user ? $user->id : null,
            ]);

            foreach ($calc['items'] as $pi) {
                PurchaseItem::create([
                    'company_id'     => $companyId,
                    'purchase_id'    => $purchase->id,
                    'product_id'     => $pi['product_id'],
                    'item_name'      => $pi['item_name'] ?? $pi['name'] ?? Product::withoutGlobalScopes()->find($pi['product_id'])?->name ?? 'Item',
                    'description'    => $pi['description'] ?? null,
                    'hsn_sac'        => $pi['hsn_sac'] ?? '',
                    'quantity'       => $pi['quantity'],
                    'unit'           => $pi['unit'] ?? 'Pcs',
                    'unit_price'     => $pi['unit_price'],
                    'discount_rate'  => $pi['discount_rate'],
                    'discount_amount'=> $pi['discount_amount'],
                    'taxable_value'  => $pi['taxable_value'],
                    'gst_rate'       => $pi['gst_rate'],
                    'cgst_rate'      => $pi['cgst_rate'],
                    'cgst_amount'    => $pi['cgst_amount'],
                    'sgst_rate'      => $pi['sgst_rate'],
                    'sgst_amount'    => $pi['sgst_amount'],
                    'igst_rate'      => $pi['igst_rate'],
                    'igst_amount'    => $pi['igst_amount'],
                    'cess_rate'      => $pi['cess_rate'] ?? 0.00,
                    'cess_amount'    => $pi['cess_amount'] ?? 0.00,
                    'tax_amount'     => round($pi['cgst_amount'] + $pi['sgst_amount'] + $pi['igst_amount'] + ($pi['cess_amount'] ?? 0.00), 2),
                    'total_amount'   => $pi['total_amount'],
                ]);
            }

            // On POSTED: update supplier AP balance and inventory
            if ($status === 'POSTED') {
                $postInput = array_merge($input, ['warehouse_id' => $warehouseId]);
                self::_postInventoryAndAccounting($purchase, $calc, $user, $companyId, $branchId, $postInput);
            }

            return $purchase;
        });
    }

    /**
     * Post a draft purchase invoice.
     * Records inventory IN movements and accounting journals.
     */
    public static function postPurchase(int $id): Purchase
    {
        return DB::transaction(function () use ($id) {
            $user     = AuthMiddleware::getUser();
            $purchase = Purchase::withoutGlobalScopes()->with('items')->findOrFail($id);

            // Tenant check
            if ($user && (int)$user->current_company_id !== (int)$purchase->company_id) {
                throw new \InvalidArgumentException('Forbidden: You do not have access to this purchase invoice.');
            }

            if ($purchase->status !== 'DRAFT') {
                throw new \InvalidArgumentException('Only DRAFT purchase invoices can be posted.');
            }

            $companyId = (int)$purchase->company_id;
            $branchId  = $purchase->branch_id;

            $purchase->update(['status' => 'POSTED']);

            $calc = [
                'cgst_amount' => floatval($purchase->cgst_amount),
                'sgst_amount' => floatval($purchase->sgst_amount),
                'igst_amount' => floatval($purchase->igst_amount),
            ];

            self::_postInventoryAndAccounting($purchase, $calc, $user, $companyId, $branchId, []);

            return $purchase->refresh();
        });
    }

    /**
     * Internal helper: update inventory, supplier AP balance, and accounting journal.
     * Called from both createPurchase (when POSTED) and postPurchase.
     */
    private static function _postInventoryAndAccounting(
        Purchase $purchase,
        array $calc,
        $user,
        int $companyId,
        ?int $branchId,
        array $input
    ): void {
        $createdByName = $user ? $user->name : 'System';

        // Resolve warehouse — validated against company, no hardcoded fallback to #1
        $warehouseId = intval($input['warehouse_id'] ?? $purchase->warehouse_id ?? 0);
        if (!$warehouseId) {
            // Default to the first active warehouse for this company
            $defaultWh = Warehouse::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_active', 1)
                ->orderBy('is_primary', 'desc')
                ->orderBy('id')
                ->first();
            if (!$defaultWh) {
                throw new \InvalidArgumentException("No active warehouse found for company #{$companyId}. Cannot post purchase invoice.");
            }
            $warehouseId = $defaultWh->id;
        } else {
            // Validate warehouse belongs to this company
            $wh = Warehouse::withoutGlobalScopes()->find($warehouseId);
            if (!$wh || (int)$wh->company_id !== $companyId) {
                throw new \InvalidArgumentException("Warehouse #{$warehouseId} is invalid or belongs to another company.");
            }
            if (!$wh->is_active) {
                throw new \InvalidArgumentException("Warehouse '{$wh->name}' is inactive.");
            }
        }

        // Record PURCHASE (IN) movement for each line item
        $purchaseItems = $purchase->items ?? \App\Models\PurchaseItem::where('purchase_id', $purchase->id)->get();
        foreach ($purchaseItems as $it) {
            InventoryService::recordStockMovement(
                companyId:    $companyId,
                warehouseId:  $warehouseId,
                productId:    (int)$it->product_id,
                movementType: 'PURCHASE',
                quantity:     floatval($it->quantity),
                direction:    'IN',
                unitCost:     floatval($it->unit_price),
                branchId:     $branchId,
                batchId:      $it->batch_id ?? null,
                refType:      'PURCHASE_INVOICE',
                refId:        $purchase->id,
                refNumber:    $purchase->purchase_number,
                movementDate: $purchase->purchase_date,
                createdBy:    $createdByName,
                notes:        "Stock received under Purchase Bill #{$purchase->purchase_number}"
            );
        }

        // Update Supplier Accounts Payable balance
        // Payable increases: current_balance goes MORE NEGATIVE (supplier owes money to us in payable sense)
        // Convention: positive current_balance = amount we OWE to supplier
        $due = floatval($purchase->amount_due);
        if ($due > 0) {
            $supplier = Supplier::withoutGlobalScopes()->find($purchase->supplier_id);
            if ($supplier) {
                $supplier->update([
                    'current_balance' => round(floatval($supplier->current_balance) + $due, 2)
                ]);
            }
        }

        // Post double-entry accounting journal
        AccountingEventService::recordPurchaseAccounting($purchase, $createdByName);
    }

    /**
     * Cancel a purchase invoice.
     * Reverses inventory and AP balance if it was POSTED.
     */
    public static function cancelPurchase(int $id, string $reason = 'Cancelled by authorized user'): Purchase
    {
        return DB::transaction(function () use ($id, $reason) {
            $user     = AuthMiddleware::getUser();
            $companyId = $user ? (int)$user->current_company_id : null;

            $purchase = Purchase::withoutGlobalScopes()->with('items')->findOrFail($id);

            if ($companyId && (int)$purchase->company_id !== $companyId) {
                throw new \InvalidArgumentException('Forbidden: You do not have access to this purchase invoice.');
            }

            if ($purchase->status === 'CANCELLED') {
                throw new \InvalidArgumentException('Purchase invoice is already cancelled.');
            }

            $oldStatus = $purchase->status;
            $purchase->update([
                'status'           => 'CANCELLED',
                'internal_remarks' => "Cancelled by " . ($user ? $user->name : 'User') . " on " . date('Y-m-d H:i:s') . ". Reason: {$reason}"
            ]);

            // Reverse inventory if it was POSTED
            if ($oldStatus === 'POSTED') {
                // Find default warehouse for reversal
                $warehouseId = intval($purchase->warehouse_id ?? 0);
                if (!$warehouseId) {
                    $defaultWh = Warehouse::withoutGlobalScopes()
                        ->where('company_id', $purchase->company_id)
                        ->where('is_active', 1)
                        ->orderBy('id')
                        ->first();
                    $warehouseId = $defaultWh ? $defaultWh->id : 1;
                }

                foreach ($purchase->items as $it) {
                    InventoryService::recordStockMovement(
                        companyId:    (int)$purchase->company_id,
                        warehouseId:  $warehouseId,
                        productId:    (int)$it->product_id,
                        movementType: 'PURCHASE_RETURN',
                        quantity:     floatval($it->quantity),
                        direction:    'OUT',
                        unitCost:     floatval($it->unit_price),
                        branchId:     $purchase->branch_id,
                        refType:      'PURCHASE_INVOICE',
                        refId:        $purchase->id,
                        refNumber:    $purchase->purchase_number,
                        movementDate: date('Y-m-d'),
                        createdBy:    $user ? $user->name : 'System',
                        notes:        "Cancellation reversal for Purchase #{$purchase->purchase_number}: {$reason}"
                    );
                }

                // Revert supplier AP balance
                $due = floatval($purchase->amount_due);
                if ($due > 0) {
                    $supplier = Supplier::withoutGlobalScopes()->find($purchase->supplier_id);
                    if ($supplier) {
                        $supplier->update([
                            'current_balance' => round(floatval($supplier->current_balance) - $due, 2)
                        ]);
                    }
                }
            }

            return $purchase->refresh();
        });
    }

    /**
     * Duplicate a purchase invoice as a new DRAFT.
     */
    public static function duplicatePurchase(int $id): Purchase
    {
        $original = Purchase::withoutGlobalScopes()->with('items')->findOrFail($id);

        $input = [
            'supplier_id'       => $original->supplier_id,
            'purchase_order_id' => $original->purchase_order_id,
            'purchase_date'     => date('Y-m-d'),
            'due_date'          => date('Y-m-d', strtotime('+30 days')),
            'reference_no'      => $original->reference_no,
            'place_of_supply'   => $original->place_of_supply,
            'status'            => 'DRAFT',
            'notes'             => $original->notes,
            'discount_rate'     => $original->discount_rate,
            'discount_amount'   => $original->discount_amount,
            'freight_amount'    => $original->freight_amount,
            'freight_taxable'   => $original->freight_taxable,
            'additional_charges'            => $original->additional_charges,
            'additional_charges_taxable'    => $original->additional_charges_taxable,
            'items' => $original->items->map(function ($it) {
                return [
                    'product_id'  => $it->product_id,
                    'item_name'   => $it->item_name,
                    'description' => $it->description,
                    'hsn_sac'     => $it->hsn_sac,
                    'quantity'    => $it->quantity,
                    'unit'        => $it->unit,
                    'unit_price'  => $it->unit_price,
                    'gst_rate'    => $it->gst_rate,   // preserved from original, not hardcoded
                    'cess_rate'   => $it->cess_rate,
                ];
            })->toArray()
        ];

        return self::createPurchase($input);
    }
}
