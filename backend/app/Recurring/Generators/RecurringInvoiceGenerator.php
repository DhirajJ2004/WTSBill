<?php

namespace App\Recurring\Generators;

use App\Models\RecurringTransaction;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Branch;
use App\Models\StockBalance;
use App\Models\Company;
use App\Services\PriceCalculationService;
use App\Services\AccountingEventService;
use App\Services\TaxCalculationService;
use App\Notifications\Events\BusinessEvent;
use App\Notifications\Events\EventDispatcher;
use Carbon\Carbon;
use Exception;
use RuntimeException;

class RecurringInvoiceGenerator
{
    /**
     * Generate an authentic commercial sales invoice from a recurring template.
     */
    public static function generate(RecurringTransaction $template, string $occurrenceDate): Invoice
    {
        $companyId = $template->company_id;
        $branchId = $template->branch_id;
        $payload = $template->template_payload_json ?: [];

        // 1. Customer Verification
        $customerId = intval($payload['customer_id'] ?? 0);
        $customer = Customer::where('company_id', $companyId)->find($customerId);
        if (!$customer) {
            throw new RuntimeException("Customer #{$customerId} not found for recurring template '{$template->name}'.");
        }
        if (isset($customer->is_active) && !$customer->is_active) {
            throw new RuntimeException("Customer '{$customer->name}' is INACTIVE. Recurring invoice generation halted.");
        }

        // 2. Branch Verification
        if ($branchId) {
            $branch = Branch::where('company_id', $companyId)->find($branchId);
            if ($branch && !$branch->is_active) {
                throw new RuntimeException("Branch '{$branch->name}' is INACTIVE. Recurring invoice generation halted.");
            }
        }

        $items = $payload['items'] ?? [];
        if (empty($items)) {
            throw new RuntimeException("Recurring template '{$template->name}' contains no invoice items.");
        }

        $company = Company::find($companyId);
        $allowNegative = $company ? (bool)$company->allow_negative_stock : false;

        $invoiceNumber = $payload['invoice_number_prefix'] ?? 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $subTotal = 0.0;
        $totalTax = 0.0;
        $processedItems = [];

        // 3. Process Items with Pricing Policy & Stock Validation
        foreach ($items as $it) {
            $productId = intval($it['product_id']);
            $product = Product::where('company_id', $companyId)->find($productId);
            if (!$product) {
                throw new RuntimeException("Product #{$productId} not found for recurring template.");
            }
            if (isset($product->is_active) && !$product->is_active) {
                throw new RuntimeException("Product '{$product->name}' is INACTIVE. Recurring invoice generation halted.");
            }

            $qty = floatval($it['quantity'] ?? 1.0);
            $unitPrice = floatval($it['unit_price'] ?? 0.0);

            // Pricing Policy: Use Current Price vs Preserve Template Price
            if ($template->pricing_policy === 'USE_CURRENT_PRICE') {
                $priceResult = PriceCalculationService::getApplicablePrice($companyId, $productId, $customerId, $qty, $branchId, $occurrenceDate);
                $unitPrice = floatval($priceResult['price']);
            }

            // Check Stock Availability if not allowing negative stock
            if (!$allowNegative && isset($product->current_stock) && $product->current_stock < $qty) {
                throw new RuntimeException("Insufficient stock for product '{$product->name}'. Available: {$product->current_stock}, Required: {$qty}");
            }

            $taxRate = floatval($it['tax_rate'] ?? ($product->tax_rate ?? 18.0));
            $taxable = round($qty * $unitPrice, 2);
            $taxAmount = round($taxable * ($taxRate / 100), 2);
            $totalAmount = $taxable + $taxAmount;

            $subTotal += $taxable;
            $totalTax += $taxAmount;

            $processedItems[] = [
                'product_id' => $productId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'taxable_amount' => $taxable,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ];
        }

        $grandTotal = $subTotal + $totalTax;
        $dueDays = intval($payload['payment_terms_days'] ?? 15);
        $dueDate = Carbon::parse($occurrenceDate)->addDays($dueDays)->toDateString();

        // 4. Create Invoice Record
        $invoice = Invoice::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $occurrenceDate,
            'due_date' => $dueDate,
            'sub_total' => $subTotal,
            'total_tax' => $totalTax,
            'grand_total' => $grandTotal,
            'amount_paid' => 0.00,
            'amount_due' => $grandTotal,
            'status' => 'UNPAID',
            'notes' => "Generated from recurring schedule: {$template->name}",
        ]);

        foreach ($processedItems as $pItem) {
            $p = Product::where('company_id', $companyId)->find($pItem['product_id']);
            InvoiceItem::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'product_id' => $pItem['product_id'],
                'item_name' => $p?->name ?: 'Item',
                'hsn_sac' => $p?->hsn_code ?: '998313',
                'quantity' => $pItem['quantity'],
                'unit' => $p?->unit ?: 'NOS',
                'unit_price' => $pItem['unit_price'],
                'taxable_value' => $pItem['taxable_amount'],
                'gst_rate' => $pItem['tax_rate'],
                'cgst_rate' => $pItem['tax_rate'] / 2,
                'cgst_amount' => $pItem['tax_amount'] / 2,
                'sgst_rate' => $pItem['tax_rate'] / 2,
                'sgst_amount' => $pItem['tax_amount'] / 2,
                'total_amount' => $pItem['total_amount'],
            ]);
        }

        // 5. Post Double-Entry Accounting
        AccountingEventService::recordSaleAccounting($invoice);

        // 6. Dispatch Business Event
        EventDispatcher::dispatch(new BusinessEvent(
            $companyId,
            'RECURRING_INVOICE_CREATED',
            'INVOICE',
            $invoice->id,
            $branchId,
            [
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $customer->name,
                'invoice_total' => $grandTotal,
            ]
        ));

        return $invoice->load(['items.product', 'customer', 'branch']);
    }
}
