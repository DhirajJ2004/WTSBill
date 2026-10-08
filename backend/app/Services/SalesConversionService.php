<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Support\Facades\DB;

class SalesConversionService
{
    /**
     * Convert Quotation Estimate to Sales Order
     */
    public static function convertQuotationToSalesOrder(int $quotationId): SalesOrder
    {
        $user = AuthMiddleware::getUser();
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $fy = AuthMiddleware::getFinancialYear();

        $quotation = Quotation::with('items')->findOrFail($quotationId);

        $soNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'SALES_ORDER');

        $salesOrder = SalesOrder::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $quotation->customer_id,
            'order_number' => $soNumber,
            'order_date' => date('Y-m-d'),
            'expected_delivery' => date('Y-m-d', strtotime('+7 days')),
            'reference_no' => $quotation->quotation_number,
            'sub_total' => $quotation->sub_total,
            'discount_amount' => $quotation->discount_amount,
            'total_tax' => $quotation->total_tax,
            'grand_total' => $quotation->grand_total,
            'status' => 'CONFIRMED',
            'fulfillment_status' => 'Draft',
            'payment_status' => 'UNPAID',
            'notes' => $quotation->notes,
            'source_quotation_id' => $quotation->id,
        ]);

        foreach ($quotation->items as $item) {
            SalesOrderItem::create([
                'company_id' => $companyId,
                'sales_order_id' => $salesOrder->id,
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

        $quotation->update([
            'status' => 'Converted',
            'converted_to_invoice' => true,
            'converted_invoice_id' => $salesOrder->id,
        ]);

        return $salesOrder;
    }

    /**
     * Convert Quotation Estimate directly to Tax Invoice
     */
    public static function convertQuotationToInvoice(int $quotationId): Invoice
    {
        $user = AuthMiddleware::getUser();
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $fy = AuthMiddleware::getFinancialYear();

        $quotation = Quotation::with('items')->findOrFail($quotationId);

        $invNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'INVOICE');

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $quotation->customer_id,
            'invoice_number' => $invNumber,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'place_of_supply' => $quotation->place_of_supply ?? '27',
            'is_igst' => $quotation->igst_amount > 0,
            'sub_total' => $quotation->sub_total,
            'discount_amount' => $quotation->discount_amount,
            'cgst_amount' => $quotation->cgst_amount,
            'sgst_amount' => $quotation->sgst_amount,
            'igst_amount' => $quotation->igst_amount,
            'total_tax' => $quotation->total_tax,
            'round_off' => $quotation->round_off,
            'grand_total' => $quotation->grand_total,
            'amount_paid' => 0.00,
            'amount_due' => $quotation->grand_total,
            'status' => 'DRAFT', // begins as DRAFT
            'payment_status' => 'UNPAID',
            'notes' => $quotation->notes,
            'terms_and_conditions' => $quotation->terms,
            'source_quotation_id' => $quotation->id,
        ]);

        foreach ($quotation->items as $item) {
            InvoiceItem::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
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

        $quotation->update([
            'status' => 'Converted',
            'converted_to_invoice' => true,
            'converted_invoice_id' => $invoice->id,
        ]);

        return $invoice;
    }

    /**
     * Convert Sales Order to Tax Invoice
     */
    public static function convertSalesOrderToInvoice(int $salesOrderId): Invoice
    {
        $user = AuthMiddleware::getUser();
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $fy = AuthMiddleware::getFinancialYear();

        $so = SalesOrder::with('items')->findOrFail($salesOrderId);

        $invNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'INVOICE');

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $so->customer_id,
            'invoice_number' => $invNumber,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'place_of_supply' => '27',
            'is_igst' => $so->items->sum('igst_amount') > 0,
            'sub_total' => $so->sub_total,
            'discount_amount' => $so->discount_amount,
            'cgst_amount' => $so->items->sum('cgst_amount'),
            'sgst_amount' => $so->items->sum('sgst_amount'),
            'igst_amount' => $so->items->sum('igst_amount'),
            'total_tax' => $so->total_tax,
            'round_off' => 0.00,
            'grand_total' => $so->grand_total,
            'amount_paid' => 0.00,
            'amount_due' => $so->grand_total,
            'status' => 'DRAFT', // begins as DRAFT
            'payment_status' => 'UNPAID',
            'notes' => $so->notes,
            'source_sales_order_id' => $so->id,
        ]);

        foreach ($so->items as $item) {
            InvoiceItem::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
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

        $so->update([
            'fulfillment_status' => 'Fulfilled',
            'status' => 'Fulfilled',
        ]);

        return $invoice;
    }

    /**
     * Convert Sales Order to Delivery Challan
     */
    public static function convertSalesOrderToChallan(int $salesOrderId): DeliveryChallan
    {
        $user = AuthMiddleware::getUser();
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $fy = AuthMiddleware::getFinancialYear();

        $so = SalesOrder::with('items')->findOrFail($salesOrderId);

        $dcNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'DELIVERY_CHALLAN');

        $dc = DeliveryChallan::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $so->customer_id,
            'challan_number' => $dcNumber,
            'challan_date' => date('Y-m-d'),
            'sales_order_id' => $so->id,
            'reference_so' => $so->order_number,
            'delivery_address' => '',
            'transport_details' => '',
            'status' => 'DRAFT',
            'notes' => $so->notes,
        ]);

        foreach ($so->items as $item) {
            DeliveryChallanItem::create([
                'company_id' => $companyId,
                'delivery_challan_id' => $dc->id,
                'product_id' => $item->product_id,
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'total_amount' => $item->total_amount,
            ]);
        }

        $so->update([
            'fulfillment_status' => 'Partially Fulfilled',
            'status' => 'Partially Fulfilled',
        ]);

        return $dc;
    }

    /**
     * Convert Delivery Challan to Tax Invoice
     */
    public static function convertChallanToInvoice(int $challanId): Invoice
    {
        $user = AuthMiddleware::getUser();
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $fy = AuthMiddleware::getFinancialYear();

        $dc = DeliveryChallan::with('items')->findOrFail($challanId);

        $invNumber = DocumentNumberingService::generateNextNumber($companyId, $branchId, $fy, 'INVOICE');

        // Estimate default totals based on items
        $subTotal = $dc->items->sum('total_amount');
        $grandTotal = $subTotal; // simple flat pricing preview

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $dc->customer_id,
            'invoice_number' => $invNumber,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'place_of_supply' => '27',
            'is_igst' => false,
            'sub_total' => $subTotal,
            'discount_amount' => 0.00,
            'cgst_amount' => 0.00,
            'sgst_amount' => 0.00,
            'igst_amount' => 0.00,
            'total_tax' => 0.00,
            'round_off' => 0.00,
            'grand_total' => $grandTotal,
            'amount_paid' => 0.00,
            'amount_due' => $grandTotal,
            'status' => 'DRAFT', // begins as DRAFT
            'payment_status' => 'UNPAID',
            'notes' => $dc->notes,
            'source_delivery_challan_id' => $dc->id,
        ]);

        foreach ($dc->items as $item) {
            InvoiceItem::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'product_id' => $item->product_id,
                'item_name' => $item->item_name,
                'hsn_sac' => '84818030', // default snapshot
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount_rate' => 0.00,
                'discount_amount' => 0.00,
                'taxable_value' => $item->total_amount,
                'gst_rate' => 0.00,
                'cgst_rate' => 0.00,
                'cgst_amount' => 0.00,
                'sgst_rate' => 0.00,
                'sgst_amount' => 0.00,
                'igst_rate' => 0.00,
                'igst_amount' => 0.00,
                'total_amount' => $item->total_amount,
            ]);
        }

        $dc->update([
            'status' => 'Delivered',
        ]);

        return $invoice;
    }
}
