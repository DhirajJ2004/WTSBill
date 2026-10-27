<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

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
     * Show single customer record (JSON for modal / details).
     */
    public function show(Request $req, mixed $id = null): Response
    {
        $customerId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));
        $customer = $this->customerService->getCustomer($customerId);

        if (!$customer) {
            return Response::json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        return Response::json(['success' => true, 'customer' => $customer]);
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
