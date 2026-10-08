<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Company;
use App\Services\InvoiceService;
use App\Services\SalesOrderService;
use App\Services\DeliveryChallanService;
use App\Services\AuditLogService;
use App\Middleware\AuthMiddleware;
use Illuminate\Database\Capsule\Manager as DB;

class SalesConversionService
{
    /**
     * Convert Quotation to Sales Order.
     */
    public static function convertQuotationToSalesOrder(
        int $quotationId,
        ?int $companyId = null,
        mixed $authUser = 'Admin'
    ): array {
        $comp = $companyId ?: (AuthMiddleware::getTenantId() ?: 1);
        $userName = is_string($authUser) ? $authUser : ($authUser->name ?? 'Admin');

        $quotation = Quotation::withoutGlobalScopes()
            ->where('id', $quotationId)
            ->where('company_id', $comp)
            ->first();

        if (!$quotation) {
            return [
                'success' => false,
                'code' => 404,
                'message' => "Quotation #{$quotationId} not found or unauthorized for current tenant."
            ];
        }

        if ($quotation->converted_to_invoice || strtoupper($quotation->status) === 'CONVERTED') {
            return [
                'success' => false,
                'code' => 422,
                'message' => "Quotation #{$quotation->quotation_number} has already been converted."
            ];
        }

        $items = QuotationItem::where('quotation_id', $quotation->id)
            ->where('company_id', $comp)
            ->get();

        if ($items->isEmpty()) {
            return [
                'success' => false,
                'code' => 422,
                'message' => "Quotation #{$quotation->quotation_number} contains no line items to convert."
            ];
        }

        $orderItems = [];
        foreach ($items as $item) {
            $orderItems[] = [
                'product_id' => $item->product_id,
                'item_name' => $item->item_name,
                'hsn_sac' => $item->hsn_sac,
                'quantity' => (float)$item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float)$item->unit_price,
                'discount_rate' => (float)$item->discount_rate,
                'tax_rate' => (float)$item->gst_rate,
            ];
        }

        $soPayload = [
            'customer_id' => $quotation->customer_id,
            'order_date' => date('Y-m-d'),
            'expected_delivery' => date('Y-m-d', strtotime('+7 days')),
            'reference_no' => $quotation->quotation_number,
            'discount_amount' => (float)$quotation->discount_amount,
            'status' => 'CONFIRMED',
            'fulfillment_status' => 'Unfulfilled',
            'notes' => $quotation->notes,
            'source_quotation_id' => $quotation->id,
            'items' => $orderItems,
        ];

