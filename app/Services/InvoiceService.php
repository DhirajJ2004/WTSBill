<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Repositories\InvoiceRepository;
use App\Validators\InvoiceValidator;
use App\Services\InventoryService;
use App\Services\AccountingEventService;
use App\Services\AccountService;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

class InvoiceService
{
    /**
     * Atomically create and post a sales invoice with complete transactional integrity.
     */
    public static function createInvoice(
        array $input,
        int $companyId,
        ?int $branchId = null,
        string $userName = 'Admin',
        ?int $userId = null
    ): array {
        // 1. Validation
        $errors = InvoiceValidator::validate($input, $companyId);
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

        // Customer
        $customerId = (int)$input['customer_id'];
        $customer = Customer::withoutGlobalScopes()
            ->where('id', $customerId)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$customer) {
            return ['success' => false, 'message' => 'Selected customer is invalid or unauthorized.'];
        }

        // Warehouse
        $warehouseId = !empty($input['warehouse_id']) ? (int)$input['warehouse_id'] : null;
        if (!$warehouseId) {
            $primaryWh = Warehouse::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $warehouseId = $primaryWh ? $primaryWh->id : 1;
        }

        $invoiceDate = !empty($input['invoice_date']) ? substr(trim($input['invoice_date']), 0, 10) : date('Y-m-d');
        $dueDate = !empty($input['due_date']) ? substr(trim($input['due_date']), 0, 10) : $invoiceDate;

        // Determine GST Place of Supply and Inter/Intra State
        $companyState = trim($company->state ?: 'Maharashtra');
        $companyStateCode = trim($company->state_code ?: '27');
        $pos = trim($input['place_of_supply'] ?? ($customer->billing_state ?: ($customer->state ?: $companyState)));
        $posCode = trim($input['place_of_supply_code'] ?? ($customer->state_code ?: ''));

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
            $unitPrice = (float)($item['unit_price'] ?? ($item['rate'] ?? $product->sales_price));

            // Line discount
            $discRate = (float)($item['discount_rate'] ?? 0.0);
            $discAmount = (float)($item['discount_amount'] ?? 0.0);
            $baseLine = $qty * $unitPrice;

            if ($discRate > 0) {
                $discAmount = round($baseLine * ($discRate / 100.0), 2);
            }
            $taxableValue = round($baseLine - $discAmount, 2);

            // GST Rates
            $gstRate = isset($item['gst_rate']) ? (float)$item['gst_rate'] : (float)$product->tax_rate;
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

