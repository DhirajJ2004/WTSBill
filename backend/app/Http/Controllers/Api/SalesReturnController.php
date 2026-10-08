<?php

namespace App\Http\Controllers\Api;

use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use App\Http\Middleware\AuthMiddleware;
use App\Services\TaxCalculationService;
use App\Services\DocumentNumberingService;
use App\Services\AuditLogService;

class SalesReturnController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('sales', 'view');
        $companyId = $user->current_company_id;

        $returns = SalesReturn::with(['customer', 'invoice', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $returns,
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('sales', 'view');

        $ret = SalesReturn::with(['customer', 'invoice', 'items.product'])->find($id);

        if (!$ret) {
            return response_json(['status' => 'error', 'message' => 'Sales Return record not found.'], 404);
        }

        return response_json([
            'status' => 'success',
            'data' => $ret,
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('sales', 'create');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $fy = AuthMiddleware::getFinancialYear();

        $input = get_json_input();

        $invoiceId = intval($input['invoice_id'] ?? 0);
        $invoice = Invoice::with('items')->find($invoiceId);
        if (!$invoice) {
            return response_json(['status' => 'error', 'message' => 'Original Invoice is required for a Sales Return.'], 422);
        }

        $items = $input['items'] ?? [];
        if (empty($items)) {
            return response_json(['status' => 'error', 'message' => 'Sales Return must contain at least 1 item.'], 422);
        }

        // Validate quantities against original sold invoice items
        foreach ($items as $it) {
            $productId = intval($it['product_id'] ?? 0);
            $returnQty = floatval($it['quantity'] ?? 0);

            // Find item in original invoice
            $origInvItem = $invoice->items->firstWhere('product_id', $productId);
            if (!$origInvItem) {
                return response_json([
                    'status' => 'error', 
                    'message' => "Product ID {$productId} was not sold in the original invoice #{$invoice->invoice_number}."
                ], 422);
            }

            // Find previously returned quantities of this product for this invoice
            $prevReturnedQty = SalesReturnItem::whereHas('salesReturn', function ($q) use ($invoiceId) {
                $q->where('invoice_id', $invoiceId);
            })->where('product_id', $productId)->sum('quantity');

            $maxEligible = $origInvItem->quantity - $prevReturnedQty;

            if ($returnQty > $maxEligible) {
                return response_json([
                    'status' => 'error',
                    'message' => "Return quantity ({$returnQty}) exceeds the maximum eligible return quantity ({$maxEligible}) for product '{$origInvItem->item_name}'."
                ], 422);
            }
        }

        $isIgst = $invoice->is_igst;
        $calculations = TaxCalculationService::calculateDocument($items, $isIgst);

        $srNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'SALES_RETURN');

        $ret = SalesReturn::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'return_number' => $srNumber,
            'return_date' => $input['return_date'] ?? date('Y-m-d'),
            'reason' => $input['reason'] ?? 'Other',
            'stock_impact' => filter_var($input['stock_impact'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'sub_total' => $calculations['sub_total'],
            'total_tax' => $calculations['total_tax'],
            'grand_total' => $calculations['grand_total'],
            'refund_amount' => $calculations['grand_total'],
            'tax_reversal_amount' => $calculations['total_tax'],
            'status' => 'COMPLETED',
            'notes' => $input['notes'] ?? '',
        ]);

        foreach ($calculations['items'] as $it) {
            SalesReturnItem::create([
                'company_id' => $companyId,
                'sales_return_id' => $ret->id,
                'product_id' => $it['product_id'],
                'item_name' => $it['item_name'] ?? $it['name'] ?? 'Item',
                'hsn_sac' => $it['hsn_sac'] ?? '84818030',
                'quantity' => $it['quantity'],
                'unit' => $it['unit'] ?? 'Pcs',
                'unit_price' => $it['unit_price'],
                'taxable_value' => $it['taxable_value'],
                'gst_rate' => $it['gst_rate'],
                'cgst_amount' => $it['cgst_amount'],
                'sgst_amount' => $it['sgst_amount'],
                'igst_amount' => $it['igst_amount'],
                'total_amount' => $it['total_amount'],
            ]);

            if ($ret->stock_impact) {
                $whId = intval($input['warehouse_id'] ?? 1) ?: 1;
                \App\Services\InventoryService::recordStockMovement(
                    companyId: $companyId,
                    warehouseId: $whId,
                    productId: $it['product_id'],
                    movementType: 'SALES_RETURN',
                    quantity: floatval($it['quantity']),
                    direction: 'IN',
                    unitCost: floatval($it['unit_price']),
                    branchId: $branchId,
                    refType: 'SALES_RETURN',
                    refId: $ret->id,
                    refNumber: $srNumber,
                    movementDate: $ret->return_date,
                    createdBy: $user ? $user->name : 'Admin',
                    notes: "Sales Return #{$srNumber} for Invoice #{$invoice->invoice_number}"
                );
            }
        }

        AuditLogService::log($companyId, $user->name, 'SALES_RETURN_CREATE', 'SalesReturn', $ret->id, "Log Return #{$srNumber} for original Invoice #{$invoice->invoice_number}");

        return response_json([
            'status' => 'success',
            'message' => "Sales Return #{$srNumber} logged successfully.",
            'data' => $ret->load('items'),
        ], 201);
    }
}
