<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Company;
use App\Models\Branch;
use App\Repositories\SalesOrderRepository;
use App\Validators\SalesOrderValidator;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class SalesOrderService
{
    /**
     * Create a new Sales Order.
     */
    public static function createSalesOrder(
        array $input,
        int $companyId,
        ?int $branchId = null,
        string $userName = 'Admin'
    ): array {
        $errors = SalesOrderValidator::validate($input, $companyId);
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

        $orderDate = !empty($input['order_date']) ? substr(trim($input['order_date']), 0, 10) : date('Y-m-d');
        $expectedDelivery = !empty($input['expected_delivery']) ? substr(trim($input['expected_delivery']), 0, 10) : date('Y-m-d', strtotime('+7 days'));

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

        $docDiscount = (float)($input['discount_amount'] ?? 0.0);
        $docDiscountRate = (float)($input['discount_rate'] ?? 0.0);
        if ($docDiscountRate > 0) {
            $docDiscount = round($grossSubtotal * ($docDiscountRate / 100.0), 2);
        }

        $subTotal = round($grossSubtotal - $docDiscount, 2);
        $totalTax = round($totalCgst + $totalSgst + $totalIgst, 2);
        $grandTotal = round($subTotal + $totalTax);

        $status = strtoupper($input['status'] ?? 'CONFIRMED');
        if (!in_array($status, ['DRAFT', 'CONFIRMED', 'PROCESSING', 'FULFILLED', 'CANCELLED'], true)) {
            $status = 'CONFIRMED';
        }

        $fulfillmentStatus = $input['fulfillment_status'] ?? ($status === 'FULFILLED' ? 'Fulfilled' : 'Unfulfilled');

        return DB::transaction(function () use (
            $companyId, $branchId, $customer, $orderDate, $expectedDelivery,
            $subTotal, $docDiscount, $totalTax, $grandTotal, $status,
            $fulfillmentStatus, $validatedItems, $input, $userName
        ) {
            $soNumber = SalesOrderRepository::generateNextSalesOrderNumber($companyId, 'SO');

            $salesOrder = SalesOrder::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'order_number' => $soNumber,
                'order_date' => $orderDate,
                'expected_delivery' => $expectedDelivery,
                'reference_no' => $input['reference_no'] ?? null,
                'sub_total' => $subTotal,
                'discount_amount' => $docDiscount,
                'total_tax' => $totalTax,
                'grand_total' => $grandTotal,
                'status' => $status,
                'fulfillment_status' => $fulfillmentStatus,
                'payment_status' => $input['payment_status'] ?? 'UNPAID',
                'notes' => $input['notes'] ?? '',
                'source_quotation_id' => !empty($input['source_quotation_id']) ? (int)$input['source_quotation_id'] : null,
            ]);

            foreach ($validatedItems as $vi) {
                SalesOrderItem::create([
                    'company_id' => $companyId,
                    'sales_order_id' => $salesOrder->id,
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
                'SALES_ORDER_CREATE',
                'SalesOrder',
                $salesOrder->id,
                "Created Sales Order #{$soNumber} for Customer: {$customer->name}, Total: ₹{$grandTotal}"
            );

            return [
                'success' => true,
                'sales_order_id' => $salesOrder->id,
                'order_number' => $soNumber,
                'grand_total' => $grandTotal,
                'sales_order' => $salesOrder->load(['customer', 'items']),
                'message' => "Sales Order #{$soNumber} created successfully."
            ];
        });
    }

    /**
     * Update an existing Sales Order.
     */
    public static function updateSalesOrder(int $id, array $input, int $companyId, string $userName = 'Admin'): array
    {
        $so = SalesOrder::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$so) {
            return ['success' => false, 'message' => "Sales Order #{$id} not found or unauthorized."];
        }

        if (in_array(strtoupper($so->status), ['FULFILLED', 'CANCELLED'], true) || in_array(strtoupper($so->fulfillment_status ?? ''), ['FULFILLED'], true)) {
            return ['success' => false, 'message' => "Sales Order #{$so->order_number} is already fulfilled or cancelled and cannot be edited."];
        }

        $errors = SalesOrderValidator::validate($input, $companyId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        return DB::transaction(function () use ($so, $input, $companyId, $userName) {
            SalesOrderItem::where('sales_order_id', $so->id)->where('company_id', $companyId)->delete();

            $customer = Customer::withoutGlobalScopes()->where('id', (int)$input['customer_id'])->where('company_id', $companyId)->first();
            $company = Company::withoutGlobalScopes()->where('id', $companyId)->first();

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

                SalesOrderItem::create([
                    'company_id' => $companyId,
                    'sales_order_id' => $so->id,
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
            $grandTotal = round($subTotal + $totalTax);

            $so->update([
                'customer_id' => $customer->id,
                'order_date' => $input['order_date'] ?? $so->order_date,
                'expected_delivery' => $input['expected_delivery'] ?? $so->expected_delivery,
                'reference_no' => $input['reference_no'] ?? $so->reference_no,
                'sub_total' => $subTotal,
                'discount_amount' => $docDiscount,
                'total_tax' => $totalTax,
                'grand_total' => $grandTotal,
                'status' => $input['status'] ?? $so->status,
                'notes' => $input['notes'] ?? $so->notes,
            ]);

            AuditLogService::log(
                $companyId,
                $userName,
                'SALES_ORDER_UPDATE',
                'SalesOrder',
                $so->id,
                "Updated Sales Order #{$so->order_number}"
            );

            return [
                'success' => true,
                'sales_order_id' => $so->id,
                'order_number' => $so->order_number,
                'grand_total' => $grandTotal,
                'sales_order' => $so->load(['customer', 'items']),
                'message' => "Sales Order #{$so->order_number} updated successfully."
            ];
        });
    }

    /**
     * Delete an unfulfilled Sales Order.
     */
    public static function deleteSalesOrder(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $so = SalesOrder::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$so) {
            return ['success' => false, 'message' => "Sales Order #{$id} not found or unauthorized."];
        }

        if (in_array(strtoupper($so->status), ['FULFILLED'], true) || in_array(strtoupper($so->fulfillment_status ?? ''), ['FULFILLED'], true)) {
            return ['success' => false, 'message' => "Fulfilled sales orders cannot be deleted."];
        }

        DB::transaction(function () use ($so, $companyId, $id, $userName) {
            SalesOrderItem::where('sales_order_id', $id)->where('company_id', $companyId)->delete();
            $so->delete();

            AuditLogService::log(
                $companyId,
                $userName,
                'SALES_ORDER_DELETE',
                'SalesOrder',
                $id,
                "Deleted Sales Order #{$so->order_number}"
            );
        });

        return ['success' => true, 'message' => "Sales Order #{$so->order_number} deleted successfully."];
    }
}
