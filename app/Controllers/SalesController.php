<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;

/**
 * Sales Controller.
 * Manages tax invoices, quotations, delivery challans, line item tax splits, and persistence.
 */
class SalesController
{
    /**
     * List all tax invoices.
     */
    public function invoicesIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $branchId = Session::getBranchId() ?? 1;
        $invoices = Database::select(
            "SELECT i.id, i.invoice_number, i.customer_id, COALESCE(c.name, i.customer_name, 'Direct Customer') as customer_name,
                    i.invoice_date, i.due_date, i.sub_total, i.total_tax, i.grand_total, i.amount_due, i.status 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.company_id = ? AND (i.branch_id = ? OR i.branch_id IS NULL)
             ORDER BY i.id DESC",
            [$companyId, $branchId]
        );

        $html = View::renderWithLayout('sales/invoices', [
            'title' => 'Tax Invoices',
            'currentUri' => '/sales/invoices',
            'invoices' => $invoices,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Show single invoice details with line items (JSON API).
     */
    public function invoiceShow(Request $req, mixed $id = null): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $invId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));

        $invoice = Database::selectOne(
            "SELECT i.*, COALESCE(c.name, i.customer_name) as customer_name, c.gstin as customer_gstin, c.phone as customer_phone, c.address_line1 as customer_address, c.city as customer_city, c.state as customer_state 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.id = ? AND i.company_id = ?",
            [$invId, $companyId]
        );

        if (!$invoice) {
            return Response::json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $items = Database::select(
            "SELECT * FROM invoice_items WHERE invoice_id = ? AND company_id = ? ORDER BY id ASC",
            [$invId, $companyId]
        );

        return Response::json([
            'success' => true,
            'invoice' => $invoice,
            'items' => $items,
        ]);
    }

