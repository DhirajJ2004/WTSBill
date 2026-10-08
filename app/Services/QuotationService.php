<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Company;
use App\Models\Branch;
use App\Repositories\QuotationRepository;
use App\Validators\QuotationValidator;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class QuotationService
{
    /**
     * Create a new sales quotation / estimate.
     */
    public static function createQuotation(
        array $input,
        int $companyId,
        ?int $branchId = null,
        string $userName = 'Admin'
    ): array {
        $errors = QuotationValidator::validate($input, $companyId);
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

        $quotationDate = !empty($input['quotation_date']) ? substr(trim($input['quotation_date']), 0, 10) : date('Y-m-d');
        $validUntil = !empty($input['valid_until']) ? substr(trim($input['valid_until']), 0, 10) : date('Y-m-d', strtotime('+30 days'));

        // GST determination
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

        // Calculate line items
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

        // Document level discount
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

        $status = $input['status'] ?? 'DRAFT';
        if (!in_array(strtoupper($status), ['DRAFT', 'SENT', 'ACCEPTED', 'REJECTED', 'EXPIRED'], true)) {
            $status = 'DRAFT';
        }

        return DB::transaction(function () use (
            $companyId, $branchId, $customer, $quotationDate, $validUntil,
            $pos, $subTotal, $docDiscount, $totalCgst, $totalSgst, $totalIgst,
            $totalTax, $roundOff, $grandTotal, $status, $validatedItems, $input, $userName
        ) {
            $qNumber = QuotationRepository::generateNextQuotationNumber($companyId, 'QTN');

            $quotation = Quotation::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'quotation_number' => $qNumber,
                'quotation_date' => $quotationDate,
                'valid_until' => $validUntil,
                'reference_no' => $input['reference_no'] ?? null,
                'billing_address' => $input['billing_address'] ?? $customer->billing_address,
                'shipping_address' => $input['shipping_address'] ?? ($input['billing_address'] ?? $customer->billing_address),
                'sub_total' => $subTotal,
                'discount_amount' => $docDiscount,
                'taxable_value' => $subTotal,
                'cgst_amount' => $totalCgst,
                'sgst_amount' => $totalSgst,
                'igst_amount' => $totalIgst,
                'total_tax' => $totalTax,
                'round_off' => $roundOff,
                'grand_total' => $grandTotal,
                'status' => $status,
                'notes' => $input['notes'] ?? '',
                'terms' => $input['terms'] ?? ($input['terms_and_conditions'] ?? ''),
                'converted_to_invoice' => false,
                'converted_invoice_id' => null,
            ]);

            foreach ($validatedItems as $vi) {
                QuotationItem::create([
                    'company_id' => $companyId,
                    'quotation_id' => $quotation->id,
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

            AuditLogService::log(
                $companyId,
                $userName,
                'QUOTATION_CREATE',
                'Quotation',
                $quotation->id,
                "Created Quotation #{$qNumber} for Customer: {$customer->name}, Total: ₹{$grandTotal}"
            );

            return [
                'success' => true,
                'quotation_id' => $quotation->id,
                'quotation_number' => $qNumber,
                'grand_total' => $grandTotal,
                'quotation' => $quotation->load(['customer', 'items']),
                'message' => "Quotation #{$qNumber} created successfully."
            ];
        });
    }

    /**
     * Update an existing quotation.
     */
    public static function updateQuotation(int $id, array $input, int $companyId, string $userName = 'Admin'): array
    {
        $quotation = Quotation::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$quotation) {
            return ['success' => false, 'message' => "Quotation #{$id} not found or unauthorized."];
        }

        if ($quotation->converted_to_invoice || strtoupper($quotation->status) === 'CONVERTED') {
            return ['success' => false, 'message' => "Quotation #{$quotation->quotation_number} has already been converted and cannot be edited."];
        }

        $errors = QuotationValidator::validate($input, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        return DB::transaction(function () use ($quotation, $input, $companyId, $userName) {
            // Delete old items and re-create updated items
            QuotationItem::where('quotation_id', $quotation->id)->where('company_id', $companyId)->delete();

            $company = Company::withoutGlobalScopes()->where('id', $companyId)->first();
            $customer = Customer::withoutGlobalScopes()->where('id', (int)$input['customer_id'])->where('company_id', $companyId)->first();

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

            $grossSubtotal = 0.0;
            $totalCgst = 0.0;
            $totalSgst = 0.0;
            $totalIgst = 0.0;

            foreach ($input['items'] as $item) {
                $product = Product::withoutGlobalScopes()->where('id', (int)$item['product_id'])->where('company_id', $companyId)->first();
                $qty = (float)$item['quantity'];
                $unitPrice = (float)($item['unit_price'] ?? ($item['rate'] ?? $product->sales_price));
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

                QuotationItem::create([
                    'company_id' => $companyId,
                    'quotation_id' => $quotation->id,
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
                ]);

                $grossSubtotal += $taxableValue;
                $totalCgst += $cgstAmount;
                $totalSgst += $sgstAmount;
                $totalIgst += $igstAmount;
            }

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

            $quotation->update([
                'customer_id' => $customer->id,
                'quotation_date' => $input['quotation_date'] ?? $quotation->quotation_date,
                'valid_until' => $input['valid_until'] ?? $quotation->valid_until,
                'reference_no' => $input['reference_no'] ?? $quotation->reference_no,
                'billing_address' => $input['billing_address'] ?? $quotation->billing_address,
                'shipping_address' => $input['shipping_address'] ?? $quotation->shipping_address,
                'sub_total' => $subTotal,
                'discount_amount' => $docDiscount,
                'taxable_value' => $subTotal,
                'cgst_amount' => $totalCgst,
                'sgst_amount' => $totalSgst,
                'igst_amount' => $totalIgst,
                'total_tax' => $totalTax,
                'round_off' => $roundOff,
                'grand_total' => $grandTotal,
                'status' => $input['status'] ?? $quotation->status,
                'notes' => $input['notes'] ?? $quotation->notes,
                'terms' => $input['terms'] ?? ($input['terms_and_conditions'] ?? $quotation->terms),
            ]);

            AuditLogService::log(
                $companyId,
                $userName,
                'QUOTATION_UPDATE',
                'Quotation',
                $quotation->id,
                "Updated Quotation #{$quotation->quotation_number}"
            );

            return [
                'success' => true,
                'quotation_id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'grand_total' => $grandTotal,
                'quotation' => $quotation->load(['customer', 'items']),
                'message' => "Quotation #{$quotation->quotation_number} updated successfully."
            ];
        });
    }

    /**
     * Delete an unconverted quotation.
     */
    public static function deleteQuotation(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $quotation = Quotation::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$quotation) {
            return ['success' => false, 'message' => "Quotation #{$id} not found or unauthorized."];
        }

        if ($quotation->converted_to_invoice || strtoupper($quotation->status) === 'CONVERTED') {
            return ['success' => false, 'message' => "Converted quotations cannot be deleted."];
        }

        DB::transaction(function () use ($quotation, $companyId, $id, $userName) {
            QuotationItem::where('quotation_id', $id)->where('company_id', $companyId)->delete();
            $quotation->delete();

            AuditLogService::log(
                $companyId,
                $userName,
                'QUOTATION_DELETE',
                'Quotation',
                $id,
                "Deleted Quotation #{$quotation->quotation_number}"
            );
        });

        return ['success' => true, 'message' => "Quotation #{$quotation->quotation_number} deleted successfully."];
    }
}
