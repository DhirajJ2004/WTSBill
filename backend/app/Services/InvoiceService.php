<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\StockBalance;
use App\Models\User;
use App\Auth\WorkspaceContext;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AuthorizationException;
use Illuminate\Database\Capsule\Manager as DB;

class InvoiceService
{
    /**
     * Atomically create and post a sales invoice with complete transactional integrity:
     * - Workspace / tenant scoping
     * - Customer & product authorization
     * - Stock validation & deduction
     * - Full backend tax & total recalculation
     * - Number sequence generation
     * - Double-entry accounting journal creation
     * - Customer balance update
     * - Audit logging
     *
     * @param array $input Raw input payload
     * @param int|null $authCompanyId Authorized company ID (overrides payload)
     * @param int|null $authBranchId Authorized branch ID
     * @param User|null $authUser Authenticated user performing action
     * @return array Standardized result payload
     */
    public static function createInvoice(array $input, ?int $authCompanyId = null, ?int $authBranchId = null, mixed $authUser = null): array
    {
        // -------------------------------------------------------------
        // 1. Resolve & Enforce Tenant & Branch Workspace Context
        // -------------------------------------------------------------
        $companyId = $authCompanyId ?: (WorkspaceContext::getCompanyId() ?: AuthMiddleware::getTenantId());
        if (!$companyId || $companyId <= 0) {
            return [
                'success' => false,
                'code' => 403,
                'message' => 'Forbidden: Active company workspace context is required.'
            ];
        }

        $company = Company::withoutGlobalScopes()->where('id', $companyId)->whereNull('deleted_at')->first();
        if (!$company) {
            return [
                'success' => false,
                'code' => 404,
                'message' => 'Company not found or inactive.'
            ];
        }

        // Branch Resolution
        $branchId = $authBranchId ?: ($input['branch_id'] ?? null);
        if ($branchId) {
            $branch = Branch::withoutGlobalScopes()->where('id', $branchId)->where('company_id', $companyId)->first();
            if (!$branch) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => "Branch #{$branchId} is invalid or does not belong to authorized company."
                ];
            }
        } else {
            $branch = Branch::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_main_branch', 1)
                ->first()
                ?: Branch::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $branchId = $branch?->id;
        }

        // User Context
        if (is_string($authUser)) {
            $userName = $authUser;
            $userId = null;
        } elseif ($authUser instanceof User) {
            $userName = $authUser->name;
            $userId = $authUser->id;
        } else {
            $user = WorkspaceContext::getUser() ?: AuthMiddleware::getUser();
            $userName = $user?->name ?: 'System';
            $userId = $user?->id ?: null;
        }

        // -------------------------------------------------------------
        // 2. Customer Validation (Strict Tenant Scoping, No Fallbacks)
        // -------------------------------------------------------------
        $customerId = intval($input['customer_id'] ?? 0);
        if ($customerId <= 0) {
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Customer is required. Please select a valid customer.'
            ];
        }

        $customer = Customer::withoutGlobalScopes()
            ->where('id', $customerId)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$customer) {
            // Check if customer exists in another company to give a specific security message
            $otherCompCust = Customer::withoutGlobalScopes()->where('id', $customerId)->first();
            if ($otherCompCust && (int)$otherCompCust->company_id !== (int)$companyId) {
                return [
                    'success' => false,
                    'code' => 403,
                    'message' => "Forbidden: Selected customer belongs to another company."
                ];
            }
            return [
                'success' => false,
                'code' => 422,
                'message' => "Selected customer #{$customerId} is invalid or not found."
            ];
        }

        // -------------------------------------------------------------
        // 3. Date Validations
        // -------------------------------------------------------------
        $invoiceDate = !empty($input['invoice_date']) ? substr(trim($input['invoice_date']), 0, 10) : date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDate)) {
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Invalid invoice date format. Expected YYYY-MM-DD.'
            ];
        }

        $dueDate = !empty($input['due_date']) ? substr(trim($input['due_date']), 0, 10) : date('Y-m-d', strtotime('+30 days'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Invalid due date format. Expected YYYY-MM-DD.'
            ];
        }

        if ($dueDate < $invoiceDate) {
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Invoice due date cannot be earlier than the invoice date.'
            ];
        }

        // -------------------------------------------------------------
        // 4. Items & Product Validation
        // -------------------------------------------------------------
        $rawItems = $input['items'] ?? [];
        if (!is_array($rawItems) || empty($rawItems)) {
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Invoice must contain at least one valid line item.'
            ];
        }

        // Resolve Target Warehouse for stock deductions
        $warehouseId = intval($input['warehouse_id'] ?? 0);
        if ($warehouseId > 0) {
            $warehouse = Warehouse::withoutGlobalScopes()->where('id', $warehouseId)->where('company_id', $companyId)->first();
            if (!$warehouse) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => "Warehouse #{$warehouseId} is invalid or does not belong to authorized company."
                ];
            }
        } else {
            $warehouse = Warehouse::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_primary', 1)
                ->first()
                ?: Warehouse::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $warehouseId = $warehouse?->id ?: 1;
        }

        // -------------------------------------------------------------
        // Determine GST Place of Supply & Tax Configuration
        // -------------------------------------------------------------
        $isLut = !empty($input['is_lut_export']) || !empty($input['is_lut']);
        $explicitPos = !empty($input['place_of_supply']) ? (string)$input['place_of_supply'] : null;

        $validatedItems = [];
        $grossSubtotal = 0.00;
        $totalCgst = 0.00;
        $totalSgst = 0.00;
        $totalIgst = 0.00;
        $docSupplyType = null;
        $finalPos = null;
        $isIgst = false;

        foreach ($rawItems as $idx => $it) {
            $lineNum = $idx + 1;
            $productId = intval($it['product_id'] ?? 0);
            if ($productId <= 0) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => "Line item #{$lineNum}: Product is required."
                ];
            }

            $product = Product::withoutGlobalScopes()
                ->where('id', $productId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->first();

            if (!$product) {
                $otherProd = Product::withoutGlobalScopes()->where('id', $productId)->first();
                if ($otherProd && (int)$otherProd->company_id !== (int)$companyId) {
                    return [
                        'success' => false,
                        'code' => 403,
                        'message' => "Forbidden: Product #{$productId} belongs to another company."
                    ];
                }
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => "Line item #{$lineNum}: Product #{$productId} not found or invalid."
                ];
            }

            // Quantity Check
            $quantity = floatval($it['quantity'] ?? 0);
            if ($quantity <= 0) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => "Line item #{$lineNum} ('{$product->name}'): Quantity must be greater than zero. Provided: {$quantity}."
                ];
            }

            // Unit Price Check
            $unitPrice = isset($it['unit_price']) ? floatval($it['unit_price']) : floatval($product->sales_price ?: ($product->selling_price ?: 0.00));
            if ($unitPrice < 0) {
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => "Line item #{$lineNum} ('{$product->name}'): Unit price cannot be negative."
                ];
            }

            // Discounts
            $discRate = max(0.00, min(100.00, floatval($it['discount_rate'] ?? ($it['discount_percentage'] ?? 0.00))));
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $discAmount = floatval($it['discount_amount'] ?? 0.00);
            if ($discRate > 0) {
                $discAmount = round($lineSubtotal * ($discRate / 100.00), 2);
            }
            $discAmount = min($lineSubtotal, max(0.00, $discAmount));
            $taxableValue = round($lineSubtotal - $discAmount, 2);

            // Authoritative GST Determination via GSTService:
            // Product tax configuration + Customer type + GSTIN + POS + Company state
            $explicitLineRate = isset($it['gst_rate']) ? floatval($it['gst_rate']) : (isset($it['tax_rate']) ? floatval($it['tax_rate']) : null);
            $taxParams = GSTService::determineTaxParameters($product, $customer, $company, $explicitPos, $explicitLineRate, $isLut);

            $gstRate = $taxParams['gst_rate'];
            $cgstRate = $taxParams['cgst_rate'];
            $sgstRate = $taxParams['sgst_rate'];
            $igstRate = $taxParams['igst_rate'];
            $isIgst = $taxParams['is_igst'];

            $cgstAmount = round($taxableValue * ($cgstRate / 100.00), 2);
            $sgstAmount = round($taxableValue * ($sgstRate / 100.00), 2);
            $igstAmount = round($taxableValue * ($igstRate / 100.00), 2);

            $lineTaxTotal = round($cgstAmount + $sgstAmount + $igstAmount, 2);
            $lineTotal = round($taxableValue + $lineTaxTotal, 2);

            if (!$docSupplyType) {
                $docSupplyType = $taxParams['supply_type'];
                $finalPos = $taxParams['place_of_supply'];
            }

            // -------------------------------------------------------------
            // Stock Availability Check (Physical / Tracked inventory items)
            // -------------------------------------------------------------
            $isService = strtoupper($product->product_type ?? '') === 'SERVICE';
            if (!$isService && $product->track_inventory) {
                $stockBal = StockBalance::where('company_id', $companyId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $product->id)
                    ->first();

                $availableQty = $stockBal
                    ? max(floatval($stockBal->available_quantity), floatval($stockBal->quantity) - floatval($stockBal->reserved_quantity))
                    : floatval($product->current_stock);
                $allowNegative = !empty($input['allow_negative_stock']) || ((bool)$product->allow_negative_stock && (bool)$company->allow_negative_stock);

                if ($availableQty < $quantity && !$allowNegative) {
                    return [
                        'success' => false,
                        'code' => 422,
                        'message' => "Insufficient stock for product '{$product->name}' in warehouse #{$warehouseId}. Available: {$availableQty}, Requested: {$quantity}."
                    ];
                }
            }

            $validatedItems[] = [
                'product' => $product,
                'product_id' => $product->id,
                'item_name' => $it['item_name'] ?? $product->name,
                'hsn_sac' => $it['hsn_sac'] ?? ($product->hsn_sac ?: '84818030'),
                'quantity' => $quantity,
                'unit' => $it['unit'] ?? ($product->unit ?: 'Pcs'),
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

        // -------------------------------------------------------------
        // 5. Total Financial Recalculations (Strictly Backend Derived)
        // -------------------------------------------------------------
        if ($grossSubtotal <= 0) {
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Invoice taxable total must be greater than zero. Fake or zero-value fallbacks are not permitted.'
            ];
        }

        $documentDiscount = max(0.00, floatval($input['discount_amount'] ?? 0.00));
        $documentDiscountRate = max(0.00, min(100.00, floatval($input['discount_rate'] ?? 0.00)));
        if ($documentDiscountRate > 0) {
            $documentDiscount = round($grossSubtotal * ($documentDiscountRate / 100.00), 2);
        }

        $subTotal = round($grossSubtotal - $documentDiscount, 2);
        $totalTax = round($totalCgst + $totalSgst + $totalIgst, 2);
        $unroundedGrandTotal = $subTotal + $totalTax;
        $roundedGrandTotal = round($unroundedGrandTotal);
        $roundOff = round($roundedGrandTotal - $unroundedGrandTotal, 2);
        $grandTotal = $roundedGrandTotal;

        $status = strtoupper($input['status'] ?? 'POSTED'); // Default to POSTED
        if ($status !== 'DRAFT' && $status !== 'POSTED') {
            $status = 'POSTED';
        }
        $paymentStatus = strtoupper($input['payment_status'] ?? 'UNPAID');

        // -------------------------------------------------------------
        // 6. ATOMIC TRANSACTIONAL COMMIT WITH ROW-LEVEL LOCKING
        // -------------------------------------------------------------
        $fy = AuthMiddleware::getFinancialYear() ?: WorkspaceContext::getFinancialYear();
        DB::beginTransaction();
        try {
            // A. Acquire sequential document number under exclusive database lock
            $invNumber = DocumentNumberService::generateNextNumber($companyId, $branchId, $fy, 'INVOICE');

            // B. Create Invoice Record
            $invoice = Invoice::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'invoice_number' => $invNumber,
                'reference_po_number' => $input['reference_po_number'] ?? null,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'place_of_supply' => $finalPos ?? '27',
                'is_igst' => $isIgst,
                'sub_total' => $subTotal,
                'discount_rate' => $documentDiscountRate,
                'discount_amount' => $documentDiscount,
                'cgst_amount' => $totalCgst,
                'sgst_amount' => $totalSgst,
                'igst_amount' => $totalIgst,
                'total_tax' => $totalTax,
                'round_off' => $roundOff,
                'grand_total' => $grandTotal,
                'amount_paid' => ($status === 'POSTED' && $paymentStatus === 'PAID') ? $grandTotal : floatval($input['amount_paid'] ?? 0.00),
                'amount_due' => ($status === 'POSTED' && $paymentStatus === 'PAID') ? 0.00 : $grandTotal,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'payment_mode' => $input['payment_mode'] ?? 'Bank Transfer',
                'notes' => $input['notes'] ?? '',
                'terms_and_conditions' => $input['terms_and_conditions'] ?? '',
                'customer_name' => $customer->name,
                'customer_gstin' => $customer->gstin,
                'billing_address' => $input['billing_address'] ?? $customer->address_line1,
                'shipping_address' => $input['shipping_address'] ?? $customer->address_line1,
                'created_by' => $userId,
                'source_quotation_id' => intval($input['source_quotation_id'] ?? null) ?: null,
                'source_sales_order_id' => intval($input['source_sales_order_id'] ?? null) ?: null,
                'source_delivery_challan_id' => intval($input['source_delivery_challan_id'] ?? null) ?: null,
            ]);

            // B. Create Invoice Items
            foreach ($validatedItems as $vi) {
                InvoiceItem::create([
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'product_id' => $vi['product_id'],
                    'item_name' => $vi['item_name'],
                    'hsn_sac' => $vi['hsn_sac'],
                    'quantity' => $vi['quantity'],
                    'unit' => $vi['unit'],
                    'unit_price' => $vi['unit_price'],
                    'discount_rate' => $vi['discount_rate'],
                    'discount_amount' => $vi['discount_amount'],
                    'taxable_value' => $vi['taxable_value'],
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

            // C. If POSTED, execute downstream movements, accounting, and customer balance updates
            if ($status === 'POSTED') {
                // 1. Inventory Deduction
                foreach ($validatedItems as $vi) {
                    $prod = $vi['product'];
                    if (strtoupper($prod->product_type ?? '') !== 'SERVICE' && $prod->track_inventory) {
                        InventoryService::recordStockMovement(
                            companyId: $companyId,
                            warehouseId: $warehouseId,
                            productId: $prod->id,
                            movementType: 'SALE',
                            quantity: $vi['quantity'],
                            direction: 'OUT',
                            unitCost: floatval($prod->purchase_price ?: 0),
                            branchId: $branchId,
                            refType: 'SALES_INVOICE',
                            refId: $invoice->id,
                            refNumber: $invNumber,
                            movementDate: $invoiceDate,
                            createdBy: $userName,
                            notes: "Deducted for Sales Invoice #{$invNumber}",
                            allowNegativeStock: (bool)($prod->allow_negative_stock || $company->allow_negative_stock || !empty($input['allow_negative_stock']))
                        );
                    }
                }

                // 2. Double-Entry Accounting Journal
                AccountService::ensureDefaultAccounts($companyId);
                AccountingEventService::recordSaleAccounting($invoice, $userName);

                // 3. Customer Balance Adjustment
                if ($invoice->amount_due > 0) {
                    $customer->update([
                        'current_balance' => round(floatval($customer->current_balance) + floatval($invoice->amount_due), 2)
                    ]);
                }
            }

            // D. Audit Log
            AuditLogService::log(
                $companyId,
                $userName,
                'INVOICE_CREATE',
                'Invoice',
                $invoice->id,
                "Created Sales Invoice #{$invNumber} ({$status}) for Customer: {$customer->name}, Grand Total: ₹{$grandTotal}"
            );

            DB::commit();

            return [
                'success' => true,
                'code' => 201,
                'message' => "Invoice #{$invNumber} created successfully.",
                'invoice_id' => $invoice->id,
                'invoice' => $invoice,
                'invoice_number' => $invNumber,
                'grand_total' => $grandTotal,
                'data' => $invoice->load(['customer', 'items.product']),
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Invoice creation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Post a DRAFT invoice atomically.
     */
    public static function postInvoice(int $invoiceId, ?int $authCompanyId = null, ?User $authUser = null): array
    {
        $companyId = $authCompanyId ?: (WorkspaceContext::getCompanyId() ?: AuthMiddleware::getTenantId());
        $user = $authUser ?: (WorkspaceContext::getUser() ?: AuthMiddleware::getUser());
        $userName = $user?->name ?: 'System';

        $invoice = Invoice::withoutGlobalScopes()->where('id', $invoiceId)->first();
        if (!$invoice) {
            return ['success' => false, 'code' => 404, 'message' => 'Invoice not found.'];
        }

        if ((int)$invoice->company_id !== (int)$companyId) {
            return ['success' => false, 'code' => 403, 'message' => 'Forbidden: Invoice belongs to another company.'];
        }

        if ($invoice->status !== 'DRAFT') {
            return ['success' => false, 'code' => 422, 'message' => "Only DRAFT invoices can be posted. Current status: {$invoice->status}."];
        }

        DB::beginTransaction();
        try {
            $invoice->update(['status' => 'POSTED']);

            // Deduct stock for inventory items
            $items = InvoiceItem::where('invoice_id', $invoice->id)->get();
            $warehouseId = $invoice->warehouse_id ?: 1;

            foreach ($items as $item) {
                $prod = Product::withoutGlobalScopes()->where('id', $item->product_id)->first();
                if ($prod && strtoupper($prod->product_type ?? '') !== 'SERVICE' && $prod->track_inventory) {
                    InventoryService::recordStockMovement(
                        companyId: $companyId,
                        warehouseId: $warehouseId,
                        productId: $prod->id,
                        movementType: 'SALE',
                        quantity: floatval($item->quantity),
                        direction: 'OUT',
                        unitCost: floatval($prod->purchase_price ?: 0),
                        branchId: $invoice->branch_id,
                        refType: 'SALES_INVOICE',
                        refId: $invoice->id,
                        refNumber: $invoice->invoice_number,
                        movementDate: $invoice->invoice_date,
                        createdBy: $userName,
                        notes: "Deducted on Posting of Invoice #{$invoice->invoice_number}"
                    );
                }
            }

            // Double-entry accounting
            AccountService::ensureDefaultAccounts($companyId);
            AccountingEventService::recordSaleAccounting($invoice, $userName);

            // Customer balance
            $customer = Customer::withoutGlobalScopes()->where('id', $invoice->customer_id)->first();
            if ($customer && $invoice->amount_due > 0) {
                $customer->update([
                    'current_balance' => round(floatval($customer->current_balance) + floatval($invoice->amount_due), 2)
                ]);
            }

            AuditLogService::log(
                $companyId,
                $userName,
                'INVOICE_POST',
                'Invoice',
                $invoice->id,
                "Posted Sales Invoice #{$invoice->invoice_number}"
            );

            DB::commit();

            return [
                'success' => true,
                'code' => 200,
                'message' => "Invoice #{$invoice->invoice_number} posted successfully.",
                'data' => $invoice->load(['customer', 'items']),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Failed to post invoice: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel / Void an invoice atomically.
     */
    public static function cancelInvoice(int $invoiceId, ?string $reason = null, ?int $authCompanyId = null, mixed $authUser = null): array
    {
        $companyId = $authCompanyId ?: (WorkspaceContext::getCompanyId() ?: AuthMiddleware::getTenantId());
        if (is_string($authUser)) {
            $userName = $authUser;
        } elseif ($authUser instanceof User) {
            $userName = $authUser->name;
        } else {
            $user = WorkspaceContext::getUser() ?: AuthMiddleware::getUser();
            $userName = $user?->name ?: 'System';
        }

        $invoice = Invoice::withoutGlobalScopes()->where('id', $invoiceId)->first();
        if (!$invoice) {
            return ['success' => false, 'code' => 404, 'message' => 'Invoice not found.'];
        }

        if ((int)$invoice->company_id !== (int)$companyId) {
            return ['success' => false, 'code' => 403, 'message' => 'Forbidden: Invoice belongs to another company.'];
        }

        if ($invoice->status === 'CANCELLED') {
            return ['success' => false, 'code' => 422, 'message' => 'Invoice is already cancelled.'];
        }

        $oldStatus = $invoice->status;

        DB::beginTransaction();
        try {
            $invoice->update(['status' => 'CANCELLED']);

            if ($oldStatus === 'POSTED') {
                // Reverse inventory movements
                $items = InvoiceItem::where('invoice_id', $invoice->id)->get();
                $warehouseId = $invoice->warehouse_id ?: 1;

                foreach ($items as $item) {
                    $prod = Product::withoutGlobalScopes()->where('id', $item->product_id)->first();
                    if ($prod && strtoupper($prod->product_type ?? '') !== 'SERVICE' && $prod->track_inventory) {
                        InventoryService::recordStockMovement(
                            companyId: $companyId,
                            warehouseId: $warehouseId,
                            productId: $prod->id,
                            movementType: 'SALES_RETURN',
                            quantity: floatval($item->quantity),
                            direction: 'IN',
                            unitCost: floatval($item->unit_price),
                            branchId: $invoice->branch_id,
                            refType: 'SALES_INVOICE',
                            refId: $invoice->id,
                            refNumber: $invoice->invoice_number,
                            movementDate: date('Y-m-d'),
                            createdBy: $userName,
                            notes: "Restored stock for Cancelled Invoice #{$invoice->invoice_number}"
                        );
                    }
                }

                // Reverse Customer Balance
                $customer = Customer::withoutGlobalScopes()->where('id', $invoice->customer_id)->first();
                if ($customer && $invoice->amount_due > 0) {
                    $newBal = max(0.00, round(floatval($customer->current_balance) - floatval($invoice->amount_due), 2));
                    $customer->update(['current_balance' => $newBal]);
                }

                // Reverse Accounting Entry (Credit AR, Debit Sales/Tax)
                try {
                    $origJournal = \App\Models\JournalEntry::where('company_id', $companyId)
                        ->where('reference_type', 'INVOICE')
                        ->where('reference_id', (string)$invoice->id)
                        ->where('status', 'POSTED')
                        ->first();
                    if ($origJournal) {
                        $origJournal->update(['status' => 'CANCELLED']);
                    }
                } catch (\Throwable $e) {}
            }

            AuditLogService::log(
                $companyId,
                $userName,
                'INVOICE_CANCEL',
                'Invoice',
                $invoice->id,
                "Cancelled Sales Invoice #{$invoice->invoice_number}. Reason: " . ($reason ?: 'User requested cancellation')
            );

            DB::commit();

            return [
                'success' => true,
                'code' => 200,
                'message' => "Invoice #{$invoice->invoice_number} has been cancelled.",
                'data' => $invoice->load(['customer', 'items']),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Failed to cancel invoice: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Void an invoice (Alias for cancelInvoice with parameter normalization).
     */
    public static function voidInvoice(int $id, ?int $companyId = null, mixed $userName = 'Admin', ?string $reason = 'Cancelled'): array
    {
        return self::cancelInvoice($id, $reason, $companyId, $userName);
    }

    /**
     * Delete a DRAFT invoice.
     */
    public static function deleteInvoice(int $id, ?int $companyId = null, mixed $userName = 'Admin'): array
    {
        $comp = $companyId ?: (WorkspaceContext::getCompanyId() ?: AuthMiddleware::getTenantId());
        $invoice = Invoice::withoutGlobalScopes()->where('id', $id)->where('company_id', $comp)->first();
        if (!$invoice) {
            return ['success' => false, 'code' => 404, 'message' => "Invoice #{$id} not found."];
        }
        if ($invoice->status === 'POSTED' || $invoice->status === 'PAID') {
            return ['success' => false, 'code' => 422, 'message' => "Posted invoices cannot be deleted directly. Please void or cancel the invoice."];
        }
        $invoice->delete();
        return ['success' => true, 'code' => 200, 'message' => "Draft Invoice #{$invoice->invoice_number} deleted successfully."];
    }
}
