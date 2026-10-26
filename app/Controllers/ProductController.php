<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Services\ProductService;

/**
 * Product & Inventory Catalog Controller.
 * Handles product listing, modal-based product creation, updates, and AJAX APIs.
 */
class ProductController
{
    protected ProductService $productService;

    public function __construct(?ProductService $productService = null)
    {
        $this->productService = $productService ?? new ProductService();
    }

    /**
     * Display product catalog page with category and unit dropdown options.
     */
    public function index(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $search = (string)($req->query('search') ?? '');

        $products = $this->productService->getCatalog($search);

        // Fetch categories for modal dropdown
        $categories = Database::select(
            "SELECT id, name FROM categories WHERE company_id = ? ORDER BY name ASC",
            [$companyId]
        );

        // Fetch units for modal dropdown
        $units = Database::select(
            "SELECT id, name, short_name FROM units WHERE company_id = ? ORDER BY id ASC",
            [$companyId]
        );

        $html = View::renderWithLayout('inventory/products', [
            'title' => 'Product & Item Catalog',
            'currentUri' => '/products',
            'products' => $products,
            'categories' => $categories,
            'units' => $units,
            'search' => $search,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Handle product creation (supports both AJAX JSON and standard form submissions).
     */
    public function store(Request $req): Response
    {
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $product = $this->productService->createProduct($payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Product '{$product['name']}' registered successfully.",
                    'product' => $product,
                ], 201);
            }

            Session::flash('success', "Product '{$product['name']}' registered successfully.");
            return Response::redirect('/products');
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
            return Response::redirect('/products');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'An error occurred while saving the product: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to register product: ' . $e->getMessage());
            return Response::redirect('/products');
        }
    }

    /**
     * Retrieve single product details (JSON endpoint for View/Edit modal).
     */
    public function show(Request $req, mixed $id = null): Response
    {
        $productId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->query('id') ?? 0)));
        $product = $this->productService->getProduct($productId);

        if (!$product) {
            return Response::json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        return Response::json(['success' => true, 'product' => $product]);
    }

    /**
     * Handle product update.
     */
    public function update(Request $req, mixed $id = null): Response
    {
        $productId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        try {
            $product = $this->productService->updateProduct($productId, $payload);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Product '{$product['name']}' updated successfully.",
                    'product' => $product,
                ]);
            }

            Session::flash('success', "Product '{$product['name']}' updated successfully.");
            return Response::redirect('/products');
        } catch (ValidationException $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            }

            Session::flash('error', $e->getMessage());
            return Response::redirect('/products');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => false,
                    'message' => 'Failed to update product: ' . $e->getMessage(),
                ], 500);
            }

            Session::flash('error', 'Failed to update product: ' . $e->getMessage());
            return Response::redirect('/products');
        }
    }

    /**
     * Handle product deactivation / deletion.
     */
    public function delete(Request $req, mixed $id = null): Response
    {
        $productId = (int)(is_array($id) ? ($id['id'] ?? 0) : ($id ?: ($req->post('id') ?? 0)));
        $deleted = $this->productService->deleteProduct($productId);

        if (!$deleted) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Product not found.'], 404);
            }
            Session::flash('error', 'Product not found.');
            return Response::redirect('/products');
        }

        if ($this->expectsJson($req)) {
            return Response::json(['success' => true, 'message' => 'Product status updated to inactive.']);
        }

        Session::flash('success', 'Product has been deactivated.');
        return Response::redirect('/products');
    }

    /**
     * Helper to determine if request expects JSON.
     */
    protected function expectsJson(Request $req): bool
    {
        $accept = $req->header('accept') ?? '';
        $contentType = $req->header('content-type') ?? '';
        $xRequestedWith = $req->header('x-requested-with') ?? '';

        return str_contains(strtolower($accept), 'application/json')
            || str_contains(strtolower($contentType), 'application/json')
            || strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}
