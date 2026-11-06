<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Auth\Gate;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\CustomerService;

/**
 * Customer Controller.
 * Handles customer directory, modal registration, full-page registration, view, and updates.
 */
class CustomerController
{
    protected CustomerService $customerService;

    public function __construct(?CustomerService $customerService = null)
    {
        $this->customerService = $customerService ?? new CustomerService();
    }

    /**
     * Customer master directory view.
     */
    public function index(Request $req): Response
    {
        $search = (string)($req->query('search') ?? '');
        $customers = $this->customerService->getCustomers($search);

        $html = View::renderWithLayout('parties/customers', [
            'title' => 'Customer Directory',
            'currentUri' => '/customers',
            'customers' => $customers,
            'search' => $search,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Render full-page registration form.
     */
    public function createForm(Request $req): Response
    {
        $html = View::renderWithLayout('parties/create', [
            'title' => 'New Customer Registration',
            'currentUri' => '/customers/create',
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new customer.
     */
    public function store(Request $req): Response
    {
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $customer = $this->customerService->createCustomer($payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Customer '{$customer['name']}' registered successfully.",
                    'customer' => $customer,
                ], 201);
            }

            Session::flash('success', "Customer '{$customer['name']}' registered successfully.");
            return Response::redirect('/customers');
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            Session::flash('error', $e->getMessage());
            Session::flash('validation_errors', $e->getErrors());
            return Response::redirect('/customers/create');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'An error occurred while saving customer: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to register customer: ' . $e->getMessage());
            return Response::redirect('/customers/create');
        }
    }

    /**
     * Show single customer record (JSON for modal / details) or render full Profile page.
     */
    public function show(Request $req, mixed $id = null): Response
    {
        $customerId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));

        // If client is AJAX or explicitly asking for JSON, preserve existing JSON contract
        if ($this->expectsJson($req) || $req->query('format') === 'json') {
            $customer = $this->customerService->getCustomer($customerId);
            if (!$customer) {
                return Response::json(['success' => false, 'message' => 'Customer not found.'], 404);
            }
            return Response::json(['success' => true, 'customer' => $customer]);
        }

        // Standard browser navigation: render full Customer Profile page
        return $this->profile($req, $customerId);
    }