        return DB::transaction(function () use ($quotation, $soPayload, $comp, $userName) {
            $soRes = SalesOrderService::createSalesOrder($soPayload, $comp, $quotation->branch_id, $userName);
            if (!$soRes['success']) {
                return $soRes;
            }

            $quotation->update([
                'status' => 'CONVERTED',
                'converted_to_invoice' => true,
            ]);

            AuditLogService::log(
                $comp,
                $userName,
                'CONVERT_QUOTATION_TO_SO',
                'Quotation',
                $quotation->id,
                "Converted Quotation #{$quotation->quotation_number} to Sales Order #{$soRes['order_number']}"
            );

            return [
                'success' => true,
                'sales_order_id' => $soRes['sales_order_id'],
                'order_number' => $soRes['order_number'],
                'sales_order' => $soRes['sales_order'],
                'message' => "Quotation #{$quotation->quotation_number} converted to Sales Order #{$soRes['order_number']} successfully."
            ];
        });
    }

    /**
     * Convert Quotation directly to Tax Invoice.
     */
    public static function convertQuotationToInvoice(
        int $quotationId,
        ?int $companyId = null,
        mixed $authUser = 'Admin'
    ): array {
        $comp = $companyId ?: (AuthMiddleware::getTenantId() ?: 1);
        $userName = is_string($authUser) ? $authUser : ($authUser->name ?? 'Admin');

        $quotation = Quotation::withoutGlobalScopes()
            ->where('id', $quotationId)
            ->where('company_id', $comp)
            ->first();

        if (!$quotation) {
            return [
                'success' => false,
                'code' => 404,
                'message' => "Quotation #{$quotationId} not found or unauthorized for current tenant."
            ];
        }

        if ($quotation->converted_to_invoice || strtoupper($quotation->status) === 'CONVERTED') {
            return [
                'success' => false,
                'code' => 422,
                'message' => "Quotation #{$quotation->quotation_number} has already been converted."
            ];
        }

        $items = QuotationItem::where('quotation_id', $quotation->id)
            ->where('company_id', $comp)
            ->get();

        if ($items->isEmpty()) {
            return [
                'success' => false,
                'code' => 422,
                'message' => "Quotation #{$quotation->quotation_number} contains no line items to convert."
            ];
        }

        $invItems = [];
        foreach ($items as $item) {
            $invItems[] = [
                'product_id' => $item->product_id,
                'item_name' => $item->item_name,
                'hsn_sac' => $item->hsn_sac,
                'quantity' => (float)$item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float)$item->unit_price,
                'discount_rate' => (float)$item->discount_rate,
                'tax_rate' => (float)$item->gst_rate,
            ];
        }

        $invPayload = [
            'customer_id' => $quotation->customer_id,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'reference_po_number' => $quotation->quotation_number,
            'discount_amount' => (float)$quotation->discount_amount,
            'status' => 'POSTED',
            'notes' => $quotation->notes,
            'terms_and_conditions' => $quotation->terms,
            'items' => $invItems,
        ];

        return DB::transaction(function () use ($quotation, $invPayload, $comp, $userName) {
            $invRes = InvoiceService::createInvoice($invPayload, $comp, $quotation->branch_id, $userName);
            if (!$invRes['success']) {
                return $invRes;
            }

            $quotation->update([
                'status' => 'CONVERTED',
                'converted_to_invoice' => true,
                'converted_invoice_id' => $invRes['invoice_id'],
            ]);

            AuditLogService::log(
                $comp,
                $userName,
                'CONVERT_QUOTATION_TO_INVOICE',
                'Quotation',
                $quotation->id,
                "Converted Quotation #{$quotation->quotation_number} to Invoice #{$invRes['invoice_number']}"
            );

            return [
                'success' => true,
                'invoice_id' => $invRes['invoice_id'],
                'invoice_number' => $invRes['invoice_number'],
                'data' => $invRes['invoice'] ?? ($invRes['data'] ?? null),
                'message' => "Quotation #{$quotation->quotation_number} converted to Invoice #{$invRes['invoice_number']} successfully."
            ];
        });
    }

    /**
     * Convert Sales Order to Delivery Challan.
     */
    public static function convertSalesOrderToChallan(
        int $salesOrderId,
        ?int $companyId = null,
        mixed $authUser = 'Admin',
        bool $dispatchStock = false
    ): array {
        $comp = $companyId ?: (AuthMiddleware::getTenantId() ?: 1);
        $userName = is_string($authUser) ? $authUser : ($authUser->name ?? 'Admin');

        $so = SalesOrder::withoutGlobalScopes()
            ->where('id', $salesOrderId)
            ->where('company_id', $comp)
            ->first();

        if (!$so) {
            return [
                'success' => false,
                'code' => 404,
                'message' => "Sales Order #{$salesOrderId} not found or unauthorized for current tenant."
            ];
        }

        if (in_array(strtoupper($so->status), ['FULFILLED', 'CANCELLED'], true) || in_array(strtoupper($so->fulfillment_status ?? ''), ['FULFILLED'], true)) {
            return [
                'success' => false,
                'code' => 422,
                'message' => "Sales Order #{$so->order_number} is already fulfilled or cancelled."
            ];
        }

        $items = SalesOrderItem::where('sales_order_id', $so->id)
            ->where('company_id', $comp)
            ->get();

        if ($items->isEmpty()) {
            return [
                'success' => false,
                'code' => 422,
                'message' => "Sales Order #{$so->order_number} contains no line items to convert."
            ];
        }

        $dcItems = [];
        foreach ($items as $item) {
            $dcItems[] = [
                'product_id' => $item->product_id,
                'item_name' => $item->item_name,
                'quantity' => (float)$item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float)$item->unit_price,
            ];
        }

        $dcPayload = [
            'customer_id' => $so->customer_id,
            'challan_date' => date('Y-m-d'),
            'sales_order_id' => $so->id,
            'reference_so' => $so->order_number,
            'dispatch_stock' => $dispatchStock,
            'status' => $dispatchStock ? 'DISPATCHED' : 'DRAFT',
            'notes' => $so->notes,
            'items' => $dcItems,
        ];

        return DB::transaction(function () use ($so, $dcPayload, $comp, $userName) {
            $dcRes = DeliveryChallanService::createDeliveryChallan($dcPayload, $comp, $so->branch_id, $userName);
            if (!$dcRes['success']) {
                return $dcRes;
            }

            $so->update([
                'status' => 'PARTIALLY_FULFILLED',
                'fulfillment_status' => 'Partially Fulfilled',
            ]);

            AuditLogService::log(
                $comp,
                $userName,
                'CONVERT_SO_TO_CHALLAN',
                'SalesOrder',
                $so->id,
                "Created Delivery Challan #{$dcRes['challan_number']} from Sales Order #{$so->order_number}"
            );

            return [
                'success' => true,
                'delivery_challan_id' => $dcRes['delivery_challan_id'],
                'challan_number' => $dcRes['challan_number'],
                'challan' => $dcRes['challan'],
                'message' => "Sales Order #{$so->order_number} converted to Delivery Challan #{$dcRes['challan_number']} successfully."
            ];
        });
    }

    /**
     * Convert Sales Order directly to Tax Invoice.
     */
    public static function convertSalesOrderToInvoice(
        int $salesOrderId,
        ?int $companyId = null,
        mixed $authUser = 'Admin'
    ): array {
        $comp = $companyId ?: (AuthMiddleware::getTenantId() ?: 1);
        $userName = is_string($authUser) ? $authUser : ($authUser->name ?? 'Admin');

        $so = SalesOrder::withoutGlobalScopes()
            ->where('id', $salesOrderId)
            ->where('company_id', $comp)
            ->first();

        if (!$so) {
            return [
                'success' => false,
                'code' => 404,
                'message' => "Sales Order #{$salesOrderId} not found or unauthorized for current tenant."
            ];
        }

        if (in_array(strtoupper($so->status), ['FULFILLED', 'CANCELLED'], true) || in_array(strtoupper($so->fulfillment_status ?? ''), ['FULFILLED'], true)) {
            return [
                'success' => false,
                'code' => 422,
                'message' => "Sales Order #{$so->order_number} is already fulfilled or cancelled."
            ];
        }

        $items = SalesOrderItem::where('sales_order_id', $so->id)
            ->where('company_id', $comp)
            ->get();

        if ($items->isEmpty()) {
            return [
                'success' => false,
                'code' => 422,
                'message' => "Sales Order #{$so->order_number} contains no line items to convert."
            ];
        }

        $invItems = [];
        foreach ($items as $item) {
            $invItems[] = [
                'product_id' => $item->product_id,
                'item_name' => $item->item_name,
                'hsn_sac' => $item->hsn_sac,
                'quantity' => (float)$item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float)$item->unit_price,
                'discount_rate' => (float)$item->discount_rate,
                'tax_rate' => (float)$item->gst_rate,
            ];
        }

        $invPayload = [
            'customer_id' => $so->customer_id,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'reference_po_number' => $so->order_number,
            'discount_amount' => (float)$so->discount_amount,
            'status' => 'POSTED',
            'notes' => $so->notes,
            'items' => $invItems,
        ];

        return DB::transaction(function () use ($so, $invPayload, $comp, $userName) {
            $invRes = InvoiceService::createInvoice($invPayload, $comp, $so->branch_id, $userName);
            if (!$invRes['success']) {
                return $invRes;
            }

            $so->update([
                'status' => 'FULFILLED',
                'fulfillment_status' => 'Fulfilled',
            ]);

            AuditLogService::log(
                $comp,
                $userName,
                'CONVERT_SO_TO_INVOICE',
                'SalesOrder',
                $so->id,
                "Converted Sales Order #{$so->order_number} to Invoice #{$invRes['invoice_number']}"
            );

            return [
                'success' => true,
                'invoice_id' => $invRes['invoice_id'],
                'invoice_number' => $invRes['invoice_number'],
                'data' => $invRes['invoice'] ?? ($invRes['data'] ?? null),
                'message' => "Sales Order #{$so->order_number} converted to Invoice #{$invRes['invoice_number']} successfully."
            ];
        });
    }

    /**
     * Convert Delivery Challan to Tax Invoice.
     * Prevents duplicate stock deductions if challan was already dispatched.
     */
    public static function convertChallanToInvoice(
        int $challanId,
        ?int $companyId = null,
        mixed $authUser = 'Admin'
    ): array {
        $comp = $companyId ?: (AuthMiddleware::getTenantId() ?: 1);
        $userName = is_string($authUser) ? $authUser : ($authUser->name ?? 'Admin');

        $dc = DeliveryChallan::withoutGlobalScopes()
            ->where('id', $challanId)
            ->where('company_id', $comp)
            ->first();

        if (!$dc) {
            return [
                'success' => false,
                'code' => 404,
                'message' => "Delivery Challan #{$challanId} not found or unauthorized for current tenant."
            ];
        }

        if (in_array(strtoupper($dc->status), ['INVOICED', 'CANCELLED'], true)) {
            return [
                'success' => false,
                'code' => 422,
                'message' => "Delivery Challan #{$dc->challan_number} is already invoiced or cancelled."
            ];
        }

        $items = DeliveryChallanItem::where('delivery_challan_id', $dc->id)
            ->where('company_id', $comp)
            ->get();

        if ($items->isEmpty()) {
            return [
                'success' => false,
                'code' => 422,
                'message' => "Delivery Challan #{$dc->challan_number} contains no line items to convert."
            ];
        }

        $invItems = [];
        foreach ($items as $item) {
            $invItems[] = [
                'product_id' => $item->product_id,
                'item_name' => $item->item_name,
                'quantity' => (float)$item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float)$item->unit_price,
            ];
        }

        // If DC was already dispatched, skip duplicate stock deduction on invoice creation!
        $alreadyDispatched = in_array(strtoupper($dc->status), ['DISPATCHED', 'DELIVERED'], true);

        $invPayload = [
            'customer_id' => $dc->customer_id,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'reference_po_number' => $dc->challan_number,
            'skip_stock_deduction' => $alreadyDispatched,
            'status' => 'POSTED',
            'notes' => $dc->notes,
            'items' => $invItems,
        ];

        return DB::transaction(function () use ($dc, $invPayload, $comp, $userName) {
            $invRes = InvoiceService::createInvoice($invPayload, $comp, $dc->branch_id, $userName);
            if (!$invRes['success']) {
                return $invRes;
            }

            $dc->update([
                'status' => 'INVOICED',
            ]);

            AuditLogService::log(
                $comp,
                $userName,
                'CONVERT_CHALLAN_TO_INVOICE',
                'DeliveryChallan',
                $dc->id,
                "Converted Delivery Challan #{$dc->challan_number} to Invoice #{$invRes['invoice_number']}"
            );

            return [
                'success' => true,
                'invoice_id' => $invRes['invoice_id'],
                'invoice_number' => $invRes['invoice_number'],
                'data' => $invRes['invoice'] ?? ($invRes['data'] ?? null),
                'message' => "Delivery Challan #{$dc->challan_number} converted to Invoice #{$invRes['invoice_number']} successfully."
            ];
        });
    }
}
