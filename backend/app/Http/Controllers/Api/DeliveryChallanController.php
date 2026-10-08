<?php

namespace App\Http\Controllers\Api;

use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\Customer;
use App\Models\Product;
use App\Http\Middleware\AuthMiddleware;
use App\Services\DocumentNumberingService;
use App\Services\SalesConversionService;
use App\Services\AuditLogService;

class DeliveryChallanController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('sales', 'view');
        $companyId = $user->current_company_id;

        $challans = DeliveryChallan::with(['customer', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $challans,
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('sales', 'view');

        $dc = DeliveryChallan::with(['customer', 'items.product'])->find($id);

        if (!$dc) {
            return response_json(['status' => 'error', 'message' => 'Delivery Challan not found.'], 404);
        }

        return response_json([
            'status' => 'success',
            'data' => $dc,
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
            return response_json(['status' => 'error', 'message' => 'Challan must contain at least 1 item.'], 422);
        }

        $dcNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'DELIVERY_CHALLAN');

        $dc = DeliveryChallan::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $customer->id,
            'challan_number' => $dcNumber,
            'challan_date' => $input['date'] ?? date('Y-m-d'),
            'sales_order_id' => intval($input['sales_order_id'] ?? null) ?: null,
            'reference_so' => $input['reference_so'] ?? null,
            'delivery_address' => $input['delivery_address'] ?? $customer->address_line1,
            'transport_details' => $input['transport_details'] ?? '',
            'status' => $input['status'] ?? 'DRAFT',
            'notes' => $input['notes'] ?? '',
        ]);

        foreach ($items as $it) {
            $prod = Product::find(intval($it['product_id'] ?? 0));
            DeliveryChallanItem::create([
                'company_id' => $companyId,
                'delivery_challan_id' => $dc->id,
                'product_id' => intval($it['product_id'] ?? 0),
                'item_name' => $it['item_name'] ?? ($prod ? $prod->name : 'Item'),
                'quantity' => floatval($it['quantity'] ?? 1),
                'unit' => $it['unit'] ?? ($prod ? $prod->unit : 'Pcs'),
                'unit_price' => floatval($it['unit_price'] ?? ($prod ? $prod->sales_price : 0)),
                'total_amount' => floatval($it['total_amount'] ?? 0),
                'batch_no' => $it['batch_no'] ?? null,
                'serial_no' => $it['serial_no'] ?? null,
            ]);
        }

        AuditLogService::log($companyId, $user->name, 'DELIVERY_CHALLAN_CREATE', 'DeliveryChallan', $dc->id, "Created Delivery Challan #{$dcNumber} for {$customer->name}");

        return response_json([
            'status' => 'success',
            'message' => "Delivery Challan #{$dcNumber} generated successfully.",
            'data' => $dc->load('items'),
        ], 201);
    }

    public function update($id)
    {
        $user = AuthMiddleware::authorize('sales', 'edit');
        $companyId = $user->current_company_id;

        $dc = DeliveryChallan::find($id);
        if (!$dc) {
            return response_json(['status' => 'error', 'message' => 'Delivery Challan not found.'], 404);
        }

        $input = get_json_input();
        
        $items = $input['items'] ?? [];
        if (!empty($items)) {
            DeliveryChallanItem::where('delivery_challan_id', $dc->id)->delete();
            foreach ($items as $it) {
                $prod = Product::find(intval($it['product_id'] ?? 0));
                DeliveryChallanItem::create([
                    'company_id' => $companyId,
                    'delivery_challan_id' => $dc->id,
                    'product_id' => intval($it['product_id'] ?? 0),
                    'item_name' => $it['item_name'] ?? ($prod ? $prod->name : 'Item'),
                    'quantity' => floatval($it['quantity'] ?? 1),
                    'unit' => $it['unit'] ?? ($prod ? $prod->unit : 'Pcs'),
                    'unit_price' => floatval($it['unit_price'] ?? ($prod ? $prod->sales_price : 0)),
                    'total_amount' => floatval($it['total_amount'] ?? 0),
                    'batch_no' => $it['batch_no'] ?? null,
                    'serial_no' => $it['serial_no'] ?? null,
                ]);
            }
        }

        $dc->update([
            'challan_date' => $input['date'] ?? $dc->challan_date,
            'delivery_address' => $input['delivery_address'] ?? $dc->delivery_address,
            'transport_details' => $input['transport_details'] ?? $dc->transport_details,
            'status' => $input['status'] ?? $dc->status,
            'notes' => $input['notes'] ?? $dc->notes,
        ]);

        AuditLogService::log($companyId, $user->name, 'DELIVERY_CHALLAN_EDIT', 'DeliveryChallan', $dc->id, "Updated Delivery Challan #{$dc->challan_number}");

        return response_json([
            'status' => 'success',
            'message' => 'Delivery Challan updated successfully.',
            'data' => $dc->load('items'),
        ]);
    }

    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('sales', 'edit');
        $companyId = $user->current_company_id;

        $dc = DeliveryChallan::find($id);
        if (!$dc) {
            return response_json(['status' => 'error', 'message' => 'Delivery Challan not found.'], 404);
        }

        $dc->items()->delete();
        $dc->delete();

        AuditLogService::log($companyId, $user->name, 'DELIVERY_CHALLAN_DELETE', 'DeliveryChallan', $id, "Deleted Delivery Challan #{$dc->challan_number}");

        return response_json([
            'status' => 'success',
            'message' => 'Delivery Challan deleted successfully.',
        ]);
    }

    /**
     * Convert Delivery Challan to Invoice
     */
    public function convert($id)
    {
        try {
            $inv = SalesConversionService::convertChallanToInvoice($id);
            return response_json([
                'status' => 'success',
                'message' => "Delivery Challan converted to Tax Invoice #{$inv->invoice_number} successfully.",
                'data' => $inv->load('items'),
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
