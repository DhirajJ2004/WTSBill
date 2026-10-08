<?php

namespace App\Http\Controllers\Api;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Company;
use App\Http\Middleware\AuthMiddleware;
use App\Services\TaxCalculationService;
use App\Services\DocumentNumberingService;
use App\Services\AuditLogService;

class InvoiceController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('sales', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $invoices = Invoice::where('company_id', $companyId)
            ->with(['customer', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $invoices,
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('sales', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $raw = Invoice::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Invoice not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $invoice = Invoice::with(['customer', 'items.product'])->find($id);

        return response_json([
            'status' => 'success',
            'data' => $invoice,
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('sales', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $branchId = AuthMiddleware::getBranchId();

        $input = get_json_input();
        $result = \App\Services\InvoiceService::createInvoice($input, $companyId, $branchId, $user);

        if (!$result['success']) {
            return response_json(['status' => 'error', 'message' => $result['message']], $result['code'] ?? 422);
        }

        return response_json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data'],
        ], 201);
    }

    /**
     * Update invoice details (e.g. notes, terms, due date, PO reference)
     */
    public function update($id)
    {
        $user = AuthMiddleware::authorize('sales', 'edit');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $invoice = Invoice::find(intval($id));
        if (!$invoice) {
            return response_json(['status' => 'error', 'message' => 'Invoice not found.'], 404);
        }

        if ((int)$invoice->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: Cannot access invoice from another company.'], 403);
        }

        if ($invoice->status === 'CANCELLED') {
            return response_json(['status' => 'error', 'message' => 'Cancelled invoice cannot be updated.'], 400);
        }

        $oldValues = [
            'notes' => $invoice->notes,
            'terms_and_conditions' => $invoice->terms_and_conditions,
            'reference_po_number' => $invoice->reference_po_number,
            'due_date' => $invoice->due_date,
        ];

        $updates = [];
        if (isset($input['notes'])) $updates['notes'] = trim($input['notes']);
        if (isset($input['terms_and_conditions'])) $updates['terms_and_conditions'] = trim($input['terms_and_conditions']);
        if (isset($input['reference_po_number'])) $updates['reference_po_number'] = trim($input['reference_po_number']);
        if (isset($input['due_date'])) $updates['due_date'] = trim($input['due_date']);

        $invoice->update($updates);

        $newValues = [
            'notes' => $invoice->notes,
            'terms_and_conditions' => $invoice->terms_and_conditions,
            'reference_po_number' => $invoice->reference_po_number,
            'due_date' => $invoice->due_date,
        ];

        // Audit Log: UPDATE_INVOICE
        AuditLogService::record([
            'action' => AuditLogService::UPDATE_INVOICE,
            'entity' => 'Invoice',
            'entity_id' => $invoice->id,
            'company_id' => $companyId,
            'branch_id' => $invoice->branch_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => "Updated Invoice #{$invoice->invoice_number}",
        ]);

        return response_json([
            'status' => 'success',
            'message' => "Invoice #{$invoice->invoice_number} updated successfully.",
            'data' => $invoice->fresh(['customer', 'items']),
        ], 200);
    }

    /**
     * Post a draft invoice
     */
    public function post($id)
    {
        $user = AuthMiddleware::authorize('sales', 'create');
        $companyId = AuthMiddleware::getTenantId();

        $result = \App\Services\InvoiceService::postInvoice(intval($id), $companyId, $user);
        if (!$result['success']) {
            return response_json(['status' => 'error', 'message' => $result['message']], $result['code'] ?? 422);
        }

        return response_json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    /**
     * Cancel an invoice
     */
    public function cancel($id)
    {
        $user = AuthMiddleware::authorize('sales', 'cancel');
        $companyId = AuthMiddleware::getTenantId();

        $input = get_json_input();
        $reason = $input['reason'] ?? null;

        $result = \App\Services\InvoiceService::cancelInvoice(intval($id), $reason, $companyId, $user);
        if (!$result['success']) {
            return response_json(['status' => 'error', 'message' => $result['message']], $result['code'] ?? 422);
        }

        return response_json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    /**
     * Duplicate an invoice
     */
    public function duplicate($id)
    {
        $user = AuthMiddleware::authorize('sales', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $branchId = AuthMiddleware::getBranchId();
        $fy = AuthMiddleware::getFinancialYear();

        $raw = Invoice::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Original Invoice not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $original = Invoice::with('items')->find($id);
        if (!$original) {
            return response_json(['status' => 'error', 'message' => 'Original Invoice not found.'], 404);
        }

        $invNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'INVOICE');

        $duplicate = Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $original->customer_id,
            'invoice_number' => $invNumber,
            'reference_po_number' => $original->reference_po_number,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'place_of_supply' => $original->place_of_supply,
            'is_igst' => $original->is_igst,
            'sub_total' => $original->sub_total,
            'discount_rate' => $original->discount_rate,
            'discount_amount' => $original->discount_amount,
            'cgst_amount' => $original->cgst_amount,
            'sgst_amount' => $original->sgst_amount,
            'igst_amount' => $original->igst_amount,
            'total_tax' => $original->total_tax,
            'round_off' => $original->round_off,
            'grand_total' => $original->grand_total,
            'amount_paid' => 0.00,
            'amount_due' => $original->grand_total,
            'status' => 'DRAFT', // Duplicated invoice starts as DRAFT
            'payment_status' => 'UNPAID',
            'payment_mode' => $original->payment_mode,
            'notes' => $original->notes,
            'terms_and_conditions' => $original->terms_and_conditions,
            'customer_name' => $original->customer_name,
            'customer_gstin' => $original->customer_gstin,
            'billing_address' => $original->billing_address,
            'shipping_address' => $original->shipping_address,
            'created_by' => $user->id,
        ]);

        foreach ($original->items as $item) {
            InvoiceItem::create([
                'company_id' => $companyId,
                'invoice_id' => $duplicate->id,
                'product_id' => $item->product_id,
                'item_name' => $item->item_name,
                'hsn_sac' => $item->hsn_sac,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount_rate' => $item->discount_rate,
                'discount_amount' => $item->discount_amount,
                'taxable_value' => $item->taxable_value,
                'gst_rate' => $item->gst_rate,
                'cgst_rate' => $item->cgst_rate,
                'cgst_amount' => $item->cgst_amount,
                'sgst_rate' => $item->sgst_rate,
                'sgst_amount' => $item->sgst_amount,
                'igst_rate' => $item->igst_rate,
                'igst_amount' => $item->igst_amount,
                'total_amount' => $item->total_amount,
            ]);
        }

        AuditLogService::log(
            $companyId,
            $user->name,
            'INVOICE_DUPLICATE',
            'Invoice',
            $duplicate->id,
            "Duplicated Invoice #{$original->invoice_number} into new Invoice Draft #{$invNumber}"
        );

        return response_json([
            'status' => 'success',
            'message' => "Invoice #{$original->invoice_number} duplicated successfully as Draft #{$invNumber}.",
            'data' => $duplicate->load(['customer', 'items']),
        ]);
    }
}