        // Invoice level discount
        $docDiscount = (float)($input['discount_amount'] ?? 0.0);
        $docDiscountRate = (float)($input['discount_rate'] ?? 0.0);
        if ($docDiscountRate > 0) {
            $docDiscount = round($grossSubtotal * ($docDiscountRate / 100.0), 2);
        }

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
            $companyId, $branchId, $warehouseId, $customer, $invoiceDate, $dueDate,
            $pos, $isIgst, $subTotal, $docDiscountRate, $docDiscount, $totalCgst,
            $totalSgst, $totalIgst, $totalTax, $roundOff, $grandTotal, $amountPaid,
            $amountDue, $status, $paymentStatus, $validatedItems, $input, $userName, $userId
        ) {
            // A. Acquire sequential invoice number under lock
            $invNumber = InvoiceRepository::generateNextInvoiceNumber($companyId, 'INV');

            // B. Create Invoice Header
            $invoice = Invoice::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'customer_id' => $customer->id,
                'invoice_number' => $invNumber,
                'reference_po_number' => $input['reference_po_number'] ?? null,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'place_of_supply' => $pos,
                'is_igst' => $isIgst,
                'sub_total' => $subTotal,
                'discount_rate' => $docDiscountRate,
                'discount_amount' => $docDiscount,
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
                'payment_mode' => $input['payment_mode'] ?? 'Cash',
                'notes' => $input['notes'] ?? '',
                'terms_and_conditions' => $input['terms_and_conditions'] ?? '',
                'customer_name' => $customer->name,
                'customer_gstin' => $customer->gstin,
                'billing_address' => $input['billing_address'] ?? $customer->billing_address,
                'shipping_address' => $input['shipping_address'] ?? ($input['billing_address'] ?? $customer->billing_address),
                'created_by' => $userId,
            ]);

            // C. Create Invoice Items
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

            // D. Deduct Stock Movements & Create Accounting Journal if POSTED
            if ($status !== 'DRAFT') {
                if (empty($input['skip_stock_deduction'])) {
                    foreach ($validatedItems as $vi) {
                        $prod = $vi['product'];
                        if (strtoupper($prod->product_type ?? '') !== 'SERVICES' && $prod->track_inventory) {
                            $stockRes = InventoryService::recordStockOut(
                                $companyId,
                                $warehouseId,
                                $prod->id,
                                $vi['quantity'],
                                (float)$prod->purchase_price,
                                'SALE',
                                [
                                    'reference_type' => 'INVOICE',
                                    'reference_id' => $invoice->id,
                                    'reference_number' => $invNumber,
                                    'movement_date' => $invoiceDate,
                                    'created_by' => $userName,
                                    'notes' => "Stock outward for Sales Invoice #{$invNumber}",
                                ]
                            );

                            if (!$stockRes['success']) {
                                throw new \Exception("Stock deduction failed: " . $stockRes['message']);
                            }
                        }
                    }
                }

                // Double-Entry Accounting Journal
                AccountService::ensureDefaultAccounts($companyId);
                AccountingEventService::recordSaleAccounting($invoice, $userName);

                // Customer Outstanding Balance Adjustment
                if ($amountDue > 0) {
                    $customer->update([
                        'current_balance' => round((float)$customer->current_balance + $amountDue, 2)
                    ]);
                }
            }

            AuditLogService::log(
                $companyId,
                $userName,
                'INVOICE_CREATE',
                'Invoice',
                $invoice->id,
                "Created Sales Invoice #{$invNumber} for Customer: {$customer->name}, Grand Total: ₹{$grandTotal}"
            );

            return [
                'success' => true,
                'invoice' => $invoice,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invNumber,
                'grand_total' => $grandTotal,
                'message' => "Invoice #{$invNumber} created successfully."
            ];
        });
    }

    /**
     * Cancel / Void an Invoice (Restoring stock and reversing accounting).
     */
    public static function voidInvoice(int $id, int $companyId, string $userName = 'Admin', string $reason = 'Cancelled'): array
    {
        return DB::transaction(function () use ($id, $companyId, $userName, $reason) {
            $invoice = Invoice::withoutGlobalScopes()
                ->where('id', $id)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$invoice) {
                return ['success' => false, 'message' => "Invoice #{$id} not found or unauthorized."];
            }

            if ($invoice->status === 'CANCELLED') {
                return ['success' => false, 'message' => "Invoice #{$invoice->invoice_number} is already cancelled."];
            }

            // Restore Stock for all line items
            $items = InvoiceItem::where('invoice_id', $id)->where('company_id', $companyId)->get();
            foreach ($items as $item) {
                $product = Product::withoutGlobalScopes()->where('id', $item->product_id)->where('company_id', $companyId)->first();
                if ($product && $product->track_inventory) {
                    InventoryService::recordStockIn(
                        $companyId,
                        $invoice->warehouse_id ?: 1,
                        $product->id,
                        $item->quantity,
                        (float)$product->purchase_price,
                        'SALES_RETURN',
                        [
                            'reference_type' => 'INVOICE_VOID',
                            'reference_id' => $invoice->id,
                            'reference_number' => $invoice->invoice_number,
                            'movement_date' => date('Y-m-d'),
                            'created_by' => $userName,
                            'notes' => "Restored stock upon voiding Invoice #{$invoice->invoice_number}. Reason: {$reason}",
                        ]
                    );
                }
            }

            // Adjust Customer Outstanding Balance
            if ($invoice->amount_due > 0) {
                $customer = Customer::withoutGlobalScopes()->where('id', $invoice->customer_id)->where('company_id', $companyId)->first();
                if ($customer) {
                    $newBalance = max(0.0, round((float)$customer->current_balance - (float)$invoice->amount_due, 2));
                    $customer->update(['current_balance' => $newBalance]);
                }
            }

            $invoice->update([
                'status' => 'CANCELLED',
                'amount_due' => 0.0,
                'notes' => trim($invoice->notes . " [VOIDED: {$reason}]"),
            ]);

            AuditLogService::log(
                $companyId,
                $userName,
                'INVOICE_VOID',
                'Invoice',
                $id,
                "Voided Invoice #{$invoice->invoice_number}. Reason: {$reason}"
            );

            return ['success' => true, 'message' => "Invoice #{$invoice->invoice_number} voided successfully."];
        });
    }

    /**
     * Delete DRAFT invoice.
     */
    public static function deleteInvoice(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $invoice = Invoice::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$invoice) {
            return ['success' => false, 'message' => "Invoice #{$id} not found or unauthorized."];
        }

        if ($invoice->status === 'POSTED' || $invoice->status === 'PAID') {
            return ['success' => false, 'message' => "Posted invoices cannot be deleted directly. Please void or cancel the invoice."];
        }

        $invoice->delete();

        AuditLogService::log(
            $companyId,
            $userName,
            'INVOICE_DELETE',
            'Invoice',
            $id,
            "Deleted draft Invoice #{$invoice->invoice_number}"
        );

        return ['success' => true, 'message' => "Invoice #{$invoice->invoice_number} deleted successfully."];
    }
}
