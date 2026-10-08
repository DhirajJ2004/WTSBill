<?php

namespace App\Http\Controllers\Api;

use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use App\Http\Middleware\AuthMiddleware;
use App\Services\TaxCalculationService;
use App\Services\AuditLogService;

class RecurringInvoiceController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('sales', 'view');
        $companyId = $user->current_company_id;

        $templates = RecurringInvoice::with(['customer', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $templates,
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('sales', 'view');

        $template = RecurringInvoice::with(['customer', 'items.product'])->find($id);

        if (!$template) {
            return response_json(['status' => 'error', 'message' => 'Recurring template not found.'], 404);
        }

        return response_json([
            'status' => 'success',
            'data' => $template,
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('sales', 'create');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();

        $input = get_json_input();

        $customerId = intval($input['customer_id'] ?? 0);
        $customer = Customer::find($customerId);
        if (!$customer) {
            return response_json(['status' => 'error', 'message' => 'Invalid Customer selected.'], 422);
        }

        $items = $input['items'] ?? [];
        if (empty($items)) {
            return response_json(['status' => 'error', 'message' => 'Recurring template must contain at least 1 item.'], 422);
        }

        $isIgst = false;
        $calculations = TaxCalculationService::calculateDocument($items, $isIgst);

        $template = RecurringInvoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $customer->id,
            'template_name' => $input['template_name'] ?? 'Recurring Profile',
            'frequency' => $input['frequency'] ?? 'MONTHLY',
            'start_date' => $input['start_date'] ?? date('Y-m-d'),
            'end_date' => $input['end_date'] ?? null,
            'next_invoice_date' => $input['next_invoice_date'] ?? date('Y-m-d', strtotime('+1 month')),
            'status' => $input['status'] ?? 'ACTIVE',
            'payment_terms' => $input['payment_terms'] ?? 'Net 30',
            'sub_total' => $calculations['sub_total'],
            'total_tax' => $calculations['total_tax'],
            'grand_total' => $calculations['grand_total'],
            'notes' => $input['notes'] ?? '',
        ]);

        foreach ($calculations['items'] as $it) {
            RecurringInvoiceItem::create([
                'company_id' => $companyId,
                'recurring_invoice_id' => $template->id,
                'product_id' => $it['product_id'],
                'item_name' => $it['item_name'] ?? $it['name'] ?? 'Item',
                'hsn_sac' => $it['hsn_sac'] ?? '84818030',
                'quantity' => $it['quantity'],
                'unit' => $it['unit'] ?? 'Pcs',
                'unit_price' => $it['unit_price'],
                'discount_rate' => $it['discount_rate'] ?? 0.00,
                'discount_amount' => $it['discount_amount'] ?? 0.00,
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

        AuditLogService::log($companyId, $user->name, 'RECURRING_INVOICE_CREATE', 'RecurringInvoice', $template->id, "Created Recurring Template: {$template->template_name}");

        return response_json([
            'status' => 'success',
            'message' => "Recurring template '{$template->template_name}' generated successfully.",
            'data' => $template->load('items'),
        ], 201);
    }

    public function update($id)
    {
        $user = AuthMiddleware::authorize('sales', 'edit');
        $companyId = $user->current_company_id;

        $template = RecurringInvoice::find($id);
        if (!$template) {
            return response_json(['status' => 'error', 'message' => 'Recurring template not found.'], 404);
        }

        $input = get_json_input();
        
        $items = $input['items'] ?? [];
        if (!empty($items)) {
            $isIgst = false;
            $calculations = TaxCalculationService::calculateDocument($items, $isIgst);

            $template->update([
                'sub_total' => $calculations['sub_total'],
                'total_tax' => $calculations['total_tax'],
                'grand_total' => $calculations['grand_total'],
            ]);

            RecurringInvoiceItem::where('recurring_invoice_id', $template->id)->delete();
            foreach ($calculations['items'] as $it) {
                RecurringInvoiceItem::create([
                    'company_id' => $companyId,
                    'recurring_invoice_id' => $template->id,
                    'product_id' => $it['product_id'],
                    'item_name' => $it['item_name'] ?? $it['name'] ?? 'Item',
                    'hsn_sac' => $it['hsn_sac'] ?? '84818030',
                    'quantity' => $it['quantity'],
                    'unit' => $it['unit'] ?? 'Pcs',
                    'unit_price' => $it['unit_price'],
                    'discount_rate' => $it['discount_rate'] ?? 0.00,
                    'discount_amount' => $it['discount_amount'] ?? 0.00,
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

        $template->update([
            'template_name' => $input['template_name'] ?? $template->template_name,
            'frequency' => $input['frequency'] ?? $template->frequency,
            'status' => $input['status'] ?? $template->status,
            'notes' => $input['notes'] ?? $template->notes,
        ]);

        AuditLogService::log($companyId, $user->name, 'RECURRING_INVOICE_EDIT', 'RecurringInvoice', $template->id, "Updated Recurring Profile '{$template->template_name}'");

        return response_json([
            'status' => 'success',
            'message' => 'Recurring profile template updated successfully.',
            'data' => $template->load('items'),
        ]);
    }

    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('sales', 'edit');
        $companyId = $user->current_company_id;

        $template = RecurringInvoice::find($id);
        if (!$template) {
            return response_json(['status' => 'error', 'message' => 'Recurring profile template not found.'], 404);
        }

        $template->items()->delete();
        $template->delete();

        AuditLogService::log($companyId, $user->name, 'RECURRING_INVOICE_DELETE', 'RecurringInvoice', $id, "Deleted Recurring Profile '{$template->template_name}'");

        return response_json([
            'status' => 'success',
            'message' => 'Recurring template profile deleted successfully.',
        ]);
    }
}
