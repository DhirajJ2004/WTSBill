<?php

namespace App\Services;

use App\Models\DebitNote;
use App\Models\DebitNoteItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

class DebitNoteService
{
    public static function createDebitNote(array $input): DebitNote
    {
        return DB::transaction(function () use ($input) {
            $user = AuthMiddleware::getUser();
            $companyId = $user->current_company_id;
            $branchId = AuthMiddleware::getBranchId();
            $fy = AuthMiddleware::getFinancialYear();

            $purchaseId = intval($input['purchase_id'] ?? 0);
            $purchase = Purchase::find($purchaseId);

            $items = $input['items'] ?? [];
            if (empty($items)) {
                throw new \Exception('Please add at least one product.');
            }

            $isIgst = $purchase ? (substr($purchase->place_of_supply ?? '27', 0, 2) !== '27') : false;
            $calc = PurchaseInvoiceService::calculateTotals($items, $isIgst);

            $dnNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'DEBIT_NOTE');

            $note = DebitNote::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_id' => $purchase ? $purchase->supplier_id : intval($input['supplier_id'] ?? 0),
                'purchase_id' => $purchaseId ?: null,
                'debit_note_number' => $dnNumber,
                'debit_note_date' => $input['debit_note_date'] ?? date('Y-m-d'),
                'original_purchase_number' => $purchase ? $purchase->purchase_number : null,
                'sub_total' => $calc['sub_total'],
                'total_tax' => $calc['total_tax'],
                'cgst_reversal' => $calc['cgst_amount'],
                'sgst_reversal' => $calc['sgst_amount'],
                'igst_reversal' => $calc['igst_amount'],
                'amount' => $calc['grand_total'],
                'reason' => $input['reason'] ?? 'Other',
                'status' => 'APPROVED',
                'notes' => $input['notes'] ?? '',
            ]);

            foreach ($calc['items'] as $it) {
                DebitNoteItem::create([
                    'company_id' => $companyId,
                    'debit_note_id' => $note->id,
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

            return $note;
        });
    }
}
