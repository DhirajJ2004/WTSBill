<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

class PurchaseOrderService
{
    public static function createPO(array $input): PurchaseOrder
    {
        return DB::transaction(function () use ($input) {
            $user = AuthMiddleware::getUser();
            $companyId = $user->current_company_id;
            $branchId = AuthMiddleware::getBranchId();
            $fy = AuthMiddleware::getFinancialYear();

            $supplier = Supplier::findOrFail(intval($input['supplier_id'] ?? 0));
            $items = $input['items'] ?? [];
            if (empty($items)) {
                throw new \Exception('Please add at least one product.');
            }

            $isIgst = (substr($supplier->state_code ?? '27', 0, 2) !== '27');
            $calc = PurchaseInvoiceService::calculateTotals($items, $isIgst);

            $poNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'PURCHASE_ORDER');

            $po = PurchaseOrder::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_id' => $supplier->id,
                'po_number' => $poNumber,
                'po_date' => $input['po_date'] ?? date('Y-m-d'),
                'expected_delivery' => $input['expected_delivery'] ?? date('Y-m-d', strtotime('+7 days')),
                'reference_no' => $input['reference_no'] ?? null,
                'sub_total' => $calc['sub_total'],
                'discount_amount' => $calc['discount_amount'],
                'total_tax' => $calc['total_tax'],
                'grand_total' => $calc['grand_total'],
                'status' => $input['status'] ?? 'DRAFT',
                'notes' => $input['notes'] ?? '',
                'terms' => $input['terms'] ?? '',
            ]);

            foreach ($calc['items'] as $it) {
                PurchaseOrderItem::create([
                    'company_id' => $companyId,
                    'purchase_order_id' => $po->id,
                    'product_id' => $it['product_id'],
                    'item_name' => $it['item_name'] ?? $it['name'] ?? 'Item',
                    'hsn_sac' => $it['hsn_sac'] ?? '84818030',
                    'quantity' => floatval($it['quantity'] ?? 0),
                    'received_quantity' => 0.000,
                    'unit' => $it['unit'] ?? 'Pcs',
                    'unit_price' => floatval($it['unit_price'] ?? 0),
                    'discount_rate' => floatval($it['discount_rate'] ?? 0),
                    'discount_amount' => floatval($it['discount_amount'] ?? 0),
                    'taxable_value' => floatval($it['taxable_value'] ?? 0),
                    'gst_rate' => floatval($it['gst_rate'] ?? 18),
                    'cgst_rate' => floatval($it['cgst_rate'] ?? 9),
                    'cgst_amount' => floatval($it['cgst_amount'] ?? 0),
                    'sgst_rate' => floatval($it['sgst_rate'] ?? 9),
                    'sgst_amount' => floatval($it['sgst_amount'] ?? 0),
                    'igst_rate' => floatval($it['igst_rate'] ?? 0),
                    'igst_amount' => floatval($it['igst_amount'] ?? 0),
                    'total_amount' => floatval($it['total_amount'] ?? 0),
                ]);
            }

            return $po;
        });
    }

    public static function convertPOToInvoice(int $poId): Purchase
    {
        return DB::transaction(function () use ($poId) {
            $user = AuthMiddleware::getUser();
            $companyId = $user->current_company_id;
            $branchId = AuthMiddleware::getBranchId();
            $fy = AuthMiddleware::getFinancialYear();

            $po = PurchaseOrder::with('items')->findOrFail($poId);

            $pinvNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'PURCHASE_INVOICE');

            $purchase = Purchase::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'purchase_number' => $pinvNumber,
                'vendor_invoice_number' => 'BILL-PO-' . rand(1000, 9999),
                'purchase_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'reference_no' => $po->po_number,
                'po_reference' => $po->po_number,
                'sub_total' => $po->sub_total,
                'cgst_amount' => $po->items->sum('cgst_amount'),
                'sgst_amount' => $po->items->sum('sgst_amount'),
                'igst_amount' => $po->items->sum('igst_amount'),
                'total_tax' => $po->total_tax,
                'grand_total' => $po->grand_total,
                'amount_paid' => 0.00,
                'amount_due' => $po->grand_total,
                'status' => 'DRAFT', // converted always starts as DRAFT
                'payment_status' => 'UNPAID',
                'notes' => $po->notes,
            ]);

            foreach ($po->items as $it) {
                PurchaseItem::create([
                    'company_id' => $companyId,
                    'purchase_id' => $purchase->id,
                    'product_id' => $it->product_id,
                    'item_name' => $it->item_name,
                    'hsn_sac' => $it->hsn_sac,
                    'quantity' => $it->quantity,
                    'unit' => $it->unit,
                    'unit_price' => $it->unit_price,
                    'discount_rate' => $it->discount_rate,
                    'discount_amount' => $it->discount_amount,
                    'taxable_value' => $it->taxable_value,
                    'gst_rate' => $it->gst_rate,
                    'cgst_rate' => $it->cgst_rate,
                    'cgst_amount' => $it->cgst_amount,
                    'sgst_rate' => $it->sgst_rate,
                    'sgst_amount' => $it->sgst_amount,
                    'igst_rate' => $it->igst_rate,
                    'igst_amount' => $it->igst_amount,
                    'tax_amount' => $it->cgst_amount + $it->sgst_amount + $it->igst_amount,
                    'total_amount' => $it->total_amount,
                ]);
            }

            $po->update(['status' => 'CLOSED']);

            return $purchase;
        });
    }
}