    /**
     * Comprehensive 360-degree Customer Profile page.
     * Route: GET /customers/{id} or /customers/{id}/profile
     */
    public function profile(Request $req, mixed $id = null): Response
    {
        $customerId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $branchId = (int)($req->query('branch_id') ?? Session::getBranchId() ?? 0);

        $profileData = $this->customerService->getCustomerProfileData($customerId, $branchId);

        if (!$profileData) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Customer record not found.'], 404);
            }
            Session::flash('error', 'Customer record not found.');
            return Response::redirect('/customers');
        }

        if ($this->expectsJson($req) || $req->query('format') === 'json') {
            return Response::json($profileData);
        }

        $bankAccounts = \WTSBill\Core\Database::select(
            "SELECT id, bank_name, account_name, account_number FROM bank_accounts WHERE company_id = ? AND is_active = 1 ORDER BY is_primary DESC, id ASC",
            [Session::getCompanyId() ?? 1]
        );

        $html = View::renderWithLayout('parties/customer_profile', [
            'title' => $profileData['customer']['name'] . ' — Customer Profile',
            'currentUri' => '/customers/' . $customerId,
            'data' => $profileData,
            'customer' => $profileData['customer'],
            'stats' => $profileData['financial_summary'],
            'invoices' => $profileData['invoices'],
            'outstandingInvoices' => $profileData['outstanding_invoices'],
            'payments' => $profileData['payments'],
            'quotations' => $profileData['quotations'],
            'creditNotes' => $profileData['credit_notes'],
            'ledger' => $profileData['ledger'],
            'activities' => $profileData['activities'],
            'addresses' => $profileData['addresses'] ?? [],
            'bankAccounts' => $bankAccounts,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Add multiple shipping / billing address to customer.
     * Route: POST /customers/{id}/addresses
     */
    public function addAddress(Request $req, mixed $id = null): Response
    {
        Gate::authorize('customers.edit', $req);
        $customerId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('customer_id') ?? $req->param('id') ?? 0)));
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $addrId = $this->customerService->addCustomerAddress($customerId, $payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => 'Customer address added successfully.',
                    'address_id' => $addrId,
                ], 201);
            }

            Session::flash('success', 'Customer address added successfully.');
            return Response::redirect('/customers/' . $customerId);
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect('/customers/' . $customerId);
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Failed to save address: ' . $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to save address: ' . $e->getMessage());
            return Response::redirect('/customers/' . $customerId);
        }
    }

    /**
     * Delete customer address.
     * Route: POST /customers/{id}/addresses/{addressId}/delete
     */
    public function deleteAddress(Request $req, mixed $id = null, mixed $addressId = null): Response
    {
        Gate::authorize('customers.edit', $req);
        $customerId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('customer_id') ?? $req->param('id') ?? 0)));
        $addrId = (int)(is_array($addressId) ? ($addressId['addressId'] ?? $addressId['id'] ?? 0) : ($addressId ?: ($req->post('address_id') ?? $req->param('addressId') ?? 0)));

        $this->customerService->deleteCustomerAddress($customerId, $addrId);

        if ($this->expectsJson($req)) {
            return Response::json([
                'success' => true,
                'message' => 'Customer address removed successfully.',
            ]);
        }

        Session::flash('success', 'Customer address removed successfully.');
        return Response::redirect('/customers/' . $customerId);
    }

    /**
     * Export customer ledger statement as CSV.
     * Route: GET /customers/{id}/statement/csv
     */
    public function statementCsv(Request $req, mixed $id = null): Response
    {
        $customerId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? $req->param('id') ?? 0)));
        $branchId = (int)($req->query('branch_id') ?? Session::getBranchId() ?? 0);

        $profileData = $this->customerService->getCustomerProfileData($customerId, $branchId);
        if (!$profileData) {
            return Response::json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $cust = $profileData['customer'];
        $lines = [];
        $lines[] = '"WTSBill ERP — Customer Account Statement"';
        $lines[] = '"Customer: ' . str_replace('"', '""', $cust['name']) . '","Code: ' . $cust['customer_code'] . '","GSTIN: ' . $cust['gstin'] . '"';
        $lines[] = '"Phone: ' . $cust['phone'] . '","Email: ' . $cust['email'] . '","As of: ' . date('d-M-Y') . '"';
        $lines[] = '"Total Invoiced: ' . $profileData['financial_summary']['total_invoiced_formatted'] . '","Total Paid: ' . $profileData['financial_summary']['total_paid_formatted'] . '","Outstanding: ' . $profileData['financial_summary']['outstanding_balance_formatted'] . '"';
        $lines[] = '""';
        $lines[] = '"Date","Voucher #","Type","Description","Debit (₹)","Credit (₹)","Running Balance (₹)"';

        foreach ($profileData['ledger'] as $entry) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s",%s,%s,"%s"',
                $entry['date'],
                $entry['voucher_number'],
                $entry['type'],
                str_replace('"', '""', $entry['description']),
                $entry['debit'] > 0 ? sprintf('%.2f', $entry['debit']) : '""',
                $entry['credit'] > 0 ? sprintf('%.2f', $entry['credit']) : '""',
                $entry['running_balance_formatted']
            );
        }

        $filename = 'statement_' . $cust['customer_code'] . '_' . date('Ymd_His') . '.csv';
        return new Response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Update customer record.
     */
    public function update(Request $req, mixed $id = null): Response
    {
        $customerId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $customer = $this->customerService->updateCustomer($customerId, $payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Customer '{$customer['name']}' updated successfully.",
                    'customer' => $customer,
                ]);
            }

            Session::flash('success', "Customer '{$customer['name']}' updated successfully.");
            return Response::redirect('/customers');
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect('/customers');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to update customer: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to update customer: ' . $e->getMessage());
            return Response::redirect('/customers');
        }
    }

    /**
     * Delete / deactivate customer record.
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        $customerId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? $req->param('id') ?? 0)));

        $customer = $this->customerService->getCustomer($customerId);
        if (!$customer) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Customer not found.'], 404);
            }
            Session::flash('error', 'Customer not found.');
            return Response::redirect('/customers');
        }

        try {
            $this->customerService->deleteCustomer($customerId);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Customer '{$customer['name']}' deleted successfully.",
                ]);
            }

            Session::flash('success', "Customer '{$customer['name']}' deleted successfully.");
            return Response::redirect('/customers');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to delete customer: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to delete customer: ' . $e->getMessage());
            return Response::redirect('/customers');
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
