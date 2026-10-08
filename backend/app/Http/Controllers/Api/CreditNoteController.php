<?php

namespace App\Http\Controllers\Api;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Http\Middleware\AuthMiddleware;
use App\Services\TaxCalculationService;
use App\Services\DocumentNumberingService;
use App\Services\AuditLogService;

class CreditNoteController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('sales', 'view');
        $companyId = $user->current_company_id;

        $notes = CreditNote::with(['customer', 'invoice', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $notes,
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('sales', 'view');

        $note = CreditNote::with(['customer', 'invoice', 'items.product'])->find($id);

        if (!$note) {
            return response_json(['status' => 'error', 'message' => 'Credit Note not found.'], 404);
        }

        return response_json([
            'status' => 'success',
            'data' => $note,
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

        $invoiceId = intval($input['invoice_id'] ?? 0);
        $invoice = Invoice::find($invoiceId);

        $items = $input['items'] ?? [];
        if (empty($items)) {
            return response_json(['status' => 'error', 'message' => 'Credit Note must contain at least 1 item.'], 422);
        }

        $isIgst = false;
        if ($invoice) {
            $isIgst = $invoice->is_igst;
        }

        $calculations = TaxCalculationService::calculateDocument($items, $isIgst);

        $cnNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'CREDIT_NOTE');

        $note = CreditNote::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice ? $invoice->id : null,
            'credit_note_number' => $cnNumber,
            'credit_note_date' => $input['date'] ?? date('Y-m-d'),
            'original_invoice_number' => $invoice ? $invoice->invoice_number : ($input['original_invoice_number'] ?? null),
            'sub_total' => $calculations['sub_total'],
            'total_tax' => $calculations['total_tax'],
            'cgst_reversal' => $calculations['cgst_amount'],
            'sgst_reversal' => $calculations['sgst_amount'],
            'igst_reversal' => $calculations['igst_amount'],
            'amount' => $calculations['grand_total'],
            'reason' => $input['reason'] ?? 'Other',
            'status' => $input['status'] ?? 'APPROVED',
            'notes' => $input['notes'] ?? '',
        ]);

        foreach ($calculations['items'] as $it) {
            CreditNoteItem::create([
                'company_id' => $companyId,
                'credit_note_id' => $note->id,
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
                'amount' => $it['total_amount'],
            ]);
        }

        AuditLogService::log($companyId, $user->name, 'CREDIT_NOTE_CREATE', 'CreditNote', $note->id, "Created Credit Note #{$cnNumber} for {$customer->name}");

        return response_json([
            'status' => 'success',
            'message' => "Credit Note #{$cnNumber} generated successfully.",
            'data' => $note->load('items'),
        ], 201);
    }

    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('sales', 'edit');
        $companyId = $user->current_company_id;

        $note = CreditNote::find($id);
        if (!$note) {
            return response_json(['status' => 'error', 'message' => 'Credit Note not found.'], 404);
        }

        $note->items()->delete();
        $note->delete();

        AuditLogService::log($companyId, $user->name, 'CREDIT_NOTE_DELETE', 'CreditNote', $id, "Deleted Credit Note #{$note->credit_note_number}");

        return response_json([
            'status' => 'success',
            'message' => 'Credit Note deleted successfully.',
        ]);
    }
}