    /**
     * Render sales invoice creation page.
     */
    public function invoiceCreateForm(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;

        $customers = Database::select(
            "SELECT id, name, company_name, gstin, phone, state_code FROM customers WHERE company_id = ? AND is_active = 1 ORDER BY name ASC",
            [$companyId]
        );

        $products = Database::select(
            "SELECT id, name, sku, COALESCE(selling_price, sales_price, 0) as selling_price, hsn_sac, tax_rate, current_stock, unit 
             FROM products 
             WHERE company_id = ? AND is_active = 1 
             ORDER BY name ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('sales/create', [
            'title' => 'Generate Tax Invoice',
            'currentUri' => '/sales/invoices/create',
            'customers' => $customers,
            'products' => $products,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new tax invoice with line items, tax split, and transaction safety.
     */
    public function invoiceStore(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $customerId = (int)($payload['customer_id'] ?? 0);
        $invoiceDate = trim((string)($payload['invoice_date'] ?? date('Y-m-d')));
        $dueDate = trim((string)($payload['due_date'] ?? date('Y-m-d', strtotime('+30 days'))));
        $placeOfSupply = trim((string)($payload['place_of_supply'] ?? '27'));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($customerId <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Please select a valid customer.'], 422);
            }
            Session::flash('error', 'Please select a valid customer.');
            return Response::redirect('/sales/invoices/create');
        }

        // Validate items
        $productId = (int)($payload['product_id'] ?? 0);
        $qty = (float)($payload['quantity'] ?? 1);
        $rate = (float)($payload['rate'] ?? 0);
        $gstRate = (float)($payload['gst_rate'] ?? 18);
        $hsn = trim((string)($payload['hsn_sac'] ?? '7318'));

        if ($rate <= 0 && $productId > 0) {
            $p = Database::selectOne("SELECT COALESCE(selling_price, sales_price, 0) as price FROM products WHERE id = ?", [$productId]);
            if ($p) {
                $rate = (float)$p['price'];
            }
        }
        if ($rate <= 0) {
            $rate = 100.00;
        }
        if ($qty <= 0) {
            $qty = 1.0;
        }

        // Intra-state (27 Maharashtra) vs Inter-state
        $isIntraState = ($placeOfSupply === '27');
        $taxableValue = round($qty * $rate, 2);

        if ($isIntraState) {
            $cgstRate = $gstRate / 2;
            $sgstRate = $gstRate / 2;
            $igstRate = 0.0;
            $cgstAmount = round($taxableValue * ($cgstRate / 100), 2);
            $sgstAmount = round($taxableValue * ($sgstRate / 100), 2);
            $igstAmount = 0.0;
        } else {
            $cgstRate = 0.0;
            $sgstRate = 0.0;
            $igstRate = $gstRate;
            $cgstAmount = 0.0;
            $sgstAmount = 0.0;
            $igstAmount = round($taxableValue * ($igstRate / 100), 2);
        }

        $totalTax = round($cgstAmount + $sgstAmount + $igstAmount, 2);
        $rawTotal = $taxableValue + $totalTax;
        $grandTotal = round($rawTotal);
        $roundOff = round($grandTotal - $rawTotal, 2);

        // Fetch customer details
        $customer = Database::selectOne("SELECT name, gstin, address_line1 FROM customers WHERE id = ?", [$customerId]);

        try {
            $invoiceId = 0;
            $invoiceNumber = '';

            Database::transaction(function() use (
                $companyId, $customerId, $customer, $invoiceDate, $dueDate, $placeOfSupply,
                $isIntraState, $taxableValue, $cgstAmount, $sgstAmount, $igstAmount, $totalTax,
                $roundOff, $grandTotal, $notes, $productId, $qty, $rate, $gstRate, $cgstRate,
                $sgstRate, $igstRate, $hsn, &$invoiceId, &$invoiceNumber
            ) {
                // Generate sequential invoice number
                $lastInv = Database::selectOne(
                    "SELECT id FROM invoices WHERE company_id = ? ORDER BY id DESC LIMIT 1",
                    [$companyId]
                );
                $nextNum = ($lastInv ? (int)$lastInv['id'] : 0) + 1;
                $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);

                // Insert into invoices
                $invoiceId = (int)Database::insert('invoices', [
                    'company_id' => $companyId,
                    'branch_id' => (int)(Session::getBranchId() ?? 1),
                    'customer_id' => $customerId,
                    'customer_name' => $customer['name'] ?? 'Direct Customer',
                    'customer_gstin' => $customer['gstin'] ?? null,
                    'billing_address' => $customer['address_line1'] ?? null,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'place_of_supply' => $placeOfSupply,
                    'is_igst' => $isIntraState ? 0 : 1,
                    'sub_total' => $taxableValue,
                    'discount_rate' => 0.0,
                    'discount_amount' => 0.0,
                    'cgst_amount' => $cgstAmount,
                    'sgst_amount' => $sgstAmount,
                    'igst_amount' => $igstAmount,
                    'total_tax' => $totalTax,
                    'round_off' => $roundOff,
                    'grand_total' => $grandTotal,
                    'amount_paid' => 0.0,
                    'amount_due' => $grandTotal,
                    'status' => 'UNPAID',
                    'notes' => $notes ?: 'Thank you for your business.',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Insert line item
                $productName = 'Standard Industrial Hardware Item';
                if ($productId > 0) {
                    $p = Database::selectOne("SELECT name FROM products WHERE id = ?", [$productId]);
                    if ($p) {
                        $productName = $p['name'];
                    }
                }

                Database::insert('invoice_items', [
                    'company_id' => $companyId,
                    'invoice_id' => $invoiceId,
                    'product_id' => $productId > 0 ? $productId : null,
                    'item_name' => $productName,
                    'hsn_sac' => $hsn ?: '7318',
                    'quantity' => $qty,
                    'unit' => 'Pcs',
                    'unit_price' => $rate,
                    'discount_rate' => 0.0,
                    'discount_amount' => 0.0,
                    'taxable_value' => $taxableValue,
                    'gst_rate' => $gstRate,
                    'cgst_rate' => $cgstRate,
                    'cgst_amount' => $cgstAmount,
                    'sgst_rate' => $sgstRate,
                    'sgst_amount' => $sgstAmount,
                    'igst_rate' => $igstRate,
                    'igst_amount' => $igstAmount,
                    'total_amount' => $grandTotal,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Update customer current balance
                $custRow = Database::selectOne("SELECT current_balance FROM customers WHERE id = ?", [$customerId]);
                $newBal = (float)($custRow['current_balance'] ?? 0) + $grandTotal;
                Database::update(
                    'customers',
                    ['current_balance' => $newBal],
                    'id = ? AND company_id = ?',
                    [$customerId, $companyId]
                );
            });

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Invoice '{$invoiceNumber}' generated successfully.",
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                ], 201);
            }

            Session::flash('success', "Invoice '{$invoiceNumber}' generated successfully.");
            return Response::redirect('/sales/invoices');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to create invoice: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to create invoice: ' . $e->getMessage());
            return Response::redirect('/sales/invoices/create');
        }
    }

    /**
     * Sales quotations view.
     */
    public function quotationsIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $quotations = Database::select(
            "SELECT q.id, q.quotation_number, q.customer_id, COALESCE(c.name, 'Direct Client') as customer_name,
                    q.quotation_date, q.valid_until, q.sub_total, q.total_tax, q.grand_total, q.status 
             FROM quotations q 
             LEFT JOIN customers c ON q.customer_id = c.id 
             WHERE q.company_id = ? 
             ORDER BY q.id DESC",
            [$companyId]
        );

        $customers = Database::select(
            "SELECT id, name, company_name FROM customers WHERE company_id = ? AND is_active = 1 ORDER BY name ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('sales/quotations', [
            'title' => 'Quotations & Estimates',
            'currentUri' => '/sales/quotations',
            'quotations' => $quotations,
            'customers' => $customers,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store sales quotation.
     */
    public function quotationStore(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $customerId = (int)($payload['customer_id'] ?? 0);
        $amount = (float)($payload['grand_total'] ?? ($payload['amount'] ?? 0));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($customerId <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Please select a customer.'], 422);
            }
            Session::flash('error', 'Please select a customer.');
            return Response::redirect('/sales/quotations');
        }

        if ($amount <= 0) {
            $amount = 5000.00;
        }

        $subTotal = round($amount / 1.18, 2);
        $totalTax = round($amount - $subTotal, 2);
        $quotationNumber = 'QT-' . date('Y') . '-' . rand(1000, 9999);

        try {
            $id = Database::insert('quotations', [
                'company_id' => $companyId,
                'branch_id' => (int)(Session::getBranchId() ?? 1),
                'customer_id' => $customerId,
                'quotation_number' => $quotationNumber,
                'quotation_date' => date('Y-m-d'),
                'valid_until' => date('Y-m-d', strtotime('+15 days')),
                'sub_total' => $subTotal,
                'total_tax' => $totalTax,
                'grand_total' => $amount,
                'status' => 'SENT',
                'notes' => $notes ?: 'Commercial quotation',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Quotation '{$quotationNumber}' created successfully.",
                ], 201);
            }

            Session::flash('success', "Quotation '{$quotationNumber}' created successfully.");
            return Response::redirect('/sales/quotations');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create quotation: ' . $e->getMessage());
            return Response::redirect('/sales/quotations');
        }
    }

    /**
     * Delivery challans view.
     */
    public function challansIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $challans = Database::select(
            "SELECT dc.id, dc.challan_number, dc.customer_id, COALESCE(c.name, 'Direct Client') as customer_name,
                    dc.challan_date, dc.status, dc.notes 
             FROM delivery_challans dc 
             LEFT JOIN customers c ON dc.customer_id = c.id 
             WHERE dc.company_id = ? 
             ORDER BY dc.id DESC",
            [$companyId]
        );

        $customers = Database::select(
            "SELECT id, name FROM customers WHERE company_id = ? AND is_active = 1 ORDER BY name ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('sales/challans', [
            'title' => 'Delivery Challans',
            'currentUri' => '/sales/challans',
            'challans' => $challans,
            'customers' => $customers,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store delivery challan.
     */
    public function challanStore(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $customerId = (int)($payload['customer_id'] ?? 0);
        $deliveryAddress = trim((string)($payload['delivery_address'] ?? ''));
        $transport = trim((string)($payload['transport_details'] ?? ''));

        if ($customerId <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Please select a customer.'], 422);
            }
            Session::flash('error', 'Please select a customer.');
            return Response::redirect('/sales/challans');
        }

        $challanNumber = 'DC-' . date('Y') . '-' . rand(1000, 9999);

        try {
            Database::insert('delivery_challans', [
                'company_id' => $companyId,
                'branch_id' => (int)(Session::getBranchId() ?? 1),
                'customer_id' => $customerId,
                'challan_number' => $challanNumber,
                'challan_date' => date('Y-m-d'),
                'delivery_address' => $deliveryAddress ?: 'Standard site delivery',
                'transport_details' => $transport ?: 'Direct dispatch via road logistics',
                'status' => 'DELIVERED',
                'notes' => 'Materials dispatched in good condition',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Delivery challan '{$challanNumber}' generated successfully.",
                ], 201);
            }

            Session::flash('success', "Delivery challan '{$challanNumber}' generated successfully.");
            return Response::redirect('/sales/challans');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to create challan: ' . $e->getMessage());
            return Response::redirect('/sales/challans');
        }
    }

    protected function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $contentType = (string)($req->header('content-type') ?? '');
        $xRequestedWith = (string)($req->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}
