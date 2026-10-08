<?php

namespace App\Http\Controllers\Api;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Customer;
use App\Models\Product;
use App\Http\Middleware\AuthMiddleware;
use App\Services\TaxCalculationService;
use App\Services\DocumentNumberingService;
use App\Services\SalesConversionService;
use App\Services\AuditLogService;

class QuotationController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('sales', 'view');
        $companyId = $user->current_company_id;

        $quotations = Quotation::with(['customer', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $quotations,
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('sales', 'view');

        $quotation = Quotation::with(['customer', 'items.product'])->find($id);

        if (!$quotation) {
            return response_json(['status' => 'error', 'message' => 'Quotation not found.'], 404);
        }

        return response_json([
            'status' => 'success',
            'data' => $quotation,
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
            return response_json(['status' => 'error', 'message' => 'Quotation must contain at least 1 item.'], 422);
        }

        $placeOfSupply = $input['place_of_supply'] ?? $customer->state_code ?? '27';
        // Check if customer state code matches place of supply code
        $isIgst = (substr($placeOfSupply, 0, 2) !== '27'); // simple hardcoded state check

        $calculations = TaxCalculationService::calculateDocument($items, $isIgst, floatval($input['discount_rate'] ?? 0), floatval($input['discount_amount'] ?? 0));

        $qNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'QUOTATION');

        $quotation = Quotation::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $customer->id,
            'quotation_number' => $qNumber,
            'quotation_date' => $input['date'] ?? date('Y-m-d'),
            'valid_until' => $input['valid_until'] ?? date('Y-m-d', strtotime('+30 days')),
            'reference_no' => $input['reference_no'] ?? null,
            'billing_address' => $input['billing_address'] ?? $customer->address_line1,
            'shipping_address' => $input['shipping_address'] ?? $customer->address_line1,
            'sub_total' => $calculations['sub_total'],
            'discount_amount' => $calculations['discount_amount'],
            'taxable_value' => $calculations['taxable_value'],
            'cgst_amount' => $calculations['cgst_amount'],
            'sgst_amount' => $calculations['sgst_amount'],
            'igst_amount' => $calculations['igst_amount'],
            'total_tax' => $calculations['total_tax'],
            'round_off' => $calculations['round_off'],
            'grand_total' => $calculations['grand_total'],
            'status' => $input['status'] ?? 'Draft',
            'notes' => $input['notes'] ?? '',
            'terms' => $input['terms'] ?? '',
        ]);

        foreach ($calculations['items'] as $it) {
            QuotationItem::create([
                'company_id' => $companyId,
                'quotation_id' => $quotation->id,
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

        AuditLogService::log($companyId, $user->name, 'QUOTATION_CREATE', 'Quotation', $quotation->id, "Created Quotation #{$qNumber} for {$customer->name}");

        return response_json([
            'status' => 'success',
            'message' => "Quotation #{$qNumber} created successfully.",
            'data' => $quotation->load('items'),
        ], 201);
    }

    public function update($id)
    {
        $user = AuthMiddleware::authorize('sales', 'edit');
        $companyId = $user->current_company_id;

        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response_json(['status' => 'error', 'message' => 'Quotation not found.'], 404);
        }

        if ($quotation->status === 'Converted') {
            return response_json(['status' => 'error', 'message' => 'Cannot modify a converted quotation.'], 422);
        }

        $input = get_json_input();
        
        // Handle self parenting recursion loops checks if circular, but Quotation has no circular parent.
        // We can just calculate and update the fields.
        $items = $input['items'] ?? [];
        if (!empty($items)) {
            $placeOfSupply = $input['place_of_supply'] ?? $quotation->place_of_supply ?? '27';
            $isIgst = (substr($placeOfSupply, 0, 2) !== '27');
            $calculations = TaxCalculationService::calculateDocument($items, $isIgst, floatval($input['discount_rate'] ?? 0), floatval($input['discount_amount'] ?? 0));

            $quotation->update([
                'sub_total' => $calculations['sub_total'],
                'discount_amount' => $calculations['discount_amount'],
                'taxable_value' => $calculations['taxable_value'],
                'cgst_amount' => $calculations['cgst_amount'],
                'sgst_amount' => $calculations['sgst_amount'],
                'igst_amount' => $calculations['igst_amount'],
                'total_tax' => $calculations['total_tax'],
                'round_off' => $calculations['round_off'],
                'grand_total' => $calculations['grand_total'],
            ]);

            QuotationItem::where('quotation_id', $quotation->id)->delete();
            foreach ($calculations['items'] as $it) {
                QuotationItem::create([
                    'company_id' => $companyId,
                    'quotation_id' => $quotation->id,
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

        $quotation->update([
            'valid_until' => $input['valid_until'] ?? $quotation->valid_until,
            'reference_no' => $input['reference_no'] ?? $quotation->reference_no,
            'status' => $input['status'] ?? $quotation->status,
            'notes' => $input['notes'] ?? $quotation->notes,
            'terms' => $input['terms'] ?? $quotation->terms,
        ]);

        AuditLogService::log($companyId, $user->name, 'QUOTATION_EDIT', 'Quotation', $quotation->id, "Updated Quotation #{$quotation->quotation_number}");

        return response_json([
            'status' => 'success',
            'message' => 'Quotation updated successfully.',
            'data' => $quotation->load('items'),
        ]);
    }

    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('sales', 'edit');
        $companyId = $user->current_company_id;

        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response_json(['status' => 'error', 'message' => 'Quotation not found.'], 404);
        }

        if ($quotation->status === 'Converted') {
            return response_json(['status' => 'error', 'message' => 'Cannot delete a converted quotation estimate.'], 422);
        }

        $quotation->items()->delete();
        $quotation->delete();

        AuditLogService::log($companyId, $user->name, 'QUOTATION_DELETE', 'Quotation', $id, "Deleted Quotation #{$quotation->quotation_number}");

        return response_json([
            'status' => 'success',
            'message' => 'Quotation estimate deleted successfully.',
        ]);
    }

    /**
     * Convert Quotation to Invoice or Sales Order
     */
    public function convert($id)
    {
        $input = get_json_input();
        $target = $input['target_type'] ?? 'invoice';

        try {
            if ($target === 'sales_order') {
                $so = SalesConversionService::convertQuotationToSalesOrder($id);
                return response_json([
                    'status' => 'success',
                    'message' => "Quotation estimate converted to Sales Order #{$so->order_number} successfully.",
                    'data' => $so->load('items'),
                ]);
            } else {
                $inv = SalesConversionService::convertQuotationToInvoice($id);
                return response_json([
                    'status' => 'success',
                    'message' => "Quotation estimate converted to Tax Invoice #{$inv->invoice_number} successfully.",
                    'data' => $inv->load('items'),
                ]);
            }
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
