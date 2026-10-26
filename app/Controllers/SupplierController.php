<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\SupplierService;

/**
 * Supplier Controller.
 * Handles supplier directory, modal registration, view, and updates.
 */
class SupplierController
{
    protected SupplierService $supplierService;

    public function __construct(?SupplierService $supplierService = null)
    {
        $this->supplierService = $supplierService ?? new SupplierService();
    }

    /**
     * Supplier master directory view.
     */
    public function index(Request $req): Response
    {
        $search = (string)($req->query('search') ?? '');
        $suppliers = $this->supplierService->getSuppliers($search);

        $html = View::renderWithLayout('parties/suppliers', [
            'title' => 'Suppliers & Vendors',
            'currentUri' => '/suppliers',
            'suppliers' => $suppliers,
            'search' => $search,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new supplier.
     */
    public function store(Request $req): Response
    {
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $supplier = $this->supplierService->createSupplier($payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Supplier '{$supplier['name']}' registered successfully.",
                    'supplier' => $supplier,
                ], 201);
            }

            Session::flash('success', "Supplier '{$supplier['name']}' registered successfully.");
            return Response::redirect('/suppliers');
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect('/suppliers');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'An error occurred while saving supplier: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to register supplier: ' . $e->getMessage());
            return Response::redirect('/suppliers');
        }
    }

    /**
     * Show single supplier record (JSON for modal / details).
     */
    public function show(Request $req, mixed $id = null): Response
    {
        $supplierId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));
        $supplier = $this->supplierService->getSupplier($supplierId);

        if (!$supplier) {
            return Response::json(['success' => false, 'message' => 'Supplier not found.'], 404);
        }

        return Response::json(['success' => true, 'supplier' => $supplier]);
    }

    /**
     * Update supplier record.
     */
    public function update(Request $req, mixed $id = null): Response
    {
        $supplierId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $supplier = $this->supplierService->updateSupplier($supplierId, $payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Supplier '{$supplier['name']}' updated successfully.",
                    'supplier' => $supplier,
                ]);
            }

            Session::flash('success', "Supplier '{$supplier['name']}' updated successfully.");
            return Response::redirect('/suppliers');
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect('/suppliers');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to update supplier: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to update supplier: ' . $e->getMessage());
            return Response::redirect('/suppliers');
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
