<?php

namespace App\Http\Controllers\Api;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Customer;
use App\Models\Product;
use App\Http\Middleware\AuthMiddleware;
use App\Services\TaxCalculationService;
use App\Services\DocumentNumberingService;
use App\Services\SalesConversionService;
use App\Services\AuditLogService;

class SalesOrderController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('sales', 'view');
        $companyId = $user->current_company_id;

        $orders = SalesOrder::with(['customer', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('sales', 'view');

        $order = SalesOrder::with(['customer', 'items.product'])->find($id);

        if (!$order) {
            return response_json(['status' => 'error', 'message' => 'Sales Order not found.'], 404);
        }

        return response_json([
            'status' => 'success',
            'data' => $order,
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('sales', 'create');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $fy = AuthMiddleware::getFinancialYear();

        $input = get_json_input();

        $customerId = intval($input['customer_id'] ?? 0);
        $customer = Customer::find($customerId);
        if (!$customer) {
            return response_json(['status' => 'error', 'message' => 'Invalid Customer selected.'], 422);
        }

        $items = $input['items'] ?? [];
        if (empty($items)) {
            return response_json(['status' => 'error', 'message' => 'Sales Order must contain at least 1 item.'], 422);
        }

        $isIgst = false; // default local
        $calculations = TaxCalculationService::calculateDocument($items, $isIgst, floatval($input['discount_rate'] ?? 0), floatval($input['discount_amount'] ?? 0));

        $soNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'SALES_ORDER');

        $order = SalesOrder::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $customer->id,
            'order_number' => $soNumber,
            'order_date' => $input['date'] ?? date('Y-m-d'),
            'expected_delivery' => $input['expected_delivery'] ?? date('Y-m-d', strtotime('+7 days')),
            'reference_no' => $input['reference_no'] ?? null,
            'sub_total' => $calculations['sub_total'],
            'discount_amount' => $calculations['discount_amount'],
            'total_tax' => $calculations['total_tax'],
            'grand_total' => $calculations['grand_total'],
            'status' => $input['status'] ?? 'Draft',
            'fulfillment_status' => $input['fulfillment_status'] ?? 'Draft',
            'payment_status' => 'UNPAID',
            'notes' => $input['notes'] ?? '',
            'source_quotation_id' => intval($input['source_quotation_id'] ?? null) ?: null,
        ]);

        foreach ($calculations['items'] as $it) {
            SalesOrderItem::create([
                'company_id' => $companyId,
                'sales_order_id' => $order->id,
                'product_id' => $it['product_id'],
                'item_name' => $it['name'] ?? $it['item_name'] ?? 'Item',
                'hsn_sac' => $it['hsn_sac'] ?? '84818030',
                'quantity' => $it['quantity'],
                'unit' => $it['unit'] ?? 'Pcs',
                'unit_price' => $it['unit_price'],
                'discount_rate' => $it['discount_rate'],
                'discount_amount' => $it['discount_amount'],
                'taxable_value' => $it['taxable_value'],
                'gst_rate' => $it['gst_rate'],
                'cgst_rate' => $it['cgst_rate'],
                'cgst_amount' => $it['cgst_amount'],
                'sgst_rate' => $it['sgst_rate'],
                'sgst_amount' => $it['sgst_amount'],
                'igst_rate' => $it['igst_rate'],
                'igst_amount' => $it['igst_amount'],
                'total_amount' => $it['total_amount'],
            ]);
        }

        AuditLogService::log($companyId, $user->name, 'SALES_ORDER_CREATE', 'SalesOrder', $order->id, "Created Sales Order #{$soNumber} for {$customer->name}");

        return response_json([
            'status' => 'success',
            'message' => "Sales Order #{$soNumber} generated successfully.",
            'data' => $order->load('items'),
        ], 201);
    }

    public function update($id)
    {
        $user = AuthMiddleware::authorize('sales', 'edit');
        $companyId = $user->current_company_id;

        $order = SalesOrder::find($id);
        if (!$order) {
            return response_json(['status' => 'error', 'message' => 'Sales Order not found.'], 404);
        }

        $input = get_json_input();
        
        $items = $input['items'] ?? [];
        if (!empty($items)) {
            $isIgst = false;
            $calculations = TaxCalculationService::calculateDocument($items, $isIgst, floatval($input['discount_rate'] ?? 0), floatval($input['discount_amount'] ?? 0));

            $order->update([
                'sub_total' => $calculations['sub_total'],
                'discount_amount' => $calculations['discount_amount'],
                'total_tax' => $calculations['total_tax'],
                'grand_total' => $calculations['grand_total'],
            ]);

            SalesOrderItem::where('sales_order_id', $order->id)->delete();
            foreach ($calculations['items'] as $it) {
                SalesOrderItem::create([
                    'company_id' => $companyId,
                    'sales_order_id' => $order->id,
                    'product_id' => $it['product_id'],
                    'item_name' => $it['name'] ?? $it['item_name'] ?? 'Item',
                    'hsn_sac' => $it['hsn_sac'] ?? '84818030',
                    'quantity' => $it['quantity'],
                    'unit' => $it['unit'] ?? 'Pcs',
                    'unit_price' => $it['unit_price'],
                    'discount_rate' => $it['discount_rate'],
                    'discount_amount' => $it['discount_amount'],
                    'taxable_value' => $it['taxable_value'],
                    'gst_rate' => $it['gst_rate'],
                    'cgst_rate' => $it['cgst_rate'],
                    'cgst_amount' => $it['cgst_amount'],
                    'sgst_rate' => $it['sgst_rate'],
                    'sgst_amount' => $it['sgst_amount'],
                    'igst_rate' => $it['igst_rate'],
                    'igst_amount' => $it['igst_amount'],
                    'total_amount' => $it['total_amount'],
                ]);
            }
        }

        $order->update([
            'expected_delivery' => $input['expected_delivery'] ?? $order->expected_delivery,
            'reference_no' => $input['reference_no'] ?? $order->reference_no,
            'status' => $input['status'] ?? $order->status,
            'fulfillment_status' => $input['fulfillment_status'] ?? $order->fulfillment_status,
            'notes' => $input['notes'] ?? $order->notes,
        ]);

        AuditLogService::log($companyId, $user->name, 'SALES_ORDER_EDIT', 'SalesOrder', $order->id, "Updated Sales Order #{$order->order_number}");

        return response_json([
            'status' => 'success',
            'message' => 'Sales Order updated successfully.',
            'data' => $order->load('items'),
        ]);
    }

    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('sales', 'edit');
        $companyId = $user->current_company_id;

        $order = SalesOrder::find($id);
        if (!$order) {
            return response_json(['status' => 'error', 'message' => 'Sales Order not found.'], 404);
        }

        $order->items()->delete();
        $order->delete();

        AuditLogService::log($companyId, $user->name, 'SALES_ORDER_DELETE', 'SalesOrder', $id, "Deleted Sales Order #{$order->order_number}");

        return response_json([
            'status' => 'success',
            'message' => 'Sales Order deleted successfully.',
        ]);
    }

    /**
     * Convert Sales Order to Delivery Challan or Invoice
     */
    public function convert($id)
    {
        $input = get_json_input();
        $target = $input['target_type'] ?? 'invoice';

        try {
            if ($target === 'delivery_challan') {
                $dc = SalesConversionService::convertSalesOrderToChallan($id);
                return response_json([
                    'status' => 'success',
                    'message' => "Sales Order converted to Delivery Challan #{$dc->challan_number} successfully.",
                    'data' => $dc->load('items'),
                ]);
            } else {
                $inv = SalesConversionService::convertSalesOrderToInvoice($id);
                return response_json([
                    'status' => 'success',
                    'message' => "Sales Order converted to Tax Invoice #{$inv->invoice_number} successfully.",
                    'data' => $inv->load('items'),
                ]);
            }
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
